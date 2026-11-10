<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/event_helpers.php';

if (!isset($_SESSION['user_id'], $_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
    http_response_code(403);
    exit('Access denied.');
}

$format = strtolower((string) ($_GET['format'] ?? ''));
if (!in_array($format, ['csv', 'pdf'], true)) {
    http_response_code(400);
    exit('Unsupported export format.');
}

$stmt = $conn->prepare(
    'SELECT e.event_id, e.event_name, e.category, e.event_date, e.end_date,
            e.location, e.capacity, e.approval_status, u.fullname AS organizer_name,
            COUNT(DISTINCT ep.participation_id) AS participant_count,
            GROUP_CONCAT(DISTINCT CONCAT_WS(" ", p.firstname, p.lastname)
                         ORDER BY p.lastname SEPARATOR "; ") AS participants
     FROM events e
     LEFT JOIN users u ON u.user_id = e.organizer_id
     LEFT JOIN event_participants ep ON ep.event_id = e.event_id
     LEFT JOIN users p ON p.user_id = ep.user_id
     GROUP BY e.event_id, e.event_name, e.category, e.event_date, e.end_date,
              e.location, e.capacity, e.approval_status, u.fullname
     ORDER BY e.event_date DESC'
);
if (!$stmt || !$stmt->execute()) {
    http_response_code(500);
    exit('Unable to build the event report.');
}

$rows = [];
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $row['schedule_status'] = event_lifecycle_status($row['event_date'], $row['end_date']);
    $rows[] = $row;
}
$stmt->close();

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="event-report-' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    if ($output === false) {
        http_response_code(500);
        exit('Unable to create the CSV report.');
    }
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, ['Event ID', 'Event', 'Category', 'Start', 'End', 'Location',
        'Organizer', 'Capacity', 'Participants', 'Participant names', 'Approval', 'Schedule status']);
    foreach ($rows as $row) {
        $fields = [
            $row['event_id'], $row['event_name'], $row['category'], $row['event_date'],
            $row['end_date'], $row['location'], $row['organizer_name'], $row['capacity'],
            $row['participant_count'], $row['participants'], $row['approval_status'],
            $row['schedule_status'],
        ];
        foreach ($fields as &$field) {
            $field = (string) ($field ?? '');
            if (preg_match('/^[\s]*[=+\-@]/', $field)) {
                $field = "'" . $field;
            }
        }
        unset($field);
        fputcsv($output, $fields);
    }
    fclose($output);
    exit();
}

if (!is_file(__DIR__ . '/../vendor/autoload.php')) {
    http_response_code(500);
    exit('TCPDF is not installed. Run: composer require tecnickcom/tcpdf');
}
require_once __DIR__ . '/../vendor/autoload.php';
if (!class_exists('TCPDF')) {
    http_response_code(500);
    exit('TCPDF is not installed. Run: composer require tecnickcom/tcpdf');
}

$pdf = new TCPDF();
$pdf->SetCreator('Event MS');
$pdf->SetTitle('Event Participation Report');
$pdf->SetMargins(10, 12, 10);
$pdf->AddPage('L');
$pdf->SetFont('helvetica', 'B', 15);
$pdf->Cell(0, 10, 'Event Participation Report', 0, 1, 'L');
$pdf->SetFont('helvetica', '', 8);
$html = '<table border="1" cellpadding="4"><thead><tr style="font-weight:bold;background-color:#eaf0f8">'
    . '<th>Event</th><th>Category</th><th>Schedule</th><th>Location</th><th>Organizer</th>'
    . '<th>Participants</th><th>Names</th><th>Approval</th><th>Event status</th></tr></thead><tbody>';
foreach ($rows as $row) {
    $html .= '<tr>';
    foreach (['event_name', 'category', 'event_date', 'location', 'organizer_name',
        'participant_count', 'participants', 'approval_status', 'schedule_status'] as $column) {
        $html .= '<td>' . htmlspecialchars((string) ($row[$column] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
    }
    $html .= '</tr>';
}
$html .= '</tbody></table>';
$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('event-report-' . date('Y-m-d') . '.pdf', 'D');
exit();

<?php

/**
 * Return the event lifecycle state calculated from its schedule.
 */
function event_lifecycle_status($eventDate, $endDate = null): string
{
    $start = strtotime((string) $eventDate);
    if ($start === false) {
        return 'Unknown';
    }

    $now = time();
    if ($start > $now) {
        return 'Upcoming';
    }

    $end = $endDate ? strtotime((string) $endDate) : false;
    if ($end === false) {
        $end = $start;
    }

    return $end >= $now ? 'Ongoing' : 'Ended';
}

/**
 * Render a lifecycle badge using the project's existing Bootstrap styles.
 */
function event_lifecycle_badge($eventDate, $endDate = null): string
{
    $status = event_lifecycle_status($eventDate, $endDate);
    $classes = [
        'Upcoming' => 'bg-success',
        'Ongoing' => 'bg-primary',
        'Ended' => 'bg-secondary',
        'Unknown' => 'bg-dark',
    ];

    return '<span class="badge ' . $classes[$status] . '">' . $status . '</span>';
}

/**
 * Render the organizer approval badge.
 */
function event_approval_badge($approvalStatus): string
{
    $labels = [
        'pending' => ['Pending', 'bg-warning text-dark'],
        'approved' => ['Approved', 'bg-success'],
        'rejected' => ['Rejected', 'bg-danger'],
    ];
    $status = strtolower((string) $approvalStatus);
    [$label, $class] = $labels[$status] ?? ['Pending', 'bg-warning text-dark'];

    return '<span class="badge ' . $class . '">' . $label . '</span>';
}

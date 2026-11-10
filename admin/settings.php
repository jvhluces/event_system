<?php

session_start();

require_once __DIR__ . '/../config/db.php';

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Admin'
) {
    header("Location: ../auth/login.php");
    exit();
}

require_once __DIR__ . '/../organizer/notification_center.php';
$notificationCenter = organizer_notification_center($conn, (int) $_SESSION['user_id']);

function e($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}


if (empty($_SESSION['settings_csrf_token'])) {
    $_SESSION['settings_csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['settings_csrf_token'];



$createSettingsTable = "
    CREATE TABLE IF NOT EXISTS system_settings (
        setting_id INT AUTO_INCREMENT PRIMARY KEY,
        setting_name VARCHAR(100) NOT NULL UNIQUE,
        setting_value TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
";

$settingsTableCreated = mysqli_query($conn, $createSettingsTable);



$defaultSettings = [
    'allow_organizer_create' => 1,
    'allow_organizer_edit'   => 1,
    'allow_organizer_delete' => 1,

    'allow_audience_join'    => 1,
    'allow_audience_leave'   => 1,
    'allow_audience_review'  => 1,

    'auto_event_status'      => 1
];



$successMessage = '';
$errorMessage = '';



if (!$settingsTableCreated) {

    $errorMessage = "System settings could not be initialized.";

} else {

  
    $insertSetting = mysqli_prepare(
        $conn,
        "INSERT IGNORE INTO system_settings
            (setting_name, setting_value)
         VALUES (?, ?)"
    );

    if ($insertSetting) {

        foreach ($defaultSettings as $settingName => $settingValue) {

            mysqli_stmt_bind_param(
                $insertSetting,
                "si",
                $settingName,
                $settingValue
            );

            mysqli_stmt_execute($insertSetting);
        }

        mysqli_stmt_close($insertSetting);
    }
}


if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'save_settings'
) {


    $submittedToken = $_POST['csrf_token'] ?? '';

    if (
        empty($submittedToken) ||
        !hash_equals($csrfToken, $submittedToken)
    ) {

        $errorMessage = "Invalid request. Please refresh the page and try again.";

    } else {

     
        $settingsToSave = [

            'allow_organizer_create' =>
                isset($_POST['allow_organizer_create']) ? 1 : 0,

            'allow_organizer_edit' =>
                isset($_POST['allow_organizer_edit']) ? 1 : 0,

            'allow_organizer_delete' =>
                isset($_POST['allow_organizer_delete']) ? 1 : 0,

            'allow_audience_join' =>
                isset($_POST['allow_audience_join']) ? 1 : 0,

            'allow_audience_leave' =>
                isset($_POST['allow_audience_leave']) ? 1 : 0,

            'allow_audience_review' =>
                isset($_POST['allow_audience_review']) ? 1 : 0,

            'auto_event_status' =>
                isset($_POST['auto_event_status']) ? 1 : 0
        ];

       
        mysqli_begin_transaction($conn);

        try {

            $updateSetting = mysqli_prepare(
                $conn,
                "UPDATE system_settings
                 SET setting_value = ?
                 WHERE setting_name = ?"
            );

            if (!$updateSetting) {
                throw new Exception("Unable to prepare settings update.");
            }

            foreach ($settingsToSave as $settingName => $settingValue) {

                mysqli_stmt_bind_param(
                    $updateSetting,
                    "is",
                    $settingValue,
                    $settingName
                );

                if (!mysqli_stmt_execute($updateSetting)) {
                    throw new Exception(
                        "Unable to save setting: " . $settingName
                    );
                }
            }

            mysqli_stmt_close($updateSetting);
            mysqli_commit($conn);

            header("Location: settings.php?success=saved");
            exit();

        } catch (Exception $e) {

          
            mysqli_rollback($conn);

            $errorMessage =
                "The settings could not be saved. Please try again.";
        }
    }
}



if (
    isset($_GET['success']) &&
    $_GET['success'] === 'saved'
) {

    $successMessage =
        "System settings have been saved successfully.";
}



$currentSettings = $defaultSettings;

if ($settingsTableCreated) {

    $result = mysqli_query(
        $conn,
        "SELECT setting_name, setting_value
         FROM system_settings"
    );

    if ($result) {

        while ($row = mysqli_fetch_assoc($result)) {

            $settingName = $row['setting_name'];

            $currentSettings[$settingName] =
                (int)$row['setting_value'];
        }
    }
}

$totalEvents = 0;
$totalOrganizers = 0;
$totalAudience = 0;
$totalJoined = 0;


$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM events"
);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $totalEvents = (int)$row['total'];
}



$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'Organizer'"
);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $totalOrganizers = (int)$row['total'];
}



$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM users
     WHERE role = 'Audience'"
);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $totalAudience = (int)$row['total'];
}



$result = mysqli_query(
    $conn,
    "SELECT COUNT(*) AS total
     FROM event_participants"
);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $totalJoined = (int)$row['total'];
}



$upcomingEvents = 0;
$ongoingEvents = 0;
$endedEvents = 0;

$result = mysqli_query(
    $conn,
    "SELECT
        SUM(
            CASE
                WHEN event_date > NOW()
                THEN 1
                ELSE 0
            END
        ) AS upcoming,

        SUM(
            CASE
                WHEN event_date <= NOW() AND end_date >= NOW()
                THEN 1
                ELSE 0
            END
        ) AS ongoing,

        SUM(
            CASE
                WHEN end_date < NOW()
                THEN 1
                ELSE 0
            END
        ) AS ended

     FROM events"
);

if ($result) {

    $row = mysqli_fetch_assoc($result);

    $upcomingEvents =
        (int)($row['upcoming'] ?? 0);

    $ongoingEvents =
        (int)($row['ongoing'] ?? 0);

    $endedEvents =
        (int)($row['ended'] ?? 0);
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>System Settings - Event System</title>
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
    >
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
    >

    <link rel="stylesheet" href="../assets/css/admin/settings.css">

</head>

<body>

<div class="app-wrapper">



    <?php require __DIR__ . '/sidebar.php'; ?>


    <div class="main-area">

   

        <header class="topbar">

            <div>

                <button type="button" class="sidebar-toggle" id="sidebarToggle"
                        aria-label="Open navigation" aria-controls="adminSidebar"
                        aria-expanded="false">
                    <i class="bi bi-list"></i>
                </button>

                <div class="topbar-title">
                    System Settings
                </div>

                <div class="topbar-subtitle">
                    Configure how the event management system operates
                </div>

            </div>


            <div class="admin-badge">

                <i class="bi bi-shield-check"></i>

                <span>
                    Administrator
                </span>

            </div>

        </header>



        <main class="content-area">



            <div class="page-header">

                <h1>
                    System Settings
                </h1>

                <p>
                    Manage permissions and system behavior from one place.
                </p>

            </div>


      

            <?php if ($successMessage !== ''): ?>

                <div
                    class="alert alert-success alert-dismissible fade show"
                    role="alert"
                >

                    <i class="bi bi-check-circle me-2"></i>

                    <?= e($successMessage); ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>


            <?php if ($errorMessage !== ''): ?>

                <div
                    class="alert alert-danger alert-dismissible fade show"
                    role="alert"
                >

                    <i class="bi bi-exclamation-triangle me-2"></i>

                    <?= e($errorMessage); ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                    ></button>

                </div>

            <?php endif; ?>


    
            <div class="row g-3 mb-4">


                <!-- EVENTS -->

                <div class="col-xl-3 col-md-6">

                    <div class="stat-card">

                        <div class="d-flex align-items-center">

                            <div class="stat-icon bg-primary-subtle text-primary me-3">

                                <i class="bi bi-calendar-event"></i>

                            </div>

                            <div>

                                <div class="stat-label">
                                    Total Events
                                </div>

                                <div class="stat-number">
                                    <?= $totalEvents; ?>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>



                <div class="col-xl-3 col-md-6">

                    <div class="stat-card">

                        <div class="d-flex align-items-center">

                            <div class="stat-icon bg-success-subtle text-success me-3">

                                <i class="bi bi-person-badge"></i>

                            </div>

                            <div>

                                <div class="stat-label">
                                    Registered Organizers
                                </div>

                                <div class="stat-number">
                                    <?= $totalOrganizers; ?>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <div class="col-xl-3 col-md-6">

                    <div class="stat-card">

                        <div class="d-flex align-items-center">

                            <div class="stat-icon bg-warning-subtle text-warning me-3">

                                <i class="bi bi-people"></i>

                            </div>

                            <div>

                                <div class="stat-label">
                                    Registered Users
                                </div>

                                <div class="stat-number">
                                    <?= $totalAudience; ?>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>



                <div class="col-xl-3 col-md-6">

                    <div class="stat-card">

                        <div class="d-flex align-items-center">

                            <div class="stat-icon bg-info-subtle text-info me-3">

                                <i class="bi bi-person-check"></i>

                            </div>

                            <div>

                                <div class="stat-label">
                                    Event Participations
                                </div>

                                <div class="stat-number">
                                    <?= $totalJoined; ?>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>



            <form
                method="POST"
                action="settings.php"
            >

                <input
                    type="hidden"
                    name="action"
                    value="save_settings"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($csrfToken); ?>"
                >



                <div class="settings-card">

                    <div class="settings-card-header">

                        <div class="settings-icon bg-success-subtle text-success">

                            <i class="bi bi-person-badge"></i>

                        </div>

                        <div>

                            <h5 class="settings-card-title">
                                Organizer Permissions
                            </h5>

                            <p class="settings-card-description">
                                Control what organizers are allowed to do.
                            </p>

                        </div>

                    </div>


                    <div class="settings-card-body">


                        <div class="setting-row">

                            <div class="setting-info">

                                <div class="setting-title">
                                    Allow Organizer to Create Events
                                </div>

                                <div class="setting-description">
                                    When enabled, organizers can create and submit new events.
                                </div>

                                <div class="setting-status <?= $currentSettings['allow_organizer_create'] ? 'enabled' : 'disabled'; ?>">

                                    <i class="bi <?= $currentSettings['allow_organizer_create'] ? 'bi-check-circle' : 'bi-x-circle'; ?>"></i>

                                    <?= $currentSettings['allow_organizer_create'] ? 'Currently Enabled' : 'Currently Disabled'; ?>

                                </div>

                            </div>


                            <div class="setting-switch">

                                <div class="form-check form-switch">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        role="switch"

                                        name="allow_organizer_create"
                                        id="allow_organizer_create"

                                        <?= $currentSettings['allow_organizer_create'] ? 'checked' : ''; ?>
                                    >

                                </div>

                            </div>

                        </div>



                        <div class="setting-row">

                            <div class="setting-info">

                                <div class="setting-title">
                                    Allow Organizer to Edit Events
                                </div>

                                <div class="setting-description">
                                    When enabled, organizers can edit eligible events according to the event rules.
                                </div>

                                <div class="setting-status <?= $currentSettings['allow_organizer_edit'] ? 'enabled' : 'disabled'; ?>">

                                    <i class="bi <?= $currentSettings['allow_organizer_edit'] ? 'bi-check-circle' : 'bi-x-circle'; ?>"></i>

                                    <?= $currentSettings['allow_organizer_edit'] ? 'Currently Enabled' : 'Currently Disabled'; ?>

                                </div>

                            </div>


                            <div class="setting-switch">

                                <div class="form-check form-switch">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        role="switch"

                                        name="allow_organizer_edit"
                                        id="allow_organizer_edit"

                                        <?= $currentSettings['allow_organizer_edit'] ? 'checked' : ''; ?>
                                    >

                                </div>

                            </div>

                        </div>


            

                        <div class="setting-row">

                            <div class="setting-info">

                                <div class="setting-title">
                                    Allow Organizer to Delete Events
                                </div>

                                <div class="setting-description">
                                    When enabled, organizers can delete eligible upcoming events.
                                </div>

                                <div class="setting-status <?= $currentSettings['allow_organizer_delete'] ? 'enabled' : 'disabled'; ?>">

                                    <i class="bi <?= $currentSettings['allow_organizer_delete'] ? 'bi-check-circle' : 'bi-x-circle'; ?>"></i>

                                    <?= $currentSettings['allow_organizer_delete'] ? 'Currently Enabled' : 'Currently Disabled'; ?>

                                </div>

                            </div>


                            <div class="setting-switch">

                                <div class="form-check form-switch">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        role="switch"

                                        name="allow_organizer_delete"
                                        id="allow_organizer_delete"

                                        <?= $currentSettings['allow_organizer_delete'] ? 'checked' : ''; ?>
                                    >

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <div class="settings-card">

                    <div class="settings-card-header">

                        <div class="settings-icon bg-warning-subtle text-warning">

                            <i class="bi bi-people"></i>

                        </div>

                        <div>

                            <h5 class="settings-card-title">
                                Audience Permissions
                            </h5>

                            <p class="settings-card-description">
                                Control audience participation and review features.
                            </p>

                        </div>

                    </div>


                    <div class="settings-card-body">


                

                        <div class="setting-row">

                            <div class="setting-info">

                                <div class="setting-title">
                                    Allow Audience to Join Events
                                </div>

                                <div class="setting-description">
                                    When enabled, registered audience users can join available events.
                                </div>

                                <div class="setting-status <?= $currentSettings['allow_audience_join'] ? 'enabled' : 'disabled'; ?>">

                                    <i class="bi <?= $currentSettings['allow_audience_join'] ? 'bi-check-circle' : 'bi-x-circle'; ?>"></i>

                                    <?= $currentSettings['allow_audience_join'] ? 'Currently Enabled' : 'Currently Disabled'; ?>

                                </div>

                            </div>


                            <div class="setting-switch">

                                <div class="form-check form-switch">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        role="switch"

                                        name="allow_audience_join"
                                        id="allow_audience_join"

                                        <?= $currentSettings['allow_audience_join'] ? 'checked' : ''; ?>
                                    >

                                </div>

                            </div>

                        </div>


                  

                        <div class="setting-row">

                            <div class="setting-info">

                                <div class="setting-title">
                                    Allow Audience to Leave Events
                                </div>

                                <div class="setting-description">
                                    When enabled, audience users can leave events they previously joined.
                                </div>

                                <div class="setting-status <?= $currentSettings['allow_audience_leave'] ? 'enabled' : 'disabled'; ?>">

                                    <i class="bi <?= $currentSettings['allow_audience_leave'] ? 'bi-check-circle' : 'bi-x-circle'; ?>"></i>

                                    <?= $currentSettings['allow_audience_leave'] ? 'Currently Enabled' : 'Currently Disabled'; ?>

                                </div>

                            </div>


                            <div class="setting-switch">

                                <div class="form-check form-switch">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        role="switch"

                                        name="allow_audience_leave"
                                        id="allow_audience_leave"

                                        <?= $currentSettings['allow_audience_leave'] ? 'checked' : ''; ?>
                                    >

                                </div>

                            </div>

                        </div>



                        <div class="setting-row">

                            <div class="setting-info">

                                <div class="setting-title">
                                    Allow Audience to Review Ended Events
                                </div>

                                <div class="setting-description">
                                    When enabled, users who joined an event can submit a review after the event ends.
                                </div>

                                <div class="setting-status <?= $currentSettings['allow_audience_review'] ? 'enabled' : 'disabled'; ?>">

                                    <i class="bi <?= $currentSettings['allow_audience_review'] ? 'bi-check-circle' : 'bi-x-circle'; ?>"></i>

                                    <?= $currentSettings['allow_audience_review'] ? 'Currently Enabled' : 'Currently Disabled'; ?>

                                </div>

                            </div>


                            <div class="setting-switch">

                                <div class="form-check form-switch">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        role="switch"

                                        name="allow_audience_review"
                                        id="allow_audience_review"

                                        <?= $currentSettings['allow_audience_review'] ? 'checked' : ''; ?>
                                    >

                                </div>

                            </div>

                        </div>

                    </div>

                </div>


                <div class="settings-card">

                    <div class="settings-card-header">

                        <div class="settings-icon bg-primary-subtle text-primary">

                            <i class="bi bi-sliders"></i>

                        </div>

                        <div>

                            <h5 class="settings-card-title">
                                System Behavior
                            </h5>

                            <p class="settings-card-description">
                                Configure general event workflow behavior.
                            </p>

                        </div>

                    </div>


                    <div class="settings-card-body">


                        <div class="setting-row">

                            <div class="setting-info">

                                <div class="setting-title">
                                    Automatic Event Status
                                </div>

                                <div class="setting-description">
                                    Allows the system's event pages and processes to use automatic Upcoming, Ongoing, and Ended status handling.
                                </div>

                                <div class="setting-status <?= $currentSettings['auto_event_status'] ? 'enabled' : 'disabled'; ?>">

                                    <i class="bi <?= $currentSettings['auto_event_status'] ? 'bi-check-circle' : 'bi-x-circle'; ?>"></i>

                                    <?= $currentSettings['auto_event_status'] ? 'Currently Enabled' : 'Currently Disabled'; ?>

                                </div>

                            </div>


                            <div class="setting-switch">

                                <div class="form-check form-switch">

                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        role="switch"

                                        name="auto_event_status"
                                        id="auto_event_status"

                                        <?= $currentSettings['auto_event_status'] ? 'checked' : ''; ?>
                                    >

                                </div>

                            </div>

                      

                        <div class="settings-info-box mt-4">

                            <i class="bi bi-info-circle"></i>

                            <div>

                                <strong>Important:</strong>

                                These settings are saved in the system database.

                                The corresponding Organizer and Audience action files must read these settings before allowing an action.

                                For example, when
                                <strong>Allow Audience to Join Events</strong>
                                is OFF, the Join Event action must reject the request even if a user attempts to access the action directly.

                            </div>

                        </div>


                  

                        <div class="settings-save-area">

                            <button
                                type="submit"
                                class="btn btn-primary save-button"
                            >

                                <i class="bi bi-check-lg me-2"></i>

                                Save Changes

                            </button>

                        </div>

                    </div>

                </div>

            </form>



            <div class="settings-card">

                <div class="settings-card-header">

                    <div class="settings-icon bg-info-subtle text-info">

                        <i class="bi bi-activity"></i>

                    </div>

                    <div>

                        <h5 class="settings-card-title">
                            Current Event Status
                        </h5>

                        <p class="settings-card-description">
                            Current event distribution. This section is informational only.
                        </p>

                    </div>

                </div>


                <div class="settings-card-body">

                    <div class="row g-3">

                        <div class="col-md-4">

                            <div class="status-item">

                                <div class="status-number">
                                    <?= $upcomingEvents; ?>
                                </div>

                                <div class="status-name">
                                    Upcoming Events
                                </div>

                            </div>

                        </div>



                        <div class="col-md-4">

                            <div class="status-item">

                                <div class="status-number">
                                    <?= $ongoingEvents; ?>
                                </div>

                                <div class="status-name">
                                    Ongoing Events
                                </div>

                            </div>

                        </div>



                        <div class="col-md-4">

                            <div class="status-item">

                                <div class="status-number">
                                    <?= $endedEvents; ?>
                                </div>

                                <div class="status-name">
                                    Ended Events
                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            </div>


        </main>

    </div>

</div>




<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>

</body>

</html>
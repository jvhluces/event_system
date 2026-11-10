<?php

session_start();

require_once '../config/db.php';




if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'Audience'
) {
    header("Location: ../auth/login_user.php");
    exit();
}


$user_id = intval($_SESSION['user_id']);
require_once __DIR__ . '/../organizer/notification_center.php';
$notificationCenter = organizer_notification_center($conn, $user_id);




$userQuery = mysqli_query(
    $conn,
    "SELECT firstname, middlename, lastname, fullname, profile_pic
     FROM users
     WHERE user_id = $user_id
     LIMIT 1"
);

$userData = mysqli_fetch_assoc($userQuery);

$userName = trim((string) ($userData['fullname'] ?? ''));
if ($userName === '') {
    $userName = trim(implode(' ', array_filter([
        $userData['firstname'] ?? '',
        $userData['middlename'] ?? '',
        $userData['lastname'] ?? ''
    ], static fn($part) => trim((string) $part) !== '')));
}
if ($userName === '') {
    $userName = 'User';
}

$profilePic = trim(
    $userData['profile_pic'] ?? ''
);




function e($value)
{
    return htmlspecialchars(
        $value ?? '',
        ENT_QUOTES,
        'UTF-8'
    );
}




$avatar = '';


if (!empty($profilePic)) {

    /* FULL URL */

    if (
        filter_var(
            $profilePic,
            FILTER_VALIDATE_URL
        )
    ) {

        $avatar = $profilePic;

    } else {

        $cleanPic = ltrim(
            $profilePic,
            '/'
        );


        $possiblePaths = [

            '../' . $cleanPic,

            '../uploads/profiles/' .
            basename($cleanPic),

            '../uploads/profile/' .
            basename($cleanPic),

            '../uploads/' .
            basename($cleanPic),

            '../assets/uploads/profiles/' .
            basename($cleanPic),

            '../assets/images/' .
            basename($cleanPic)

        ];


        foreach (
            $possiblePaths
            as $path
        ) {

            if (
                file_exists($path) &&
                is_file($path)
            ) {

                $avatar =
                    $path .
                    '?v=' .
                    filemtime($path);

                break;
            }
        }
    }
}



if (empty($avatar)) {

    $avatar =
        'https://ui-avatars.com/api/?name=' .
        urlencode($userName) .
        '&background=0d6efd&color=fff&bold=true';

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

    <title>
        Help & Support -Event System
    </title>



    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
    >
    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
    >

    
    
    <link rel="stylesheet" href="../assets/css/users/help_user.css">

</head>


<body>

<nav class="top-navbar">
    <button
        type="button"
        class="menu-btn"
        id="sidebarToggle"
        aria-label="Open menu"
    >
        <i class="bi bi-list"></i>
    </button>
    <div class="top-navbar-title">
        <i class="bi bi-question-circle"></i>
        <span>Help &amp; Support</span>
    </div>
    <div class="topbar-spacer"></div>
    <?php require __DIR__ . '/../organizer/notification_menu.php'; ?>
    <div class="topbar-user">
        <div class="topbar-avatar"><?= e(strtoupper(substr($userName, 0, 1))); ?></div>
        <span><?= e($userName); ?></span>
        <i class="bi bi-chevron-down"></i>
    </div>
</nav>


<!-- ==========================================================
     SIDEBAR
========================================================== -->

<aside
    id="sidebar"
    class="sidebar"
>

    <div class="audience-sidebar-header">
        <a href="user.php" class="audience-sidebar-brand">
            <img src="../assets/images/organizer-sidebar-logo.png" alt="Event MS">
        </a>
        <button type="button" class="close-sidebar" onclick="document.getElementById('sidebarToggle').click()" aria-label="Close menu">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    <!-- PROFILE -->

    <div class="sidebar-profile audience-sidebar-profile">

        <div class="audience-sidebar-avatar">
            <img
                src="<?= e($avatar); ?>"
                alt="Profile"
                onerror="
                    this.onerror=null;
                    this.src='https://ui-avatars.com/api/?name=<?= urlencode($userName); ?>&background=0d6efd&color=fff&bold=true';
                "
            >
        </div>

        <div class="audience-sidebar-info">
            <div class="sidebar-user-name audience-sidebar-name">
                <?= e($userName); ?>
            </div>
            <div class="sidebar-role audience-sidebar-role">
                Audience
            </div>
        </div>

    </div>


    <!-- MENU -->

    <nav class="sidebar-menu" aria-label="Audience navigation">


        <div class="sidebar-section-title">

            Main

        </div>


        <!-- HOME -->

        <a
            href="user.php?status=All&amp;category=All"
            class="sidebar-link"
        >

            <i class="bi bi-house-door"></i>

            <span>
                Home
            </span>

        </a>




        <!-- JOINED -->

        <a
            href="user.php?status=Joined"
            class="sidebar-link"
        >

            <i class="bi bi-person-check"></i>

            <span>
                My Registrations
            </span>

        </a>


        <!-- REVIEWS -->

        <a
            href="user.php?status=Ended&amp;category=All"
            class="sidebar-link"
        >

            <i class="bi bi-star"></i>

            <span>
                My Reviews
            </span>

        </a>


        <div class="sidebar-divider"></div>


        <div class="sidebar-section-title">

            Account

        </div>


        <!-- PROFILE -->

        <a
            href="user_profile.php"
            class="sidebar-link"
        >

            <i class="bi bi-person-circle"></i>

            <span>
                My Profile
            </span>

        </a>


        <!-- SETTINGS -->

        <a
            href="user_settings.php"
            class="sidebar-link"
        >

            <i class="bi bi-gear"></i>

            <span>
                Settings
            </span>

        </a>


        <!-- HELP -->

        <a
            href="help_user.php"
            class="sidebar-link active"
        >

            <i class="bi bi-question-circle"></i>

            <span>
                Help & Support
            </span>

        </a>


        <div class="sidebar-divider"></div>


        <!-- LOGOUT -->

        <a
            href="../auth/logout_user.php"
            class="sidebar-link logout-link"
            onclick="
                return confirm(
                    'Are you sure you want to logout?'
                );
            "
        >

            <i class="bi bi-box-arrow-right"></i>

            <span>
                Logout
            </span>

        </a>

    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-footer-line"></div>
        <p>Better Events.<br>Stronger Communities.</p>
    </div>

</aside>


<!-- ==========================================================
     MOBILE OVERLAY
========================================================== -->

<div
    id="sidebarOverlay"
    class="sidebar-overlay"
></div>


<!-- ==========================================================
     HERO
========================================================== -->

<main class="main-content">
<section class="hero">

    <div class="container">

        <h1>

            How can we help you,
            <?= e($userName); ?>?

        </h1>


        <p class="lead mb-0">

            Find answers, learn how events work,
            and get help using the Event System.

        </p>

    </div>

</section>



<section class="content-area dashboard-section container py-5">


    

    <div class="help-search-card">

        <div class="help-search-title">

            <i class="bi bi-search text-primary"></i>

            Search Help Center

        </div>


        <div class="help-search-subtitle">

            Search for answers about events,
            joining, reviews, your account, and more.

        </div>


        <div class="help-search-box">

            <i class="bi bi-search"></i>


            <input
                type="text"
                id="helpSearch"
                placeholder="What do you need help with?"
                autocomplete="off"
            >


            <button
                type="button"
                id="clearHelpSearch"
            >

                <i class="bi bi-x-circle-fill"></i>

            </button>

        </div>


        <div
            id="searchResultInfo"
            class="search-result-info"
        ></div>

    </div>



    <section
        class="help-section"
        id="quickHelpSection"
    >

        <div class="section-heading">

            <h2>

                Quick Help

            </h2>

            <p>

                Choose a topic to quickly find what you need.

            </p>

        </div>


        <div class="quick-help-grid">


            <!-- EVENTS -->

            <div
                class="quick-help-card"
                data-target="eventsHelp"
            >

                <div class="quick-help-icon">

                    <i class="bi bi-calendar-event"></i>

                </div>


                <h5>

                    Finding Events

                </h5>


                <p>

                    Learn how to search, filter,
                    and view available events.

                </p>

            </div>


            <!-- JOIN -->

            <div
                class="quick-help-card"
                data-target="joiningHelp"
            >

                <div class="quick-help-icon">

                    <i class="bi bi-person-plus"></i>

                </div>


                <h5>

                    Joining Events

                </h5>


                <p>

                    Learn how to join or leave
                    an upcoming event.

                </p>

            </div>


            <!-- REVIEWS -->

            <div
                class="quick-help-card"
                data-target="reviewHelp"
            >

                <div class="quick-help-icon">

                    <i class="bi bi-star"></i>

                </div>


                <h5>

                    Reviews

                </h5>


                <p>

                    Learn when and how you can
                    submit an event review.

                </p>

            </div>


            <!-- ACCOUNT -->

            <div
                class="quick-help-card"
                data-target="accountHelp"
            >

                <div class="quick-help-icon">

                    <i class="bi bi-person-circle"></i>

                </div>


                <h5>

                    Account

                </h5>


                <p>

                    Manage your profile,
                    settings, and account information.

                </p>

            </div>

        </div>

    </section>


    <!-- ======================================================
         GETTING STARTED
    ======================================================= -->

    <section
        class="help-section searchable-section"
        data-search-content="
            getting started home dashboard events search
            categories upcoming ongoing joined ended
        "
    >

        <div class="section-heading">

            <h2>

                <i class="bi bi-compass text-primary"></i>

                Getting Started

            </h2>


            <p>

                A quick guide for using the
                QC Event System.

            </p>

        </div>


        <div class="guide-card">


            <!-- STEP 1 -->

            <div class="guide-step">

                <div class="step-number">

                    1

                </div>


                <div>

                    <h5>

                        Open your Home Dashboard

                    </h5>


                    <p>

                        Your Home page shows available events
                        that you can browse and join.

                    </p>

                </div>

            </div>


            <!-- STEP 2 -->

            <div class="guide-step">

                <div class="step-number">

                    2

                </div>


                <div>

                    <h5>

                        Search or Filter Events

                    </h5>


                    <p>

                        Use the search box to find an event
                        by its name, category, location,
                        or organizer. You can also filter
                        events by category.

                    </p>

                </div>

            </div>


            <!-- STEP 3 -->

            <div class="guide-step">

                <div class="step-number">

                    3

                </div>


                <div>

                    <h5>

                        Check the Event Status

                    </h5>


                    <p>

                        Events are organized into Upcoming,
                        Ongoing, Joined, and Ended sections
                        so you can easily understand their
                        current status.

                    </p>

                </div>

            </div>


            <!-- STEP 4 -->

            <div class="guide-step">

                <div class="step-number">

                    4

                </div>


                <div>

                    <h5>

                        View Event Details

                    </h5>


                    <p>

                        Open an event to see important
                        information such as the event name,
                        organizer, date, time, location,
                        capacity, and other available details.

                    </p>

                </div>

            </div>


            <!-- STEP 5 -->

            <div class="guide-step">

                <div class="step-number">

                    5

                </div>


                <div>

                    <h5>

                        Join an Upcoming Event

                    </h5>


                    <p>

                        If an upcoming event has available
                        slots, use the Join Event button
                        to register your participation.

                    </p>

                </div>

            </div>


            <!-- STEP 6 -->

            <div class="guide-step">

                <div class="step-number">

                    6

                </div>


                <div>

                    <h5>

                        Review Events You Attended

                    </h5>


                    <p>

                        After an event has ended, participants
                        who joined the event can submit a
                        rating and feedback.

                    </p>

                </div>

            </div>

        </div>

    </section>


    <!-- ======================================================
         FINDING EVENTS
    ======================================================= -->

    <section
        id="eventsHelp"
        class="help-section searchable-section"
        data-search-content="
            events finding event search filter category
            wedding birthday meeting seminar workshop
            community event location organizer
        "
    >

        <div class="section-heading">

            <h2>

                <i class="bi bi-calendar-event text-primary"></i>

                Finding Events

            </h2>


            <p>

                Everything you need to know about
                discovering events.

            </p>

        </div>


        <div class="guide-card">


            <div class="guide-step">

                <div class="step-number">

                    <i class="bi bi-search"></i>

                </div>


                <div>

                    <h5>

                        Search for an Event

                    </h5>


                    <p>

                        On your Home page, use the Search Events
                        box. You can enter an event name or
                        keywords related to the event.

                    </p>

                </div>

            </div>


            <div class="guide-step">

                <div class="step-number">

                    <i class="bi bi-funnel"></i>

                </div>


                <div>

                    <h5>

                        Filter by Category

                    </h5>


                    <p>

                        Use the category filters to narrow
                        events by Wedding, Birthday, Meeting,
                        Seminar, Workshop, or Community Event.

                    </p>

                </div>

            </div>


            <div class="guide-step">

                <div class="step-number">

                    <i class="bi bi-eye"></i>

                </div>


                <div>

                    <h5>

                        View Event Details

                    </h5>


                    <p>

                        Select View Details on an event card
                        to inspect the event information before
                        deciding whether to join.

                    </p>

                </div>

            </div>

        </div>

    </section>


    <!-- ======================================================
         EVENT STATUS
    ======================================================= -->

    <section
        class="help-section searchable-section"
        data-search-content="
            upcoming ongoing joined ended event status
            happening now event full
        "
    >

        <div class="section-heading">

            <h2>

                <i class="bi bi-activity text-primary"></i>

                Understanding Event Status

            </h2>


            <p>

                Here's what each event status means.

            </p>

        </div>


        <div class="status-grid">


            <!-- UPCOMING -->

            <div class="status-card">

                <div
                    class="
                        status-icon
                        status-upcoming
                    "
                >

                    <i class="bi bi-clock"></i>

                </div>


                <h5>

                    Upcoming

                </h5>


                <p>

                    The event has not started yet.
                    If there are available slots,
                    you can join the event.

                </p>

            </div>


            <!-- ONGOING -->

            <div class="status-card">

                <div
                    class="
                        status-icon
                        status-ongoing
                    "
                >

                    <i class="bi bi-play-circle"></i>

                </div>


                <h5>

                    Ongoing

                </h5>


                <p>

                    The event is currently happening.
                    The system shows it as ongoing,
                    and new event participation actions
                    are not treated as upcoming registration.

                </p>

            </div>


            <!-- ENDED -->

            <div class="status-card">

                <div
                    class="
                        status-icon
                        status-ended
                    "
                >

                    <i class="bi bi-check-circle"></i>

                </div>


                <h5>

                    Ended

                </h5>


                <p>

                    The event has already finished.
                    If you joined the event, you may be
                    able to leave a review.

                </p>

            </div>

        </div>

    </section>


    <!-- ======================================================
         JOINING EVENTS
    ======================================================= -->

    <section
        id="joiningHelp"
        class="help-section searchable-section"
        data-search-content="
            join joining event leave event full capacity
            participant upcoming already joined
        "
    >

        <div class="section-heading">

            <h2>

                <i class="bi bi-person-plus text-primary"></i>

                Joining & Leaving Events

            </h2>


            <p>

                Learn how event participation works.

            </p>

        </div>


        <div class="troubleshooting-grid">


            <!-- JOIN -->

            <div class="issue-card">

                <h5>

                    <i class="bi bi-person-plus text-primary"></i>

                    How do I join an event?

                </h5>


                <p>

                    To join an event:

                </p>


                <ul>

                    <li>
                        Find an Upcoming event.
                    </li>

                    <li>
                        Make sure the event is not full.
                    </li>

                    <li>
                        Click Join Event.
                    </li>

                    <li>
                        The event will appear under Joined.
                    </li>

                </ul>

            </div>


            <!-- LEAVE -->

            <div class="issue-card">

                <h5>

                    <i class="bi bi-box-arrow-left text-danger"></i>

                    How do I leave an event?

                </h5>


                <p>

                    If you already joined an Upcoming event,
                    the event card will provide a Leave option.

                </p>


                <ul>

                    <li>
                        Open your Joined events.
                    </li>

                    <li>
                        Find the event.
                    </li>

                    <li>
                        Select Leave.
                    </li>

                    <li>
                        Confirm the action.
                    </li>

                </ul>

            </div>


            <!-- FULL -->

            <div class="issue-card">

                <h5>

                    <i class="bi bi-people text-primary"></i>

                    What if the event is full?

                </h5>


                <p>

                    When the number of participants reaches
                    the event capacity, the system displays
                    Event Full.

                </p>


                <ul>

                    <li>
                        You cannot join while it is full.
                    </li>

                    <li>
                        Check the event again later if
                        participation availability changes.
                    </li>

                </ul>

            </div>


            <!-- ALREADY JOINED -->

            <div class="issue-card">

                <h5>

                    <i class="bi bi-person-check text-success"></i>

                    I already joined an event.

                </h5>


                <p>

                    Once you have joined an event, the system
                    recognizes your participation.

                </p>


                <ul>

                    <li>
                        Check it under Joined Events.
                    </li>

                    <li>
                        You should not need to join it again.
                    </li>

                    <li>
                        For an Upcoming event, you can use
                        Leave if you no longer want to participate.
                    </li>

                </ul>

            </div>

        </div>

    </section>


    <!-- ======================================================
         REVIEWS
    ======================================================= -->

    <section
        id="reviewHelp"
        class="help-section searchable-section"
        data-search-content="
            review reviews rating feedback stars leave review
            ended event participant
        "
    >

        <div class="section-heading">

            <h2>

                <i class="bi bi-star text-primary"></i>

                Event Reviews

            </h2>


            <p>

                Share your experience after participating
                in an event.

            </p>

        </div>


        <div class="guide-card">


            <div class="guide-step">

                <div class="step-number">

                    <i class="bi bi-check-circle"></i>

                </div>


                <div>

                    <h5>

                        When can I leave a review?

                    </h5>


                    <p>

                        Reviews are available after an event
                        has ended. You must also have joined
                        the event.

                    </p>

                </div>

            </div>


            <div class="guide-step">

                <div class="step-number">

                    <i class="bi bi-star"></i>

                </div>


                <div>

                    <h5>

                        How do I review an event?

                    </h5>


                    <p>

                        Go to your Ended events and find an
                        event that you joined. If you have not
                        reviewed it yet, select Leave Review.

                    </p>

                </div>

            </div>


            <div class="guide-step">

                <div class="step-number">

                    <i class="bi bi-chat-left-text"></i>

                </div>


                <div>

                    <h5>

                        What should I write?

                    </h5>


                    <p>

                        You can provide an honest rating and
                        useful feedback about your experience,
                        such as the organization of the event,
                        overall experience, and areas that may
                        be improved.

                    </p>

                </div>

            </div>


            <div class="guide-step">

                <div class="step-number">

                    <i class="bi bi-shield-check"></i>

                </div>


                <div>

                    <h5>

                        Why can't I review an event?

                    </h5>


                    <p>

                        Reviews are only available for eligible
                        ended events that you joined. If the
                        event has not ended or you did not
                        participate, the review option will not
                        be available.

                    </p>

                </div>

            </div>

        </div>

    </section>


    <!-- ======================================================
         FAQ
    ======================================================= -->

    <section
        class="help-section searchable-section"
        data-search-content="
            frequently asked questions faq help event
            account password profile join leave review
        "
    >

        <div class="section-heading">

            <h2>

                <i class="bi bi-patch-question text-primary"></i>

                Frequently Asked Questions

            </h2>


            <p>

                Common questions from event participants.

            </p>

        </div>


        <div class="faq-wrapper">

            <div
                class="accordion"
                id="faqAccordion"
            >


                <!-- FAQ 1 -->

                <div class="accordion-item">

                    <h2
                        class="accordion-header"
                        id="faqOneHeading"
                    >

                        <button
                            class="accordion-button collapsed"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#faqOne"
                        >

                            How do I find an event?

                        </button>

                    </h2>


                    <div
                        id="faqOne"
                        class="accordion-collapse collapse"
                        data-bs-parent="#faqAccordion"
                    >

                        <div class="accordion-body">

                            Go to your Home dashboard.
                            You can browse Upcoming events,
                            use the search field, or filter
                            events by category.

                        </div>

                    </div>

                </div>


                <!-- FAQ 2 -->

                <div class="accordion-item">

                    <h2
                        class="accordion-header"
                        id="faqTwoHeading"
                    >

                        <button
                            class="accordion-button collapsed"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#faqTwo"
                        >

                            Why can't I join an event?

                        </button>

                    </h2>


                    <div
                        id="faqTwo"
                        class="accordion-collapse collapse"
                        data-bs-parent="#faqAccordion"
                    >

                        <div class="accordion-body">

                            An event can only be joined while
                            it is Upcoming and has available
                            participant capacity. You also
                            cannot join the same event more
                            than once.

                        </div>

                    </div>

                </div>


                <!-- FAQ 3 -->

                <div class="accordion-item">

                    <h2
                        class="accordion-header"
                        id="faqThreeHeading"
                    >

                        <button
                            class="accordion-button collapsed"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#faqThree"
                        >

                            What does "Event Full" mean?

                        </button>

                    </h2>


                    <div
                        id="faqThree"
                        class="accordion-collapse collapse"
                        data-bs-parent="#faqAccordion"
                    >

                        <div class="accordion-body">

                            Event Full means that the number
                            of participants has reached the
                            capacity configured for that event.
                            The Join Event button will not be
                            available while the event is full.

                        </div>

                    </div>

                </div>


                <!-- FAQ 4 -->

                <div class="accordion-item">

                    <h2
                        class="accordion-header"
                        id="faqFourHeading"
                    >

                        <button
                            class="accordion-button collapsed"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#faqFour"
                        >

                            Can I leave an event after joining?

                        </button>

                    </h2>


                    <div
                        id="faqFour"
                        class="accordion-collapse collapse"
                        data-bs-parent="#faqAccordion"
                    >

                        <div class="accordion-body">

                            You can leave an event while it is
                            still Upcoming, using the Leave
                            option available on your event card.

                        </div>

                    </div>

                </div>


                <!-- FAQ 5 -->

                <div class="accordion-item">

                    <h2
                        class="accordion-header"
                        id="faqFiveHeading"
                    >

                        <button
                            class="accordion-button collapsed"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#faqFive"
                        >

                            Why can't I leave a review?

                        </button>

                    </h2>


                    <div
                        id="faqFive"
                        class="accordion-collapse collapse"
                        data-bs-parent="#faqAccordion"
                    >

                        <div class="accordion-body">

                            You can leave a review only after
                            the event has ended and only if you
                            joined the event. If you have already
                            submitted a review, the event will
                            be marked as Reviewed.

                        </div>

                    </div>

                </div>


                <!-- FAQ 6 -->

                <div class="accordion-item">

                    <h2
                        class="accordion-header"
                        id="faqSixHeading"
                    >

                        <button
                            class="accordion-button collapsed"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#faqSix"
                        >

                            Where can I see the events I joined?

                        </button>

                    </h2>


                    <div
                        id="faqSix"
                        class="accordion-collapse collapse"
                        data-bs-parent="#faqAccordion"
                    >

                        <div class="accordion-body">

                            Open the Joined Events section
                            from your sidebar or select the
                            Joined tab on the Home dashboard.

                        </div>

                    </div>

                </div>


                <!-- FAQ 7 -->

                <div class="accordion-item">

                    <h2
                        class="accordion-header"
                        id="faqSevenHeading"
                    >

                        <button
                            class="accordion-button collapsed"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#faqSeven"
                        >

                            How can I update my profile?

                        </button>

                    </h2>


                    <div
                        id="faqSeven"
                        class="accordion-collapse collapse"
                        data-bs-parent="#faqAccordion"
                    >

                        <div class="accordion-body">

                            Open My Profile from the sidebar.
                            From there, you can manage the
                            profile information made available
                            by the system.

                        </div>

                    </div>

                </div>


                <!-- FAQ 8 -->

                <div class="accordion-item">

                    <h2
                        class="accordion-header"
                        id="faqEightHeading"
                    >

                        <button
                            class="accordion-button collapsed"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#faqEight"
                        >

                            What should I do if my profile picture
                            does not appear?

                        </button>

                    </h2>


                    <div
                        id="faqEight"
                        class="accordion-collapse collapse"
                        data-bs-parent="#faqAccordion"
                    >

                        <div class="accordion-body">

                            First refresh the page. If the
                            image still does not appear, open
                            My Profile and check whether your
                            profile picture was successfully
                            saved. The system also provides a
                            default avatar when no profile image
                            is available.

                        </div>

                    </div>

                </div>


                <!-- FAQ 9 -->

                <div class="accordion-item">

                    <h2
                        class="accordion-header"
                        id="faqNineHeading"
                    >

                        <button
                            class="accordion-button collapsed"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#faqNine"
                        >

                            Why am I redirected to the login page?

                        </button>

                    </h2>


                    <div
                        id="faqNine"
                        class="accordion-collapse collapse"
                        data-bs-parent="#faqAccordion"
                    >

                        <div class="accordion-body">

                            Your session may have expired or
                            you may have logged out. Log in again
                            using your Audience account to continue
                            using the system.

                        </div>

                    </div>

                </div>


                <!-- FAQ 10 -->

                <div class="accordion-item">

                    <h2
                        class="accordion-header"
                        id="faqTenHeading"
                    >

                        <button
                            class="accordion-button collapsed"
                            type="button"
                            data-bs-toggle="collapse"
                            data-bs-target="#faqTen"
                        >

                            Is there a live video feature?

                        </button>

                    </h2>


                    <div
                        id="faqTen"
                        class="accordion-collapse collapse"
                        data-bs-parent="#faqAccordion"
                    >

                        <div class="accordion-body">

                            No. The current QC Event System is
                            focused on event discovery,
                            participation, event information,
                            and post-event reviews.

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </section>


    <!-- ======================================================
         TROUBLESHOOTING
    ======================================================= -->

    <section
        class="help-section searchable-section"
        data-search-content="
            troubleshooting problem error issue not working
            page button event join login profile
        "
    >

        <div class="section-heading">

            <h2>

                <i class="bi bi-tools text-primary"></i>

                Troubleshooting

            </h2>


            <p>

                Try these steps when something does not
                work as expected.

            </p>

        </div>


        <div class="troubleshooting-grid">


            <!-- PAGE -->

            <div class="issue-card">

                <h5>

                    <i class="bi bi-browser-chrome text-primary"></i>

                    A page is not loading correctly

                </h5>


                <ul>

                    <li>
                        Refresh the page.
                    </li>

                    <li>
                        Check your internet connection.
                    </li>

                    <li>
                        Try opening the page again.
                    </li>

                    <li>
                        If the problem continues, log out
                        and log in again.
                    </li>

                </ul>

            </div>


            <!-- JOIN -->

            <div class="issue-card">

                <h5>

                    <i class="bi bi-person-plus text-primary"></i>

                    The Join Event button is unavailable

                </h5>


                <ul>

                    <li>
                        Check if the event is Upcoming.
                    </li>

                    <li>
                        Check whether the event is already full.
                    </li>

                    <li>
                        Check whether you have already joined it.
                    </li>

                </ul>

            </div>


            <!-- REVIEW -->

            <div class="issue-card">

                <h5>

                    <i class="bi bi-star text-primary"></i>

                    The Leave Review button is unavailable

                </h5>


                <ul>

                    <li>
                        The event must be Ended.
                    </li>

                    <li>
                        You must have joined the event.
                    </li>

                    <li>
                        You cannot submit another review
                        after reviewing the event.
                    </li>

                </ul>

            </div>


            <!-- LOGIN -->

            <div class="issue-card">

                <h5>

                    <i class="bi bi-box-arrow-in-right text-primary"></i>

                    I was sent back to Login

                </h5>


                <ul>

                    <li>
                        Your session may have expired.
                    </li>

                    <li>
                        Log in again using your Audience account.
                    </li>

                    <li>
                        If the problem repeatedly occurs,
                        contact the system administrator.
                    </li>

                </ul>

            </div>

        </div>

    </section>


    <!-- ======================================================
         ACCOUNT HELP
    ======================================================= -->

    <section
        id="accountHelp"
        class="help-section searchable-section"
        data-search-content="
            account profile settings username password
            personal information profile picture logout
        "
    >

        <div class="section-heading">

            <h2>

                <i class="bi bi-person-circle text-primary"></i>

                Account & Profile Help

            </h2>


            <p>

                Manage your account information and
                personal settings.

            </p>

        </div>


        <div class="account-help">


            <!-- PROFILE -->

            <div class="account-card">

                <i class="bi bi-person-circle"></i>


                <h5>

                    My Profile

                </h5>


                <p>

                    View and manage your available profile
                    information and profile picture.

                </p>


                <a
                    href="user_profile.php"
                    class="btn btn-outline-primary btn-sm"
                >

                    <i class="bi bi-arrow-right"></i>

                    Open My Profile

                </a>

            </div>


            <!-- SETTINGS -->

            <div class="account-card">

                <i class="bi bi-gear"></i>


                <h5>

                    Account Settings

                </h5>


                <p>

                    Access the settings area to manage
                    available account preferences.

                </p>


                <a
                    href="user_settings.php"
                    class="btn btn-outline-primary btn-sm"
                >

                    <i class="bi bi-arrow-right"></i>

                    Open Settings

                </a>

            </div>


            <!-- HOME -->

            <div class="account-card">

                <i class="bi bi-house-door"></i>


                <h5>

                    Return to Home

                </h5>


                <p>

                    Go back to your event dashboard and
                    continue browsing events.

                </p>


                <a
                    href="user.php"
                    class="btn btn-outline-primary btn-sm"
                >

                    <i class="bi bi-arrow-right"></i>

                    Go to Home

                </a>

            </div>

        </div>

    </section>


    <!-- ======================================================
         SAFETY / GOOD PRACTICES
    ======================================================= -->

    <section
        class="help-section searchable-section"
        data-search-content="
            safety security privacy account password
            event information personal information
        "
    >

        <div class="section-heading">

            <h2>

                <i class="bi bi-shield-check text-primary"></i>

                Account Safety Tips

            </h2>


            <p>

                Simple practices to keep your account
                and event activity secure.

            </p>

        </div>


        <div class="troubleshooting-grid">


            <div class="issue-card">

                <h5>

                    <i class="bi bi-key text-primary"></i>

                    Protect Your Password

                </h5>


                <p>

                    Keep your account password private.
                    Do not share your login credentials
                    with other people.

                </p>

            </div>


            <div class="issue-card">

                <h5>

                    <i class="bi bi-box-arrow-right text-primary"></i>

                    Log Out on Shared Devices

                </h5>


                <p>

                    If you use a shared or public computer,
                    always log out after finishing your session.

                </p>

            </div>


            <div class="issue-card">

                <h5>

                    <i class="bi bi-calendar-check text-primary"></i>

                    Check Event Details

                </h5>


                <p>

                    Before joining an event, review the event
                    name, date, time, location, organizer,
                    and available capacity.

                </p>

            </div>


            <div class="issue-card">

                <h5>

                    <i class="bi bi-chat-left-text text-primary"></i>

                    Submit Useful Feedback

                </h5>


                <p>

                    When leaving a review, provide clear and
                    respectful feedback that can help improve
                    future events.

                </p>

            </div>

        </div>

    </section>


    <!-- ======================================================
         SUPPORT
    ======================================================= -->

    <section
        class="help-section"
        id="support"
    >

        <div class="support-card">

            <div>

                <h3>

                    Still need help?

                </h3>


                <p>

                    If the information above does not solve
                    your problem, contact your system
                    administrator or the person responsible
                    for managing the QC Event System.

                </p>

            </div>


            <div class="support-icon">

                <i class="bi bi-headset"></i>

            </div>

        </div>

    </section>


    <!-- ======================================================
         NO SEARCH RESULTS
    ======================================================= -->

    <div
        id="noHelpResults"
        class="no-help-results"
    >

        <i class="bi bi-search"></i>


        <h4>

            No help articles found

        </h4>


        <p class="text-muted mb-0">

            Try searching for another keyword such as
            "join", "review", "events", "profile",
            or "account".

        </p>

    </div>


</section>


<!-- ==========================================================
     FOOTER
========================================================== -->

<footer
    class="
        text-center
        py-4
    "
>

    <p class="mb-0">

        Event MS | Better Events. Stronger Communities.

    </p>

</footer>

 </main>



<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
></script>

<script src="../assets/js/users/help_user.js"></script>
<script src="../assets/js/organizer/notifications.js"></script>


</body>

</html>
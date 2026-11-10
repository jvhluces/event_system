<?php
session_start();

require_once '../config/db.php';

if(!isset($_SESSION['user_id'])){
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

if ($role === 'Organizer') {
    require_once __DIR__ . '/notification_center.php';
    $notificationCenter = organizer_notification_center($conn, (int) $user_id);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Event System</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../assets/css/organizer/organizer_page.css">
</head>



<body>


<nav class="navbar navbar-dark bg-primary">
  <div class="container-fluid">
    <button class="btn btn-outline-light me-2" data-bs-toggle="offcanvas" data-bs-target="#sidebar">
      <i class="bi bi-list"></i>
    </button>
    <span class="navbar-brand fw-bold">Event System</span>
    <?php if ($role === 'Organizer'): ?>
      <?php require __DIR__ . '/notification_menu.php'; ?>
    <?php endif; ?>
    <a href="../login_user.php" class="btn btn-outline-light">Login</a>

  </div>
</nav>

<div class="offcanvas offcanvas-start bg-dark text-white" id="sidebar">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title"><img class="offcanvas-logo" src="../assets/images/organizer-sidebar-logo.png" alt="Event MS"></h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
  </div>
  <div class="offcanvas-body">
    <ul class="nav flex-column">
      <li class="nav-item"><a href="#intro" class="nav-link text-white"><i class="bi bi-info-circle"></i> About System</a></li>
      <li class="nav-item"><a href="#solutions" class="nav-link text-white"><i class="bi bi-lightbulb"></i> Event Solutions</a></li>
      <li class="nav-item"><a href="#flow" class="nav-link text-white"><i class="bi bi-diagram-3"></i> System Flow</a></li>
      <li class="nav-item"><a href="#resources" class="nav-link text-white"><i class="bi bi-book"></i> Resources</a></li>
      <li class="nav-item"><a href="#about" class="nav-link text-white"><i class="bi bi-people"></i> About Us</a></li>
    </ul>
    <div class="mt-4">
      <a href="signup.php" class="btn btn-success w-100 mb-2">Request Demo</a>
      <a href="venues.php" class="btn btn-outline-light w-100 mb-2">Find Event Venues</a>
      <a href="login.php" class="btn btn-outline-light w-100">Log In</a>
    </div>
  </div>
</div>


<!-- Intro Section -->
<section id="intro" class="d-flex align-items-center justify-content-center text-center text-white position-relative" 
         style="height:100vh; background:url('./images/hero-bg.avif') center/cover no-repeat;">

  <!-- Overlay (gradient + blur) -->
  <div class="position-absolute top-0 start-0 w-100 h-100" 
       style="backdrop-filter: blur(4px); background: linear-gradient(180deg, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0.3) 100%);">
  </div>

  <!-- Content -->
  <div class="container position-relative">
    <h1 class="display-2 fw-bold mb-3 text-shadow">Event System</h1>
    <p class="lead fs-4 mb-4">A modern corporate platform for seamless event management.</p>
    <a href="#flow" class="btn btn-lg btn-outline-light px-4">Explore System Flow</a>
  </div>
</section>


<!-- 2. System Flow Section -->
<section id="flow" class="container py-5">
  <h2 class="text-center mb-4">System Flow</h2>
  <div class="row text-center">
    <div class="col-md-3"><i class="bi bi-person-plus display-4 text-primary"></i><p>Sign Up</p></div>
    <div class="col-md-3"><i class="bi bi-calendar-plus display-4 text-success"></i><p>Post Event</p></div>
    <div class="col-md-3"><i class="bi bi-ticket display-4 text-warning"></i><p>Join Event</p></div>
    <div class="col-md-3"><i class="bi bi-graph-up display-4 text-danger"></i><p>Track Reports</p></div>
  </div>
</section>

<!-- 3. Placeholder Section -->
<section id="solutions" class="bg-light py-5">
  <div class="container text-center">
    <h2 class="mb-4">Event Solutions</h2>
    <p class="text-secondary">This section can highlight solutions offered by the system — like automated scheduling, audience management, and venue booking.</p>
  </div>
</section>

<!-- 4. Placeholder Section -->
<section id="resources" class="container py-5">
  <h2 class="text-center mb-4">Resources</h2>
  <p class="text-center text-secondary">Here you can add guides, FAQs, or documentation about how to use the system.</p>
</section>

<!-- Footer -->
<footer id="about" class="bg-dark text-white text-center py-3">
  <p>© 2026 Event System. All rights reserved.</p>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/organizer/notifications.js"></script>
</body>
</html>

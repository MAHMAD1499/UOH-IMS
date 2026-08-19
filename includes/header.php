<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internship Management System</title>
    <!-- FontAwesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- CSS stylesheet link -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content Wrapper -->
    <div class="main-wrapper">
        <div class="top-navbar">
            <div class="welcome-text">
                <i class="fa-solid fa-bars" id="sidebarToggle" style="cursor: pointer;"></i> Welcome
            </div>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>

        <!-- Content Area start (footer.php closes this) -->
        <div class="content-area">
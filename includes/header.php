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
                <i class="fa-solid fa-bars" id="sidebarToggle" style="cursor: pointer;"></i> 
                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'FP'): ?>
                    Focal Person Portal
                <?php elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'FSP'): ?>
                    Faculty Supervisor Portal
                <?php else: ?>
                    Welcome
                <?php endif; ?>
            </div>
            <div style="display: flex; align-items: center; gap: 15px;">
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <!-- Content Area start (footer.php closes this) -->
        <div class="content-area">
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
    <link rel="stylesheet" href="assets/css/style.css?v=2.3">
</head>
<body>

    <?php include __DIR__ . '/sidebar.php'; ?>

    <!-- Main Content Wrapper -->
    <div class="main-wrapper">
        <div class="top-navbar">
            <div class="welcome-text">
                <i class="fa-solid fa-bars" id="sidebarToggle" style="cursor: pointer;"></i> 
                <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'FP'): ?>
                    <?php
                        $headerFpName = 'Focal Person';
                        if (isset($conn) && isset($_SESSION['user_id'])) {
                            $fpId = (int)$_SESSION['user_id'];
                            $fpq = mysqli_query($conn, "SELECT full_name FROM users WHERE user_id = $fpId LIMIT 1");
                            if ($fpq && $fpr = mysqli_fetch_assoc($fpq)) {
                                $headerFpName = $fpr['full_name'] ?: 'Focal Person';
                            }
                        }
                    ?>
                    Welcome <?php echo htmlspecialchars($headerFpName); ?>
                <?php elseif (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'FSP'): ?>
                    <?php
                        $headerFspName = $_SESSION['username'] ?? 'Faculty Supervisor';
                        if (isset($conn) && isset($_SESSION['user_id'])) {
                            $fspId = (int)$_SESSION['user_id'];
                            $fspq = mysqli_query($conn, "SELECT name FROM user_profile WHERE u_id = $fspId LIMIT 1");
                            if ($fspq && $fspr = mysqli_fetch_assoc($fspq)) {
                                $headerFspName = $fspr['name'] ?: $headerFspName;
                            }
                        }
                    ?>
                    Welcome <?php echo htmlspecialchars($headerFspName); ?>
                <?php else: ?>
                    Welcome <?php echo htmlspecialchars($_SESSION['username'] ?? 'Student'); ?>
                <?php endif; ?>
            </div>
            <div style="display: flex; align-items: center; gap: 15px;">
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>

        <!-- Content Area start (footer.php closes this) -->
        <div class="content-area">
<div class="sidebar">
    <div class="sidebar-header">
        <img src="assets/img/uoh logo.svg" alt="UOH Logo" class="sidebar-logo">
    </div>
    <div class="user-profile-mini">
        <i class="fa-solid fa-user-circle fa-2x"></i>
        <span><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
    </div>

    <ul class="nav-menu">
        <li class="nav-section-title">Internship Management System</li>

        <?php if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] === 'STD'): ?>
            <li class="nav-item active" onclick="switchTab('student-dashboard', this)">
                <i class="fa-solid fa-user"></i> Personal
            </li>
            <li class="nav-item" onclick="switchTab('student-reports', this)">
                <i class="fa-solid fa-file-lines"></i> Internship Reports
            </li>
            <li class="nav-item" onclick="switchTab('student-letters', this)">
                <i class="fa-solid fa-envelope-open-text"></i> Internship Letters
            </li>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'FP'): ?>
            <li class="nav-item active" onclick="switchTab('focal-dashboard', this)">
                <i class="fa-solid fa-gauge"></i> Registered Students list
            </li>
            <li class="nav-item" onclick="switchTab('focal-letters', this)">
                <i class="fa-solid fa-file-contract"></i> Internship Letter
            </li>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'FSP'): ?>
            <li class="nav-item active" onclick="switchTab('faculty-dashboard', this)">
                <i class="fa-solid fa-users-rectangle"></i> Assigned Students
            </li>
            <li class="nav-item" onclick="switchTab('faculty-reports', this)">
                <i class="fa-solid fa-file-signature"></i> Weekly Reports Review
            </li>
            <li class="nav-item" onclick="switchTab('faculty-marks', this)">
                <i class="fa-solid fa-award"></i> Marks Evaluation
            </li>
            <li class="nav-item" onclick="switchTab('faculty-orgs', this)">
                <i class="fa-solid fa-building"></i> Organizations & Supervisors
            </li>
        <?php endif; ?>
    </ul>
</div>
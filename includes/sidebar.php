<div class="sidebar">
    <div class="sidebar-header">
        <img src="assets/img/uoh logo.svg" alt="UOH Logo" class="sidebar-logo">
    </div>
    <div class="user-profile-mini" style="cursor: pointer;" onclick="switchToProfileTab()">
        <i class="fa-solid fa-user-circle fa-2x"></i>
        <span><?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
    </div>

    <ul class="nav-menu">
        <li class="nav-section-title">Internship Management System</li>

        <?php if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] === 'STD'): ?>
            <li class="nav-item nav-item-dropdown-toggle" onclick="toggleSidebarDropdown('profile-dropdown')">
                <div>
                    <i class="fa-solid fa-id-card"></i> <span style="margin-left: 12px;">Profile</span>
                </div>
                <i class="fa-solid fa-chevron-down dropdown-chevron"></i>
            </li>
            <ul class="nav-dropdown" id="profile-dropdown">
                <li class="nav-subitem" id="nav-subitem-view-profile" onclick="switchTab('student-dashboard', this)">
                    <i class="fa-solid fa-chevron-right"></i> <span>View Profile</span>
                </li>
                <li class="nav-subitem" id="nav-subitem-change-password" onclick="switchTab('student-change-password', this)">
                    <i class="fa-solid fa-chevron-right"></i> <span>Change Password</span>
                </li>
            </ul>
            <li class="nav-item active" onclick="switchTab('student-welcome-dashboard', this)">
                <i class="fa-solid fa-gauge"></i> <span>Dashboard</span>
            </li>
            <li class="nav-item" onclick="switchTab('student-reports', this)">
                <i class="fa-solid fa-file-lines"></i> <span>Internship Reports</span>
            </li>
            <li class="nav-item" onclick="switchTab('student-letters', this)">
                <i class="fa-solid fa-envelope-open-text"></i> <span>Internship Letters</span>
            </li>
            <li class="nav-item" onclick="switchTab('student-site-supervisor', this)">
                <i class="fa-solid fa-user-tie"></i> <span>Site Supervisor</span>
            </li>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'FP'): ?>
            <li class="nav-item active" onclick="switchTab('focal-dashboard', this)">
                <i class="fa-solid fa-gauge"></i> <span>Registered Students list</span>
            </li>
            <li class="nav-item" onclick="switchTab('focal-letters', this)">
                <i class="fa-solid fa-file-contract"></i> <span>Internship Letter</span>
            </li>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'FSP'): ?>
            <li class="nav-item active" onclick="switchTab('faculty-dashboard', this)">
                <i class="fa-solid fa-users-rectangle"></i> <span>Assigned Students</span>
            </li>
            <li class="nav-item" onclick="switchTab('faculty-reports', this)">
                <i class="fa-solid fa-file-signature"></i> <span>Weekly Reports Review</span>
            </li>
            <li class="nav-item" onclick="switchTab('faculty-marks', this)">
                <i class="fa-solid fa-award"></i> <span>Marks Evaluation</span>
            </li>
        <?php endif; ?>
    </ul>
</div>
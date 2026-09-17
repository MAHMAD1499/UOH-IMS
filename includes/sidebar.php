<!--
  Sidebar Navigation Partial

  Renders the left-hand sidebar for the IMS dashboard.
  The menu items are conditionally displayed based on the user's role
  stored in $_SESSION['user_type']:
    - STD  → Student menu items (profile, faculty/site supervisor, letters, reports)
    - FP   → Focal Person menu items (profile, registered students, letters)
    - FSP  → Faculty Supervisor menu items (profile, assigned students, reports, marks)

  JavaScript functions `switchTab()` and `toggleSidebarDropdown()` (in footer.php)
  handle navigation without full page reloads — all tab content lives on one page.

  @file    includes/sidebar.php
  @project Internship Management System (IMS) — University of Haripur
-->

<!-- ── Sidebar Container ──────────────────────────────────────────────── -->
<div class="sidebar">
    <!-- Sidebar Logo / Branding -->
    <div class="sidebar-header">
        <img src="assets/img/internship_management_system.svg" alt="UOH Logo" class="sidebar-logo">
    </div>
    <!-- Mini profile badge — clicking navigates to the user's profile tab -->
    <?php
    $sidebarName = $_SESSION['username'] ?? 'User';
    $sidebarProfileImg = null;

    if (isset($conn) && isset($_SESSION['user_id'])) {
        $uid = (int) $_SESSION['user_id'];
        $role = $_SESSION['user_type'] ?? 'STD';

        $imgq = mysqli_query($conn, "SELECT profile_image FROM user WHERE u_id = $uid LIMIT 1");
        if ($imgq && $imgr = mysqli_fetch_assoc($imgq)) {
            if (!empty($imgr['profile_image'])) {
                $sidebarProfileImg = $imgr['profile_image'];
            }
        }

        if ($role === 'FP') {
            $nq = mysqli_query($conn, "SELECT full_name FROM users WHERE user_id = $uid LIMIT 1");
            if ($nq && $nr = mysqli_fetch_assoc($nq)) {
                $sidebarName = $nr['full_name'] ?: $sidebarName;
            }
        } else {
            $nq = mysqli_query($conn, "SELECT name FROM user_profile WHERE u_id = $uid LIMIT 1");
            if ($nq && $nr = mysqli_fetch_assoc($nq)) {
                $sidebarName = $nr['name'] ?: $sidebarName;
            }
        }
    }
    ?>
    <div class="user-profile-mini" style="cursor: pointer;" onclick="switchToProfileTab()">
        <?php if ($sidebarProfileImg): ?>
            <img src="<?php echo htmlspecialchars($sidebarProfileImg); ?>" alt="Profile Picture"
                style="width: 40px; height: 40px; border-radius: 50%; object-fit: cover;">
        <?php else: ?>
            <i class="fa-solid fa-user-circle fa-2x"></i>
        <?php endif; ?>
        <span><?php echo htmlspecialchars($sidebarName); ?></span>
    </div>

    <ul class="nav-menu">
        <li class="nav-section-title">
            <span class="full-title">Internship Management System</span>
            <span class="short-title">I<br><span class="letter-m">M</span><br>S</span>
        </li>

        <!-- ═══════════════════════════════════════════════════════════ -->
        <!--  STUDENT NAVIGATION (role = STD or not set)               -->
        <!-- ═══════════════════════════════════════════════════════════ -->
        <?php if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] === 'STD'): ?>
            <!-- Dashboard overview — default active tab -->
            <li class="nav-item active" id="nav-item-student-dashboard"
                onclick="switchTab('student-welcome-dashboard', this)">
                <i class="fa-solid fa-gauge"></i> <span>Dashboard</span>
            </li>
            <!-- Profile dropdown with sub-items: View Profile & Change Password -->
            <li class="nav-item nav-item-dropdown-toggle" id="nav-item-student-profile-toggle"
                onclick="toggleSidebarDropdown('profile-dropdown')">
                <div>
                    <i class="fa-solid fa-id-card"></i> <span style="margin-left: 12px;">Profile</span>
                </div>
                <i class="fa-solid fa-chevron-down dropdown-chevron"></i>
            </li>
            <ul class="nav-dropdown" id="profile-dropdown">
                <li class="nav-subitem" id="nav-subitem-view-profile" onclick="switchTab('student-dashboard', this)">
                    <i class="fa-solid fa-chevron-right"></i> <span>View Profile</span>
                </li>
                <li class="nav-subitem" id="nav-subitem-change-password"
                    onclick="switchTab('student-change-password', this)">
                    <i class="fa-solid fa-chevron-right"></i> <span>Change Password</span>
                </li>
            </ul>
            <!-- Faculty Supervisor details -->
            <li class="nav-item" id="nav-item-student-faculty-supervisor"
                onclick="switchTab('student-faculty-supervisor', this)">
                <i class="fa-solid fa-user-graduate"></i> <span>Faculty Supervisor</span>
            </li>
            <!-- Site Supervisor & Organisation details -->
            <li class="nav-item" id="nav-item-student-site-supervisor" onclick="switchTab('student-site-supervisor', this)">
                <i class="fa-solid fa-user-tie"></i> <span>Site Supervisor</span>
            </li>
            <!-- Internship recommendation letters -->
            <li class="nav-item" id="nav-item-student-letters" onclick="switchTab('student-letters', this)">
                <i class="fa-solid fa-file-contract"></i> <span>Internship Letter</span>
            </li>
            <!-- Weekly / biweekly internship reports -->
            <li class="nav-item" id="nav-item-student-reports" onclick="switchTab('student-reports', this)">
                <i class="fa-solid fa-file-signature"></i> <span>Internship Reports</span>
            </li>
        <?php endif; ?>

        <!-- ═══════════════════════════════════════════════════════════ -->
        <!--  FOCAL PERSON NAVIGATION (role = FP)                      -->
        <!-- ═══════════════════════════════════════════════════════════ -->
        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'FP'): ?>
            <!-- Focal Person dashboard overview -->
            <li class="nav-item active" id="nav-item-focal-dashboard-welcome"
                onclick="switchTab('focal-welcome-dashboard', this)">
                <i class="fa-solid fa-gauge"></i> <span>Dashboard</span>
            </li>
            <!-- FP Profile dropdown -->
            <li class="nav-item nav-item-dropdown-toggle" id="nav-item-focal-profile-toggle"
                onclick="toggleSidebarDropdown('focal-profile-dropdown')">
                <div>
                    <i class="fa-solid fa-id-card"></i> <span style="margin-left: 12px;">Profile</span>
                </div>
                <i class="fa-solid fa-chevron-down dropdown-chevron"></i>
            </li>
            <ul class="nav-dropdown" id="focal-profile-dropdown">
                <li class="nav-subitem" id="nav-item-focal-profile" onclick="switchTab('focal-profile', this)">
                    <i class="fa-solid fa-chevron-right"></i> <span>View Profile</span>
                </li>
                <li class="nav-subitem" id="nav-item-focal-change-password"
                    onclick="switchTab('focal-change-password', this)">
                    <i class="fa-solid fa-chevron-right"></i> <span>Change Password</span>
                </li>
            </ul>
            <!-- Registered Students dropdown with filter options -->
            <li class="nav-item nav-item-dropdown-toggle" id="nav-item-focal-students-toggle"
                onclick="toggleSidebarDropdown('focal-students-dropdown')">
                <div>
                    <i class="fa-solid fa-users-rectangle"></i> <span style="margin-left: 12px;">Registered Students</span>
                </div>
                <i class="fa-solid fa-chevron-down dropdown-chevron"></i>
            </li>
            <ul class="nav-dropdown" id="focal-students-dropdown">
                <!-- All / Assigned / Unassigned sub-filters -->
                <li class="nav-subitem active" id="nav-subitem-all-students"
                    onclick="switchTab('focal-dashboard', this); if(typeof setAssignmentFilter === 'function') setAssignmentFilter('all');">
                    <i class="fa-solid fa-chevron-right"></i> <span>All Students</span>
                </li>
                <li class="nav-subitem" id="nav-subitem-assigned-students"
                    onclick="switchTab('focal-dashboard', this); if(typeof setAssignmentFilter === 'function') setAssignmentFilter('assigned');">
                    <i class="fa-solid fa-chevron-right"></i> <span>Assigned Students</span>
                </li>
                <li class="nav-subitem" id="nav-subitem-unassigned-students"
                    onclick="switchTab('focal-dashboard', this); if(typeof setAssignmentFilter === 'function') setAssignmentFilter('unassigned');">
                    <i class="fa-solid fa-chevron-right"></i> <span>Unassigned Students</span>
                </li>
            </ul>
            <!-- Letter approval management -->
            <li class="nav-item" id="nav-item-focal-letters" onclick="switchTab('focal-letters', this)">
                <i class="fa-solid fa-file-contract"></i> <span>Internship Letter</span>
            </li>
        <?php endif; ?>

        <!-- ═══════════════════════════════════════════════════════════ -->
        <!--  FACULTY SUPERVISOR NAVIGATION (role = FSP)               -->
        <!-- ═══════════════════════════════════════════════════════════ -->
        <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'FSP'): ?>
            <!-- Faculty Supervisor dashboard overview -->
            <li class="nav-item active" id="nav-item-faculty-dashboard-welcome"
                onclick="switchTab('faculty-welcome-dashboard', this)">
                <i class="fa-solid fa-gauge"></i> <span>Dashboard</span>
            </li>
            <!-- FSP Profile dropdown -->
            <li class="nav-item nav-item-dropdown-toggle" id="nav-item-faculty-profile-toggle"
                onclick="toggleSidebarDropdown('faculty-profile-dropdown')">
                <div>
                    <i class="fa-solid fa-id-card"></i> <span style="margin-left: 12px;">Profile</span>
                </div>
                <i class="fa-solid fa-chevron-down dropdown-chevron"></i>
            </li>
            <ul class="nav-dropdown" id="faculty-profile-dropdown">
                <li class="nav-subitem" id="nav-item-faculty-profile" onclick="switchTab('faculty-profile', this)">
                    <i class="fa-solid fa-chevron-right"></i> <span>View Profile</span>
                </li>
                <li class="nav-subitem" id="nav-item-faculty-change-password"
                    onclick="switchTab('faculty-change-password', this)">
                    <i class="fa-solid fa-chevron-right"></i> <span>Change Password</span>
                </li>
            </ul>
            <!-- List of students assigned to this supervisor -->
            <li class="nav-item" id="nav-item-faculty-dashboard" onclick="switchTab('faculty-dashboard', this)">
                <i class="fa-solid fa-users-rectangle"></i> <span>Assigned Students</span>
            </li>
            <!-- Weekly report review for assigned students -->
            <li class="nav-item" id="nav-item-faculty-reports" onclick="switchTab('faculty-reports', this)">
                <i class="fa-solid fa-file-signature"></i> <span>Weekly Reports Review</span>
            </li>
            <!-- Marks / evaluation management -->
            <li class="nav-item" id="nav-item-faculty-marks" onclick="switchTab('faculty-marks', this)">
                <i class="fa-solid fa-award"></i> <span>Marks Evaluation</span>
            </li>
        <?php endif; ?>
    </ul>
</div>
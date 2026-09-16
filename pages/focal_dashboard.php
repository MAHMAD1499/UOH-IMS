<?php
/**
 * Focal Person Dashboard View (role = FP)
 * 
 * Renders the Focal Person's management interface. This file is included by
 * index.php (never accessed directly) and provides:
 * 
 * Features:
 *   - Dashboard overview with student statistics and announcements
 *   - Focal Person profile management (view/edit personal details)
 *   - Registered students listing with assignment filters (all/assigned/unassigned)
 *   - Faculty Supervisor assignment to students
 *   - Internship letter approval and preview for students
 *   - Student details modal with full profile, placement, and report info
 *   - Password change functionality
 *   - Announcement creation and management
 * 
 * Expected globals (set by index.php):
 *   $conn, $_SESSION['user_id'], $_SESSION['user_type'], $_SESSION['username'],
 *   $flashMessage, $flashType, $accountDetails
 * 
 * @file    focal_dashboard.php
 * @project Internship Management System (IMS) — University of Haripur
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';

// Fetch Focal Person details from users table
$fpUserId = (int) ($_SESSION['user_id'] ?? 21);
$fpStmt = mysqli_prepare($conn, "SELECT user_id, full_name, email, phone, designation FROM users WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($fpStmt, 'i', $fpUserId);
mysqli_stmt_execute($fpStmt);
$fpRes = mysqli_stmt_get_result($fpStmt);
$focalPerson = mysqli_fetch_assoc($fpRes) ?: [
    'user_id' => $fpUserId,
    'full_name' => 'Focal Person',
    'email' => 'focal@uoh.edu.pk',
    'phone' => '0300-1234567',
    'designation' => 'Lecturer / Internship Focal Person'
];
mysqli_stmt_close($fpStmt);

// Create announcements table if it doesn't exist
mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS `announcements` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL,
        `content` TEXT NOT NULL,
        `created_by` VARCHAR(100) DEFAULT 'Focal Person',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Fetch existing announcements
$announcementsResult = mysqli_query($conn, "SELECT * FROM announcements ORDER BY created_at DESC");
$announcements = [];
if ($announcementsResult) {
    while ($row = mysqli_fetch_assoc($announcementsResult)) {
        $announcements[] = $row;
    }
}

// Handle POST request processing for Focal Person actions
// Handle Form Submissions: Adding students, approving letters, assigning faculty, etc.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add Announcement Action
    if (isset($_POST['add_announcement'])) {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $createdBy = $_SESSION['username'] ?? 'Focal Person';

        if ($title !== '' && $content !== '') {
            $stmt = mysqli_prepare($conn, "INSERT INTO announcements (title, content, created_by) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'sss', $title, $content, $createdBy);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['flash_message'] = 'Announcement published successfully.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Title and content cannot be empty.';
            $_SESSION['flash_type'] = 'error';
        }
        header('Location: index.php');
        exit;
    }

    // Delete Announcement Action
    if (isset($_POST['delete_announcement'])) {
        $announcementId = (int) ($_POST['announcement_id'] ?? 0);
        if ($announcementId > 0) {
            $stmt = mysqli_prepare($conn, "DELETE FROM announcements WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'i', $announcementId);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['flash_message'] = 'Announcement deleted successfully.';
            $_SESSION['flash_type'] = 'success';
        }
        header('Location: index.php');
        exit;
    }

    if (isset($_POST['add_student'])) {
        $rollno = strtoupper(trim($_POST['rollno'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $fname = trim($_POST['fname'] ?? '');
        $cnic = '';
        $depart = trim($_POST['depart'] ?? '');
        $program = trim($_POST['program'] ?? '');
        $sem = trim($_POST['sem'] ?? '');
        $session = trim($_POST['session'] ?? '');

        // Server-side validation
        if ($rollno === '' || $name === '' || $fname === '' || $depart === '' || $program === '' || $sem === '' || $session === '') {
            $_SESSION['flash_message'] = 'All fields are required.';
            $_SESSION['flash_type'] = 'error';
            header('Location: index.php');
            exit;
        }

        if (!preg_match('/^[a-zA-Z]\d{2}-\d{4}$/', $rollno)) {
            $_SESSION['flash_message'] = 'Invalid Roll No format. Expected format: e.g. S23-1234 or F26-0001';
            $_SESSION['flash_type'] = 'error';
            header('Location: index.php');
            exit;
        }

        // Check if student already exists in user table
        $checkQuery = 'SELECT u_id FROM user WHERE u_name = ? AND u_type = \'STD\' LIMIT 1';
        $checkStmt = mysqli_prepare($conn, $checkQuery);
        mysqli_stmt_bind_param($checkStmt, 's', $rollno);
        mysqli_stmt_execute($checkStmt);
        $checkRes = mysqli_stmt_get_result($checkStmt);
        if (mysqli_fetch_assoc($checkRes)) {
            mysqli_stmt_close($checkStmt);
            $_SESSION['flash_message'] = 'Student with this Roll No already exists.';
            $_SESSION['flash_type'] = 'error';
            header('Location: index.php');
            exit;
        }
        mysqli_stmt_close($checkStmt);

        // Insert into database tables using Transaction
        mysqli_begin_transaction($conn);
        try {
            // Default password is the roll number hashed
            $hashedPass = password_hash($rollno, PASSWORD_BCRYPT);

            $userQuery = 'INSERT INTO user (u_name, u_pass, u_type, status) VALUES (?, ?, \'STD\', 1)';
            $userStmt = mysqli_prepare($conn, $userQuery);
            mysqli_stmt_bind_param($userStmt, 'ss', $rollno, $hashedPass);
            mysqli_stmt_execute($userStmt);
            $newUserId = mysqli_insert_id($conn);
            mysqli_stmt_close($userStmt);

            $profileQuery = 'INSERT INTO user_profile (u_id, name, fname, cnic, cell_no, email, rollno_Empno, address, city) VALUES (?, ?, ?, ?, \'\', \'\', ?, \'\', \'\')';
            $profileStmt = mysqli_prepare($conn, $profileQuery);
            mysqli_stmt_bind_param($profileStmt, 'issss', $newUserId, $name, $fname, $cnic, $rollno);
            mysqli_stmt_execute($profileStmt);
            mysqli_stmt_close($profileStmt);

            $semQuery = 'INSERT INTO user_semester_detail (rollno, session, semester, department, program) VALUES (?, ?, ?, ?, ?)';
            $semStmt = mysqli_prepare($conn, $semQuery);
            mysqli_stmt_bind_param($semStmt, 'sssss', $rollno, $session, $sem, $depart, $program);
            mysqli_stmt_execute($semStmt);
            mysqli_stmt_close($semStmt);

            mysqli_commit($conn);
            $_SESSION['flash_message'] = 'Student record added successfully.';
            $_SESSION['flash_type'] = 'success';
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $_SESSION['flash_message'] = 'Failed to add student: ' . $e->getMessage();
            $_SESSION['flash_type'] = 'error';
        }
        header('Location: index.php');
        exit;
    }

    if (isset($_POST['assign_supervisor'])) {
        $rollno = trim($_POST['rollno'] ?? '');
        $supervisorId = (int) ($_POST['supervisor_id'] ?? 0);

        if ($rollno === '' || $supervisorId <= 0) {
            $_SESSION['flash_message'] = 'Invalid selection. Roll number and Supervisor are required.';
            $_SESSION['flash_type'] = 'error';
            header('Location: index.php');
            exit;
        }

        // Verify that assignment row already exists or not
        $checkAssign = mysqli_prepare($conn, 'SELECT a_f_s_id FROM assign_faculty_supervisor WHERE rollno = ? LIMIT 1');
        mysqli_stmt_bind_param($checkAssign, 's', $rollno);
        mysqli_stmt_execute($checkAssign);
        $resAssign = mysqli_stmt_get_result($checkAssign);
        $assignment = mysqli_fetch_assoc($resAssign);
        mysqli_stmt_close($checkAssign);

        if ($assignment) {
            $updateAssign = mysqli_prepare($conn, 'UPDATE assign_faculty_supervisor SET u_id = ?, status = 1, updated_at = NOW() WHERE rollno = ?');
            mysqli_stmt_bind_param($updateAssign, 'is', $supervisorId, $rollno);
            mysqli_stmt_execute($updateAssign);
            mysqli_stmt_close($updateAssign);
        } else {
            $insertAssign = mysqli_prepare($conn, 'INSERT INTO assign_faculty_supervisor (rollno, u_id, status) VALUES (?, ?, 1)');
            mysqli_stmt_bind_param($insertAssign, 'si', $rollno, $supervisorId);
            mysqli_stmt_execute($insertAssign);
            mysqli_stmt_close($insertAssign);
        }

        // Sync with students table
        $checkStudent = mysqli_prepare($conn, 'SELECT student_id FROM students WHERE roll_no = ? LIMIT 1');
        if ($checkStudent) {
            mysqli_stmt_bind_param($checkStudent, 's', $rollno);
            mysqli_stmt_execute($checkStudent);
            $resStudent = mysqli_stmt_get_result($checkStudent);
            $studentExists = mysqli_fetch_assoc($resStudent);
            mysqli_stmt_close($checkStudent);

            if ($studentExists) {
                $updateStudent = mysqli_prepare($conn, 'UPDATE students SET faculty_supervisor_id = ? WHERE roll_no = ?');
                if ($updateStudent) {
                    mysqli_stmt_bind_param($updateStudent, 'is', $supervisorId, $rollno);
                    mysqli_stmt_execute($updateStudent);
                    mysqli_stmt_close($updateStudent);
                }
            } else {
                $uQuery = mysqli_prepare($conn, 'SELECT u.u_id, sd.session FROM user u LEFT JOIN user_semester_detail sd ON u.u_name = sd.rollno WHERE u.u_name = ? LIMIT 1');
                if ($uQuery) {
                    mysqli_stmt_bind_param($uQuery, 's', $rollno);
                    mysqli_stmt_execute($uQuery);
                    $uRes = mysqli_stmt_get_result($uQuery);
                    if ($uRow = mysqli_fetch_assoc($uRes)) {
                        $uId = $uRow['u_id'];
                        $session = $uRow['session'] ?: 'Unknown';
                        $insStudent = mysqli_prepare($conn, 'INSERT INTO students (user_id, roll_no, session, faculty_supervisor_id) VALUES (?, ?, ?, ?)');
                        if ($insStudent) {
                            mysqli_stmt_bind_param($insStudent, 'issi', $uId, $rollno, $session, $supervisorId);
                            mysqli_stmt_execute($insStudent);
                            mysqli_stmt_close($insStudent);
                        }
                    }
                    mysqli_stmt_close($uQuery);
                }
            }
        }

        $_SESSION['flash_message'] = 'Faculty supervisor assigned successfully.';
        $_SESSION['flash_type'] = 'success';
        header('Location: index.php');
        exit;
    }

    if (isset($_POST['bulk_assign_supervisor'])) {
        $supervisorId = (int) ($_POST['supervisor_id'] ?? 0);
        $rollnos = $_POST['rollnos'] ?? [];

        if ($supervisorId <= 0 || empty($rollnos)) {
            $_SESSION['flash_message'] = 'Invalid selection. Supervisor and at least one student are required.';
            $_SESSION['flash_type'] = 'error';
            header('Location: index.php');
            exit;
        }

        if (count($rollnos) > 30) {
            $_SESSION['flash_message'] = 'You can only assign up to 30 students at a time.';
            $_SESSION['flash_type'] = 'error';
            header('Location: index.php');
            exit;
        }

        $assignedCount = 0;
        foreach ($rollnos as $rollno) {
            $rollno = trim($rollno);
            if ($rollno === '')
                continue;

            $checkAssign = mysqli_prepare($conn, 'SELECT a_f_s_id FROM assign_faculty_supervisor WHERE rollno = ? LIMIT 1');
            mysqli_stmt_bind_param($checkAssign, 's', $rollno);
            mysqli_stmt_execute($checkAssign);
            $resAssign = mysqli_stmt_get_result($checkAssign);
            $assignment = mysqli_fetch_assoc($resAssign);
            mysqli_stmt_close($checkAssign);

            if ($assignment) {
                $updateAssign = mysqli_prepare($conn, 'UPDATE assign_faculty_supervisor SET u_id = ?, status = 1, updated_at = NOW() WHERE rollno = ?');
                mysqli_stmt_bind_param($updateAssign, 'is', $supervisorId, $rollno);
                mysqli_stmt_execute($updateAssign);
                mysqli_stmt_close($updateAssign);
            } else {
                $insertAssign = mysqli_prepare($conn, 'INSERT INTO assign_faculty_supervisor (rollno, u_id, status) VALUES (?, ?, 1)');
                mysqli_stmt_bind_param($insertAssign, 'si', $rollno, $supervisorId);
                mysqli_stmt_execute($insertAssign);
                mysqli_stmt_close($insertAssign);
            }

            // Sync with students table
            $checkStudent = mysqli_prepare($conn, 'SELECT student_id FROM students WHERE roll_no = ? LIMIT 1');
            if ($checkStudent) {
                mysqli_stmt_bind_param($checkStudent, 's', $rollno);
                mysqli_stmt_execute($checkStudent);
                $resStudent = mysqli_stmt_get_result($checkStudent);
                $studentExists = mysqli_fetch_assoc($resStudent);
                mysqli_stmt_close($checkStudent);

                if ($studentExists) {
                    $updateStudent = mysqli_prepare($conn, 'UPDATE students SET faculty_supervisor_id = ? WHERE roll_no = ?');
                    if ($updateStudent) {
                        mysqli_stmt_bind_param($updateStudent, 'is', $supervisorId, $rollno);
                        mysqli_stmt_execute($updateStudent);
                        mysqli_stmt_close($updateStudent);
                    }
                } else {
                    $uQuery = mysqli_prepare($conn, 'SELECT u.u_id, sd.session FROM user u LEFT JOIN user_semester_detail sd ON u.u_name = sd.rollno WHERE u.u_name = ? LIMIT 1');
                    if ($uQuery) {
                        mysqli_stmt_bind_param($uQuery, 's', $rollno);
                        mysqli_stmt_execute($uQuery);
                        $uRes = mysqli_stmt_get_result($uQuery);
                        if ($uRow = mysqli_fetch_assoc($uRes)) {
                            $uId = $uRow['u_id'];
                            $session = $uRow['session'] ?: 'Unknown';
                            $insStudent = mysqli_prepare($conn, 'INSERT INTO students (user_id, roll_no, session, faculty_supervisor_id) VALUES (?, ?, ?, ?)');
                            if ($insStudent) {
                                mysqli_stmt_bind_param($insStudent, 'issi', $uId, $rollno, $session, $supervisorId);
                                mysqli_stmt_execute($insStudent);
                                mysqli_stmt_close($insStudent);
                            }
                        }
                        mysqli_stmt_close($uQuery);
                    }
                }
            }
            $assignedCount++;
        }

        $_SESSION['flash_message'] = "Faculty supervisor assigned successfully to $assignedCount student(s).";
        $_SESSION['flash_type'] = 'success';
        header('Location: index.php');
        exit;
    }

    if (isset($_POST['approve_letter'])) {
        $rollno = trim($_POST['rollno'] ?? '');
        if ($rollno !== '') {
            $stmt = mysqli_prepare($conn, 'UPDATE user_semester_detail SET letter_approved = 1 WHERE rollno = ?');
            mysqli_stmt_bind_param($stmt, 's', $rollno);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['flash_message'] = 'Internship letter approved successfully.';
            $_SESSION['flash_type'] = 'success';
        }
        header('Location: index.php');
        exit;
    }

    if (isset($_POST['reject_letter'])) {
        $rollno = trim($_POST['rollno'] ?? '');
        if ($rollno !== '') {
            $stmt = mysqli_prepare($conn, 'UPDATE user_semester_detail SET letter_approved = 0 WHERE rollno = ?');
            mysqli_stmt_bind_param($stmt, 's', $rollno);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $_SESSION['flash_message'] = 'Internship letter approval revoked.';
            $_SESSION['flash_type'] = 'success';
        }
        header('Location: index.php');
        exit;
    }
}

$studentsQuery = "
    SELECT 
        u.u_id,
        u.u_name AS student_rollno,
        p.name AS student_name,
        p.fname AS student_fname,
        p.cnic AS student_cnic,
        sd.department AS student_department,
        sd.program AS student_program,
        sd.semester AS student_semester,
        sd.session AS student_session,
        sd.letter_approved AS letter_approved,
        sup.u_id AS supervisor_id,
        sup_p.name AS supervisor_name,
        sup.u_name AS supervisor_username,
        ssd.org_name,
        ssd.org_address,
        ssd.org_category,
        ssd.org_type,
        ssd.org_contact_person AS contact_person_name,
        ssd.org_contact_cell AS contact_person_phone,
        ssd.org_contact_email AS contact_person_email,
        ssd.org_contact_designation AS contact_person_designation,
        ssd.site_supervisor_name,
        ssd.site_supervisor_cell AS site_supervisor_phone,
        ssd.site_supervisor_email,
        ssd.site_supervisor_designation,
        ssd.internship_title,
        ssd.internship_duration AS duration_weeks
    FROM user u
    LEFT JOIN user_profile p ON u.u_id = p.u_id
    LEFT JOIN user_semester_detail sd ON u.u_name = sd.rollno
    LEFT JOIN assign_faculty_supervisor afs ON u.u_name = afs.rollno AND afs.status = 1
    LEFT JOIN user sup ON afs.u_id = sup.u_id
    LEFT JOIN user_profile sup_p ON sup.u_id = sup_p.u_id
    LEFT JOIN site_supervisor_details ssd ON u.u_name = ssd.rollno
    WHERE u.u_type = 'STD' AND u.status = 1
    ORDER BY sd.session DESC, u.u_name ASC
";
$studentsResult = mysqli_query($conn, $studentsQuery);
$students = [];
if ($studentsResult) {
    while ($row = mysqli_fetch_assoc($studentsResult)) {
        $students[] = $row;
    }
}

// Fetch active supervisors for dropdown
$supervisorsResult = mysqli_query($conn, "
    SELECT u.u_id, u.u_name, p.name 
    FROM user u 
    LEFT JOIN user_profile p ON u.u_id = p.u_id 
    WHERE u.u_type = 'FSP' AND u.status = 1
    ORDER BY p.name ASC, u.u_name ASC
");
$supervisors = [];
if ($supervisorsResult) {
    while ($row = mysqli_fetch_assoc($supervisorsResult)) {
        $supervisors[] = $row;
    }
}

// Count how many students each supervisor has assigned
$supervisorCounts = [];
foreach ($students as $stud) {
    if (!empty($stud['supervisor_id'])) {
        $sid = $stud['supervisor_id'];
        if (!isset($supervisorCounts[$sid])) {
            $supervisorCounts[$sid] = 0;
        }
        $supervisorCounts[$sid]++;
    }
}

// Get distinct sessions for filtering
$sessions = [];
foreach ($students as $stud) {
    if (!empty($stud['student_session']) && !in_array($stud['student_session'], $sessions)) {
        $sessions[] = $stud['student_session'];
    }
}
sort($sessions);

// Compute registered candidates and unassigned student stats
$totalRegisteredCandidates = count($students);
$unassignedStudentsCount = 0;
foreach ($students as $stud) {
    if (empty($stud['supervisor_id'])) {
        $unassignedStudentsCount++;
    }
}
?>

<!-- FLASH MESSAGES -->
<?php if (!empty($flashMessage)): ?>
    <div class="card"
        style="margin-bottom: 20px; border-color: <?php echo $flashType === 'error' ? '#fecaca' : '#bbf7d0'; ?>;">
        <div class="card-body"
            style="padding: 14px 18px; color: <?php echo $flashType === 'error' ? '#991b1b' : '#166534'; ?>; background: <?php echo $flashType === 'error' ? '#fef2f2' : '#f0fdf4'; ?>;">
            <?php echo htmlspecialchars($flashMessage); ?>
        </div>
    </div>
<?php endif; ?>

<!-- ========================================== -->
<!-- TAB 0: WELCOME DASHBOARD                   -->
<!-- ========================================== -->
<div id="focal-welcome-dashboard" class="tab-content active">


    <div class="welcome-banner">
        <h2>Welcome back, <?php echo htmlspecialchars($focalPerson['full_name'] ?: 'Focal Person'); ?>!</h2>
        <p>Designation:
            <strong><?php echo htmlspecialchars($focalPerson['designation'] ?: 'Internship Focal Person'); ?></strong> |
            Email: <strong><?php echo htmlspecialchars($focalPerson['email'] ?: 'focal@uoh.edu.pk'); ?></strong></p>
    </div>

    <!-- KPI Summary Metrics for FP -->
    <div class="fsp-kpi-grid"
        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 24px;">
        <div class="fsp-kpi-card"
            style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px; display: flex; align-items: center; gap: 16px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
            <div class="fsp-kpi-icon kpi-blue"
                style="width: 50px; height: 50px; background: rgba(59, 130, 246, 0.1); color: #3b82f6; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                <i class="fa-solid fa-user-graduate"></i>
            </div>
            <div class="fsp-kpi-info">
                <h4 style="font-size: 20px; font-weight: 700; color: #1e293b; margin: 0;">
                    <?php echo $totalRegisteredCandidates; ?></h4>
                <p style="font-size: 13px; color: #64748b; margin: 2px 0 0 0;">Total Registered Candidates</p>
            </div>
        </div>

        <div class="fsp-kpi-card"
            style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 6px; padding: 20px; display: flex; align-items: center; gap: 16px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
            <div class="fsp-kpi-icon kpi-amber"
                style="width: 50px; height: 50px; background: rgba(245, 158, 11, 0.1); color: #f59e0b; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 20px;">
                <i class="fa-solid fa-user-slash"></i>
            </div>
            <div class="fsp-kpi-info">
                <h4 style="font-size: 20px; font-weight: 700; color: #1e293b; margin: 0;">
                    <?php echo $unassignedStudentsCount; ?></h4>
                <p style="font-size: 13px; color: #64748b; margin: 2px 0 0 0;">Unassigned Students</p>
            </div>
        </div>
    </div>

    <h3 style="font-size: 16px; font-weight: 600; color: #1e293b; margin-bottom: 15px;">Quick Actions</h3>
    <div class="action-boxes-container">
        <!-- Action 0: Profile Settings -->
        <div class="action-box" onclick="switchTab('focal-profile', document.getElementById('nav-item-focal-profile'))">
            <div class="action-icon-wrapper">
                <i class="fa-solid fa-user-gear"></i>
            </div>
            <h3>My Profile</h3>
            <p>View your profile details including designation and contact information.</p>
        </div>

        <!-- Action 1: Registered Students List -->
        <div class="action-box"
            onclick="switchTab('focal-dashboard', document.getElementById('nav-item-focal-dashboard'))">
            <div class="action-icon-wrapper">
                <i class="fa-solid fa-users-rectangle"></i>
            </div>
            <h3>Registered Students List</h3>
            <p>View registered student profiles, filter by session, and assign faculty supervisors.</p>
        </div>

        <!-- Action 2: Add Student -->
        <div class="action-box" onclick="openModal('addStudentModal')">
            <div class="action-icon-wrapper">
                <i class="fa-solid fa-user-plus"></i>
            </div>
            <h3>Add New Student</h3>
            <p>Directly register a new student profile in the Internship Management System.</p>
        </div>

        <!-- Action 3: Letters -->
        <div class="action-box" onclick="switchTab('focal-letters', document.getElementById('nav-item-focal-letters'))">
            <div class="action-icon-wrapper">
                <i class="fa-solid fa-file-contract"></i>
            </div>
            <h3>Internship Letters</h3>
            <p>Approve or revoke student recommendations and manage department-approved letters.</p>
        </div>
    </div>

    <!-- Announcements Layout -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px; margin-top: 24px;">
        <!-- Left Side: Active Announcements -->
        <div>
            <div class="card" style="margin: 0; height: 100%;">
                <div class="card-header"
                    style="background: #2e6652; color: #ffffff; padding: 12px 20px; font-size: 15px; font-weight: 600; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-bullhorn"></i> Active Announcements & Circulars
                </div>
                <div class="card-body" style="padding: 20px; max-height: 450px; overflow-y: auto;">
                    <?php if (empty($announcements)): ?>
                        <p style="font-size: 13.5px; color: #64748b; text-align: center; padding: 20px 0;">No active
                            announcements. Use the panel on the right to broadcast one.</p>
                    <?php else: ?>
                        <?php foreach ($announcements as $ann): ?>
                            <div
                                style="padding-bottom: 15px; margin-bottom: 15px; border-bottom: 1px solid #e2e8f0; position: relative;">
                                <div
                                    style="display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: #64748b; margin-bottom: 6px;">
                                    <span><i class="fa-solid fa-user-tie"></i>
                                        <?php echo htmlspecialchars($ann['created_by']); ?></span>
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <span><?php echo date('M d, Y', strtotime($ann['created_at'])); ?></span>
                                        <form action="" method="POST" style="margin: 0;"
                                            onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                                            <input type="hidden" name="announcement_id" value="<?php echo $ann['id']; ?>">
                                            <button type="submit" name="delete_announcement"
                                                style="background: #fee2e2; border: 1px solid #fecaca; color: #ef4444; cursor: pointer; font-size: 12px; padding: 4px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px; transition: all 0.2s ease;"
                                                title="Delete Announcement"
                                                onmouseover="this.style.background='#fecaca'; this.style.borderColor='#f87171'" 
                                                onmouseout="this.style.background='#fee2e2'; this.style.borderColor='#fecaca'">
                                                <i class="fa-solid fa-trash-can"></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <h4
                                    style="font-size: 15px; font-weight: 600; color: #1e293b; margin-bottom: 4px;">
                                    <?php echo htmlspecialchars($ann['title']); ?></h4>
                                <p style="font-size: 13.5px; color: #334155; line-height: 1.5; margin-bottom: 8px;">
                                    <?php echo nl2br(htmlspecialchars($ann['content'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right Side: Broadcast Form -->
        <div>
            <div class="card" style="margin: 0;">
                <div class="card-header"
                    style="background: #26294d; color: #ffffff; padding: 12px 20px; font-size: 15px; font-weight: 600;">
                    <i class="fa-solid fa-paper-plane"></i> Broadcast Circular
                </div>
                <div class="card-body" style="padding: 20px;">
                    <form action="" method="POST">
                        <div style="margin-bottom: 14px;">
                            <label
                                style="display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px;">Announcement
                                Title</label>
                            <input type="text" name="title" required
                                placeholder="e.g. Internship Report Deadline Extended"
                                style="width: 100%; padding: 8px 12px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 4px; outline: none; box-sizing: border-box;">
                        </div>
                        <div style="margin-bottom: 16px;">
                            <label
                                style="display: block; font-size: 13px; font-weight: 600; color: #475569; margin-bottom: 6px;">Announcement
                                Details</label>
                            <textarea name="content" required rows="5"
                                placeholder="Write guidelines, deadlines, or templates links here..."
                                style="width: 100%; padding: 8px 12px; font-size: 13px; border: 1px solid #cbd5e1; border-radius: 4px; outline: none; resize: vertical; box-sizing: border-box;"></textarea>
                        </div>
                        <button type="submit" name="add_announcement" class="btn-primary-action"
                            style="width: 100%; margin: 0; padding: 10px; font-size: 13px; display: flex; align-items: center; justify-content: center; gap: 8px;">
                            <i class="fa-solid fa-broadcast-tower"></i> Broadcast Announcement
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- TAB PROFILE: FOCAL PERSON PROFILE          -->
<!-- ========================================== -->
<div id="focal-profile" class="tab-content">
    <div class="student-profile-wrapper">
        <!-- LEFT COLUMN: Profile Sidebar -->
        <div class="student-profile-sidebar">
            <div class="profile-pic-frame">
                <?php if (!empty($accountDetails['profile_image'])): ?>
                    <img src="<?php echo htmlspecialchars($accountDetails['profile_image']); ?>" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">
                <?php else: ?>
                    <i class="fa-solid fa-user-tie"></i>
                <?php endif; ?>
            </div>

            <div class="student-name-title"><?php echo htmlspecialchars($focalPerson['full_name'] ?: 'Focal Person'); ?></div>
            <div class="student-dept-subtitle">
                <?php echo htmlspecialchars($focalPerson['designation'] ?: 'Internship Focal Person'); ?>
            </div>

            <hr class="profile-divider">
            <div class="sidebar-info-text">Department of IT / CS</div>

            <hr class="profile-divider">
            <div class="sidebar-info-text"><?php echo htmlspecialchars($_SESSION['username'] ?? 'FP-0001'); ?></div>


        </div>

        <!-- RIGHT COLUMN: Information Card / Form -->
        <div class="student-profile-main">
            <div class="info-card-header">Profile Information</div>
            <div class="info-card-body">
                <div class="info-row">
                    <label class="info-label">Full Name & Title</label>
                    <div class="info-value">
                        <input type="text" value="<?php echo htmlspecialchars($focalPerson['full_name'] ?? ''); ?>" readonly class="info-input-field">
                    </div>
                </div>

                <div class="info-row">
                    <label class="info-label">Designation</label>
                    <div class="info-value">
                        <input type="text" value="<?php echo htmlspecialchars($focalPerson['designation'] ?? ''); ?>" readonly class="info-input-field">
                    </div>
                </div>

                <div class="info-row">
                    <label class="info-label">Department/Division</label>
                    <div class="info-value">
                        <input type="text" value="Department of IT / CS" readonly class="info-input-field">
                    </div>
                </div>

                <div class="info-row">
                    <label class="info-label">Email Address</label>
                    <div class="info-value">
                        <input type="text" value="<?php echo htmlspecialchars($focalPerson['email'] ?? ''); ?>" readonly class="info-input-field">
                    </div>
                </div>

                <div class="info-row">
                    <label class="info-label">Phone Number</label>
                    <div class="info-value">
                        <input type="text" value="<?php echo htmlspecialchars($focalPerson['phone'] ?? ''); ?>" readonly class="info-input-field">
                    </div>
                </div>
                
                <div class="info-row">
                    <label class="info-label">Office Location</label>
                    <div class="info-value">
                        <input type="text" value="Office # 101, Academic Block" readonly class="info-input-field">
                    </div>
                </div>

                <div style="text-align: right; margin-top: 20px;">
                    <button type="button" onclick="openProfileEditModal()" class="btn-save-info" style="background: linear-gradient(135deg, #2e6652 0%, #26294d 100%);">Edit Profile</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit Profile Modal -->
<div id="profileEditModal" class="modal-overlay" style="display:none;">
    <div class="modal-container" style="max-width: 600px;">
        <div class="modal-header" style="background: linear-gradient(135deg, #2e6652 0%, #26294d 100%);">
            <h3><i class="fa-solid fa-user-pen"></i> Edit Profile Information</h3>
            <span class="modal-close" onclick="closeProfileEditModal()">&times;</span>
        </div>
        <div class="modal-body" style="max-height: 75vh; overflow-y: auto; padding-right: 8px;">
            <form action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="profile_image_base64" id="profile_image_base64">
                
                <div class="info-row">
                    <label class="info-label" for="profile_image_input">Profile Picture</label>
                    <div class="info-value">
                        <input type="file" id="profile_image_input" accept="image/*" class="info-input-field" style="background-color: #ffffff;">
                    </div>
                </div>

                <div class="info-row">
                    <label class="info-label" for="full_name">Full Name & Title</label>
                    <div class="info-value">
                        <input type="text" id="full_name" name="full_name" value="<?php echo htmlspecialchars($focalPerson['full_name'] ?? ''); ?>" class="info-input-field" style="background-color: #ffffff;">
                    </div>
                </div>

                <div class="info-row">
                    <label class="info-label" for="designation">Designation</label>
                    <div class="info-value">
                        <input type="text" id="designation" name="designation" value="<?php echo htmlspecialchars($focalPerson['designation'] ?? ''); ?>" class="info-input-field" style="background-color: #ffffff;">
                    </div>
                </div>

                <div class="info-row">
                    <label class="info-label" for="email">Email Address</label>
                    <div class="info-value">
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($focalPerson['email'] ?? ''); ?>" class="info-input-field" style="background-color: #ffffff;">
                    </div>
                </div>

                <div class="info-row">
                    <label class="info-label" for="phone">Phone Number</label>
                    <div class="info-value">
                        <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($focalPerson['phone'] ?? ''); ?>" class="info-input-field" style="background-color: #ffffff;">
                    </div>
                </div>

                <div class="modal-actions" style="display:flex; justify-content:flex-end; gap:10px; margin-top:20px;">
                    <button type="button" class="btn-cancel" onclick="closeProfileEditModal()">Cancel</button>
                    <button type="submit" name="save_fp_profile" class="btn-save-info">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- TAB: CHANGE PASSWORD (FOCAL PERSON)        -->
<!-- ========================================== -->
<div id="focal-change-password" class="tab-content" style="background-color: #e3efea; padding: 20px; border-radius: 6px;">
    <h2 style="font-size: 22px; font-weight: 600; color: #1e293b; margin-bottom: 20px;">Reset Password</h2>

    <div class="student-profile-wrapper" style="margin-top: 0;">
        <!-- LEFT COLUMN: Profile Sidebar -->
        <div class="student-profile-sidebar" style="border: 1px solid #c2dbd0;">
            <div class="profile-pic-frame"
                style="border-color: #2e6652; background: linear-gradient(135deg, #2e6652 0%, #26294d 100%);">
                <?php if (!empty($accountDetails['profile_image'])): ?>
                    <img src="<?php echo htmlspecialchars($accountDetails['profile_image']); ?>" style="width:100%; height:100%; border-radius:50%; object-fit:cover;">
                <?php else: ?>
                    <i class="fa-solid fa-user-tie"></i>
                <?php endif; ?>
            </div>

            <div class="student-name-title" style="color: #2e6652;">
                <?php echo htmlspecialchars($focalPerson['full_name'] ?: 'Focal Person'); ?>
            </div>
            <div class="student-dept-subtitle" style="color: #2e6652; font-weight: 600;">
                <?php echo htmlspecialchars($focalPerson['designation'] ?: 'Internship Focal Person'); ?>
            </div>

            <hr class="profile-divider">
            <div class="sidebar-info-text">Department of IT / CS</div>

            <hr class="profile-divider">
            <div class="sidebar-info-text"><?php echo htmlspecialchars($_SESSION['username'] ?? 'FP-0001'); ?></div>
        </div>

        <!-- RIGHT COLUMN: Reset Password Card -->
        <div class="student-profile-main" style="border: 1px solid #c2dbd0;">
            <div class="info-card-header" style="background: linear-gradient(135deg, #2e6652 0%, #26294d 100%); padding: 14px 20px;">Reset Password</div>
            <div class="info-card-body" style="padding: 25px 20px;">
                <form action="" method="POST">

                    <div class="info-row" style="margin-bottom: 20px;">
                        <label class="info-label" style="width: 25%; font-weight: bold; color: #2e6652;" for="old_password">Old Password</label>
                        <div class="info-value" style="width: 75%;">
                            <input type="password" id="old_password" name="old_password" required class="info-input-field" style="background-color: #ffffff; border: 1px solid #cbd5e1; width: 100%;" placeholder="Old Password">
                        </div>
                    </div>

                    <div class="info-row" style="margin-bottom: 20px;">
                        <label class="info-label" style="width: 25%; font-weight: bold; color: #2e6652;" for="new_password">New Password</label>
                        <div class="info-value" style="width: 75%;">
                            <input type="password" id="new_password" name="new_password" required class="info-input-field" style="background-color: #ffffff; border: 1px solid #cbd5e1; width: 100%;" placeholder="New Password">
                        </div>
                    </div>

                    <div class="info-row" style="margin-bottom: 20px;">
                        <label class="info-label" style="width: 25%; font-weight: bold; color: #2e6652;" for="confirm_password">Confirm Password</label>
                        <div class="info-value" style="width: 75%;">
                            <input type="password" id="confirm_password" name="confirm_password" required class="info-input-field" style="background-color: #ffffff; border: 1px solid #cbd5e1; width: 100%;" placeholder="Confirm Password">
                        </div>
                    </div>

                    <div style="display: flex; justify-content: flex-end; margin-top: 30px;">
                        <button type="submit" name="change_password" class="btn-submit" style="background: linear-gradient(135deg, #2e6652 0%, #26294d 100%); padding: 12px 25px; font-size: 15px;">Update Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MAIN SECTION: PORTAL DASHBOARD             -->
<!-- ========================================== -->
<div id="focal-dashboard" class="tab-content">

    <!-- Add Student and Bulk Actions Button top-right -->
    <div style="display: flex; justify-content: flex-end; margin-bottom: 15px; gap: 10px; align-items: center;">
        <button type="button" id="toggleBulkSelectionBtn" class="btn-primary-action" data-active="false"
            onclick="toggleBulkSelectionMode()"
            style="display: inline-flex; padding: 8px 16px; font-size: 13px; margin: 0; background: linear-gradient(135deg, #475569 0%, #334155 100%); cursor: pointer;">
            <i class="fa-solid fa-list-check"></i> Bulk Selection
        </button>
        <button type="button" id="bulkAssignBtn" class="btn-primary-action" disabled
            onclick="openBulkAssignModal()"
            style="display: none; padding: 8px 16px; font-size: 13px; margin: 0; background: linear-gradient(135deg, #10b981 0%, #059669 100%); opacity: 0.6; cursor: not-allowed; align-items: center; gap: 4px;">
            <i class="fa-solid fa-users"></i> Assign Selected (<span id="bulkCount">0</span>/30)
        </button>
        <button class="btn-primary-action" onclick="openModal('addStudentModal');"
            style="padding: 8px 16px; font-size: 13px; margin: 0; background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
            <i class="fa-solid fa-user-plus"></i> Add Student
        </button>
    </div>

    <!-- Section 2 — Session-wise Student Table & Assignment -->
    <div class="card">
        <div class="card-header"
            style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <span style="white-space: nowrap;"><i class="fa-solid fa-users-rectangle"></i> Registered Students List</span>
            <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 12px;">
                <!-- Session Filter and Search -->
                <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 8px;">
                    <input type="text" id="rollno-search" placeholder="Search by Roll No..." onkeyup="filterByRollNo(this.value);" style="padding: 4px 8px; font-size: 13px; border-radius: 4px; border: 1px solid #cbd5e1; outline: none; margin-right: 10px; width: 160px;">
                    <label for="session-filter-dropdown"
                        style="font-size: 13px; font-weight: bold; color: #fff; white-space: nowrap;">Session:</label>
                    <select id="session-filter-dropdown" onchange="filterSession(this.value);"
                        style="padding: 4px 8px; font-size: 13px; border-radius: 4px; border: 1px solid #cbd5e1; outline: none; color: #333; transition: all 0.25s ease;"
                        onmouseover="this.style.borderColor='#10b981'; this.style.backgroundColor='#f8fafc';"
                        onmouseout="this.style.borderColor='#cbd5e1'; this.style.backgroundColor='#fff';"
                        onfocus="this.style.borderColor='#10b981'; this.style.boxShadow='0 0 0 3px rgba(16, 185, 129, 0.25)';"
                        onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                        <option value="">All Sessions</option>
                        <?php
                        $endYear = (int) date('Y') + 4;
                        for ($y = $endYear; $y >= 2021; $y--) {
                            $fallSelected = ($y == 2026) ? 'selected' : '';
                            echo '<option value="Fall ' . $y . '" ' . $fallSelected . '>Fall ' . $y . '</option>';
                            echo '<option value="Spring ' . $y . '">Spring ' . $y . '</option>';
                        }
                        ?>
                    </select>

                    <label id="supervisor-filter-label" for="supervisor-filter-dropdown"
                        style="font-size: 13px; font-weight: bold; color: #fff; white-space: nowrap; margin-left: 6px;">Supervisor:</label>
                    <select id="supervisor-filter-dropdown" onchange="filterBySupervisor(this.value);"
                        style="padding: 4px 8px; font-size: 13px; border-radius: 4px; border: 1px solid #cbd5e1; outline: none; color: #333; transition: all 0.25s ease; max-width: 180px;"
                        onmouseover="this.style.borderColor='#10b981'; this.style.backgroundColor='#f8fafc';"
                        onmouseout="this.style.borderColor='#cbd5e1'; this.style.backgroundColor='#fff';"
                        onfocus="this.style.borderColor='#10b981'; this.style.boxShadow='0 0 0 3px rgba(16, 185, 129, 0.25)';"
                        onblur="this.style.borderColor='#cbd5e1'; this.style.boxShadow='none';">
                        <option value="all">All Supervisors</option>
                        <?php foreach ($supervisors as $sup): ?>
                            <?php
                            $c = $supervisorCounts[$sup['u_id']] ?? 0;
                            $display = htmlspecialchars($sup['name'] ?: $sup['u_name']) . " ($c/65)";
                            ?>
                            <option value="<?php echo $sup['u_id']; ?>"><?php echo $display; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <div class="card-body" style="padding: 0; overflow-x: auto;">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th class="bulk-select-col" style="width: 40px; text-align: center; display: none;"><input
                                type="checkbox" id="selectAllCheckbox"></th>
                        <th>Roll No</th>
                        <th>Name</th>
                        <th>Father Name</th>
                        <th>Department</th>
                        <th>Program</th>
                        <th>Semester</th>
                        <th>Session</th>
                        <th>Organization and Site Supervisor</th>
                        <th>Assigned Supervisor</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="student-table-body">
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="11" style="text-align: center; color: #64748b; padding: 20px;">No student records
                                found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $student): ?>
                            <tr data-session="<?php echo htmlspecialchars($student['student_session'] ?? ''); ?>"
                                data-assigned="<?php echo !empty($student['supervisor_id']) ? '1' : '0'; ?>"
                                data-supervisor-id="<?php echo htmlspecialchars($student['supervisor_id'] ?? ''); ?>">
                                <td class="bulk-select-col" style="text-align: center; display: none;"><input type="checkbox"
                                        class="student-checkbox"
                                        value="<?php echo htmlspecialchars($student['student_rollno']); ?>"></td>
                                <td><strong><?php echo htmlspecialchars($student['student_rollno']); ?></strong></td>
                                <td><?php echo htmlspecialchars($student['student_name'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($student['student_fname'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($student['student_department'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($student['student_program'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($student['student_semester'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($student['student_session'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php if (!empty($student['org_name'])): ?>
                                        <div style="font-weight: 600; color: #1e293b; font-size: 13px;">
                                            <?php echo htmlspecialchars($student['org_name']); ?>
                                            <?php if (!empty($student['duration_weeks'])): ?>
                                                (<?php echo (int) $student['duration_weeks']; ?> W)
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($student['site_supervisor_name'])): ?>
                                            <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                                                <i class="fa-solid fa-user-tie"></i> SS:
                                                <?php echo htmlspecialchars($student['site_supervisor_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <button type="button" class="btn-table-action"
                                            style="margin-top: 5px; padding: 4px 8px; font-size: 11px; background: #26294d; color: #ffffff; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;"
                                            onclick="openSupervisorModal(
                                                '<?php echo htmlspecialchars(addslashes($student['student_name'] ?? 'Student')); ?>',
                                                '<?php echo htmlspecialchars(addslashes($student['student_rollno'])); ?>',
                                                '<?php echo htmlspecialchars(addslashes($student['org_name'])); ?>',
                                                '<?php echo htmlspecialchars(addslashes($student['org_address'] ?? 'N/A')); ?>',
                                                '<?php echo htmlspecialchars(addslashes($student['org_category'] ?? 'General')); ?>',
                                                '<?php echo htmlspecialchars(addslashes($student['org_type'] ?? 'IT')); ?>',
                                                '<?php echo htmlspecialchars(addslashes($student['contact_person_name'] ?? 'N/A')); ?>',
                                                '<?php echo htmlspecialchars(addslashes($student['contact_person_phone'] ?? 'N/A')); ?>',
                                                '<?php echo htmlspecialchars(addslashes($student['contact_person_email'] ?? 'N/A')); ?>',
                                                '<?php echo htmlspecialchars(addslashes($student['contact_person_designation'] ?? 'N/A')); ?>',
                                                '<?php echo htmlspecialchars(addslashes($student['site_supervisor_name'] ?? 'Not Assigned')); ?>',
                                                '<?php echo htmlspecialchars(addslashes($student['site_supervisor_phone'] ?? 'N/A')); ?>',
                                                '<?php echo htmlspecialchars(addslashes($student['site_supervisor_email'] ?? 'N/A')); ?>',
                                                '<?php echo htmlspecialchars(addslashes($student['site_supervisor_designation'] ?? 'N/A')); ?>',
                                                '<?php echo htmlspecialchars(addslashes($student['internship_title'] ?? 'N/A')); ?>',
                                                '<?php echo (int) $student['duration_weeks']; ?>'
                                            )">
                                            <i class="fa-solid fa-user-tie"></i> View Details
                                        </button>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-style: italic; font-size: 12px;">Not Placed</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($student['supervisor_id'])): ?>
                                        <span class="badge-status badge-approved">
                                            <i class="fa-solid fa-user-tie"></i>
                                            <?php echo htmlspecialchars($student['supervisor_name'] ?: $student['supervisor_username']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-status badge-pending">Not Assigned</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn-table-action"
                                        data-rollno="<?php echo htmlspecialchars($student['student_rollno']); ?>"
                                        data-name="<?php echo htmlspecialchars($student['student_name'] ?? ''); ?>"
                                        data-dept="<?php echo htmlspecialchars($student['student_department'] ?? ''); ?>"
                                        data-session="<?php echo htmlspecialchars($student['student_session'] ?? ''); ?>"
                                        data-current-supervisor="<?php echo htmlspecialchars($student['supervisor_id'] ?? ''); ?>"
                                        onclick="openAssignSupervisorModal(this);">
                                        <i class="fa-solid fa-user-pen"></i> Assign Supervisor
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- SECTION: INTERNSHIP LETTER REPORTS         -->
<!-- ========================================== -->
<div id="focal-letters" class="tab-content">
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <span><i class="fa-solid fa-file-contract"></i> Student Internship Letters Report</span>
            <input type="text" id="letters-rollno-search" placeholder="Search by Roll No..." onkeyup="filterLettersByRollNo(this.value);" style="padding: 4px 8px; font-size: 13px; border-radius: 4px; border: 1px solid #cbd5e1; outline: none; width: 160px; color: #333;">
        </div>
        <div class="card-body" style="padding: 0; overflow-x: auto;">
            <table class="custom-table">
                <thead>
                    <tr>
                        <th>Sr#</th>
                        <th>Student Name</th>
                        <th>Roll No</th>
                        <th>Session</th>
                        <th>Letter Type</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="letters-table-body">
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: #64748b; padding: 20px;">No student records
                                found.</td>
                        </tr>
                    <?php else: ?>
                        <?php $sr = 1;
                        foreach ($students as $student): ?>
                            <tr>
                                <td><?php echo $sr++; ?></td>
                                <td><strong><?php echo htmlspecialchars($student['student_name'] ?? 'N/A'); ?></strong></td>
                                <td><strong><?php echo htmlspecialchars($student['student_rollno']); ?></strong></td>
                                <td><?php echo htmlspecialchars($student['student_session'] ?? 'N/A'); ?></td>
                                <td>Official Internship Request Letter</td>
                                <td>
                                    <?php if ((int) ($student['letter_approved'] ?? 0) === 1): ?>
                                        <span class="badge-status badge-approved">Approved</span>
                                    <?php else: ?>
                                        <span class="badge-status badge-pending">Pending Approval</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 5px; align-items: center;">
                                        <button class="btn-table-action" onclick="viewStudentLetter(
                                                    '<?php echo addslashes($student['student_name'] ?? ''); ?>',
                                                    '<?php echo addslashes($student['student_fname'] ?? ''); ?>',
                                                    '<?php echo addslashes($student['student_rollno'] ?? ''); ?>',
                                                    '<?php echo addslashes($student['student_session'] ?? ''); ?>',
                                                    '<?php echo addslashes($student['student_department'] ?? ''); ?>',
                                                    '<?php echo addslashes($student['student_program'] ?? ''); ?>'
                                                );">
                                            <i class="fa-solid fa-eye"></i> View Draft
                                        </button>
                                        <?php if ((int) ($student['letter_approved'] ?? 0) === 1): ?>
                                            <form action="" method="POST" style="margin: 0; display: inline;">
                                                <input type="hidden" name="rollno"
                                                    value="<?php echo htmlspecialchars($student['student_rollno']); ?>">
                                                <button type="submit" name="reject_letter" class="btn-table-action"
                                                    style="background-color: #dc2626;">
                                                    <i class="fa-solid fa-ban"></i> Revoke
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form action="" method="POST" style="margin: 0; display: inline;">
                                                <input type="hidden" name="rollno"
                                                    value="<?php echo htmlspecialchars($student['student_rollno']); ?>">
                                                <button type="submit" name="approve_letter" class="btn-table-action"
                                                    style="background-color: #16a34a;">
                                                    <i class="fa-solid fa-check"></i> Approve
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
        function filterLettersByRollNo(query) {
            query = query.toLowerCase().trim();
            const tbody = document.getElementById('letters-table-body');
            if (!tbody) return;
            const rows = tbody.querySelectorAll('tr:not(.letters-empty-message)');
            
            const existingEmpty = document.querySelector('.letters-empty-message');
            if (existingEmpty) existingEmpty.remove();

            let anyVisible = false;
            rows.forEach(row => {
                if (row.textContent.indexOf('No student records') !== -1) {
                    row.style.display = 'none';
                    return;
                }
                const rollTd = row.querySelector('td:nth-child(3)');
                if (rollTd) {
                    const text = rollTd.textContent.toLowerCase();
                    if (text.includes(query)) {
                        row.style.display = '';
                        anyVisible = true;
                    } else {
                        row.style.display = 'none';
                    }
                }
            });

            if (!anyVisible && rows.length > 0) {
                const tr = document.createElement('tr');
                tr.className = 'letters-empty-message';
                tr.innerHTML = '<td colspan="7" style="text-align: center; color: #64748b; padding: 25px;">No matching records found.</td>';
                tbody.appendChild(tr);
            }
        }
    </script>
</div>

<!-- ========================================== -->
<!-- POPUP MODAL: ADD STUDENT                   -->
<!-- ========================================== -->
<div id="addStudentModal" class="modal-overlay">
    <div class="modal-container" style="max-width: 600px;">
        <div class="modal-header">
            <h3><i class="fa-solid fa-user-plus"></i> Add New Student Record</h3>
            <span class="modal-close" onclick="closeModal('addStudentModal')">&times;</span>
        </div>
        <div class="modal-body">
            <form id="add-student-form" action="" method="POST" onsubmit="return validateAddStudentForm();">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="student_rollno">Roll No <span style="color: red;">*</span></label>
                        <input type="text" id="student_rollno" name="rollno" placeholder="e.g. S23-1234" required>
                    </div>
                    <div class="form-group">
                        <label for="student_name">Student Full Name <span style="color: red;">*</span></label>
                        <input type="text" id="student_name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="student_fname">Father Name <span style="color: red;">*</span></label>
                        <input type="text" id="student_fname" name="fname" required>
                    </div>
                    <div class="form-group">
                        <label for="student_depart">Department <span style="color: red;">*</span></label>
                        <select id="student_depart" name="depart" required>
                            <option value="">-- Select Department --</option>
                            <optgroup label="Faculty of Information Technology & Numerical Sciences">
                                <option value="Department of Information Technology / Computer Science">Department of Information Technology / Computer Science</option>
                                <option value="Department of Pure & Applied Mathematics">Department of Pure & Applied Mathematics</option>
                                <option value="Department of Physics">Department of Physics</option>
                            </optgroup>
                            <optgroup label="Faculty of Biological & Biomedical Sciences">
                                <option value="Department of Biology (Botany & Zoology)">Department of Biology (Botany & Zoology)</option>
                                <option value="Department of Medical Lab Sciences (MLT)">Department of Medical Lab Sciences (MLT)</option>
                                <option value="Department of Microbiology">Department of Microbiology</option>
                                <option value="Department of Public Health">Department of Public Health</option>
                            </optgroup>
                            <optgroup label="Faculty of Physical and Applied Sciences">
                                <option value="Department of Agricultural Sciences">Department of Agricultural Sciences</option>
                                <option value="Department of Food Science & Technology">Department of Food Science & Technology</option>
                                <option value="Department of Environmental Sciences">Department of Environmental Sciences</option>
                                <option value="Department of Earth Sciences / Geology">Department of Earth Sciences / Geology</option>
                                <option value="Department of Forestry & Wildlife Management">Department of Forestry & Wildlife Management</option>
                                <option value="Department of Chemistry">Department of Chemistry</option>
                            </optgroup>
                            <optgroup label="Faculty of Social & Administrative Sciences">
                                <option value="Department of Management Sciences">Department of Management Sciences</option>
                                <option value="Department of Economics">Department of Economics</option>
                                <option value="Department of Education">Department of Education</option>
                                <option value="Department of Psychology">Department of Psychology</option>
                                <option value="Department of Islamic & Religious Studies">Department of Islamic & Religious Studies</option>
                                <option value="Department of Linguistics & Literature">Department of Linguistics & Literature</option>
                                <option value="Department of History & Politics">Department of History & Politics</option>
                                <option value="Department of Law (Law College)">Department of Law (Law College)</option>
                                <option value="Department of Sports Science & Physical Education">Department of Sports Science & Physical Education</option>
                            </optgroup>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="student_program">Program <span style="color: red;">*</span></label>
                        <select id="student_program" name="program" required>
                            <option value="">-- Select Program --</option>
                            <optgroup label="Computing & Technology">
                                <option value="BS Computer Science">BS Computer Science</option>
                                <option value="BS Software Engineering">BS Software Engineering</option>
                                <option value="BS Artificial Intelligence">BS Artificial Intelligence</option>
                                <option value="BS Data Science">BS Data Science</option>
                                <option value="BS Telecom & Networking">BS Telecom & Networking</option>
                            </optgroup>
                            <optgroup label="Medical, Life & Health Sciences">
                                <option value="Doctor of Physical Therapy (DPT – 5 Years)">Doctor of Physical Therapy (DPT – 5 Years)</option>
                                <option value="BS Medical Lab Technology (MLT)">BS Medical Lab Technology (MLT)</option>
                                <option value="BS Microbiology">BS Microbiology</option>
                                <option value="BS Public Health">BS Public Health</option>
                                <option value="BS Biochemistry">BS Biochemistry</option>
                                <option value="BS Botany">BS Botany</option>
                                <option value="BS Zoology">BS Zoology</option>
                            </optgroup>
                            <optgroup label="Physical, Mathematical & Earth Sciences">
                                <option value="BS Physics">BS Physics</option>
                                <option value="BS Chemistry">BS Chemistry</option>
                                <option value="BS Mathematics">BS Mathematics</option>
                                <option value="BS Statistics">BS Statistics</option>
                                <option value="BS Geology">BS Geology</option>
                                <option value="BS Engineering Geology">BS Engineering Geology</option>
                                <option value="BS Environmental Sciences">BS Environmental Sciences</option>
                                <option value="BS Climate Change">BS Climate Change</option>
                                <option value="BS Disaster Management">BS Disaster Management</option>
                                <option value="BS Remote Sensing (RS) & Geographical Information System (GIS)">BS Remote Sensing (RS) & Geographical Information System (GIS)</option>
                            </optgroup>
                            <optgroup label="Agricultural Sciences, Forestry & Food">
                                <option value="BS Agronomy">BS Agronomy</option>
                                <option value="BS Horticulture">BS Horticulture</option>
                                <option value="BS Entomology">BS Entomology</option>
                                <option value="BS Plant Breeding & Genetics (PBG)">BS Plant Breeding & Genetics (PBG)</option>
                                <option value="BS Soil Science">BS Soil Science</option>
                                <option value="BS Agribusiness">BS Agribusiness</option>
                                <option value="BS Agricultural Biotechnology">BS Agricultural Biotechnology</option>
                                <option value="BS Food Science & Technology">BS Food Science & Technology</option>
                                <option value="BS Forestry">BS Forestry</option>
                                <option value="BS Wildlife Management">BS Wildlife Management</option>
                            </optgroup>
                            <optgroup label="Management & Business Studies">
                                <option value="Bachelor of Business Administration (BBA)">Bachelor of Business Administration (BBA)</option>
                                <option value="BS Accounting and Finance">BS Accounting and Finance</option>
                                <option value="BS Business Analytics">BS Business Analytics</option>
                                <option value="BS Public Administration & Governance">BS Public Administration & Governance</option>
                                <option value="BS Tourism & Hospitality Management">BS Tourism & Hospitality Management</option>
                            </optgroup>
                            <optgroup label="Social Sciences, Humanities & Law">
                                <option value="Bachelor of Laws (LLB – 5 Years)">Bachelor of Laws (LLB – 5 Years)</option>
                                <option value="BS Economics">BS Economics</option>
                                <option value="BS Psychology">BS Psychology</option>
                                <option value="BS Sociology">BS Sociology</option>
                                <option value="Bachelors of Education (B.Ed Hons – 4 Years)">Bachelors of Education (B.Ed Hons – 4 Years)</option>
                                <option value="BS English">BS English</option>
                                <option value="BS Urdu">BS Urdu</option>
                                <option value="BS Arabic">BS Arabic</option>
                                <option value="BS Islamic and Religious Studies">BS Islamic and Religious Studies</option>
                                <option value="BS Political Science">BS Political Science</option>
                                <option value="BS International Relations">BS International Relations</option>
                                <option value="BS Pakistan Studies">BS Pakistan Studies</option>
                                <option value="BS History">BS History</option>
                                <option value="BS Sport Science & Physical Education">BS Sport Science & Physical Education</option>
                            </optgroup>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="student_sem">Semester <span style="color: red;">*</span></label>
                        <input type="text" id="student_sem" name="sem" required>
                    </div>
                    <div class="form-group">
                        <label for="student_session">Session <span style="color: red;">*</span></label>
                        <select id="student_session" name="session" required>
                            <option value="">-- Select Session --</option>
                            <?php
                            $endYear = (int) date('Y') + 4;
                            for ($y = $endYear; $y >= 2021; $y--) {
                                echo '<option value="Fall ' . $y . '">Fall ' . $y . '</option>';
                                echo '<option value="Spring ' . $y . '">Spring ' . $y . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>
                <div style="margin-top: 20px; text-align: right;">
                    <button type="button" class="btn-cancel" onclick="closeModal('addStudentModal')">Cancel</button>
                    <button type="submit" name="add_student" class="btn-submit" style="margin-top: 0;">
                        <i class="fa-solid fa-save"></i> Save Student Record
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- SECTION 3: ASSIGN SUPERVISOR MODAL         -->
<!-- ========================================== -->
<div id="assignSupervisorModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3><i class="fa-solid fa-user-tie"></i> Assign Faculty Supervisor</h3>
            <span class="modal-close" onclick="closeModal('assignSupervisorModal')">&times;</span>
        </div>
        <div class="modal-body">
            <div
                style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 12px; margin-bottom: 15px;">
                <p style="font-size: 13px; margin-bottom: 5px;"><strong>Student Name:</strong> <span
                        id="modal_student_name"></span></p>
                <p style="font-size: 13px; margin-bottom: 5px;"><strong>Roll No:</strong> <span
                        id="modal_student_rollno"></span></p>
                <p style="font-size: 13px; margin-bottom: 5px;"><strong>Department:</strong> <span
                        id="modal_student_dept"></span></p>
                <p style="font-size: 13px; margin-bottom: 0;"><strong>Session:</strong> <span
                        id="modal_student_session"></span></p>
            </div>
            <form action="" method="POST">
                <input type="hidden" name="rollno" id="modal_input_rollno">
                <div class="form-group">
                    <label for="modal_supervisor_select">Select Faculty Supervisor <span
                            style="color: red;">*</span></label>
                    <select id="modal_supervisor_select" name="supervisor_id" required
                        style="width: 100%; margin-top: 5px;">
                        <option value="">-- Select Supervisor --</option>
                        <?php foreach ($supervisors as $supervisor): ?>
                            <?php
                            $supId = $supervisor['u_id'];
                            $count = isset($supervisorCounts[$supId]) ? $supervisorCounts[$supId] : 0;
                            ?>
                            <option value="<?php echo $supId; ?>">
                                <?php echo htmlspecialchars($supervisor['name'] ?: $supervisor['u_name']); ?> (Faculty
                                Supervisor) [<?php echo $count; ?> Assigned]
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-top: 20px; text-align: right;">
                    <button type="button" class="btn-cancel"
                        onclick="closeModal('assignSupervisorModal')">Cancel</button>
                    <button type="submit" name="assign_supervisor" class="btn-submit" style="margin-top: 0;">Assign
                        Supervisor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: BULK ASSIGN SUPERVISOR              -->
<!-- ========================================== -->
<div id="bulkAssignSupervisorModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3><i class="fa-solid fa-users"></i> Bulk Assign Faculty Supervisor</h3>
            <span class="modal-close" onclick="closeModal('bulkAssignSupervisorModal')">&times;</span>
        </div>
        <div class="modal-body">
            <div
                style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 12px; margin-bottom: 15px;">
                <p style="font-size: 14px; margin-bottom: 0;"><strong>Selected Students:</strong> <span
                        id="modal_bulk_count">0</span></p>
            </div>
            <form action="" method="POST" id="bulkAssignForm">
                <div id="bulk_hidden_inputs"></div>
                <div class="form-group">
                    <label for="modal_bulk_supervisor_select">Select Faculty Supervisor <span
                            style="color: red;">*</span></label>
                    <select id="modal_bulk_supervisor_select" name="supervisor_id" required
                        style="width: 100%; margin-top: 5px;">
                        <option value="">-- Select Supervisor --</option>
                        <?php foreach ($supervisors as $supervisor): ?>
                            <?php
                            $supId = $supervisor['u_id'];
                            $count = isset($supervisorCounts[$supId]) ? $supervisorCounts[$supId] : 0;
                            ?>
                            <option value="<?php echo $supId; ?>">
                                <?php echo htmlspecialchars($supervisor['name'] ?: $supervisor['u_name']); ?> (Faculty
                                Supervisor) [<?php echo $count; ?> Assigned]
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-top: 20px; text-align: right;">
                    <button type="button" class="btn-cancel"
                        onclick="closeModal('bulkAssignSupervisorModal')">Cancel</button>
                    <button type="submit" name="bulk_assign_supervisor" class="btn-submit" style="margin-top: 0;">Assign
                        Supervisor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: INTERNSHIP RECOMMENDATION LETTER     -->
<!-- ========================================== -->

<div id="letterViewModal" class="modal-overlay">
    <div class="modal-container" style="max-width: 650px;">
        <div class="modal-header">
            <h3><i class="fa-solid fa-file-contract"></i> Internship Recommendation Letter</h3>
            <span class="modal-close" onclick="closeModal('letterViewModal')">&times;</span>
        </div>
        <div class="modal-body" style="max-height: 75vh; overflow-y: auto;">
            <div class="letter-paper"
                style="padding: 40px; font-family: 'Times New Roman', Times, serif; color: #000; background: #fff; max-width: 800px; margin: 0 auto; line-height: 1.6;">
                <div style="display: flex; align-items: center; margin-bottom: 20px;">
                    <div style="flex-shrink: 0; width: 140px; text-align: left;">
                        <img src="assets/img/uoh_logo.png" alt="UoH Logo"
                            style="width: 80px; height: 80px; object-fit: contain;">
                    </div>
                    <div style="flex-grow: 1; text-align: center; margin-left: -50px;">
                        <h2
                            style="font-size: 16px; color: #000; text-transform: uppercase; margin: 0; font-family: 'Times New Roman', Times, serif; font-weight: bold; text-decoration: underline;">
                            DEPARTMENT OF INFORMATION TECHNOLOGY</h2>
                        <p
                            style="font-size: 15px; color: #000; margin: 2px 0; font-family: 'Times New Roman', Times, serif; font-weight: bold;">
                            The University of Haripur, Khyber Pakhtunkhwa</p>
                        <p style="font-size: 14px; margin: 0; font-family: 'Times New Roman', Times, serif;"><a
                                href="http://www.uoh.edu.pk"
                                style="color: blue; text-decoration: underline;">www.uoh.edu.pk</a></p>
                    </div>
                </div>

                <div
                    style="display: flex; justify-content: space-between; font-size: 14px; color: #000; margin-bottom: 30px;">
                    <div><span style="text-decoration: underline;">F. No. UoH/IT/</span>
                        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</div>
                    <div><span style="text-decoration: underline;">Dated: <span id="let_date"></span></span></div>
                </div>

                <h3
                    style="text-align: center; font-weight: bold; font-size: 18px; margin-bottom: 25px; font-family: 'Times New Roman', Times, serif;">
                    To Whom It May Concern,</h3>

                <p style="font-size: 15px; color: #000; margin-bottom: 15px; text-align: justify;">
                    This is to certify that <strong><span id="let_student_name"></span></strong>, bearing Student ID
                    <strong><span id="let_student_rollno"></span></strong>, is currently enrolled in the <strong><span
                            id="let_student_program"></span></strong> program at University of Haripur.
                </p>

                <p style="font-size: 15px; color: #000; margin-bottom: 15px; text-align: justify;">
                    As part of the degree requirements of the <span id="let_student_program_short"
                        style="font-weight: bold;"></span> program, students are required to complete an industry
                    internship. This internship is intended to provide practical exposure and hands-on experience
                    related to their field of study, helping them bridge the gap between theoretical knowledge and
                    real-world applications.
                </p>

                <p style="font-size: 15px; color: #000; margin-bottom: 15px; text-align: justify;">
                    We kindly request your organization to consider <strong><span
                            id="let_student_name2"></span></strong> for an internship opportunity in your esteemed
                    organization. The duration of the internship is <strong>6-8 weeks</strong>, and it is expected to be
                    conducted during the <strong><span id="let_student_session"></span></strong> session of the academic
                    calendar. Upon completion, students are required to submit an internship report and obtain an
                    evaluation from the host organization.
                </p>

                <p style="font-size: 15px; color: #000; margin-bottom: 15px; text-align: justify;">
                    We would greatly appreciate your support in providing internship to <strong><span
                            id="let_student_name3"></span></strong> with an opportunity to gain valuable experience in
                    the professional field.
                </p>

                <p style="font-size: 15px; color: #000; margin-bottom: 40px; text-align: left;">
                    If you require any further information, please feel free to contact us.
                </p>

                <div style="font-size: 15px; color: #000; text-align: left;">
                    <p style="margin-bottom: 60px;">Sincerely,</p>
                    <p style="margin: 0;"><span id="let_fp_designation"></span></p>
                    <p style="margin: 0;"><span id="let_fp_name"></span></p>
                    <p style="margin: 0;">Department of <span id="let_fp_dept"></span></p>
                    <p style="margin: 0;">Email: <span id="let_fp_email"></span></p>
                </div>
            </div>

            <div style="margin-top: 20px; text-align: right; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn-cancel" onclick="closeModal('letterViewModal')">Close</button>
                <button type="button" class="btn-submit" style="margin-top: 0;" onclick="window.location.href='downloads/download_letter.php?rollno=' + document.getElementById('let_student_rollno').innerText">
                    <i class="fa-solid fa-download"></i> Download PDF
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: SITE SUPERVISOR DETAILS POPUP       -->
<!-- ========================================== -->
<div id="supervisorDetailsModal" class="modal-overlay"
    style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="modal-container"
        style="background: #fff; width: 90%; max-width: 700px; border-radius: 6px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); overflow: hidden; position: relative;">
        <div class="modal-header"
            style="background: #1e293b; color: #fff; padding: 14px 18px; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 16px;"><i class="fa-solid fa-user-tie"></i> Placement & Site Supervisor
                Details</h3>
            <span class="modal-close" onclick="closeSupervisorModal()"
                style="cursor: pointer; font-size: 22px; font-weight: bold;">&times;</span>
        </div>
        <div class="modal-body" style="padding: 18px; max-height: calc(100vh - 200px); overflow-y: auto;">

            <div
                style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 12px 15px; margin-bottom: 18px;">
                <div style="font-weight: 700; color: #1e293b; font-size: 15px;" id="sv_student_name"></div>
                <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                    Roll No: <span id="sv_student_roll" style="font-weight: 600; color: #334155;"></span>
                </div>
            </div>

            <!-- Organization Card -->
            <div class="org-info-card"
                style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 15px; background: #ffffff; margin-bottom: 15px;">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px; margin-bottom: 10px;">
                    <h4 style="font-size: 15px; font-weight: 700; color: #1e293b; margin: 0;">
                        <i class="fa-solid fa-building text-success" style="margin-right: 5px;"></i> <span
                            id="sv_org_name"></span>
                    </h4>
                    <span id="sv_org_type_badge" class="status-pill"
                        style="font-size: 10px; background-color: #3b82f6; color: white; padding: 2px 6px; border-radius: 10px;"></span>
                </div>
                <p style="margin-bottom: 5px; font-size: 13px;"><strong>Category:</strong> <span
                        id="sv_org_category"></span></p>
                <p style="margin-bottom: 5px; font-size: 13px;"><strong>Address:</strong> <span
                        id="sv_org_address"></span></p>
            </div>

            <div class="org-details-grid"
                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px; font-size: 13px;">
                <!-- Organization Contact Person Block -->
                <div class="org-detail-block"
                    style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 12px;">
                    <h5
                        style="font-size: 11px; text-transform: uppercase; color: #64748b; margin-bottom: 8px; font-weight: 700; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px;">
                        <i class="fa-solid fa-address-book"></i> Organization Contact Person
                    </h5>
                    <p style="margin-bottom: 4px;"><strong>Name:</strong> <span id="sv_cp_name"></span></p>
                    <p style="margin-bottom: 4px;"><strong>Designation:</strong> <span id="sv_cp_designation"></span>
                    </p>
                    <p style="margin-bottom: 4px;"><strong>Cell No:</strong> <span id="sv_cp_phone"></span></p>
                    <p style="margin-bottom: 4px;"><strong>Email:</strong> <span id="sv_cp_email"></span></p>
                </div>

                <!-- Site Supervisor Block -->
                <div class="org-detail-block"
                    style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 12px;">
                    <h5
                        style="font-size: 11px; text-transform: uppercase; color: #64748b; margin-bottom: 8px; font-weight: 700; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px;">
                        <i class="fa-solid fa-user-tie"></i> Assigned Site Supervisor
                    </h5>
                    <p style="margin-bottom: 4px;"><strong>Name:</strong> <span id="sv_ss_name"></span></p>
                    <p style="margin-bottom: 4px;"><strong>Designation:</strong> <span id="sv_ss_designation"></span>
                    </p>
                    <p style="margin-bottom: 4px;"><strong>Cell No:</strong> <span id="sv_ss_phone"></span></p>
                    <p style="margin-bottom: 4px;"><strong>Email:</strong> <span id="sv_ss_email"></span></p>
                </div>
            </div>

            <!-- Project Placement Block -->
            <div class="org-detail-block"
                style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 12px; margin-top: 15px;">
                <h5
                    style="font-size: 11px; text-transform: uppercase; color: #64748b; margin-bottom: 8px; font-weight: 700; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px;">
                    <i class="fa-solid fa-briefcase"></i> Placed Student & Project
                </h5>
                <p style="margin-bottom: 4px;"><strong>Project Title:</strong> <span id="sv_project_title"></span></p>
                <p style="margin-bottom: 4px;"><strong>Duration:</strong> <span id="sv_project_duration"></span> Weeks
                </p>
            </div>

            <div style="margin-top: 18px; text-align: right;">
                <button type="button" class="btn-cancel" onclick="closeSupervisorModal()"
                    style="padding: 6px 14px; margin-top: 0;">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    // Open Modal
    function openModal(modalId) {
        document.getElementById(modalId).style.display = 'flex';
    }

    // Close Modal
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
    }

    // Open and populate assignment modal
    function openAssignSupervisorModal(button) {
        const rollno = button.getAttribute('data-rollno');
        const name = button.getAttribute('data-name');
        const dept = button.getAttribute('data-dept');
        const session = button.getAttribute('data-session');
        const currentSupervisor = button.getAttribute('data-current-supervisor');

        document.getElementById('modal_student_name').innerText = name || 'N/A';
        document.getElementById('modal_student_rollno').innerText = rollno || 'N/A';
        document.getElementById('modal_student_dept').innerText = dept || 'N/A';
        document.getElementById('modal_student_session').innerText = session || 'N/A';
        document.getElementById('modal_input_rollno').value = rollno;

        document.getElementById('modal_supervisor_select').value = currentSupervisor || '';

        openModal('assignSupervisorModal');
    }

    // Client-side validation for student form
    function validateAddStudentForm() {
        const rollno = document.getElementById('student_rollno').value.trim();

        // Roll number check: Format e.g. S23-1234 or F26-0001
        const rollRegex = /^[a-zA-Z]\d{2}-\d{4}$/;
        if (!rollRegex.test(rollno)) {
            alert('Invalid Roll No format. Expected format: e.g. S23-1234 or F26-0001');
            return false;
        }

        return true;
    }

    // Dynamic Program Dropdown based on Department
    document.addEventListener('DOMContentLoaded', function() {
        const deptSelect = document.getElementById('student_depart');
        const progSelect = document.getElementById('student_program');
        if (!deptSelect || !progSelect) return;

        // Store original optgroups
        const allOptGroups = Array.from(progSelect.querySelectorAll('optgroup')).map(og => og.cloneNode(true));
        
        deptSelect.addEventListener('change', function() {
            const selectedDept = this.value;
            // Clear current programs
            progSelect.innerHTML = '<option value="">-- Select Program --</option>';
            
            if (!selectedDept) {
                // If no department selected, show all
                allOptGroups.forEach(og => progSelect.appendChild(og.cloneNode(true)));
                return;
            }

            // Determine faculty category based on department
            let targetCategory = '';
            
            if (['Department of Information Technology / Computer Science'].includes(selectedDept)) {
                targetCategory = 'Computing & Technology';
            } else if (['Department of Pure & Applied Mathematics', 'Department of Physics', 'Department of Environmental Sciences', 'Department of Earth Sciences / Geology', 'Department of Chemistry'].includes(selectedDept)) {
                targetCategory = 'Physical, Mathematical & Earth Sciences';
            } else if (['Department of Biology (Botany & Zoology)', 'Department of Medical Lab Sciences (MLT)', 'Department of Microbiology', 'Department of Public Health'].includes(selectedDept)) {
                targetCategory = 'Medical, Life & Health Sciences';
            } else if (['Department of Agricultural Sciences', 'Department of Food Science & Technology', 'Department of Forestry & Wildlife Management'].includes(selectedDept)) {
                targetCategory = 'Agricultural Sciences, Forestry & Food';
            } else if (['Department of Management Sciences'].includes(selectedDept)) {
                targetCategory = 'Management & Business Studies';
            } else if (['Department of Economics', 'Department of Education', 'Department of Psychology', 'Department of Islamic & Religious Studies', 'Department of Linguistics & Literature', 'Department of History & Politics', 'Department of Law (Law College)', 'Department of Sports Science & Physical Education'].includes(selectedDept)) {
                targetCategory = 'Social Sciences, Humanities & Law';
            }

            if (targetCategory) {
                const matchingGroup = allOptGroups.find(og => og.label === targetCategory);
                if (matchingGroup) {
                    progSelect.appendChild(matchingGroup.cloneNode(true));
                }
            } else {
                // Fallback, show all
                allOptGroups.forEach(og => progSelect.appendChild(og.cloneNode(true)));
            }
        });
    });

    let currentAssignmentFilter = 'all';
    let currentSupervisorFilter = 'all';
    let currentSearchQuery = '';

    function filterByRollNo(query) {
        currentSearchQuery = query.toLowerCase().trim();
        const sessionVal = document.getElementById('session-filter-dropdown') ? document.getElementById('session-filter-dropdown').value : 'all';
        applyFilters(sessionVal, currentAssignmentFilter, currentSupervisorFilter);
    }

    function setAssignmentFilter(filterType) {
        currentAssignmentFilter = filterType;
        const sessionVal = document.getElementById('session-filter-dropdown') ? document.getElementById('session-filter-dropdown').value : 'all';

        const supDropdown = document.getElementById('supervisor-filter-dropdown');
        const supLabel = document.getElementById('supervisor-filter-label');
        if (supDropdown && supLabel) {
            if (filterType === 'unassigned') {
                supDropdown.value = 'all';
                currentSupervisorFilter = 'all';
                supDropdown.style.display = 'none';
                supLabel.style.display = 'none';
            } else {
                supDropdown.style.display = 'inline-block';
                supLabel.style.display = 'inline-block';
            }
        }

        applyFilters(sessionVal, currentAssignmentFilter, currentSupervisorFilter);

        const toggleBtn = document.getElementById('toggleBulkSelectionBtn');
        if (toggleBtn) {
            if (filterType === 'unassigned') {
                toggleBtn.style.display = 'inline-flex';
            } else {
                toggleBtn.style.display = 'none';
                if (toggleBtn.getAttribute('data-active') === 'true') {
                    if (typeof toggleBulkSelectionMode === 'function') {
                        toggleBulkSelectionMode();
                    }
                }
            }
        }
    }

    function filterSession(sessionValue) {
        applyFilters(sessionValue, currentAssignmentFilter, currentSupervisorFilter);
    }

    function filterBySupervisor(supervisorId) {
        currentSupervisorFilter = supervisorId;
        const sessionVal = document.getElementById('session-filter-dropdown') ? document.getElementById('session-filter-dropdown').value : 'all';
        applyFilters(sessionVal, currentAssignmentFilter, currentSupervisorFilter);
    }

    function applyFilters(sessionValue, assignmentFilter, supervisorFilter) {
        const tbody = document.getElementById('student-table-body');
        if (!tbody) return;

        const rows = tbody.querySelectorAll('tr:not(.empty-message-row)');

        // Remove existing empty message row if any
        const existingEmptyRow = document.querySelector('.empty-message-row');
        if (existingEmptyRow) {
            existingEmptyRow.remove();
        }

        const legacyEmptyRow = document.getElementById('filter-empty-row');
        if (legacyEmptyRow) legacyEmptyRow.remove();

        let anyVisible = false;
        rows.forEach(row => {
            const rowSession = row.getAttribute('data-session');
            const rowAssigned = row.getAttribute('data-assigned');

            // Structural empty row or other row without data-session
            if (!rowSession && row.textContent.indexOf('No student records') !== -1) {
                row.style.display = 'none';
                return;
            }

            // Session match
            let sessionMatch = false;
            if (sessionValue === 'all' || sessionValue === '') {
                sessionMatch = true;
            } else if (rowSession) {
                const normSession = sessionValue.replace(/\s+/g, '-').toLowerCase();
                const normRowSession = rowSession.replace(/\s+/g, '-').toLowerCase();
                if (normSession === normRowSession) {
                    sessionMatch = true;
                }
            }

            // Assignment match
            let assignmentMatch = false;
            if (assignmentFilter === 'all') {
                assignmentMatch = true;
            } else if (assignmentFilter === 'assigned' && rowAssigned === '1') {
                assignmentMatch = true;
            } else if (assignmentFilter === 'unassigned' && rowAssigned === '0') {
                assignmentMatch = true;
            }

            // Supervisor match
            const rowSupervisor = row.getAttribute('data-supervisor-id');
            let supervisorMatch = false;
            if (supervisorFilter === 'all' || supervisorFilter === '') {
                supervisorMatch = true;
            } else if (rowSupervisor === supervisorFilter) {
                supervisorMatch = true;
            }

            // Search match (Check rollno which is in 2nd td, but we can just check the whole row text for robustness)
            let searchMatch = true;
            if (currentSearchQuery !== '') {
                const rowText = row.textContent.toLowerCase();
                if (!rowText.includes(currentSearchQuery)) {
                    searchMatch = false;
                }
            }

            if (sessionMatch && assignmentMatch && supervisorMatch && searchMatch) {
                row.style.display = '';
                anyVisible = true;
            } else {
                row.style.display = 'none';
            }
        });

        // If no rows are visible, inject a friendly message
        if (!anyVisible && rows.length > 0) {
            const emptyTr = document.createElement('tr');
            emptyTr.className = 'empty-message-row';
            let msg = 'No students found for the selected filters.';
            emptyTr.innerHTML = '<td colspan="11" style="text-align: center; color: #64748b; padding: 25px;">' + msg + '</td>';
            tbody.appendChild(emptyTr);
        }

        // Uncheck all when filtering
        const selectAllCb = document.getElementById('selectAllCheckbox');
        if (selectAllCb) selectAllCb.checked = false;
        document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = false);
        if (typeof updateBulkButtonState === 'function') {
            updateBulkButtonState();
        }
    }
    // Populate and show the recommendation letter
    function viewStudentLetter(name, fname, rollno, session, department, program) {
        document.getElementById('let_student_name').innerText = name || '[Student Name]';
        document.getElementById('let_student_name2').innerText = name || '[Student Name]';
        document.getElementById('let_student_name3').innerText = name || '[Student Name]';
        document.getElementById('let_student_rollno').innerText = rollno || '[Roll No]';

        let sessionParts = (session || 'Summer / Fall / Spring').split(' ');
        let sessionName = sessionParts[0]; // e.g. "Fall" from "Fall 2026"
        if (!sessionName) sessionName = 'Summer / Fall / Spring';
        document.getElementById('let_student_session').innerText = sessionName;

        document.getElementById('let_student_program').innerText = program || 'Bachelor of Science in Artificial Intelligence';

        let shortProg = 'BS Artificial Intelligence';
        if (program) {
            shortProg = program.replace('Bachelor of Science in ', 'BS ').replace('Bachelor of Science ', 'BS ');
        }
        document.getElementById('let_student_program_short').innerText = shortProg;

        // Inject Focal Person details
        document.getElementById('let_fp_name').innerText = '<?php echo addslashes($focalPerson["full_name"] ?? "Focal Person"); ?>';
        document.getElementById('let_fp_designation').innerText = '<?php echo addslashes($focalPerson["designation"] ?? "Internship Focal Person"); ?>';
        document.getElementById('let_fp_email').innerText = '<?php echo addslashes($focalPerson["email"] ?? "focal@uoh.edu.pk"); ?>';
        let deptVal = department || '';
        document.getElementById('let_fp_dept').innerText = (deptVal.toLowerCase().indexOf('computer science') !== -1) ? 'CS' : 'IT';

        let today = new Date();
        let dd = String(today.getDate()).padStart(2, '0');
        let mm = String(today.getMonth() + 1).padStart(2, '0'); //January is 0!
        let yyyy = today.getFullYear();
        document.getElementById('let_date').innerText = dd + '/' + mm + '/' + yyyy;

        openModal('letterViewModal');
    }

    // Close modal when clicking on overlay
    window.onclick = function (event) {
        if (event.target.classList.contains('modal-overlay')) {
            event.target.style.display = 'none';
        }
    };

    function openSupervisorModal(stdName, rollNo, orgName, orgAddress, orgCategory, orgType, cpName, cpPhone, cpEmail, cpDesignation, ssName, ssPhone, ssEmail, ssDesignation, projTitle, duration) {
        document.getElementById('sv_student_name').textContent = stdName;
        document.getElementById('sv_student_roll').textContent = rollNo;
        document.getElementById('sv_org_name').textContent = orgName;
        document.getElementById('sv_org_address').textContent = orgAddress;
        document.getElementById('sv_org_category').textContent = orgCategory;
        document.getElementById('sv_org_type_badge').textContent = orgType;

        document.getElementById('sv_cp_name').textContent = cpName;
        document.getElementById('sv_cp_designation').textContent = cpDesignation;
        document.getElementById('sv_cp_phone').textContent = cpPhone;
        document.getElementById('sv_cp_email').textContent = cpEmail;

        document.getElementById('sv_ss_name').textContent = ssName;
        document.getElementById('sv_ss_designation').textContent = ssDesignation;
        document.getElementById('sv_ss_phone').textContent = ssPhone;
        document.getElementById('sv_ss_email').textContent = ssEmail;

        document.getElementById('sv_project_title').textContent = projTitle;
        document.getElementById('sv_project_duration').textContent = duration;

        document.getElementById('supervisorDetailsModal').style.display = 'flex';
    }

    function closeSupervisorModal() {
        document.getElementById('supervisorDetailsModal').style.display = 'none';
    }

    document.addEventListener("DOMContentLoaded", function () {
        const sessionFilter = document.getElementById('session-filter-dropdown');
        if (sessionFilter) {
            // Filter by selected value (which defaults to 'Fall 2026')
            filterSession(sessionFilter.value);
        }

        // Bulk Assignment Checkbox Logic
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        const studentCheckboxes = document.querySelectorAll('.student-checkbox');
        const bulkAssignBtn = document.getElementById('bulkAssignBtn');
        const bulkCountDisplay = document.getElementById('bulkCount');
        const MAX_SELECTION = 30;

        function updateBulkButtonState() {
            const checkedCount = document.querySelectorAll('.student-checkbox:checked').length;
            bulkCountDisplay.textContent = checkedCount;
            bulkAssignBtn.disabled = !(checkedCount > 0 && checkedCount <= MAX_SELECTION);
            bulkAssignBtn.style.opacity = (checkedCount > 0 && checkedCount <= MAX_SELECTION) ? '1' : '0.6';
            bulkAssignBtn.style.cursor = (checkedCount > 0 && checkedCount <= MAX_SELECTION) ? 'pointer' : 'not-allowed';
        }

        window.toggleBulkSelectionMode = function () {
            const toggleBtn = document.getElementById('toggleBulkSelectionBtn');
            const assignBtn = document.getElementById('bulkAssignBtn');
            const cols = document.querySelectorAll('.bulk-select-col');

            const isCurrentlyActive = toggleBtn.getAttribute('data-active') === 'true';

            if (!isCurrentlyActive) {
                toggleBtn.setAttribute('data-active', 'true');
                toggleBtn.innerHTML = '<i class="fa-solid fa-xmark"></i> Cancel Bulk Selection';
                toggleBtn.style.background = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
                assignBtn.style.display = 'inline-flex';
                cols.forEach(col => col.style.display = 'table-cell');
            } else {
                toggleBtn.setAttribute('data-active', 'false');
                toggleBtn.innerHTML = '<i class="fa-solid fa-list-check"></i> Bulk Selection';
                toggleBtn.style.background = 'linear-gradient(135deg, #475569 0%, #334155 100%)';
                assignBtn.style.display = 'none';
                cols.forEach(col => col.style.display = 'none');

                document.getElementById('selectAllCheckbox').checked = false;
                document.querySelectorAll('.student-checkbox').forEach(cb => cb.checked = false);
                updateBulkButtonState();
            }
        };

        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', function () {
                let checkedCount = document.querySelectorAll('.student-checkbox:checked').length;
                const isChecked = this.checked;

                studentCheckboxes.forEach(checkbox => {
                    // Only modify checkboxes in visible rows
                    const row = checkbox.closest('tr');
                    if (row && row.style.display !== 'none') {
                        if (isChecked) {
                            if (!checkbox.checked && checkedCount < MAX_SELECTION) {
                                checkbox.checked = true;
                                checkedCount++;
                            }
                        } else {
                            checkbox.checked = false;
                        }
                    }
                });

                if (isChecked && checkedCount === MAX_SELECTION) {
                    alert('Maximum selection limit of 30 students reached.');
                }
                updateBulkButtonState();
            });
        }

        studentCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function () {
                const checkedCount = document.querySelectorAll('.student-checkbox:checked').length;
                if (this.checked && checkedCount > MAX_SELECTION) {
                    this.checked = false;
                    alert('You can only select up to 30 students at a time.');
                }
                updateBulkButtonState();

                // Update 'select all' checkbox state
                const visibleCheckboxes = Array.from(studentCheckboxes).filter(cb => {
                    const row = cb.closest('tr');
                    return row && row.style.display !== 'none';
                });
                const allVisibleChecked = visibleCheckboxes.length > 0 && visibleCheckboxes.every(cb => cb.checked);
                if (selectAllCheckbox) selectAllCheckbox.checked = allVisibleChecked;
            });
        });
    });

    function openBulkAssignModal() {
        const checkedCheckboxes = document.querySelectorAll('.student-checkbox:checked');
        if (checkedCheckboxes.length === 0) return;

        document.getElementById('modal_bulk_count').textContent = checkedCheckboxes.length;

        const hiddenInputsContainer = document.getElementById('bulk_hidden_inputs');
        hiddenInputsContainer.innerHTML = '';

        checkedCheckboxes.forEach(cb => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'rollnos[]';
            input.value = cb.value;
            hiddenInputsContainer.appendChild(input);
        });

        openModal('bulkAssignSupervisorModal');
    }
</script>
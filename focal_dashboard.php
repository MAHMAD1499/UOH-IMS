<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/db.php';

// Handle POST request processing for Focal Person actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

        if (!preg_match('/^[sS]\d{2}-\d{4}$/', $rollno)) {
            $_SESSION['flash_message'] = 'Invalid Roll No format. Expected format: S23-1234';
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

        $_SESSION['flash_message'] = 'Faculty supervisor assigned successfully.';
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

// Get distinct sessions for filtering
$sessions = [];
foreach ($students as $stud) {
    if (!empty($stud['student_session']) && !in_array($stud['student_session'], $sessions)) {
        $sessions[] = $stud['student_session'];
    }
}
sort($sessions);
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
<!-- MAIN SECTION: PORTAL DASHBOARD             -->
<!-- ========================================== -->
<div id="focal-dashboard" class="tab-content active">

    <!-- Add Student Button top-right (unattached from Registered Students List tab/card) -->
    <div style="display: flex; justify-content: flex-end; margin-bottom: 15px;">
        <button class="btn-primary-action" onclick="openModal('addStudentModal');"
            style="padding: 8px 16px; font-size: 13px; margin: 0; background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
            <i class="fa-solid fa-user-plus"></i> Add Student
        </button>
    </div>

    <!-- Section 2 — Session-wise Student Table & Assignment -->
    <div class="card">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <span><i class="fa-solid fa-users"></i> Registered Students List</span>
            <div style="display: flex; align-items: center; gap: 12px;">
                <!-- Session Filter -->
                <div style="display: flex; align-items: center; gap: 8px;">
                    <label for="session-filter-dropdown" style="font-size: 13px; font-weight: bold; color: #fff;">Filter
                        Session:</label>
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
                </div>
            </div>
        </div>
        <div class="card-body" style="padding: 0; overflow-x: auto;">
            <table class="custom-table">
                <thead>
                    <tr>
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
                            <td colspan="10" style="text-align: center; color: #64748b; padding: 20px;">No student records
                                found.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $student): ?>
                            <tr data-session="<?php echo htmlspecialchars($student['student_session'] ?? ''); ?>">
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
                                                (<?php echo (int)$student['duration_weeks']; ?> W)
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($student['site_supervisor_name'])): ?>
                                            <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                                                <i class="fa-solid fa-user-tie"></i> SS: <?php echo htmlspecialchars($student['site_supervisor_name']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <button type="button" class="btn-table-action" style="margin-top: 5px; padding: 4px 8px; font-size: 11px; background: #26294d; color: #ffffff; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;"
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
        <div class="card-header">
            <span><i class="fa-solid fa-envelope-open-text"></i> Student Internship Letters Report</span>
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
                <tbody>
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
                        <input type="text" id="student_depart" name="depart" required>
                    </div>
                    <div class="form-group">
                        <label for="student_program">Program <span style="color: red;">*</span></label>
                        <input type="text" id="student_program" name="program" required>
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
                            <option value="<?php echo $supervisor['u_id']; ?>">
                                <?php echo htmlspecialchars($supervisor['name'] ?: $supervisor['u_name']); ?> (Faculty
                                Supervisor)
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
<!-- MODAL: INTERNSHIP RECOMMENDATION LETTER     -->
<!-- ========================================== -->
<div id="letterViewModal" class="modal-overlay">
    <div class="modal-container" style="max-width: 650px;">
        <div class="modal-header">
            <h3><i class="fa-solid fa-file-contract"></i> Internship Recommendation Letter</h3>
            <span class="modal-close" onclick="closeModal('letterViewModal')">&times;</span>
        </div>
        <div class="modal-body">
            <div class="letter-paper">
                <div
                    style="text-align: center; border-bottom: 2px solid #26294d; padding-bottom: 10px; margin-bottom: 15px;">
                    <h2 style="font-size: 18px; color: #26294d; text-transform: uppercase; margin: 0;">University of
                        Haripur</h2>
                    <p style="font-size: 12px; color: #666; margin-top: 2px;">Department of <span
                            id="let_student_dept"></span></p>
                </div>

                <p style="text-align: right; font-size: 12px; color: #555; margin-bottom: 15px;">Date: <strong
                        id="let_date"></strong></p>

                <p style="font-weight: bold; margin-bottom: 10px; font-size: 13px;">To Whom It May Concern,</p>

                <p style="font-size: 13px; line-height: 1.6; color: #333; margin-bottom: 12px;">
                    This is to certify that <strong><span id="let_student_name"></span></strong>, Son/Daughter of
                    <strong><span id="let_student_fname"></span></strong> bearing Roll No: <strong><span
                            id="let_student_rollno"></span></strong>, is a bona fide student of Session <strong><span
                            id="let_student_session"></span></strong> at our institution.
                </p>

                <p style="font-size: 13px; line-height: 1.6; color: #333; margin-bottom: 15px;">
                    As part of our degree program requirements, the student is required to complete an internship to
                    gain practical industry exposure. We highly recommend them for an internship position at your
                    esteemed organization.
                </p>

                <div
                    style="margin-top: 30px; display: flex; justify-content: space-between; font-size: 12px; color: #444;">
                    <div>
                        <p>_______________________</p>
                        <p><strong>Department Focal Person</strong></p>
                    </div>
                    <div style="text-align: right;">
                        <p>_______________________</p>
                        <p><strong>Head of Department</strong></p>
                    </div>
                </div>
            </div>

            <div style="margin-top: 20px; text-align: right; display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" class="btn-cancel" onclick="closeModal('letterViewModal')">Close</button>
                <button type="button" class="btn-submit" style="margin-top: 0;" onclick="window.print()">
                    <i class="fa-solid fa-print"></i> Print / Download PDF
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: SITE SUPERVISOR DETAILS POPUP       -->
<!-- ========================================== -->
<div id="supervisorDetailsModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="modal-container" style="background: #fff; width: 90%; max-width: 700px; border-radius: 6px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); overflow: hidden; position: relative;">
        <div class="modal-header" style="background: #1e293b; color: #fff; padding: 14px 18px; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 16px;"><i class="fa-solid fa-user-tie"></i> Placement & Site Supervisor Details</h3>
            <span class="modal-close" onclick="closeSupervisorModal()" style="cursor: pointer; font-size: 22px; font-weight: bold;">&times;</span>
        </div>
        <div class="modal-body" style="padding: 18px; max-height: calc(100vh - 200px); overflow-y: auto;">
            
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 12px 15px; margin-bottom: 18px;">
                <div style="font-weight: 700; color: #1e293b; font-size: 15px;" id="sv_student_name"></div>
                <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                    Roll No: <span id="sv_student_roll" style="font-weight: 600; color: #334155;"></span>
                </div>
            </div>

            <!-- Organization Card -->
            <div class="org-info-card" style="border: 1px solid #e2e8f0; border-radius: 6px; padding: 15px; background: #ffffff; margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px; margin-bottom: 10px;">
                    <h4 style="font-size: 15px; font-weight: 700; color: #1e293b; margin: 0;">
                        <i class="fa-solid fa-building text-success" style="margin-right: 5px;"></i> <span id="sv_org_name"></span>
                    </h4>
                    <span id="sv_org_type_badge" class="status-pill" style="font-size: 10px; background-color: #3b82f6; color: white; padding: 2px 6px; border-radius: 10px;"></span>
                </div>
                <p style="margin-bottom: 5px; font-size: 13px;"><strong>Category:</strong> <span id="sv_org_category"></span></p>
                <p style="margin-bottom: 5px; font-size: 13px;"><strong>Address:</strong> <span id="sv_org_address"></span></p>
            </div>

            <div class="org-details-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 14px; font-size: 13px;">
                <!-- Organization Contact Person Block -->
                <div class="org-detail-block" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 12px;">
                    <h5 style="font-size: 11px; text-transform: uppercase; color: #64748b; margin-bottom: 8px; font-weight: 700; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px;">
                        <i class="fa-solid fa-address-book"></i> Organization Contact Person
                    </h5>
                    <p style="margin-bottom: 4px;"><strong>Name:</strong> <span id="sv_cp_name"></span></p>
                    <p style="margin-bottom: 4px;"><strong>Designation:</strong> <span id="sv_cp_designation"></span></p>
                    <p style="margin-bottom: 4px;"><strong>Cell No:</strong> <span id="sv_cp_phone"></span></p>
                    <p style="margin-bottom: 4px;"><strong>Email:</strong> <span id="sv_cp_email"></span></p>
                </div>

                <!-- Site Supervisor Block -->
                <div class="org-detail-block" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 12px;">
                    <h5 style="font-size: 11px; text-transform: uppercase; color: #64748b; margin-bottom: 8px; font-weight: 700; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px;">
                        <i class="fa-solid fa-user-tie"></i> Assigned Site Supervisor
                    </h5>
                    <p style="margin-bottom: 4px;"><strong>Name:</strong> <span id="sv_ss_name"></span></p>
                    <p style="margin-bottom: 4px;"><strong>Designation:</strong> <span id="sv_ss_designation"></span></p>
                    <p style="margin-bottom: 4px;"><strong>Cell No:</strong> <span id="sv_ss_phone"></span></p>
                    <p style="margin-bottom: 4px;"><strong>Email:</strong> <span id="sv_ss_email"></span></p>
                </div>
            </div>

            <!-- Project Placement Block -->
            <div class="org-detail-block" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 12px; margin-top: 15px;">
                <h5 style="font-size: 11px; text-transform: uppercase; color: #64748b; margin-bottom: 8px; font-weight: 700; letter-spacing: 0.5px; border-bottom: 1px solid #e2e8f0; padding-bottom: 4px;">
                    <i class="fa-solid fa-briefcase"></i> Placed Student & Project
                </h5>
                <p style="margin-bottom: 4px;"><strong>Project Title:</strong> <span id="sv_project_title"></span></p>
                <p style="margin-bottom: 4px;"><strong>Duration:</strong> <span id="sv_project_duration"></span> Weeks</p>
            </div>

            <div style="margin-top: 18px; text-align: right;">
                <button type="button" class="btn-cancel" onclick="closeSupervisorModal()" style="padding: 6px 14px; margin-top: 0;">Close</button>
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

        // Roll number check: Format S23-1234
        const rollRegex = /^[sS]\d{2}-\d{4}$/;
        if (!rollRegex.test(rollno)) {
            alert('Invalid Roll No format. Expected format: S23-1234');
            return false;
        }

        return true;
    }

    // Dynamic Filter for Session dropdown
    function filterSession(sessionValue) {
        const rows = document.querySelectorAll('#student-table-body tr');
        rows.forEach(row => {
            const session = row.getAttribute('data-session');
            // Handle empty row
            if (!session) return;

            if (sessionValue === '') {
                row.style.display = '';
            } else {
                const normSession = session.replace(/\s+/g, '-').toLowerCase();
                const normVal = sessionValue.replace(/\s+/g, '-').toLowerCase();
                if (normSession === normVal) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });
    }


    // Populate and show the recommendation letter
    function viewStudentLetter(name, fname, rollno, session, department, program) {
        document.getElementById('let_student_name').innerText = name || '[Student Name]';
        document.getElementById('let_student_fname').innerText = fname || '[Father Name]';
        document.getElementById('let_student_rollno').innerText = rollno || '[Roll No]';
        document.getElementById('let_student_session').innerText = session || '[Session]';
        document.getElementById('let_student_dept').innerText = department || 'Information Technology / Computer Science';
        document.getElementById('let_date').innerText = new Date().toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
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

    document.addEventListener("DOMContentLoaded", function() {
        const sessionFilter = document.getElementById('session-filter-dropdown');
        if (sessionFilter) {
            // Filter by selected value (which defaults to 'Fall 2026')
            filterSession(sessionFilter.value);
        }
    });
</script>
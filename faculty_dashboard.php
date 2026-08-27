<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/db.php';

// Ensure user is logged in as Faculty Supervisor (FSP)
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'FSP') {
    header('Location: login.php');
    exit;
}

$fspUsername = $_SESSION['username'] ?? 'FSP-0001';

// Resolve faculty supervisor user_id from users table
// Mapping FSP-0001 -> user_id = 1 (Dr. Yousaf), FSP-0002 -> user_id = 2 (Dr. Ikramullah)
$fspUserId = ($fspUsername === 'FSP-0002') ? 2 : 1;

// Fetch Supervisor Profile Details
$supStmt = mysqli_prepare($conn, "SELECT user_id, full_name, email, phone, designation FROM users WHERE user_id = ? LIMIT 1");
mysqli_stmt_bind_param($supStmt, 'i', $fspUserId);
mysqli_stmt_execute($supStmt);
$supRes = mysqli_stmt_get_result($supStmt);
$supervisor = mysqli_fetch_assoc($supRes) ?: [
    'user_id' => $fspUserId,
    'full_name' => 'Faculty Supervisor',
    'email' => 'supervisor@uoh.edu.pk',
    'phone' => '0300-1234567',
    'designation' => 'Assistant Professor'
];
mysqli_stmt_close($supStmt);

// ==========================================
// POST Handlers for Faculty Supervisor Actions
// ==========================================
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {

    // 1. Handle Weekly Report Review & Remarks
    if (isset($_POST['action_review_report'])) {
        $reportId = (int) ($_POST['report_id'] ?? 0);
        $newStatus = trim($_POST['report_status'] ?? 'submitted');
        $facultyRemarks = trim($_POST['faculty_remarks'] ?? '');

        $validStatuses = ['submitted', 'approved', 'rejected', 'needs_improvement'];
        if (!in_array($newStatus, $validStatuses, true)) {
            $_SESSION['flash_message'] = 'Invalid status selected.';
            $_SESSION['flash_type'] = 'error';
            header('Location: index.php');
            exit;
        }

        if ($reportId <= 0) {
            $_SESSION['flash_message'] = 'Invalid report ID.';
            $_SESSION['flash_type'] = 'error';
            header('Location: index.php');
            exit;
        }

        // Fetch current report details and verify supervisor ownership
        $checkSql = "SELECT wr.report_id, wr.revision_count, wr.status, s.faculty_supervisor_id 
                     FROM weekly_reports wr
                     JOIN internships i ON wr.internship_id = i.internship_id
                     JOIN students s ON i.student_id = s.student_id
                     WHERE wr.report_id = ? AND s.faculty_supervisor_id = ? LIMIT 1";
        $checkStmt = mysqli_prepare($conn, $checkSql);
        mysqli_stmt_bind_param($checkStmt, 'ii', $reportId, $fspUserId);
        mysqli_stmt_execute($checkStmt);
        $currReport = mysqli_fetch_assoc(mysqli_stmt_get_result($checkStmt));
        mysqli_stmt_close($checkStmt);

        if (!$currReport) {
            $_SESSION['flash_message'] = 'Report not found or not assigned to you.';
            $_SESSION['flash_type'] = 'error';
            header('Location: index.php');
            exit;
        }

        $currentRevisionCount = (int) ($currReport['revision_count'] ?? 0);

        // Enforce maximum 3 revisions if status is 'needs_improvement'
        if ($newStatus === 'needs_improvement') {
            if ($currentRevisionCount >= 3) {
                $_SESSION['flash_message'] = 'Cannot request improvement: Maximum revision limit of 3 has already been reached for this report.';
                $_SESSION['flash_type'] = 'error';
                header('Location: index.php');
                exit;
            }
            $currentRevisionCount++;
        }

        $updateSql = "UPDATE weekly_reports 
                      SET faculty_remarks = ?, status = ?, revision_count = ? 
                      WHERE report_id = ?";
        $updateStmt = mysqli_prepare($conn, $updateSql);
        mysqli_stmt_bind_param($updateStmt, 'ssii', $facultyRemarks, $newStatus, $currentRevisionCount, $reportId);
        if (mysqli_stmt_execute($updateStmt)) {
            $_SESSION['flash_message'] = 'Weekly report review and remarks saved successfully.';
            $_SESSION['flash_type'] = 'success';
        } else {
            $_SESSION['flash_message'] = 'Failed to update weekly report: ' . mysqli_error($conn);
            $_SESSION['flash_type'] = 'error';
        }
        mysqli_stmt_close($updateStmt);
        header('Location: index.php');
        exit;
    }

    // 2. Handle Marks Evaluation (One-time per session basis)
    if (isset($_POST['action_save_evaluation'])) {
        $studentId = (int) ($_POST['student_id'] ?? 0);
        $evalSession = trim($_POST['session'] ?? '');
        $totalMarks = (float) ($_POST['total_marks'] ?? 100);
        $obtainedMarks = (float) ($_POST['obtained_marks'] ?? 0);

        if ($studentId <= 0 || $evalSession === '' || $totalMarks <= 0) {
            $_SESSION['flash_message'] = 'Invalid evaluation parameters.';
            $_SESSION['flash_type'] = 'error';
            header('Location: index.php');
            exit;
        }

        if ($obtainedMarks < 0 || $obtainedMarks > $totalMarks) {
            $_SESSION['flash_message'] = 'Obtained marks must be between 0 and total marks (' . $totalMarks . ').';
            $_SESSION['flash_type'] = 'error';
            header('Location: index.php');
            exit;
        }

        // Verify student is assigned to this supervisor
        $stdCheck = mysqli_prepare($conn, "SELECT student_id FROM students WHERE student_id = ? AND faculty_supervisor_id = ? LIMIT 1");
        mysqli_stmt_bind_param($stdCheck, 'ii', $studentId, $fspUserId);
        mysqli_stmt_execute($stdCheck);
        $validStudent = mysqli_fetch_assoc(mysqli_stmt_get_result($stdCheck));
        mysqli_stmt_close($stdCheck);

        if (!$validStudent) {
            $_SESSION['flash_message'] = 'Student is not assigned to your supervision.';
            $_SESSION['flash_type'] = 'error';
            header('Location: index.php');
            exit;
        }

        // Check if marks evaluation already exists for this student and session
        $checkEval = mysqli_prepare($conn, "SELECT evaluation_id FROM marks_evaluations WHERE student_id = ? AND session = ? LIMIT 1");
        mysqli_stmt_bind_param($checkEval, 'is', $studentId, $evalSession);
        mysqli_stmt_execute($checkEval);
        $existingEval = mysqli_fetch_assoc(mysqli_stmt_get_result($checkEval));
        mysqli_stmt_close($checkEval);

        if ($existingEval) {
            $evalId = (int) $existingEval['evaluation_id'];
            $updateEval = mysqli_prepare($conn, "UPDATE marks_evaluations SET total_marks = ?, obtained_marks = ?, evaluated_at = CURRENT_TIMESTAMP WHERE evaluation_id = ?");
            mysqli_stmt_bind_param($updateEval, 'ddi', $totalMarks, $obtainedMarks, $evalId);
            mysqli_stmt_execute($updateEval);
            mysqli_stmt_close($updateEval);
            $_SESSION['flash_message'] = 'Marks evaluation updated successfully.';
        } else {
            $insertEval = mysqli_prepare($conn, "INSERT INTO marks_evaluations (student_id, faculty_supervisor_id, session, total_marks, obtained_marks) VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($insertEval, 'iisdd', $studentId, $fspUserId, $evalSession, $totalMarks, $obtainedMarks);
            mysqli_stmt_execute($insertEval);
            mysqli_stmt_close($insertEval);
            $_SESSION['flash_message'] = 'Marks evaluation recorded successfully.';
        }

        $_SESSION['flash_type'] = 'success';
        header('Location: index.php');
        exit;
    }
}

// ==========================================
// Data Fetching for Views
// ==========================================

// 1. Assigned Students List with Internship and Marks Data
$studentsQuery = "
    SELECT 
        s.student_id,
        s.roll_no,
        s.session,
        u.full_name AS student_name,
        u.email AS student_email,
        u.phone AS student_phone,
        i.internship_id,
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
        ssd.internship_duration AS duration_weeks,
        m.evaluation_id,
        m.total_marks,
        m.obtained_marks,
        m.evaluated_at,
        (SELECT COUNT(*) FROM weekly_reports wr WHERE wr.internship_id = i.internship_id) AS total_reports,
        (SELECT COUNT(*) FROM weekly_reports wr WHERE wr.internship_id = i.internship_id AND wr.status = 'approved') AS approved_reports,
        (SELECT COUNT(*) FROM weekly_reports wr WHERE wr.internship_id = i.internship_id AND wr.status = 'submitted') AS pending_reports
    FROM students s
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN internships i ON s.student_id = i.student_id
    LEFT JOIN site_supervisor_details ssd ON s.roll_no = ssd.rollno
    LEFT JOIN marks_evaluations m ON s.student_id = m.student_id AND s.session = m.session
    WHERE s.faculty_supervisor_id = ?
    ORDER BY s.session DESC, s.roll_no ASC
";
$stmtStd = mysqli_prepare($conn, $studentsQuery);
mysqli_stmt_bind_param($stmtStd, 'i', $fspUserId);
mysqli_stmt_execute($stmtStd);
$assignedStudentsResult = mysqli_stmt_get_result($stmtStd);
$assignedStudents = [];
$sessionsList = [];
$totalStudentsCount = 0;
$totalPendingReportsCount = 0;
$totalGradedCount = 0;

while ($row = mysqli_fetch_assoc($assignedStudentsResult)) {
    $assignedStudents[] = $row;
    if (!in_array($row['session'], $sessionsList, true)) {
        $sessionsList[] = $row['session'];
    }
    $totalStudentsCount++;
    $totalPendingReportsCount += (int) $row['pending_reports'];
    if ($row['obtained_marks'] !== null) {
        $totalGradedCount++;
    }
}
mysqli_stmt_close($stmtStd);

// 2. Weekly Reports List for Assigned Students
$reportsQuery = "
    SELECT 
        wr.report_id,
        wr.internship_id,
        wr.week_number,
        wr.task_description,
        wr.weekly_targets,
        wr.fp_remarks,
        wr.faculty_remarks,
        wr.revision_count,
        wr.status,
        wr.submitted_at,
        s.student_id,
        s.roll_no,
        s.session,
        u.full_name AS student_name,
        i.internship_title,
        o.org_name
    FROM weekly_reports wr
    JOIN internships i ON wr.internship_id = i.internship_id
    JOIN students s ON i.student_id = s.student_id
    JOIN users u ON s.user_id = u.user_id
    LEFT JOIN organizations o ON i.org_id = o.org_id
    WHERE s.faculty_supervisor_id = ?
    ORDER BY wr.submitted_at DESC, wr.week_number DESC
";
$stmtRep = mysqli_prepare($conn, $reportsQuery);
mysqli_stmt_bind_param($stmtRep, 'i', $fspUserId);
mysqli_stmt_execute($stmtRep);
$weeklyReportsResult = mysqli_stmt_get_result($stmtRep);
$weeklyReports = [];
while ($row = mysqli_fetch_assoc($weeklyReportsResult)) {
    $weeklyReports[] = $row;
}
mysqli_stmt_close($stmtRep);

$orgsQuery = "
    SELECT 
        ssd.site_sup_id AS org_id,
        ssd.org_name,
        ssd.org_address AS address,
        ssd.org_category AS category,
        ssd.org_type AS type,
        ssd.org_contact_person AS contact_person_name,
        ssd.org_contact_cell AS contact_person_phone,
        ssd.org_contact_email AS contact_person_email,
        ssd.org_contact_designation AS contact_person_designation,
        ssd.site_supervisor_name,
        ssd.site_supervisor_email,
        ssd.site_supervisor_cell AS site_supervisor_phone,
        ssd.site_supervisor_designation,
        ssd.internship_title,
        ssd.internship_duration AS duration_weeks,
        s.roll_no,
        s.session,
        u.full_name AS student_name
    FROM site_supervisor_details ssd
    JOIN students s ON ssd.rollno = s.roll_no
    JOIN users u ON s.user_id = u.user_id
    WHERE s.faculty_supervisor_id = ?
    ORDER BY ssd.org_name ASC, s.roll_no ASC
";
$stmtOrg = mysqli_prepare($conn, $orgsQuery);
mysqli_stmt_bind_param($stmtOrg, 'i', $fspUserId);
mysqli_stmt_execute($stmtOrg);
$orgsResult = mysqli_stmt_get_result($stmtOrg);
$organizations = [];
while ($row = mysqli_fetch_assoc($orgsResult)) {
    $organizations[] = $row;
}
mysqli_stmt_close($stmtOrg);

// Compute Pending Tasks & Alerts for Faculty Supervisor Welcome Dashboard
$urgentReviews = [];
$flaggedIssues = [];
$overdueSubmissions = [];

// Track report counts and last submissions per student
$studentReportCount = [];
$studentLastReportDate = [];

foreach ($weeklyReports as $rep) {
    $rollNo = $rep['roll_no'];
    if (!isset($studentReportCount[$rollNo])) {
        $studentReportCount[$rollNo] = 0;
    }
    $studentReportCount[$rollNo]++;
    
    $subDate = strtotime($rep['submitted_at']);
    if (!isset($studentLastReportDate[$rollNo]) || $subDate > $studentLastReportDate[$rollNo]) {
        $studentLastReportDate[$rollNo] = $subDate;
    }

    if ($rep['status'] === 'submitted') {
        $urgentReviews[] = [
            'student_name' => $rep['student_name'],
            'roll_no' => $rep['roll_no'],
            'week_number' => $rep['week_number'],
            'submitted_at' => $rep['submitted_at'],
            'report_id' => $rep['report_id']
        ];
    } elseif ($rep['status'] === 'needs_improvement') {
        $flaggedIssues[] = [
            'student_name' => $rep['student_name'],
            'roll_no' => $rep['roll_no'],
            'week_number' => $rep['week_number'],
            'revision_count' => $rep['revision_count']
        ];
    }
}

// Find overdue submissions
foreach ($assignedStudents as $student) {
    $rollNo = $student['roll_no'];
    $repCount = $studentReportCount[$rollNo] ?? 0;
    
    if ($repCount === 0) {
        $overdueSubmissions[] = [
            'student_name' => $student['student_name'],
            'roll_no' => $rollNo,
            'reason' => 'No reports submitted yet'
        ];
    } else {
        $lastSub = $studentLastReportDate[$rollNo];
        $daysSinceLastSub = (time() - $lastSub) / (60 * 60 * 24);
        if ($daysSinceLastSub > 10) {
            $overdueSubmissions[] = [
                'student_name' => $student['student_name'],
                'roll_no' => $rollNo,
                'reason' => 'Last submission was ' . round($daysSinceLastSub) . ' days ago'
            ];
        }
    }
}
?>


<!-- Flash Alerts Notification -->
<?php if (!empty($flashMessage)): ?>
    <div
        style="background: <?php echo ($flashType === 'error') ? '#fee2e2' : '#dcfce7'; ?>; 
                color: <?php echo ($flashType === 'error') ? '#991b1b' : '#166534'; ?>; 
                border: 1px solid <?php echo ($flashType === 'error') ? '#fca5a5' : '#86efac'; ?>; 
                padding: 12px 18px; border-radius: 6px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; font-weight: 500; font-size: 14px;">
        <i class="fa-solid <?php echo ($flashType === 'error') ? 'fa-circle-exclamation' : 'fa-circle-check'; ?>"></i>
        <span><?php echo htmlspecialchars($flashMessage); ?></span>
    </div>
<?php endif; ?>

<!-- ========================================== -->
<!-- TAB 0: WELCOME DASHBOARD                   -->
<!-- ========================================== -->
<div id="faculty-welcome-dashboard" class="tab-content active">


    <div class="welcome-banner">
        <h2>Welcome back, <?php echo htmlspecialchars($supervisor['full_name'] ?: 'Faculty Supervisor'); ?>!</h2>
        <p>Designation: <strong><?php echo htmlspecialchars($supervisor['designation'] ?: 'Faculty Supervisor'); ?></strong> | Email: <strong><?php echo htmlspecialchars($supervisor['email'] ?: 'supervisor@uoh.edu.pk'); ?></strong></p>
    </div>

    <h3 style="font-size: 16px; font-weight: 600; color: #1e293b; margin-bottom: 15px;">Quick Actions</h3>
    <div class="action-boxes-container">
        <!-- Action 1: Assigned Students -->
        <div class="action-box" onclick="switchTab('faculty-dashboard', document.getElementById('nav-item-faculty-dashboard'))">
            <div class="action-icon-wrapper">
                <i class="fa-solid fa-users-rectangle"></i>
            </div>
            <h3>Assigned Students</h3>
            <p>View lists and supervisor status of all students assigned to your supervision.</p>
        </div>
        
        <!-- Action 2: Weekly Reports Review -->
        <div class="action-box" onclick="switchTab('faculty-reports', document.getElementById('nav-item-faculty-reports'))">
            <div class="action-icon-wrapper">
                <i class="fa-solid fa-file-signature"></i>
            </div>
            <h3>Weekly Reports Review</h3>
            <p>Review student weekly log submissions, submit feedback comments, and request changes.</p>
        </div>

        <!-- Action 3: Marks Evaluation -->
        <div class="action-box" onclick="switchTab('faculty-marks', document.getElementById('nav-item-faculty-marks'))">
            <div class="action-icon-wrapper">
                <i class="fa-solid fa-award"></i>
            </div>
            <h3>Marks Evaluation</h3>
            <p>Submit final performance evaluations and award final marks to students.</p>
        </div>
    </div>



    <!-- 1. Pending Tasks & Alerts -->
    <div class="widget-card">
        <div class="widget-header accent-amber">
            <i class="fa-solid fa-triangle-exclamation"></i> Pending Tasks & Alerts
        </div>
        <div class="widget-body">
            <?php if (empty($urgentReviews) && empty($flaggedIssues) && empty($overdueSubmissions)): ?>
                <p style="font-size: 13.5px; color: #64748b;">No pending alerts or issues found.</p>
            <?php endif; ?>

            <!-- Urgent Reviews -->
            <?php foreach ($urgentReviews as $review): ?>
                <div class="alert-item urgent">
                    <i class="fa-solid fa-circle-exclamation alert-icon"></i>
                    <div class="alert-details">
                        <div class="alert-title">Urgent Review Request</div>
                        <div class="alert-desc">
                            <strong><?php echo htmlspecialchars($review['student_name']); ?></strong> (<?php echo htmlspecialchars($review['roll_no']); ?>) submitted Weekly Report for Week <?php echo $review['week_number']; ?>.
                        </div>
                    </div>
                    <button class="btn-primary-action" onclick="switchTab('faculty-reports', document.querySelectorAll('.nav-menu .nav-item')[2])" style="padding: 4px 8px; font-size: 11px; margin: 0; background: #991b1b; border: none;">Review</button>
                </div>
            <?php endforeach; ?>

            <!-- Flagged Issues -->
            <?php foreach ($flaggedIssues as $issue): ?>
                <div class="alert-item flagged">
                    <i class="fa-solid fa-flag alert-icon"></i>
                    <div class="alert-details">
                        <div class="alert-title">Flagged student issue (Needs Improvement)</div>
                        <div class="alert-desc">
                            <strong><?php echo htmlspecialchars($issue['student_name']); ?></strong>'s Report for Week <?php echo $issue['week_number']; ?> is marked as Needs Improvement (Revision #<?php echo $issue['revision_count']; ?>).
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Overdue Submissions -->
            <?php foreach (array_slice($overdueSubmissions, 0, 5) as $overdue): ?>
                <div class="alert-item overdue">
                    <i class="fa-solid fa-clock alert-icon"></i>
                    <div class="alert-details">
                        <div class="alert-title">Report submission overdue</div>
                        <div class="alert-desc">
                            <strong><?php echo htmlspecialchars($overdue['student_name']); ?></strong> (<?php echo htmlspecialchars($overdue['roll_no']); ?>): <?php echo htmlspecialchars($overdue['reason']); ?>.
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- TAB 1: Assigned Students Directory -->
<!-- ========================================== -->
<div id="faculty-dashboard" class="tab-content">

    <!-- KPI Summary Metrics -->
    <div class="fsp-kpi-grid">
        <div class="fsp-kpi-card">
            <div class="fsp-kpi-icon kpi-blue">
                <i class="fa-solid fa-user-graduate"></i>
            </div>
            <div class="fsp-kpi-info">
                <h4><?php echo $totalStudentsCount; ?></h4>
                <p>Assigned Students</p>
            </div>
        </div>
        <div class="fsp-kpi-card">
            <div class="fsp-kpi-icon kpi-amber">
                <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <div class="fsp-kpi-info">
                <h4><?php echo $totalPendingReportsCount; ?></h4>
                <p>Reports Pending Review</p>
            </div>
        </div>
        <div class="fsp-kpi-card">
            <div class="fsp-kpi-icon kpi-green">
                <i class="fa-solid fa-square-poll-vertical"></i>
            </div>
            <div class="fsp-kpi-info">
                <h4><?php echo $totalGradedCount; ?> / <?php echo $totalStudentsCount; ?></h4>
                <p>Graded Evaluations</p>
            </div>
        </div>
        <div class="fsp-kpi-card">
            <div class="fsp-kpi-icon kpi-purple">
                <i class="fa-solid fa-building"></i>
            </div>
            <div class="fsp-kpi-info">
                <h4><?php echo count($organizations); ?></h4>
                <p>Active Organizations</p>
            </div>
        </div>
    </div>

    <!-- Main Card -->
    <div class="card">
        <div class="card-header">
            <span><i class="fa-solid fa-chalkboard-user"></i> Assigned Students Directory &mdash;
                <?php echo htmlspecialchars($supervisor['full_name']); ?>
                (<?php echo htmlspecialchars($supervisor['designation']); ?>)</span>
            <span style="font-size: 13px; font-weight: 500; opacity: 0.9;">
                <i class="fa-solid fa-id-badge"></i> <?php echo htmlspecialchars($fspUsername); ?>
            </span>
        </div>
        <div class="card-body">

            <!-- Filter Controls -->
            <div class="fsp-filter-bar">
                <div class="fsp-filter-group">
                    <label for="sessionFilterSelect"><i class="fa-solid fa-filter"></i> Session:</label>
                    <select id="sessionFilterSelect" class="fsp-select" onchange="filterAssignedStudents()">
                        <option value="ALL">All Academic Sessions</option>
                        <?php foreach ($sessionsList as $sess): ?>
                            <option value="<?php echo htmlspecialchars($sess); ?>" <?php echo ($sess === 'Fall 2026' || $sess === 'Fall-2026') ? 'selected' : ''; ?>><?php echo htmlspecialchars($sess); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fsp-filter-group">
                    <label for="studentSearchInput"><i class="fa-solid fa-magnifying-glass"></i> Search:</label>
                    <input type="text" id="studentSearchInput" class="fsp-input" placeholder="Roll No, Name, Org..."
                        onkeyup="filterAssignedStudents()">
                </div>
            </div>

            <!-- Assigned Students Table -->
            <div style="overflow-x: auto;">
                <table class="custom-table" id="assignedStudentsTable">
                    <thead>
                        <tr>
                            <th>Roll Number</th>
                            <th>Student Name</th>
                            <th>Academic Session</th>
                            <th>Host Organization</th>
                            <th>Internship Project</th>
                            <th>Reports Progress</th>
                            <th>Final Marks</th>
                            <th>Site Supervisor</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($assignedStudents)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; color: #64748b; padding: 25px;">
                                    <i class="fa-solid fa-circle-info fa-2x"
                                        style="display: block; margin-bottom: 8px;"></i>
                                    No students are currently assigned to your supervision.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($assignedStudents as $std): ?>
                                <tr data-session="<?php echo htmlspecialchars($std['session']); ?>"
                                    data-search="<?php echo htmlspecialchars(strtolower($std['roll_no'] . ' ' . $std['student_name'] . ' ' . ($std['org_name'] ?? '') . ' ' . ($std['internship_title'] ?? ''))); ?>">
                                    <td><strong><?php echo htmlspecialchars($std['roll_no']); ?></strong></td>
                                    <td>
                                        <div style="font-weight: 600; color: #1e293b;">
                                            <?php echo htmlspecialchars($std['student_name']); ?></div>
                                        <div style="font-size: 11px; color: #64748b;">
                                            <?php echo htmlspecialchars($std['student_email'] ?? ''); ?></div>
                                    </td>
                                    <td>
                                        <span class="revision-badge"><?php echo htmlspecialchars($std['session']); ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($std['org_name'])): ?>
                                            <div style="font-weight: 600; color: #1e293b;">
                                                <?php echo htmlspecialchars($std['org_name']); ?></div>
                                            <div style="font-size: 11px; color: #64748b;">
                                                <?php echo htmlspecialchars($std['org_category'] ?? ''); ?></div>
                                        <?php else: ?>
                                            <span style="color: #94a3b8; font-style: italic;">Not Registered</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($std['internship_title'])): ?>
                                            <div style="color: #334155; font-size: 13px;">
                                                <?php echo htmlspecialchars($std['internship_title']); ?></div>
                                            <div style="font-size: 11px; color: #64748b;"><i class="fa-solid fa-calendar-week"></i>
                                                <?php echo (int) $std['duration_weeks']; ?> Weeks</div>
                                        <?php else: ?>
                                            <span style="color: #94a3b8; font-style: italic;">Pending Placement</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; font-size: 12px;">
                                            <span style="color: #16a34a;"><?php echo (int) $std['approved_reports']; ?>
                                                Approved</span> /
                                            <span><?php echo (int) $std['total_reports']; ?> Total</span>
                                        </div>
                                        <?php if ((int) $std['pending_reports'] > 0): ?>
                                            <span class="status-pill pill-submitted" style="margin-top: 4px; font-size: 10px;">
                                                <i class="fa-solid fa-clock"></i> <?php echo (int) $std['pending_reports']; ?>
                                                Pending Review
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($std['obtained_marks'] !== null): ?>
                                            <div style="font-weight: 700; color: #16a34a; font-size: 14px;">
                                                <?php echo number_format((float) $std['obtained_marks'], 1); ?> <span
                                                    style="font-size: 11px; color: #64748b;">/
                                                    <?php echo number_format((float) $std['total_marks'], 0); ?></span>
                                            </div>
                                            <span class="status-pill pill-approved" style="font-size: 10px;">Evaluated</span>
                                        <?php else: ?>
                                            <span class="status-pill pill-submitted" style="font-size: 10px;">Unevaluated</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($std['org_name'])): ?>
                                            <button type="button" class="action-btn-sm btn-fsp-secondary"
                                                onclick="openSupervisorModal(
                                                    '<?php echo htmlspecialchars(addslashes($std['student_name'])); ?>',
                                                    '<?php echo htmlspecialchars(addslashes($std['roll_no'])); ?>',
                                                    '<?php echo htmlspecialchars(addslashes($std['org_name'])); ?>',
                                                    '<?php echo htmlspecialchars(addslashes($std['org_address'] ?? 'N/A')); ?>',
                                                    '<?php echo htmlspecialchars(addslashes($std['org_category'] ?? 'General')); ?>',
                                                    '<?php echo htmlspecialchars(addslashes($std['org_type'] ?? 'IT')); ?>',
                                                    '<?php echo htmlspecialchars(addslashes($std['contact_person_name'] ?? 'N/A')); ?>',
                                                    '<?php echo htmlspecialchars(addslashes($std['contact_person_phone'] ?? 'N/A')); ?>',
                                                    '<?php echo htmlspecialchars(addslashes($std['contact_person_email'] ?? 'N/A')); ?>',
                                                    '<?php echo htmlspecialchars(addslashes($std['contact_person_designation'] ?? 'N/A')); ?>',
                                                    '<?php echo htmlspecialchars(addslashes($std['site_supervisor_name'] ?? 'Not Assigned')); ?>',
                                                    '<?php echo htmlspecialchars(addslashes($std['site_supervisor_phone'] ?? 'N/A')); ?>',
                                                    '<?php echo htmlspecialchars(addslashes($std['site_supervisor_email'] ?? 'N/A')); ?>',
                                                    '<?php echo htmlspecialchars(addslashes($std['site_supervisor_designation'] ?? 'N/A')); ?>',
                                                    '<?php echo htmlspecialchars(addslashes($std['internship_title'] ?? 'N/A')); ?>',
                                                    '<?php echo (int) $std['duration_weeks']; ?>'
                                                )">
                                                <i class="fa-solid fa-user-tie"></i> Supervisor Info
                                            </button>
                                        <?php else: ?>
                                            <span style="color: #94a3b8; font-style: italic; font-size: 12px;">Not Registered</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 6px;">
                                            <button type="button" class="action-btn-sm btn-fsp-primary"
                                                onclick="openMarksModal(<?php echo (int) $std['student_id']; ?>, '<?php echo htmlspecialchars(addslashes($std['roll_no'])); ?>', '<?php echo htmlspecialchars(addslashes($std['student_name'])); ?>', '<?php echo htmlspecialchars(addslashes($std['session'])); ?>', <?php echo ($std['total_marks'] !== null) ? (float) $std['total_marks'] : 100; ?>, <?php echo ($std['obtained_marks'] !== null) ? (float) $std['obtained_marks'] : "''"; ?>)">
                                                <i class="fa-solid fa-award"></i> Grade
                                            </button>
                                            <button type="button" class="action-btn-sm btn-fsp-outline"
                                                onclick="switchTab('faculty-reports', document.querySelectorAll('.nav-menu .nav-item')[1]); filterReportsByStudent('<?php echo htmlspecialchars(addslashes($std['roll_no'])); ?>')">
                                                <i class="fa-solid fa-file-lines"></i> Reports
                                            </button>
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
</div>

<!-- ========================================== -->
<!-- TAB 2: Weekly Reports Review Desk -->
<!-- ========================================== -->
<div id="faculty-reports" class="tab-content">
    <div class="card">
        <div class="card-header">
            <span><i class="fa-solid fa-file-signature"></i> Student Weekly Internship Reports Review Desk</span>
            <span style="font-size: 13px; opacity: 0.9;"><i class="fa-solid fa-rotate"></i> Max 3 Revisions
                Enforced</span>
        </div>
        <div class="card-body">

            <!-- Filter Controls for Reports -->
            <div class="fsp-filter-bar">
                <div class="fsp-filter-group">
                    <label for="reportStatusFilter"><i class="fa-solid fa-filter"></i> Status:</label>
                    <select id="reportStatusFilter" class="fsp-select" onchange="filterReportsList()">
                        <option value="ALL">All Statuses</option>
                        <option value="submitted">Submitted (Pending)</option>
                        <option value="needs_improvement">Needs Improvement (Revision Requested)</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <div class="fsp-filter-group">
                    <label for="reportSearchInput"><i class="fa-solid fa-magnifying-glass"></i> Search Student /
                        Topic:</label>
                    <input type="text" id="reportSearchInput" class="fsp-input" placeholder="Roll No, Name, Task..."
                        onkeyup="filterReportsList()">
                </div>
            </div>

            <!-- Weekly Reports Table -->
            <div style="overflow-x: auto;">
                <table class="custom-table" id="weeklyReportsTable">
                    <thead>
                        <tr>
                            <th>Student (Roll No)</th>
                            <th>Week #</th>
                            <th>Task & Objectives Draft</th>
                            <th>Status</th>
                            <th>Revisions</th>
                            <th>Faculty Remarks</th>
                            <th>Submitted Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($weeklyReports)): ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: #64748b; padding: 25px;">
                                    <i class="fa-solid fa-inbox fa-2x" style="display: block; margin-bottom: 8px;"></i>
                                    No weekly reports submitted yet by assigned students.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($weeklyReports as $rep): ?>
                                <tr data-status="<?php echo htmlspecialchars($rep['status']); ?>"
                                    data-rollno="<?php echo htmlspecialchars($rep['roll_no']); ?>"
                                    data-search="<?php echo htmlspecialchars(strtolower($rep['roll_no'] . ' ' . $rep['student_name'] . ' ' . $rep['task_description'] . ' ' . ($rep['weekly_targets'] ?? ''))); ?>">
                                    <td>
                                        <strong><?php echo htmlspecialchars($rep['roll_no']); ?></strong>
                                        <div style="font-size: 12px; color: #64748b;">
                                            <?php echo htmlspecialchars($rep['student_name']); ?></div>
                                    </td>
                                    <td style="text-align: center;">
                                        <span class="revision-badge" style="font-weight: 700;">Week
                                            <?php echo (int) $rep['week_number']; ?></span>
                                    </td>
                                    <td style="max-width: 320px;">
                                        <div style="font-weight: 600; color: #1e293b; font-size: 13px; margin-bottom: 3px;">
                                            <?php echo htmlspecialchars(mb_strimwidth($rep['task_description'], 0, 85, '...')); ?>
                                        </div>
                                        <?php if (!empty($rep['weekly_targets'])): ?>
                                            <div style="font-size: 11px; color: #64748b;">
                                                <i class="fa-solid fa-bullseye"></i> Targets:
                                                <?php echo htmlspecialchars(mb_strimwidth($rep['weekly_targets'], 0, 60, '...')); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $stClass = 'pill-' . $rep['status'];
                                        $icon = 'fa-clock';
                                        if ($rep['status'] === 'approved')
                                            $icon = 'fa-circle-check';
                                        if ($rep['status'] === 'rejected')
                                            $icon = 'fa-circle-xmark';
                                        if ($rep['status'] === 'needs_improvement')
                                            $icon = 'fa-rotate-right';
                                        ?>
                                        <span class="status-pill <?php echo $stClass; ?>">
                                            <i class="fa-solid <?php echo $icon; ?>"></i>
                                            <?php echo str_replace('_', ' ', $rep['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php $revs = (int) ($rep['revision_count'] ?? 0); ?>
                                        <span
                                            class="revision-badge <?php echo ($revs >= 3) ? 'revision-limit-reached' : ''; ?>">
                                            <?php echo $revs; ?> / 3 <?php echo ($revs >= 3) ? '(Max)' : ''; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($rep['faculty_remarks'])): ?>
                                            <span
                                                style="font-size: 12px; color: #334155;"><?php echo htmlspecialchars(mb_strimwidth($rep['faculty_remarks'], 0, 50, '...')); ?></span>
                                        <?php else: ?>
                                            <span style="color: #94a3b8; font-style: italic; font-size: 12px;">No remarks
                                                added</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size: 12px; color: #64748b; white-space: nowrap;">
                                        <?php echo date('d M, Y', strtotime($rep['submitted_at'])); ?>
                                    </td>
                                    <td>
                                        <button type="button" class="action-btn-sm btn-fsp-primary" onclick="openReportReviewModal(
                                                    <?php echo (int) $rep['report_id']; ?>,
                                                    '<?php echo htmlspecialchars(addslashes($rep['roll_no'])); ?>',
                                                    '<?php echo htmlspecialchars(addslashes($rep['student_name'])); ?>',
                                                    <?php echo (int) $rep['week_number']; ?>,
                                                    '<?php echo htmlspecialchars(addslashes($rep['task_description'])); ?>',
                                                    '<?php echo htmlspecialchars(addslashes($rep['weekly_targets'] ?? '')); ?>',
                                                    '<?php echo htmlspecialchars(addslashes($rep['fp_remarks'] ?? '')); ?>',
                                                    '<?php echo htmlspecialchars(addslashes($rep['faculty_remarks'] ?? '')); ?>',
                                                    '<?php echo htmlspecialchars(addslashes($rep['status'])); ?>',
                                                    <?php echo (int) $rep['revision_count']; ?>,
                                                    '<?php echo htmlspecialchars(addslashes($rep['org_name'] ?? '')); ?>'
                                                )">
                                            <i class="fa-solid fa-pen-to-square"></i> Review
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
</div>

<!-- ========================================== -->
<!-- TAB 3: Marks Evaluation Register -->
<!-- ========================================== -->
<div id="faculty-marks" class="tab-content">
    <div class="card">
        <div class="card-header">
            <span><i class="fa-solid fa-award"></i> Student Internship Marks Evaluation (One-Time Per Session
                Basis)</span>
            <span style="font-size: 13px; opacity: 0.9;"><i class="fa-solid fa-shield-check"></i> Standard Evaluation
                Scale: 100 Marks</span>
        </div>
        <div class="card-body">

            <div class="fsp-filter-bar">
                <div class="fsp-filter-group">
                    <label for="marksSessionFilter"><i class="fa-solid fa-filter"></i> Session:</label>
                    <select id="marksSessionFilter" class="fsp-select" onchange="filterMarksList()">
                        <option value="ALL">All Academic Sessions</option>
                        <?php foreach ($sessionsList as $sess): ?>
                            <option value="<?php echo htmlspecialchars($sess); ?>" <?php echo ($sess === 'Fall 2026' || $sess === 'Fall-2026') ? 'selected' : ''; ?>><?php echo htmlspecialchars($sess); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="fsp-filter-group">
                    <label for="marksSearchInput"><i class="fa-solid fa-magnifying-glass"></i> Search Student:</label>
                    <input type="text" id="marksSearchInput" class="fsp-input" placeholder="Roll No, Name..."
                        onkeyup="filterMarksList()">
                </div>
            </div>

            <div style="overflow-x: auto;">
                <table class="custom-table" id="marksEvaluationTable">
                    <thead>
                        <tr>
                            <th>Roll Number</th>
                            <th>Student Name</th>
                            <th>Session</th>
                            <th>Host Organization</th>
                            <th>Total Marks</th>
                            <th>Obtained Marks</th>
                            <th>Percentage / Grade</th>
                            <th>Evaluation Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($assignedStudents)): ?>
                            <tr>
                                <td colspan="9" style="text-align: center; color: #64748b; padding: 25px;">
                                    No students available for marks evaluation.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($assignedStudents as $std): ?>
                                <tr data-session="<?php echo htmlspecialchars($std['session']); ?>"
                                    data-search="<?php echo htmlspecialchars(strtolower($std['roll_no'] . ' ' . $std['student_name'])); ?>">
                                    <td><strong><?php echo htmlspecialchars($std['roll_no']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($std['student_name']); ?></td>
                                    <td><span class="revision-badge"><?php echo htmlspecialchars($std['session']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($std['org_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo ($std['total_marks'] !== null) ? number_format((float) $std['total_marks'], 2) : '100.00'; ?>
                                    </td>
                                    <td>
                                        <?php if ($std['obtained_marks'] !== null): ?>
                                            <strong
                                                style="color: #16a34a; font-size: 14px;"><?php echo number_format((float) $std['obtained_marks'], 2); ?></strong>
                                        <?php else: ?>
                                            <span style="color: #94a3b8; font-style: italic;">Not Entered</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($std['obtained_marks'] !== null && $std['total_marks'] > 0): ?>
                                            <?php
                                            $pct = ((float) $std['obtained_marks'] / (float) $std['total_marks']) * 100;
                                            $grade = ($pct >= 85) ? 'A' : (($pct >= 75) ? 'B' : (($pct >= 65) ? 'C' : (($pct >= 50) ? 'D' : 'F')));
                                            ?>
                                            <span
                                                style="font-weight: 700; color: #1e293b;"><?php echo number_format($pct, 1); ?>%</span>
                                            <span
                                                class="status-pill <?php echo ($pct >= 50) ? 'pill-approved' : 'pill-rejected'; ?>"
                                                style="margin-left: 6px;">Grade <?php echo $grade; ?></span>
                                        <?php else: ?>
                                            <span style="color: #94a3b8;">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="font-size: 12px; color: #64748b;">
                                        <?php echo (!empty($std['evaluated_at'])) ? date('d M, Y', strtotime($std['evaluated_at'])) : '&mdash;'; ?>
                                    </td>
                                    <td>
                                        <button type="button" class="action-btn-sm btn-fsp-primary"
                                            onclick="openMarksModal(<?php echo (int) $std['student_id']; ?>, '<?php echo htmlspecialchars(addslashes($std['roll_no'])); ?>', '<?php echo htmlspecialchars(addslashes($std['student_name'])); ?>', '<?php echo htmlspecialchars(addslashes($std['session'])); ?>', <?php echo ($std['total_marks'] !== null) ? (float) $std['total_marks'] : 100; ?>, <?php echo ($std['obtained_marks'] !== null) ? (float) $std['obtained_marks'] : "''"; ?>)">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                            <?php echo ($std['obtained_marks'] !== null) ? 'Update Marks' : 'Award Marks'; ?>
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
</div>

<!-- ========================================== -->
<!-- TAB 4: Organizations & Site Supervisors -->
<!-- ========================================== -->
<div id="faculty-orgs" class="tab-content">
    <div class="card">
        <div class="card-header">
            <span><i class="fa-solid fa-user-tie"></i> Site Supervisor & Placement Details Directory</span>
            <span style="font-size: 13px; opacity: 0.9;">Industry Collaborators</span>
        </div>
        <div class="card-body">

            <?php if (empty($organizations)): ?>
                <div style="text-align: center; color: #64748b; padding: 30px;">
                    <i class="fa-solid fa-building-circle-exclamation fa-2x"
                        style="display: block; margin-bottom: 8px;"></i>
                    No organizations linked to assigned student internships yet.
                </div>
            <?php else: ?>
                <?php foreach ($organizations as $org): ?>
                    <div class="org-info-card">
                        <div class="org-info-header">
                            <div>
                                <h4><i class="fa-solid fa-building text-success"></i>
                                    <?php echo htmlspecialchars($org['org_name']); ?></h4>
                                <div style="display: flex; gap: 6px; margin-top: 4px;">
                                    <span class="status-pill pill-submitted" style="font-size: 11px;">
                                        Category: <?php echo htmlspecialchars($org['category'] ?? 'General'); ?>
                                    </span>
                                    <?php if (!empty($org['type'])): ?>
                                        <span class="status-pill" style="font-size: 11px; background-color: #3b82f6; color: white;">
                                            Type: <?php echo htmlspecialchars($org['type']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div>
                                <span class="revision-badge"><i class="fa-solid fa-location-dot"></i>
                                    <?php echo htmlspecialchars($org['address']); ?></span>
                            </div>
                        </div>

                        <div class="org-details-grid">
                            <!-- Organization Contact Person Block -->
                            <div class="org-detail-block">
                                <h5><i class="fa-solid fa-address-book"></i> Organization Contact Person</h5>
                                <p><strong>Name:</strong> <?php echo htmlspecialchars($org['contact_person_name'] ?? 'N/A'); ?>
                                </p>
                                <p><strong>Designation:</strong>
                                    <?php echo htmlspecialchars($org['contact_person_designation'] ?? 'N/A'); ?></p>
                                <p><strong>Cell No:</strong>
                                    <?php echo htmlspecialchars($org['contact_person_phone'] ?? 'N/A'); ?></p>
                                <p><strong>Email:</strong>
                                    <?php echo htmlspecialchars($org['contact_person_email'] ?? 'N/A'); ?></p>
                            </div>

                            <!-- Site Supervisor Block -->
                            <div class="org-detail-block">
                                <h5><i class="fa-solid fa-user-tie"></i> Assigned Site Supervisor</h5>
                                <p><strong>Name:</strong>
                                    <?php echo htmlspecialchars($org['site_supervisor_name'] ?? 'Not Assigned'); ?></p>
                                <p><strong>Designation:</strong>
                                    <?php echo htmlspecialchars($org['site_supervisor_designation'] ?? 'N/A'); ?></p>
                                <p><strong>Cell No:</strong>
                                    <?php echo htmlspecialchars($org['site_supervisor_phone'] ?? 'N/A'); ?></p>
                                <p><strong>Email:</strong>
                                    <?php echo htmlspecialchars($org['site_supervisor_email'] ?? 'N/A'); ?></p>
                            </div>

                            <!-- Internship Project Placement Block -->
                            <div class="org-detail-block">
                                <h5><i class="fa-solid fa-briefcase"></i> Placed Student & Project</h5>
                                <p><strong>Student:</strong> <?php echo htmlspecialchars($org['student_name']); ?>
                                    (<?php echo htmlspecialchars($org['roll_no']); ?>)</p>
                                <p><strong>Session:</strong> <?php echo htmlspecialchars($org['session']); ?></p>
                                <p><strong>Project:</strong> <?php echo htmlspecialchars($org['internship_title']); ?></p>
                                <p><strong>Duration:</strong> <?php echo (int) $org['duration_weeks']; ?> Weeks</p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: Review Weekly Report -->
<!-- ========================================== -->
<div id="reportReviewModal" class="modal-overlay">
    <div class="modal-container" style="max-width: 650px;">
        <div class="modal-header">
            <h3><i class="fa-solid fa-file-pen"></i> Review Weekly Internship Report</h3>
            <span class="modal-close" onclick="closeReportReviewModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form action="" method="POST">
                <input type="hidden" name="action_review_report" value="1">
                <input type="hidden" id="modal_report_id" name="report_id" value="">

                <div
                    style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; background: #f1f5f9; padding: 10px 14px; border-radius: 4px;">
                    <div>
                        <strong id="modal_student_info" style="color: #1e293b; font-size: 14px;"></strong>
                        <div id="modal_org_info" style="font-size: 12px; color: #64748b;"></div>
                    </div>
                    <div style="text-align: right;">
                        <span id="modal_week_badge" class="revision-badge" style="font-weight: 700;"></span>
                        <div id="modal_revision_status" style="font-size: 11px; margin-top: 3px;"></div>
                    </div>
                </div>

                <!-- Student Draft Task Description -->
                <div class="report-callout">
                    <div class="report-callout-title">
                        <i class="fa-solid fa-clipboard-list text-primary"></i> Student Task Description Draft:
                    </div>
                    <div id="modal_task_description" style="white-space: pre-wrap; font-size: 13px; color: #1e293b;">
                    </div>
                </div>

                <!-- Student Weekly Targets -->
                <div class="report-callout" style="border-left-color: #0284c7;">
                    <div class="report-callout-title" style="color: #0284c7;">
                        <i class="fa-solid fa-bullseye"></i> Weekly Objectives & Target Milestones:
                    </div>
                    <div id="modal_weekly_targets" style="white-space: pre-wrap; font-size: 13px; color: #1e293b;">
                    </div>
                </div>

                <!-- Focal Person Remarks (if any) -->
                <div id="modal_fp_remarks_container" class="report-callout"
                    style="border-left-color: #f59e0b; display: none;">
                    <div class="report-callout-title" style="color: #b45309;">
                        <i class="fa-solid fa-comment-dots"></i> Focal Person Remarks:
                    </div>
                    <div id="modal_fp_remarks" style="font-size: 13px; color: #1e293b;"></div>
                </div>

                <!-- Revision Limit Notice (Max 3 Revisions) -->
                <div id="revisionLimitAlert"
                    style="display: none; background: #fee2e2; color: #991b1b; padding: 10px 14px; border-radius: 4px; font-size: 12px; margin-bottom: 14px;">
                    <i class="fa-solid fa-triangle-exclamation"></i> <strong>Notice:</strong> This report has reached
                    its maximum allowed 3 revisions. You can Approve or Reject this submission.
                </div>

                <!-- Faculty Remarks Input -->
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="modal_faculty_remarks"><i class="fa-solid fa-comment-medical"></i> Faculty Supervisor
                        Remarks & Feedback:</label>
                    <textarea id="modal_faculty_remarks" name="faculty_remarks" rows="3" class="fsp-input"
                        placeholder="Provide constructive feedback, corrections, or approval notes..."></textarea>
                </div>

                <!-- Status Update Dropdown -->
                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="modal_report_status"><i class="fa-solid fa-traffic-light"></i> Decision Status:</label>
                    <select id="modal_report_status" name="report_status" class="fsp-select" required>
                        <option value="submitted">Submitted (Keep in Review)</option>
                        <option value="approved">Approved</option>
                        <option value="needs_improvement" id="opt_needs_improvement">Needs Improvement (Request Revision
                            & Resubmission)</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>

                <!-- Modal Actions -->
                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn-cancel" onclick="closeReportReviewModal()">Cancel</button>
                    <button type="submit" class="btn-primary-action">
                        <i class="fa-solid fa-check"></i> Save Decision & Remarks
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: Marks Evaluation -->
<!-- ========================================== -->
<div id="marksEvaluationModal" class="modal-overlay">
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header">
            <h3><i class="fa-solid fa-award"></i> Assign Student Marks</h3>
            <span class="modal-close" onclick="closeMarksModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form action="" method="POST" onsubmit="return validateMarksForm();">
                <input type="hidden" name="action_save_evaluation" value="1">
                <input type="hidden" id="marks_student_id" name="student_id" value="">
                <input type="hidden" id="marks_session" name="session" value="">

                <div
                    style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 12px 15px; margin-bottom: 18px;">
                    <div style="font-weight: 700; color: #1e293b; font-size: 15px;" id="marks_student_name"></div>
                    <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                        Roll No: <span id="marks_student_roll" style="font-weight: 600; color: #334155;"></span> &bull;
                        Session: <span id="marks_session_label" style="font-weight: 600; color: #334155;"></span>
                    </div>
                </div>

                <div class="form-grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 15px;">
                    <div class="form-group">
                        <label for="marks_total"><i class="fa-solid fa-calculator"></i> Total Marks:</label>
                        <input type="number" step="0.5" min="1" max="500" id="marks_total" name="total_marks"
                            value="100" class="fsp-input" required oninput="calculateGradePreview()">
                    </div>
                    <div class="form-group">
                        <label for="marks_obtained"><i class="fa-solid fa-star"></i> Obtained Marks:</label>
                        <input type="number" step="0.5" min="0" max="500" id="marks_obtained" name="obtained_marks"
                            class="fsp-input" placeholder="e.g. 85.5" required oninput="calculateGradePreview()">
                    </div>
                </div>

                <!-- Real-time Grade & Percentage Preview -->
                <div id="gradePreviewBox"
                    style="background: #e0f2fe; border: 1px solid #bae6fd; border-radius: 4px; padding: 10px 14px; margin-bottom: 18px; display: flex; justify-content: space-between; align-items: center; font-size: 13px;">
                    <div><strong>Score:</strong> <span id="previewPct">0.0%</span></div>
                    <div><strong>Calculated Grade:</strong> <span id="previewGrade"
                            class="status-pill pill-approved">--</span></div>
                </div>

                <div id="marksClientError"
                    style="display: none; background: #fee2e2; color: #991b1b; padding: 8px 12px; border-radius: 4px; font-size: 12px; margin-bottom: 15px;">
                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn-cancel" onclick="closeMarksModal()">Cancel</button>
                    <button type="submit" class="btn-primary-action">
                        <i class="fa-solid fa-floppy-disk"></i> Save Evaluation
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: SITE SUPERVISOR DETAILS POPUP       -->
<!-- ========================================== -->
<div id="supervisorDetailsModal" class="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="modal-container" style="background: #fff; width: 90%; max-width: 700px; border-radius: 6px; box-shadow: 0 5px 15px rgba(0,0,0,0.3); overflow: hidden; position: relative;">
        <div class="modal-header" style="background: #2e6652; color: #fff; padding: 14px 18px; display: flex; justify-content: space-between; align-items: center;">
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

<!-- ========================================== -->
<!-- Client-side Interactive Scripts -->
<!-- ========================================== -->
<script>
    // Filter Assigned Students by Session & Search
    function filterAssignedStudents() {
        const session = document.getElementById('sessionFilterSelect').value;
        const search = document.getElementById('studentSearchInput').value.toLowerCase().trim();
        const rows = document.querySelectorAll('#assignedStudentsTable tbody tr');

        rows.forEach(row => {
            const rowSession = row.getAttribute('data-session') || '';
            const rowSearch = row.getAttribute('data-search') || '';

            const normRowSession = rowSession.replace(/\s+/g, '-').toLowerCase();
            const normFilterSession = session.replace(/\s+/g, '-').toLowerCase();

            const matchesSession = (session === 'ALL' || normRowSession === normFilterSession);
            const matchesSearch = (search === '' || rowSearch.includes(search));

            if (matchesSession && matchesSearch) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    // Filter Reports by Status & Search
    function filterReportsList() {
        const status = document.getElementById('reportStatusFilter').value;
        const search = document.getElementById('reportSearchInput').value.toLowerCase().trim();
        const rows = document.querySelectorAll('#weeklyReportsTable tbody tr');

        rows.forEach(row => {
            const rowStatus = row.getAttribute('data-status');
            const rowSearch = row.getAttribute('data-search') || '';

            const matchesStatus = (status === 'ALL' || rowStatus === status);
            const matchesSearch = (search === '' || rowSearch.includes(search));

            if (matchesStatus && matchesSearch) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    // Helper to filter reports when clicking 'Reports' on Assigned Students table
    function filterReportsByStudent(rollNo) {
        const searchInput = document.getElementById('reportSearchInput');
        if (searchInput) {
            searchInput.value = rollNo;
            filterReportsList();
        }
    }

    // Filter Marks Evaluation Table
    function filterMarksList() {
        const session = document.getElementById('marksSessionFilter').value;
        const search = document.getElementById('marksSearchInput').value.toLowerCase().trim();
        const rows = document.querySelectorAll('#marksEvaluationTable tbody tr');

        rows.forEach(row => {
            const rowSession = row.getAttribute('data-session') || '';
            const rowSearch = row.getAttribute('data-search') || '';

            const normRowSession = rowSession.replace(/\s+/g, '-').toLowerCase();
            const normFilterSession = session.replace(/\s+/g, '-').toLowerCase();

            const matchesSession = (session === 'ALL' || normRowSession === normFilterSession);
            const matchesSearch = (search === '' || rowSearch.includes(search));

            if (matchesSession && matchesSearch) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    // Modal Handlers for Report Review
    function openReportReviewModal(reportId, rollNo, studentName, weekNumber, taskDesc, weeklyTargets, fpRemarks, facultyRemarks, status, revisionCount, orgName) {
        document.getElementById('modal_report_id').value = reportId;
        document.getElementById('modal_student_info').textContent = studentName + ' (' + rollNo + ')';
        document.getElementById('modal_org_info').textContent = orgName ? 'Placement: ' + orgName : '';
        document.getElementById('modal_week_badge').textContent = 'Week ' + weekNumber;

        const revSpan = document.getElementById('modal_revision_status');
        revSpan.textContent = 'Revisions Used: ' + revisionCount + ' / 3';
        revSpan.style.color = (revisionCount >= 3) ? '#dc2626' : '#475569';

        document.getElementById('modal_task_description').textContent = taskDesc || 'No description provided.';
        document.getElementById('modal_weekly_targets').textContent = weeklyTargets || 'No targets specified.';

        const fpContainer = document.getElementById('modal_fp_remarks_container');
        if (fpRemarks && fpRemarks.trim() !== '') {
            document.getElementById('modal_fp_remarks').textContent = fpRemarks;
            fpContainer.style.display = 'block';
        } else {
            fpContainer.style.display = 'none';
        }

        document.getElementById('modal_faculty_remarks').value = facultyRemarks || '';
        document.getElementById('modal_report_status').value = status;

        // Enforce 3 revision limit
        const optImprovement = document.getElementById('opt_needs_improvement');
        const alertBox = document.getElementById('revisionLimitAlert');
        if (revisionCount >= 3) {
            optImprovement.disabled = true;
            optImprovement.textContent = 'Needs Improvement (Max 3 Revisions Exceeded)';
            alertBox.style.display = 'block';
        } else {
            optImprovement.disabled = false;
            optImprovement.textContent = 'Needs Improvement (Request Revision & Resubmission)';
            alertBox.style.display = 'none';
        }

        document.getElementById('reportReviewModal').style.display = 'flex';
    }

    function closeReportReviewModal() {
        document.getElementById('reportReviewModal').style.display = 'none';
    }

    // Modal Handlers for Marks Evaluation
    function openMarksModal(studentId, rollNo, studentName, session, totalMarks, obtainedMarks) {
        document.getElementById('marks_student_id').value = studentId;
        document.getElementById('marks_session').value = session;
        document.getElementById('marks_student_name').textContent = studentName;
        document.getElementById('marks_student_roll').textContent = rollNo;
        document.getElementById('marks_session_label').textContent = session;

        document.getElementById('marks_total').value = totalMarks || 100;
        document.getElementById('marks_obtained').value = (obtainedMarks !== "''" && obtainedMarks !== null) ? obtainedMarks : '';

        document.getElementById('marksClientError').style.display = 'none';
        calculateGradePreview();
        document.getElementById('marksEvaluationModal').style.display = 'flex';
    }

    function closeMarksModal() {
        document.getElementById('marksEvaluationModal').style.display = 'none';
    }

    function calculateGradePreview() {
        const total = parseFloat(document.getElementById('marks_total').value) || 0;
        const obtained = parseFloat(document.getElementById('marks_obtained').value);
        const previewBox = document.getElementById('gradePreviewBox');
        const pctSpan = document.getElementById('previewPct');
        const gradeSpan = document.getElementById('previewGrade');

        if (!isNaN(obtained) && total > 0 && obtained >= 0) {
            const pct = (obtained / total) * 100;
            pctSpan.textContent = pct.toFixed(1) + '%';
            let grade = 'F';
            if (pct >= 85) grade = 'A';
            else if (pct >= 75) grade = 'B';
            else if (pct >= 65) grade = 'C';
            else if (pct >= 50) grade = 'D';

            gradeSpan.textContent = 'Grade ' + grade;
            gradeSpan.className = 'status-pill ' + ((pct >= 50) ? 'pill-approved' : 'pill-rejected');
            previewBox.style.display = 'flex';
        } else {
            pctSpan.textContent = '--';
            gradeSpan.textContent = '--';
        }
    }

    function validateMarksForm() {
        const total = parseFloat(document.getElementById('marks_total').value);
        const obtained = parseFloat(document.getElementById('marks_obtained').value);
        const errorDiv = document.getElementById('marksClientError');

        if (isNaN(total) || total <= 0) {
            errorDiv.textContent = 'Total marks must be greater than 0.';
            errorDiv.style.display = 'block';
            return false;
        }

        if (isNaN(obtained) || obtained < 0) {
            errorDiv.textContent = 'Please enter valid obtained marks (0 or above).';
            errorDiv.style.display = 'block';
            return false;
        }

        if (obtained > total) {
            errorDiv.textContent = 'Obtained marks (' + obtained + ') cannot exceed total marks (' + total + ').';
            errorDiv.style.display = 'block';
            return false;
        }

        errorDiv.style.display = 'none';
        return true;
    }

    // Close modals when clicking outside container
    window.addEventListener('click', function (e) {
        const repModal = document.getElementById('reportReviewModal');
        const marksModal = document.getElementById('marksEvaluationModal');
        const svModal = document.getElementById('supervisorDetailsModal');
        if (e.target === repModal) closeReportReviewModal();
        if (e.target === marksModal) closeMarksModal();
        if (e.target === svModal) closeSupervisorModal();
    });

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
        const studentFilter = document.getElementById('sessionFilterSelect');
        if (studentFilter) {
            filterAssignedStudents();
        }
        const marksFilter = document.getElementById('marksSessionFilter');
        if (marksFilter) {
            filterMarksList();
        }
    });
</script>
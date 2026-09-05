<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auth check
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'STD') {
    header('Location: login.php');
    exit;
}

require __DIR__ . '/includes/db.php';

$rollno = (string)($_SESSION['username'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);

// ── Fetch all student data ─────────────────────────────────────────────────
$profile = ['name' => '', 'fname' => '', 'cnic' => '', 'cell_no' => '', 'email' => '', 'address' => '', 'city' => '', 'dob' => ''];
$stmt = mysqli_prepare($conn, 'SELECT * FROM user_profile WHERE u_id = ? LIMIT 1');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res && ($row = mysqli_fetch_assoc($res))) $profile = array_merge($profile, $row);
    mysqli_stmt_close($stmt);
}

$semesterDetail = ['session' => '', 'semester' => '', 'department' => '', 'program' => '', 'batch' => '', 'section' => ''];
$stmt = mysqli_prepare($conn, 'SELECT * FROM user_semester_detail WHERE rollno = ? ORDER BY u_s_d_id DESC LIMIT 1');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 's', $rollno);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res && ($row = mysqli_fetch_assoc($res))) $semesterDetail = array_merge($semesterDetail, $row);
    mysqli_stmt_close($stmt);
}

$placement = ['org_name' => '', 'address' => '', 'site_supervisor_name' => '', 'site_supervisor_designation' => '', 'site_supervisor_phone' => '', 'site_supervisor_email' => '', 'internship_title' => '', 'duration_weeks' => 0];
$stmt = mysqli_prepare($conn, 'SELECT org_name, org_address AS address, site_supervisor_name, site_supervisor_designation, site_supervisor_cell AS site_supervisor_phone, site_supervisor_email, internship_title, internship_duration AS duration_weeks FROM site_supervisor_details WHERE rollno = ? LIMIT 1');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 's', $rollno);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res && ($row = mysqli_fetch_assoc($res))) $placement = array_merge($placement, $row);
    mysqli_stmt_close($stmt);
}

$facultySupervisor = ['full_name' => '', 'email' => '', 'phone' => '', 'designation' => ''];
$stmt = mysqli_prepare($conn, 'SELECT u.full_name, u.email, u.phone, u.designation FROM assign_faculty_supervisor afs JOIN users u ON afs.u_id = u.user_id WHERE afs.rollno = ?');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 's', $rollno);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res && ($row = mysqli_fetch_assoc($res))) $facultySupervisor = array_merge($facultySupervisor, $row);
    mysqli_stmt_close($stmt);
}

$fullReport = ['internship_title_custom' => '', 'internship_duration_custom' => '', 'internship_start_date' => '', 'internship_end_date' => '', 'learning_objectives' => '', 'tasks_performed' => '', 'learning_experience' => '', 'challenges_faced' => '', 'student_feedback' => ''];
$stmt = mysqli_prepare($conn, 'SELECT * FROM internship_full_report WHERE rollno = ? LIMIT 1');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 's', $rollno);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res && ($row = mysqli_fetch_assoc($res))) $fullReport = array_merge($fullReport, $row);
    mysqli_stmt_close($stmt);
}

$weeklyReports = [];
$stmt = mysqli_prepare($conn, 'SELECT wr.* FROM weekly_reports wr JOIN internships i ON wr.internship_id = i.internship_id JOIN students s ON i.student_id = s.student_id WHERE s.roll_no = ? ORDER BY wr.week_number ASC');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 's', $rollno);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) while ($row = mysqli_fetch_assoc($res)) $weeklyReports[] = $row;
    mysqli_stmt_close($stmt);
}

$activityLogs = [];
$stmt = mysqli_prepare($conn, 'SELECT * FROM internship_activity_log WHERE rollno = ? ORDER BY week_number ASC');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 's', $rollno);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) while ($row = mysqli_fetch_assoc($res)) $activityLogs[] = $row;
    mysqli_stmt_close($stmt);
}

$annexure2Logs = [];
$stmt = mysqli_prepare($conn, 'SELECT * FROM internship_annexure2 WHERE rollno = ? ORDER BY report_number ASC');
if ($stmt) {
    mysqli_stmt_bind_param($stmt, 's', $rollno);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    if ($res) while ($row = mysqli_fetch_assoc($res)) $annexure2Logs[] = $row;
    mysqli_stmt_close($stmt);
}

// Compute effective fields
$internshipTitle = $fullReport['internship_title_custom'] ?: $placement['internship_title'] ?: 'N/A';
$internshipDuration = $fullReport['internship_duration_custom'] ?: ($placement['duration_weeks'] ? $placement['duration_weeks'] . ' Weeks' : 'N/A');
$startDate = $fullReport['internship_start_date'] ? date('M d, Y', strtotime($fullReport['internship_start_date'])) : 'N/A';
$endDate   = $fullReport['internship_end_date']   ? date('M d, Y', strtotime($fullReport['internship_end_date']))   : 'N/A';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Internship Report – <?php echo htmlspecialchars($rollno); ?> | University of Haripur</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Times New Roman', Times, serif;
            background: #e8e8e8;
            color: #1a1a1a;
            font-size: 12pt;
            line-height: 1.6;
        }

        /* ── Print Control Bar ─────────────────────────── */
        .print-bar {
            background: linear-gradient(135deg, #2e6652, #26294d);
            color: #fff;
            padding: 12px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }
        .print-bar h1 { font-size: 16px; font-weight: 600; font-family: 'Segoe UI', sans-serif; }
        .print-bar .actions { display: flex; gap: 10px; }
        .btn-print {
            background: #fff;
            color: #2e6652;
            border: none;
            padding: 8px 20px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            font-family: 'Segoe UI', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .btn-print:hover { background: #f0fdf4; }
        .btn-back {
            background: rgba(255,255,255,0.15);
            color: #fff;
            border: 1px solid rgba(255,255,255,0.4);
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            font-family: 'Segoe UI', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }
        .btn-back:hover { background: rgba(255,255,255,0.25); }

        /* ── Page Container ─────────────────────────────── */
        .pages-container {
            max-width: 900px;
            margin: 30px auto;
            display: flex;
            flex-direction: column;
            gap: 30px;
            padding: 0 20px 60px 20px;
        }

        /* ── Single Page ─────────────────────────────────── */
        .report-page {
            background: #ffffff;
            width: 100%;
            min-height: 297mm;
            padding: 25mm 20mm 20mm 25mm;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            border-radius: 2px;
            position: relative;
            page-break-after: always;
        }
        .report-page:last-child { page-break-after: auto; }

        /* ── University Header ───────────────────────────── */
        .uni-header {
            display: flex;
            align-items: flex-start;
            gap: 16px;
            border-bottom: 3px double #1e293b;
            padding-bottom: 12px;
            margin-bottom: 10px;
        }
        .uni-logo-placeholder {
            width: 70px;
            height: 70px;
            border: 2px solid #2e6652;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            background: #f0fdf4;
            font-size: 10px;
            color: #2e6652;
            font-weight: bold;
            text-align: center;
            line-height: 1.2;
        }
        .uni-title-block { flex: 1; text-align: center; }
        .uni-name { font-size: 18pt; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; color: #1e293b; }
        .uni-dept { font-size: 11pt; color: #475569; margin-top: 3px; }
        .uni-tagline { font-size: 9pt; color: #64748b; margin-top: 2px; font-style: italic; }



        /* ── Info Grid ───────────────────────────────────── */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0;
            border: 1px solid #cbd5e1;
            border-radius: 2px;
            overflow: hidden;
        }
        .info-grid-item {
            display: flex;
            border-right: 1px solid #cbd5e1;
            border-bottom: 1px solid #cbd5e1;
        }
        .info-grid-item:nth-child(2n) { border-right: none; }
        .info-grid-item:nth-last-child(-n+2) { border-bottom: none; }
        .info-label-cell {
            background: #f8fafc;
            font-weight: bold;
            font-size: 9.5pt;
            padding: 8px 10px;
            min-width: 110px;
            color: #374151;
            border-right: 1px solid #cbd5e1;
        }
        .info-value-cell {
            padding: 8px 10px;
            font-size: 10pt;
            flex: 1;
            color: #1e293b;
        }

        /* ── Content Box ─────────────────────────────────── */
        .content-box {
            border: 1px solid #e2e8f0;
            border-radius: 2px;
            padding: 12px 14px;
            min-height: 60px;
            background: #fafafa;
            font-size: 10.5pt;
            color: #1e293b;
            margin-bottom: 14px;
            white-space: pre-wrap;
            word-break: break-word;
        }
        .content-box.empty {
            color: #94a3b8;
            font-style: italic;
        }

        /* ── Table Styles ────────────────────────────────── */
        .report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 9.5pt;
            margin-bottom: 16px;
        }
        .report-table th {
            background: #2e6652;
            color: #fff;
            padding: 8px 10px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #1e4d3a;
        }
        .report-table td {
            padding: 8px 10px;
            border: 1px solid #e2e8f0;
            vertical-align: top;
            color: #1e293b;
        }
        .report-table tr:nth-child(even) td { background: #f8fafc; }
        .report-table tr:last-child td { border-bottom: 1px solid #cbd5e1; }

        /* Status Pills */
        .status-pill {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 8.5pt;
            font-weight: bold;
        }
        .pill-submitted  { background: #dbeafe; color: #1d4ed8; }
        .pill-approved   { background: #dcfce7; color: #166534; }
        .pill-rejected   { background: #fee2e2; color: #991b1b; }
        .pill-needs_improvement { background: #fef3c7; color: #92400e; }

        /* ── Cover Page Specific ─────────────────────────── */
        .cover-center { text-align: center; }
        .cover-report-title {
            font-size: 22pt;
            font-weight: bold;
            color: #1e293b;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin: 40px 0 8px 0;
            border-top: 3px solid #2e6652;
            border-bottom: 3px solid #2e6652;
            padding: 14px 0;
        }
        .cover-subtitle { font-size: 13pt; color: #2e6652; font-style: italic; margin-bottom: 40px; }
        .cover-info-block {
            border: 2px solid #2e6652;
            border-radius: 4px;
            padding: 20px 30px;
            text-align: left;
            max-width: 480px;
            margin: 0 auto 40px auto;
        }
        .cover-info-row {
            display: flex;
            gap: 12px;
            margin-bottom: 10px;
            font-size: 11pt;
        }
        .cover-info-key { font-weight: bold; color: #374151; min-width: 120px; }
        .cover-info-val { color: #1e293b; }
        .cover-submitted-to {
            font-size: 10pt;
            color: #64748b;
            margin-top: 30px;
        }

        /* ── Base Styles ─────────────────────────────────── */
        body {
            font-family: 'Times New Roman', Times, serif;
            background: #e8e8e8;
            color: #000;
            font-size: 11pt;
            line-height: 1.4;
        }

        /* ── Print Control Bar ─────────────────────────── */
        .print-bar {
            background: #1e293b;
            color: #fff;
            padding: 12px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .print-bar h1 { font-size: 16px; font-weight: 600; font-family: sans-serif; margin: 0; }
        .print-bar .actions { display: flex; gap: 10px; }
        .btn-print {
            background: #fff; color: #1e293b; border: none; padding: 8px 20px; border-radius: 4px;
            font-size: 14px; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
        }
        .btn-back {
            background: rgba(255,255,255,0.2); color: #fff; border: 1px solid rgba(255,255,255,0.4);
            padding: 8px 16px; border-radius: 4px; font-size: 14px; cursor: pointer; text-decoration: none;
        }

        /* ── Pages Container ─────────────────────────────── */
        .pages-container {
            max-width: 850px;
            margin: 30px auto;
            display: flex;
            flex-direction: column;
            gap: 30px;
            padding: 0 20px 60px 20px;
        }

        /* ── Report Page ─────────────────────────────────── */
        .report-page {
            background: #ffffff;
            width: 100%;
            min-height: 297mm;
            padding: 20mm;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            position: relative;
            page-break-after: always;
        }
        .report-page-bordered {
            border: 6px double #000;
            padding: 15mm;
        }

        /* ── Header ──────────────────────────────────────── */
        .pdf-header {
            text-align: center;
            color: #003399; /* Blue text */
            font-style: italic;
            margin-bottom: 20px;
        }
        .pdf-header .dept {
            font-weight: bold;
            font-size: 12pt;
        }
        .pdf-header .uni {
            font-weight: bold;
            font-size: 14pt;
        }
        .pdf-header .details {
            font-size: 10pt;
        }
        .pdf-header .line {
            display: inline-block;
            border-bottom: 1px solid #003399;
            min-width: 150px;
        }

        /* ── Titles ──────────────────────────────────────── */
        .annex-title {
            text-align: right;
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 5px;
            font-size: 11pt;
            color: #000;
        }
        .main-title {
            text-align: center;
            font-weight: bold;
            text-decoration: underline;
            margin-bottom: 20px;
            font-size: 12pt;
            color: #000;
        }
        .section-title {
            text-align: center;
            font-weight: bold;
            text-decoration: underline;
            margin: 20px 0 10px 0;
            font-size: 12pt;
            color: #000;
        }

        /* ── Grid Details ────────────────────────────────── */
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            column-gap: 20px;
            row-gap: 5px;
            margin-bottom: 10px;
            color: #000;
        }
        .detail-row {
            display: flex;
            align-items: baseline;
        }
        .detail-label {
            font-weight: bold;
            white-space: nowrap;
            margin-right: 5px;
        }
        .detail-value {
            flex-grow: 1;
            border-bottom: 1px solid #000;
            min-height: 16px;
            font-weight: bold;
            padding-left: 5px;
        }

        .form-section {
            margin-bottom: 10px;
            color: #000;
        }
        .form-section-title {
            font-weight: bold;
            margin-bottom: 2px;
        }
        .underline-box {
            width: 100%;
            border-bottom: 1px solid #000;
            line-height: 18px;
            min-height: 18px;
            margin-bottom: 4px;
        }

        /* ── Tables ──────────────────────────────────────── */
        .pdf-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            color: #000;
        }
        .pdf-table th, .pdf-table td {
            border: 1px solid #000;
            padding: 8px;
            vertical-align: top;
        }
        .pdf-table th {
            font-weight: bold;
            text-align: center;
            background: #e2e8f0;
        }
        .pdf-table .center {
            text-align: center;
        }

        .signatures {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            color: #000;
        }
        .sig-block {
            display: flex;
            align-items: baseline;
        }
        .sig-label {
            font-weight: bold;
            margin-right: 5px;
        }
        .sig-line {
            border-bottom: 1px solid #000;
            width: 180px;
        }

        /* ── Footer ──────────────────────────────────────── */
        .page-footer {
            position: absolute;
            bottom: 10mm;
            left: 15mm;
            right: 15mm;
            border-top: 3px double #6c2323;
            padding-top: 5px;
            display: flex;
            justify-content: space-between;
            font-style: italic;
            font-size: 10pt;
            color: #000;
        }

        /* ── Print Media ─────────────────────────────────── */
        @media print {
            body { background: #fff !important; }
            .print-bar { display: none !important; }
            .pages-container { max-width: 100%; margin: 0; padding: 0; gap: 0; }
            .report-page {
                box-shadow: none !important;
                border-radius: 0 !important;
                padding: 15mm !important;
                min-height: 100vh;
            }
        }
    </style>
</head>
<body>

<div class="print-bar">
    <h1>Internship Submission Report — <?php echo htmlspecialchars($profile['name'] ?: $rollno); ?></h1>
    <div class="actions">
        <a href="javascript:history.back()" class="btn-back">Back</a>
        <button class="btn-print" onclick="window.print()">Print / Save as PDF</button>
    </div>
</div>

<div class="pages-container">

    <!-- ══════════════════════════════════════════════════════════════════ -->
    <!--  PAGES: ANNEXURE-2 (SECTION-A) COMPREHENSIVE REPORT                -->
    <!-- ══════════════════════════════════════════════════════════════════ -->
    <?php 
        $a2Log = $annexure2Logs[0] ?? null;
        $rNum = (int)($a2Log['report_number'] ?? 0);
        $tasksAct = $a2Log['tasks_performed'] ?? '';
    ?>
    <div class="report-page report-page-bordered">
        <div class="pdf-header">
            <div class="dept">Department of <span class="line" style="color:#000; font-style:normal;"><?php echo htmlspecialchars($semesterDetail['department'] ?: ''); ?></span></div>
            <div class="uni">The University of Haripur</div>
            <div class="details">
                Tel: <span class="line" style="width:100px;"></span>, Fax: <span class="line" style="width:120px;"></span><br>
                Website: <span style="text-decoration:underline;">www.uoh.edu.pk</span><br>
                Email: <span class="line" style="width:100px;"></span>@uoh.edu.pk
            </div>
        </div>

        <div class="annex-title">Annexure-2</div>
        <div class="main-title">STUDENT INTERNSHIP REPORT FORM</div>

        <div class="details-grid">
            <div class="detail-row">
                <div class="detail-label">Student-intern Name:</div>
                <div class="detail-value"><?php echo htmlspecialchars($profile['name'] ?: ''); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Roll No:</div>
                <div class="detail-value"><?php echo htmlspecialchars($rollno); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Program:</div>
                <div class="detail-value"><?php echo htmlspecialchars($semesterDetail['program'] ?: ''); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Faculty supervisor:</div>
                <div class="detail-value"><?php echo htmlspecialchars($facultySupervisor['full_name'] ?: ''); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Host Institution Name:</div>
                <div class="detail-value"><?php echo htmlspecialchars($placement['org_name'] ?: ''); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Host Institution address:</div>
                <div class="detail-value"><?php echo htmlspecialchars($placement['address'] ?: ''); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Site supervisor Name:</div>
                <div class="detail-value"><?php echo htmlspecialchars($placement['site_supervisor_name'] ?: ''); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Designation:</div>
                <div class="detail-value"><?php echo htmlspecialchars($placement['site_supervisor_designation'] ?: ''); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Site supervisor Cell:</div>
                <div class="detail-value"><?php echo htmlspecialchars($placement['site_supervisor_phone'] ?: ''); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Site supervisor Email:</div>
                <div class="detail-value"><?php echo htmlspecialchars($placement['site_supervisor_email'] ?: ''); ?></div>
            </div>
        </div>

        <div style="margin-bottom: 5px;">
            <strong>Reporting Period:</strong> &nbsp;&nbsp;
            From <strong>___/___/20___</strong> &nbsp;
            to <strong>___/___/20___</strong>
        </div>
        <div style="margin-bottom: 20px;">
            <strong>Report Number (circle one):</strong> &nbsp;&nbsp;
            <?php echo $rNum === 1 ? '&#9673;' : '&#9711;'; ?> 1 <span style="font-size:8pt;">(Week 1-2)</span> &nbsp;&nbsp;
            <?php echo $rNum === 2 ? '&#9673;' : '&#9711;'; ?> 2 <span style="font-size:8pt;">(Week 3-4)</span> &nbsp;&nbsp;
            <?php echo $rNum === 3 ? '&#9673;' : '&#9711;'; ?> 3 <span style="font-size:8pt;">(Week 5-6)</span> &nbsp;&nbsp;
            <?php echo $rNum === 4 ? '&#9673;' : '&#9711;'; ?> 4 <span style="font-size:8pt;">(Week 7-8)</span>
        </div>

        <div class="section-title">Section-A</div>
        
        <div style="font-weight: bold; margin-bottom: 10px;">Instructions:</div>
        <div style="margin-bottom: 15px;">The intern will complete this section:</div>

        <div class="form-section">
            <div class="form-section-title">a) Task(s) performed <span style="font-weight:normal;">(Includes major duties designated to you by site supervisor and assignments you have completed.)</span></div>
            <?php 
                if ($tasksAct) {
                    echo '<div style="margin-top:5px; margin-bottom:10px; white-space:pre-wrap;">' . htmlspecialchars($tasksAct) . '</div>';
                } else {
                    echo '<div class="underline-box"></div><div class="underline-box"></div><div class="underline-box"></div><div class="underline-box"></div>';
                }
            ?>
        </div>

        <div class="form-section">
            <div class="form-section-title">b) Learning Experience <span style="font-weight:normal;">(Communicate skills and knowledge that you gained or refined through the internship so far).</span></div>
            <?php 
                $exp = trim($a2Log['learning_experience'] ?? '');
                if ($exp) {
                    echo '<div style="margin-top:5px; margin-bottom:10px; white-space:pre-wrap;">' . htmlspecialchars($exp) . '</div>';
                } else {
                    echo '<div class="underline-box"></div><div class="underline-box"></div><div class="underline-box"></div><div class="underline-box"></div>';
                }
            ?>
        </div>

        <div class="form-section">
            <div class="form-section-title">c) Challenges <span style="font-weight:normal;">(Detail major challenges in your role and how you tackled them).</span></div>
            <?php 
                $chal = trim($a2Log['challenges_faced'] ?? '');
                if ($chal) {
                    echo '<div style="margin-top:5px; margin-bottom:10px; white-space:pre-wrap;">' . htmlspecialchars($chal) . '</div>';
                } else {
                    echo '<div class="underline-box"></div><div class="underline-box"></div><div class="underline-box"></div><div class="underline-box"></div>';
                }
            ?>
        </div>

        <div class="signatures">
            <div class="sig-block">
                <div class="sig-label">Student's Signature:</div>
                <div class="sig-line"></div>
            </div>
            <div class="sig-block">
                <div class="sig-label">Date of submission:</div>
                <div class="sig-line" style="width:140px;"></div>
            </div>
        </div>

        <div class="page-footer">
            <span>Internship/Field Experience Policy - 2023</span>
            <span>Page 8</span>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════ -->
    <!--  PAGE 2: ANNEXURE-3 (ACTIVITY LOG)                                 -->
    <!-- ══════════════════════════════════════════════════════════════════ -->
    <div class="report-page report-page-bordered">
        <div class="pdf-header">
            <div class="dept">Department of <span class="line" style="color:#000; font-style:normal;"><?php echo htmlspecialchars($semesterDetail['department'] ?: ''); ?></span></div>
            <div class="uni">The University of Haripur</div>
            <div class="details">
                Tel: <span class="line" style="width:100px;"></span>, Fax: <span class="line" style="width:120px;"></span><br>
                Website: <span style="text-decoration:underline;">www.uoh.edu.pk</span><br>
                Email: <span class="line" style="width:100px;"></span>@uoh.edu.pk
            </div>
        </div>

        <div class="annex-title">Annexure-3</div>
        <div class="main-title">SAMPLE STUDENT INTERNSHIP ACTIVITY LOG</div>

        <div class="details-grid" style="margin-bottom:15px;">
            <div class="detail-row">
                <div class="detail-label">Student-intern Name:</div>
                <div class="detail-value"><?php echo htmlspecialchars($profile['name'] ?: ''); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Roll No:</div>
                <div class="detail-value"><?php echo htmlspecialchars($rollno); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Program:</div>
                <div class="detail-value"><?php echo htmlspecialchars($semesterDetail['program'] ?: ''); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Faculty supervisor:</div>
                <div class="detail-value"><?php echo htmlspecialchars($facultySupervisor['full_name'] ?: ''); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Host Institution Name:</div>
                <div class="detail-value"><?php echo htmlspecialchars($placement['org_name'] ?: ''); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Host Institution address:</div>
                <div class="detail-value"><?php echo htmlspecialchars($placement['address'] ?: ''); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Site supervisor Name:</div>
                <div class="detail-value"><?php echo htmlspecialchars($placement['site_supervisor_name'] ?: ''); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Designation:</div>
                <div class="detail-value"><?php echo htmlspecialchars($placement['site_supervisor_designation'] ?: ''); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Site supervisor Cell:</div>
                <div class="detail-value"><?php echo htmlspecialchars($placement['site_supervisor_phone'] ?: ''); ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Site supervisor Email:</div>
                <div class="detail-value"><?php echo htmlspecialchars($placement['site_supervisor_email'] ?: ''); ?></div>
            </div>
        </div>

        <div style="font-weight: bold; margin-bottom: 10px;">Instructions:</div>
        <div style="margin-bottom: 15px; line-height: 1.5; padding-left: 20px;">
            1. The student-intern has to fill this form by recording major tasks assigned by the Site Supervisor and tasks performed during the given period.<br>
            2. Respective Site Supervisor shall review and evaluate the student's activity log.<br>
            3. Lastly, intern has to present this form to Faculty supervisor for review, who will discuss the activities, sign against the respective period and return it to internee for next period report. After the last reporting period, Faculty supervisor will keep it in the student record.
        </div>

        <table class="pdf-table">
            <thead>
                <tr>
                    <th style="width: 12%;">Weeks</th>
                    <th style="width: 40%;">Tasks Assigned and Performed</th>
                    <th style="width: 16%;">Activity<br>Period Dates</th>
                    <th style="width: 16%;">Site<br>Supervisor<br>Signature</th>
                    <th style="width: 16%;">Faculty<br>Supervisor<br>Signature</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                // Guarantee exactly 4 rows representing the 4 biweekly periods
                $defaultTasks = "1. \n2. \n3. ";
                $rows = [
                    ['label' => "Week\n1 & 2", 'activities' => $defaultTasks, 'dates' => ''],
                    ['label' => "Week\n3 & 4", 'activities' => $defaultTasks, 'dates' => ''],
                    ['label' => "Week\n5 & 6", 'activities' => $defaultTasks, 'dates' => ''],
                    ['label' => "Week\n7 & 8", 'activities' => $defaultTasks, 'dates' => '']
                ];
                
                // Populate rows with any saved data
                if (!empty($activityLogs)) {
                    foreach ($activityLogs as $index => $log) {
                        if (isset($rows[$index])) {
                            $act = trim($log['activities']);
                            if ($act !== '') {
                                // If the saved data doesn't seem to contain numbering, format it
                                if (strpos($act, '1.') === false) {
                                    $lines = explode("\n", str_replace("\r", "", $act));
                                    $formattedAct = "";
                                    for ($j = 1; $j <= 3; $j++) {
                                        $line = isset($lines[$j-1]) ? trim($lines[$j-1]) : '';
                                        $formattedAct .= $j . ". " . $line . "\n";
                                    }
                                    $rows[$index]['activities'] = trim($formattedAct);
                                } else {
                                    $rows[$index]['activities'] = $log['activities'];
                                }
                            }
                            $rows[$index]['dates'] = $log['date_range'];
                        }
                    }
                }
                
                foreach ($rows as $row): 
                ?>
                <tr>
                    <td class="center" style="font-weight:bold; white-space:pre-wrap; vertical-align:middle;"><?php echo htmlspecialchars($row['label']); ?></td>
                    <td style="white-space:pre-wrap; min-height: 80px;"><?php echo htmlspecialchars($row['activities']); ?></td>
                    <td class="center" style="vertical-align:middle;"><?php echo htmlspecialchars($row['dates']); ?></td>
                    <td></td>
                    <td></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="page-footer">
            <span>Internship/Field Experience Policy - 2023</span>
            <span>Page 10</span>
        </div>
    </div>

</div><!-- /pages-container -->

</body>
</html>

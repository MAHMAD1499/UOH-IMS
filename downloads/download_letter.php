<?php
/**
 * Internship Letter PDF Generator
 * 
 * Generates an official A4 internship recommendation letter as a downloadable PDF.
 * Uses the mPDF library to convert an HTML template (with university letterhead,
 * student details, and focal person signature) into a PDF document.
 * 
 * Access control:
 *   - Students (STD) can only download their own letter after Focal Person approval.
 *   - Focal Persons (FP) and Faculty Supervisors (FSP) can download any student's
 *     letter by providing ?rollno=<roll_number> in the query string.
 * 
 * A "DRAFT" watermark is applied when the letter has NOT yet been approved.
 * 
 * Dependencies:
 *   - includes/db.php       — Database connection
 *   - vendor/autoload.php   — Composer autoloader (loads mPDF)
 * 
 * @file    download_letter.php
 * @project Internship Management System (IMS) — University of Haripur
 */
session_start();

/* ── Authentication Guard ─────────────────────────────────────────────── */
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

/* ── Dependencies ─────────────────────────────────────────────────────── */
require __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

$role = $_SESSION['user_type'] ?? 'STD';

// Determine the target roll number
if ($role === 'STD') {
    $rollno = (string) ($_SESSION['username'] ?? '');
} else {
    // For Focal Person or Faculty Supervisor, they must provide the student's roll number via GET
    $rollno = (string) ($_GET['rollno'] ?? '');
    if ($rollno === '') {
        die('Roll number is required.');
    }
}

/* ── Fetch Student Profile (name, father name, etc.) ─────────────────── */
$profile = [
    'name' => '',
    'fname' => '',
];

$profileStmt = mysqli_prepare($conn, 'SELECT * FROM user_profile WHERE rollno_Empno = ? LIMIT 1');
if ($profileStmt) {
    mysqli_stmt_bind_param($profileStmt, 's', $rollno);
    mysqli_stmt_execute($profileStmt);
    $profileResult = mysqli_stmt_get_result($profileStmt);
    if ($profileResult && ($row = mysqli_fetch_assoc($profileResult))) {
        $profile = array_merge($profile, $row);
    }
    mysqli_stmt_close($profileStmt);
}

/* ── Fetch Student Semester/Academic Details ──────────────────────────── */
$semesterDetail = [
    'session' => '',
    'department' => '',
    'program' => '',
];

$semesterStmt = mysqli_prepare($conn, 'SELECT * FROM user_semester_detail WHERE rollno = ? ORDER BY u_s_d_id DESC LIMIT 1');
if ($semesterStmt) {
    mysqli_stmt_bind_param($semesterStmt, 's', $rollno);
    mysqli_stmt_execute($semesterStmt);
    $semesterResult = mysqli_stmt_get_result($semesterStmt);
    if ($semesterResult && ($row = mysqli_fetch_assoc($semesterResult))) {
        $semesterDetail = array_merge($semesterDetail, $row);
    }
    mysqli_stmt_close($semesterStmt);
}

// Fetch Focal Person for the student based on Department
$studentFocalPerson = [
    'full_name' => 'Dr. Muhammad Faizan Khan',
    'email' => 'khanmuhammadfaizan@uoh.edu.pk',
    'designation' => 'Internship Focal Person'
];
$deptString = $semesterDetail['department'] ?? '';
if (stripos($deptString, 'Information Technology') !== false || stripos($deptString, 'IT') !== false) {
    $fpQueryStr = '%IT%';
} elseif (stripos($deptString, 'Computer Science') !== false || stripos($deptString, 'CS') !== false) {
    $fpQueryStr = '%CS%';
} else {
    $fpQueryStr = '%IT%'; // Default fallback
}

$fpStmt = mysqli_prepare($conn, "SELECT full_name, email, phone, designation FROM users WHERE role = 'focal_person' AND full_name LIKE ? LIMIT 1");
if ($fpStmt) {
    mysqli_stmt_bind_param($fpStmt, "s", $fpQueryStr);
    mysqli_stmt_execute($fpStmt);
    $fpRes = mysqli_stmt_get_result($fpStmt);
    if ($fpRes && ($row = mysqli_fetch_assoc($fpRes))) {
        $studentFocalPerson['full_name'] = $row['full_name'];
        $studentFocalPerson['email'] = $row['email'];
        $studentFocalPerson['designation'] = $row['designation'] ?: 'Internship Focal Person';
    }
    mysqli_stmt_close($fpStmt);
}

/* ── Validations ──────────────────────────────────────────────────────── */
/* 1. Completeness Check — student name and program must be filled in */
if (empty(trim($profile['name'] ?? '')) || empty(trim($semesterDetail['program'] ?? ''))) {
    die('<div style="font-family: sans-serif; padding: 20px; color: red; border: 1px solid red; background: #fff1f1; border-radius: 5px; max-width: 500px; margin: 50px auto; line-height: 1.5;"><strong>Error:</strong> Your profile or academic details are incomplete. Please update your full name and program details in the dashboard before downloading the letter. <br><br><a href="javascript:history.back()" style="color: blue; text-decoration: none;">&larr; Go Back</a></div>');
}

// 2. Approval Check
$isApproved = (int)($semesterDetail['letter_approved'] ?? 0) === 1;
if ($role === 'STD' && !$isApproved) {
    die('<div style="font-family: sans-serif; padding: 20px; color: red; border: 1px solid red; background: #fff1f1; border-radius: 5px; max-width: 500px; margin: 50px auto; line-height: 1.5;"><strong>Error:</strong> Your internship letter has not been approved by the Focal Person yet. Please wait for approval or contact your department. <br><br><a href="javascript:history.back()" style="color: blue; text-decoration: none;">&larr; Go Back</a></div>');
}
// -------------------

/* ── Prepare display values for the letter template ───────────────────── */
$progFull = htmlspecialchars($semesterDetail['program'] ?: 'Bachelor of Science in Artificial Intelligence');
$progShort = str_replace(['Bachelor of Science in ', 'Bachelor of Science '], ['BS ', 'BS '], $progFull);
$sessionParts = explode(' ', $semesterDetail['session'] ?? 'Summer / Fall / Spring');
$sessionName = $sessionParts[0] ?: 'Summer / Fall / Spring';
$deptShort = (stripos($semesterDetail['department'] ?? '', 'Computer Science') !== false) ? 'CS' : 'IT';

$studentName = htmlspecialchars($profile['name'] ?: '[Student Name]');
$studentRollNo = htmlspecialchars($rollno);
$currentDate = date('d/m/Y');
$focalDesignation = htmlspecialchars($studentFocalPerson['designation']);
$focalName = htmlspecialchars($studentFocalPerson['full_name']);
$focalEmail = htmlspecialchars($studentFocalPerson['email']);

// Get absolute path to logo
$logoPath = __DIR__ . '/../assets/img/uoh_logo.png';
$logoSrc = file_exists($logoPath) ? $logoPath : '../assets/img/uoh_logo.png';

/* ── Build the HTML template for the PDF letter ──────────────────────── */
$html = <<<HTML
<div style="font-family: 'Times New Roman', Times, serif; color: #000; line-height: 1.6; padding: 20px;">
    
    <table width="100%" style="border-bottom: 2px solid #000; margin-bottom: 20px; padding-bottom: 10px;">
        <tr>
            <td width="20%" style="text-align: left; vertical-align: middle;">
                <img src="$logoSrc" alt="UoH Logo" style="width: 100px;">
            </td>
            <td width="80%" style="text-align: center; vertical-align: middle;">
                <h2 style="font-size: 20px; color: #000; text-transform: uppercase; margin: 0; font-weight: bold; text-decoration: underline;">
                    DEPARTMENT OF INFORMATION TECHNOLOGY
                </h2>
                <p style="font-size: 16px; color: #000; margin: 5px 0; font-weight: bold;">
                    The University of Haripur, Khyber Pakhtunkhwa
                </p>
                <p style="font-size: 14px; margin: 0;">
                    <a href="http://www.uoh.edu.pk" style="color: blue; text-decoration: underline;">www.uoh.edu.pk</a>
                </p>
            </td>
        </tr>
    </table>

    <table width="100%" style="font-size: 14px; margin-bottom: 30px;">
        <tr>
            <td style="text-align: left;">
                <span style="text-decoration: underline;">F. No. UoH/IT/</span> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
            </td>
            <td style="text-align: right;">
                <span style="text-decoration: underline;">Dated: $currentDate</span>
            </td>
        </tr>
    </table>

    <h3 style="text-align: center; font-weight: bold; font-size: 18px; margin-bottom: 25px;">
        To Whom It May Concern,
    </h3>

    <p style="font-size: 15px; margin-bottom: 15px; text-align: justify;">
        This is to certify that <strong>$studentName</strong>, bearing Student ID <strong>$studentRollNo</strong>, is currently enrolled in the <strong>$progFull</strong> program at University of Haripur.
    </p>

    <p style="font-size: 15px; margin-bottom: 15px; text-align: justify;">
        As part of the degree requirements of the <span style="font-weight: bold;">$progShort</span> program, students are required to complete an industry internship. This internship is intended to provide practical exposure and hands-on experience related to their field of study, helping them bridge the gap between theoretical knowledge and real-world applications.
    </p>

    <p style="font-size: 15px; margin-bottom: 15px; text-align: justify;">
        We kindly request your organization to consider <strong>$studentName</strong> for an internship opportunity in your esteemed organization. The duration of the internship is <strong>6-8 weeks</strong>, and it is expected to be conducted during the <strong>$sessionName</strong> session of the academic calendar. Upon completion, students are required to submit an internship report and obtain an evaluation from the host organization.
    </p>

    <p style="font-size: 15px; margin-bottom: 15px; text-align: justify;">
        We would greatly appreciate your support in providing internship to <strong>$studentName</strong> with an opportunity to gain valuable experience in the professional field.
    </p>

    <p style="font-size: 15px; margin-bottom: 40px; text-align: left;">
        If you require any further information, please feel free to contact us.
    </p>

    <div style="font-size: 15px; text-align: left; margin-top: 50px;">
        <p style="margin-bottom: 50px;">Sincerely,</p>
        <p style="margin: 0;"><strong>$focalDesignation</strong></p>
        <p style="margin: 0;">$focalName</p>
        <p style="margin: 0;">Department of $deptShort</p>
        <p style="margin: 0;">Email: $focalEmail</p>
    </div>
</div>
HTML;

/* ── Generate the PDF using mPDF and send it as a download ───────────── */
try {
    $mpdf = new \Mpdf\Mpdf([
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 15,
        'format' => 'A4',
        'default_font' => 'times'
    ]);
    
    // Add watermark if not approved
    if (!$isApproved) {
        $mpdf->SetWatermarkText('DRAFT');
        $mpdf->showWatermarkText = true;
        $mpdf->watermarkTextAlpha = 0.1;
    }
    
    // Write HTML content
    $mpdf->WriteHTML($html);
    
    // Output PDF to browser for download
    $filename = 'Internship_Letter_' . $studentRollNo . '.pdf';
    $mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);
} catch (\Mpdf\MpdfException $e) {
    die("Error generating PDF: " . $e->getMessage());
}

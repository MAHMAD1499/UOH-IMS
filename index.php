<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require __DIR__ . '/includes/db.php';

$role = $_SESSION['user_type'] ?? 'STD';
$rollno = (string) ($_SESSION['username'] ?? '');
$userId = (int) ($_SESSION['user_id'] ?? 0);
$flashMessage = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

$profile = [
    'name' => '',
    'fname' => '',
    'cnic' => '',
    'cell_no' => '',
    'email' => '',
    'rollno_Empno' => $rollno,
    'address' => '',
    'city' => '',
];

$semesterDetail = [
    'session' => '',
    'semester' => '',
];

$latestReport = [
    'report_detail' => '',
    'report_ref_img' => '',
    'report_marks' => '',
    'report_feedback' => '',
];

$latestMarks = [
    'intern_total_obt_marks' => '',
    'total_marks' => '',
];

function redirectWithFlash(string $message, string $type = 'success'): void
{
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
    header('Location: index.php');
    exit;
}

if ($role === 'STD') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['save_academic'])) {
            $academicSession = trim($_POST['session'] ?? '');
            $academicSemester = trim($_POST['semester'] ?? '');

            if ($rollno === '' || $academicSession === '' || $academicSemester === '') {
                redirectWithFlash('Roll number, session, and semester are required.', 'error');
            }

            $checkStmt = mysqli_prepare($conn, 'SELECT u_s_d_id FROM user_semester_detail WHERE rollno = ? LIMIT 1');
            if ($checkStmt) {
                mysqli_stmt_bind_param($checkStmt, 's', $rollno);
                mysqli_stmt_execute($checkStmt);
                $existingAcademic = mysqli_stmt_get_result($checkStmt);
                $academicRow = $existingAcademic ? mysqli_fetch_assoc($existingAcademic) : null;
                mysqli_stmt_close($checkStmt);

                if ($academicRow) {
                    $updateStmt = mysqli_prepare($conn, 'UPDATE user_semester_detail SET session = ?, semester = ? WHERE rollno = ?');
                    if ($updateStmt) {
                        mysqli_stmt_bind_param($updateStmt, 'sss', $academicSession, $academicSemester, $rollno);
                        mysqli_stmt_execute($updateStmt);
                        mysqli_stmt_close($updateStmt);
                    }
                } else {
                    $insertStmt = mysqli_prepare($conn, 'INSERT INTO user_semester_detail (rollno, session, semester) VALUES (?, ?, ?)');
                    if ($insertStmt) {
                        mysqli_stmt_bind_param($insertStmt, 'sss', $rollno, $academicSession, $academicSemester);
                        mysqli_stmt_execute($insertStmt);
                        mysqli_stmt_close($insertStmt);
                    }
                }
                redirectWithFlash('Academic details saved.');
            }
        }

        if (isset($_POST['save_profile'])) {
            $name = trim($_POST['name'] ?? '');
            $fname = trim($_POST['fname'] ?? '');
            $cnic = trim($_POST['cnic'] ?? '');
            $cellNo = trim($_POST['cellno'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $city = trim($_POST['city'] ?? '');

            if ($userId <= 0 || $rollno === '' || $name === '' || $fname === '' || $cnic === '' || $cellNo === '' || $email === '') {
                redirectWithFlash('Please fill in all required profile fields.', 'error');
            }

            $checkStmt = mysqli_prepare($conn, 'SELECT u_p_id FROM user_profile WHERE u_id = ? LIMIT 1');
            if ($checkStmt) {
                mysqli_stmt_bind_param($checkStmt, 'i', $userId);
                mysqli_stmt_execute($checkStmt);
                $existingProfile = mysqli_stmt_get_result($checkStmt);
                $profileRow = $existingProfile ? mysqli_fetch_assoc($existingProfile) : null;
                mysqli_stmt_close($checkStmt);

                if ($profileRow) {
                    $updateStmt = mysqli_prepare($conn, 'UPDATE user_profile SET name = ?, fname = ?, cnic = ?, cell_no = ?, email = ?, rollno_Empno = ?, address = ?, city = ? WHERE u_id = ?');
                    if ($updateStmt) {
                        mysqli_stmt_bind_param($updateStmt, 'ssssssssi', $name, $fname, $cnic, $cellNo, $email, $rollno, $address, $city, $userId);
                        mysqli_stmt_execute($updateStmt);
                        mysqli_stmt_close($updateStmt);
                    }
                } else {
                    $insertStmt = mysqli_prepare($conn, 'INSERT INTO user_profile (u_id, name, fname, cnic, cell_no, email, rollno_Empno, address, city) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    if ($insertStmt) {
                        mysqli_stmt_bind_param($insertStmt, 'issssssss', $userId, $name, $fname, $cnic, $cellNo, $email, $rollno, $address, $city);
                        mysqli_stmt_execute($insertStmt);
                        mysqli_stmt_close($insertStmt);
                    }
                }
                redirectWithFlash('Profile saved.');
            }
        }

        if (isset($_POST['submit_report'])) {
            $reportSession = trim($_POST['session'] ?? '');
            $reportSemester = trim($_POST['semester'] ?? '');
            $reportDetail = trim($_POST['report_detail'] ?? '');

            if ($rollno === '' || $reportSession === '' || $reportSemester === '' || $reportDetail === '') {
                redirectWithFlash('Please complete the report form before submitting.', 'error');
            }

            $reportRefImg = null;

            if (!empty($_FILES['report_ref_img']['name']) && is_uploaded_file($_FILES['report_ref_img']['tmp_name'])) {
                $uploadDir = __DIR__ . '/uploads/reports/';
                $allowedExtensions = ['jpg', 'jpeg', 'png'];
                $originalName = (string) $_FILES['report_ref_img']['name'];
                $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

                if (!in_array($fileExtension, $allowedExtensions, true)) {
                    redirectWithFlash('Only JPG and PNG files are allowed for report uploads.', 'error');
                }

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $safeName = uniqid('report_', true) . '.' . $fileExtension;
                $destination = $uploadDir . $safeName;

                if (move_uploaded_file($_FILES['report_ref_img']['tmp_name'], $destination)) {
                    $reportRefImg = 'uploads/reports/' . $safeName;
                }
            }

            $insertStmt = mysqli_prepare($conn, 'INSERT INTO user_intern_report (rollno, session, semester, report_detail, report_ref_img) VALUES (?, ?, ?, ?, ?)');
            if ($insertStmt) {
                mysqli_stmt_bind_param($insertStmt, 'sssss', $rollno, $reportSession, $reportSemester, $reportDetail, $reportRefImg);
                mysqli_stmt_execute($insertStmt);
                mysqli_stmt_close($insertStmt);
            }

            redirectWithFlash('Internship report submitted.');
        }
    }

    $profileStmt = mysqli_prepare($conn, 'SELECT * FROM user_profile WHERE u_id = ? LIMIT 1');
    if ($profileStmt) {
        mysqli_stmt_bind_param($profileStmt, 'i', $userId);
        mysqli_stmt_execute($profileStmt);
        $profileResult = mysqli_stmt_get_result($profileStmt);
        if ($profileResult && ($row = mysqli_fetch_assoc($profileResult))) {
            $profile = array_merge($profile, $row);
        }
        mysqli_stmt_close($profileStmt);
    }

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

    $reportStmt = mysqli_prepare($conn, 'SELECT * FROM user_intern_report WHERE rollno = ? ORDER BY u_in_r_id DESC LIMIT 1');
    if ($reportStmt) {
        mysqli_stmt_bind_param($reportStmt, 's', $rollno);
        mysqli_stmt_execute($reportStmt);
        $reportResult = mysqli_stmt_get_result($reportStmt);
        if ($reportResult && ($row = mysqli_fetch_assoc($reportResult))) {
            $latestReport = array_merge($latestReport, $row);
        }
        mysqli_stmt_close($reportStmt);
    }

    $marksStmt = mysqli_prepare($conn, 'SELECT * FROM user_internship_marks WHERE rollno = ? ORDER BY u_i_id DESC LIMIT 1');
    if ($marksStmt) {
        mysqli_stmt_bind_param($marksStmt, 's', $rollno);
        mysqli_stmt_execute($marksStmt);
        $marksResult = mysqli_stmt_get_result($marksStmt);
        if ($marksResult && ($row = mysqli_fetch_assoc($marksResult))) {
            $latestMarks = array_merge($latestMarks, $row);
        }
        mysqli_stmt_close($marksStmt);
    }
}
?>

<?php include 'includes/header.php'; ?>

<?php
if ($role === 'STD') {
    include 'student_dashboard.php';
} elseif ($role === 'FP') {
    include 'focal_dashboard.php';
} elseif ($role === 'FSP') {
    include 'faculty_dashboard.php';
}
?>

<?php include 'includes/footer.php'; ?>

// handle your work bitch
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
    'dob' => '',
];

$semesterDetail = [
    'session' => '',
    'semester' => '',
    'department' => '',
    'program' => '',
    'batch' => '',
    'section' => '',
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
        if (isset($_POST['save_student_dashboard'])) {
            $name = trim($_POST['name'] ?? '');
            $fname = trim($_POST['fname'] ?? '');
            $cnic = trim($_POST['cnic'] ?? '');
            $dob = trim($_POST['dob'] ?? '');
            $cellNo = trim($_POST['cellno'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $address = trim($_POST['address'] ?? '');

            $academicSession = trim($_POST['session'] ?? '');
            $academicSemester = trim($_POST['semester'] ?? '');
            $department = trim($_POST['department'] ?? '');
            $program = trim($_POST['program'] ?? '');
            $batch = trim($_POST['batch'] ?? '');
            $section = trim($_POST['section'] ?? '');

            if ($rollno === '' || $name === '') {
                redirectWithFlash('Roll number and Name are required.', 'error');
            }

            // Update user_profile
            $checkStmt = mysqli_prepare($conn, 'SELECT u_p_id FROM user_profile WHERE u_id = ? LIMIT 1');
            if ($checkStmt) {
                mysqli_stmt_bind_param($checkStmt, 'i', $userId);
                mysqli_stmt_execute($checkStmt);
                $existingProfile = mysqli_stmt_get_result($checkStmt);
                $profileRow = $existingProfile ? mysqli_fetch_assoc($existingProfile) : null;
                mysqli_stmt_close($checkStmt);

                if ($profileRow) {
                    $updateStmt = mysqli_prepare($conn, 'UPDATE user_profile SET name = ?, fname = ?, cnic = ?, dob = ?, cell_no = ?, email = ?, rollno_Empno = ?, address = ?, city = ? WHERE u_id = ?');
                    if ($updateStmt) {
                        mysqli_stmt_bind_param($updateStmt, 'sssssssssi', $name, $fname, $cnic, $dob, $cellNo, $email, $rollno, $address, $city, $userId);
                        mysqli_stmt_execute($updateStmt);
                        mysqli_stmt_close($updateStmt);
                    }
                } else {
                    $insertStmt = mysqli_prepare($conn, 'INSERT INTO user_profile (u_id, name, fname, cnic, dob, cell_no, email, rollno_Empno, address, city) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
                    if ($insertStmt) {
                        mysqli_stmt_bind_param($insertStmt, 'isssssssss', $userId, $name, $fname, $cnic, $dob, $cellNo, $email, $rollno, $address, $city);
                        mysqli_stmt_execute($insertStmt);
                        mysqli_stmt_close($insertStmt);
                    }
                }
            }

            // Update user_semester_detail
            $checkSemStmt = mysqli_prepare($conn, 'SELECT u_s_d_id FROM user_semester_detail WHERE rollno = ? LIMIT 1');
            if ($checkSemStmt) {
                mysqli_stmt_bind_param($checkSemStmt, 's', $rollno);
                mysqli_stmt_execute($checkSemStmt);
                $existingAcademic = mysqli_stmt_get_result($checkSemStmt);
                $academicRow = $existingAcademic ? mysqli_fetch_assoc($existingAcademic) : null;
                mysqli_stmt_close($checkSemStmt);

                if ($academicRow) {
                    $updateSemStmt = mysqli_prepare($conn, 'UPDATE user_semester_detail SET session = ?, semester = ?, department = ?, program = ?, batch = ?, section = ? WHERE rollno = ?');
                    if ($updateSemStmt) {
                        mysqli_stmt_bind_param($updateSemStmt, 'sssssss', $academicSession, $academicSemester, $department, $program, $batch, $section, $rollno);
                        mysqli_stmt_execute($updateSemStmt);
                        mysqli_stmt_close($updateSemStmt);
                    }
                } else {
                    $insertSemStmt = mysqli_prepare($conn, 'INSERT INTO user_semester_detail (rollno, session, semester, department, program, batch, section) VALUES (?, ?, ?, ?, ?, ?, ?)');
                    if ($insertSemStmt) {
                        mysqli_stmt_bind_param($insertSemStmt, 'sssssss', $rollno, $academicSession, $academicSemester, $department, $program, $batch, $section);
                        mysqli_stmt_execute($insertSemStmt);
                        mysqli_stmt_close($insertSemStmt);
                    }
                }
            }
            redirectWithFlash('Information updated successfully.');
        }

        if (isset($_POST['save_placement_details'])) {
            $orgName = trim($_POST['org_name'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $type = trim($_POST['type'] ?? '');
            $cpName = trim($_POST['contact_person_name'] ?? '');
            $cpPhone = trim($_POST['contact_person_phone'] ?? '');
            $cpEmail = trim($_POST['contact_person_email'] ?? '');
            $cpDesignation = trim($_POST['contact_person_designation'] ?? '');

            $ssName = trim($_POST['site_supervisor_name'] ?? '');
            $ssPhone = trim($_POST['site_supervisor_phone'] ?? '');
            $ssEmail = trim($_POST['site_supervisor_email'] ?? '');
            $ssDesignation = trim($_POST['site_supervisor_designation'] ?? '');

            $internshipTitle = trim($_POST['internship_title'] ?? '');
            $durationWeeks = (int) ($_POST['duration_weeks'] ?? 0);

            // 1. Resolve student_id
            $studentId = 0;
            $stdStmt = mysqli_prepare($conn, 'SELECT student_id FROM students WHERE roll_no = ? LIMIT 1');
            if ($stdStmt) {
                mysqli_stmt_bind_param($stdStmt, 's', $rollno);
                mysqli_stmt_execute($stdStmt);
                $stdRes = mysqli_stmt_get_result($stdStmt);
                if ($row = mysqli_fetch_assoc($stdRes)) {
                    $studentId = (int) $row['student_id'];
                }
                mysqli_stmt_close($stdStmt);
            }
            if ($studentId === 0) {
                // Insert into students table
                $insStd = mysqli_prepare($conn, 'INSERT INTO students (user_id, roll_no, session) VALUES (?, ?, ?)');
                if ($insStd) {
                    $sessionVal = $semesterDetail['session'] ?: 'Fall 2026';
                    mysqli_stmt_bind_param($insStd, 'iss', $userId, $rollno, $sessionVal);
                    mysqli_stmt_execute($insStd);
                    $studentId = mysqli_insert_id($conn);
                    mysqli_stmt_close($insStd);
                }
            }

            // 2. Check if internship record exists
            $internshipId = 0;
            $orgId = 0;
            $siteSupervisorId = 0;
            $internStmt = mysqli_prepare($conn, 'SELECT internship_id, org_id, site_supervisor_id FROM internships WHERE student_id = ? LIMIT 1');
            if ($internStmt) {
                mysqli_stmt_bind_param($internStmt, 'i', $studentId);
                mysqli_stmt_execute($internStmt);
                $internRes = mysqli_stmt_get_result($internStmt);
                if ($row = mysqli_fetch_assoc($internRes)) {
                    $internshipId = (int) $row['internship_id'];
                    $orgId = (int) $row['org_id'];
                    $siteSupervisorId = (int) $row['site_supervisor_id'];
                }
                mysqli_stmt_close($internStmt);
            }

            // 3. Insert or Update Organization
            if ($orgId > 0) {
                $orgUpdate = mysqli_prepare($conn, 'UPDATE organizations SET org_name = ?, address = ?, category = ?, type = ?, contact_person_name = ?, contact_person_phone = ?, contact_person_email = ?, contact_person_designation = ? WHERE org_id = ?');
                if ($orgUpdate) {
                    mysqli_stmt_bind_param($orgUpdate, 'ssssssssi', $orgName, $address, $category, $type, $cpName, $cpPhone, $cpEmail, $cpDesignation, $orgId);
                    mysqli_stmt_execute($orgUpdate);
                    mysqli_stmt_close($orgUpdate);
                }
            } else {
                $orgInsert = mysqli_prepare($conn, 'INSERT INTO organizations (org_name, address, category, type, contact_person_name, contact_person_phone, contact_person_email, contact_person_designation) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                if ($orgInsert) {
                    mysqli_stmt_bind_param($orgInsert, 'ssssssss', $orgName, $address, $category, $type, $cpName, $cpPhone, $cpEmail, $cpDesignation);
                    mysqli_stmt_execute($orgInsert);
                    $orgId = mysqli_insert_id($conn);
                    mysqli_stmt_close($orgInsert);
                }
            }

            // 4. Insert or Update Site Supervisor in users table
            if ($siteSupervisorId > 0) {
                $ssUpdate = mysqli_prepare($conn, 'UPDATE users SET full_name = ?, phone = ?, email = ?, designation = ? WHERE user_id = ?');
                if ($ssUpdate) {
                    mysqli_stmt_bind_param($ssUpdate, 'ssssi', $ssName, $ssPhone, $ssEmail, $ssDesignation, $siteSupervisorId);
                    mysqli_stmt_execute($ssUpdate);
                    mysqli_stmt_close($ssUpdate);
                }
            } else {
                $ssInsert = mysqli_prepare($conn, "INSERT INTO users (full_name, phone, email, role, designation) VALUES (?, ?, ?, 'site_supervisor', ?)");
                if ($ssInsert) {
                    mysqli_stmt_bind_param($ssInsert, 'ssss', $ssName, $ssPhone, $ssEmail, $ssDesignation);
                    mysqli_stmt_execute($ssInsert);
                    $siteSupervisorId = mysqli_insert_id($conn);
                    mysqli_stmt_close($ssInsert);
                }
            }

            // 5. Insert or Update Internship
            if ($internshipId > 0) {
                $internUpdate = mysqli_prepare($conn, 'UPDATE internships SET org_id = ?, site_supervisor_id = ?, internship_title = ?, duration_weeks = ? WHERE internship_id = ?');
                if ($internUpdate) {
                    mysqli_stmt_bind_param($internUpdate, 'iisii', $orgId, $siteSupervisorId, $internshipTitle, $durationWeeks, $internshipId);
                    mysqli_stmt_execute($internUpdate);
                    mysqli_stmt_close($internUpdate);
                }
            } else {
                $internInsert = mysqli_prepare($conn, 'INSERT INTO internships (student_id, org_id, site_supervisor_id, internship_title, duration_weeks) VALUES (?, ?, ?, ?, ?)');
                if ($internInsert) {
                    mysqli_stmt_bind_param($internInsert, 'iiisi', $studentId, $orgId, $siteSupervisorId, $internshipTitle, $durationWeeks);
                    mysqli_stmt_execute($internInsert);
                    mysqli_stmt_close($internInsert);
                }
            }

            // 6. Sync to site_supervisor_details flat table
            $checkSsd = mysqli_prepare($conn, 'SELECT site_sup_id FROM site_supervisor_details WHERE rollno = ? LIMIT 1');
            $ssdExists = false;
            if ($checkSsd) {
                mysqli_stmt_bind_param($checkSsd, 's', $rollno);
                mysqli_stmt_execute($checkSsd);
                $resSsd = mysqli_stmt_get_result($checkSsd);
                if (mysqli_fetch_assoc($resSsd)) {
                    $ssdExists = true;
                }
                mysqli_stmt_close($checkSsd);
            }

            if ($ssdExists) {
                $ssdQuery = 'UPDATE site_supervisor_details SET 
                    org_name = ?, 
                    org_address = ?, 
                    org_category = ?, 
                    org_type = ?, 
                    org_contact_person = ?, 
                    org_contact_cell = ?, 
                    org_contact_email = ?, 
                    org_contact_designation = ?, 
                    site_supervisor_name = ?, 
                    site_supervisor_cell = ?, 
                    site_supervisor_email = ?, 
                    site_supervisor_designation = ?, 
                    internship_title = ?, 
                    internship_duration = ?,
                    updated_at = NOW() 
                    WHERE rollno = ?';
                $ssdStmt = mysqli_prepare($conn, $ssdQuery);
                if ($ssdStmt) {
                    mysqli_stmt_bind_param($ssdStmt, 'sssssssssssssss', 
                        $orgName, $address, $category, $type, 
                        $cpName, $cpPhone, $cpEmail, $cpDesignation,
                        $ssName, $ssPhone, $ssEmail, $ssDesignation,
                        $internshipTitle, $durationWeeks, $rollno
                    );
                    mysqli_stmt_execute($ssdStmt);
                    mysqli_stmt_close($ssdStmt);
                }
            } else {
                $ssdQuery = 'INSERT INTO site_supervisor_details (
                    rollno, org_name, org_address, org_category, org_type, 
                    org_contact_person, org_contact_cell, org_contact_email, org_contact_designation,
                    site_supervisor_name, site_supervisor_cell, site_supervisor_email, site_supervisor_designation,
                    internship_title, internship_duration, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())';
                $ssdStmt = mysqli_prepare($conn, $ssdQuery);
                if ($ssdStmt) {
                    mysqli_stmt_bind_param($ssdStmt, 'sssssssssssssss', 
                        $rollno, $orgName, $address, $category, $type, 
                        $cpName, $cpPhone, $cpEmail, $cpDesignation,
                        $ssName, $ssPhone, $ssEmail, $ssDesignation,
                        $internshipTitle, $durationWeeks
                    );
                    mysqli_stmt_execute($ssdStmt);
                    mysqli_stmt_close($ssdStmt);
                }
            }

            redirectWithFlash('Placement and Site Supervisor details saved.');
        }

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

    $placement = [
        'org_name' => '',
        'address' => '',
        'category' => '',
        'type' => '',
        'contact_person_name' => '',
        'contact_person_phone' => '',
        'contact_person_email' => '',
        'contact_person_designation' => '',
        'site_supervisor_name' => '',
        'site_supervisor_phone' => '',
        'site_supervisor_email' => '',
        'site_supervisor_designation' => '',
        'internship_title' => '',
        'duration_weeks' => 0
    ];
    $placementQuery = "
        SELECT 
            org_name,
            org_address AS address,
            org_category AS category,
            org_type AS type,
            org_contact_person AS contact_person_name,
            org_contact_cell AS contact_person_phone,
            org_contact_email AS contact_person_email,
            org_contact_designation AS contact_person_designation,
            site_supervisor_name,
            site_supervisor_cell AS site_supervisor_phone,
            site_supervisor_email,
            site_supervisor_designation,
            internship_title,
            internship_duration AS duration_weeks
        FROM site_supervisor_details
        WHERE rollno = ? LIMIT 1
    ";
    $placementStmt = mysqli_prepare($conn, $placementQuery);
    if ($placementStmt) {
        mysqli_stmt_bind_param($placementStmt, 's', $rollno);
        mysqli_stmt_execute($placementStmt);
        $placementRes = mysqli_stmt_get_result($placementStmt);
        if ($placementRes && ($row = mysqli_fetch_assoc($placementRes))) {
            $placement = array_merge($placement, $row);
        }
        mysqli_stmt_close($placementStmt);
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

<?php
require 'd:/xampp/htdocs/IMS/includes/db.php'; 
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); 

try { 
    mysqli_begin_transaction($conn); 
    
    $u = 'S23-0101'; 
    $p = password_hash($u, PASSWORD_BCRYPT); 
    
    $userStmt = mysqli_prepare($conn, 'INSERT INTO user (u_name, u_pass, u_type, status) VALUES (?, ?, \'STD\', 1)'); 
    mysqli_stmt_bind_param($userStmt, 'ss', $u, $p); 
    mysqli_stmt_execute($userStmt); 
    
    $id = mysqli_insert_id($conn); 
    
    $profileStmt = mysqli_prepare($conn, 'INSERT INTO user_profile (u_id, name, fname, cnic, cell_no, email, rollno_Empno, address, city) VALUES (?, ?, ?, ?, \'\', \'\', ?, \'\', \'\')'); 
    $n = 'Test Student'; 
    $c = ''; 
    mysqli_stmt_bind_param($profileStmt, 'issss', $id, $n, $n, $c, $u); 
    mysqli_stmt_execute($profileStmt); 
    
    $semStmt = mysqli_prepare($conn, 'INSERT INTO user_semester_detail (rollno, session, semester, department, program) VALUES (?, ?, ?, ?, ?)'); 
    $sess = 'Fall 2026'; 
    $sem = '8th'; 
    $dep = 'IT'; 
    $prog = 'BS'; 
    mysqli_stmt_bind_param($semStmt, 'sssss', $u, $sess, $sem, $dep, $prog); 
    mysqli_stmt_execute($semStmt); 
    
    mysqli_commit($conn); 
    echo 'Success!'; 
} catch (Exception $e) { 
    echo 'Error: ' . $e->getMessage(); 
}

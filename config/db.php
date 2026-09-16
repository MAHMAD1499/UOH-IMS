<?php
/**
 * Database Connection Configuration
 * 
 * Establishes a MySQLi connection to the IMS database using the credentials
 * defined below. This file is included by every page that needs database access.
 * 
 * Connection charset is set to utf8mb4 to support full Unicode (including emojis).
 * 
 * @file    includes/db.php
 * @project Internship Management System (IMS) — University of Haripur
 */

/* ── MySQL Server Credentials ────────────────────────────────────────────── */
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = '';
$dbName = 'internship management system';

/* ── Establish the database connection ────────────────────────────────────── */
$conn = mysqli_connect($dbHost, $dbUser, $dbPass, $dbName);

/* Halt execution immediately if the connection fails */
if (!$conn) {
    die('Database connection failed: ' . mysqli_connect_error());
}

/* Ensure the connection uses utf8mb4 encoding for full Unicode support */
mysqli_set_charset($conn, 'utf8mb4');
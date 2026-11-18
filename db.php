<?php
// =========================================================================
// RIIT STUDENT & FACULTY PORTAL - DATABASE CONFIGURATION
// Auto-detects Localhost (XAMPP) vs Live Hosting (InfinityFree / cPanel)
// =========================================================================

// Check whether running on Localhost or Live Production Server
$server_name = $_SERVER['SERVER_NAME'] ?? 'localhost';
$is_local = in_array($server_name, ['localhost', '127.0.0.1']) || strpos($server_name, '192.168.') === 0;

if ($is_local) {
    // -------------------------------------------------------------
    // LOCALHOST (XAMPP) SETTINGS
    // -------------------------------------------------------------
    $host = "localhost";
    $user = "root";
    $pass = "";
    $dbname = "riit_student_portal";

    // 1. Initial Connection to local MySQL Server
    $conn = new mysqli($host, $user, $pass);
    if ($conn->connect_error) {
        die("Localhost Database Connection Failed: " . $conn->connect_error);
    }

    // 2. Auto-create database if not exists locally
    $conn->query("CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $conn->select_db($dbname);

} else {
    // -------------------------------------------------------------
    // INFINITYFREE / RENDER / LIVE HOSTING CREDENTIALS
    // Reads Render environment variables or fallback to InfinityFree:
    // -------------------------------------------------------------
    $host = getenv('DB_HOST') ?: "sqlXXX.epizy.com";
    $user = getenv('DB_USER') ?: "if0_XXXXXXXX";
    $pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : "YOUR_VHOST_PASSWORD";
    $dbname = getenv('DB_NAME') ?: "if0_XXXXXXXX_riit";
    $port = (int)(getenv('DB_PORT') ?: 3306);

    // Connect directly to the created database
    $conn = @new mysqli($host, $user, $pass, $dbname, $port);
    if ($conn->connect_error) {
        die("<div style='font-family: system-ui, -apple-system, sans-serif; padding: 28px; max-width: 620px; margin: 40px auto; background: #fff5f5; border: 1.5px solid #feb2b2; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.06);'>
            <h3 style='color: #8B0000; margin-top:0; font-size: 20px;'>InfinityFree Database Connection Notice</h3>
            <p style='color: #4a5568; line-height: 1.6;'>Could not connect to the remote MySQL database. Please update the 4 credentials inside <code>db.php</code>:</p>
            <ul style='color: #2d3748; background: #fff; padding: 16px 24px; border-radius: 8px; border: 1px solid #edf2f7; font-family: monospace; font-size: 13px;'>
                <li>Host: <strong>$host</strong></li>
                <li>User: <strong>$user</strong></li>
                <li>Database: <strong>$dbname</strong></li>
            </ul>
            <p style='color: #718096; font-size: 13px; margin-top: 15px;'><strong>Tip:</strong> Log in to your <a href='https://app.infinityfree.com' target='_blank' style='color:#8B0000; font-weight:600;'>InfinityFree Client Area</a>, click <em>Manage &gt; Database Details</em> to copy your MySQL Hostname, Username, and Password.</p>
        </div>");
    }
}

// -----------------------------------------------------------------
// AUTO-CREATE TABLES IF NOT EXISTS (Runs smoothly on both Localhost & InfinityFree)
// -----------------------------------------------------------------

// 1. Students table for registration and login
$conn->query("CREATE TABLE IF NOT EXISTS `students` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `reg_no` VARCHAR(100) NOT NULL UNIQUE,
    `user_id` VARCHAR(100) NOT NULL UNIQUE,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `course` VARCHAR(100) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `photo` VARCHAR(255) DEFAULT 'riit_logo.jpg',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// 2. Admissions table for 20-step application form
$conn->query("CREATE TABLE IF NOT EXISTS `admissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `app_id` VARCHAR(100) NOT NULL UNIQUE,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `dob` DATE NULL,
    `gender` VARCHAR(20) NULL,
    `blood_group` VARCHAR(10) NULL,
    `phone` VARCHAR(20) NULL,
    `whatsapp` VARCHAR(20) NULL,
    `email` VARCHAR(255) NOT NULL,
    `perm_address` TEXT NULL,
    `perm_city` VARCHAR(100) NULL,
    `perm_pin` VARCHAR(20) NULL,
    `curr_address` TEXT NULL,
    `father_name` VARCHAR(255) NULL,
    `father_occ` VARCHAR(100) NULL,
    `mother_name` VARCHAR(255) NULL,
    `mother_occ` VARCHAR(100) NULL,
    `tenth_board` VARCHAR(100) NULL,
    `tenth_marks` VARCHAR(50) NULL,
    `twelfth_stream` VARCHAR(100) NULL,
    `twelfth_marks` VARCHAR(50) NULL,
    `course_applied` VARCHAR(100) NOT NULL,
    `aadhaar_no` VARCHAR(50) NULL,
    `category` VARCHAR(50) NULL,
    `migration` VARCHAR(20) NULL,
    `extra_curricular` TEXT NULL,
    `hostel` VARCHAR(20) NULL,
    `bus` VARCHAR(20) NULL,
    `status` VARCHAR(50) DEFAULT 'Pending',
    `photo_path` VARCHAR(255) DEFAULT NULL,
    `marksheet_path` VARCHAR(255) DEFAULT NULL,
    `id_doc_path` VARCHAR(255) DEFAULT NULL,
    `submitted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Add status column to admissions if table existed previously without status
$col_check = $conn->query("SHOW COLUMNS FROM `admissions` LIKE 'status'");
if ($col_check && $col_check->num_rows == 0) {
    $conn->query("ALTER TABLE `admissions` ADD `status` VARCHAR(50) DEFAULT 'Pending' AFTER `bus`");
}

// 3. Teachers / Faculty table
$conn->query("CREATE TABLE IF NOT EXISTS `teachers` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_id` VARCHAR(100) NOT NULL UNIQUE,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `department` VARCHAR(150) NOT NULL,
    `designation` VARCHAR(150) NOT NULL,
    `phone` VARCHAR(30) NULL,
    `password` VARCHAR(255) NOT NULL,
    `photo` VARCHAR(255) DEFAULT 'riit_logo.jpg',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Seed default teacher if table is empty
$t_res = $conn->query("SELECT COUNT(*) AS total FROM `teachers`");
if ($t_res && ($row = $t_res->fetch_assoc()) && $row['total'] == 0) {
    $def_pass = password_hash("riit123", PASSWORD_DEFAULT);
    $conn->query("INSERT INTO `teachers` (`faculty_id`, `name`, `email`, `department`, `designation`, `phone`, `password`, `photo`) VALUES
    ('RIIT-FAC-01', 'Prof. Somnath Banerjee', 'faculty@riit.edu.in', 'Computer Science & Applications (BCA/MCA)', 'Associate Professor & Academic Coordinator', '+91 98765 43210', '$def_pass', 'riit_logo.jpg')");
}

// 4. Student Attendance Table (Marked by Teachers)
$conn->query("CREATE TABLE IF NOT EXISTS `student_attendance` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` VARCHAR(100) NOT NULL,
    `student_name` VARCHAR(255) NOT NULL,
    `course` VARCHAR(100) NOT NULL,
    `date` DATE NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'Present',
    `marked_by` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// 5. Student Marks & Internal Assessment Table
$conn->query("CREATE TABLE IF NOT EXISTS `student_marks` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `student_id` VARCHAR(100) NOT NULL,
    `student_name` VARCHAR(255) NOT NULL,
    `course` VARCHAR(100) NOT NULL,
    `subject` VARCHAR(150) NOT NULL,
    `exam_type` VARCHAR(100) NOT NULL,
    `marks_obtained` INT NOT NULL,
    `total_marks` INT NOT NULL DEFAULT 100,
    `grade` VARCHAR(10) NOT NULL,
    `marked_by` VARCHAR(100) NOT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// 6. Study Materials & Lecture Notes Table
$conn->query("CREATE TABLE IF NOT EXISTS `study_materials` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_id` VARCHAR(100) NOT NULL,
    `faculty_name` VARCHAR(255) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `course` VARCHAR(100) NOT NULL,
    `subject` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `file_path` VARCHAR(255) NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// 7. Faculty Notices & Circulars Table
$conn->query("CREATE TABLE IF NOT EXISTS `faculty_notices` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `faculty_id` VARCHAR(100) NOT NULL,
    `faculty_name` VARCHAR(255) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `target_course` VARCHAR(100) NOT NULL,
    `content` TEXT NOT NULL,
    `priority` VARCHAR(20) DEFAULT 'Normal',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
?>

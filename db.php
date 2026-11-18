<?php
// =========================================================================
// RIIT STUDENT & FACULTY PORTAL - RESILIENT DATABASE CONFIGURATION
// Auto-detects Localhost (XAMPP), Render / Cloud, InfinityFree with SQLite Fallback
// =========================================================================

// 1. Disable uncaught fatal mysqli exceptions from crashing the application
mysqli_report(MYSQLI_REPORT_OFF);

// 2. Auto-load .env file if present
$env_file = __DIR__ . '/.env';
if (file_exists($env_file)) {
    $env_lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($env_lines as $env_line) {
        $env_line = trim($env_line);
        if ($env_line === '' || strpos($env_line, '#') === 0) continue;
        if (strpos($env_line, '=') !== false) {
            list($e_key, $e_val) = explode('=', $env_line, 2);
            $e_key = trim($e_key);
            $e_val = trim($e_val, " \t\n\r\0\x0B\"'");
            if (getenv($e_key) === false) {
                putenv("$e_key=$e_val");
                $_ENV[$e_key] = $e_val;
                $_SERVER[$e_key] = $e_val;
            }
        }
    }
}

// 3. Resolve Database Credentials from Environment or Defaults
$server_name = $_SERVER['SERVER_NAME'] ?? 'localhost';
$is_local_xampp = (PHP_OS_FAMILY === 'Windows') && (in_array($server_name, ['localhost', '127.0.0.1']) || strpos($server_name, '192.168.') === 0);

$env_host = getenv('DB_HOST');
$env_user = getenv('DB_USER');
$env_pass = getenv('DB_PASS');
$env_name = getenv('DB_NAME');
$env_port = (int)(getenv('DB_PORT') ?: 3306);

$conn = null;
$db_connected = false;

// 4. Connect to MySQL with Error Handling and Fallback
if ($is_local_xampp && empty($env_host)) {
    // Local Windows XAMPP
    $host = "localhost";
    $user = "root";
    $pass = "";
    $dbname = "riit_student_portal";

    try {
        $conn = @new mysqli($host, $user, $pass);
        if ($conn && !$conn->connect_error) {
            $conn->query("CREATE DATABASE IF NOT EXISTS `$dbname` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            if ($conn->select_db($dbname)) {
                $db_connected = true;
            }
        }
    } catch (Throwable $e) {
        $db_connected = false;
    }
} else {
    // Cloud / Render / Linux / Live Server
    $host = $env_host ?: "127.0.0.1";
    // Avoid Unix socket on Linux if localhost specified
    if ($host === "localhost" && PHP_OS_FAMILY === "Linux") {
        $host = "127.0.0.1";
    }
    $user = $env_user ?: "root";
    $pass = ($env_pass !== false && $env_pass !== null) ? $env_pass : "";
    $dbname = $env_name ?: "riit_student_portal";
    $port = $env_port ?: 3306;

    // Connect to MySQL if valid host given
    if ($host !== "sqlXXX.epizy.com" && !empty($host)) {
        try {
            $conn = @new mysqli($host, $user, $pass, $dbname, $port);
            if ($conn && !$conn->connect_error) {
                $db_connected = true;
            }
        } catch (Throwable $e) {
            $db_connected = false;
        }
    }
}

// 5. If MySQL is not reachable or failed, use SQLite Fallback
// Guarantees the portal NEVER throws 500 error on Render!
if (!$db_connected || !$conn || $conn->connect_error) {
    require_once __DIR__ . '/fallback_db.php';
    $conn = new FallbackDB(__DIR__ . '/riit_portal.sqlite');
}

// -----------------------------------------------------------------
// AUTO-CREATE TABLES IF NOT EXISTS (Runs smoothly on both MySQL & SQLite)
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

// 4. Student Attendance Table
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

// 6. Study Materials Table
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

// 7. Faculty Notices Table
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

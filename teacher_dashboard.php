<?php
session_start();
require_once "db.php";

// Strict Access Control: Must be logged in as a faculty member
if (!isset($_SESSION["teacher_logged"]) || $_SESSION["teacher_logged"] !== true) {
    header("Location: index.php?auth_error=teacher_required");
    exit();
}

$faculty_id = $_SESSION["faculty_id"] ?? "RIIT-FAC-01";
$faculty_name = $_SESSION["teacher_name"] ?? "Prof. Somnath Banerjee";
$faculty_email = $_SESSION["teacher_email"] ?? "faculty@riit.edu.in";
$faculty_dept = $_SESSION["teacher_dept"] ?? "Computer Applications (BCA & MCA)";
$faculty_desig = $_SESSION["teacher_desig"] ?? "Associate Professor & Academic Coordinator";
$faculty_photo = $_SESSION["teacher_photo"] ?? "riit_logo.jpg";

$success_msg = "";
$error_msg = "";

$upload_dir = __DIR__ . "/uploads/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// =========================================================================
// 1. HANDLE POST ACTIONS (ONLINE COLLEGE TEACHER WORKFLOWS)
// =========================================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"])) {
    
    // ACTION A: Mark & Save Student Attendance
    if ($_POST["action"] == "save_attendance") {
        $course = htmlspecialchars($_POST["course"] ?? "BCA");
        $subject = htmlspecialchars($_POST["subject"] ?? "Web Technologies");
        $att_date = htmlspecialchars($_POST["att_date"] ?? date("Y-m-d"));
        $attendance_data = $_POST["attendance"] ?? []; // [student_id => 'Present'/'Absent'/'Late']
        $student_names = $_POST["student_name"] ?? [];

        $stmt = $conn->prepare("INSERT INTO `student_attendance` (`student_id`, `student_name`, `course`, `date`, `status`, `marked_by`) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            foreach ($attendance_data as $s_id => $status) {
                $s_name = $student_names[$s_id] ?? "Student";
                $stmt->bind_param("ssssss", $s_id, $s_name, $course, $att_date, $status, $faculty_name);
                $stmt->execute();
            }
            $stmt->close();
            $success_msg = "Class attendance for <strong>$course ($subject)</strong> on <strong>$att_date</strong> successfully saved to database!";
        } else {
            $error_msg = "Database error saving attendance: " . $conn->error;
        }

    // ACTION B: Submit & Publish Student Marks / Grades
    } elseif ($_POST["action"] == "save_marks") {
        $course = htmlspecialchars($_POST["course"] ?? "BCA");
        $subject = htmlspecialchars($_POST["subject"] ?? "Web Technologies");
        $exam_type = htmlspecialchars($_POST["exam_type"] ?? "Mid-Term Examination");
        $marks_input = $_POST["marks"] ?? [];
        $student_names = $_POST["student_name"] ?? [];

        $stmt = $conn->prepare("INSERT INTO `student_marks` (`student_id`, `student_name`, `course`, `subject`, `exam_type`, `marks_obtained`, `total_marks`, `grade`, `marked_by`) VALUES (?, ?, ?, ?, ?, ?, 100, ?, ?) ON DUPLICATE KEY UPDATE `marks_obtained`=VALUES(`marks_obtained`), `grade`=VALUES(`grade`)");
        
        if ($stmt) {
            foreach ($marks_input as $s_id => $score) {
                $score = (int)$score;
                $s_name = $student_names[$s_id] ?? "Student";
                // Grade calculation
                if ($score >= 90) $grade = "O (Outstanding)";
                elseif ($score >= 80) $grade = "A+ (Excellent)";
                elseif ($score >= 70) $grade = "A (Very Good)";
                elseif ($score >= 60) $grade = "B+ (Good)";
                elseif ($score >= 50) $grade = "B (Satisfactory)";
                elseif ($score >= 40) $grade = "C (Pass)";
                else $grade = "F (Re-appear)";

                $stmt->bind_param("sssssiss", $s_id, $s_name, $course, $subject, $exam_type, $score, $grade, $faculty_name);
                $stmt->execute();
            }
            $stmt->close();
            $success_msg = "Marks & Grades for <strong>$subject ($exam_type)</strong> successfully recorded and published to students!";
        }

    // ACTION C: Approve / Reject Admission Application
    } elseif ($_POST["action"] == "update_admission") {
        $app_id = htmlspecialchars($_POST["app_id"] ?? "");
        $new_status = htmlspecialchars($_POST["status"] ?? "Approved");

        $stmt = $conn->prepare("UPDATE `admissions` SET `status` = ? WHERE `app_id` = ?");
        if ($stmt) {
            $stmt->bind_param("ss", $new_status, $app_id);
            $stmt->execute();
            $stmt->close();
            $success_msg = "Application ID <strong>$app_id</strong> has been successfully updated to <strong>$new_status</strong>!";
        }

    // ACTION D: Upload Study Materials & Notes
    } elseif ($_POST["action"] == "upload_material") {
        $title = htmlspecialchars($_POST["title"] ?? "");
        $course = htmlspecialchars($_POST["course"] ?? "BCA");
        $subject = htmlspecialchars($_POST["subject"] ?? "");
        $description = htmlspecialchars($_POST["description"] ?? "");
        $file_path = "";

        if (isset($_FILES["material_file"]) && $_FILES["material_file"]["error"] == 0) {
            $ext = pathinfo($_FILES["material_file"]["name"], PATHINFO_EXTENSION);
            $new_name = "doc_" . time() . "_" . uniqid() . "." . $ext;
            if (move_uploaded_file($_FILES["material_file"]["tmp_name"], $upload_dir . $new_name)) {
                $file_path = "uploads/" . $new_name;
            }
        }

        $stmt = $conn->prepare("INSERT INTO `study_materials` (`faculty_id`, `faculty_name`, `title`, `course`, `subject`, `description`, `file_path`) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("sssssss", $faculty_id, $faculty_name, $title, $course, $subject, $description, $file_path);
            $stmt->execute();
            $stmt->close();
            $success_msg = "Study material <strong>'$title'</strong> successfully uploaded and shared with students!";
        }

    // ACTION E: Post Official Class / Departmental Notice
    } elseif ($_POST["action"] == "post_notice") {
        $title = htmlspecialchars($_POST["title"] ?? "");
        $target_course = htmlspecialchars($_POST["target_course"] ?? "All Courses");
        $priority = htmlspecialchars($_POST["priority"] ?? "Normal");
        $content = htmlspecialchars($_POST["content"] ?? "");

        $stmt = $conn->prepare("INSERT INTO `faculty_notices` (`faculty_id`, `faculty_name`, `title`, `target_course`, `content`, `priority`) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ssssss", $faculty_id, $faculty_name, $title, $target_course, $content, $priority);
            $stmt->execute();
            $stmt->close();
            $success_msg = "Notice <strong>'$title'</strong> officially broadcasted to <strong>$target_course</strong>!";
        }
    }
}

// =========================================================================
// 2. QUERY LIVE DATA FROM DATABASE FOR TEACHER DASHBOARD
// =========================================================================

// Total Registered Students
$std_count_res = $conn->query("SELECT COUNT(*) AS total FROM `students`");
$total_students = ($std_count_res && $row = $std_count_res->fetch_assoc()) ? (int)$row['total'] : 45;

// Pending Admission Applications
$adm_count_res = $conn->query("SELECT COUNT(*) AS total FROM `admissions` WHERE `status` = 'Pending'");
$pending_admissions = ($adm_count_res && $row = $adm_count_res->fetch_assoc()) ? (int)$row['total'] : 0;

// Fetch Students Roster (limit 15 for display)
$students_query = $conn->query("SELECT * FROM `students` ORDER BY `id` DESC LIMIT 15");
$students_roster = [];
if ($students_query) {
    while ($r = $students_query->fetch_assoc()) {
        $students_roster[] = $r;
    }
}

// If students roster empty, provide fallback demo students
if (empty($students_roster)) {
    $students_roster = [
        ['id' => 1, 'reg_no' => 'Somnath211244100', 'user_id' => 'Somnath211244100', 'name' => 'Somnath Chatterjee', 'email' => 'somnath@riit.edu.in', 'course' => 'BCA', 'photo' => 'riit_logo.jpg'],
        ['id' => 2, 'reg_no' => 'Ananya211244101', 'user_id' => 'Ananya211244101', 'name' => 'Ananya Sharma', 'email' => 'ananya@riit.edu.in', 'course' => 'BCA', 'photo' => 'riit_logo.jpg'],
        ['id' => 3, 'reg_no' => 'Rahul211244102', 'user_id' => 'Rahul211244102', 'name' => 'Rahul Mukherjee', 'email' => 'rahul@riit.edu.in', 'course' => 'MCA', 'photo' => 'riit_logo.jpg'],
        ['id' => 4, 'reg_no' => 'Pooja211244103', 'user_id' => 'Pooja211244103', 'name' => 'Pooja Sen', 'email' => 'pooja@riit.edu.in', 'course' => 'BCA', 'photo' => 'riit_logo.jpg'],
    ];
}

// Fetch Admission Applications
$admissions_query = $conn->query("SELECT * FROM `admissions` ORDER BY `id` DESC LIMIT 15");
$admissions_list = [];
if ($admissions_query) {
    while ($ar = $admissions_query->fetch_assoc()) {
        $admissions_list[] = $ar;
    }
}

// Fetch Study Materials
$materials_query = $conn->query("SELECT * FROM `study_materials` ORDER BY `id` DESC LIMIT 10");
$materials_list = [];
if ($materials_query) {
    while ($mr = $materials_query->fetch_assoc()) {
        $materials_list[] = $mr;
    }
}

// Fetch Faculty Notices
$notices_query = $conn->query("SELECT * FROM `faculty_notices` ORDER BY `id` DESC LIMIT 10");
$notices_list = [];
if ($notices_query) {
    while ($nr = $notices_query->fetch_assoc()) {
        $notices_list[] = $nr;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Portal Dashboard - RIIT</title>
    <link rel="stylesheet" href="teacher_dashboard.css">
</head>
<body class="teacher-dashboard-body" id="facultyAppBody">

    <!-- ========================================================
         LEFT SIDEBAR NAVIGATION
         ======================================================== -->
    <aside class="teacher-sidebar">
        <div>
            <!-- Brand Logo & Header -->
            <a href="teacher_dashboard.php" class="teacher-brand">
                <img src="<?php echo htmlspecialchars($faculty_photo); ?>" alt="RIIT Logo" class="teacher-logo" onerror="this.src='riit_logo.jpg'">
                <div class="brand-info">
                    <h3>RIIT <span>Faculty</span></h3>
                    <span>Teacher Portal</span>
                </div>
            </a>

            <!-- Navigation Links -->
            <div class="nav-title">Academics &amp; Work</div>
            <ul class="teacher-nav-list">
                <li>
                    <a href="javascript:void(0)" class="teacher-nav-link active" onclick="switchFacultyView('overview', this)">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <rect x="3" y="3" width="7" height="7" rx="1.5"></rect>
                            <rect x="14" y="3" width="7" height="7" rx="1.5"></rect>
                            <rect x="14" y="14" width="7" height="7" rx="1.5"></rect>
                            <rect x="3" y="14" width="7" height="7" rx="1.5"></rect>
                        </svg>
                        <span>Dashboard Overview</span>
                    </a>
                </li>
                <li>
                    <a href="javascript:void(0)" class="teacher-nav-link" onclick="switchFacultyView('attendance', this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 11l3 3L22 4"></path>
                            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"></path>
                        </svg>
                        <span>Daily Attendance</span>
                    </a>
                </li>
                <li>
                    <a href="javascript:void(0)" class="teacher-nav-link" onclick="switchFacultyView('marks', this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                        <span>Marks &amp; Grading</span>
                    </a>
                </li>
                <li>
                    <a href="javascript:void(0)" class="teacher-nav-link" onclick="switchFacultyView('admissions', this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="8.5" cy="7" r="4"></circle>
                            <line x1="20" y1="8" x2="20" y2="14"></line>
                            <line x1="23" y1="11" x2="17" y2="11"></line>
                        </svg>
                        <span>Admission Review</span>
                        <?php if ($pending_admissions > 0): ?>
                            <span class="nav-badge-pill"><?php echo $pending_admissions; ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="javascript:void(0)" class="teacher-nav-link" onclick="switchFacultyView('materials', this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="17 8 12 3 7 8"></polyline>
                            <line x1="12" y1="3" x2="12" y2="15"></line>
                        </svg>
                        <span>Study Notes &amp; Slides</span>
                    </a>
                </li>
                <li>
                    <a href="javascript:void(0)" class="teacher-nav-link" onclick="switchFacultyView('notices', this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                        <span>Notice Broadcast</span>
                    </a>
                </li>
            </ul>

            <div class="nav-title">Management</div>
            <ul class="teacher-nav-list">
                <li>
                    <a href="javascript:void(0)" class="teacher-nav-link" onclick="switchFacultyView('students', this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                        <span>Student Directory</span>
                    </a>
                </li>
                <li>
                    <a href="javascript:void(0)" class="teacher-nav-link" onclick="switchFacultyView('routine', this)">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        <span>Weekly Routine</span>
                    </a>
                </li>
                <li>
                    <a href="dashboard.php" class="teacher-nav-link" target="_blank">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                            <polyline points="15 3 21 3 21 9"></polyline>
                            <line x1="10" y1="14" x2="21" y2="3"></line>
                        </svg>
                        <span>View Student Portal</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Faculty Profile Card at Bottom of Sidebar -->
        <div class="faculty-profile-card">
            <div class="faculty-avatar-wrap">
                <img src="<?php echo htmlspecialchars($faculty_photo); ?>" alt="Faculty Photo" class="faculty-avatar" onerror="this.src='riit_logo.jpg'">
                <span class="faculty-status-dot" title="On Duty"></span>
            </div>
            <div class="faculty-name"><?php echo htmlspecialchars($faculty_name); ?></div>
            <span class="faculty-role">ID: <?php echo htmlspecialchars($faculty_id); ?></span>
            <a href="logout.php" class="btn-faculty-exit">
                <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
                <span>Logout / Switch</span>
            </a>
        </div>
    </aside>

    <!-- ========================================================
         MAIN TEACHER WORKSPACE
         ======================================================== -->
    <main class="teacher-main">

        <!-- Top Header Bar -->
        <div class="teacher-topbar">
            <div class="topbar-welcome">
                <h2>Welcome, <?php echo htmlspecialchars($faculty_name); ?> &#128075;</h2>
                <p><?php echo htmlspecialchars($faculty_desig); ?> &bull; <?php echo htmlspecialchars($faculty_dept); ?></p>
            </div>
            <div class="topbar-actions">
                <div class="date-pill">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <span><?php echo date("l, d M Y"); ?></span>
                </div>
                <button type="button" class="btn-action-primary" onclick="switchFacultyView('attendance')">
                    <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor">
                        <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                    </svg>
                    <span>Take Class Attendance</span>
                </button>
            </div>
        </div>

        <!-- Success Banner Alert -->
        <?php if ($success_msg != ""): ?>
            <div class="alert-banner success">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <span><?php echo $success_msg; ?></span>
            </div>
        <?php endif; ?>

        <!-- Error Banner Alert -->
        <?php if ($error_msg != ""): ?>
            <div class="alert-banner" style="background:#fee2e2; border-color:#fca5a5; color:#991b1b;">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5">
                    <circle cx="12" cy="12" r="10"></circle>
                    <line x1="12" y1="8" x2="12" y2="12"></line>
                    <line x1="12" y1="16" x2="12.01" y2="16"></line>
                </svg>
                <span><?php echo $error_msg; ?></span>
            </div>
        <?php endif; ?>

        <!-- ====================================================
             KPI STATS CARDS
             ==================================================== -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon-box red">
                    <svg viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                </div>
                <div>
                    <div class="stat-value"><?php echo $total_students; ?></div>
                    <div class="stat-label">Enrolled Students</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon-box blue">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                        <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                    </svg>
                </div>
                <div>
                    <div class="stat-value">4 Courses</div>
                    <div class="stat-label">Active Subjects Taught</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon-box green">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                </div>
                <div>
                    <div class="stat-value">88.5%</div>
                    <div class="stat-label">Avg Class Attendance</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon-box amber">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                    </svg>
                </div>
                <div>
                    <div class="stat-value"><?php echo $pending_admissions; ?></div>
                    <div class="stat-label">Pending Admissions</div>
                </div>
            </div>
        </div>

        <!-- ====================================================
             VIEW 1: DASHBOARD OVERVIEW
             ==================================================== -->
        <div id="view-overview" class="teacher-view-section active">
            
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 24px;">
                <!-- Left: Quick Academic Actions & Upcoming Classes -->
                <div>
                    <div class="dashboard-card">
                        <div class="card-header-flex">
                            <div>
                                <h3>Today's Scheduled Lectures</h3>
                                <div class="card-subtitle">Class schedule for <?php echo date("l"); ?></div>
                            </div>
                            <span class="badge-status approved">3 Classes Today</span>
                        </div>

                        <div style="display: flex; flex-direction: column; gap: 12px;">
                            <div class="routine-cell-class">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                    <strong>10:00 AM - 11:30 AM &bull; Web Technologies &amp; PHP</strong>
                                    <span style="background: #ffffff; padding: 2px 8px; border-radius: 4px; font-weight: 700; color: var(--primary);">Lab 3 (Ground Floor)</span>
                                </div>
                                <span>Batch: BCA 4th Semester &bull; Topic: MySQL Session Management &amp; Prepared Statements</span>
                            </div>

                            <div class="routine-cell-class" style="border-left-color: #2563eb; background: #eff6ff;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                    <strong style="color: #2563eb;">12:00 PM - 01:15 PM &bull; Data Structures &amp; Algorithms</strong>
                                    <span style="background: #ffffff; padding: 2px 8px; border-radius: 4px; font-weight: 700; color: #2563eb;">Lecture Hall 102</span>
                                </div>
                                <span>Batch: MCA 2nd Semester &bull; Topic: Binary Search Trees &amp; Graph Traversals</span>
                            </div>

                            <div class="routine-cell-class" style="border-left-color: #10b981; background: #ecfdf5;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                    <strong style="color: #10b981;">02:30 PM - 03:45 PM &bull; Database Management Systems (DBMS)</strong>
                                    <span style="background: #ffffff; padding: 2px 8px; border-radius: 4px; font-weight: 700; color: #10b981;">Seminar Hall A</span>
                                </div>
                                <span>Batch: BCA 4th Semester &bull; Topic: Normalization (1NF, 2NF, 3NF &amp; BCNF)</span>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Admission Applications -->
                    <div class="dashboard-card">
                        <div class="card-header-flex">
                            <div>
                                <h3>Recent Admission Submissions</h3>
                                <div class="card-subtitle">Students who submitted the 20-step admission form</div>
                            </div>
                            <a href="javascript:void(0)" onclick="switchFacultyView('admissions')" style="font-size: 12.5px; color: var(--primary); font-weight: 700; text-decoration: none;">View All &rarr;</a>
                        </div>

                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Application ID</th>
                                        <th>Applicant Name</th>
                                        <th>Course</th>
                                        <th>12th Marks</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($admissions_list)): ?>
                                        <?php foreach (array_slice($admissions_list, 0, 4) as $adm): ?>
                                            <tr>
                                                <td><strong style="color: var(--primary);"><?php echo htmlspecialchars($adm['app_id']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($adm['first_name'] . ' ' . $adm['last_name']); ?></td>
                                                <td><?php echo htmlspecialchars($adm['course_applied']); ?></td>
                                                <td><?php echo htmlspecialchars($adm['twelfth_marks'] ?: '85%'); ?></td>
                                                <td>
                                                    <span class="badge-status <?php echo strtolower($adm['status'] ?? 'pending'); ?>">
                                                        <?php echo htmlspecialchars($adm['status'] ?? 'Pending'); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="update_admission">
                                                        <input type="hidden" name="app_id" value="<?php echo htmlspecialchars($adm['app_id']); ?>">
                                                        <input type="hidden" name="status" value="Approved">
                                                        <button type="submit" class="btn-sm-action approve" title="Approve Application">&#10004; Approve</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" style="text-align: center; color: var(--text-muted);">No new admission submissions.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Quick Tools & Department Notices -->
                <div>
                    <!-- Faculty Quick Actions -->
                    <div class="dashboard-card">
                        <h3 style="font-size: 15px; margin-bottom: 14px;">Quick Academic Tools</h3>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <button type="button" class="btn-action-primary" style="justify-content: center; width: 100%; border-radius: 8px;" onclick="switchFacultyView('attendance')">
                                Take Attendance Now
                            </button>
                            <button type="button" class="btn-action-primary" style="justify-content: center; width: 100%; border-radius: 8px; background: #1e293b;" onclick="switchFacultyView('marks')">
                                Upload Exam Marks
                            </button>
                            <button type="button" class="btn-action-primary" style="justify-content: center; width: 100%; border-radius: 8px; background: #2563eb;" onclick="switchFacultyView('materials')">
                                Share Lecture Slides
                            </button>
                            <button type="button" class="btn-action-primary" style="justify-content: center; width: 100%; border-radius: 8px; background: #059669;" onclick="switchFacultyView('notices')">
                                Broadcast Class Notice
                            </button>
                        </div>
                    </div>

                    <!-- Department Notices Preview -->
                    <div class="dashboard-card">
                        <div class="card-header-flex">
                            <h3 style="font-size: 15px;">Active Notices</h3>
                            <a href="javascript:void(0)" onclick="switchFacultyView('notices')" style="font-size: 12px; color: var(--primary); font-weight: 700; text-decoration: none;">+ Post</a>
                        </div>
                        <?php if (!empty($notices_list)): ?>
                            <?php foreach (array_slice($notices_list, 0, 3) as $nt): ?>
                                <div class="notice-item">
                                    <div class="notice-header-row">
                                        <h4><?php echo htmlspecialchars($nt['title']); ?></h4>
                                        <span class="notice-date"><?php echo date("d M", strtotime($nt['created_at'])); ?></span>
                                    </div>
                                    <p><?php echo htmlspecialchars(substr($nt['content'], 0, 80)) . '...'; ?></p>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="notice-item">
                                <div class="notice-header-row">
                                    <h4>Internal Assessment Schedule</h4>
                                    <span class="notice-date">Today</span>
                                </div>
                                <p>Mid-term lab tests for BCA 4th Semester will begin next Monday.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>

        <!-- ====================================================
             VIEW 2: DAILY ATTENDANCE REGISTER
             ==================================================== -->
        <div id="view-attendance" class="teacher-view-section">
            <div class="dashboard-card">
                <div class="card-header-flex">
                    <div>
                        <h3>Daily Student Attendance Register</h3>
                        <div class="card-subtitle">Mark student attendance and save directly to MySQL database</div>
                    </div>
                    <div style="display: flex; gap: 8px;">
                        <button type="button" class="btn-sm-action approve" onclick="markAllAttendance('Present')">&#10004; Mark All Present</button>
                        <button type="button" class="btn-sm-action reject" onclick="markAllAttendance('Absent')">&times; Mark All Absent</button>
                    </div>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="save_attendance">

                    <!-- Filter / Class Selection Bar -->
                    <div class="filter-bar">
                        <div class="filter-group">
                            <label>Course / Batch:</label>
                            <select name="course" class="filter-select">
                                <option value="BCA">BCA - 4th Semester</option>
                                <option value="MCA">MCA - 2nd Semester</option>
                                <option value="BBA">BBA - 3rd Semester</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Subject:</label>
                            <select name="subject" class="filter-select">
                                <option value="Web Technologies">Web Technologies (PHP &amp; MySQL)</option>
                                <option value="Data Structures">Data Structures &amp; Algorithms</option>
                                <option value="Database Systems">Database Management Systems</option>
                                <option value="Python Programming">Python Programming &amp; AI</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Date:</label>
                            <input type="date" name="att_date" value="<?php echo date('Y-m-d'); ?>" class="filter-input">
                        </div>
                    </div>

                    <!-- Attendance Table -->
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th># Roll</th>
                                    <th>Student Name &amp; Profile</th>
                                    <th>Student ID</th>
                                    <th>Course</th>
                                    <th style="text-align: center;">Attendance Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students_roster as $idx => $std): ?>
                                    <tr>
                                        <td><strong>#<?php echo ($idx + 1); ?></strong></td>
                                        <td>
                                            <div class="student-row-info">
                                                <img src="<?php echo htmlspecialchars($std['photo'] ?: 'riit_logo.jpg'); ?>" alt="Student Avatar" class="table-avatar" onerror="this.src='riit_logo.jpg'">
                                                <div class="student-meta">
                                                    <strong><?php echo htmlspecialchars($std['name']); ?></strong>
                                                    <span><?php echo htmlspecialchars($std['email']); ?></span>
                                                </div>
                                            </div>
                                            <input type="hidden" name="student_name[<?php echo htmlspecialchars($std['user_id']); ?>]" value="<?php echo htmlspecialchars($std['name']); ?>">
                                        </td>
                                        <td><code style="background: #f1f5f9; padding: 3px 8px; border-radius: 4px; font-weight: 700; color: var(--primary);"><?php echo htmlspecialchars($std['user_id']); ?></code></td>
                                        <td><?php echo htmlspecialchars($std['course']); ?></td>
                                        <td>
                                            <div class="attendance-radio-group" style="justify-content: center;">
                                                <label class="radio-pill-label present">
                                                    <input type="radio" name="attendance[<?php echo htmlspecialchars($std['user_id']); ?>]" value="Present" checked>
                                                    P (Present)
                                                </label>
                                                <label class="radio-pill-label absent">
                                                    <input type="radio" name="attendance[<?php echo htmlspecialchars($std['user_id']); ?>]" value="Absent">
                                                    A (Absent)
                                                </label>
                                                <label class="radio-pill-label late">
                                                    <input type="radio" name="attendance[<?php echo htmlspecialchars($std['user_id']); ?>]" value="Late">
                                                    L (Late)
                                                </label>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top: 20px; display: flex; justify-content: flex-end;">
                        <button type="submit" class="btn-action-primary" style="padding: 10px 24px; font-size: 13.5px;">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                                <path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/>
                            </svg>
                            <span>Save &amp; Submit Attendance to Database</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ====================================================
             VIEW 3: MARKS & INTERNAL ASSESSMENT
             ==================================================== -->
        <div id="view-marks" class="teacher-view-section">
            <div class="dashboard-card">
                <div class="card-header-flex">
                    <div>
                        <h3>Marks &amp; Internal Assessment Grading</h3>
                        <div class="card-subtitle">Enter examination marks out of 100 with automatic grade calculation</div>
                    </div>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="save_marks">

                    <div class="filter-bar">
                        <div class="filter-group">
                            <label>Course:</label>
                            <select name="course" class="filter-select">
                                <option value="BCA">BCA - 4th Semester</option>
                                <option value="MCA">MCA - 2nd Semester</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Subject:</label>
                            <select name="subject" class="filter-select">
                                <option value="Web Technologies">Web Technologies (PHP &amp; MySQL)</option>
                                <option value="Data Structures">Data Structures &amp; Algorithms</option>
                                <option value="DBMS">Database Management Systems</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label>Assessment Type:</label>
                            <select name="exam_type" class="filter-select">
                                <option value="Mid-Term Examination">Mid-Term Examination</option>
                                <option value="Class Assessment &amp; Quiz">Class Assessment &amp; Quiz</option>
                                <option value="Practical Lab Assessment">Practical Lab Assessment</option>
                                <option value="Final Internal Evaluation">Final Internal Evaluation</option>
                            </select>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student ID</th>
                                    <th>Student Name</th>
                                    <th>Course</th>
                                    <th>Marks (Out of 100)</th>
                                    <th>Calculated Grade</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students_roster as $idx => $std): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($std['user_id']); ?></strong></td>
                                        <td>
                                            <?php echo htmlspecialchars($std['name']); ?>
                                            <input type="hidden" name="student_name[<?php echo htmlspecialchars($std['user_id']); ?>]" value="<?php echo htmlspecialchars($std['name']); ?>">
                                        </td>
                                        <td><?php echo htmlspecialchars($std['course']); ?></td>
                                        <td>
                                            <input type="number" name="marks[<?php echo htmlspecialchars($std['user_id']); ?>]" min="0" max="100" value="<?php echo rand(75, 95); ?>" class="filter-input marks-input" style="width: 100px; font-weight: 700; text-align: center;" oninput="updateLiveGrade(this)">
                                        </td>
                                        <td>
                                            <span class="badge-status approved live-grade-pill">A+ (Excellent)</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top: 20px; display: flex; justify-content: flex-end;">
                        <button type="submit" class="btn-action-primary" style="padding: 10px 24px; font-size: 13.5px;">
                            <span>Publish &amp; Save Marks to Student Portal</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ====================================================
             VIEW 4: ADMISSION REVIEW & APPROVAL DESK
             ==================================================== -->
        <div id="view-admissions" class="teacher-view-section">
            <div class="dashboard-card">
                <div class="card-header-flex">
                    <div>
                        <h3>Online Admission Application Review Desk</h3>
                        <div class="card-subtitle">Review, verify and approve students who submitted the 20-step application form</div>
                    </div>
                    <span class="badge-status pending"><?php echo count($admissions_list); ?> Total Applications</span>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>App ID</th>
                                <th>Applicant Details</th>
                                <th>Course Applied</th>
                                <th>10th / 12th Marks</th>
                                <th>Phone / WhatsApp</th>
                                <th>Status</th>
                                <th>Verification Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($admissions_list)): ?>
                                <?php foreach ($admissions_list as $adm): ?>
                                    <tr>
                                        <td><strong style="color: var(--primary);"><?php echo htmlspecialchars($adm['app_id']); ?></strong></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($adm['first_name'] . ' ' . $adm['last_name']); ?></strong><br>
                                            <span style="font-size: 11.5px; color: var(--text-muted);"><?php echo htmlspecialchars($adm['email']); ?></span>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($adm['course_applied']); ?></strong></td>
                                        <td>
                                            <span>10th: <strong><?php echo htmlspecialchars($adm['tenth_marks'] ?: 'N/A'); ?></strong></span><br>
                                            <span>12th: <strong><?php echo htmlspecialchars($adm['twelfth_marks'] ?: 'N/A'); ?></strong></span>
                                        </td>
                                        <td>
                                            <span><?php echo htmlspecialchars($adm['phone'] ?: 'N/A'); ?></span>
                                        </td>
                                        <td>
                                            <span class="badge-status <?php echo strtolower($adm['status'] ?? 'pending'); ?>">
                                                <?php echo htmlspecialchars($adm['status'] ?? 'Pending'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="display: flex; gap: 6px;">
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="update_admission">
                                                    <input type="hidden" name="app_id" value="<?php echo htmlspecialchars($adm['app_id']); ?>">
                                                    <input type="hidden" name="status" value="Approved">
                                                    <button type="submit" class="btn-sm-action approve" title="Approve Application">&#10004; Approve</button>
                                                </form>

                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="update_admission">
                                                    <input type="hidden" name="app_id" value="<?php echo htmlspecialchars($adm['app_id']); ?>">
                                                    <input type="hidden" name="status" value="Rejected">
                                                    <button type="submit" class="btn-sm-action reject" title="Reject Application">&times; Reject</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 24px;">No admission forms submitted yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ====================================================
             VIEW 5: STUDY NOTES & SLIDES UPLOAD
             ==================================================== -->
        <div id="view-materials" class="teacher-view-section">
            <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 24px;">
                <!-- Upload Form -->
                <div class="dashboard-card">
                    <h3 style="font-size: 16px; margin-bottom: 16px;">Upload Study Material / Lecture Notes</h3>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="upload_material">

                        <div class="form-group-field" style="margin-bottom: 14px;">
                            <label>Material Title / Chapter:</label>
                            <input type="text" name="title" placeholder="e.g. Chapter 4: PHP MySQL Database Integration" required>
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group-field">
                                <label>Course:</label>
                                <select name="course">
                                    <option value="BCA">BCA</option>
                                    <option value="MCA">MCA</option>
                                    <option value="BBA">BBA</option>
                                </select>
                            </div>
                            <div class="form-group-field">
                                <label>Subject:</label>
                                <input type="text" name="subject" placeholder="Web Technologies" required>
                            </div>
                        </div>

                        <div class="form-group-field" style="margin-bottom: 14px;">
                            <label>Description / Instructions:</label>
                            <textarea name="description" rows="3" placeholder="Read this module before next Tuesday's practical exam..."></textarea>
                        </div>

                        <div class="form-group-field" style="margin-bottom: 20px;">
                            <label>Attach File (PDF, DOCX, ZIP):</label>
                            <input type="file" name="material_file" required>
                        </div>

                        <button type="submit" class="btn-action-primary" style="width: 100%; justify-content: center;">
                            Upload &amp; Share with Class
                        </button>
                    </form>
                </div>

                <!-- List of Shared Notes -->
                <div class="dashboard-card">
                    <h3 style="font-size: 16px; margin-bottom: 16px;">Previously Shared Class Materials</h3>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Course</th>
                                    <th>Subject</th>
                                    <th>Date</th>
                                    <th>Download</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($materials_list)): ?>
                                    <?php foreach ($materials_list as $mat): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($mat['title']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($mat['course']); ?></td>
                                            <td><?php echo htmlspecialchars($mat['subject']); ?></td>
                                            <td><?php echo date("d M Y", strtotime($mat['created_at'])); ?></td>
                                            <td>
                                                <?php if (!empty($mat['file_path'])): ?>
                                                    <a href="<?php echo htmlspecialchars($mat['file_path']); ?>" download class="btn-sm-action view">&#128190; Download</a>
                                                <?php else: ?>
                                                    <span style="color: var(--text-muted); font-size: 12px;">Text Note</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td><strong>Lab Manual - PHP OOP &amp; MySQL</strong></td>
                                        <td>BCA</td>
                                        <td>Web Tech</td>
                                        <td>18 Nov 2026</td>
                                        <td><span class="btn-sm-action view">&#128190; Demo PDF</span></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Binary Tree Algorithms Slide Deck</strong></td>
                                        <td>MCA</td>
                                        <td>Data Structures</td>
                                        <td>17 Nov 2026</td>
                                        <td><span class="btn-sm-action view">&#128190; Demo PDF</span></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ====================================================
             VIEW 6: BROADCAST NOTICES & CIRCULARS
             ==================================================== -->
        <div id="view-notices" class="teacher-view-section">
            <div style="display: grid; grid-template-columns: 1fr 1.5fr; gap: 24px;">
                <div class="dashboard-card">
                    <h3 style="font-size: 16px; margin-bottom: 16px;">Issue Departmental Notice</h3>
                    <form method="POST">
                        <input type="hidden" name="action" value="post_notice">

                        <div class="form-group-field" style="margin-bottom: 14px;">
                            <label>Notice Heading / Subject:</label>
                            <input type="text" name="title" placeholder="e.g. Internal Assessment Submission Date" required>
                        </div>

                        <div class="form-grid-2">
                            <div class="form-group-field">
                                <label>Target Class:</label>
                                <select name="target_course">
                                    <option value="All Courses">All College Students</option>
                                    <option value="BCA">BCA (All Semesters)</option>
                                    <option value="MCA">MCA (All Semesters)</option>
                                    <option value="BBA">BBA (All Semesters)</option>
                                </select>
                            </div>
                            <div class="form-group-field">
                                <label>Priority Level:</label>
                                <select name="priority">
                                    <option value="Normal">Normal Notice</option>
                                    <option value="Important">Important &#9888;</option>
                                    <option value="Urgent">Urgent / Action Required &#128680;</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group-field" style="margin-bottom: 20px;">
                            <label>Notice Content:</label>
                            <textarea name="content" rows="5" placeholder="Write notice details here..." required></textarea>
                        </div>

                        <button type="submit" class="btn-action-primary" style="width: 100%; justify-content: center;">
                            Publish Notice to Portal
                        </button>
                    </form>
                </div>

                <div class="dashboard-card">
                    <h3 style="font-size: 16px; margin-bottom: 16px;">Active Notice Board</h3>
                    <?php if (!empty($notices_list)): ?>
                        <?php foreach ($notices_list as $nt): ?>
                            <div class="notice-item" style="border-left-color: <?php echo ($nt['priority'] == 'Urgent') ? 'var(--danger)' : 'var(--primary)'; ?>;">
                                <div class="notice-header-row">
                                    <h4><?php echo htmlspecialchars($nt['title']); ?></h4>
                                    <span class="notice-date"><?php echo date("d M Y, h:i A", strtotime($nt['created_at'])); ?></span>
                                </div>
                                <div style="font-size: 11.5px; color: var(--primary); font-weight: 700; margin-bottom: 6px;">
                                    Target: <?php echo htmlspecialchars($nt['target_course']); ?> &bull; Priority: <?php echo htmlspecialchars($nt['priority']); ?>
                                </div>
                                <p><?php echo nl2br(htmlspecialchars($nt['content'])); ?></p>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="notice-item">
                            <div class="notice-header-row">
                                <h4>Mid-Term Practical Lab Submissions</h4>
                                <span class="notice-date">Today</span>
                            </div>
                            <div style="font-size: 11.5px; color: var(--primary); font-weight: 700; margin-bottom: 6px;">Target: BCA 4th Sem &bull; Priority: Urgent</div>
                            <p>All BCA students must submit their Web Tech PHP code repository and assignment files by Friday 5:00 PM positively.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ====================================================
             VIEW 7: ENROLLED STUDENTS DIRECTORY
             ==================================================== -->
        <div id="view-students" class="teacher-view-section">
            <div class="dashboard-card">
                <div class="card-header-flex">
                    <div>
                        <h3>Enrolled Student Directory</h3>
                        <div class="card-subtitle">Search and manage registered college students</div>
                    </div>
                    <input type="text" id="studentSearchInput" placeholder="Search student name or ID..." class="filter-input" style="width: 260px;" onkeyup="filterStudentDirectory()">
                </div>

                <div class="table-responsive">
                    <table class="data-table" id="studentDirTable">
                        <thead>
                            <tr>
                                <th># ID</th>
                                <th>Student Name</th>
                                <th>Portal User ID</th>
                                <th>Email</th>
                                <th>Course</th>
                                <th>Attendance %</th>
                                <th>CGPA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students_roster as $idx => $std): ?>
                                <tr>
                                    <td><strong>#<?php echo ($idx + 1); ?></strong></td>
                                    <td>
                                        <div class="student-row-info">
                                            <img src="<?php echo htmlspecialchars($std['photo'] ?: 'riit_logo.jpg'); ?>" alt="Student Avatar" class="table-avatar" onerror="this.src='riit_logo.jpg'">
                                            <div class="student-meta">
                                                <strong><?php echo htmlspecialchars($std['name']); ?></strong>
                                                <span>Admitted Student</span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><code style="background: #f1f5f9; padding: 3px 8px; border-radius: 4px; font-weight: 700; color: var(--primary);"><?php echo htmlspecialchars($std['user_id']); ?></code></td>
                                    <td><?php echo htmlspecialchars($std['email']); ?></td>
                                    <td><strong><?php echo htmlspecialchars($std['course']); ?></strong></td>
                                    <td><span class="badge-status approved"><?php echo rand(82, 98); ?>%</span></td>
                                    <td><strong style="color: var(--primary);"><?php echo number_format(rand(80, 96) / 10, 1); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ====================================================
             VIEW 8: WEEKLY TEACHING ROUTINE
             ==================================================== -->
        <div id="view-routine" class="teacher-view-section">
            <div class="dashboard-card">
                <div class="card-header-flex">
                    <div>
                        <h3>Faculty Weekly Teaching Timetable</h3>
                        <div class="card-subtitle">Academic Schedule: Monday through Friday</div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="routine-grid">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>10:00 - 11:15 AM</th>
                                <th>11:30 - 12:45 PM</th>
                                <th>01:30 - 02:45 PM</th>
                                <th>03:00 - 04:15 PM</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>Monday</strong></td>
                                <td>
                                    <div class="routine-cell-class">
                                        <strong>Web Technologies (PHP)</strong>
                                        <span>BCA Sem 4 &bull; Lab 3</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="routine-cell-class" style="border-left-color: #2563eb; background: #eff6ff;">
                                        <strong style="color: #2563eb;">Data Structures</strong>
                                        <span>MCA Sem 2 &bull; Hall 102</span>
                                    </div>
                                </td>
                                <td><span style="color: var(--text-light); font-size: 12px;">Research / Faculty Hours</span></td>
                                <td>
                                    <div class="routine-cell-class" style="border-left-color: #10b981; background: #ecfdf5;">
                                        <strong style="color: #10b981;">DBMS Lab Practical</strong>
                                        <span>BCA Sem 4 &bull; Lab 2</span>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Tuesday</strong></td>
                                <td><span style="color: var(--text-light); font-size: 12px;">Staff Meeting</span></td>
                                <td>
                                    <div class="routine-cell-class">
                                        <strong>Web Technologies (PHP)</strong>
                                        <span>BCA Sem 4 &bull; Hall 101</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="routine-cell-class" style="border-left-color: #2563eb; background: #eff6ff;">
                                        <strong style="color: #2563eb;">Algorithms Analysis</strong>
                                        <span>MCA Sem 2 &bull; Hall 102</span>
                                    </div>
                                </td>
                                <td><span style="color: var(--text-light); font-size: 12px;">Mentorship Desk</span></td>
                            </tr>
                            <tr>
                                <td><strong>Wednesday</strong></td>
                                <td>
                                    <div class="routine-cell-class" style="border-left-color: #10b981; background: #ecfdf5;">
                                        <strong style="color: #10b981;">Database Systems</strong>
                                        <span>BCA Sem 4 &bull; Hall 103</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="routine-cell-class">
                                        <strong>Full Stack Web Dev</strong>
                                        <span>MCA Sem 2 &bull; Lab 3</span>
                                    </div>
                                </td>
                                <td><span style="color: var(--text-light); font-size: 12px;">Free Slot</span></td>
                                <td>
                                    <div class="routine-cell-class">
                                        <strong>PHP Project Review</strong>
                                        <span>BCA Sem 4 &bull; Lab 3</span>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td><strong>Thursday</strong></td>
                                <td>
                                    <div class="routine-cell-class" style="border-left-color: #2563eb; background: #eff6ff;">
                                        <strong style="color: #2563eb;">Data Structures Lab</strong>
                                        <span>MCA Sem 2 &bull; Lab 1</span>
                                    </div>
                                </td>
                                <td><span style="color: var(--text-light); font-size: 12px;">Library Research</span></td>
                                <td>
                                    <div class="routine-cell-class">
                                        <strong>Web Technologies</strong>
                                        <span>BCA Sem 4 &bull; Hall 101</span>
                                    </div>
                                </td>
                                <td><span style="color: var(--text-light); font-size: 12px;">Departmental Meeting</span></td>
                            </tr>
                            <tr>
                                <td><strong>Friday</strong></td>
                                <td>
                                    <div class="routine-cell-class">
                                        <strong>Web Tech Practical Exam</strong>
                                        <span>BCA Sem 4 &bull; Lab 3</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="routine-cell-class" style="border-left-color: #10b981; background: #ecfdf5;">
                                        <strong style="color: #10b981;">DBMS Seminar</strong>
                                        <span>BCA Sem 4 &bull; Hall A</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="routine-cell-class" style="border-left-color: #2563eb; background: #eff6ff;">
                                        <strong style="color: #2563eb;">MCA Lab Viva</strong>
                                        <span>MCA Sem 2 &bull; Lab 2</span>
                                    </div>
                                </td>
                                <td><span style="color: var(--text-light); font-size: 12px;">Weekly Wrap-up</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>

    <!-- JavaScript for View Navigation, Dynamic Attendance & Search -->
    <script>
    function switchFacultyView(viewName, linkElem) {
        // Hide all views
        document.querySelectorAll('.teacher-view-section').forEach(view => {
            view.classList.remove('active');
        });

        // Show target view
        const targetView = document.getElementById('view-' + viewName);
        if (targetView) {
            targetView.classList.add('active');
        }

        // Update nav links active status
        if (linkElem) {
            document.querySelectorAll('.teacher-nav-link').forEach(link => {
                link.classList.remove('active');
            });
            linkElem.classList.add('active');
        }
    }

    function markAllAttendance(status) {
        document.querySelectorAll('input[type="radio"]').forEach(radio => {
            if (radio.value === status) {
                radio.checked = true;
            }
        });
    }

    function updateLiveGrade(inputElem) {
        const score = parseInt(inputElem.value) || 0;
        const row = inputElem.closest('tr');
        const pill = row.querySelector('.live-grade-pill');
        if (!pill) return;

        if (score >= 90) {
            pill.textContent = "O (Outstanding)";
            pill.className = "badge-status approved live-grade-pill";
        } else if (score >= 80) {
            pill.textContent = "A+ (Excellent)";
            pill.className = "badge-status approved live-grade-pill";
        } else if (score >= 70) {
            pill.textContent = "A (Very Good)";
            pill.className = "badge-status approved live-grade-pill";
        } else if (score >= 60) {
            pill.textContent = "B+ (Good)";
            pill.className = "badge-status pending live-grade-pill";
        } else if (score >= 50) {
            pill.textContent = "B (Satisfactory)";
            pill.className = "badge-status pending live-grade-pill";
        } else if (score >= 40) {
            pill.textContent = "C (Pass)";
            pill.className = "badge-status pending live-grade-pill";
        } else {
            pill.textContent = "F (Fail / Re-appear)";
            pill.className = "badge-status rejected live-grade-pill";
        }
    }

    function filterStudentDirectory() {
        const query = document.getElementById('studentSearchInput').value.toLowerCase();
        const rows = document.querySelectorAll('#studentDirTable tbody tr');
        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            row.style.display = text.includes(query) ? '' : 'none';
        });
    }
    </script>
    <!-- SweetAlert2 Library -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        <?php if ($success_msg != ""): ?>
            Swal.fire({
                icon: 'success',
                title: 'অপারেশন সফল হয়েছে!',
                html: '<?php echo addslashes($success_msg); ?>',
                confirmButtonColor: '#1e293b',
                timer: 3500
            });
        <?php endif; ?>

        <?php if ($error_msg != ""): ?>
            Swal.fire({
                icon: 'error',
                title: 'ত্রুটি!',
                html: '<?php echo addslashes($error_msg); ?>',
                confirmButtonColor: '#8B0000'
            });
        <?php endif; ?>
    });
    </script>
</body>
</html>

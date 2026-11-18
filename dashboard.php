<?php
session_start();
require_once "db.php";

// Strict Access Control: Must be logged in as a student
if (!isset($_SESSION["student_name"]) || empty($_SESSION["student_name"])) {
    header("Location: index.php?auth_error=student_required");
    exit();
}

$admission_success = "";
$generated_id = "";
$upload_dir = __DIR__ . "/uploads/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Handle In-Dashboard Admission Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["action"]) && $_POST["action"] == "submit_admission") {
    $first_name = htmlspecialchars(trim($_POST["first_name"] ?? "Somnath"));
    $middle_name = htmlspecialchars(trim($_POST["middle_name"] ?? ""));
    $last_name = htmlspecialchars(trim($_POST["last_name"] ?? ""));
    $full_name = trim("$first_name $middle_name $last_name");

    // Format Unique Admission Number: Name + 211244100 (e.g. Somnath211244100)
    $clean_name = ucfirst(preg_replace('/[^a-zA-Z]/', '', $first_name)) ?: "Student";
    $count_res = $conn->query("SELECT COUNT(*) AS total FROM `admissions`");
    $count = ($count_res && $row = $count_res->fetch_assoc()) ? (int)$row['total'] : 0;
    $app_id = $clean_name . "211244" . (100 + $count);
    $generated_id = $app_id;
    
    $dob = htmlspecialchars($_POST["dob"] ?? "2005-08-15");
    $gender = htmlspecialchars($_POST["gender"] ?? "Male");
    $blood_group = htmlspecialchars($_POST["blood_group"] ?? "O+");
    $phone = htmlspecialchars($_POST["phone"] ?? "");
    $whatsapp = htmlspecialchars($_POST["whatsapp"] ?? "");
    $email = htmlspecialchars($_POST["email"] ?? "student@riit.edu.in");
    
    $perm_address = htmlspecialchars($_POST["perm_address"] ?? "");
    $perm_city = htmlspecialchars($_POST["perm_city"] ?? "");
    $perm_pin = htmlspecialchars($_POST["perm_pin"] ?? "");
    $curr_address = htmlspecialchars($_POST["curr_address"] ?? "");
    
    $father_name = htmlspecialchars($_POST["father_name"] ?? "");
    $father_occ = htmlspecialchars($_POST["father_occ"] ?? "");
    $mother_name = htmlspecialchars($_POST["mother_name"] ?? "");
    $mother_occ = htmlspecialchars($_POST["mother_occ"] ?? "");
    
    $tenth_board = htmlspecialchars($_POST["tenth_board"] ?? "");
    $tenth_marks = htmlspecialchars($_POST["tenth_marks"] ?? "");
    $twelfth_stream = htmlspecialchars($_POST["twelfth_stream"] ?? "");
    $twelfth_marks = htmlspecialchars($_POST["twelfth_marks"] ?? "");
    $course_applied = htmlspecialchars($_POST["course_applied"] ?? "BCA");
    
    $aadhaar_no = htmlspecialchars($_POST["aadhaar_no"] ?? "");
    $category = htmlspecialchars($_POST["category"] ?? "General");
    $migration = htmlspecialchars($_POST["migration"] ?? "No");
    $extra_curricular = htmlspecialchars($_POST["extra_curricular"] ?? "");
    $hostel = htmlspecialchars($_POST["hostel"] ?? "No");
    $bus = htmlspecialchars($_POST["bus"] ?? "No");

    // Profile photo upload handling
    $photo_path = "riit_logo.jpg";
    if (isset($_FILES["profile_photo"]) && $_FILES["profile_photo"]["error"] == 0) {
        $ext = pathinfo($_FILES["profile_photo"]["name"], PATHINFO_EXTENSION);
        $new_name = "dash_photo_" . time() . "_" . uniqid() . "." . $ext;
        if (move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $upload_dir . $new_name)) {
            $photo_path = "uploads/" . $new_name;
        }
    }

    // Marksheet upload
    $marksheet_path = "";
    if (isset($_FILES["marksheet_doc"]) && $_FILES["marksheet_doc"]["error"] == 0) {
        $ext = pathinfo($_FILES["marksheet_doc"]["name"], PATHINFO_EXTENSION);
        $new_m = "dash_marksheet_" . time() . "_" . uniqid() . "." . $ext;
        if (move_uploaded_file($_FILES["marksheet_doc"]["tmp_name"], $upload_dir . $new_m)) {
            $marksheet_path = "uploads/" . $new_m;
        }
    }

    $id_doc_path = "";
    if (isset($_FILES["id_doc"]) && $_FILES["id_doc"]["error"] == 0) {
        $ext = pathinfo($_FILES["id_doc"]["name"], PATHINFO_EXTENSION);
        $new_id = "dash_idproof_" . time() . "_" . uniqid() . "." . $ext;
        if (move_uploaded_file($_FILES["id_doc"]["tmp_name"], $upload_dir . $new_id)) {
            $id_doc_path = "uploads/" . $new_id;
        }
    }

    // 1. Insert into MySQL admissions table
    $stmt = $conn->prepare("INSERT INTO `admissions` (
        `app_id`, `first_name`, `last_name`, `dob`, `gender`, `blood_group`,
        `phone`, `whatsapp`, `email`, `perm_address`, `perm_city`, `perm_pin`,
        `curr_address`, `father_name`, `father_occ`, `mother_name`, `mother_occ`,
        `tenth_board`, `tenth_marks`, `twelfth_stream`, `twelfth_marks`,
        `course_applied`, `aadhaar_no`, `category`, `migration`, `extra_curricular`,
        `hostel`, `bus`, `photo_path`, `marksheet_path`, `id_doc_path`
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    if ($stmt) {
        $stmt->bind_param("sssssssssssssssssssssssssssssss",
            $app_id, $first_name, $last_name, $dob, $gender, $blood_group,
            $phone, $whatsapp, $email, $perm_address, $perm_city, $perm_pin,
            $curr_address, $father_name, $father_occ, $mother_name, $mother_occ,
            $tenth_board, $tenth_marks, $twelfth_stream, $twelfth_marks,
            $course_applied, $aadhaar_no, $category, $migration, $extra_curricular,
            $hostel, $bus, $photo_path, $marksheet_path, $id_doc_path
        );
        $stmt->execute();
        $stmt->close();
    }

    // 2. Register student with this exact Unique ID so they can log in immediately
    $default_pass = password_hash("riit123", PASSWORD_DEFAULT);
    $std_stmt = $conn->prepare("INSERT INTO `students` (`reg_no`, `user_id`, `name`, `email`, `course`, `password`, `photo`) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE `name`=?, `user_id`=?");
    if ($std_stmt) {
        $std_stmt->bind_param("sssssssss", $app_id, $app_id, $full_name, $email, $course_applied, $default_pass, $photo_path, $full_name, $app_id);
        $std_stmt->execute();
        $std_stmt->close();
    }

    // Update Session
    $_SESSION["student_name"] = $full_name ?: $clean_name;
    $_SESSION["student_email"] = $email;
    $_SESSION["student_course"] = $course_applied . " (Admitted 2026)";
    $_SESSION["student_reg"] = $app_id;
    if ($photo_path != "riit_logo.jpg") {
        $_SESSION["student_photo"] = $photo_path;
    }

    $admission_success = "Admission Confirmed! Your Unique Admission ID is <strong>$app_id</strong>. You can use this ID to login anytime with password: <strong>riit123</strong>";
}

// Student details from session or default student profile
$student_name = $_SESSION["student_name"] ?? "Somnath";
$student_email = $_SESSION["student_email"] ?? "somnath@riit.edu.in";
$student_reg = $_SESSION["student_reg"] ?? "Somnath211244100";
$student_course = $_SESSION["student_course"] ?? "BCA - 4th Semester";
$student_photo = $_SESSION["student_photo"] ?? "riit_logo.jpg";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal Dashboard - RIIT</title>
    <link rel="stylesheet" href="dashboard.css">
</head>
<body class="dashboard-body" id="appBody" data-theme="red">

    <!-- ========================================================
         LEFT SIDEBAR NAVIGATION
         ======================================================== -->
    <aside class="sidebar">
        <div>
            <!-- Sidebar Brand Header -->
            <a href="dashboard.php" class="sidebar-brand">
                <img src="riit_logo.jpg" alt="RIIT Logo" class="sidebar-logo">
                <div class="sidebar-brand-text">RIIT <span>Portal</span></div>
            </a>

            <!-- Navigation: MENU -->
            <div class="nav-section-title">Menu</div>
            <ul class="nav-list">
                <li class="nav-item active" id="nav-dashboard">
                    <a href="javascript:void(0)" class="nav-link" onclick="switchMainView('dashboard')">
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <rect x="3" y="3" width="7" height="7" rx="1.5"></rect>
                            <rect x="14" y="3" width="7" height="7" rx="1.5"></rect>
                            <rect x="14" y="14" width="7" height="7" rx="1.5"></rect>
                            <rect x="3" y="14" width="7" height="7" rx="1.5"></rect>
                        </svg>
                        <span>Dashboard</span>
                    </a>
                </li>
                <!-- In-Dashboard Admission Section -->
                <li class="nav-item" id="nav-admission">
                    <a href="javascript:void(0)" class="nav-link" onclick="switchMainView('admission')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                        <span>Admission</span>
                        <span class="nav-badge" style="background: #059669;">New</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#academics" class="nav-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 20V10M12 20V4M6 20v-6"></path>
                        </svg>
                        <span>Academic Analytics</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#courses" class="nav-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                        </svg>
                        <span>My Courses</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#attendance" class="nav-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        <span>Attendance</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#exams" class="nav-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 12h-4l-3 9L9 3l-3 9H2"></path>
                        </svg>
                        <span>Exams &amp; Results</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#messages" class="nav-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                        <span>Faculty Chat</span>
                        <span class="nav-badge">3</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#notifications" class="nav-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                        <span>Notices</span>
                    </a>
                </li>
            </ul>

            <!-- Navigation: ACCOUNT -->
            <div class="nav-section-title">Account</div>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="#profile" class="nav-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <span style="font-size: 11.5px; font-weight: 700; color: var(--primary);">ID: <?php echo htmlspecialchars($student_reg); ?></span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="#settings" class="nav-link">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="3"></circle>
                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                        </svg>
                        <span>Portal Settings</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Sidebar Profile Card (Replaced Rocket Symbol with Student Profile Photo) -->
        <div class="sidebar-pro-card">
            <div class="sidebar-profile-box">
                <img src="<?php echo htmlspecialchars($student_photo); ?>" alt="Student Profile" class="sidebar-student-avatar" onerror="this.src='riit_logo.jpg'">
                <span class="status-online-dot"></span>
            </div>
            <h4 style="font-size: 13.5px; font-weight: 700; color: var(--text-main); margin-bottom: 2px;"><?php echo htmlspecialchars($student_name); ?></h4>
            <span style="font-size: 11px; color: var(--primary); font-weight: 700; display: block; margin-bottom: 12px; letter-spacing: 0.3px;"><?php echo htmlspecialchars($student_reg); ?></span>
            <a href="logout.php" class="btn-sidebar-pro">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor">
                    <path d="M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5zm14 3c0 .6-.4 1-1 1H6c-.6 0-1-.4-1-1v-1h14v1z"/>
                </svg>
                <span>Logout / Exit</span>
            </a>
        </div>
    </aside>

    <!-- ========================================================
         MAIN DASHBOARD WORKSPACE
         ======================================================== -->
    <main class="main-wrapper">

        <!-- Top Bar: Search, Theme Toggle, Student Profile Widget -->
        <div class="top-bar">
            <div class="search-container">
                <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <input type="text" class="search-input" placeholder="Search courses, exam notices, faculty...">
            </div>

            <div class="topbar-right">
                <!-- Theme Switcher Pill -->
                <button type="button" class="theme-pill-btn" onclick="toggleDashboardTheme()">
                    <span class="theme-color-dot" id="themeDot"></span>
                    <span id="themeLabel">Theme: College Red</span>
                </button>

                <!-- Student Profile Display -->
                <a href="#profile" class="user-profile-widget">
                    <img src="<?php echo htmlspecialchars($student_photo); ?>" alt="Student Photo" class="user-avatar" onerror="this.src='riit_logo.jpg'">
                    <div class="user-info">
                        <div class="user-name"><?php echo htmlspecialchars($student_name); ?></div>
                        <div class="user-email"><?php echo htmlspecialchars($student_email); ?></div>
                    </div>
                </a>
            </div>
        </div>

        <?php if ($admission_success != ""): ?>
            <div style="background: #ecfdf5; border: 2px solid #10b981; color: #065f46; padding: 18px 24px; border-radius: 14px; margin-bottom: 24px; font-size: 14px; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.15);">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
                    <span style="font-size: 20px;">&#10004;</span>
                    <h4 style="margin: 0; font-size: 16px; font-weight: 800; color: #065f46;">Admission Confirmed &amp; Unique ID Generated</h4>
                </div>
                <p style="margin: 0 0 10px 0;"><?php echo $admission_success; ?></p>
                <div style="display: inline-block; background: #ffffff; border: 1.5px dashed #059669; color: #8B0000; font-weight: 800; padding: 6px 14px; border-radius: 6px; font-size: 15px; letter-spacing: 0.5px;">
                    Login User ID: <?php echo htmlspecialchars($generated_id); ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- ========================================================
             VIEW 1: ACADEMIC DASHBOARD OVERVIEW
             ======================================================== -->
        <div id="view-dashboard" class="main-view-section active">
            
            <!-- Dashboard Header & Tabs -->
            <div class="dashboard-header">
                <div class="header-left">
                    <h1>My Academic Dashboard</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($student_name); ?>! Here is your semester progress summary.</p>
                </div>
                <div class="header-right">
                    <button type="button" class="header-pill-btn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                            <line x1="16" y1="2" x2="16" y2="6"></line>
                            <line x1="8" y1="2" x2="8" y2="6"></line>
                            <line x1="3" y1="10" x2="21" y2="10"></line>
                        </svg>
                        <span>Semester 4</span>
                        <span style="font-size: 10px;">&#9662;</span>
                    </button>
                    <button type="button" class="header-pill-btn" onclick="switchMainView('admission')">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                        <span>Admission Form</span>
                    </button>
                </div>
            </div>

            <!-- Academic Tabs -->
            <div class="dashboard-tabs">
                <div class="dash-tab active" onclick="switchDashTab(this)">Academic Overview</div>
                <div class="dash-tab" onclick="switchDashTab(this)">Attendance &amp; Grades</div>
            </div>

            <!-- Top Stat Cards (3 KPI Cards) -->
            <div class="stat-cards-grid">
                <!-- Stat Card 1 -->
                <div class="stat-card">
                    <div class="stat-icon-wrapper red">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"></path>
                            <path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"></path>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-header">
                            <span class="stat-title">Enrolled Courses</span>
                            <span class="stat-dropdown-caret">&#9662;</span>
                        </div>
                        <div class="stat-value">6 Subjects <span style="font-size: 14px; color: var(--text-muted); font-weight: 500;">(24 Credits)</span></div>
                        <span class="stat-badge green">+ 2 Electives Added</span>
                    </div>
                </div>

                <!-- Stat Card 2 -->
                <div class="stat-card">
                    <div class="stat-icon-wrapper blue">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                            <polyline points="22 4 12 14.01 9 11.01"></polyline>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-header">
                            <span class="stat-title">Overall Attendance</span>
                            <span class="stat-dropdown-caret">&#9662;</span>
                        </div>
                        <div class="stat-value">89.5%</div>
                        <span class="stat-badge green">&#8599; 5.2% Above Criteria</span>
                    </div>
                </div>

                <!-- Stat Card 3 -->
                <div class="stat-card">
                    <div class="stat-icon-wrapper green">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                            <polyline points="2 17 12 22 22 17"></polyline>
                            <polyline points="2 12 12 17 22 12"></polyline>
                        </svg>
                    </div>
                    <div class="stat-content">
                        <div class="stat-header">
                            <span class="stat-title">Cumulative GPA</span>
                            <span class="stat-dropdown-caret">&#9662;</span>
                        </div>
                        <div class="stat-value">9.24 <span style="font-size: 13px; color: var(--text-muted); font-weight: 600;">/ 10</span></div>
                        <span class="stat-badge green">&#8593; Top 5% in Dept</span>
                    </div>
                </div>
            </div>

            <!-- Middle Row: Charts & Notices -->
            <div class="middle-grid">
                <!-- Bar Chart: Attendance -->
                <div class="dashboard-card">
                    <div class="card-top-title">
                        <h3>Monthly Class Attendance</h3>
                        <span class="dots-menu">&#8943;</span>
                    </div>
                    <div class="chart-container">
                        <div class="chart-y-axis">
                            <span>100%</span>
                            <span>80%</span>
                            <span>60%</span>
                            <span>40%</span>
                        </div>
                        <div class="bars-group-wrapper">
                            <div class="bar-col"><div class="bar-track"><div class="bar-fill" style="height: 75%;"></div></div><span class="bar-label">Ja</span></div>
                            <div class="bar-col"><div class="bar-track"><div class="bar-fill" style="height: 85%;"></div></div><span class="bar-label">Fb</span></div>
                            <div class="bar-col"><div class="bar-track"><div class="bar-fill" style="height: 70%;"></div></div><span class="bar-label">Mr</span></div>
                            <div class="bar-col"><div class="bar-track"><div class="bar-fill" style="height: 90%;"></div></div><span class="bar-label">Ap</span></div>
                            <div class="bar-col"><div class="bar-track"><div class="bar-fill" style="height: 95%;"></div></div><span class="bar-label">My</span></div>
                            <div class="bar-col"><div class="bar-track"><div class="bar-fill" style="height: 80%;"></div></div><span class="bar-label">Jn</span></div>
                            <div class="bar-col"><div class="bar-track"><div class="bar-fill" style="height: 92%;"></div></div><span class="bar-label">Jl</span></div>
                            <div class="bar-col"><div class="bar-track"><div class="bar-fill" style="height: 88%;"></div></div><span class="bar-label">Ag</span></div>
                            <div class="bar-col"><div class="bar-track"><div class="bar-fill" style="height: 94%;"></div></div><span class="bar-label">Sp</span></div>
                            <div class="bar-col"><div class="bar-track"><div class="bar-fill" style="height: 82%;"></div></div><span class="bar-label">Oc</span></div>
                            <div class="bar-col"><div class="bar-track"><div class="bar-fill" style="height: 89%;"></div></div><span class="bar-label">Nv</span></div>
                            <div class="bar-col"><div class="bar-track"><div class="bar-fill" style="height: 78%;"></div></div><span class="bar-label">Dc</span></div>
                        </div>
                    </div>
                </div>

                <!-- Donut Chart: Syllabus Progress -->
                <div class="dashboard-card">
                    <div class="card-top-title">
                        <h3>Syllabus Progress</h3>
                        <span class="dots-menu">&#8943;</span>
                    </div>
                    <div class="donut-chart-wrapper">
                        <div class="donut-svg-container">
                            <svg class="donut-svg" width="125" height="125" viewBox="0 0 100 100">
                                <circle cx="50" cy="50" r="40" fill="transparent" stroke="#f1f3f9" stroke-width="12"></circle>
                                <circle cx="50" cy="50" r="40" fill="transparent" stroke="var(--primary)" stroke-width="12" stroke-dasharray="251.2" stroke-dashoffset="45.2" stroke-linecap="round"></circle>
                            </svg>
                            <div class="donut-percentage-text">82%</div>
                        </div>
                        <div class="donut-legend">
                            <div class="legend-item"><span class="legend-dot primary"></span> Completed</div>
                            <div class="legend-item"><span class="legend-dot dark"></span> Ongoing</div>
                            <div class="legend-item"><span class="legend-dot muted"></span> Upcoming</div>
                        </div>
                    </div>
                </div>

                <!-- Action Notice Card -->
                <div class="dashboard-card notice-action-card">
                    <div>
                        <div class="notice-header">
                            <div class="notice-icon-badge">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                    <polyline points="14 2 14 8 20 8"></polyline>
                                </svg>
                            </div>
                            <div class="notice-text">
                                <h4>Semester 4 Admit Card &amp; Hall Ticket Released</h4>
                                <span class="notice-date">18 Nov 2026 &bull; Exam Cell</span>
                            </div>
                        </div>
                    </div>
                    <div class="notice-btn-row">
                        <button type="button" class="btn-grant" onclick="alert('Downloading RIIT Admit Card (PDF)...')">Download Admit Card</button>
                        <button type="button" class="btn-cancel-outline" onclick="this.closest('.notice-action-card').style.display='none'">Dismiss</button>
                    </div>
                </div>
            </div>

            <!-- Bottom Row: Faculty, Elective & Submissions -->
            <div class="bottom-grid">
                <div class="dashboard-card">
                    <div class="card-top-title">
                        <h3>Faculty Mentors</h3>
                    </div>
                    <div class="team-list">
                        <div class="team-item">
                            <div class="team-user">
                                <img src="riit_logo.jpg" alt="Prof. Roy" class="team-avatar" style="border: 2px solid #8B0000;">
                                <div class="team-info">
                                    <h5>Prof. Amitava Roy</h5>
                                    <span>Data Structures &amp; Algorithms</span>
                                </div>
                            </div>
                            <button type="button" class="btn-team-msg" title="Send Message" onclick="alert('Chat with Prof. Roy opened.')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                            </button>
                        </div>
                        <div class="team-item">
                            <div class="team-user">
                                <img src="riit_logo.jpg" alt="Dr. Sharma" class="team-avatar" style="border: 2px solid #3b82f6;">
                                <div class="team-info">
                                    <h5>Dr. Shreya Sharma</h5>
                                    <span>Database Systems (DBMS)</span>
                                </div>
                            </div>
                            <button type="button" class="btn-team-msg" title="Send Message" onclick="alert('Chat with Dr. Sharma opened.')">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="add-course-box">
                        <div class="add-course-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                                <path d="M6 12v5c0 2 6 3 6 3s6-1 6-3v-5"></path>
                            </svg>
                        </div>
                        <h4>Choose Elective Course</h4>
                        <p>Select specialized subjects for Semester 5 before registration closes.</p>
                        <button type="button" class="btn-new-course" onclick="alert('Opening Elective Course Selection Portal...')">+ Enroll Elective</button>
                    </div>
                </div>

                <div class="dashboard-card">
                    <div class="card-top-title">
                        <h3>Recent Submissions &amp; Scores</h3>
                    </div>
                    <div class="recent-list">
                        <div class="recent-item">
                            <div class="recent-left">
                                <div class="recent-img-thumb">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"></polyline><polyline points="8 6 2 12 8 18"></polyline></svg>
                                </div>
                                <div class="recent-details">
                                    <h5>PHP Student Portal Project</h5>
                                    <span>Web Technology Lab</span>
                                </div>
                            </div>
                            <span class="status-tag pending">Under Review</span>
                        </div>
                        <div class="recent-item">
                            <div class="recent-left">
                                <div class="recent-img-thumb">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path></svg>
                                </div>
                                <div class="recent-details">
                                    <h5>DBMS Mid-Semester Exam</h5>
                                    <span>Score: 48 / 50</span>
                                </div>
                            </div>
                            <span class="status-tag completed">Grade: A+</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- ========================================================
             VIEW 2: ADVANCED IN-DASHBOARD ADMISSION FORM MODULE
             ======================================================== -->
        <div id="view-admission" class="main-view-section">
            
            <div class="dashboard-header">
                <div class="header-left">
                    <h1>Student Admission Portal (2026-2027)</h1>
                    <p>Submit your complete 20-step admission form to receive your official Unique Admission Number.</p>
                </div>
                <div class="header-right">
                    <button type="button" class="header-pill-btn" onclick="switchMainView('dashboard')">
                        &larr; Back to Dashboard
                    </button>
                </div>
            </div>

            <!-- In-Dashboard Stepper Navigation -->
            <div class="dash-stepper-track">
                <div class="dash-step-pill active" id="d-step-tab-1" onclick="jumpDashStage(1)">
                    <span class="pill-num">1</span>
                    <span>1. Personal Profile</span>
                </div>
                <div class="dash-step-pill" id="d-step-tab-2" onclick="jumpDashStage(2)">
                    <span class="pill-num">2</span>
                    <span>2. Address &amp; Family</span>
                </div>
                <div class="dash-step-pill" id="d-step-tab-3" onclick="jumpDashStage(3)">
                    <span class="pill-num">3</span>
                    <span>3. Academic Records</span>
                </div>
                <div class="dash-step-pill" id="d-step-tab-4" onclick="jumpDashStage(4)">
                    <span class="pill-num">4</span>
                    <span>4. Quota &amp; Services</span>
                </div>
                <div class="dash-step-pill" id="d-step-tab-5" onclick="jumpDashStage(5)">
                    <span class="pill-num">5</span>
                    <span>5. Uploads &amp; Submit</span>
                </div>
            </div>

            <!-- Advanced In-Dashboard Admission Form -->
            <form method="POST" enctype="multipart/form-data" id="inDashAdmissionForm">
                <input type="hidden" name="action" value="submit_admission">

                <div class="dash-admission-card">
                    
                    <!-- STAGE 1: Steps 1-5 (Personal Details) -->
                    <div class="d-stage" id="d-stage-1">
                        <div class="dash-admission-header">
                            <h3>Stage 1: Candidate Personal Profile</h3>
                            <span style="font-size: 11.5px; opacity: 0.9;">Steps 1 to 5 of 20</span>
                        </div>
                        <div class="dash-admission-body">
                            <div class="dash-sub-title">Step 1: Student Full Name</div>
                            <div class="dash-form-row">
                                <div class="dash-field">
                                    <label>First Name * (Used for Unique ID)</label>
                                    <input type="text" name="first_name" placeholder="First Name (e.g. Somnath)" value="Somnath" required>
                                </div>
                                <div class="dash-field">
                                    <label>Middle Name</label>
                                    <input type="text" name="middle_name" placeholder="Middle Name">
                                </div>
                                <div class="dash-field">
                                    <label>Last Name *</label>
                                    <input type="text" name="last_name" placeholder="Last Name" value="Mandal" required>
                                </div>
                            </div>

                            <div class="dash-sub-title">Step 2 &amp; 3: Date of Birth, Gender &amp; Blood Group</div>
                            <div class="dash-form-row">
                                <div class="dash-field">
                                    <label>Date of Birth *</label>
                                    <input type="date" name="dob" value="2005-08-15" required>
                                </div>
                                <div class="dash-field">
                                    <label>Gender *</label>
                                    <select name="gender" required>
                                        <option value="Male" selected>Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="dash-field">
                                    <label>Blood Group</label>
                                    <select name="blood_group">
                                        <option value="O+" selected>O Positive (O+)</option>
                                        <option value="A+">A Positive (A+)</option>
                                        <option value="B+">B Positive (B+)</option>
                                        <option value="AB+">AB Positive (AB+)</option>
                                    </select>
                                </div>
                            </div>

                            <div class="dash-sub-title">Step 4 &amp; 5: Contact Details &amp; Verified Email</div>
                            <div class="dash-form-row">
                                <div class="dash-field">
                                    <label>Mobile Number *</label>
                                    <input type="tel" name="phone" placeholder="10-digit Mobile" value="9876543210" required>
                                </div>
                                <div class="dash-field">
                                    <label>WhatsApp Number</label>
                                    <input type="tel" name="whatsapp" placeholder="WhatsApp Number" value="9876543210">
                                </div>
                                <div class="dash-field">
                                    <label>Student Email *</label>
                                    <input type="email" name="email" placeholder="Email Address" value="somnath@riit.edu.in" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STAGE 2: Steps 6-9 (Address & Parents) -->
                    <div class="d-stage" id="d-stage-2" style="display: none;">
                        <div class="dash-admission-header">
                            <h3>Stage 2: Residential Address &amp; Parents' Information</h3>
                            <span style="font-size: 11.5px; opacity: 0.9;">Steps 6 to 9 of 20</span>
                        </div>
                        <div class="dash-admission-body">
                            <div class="dash-sub-title">Step 6 &amp; 7: Permanent &amp; Current Residential Address</div>
                            <div class="dash-form-row-2">
                                <div class="dash-field">
                                    <label>Permanent House &amp; Street Address *</label>
                                    <input type="text" name="perm_address" placeholder="Street Address" value="12/A College Road">
                                </div>
                                <div class="dash-field">
                                    <label>City &amp; PIN Code *</label>
                                    <input type="text" name="perm_city" placeholder="City" value="Kolkata 700001">
                                </div>
                            </div>
                            <div class="dash-field" style="margin-bottom: 14px;">
                                <label>Correspondence / Current Address *</label>
                                <input type="text" name="curr_address" placeholder="Current Address" value="12/A College Road, Kolkata">
                            </div>

                            <div class="dash-sub-title">Step 8 &amp; 9: Parents' &amp; Guardian Profile</div>
                            <div class="dash-form-row-2">
                                <div class="dash-field">
                                    <label>Father's Full Name *</label>
                                    <input type="text" name="father_name" placeholder="Father's Name" value="S. Mandal">
                                </div>
                                <div class="dash-field">
                                    <label>Father's Profession</label>
                                    <input type="text" name="father_occ" placeholder="Occupation" value="Service">
                                </div>
                            </div>
                            <div class="dash-form-row-2">
                                <div class="dash-field">
                                    <label>Mother's Full Name *</label>
                                    <input type="text" name="mother_name" placeholder="Mother's Name" value="M. Mandal">
                                </div>
                                <div class="dash-field">
                                    <label>Mother's Profession</label>
                                    <input type="text" name="mother_occ" placeholder="Occupation" value="Homemaker">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STAGE 3: Steps 10-12 (Academic Qualifications) -->
                    <div class="d-stage" id="d-stage-3" style="display: none;">
                        <div class="dash-admission-header">
                            <h3>Stage 3: Academic Qualifications &amp; Course Selection</h3>
                            <span style="font-size: 11.5px; opacity: 0.9;">Steps 10 to 12 of 20</span>
                        </div>
                        <div class="dash-admission-body">
                            <div class="dash-sub-title">Step 10: Class 10th (Secondary) Board Examination</div>
                            <div class="dash-form-row">
                                <div class="dash-field">
                                    <label>Board / Council *</label>
                                    <input type="text" name="tenth_board" placeholder="Board" value="CBSE">
                                </div>
                                <div class="dash-field">
                                    <label>Percentage / Marks *</label>
                                    <input type="text" name="tenth_marks" placeholder="Percentage" value="88.5%">
                                </div>
                                <div class="dash-field">
                                    <label>Passing Year</label>
                                    <input type="text" name="tenth_year" placeholder="Year" value="2022">
                                </div>
                            </div>

                            <div class="dash-sub-title">Step 11: Class 12th (Higher Secondary) Examination</div>
                            <div class="dash-form-row">
                                <div class="dash-field">
                                    <label>Stream *</label>
                                    <select name="twelfth_stream">
                                        <option value="Science (PCM)" selected>Science (PCM)</option>
                                        <option value="Commerce with Maths">Commerce with Maths</option>
                                        <option value="Arts / Humanities">Arts / Humanities</option>
                                    </select>
                                </div>
                                <div class="dash-field">
                                    <label>Overall Percentage *</label>
                                    <input type="text" name="twelfth_marks" placeholder="Percentage" value="85.2%">
                                </div>
                                <div class="dash-field">
                                    <label>Board / Council</label>
                                    <input type="text" name="twelfth_board" placeholder="Board" value="CBSE">
                                </div>
                            </div>

                            <div class="dash-sub-title">Step 12: Degree Program Applied For</div>
                            <div class="dash-form-row-2">
                                <div class="dash-field">
                                    <label>Choose Degree Program *</label>
                                    <select name="course_applied" required>
                                        <option value="BCA" selected>Bachelor of Computer Applications (BCA)</option>
                                        <option value="MCA">Master of Computer Applications (MCA)</option>
                                        <option value="BBA">Bachelor of Business Administration (BBA)</option>
                                    </select>
                                </div>
                                <div class="dash-field">
                                    <label>Shift Preference</label>
                                    <select name="shift">
                                        <option value="Morning" selected>Morning Shift (09:00 AM - 02:00 PM)</option>
                                        <option value="Day">Day Shift (11:00 AM - 04:30 PM)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STAGE 4: Steps 13-17 (Quota & Campus Facilities) -->
                    <div class="d-stage" id="d-stage-4" style="display: none;">
                        <div class="dash-admission-header">
                            <h3>Stage 4: Quota, Government Identity &amp; Campus Facilities</h3>
                            <span style="font-size: 11.5px; opacity: 0.9;">Steps 13 to 17 of 20</span>
                        </div>
                        <div class="dash-admission-body">
                            <div class="dash-sub-title">Step 13 &amp; 14: Aadhaar Identity &amp; Category Reservation</div>
                            <div class="dash-form-row">
                                <div class="dash-field">
                                    <label>Aadhaar Number (12 Digits) *</label>
                                    <input type="text" name="aadhaar_no" placeholder="xxxx xxxx xxxx" value="5421 8902 4412" required>
                                </div>
                                <div class="dash-field">
                                    <label>Social Category *</label>
                                    <select name="category">
                                        <option value="General" selected>General</option>
                                        <option value="OBC-NCL">OBC</option>
                                        <option value="SC">SC</option>
                                        <option value="ST">ST</option>
                                        <option value="EWS">EWS</option>
                                    </select>
                                </div>
                                <div class="dash-field">
                                    <label>Migration Required?</label>
                                    <select name="migration">
                                        <option value="No" selected>No (Direct Admission)</option>
                                        <option value="Yes">Yes</option>
                                    </select>
                                </div>
                            </div>

                            <div class="dash-sub-title">Step 16 &amp; 17: Sports, Achievements, Hostel &amp; Bus</div>
                            <div class="dash-field" style="margin-bottom: 14px;">
                                <label>Extra-Curricular &amp; Sports Distinctions</label>
                                <input type="text" name="extra_curricular" placeholder="Achievements / Hackathons" value="State Level Coding Competition">
                            </div>
                            <div class="dash-form-row-2">
                                <div class="dash-field">
                                    <label>College Hostel Accommodation?</label>
                                    <select name="hostel">
                                        <option value="No" selected>No (Day Scholar)</option>
                                        <option value="Yes">Yes (Require Hostel Room)</option>
                                    </select>
                                </div>
                                <div class="dash-field">
                                    <label>College Bus Transport Route?</label>
                                    <select name="bus">
                                        <option value="Yes" selected>Yes (College Bus Route Service)</option>
                                        <option value="No">No (Self Commute)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STAGE 5: Steps 18-20 (Uploads & Declaration) -->
                    <div class="d-stage" id="d-stage-5" style="display: none;">
                        <div class="dash-admission-header">
                            <h3>Stage 5: Document Uploads &amp; Final Declaration</h3>
                            <span style="font-size: 11.5px; opacity: 0.9;">Steps 18 to 20 of 20</span>
                        </div>
                        <div class="dash-admission-body">
                            <div class="dash-sub-title">Step 18 &amp; 19: Photo &amp; Certificate Uploads</div>
                            <div class="dash-form-row-2">
                                <div class="dash-field">
                                    <label>Upload Passport Photograph (JPG/PNG)</label>
                                    <input type="file" name="profile_photo" accept="image/*">
                                </div>
                                <div class="dash-field">
                                    <label>Upload 10th &amp; 12th Marksheet (PDF/Image)</label>
                                    <input type="file" name="marksheet_doc" accept=".pdf,.jpg,.jpeg,.png">
                                </div>
                            </div>
                            <div class="dash-field" style="margin-bottom: 20px;">
                                <label>Upload Aadhaar / Government ID Proof (PDF/JPG)</label>
                                <input type="file" name="id_doc" accept=".pdf,.jpg,.jpeg,.png">
                            </div>

                            <div class="dash-sub-title">Step 20: Code of Conduct &amp; Declaration</div>
                            <div style="background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 8px; padding: 14px; font-size: 12.5px; line-height: 1.6; color: var(--text-muted); margin-bottom: 14px;">
                                I solemnly declare that all particulars stated in this online admission application are true and correct. I understand my Unique Admission ID will be generated in the format <strong>[Name]211244100</strong> and will be my permanent login credential.
                            </div>
                            <label style="display: flex; align-items: center; gap: 8px; font-size: 13px; font-weight: 600; cursor: pointer;">
                                <input type="checkbox" required checked style="width: 16px; height: 16px; accent-color: var(--primary);">
                                I agree to the terms and authorize generating my unique student admission credential.
                            </label>
                        </div>
                    </div>

                    <!-- In-Dashboard Footer Navigation -->
                    <div class="dash-admission-footer">
                        <button type="button" class="btn-cancel-outline" id="dBackBtn" onclick="navigateDashStage(-1)" disabled>&larr; Previous Stage</button>
                        <button type="button" class="btn-grant" id="dNextBtn" onclick="navigateDashStage(1)">Next Stage &rarr;</button>
                    </div>

                </div>
            </form>

        </div>

    </main>

    <!-- Interactive Navigation Scripts -->
    <script>
    // Toggle View between Main Dashboard & In-Dashboard Admission Form
    function switchMainView(viewName) {
        const dashView = document.getElementById('view-dashboard');
        const admView = document.getElementById('view-admission');
        const navDash = document.getElementById('nav-dashboard');
        const navAdm = document.getElementById('nav-admission');

        if (viewName === 'admission') {
            dashView.classList.remove('active');
            admView.classList.add('active');
            navDash.classList.remove('active');
            navAdm.classList.add('active');
        } else {
            admView.classList.remove('active');
            dashView.classList.add('active');
            navAdm.classList.remove('active');
            navDash.classList.add('active');
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // In-Dashboard Multi-Stage Stepper
    let curDashStage = 1;
    const maxDashStages = 5;

    function jumpDashStage(num) {
        if (num >= 1 && num <= maxDashStages) {
            curDashStage = num;
            renderDashStage();
        }
    }

    function renderDashStage() {
        for (let i = 1; i <= maxDashStages; i++) {
            const stageEl = document.getElementById('d-stage-' + i);
            const pillEl = document.getElementById('d-step-tab-' + i);
            if (stageEl && pillEl) {
                if (i === curDashStage) {
                    stageEl.style.display = 'block';
                    pillEl.classList.add('active');
                    pillEl.classList.remove('completed');
                } else if (i < curDashStage) {
                    stageEl.style.display = 'none';
                    pillEl.classList.remove('active');
                    pillEl.classList.add('completed');
                } else {
                    stageEl.style.display = 'none';
                    pillEl.classList.remove('active');
                    pillEl.classList.remove('completed');
                }
            }
        }

        const backBtn = document.getElementById('dBackBtn');
        const nextBtn = document.getElementById('dNextBtn');

        if (backBtn && nextBtn) {
            backBtn.disabled = (curDashStage === 1);
            if (curDashStage === maxDashStages) {
                nextBtn.innerText = 'Generate Unique ID & Submit \u279C';
            } else {
                nextBtn.innerText = 'Next Stage \u2192';
            }
        }
    }

    function navigateDashStage(delta) {
        if (delta === 1) {
            if (curDashStage === maxDashStages) {
                document.getElementById('inDashAdmissionForm').submit();
                return;
            }
            curDashStage++;
        } else if (delta === -1) {
            if (curDashStage > 1) {
                curDashStage--;
            }
        }
        renderDashStage();
    }

    // Theme Switcher
    function toggleDashboardTheme() {
        const body = document.getElementById('appBody');
        const themeLabel = document.getElementById('themeLabel');
        const currentTheme = body.getAttribute('data-theme');

        if (currentTheme === 'red') {
            body.setAttribute('data-theme', 'purple');
            themeLabel.innerText = 'Theme: Image Purple';
        } else {
            body.setAttribute('data-theme', 'red');
            themeLabel.innerText = 'Theme: College Red';
        }
    }

    function switchDashTab(tabEl) {
        document.querySelectorAll('.dash-tab').forEach(t => t.classList.remove('active'));
        tabEl.classList.add('active');
    }

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    <?php if ($admission_success != ""): ?>
    document.addEventListener('DOMContentLoaded', () => {
        switchMainView('admission');
        Swal.fire({
            icon: 'success',
            title: 'ভর্তি আবেদন সফল!',
            html: '<?php echo addslashes($admission_success); ?>',
            confirmButtonColor: '#8B0000'
        });
    });
    <?php endif; ?>
    </script>
</body>
</html>

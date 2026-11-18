<?php
session_start();
require_once "db.php";

$message = "";
$error = "";
$name = "";
$email = "";
$course = "";
$active_role = "student";

// Check query params for alerts
$auth_error = $_GET["auth_error"] ?? "";
$logged_out = isset($_GET["logged_out"]);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. If Quick Student Registration submitted
    if (isset($_POST["name"])) {
        $name = htmlspecialchars(trim($_POST["name"]));
        $email = htmlspecialchars(trim($_POST["email"]));
        $course = htmlspecialchars(trim($_POST["course"]));
        
        // Generate Unique Student ID: [FirstName]211244100
        $clean_first = ucfirst(preg_replace('/[^a-zA-Z]/', '', explode(" ", $name)[0])) ?: "Student";
        $count_res = $conn->query("SELECT COUNT(*) AS total FROM `students`");
        $count = ($count_res && $row = $count_res->fetch_assoc()) ? (int)$row['total'] : 0;
        $user_id = $clean_first . "211244" . (100 + $count);
        $reg_no = $user_id;
        $pass_hash = password_hash("riit123", PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO `students` (`reg_no`, `user_id`, `name`, `email`, `course`, `password`, `photo`) VALUES (?, ?, ?, ?, ?, ?, 'riit_logo.jpg') ON DUPLICATE KEY UPDATE `name`=?");
        if ($stmt) {
            $stmt->bind_param("sssssss", $reg_no, $user_id, $name, $email, $course, $pass_hash, $name);
            $stmt->execute();
            $stmt->close();
        }

        $message = "Registration Successful! Your Student ID is <strong>$user_id</strong> (Default Password: <strong>riit123</strong>). Please login now.";
        $active_role = "student";

    // 2. If Student Login submitted
    } elseif (isset($_POST["student_id"])) {
        $login_input = trim($_POST["student_id"]);
        $password_input = $_POST["password"] ?? "";
        $active_role = "student";

        // Query database strictly for student
        $stmt = $conn->prepare("SELECT * FROM `students` WHERE `user_id` = ? OR `reg_no` = ? OR `email` = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("sss", $login_input, $login_input, $login_input);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                // Verify password (or allow test password riit123)
                if (password_verify($password_input, $row["password"]) || $password_input === "riit123" || empty($row["password"])) {
                    $_SESSION["student_name"] = $row["name"];
                    $_SESSION["student_email"] = $row["email"];
                    $_SESSION["student_course"] = $row["course"];
                    $_SESSION["student_reg"] = $row["reg_no"];
                    $_SESSION["student_photo"] = $row["photo"] ?: "riit_logo.jpg";

                    header("Location: dashboard.php");
                    exit();
                } else {
                    $error = "ভুল পাসওয়ার্ড! দয়া করে সঠিক পাসওয়ার্ড দিন।";
                }
            } else {
                $error = "স্টুডেন্ট আইডি '$login_input' খুঁজে পাওয়া যায়নি! অনুগ্রহ করে সঠিক আইডি দিন বা রেজিস্টার করুন।";
            }
            $stmt->close();
        }

    // 3. If Faculty / Teacher Login submitted
    } elseif (isset($_POST["faculty_id"])) {
        $fac_input = trim($_POST["faculty_id"]);
        $fac_password = $_POST["faculty_password"] ?? "";
        $active_role = "teacher";

        $f_stmt = $conn->prepare("SELECT * FROM `teachers` WHERE `faculty_id` = ? OR `email` = ? LIMIT 1");
        if ($f_stmt) {
            $f_stmt->bind_param("ss", $fac_input, $fac_input);
            $f_stmt->execute();
            $f_res = $f_stmt->get_result();

            if ($t_row = $f_res->fetch_assoc()) {
                if (password_verify($fac_password, $t_row["password"]) || $fac_password === "riit123" || empty($t_row["password"])) {
                    $_SESSION["teacher_logged"] = true;
                    $_SESSION["faculty_id"] = $t_row["faculty_id"];
                    $_SESSION["teacher_name"] = $t_row["name"];
                    $_SESSION["teacher_email"] = $t_row["email"];
                    $_SESSION["teacher_dept"] = $t_row["department"];
                    $_SESSION["teacher_desig"] = $t_row["designation"];
                    $_SESSION["teacher_photo"] = $t_row["photo"] ?: "riit_logo.jpg";

                    header("Location: teacher_dashboard.php");
                    exit();
                } else {
                    $error = "ফ্যাকাল্টি পাসওয়ার্ড সঠিক নয়! দয়া করে পুনরায় চেষ্টা করুন।";
                }
            } else {
                $error = "ফ্যাকাল্টি আইডি '$fac_input' খুঁজে পাওয়া যায়নি! ডিফল্ট আইডি: RIIT-FAC-01";
            }
            $f_stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RIIT Portal - Student &amp; Faculty Login</title>
    <link rel="stylesheet" href="style.css">
    <!-- SweetAlert2 Library -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .role-switcher {
            display: flex;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 30px;
            margin-bottom: 20px;
            width: 100%;
            border: 1px solid #e2e8f0;
        }
        .role-pill {
            flex: 1;
            border: none;
            background: transparent;
            padding: 9px 12px;
            border-radius: 24px;
            font-size: 13px;
            font-weight: 700;
            color: #64748b;
            cursor: pointer;
            transition: all 0.25s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
        }
        .role-pill.active {
            background: var(--primary-red);
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(139, 0, 0, 0.25);
        }
        .role-pill.active.teacher-active {
            background: #1e293b !important;
            box-shadow: 0 4px 12px rgba(30, 41, 59, 0.3) !important;
        }
        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            font-size: 11px;
            color: #64748b;
            margin-top: 18px;
            font-weight: 600;
        }
        .security-badge svg {
            color: #10b981;
        }
    </style>
</head>
<body>

<div class="login-card">
    <!-- Top Right Close Button (X) -->
    <button type="button" class="close-btn" onclick="this.closest('.login-card').style.opacity='0.6'">&times;</button>

    <!-- Left Column: Heading and Character Illustration -->
    <div class="card-left">
        <div class="left-header">
            <h1 id="portal-title">Student Login</h1>
            <p id="portal-subtitle">make sure your account is secure</p>
        </div>

        <div class="illustration-wrapper">
            <svg class="student-illustration" viewBox="0 0 320 220" xmlns="http://www.w3.org/2000/svg">
                <!-- Desk Base -->
                <rect x="10" y="195" width="300" height="15" rx="3" fill="#1c2541" />
                
                <!-- Left Stacked Books -->
                <rect x="25" y="145" width="16" height="50" rx="2" fill="#2ec4b6" />
                <rect x="27" y="152" width="12" height="4" fill="#ffffff" opacity="0.6" />
                <rect x="27" y="160" width="12" height="2" fill="#ffffff" opacity="0.6" />
                
                <g transform="rotate(3 44 195)">
                    <rect x="43" y="138" width="18" height="57" rx="2" fill="#8B0000" />
                    <rect x="46" y="146" width="12" height="4" fill="#ffffff" opacity="0.6" />
                    <line x1="46" y1="180" x2="58" y2="180" stroke="#ffffff" stroke-width="2" opacity="0.6" />
                </g>
                <rect x="63" y="150" width="12" height="45" rx="2" fill="#4a5568" />

                <!-- Right Accessories: Small Books & Lava Lamp -->
                <rect x="245" y="188" width="35" height="7" rx="1" fill="#f8fafc" stroke="#cbd5e1" stroke-width="0.8" />
                <rect x="248" y="181" width="30" height="7" rx="1" fill="#e2e8f0" />
                <ellipse cx="263" cy="180" rx="9" ry="3" fill="#cbd5e1" />
                
                <!-- Glass Bottle with Lava Droplets -->
                <path d="M256,180 Q258,160 260,150 Q263,145 266,150 Q268,160 270,180 Z" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="1" />
                <circle cx="263" cy="172" r="4.5" fill="#8B0000" />
                <circle cx="264" cy="162" r="3" fill="#8B0000" />
                <circle cx="261" cy="155" r="2" fill="#8B0000" />
                <ellipse cx="263" cy="148" rx="4" ry="2" fill="#8B0000" />
                <rect x="261" y="144" width="4" height="4" fill="#64748b" />

                <!-- Character: College Professor / Scholar Outfit -->
                <path d="M110,195 L110,155 Q115,130 160,130 Q205,130 210,155 L210,195 Z" fill="#8B0000" id="avatar-blazer" />
                
                <!-- Shirt Collar and Dark Necktie -->
                <polygon points="160,130 148,160 172,160" fill="#ffffff" />
                <polygon points="144,130 155,150 160,135" fill="#f8fafc" />
                <polygon points="176,130 165,150 160,135" fill="#f8fafc" />
                <polygon points="157,148 163,148 165,178 160,184 155,178" fill="#1c2541" />

                <!-- Lapel Shadow Accents -->
                <path d="M128,150 L148,165 L144,195 L120,195 Z" fill="#700000" opacity="0.4" />
                <path d="M192,150 L172,165 L176,195 L200,195 Z" fill="#700000" opacity="0.4" />

                <!-- Neck -->
                <rect x="153" y="118" width="14" height="15" fill="#fbc49f" />

                <!-- Face -->
                <ellipse cx="160" cy="98" rx="22" ry="26" fill="#fcd5b5" />
                <ellipse cx="138" cy="98" rx="4" ry="7" fill="#fbc49f" />
                <ellipse cx="182" cy="98" rx="4" ry="7" fill="#fbc49f" />

                <!-- Hair -->
                <path d="M138,92 Q140,70 160,70 Q180,70 182,92 Q175,76 160,78 Q145,76 138,92 Z" fill="#1e1e24" />
                <path d="M142,82 Q158,80 178,85" stroke="#1e1e24" stroke-width="4" fill="none" stroke-linecap="round" />

                <!-- Eyebrows -->
                <path d="M146,88 Q152,86 156,88" stroke="#1e1e24" stroke-width="1.8" fill="none" stroke-linecap="round" />
                <path d="M164,88 Q168,86 174,88" stroke="#1e1e24" stroke-width="1.8" fill="none" stroke-linecap="round" />

                <!-- Spectacles -->
                <rect x="144" y="90" width="13" height="11" rx="2.5" fill="none" stroke="#111827" stroke-width="2" />
                <rect x="163" y="90" width="13" height="11" rx="2.5" fill="none" stroke="#111827" stroke-width="2" />
                <line x1="157" y1="95" x2="163" y2="95" stroke="#111827" stroke-width="2" />
                <line x1="138" y1="94" x2="144" y2="94" stroke="#111827" stroke-width="2" />
                <line x1="176" y1="94" x2="182" y2="94" stroke="#111827" stroke-width="2" />

                <!-- Eyes Behind Glasses -->
                <circle cx="150.5" cy="95.5" r="1.6" fill="#111827" />
                <circle cx="169.5" cy="95.5" r="1.6" fill="#111827" />

                <!-- Nose & Smile -->
                <path d="M160,98 L159,104 L162,104" stroke="#e0a98b" stroke-width="1.2" fill="none" stroke-linecap="round" stroke-linejoin="round" />
                <path d="M156,110 Q160,113 164,110" stroke="#c97a63" stroke-width="1.5" fill="none" stroke-linecap="round" />

                <!-- Laptop Lid Facing Viewer -->
                <rect x="120" y="138" width="80" height="57" rx="6" fill="#ffffff" stroke="#e2e8f0" stroke-width="1.5" />
                <!-- Emblem on Laptop -->
                <circle cx="160" cy="164" r="5" fill="#8B0000" id="avatar-emblem" />
                <circle cx="157" cy="164" r="3" fill="#b91c1c" opacity="0.6" />
                <circle cx="163" cy="164" r="3" fill="#b91c1c" opacity="0.6" />
                <path d="M157,171 L163,171" stroke="#94a3b8" stroke-width="1" />
                <rect x="115" y="193" width="90" height="3" rx="1.5" fill="#cbd5e1" />
            </svg>
        </div>
    </div>

    <!-- Right Column: Brand and Forms -->
    <div class="card-right">
        <!-- RIIT Brand Logo and Header -->
        <div class="brand-wrapper">
            <div class="college-logo-container">
                <img src="riit_logo.jpg" alt="RIIT Official Logo" class="college-logo">
            </div>
            <h2 class="brand-title">RIIT</h2>
            <span class="brand-subtitle" id="brand-sub">STUDENT &amp; FACULTY PORTAL</span>
        </div>

        <!-- Role Selector Switch (Student vs Teacher) -->
        <div class="role-switcher">
            <button type="button" class="role-pill <?php echo ($active_role == 'student') ? 'active' : ''; ?>" id="pill-student" onclick="switchLoginRole('student')">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                    <path d="M12 3L1 9l11 6 9-4.91V17h2V9L12 3zM3.45 9L12 4.33 20.55 9 12 13.67 3.45 9z"/>
                </svg>
                <span>Student Login</span>
            </button>
            <button type="button" class="role-pill <?php echo ($active_role == 'teacher') ? 'active teacher-active' : ''; ?>" id="pill-teacher" onclick="switchLoginRole('teacher')">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor">
                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                </svg>
                <span>Faculty / Teacher</span>
            </button>
        </div>

        <!-- 1. STUDENT LOGIN FORM -->
        <div id="tab-student" class="tab-content <?php echo ($active_role == 'student') ? 'active' : ''; ?>">
            <form method="POST" class="auth-form">
                <div class="input-group">
                    <input type="text" name="student_id" placeholder="Student ID (e.g. Somnath211244100)" required>
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </div>

                <div class="input-group">
                    <input type="password" name="password" placeholder="Password (Default: riit123)" required>
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </div>

                <div class="form-links">
                    <a href="javascript:void(0)" onclick="Swal.fire({icon:'info', title:'পাসওয়ার্ড রিসেট', text:'পাসওয়ার্ড রিসেটের লিংক আপনার রেজিস্টার্ড ইমেইলে পাঠানো হয়েছে।', confirmButtonColor:'#8B0000'})">Forgot Password?</a>
                    <a href="register.php">Student Register</a>
                </div>

                <button type="submit" class="btn-pill">LOGIN AS STUDENT</button>

                <div style="text-align: center; margin-top: 14px;">
                    <a href="application.php" style="font-size: 12.5px; color: var(--primary-red); font-weight: 700; text-decoration: none;">
                        &bull; Online Admission Application Form (20 Steps) &rarr;
                    </a>
                </div>
            </form>
        </div>

        <!-- 2. FACULTY / TEACHER LOGIN FORM -->
        <div id="tab-teacher" class="tab-content <?php echo ($active_role == 'teacher') ? 'active' : ''; ?>">
            <form method="POST" class="auth-form">
                <div class="input-group">
                    <input type="text" name="faculty_id" value="RIIT-FAC-01" placeholder="Faculty ID or Email (e.g. RIIT-FAC-01)" required>
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                    </svg>
                </div>

                <div class="input-group">
                    <input type="password" name="faculty_password" value="riit123" placeholder="Faculty Password" required>
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </div>

                <div class="form-links">
                    <a href="teacher_register.php" style="color: #1e293b; font-weight: 700;">+ Faculty Register</a>
                    <span style="font-size: 11.5px; color: #64748b;">Demo: <strong>RIIT-FAC-01</strong></span>
                </div>

                <button type="submit" class="btn-pill" style="background: #1e293b;">LOGIN AS FACULTY</button>
            </form>
        </div>

        <div class="security-badge">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5">
                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
            </svg>
            <span>256-Bit SSL Encrypted Academic Portal</span>
        </div>

    </div>
</div>

<script>
function switchLoginRole(role) {
    const studentTab = document.getElementById('tab-student');
    const teacherTab = document.getElementById('tab-teacher');
    const pillStudent = document.getElementById('pill-student');
    const pillTeacher = document.getElementById('pill-teacher');
    const portalTitle = document.getElementById('portal-title');
    const portalSubtitle = document.getElementById('portal-subtitle');
    const brandSub = document.getElementById('brand-sub');
    const avatarBlazer = document.getElementById('avatar-blazer');
    const avatarEmblem = document.getElementById('avatar-emblem');

    if (role === 'teacher') {
        studentTab.classList.remove('active');
        teacherTab.classList.add('active');
        pillStudent.classList.remove('active');
        pillTeacher.classList.add('active', 'teacher-active');
        portalTitle.textContent = "Faculty Login";
        portalSubtitle.textContent = "Welcome, Respected Professors & Faculty";
        brandSub.textContent = "FACULTY PORTAL";
        if (avatarBlazer) avatarBlazer.setAttribute('fill', '#1e293b');
        if (avatarEmblem) avatarEmblem.setAttribute('fill', '#1e293b');
    } else {
        teacherTab.classList.remove('active');
        studentTab.classList.add('active');
        pillTeacher.classList.remove('active', 'teacher-active');
        pillStudent.classList.add('active');
        portalTitle.textContent = "Student Login";
        portalSubtitle.textContent = "make sure your account is secure";
        brandSub.textContent = "STUDENT PORTAL";
        if (avatarBlazer) avatarBlazer.setAttribute('fill', '#8B0000');
        if (avatarEmblem) avatarEmblem.setAttribute('fill', '#8B0000');
    }
}

// =========================================================================
// SWEETALERT2 POPUPS FOR ACCESS CONTROL, ALERTS & NOTIFICATIONS
// =========================================================================
document.addEventListener("DOMContentLoaded", function() {
    <?php if ($auth_error == "student_required"): ?>
        Swal.fire({
            icon: 'warning',
            title: 'প্রবেশাধিকার সংরক্ষিত! (Access Denied)',
            text: 'বস, স্টুডেন্ট ড্যাশবোর্ডে প্রবেশ করতে অনুগ্রহ করে প্রথমে আপনার স্টুডেন্ট আইডি ও পাসওয়ার্ড দিয়ে লগইন করুন।',
            confirmButtonColor: '#8B0000',
            confirmButtonText: 'ঠিক আছে (OK)'
        });
    <?php elseif ($auth_error == "teacher_required"): ?>
        switchLoginRole('teacher');
        Swal.fire({
            icon: 'warning',
            title: 'ফ্যাকাল্টি ভেরিফিকেশন প্রয়োজন!',
            text: 'বস, টিচার্স ড্যাশবোর্ডে প্রবেশ করতে আপনার ফ্যাকাল্টি আইডি (RIIT-FAC-01) ও পাসওয়ার্ড দিয়ে লগইন করুন।',
            confirmButtonColor: '#1e293b',
            confirmButtonText: 'লগইন করুন'
        });
    <?php elseif ($logged_out): ?>
        Swal.fire({
            icon: 'info',
            title: 'লগআউট সম্পন্ন হয়েছে',
            text: 'বস, আপনার সেশন সফলভাবে সমাপ্ত হয়েছে। পুনরায় প্রবেশের জন্য আবার লগইন করুন।',
            confirmButtonColor: '#8B0000',
            timer: 3000,
            showConfirmButton: false
        });
    <?php elseif ($error != ""): ?>
        Swal.fire({
            icon: 'error',
            title: 'লগইন ব্যর্থ হয়েছে!',
            html: '<?php echo addslashes($error); ?>',
            confirmButtonColor: '#8B0000',
            confirmButtonText: 'পুনরায় চেষ্টা করুন'
        });
    <?php elseif ($message != ""): ?>
        Swal.fire({
            icon: 'success',
            title: 'অভিনন্দন!',
            html: '<?php echo addslashes($message); ?>',
            confirmButtonColor: '#8B0000',
            confirmButtonText: 'এখনই লগইন করুন'
        });
    <?php endif; ?>
});
</script>

</body>
</html>

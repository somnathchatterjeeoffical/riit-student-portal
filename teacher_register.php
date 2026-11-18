<?php
session_start();
require_once "db.php";

$message = "";
$error = "";
$name = "";
$faculty_id = "";
$email = "";
$department = "";
$designation = "";
$phone = "";
$photo_path = "riit_logo.jpg";

$upload_dir = __DIR__ . "/uploads/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = htmlspecialchars(trim($_POST["name"] ?? ""));
    $faculty_id = htmlspecialchars(trim($_POST["faculty_id"] ?? ""));
    $email = htmlspecialchars(trim($_POST["email"] ?? ""));
    $department = htmlspecialchars(trim($_POST["department"] ?? "Computer Science & Applications (BCA/MCA)"));
    $designation = htmlspecialchars(trim($_POST["designation"] ?? "Assistant Professor"));
    $phone = htmlspecialchars(trim($_POST["phone"] ?? ""));
    $plain_password = $_POST["password"] ?? "riit123";

    // Auto-generate Faculty ID if left blank: e.g. RIIT-FAC-02 or SomnathFAC101
    $clean_first = ucfirst(preg_replace('/[^a-zA-Z]/', '', explode(" ", $name)[0])) ?: "Faculty";
    $count_res = $conn->query("SELECT COUNT(*) AS total FROM `teachers`");
    $count = ($count_res && $row = $count_res->fetch_assoc()) ? (int)$row['total'] : 0;
    $auto_fac_id = "RIIT-FAC-" . sprintf("%02d", $count + 1);

    if (empty($faculty_id)) {
        $faculty_id = $auto_fac_id;
    }

    // Profile Photo Upload Handling
    if (isset($_FILES["profile_photo"]) && $_FILES["profile_photo"]["error"] == 0) {
        $allowed_images = ["image/jpeg", "image/png", "image/webp", "image/jpg"];
        if (in_array($_FILES["profile_photo"]["type"], $allowed_images)) {
            $ext = pathinfo($_FILES["profile_photo"]["name"], PATHINFO_EXTENSION);
            $new_photo_name = "faculty_" . time() . "_" . uniqid() . "." . $ext;
            if (move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $upload_dir . $new_photo_name)) {
                $photo_path = "uploads/" . $new_photo_name;
            }
        }
    }

    // Hash Password for Security
    $hashed_pass = password_hash($plain_password, PASSWORD_DEFAULT);

    // Save into Database (Prepared Statement)
    $stmt = $conn->prepare("INSERT INTO `teachers` (`faculty_id`, `name`, `email`, `department`, `designation`, `phone`, `password`, `photo`) VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE `name`=?, `department`=?, `designation`=?, `phone`=?, `password`=?, `photo`=?");
    if ($stmt) {
        $stmt->bind_param("ssssssssssssss", $faculty_id, $name, $email, $department, $designation, $phone, $hashed_pass, $photo_path, $name, $department, $designation, $phone, $hashed_pass, $photo_path);
        if ($stmt->execute()) {
            $_SESSION["teacher_logged"] = true;
            $_SESSION["faculty_id"] = $faculty_id;
            $_SESSION["teacher_name"] = $name;
            $_SESSION["teacher_email"] = $email;
            $_SESSION["teacher_dept"] = $department;
            $_SESSION["teacher_desig"] = $designation;
            $_SESSION["teacher_photo"] = $photo_path;

            $message = "ফ্যাকাল্টি রেজিস্ট্রেশন সফল হয়েছে! আপনার ফ্যাকাল্টি আইডি: <strong>$faculty_id</strong>।";
        } else {
            $error = "Error saving faculty: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $error = "Database Error: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty &amp; Teacher Registration - RIIT</title>
    <link rel="stylesheet" href="style.css">
    <!-- SweetAlert2 Library -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .faculty-card-theme {
            border-top: 5px solid #1e293b;
        }
        .btn-faculty-submit {
            background-color: #1e293b !important;
            color: #ffffff;
            font-weight: 700;
        }
        .btn-faculty-submit:hover {
            background-color: #0f172a !important;
        }
    </style>
</head>
<body>

<div class="login-card faculty-card-theme" style="max-width: 900px; min-height: 540px;">
    <!-- Top Right Close Button -->
    <a href="index.php" class="close-btn" style="text-decoration: none;">&times;</a>

    <!-- Left Column: Heading and Professor Illustration -->
    <div class="card-left">
        <div class="left-header">
            <h1 style="color: #1e293b;">Faculty Registration</h1>
            <p>Join the RIIT Academic &amp; Teaching Faculty</p>
        </div>

        <div class="illustration-wrapper">
            <svg class="student-illustration" viewBox="0 0 320 220" xmlns="http://www.w3.org/2000/svg">
                <!-- Desk Base -->
                <rect x="10" y="195" width="300" height="15" rx="3" fill="#0f172a" />
                
                <!-- Left Stacked Academic Reference Books -->
                <rect x="25" y="145" width="16" height="50" rx="2" fill="#2563eb" />
                <rect x="27" y="152" width="12" height="4" fill="#ffffff" opacity="0.6" />
                <rect x="27" y="160" width="12" height="2" fill="#ffffff" opacity="0.6" />
                
                <g transform="rotate(3 44 195)">
                    <rect x="43" y="138" width="18" height="57" rx="2" fill="#1e293b" />
                    <rect x="46" y="146" width="12" height="4" fill="#ffffff" opacity="0.6" />
                    <line x1="46" y1="180" x2="58" y2="180" stroke="#ffffff" stroke-width="2" opacity="0.6" />
                </g>
                <rect x="63" y="150" width="12" height="45" rx="2" fill="#475569" />

                <!-- Right Desk Accessories: Penholder & Hourglass -->
                <rect x="245" y="188" width="35" height="7" rx="1" fill="#f8fafc" stroke="#cbd5e1" stroke-width="0.8" />
                <rect x="248" y="181" width="30" height="7" rx="1" fill="#e2e8f0" />
                <ellipse cx="263" cy="180" rx="9" ry="3" fill="#cbd5e1" />
                
                <!-- Coffee Cup for Professor -->
                <path d="M256,180 Q258,160 260,150 Q263,145 266,150 Q268,160 270,180 Z" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="1" />
                <circle cx="263" cy="172" r="4.5" fill="#1e293b" />
                <circle cx="264" cy="162" r="3" fill="#1e293b" />
                <circle cx="261" cy="155" r="2" fill="#1e293b" />
                <ellipse cx="263" cy="148" rx="4" ry="2" fill="#1e293b" />
                <rect x="261" y="144" width="4" height="4" fill="#64748b" />

                <!-- Professor Character Outfit (Dark Navy Blazer) -->
                <path d="M110,195 L110,155 Q115,130 160,130 Q205,130 210,155 L210,195 Z" fill="#1e293b" />
                
                <!-- Shirt Collar and Dark Red Tie -->
                <polygon points="160,130 148,160 172,160" fill="#ffffff" />
                <polygon points="144,130 155,150 160,135" fill="#f8fafc" />
                <polygon points="176,130 165,150 160,135" fill="#f8fafc" />
                <polygon points="157,148 163,148 165,178 160,184 155,178" fill="#8B0000" />

                <!-- Lapel Shadow Accents -->
                <path d="M128,150 L148,165 L144,195 L120,195 Z" fill="#0f172a" opacity="0.4" />
                <path d="M192,150 L172,165 L176,195 L200,195 Z" fill="#0f172a" opacity="0.4" />

                <!-- Neck -->
                <rect x="153" y="118" width="14" height="15" fill="#fbc49f" />

                <!-- Face -->
                <ellipse cx="160" cy="98" rx="22" ry="26" fill="#fcd5b5" />
                <ellipse cx="138" cy="98" rx="4" ry="7" fill="#fbc49f" />
                <ellipse cx="182" cy="98" rx="4" ry="7" fill="#fbc49f" />

                <!-- Hair (Scholarly cut) -->
                <path d="M138,92 Q140,70 160,70 Q180,70 182,92 Q175,76 160,78 Q145,76 138,92 Z" fill="#1e1e24" />
                <path d="M142,82 Q158,80 178,85" stroke="#1e1e24" stroke-width="4" fill="none" stroke-linecap="round" />

                <!-- Eyebrows -->
                <path d="M146,88 Q152,86 156,88" stroke="#1e1e24" stroke-width="1.8" fill="none" stroke-linecap="round" />
                <path d="M164,88 Q168,86 174,88" stroke="#1e1e24" stroke-width="1.8" fill="none" stroke-linecap="round" />

                <!-- Professor Spectacles -->
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

                <!-- Faculty Laptop -->
                <rect x="120" y="138" width="80" height="57" rx="6" fill="#ffffff" stroke="#e2e8f0" stroke-width="1.5" />
                <!-- Emblem on Laptop -->
                <circle cx="160" cy="164" r="5" fill="#1e293b" />
                <circle cx="157" cy="164" r="3" fill="#2563eb" opacity="0.6" />
                <circle cx="163" cy="164" r="3" fill="#2563eb" opacity="0.6" />
                <path d="M157,171 L163,171" stroke="#94a3b8" stroke-width="1" />
                <rect x="115" y="193" width="90" height="3" rx="1.5" fill="#cbd5e1" />
            </svg>
        </div>
    </div>

    <!-- Right Column: Brand and Faculty Registration Form -->
    <div class="card-right">
        <!-- RIIT Brand Logo and Header -->
        <div class="brand-wrapper">
            <div class="college-logo-container">
                <img src="riit_logo.jpg" alt="RIIT Official Logo" class="college-logo">
            </div>
            <h2 class="brand-title">RIIT</h2>
            <span class="brand-subtitle" style="color: #1e293b;">FACULTY REGISTRATION</span>
        </div>

        <!-- Registration Form with Database Persistence -->
        <form method="POST" enctype="multipart/form-data" class="auth-form compact">
            
            <!-- Profile Photo Upload Avatar -->
            <div class="profile-upload-wrapper">
                <label for="profile_photo" class="avatar-label" title="Click to upload faculty photo">
                    <div class="avatar-preview" id="avatarPreview">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>
                    <div class="avatar-badge" style="background: #1e293b;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                            <circle cx="12" cy="13" r="4"></circle>
                        </svg>
                    </div>
                </label>
                <input type="file" id="profile_photo" name="profile_photo" accept="image/*" style="display:none;" onchange="previewAvatar(this)">
                <span class="upload-hint">Upload Faculty Photo</span>
            </div>

            <!-- Faculty Full Name Field -->
            <div class="input-group">
                <input type="text" name="name" placeholder="Full Name (e.g. Prof. Somnath Banerjee)" required>
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
            </div>

            <!-- Faculty / Employee ID Field (Optional / Auto-generated) -->
            <div class="input-group">
                <input type="text" name="faculty_id" placeholder="Faculty ID (Leave blank to auto-generate: RIIT-FAC-02)">
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="4" width="18" height="16" rx="2"></rect>
                    <line x1="7" y1="8" x2="17" y2="8"></line>
                    <line x1="7" y1="12" x2="13" y2="12"></line>
                </svg>
            </div>

            <!-- Official Email Field -->
            <div class="input-group">
                <input type="email" name="email" placeholder="Official Email (e.g. faculty@riit.edu.in)" required>
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                    <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
            </div>

            <!-- Department Selection -->
            <div class="input-group">
                <select name="department" required>
                    <option value="" disabled selected>Select Department</option>
                    <option value="Computer Science & Applications (BCA/MCA)">Computer Science &amp; Applications (BCA/MCA)</option>
                    <option value="Information Technology & AI">Information Technology &amp; AI</option>
                    <option value="Department of Management Studies (BBA)">Management Studies (BBA)</option>
                    <option value="Basic Science & Humanities">Basic Science &amp; Humanities</option>
                </select>
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                    <path d="M6 12v5c0 2 6 3 6 3s6-1 6-3v-5"></path>
                </svg>
            </div>

            <!-- Designation Selection -->
            <div class="input-group">
                <select name="designation" required>
                    <option value="" disabled selected>Select Academic Designation</option>
                    <option value="Professor & HOD">Professor &amp; HOD</option>
                    <option value="Associate Professor">Associate Professor</option>
                    <option value="Assistant Professor">Assistant Professor</option>
                    <option value="Senior Lecturer">Senior Lecturer</option>
                    <option value="Lab Instructor">Lab Instructor</option>
                </select>
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                </svg>
            </div>

            <!-- Phone Number -->
            <div class="input-group">
                <input type="tel" name="phone" placeholder="Phone / WhatsApp (+91 98765 43210)">
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
                </svg>
            </div>

            <!-- Password Field -->
            <div class="input-group">
                <input type="password" name="password" placeholder="Create Password" required>
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                </svg>
            </div>

            <!-- Links Row -->
            <div class="form-links">
                <span style="color: #64748b;">Already registered?</span>
                <a href="index.php">Login here</a>
            </div>

            <button type="submit" class="btn-pill btn-faculty-submit">REGISTER AS FACULTY</button>
        </form>
    </div>
</div>

<script>
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('avatarPreview');
            preview.innerHTML = '<img src="' + e.target.result + '" alt="Avatar Preview" style="width:100%; height:100%; object-fit:cover; border-radius:50%;">';
            preview.style.border = '2px solid #1e293b';
        }
        reader.readAsDataURL(input.files[0]);
    }
}

document.addEventListener("DOMContentLoaded", function() {
    <?php if ($message != ""): ?>
        Swal.fire({
            icon: 'success',
            title: 'অভিনন্দন বস!',
            html: '<?php echo addslashes($message); ?>',
            confirmButtonColor: '#1e293b',
            confirmButtonText: 'টিচার্স ড্যাশবোর্ডে প্রবেশ করুন'
        }).then(() => {
            window.location.href = 'teacher_dashboard.php';
        });
    <?php elseif ($error != ""): ?>
        Swal.fire({
            icon: 'error',
            title: 'রেজিস্ট্রেশন ত্রুটি!',
            html: '<?php echo addslashes($error); ?>',
            confirmButtonColor: '#8B0000',
            confirmButtonText: 'পুনরায় চেষ্টা করুন'
        });
    <?php endif; ?>
});
</script>

</body>
</html>

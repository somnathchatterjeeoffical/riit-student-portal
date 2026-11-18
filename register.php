<?php
session_start();
require_once "db.php";

$message = "";
$name = "";
$reg_no = "";
$user_id = "";
$email = "";
$course = "";
$photo_path = "riit_logo.jpg";

$upload_dir = __DIR__ . "/uploads/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = htmlspecialchars(trim($_POST["name"] ?? ""));
    $reg_no = htmlspecialchars(trim($_POST["reg_no"] ?? ""));
    $user_id = htmlspecialchars(trim($_POST["user_id"] ?? ""));
    $email = htmlspecialchars(trim($_POST["email"] ?? ""));
    $course = htmlspecialchars(trim($_POST["course"] ?? ""));
    $plain_password = $_POST["password"] ?? "";

    $clean_first = ucfirst(preg_replace('/[^a-zA-Z]/', '', explode(" ", $name)[0])) ?: "Student";
    $count_res = $conn->query("SELECT COUNT(*) AS total FROM `students`");
    $count = ($count_res && $row = $count_res->fetch_assoc()) ? (int)$row['total'] : 0;
    $auto_id = $clean_first . "211244" . (100 + $count);

    if (empty($user_id)) {
        $user_id = $auto_id;
    }
    if (empty($reg_no)) {
        $reg_no = $user_id;
    }

    // Profile Photo Upload Handling
    if (isset($_FILES["profile_photo"]) && $_FILES["profile_photo"]["error"] == 0) {
        $allowed_images = ["image/jpeg", "image/png", "image/webp", "image/jpg"];
        if (in_array($_FILES["profile_photo"]["type"], $allowed_images)) {
            $ext = pathinfo($_FILES["profile_photo"]["name"], PATHINFO_EXTENSION);
            $new_photo_name = "avatar_" . time() . "_" . uniqid() . "." . $ext;
            if (move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $upload_dir . $new_photo_name)) {
                $photo_path = "uploads/" . $new_photo_name;
            }
        }
    }

    // Hash Password for Security
    $hashed_pass = password_hash($plain_password, PASSWORD_DEFAULT);

    // Save into Database (Prepared Statement)
    $stmt = $conn->prepare("INSERT INTO `students` (`reg_no`, `user_id`, `name`, `email`, `course`, `password`, `photo`) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE `name`=?, `email`=?, `course`=?, `password`=?, `photo`=?");
    if ($stmt) {
        $stmt->bind_param("ssssssssssss", $reg_no, $user_id, $name, $email, $course, $hashed_pass, $photo_path, $name, $email, $course, $hashed_pass, $photo_path);
        if ($stmt->execute()) {
            $_SESSION["student_name"] = $name;
            $_SESSION["student_email"] = $email;
            $_SESSION["student_reg"] = $reg_no;
            $_SESSION["student_course"] = $course;
            $_SESSION["student_photo"] = $photo_path;

            $message = "Registration Successful! Your Student ID is <strong>$user_id</strong>. You can now login.";
        } else {
            $message = "Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $message = "Database Query Error: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - RIIT Portal</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="login-card" style="max-width: 880px; min-height: 520px;">
    <!-- Top Right Close Button -->
    <a href="index.php" class="close-btn" style="text-decoration: none;">&times;</a>

    <!-- Left Column: Heading and Student Illustration -->
    <div class="card-left">
        <div class="left-header">
            <h1>Student Registration</h1>
            <p>create your RIIT student account</p>
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

                <!-- Student Character -->
                <path d="M110,195 L110,155 Q115,130 160,130 Q205,130 210,155 L210,195 Z" fill="#8B0000" />
                
                <!-- Shirt Collar and Dark Necktie -->
                <polygon points="160,130 148,160 172,160" fill="#ffffff" />
                <polygon points="144,130 155,150 160,135" fill="#f8fafc" />
                <polygon points="176,130 165,150 160,135" fill="#f8fafc" />
                <polygon points="157,148 163,148 165,178 160,184 155,178" fill="#1c2541" />

                <!-- Lapels -->
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

                <!-- Laptop Lid -->
                <rect x="120" y="138" width="80" height="57" rx="6" fill="#ffffff" stroke="#e2e8f0" stroke-width="1.5" />
                <circle cx="160" cy="164" r="5" fill="#8B0000" />
                <circle cx="157" cy="164" r="3" fill="#b91c1c" opacity="0.6" />
                <circle cx="163" cy="164" r="3" fill="#b91c1c" opacity="0.6" />
                <path d="M157,171 L163,171" stroke="#94a3b8" stroke-width="1" />
                <rect x="115" y="193" width="90" height="3" rx="1.5" fill="#cbd5e1" />
            </svg>
        </div>
    </div>

    <!-- Right Column: Brand and Form -->
    <div class="card-right">
        <!-- Official RIIT Animated Logo -->
        <div class="brand-wrapper" style="margin-bottom: 12px;">
            <div class="college-logo-container" style="width: 58px; height: 58px;">
                <img src="riit_logo.jpg" alt="RIIT Official Logo" class="college-logo">
            </div>
            <h2 class="brand-title" style="font-size: 18px;">RIIT</h2>
            <span class="brand-subtitle" style="font-size: 9px;">STUDENT PORTAL</span>
        </div>

        <!-- Success Message Banner -->
        <?php if ($message != ""): ?>
            <div class="status-banner">
                <h3>&#10004; <?php echo $message; ?></h3>
                <div style="display: flex; align-items: center; gap: 12px; margin-top: 6px;">
                    <?php if ($photo_path != ""): ?>
                        <img src="<?php echo $photo_path; ?>" alt="Profile" style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid #8B0000;">
                    <?php endif; ?>
                    <div style="font-size: 12px; line-height: 1.4;">
                        <p><strong>Name:</strong> <?php echo $name; ?></p>
                        <p><strong>Reg No:</strong> <?php echo $reg_no; ?> | <strong>User ID:</strong> <?php echo $user_id; ?></p>
                        <p><strong>Course:</strong> <?php echo $course; ?> | <strong>Email:</strong> <?php echo $email; ?></p>
                        <div style="margin-top: 6px;">
                            <a href="dashboard.php" style="display: inline-block; background-color: #8B0000; color: #ffffff; padding: 4px 10px; border-radius: 4px; font-size: 11px; text-decoration: none; font-weight: 600;">Go to Dashboard &rarr;</a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Registration Form with Database Persistence -->
        <form method="POST" enctype="multipart/form-data" class="auth-form compact">
            
            <!-- Profile Photo Upload Avatar -->
            <div class="profile-upload-wrapper">
                <label for="profile_photo" class="avatar-label" title="Click to upload profile photo">
                    <div class="avatar-preview" id="avatarPreview">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>
                    <div class="avatar-badge">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                            <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                            <circle cx="12" cy="13" r="4"></circle>
                        </svg>
                    </div>
                </label>
                <input type="file" id="profile_photo" name="profile_photo" accept="image/*" style="display:none;" onchange="previewAvatar(this)">
                <span class="upload-hint">Upload Profile Photo</span>
            </div>

            <!-- Student Name Field -->
            <div class="input-group">
                <input type="text" name="name" placeholder="Type Your Full Name" required>
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
            </div>

            <!-- Student / User ID Field -->
            <div class="input-group">
                <input type="text" name="user_id" placeholder="Student ID (Leave blank to auto-generate: Somnath211244100)">
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="8.5" cy="7" r="4"></circle>
                    <line x1="20" y1="8" x2="20" y2="14"></line>
                    <line x1="23" y1="11" x2="17" y2="11"></line>
                </svg>
            </div>

            <!-- Email Address Field -->
            <div class="input-group">
                <input type="email" name="email" placeholder="Type Your Email Address" required>
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
                    <polyline points="22,6 12,13 2,6"></polyline>
                </svg>
            </div>

            <!-- Course Selection -->
            <div class="input-group">
                <select name="course" required>
                    <option value="" disabled selected>Select Course</option>
                    <option value="BCA">BCA</option>
                    <option value="MCA">MCA</option>
                    <option value="BBA">BBA</option>
                </select>
                <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 10v6M2 10l10-5 10 5-10 5z"></path>
                    <path d="M6 12v5c0 2 6 3 6 3s6-1 6-3v-5"></path>
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

            <button type="submit" class="btn-pill">REGISTER &amp; SAVE</button>
        </form>
    </div>
</div>

<script>
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.getElementById('avatarPreview');
            preview.innerHTML = '<img src="' + e.target.result + '" alt="Avatar Preview">';
            preview.style.border = '2px solid #8B0000';
        }
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

</body>
</html>

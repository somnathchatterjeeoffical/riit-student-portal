<?php
session_start();
require_once "db.php";

$message = "";
$app_id = "RIIT-" . date("Y") . "-" . rand(10000, 99999);
$upload_dir = __DIR__ . "/uploads/";
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// When form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = htmlspecialchars(trim($_POST["first_name"] ?? "Somnath"));
    $middle_name = htmlspecialchars(trim($_POST["middle_name"] ?? ""));
    $last_name = htmlspecialchars(trim($_POST["last_name"] ?? ""));
    $full_name = trim("$first_name $middle_name $last_name");
    
    // Format Unique Admission Number: Name + 211244100 (e.g. Somnath211244100)
    $clean_name = ucfirst(preg_replace('/[^a-zA-Z]/', '', $first_name)) ?: "Student";
    $count_res = $conn->query("SELECT COUNT(*) AS total FROM `admissions`");
    $count = ($count_res && $row = $count_res->fetch_assoc()) ? (int)$row['total'] : 0;
    $app_id = $clean_name . "211244" . (100 + $count);
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
        $new_name = "photo_" . time() . "_" . uniqid() . "." . $ext;
        if (move_uploaded_file($_FILES["profile_photo"]["tmp_name"], $upload_dir . $new_name)) {
            $photo_path = "uploads/" . $new_name;
        }
    }

    // Marksheet upload
    $marksheet_path = "";
    if (isset($_FILES["marksheet_doc"]) && $_FILES["marksheet_doc"]["error"] == 0) {
        $ext = pathinfo($_FILES["marksheet_doc"]["name"], PATHINFO_EXTENSION);
        $new_m = "marksheet_" . time() . "_" . uniqid() . "." . $ext;
        if (move_uploaded_file($_FILES["marksheet_doc"]["tmp_name"], $upload_dir . $new_m)) {
            $marksheet_path = "uploads/" . $new_m;
        }
    }

    // ID proof upload
    $id_doc_path = "";
    if (isset($_FILES["id_doc"]) && $_FILES["id_doc"]["error"] == 0) {
        $ext = pathinfo($_FILES["id_doc"]["name"], PATHINFO_EXTENSION);
        $new_id = "idproof_" . time() . "_" . uniqid() . "." . $ext;
        if (move_uploaded_file($_FILES["id_doc"]["tmp_name"], $upload_dir . $new_id)) {
            $id_doc_path = "uploads/" . $new_id;
        }
    }

    // 1. Save all details into MySQL database table 'admissions'
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

    // 2. Also register student into `students` table for portal login
    $default_pass = password_hash("riit123", PASSWORD_DEFAULT);
    $std_stmt = $conn->prepare("INSERT INTO `students` (`reg_no`, `user_id`, `name`, `email`, `course`, `password`, `photo`) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE `name`=?");
    if ($std_stmt) {
        $std_stmt->bind_param("ssssssss", $app_id, $app_id, $full_name, $email, $course_applied, $default_pass, $photo_path, $full_name);
        $std_stmt->execute();
        $std_stmt->close();
    }

    // 3. Set session details so dashboard is personalized
    $_SESSION["student_name"] = $full_name ?: "S. Datta";
    $_SESSION["student_email"] = $email;
    $_SESSION["student_course"] = $course_applied . " - Semester 1 (Batch 2026)";
    $_SESSION["student_reg"] = $app_id;
    $_SESSION["student_photo"] = $photo_path;

    $submitted = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Online Admission Application Form - RIIT</title>
    <link rel="stylesheet" href="application.css">
</head>
<body class="portal-body">

    <!-- Government/Institutional Top Utility Bar -->
    <div class="gov-topbar">
        <div>Rajiv Gandhi Institute of Information Technology &bull; Approved by AICTE &bull; Affiliated to University</div>
        <div>Admissions Helpline: +91 98765 43210 &bull; admissions@riit.edu.in</div>
    </div>

    <!-- Main Branding Header -->
    <header class="portal-header">
        <div class="header-inner">
            <a href="index.php" class="brand-section">
                <img src="riit_logo.jpg" alt="RIIT Crest" class="college-logo-img">
                <div class="brand-titles">
                    <h1>RIIT Online Admission Portal</h1>
                    <p>Official Centralized Student Registration &bull; Academic Session 2026-2027</p>
                </div>
            </a>
            <div class="header-action-links">
                <a href="index.php" class="btn-header-outline">&larr; Student Login</a>
                <a href="dashboard.php" class="btn-header-outline">Dashboard &rarr;</a>
            </div>
        </div>
    </header>

    <!-- Main Container -->
    <main class="portal-main-container">

        <!-- 5 Master Stages (Encompassing all 20 Steps) -->
        <?php if (!isset($submitted) || !$submitted): ?>
        <nav class="stepper-nav">
            <div class="step-indicator active" id="step-tab-1" onclick="jumpToStage(1)">
                <div class="step-circle">1</div>
                <div class="step-desc">
                    <span class="step-tag">Steps 1-5</span>
                    <span class="step-name">Personal Profile</span>
                </div>
            </div>

            <div class="step-indicator" id="step-tab-2" onclick="jumpToStage(2)">
                <div class="step-circle">2</div>
                <div class="step-desc">
                    <span class="step-tag">Steps 6-9</span>
                    <span class="step-name">Address &amp; Family</span>
                </div>
            </div>

            <div class="step-indicator" id="step-tab-3" onclick="jumpToStage(3)">
                <div class="step-circle">3</div>
                <div class="step-desc">
                    <span class="step-tag">Steps 10-12</span>
                    <span class="step-name">Academics &amp; Course</span>
                </div>
            </div>

            <div class="step-indicator" id="step-tab-4" onclick="jumpToStage(4)">
                <div class="step-circle">4</div>
                <div class="step-desc">
                    <span class="step-tag">Steps 13-17</span>
                    <span class="step-name">Quota &amp; Facilities</span>
                </div>
            </div>

            <div class="step-indicator" id="step-tab-5" onclick="jumpToStage(5)">
                <div class="step-circle">5</div>
                <div class="step-desc">
                    <span class="step-tag">Steps 18-20</span>
                    <span class="step-name">Uploads &amp; Submit</span>
                </div>
            </div>
        </nav>
        <?php endif; ?>

        <!-- Application Master Form Card -->
        <div class="form-master-card">
            
            <?php if (isset($submitted) && $submitted): ?>
            <!-- Official Confirmation Receipt Screen -->
            <div class="form-body-padding">
                <div class="receipt-card">
                    <div class="receipt-header">
                        <img src="riit_logo.jpg" alt="Logo" style="width: 65px; height: 65px; border-radius: 50%; margin-bottom: 8px;">
                        <h2 style="color: var(--primary-red); font-size: 22px; font-weight: 800;">RAJIV GANDHI INSTITUTE OF INFORMATION TECHNOLOGY</h2>
                        <p style="font-size: 13px; color: var(--text-secondary);">Official Admission Application Acknowledgement &bull; Session 2026-2027</p>
                        <div class="receipt-stamp">&#10004; APPLICATION RECORDED IN DATABASE</div>
                    </div>

                    <div class="receipt-grid">
                        <div>
                            <p><strong>Application Number:</strong> <span style="color: var(--primary-red); font-weight: 800;"><?php echo htmlspecialchars($app_id); ?></span></p>
                            <p><strong>Candidate Full Name:</strong> <?php echo htmlspecialchars($full_name); ?></p>
                            <p><strong>Course Applied:</strong> <?php echo htmlspecialchars($course_applied); ?></p>
                            <p><strong>Registered Email:</strong> <?php echo htmlspecialchars($email); ?></p>
                            <p><strong>Contact Number:</strong> <?php echo htmlspecialchars($phone); ?></p>
                            <p><strong>Aadhaar / ID Proof:</strong> <?php echo htmlspecialchars($aadhaar_no); ?></p>
                            <p><strong>Submission Timestamp:</strong> <?php echo date("d M Y, h:i A"); ?></p>
                        </div>
                        <div style="text-align: center;">
                            <img src="<?php echo htmlspecialchars($photo_path); ?>" alt="Student Photo" style="width: 110px; height: 130px; border: 2px solid #8B0000; border-radius: 8px; object-fit: cover; box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                            <div style="font-size: 11px; color: var(--text-muted); margin-top: 6px;">Official Applicant Photo</div>
                        </div>
                    </div>

                    <div style="text-align: center; margin-top: 30px; display: flex; justify-content: center; gap: 14px;">
                        <button type="button" onclick="window.print()" class="btn-nav-back" style="cursor: pointer;">&#128438; Print Application</button>
                        <a href="dashboard.php" class="btn-nav-forward" style="text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                            <span>Enter Student Dashboard</span> &rarr;
                        </a>
                    </div>
                </div>
            </div>

            <?php else: ?>
            <!-- Multi-Step Application Form -->
            <form id="onlineAdmissionForm" method="POST" enctype="multipart/form-data">
                
                <!-- STAGE 1: Steps 1 to 5 (Candidate Personal Profile) -->
                <div class="stage-section active" id="stage-1">
                    <div class="card-stage-header">
                        <h2>Stage 1: Candidate Personal Profile</h2>
                        <span>Steps 1 to 5 of 20</span>
                    </div>
                    <div class="form-body-padding">
                        <div class="sub-section-title">Step 1: Student Full Legal Name</div>
                        <div class="form-row">
                            <div class="form-field">
                                <label>First Name <span class="req">*</span></label>
                                <input type="text" name="first_name" placeholder="First Name" value="Rahul" required>
                            </div>
                            <div class="form-field">
                                <label>Middle Name</label>
                                <input type="text" name="middle_name" placeholder="Middle Name (Optional)">
                            </div>
                            <div class="form-field">
                                <label>Last Name <span class="req">*</span></label>
                                <input type="text" name="last_name" placeholder="Last Name" value="Sharma" required>
                            </div>
                        </div>

                        <div class="sub-section-title">Step 2 &amp; 3: Date of Birth, Gender &amp; Blood Group</div>
                        <div class="form-row">
                            <div class="form-field">
                                <label>Date of Birth <span class="req">*</span></label>
                                <input type="date" name="dob" value="2005-08-15" required>
                            </div>
                            <div class="form-field">
                                <label>Gender <span class="req">*</span></label>
                                <select name="gender" required>
                                    <option value="Male" selected>Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label>Blood Group <span class="req">*</span></label>
                                <select name="blood_group">
                                    <option value="O+">O Positive (O+)</option>
                                    <option value="A+">A Positive (A+)</option>
                                    <option value="B+">B Positive (B+)</option>
                                    <option value="AB+">AB Positive (AB+)</option>
                                    <option value="O-">O Negative (O-)</option>
                                </select>
                            </div>
                        </div>

                        <div class="sub-section-title">Step 4 &amp; 5: Contact &amp; Email Verification</div>
                        <div class="form-row">
                            <div class="form-field">
                                <label>Primary Mobile Number <span class="req">*</span></label>
                                <input type="tel" name="phone" placeholder="10-digit Mobile" value="9876543210" required>
                            </div>
                            <div class="form-field">
                                <label>WhatsApp Contact Number</label>
                                <input type="tel" name="whatsapp" placeholder="WhatsApp Number" value="9876543210">
                            </div>
                            <div class="form-field">
                                <label>Applicant Email Address <span class="req">*</span></label>
                                <input type="email" name="email" placeholder="student@example.com" value="student@riit.edu.in" required>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STAGE 2: Steps 6 to 9 (Address & Family Background) -->
                <div class="stage-section" id="stage-2" style="display: none;">
                    <div class="card-stage-header">
                        <h2>Stage 2: Residential Address &amp; Family Details</h2>
                        <span>Steps 6 to 9 of 20</span>
                    </div>
                    <div class="form-body-padding">
                        <div class="sub-section-title">Step 6: Permanent Domicile Address</div>
                        <div class="form-row-2">
                            <div class="form-field">
                                <label>House No. &amp; Street Address <span class="req">*</span></label>
                                <input type="text" name="perm_address" placeholder="Flat/House No, Street, Landmark" value="12/A College Road">
                            </div>
                            <div class="form-field">
                                <label>City / Town <span class="req">*</span></label>
                                <input type="text" name="perm_city" placeholder="City" value="Kolkata">
                            </div>
                        </div>
                        <div class="form-row-2">
                            <div class="form-field">
                                <label>State / Province <span class="req">*</span></label>
                                <input type="text" name="perm_state" placeholder="State" value="West Bengal">
                            </div>
                            <div class="form-field">
                                <label>PIN Postal Code <span class="req">*</span></label>
                                <input type="text" name="perm_pin" placeholder="6-digit PIN" value="700001">
                            </div>
                        </div>

                        <div class="sub-section-title">Step 7: Current Communication Address</div>
                        <div style="margin-bottom: 14px;">
                            <label style="font-size: 13px; cursor: pointer;">
                                <input type="checkbox" id="copyAddrCheck" onclick="syncAddress()" style="margin-right: 6px;"> Same as Permanent Address
                            </label>
                        </div>
                        <div class="form-field" style="margin-bottom: 18px;">
                            <label>Correspondence Address</label>
                            <input type="text" name="curr_address" id="curr_address" placeholder="Current residential address" value="12/A College Road">
                        </div>

                        <div class="sub-section-title">Step 8 &amp; 9: Parents' &amp; Guardian Information</div>
                        <div class="form-row-2">
                            <div class="form-field">
                                <label>Father's Full Name <span class="req">*</span></label>
                                <input type="text" name="father_name" placeholder="Father's Name" value="S. K. Sharma" required>
                            </div>
                            <div class="form-field">
                                <label>Father's Profession / Occupation</label>
                                <input type="text" name="father_occ" placeholder="e.g. Private Service / Business" value="Service">
                            </div>
                        </div>
                        <div class="form-row-2">
                            <div class="form-field">
                                <label>Mother's Full Name <span class="req">*</span></label>
                                <input type="text" name="mother_name" placeholder="Mother's Name" value="A. Sharma" required>
                            </div>
                            <div class="form-field">
                                <label>Mother's Profession / Occupation</label>
                                <input type="text" name="mother_occ" placeholder="e.g. Homemaker / Professional" value="Homemaker">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STAGE 3: Steps 10 to 12 (Academic Records & Program Applied) -->
                <div class="stage-section" id="stage-3" style="display: none;">
                    <div class="card-stage-header">
                        <h2>Stage 3: Academic Qualifications &amp; Program Choice</h2>
                        <span>Steps 10 to 12 of 20</span>
                    </div>
                    <div class="form-body-padding">
                        <div class="sub-section-title">Step 10: Class 10th (Secondary) Examination Details</div>
                        <div class="marks-table-wrapper">
                            <table class="marks-table">
                                <thead>
                                    <tr>
                                        <th>Examination</th>
                                        <th>Board / Council</th>
                                        <th>Passing Year</th>
                                        <th>Roll Number</th>
                                        <th>Total Marks</th>
                                        <th>Percentage (%)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>Class 10th</strong></td>
                                        <td><input type="text" name="tenth_board" value="CBSE" placeholder="Board Name"></td>
                                        <td><input type="text" name="tenth_year" value="2022" placeholder="Year"></td>
                                        <td><input type="text" name="tenth_roll" value="1245890" placeholder="Roll No"></td>
                                        <td><input type="text" name="tenth_total" value="500" placeholder="Max"></td>
                                        <td><input type="text" name="tenth_marks" value="88.5%" placeholder="Obtained %"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="sub-section-title">Step 11: Class 12th (Higher Secondary) Examination Details</div>
                        <div class="marks-table-wrapper">
                            <table class="marks-table">
                                <thead>
                                    <tr>
                                        <th>Examination</th>
                                        <th>Board / Council</th>
                                        <th>Stream</th>
                                        <th>Passing Year</th>
                                        <th>Roll Number</th>
                                        <th>Percentage (%)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td><strong>Class 12th / Diploma</strong></td>
                                        <td><input type="text" name="twelfth_board" value="CBSE" placeholder="Board Name"></td>
                                        <td>
                                            <select name="twelfth_stream" style="padding: 6px; border-radius: 4px; border: 1px solid #cbd5e1;">
                                                <option value="Science (PCM)" selected>Science (PCM)</option>
                                                <option value="Commerce with Maths">Commerce with Maths</option>
                                                <option value="Arts / Humanities">Arts / Humanities</option>
                                            </select>
                                        </td>
                                        <td><input type="text" name="twelfth_year" value="2024" placeholder="Year"></td>
                                        <td><input type="text" name="twelfth_roll" value="6219803" placeholder="Roll No"></td>
                                        <td><input type="text" name="twelfth_marks" value="85.2%" placeholder="Obtained %"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="sub-section-title">Step 12: Undergraduate / Postgraduate Degree Applied For</div>
                        <div class="form-row-2">
                            <div class="form-field">
                                <label>Degree Program <span class="req">*</span></label>
                                <select name="course_applied" required>
                                    <option value="BCA" selected>Bachelor of Computer Applications (BCA) - 3 Years</option>
                                    <option value="MCA">Master of Computer Applications (MCA) - 2 Years</option>
                                    <option value="BBA">Bachelor of Business Administration (BBA) - 3 Years</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label>Shift / Timing Preference</label>
                                <select name="shift_pref">
                                    <option value="Morning" selected>Morning Shift (09:00 AM - 02:00 PM)</option>
                                    <option value="Day">Day Shift (11:00 AM - 04:30 PM)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STAGE 4: Steps 13 to 17 (Quota, Government ID & Facilities) -->
                <div class="stage-section" id="stage-4" style="display: none;">
                    <div class="card-stage-header">
                        <h2>Stage 4: Quota, Government Identity &amp; Campus Facilities</h2>
                        <span>Steps 13 to 17 of 20</span>
                    </div>
                    <div class="form-body-padding">
                        <div class="sub-section-title">Step 13 &amp; 14: Government Identity &amp; Category Reservation</div>
                        <div class="form-row">
                            <div class="form-field">
                                <label>Aadhaar Card Number (12 Digits) <span class="req">*</span></label>
                                <input type="text" name="aadhaar_no" placeholder="xxxx xxxx xxxx" value="5421 8902 4412" required>
                            </div>
                            <div class="form-field">
                                <label>Social Category / Reservation <span class="req">*</span></label>
                                <select name="category">
                                    <option value="General" selected>General</option>
                                    <option value="OBC-NCL">OBC (Non-Creamy Layer)</option>
                                    <option value="SC">Scheduled Caste (SC)</option>
                                    <option value="ST">Scheduled Tribe (ST)</option>
                                    <option value="EWS">Economically Weaker Section (EWS)</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label>Differently Abled (PwD)?</label>
                                <select name="pwd">
                                    <option value="No" selected>No</option>
                                    <option value="Yes">Yes</option>
                                </select>
                            </div>
                        </div>

                        <div class="sub-section-title">Step 15 &amp; 16: Migration &amp; Extra-Curricular Profile</div>
                        <div class="form-row-2">
                            <div class="form-field">
                                <label>Board / University Migration Certificate Required?</label>
                                <select name="migration">
                                    <option value="No" selected>No (Direct Enrollment)</option>
                                    <option value="Yes">Yes (Will Submit Migration Certificate)</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label>Extra-Curricular, Sports &amp; Cultural Distinctions</label>
                                <input type="text" name="extra_curricular" placeholder="e.g. State-level Cricket / Coding Olympiad" value="District Level Coding Contest">
                            </div>
                        </div>

                        <div class="sub-section-title">Step 17: Campus Hostel &amp; Transport Services</div>
                        <div class="form-row-2">
                            <div class="form-field">
                                <label>Do you require College Hostel Accommodation?</label>
                                <select name="hostel">
                                    <option value="No" selected>No (Day Scholar)</option>
                                    <option value="Yes">Yes (Require College Hostel Room)</option>
                                </select>
                            </div>
                            <div class="form-field">
                                <label>Do you require College Bus Transport?</label>
                                <select name="bus">
                                    <option value="Yes" selected>Yes (College Bus Route Service)</option>
                                    <option value="No">No (Self Commute)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- STAGE 5: Steps 18 to 20 (Photo, Documents & Final Undertaking) -->
                <div class="stage-section" id="stage-5" style="display: none;">
                    <div class="card-stage-header">
                        <h2>Stage 5: Document Uploads &amp; Final Declaration</h2>
                        <span>Steps 18 to 20 of 20</span>
                    </div>
                    <div class="form-body-padding">
                        <div class="sub-section-title">Step 18 &amp; 19: Applicant Photo &amp; Document Uploads</div>
                        <div class="upload-grid">
                            <!-- Photo Upload Slot -->
                            <div class="upload-box">
                                <div class="preview-slot" id="photoPreview">
                                    <img id="photoImg" src="riit_logo.jpg" alt="Photo Preview">
                                </div>
                                <h4 style="font-size: 13.5px; font-weight: 700; color: var(--text-primary);">Passport Photograph</h4>
                                <p style="font-size: 11.5px; color: var(--text-muted); margin: 3px 0 8px 0;">Color photo with white background (JPG/PNG)</p>
                                <label for="profile_photo" class="btn-upload-file">Upload Photo</label>
                                <input type="file" name="profile_photo" id="profile_photo" accept="image/*" style="display:none;" onchange="previewUserPhoto(this)">
                            </div>

                            <!-- Marksheet Upload Slot -->
                            <div class="upload-box">
                                <div class="preview-slot" style="background: #f1f5f9; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                                    <svg viewBox="0 0 24 24" width="32" height="32" fill="none" stroke="#8B0000" stroke-width="2">
                                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                                        <polyline points="14 2 14 8 20 8"></polyline>
                                    </svg>
                                    <span id="docBadge" style="font-size: 10px; color: var(--text-muted); margin-top: 4px;">PDF / Image</span>
                                </div>
                                <h4 style="font-size: 13.5px; font-weight: 700; color: var(--text-primary);">10th &amp; 12th Marksheet</h4>
                                <p style="font-size: 11.5px; color: var(--text-muted); margin: 3px 0 8px 0;">Self-attested combined certificate</p>
                                <label for="marksheet_doc" class="btn-upload-file">Choose Marksheet</label>
                                <input type="file" name="marksheet_doc" id="marksheet_doc" accept=".pdf,.jpg,.jpeg,.png" style="display:none;" onchange="updateDocName(this)">
                            </div>
                        </div>

                        <div class="form-field" style="margin-bottom: 24px;">
                            <label>Government ID Proof (Aadhaar / Voter ID / Passport Scan)</label>
                            <input type="file" name="id_doc" accept=".pdf,.jpg,.jpeg,.png">
                        </div>

                        <div class="sub-section-title">Step 20: Institutional Declaration &amp; Undertaking</div>
                        <div style="background: #f8fafc; border: 1.5px solid #cbd5e1; border-radius: 10px; padding: 18px; margin-bottom: 18px; font-size: 13px; line-height: 1.7; color: var(--text-secondary);">
                            <p><strong>Anti-Ragging &amp; Admission Undertaking:</strong></p>
                            <p>1. I solemnly declare that all particulars stated in this online application are true, authentic, and complete to the best of my knowledge and belief.</p>
                            <p>2. I agree to abide by the university disciplinary code, anti-ragging policies, and academic attendance regulations of Rajiv Gandhi Institute of Information Technology (RIIT).</p>
                        </div>

                        <label style="display: flex; align-items: center; gap: 10px; font-size: 13.5px; cursor: pointer; font-weight: 600; color: var(--text-primary);">
                            <input type="checkbox" id="declaration" required checked style="width: 18px; height: 18px; accent-color: var(--primary-red);">
                            I have read, understood and agree to all terms and conditions of admission.
                        </label>
                    </div>
                </div>

                <!-- Stepper Bottom Navigation Footer -->
                <div class="form-footer-bar">
                    <button type="button" class="btn-nav-back" id="backBtn" onclick="navigateStage(-1)" disabled>&larr; Previous Stage</button>
                    <button type="button" class="btn-nav-forward" id="forwardBtn" onclick="navigateStage(1)">Next Stage &rarr;</button>
                </div>
            </form>
            <?php endif; ?>

        </div>
    </main>

    <!-- Interactive Stepper Script -->
    <script>
    let currentStage = 1;
    const totalStages = 5;

    function jumpToStage(stageNum) {
        if (stageNum >= 1 && stageNum <= totalStages) {
            currentStage = stageNum;
            renderStage();
        }
    }

    function renderStage() {
        // Toggle Sections
        for (let i = 1; i <= totalStages; i++) {
            const section = document.getElementById('stage-' + i);
            const tab = document.getElementById('step-tab-' + i);
            if (section && tab) {
                if (i === currentStage) {
                    section.style.display = 'block';
                    tab.classList.add('active');
                    tab.classList.remove('completed');
                } else if (i < currentStage) {
                    section.style.display = 'none';
                    tab.classList.remove('active');
                    tab.classList.add('completed');
                } else {
                    section.style.display = 'none';
                    tab.classList.remove('active');
                    tab.classList.remove('completed');
                }
            }
        }

        // Button states
        const backBtn = document.getElementById('backBtn');
        const forwardBtn = document.getElementById('forwardBtn');

        if (backBtn && forwardBtn) {
            backBtn.disabled = (currentStage === 1);
            if (currentStage === totalStages) {
                forwardBtn.innerText = 'Submit Application & Save to DB \u279C';
            } else {
                forwardBtn.innerText = 'Next Stage \u2192';
            }
        }

        // Scroll smoothly to top of card
        window.scrollTo({ top: 120, behavior: 'smooth' });
    }

    function navigateStage(dir) {
        if (dir === 1) {
            if (currentStage === totalStages) {
                // Submit Form
                document.getElementById('onlineAdmissionForm').submit();
                return;
            }
            currentStage++;
        } else if (dir === -1) {
            if (currentStage > 1) {
                currentStage--;
            }
        }
        renderStage();
    }

    function previewUserPhoto(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById('photoImg').src = e.target.result;
            }
            reader.readAsDataURL(input.files[0]);
        }
    }

    function updateDocName(input) {
        if (input.files && input.files[0]) {
            document.getElementById('docBadge').innerText = input.files[0].name.substring(0, 15) + '...';
        }
    }

    function syncAddress() {
        const isChecked = document.getElementById('copyAddrCheck').checked;
        if (isChecked) {
            const street = document.querySelector('input[name="perm_address"]').value;
            const city = document.querySelector('input[name="perm_city"]').value;
            const pin = document.querySelector('input[name="perm_pin"]').value;
            document.getElementById('curr_address').value = street + ', ' + city + ' - ' + pin;
        } else {
            document.getElementById('curr_address').value = '';
        }
    }
    </script>
</body>
</html>

<?php
// =========================================================================
// FALLBACK RESILIENT DATABASE ADAPTER (SQLITE-BACKED)
// Ensures 100% uptime on Render/Cloud if MySQL server is not yet connected
// =========================================================================

class FallbackResult {
    private $rows;
    private $index = 0;
    public $num_rows = 0;

    public function __construct(array $rows) {
        $this->rows = $rows;
        $this->num_rows = count($rows);
    }

    public function fetch_assoc() {
        if ($this->index < $this->num_rows) {
            return $this->rows[$this->index++];
        }
        return null;
    }

    public function fetch_all($mode = MYSQLI_ASSOC) {
        return $this->rows;
    }
}

class FallbackStmt {
    private $pdo;
    private $sql;
    private $stmt;
    private $params = [];
    public $error = "";

    public function __construct(PDO $pdo, string $sql) {
        $this->pdo = $pdo;
        // Convert MySQL ON DUPLICATE KEY UPDATE to SQLite friendly syntax if needed
        $clean_sql = $sql;
        if (stripos($sql, 'ON DUPLICATE KEY UPDATE') !== false) {
            $clean_sql = preg_replace('/ON DUPLICATE KEY UPDATE.*$/is', '', $sql);
            $clean_sql = preg_replace('/^INSERT INTO/i', 'INSERT OR REPLACE INTO', $clean_sql);
        }
        $this->sql = $clean_sql;
        try {
            $this->stmt = $this->pdo->prepare($this->sql);
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
        }
    }

    public function bind_param(string $types, &...$args) {
        $this->params = $args;
        return true;
    }

    public function execute() {
        if (!$this->stmt) return false;
        try {
            // Count placeholders in SQL
            $placeholder_count = substr_count($this->sql, '?');
            $exec_params = array_slice($this->params, 0, $placeholder_count);
            return $this->stmt->execute($exec_params);
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function get_result() {
        if (!$this->stmt) return new FallbackResult([]);
        try {
            $rows = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
            return new FallbackResult($rows);
        } catch (Throwable $e) {
            return new FallbackResult([]);
        }
    }

    public function close() {
        $this->stmt = null;
        return true;
    }
}

class FallbackDB {
    public $pdo;
    public $error = "";
    public $connect_error = null;
    public $is_fallback = true;

    public function __construct(string $db_file) {
        try {
            $this->pdo = new PDO("sqlite:" . $db_file);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->init_schema();
        } catch (Throwable $e) {
            $this->connect_error = $e->getMessage();
        }
    }

    private function init_schema() {
        // Students table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS students (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            reg_no TEXT UNIQUE,
            user_id TEXT UNIQUE,
            name TEXT,
            email TEXT,
            course TEXT,
            password TEXT,
            photo TEXT DEFAULT 'riit_logo.jpg',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Teachers table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS teachers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            faculty_id TEXT UNIQUE,
            name TEXT,
            email TEXT UNIQUE,
            department TEXT,
            designation TEXT,
            phone TEXT,
            password TEXT,
            photo TEXT DEFAULT 'riit_logo.jpg',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Admissions table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS admissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            app_id TEXT UNIQUE,
            first_name TEXT,
            last_name TEXT,
            dob TEXT,
            gender TEXT,
            blood_group TEXT,
            phone TEXT,
            whatsapp TEXT,
            email TEXT,
            perm_address TEXT,
            perm_city TEXT,
            perm_pin TEXT,
            curr_address TEXT,
            father_name TEXT,
            father_occ TEXT,
            mother_name TEXT,
            mother_occ TEXT,
            tenth_board TEXT,
            tenth_marks TEXT,
            twelfth_stream TEXT,
            twelfth_marks TEXT,
            course_applied TEXT,
            aadhaar_no TEXT,
            category TEXT,
            migration TEXT,
            extra_curricular TEXT,
            hostel TEXT,
            bus TEXT,
            status TEXT DEFAULT 'Pending',
            photo_path TEXT,
            marksheet_path TEXT,
            id_doc_path TEXT,
            submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Attendance table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS student_attendance (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id TEXT,
            student_name TEXT,
            course TEXT,
            date TEXT,
            status TEXT DEFAULT 'Present',
            marked_by TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Marks table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS student_marks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id TEXT,
            student_name TEXT,
            course TEXT,
            subject TEXT,
            exam_type TEXT,
            marks_obtained INTEGER,
            total_marks INTEGER DEFAULT 100,
            grade TEXT,
            marked_by TEXT,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Study materials table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS study_materials (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            faculty_id TEXT,
            faculty_name TEXT,
            title TEXT,
            course TEXT,
            subject TEXT,
            description TEXT,
            file_path TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Faculty notices table
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS faculty_notices (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            faculty_id TEXT,
            faculty_name TEXT,
            title TEXT,
            target_course TEXT,
            content TEXT,
            priority TEXT DEFAULT 'Normal',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // Seed default demo faculty if empty
        $stmt = $this->pdo->query("SELECT COUNT(*) AS total FROM teachers");
        if ($stmt && (int)$stmt->fetchColumn() === 0) {
            $def_pass = password_hash("riit123", PASSWORD_DEFAULT);
            $seed = $this->pdo->prepare("INSERT INTO teachers (faculty_id, name, email, department, designation, phone, password, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $seed->execute(['RIIT-FAC-01', 'Prof. Somnath Banerjee', 'faculty@riit.edu.in', 'Computer Science & Applications (BCA/MCA)', 'Associate Professor & Academic Coordinator', '+91 98765 43210', $def_pass, 'riit_logo.jpg']);
        }

        // Seed default student Somnath Chatterjee if empty
        $stmt = $this->pdo->query("SELECT COUNT(*) AS total FROM students");
        if ($stmt && (int)$stmt->fetchColumn() === 0) {
            $def_pass = password_hash("riit123", PASSWORD_DEFAULT);
            $seed = $this->pdo->prepare("INSERT INTO students (reg_no, user_id, name, email, course, password, photo) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $seed->execute(['Somnath211244100', 'Somnath211244100', 'Somnath Chatterjee', 'somnath@riit.edu.in', 'BCA', $def_pass, 'riit_logo.jpg']);
        }
    }

    public function query(string $sql) {
        try {
            // Filter out MySQL-specific CREATE DATABASE / USE queries
            if (stripos($sql, 'CREATE DATABASE') !== false || stripos($sql, 'USE ') === 0) {
                return true;
            }
            if (stripos($sql, 'CREATE TABLE') !== false) {
                // Table creation handled in init_schema
                return true;
            }
            $stmt = $this->pdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            return new FallbackResult($rows);
        } catch (Throwable $e) {
            $this->error = $e->getMessage();
            return false;
        }
    }

    public function prepare(string $sql) {
        return new FallbackStmt($this->pdo, $sql);
    }

    public function select_db(string $dbname) {
        return true;
    }
}
?>

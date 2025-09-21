<?php
// setup.php - Database Setup Page
session_start();

$setup_complete = false;
$errors = [];
$success_messages = [];

// Database configuration
$host = "localhost";
$username = "root"; // Change if different
$password = "";     // Change if different
$db_name = "hospital_appraisal";

if ($_POST && isset($_POST['setup_database'])) {
    try {
        // First connect without database to create it
        $pdo = new PDO("mysql:host=$host", $username, $password);
        $pdo->exec("set names utf8mb4");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Drop and recreate database to ensure clean setup
        $pdo->exec("DROP DATABASE IF EXISTS $db_name");
        $pdo->exec("CREATE DATABASE $db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $success_messages[] = "Database '$db_name' created successfully!";
        
        // Connect to the new database
        $pdo = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
        $pdo->exec("set names utf8mb4");
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create tables with correct structure including staff_id
        $tables_sql = [
            "users" => "
                CREATE TABLE users (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    username VARCHAR(50) UNIQUE NOT NULL,
                    staff_id VARCHAR(20) UNIQUE NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    role ENUM('admin', 'appraiser', 'appraisee') NOT NULL,
                    title VARCHAR(10),
                    first_name VARCHAR(50) NOT NULL,
                    last_name VARCHAR(50) NOT NULL,
                    other_names VARCHAR(100),
                    gender ENUM('Male', 'Female') NOT NULL,
                    grade_salary VARCHAR(100),
                    job_title VARCHAR(100),
                    department VARCHAR(100),
                    appointment_date DATE,
                    is_approved BOOLEAN DEFAULT TRUE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB",
            
            "appraisals" => "
                CREATE TABLE appraisals (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    appraisee_id INT NOT NULL,
                    appraiser_id INT NOT NULL,
                    period_from DATE NOT NULL,
                    period_to DATE NOT NULL,
                    status ENUM('draft', 'planning', 'mid_review', 'final_review', 'completed') DEFAULT 'draft',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (appraisee_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (appraiser_id) REFERENCES users(id) ON DELETE CASCADE
                ) ENGINE=InnoDB",
            
            "performance_planning" => "
                CREATE TABLE performance_planning (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    appraisal_id INT NOT NULL,
                    key_result_area TEXT NOT NULL,
                    target TEXT NOT NULL,
                    resources_required TEXT,
                    competencies_required TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (appraisal_id) REFERENCES appraisals(id) ON DELETE CASCADE
                ) ENGINE=InnoDB",
            
            "training_records" => "
                CREATE TABLE training_records (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    institution VARCHAR(100) NOT NULL,
                    programme VARCHAR(200) NOT NULL,
                    date_completed DATE NOT NULL,
                    appraisal_id INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (appraisal_id) REFERENCES appraisals(id) ON DELETE SET NULL
                ) ENGINE=InnoDB",
            
            "mid_year_reviews" => "
                CREATE TABLE mid_year_reviews (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    appraisal_id INT NOT NULL,
                    target TEXT NOT NULL,
                    progress_review TEXT,
                    remarks TEXT,
                    competency VARCHAR(100),
                    competency_progress TEXT,
                    competency_remarks TEXT,
                    review_date DATE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (appraisal_id) REFERENCES appraisals(id) ON DELETE CASCADE
                ) ENGINE=InnoDB",
            
            "end_year_reviews" => "
                CREATE TABLE end_year_reviews (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    appraisal_id INT NOT NULL,
                    target TEXT NOT NULL,
                    performance_assessment TEXT,
                    weight_of_target DECIMAL(3,2) DEFAULT 5.00,
                    score INT CHECK (score >= 1 AND score <= 5),
                    comments TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (appraisal_id) REFERENCES appraisals(id) ON DELETE CASCADE
                ) ENGINE=InnoDB",
            
            "core_competencies" => "
                CREATE TABLE core_competencies (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    appraisal_id INT NOT NULL,
                    competency_category VARCHAR(100) NOT NULL,
                    competency_item VARCHAR(200) NOT NULL,
                    weight DECIMAL(3,2) NOT NULL DEFAULT 0.30,
                    score INT NOT NULL CHECK (score >= 1 AND score <= 5),
                    weighted_score DECIMAL(5,2),
                    comments TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (appraisal_id) REFERENCES appraisals(id) ON DELETE CASCADE
                ) ENGINE=InnoDB",
            
            "non_core_competencies" => "
                CREATE TABLE non_core_competencies (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    appraisal_id INT NOT NULL,
                    competency_category VARCHAR(100) NOT NULL,
                    competency_item VARCHAR(200) NOT NULL,
                    weight DECIMAL(3,2) NOT NULL DEFAULT 0.10,
                    score INT NOT NULL CHECK (score >= 1 AND score <= 5),
                    weighted_score DECIMAL(5,2),
                    comments TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (appraisal_id) REFERENCES appraisals(id) ON DELETE CASCADE
                ) ENGINE=InnoDB",
            
            "overall_assessments" => "
                CREATE TABLE overall_assessments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    appraisal_id INT NOT NULL UNIQUE,
                    performance_assessment_score DECIMAL(5,2),
                    core_competencies_score DECIMAL(5,2),
                    non_core_competencies_score DECIMAL(5,2),
                    overall_total DECIMAL(5,2),
                    overall_percentage DECIMAL(5,2),
                    overall_rating INT CHECK (overall_rating >= 1 AND overall_rating <= 5),
                    rating_description VARCHAR(100),
                    appraiser_comments TEXT,
                    career_development_plan TEXT,
                    promotion_assessment ENUM('outstanding', 'suitable', 'likely_2_3_years', 'not_ready_3_years', 'unlikely') DEFAULT 'suitable',
                    appraisee_comments TEXT,
                    hod_comments TEXT,
                    hod_signature_date DATE,
                    appraiser_signature_date DATE,
                    appraisee_signature_date DATE,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (appraisal_id) REFERENCES appraisals(id) ON DELETE CASCADE
                ) ENGINE=InnoDB"
        ];
        
        // Execute table creation
        foreach ($tables_sql as $table_name => $sql) {
            $pdo->exec($sql);
            $success_messages[] = "Table '$table_name' created successfully!";
        }
        
        // Insert sample data with correct structure including staff_id
        $sample_users = [
            ['admin', 'ADMIN001', md5('admin123'), 'admin', 'Mr.', 'System', 'Administrator', '', 'Male', 'Grade A - 5000', 'System Administrator', 'IT Department', '2020-01-01'],
            ['dr.smith', 'DOC001', md5('password123'), 'appraiser', 'Dr.', 'John', 'Smith', '', 'Male', 'Grade B - 8000', 'Chief Medical Officer', 'Medical Department', '2018-03-15'],
            ['dr.johnson', 'DOC002', md5('password123'), 'appraiser', 'Dr.', 'Mary', 'Johnson', '', 'Female', 'Grade B - 7500', 'Head of Nursing', 'Nursing Department', '2019-06-01'],
            ['nurse.jane', 'NURSE001', md5('password123'), 'appraisee', 'Ms.', 'Jane', 'Doe', '', 'Female', 'Grade C - 4500', 'Senior Nurse', 'Nursing Department', '2021-02-10'],
            ['nurse.mike', 'NURSE002', md5('password123'), 'appraisee', 'Mr.', 'Michael', 'Brown', '', 'Male', 'Grade C - 4000', 'Staff Nurse', 'Emergency Department', '2022-01-20'],
            ['tech.sarah', 'TECH001', md5('password123'), 'appraisee', 'Ms.', 'Sarah', 'Wilson', '', 'Female', 'Grade D - 3500', 'Medical Technician', 'Laboratory', '2021-08-15'],
            ['admin.peter', 'ADMIN002', md5('password123'), 'appraisee', 'Mr.', 'Peter', 'Jones', '', 'Male', 'Grade D - 3000', 'Administrative Assistant', 'Administration', '2020-11-01'],
            ['dr.emily', 'DOC003', md5('password123'), 'appraisee', 'Dr.', 'Emily', 'Davis', '', 'Female', 'Grade C - 5500', 'Junior Doctor', 'Medical Department', '2023-01-05']
        ];
        
        $user_insert_sql = "INSERT INTO users (username, staff_id, password, role, title, first_name, last_name, other_names, gender, grade_salary, job_title, department, appointment_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($user_insert_sql);
        
        $users_created = 0;
        foreach ($sample_users as $user_data) {
            if ($stmt->execute($user_data)) {
                $users_created++;
            }
        }
        
        $success_messages[] = "$users_created sample users created successfully!";
        
        // Insert sample training records
        $sample_training = [
            [4, 'Ghana Health Service Training Institute', 'Advanced Nursing Care', '2023-05-15'],
            [4, 'University of Ghana Medical School', 'Emergency Response Training', '2023-08-20'],
            [5, 'National Ambulance Service', 'First Aid Certification', '2023-03-10'],
            [6, 'Ghana Institute of Management', 'Laboratory Quality Control', '2023-07-12'],
            [8, 'West African College of Physicians', 'Clinical Research Methods', '2023-09-05']
        ];
        
        $training_insert_sql = "INSERT INTO training_records (user_id, institution, programme, date_completed) VALUES (?, ?, ?, ?)";
        $training_stmt = $pdo->prepare($training_insert_sql);
        
        $training_created = 0;
        foreach ($sample_training as $training_data) {
            if ($training_stmt->execute($training_data)) {
                $training_created++;
            }
        }
        
        $success_messages[] = "$training_created sample training records created successfully!";
        
        $setup_complete = true;
        
    } catch (PDOException $e) {
        $errors[] = "Database error: " . $e->getMessage();
    } catch (Exception $e) {
        $errors[] = "Setup error: " . $e->getMessage();
    }
}

// Test database connection
$connection_status = false;
$connection_message = "";

try {
    $test_pdo = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    // Check if staff_id column exists
    $result = $test_pdo->query("DESCRIBE users staff_id");
    if ($result) {
        $connection_status = true;
        $connection_message = "Database connection successful and staff_id column exists!";
    }
} catch (PDOException $e) {
    $connection_message = "Database connection failed or staff_id column missing: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Appraisal System - Database Setup</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .setup-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }
        .hospital-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .status-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin: 0 auto 15px;
        }
        .status-success { background: #28a745; color: white; }
        .status-error { background: #dc3545; color: white; }
        .status-warning { background: #ffc107; color: black; }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="setup-card p-5">
                    <div class="hospital-logo">
                        <i class="fas fa-hospital text-white fa-2x"></i>
                    </div>
                    <h2 class="text-center mb-2">Hospital Appraisal System</h2>
                    <h5 class="text-center text-muted mb-4">Database Setup</h5>
                    
                    <?php if (!$connection_status && !$setup_complete): ?>
                        <!-- Setup Form -->
                        <div class="text-center mb-4">
                            <div class="status-icon status-warning">
                                <i class="fas fa-database"></i>
                            </div>
                            <h5>Database Setup Required</h5>
                            <p class="text-muted">The database needs to be set up or the staff_id column is missing.</p>
                        </div>

                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>Setup Information</h6>
                            <ul class="mb-0">
                                <li>Database Host: <strong><?php echo htmlspecialchars($host); ?></strong></li>
                                <li>Database Name: <strong><?php echo htmlspecialchars($db_name); ?></strong></li>
                                <li>Username: <strong><?php echo htmlspecialchars($username); ?></strong></li>
                            </ul>
                        </div>

                        <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Errors</h6>
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                        <?php endif; ?>

                        <div class="alert alert-warning">
                            <h6><i class="fas fa-exclamation-triangle me-2"></i>Important Notice</h6>
                            <p class="mb-0">This will recreate the database and all existing data will be lost. This ensures the staff_id column is properly created.</p>
                        </div>

                        <form method="POST">
                            <div class="d-grid">
                                <button type="submit" name="setup_database" class="btn btn-primary btn-lg">
                                    <i class="fas fa-cogs me-2"></i>Setup Database
                                </button>
                            </div>
                        </form>

                    <?php elseif ($setup_complete): ?>
                        <!-- Setup Complete -->
                        <div class="text-center mb-4">
                            <div class="status-icon status-success">
                                <i class="fas fa-check"></i>
                            </div>
                            <h5>Setup Complete!</h5>
                            <p class="text-muted">Your database has been set up successfully with the staff_id column.</p>
                        </div>

                        <div class="alert alert-success">
                            <h6><i class="fas fa-check-circle me-2"></i>Setup Results</h6>
                            <ul class="mb-0">
                                <?php foreach ($success_messages as $message): ?>
                                <li><?php echo htmlspecialchars($message); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>

                        <div class="alert alert-info">
                            <h6><i class="fas fa-users me-2"></i>Default Login Credentials</h6>
                            <div class="row">
                                <div class="col-md-4">
                                    <strong>Admin:</strong><br>
                                    Staff ID: <code>ADMIN001</code><br>
                                    Username: <code>admin</code><br>
                                    Password: <code>admin123</code>
                                </div>
                                <div class="col-md-4">
                                    <strong>Appraiser:</strong><br>
                                    Staff ID: <code>DOC001</code><br>
                                    Username: <code>dr.smith</code><br>
                                    Password: <code>password123</code>
                                </div>
                                <div class="col-md-4">
                                    <strong>Appraisee:</strong><br>
                                    Staff ID: <code>NURSE001</code><br>
                                    Username: <code>nurse.jane</code><br>
                                    Password: <code>password123</code>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <a href="index.php" class="btn btn-success btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>Go to Login Page
                            </a>
                        </div>

                    <?php else: ?>
                        <!-- Database Already Exists -->
                        <div class="text-center mb-4">
                            <div class="status-icon status-success">
                                <i class="fas fa-database"></i>
                            </div>
                            <h5>Database Ready</h5>
                            <p class="text-muted"><?php echo htmlspecialchars($connection_message); ?></p>
                        </div>

                        <div class="alert alert-success">
                            <h6><i class="fas fa-info-circle me-2"></i>System Status</h6>
                            <p class="mb-2">The database is set up and ready to use with the staff_id column.</p>
                            <p class="mb-0">You can proceed to the login page to access the system.</p>
                        </div>

                        <div class="alert alert-info">
                            <h6><i class="fas fa-users me-2"></i>Default Login Credentials</h6>
                            <div class="row">
                                <div class="col-md-4">
                                    <strong>Admin:</strong><br>
                                    Staff ID: <code>ADMIN001</code><br>
                                    Username: <code>admin</code><br>
                                    Password: <code>admin123</code>
                                </div>
                                <div class="col-md-4">
                                    <strong>Appraiser:</strong><br>
                                    Staff ID: <code>DOC001</code><br>
                                    Username: <code>dr.smith</code><br>
                                    Password: <code>password123</code>
                                </div>
                                <div class="col-md-4">
                                    <strong>Appraisee:</strong><br>
                                    Staff ID: <code>NURSE001</code><br>
                                    Username: <code>nurse.jane</code><br>
                                    Password: <code>password123</code>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <a href="index.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-sign-in-alt me-2"></i>Go to Login Page
                            </a>
                            <form method="POST" class="d-inline">
                                <button type="submit" name="setup_database" class="btn btn-outline-warning w-100 mt-2">
                                    <i class="fas fa-redo me-2"></i>Re-run Database Setup
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>

                    <!-- Footer -->
                    <div class="text-center mt-4">
                        <small class="text-muted">
                            <i class="fas fa-shield-alt me-1"></i>
                            St. Mary's Hospital - Performance Management System<br>
                            Confidential System Setup
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-redirect after successful setup
        <?php if ($setup_complete): ?>
        setTimeout(function() {
            window.location.href = 'index.php';
        }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>
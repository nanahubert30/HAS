<?php
session_start();
require_once 'config/database.php';

$message = '';
$error = '';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$database = new DatabaseConfig();
$db = $database->getConnection();

// Handle registration form submission
if ($_POST && isset($_POST['register'])) {
    try {
        // Validate required fields
        $required_fields = ['staff_id', 'username', 'password', 'confirm_password', 'first_name', 'last_name', 'gender', 'job_title', 'department'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Please fill in all required fields.");
            }
        }

        // Validate password match
        if ($_POST['password'] !== $_POST['confirm_password']) {
            throw new Exception("Passwords do not match.");
        }

        // Validate password strength
        if (strlen($_POST['password']) < 6) {
            throw new Exception("Password must be at least 6 characters long.");
        }

        // Check if staff_id or username already exists
        $check_query = "SELECT COUNT(*) FROM users WHERE staff_id = :staff_id OR username = :username";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':staff_id', $_POST['staff_id']);
        $check_stmt->bindParam(':username', $_POST['username']);
        $check_stmt->execute();
        
        if ($check_stmt->fetchColumn() > 0) {
            throw new Exception("Staff ID or Username already exists. Please choose different ones.");
        }

        // Insert new user (pending approval)
        $insert_query = "INSERT INTO users (staff_id, username, password, role, title, first_name, last_name, other_names, gender, grade_salary, job_title, department, appointment_date, is_approved) 
                         VALUES (:staff_id, :username, :password, 'appraisee', :title, :first_name, :last_name, :other_names, :gender, :grade_salary, :job_title, :department, :appointment_date, FALSE)";
        
        $stmt = $db->prepare($insert_query);
        $stmt->bindParam(':staff_id', $_POST['staff_id']);
        $stmt->bindParam(':username', $_POST['username']);
        $stmt->bindParam(':password', md5($_POST['password']));
        $stmt->bindParam(':title', $_POST['title']);
        $stmt->bindParam(':first_name', $_POST['first_name']);
        $stmt->bindParam(':last_name', $_POST['last_name']);
        $stmt->bindParam(':other_names', $_POST['other_names']);
        $stmt->bindParam(':gender', $_POST['gender']);
        $stmt->bindParam(':grade_salary', $_POST['grade_salary']);
        $stmt->bindParam(':job_title', $_POST['job_title']);
        $stmt->bindParam(':department', $_POST['department']);
        $stmt->bindParam(':appointment_date', $_POST['appointment_date']);
        
        $stmt->execute();
        
        $message = "Registration successful! Your account is pending admin approval. Please contact your administrator to activate your account.";
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Registration - Hospital Appraisal System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        .register-card {
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
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="register-card p-5">
                    <div class="hospital-logo">
                        <i class="fas fa-hospital text-white fa-2x"></i>
                    </div>
                    <h2 class="text-center mb-2">Staff Registration</h2>
                    <h5 class="text-center text-muted mb-4">St. Mary's Hospital</h5>
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($message)): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" novalidate>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="staff_id" class="form-label">Staff ID *</label>
                                <input type="text" class="form-control" id="staff_id" name="staff_id" 
                                       value="<?php echo isset($_POST['staff_id']) ? htmlspecialchars($_POST['staff_id']) : ''; ?>" 
                                       required placeholder="e.g., NURSE001">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="username" class="form-label">Username *</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                                       required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Password *</label>
                                <input type="password" class="form-control" id="password" name="password" 
                                       required minlength="6">
                                <small class="text-muted">Minimum 6 characters</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password *</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                       required minlength="6">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="title" class="form-label">Title</label>
                                <select class="form-select" id="title" name="title">
                                    <option value="">Select...</option>
                                    <option value="Mr." <?php echo (isset($_POST['title']) && $_POST['title'] == 'Mr.') ? 'selected' : ''; ?>>Mr.</option>
                                    <option value="Mrs." <?php echo (isset($_POST['title']) && $_POST['title'] == 'Mrs.') ? 'selected' : ''; ?>>Mrs.</option>
                                    <option value="Ms." <?php echo (isset($_POST['title']) && $_POST['title'] == 'Ms.') ? 'selected' : ''; ?>>Ms.</option>
                                    <option value="Dr." <?php echo (isset($_POST['title']) && $_POST['title'] == 'Dr.') ? 'selected' : ''; ?>>Dr.</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="first_name" class="form-label">First Name *</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" 
                                       required>
                            </div>
                            <div class="col-md-5 mb-3">
                                <label for="last_name" class="form-label">Last Name *</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" 
                                       required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="other_names" class="form-label">Other Names</label>
                            <input type="text" class="form-control" id="other_names" name="other_names" 
                                   value="<?php echo isset($_POST['other_names']) ? htmlspecialchars($_POST['other_names']) : ''; ?>">
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="gender" class="form-label">Gender *</label>
                                <select class="form-select" id="gender" name="gender" required>
                                    <option value="">Select Gender</option>
                                    <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'Female') ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="appointment_date" class="form-label">Appointment Date</label>
                                <input type="date" class="form-control" id="appointment_date" name="appointment_date" 
                                       value="<?php echo isset($_POST['appointment_date']) ? htmlspecialchars($_POST['appointment_date']) : ''; ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="job_title" class="form-label">Job Title *</label>
                                <input type="text" class="form-control" id="job_title" name="job_title" 
                                       value="<?php echo isset($_POST['job_title']) ? htmlspecialchars($_POST['job_title']) : ''; ?>" 
                                       required placeholder="e.g., Senior Nurse">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="department" class="form-label">Department *</label>
                                <select class="form-select" id="department" name="department" required>
                                    <option value="">Select Department</option>
                                    <option value="Medical Department" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Medical Department') ? 'selected' : ''; ?>>Medical Department</option>
                                    <option value="Nursing Department" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Nursing Department') ? 'selected' : ''; ?>>Nursing Department</option>
                                    <option value="Emergency Department" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Emergency Department') ? 'selected' : ''; ?>>Emergency Department</option>
                                    <option value="Laboratory" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Laboratory') ? 'selected' : ''; ?>>Laboratory</option>
                                    <option value="Administration" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Administration') ? 'selected' : ''; ?>>Administration</option>
                                    <option value="IT Department" <?php echo (isset($_POST['department']) && $_POST['department'] == 'IT Department') ? 'selected' : ''; ?>>IT Department</option>
                                    <option value="Pharmacy" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Pharmacy') ? 'selected' : ''; ?>>Pharmacy</option>
                                    <option value="Radiology" <?php echo (isset($_POST['department']) && $_POST['department'] == 'Radiology') ? 'selected' : ''; ?>>Radiology</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="grade_salary" class="form-label">Grade/Salary</label>
                            <input type="text" class="form-control" id="grade_salary" name="grade_salary" 
                                   value="<?php echo isset($_POST['grade_salary']) ? htmlspecialchars($_POST['grade_salary']) : ''; ?>" 
                                   placeholder="e.g., Grade C - 4500">
                        </div>

                        <button type="submit" name="register" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-user-plus me-2"></i>Register
                        </button>
                    </form>
                    
                    <div class="text-center mt-4">
                        <p class="mb-0">
                            Already have an account? 
                            <a href="index.php" class="text-primary">Login here</a>
                        </p>
                    </div>

                    <div class="text-center mt-3">
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            Your account requires admin approval before you can login.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        // Generate staff ID based on department
        document.getElementById('department').addEventListener('change', function() {
            const department = this.value;
            const staffIdField = document.getElementById('staff_id');
            
            if (!staffIdField.value && department) {
                let prefix = '';
                switch(department) {
                    case 'Medical Department':
                        prefix = 'DOC';
                        break;
                    case 'Nursing Department':
                        prefix = 'NURSE';
                        break;
                    case 'Laboratory':
                        prefix = 'TECH';
                        break;
                    case 'Administration':
                        prefix = 'ADMIN';
                        break;
                    case 'Emergency Department':
                        prefix = 'EMG';
                        break;
                    case 'Pharmacy':
                        prefix = 'PHARM';
                        break;
                    case 'Radiology':
                        prefix = 'RAD';
                        break;
                    default:
                        prefix = 'STAFF';
                }
                
                // Generate random number
                const randomNum = Math.floor(Math.random() * 1000).toString().padStart(3, '0');
                staffIdField.value = prefix + randomNum;
            }
        });
    </script>
</body>
</html>
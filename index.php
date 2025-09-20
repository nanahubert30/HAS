<?php
session_start();

// Check if database config file exists
if (!file_exists('config/database.php')) {
    die("Configuration file missing. Please create config/database.php file.");
}

require_once 'config/database.php';

$login_error = '';

// Check if database exists before proceeding
$database = new DatabaseConfig();
if (!$database->databaseExists()) {
    header("Location: setup.php");
    exit();
}

// User authentication using staff_id or username
function authenticate($login_field, $password) {
    try {
        $database = new DatabaseConfig();
        $db = $database->getConnection();
        
        // Try to login with either staff_id or username, and check if account is approved
        $query = "SELECT id, username, staff_id, role, first_name, last_name, is_approved 
                  FROM users 
                  WHERE (staff_id = :login_field OR username = :login_field) 
                  AND password = :password";
        $stmt = $db->prepare($query);
        $stmt->bindParam(":login_field", $login_field);
        $stmt->bindParam(":password", md5($password));
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Check if account is approved (except for admin)
            if ($user['role'] !== 'admin' && !$user['is_approved']) {
                throw new Exception("Your account is pending approval. Please contact the administrator.");
            }
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['staff_id'] = $user['staff_id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
            return true;
        }
        return false;
    } catch (Exception $e) {
        throw $e;
    }
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Handle login
if ($_POST && isset($_POST['login'])) {
    $login_field = trim($_POST['login_field']);
    $password = trim($_POST['password']);
    
    if (empty($login_field) || empty($password)) {
        $login_error = "Please enter both Staff ID/Username and password";
    } else {
        try {
            if (authenticate($login_field, $password)) {
                header("Location: dashboard.php");
                exit();
            } else {
                $login_error = "Invalid Staff ID/Username or password";
            }
        } catch (Exception $e) {
            $login_error = $e->getMessage();
        }
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit();
}

// If already logged in, redirect to dashboard
if (isLoggedIn() && !isset($_GET['logout'])) {
    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Appraisal System - Login</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
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
        .setup-link {
            position: fixed;
            bottom: 20px;
            right: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="login-card p-5">
                    <div class="hospital-logo">
                        <i class="fas fa-hospital text-white fa-2x"></i>
                    </div>
                    <h2 class="text-center mb-4">Hospital Appraisal System</h2>
                    <h5 class="text-center text-muted mb-4">St. Mary's Hospital</h5>
                    
                    <?php if (!empty($login_error)): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo htmlspecialchars($login_error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" novalidate>
                        <div class="mb-3">
                            <label for="login_field" class="form-label">
                                <i class="fas fa-id-badge me-2"></i>Staff ID or Username
                            </label>
                            <input type="text" class="form-control" id="login_field" name="login_field" 
                                   value="<?php echo isset($_POST['login_field']) ? htmlspecialchars($_POST['login_field']) : ''; ?>" 
                                   required placeholder="Enter your Staff ID or Username">
                        </div>
                        <div class="mb-4">
                            <label for="password" class="form-label">
                                <i class="fas fa-lock me-2"></i>Password
                            </label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" name="login" class="btn btn-primary w-100 py-2">
                            <i class="fas fa-sign-in-alt me-2"></i>Login
                        </button>
                    </form>
                    
                    <div class="text-center mt-4">
                        <p class="mb-0">
                            Don't have an account? 
                            <a href="register.php" class="text-primary">Register here</a>
                        </p>
                    </div>
                    
                    <div class="text-center mt-3">
                        <small class="text-muted">
                            Performance Management System<br>
                            Confidential System Access
                        </small>
                    </div>

                    <!-- Login Help -->
                    <div class="mt-4">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>Default Admin Login</h6>
                            <div class="text-center">
                                <strong>Admin:</strong><br>
                                Staff ID: <code style="cursor: pointer;" onclick="quickLogin('ADMIN001', 'admin123')" title="Click to fill">ADMIN001</code><br>
                                Password: <code>admin123</code>
                            </div>
                            <hr>
                            <small class="text-muted">
                                <strong>New Staff:</strong> Please register first and wait for admin approval.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Setup Link -->
    <div class="setup-link">
        <a href="setup.php" class="btn btn-warning btn-sm">
            <i class="fas fa-cogs me-2"></i>Database Setup
        </a>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Focus on login field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('login_field').focus();
        });
        
        // Quick login function for testing
        function quickLogin(staff_id, password) {
            document.getElementById('login_field').value = staff_id;
            document.getElementById('password').value = password;
        }
    </script>
</body>
</html>
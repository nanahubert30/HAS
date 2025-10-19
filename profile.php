<?php
session_start();
require_once 'config/database.php';

checkSession();

$database = new DatabaseConfig();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Get current user data
$query = "SELECT * FROM users WHERE id = :user_id";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: dashboard.php");
    exit();
}

// Handle profile update
if ($_POST && isset($_POST['update_profile'])) {
    try {
        $title = $_POST['title'];
        $first_name = trim($_POST['first_name']);
        $last_name = trim($_POST['last_name']);
        $other_names = trim($_POST['other_names']);
        $job_title = trim($_POST['job_title']);
        $department = $_POST['department'];
        $grade_salary = trim($_POST['grade_salary']);
        $appointment_date = $_POST['appointment_date'];
        
        // Validate required fields
        if (empty($first_name) || empty($last_name)) {
            throw new Exception("First name and last name are required.");
        }
        
        $update_query = "UPDATE users SET 
                        title = :title,
                        first_name = :first_name,
                        last_name = :last_name,
                        other_names = :other_names,
                        job_title = :job_title,
                        department = :department,
                        grade_salary = :grade_salary,
                        appointment_date = :appointment_date,
                        updated_at = CURRENT_TIMESTAMP
                        WHERE id = :user_id";
        
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':title', $title);
        $update_stmt->bindParam(':first_name', $first_name);
        $update_stmt->bindParam(':last_name', $last_name);
        $update_stmt->bindParam(':other_names', $other_names);
        $update_stmt->bindParam(':job_title', $job_title);
        $update_stmt->bindParam(':department', $department);
        $update_stmt->bindParam(':grade_salary', $grade_salary);
        $update_stmt->bindParam(':appointment_date', $appointment_date);
        $update_stmt->bindParam(':user_id', $user_id);
        
        $update_stmt->execute();
        
        // Update session data
        $_SESSION['full_name'] = $first_name . ' ' . $last_name;
        
        $message = "Profile updated successfully!";
        
        // Refresh user data
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Handle password change
if ($_POST && isset($_POST['change_password'])) {
    try {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            throw new Exception("All password fields are required.");
        }
        
        if ($new_password !== $confirm_password) {
            throw new Exception("New passwords do not match.");
        }
        
        if (strlen($new_password) < 6) {
            throw new Exception("New password must be at least 6 characters long.");
        }
        
        // Verify current password
        $check_query = "SELECT password FROM users WHERE id = :user_id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':user_id', $user_id);
        $check_stmt->execute();
        $current_hash = $check_stmt->fetchColumn();
        
        if (md5($current_password) !== $current_hash) {
            throw new Exception("Current password is incorrect.");
        }
        
        // Update password
        $password_query = "UPDATE users SET password = :password WHERE id = :user_id";
        $password_stmt = $db->prepare($password_query);
        $password_stmt->bindParam(':password', md5($new_password));
        $password_stmt->bindParam(':user_id', $user_id);
        $password_stmt->execute();
        
        $message = "Password changed successfully!";
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<?php
$page_title = 'Profile - Hospital Appraisal System';
include 'includes/header.php';
?>

    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-md-3 col-lg-2 px-0">
                <?php include 'sidebar.php'; ?>
            </div>
            <div class="col-md-9 col-lg-10">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-1">My Profile</h1>
                        <p class="text-muted mb-0">Manage your personal information and account settings</p>
                    </div>
                    <a href="dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>

                <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (!empty($message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Profile Overview -->
                <div class="profile-card">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                        </div>
                        <h3><?php echo htmlspecialchars(($user['title'] ?? '') . ' ' . $user['first_name'] . ' ' . $user['last_name']); ?></h3>
                        <p class="mb-0 opacity-75"><?php echo htmlspecialchars($user['job_title'] ?? 'N/A'); ?> - <?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?></p>
                        <p class="mb-0 opacity-75">Staff ID: <?php echo htmlspecialchars($user['staff_id']); ?></p>
                    </div>
                    <div class="p-4">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td class="fw-bold">Username:</td>
                                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Role:</td>
                                        <td><span class="badge bg-primary"><?php echo ucfirst($user['role']); ?></span></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Gender:</td>
                                        <td><?php echo htmlspecialchars($user['gender']); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td class="fw-bold">Grade/Salary:</td>
                                        <td><?php echo htmlspecialchars($user['grade_salary'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Appointment Date:</td>
                                        <td><?php echo $user['appointment_date'] ? formatDate($user['appointment_date']) : 'N/A'; ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Member Since:</td>
                                        <td><?php echo formatDate($user['created_at']); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Update Profile Information -->
                    <div class="col-lg-8 mb-4">
                        <div class="section-card">
                            <div class="section-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-user-edit me-2"></i>Update Profile Information
                                </h5>
                            </div>
                            <div class="p-4">
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-3 mb-3">
                                            <label for="title" class="form-label">Title</label>
                                            <select class="form-select" id="title" name="title">
                                                <option value="">Select...</option>
                                                <option value="Mr." <?php echo ($user['title'] == 'Mr.') ? 'selected' : ''; ?>>Mr.</option>
                                                <option value="Mrs." <?php echo ($user['title'] == 'Mrs.') ? 'selected' : ''; ?>>Mrs.</option>
                                                <option value="Ms." <?php echo ($user['title'] == 'Ms.') ? 'selected' : ''; ?>>Ms.</option>
                                                <option value="Dr." <?php echo ($user['title'] == 'Dr.') ? 'selected' : ''; ?>>Dr.</option>
                                            </select>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="first_name" class="form-label">First Name *</label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                                   value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                                        </div>
                                        <div class="col-md-5 mb-3">
                                            <label for="last_name" class="form-label">Last Name *</label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                                   value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="other_names" class="form-label">Other Names</label>
                                        <input type="text" class="form-control" id="other_names" name="other_names" 
                                               value="<?php echo htmlspecialchars($user['other_names'] ?? ''); ?>">
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="job_title" class="form-label">Job Title</label>
                                            <input type="text" class="form-control" id="job_title" name="job_title" 
                                                   value="<?php echo htmlspecialchars($user['job_title'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="department" class="form-label">Department</label>
                                            <select class="form-select" id="department" name="department">
                                                <option value="">Select Department</option>
                                                <option value="Medical Department" <?php echo ($user['department'] == 'Medical Department') ? 'selected' : ''; ?>>Medical Department</option>
                                                <option value="Nursing Department" <?php echo ($user['department'] == 'Nursing Department') ? 'selected' : ''; ?>>Nursing Department</option>
                                                <option value="Emergency Department" <?php echo ($user['department'] == 'Emergency Department') ? 'selected' : ''; ?>>Emergency Department</option>
                                                <option value="Laboratory" <?php echo ($user['department'] == 'Laboratory') ? 'selected' : ''; ?>>Laboratory</option>
                                                <option value="Administration" <?php echo ($user['department'] == 'Administration') ? 'selected' : ''; ?>>Administration</option>
                                                <option value="IT Department" <?php echo ($user['department'] == 'IT Department') ? 'selected' : ''; ?>>IT Department</option>
                                                <option value="Pharmacy" <?php echo ($user['department'] == 'Pharmacy') ? 'selected' : ''; ?>>Pharmacy</option>
                                                <option value="Radiology" <?php echo ($user['department'] == 'Radiology') ? 'selected' : ''; ?>>Radiology</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label for="grade_salary" class="form-label">Grade/Salary</label>
                                            <input type="text" class="form-control" id="grade_salary" name="grade_salary" 
                                                   value="<?php echo htmlspecialchars($user['grade_salary'] ?? ''); ?>" 
                                                   placeholder="e.g., Grade C - 4500">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="appointment_date" class="form-label">Appointment Date</label>
                                            <input type="date" class="form-control" id="appointment_date" name="appointment_date" 
                                                   value="<?php echo $user['appointment_date']; ?>">
                                        </div>
                                    </div>

                                    <button type="submit" name="update_profile" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Update Profile
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Change Password -->
                    <div class="col-lg-4 mb-4">
                        <div class="section-card">
                            <div class="section-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-lock me-2"></i>Change Password
                                </h5>
                            </div>
                            <div class="p-4">
                                <form method="POST" id="passwordForm">
                                    <div class="mb-3">
                                        <label for="current_password" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                                        <small class="text-muted">Minimum 6 characters</small>
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                                    </div>
                                    <button type="submit" name="change_password" class="btn btn-warning w-100">
                                        <i class="fas fa-key me-2"></i>Change Password
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Account Information -->
                        <div class="section-card">
                            <div class="section-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-info-circle me-2"></i>Account Information
                                </h5>
                            </div>
                            <div class="p-4">
                                <div class="mb-3">
                                    <small class="text-muted">Staff ID</small>
                                    <div class="fw-bold"><?php echo htmlspecialchars($user['staff_id']); ?></div>
                                </div>
                                <div class="mb-3">
                                    <small class="text-muted">Username</small>
                                    <div class="fw-bold"><?php echo htmlspecialchars($user['username']); ?></div>
                                </div>
                                <div class="mb-3">
                                    <small class="text-muted">Account Status</small>
                                    <div>
                                        <?php if ($user['is_approved']): ?>
                                            <span class="badge bg-success">Approved</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Pending Approval</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div>
                                    <small class="text-muted">Last Updated</small>
                                    <div class="fw-bold"><?php echo formatDate($user['updated_at']); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        // Form validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New passwords do not match.');
                return false;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('New password must be at least 6 characters long.');
                return false;
            }
        });
    </script>
<?php include 'includes/footer.php'; ?>
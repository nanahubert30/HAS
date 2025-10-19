<?php
session_start();
require_once 'config/database.php';

checkSession();

// Only admins can access this page
if ($_SESSION['role'] != 'admin') {
    header("Location: dashboard.php");
    exit();
}

$database = new DatabaseConfig();
$db = $database->getConnection();

$message = '';
$error = '';

// Handle approve/reject/delete actions
if ($_POST) {
    try {
        if (isset($_POST['approve_user'])) {
            $user_id = $_POST['user_id'];
            $query = "UPDATE users SET is_approved = TRUE WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $message = "User approved successfully!";
            
        } elseif (isset($_POST['reject_user'])) {
            $user_id = $_POST['user_id'];
            $query = "UPDATE users SET is_approved = FALSE WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $message = "User access revoked successfully!";
            
        } elseif (isset($_POST['delete_user'])) {
            $user_id = $_POST['user_id'];
            // Don't allow deleting admin users
            $check_query = "SELECT role FROM users WHERE id = :user_id";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':user_id', $user_id);
            $check_stmt->execute();
            $user = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && $user['role'] != 'admin') {
                $query = "DELETE FROM users WHERE id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->execute();
                $message = "User deleted successfully!";
            } else {
                $error = "Cannot delete admin users!";
            }
            
        } elseif (isset($_POST['change_role'])) {
            $user_id = $_POST['user_id'];
            $new_role = $_POST['new_role'];
            $query = "UPDATE users SET role = :new_role WHERE id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':new_role', $new_role);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->execute();
            $message = "User role updated successfully!";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get all users
$query = "SELECT * FROM users ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN is_approved = TRUE THEN 1 ELSE 0 END) as approved_users,
    SUM(CASE WHEN is_approved = FALSE THEN 1 ELSE 0 END) as pending_users,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_users,
    SUM(CASE WHEN role = 'appraiser' THEN 1 ELSE 0 END) as appraiser_users,
    SUM(CASE WHEN role = 'appraisee' THEN 1 ELSE 0 END) as appraisee_users
FROM users";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<?php
$page_title = 'Manage Users - Hospital Appraisal System';
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
                        <h1 class="h3 mb-1">Manage Users</h1>
                        <p class="text-muted mb-0">Approve, manage, and monitor system users</p>
                    </div>
                    <div>
                        <a href="register.php" class="btn btn-success me-2">
                            <i class="fas fa-user-plus me-2"></i>Add New User
                        </a>
                        <a href="dashboard.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
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

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                        <div class="stat-card p-3">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="ms-3">
                                    <h4 class="mb-0"><?php echo $stats['total_users']; ?></h4>
                                    <small class="text-muted">Total Users</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                        <div class="stat-card p-3">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="ms-3">
                                    <h4 class="mb-0"><?php echo $stats['approved_users']; ?></h4>
                                    <small class="text-muted">Approved</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                        <div class="stat-card p-3">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="ms-3">
                                    <h4 class="mb-0"><?php echo $stats['pending_users']; ?></h4>
                                    <small class="text-muted">Pending</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                        <div class="stat-card p-3">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
                                    <i class="fas fa-user-shield"></i>
                                </div>
                                <div class="ms-3">
                                    <h4 class="mb-0"><?php echo $stats['admin_users']; ?></h4>
                                    <small class="text-muted">Admins</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                        <div class="stat-card p-3">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);">
                                    <i class="fas fa-user-tie"></i>
                                </div>
                                <div class="ms-3">
                                    <h4 class="mb-0"><?php echo $stats['appraiser_users']; ?></h4>
                                    <small class="text-muted">Appraisers</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                        <div class="stat-card p-3">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <i class="fas fa-user"></i>
                                </div>
                                <div class="ms-3">
                                    <h4 class="mb-0"><?php echo $stats['appraisee_users']; ?></h4>
                                    <small class="text-muted">Appraisees</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="table-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-users me-2"></i>All Users
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>User</th>
                                        <th>Staff ID</th>
                                        <th>Role</th>
                                        <th>Department</th>
                                        <th>Status</th>
                                        <th>Registered</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="user-avatar me-3">
                                                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <strong><?php echo htmlspecialchars(($user['title'] ?? '') . ' ' . $user['first_name'] . ' ' . $user['last_name']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($user['job_title'] ?? 'N/A'); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($user['staff_id'] ?? 'N/A'); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($user['username']); ?></small>
                                        </td>
                                        <td>
                                            <?php
                                            $role_colors = [
                                                'admin' => 'bg-danger',
                                                'appraiser' => 'bg-warning',
                                                'appraisee' => 'bg-info'
                                            ];
                                            $role_color = $role_colors[$user['role']] ?? 'bg-secondary';
                                            ?>
                                            <span class="badge <?php echo $role_color; ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['department'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php if ($user['is_approved'] || $user['role'] == 'admin'): ?>
                                                <span class="badge bg-success">Approved</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                    Actions
                                                </button>
                                                <ul class="dropdown-menu">
                                                    <?php if (!$user['is_approved'] && $user['role'] != 'admin'): ?>
                                                    <li>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <button type="submit" name="approve_user" class="dropdown-item text-success">
                                                                <i class="fas fa-check me-2"></i>Approve
                                                            </button>
                                                        </form>
                                                    </li>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($user['is_approved'] && $user['role'] != 'admin'): ?>
                                                    <li>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <button type="submit" name="reject_user" class="dropdown-item text-warning">
                                                                <i class="fas fa-ban me-2"></i>Revoke Access
                                                            </button>
                                                        </form>
                                                    </li>
                                                    <?php endif; ?>
                                                    
                                                    <li>
                                                        <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#roleModal<?php echo $user['id']; ?>">
                                                            <i class="fas fa-user-cog me-2"></i>Change Role
                                                        </button>
                                                    </li>
                                                    
                                                    <?php if ($user['role'] != 'admin'): ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?')">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <button type="submit" name="delete_user" class="dropdown-item text-danger">
                                                                <i class="fas fa-trash me-2"></i>Delete
                                                            </button>
                                                        </form>
                                                    </li>
                                                    <?php endif; ?>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>

                                    <!-- Role Change Modal -->
                                    <div class="modal fade" id="roleModal<?php echo $user['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Change Role for <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST">
                                                    <div class="modal-body">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <div class="mb-3">
                                                            <label for="new_role<?php echo $user['id']; ?>" class="form-label">Select New Role</label>
                                                            <select class="form-select" id="new_role<?php echo $user['id']; ?>" name="new_role" required>
                                                                <option value="appraisee" <?php echo $user['role'] == 'appraisee' ? 'selected' : ''; ?>>Appraisee</option>
                                                                <option value="appraiser" <?php echo $user['role'] == 'appraiser' ? 'selected' : ''; ?>>Appraiser</option>
                                                                <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="change_role" class="btn btn-primary">Change Role</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php include 'includes/footer.php'; ?>
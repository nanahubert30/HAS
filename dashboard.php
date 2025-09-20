<?php
session_start();

// Include the database configuration from index.php
require_once 'config/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$database = new DatabaseConfig();
$db = $database->getConnection();

// Get user information
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get dashboard statistics
function getDashboardStats($db, $user_id, $role) {
    $stats = [];
    
    if ($role == 'admin') {
        // Admin statistics
        $query = "SELECT COUNT(*) as total_users FROM users WHERE role != 'admin'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_users'];
        
        $query = "SELECT COUNT(*) as total_appraisals FROM appraisals";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $stats['total_appraisals'] = $stmt->fetch(PDO::FETCH_ASSOC)['total_appraisals'];
        
        $query = "SELECT COUNT(*) as pending_reviews FROM appraisals WHERE status IN ('draft', 'planning', 'mid_review')";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $stats['pending_reviews'] = $stmt->fetch(PDO::FETCH_ASSOC)['pending_reviews'];
        
        $query = "SELECT COUNT(*) as completed_appraisals FROM appraisals WHERE status = 'completed'";
        $stmt = $db->prepare($query);
        $stmt->execute();
        $stats['completed_appraisals'] = $stmt->fetch(PDO::FETCH_ASSOC)['completed_appraisals'];
        
    } elseif ($role == 'appraiser') {
        // Appraiser statistics
        $query = "SELECT COUNT(*) as my_appraisals FROM appraisals WHERE appraiser_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $stats['my_appraisals'] = $stmt->fetch(PDO::FETCH_ASSOC)['my_appraisals'];
        
        $query = "SELECT COUNT(*) as pending_reviews FROM appraisals WHERE appraiser_id = :user_id AND status IN ('draft', 'planning', 'mid_review')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $stats['pending_reviews'] = $stmt->fetch(PDO::FETCH_ASSOC)['pending_reviews'];
        
    } else {
        // Appraisee statistics
        $query = "SELECT COUNT(*) as my_appraisals FROM appraisals WHERE appraisee_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $stats['my_appraisals'] = $stmt->fetch(PDO::FETCH_ASSOC)['my_appraisals'];
        
        $query = "SELECT * FROM appraisals WHERE appraisee_id = :user_id ORDER BY created_at DESC LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $stats['latest_appraisal'] = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    return $stats;
}

$stats = getDashboardStats($db, $user_id, $role);

// Get recent appraisals
function getRecentAppraisals($db, $user_id, $role) {
    if ($role == 'admin') {
        $query = "SELECT a.*, 
                         u1.first_name as appraisee_fname, u1.last_name as appraisee_lname,
                         u2.first_name as appraiser_fname, u2.last_name as appraiser_lname
                  FROM appraisals a 
                  JOIN users u1 ON a.appraisee_id = u1.id 
                  JOIN users u2 ON a.appraiser_id = u2.id 
                  ORDER BY a.created_at DESC LIMIT 5";
        $stmt = $db->prepare($query);
    } elseif ($role == 'appraiser') {
        $query = "SELECT a.*, 
                         u1.first_name as appraisee_fname, u1.last_name as appraisee_lname,
                         u2.first_name as appraiser_fname, u2.last_name as appraiser_lname
                  FROM appraisals a 
                  JOIN users u1 ON a.appraisee_id = u1.id 
                  JOIN users u2 ON a.appraiser_id = u2.id 
                  WHERE a.appraiser_id = :user_id
                  ORDER BY a.created_at DESC LIMIT 5";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
    } else {
        $query = "SELECT a.*, 
                         u1.first_name as appraisee_fname, u1.last_name as appraisee_lname,
                         u2.first_name as appraiser_fname, u2.last_name as appraiser_lname
                  FROM appraisals a 
                  JOIN users u1 ON a.appraisee_id = u1.id 
                  JOIN users u2 ON a.appraiser_id = u2.id 
                  WHERE a.appraisee_id = :user_id
                  ORDER BY a.created_at DESC LIMIT 5";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
    }
    
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$recent_appraisals = getRecentAppraisals($db, $user_id, $role);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Hospital Appraisal System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .nav-link {
            color: rgba(255,255,255,0.8) !important;
            border-radius: 8px;
            margin: 2px 0;
            transition: all 0.3s ease;
        }
        .nav-link:hover, .nav-link.active {
            background: rgba(255,255,255,0.2) !important;
            color: white !important;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }
        .main-content {
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
        }
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 px-0">
                <div class="sidebar p-3">
                    <div class="text-center mb-4">
                        <i class="fas fa-hospital fa-2x mb-2"></i>
                        <h5>St. Mary's Hospital</h5>
                        <small>Appraisal System</small>
                    </div>
                    
                    <div class="text-center mb-4">
                        <div class="user-avatar mx-auto mb-2">
                            <?php echo strtoupper(substr($_SESSION['full_name'], 0, 2)); ?>
                        </div>
                        <div>
                            <strong><?php echo htmlspecialchars($_SESSION['full_name']); ?></strong>
                            <br><small><?php echo ucfirst($role); ?></small>
                        </div>
                    </div>

                    <nav class="nav flex-column">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                        
                        <?php if ($role == 'admin'): ?>
                        <a class="nav-link" href="manage_users.php">
                            <i class="fas fa-users me-2"></i> Manage Users
                        </a>
                        <a class="nav-link" href="view_all_appraisals.php">
                            <i class="fas fa-clipboard-list me-2"></i> All Appraisals
                        </a>
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar me-2"></i> System Reports
                        </a>
                        <?php elseif ($role == 'appraiser'): ?>
                        <a class="nav-link" href="my_appraisals.php">
                            <i class="fas fa-clipboard-check me-2"></i> My Appraisals
                        </a>
                        <a class="nav-link" href="create_appraisal.php">
                            <i class="fas fa-plus me-2"></i> New Appraisal
                        </a>
                        <?php else: ?>
                        <a class="nav-link" href="my_appraisals.php">
                            <i class="fas fa-user-edit me-2"></i> My Appraisals
                        </a>
                        <?php endif; ?>
                        
                        <a class="nav-link" href="performance_planning.php">
                            <i class="fas fa-bullseye me-2"></i> Performance Planning
                        </a>
                        <a class="nav-link" href="reports.php">
                            <i class="fas fa-chart-bar me-2"></i> Reports
                        </a>
                        <hr class="text-white-50">
                        <a class="nav-link" href="profile.php">
                            <i class="fas fa-user me-2"></i> Profile
                        </a>
                        <a class="nav-link" href="index.php?logout=1">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a>
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-9 col-lg-10 px-0">
                <div class="main-content p-4">
                    <!-- Header -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3">Dashboard</h1>
                        <div class="text-muted">
                            <i class="fas fa-calendar me-2"></i>
                            <?php echo date('l, F j, Y'); ?>
                        </div>
                    </div>

                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <?php if ($role == 'admin'): ?>
                            <div class="col-lg-3 col-md-6 mb-3">
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
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="stat-card p-3">
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                                            <i class="fas fa-clipboard-list"></i>
                                        </div>
                                        <div class="ms-3">
                                            <h4 class="mb-0"><?php echo $stats['total_appraisals']; ?></h4>
                                            <small class="text-muted">Total Appraisals</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="stat-card p-3">
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon" style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <div class="ms-3">
                                            <h4 class="mb-0"><?php echo $stats['pending_reviews']; ?></h4>
                                            <small class="text-muted">Pending Reviews</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3">
                                <div class="stat-card p-3">
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon" style="background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);">
                                            <i class="fas fa-check-circle"></i>
                                        </div>
                                        <div class="ms-3">
                                            <h4 class="mb-0"><?php echo $stats['completed_appraisals']; ?></h4>
                                            <small class="text-muted">Completed</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($role == 'appraiser'): ?>
                            <div class="col-lg-4 col-md-6 mb-3">
                                <div class="stat-card p-3">
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                            <i class="fas fa-clipboard-check"></i>
                                        </div>
                                        <div class="ms-3">
                                            <h4 class="mb-0"><?php echo $stats['my_appraisals']; ?></h4>
                                            <small class="text-muted">My Appraisals</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-4 col-md-6 mb-3">
                                <div class="stat-card p-3">
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon" style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);">
                                            <i class="fas fa-clock"></i>
                                        </div>
                                        <div class="ms-3">
                                            <h4 class="mb-0"><?php echo $stats['pending_reviews']; ?></h4>
                                            <small class="text-muted">Pending Reviews</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="col-lg-6 col-md-12 mb-3">
                                <div class="stat-card p-3">
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                            <i class="fas fa-user-edit"></i>
                                        </div>
                                        <div class="ms-3">
                                            <h4 class="mb-0"><?php echo $stats['my_appraisals']; ?></h4>
                                            <small class="text-muted">My Appraisals</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6 col-md-12 mb-3">
                                <div class="stat-card p-3">
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                                            <i class="fas fa-chart-line"></i>
                                        </div>
                                        <div class="ms-3">
                                            <h4 class="mb-0">
                                                <?php echo $stats['latest_appraisal'] ? ucfirst($stats['latest_appraisal']['status']) : 'None'; ?>
                                            </h4>
                                            <small class="text-muted">Latest Status</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Recent Appraisals -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-history me-2"></i>Recent Appraisals
                            </h5>
                            <?php if ($role == 'appraiser'): ?>
                            <a href="create_appraisal.php" class="btn btn-primary btn-sm">
                                <i class="fas fa-plus me-2"></i>New Appraisal
                            </a>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if (empty($recent_appraisals)): ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No appraisals found</h5>
                                    <p class="text-muted">
                                        <?php if ($role == 'appraiser'): ?>
                                            Start by creating your first appraisal.
                                        <?php else: ?>
                                            Contact your supervisor to begin the appraisal process.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Appraisee</th>
                                                <th>Appraiser</th>
                                                <th>Period</th>
                                                <th>Status</th>
                                                <th>Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($recent_appraisals as $appraisal): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <div class="user-avatar me-2" style="width: 32px; height: 32px;">
                                                            <?php echo strtoupper(substr($appraisal['appraisee_fname'], 0, 1) . substr($appraisal['appraisee_lname'], 0, 1)); ?>
                                                        </div>
                                                        <?php echo htmlspecialchars($appraisal['appraisee_fname'] . ' ' . $appraisal['appraisee_lname']); ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($appraisal['appraiser_fname'] . ' ' . $appraisal['appraiser_lname']); ?>
                                                </td>
                                                <td>
                                                    <?php echo date('M Y', strtotime($appraisal['period_from'])) . ' - ' . date('M Y', strtotime($appraisal['period_to'])); ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_class = '';
                                                    $status_text = ucfirst(str_replace('_', ' ', $appraisal['status']));
                                                    
                                                    switch($appraisal['status']) {
                                                        case 'draft':
                                                            $status_class = 'bg-secondary';
                                                            break;
                                                        case 'planning':
                                                            $status_class = 'bg-info';
                                                            break;
                                                        case 'mid_review':
                                                            $status_class = 'bg-warning';
                                                            break;
                                                        case 'final_review':
                                                            $status_class = 'bg-primary';
                                                            break;
                                                        case 'completed':
                                                            $status_class = 'bg-success';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge status-badge <?php echo $status_class; ?>">
                                                        <?php echo $status_text; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y', strtotime($appraisal['created_at'])); ?></td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <a href="view_appraisal.php?id=<?php echo $appraisal['id']; ?>" 
                                                           class="btn btn-outline-primary btn-sm" title="View">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if ($role == 'appraiser' && in_array($appraisal['status'], ['draft', 'planning', 'mid_review'])): ?>
                                                        <a href="edit_appraisal.php?id=<?php echo $appraisal['id']; ?>" 
                                                           class="btn btn-outline-success btn-sm" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-rocket me-2"></i>Quick Actions
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <?php if ($role == 'admin'): ?>
                                            <a href="manage_users.php" class="btn btn-outline-primary">
                                                <i class="fas fa-user-plus me-2"></i>Add New User
                                            </a>
                                            <a href="view_all_appraisals.php" class="btn btn-outline-info">
                                                <i class="fas fa-chart-bar me-2"></i>View All Reports
                                            </a>
                                        <?php elseif ($role == 'appraiser'): ?>
                                            <a href="create_appraisal.php" class="btn btn-outline-primary">
                                                <i class="fas fa-plus me-2"></i>Create New Appraisal
                                            </a>
                                            <a href="performance_planning.php" class="btn btn-outline-success">
                                                <i class="fas fa-bullseye me-2"></i>Performance Planning
                                            </a>
                                        <?php else: ?>
                                            <a href="my_appraisals.php" class="btn btn-outline-primary">
                                                <i class="fas fa-eye me-2"></i>View My Progress
                                            </a>
                                            <a href="profile.php" class="btn btn-outline-info">
                                                <i class="fas fa-user me-2"></i>Update Profile
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-info-circle me-2"></i>System Information
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="mb-2">
                                        <strong>Performance Management Cycle:</strong>
                                    </p>
                                    <ul class="list-unstyled mb-0">
                                        <li><i class="fas fa-check text-success me-2"></i>Phase 1: Performance Planning</li>
                                        <li><i class="fas fa-check text-warning me-2"></i>Phase 2: Progress Reviews (July)</li>
                                        <li><i class="fas fa-check text-info me-2"></i>Phase 3: Review & Appraisal (Dec)</li>
                                        <li><i class="fas fa-check text-primary me-2"></i>Phase 4: Decision Making</li>
                                    </ul>
                                    <hr>
                                    <small class="text-muted">
                                        <i class="fas fa-shield-alt me-1"></i>
                                        This system maintains strict confidentiality in accordance with hospital policies.
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add some interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to stat cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Auto-refresh dashboard every 5 minutes
            setTimeout(function() {
                location.reload();
            }, 300000);
        });
    </script>
</body>
</html>
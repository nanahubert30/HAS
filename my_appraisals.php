<?php
session_start();
require_once 'config/database.php';

checkSession();

$database = new DatabaseConfig();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get user's appraisals based on role
if ($role == 'appraiser') {
    $query = "SELECT a.*, 
                     u1.title as appraisee_title, u1.first_name as appraisee_fname, u1.last_name as appraisee_lname,
                     u1.job_title as appraisee_job, u1.department as appraisee_dept
              FROM appraisals a 
              JOIN users u1 ON a.appraisee_id = u1.id 
              WHERE a.appraiser_id = :user_id
              ORDER BY a.created_at DESC";
} else {
    $query = "SELECT a.*, 
                     u2.title as appraiser_title, u2.first_name as appraiser_fname, u2.last_name as appraiser_lname,
                     u2.job_title as appraiser_job
              FROM appraisals a 
              JOIN users u2 ON a.appraiser_id = u2.id 
              WHERE a.appraisee_id = :user_id
              ORDER BY a.created_at DESC";
}

$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$appraisals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user statistics
if ($role == 'appraiser') {
    $stats_query = "SELECT 
        COUNT(*) as total_appraisals,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status IN ('draft', 'planning', 'mid_review', 'final_review') THEN 1 ELSE 0 END) as pending
    FROM appraisals WHERE appraiser_id = :user_id";
} else {
    $stats_query = "SELECT 
        COUNT(*) as total_appraisals,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
        SUM(CASE WHEN status IN ('draft', 'planning', 'mid_review', 'final_review') THEN 1 ELSE 0 END) as pending,
        AVG(CASE WHEN oa.overall_percentage IS NOT NULL THEN oa.overall_percentage END) as avg_performance
    FROM appraisals a
    LEFT JOIN overall_assessments oa ON a.id = oa.appraisal_id
    WHERE a.appraisee_id = :user_id";
}

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->bindParam(':user_id', $user_id);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<?php
$page_title = 'My Appraisals - Hospital Appraisal System';
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
                        <h1 class="h3 mb-1">My Appraisals</h1>
                        <p class="text-muted mb-0">
                            <?php if ($role == 'appraiser'): ?>
                                Appraisals you are conducting as an appraiser
                            <?php else: ?>
                                Your performance appraisals and reviews
                            <?php endif; ?>
                        </p>
                    </div>
                    <div>
                        <?php if ($role == 'appraiser'): ?>
                        <a href="create_appraisal.php" class="btn btn-success me-2">
                            <i class="fas fa-plus me-2"></i>New Appraisal
                        </a>
                        <?php endif; ?>
                        <a href="dashboard.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stat-card p-3">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <i class="fas fa-clipboard-list"></i>
                                </div>
                                <div class="ms-3">
                                    <h4 class="mb-0"><?php echo $stats['total_appraisals'] ?? 0; ?></h4>
                                    <small class="text-muted">Total Appraisals</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stat-card p-3">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #28a745 0%, #20c997 100%);">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="ms-3">
                                    <h4 class="mb-0"><?php echo $stats['completed'] ?? 0; ?></h4>
                                    <small class="text-muted">Completed</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stat-card p-3">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="ms-3">
                                    <h4 class="mb-0"><?php echo $stats['pending'] ?? 0; ?></h4>
                                    <small class="text-muted">In Progress</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if ($role == 'appraisee' && isset($stats['avg_performance'])): ?>
                    <div class="col-lg-3 col-md-6 mb-3">
                        <div class="stat-card p-3">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="ms-3">
                                    <h4 class="mb-0"><?php echo $stats['avg_performance'] ? number_format($stats['avg_performance'], 1) . '%' : 'N/A'; ?></h4>
                                    <small class="text-muted">Avg Performance</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Appraisals List -->
                <div class="row">
                    <div class="col-12">
                        <?php if (empty($appraisals)): ?>
                            <div class="appraisal-card text-center py-5">
                                <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
                                <h4 class="text-muted">No Appraisals Found</h4>
                                <p class="text-muted mb-4">
                                    <?php if ($role == 'appraiser'): ?>
                                        You haven't created any appraisals yet. Start by creating your first appraisal.
                                    <?php else: ?>
                                        No appraisals have been created for you yet. Contact your supervisor if you think this is an error.
                                    <?php endif; ?>
                                </p>
                                <?php if ($role == 'appraiser'): ?>
                                <a href="create_appraisal.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Create Your First Appraisal
                                </a>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <?php foreach ($appraisals as $appraisal): ?>
                            <div class="appraisal-card">
                                <div class="card-body p-4">
                                    <div class="row align-items-center">
                                        <div class="col-md-6">
                                            <div class="d-flex align-items-center mb-2">
                                                <?php if ($role == 'appraiser'): ?>
                                                    <div class="user-avatar me-3">
                                                        <?php echo strtoupper(substr($appraisal['appraisee_fname'], 0, 1) . substr($appraisal['appraisee_lname'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <h5 class="mb-1"><?php echo htmlspecialchars(($appraisal['appraisee_title'] ?? '') . ' ' . $appraisal['appraisee_fname'] . ' ' . $appraisal['appraisee_lname']); ?></h5>
                                                        <small class="text-muted"><?php echo htmlspecialchars($appraisal['appraisee_job'] ?? 'N/A'); ?> - <?php echo htmlspecialchars($appraisal['appraisee_dept'] ?? 'N/A'); ?></small>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="user-avatar me-3">
                                                        <?php echo strtoupper(substr($appraisal['appraiser_fname'], 0, 1) . substr($appraisal['appraiser_lname'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <h5 class="mb-1">Appraised by: <?php echo htmlspecialchars(($appraisal['appraiser_title'] ?? '') . ' ' . $appraisal['appraiser_fname'] . ' ' . $appraisal['appraiser_lname']); ?></h5>
                                                        <small class="text-muted"><?php echo htmlspecialchars($appraisal['appraiser_job'] ?? 'N/A'); ?></small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <strong class="d-block">Appraisal Period</strong>
                                                <span class="text-muted">
                                                    <?php echo date('M j, Y', strtotime($appraisal['period_from'])); ?><br>
                                                    to<br>
                                                    <?php echo date('M j, Y', strtotime($appraisal['period_to'])); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="col-md-3">
                                            <div class="text-center">
                                                <div class="mb-2">
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
                                                </div>
                                                <small class="text-muted d-block">
                                                    Created: <?php echo date('M j, Y', strtotime($appraisal['created_at'])); ?>
                                                </small>
                                                <div class="btn-group mt-2" role="group">
                                                    <a href="view_appraisal.php?id=<?php echo $appraisal['id']; ?>" 
                                                       class="btn btn-outline-primary btn-sm">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                    <?php if ($role == 'appraiser' && in_array($appraisal['status'], ['draft', 'planning', 'mid_review'])): ?>
                                                    <a href="edit_appraisal.php?id=<?php echo $appraisal['id']; ?>" 
                                                       class="btn btn-outline-success btn-sm">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php include 'includes/footer.php'; ?>
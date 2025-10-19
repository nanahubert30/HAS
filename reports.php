<?php
session_start();
require_once 'config/database.php';

checkSession();

$database = new DatabaseConfig();
$db = $database->getConnection();
$user_role = $_SESSION['role'];

// Get comprehensive statistics
$stats = [];

// General statistics
$general_query = "SELECT 
    COUNT(DISTINCT u.id) as total_staff,
    COUNT(DISTINCT a.id) as total_appraisals,
    AVG(CASE WHEN oa.overall_percentage IS NOT NULL THEN oa.overall_percentage END) as avg_performance
FROM users u
LEFT JOIN appraisals a ON u.id = a.appraisee_id
LEFT JOIN overall_assessments oa ON a.id = oa.appraisal_id
WHERE u.role = 'appraisee'";

$general_stmt = $db->prepare($general_query);
$general_stmt->execute();
$general_stats = $general_stmt->fetch(PDO::FETCH_ASSOC);

// Department performance
$dept_query = "SELECT 
    u.department,
    COUNT(DISTINCT u.id) as staff_count,
    COUNT(DISTINCT a.id) as appraisal_count,
    AVG(CASE WHEN oa.overall_percentage IS NOT NULL THEN oa.overall_percentage END) as avg_performance
FROM users u
LEFT JOIN appraisals a ON u.id = a.appraisee_id
LEFT JOIN overall_assessments oa ON a.id = oa.appraisal_id
WHERE u.role = 'appraisee' AND u.department IS NOT NULL
GROUP BY u.department
ORDER BY avg_performance DESC";

$dept_stmt = $db->prepare($dept_query);
$dept_stmt->execute();
$dept_stats = $dept_stmt->fetchAll(PDO::FETCH_ASSOC);

// Appraisal status distribution
$status_query = "SELECT 
    status,
    COUNT(*) as count,
    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM appraisals), 2) as percentage
FROM appraisals
GROUP BY status
ORDER BY count DESC";

$status_stmt = $db->prepare($status_query);
$status_stmt->execute();
$status_stats = $status_stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent activity
$activity_query = "SELECT 
    a.id,
    a.status,
    a.updated_at,
    u1.first_name as appraisee_fname,
    u1.last_name as appraisee_lname,
    u2.first_name as appraiser_fname,
    u2.last_name as appraiser_lname
FROM appraisals a
JOIN users u1 ON a.appraisee_id = u1.id
JOIN users u2 ON a.appraiser_id = u2.id
ORDER BY a.updated_at DESC
LIMIT 10";

if ($user_role != 'admin') {
    $activity_query = "SELECT 
        a.id,
        a.status,
        a.updated_at,
        u1.first_name as appraisee_fname,
        u1.last_name as appraisee_lname,
        u2.first_name as appraiser_fname,
        u2.last_name as appraiser_lname
    FROM appraisals a
    JOIN users u1 ON a.appraisee_id = u1.id
    JOIN users u2 ON a.appraiser_id = u2.id
    WHERE a.appraiser_id = :user_id OR a.appraisee_id = :user_id
    ORDER BY a.updated_at DESC
    LIMIT 10";
}

$activity_stmt = $db->prepare($activity_query);
if ($user_role != 'admin') {
    $activity_stmt->bindParam(':user_id', $_SESSION['user_id']);
}
$activity_stmt->execute();
$recent_activity = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);

// Performance ratings distribution
$rating_query = "SELECT 
    overall_rating,
    COUNT(*) as count,
    ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM overall_assessments WHERE overall_rating IS NOT NULL), 2) as percentage
FROM overall_assessments
WHERE overall_rating IS NOT NULL
GROUP BY overall_rating
ORDER BY overall_rating";

$rating_stmt = $db->prepare($rating_query);
$rating_stmt->execute();
$rating_stats = $rating_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php
$page_title = 'Reports - Hospital Appraisal System';
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
                        <h1 class="h3 mb-1">System Reports</h1>
                        <p class="text-muted mb-0">Performance analytics and system overview</p>
                    </div>
                    <div>
                        <button onclick="window.print()" class="btn btn-success me-2">
                            <i class="fas fa-print me-2"></i>Print Report
                        </button>
                        <a href="dashboard.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>

                <!-- Summary Statistics -->
                <div class="row mb-4">
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="stat-card p-3">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="ms-3">
                                    <h4 class="mb-0"><?php echo $general_stats['total_staff'] ?? 0; ?></h4>
                                    <small class="text-muted">Total Staff</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="stat-card p-3">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                                    <i class="fas fa-clipboard-list"></i>
                                </div>
                                <div class="ms-3">
                                    <h4 class="mb-0"><?php echo $general_stats['total_appraisals'] ?? 0; ?></h4>
                                    <small class="text-muted">Total Appraisals</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="stat-card p-3">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="ms-3">
                                    <h4 class="mb-0"><?php echo $general_stats['avg_performance'] ? number_format($general_stats['avg_performance'], 1) . '%' : 'N/A'; ?></h4>
                                    <small class="text-muted">Avg Performance</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Appraisal Status Distribution -->
                    <div class="col-lg-6 mb-4">
                        <div class="report-card">
                            <div class="report-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-pie me-2"></i>Appraisal Status Distribution
                                </h5>
                            </div>
                            <div class="p-4">
                                <?php if (!empty($status_stats)): ?>
                                    <?php foreach ($status_stats as $status): ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <span class="fw-bold"><?php echo ucfirst(str_replace('_', ' ', $status['status'])); ?></span>
                                            <span class="badge bg-primary"><?php echo $status['count']; ?> (<?php echo $status['percentage']; ?>%)</span>
                                        </div>
                                        <div class="progress progress-custom">
                                            <div class="progress-bar" role="progressbar" style="width: <?php echo $status['percentage']; ?>%"></div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center">No appraisal data available</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Performance Ratings -->
                    <div class="col-lg-6 mb-4">
                        <div class="report-card">
                            <div class="report-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-star me-2"></i>Performance Ratings Distribution
                                </h5>
                            </div>
                            <div class="p-4">
                                <?php if (!empty($rating_stats)): ?>
                                    <?php 
                                    $rating_labels = [
                                        1 => 'Unacceptable',
                                        2 => 'Below Expectation',
                                        3 => 'Meets Expectations',
                                        4 => 'Exceeds Expectations',
                                        5 => 'Exceptional'
                                    ];
                                    ?>
                                    <?php foreach ($rating_stats as $rating): ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <div class="d-flex align-items-center">
                                                <span class="rating-badge rating-<?php echo $rating['overall_rating']; ?> me-2">
                                                    <?php echo $rating['overall_rating']; ?>
                                                </span>
                                                <span><?php echo $rating_labels[$rating['overall_rating']] ?? 'Unknown'; ?></span>
                                            </div>
                                            <span class="badge bg-secondary"><?php echo $rating['count']; ?> (<?php echo $rating['percentage']; ?>%)</span>
                                        </div>
                                        <div class="progress progress-custom">
                                            <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $rating['percentage']; ?>%"></div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <p class="text-muted text-center">No rating data available</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Department Performance -->
                <?php if ($user_role == 'admin' && !empty($dept_stats)): ?>
                <div class="row">
                    <div class="col-12 mb-4">
                        <div class="report-card">
                            <div class="report-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-building me-2"></i>Department Performance Overview
                                </h5>
                            </div>
                            <div class="p-4">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Department</th>
                                                <th>Staff Count</th>
                                                <th>Appraisals</th>
                                                <th>Avg Performance</th>
                                                <th>Performance Level</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($dept_stats as $dept): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($dept['department']); ?></strong></td>
                                                <td><?php echo $dept['staff_count']; ?></td>
                                                <td><?php echo $dept['appraisal_count']; ?></td>
                                                <td>
                                                    <?php if ($dept['avg_performance']): ?>
                                                        <strong><?php echo number_format($dept['avg_performance'], 1); ?>%</strong>
                                                    <?php else: ?>
                                                        <span class="text-muted">No data</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($dept['avg_performance']): ?>
                                                        <?php
                                                        $perf = $dept['avg_performance'];
                                                        if ($perf >= 80) {
                                                            echo '<span class="badge bg-success">Excellent</span>';
                                                        } elseif ($perf >= 65) {
                                                            echo '<span class="badge bg-primary">Good</span>';
                                                        } elseif ($perf >= 50) {
                                                            echo '<span class="badge bg-warning">Satisfactory</span>';
                                                        } else {
                                                            echo '<span class="badge bg-danger">Needs Improvement</span>';
                                                        }
                                                        ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">No data</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Recent Activity -->
                <div class="row">
                    <div class="col-12">
                        <div class="report-card">
                            <div class="report-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-history me-2"></i>Recent Activity
                                </h5>
                            </div>
                            <div class="p-4">
                                <?php if (!empty($recent_activity)): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Appraisee</th>
                                                    <th>Appraiser</th>
                                                    <th>Status</th>
                                                    <th>Last Updated</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_activity as $activity): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($activity['appraisee_fname'] . ' ' . $activity['appraisee_lname']); ?></td>
                                                    <td><?php echo htmlspecialchars($activity['appraiser_fname'] . ' ' . $activity['appraiser_lname']); ?></td>
                                                    <td><?php echo getStatusBadge($activity['status']); ?></td>
                                                    <td><?php echo date('M j, Y g:i A', strtotime($activity['updated_at'])); ?></td>
                                                    <td>
                                                        <a href="view_appraisal.php?id=<?php echo $activity['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-eye"></i> View
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center">No recent activity</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php include 'includes/footer.php'; ?>
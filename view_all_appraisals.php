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

// Get all appraisals with user details
$query = "SELECT a.*, 
                 u1.title as appraisee_title, u1.first_name as appraisee_fname, u1.last_name as appraisee_lname,
                 u1.job_title as appraisee_job, u1.department as appraisee_dept,
                 u2.title as appraiser_title, u2.first_name as appraiser_fname, u2.last_name as appraiser_lname,
                 u2.job_title as appraiser_job
          FROM appraisals a 
          JOIN users u1 ON a.appraisee_id = u1.id 
          JOIN users u2 ON a.appraiser_id = u2.id 
          ORDER BY a.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute();
$appraisals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$stats_query = "SELECT 
    COUNT(*) as total_appraisals,
    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_count,
    SUM(CASE WHEN status = 'planning' THEN 1 ELSE 0 END) as planning_count,
    SUM(CASE WHEN status = 'mid_review' THEN 1 ELSE 0 END) as mid_review_count,
    SUM(CASE WHEN status = 'final_review' THEN 1 ELSE 0 END) as final_review_count,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count
FROM appraisals";

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Appraisals - Hospital Appraisal System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="sidebar.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .stat-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .stat-card:hover { transform: translateY(-5px); }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            color: white;
        }
        .user-avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 12px;
        }
        .table-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-md-3 col-lg-2 px-0">
                <?php include 'sidebar.php'; ?>
            </div>
            <div class="col-md-9 col-lg-10">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-1">All Appraisals</h1>
                        <p class="text-muted mb-0">System-wide appraisal overview and management</p>
                    </div>
                    <a href="dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                        <div class="stat-card p-3">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                    <i class="fas fa-clipboard-list"></i>
                                </div>
                                <div class="ms-3">
                                    <h4 class="mb-0"><?php echo $stats['total_appraisals']; ?></h4>
                                    <small class="text-muted">Total</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                        <div class="stat-card p-3">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #6c757d 0%, #495057 100%);">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div class="ms-3">
                                    <h4 class="mb-0"><?php echo $stats['draft_count']; ?></h4>
                                    <small class="text-muted">Draft</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                        <div class="stat-card p-3">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);">
                                    <i class="fas fa-bullseye"></i>
                                </div>
                                <div class="ms-3">
                                    <h4 class="mb-0"><?php echo $stats['planning_count']; ?></h4>
                                    <small class="text-muted">Planning</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                        <div class="stat-card p-3">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="ms-3">
                                    <h4 class="mb-0"><?php echo $stats['mid_review_count']; ?></h4>
                                    <small class="text-muted">Mid Review</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                        <div class="stat-card p-3">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);">
                                    <i class="fas fa-clipboard-check"></i>
                                </div>
                                <div class="ms-3">
                                    <h4 class="mb-0"><?php echo $stats['final_review_count']; ?></h4>
                                    <small class="text-muted">Final Review</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-2 col-md-4 col-6 mb-3">
                        <div class="stat-card p-3">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon" style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div class="ms-3">
                                    <h4 class="mb-0"><?php echo $stats['completed_count']; ?></h4>
                                    <small class="text-muted">Completed</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Appraisals Table -->
                <div class="table-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-clipboard-list me-2"></i>All System Appraisals
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($appraisals)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No appraisals found</h5>
                                <p class="text-muted">No appraisals have been created in the system yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Appraisee</th>
                                            <th>Appraiser</th>
                                            <th>Period</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Last Updated</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($appraisals as $appraisal): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar me-2">
                                                        <?php echo strtoupper(substr($appraisal['appraisee_fname'], 0, 1) . substr($appraisal['appraisee_lname'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars(($appraisal['appraisee_title'] ?? '') . ' ' . $appraisal['appraisee_fname'] . ' ' . $appraisal['appraisee_lname']); ?></strong>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($appraisal['appraisee_job'] ?? 'N/A'); ?> - <?php echo htmlspecialchars($appraisal['appraisee_dept'] ?? 'N/A'); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="user-avatar me-2">
                                                        <?php echo strtoupper(substr($appraisal['appraiser_fname'], 0, 1) . substr($appraisal['appraiser_lname'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars(($appraisal['appraiser_title'] ?? '') . ' ' . $appraisal['appraiser_fname'] . ' ' . $appraisal['appraiser_lname']); ?></strong>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($appraisal['appraiser_job'] ?? 'N/A'); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <strong><?php echo date('M Y', strtotime($appraisal['period_from'])) . ' - ' . date('M Y', strtotime($appraisal['period_to'])); ?></strong>
                                                <br><small class="text-muted"><?php echo date('M j, Y', strtotime($appraisal['period_from'])) . ' - ' . date('M j, Y', strtotime($appraisal['period_to'])); ?></small>
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
                                            <td><?php echo date('M j, Y', strtotime($appraisal['updated_at'])); ?></td>
                                            <td>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="view_appraisal.php?id=<?php echo $appraisal['id']; ?>" 
                                                       class="btn btn-outline-primary btn-sm" title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if (in_array($appraisal['status'], ['draft', 'planning', 'mid_review'])): ?>
                                                    <a href="edit_appraisal.php?id=<?php echo $appraisal['id']; ?>" 
                                                       class="btn btn-outline-success btn-sm" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    <button class="btn btn-outline-danger btn-sm" title="Delete" 
                                                            onclick="deleteAppraisal(<?php echo $appraisal['id']; ?>, '<?php echo htmlspecialchars($appraisal['appraisee_fname'] . ' ' . $appraisal['appraisee_lname']); ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
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
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteAppraisal(id, name) {
            if (confirm(`Are you sure you want to delete the appraisal for ${name}? This action cannot be undone.`)) {
                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'delete_appraisal.php';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'appraisal_id';
                input.value = id;
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
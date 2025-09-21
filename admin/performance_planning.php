<?php
session_start();
require_once 'config/database.php';

checkSession();

$database = new DatabaseConfig();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get user's current performance planning data
if ($role == 'appraiser') {
    $query = "SELECT pp.*, a.id as appraisal_id, a.status,
                     u.first_name, u.last_name, u.job_title, u.department
              FROM performance_planning pp
              JOIN appraisals a ON pp.appraisal_id = a.id
              JOIN users u ON a.appraisee_id = u.id
              WHERE a.appraiser_id = :user_id
              ORDER BY a.created_at DESC";
} else {
    $query = "SELECT pp.*, a.id as appraisal_id, a.status,
                     u.first_name as appraiser_fname, u.last_name as appraiser_lname
              FROM performance_planning pp
              JOIN appraisals a ON pp.appraisal_id = a.id
              JOIN users u ON a.appraiser_id = u.id
              WHERE a.appraisee_id = :user_id
              ORDER BY a.created_at DESC";
}

$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$planning_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by appraisal
$appraisals = [];
foreach ($planning_data as $item) {
    $appraisals[$item['appraisal_id']][] = $item;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Planning - Hospital Appraisal System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .planning-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .planning-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 15px 15px 0 0;
        }
        .kra-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #667eea;
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
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-1">Performance Planning</h1>
                        <p class="text-muted mb-0">
                            <?php if ($role == 'appraiser'): ?>
                                Performance plans you've created for your appraisees
                            <?php else: ?>
                                Your performance plans and targets
                            <?php endif; ?>
                        </p>
                    </div>
                    <a href="dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
                </div>

                <?php if (empty($planning_data)): ?>
                    <div class="planning-card text-center py-5">
                        <i class="fas fa-bullseye fa-4x text-muted mb-3"></i>
                        <h4 class="text-muted">No Performance Plans Found</h4>
                        <p class="text-muted mb-4">
                            <?php if ($role == 'appraiser'): ?>
                                You haven't created any performance plans yet. Start by creating an appraisal with performance planning.
                            <?php else: ?>
                                No performance plans have been created for you yet. Contact your supervisor to begin the performance planning process.
                            <?php endif; ?>
                        </p>
                        <?php if ($role == 'appraiser'): ?>
                        <a href="create_appraisal.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Create New Appraisal
                        </a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($appraisals as $appraisal_id => $plans): ?>
                        <?php $first_plan = $plans[0]; ?>
                        <div class="planning-card">
                            <div class="planning-header">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <?php if ($role == 'appraiser'): ?>
                                            <h4 class="mb-1"><?php echo htmlspecialchars($first_plan['first_name'] . ' ' . $first_plan['last_name']); ?></h4>
                                            <p class="mb-0 opacity-75"><?php echo htmlspecialchars($first_plan['job_title'] ?? 'N/A'); ?> - <?php echo htmlspecialchars($first_plan['department'] ?? 'N/A'); ?></p>
                                        <?php else: ?>
                                            <h4 class="mb-1">My Performance Plan</h4>
                                            <p class="mb-0 opacity-75">Supervised by: <?php echo htmlspecialchars($first_plan['appraiser_fname'] . ' ' . $first_plan['appraiser_lname']); ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <?php
                                        $status_class = '';
                                        $status_text = ucfirst(str_replace('_', ' ', $first_plan['status']));
                                        
                                        switch($first_plan['status']) {
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
                                </div>
                            </div>

                            <div class="p-4">
                                <h5 class="mb-3">
                                    <i class="fas fa-bullseye me-2 text-primary"></i>Key Result Areas & Targets
                                </h5>

                                <?php foreach ($plans as $index => $plan): ?>
                                    <div class="kra-card">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <h6 class="text-primary mb-2">
                                                    <i class="fas fa-flag me-1"></i>KRA <?php echo $index + 1; ?>
                                                </h6>
                                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($plan['key_result_area'])); ?></p>
                                            </div>
                                            <div class="col-md-4">
                                                <h6 class="text-success mb-2">
                                                    <i class="fas fa-target me-1"></i>Target
                                                </h6>
                                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($plan['target'])); ?></p>
                                            </div>
                                            <div class="col-md-4">
                                                <h6 class="text-info mb-2">
                                                    <i class="fas fa-tools me-1"></i>Resources Required
                                                </h6>
                                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($plan['resources_required'] ?? 'None specified')); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <?php if (!empty($first_plan['competencies_required'])): ?>
                                    <div class="mt-4">
                                        <h6 class="text-warning mb-2">
                                            <i class="fas fa-graduation-cap me-1"></i>Key Competencies Required
                                        </h6>
                                        <div class="alert alert-light">
                                            <?php echo nl2br(htmlspecialchars($first_plan['competencies_required'])); ?>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="mt-4 text-end">
                                    <a href="view_appraisal.php?id=<?php echo $appraisal_id; ?>" class="btn btn-outline-primary me-2">
                                        <i class="fas fa-eye me-1"></i>View Full Appraisal
                                    </a>
                                    <?php if ($role == 'appraiser' && in_array($first_plan['status'], ['draft', 'planning'])): ?>
                                    <a href="edit_appraisal.php?id=<?php echo $appraisal_id; ?>" class="btn btn-primary">
                                        <i class="fas fa-edit me-1"></i>Edit Plan
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>

                <!-- Performance Planning Guidelines -->
                <div class="planning-card">
                    <div class="planning-header">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>Performance Planning Guidelines
                        </h5>
                    </div>
                    <div class="p-4">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-primary">Key Result Areas (KRAs)</h6>
                                <ul class="mb-4">
                                    <li>Should not exceed 5 areas</li>
                                    <li>Must be drawn from job description</li>
                                    <li>Should cover major responsibilities</li>
                                    <li>Must be measurable and specific</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-success">SMART Targets</h6>
                                <ul class="mb-4">
                                    <li><strong>S</strong>pecific - Clear and well-defined</li>
                                    <li><strong>M</strong>easurable - Quantifiable results</li>
                                    <li><strong>A</strong>chievable - Realistic goals</li>
                                    <li><strong>R</strong>elevant - Aligned with objectives</li>
                                    <li><strong>T</strong>ime-bound - Clear deadlines</li>
                                </ul>
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <h6><i class="fas fa-lightbulb me-2"></i>Best Practices</h6>
                            <p class="mb-2">• Collaborate with your supervisor/appraisee when setting targets</p>
                            <p class="mb-2">• Regularly review progress throughout the year</p>
                            <p class="mb-2">• Ensure alignment with departmental and organizational goals</p>
                            <p class="mb-0">• Document any changes or updates made during the period</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
session_start();
require_once 'config/database.php';

checkSession();

$database = new DatabaseConfig();
$db = $database->getConnection();

// Initialize user variables
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get user's department for permission checks
$user_dept_query = "SELECT department FROM users WHERE id = :user_id";
$user_dept_stmt = $db->prepare($user_dept_query);
$user_dept_stmt->bindParam(':user_id', $user_id);
$user_dept_stmt->execute();
$user_department = $user_dept_stmt->fetchColumn();

// Initialize permissions
$can_view = false;
$can_edit = false;
$can_comment = false;
$can_save = false;

// Get appraisal ID if editing
$appraisal_id = $_GET['id'] ?? null;
$current_stage = 'initial'; // initial, planning, final

$message = '';
$error = '';
$appraisal = null;
$performance_planning = [];
$existing_endyear = [];
$existing_core = [];
$existing_noncore = [];
$existing_overall = null;

// If editing an existing appraisal
if ($appraisal_id) {
    $query = "SELECT a.*, 
                     u1.title as appraisee_title, u1.first_name as appraisee_fname, u1.last_name as appraisee_lname,
                     u1.job_title as appraisee_job, u1.department as appraisee_dept,
                     u2.title as appraiser_title, u2.first_name as appraiser_fname, u2.last_name as appraiser_lname
              FROM appraisals a 
              JOIN users u1 ON a.appraisee_id = u1.id 
              JOIN users u2 ON a.appraiser_id = u2.id 
              WHERE a.id = :appraisal_id";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':appraisal_id', $appraisal_id);
    $stmt->execute();
    $appraisal = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appraisal) {
        header("Location: dashboard.php");
        exit();
    }

    // Check permissions for the appraisal
    $permissions = hasAppraisalPermission($user_id, $role, $appraisal, $user_department);
    $can_view = $permissions['can_view'];
    $can_edit = $permissions['can_edit'];
    $can_comment = $permissions['can_comment'];
    $can_save = $permissions['can_save'];

    // Redirect if no view permission
    if (!$can_view) {
        header("Location: dashboard.php");
        exit();
    }

    // Get stage-specific permissions
    $stage_permissions = getAppraisalStagePermissions($role, $appraisal['status'], 
        $user_id == $appraisal['appraiser_id']);

    // Determine current stage
    if (in_array($appraisal['status'], ['mid_review', 'final_review', 'completed'])) {
        $current_stage = 'final';
    } elseif ($appraisal['status'] == 'planning') {
        $current_stage = 'planning';
    }

    // Get performance planning data
    $planning_query = "SELECT * FROM performance_planning WHERE appraisal_id = :appraisal_id ORDER BY id";
    $planning_stmt = $db->prepare($planning_query);
    $planning_stmt->bindParam(':appraisal_id', $appraisal_id);
    $planning_stmt->execute();
    $performance_planning = $planning_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get existing end-of-year reviews
    $endyear_query = "SELECT * FROM end_year_reviews WHERE appraisal_id = :appraisal_id ORDER BY id";
    $endyear_stmt = $db->prepare($endyear_query);
    $endyear_stmt->bindParam(':appraisal_id', $appraisal_id);
    $endyear_stmt->execute();
    $existing_endyear = $endyear_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get existing core competencies
    $core_query = "SELECT * FROM core_competencies WHERE appraisal_id = :appraisal_id ORDER BY competency_category, id";
    $core_stmt = $db->prepare($core_query);
    $core_stmt->bindParam(':appraisal_id', $appraisal_id);
    $core_stmt->execute();
    $existing_core = $core_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get existing non-core competencies
    $noncore_query = "SELECT * FROM non_core_competencies WHERE appraisal_id = :appraisal_id ORDER BY competency_category, id";
    $noncore_stmt = $db->prepare($noncore_query);
    $noncore_stmt->bindParam(':appraisal_id', $appraisal_id);
    $noncore_stmt->execute();
    $existing_noncore = $noncore_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get existing overall assessment
    $overall_query = "SELECT * FROM overall_assessments WHERE appraisal_id = :appraisal_id";
    $overall_stmt = $db->prepare($overall_query);
    $overall_stmt->bindParam(':appraisal_id', $appraisal_id);
    $overall_stmt->execute();
    $existing_overall = $overall_stmt->fetch(PDO::FETCH_ASSOC);
} else {
    // For creating new appraisal, check initial permissions
    if (!in_array($role, ['admin', 'appraiser'])) {
        header("Location: dashboard.php");
        exit();
    }
    
    $can_view = true;
    $can_edit = true;
    $can_save = true;
    $can_comment = true;
}

// Get list of users who can be appraised
$users_query = "SELECT id, title, first_name, last_name, job_title, department FROM users WHERE role = 'appraisee' ORDER BY first_name, last_name";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute();
$appraisees = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission - CREATE NEW APPRAISAL
if ($_POST && isset($_POST['create_appraisal']) && !$appraisal_id) {
    try {
        $appraisee_id = $_POST['appraisee_id'];
        $appraiser_id = $user_id;
        $period_from = $_POST['period_from'];
        $period_to = $_POST['period_to'];
        
        if (strtotime($period_from) >= strtotime($period_to)) {
            throw new Exception("Period 'from' date must be before 'to' date.");
        }
        
        $check_query = "SELECT COUNT(*) FROM appraisals WHERE appraisee_id = :appraisee_id 
                        AND ((period_from <= :period_from AND period_to >= :period_from) 
                        OR (period_from <= :period_to AND period_to >= :period_to)
                        OR (period_from >= :period_from AND period_to <= :period_to))";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':appraisee_id', $appraisee_id);
        $check_stmt->bindParam(':period_from', $period_from);
        $check_stmt->bindParam(':period_to', $period_to);
        $check_stmt->execute();
        
        if ($check_stmt->fetchColumn() > 0) {
            throw new Exception("An appraisal already exists for this employee during the selected period.");
        }
        
        $db->beginTransaction();
        
        $query = "INSERT INTO appraisals (appraisee_id, appraiser_id, period_from, period_to, status) 
                  VALUES (:appraisee_id, :appraiser_id, :period_from, :period_to, 'draft')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':appraisee_id', $appraisee_id);
        $stmt->bindParam(':appraiser_id', $appraiser_id);
        $stmt->bindParam(':period_from', $period_from);
        $stmt->bindParam(':period_to', $period_to);
        $stmt->execute();
        
        $appraisal_id = $db->lastInsertId();
        
        if (!empty($_POST['key_result_areas']) && is_array($_POST['key_result_areas'])) {
            for ($i = 0; $i < count($_POST['key_result_areas']); $i++) {
                if (!empty($_POST['key_result_areas'][$i])) {
                    $planning_query = "INSERT INTO performance_planning (appraisal_id, key_result_area, target, resources_required, competencies_required) 
                                     VALUES (:appraisal_id, :key_result_area, :target, :resources_required, :competencies_required)";
                    $planning_stmt = $db->prepare($planning_query);
                    $planning_stmt->bindParam(':appraisal_id', $appraisal_id);
                    $planning_stmt->bindParam(':key_result_area', $_POST['key_result_areas'][$i]);
                    $planning_stmt->bindParam(':target', $_POST['targets'][$i] ?? '');
                    $planning_stmt->bindParam(':resources_required', $_POST['resources_required'][$i] ?? '');
                    $planning_stmt->bindParam(':competencies_required', $_POST['competencies_required'] ?? '');
                    $planning_stmt->execute();
                }
            }
            
            $update_query = "UPDATE appraisals SET status = 'planning' WHERE id = :appraisal_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':appraisal_id', $appraisal_id);
            $update_stmt->execute();
        }
        
        $db->commit();
        $message = "Appraisal created successfully!";
        header("Location: view_appraisal.php?id=" . $appraisal_id . "&created=1");
        exit();
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
}

// Handle form submission - SAVE FINAL APPRAISAL
if ($_POST && isset($_POST['save_final_appraisal']) && $appraisal_id) {
    // Check edit permissions for current stage
    $stage_permissions = getAppraisalStagePermissions($role, $appraisal['status'], 
        $user_id == $appraisal['appraiser_id']);

    if (!$can_save || !$stage_permissions['can_finalize']) {
        $_SESSION['error'] = "You do not have permission to save the final appraisal at this stage.";
        header("Location: dashboard.php");
        exit();
    }

    try {
        $db->beginTransaction();
        
        // Save End-of-Year Reviews
        if (!empty($_POST['target_performance'])) {
            $delete_query = "DELETE FROM end_year_reviews WHERE appraisal_id = :appraisal_id";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->bindParam(':appraisal_id', $appraisal_id);
            $delete_stmt->execute();
            
            foreach ($_POST['target_performance'] as $index => $target) {
                if (!empty($target)) {
                    $insert_query = "INSERT INTO end_year_reviews (appraisal_id, target, performance_assessment, weight_of_target, score, comments) 
                                   VALUES (:appraisal_id, :target, :performance_assessment, :weight, :score, :comments)";
                    $insert_stmt = $db->prepare($insert_query);
                    $insert_stmt->bindParam(':appraisal_id', $appraisal_id);
                    $insert_stmt->bindParam(':target', $target);
                    $insert_stmt->bindParam(':performance_assessment', $_POST['performance_assessment'][$index] ?? '');
                    $insert_stmt->bindParam(':weight', $_POST['target_weight'][$index] ?? 5.00);
                    $insert_stmt->bindParam(':score', $_POST['target_score'][$index] ?? null);
                    $insert_stmt->bindParam(':comments', $_POST['target_comments'][$index] ?? '');
                    $insert_stmt->execute();
                }
            }
        }

        // Save Core Competencies
        if (!empty($_POST['core_competencies'])) {
            $delete_query = "DELETE FROM core_competencies WHERE appraisal_id = :appraisal_id";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->bindParam(':appraisal_id', $appraisal_id);
            $delete_stmt->execute();
            
            foreach ($_POST['core_competencies'] as $category => $items) {
                foreach ($items as $index => $item) {
                    if (!empty($_POST['core_scores'][$category][$index])) {
                        $score = (int)$_POST['core_scores'][$category][$index];
                        $weight = 0.30;
                        $weighted_score = $score * $weight;
                        
                        $insert_query = "INSERT INTO core_competencies (appraisal_id, competency_category, competency_item, weight, score, weighted_score, comments) 
                                       VALUES (:appraisal_id, :category, :item, :weight, :score, :weighted_score, :comments)";
                        $insert_stmt = $db->prepare($insert_query);
                        $insert_stmt->bindParam(':appraisal_id', $appraisal_id);
                        $insert_stmt->bindParam(':category', $category);
                        $insert_stmt->bindParam(':item', $item);
                        $insert_stmt->bindParam(':weight', $weight);
                        $insert_stmt->bindParam(':score', $score);
                        $insert_stmt->bindParam(':weighted_score', $weighted_score);
                        $insert_stmt->bindParam(':comments', $_POST['core_comments'][$category][$index] ?? '');
                        $insert_stmt->execute();
                    }
                }
            }
        }

        // Save Non-Core Competencies
        if (!empty($_POST['noncore_competencies'])) {
            $delete_query = "DELETE FROM non_core_competencies WHERE appraisal_id = :appraisal_id";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->bindParam(':appraisal_id', $appraisal_id);
            $delete_stmt->execute();
            
            foreach ($_POST['noncore_competencies'] as $category => $items) {
                foreach ($items as $index => $item) {
                    if (!empty($_POST['noncore_scores'][$category][$index])) {
                        $score = (int)$_POST['noncore_scores'][$category][$index];
                        $weight = 0.10;
                        $weighted_score = $score * $weight;
                        
                        $insert_query = "INSERT INTO non_core_competencies (appraisal_id, competency_category, competency_item, weight, score, weighted_score, comments) 
                                       VALUES (:appraisal_id, :category, :item, :weight, :score, :weighted_score, :comments)";
                        $insert_stmt = $db->prepare($insert_query);
                        $insert_stmt->bindParam(':appraisal_id', $appraisal_id);
                        $insert_stmt->bindParam(':category', $category);
                        $insert_stmt->bindParam(':item', $item);
                        $insert_stmt->bindParam(':weight', $weight);
                        $insert_stmt->bindParam(':score', $score);
                        $insert_stmt->bindParam(':weighted_score', $weighted_score);
                        $insert_stmt->bindParam(':comments', $_POST['noncore_comments'][$category][$index] ?? '');
                        $insert_stmt->execute();
                    }
                }
            }
        }

        // Calculate overall assessment
        $performance_score = 0;
        $core_score = 0;
        $noncore_score = 0;

        if (!empty($_POST['target_score']) && !empty($_POST['target_weight'])) {
            $total_weighted_score = 0;
            $total_weight = 0;
            
            foreach ($_POST['target_score'] as $index => $score) {
                if (!empty($score)) {
                    $weight = floatval($_POST['target_weight'][$index] ?? 5);
                    $total_weighted_score += floatval($score) * $weight;
                    $total_weight += $weight;
                }
            }
            
            if ($total_weight > 0) {
                $performance_score = $total_weighted_score / $total_weight;
            }
        }

        if (!empty($_POST['core_scores'])) {
            $core_scores = [];
            foreach ($_POST['core_scores'] as $category => $scores) {
                $valid_scores = array_filter($scores, function($score) { return !empty($score); });
                if (!empty($valid_scores)) {
                    $core_scores = array_merge($core_scores, array_map('floatval', $valid_scores));
                }
            }
            if (!empty($core_scores)) {
                $core_score = array_sum($core_scores) / count($core_scores);
            }
        }

        if (!empty($_POST['noncore_scores'])) {
            $noncore_scores = [];
            foreach ($_POST['noncore_scores'] as $category => $scores) {
                $valid_scores = array_filter($scores, function($score) { return !empty($score); });
                if (!empty($valid_scores)) {
                    $noncore_scores = array_merge($noncore_scores, array_map('floatval', $valid_scores));
                }
            }
            if (!empty($noncore_scores)) {
                $noncore_score = array_sum($noncore_scores) / count($noncore_scores);
            }
        }

        $performance_weighted = $performance_score * 0.6;
        $core_weighted = $core_score * 0.3;
        $noncore_weighted = $noncore_score * 0.1;

        $overall_total = $performance_weighted + $core_weighted + $noncore_weighted;
        $overall_percentage = ($overall_total / 5) * 100;
        $overall_rating = calculateOverallRating($overall_percentage);
        $rating_description = getRatingDescription($overall_rating);

        $overall_data = [
            'performance_assessment_score' => $performance_score,
            'core_competencies_score' => $core_score,
            'non_core_competencies_score' => $noncore_score,
            'overall_total' => $overall_total,
            'overall_percentage' => $overall_percentage,
            'overall_rating' => $overall_rating,
            'rating_description' => $rating_description,
            'appraiser_comments' => $_POST['appraiser_comments'] ?? '',
            'career_development_plan' => $_POST['career_development_plan'] ?? '',
            'promotion_assessment' => $_POST['promotion_assessment'] ?? 'suitable',
            'appraisee_comments' => $_POST['appraisee_comments'] ?? '',
            'hod_comments' => $_POST['hod_comments'] ?? ''
        ];

        if ($existing_overall) {
            $update_query = "UPDATE overall_assessments SET 
                           performance_assessment_score = :performance_assessment_score,
                           core_competencies_score = :core_competencies_score,
                           non_core_competencies_score = :non_core_competencies_score,
                           overall_total = :overall_total,
                           overall_percentage = :overall_percentage,
                           overall_rating = :overall_rating,
                           rating_description = :rating_description,
                           appraiser_comments = :appraiser_comments,
                           career_development_plan = :career_development_plan,
                           promotion_assessment = :promotion_assessment,
                           appraisee_comments = :appraisee_comments,
                           hod_comments = :hod_comments
                           WHERE appraisal_id = :appraisal_id";
            $update_stmt = $db->prepare($update_query);
            foreach ($overall_data as $key => $value) {
                $update_stmt->bindValue(':' . $key, $value);
            }
            $update_stmt->bindParam(':appraisal_id', $appraisal_id);
            $update_stmt->execute();
        } else {
            $insert_query = "INSERT INTO overall_assessments (appraisal_id, performance_assessment_score, core_competencies_score, non_core_competencies_score, overall_total, overall_percentage, overall_rating, rating_description, appraiser_comments, career_development_plan, promotion_assessment, appraisee_comments, hod_comments) 
                           VALUES (:appraisal_id, :performance_assessment_score, :core_competencies_score, :non_core_competencies_score, :overall_total, :overall_percentage, :overall_rating, :rating_description, :appraiser_comments, :career_development_plan, :promotion_assessment, :appraisee_comments, :hod_comments)";
            $insert_stmt = $db->prepare($insert_query);
            $insert_stmt->bindParam(':appraisal_id', $appraisal_id);
            foreach ($overall_data as $key => $value) {
                $insert_stmt->bindValue(':' . $key, $value);
            }
            $insert_stmt->execute();
        }

        $status_query = "UPDATE appraisals SET status = 'completed' WHERE id = :appraisal_id";
        $status_stmt = $db->prepare($status_query);
        $status_stmt->bindParam(':appraisal_id', $appraisal_id);
        $status_stmt->execute();

        $db->commit();
        $message = "Final appraisal completed successfully!";
        header("Location: view_appraisal.php?id=" . $appraisal_id);
        exit();

    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
}

// Prepare competency data
$core_competencies = getCoreCompetencies();
$noncore_competencies = getNonCoreCompetencies();

// Create indexed arrays for pre-population
$existing_endyear_indexed = [];
foreach ($existing_endyear as $item) {
    $existing_endyear_indexed[] = $item;
}

$existing_core_indexed = [];
foreach ($existing_core as $item) {
    $key = $item['competency_category'] . '_' . $item['competency_item'];
    $existing_core_indexed[$key] = $item;
}

$existing_noncore_indexed = [];
foreach ($existing_noncore as $item) {
    $key = $item['competency_category'] . '_' . $item['competency_item'];
    $existing_noncore_indexed[$key] = $item;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $appraisal_id ? 'Final Appraisal' : 'Create New Appraisal'; ?> - Hospital Appraisal System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .form-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        .section-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 15px 15px 0 0;
            margin: 0;
        }
        .rating-scale {
            background: #e3f2fd;
            border: 1px solid #bbdefb;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .competency-item {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .score-radio {
            transform: scale(1.2);
            margin: 0 0.5rem;
        }
        .target-row {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .performance-row {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            margin-bottom: 1rem;
            padding: 1rem;
        }
        .add-row-btn {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            border: none;
            border-radius: 25px;
            padding: 0.5rem 1rem;
            color: white;
            transition: all 0.3s ease;
        }
        .add-row-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            color: white;
        }
        .remove-row-btn {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            border: none;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-1"><?php echo $appraisal_id ? 'Final Appraisal' : 'Create New Appraisal'; ?></h1>
                        <p class="text-muted mb-0">
                            <?php 
                            if ($appraisal_id) {
                                echo "Complete end-of-year review for: <strong>" . htmlspecialchars($appraisal['appraisee_fname'] . ' ' . $appraisal['appraisee_lname']) . "</strong>";
                            } else {
                                echo "Staff Performance Planning, Review and Appraisal Form";
                            }
                            ?>
                        </p>
                    </div>
                    <div>
                        <?php if ($appraisal_id): ?>
                        <a href="view_appraisal.php?id=<?php echo $appraisal_id; ?>" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-eye me-2"></i>View Progress
                        </a>
                        <?php endif; ?>
                        <a href="dashboard.php" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                </div>

                <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <form method="POST" id="appraisalForm">
                    <?php if (!$appraisal_id): // CREATE NEW APPRAISAL SECTION ?>
                        <!-- Create New Appraisal Form -->
                        <!-- ... Rest of the create form HTML ... -->
                    <?php else: // FINAL APPRAISAL SECTION ?>
                        <!-- Final Appraisal Form -->
                        <!-- ... Rest of the final appraisal form HTML ... -->
                    <?php endif; ?>

                    <?php if ($role == 'admin' || $role == 'appraiser'): ?>
                        <?php if ($can_save): ?>
                            <div class="mt-4 pt-3 border-top text-end">
                                <button type="submit" name="save_final_appraisal" value="1" class="btn btn-success btn-lg">
                                    <i class="fas fa-check-circle me-2"></i>Complete Final Appraisal
                                </button>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // ... Rest of the JavaScript code ...
    </script>
</body>
</html>
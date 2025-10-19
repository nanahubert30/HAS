<?php
session_start();
require_once 'config/database.php';

checkSession();

// Only appraisers and admins can create appraisals
if (!in_array($_SESSION['role'], ['admin', 'appraiser'])) {
    header("Location: create_appraisal.php");
    exit();
}

$database = new DatabaseConfig();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
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

    // Check permissions
    if ($role != 'admin' && $user_id != $appraisal['appraiser_id']) {
        header("Location: dashboard.php");
        exit();
    }

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
    <link href="sidebar.css" rel="stylesheet">
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
            <div class="col-md-3 col-lg-2 px-0">
                <?php include 'sidebar.php'; ?>
            </div>
            <div class="col-md-9 col-lg-10">
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
                    <!-- CREATE APPRAISAL SECTION -->
                    <?php if (!$appraisal_id): ?>

                    <div class="row">
                        <!-- Section 1-A: Appraisee Personal Information -->
                        <div class="col-lg-6 mb-4">
                            <div class="form-card p-4">
                                <div class="section-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-user me-2"></i>Section 1-A: Appraisee Personal Information
                                    </h5>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="period_from" class="form-label">Period From</label>
                                        <input type="date" class="form-control" id="period_from" name="period_from" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="period_to" class="form-label">Period To</label>
                                        <input type="date" class="form-control" id="period_to" name="period_to" required>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="appraisee_id" class="form-label">Select Employee to Appraise</label>
                                    <select class="form-select" id="appraisee_id" name="appraisee_id" required>
                                        <option value="">Choose an employee...</option>
                                        <?php foreach ($appraisees as $employee): ?>
                                        <option value="<?php echo $employee['id']; ?>">
                                            <?php echo htmlspecialchars($employee['title'] . ' ' . $employee['first_name'] . ' ' . $employee['last_name'] . ' - ' . $employee['job_title'] . ' (' . $employee['department'] . ')'); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>
                                    <strong>Note:</strong> Employee details will be automatically filled from their profile once selected.
                                </div>
                            </div>
                        </div>

                        <!-- Section 1-B: Appraiser Information -->
                        <div class="col-lg-6 mb-4">
                            <div class="form-card p-4">
                                <div class="section-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-user-tie me-2"></i>Section 1-B: Appraiser Information
                                    </h5>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Appraiser Name</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['full_name']); ?>" readonly>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Position of Appraiser</label>
                                    <?php
                                    $user_query = "SELECT job_title FROM users WHERE id = :user_id";
                                    $user_stmt = $db->prepare($user_query);
                                    $user_stmt->bindParam(':user_id', $user_id);
                                    $user_stmt->execute();
                                    $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
                                    ?>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_data['job_title'] ?? 'N/A'); ?>" readonly>
                                </div>

                                <div class="alert alert-warning">
                                    <i class="fas fa-shield-alt me-2"></i>
                                    <strong>Confidential:</strong> This form is strictly confidential and should be handled according to hospital policies.
                                </div>
                            </div>
                        </div>

                        <!-- Section 2: Performance Planning Form -->
                        <div class="col-12 mb-4">
                            <div class="form-card p-4">
                                <div class="section-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-bullseye me-2"></i>Section 2: Performance Planning Form
                                    </h5>
                                    <small class="opacity-75">To be agreed between the appraiser and the employee at the start of the annual appraisal cycle</small>
                                </div>

                                <div id="performance-planning-container">
                                    <div class="performance-row" data-row="0">
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Key Result Area</label>
                                                <textarea class="form-control" name="key_result_areas[]" rows="3" 
                                                         placeholder="Not more than 5 - To be drawn from employee's Job Description"></textarea>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Targets</label>
                                                <textarea class="form-control" name="targets[]" rows="3" 
                                                         placeholder="Results to be achieved, should be specific, measurable, realistic and time-framed"></textarea>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label">Resources Required</label>
                                                <textarea class="form-control" name="resources_required[]" rows="3" 
                                                         placeholder="List resources needed"></textarea>
                                            </div>
                                            <div class="col-md-1 mb-3 d-flex align-items-end">
                                                <button type="button" class="btn remove-row-btn d-none" onclick="removePerformanceRow(this)">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-center mb-3">
                                    <button type="button" class="btn add-row-btn" onclick="addPerformanceRow()">
                                        <i class="fas fa-plus me-2"></i>Add Another Key Result Area
                                    </button>
                                </div>

                                <div class="mb-3">
                                    <label for="competencies_required" class="form-label">Key Competencies Required</label>
                                    <textarea class="form-control" id="competencies_required" name="competencies_required" rows="3" 
                                             placeholder="See Section 5 for detailed competencies list"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Training Records -->
                        <div class="col-12 mb-4">
                            <div class="form-card p-4">
                                <div class="section-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-graduation-cap me-2"></i>Training Received During Previous Year
                                    </h5>
                                </div>

                                <div id="training-container">
                                    <div class="training-row" data-row="0">
                                        <div class="row align-items-end">
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Institution</label>
                                                <input type="text" class="form-control" name="training_institution[]" 
                                                       placeholder="Training institution name">
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label">Date</label>
                                                <input type="date" class="form-control" name="training_date[]">
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Programme</label>
                                                <input type="text" class="form-control" name="training_programme[]" 
                                                       placeholder="Training programme/course name">
                                            </div>
                                            <div class="col-md-1 mb-3">
                                                <button type="button" class="btn remove-row-btn d-none" onclick="removeTrainingRow(this)">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-center">
                                    <button type="button" class="btn add-row-btn" onclick="addTrainingRow()">
                                        <i class="fas fa-plus me-2"></i>Add Training Record
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons for Create -->
                        <div class="col-12">
                            <div class="form-card p-4">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="alert alert-info">
                                            <i class="fas fa-lightbulb me-2"></i>
                                            <strong>Tips:</strong>
                                            <ul class="mb-0 mt-2">
                                                <li>Ensure all key result areas align with organizational objectives</li>
                                                <li>Make targets SMART (Specific, Measurable, Achievable, Relevant, Time-bound)</li>
                                                <li>Include necessary resources for successful completion</li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="col-md-6 d-flex align-items-center justify-content-end">
                                        <div class="d-grid gap-2 d-md-flex">
                                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                                <i class="fas fa-times me-2"></i>Cancel
                                            </a>
                                            <button type="submit" name="create_appraisal" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i>Create Appraisal
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php else: ?>

                    <!-- Section 4: End-of-Year Review -->
                    <div class="form-card">
                        <div class="section-header">
                            <h5 class="mb-0">
                                <i class="fas fa-clipboard-check me-2"></i>Section 4: End-of-Year Review Form
                            </h5>
                        </div>
                        <div class="p-4">
                            <?php if (!empty($performance_planning)): ?>
                                <?php foreach ($performance_planning as $index => $plan): 
                                    $existing = $existing_endyear_indexed[$index] ?? null;
                                ?>
                                <div class="target-row">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <label class="form-label fw-bold">Target <?php echo $index + 1; ?></label>
                                            <textarea class="form-control" name="target_performance[]" rows="3" readonly><?php echo htmlspecialchars($plan['target']); ?></textarea>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Performance Assessment</label>
                                            <textarea class="form-control" name="performance_assessment[]" rows="3" 
                                                     placeholder="Describe actual performance and achievements"><?php echo $existing ? htmlspecialchars($existing['performance_assessment']) : ''; ?></textarea>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Weight</label>
                                            <select class="form-control" name="target_weight[]">
                                                <option value="1" <?php echo ($existing && $existing['weight_of_target'] == 1) ? 'selected' : ''; ?>>1</option>
                                                <option value="2" <?php echo ($existing && $existing['weight_of_target'] == 2) ? 'selected' : ''; ?>>2</option>
                                                <option value="3" <?php echo ($existing && $existing['weight_of_target'] == 3) ? 'selected' : ''; ?>>3</option>
                                                <option value="4" <?php echo ($existing && $existing['weight_of_target'] == 4) ? 'selected' : ''; ?>>4</option>
                                                <option value="5" <?php echo (!$existing || $existing['weight_of_target'] == 5) ? 'selected' : ''; ?>>5</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Score (1-5)</label>
                                            <select class="form-control" name="target_score[]" required>
                                                <option value="">Select</option>
                                                <option value="1" <?php echo ($existing && $existing['score'] == 1) ? 'selected' : ''; ?>>1 - Unacceptable</option>
                                                <option value="2" <?php echo ($existing && $existing['score'] == 2) ? 'selected' : ''; ?>>2 - Below Expectation</option>
                                                <option value="3" <?php echo ($existing && $existing['score'] == 3) ? 'selected' : ''; ?>>3 - Meets Expectations</option>
                                                <option value="4" <?php echo ($existing && $existing['score'] == 4) ? 'selected' : ''; ?>>4 - Exceeds Expectations</option>
                                                <option value="5" <?php echo ($existing && $existing['score'] == 5) ? 'selected' : ''; ?>>5 - Exceptional</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label">Comments</label>
                                            <textarea class="form-control" name="target_comments[]" rows="3" 
                                                     placeholder="Additional comments"><?php echo $existing ? htmlspecialchars($existing['comments']) : ''; ?></textarea>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    No performance planning data found. Please complete performance planning first.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Section 5A: Core Competencies -->
                    <div class="form-card">
                        <div class="section-header">
                            <h5 class="mb-0">
                                <i class="fas fa-star me-2"></i>Section 5A: Annual Appraisal - Core Competencies
                            </h5>
                        </div>
                        <div class="p-4">
                            <div class="rating-scale mb-4">
                                <h6><i class="fas fa-info-circle me-2"></i>Rating Scale</h6>
                                <div class="row text-center">
                                    <div class="col"><span class="badge bg-danger">1</span> Unacceptable</div>
                                    <div class="col"><span class="badge bg-warning">2</span> Below Expectation</div>
                                    <div class="col"><span class="badge bg-info">3</span> Meets Expectations</div>
                                    <div class="col"><span class="badge bg-success">4</span> Exceeds Expectations</div>
                                    <div class="col"><span class="badge bg-primary">5</span> Exceptional</div>
                                </div>
                            </div>

                            <?php foreach ($core_competencies as $category => $competency): ?>
                            <div class="competency-item">
                                <h6 class="text-primary mb-3"><?php echo htmlspecialchars($competency['title']); ?></h6>
                                <?php foreach ($competency['items'] as $item_index => $item): 
                                    $item_key = $category . '_' . $item;
                                    $existing = $existing_core_indexed[$item_key] ?? null;
                                ?>
                                <div class="row align-items-center mb-3">
                                    <div class="col-md-6">
                                        <input type="hidden" name="core_competencies[<?php echo $category; ?>][]" value="<?php echo htmlspecialchars($item); ?>">
                                        <small><?php echo htmlspecialchars($item); ?></small>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <label>
                                                <input type="radio" name="core_scores[<?php echo $category; ?>][<?php echo $item_index; ?>]" 
                                                       value="<?php echo $i; ?>" class="score-radio" 
                                                       <?php echo ($existing && $existing['score'] == $i) ? 'checked' : ''; ?> required>
                                                <?php echo $i; ?>
                                            </label>
                                        <?php endfor; ?>
                                    </div>
                                    <div class="col-md-3">
                                        <textarea class="form-control form-control-sm" 
                                                 name="core_comments[<?php echo $category; ?>][<?php echo $item_index; ?>]" 
                                                 rows="2" placeholder="Comments"><?php echo $existing ? htmlspecialchars($existing['comments']) : ''; ?></textarea>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Section 5B: Non-Core Competencies -->
                    <div class="form-card">
                        <div class="section-header">
                            <h5 class="mb-0">
                                <i class="fas fa-star-half-alt me-2"></i>Section 5B: Non-Core Competencies
                            </h5>
                        </div>
                        <div class="p-4">
                            <div class="rating-scale mb-4">
                                <h6><i class="fas fa-info-circle me-2"></i>Rating Scale</h6>
                                <div class="row text-center">
                                    <div class="col"><span class="badge bg-danger">1</span> Unacceptable</div>
                                    <div class="col"><span class="badge bg-warning">2</span> Below Expectation</div>
                                    <div class="col"><span class="badge bg-info">3</span> Meets Expectations</div>
                                    <div class="col"><span class="badge bg-success">4</span> Exceeds Expectations</div>
                                    <div class="col"><span class="badge bg-primary">5</span> Exceptional</div>
                                </div>
                            </div>

                            <?php foreach ($noncore_competencies as $category => $competency): ?>
                            <div class="competency-item">
                                <h6 class="text-success mb-3"><?php echo htmlspecialchars($competency['title']); ?></h6>
                                <?php foreach ($competency['items'] as $item_index => $item): 
                                    $item_key = $category . '_' . $item;
                                    $existing = $existing_noncore_indexed[$item_key] ?? null;
                                ?>
                                <div class="row align-items-center mb-3">
                                    <div class="col-md-6">
                                        <input type="hidden" name="noncore_competencies[<?php echo $category; ?>][]" value="<?php echo htmlspecialchars($item); ?>">
                                        <small><?php echo htmlspecialchars($item); ?></small>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <label>
                                                <input type="radio" name="noncore_scores[<?php echo $category; ?>][<?php echo $item_index; ?>]" 
                                                       value="<?php echo $i; ?>" class="score-radio" 
                                                       <?php echo ($existing && $existing['score'] == $i) ? 'checked' : ''; ?>>
                                                <?php echo $i; ?>
                                            </label>
                                        <?php endfor; ?>
                                    </div>
                                    <div class="col-md-3">
                                        <textarea class="form-control form-control-sm" 
                                                 name="noncore_comments[<?php echo $category; ?>][<?php echo $item_index; ?>]" 
                                                 rows="2" placeholder="Comments"><?php echo $existing ? htmlspecialchars($existing['comments']) : ''; ?></textarea>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Section 6: Appraiser's Comments -->
                    <div class="form-card">
                        <div class="section-header">
                            <h5 class="mb-0">
                                <i class="fas fa-comment me-2"></i>Section 6: Appraiser's Comments
                            </h5>
                        </div>
                        <div class="p-4">
                            <div class="mb-3">
                                <label for="appraiser_comments" class="form-label">Comments on Performance Plan Achievements</label>
                                <textarea class="form-control" id="appraiser_comments" name="appraiser_comments" rows="4" 
                                         placeholder="Comment on Performance Plan achievements and additional contributions made"><?php echo $existing_overall ? htmlspecialchars($existing_overall['appraiser_comments']) : ''; ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Section 7: Career Development -->
                    <div class="form-card">
                        <div class="section-header">
                            <h5 class="mb-0">
                                <i class="fas fa-graduation-cap me-2"></i>Section 7: Career Development
                            </h5>
                        </div>
                        <div class="p-4">
                            <div class="mb-3">
                                <label for="career_development_plan" class="form-label">Training and Development - Comments and Plan</label>
                                <textarea class="form-control" id="career_development_plan" name="career_development_plan" rows="4" 
                                         placeholder="To be completed by the Appraiser in discussion with the employee"><?php echo $existing_overall ? htmlspecialchars($existing_overall['career_development_plan']) : ''; ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Section 8: Assessment Decision -->
                    <div class="form-card">
                        <div class="section-header">
                            <h5 class="mb-0">
                                <i class="fas fa-balance-scale me-2"></i>Section 8: Assessment Decision
                            </h5>
                        </div>
                        <div class="p-4">
                            <div class="mb-3">
                                <label for="promotion_assessment" class="form-label">Promotion Assessment Decision</label>
                                <select class="form-select" id="promotion_assessment" name="promotion_assessment" required>
                                    <option value="outstanding" <?php echo ($existing_overall && $existing_overall['promotion_assessment'] == 'outstanding') ? 'selected' : ''; ?>>Outstanding - should be promoted as soon as possible</option>
                                    <option value="suitable" <?php echo (!$existing_overall || $existing_overall['promotion_assessment'] == 'suitable') ? 'selected' : ''; ?>>Suitable for promotion</option>
                                    <option value="likely_2_3_years" <?php echo ($existing_overall && $existing_overall['promotion_assessment'] == 'likely_2_3_years') ? 'selected' : ''; ?>>Likely to be ready for promotion in 2 to 3 years</option>
                                    <option value="not_ready_3_years" <?php echo ($existing_overall && $existing_overall['promotion_assessment'] == 'not_ready_3_years') ? 'selected' : ''; ?>>Not ready for promotion for at least 3 years</option>
                                    <option value="unlikely" <?php echo ($existing_overall && $existing_overall['promotion_assessment'] == 'unlikely') ? 'selected' : ''; ?>>Unlikely to be promoted further</option>
                                </select>
                            </div>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>Note:</strong> This assessment should be based on the overall performance rating, competency levels, and the employee's readiness for increased responsibilities.
                            </div>
                        </div>
                    </div>

                    <!-- Section 9: Appraisee's Comments -->
                    <div class="form-card">
                        <div class="section-header">
                            <h5 class="mb-0">
                                <i class="fas fa-user-edit me-2"></i>Section 9: Appraisee's Comments
                            </h5>
                        </div>
                        <div class="p-4">
                            <div class="mb-3">
                                <label for="appraisee_comments" class="form-label">Employee's Comments on the Appraisal</label>
                                <textarea class="form-control" id="appraisee_comments" name="appraisee_comments" rows="4" 
                                         placeholder="Employee may comment on the appraisal, agree or disagree with assessments, and add any relevant information"><?php echo $existing_overall ? htmlspecialchars($existing_overall['appraisee_comments']) : ''; ?></textarea>
                            </div>
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>For Appraisee:</strong> You have the right to comment on your appraisal. If you disagree with any assessment, please provide specific reasons and examples.
                            </div>
                        </div>
                    </div>

                    <!-- Section 10: Head of Department's Comments -->
                    <div class="form-card">
                        <div class="section-header">
                            <h5 class="mb-0">
                                <i class="fas fa-user-tie me-2"></i>Section 10: Head of Department's / Division's (HOD) Comments
                            </h5>
                        </div>
                        <div class="p-4">
                            <div class="mb-3">
                                <label for="hod_comments" class="form-label">HOD's Comments and Recommendations</label>
                                <textarea class="form-control" id="hod_comments" name="hod_comments" rows="4" 
                                         placeholder="Head of Department should review the appraisal and provide comments, endorsements, or recommendations"><?php echo $existing_overall ? htmlspecialchars($existing_overall['hod_comments']) : ''; ?></textarea>
                            </div>
                            <div class="alert alert-secondary">
                                <i class="fas fa-info-circle me-2"></i>
                                <strong>For HOD:</strong> Review the appraisal for fairness and accuracy. Provide your assessment of the employee's performance and potential. Recommend any necessary actions.
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons and Summary -->
                    <div class="form-card">
                        <div class="section-header">
                            <h5 class="mb-0">
                                <i class="fas fa-save me-2"></i>Complete Appraisal
                            </h5>
                        </div>
                        <div class="p-4">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="alert alert-success">
                                        <h6><i class="fas fa-check-circle me-2"></i>Before Submitting</h6>
                                        <p class="mb-2">Please ensure:</p>
                                        <ul class="mb-0">
                                            <li>All sections are completed accurately</li>
                                            <li>All scores are provided (1-5 scale)</li>
                                            <li>Comments are clear and constructive</li>
                                            <li>Assessment decision is fair and justified</li>
                                            <li>All parties have opportunity to review</li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="alert alert-warning">
                                        <h6><i class="fas fa-exclamation-triangle me-2"></i>Important Notice</h6>
                                        <p class="mb-2">Once submitted:</p>
                                        <ul class="mb-0">
                                            <li>The appraisal will be marked as completed</li>
                                            <li>All scores will be calculated automatically</li>
                                            <li>The overall rating will be determined</li>
                                            <li>Changes may require administrator approval</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <hr class="my-4">

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title">Scoring Breakdown</h6>
                                            <ul class="list-unstyled mb-0">
                                                <li><i class="fas fa-chart-line text-primary me-2"></i>Performance Assessment: <strong>60%</strong></li>
                                                <li><i class="fas fa-star text-warning me-2"></i>Core Competencies: <strong>30%</strong></li>
                                                <li><i class="fas fa-star-half-alt text-info me-2"></i>Non-Core Competencies: <strong>10%</strong></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title">Rating Scale</h6>
                                            <ul class="list-unstyled mb-0">
                                                <li><span class="badge bg-danger me-2">1</span>Unacceptable (0-39%)</li>
                                                <li><span class="badge bg-warning me-2">2</span>Below Expectation (40-49%)</li>
                                                <li><span class="badge bg-info me-2">3</span>Meets Expectations (50-64%)</li>
                                                <li><span class="badge bg-success me-2">4</span>Exceeds Expectations (65-79%)</li>
                                                <li><span class="badge bg-primary me-2">5</span>Exceptional (80-100%)</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4 pt-3 border-top">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <small class="text-muted">
                                            <i class="fas fa-shield-alt me-1"></i>
                                            This appraisal is confidential and will be stored securely in accordance with hospital policies.
                                        </small>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="view_appraisal.php?id=<?php echo $appraisal_id; ?>" class="btn btn-outline-secondary">
                                            <i class="fas fa-times me-2"></i>Cancel
                                        </a>
                                        <button type="submit" name="save_final_appraisal" value="1" class="btn btn-success btn-lg">
                                            <i class="fas fa-check-circle me-2"></i>Complete Final Appraisal
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        let performanceRowCount = 0;
        let trainingRowCount = 0;

        function addPerformanceRow() {
            performanceRowCount++;
            const container = document.getElementById('performance-planning-container');
            const newRow = document.createElement('div');
            newRow.className = 'performance-row';
            newRow.setAttribute('data-row', performanceRowCount);
            
            newRow.innerHTML = `
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Key Result Area</label>
                        <textarea class="form-control" name="key_result_areas[]" rows="3" 
                                 placeholder="Not more than 5 - To be drawn from employee's Job Description"></textarea>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Targets</label>
                        <textarea class="form-control" name="targets[]" rows="3" 
                                 placeholder="Results to be achieved, should be specific, measurable, realistic and time-framed"></textarea>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Resources Required</label>
                        <textarea class="form-control" name="resources_required[]" rows="3" 
                                 placeholder="List resources needed"></textarea>
                    </div>
                    <div class="col-md-1 mb-3 d-flex align-items-end">
                        <button type="button" class="btn remove-row-btn" onclick="removePerformanceRow(this)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
            
            container.appendChild(newRow);
            updateRemoveButtons();
        }

        function removePerformanceRow(button) {
            button.closest('.performance-row').remove();
            updateRemoveButtons();
        }

        function addTrainingRow() {
            trainingRowCount++;
            const container = document.getElementById('training-container');
            const newRow = document.createElement('div');
            newRow.className = 'training-row';
            newRow.setAttribute('data-row', trainingRowCount);
            
            newRow.innerHTML = `
                <div class="row align-items-end">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Institution</label>
                        <input type="text" class="form-control" name="training_institution[]" 
                               placeholder="Training institution name">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label class="form-label">Date</label>
                        <input type="date" class="form-control" name="training_date[]">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Programme</label>
                        <input type="text" class="form-control" name="training_programme[]" 
                               placeholder="Training programme/course name">
                    </div>
                    <div class="col-md-1 mb-3">
                        <button type="button" class="btn remove-row-btn" onclick="removeTrainingRow(this)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
            
            container.appendChild(newRow);
            updateTrainingRemoveButtons();
        }

        function removeTrainingRow(button) {
            button.closest('.training-row').remove();
            updateTrainingRemoveButtons();
        }

        function updateRemoveButtons() {
            const rows = document.querySelectorAll('.performance-row');
            rows.forEach((row, index) => {
                const removeBtn = row.querySelector('.remove-row-btn');
                if (rows.length > 1) {
                    removeBtn.classList.remove('d-none');
                } else {
                    removeBtn.classList.add('d-none');
                }
            });
        }

        function updateTrainingRemoveButtons() {
            const rows = document.querySelectorAll('.training-row');
            rows.forEach((row, index) => {
                const removeBtn = row.querySelector('.remove-row-btn');
                if (rows.length > 1) {
                    removeBtn.classList.remove('d-none');
                } else {
                    removeBtn.classList.add('d-none');
                }
            });
        }

        // Form validation
        document.getElementById('appraisalForm').addEventListener('submit', function(e) {
            const submitButton = e.submitter;
            const isCreateMode = submitButton && submitButton.name === 'create_appraisal';
            const isFinalMode = submitButton && submitButton.name === 'save_final_appraisal';
            
            if (isCreateMode) {
                // Validate for create mode
                const periodFrom = document.getElementById('period_from').value;
                const periodTo = document.getElementById('period_to').value;
                
                if (!periodFrom || !periodTo) {
                    e.preventDefault();
                    alert('Please select both period from and to dates.');
                    return false;
                }
                
                if (new Date(periodFrom) >= new Date(periodTo)) {
                    e.preventDefault();
                    alert('Period "from" date must be before "to" date.');
                    return false;
                }
                
                const keyResultAreas = document.querySelectorAll('textarea[name="key_result_areas[]"]');
                let hasContent = false;
                keyResultAreas.forEach(textarea => {
                    if (textarea.value.trim()) {
                        hasContent = true;
                    }
                });
                
                if (!hasContent) {
                    e.preventDefault();
                    alert('Please add at least one Key Result Area with targets.');
                    return false;
                }
            } else if (isFinalMode) {
                // Validate for final appraisal mode
                const targetScores = document.querySelectorAll('select[name="target_score[]"]');
                let allTargetScoresProvided = true;
                let targetCount = 0;
                
                targetScores.forEach(select => {
                    targetCount++;
                    if (!select.value) {
                        allTargetScoresProvided = false;
                    }
                });
                
                if (targetCount === 0) {
                    e.preventDefault();
                    alert('No performance targets found. Please complete performance planning first.');
                    return false;
                }
                
                if (!allTargetScoresProvided) {
                    e.preventDefault();
                    alert('Please provide scores for all targets before submitting.');
                    return false;
                }
                
                const coreScores = document.querySelectorAll('input[type="radio"][name^="core_scores"]:checked');
                if (coreScores.length === 0) {
                    e.preventDefault();
                    alert('Please provide scores for all core competencies before submitting.');
                    return false;
                }
                
                const noncoreScores = document.querySelectorAll('input[type="radio"][name^="noncore_scores"]:checked');
                if (noncoreScores.length === 0) {
                    e.preventDefault();
                    alert('Please provide scores for all non-core competencies before submitting.');
                    return false;
                }
                
                if (!confirm('Are you sure you want to complete this final appraisal?\n\nThis will:\n- Calculate the overall rating\n- Mark the appraisal as completed\n- Make it available for review')) {
                    e.preventDefault();
                    return false;
                }
                
                return true;
            }
        });

        // Set default dates (current year) for create mode
        document.addEventListener('DOMContentLoaded', function() {
            const periodFromInput = document.getElementById('period_from');
            if (periodFromInput) {
                const currentYear = new Date().getFullYear();
                periodFromInput.value = currentYear + '-01-01';
                document.getElementById('period_to').value = currentYear + '-12-31';
            }
        });

        // Auto-save draft functionality for final appraisal
        let autoSaveTimer;
        const finalAppraisalForm = document.getElementById('appraisalForm');
        if (finalAppraisalForm && document.querySelector('input[name="save_final_appraisal"]')) {
            const formInputs = finalAppraisalForm.querySelectorAll('textarea, select, input[type="radio"]');
            
            formInputs.forEach(input => {
                input.addEventListener('change', function() {
                    clearTimeout(autoSaveTimer);
                    autoSaveTimer = setTimeout(() => {
                        console.log('Auto-save would trigger here (not implemented in this version)');
                    }, 3000);
                });
            });
        }

        // Progress indicator for final appraisal
        function updateProgress() {
            const totalSections = 10;
            let completedSections = 0;
            
            const targetScores = document.querySelectorAll('select[name="target_score[]"]');
            if (Array.from(targetScores).every(s => s.value)) completedSections++;
            
            const coreRadios = document.querySelectorAll('input[type="radio"][name^="core_scores"]:checked');
            if (coreRadios.length > 0) completedSections++;
            
            const noncoreRadios = document.querySelectorAll('input[type="radio"][name^="noncore_scores"]:checked');
            if (noncoreRadios.length > 0) completedSections++;
            
            const appraiserComments = document.getElementById('appraiser_comments');
            if (appraiserComments && appraiserComments.value) completedSections++;
            
            const careerDev = document.getElementById('career_development_plan');
            if (careerDev && careerDev.value) completedSections++;
            
            const promotion = document.getElementById('promotion_assessment');
            if (promotion && promotion.value) completedSections++;
            
            const appraiseeComments = document.getElementById('appraisee_comments');
            if (appraiseeComments && appraiseeComments.value) completedSections++;
            
            const hodComments = document.getElementById('hod_comments');
            if (hodComments && hodComments.value) completedSections++;
            
            const progress = (completedSections / totalSections) * 100;
            console.log(`Form completion: ${progress.toFixed(1)}%`);
        }

        // Update progress on any form change
        if (finalAppraisalForm && document.querySelector('input[name="save_final_appraisal"]')) {
            const formInputs = finalAppraisalForm.querySelectorAll('textarea, select, input[type="radio"]');
            formInputs.forEach(input => {
                input.addEventListener('change', updateProgress);
            });
        }
    </script>
</body>
</html>
<?php
session_start();
require_once 'config/database.php';

checkSession();

$database = new DatabaseConfig();
$db = $database->getConnection();

$appraisal_id = $_GET['id'] ?? null;
if (!$appraisal_id) {
    header("Location: dashboard.php");
    exit();
}

// Get appraisal details
$query = "SELECT a.*, 
                 u1.title as appraisee_title, u1.first_name as appraisee_fname, u1.last_name as appraisee_lname,
                 u1.other_names as appraisee_other, u1.gender as appraisee_gender, u1.grade_salary,
                 u1.job_title as appraisee_job, u1.department as appraisee_dept, u1.appointment_date,
                 u2.title as appraiser_title, u2.first_name as appraiser_fname, u2.last_name as appraiser_lname,
                 u2.job_title as appraiser_job
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

// Check access permissions
$user_role = $_SESSION['role'];
$user_id = $_SESSION['user_id'];

if ($user_role != 'admin' && $user_id != $appraisal['appraiser_id'] && $user_id != $appraisal['appraisee_id']) {
    header("Location: dashboard.php");
    exit();
}

// Get performance planning data
$planning_query = "SELECT * FROM performance_planning WHERE appraisal_id = :appraisal_id ORDER BY id";
$planning_stmt = $db->prepare($planning_query);
$planning_stmt->bindParam(':appraisal_id', $appraisal_id);
$planning_stmt->execute();
$performance_planning = $planning_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get training records
$training_query = "SELECT * FROM training_records WHERE appraisal_id = :appraisal_id ORDER BY date_completed DESC";
$training_stmt = $db->prepare($training_query);
$training_stmt->bindParam(':appraisal_id', $appraisal_id);
$training_stmt->execute();
$training_records = $training_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get mid-year reviews
$midyear_query = "SELECT * FROM mid_year_reviews WHERE appraisal_id = :appraisal_id ORDER BY id";
$midyear_stmt = $db->prepare($midyear_query);
$midyear_stmt->bindParam(':appraisal_id', $appraisal_id);
$midyear_stmt->execute();
$midyear_reviews = $midyear_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get end-of-year reviews
$endyear_query = "SELECT * FROM end_year_reviews WHERE appraisal_id = :appraisal_id ORDER BY id";
$endyear_stmt = $db->prepare($endyear_query);
$endyear_stmt->bindParam(':appraisal_id', $appraisal_id);
$endyear_stmt->execute();
$endyear_reviews = $endyear_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get core competencies
$core_comp_query = "SELECT * FROM core_competencies WHERE appraisal_id = :appraisal_id ORDER BY competency_category, id";
$core_comp_stmt = $db->prepare($core_comp_query);
$core_comp_stmt->bindParam(':appraisal_id', $appraisal_id);
$core_comp_stmt->execute();
$core_competencies = $core_comp_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get non-core competencies
$non_core_comp_query = "SELECT * FROM non_core_competencies WHERE appraisal_id = :appraisal_id ORDER BY competency_category, id";
$non_core_comp_stmt = $db->prepare($non_core_comp_query);
$non_core_comp_stmt->bindParam(':appraisal_id', $appraisal_id);
$non_core_comp_stmt->execute();
$non_core_competencies = $non_core_comp_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get overall assessment
$overall_query = "SELECT * FROM overall_assessments WHERE appraisal_id = :appraisal_id";
$overall_stmt = $db->prepare($overall_query);
$overall_stmt->bindParam(':appraisal_id', $appraisal_id);
$overall_stmt->execute();
$overall_assessment = $overall_stmt->fetch(PDO::FETCH_ASSOC);

$created_message = isset($_GET['created']) ? "Appraisal created successfully!" : "";
?>

?>

<?php
$page_title = 'View Appraisal - Hospital Appraisal System';
include 'includes/header.php';
?>

    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-lg-9">
                <!-- Header -->
                <div class="appraisal-header">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1 class="h3 mb-1">Staff Performance Appraisal</h1>
                            <p class="mb-2 opacity-75">
                                <strong>Employee:</strong> 
                                <?php echo htmlspecialchars($appraisal['appraisee_title'] . ' ' . $appraisal['appraisee_fname'] . ' ' . $appraisal['appraisee_lname']); ?>
                            </p>
                            <p class="mb-0 opacity-75">
                                <strong>Period:</strong> 
                                <?php echo formatDate($appraisal['period_from']) . ' - ' . formatDate($appraisal['period_to']); ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-end">
                            <div class="mb-2">
                                <?php echo getStatusBadge($appraisal['status']); ?>
                            </div>
                            <small class="opacity-75">
                                Created: <?php echo formatDate($appraisal['created_at']); ?>
                            </small>
                        </div>
                    </div>
                </div>

                <?php if ($created_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $created_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Section 1-A: Appraisee Personal Information -->
                <div class="section-card">
                    <div class="section-header">
                        <h5 class="mb-0">Section 1-A: Appraisee Personal Information</h5>
                    </div>
                    <div class="p-4">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td class="fw-bold" width="40%">Name:</td>
                                        <td><?php echo htmlspecialchars($appraisal['appraisee_title'] . ' ' . $appraisal['appraisee_fname'] . ' ' . $appraisal['appraisee_lname']); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Other Names:</td>
                                        <td><?php echo htmlspecialchars($appraisal['appraisee_other'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Gender:</td>
                                        <td><?php echo htmlspecialchars($appraisal['appraisee_gender']); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Job Title:</td>
                                        <td><?php echo htmlspecialchars($appraisal['appraisee_job']); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td class="fw-bold" width="40%">Department:</td>
                                        <td><?php echo htmlspecialchars($appraisal['appraisee_dept']); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Grade/Salary:</td>
                                        <td><?php echo htmlspecialchars($appraisal['grade_salary'] ?? 'N/A'); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Appointment Date:</td>
                                        <td><?php echo $appraisal['appointment_date'] ? formatDate($appraisal['appointment_date']) : 'N/A'; ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Appraisal Period:</td>
                                        <td><?php echo formatDate($appraisal['period_from']) . ' - ' . formatDate($appraisal['period_to']); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Section 1-B: Appraiser Information -->
                <div class="section-card">
                    <div class="section-header">
                        <h5 class="mb-0">Section 1-B: Appraiser Information</h5>
                    </div>
                    <div class="p-4">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-borderless">
                                    <tr>
                                        <td class="fw-bold" width="40%">Name:</td>
                                        <td><?php echo htmlspecialchars($appraisal['appraiser_title'] . ' ' . $appraisal['appraiser_fname'] . ' ' . $appraisal['appraiser_lname']); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Position:</td>
                                        <td><?php echo htmlspecialchars($appraisal['appraiser_job']); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Training Records -->
                <?php if (!empty($training_records)): ?>
                <div class="section-card">
                    <div class="section-header">
                        <h5 class="mb-0">Training Received During Previous Year</h5>
                    </div>
                    <div class="p-4">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Institution</th>
                                        <th>Date</th>
                                        <th>Programme</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($training_records as $training): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($training['institution']); ?></td>
                                        <td><?php echo formatDate($training['date_completed']); ?></td>
                                        <td><?php echo htmlspecialchars($training['programme']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Section 2: Performance Planning -->
                <?php if (!empty($performance_planning)): ?>
                <div class="section-card">
                    <div class="section-header">
                        <h5 class="mb-0">Section 2: Performance Planning Form</h5>
                    </div>
                    <div class="p-4">
                        <?php foreach ($performance_planning as $index => $plan): ?>
                        <div class="row mb-4 <?php echo $index > 0 ? 'border-top pt-4' : ''; ?>">
                            <div class="col-md-4">
                                <h6 class="text-primary">Key Result Area <?php echo $index + 1; ?></h6>
                                <p><?php echo nl2br(htmlspecialchars($plan['key_result_area'])); ?></p>
                            </div>
                            <div class="col-md-4">
                                <h6 class="text-success">Targets</h6>
                                <p><?php echo nl2br(htmlspecialchars($plan['target'])); ?></p>
                            </div>
                            <div class="col-md-4">
                                <h6 class="text-info">Resources Required</h6>
                                <p><?php echo nl2br(htmlspecialchars($plan['resources_required'] ?? 'N/A')); ?></p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <?php if (!empty($performance_planning[0]['competencies_required'])): ?>
                        <div class="border-top pt-3 mt-3">
                            <h6 class="text-warning">Key Competencies Required</h6>
                            <p><?php echo nl2br(htmlspecialchars($performance_planning[0]['competencies_required'])); ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Section 3: Mid-Year Review -->
                <?php if (!empty($midyear_reviews)): ?>
                <div class="section-card">
                    <div class="section-header">
                        <h5 class="mb-0">Section 3: Mid-Year Review Form</h5>
                    </div>
                    <div class="p-4">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th width="30%">Target</th>
                                        <th width="35%">Progress Review</th>
                                        <th width="35%">Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($midyear_reviews as $review): ?>
                                    <tr>
                                        <td><?php echo nl2br(htmlspecialchars($review['target'])); ?></td>
                                        <td><?php echo nl2br(htmlspecialchars($review['progress_review'] ?? 'N/A')); ?></td>
                                        <td><?php echo nl2br(htmlspecialchars($review['remarks'] ?? 'N/A')); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Section 4: End-of-Year Review -->
                <?php if (!empty($endyear_reviews)): ?>
                <div class="section-card">
                    <div class="section-header">
                        <h5 class="mb-0">Section 4: End-of-Year Review Form</h5>
                    </div>
                    <div class="p-4">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th width="25%">Target</th>
                                        <th width="25%">Performance Assessment</th>
                                        <th width="15%">Weight</th>
                                        <th width="10%">Score</th>
                                        <th width="25%">Comments</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $total_score = 0;
                                    $count = 0;
                                    foreach ($endyear_reviews as $review): 
                                        if ($review['score']) {
                                            $total_score += $review['score'];
                                            $count++;
                                        }
                                    ?>
                                    <tr>
                                        <td><?php echo nl2br(htmlspecialchars($review['target'])); ?></td>
                                        <td><?php echo nl2br(htmlspecialchars($review['performance_assessment'] ?? 'N/A')); ?></td>
                                        <td><?php echo $review['weight_of_target']; ?></td>
                                        <td>
                                            <?php if ($review['score']): ?>
                                                <span class="competency-score score-<?php echo $review['score']; ?>">
                                                    <?php echo $review['score']; ?>
                                                </span>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo nl2br(htmlspecialchars($review['comments'] ?? 'N/A')); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-info">
                                        <td colspan="3"><strong>Average Score</strong></td>
                                        <td><strong><?php echo $count > 0 ? number_format($total_score / $count, 2) : 'N/A'; ?></strong></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Section 5: Core Competencies Assessment -->
                <?php if (!empty($core_competencies)): ?>
                <div class="section-card">
                    <div class="section-header">
                        <h5 class="mb-0">Section 5: Annual Appraisal - Core Competencies</h5>
                    </div>
                    <div class="p-4">
                        <?php 
                        $competencies = getCoreCompetencies();
                        $grouped = [];
                        foreach ($core_competencies as $comp) {
                            $grouped[$comp['competency_category']][] = $comp;
                        }
                        ?>
                        
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <strong>Rating Scale:</strong>
                                    <span class="ms-3"><span class="badge bg-danger">1</span> Unacceptable</span>
                                    <span class="ms-3"><span class="badge bg-warning">2</span> Below Expectation</span>
                                    <span class="ms-3"><span class="badge bg-warning text-dark">3</span> Meets Expectations</span>
                                    <span class="ms-3"><span class="badge bg-success">4</span> Exceeds Expectations</span>
                                    <span class="ms-3"><span class="badge bg-primary">5</span> Exceptional</span>
                                </div>
                            </div>
                        </div>

                        <?php foreach ($grouped as $category => $items): ?>
                        <div class="mb-4">
                            <h6 class="text-primary"><?php echo htmlspecialchars($competencies[$category]['title'] ?? $category); ?></h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th width="50%">Competency Item</th>
                                            <th width="15%">Weight</th>
                                            <th width="10%">Score</th>
                                            <th width="15%">Weighted Score</th>
                                            <th width="35%">Comments</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['competency_item']); ?></td>
                                            <td><?php echo $item['weight']; ?></td>
                                            <td>
                                                <span class="competency-score score-<?php echo $item['score']; ?>">
                                                    <?php echo $item['score']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo number_format($item['weighted_score'], 2); ?></td>
                                            <td><?php echo nl2br(htmlspecialchars($item['comments'] ?? '')); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Section 5: Non-Core Competencies Assessment -->
                <?php if (!empty($non_core_competencies)): ?>
                <div class="section-card">
                    <div class="section-header">
                        <h5 class="mb-0">Section 5: Non-Core Competencies Assessment</h5>
                    </div>
                    <div class="p-4">
                        <?php 
                        $non_core_comp_structure = getNonCoreCompetencies();
                        $grouped_non_core = [];
                        foreach ($non_core_competencies as $comp) {
                            $grouped_non_core[$comp['competency_category']][] = $comp;
                        }
                        ?>

                        <?php foreach ($grouped_non_core as $category => $items): ?>
                        <div class="mb-4">
                            <h6 class="text-success"><?php echo htmlspecialchars($non_core_comp_structure[$category]['title'] ?? $category); ?></h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th width="50%">Competency Item</th>
                                            <th width="15%">Weight</th>
                                            <th width="10%">Score</th>
                                            <th width="15%">Weighted Score</th>
                                            <th width="35%">Comments</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['competency_item']); ?></td>
                                            <td><?php echo $item['weight']; ?></td>
                                            <td>
                                                <span class="competency-score score-<?php echo $item['score']; ?>">
                                                    <?php echo $item['score']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo number_format($item['weighted_score'], 2); ?></td>
                                            <td><?php echo nl2br(htmlspecialchars($item['comments'] ?? '')); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Overall Assessment -->
                <?php if ($overall_assessment): ?>
                <div class="section-card">
                    <div class="section-header">
                        <h5 class="mb-0">Overall Assessment</h5>
                    </div>
                    <div class="p-4">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <table class="table table-bordered">
                                    <tr>
                                        <td class="fw-bold">Performance Assessment (M)</td>
                                        <td><?php echo number_format($overall_assessment['performance_assessment_score'], 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Core Competencies Assessment (N)</td>
                                        <td><?php echo number_format($overall_assessment['core_competencies_score'], 2); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="fw-bold">Non-Core Competencies Assessment (O)</td>
                                        <td><?php echo number_format($overall_assessment['non_core_competencies_score'], 2); ?></td>
                                    </tr>
                                    <tr class="table-primary">
                                        <td class="fw-bold">Overall Total</td>
                                        <td class="fw-bold"><?php echo number_format($overall_assessment['overall_total'], 2); ?></td>
                                    </tr>
                                    <tr class="table-success">
                                        <td class="fw-bold">Overall Percentage</td>
                                        <td class="fw-bold"><?php echo number_format($overall_assessment['overall_percentage'], 2); ?>%</td>
                                    </tr>
                                    <tr class="table-warning">
                                        <td class="fw-bold">Rating</td>
                                        <td class="fw-bold">
                                            <span class="competency-score score-<?php echo $overall_assessment['overall_rating']; ?>">
                                                <?php echo $overall_assessment['overall_rating']; ?>
                                            </span>
                                            <?php echo htmlspecialchars($overall_assessment['rating_description']); ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-info">
                                    <h6>Rating Scale:</h6>
                                    <?php $scale = getOverallRatingScale(); ?>
                                    <ul class="mb-0">
                                        <?php foreach ($scale as $rating => $info): ?>
                                        <li><strong><?php echo $rating; ?> (<?php echo $info['range']; ?>):</strong> <?php echo $info['title']; ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <?php if ($overall_assessment['appraiser_comments']): ?>
                        <div class="mb-3">
                            <h6>Appraiser's Comments on Performance Plan Achievements</h6>
                            <div class="bg-light p-3 rounded">
                                <?php echo nl2br(htmlspecialchars($overall_assessment['appraiser_comments'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($overall_assessment['career_development_plan']): ?>
                        <div class="mb-3">
                            <h6>Career Development - Training and Development Plan</h6>
                            <div class="bg-light p-3 rounded">
                                <?php echo nl2br(htmlspecialchars($overall_assessment['career_development_plan'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="row">
                            <div class="col-md-6">
                                <h6>Assessment Decision</h6>
                                <div class="bg-light p-3 rounded">
                                    <?php
                                    $promotion_texts = [
                                        'outstanding' => 'Outstanding – should be promoted as soon as possible',
                                        'suitable' => 'Suitable for promotion',
                                        'likely_2_3_years' => 'Likely to be ready for promotion in 2 to 3 years',
                                        'not_ready_3_years' => 'Not ready for promotion for at least 3 years',
                                        'unlikely' => 'Unlikely to be promoted further'
                                    ];
                                    echo htmlspecialchars($promotion_texts[$overall_assessment['promotion_assessment']] ?? 'Not specified');
                                    ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($overall_assessment['appraisee_comments']): ?>
                        <div class="mt-4">
                            <h6>Appraisee's Comments</h6>
                            <div class="bg-light p-3 rounded">
                                <?php echo nl2br(htmlspecialchars($overall_assessment['appraisee_comments'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($overall_assessment['hod_comments']): ?>
                        <div class="mt-3">
                            <h6>Head of Department's Comments</h6>
                            <div class="bg-light p-3 rounded">
                                <?php echo nl2br(htmlspecialchars($overall_assessment['hod_comments'])); ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Signature Section -->
                        <div class="row mt-4 pt-4 border-top">
                            <div class="col-md-4 text-center">
                                <div class="mb-2">
                                    <strong>Appraisee's Signature</strong>
                                </div>
                                <div class="border-bottom" style="height: 50px;"></div>
                                <small class="text-muted">
                                    Date: <?php echo $overall_assessment['appraisee_signature_date'] ? formatDate($overall_assessment['appraisee_signature_date']) : '___________'; ?>
                                </small>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="mb-2">
                                    <strong>Appraiser's Signature</strong>
                                </div>
                                <div class="border-bottom" style="height: 50px;"></div>
                                <small class="text-muted">
                                    Date: <?php echo $overall_assessment['appraiser_signature_date'] ? formatDate($overall_assessment['appraiser_signature_date']) : '___________'; ?>
                                </small>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="mb-2">
                                    <strong>HOD's Signature</strong>
                                </div>
                                <div class="border-bottom" style="height: 50px;"></div>
                                <small class="text-muted">
                                    Date: <?php echo $overall_assessment['hod_signature_date'] ? formatDate($overall_assessment['hod_signature_date']) : '___________'; ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar with Actions and Status -->
            <div class="col-lg-3">
                <div class="action-buttons no-print">
                    <h6 class="mb-3">
                        <i class="fas fa-cog me-2"></i>Actions
                    </h6>
                    
                    <?php if ($user_role == 'appraiser' || $user_role == 'admin'): ?>
                    <div class="d-grid gap-2 mb-3">
                        <?php if (in_array($appraisal['status'], ['draft', 'planning'])): ?>
                        <a href="edit_appraisal.php?id=<?php echo $appraisal_id; ?>" class="btn btn-primary">
                            <i class="fas fa-edit me-2"></i>Edit Appraisal
                        </a>
                        <a href="performance_planning.php?id=<?php echo $appraisal_id; ?>" class="btn btn-success">
                            <i class="fas fa-bullseye me-2"></i>Performance Planning
                        </a>
                        <?php endif; ?>
                        
                        <?php if ($appraisal['status'] == 'planning'): ?>
                        <a href="mid_year_review.php?id=<?php echo $appraisal_id; ?>" class="btn btn-warning">
                            <i class="fas fa-clock me-2"></i>Mid-Year Review
                        </a>
                        <?php endif; ?>
                        
                        <?php if (in_array($appraisal['status'], ['mid_review', 'final_review'])): ?>
                        <a href="final_appraisal.php?id=<?php echo $appraisal_id; ?>" class="btn btn-info">
                            <i class="fas fa-clipboard-check me-2"></i>Final Appraisal
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="d-grid gap-2 mb-3">
                        <button onclick="window.print()" class="btn btn-outline-primary">
                            <i class="fas fa-print me-2"></i>Print
                        </button>
                        <a href="dashboard.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>

                    <!-- Status Timeline -->
                    <div class="mt-4">
                        <h6 class="mb-3">
                            <i class="fas fa-tasks me-2"></i>Progress Status
                        </h6>
                        <div class="status-timeline">
                            <div class="status-step <?php echo in_array($appraisal['status'], ['draft']) ? 'current' : (in_array($appraisal['status'], ['planning', 'mid_review', 'final_review', 'completed']) ? 'active' : ''); ?>">
                                <strong>Draft Created</strong>
                                <br><small class="text-muted">Initial appraisal setup</small>
                            </div>
                            <div class="status-step <?php echo in_array($appraisal['status'], ['planning']) ? 'current' : (in_array($appraisal['status'], ['mid_review', 'final_review', 'completed']) ? 'active' : ''); ?>">
                                <strong>Performance Planning</strong>
                                <br><small class="text-muted">Set targets and goals</small>
                            </div>
                            <div class="status-step <?php echo in_array($appraisal['status'], ['mid_review']) ? 'current' : (in_array($appraisal['status'], ['final_review', 'completed']) ? 'active' : ''); ?>">
                                <strong>Mid-Year Review</strong>
                                <br><small class="text-muted">July progress review</small>
                            </div>
                            <div class="status-step <?php echo in_array($appraisal['status'], ['final_review']) ? 'current' : (in_array($appraisal['status'], ['completed']) ? 'active' : ''); ?>">
                                <strong>Final Review</strong>
                                <br><small class="text-muted">End of year assessment</small>
                            </div>
                            <div class="status-step <?php echo $appraisal['status'] == 'completed' ? 'current active' : ''; ?>">
                                <strong>Completed</strong>
                                <br><small class="text-muted">Appraisal finalized</small>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <?php if (!empty($performance_planning) || !empty($core_competencies)): ?>
                    <div class="mt-4">
                        <h6 class="mb-3">
                            <i class="fas fa-chart-pie me-2"></i>Quick Stats
                        </h6>
                        <div class="bg-light p-3 rounded">
                            <?php if (!empty($performance_planning)): ?>
                            <div class="mb-2">
                                <small class="text-muted">Key Result Areas:</small>
                                <strong class="float-end"><?php echo count($performance_planning); ?></strong>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($core_competencies)): ?>
                            <div class="mb-2">
                                <small class="text-muted">Core Competencies:</small>
                                <strong class="float-end"><?php echo count($core_competencies); ?></strong>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($non_core_competencies)): ?>
                            <div class="mb-2">
                                <small class="text-muted">Non-Core Competencies:</small>
                                <strong class="float-end"><?php echo count($non_core_competencies); ?></strong>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($overall_assessment): ?>
                            <hr>
                            <div class="text-center">
                                <div class="h4 mb-1"><?php echo number_format($overall_assessment['overall_percentage'], 1); ?>%</div>
                                <small class="text-muted">Overall Score</small>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php include 'includes/footer.php'; ?>
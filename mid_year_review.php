<?php
session_start();
require_once 'config/database.php';

checkSession();

$database = new DatabaseConfig();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Get appraisal ID from URL
$appraisal_id = $_GET['id'] ?? null;
if (!$appraisal_id) {
    header("Location: dashboard.php");
    exit();
}

// Get appraisal details
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

// Get performance planning data
$planning_query = "SELECT * FROM performance_planning WHERE appraisal_id = :appraisal_id ORDER BY id";
$planning_stmt = $db->prepare($planning_query);
$planning_stmt->bindParam(':appraisal_id', $appraisal_id);
$planning_stmt->execute();
$performance_planning = $planning_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get existing mid-year reviews
$midyear_query = "SELECT * FROM mid_year_reviews WHERE appraisal_id = :appraisal_id ORDER BY id";
$midyear_stmt = $db->prepare($midyear_query);
$midyear_stmt->bindParam(':appraisal_id', $appraisal_id);
$midyear_stmt->execute();
$existing_reviews = $midyear_stmt->fetchAll(PDO::FETCH_ASSOC);

$message = '';
$error = '';

// Handle form submission
if ($_POST && isset($_POST['save_midyear_review'])) {
    try {
        $db->beginTransaction();
        
        // Delete existing mid-year reviews
        $delete_query = "DELETE FROM mid_year_reviews WHERE appraisal_id = :appraisal_id";
        $delete_stmt = $db->prepare($delete_query);
        $delete_stmt->bindParam(':appraisal_id', $appraisal_id);
        $delete_stmt->execute();
        
        // Insert new mid-year reviews
        if (!empty($_POST['target'])) {
            foreach ($_POST['target'] as $index => $target) {
                if (!empty($target)) {
                    $insert_query = "INSERT INTO mid_year_reviews (appraisal_id, target, progress_review, remarks, review_date) 
                                   VALUES (:appraisal_id, :target, :progress_review, :remarks, :review_date)";
                    $insert_stmt = $db->prepare($insert_query);
                    $insert_stmt->bindParam(':appraisal_id', $appraisal_id);
                    $insert_stmt->bindParam(':target', $target);
                    $insert_stmt->bindParam(':progress_review', $_POST['progress_review'][$index] ?? '');
                    $insert_stmt->bindParam(':remarks', $_POST['remarks'][$index] ?? '');
                    $insert_stmt->bindParam(':review_date', $_POST['review_date'] ?? date('Y-m-d'));
                    $insert_stmt->execute();
                }
            }
        }
        
        // Update appraisal status to mid_review
        $status_query = "UPDATE appraisals SET status = 'mid_review' WHERE id = :appraisal_id";
        $status_stmt = $db->prepare($status_query);
        $status_stmt->bindParam(':appraisal_id', $appraisal_id);
        $status_stmt->execute();
        
        $db->commit();
        $message = "Mid-year review saved successfully!";
        
        // Redirect to view the appraisal
        header("Location: view_appraisal.php?id=" . $appraisal_id);
        exit();
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mid-Year Review - Hospital Appraisal System</title>
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
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 15px 15px 0 0;
            margin: 0;
        }
        .review-row {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-1">Mid-Year Review</h1>
                        <p class="text-muted mb-0">
                            Progress review for: 
                            <strong><?php echo htmlspecialchars($appraisal['appraisee_fname'] . ' ' . $appraisal['appraisee_lname']); ?></strong>
                        </p>
                    </div>
                    <div>
                        <a href="view_appraisal.php?id=<?php echo $appraisal_id; ?>" class="btn btn-outline-secondary me-2">
                            <i class="fas fa-eye me-2"></i>View Full Appraisal
                        </a>
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

                <div class="form-card">
                    <div class="section-header">
                        <h5 class="mb-0">
                            <i class="fas fa-clock me-2"></i>Section 3: Mid-Year Review Form
                        </h5>
                        <small class="opacity-75">This is to be completed in July by the Appraiser and Appraisee</small>
                    </div>
                    <div class="p-4">
                        <div class="alert alert-info mb-4">
                            <h6><i class="fas fa-info-circle me-2"></i>Purpose of Mid-Year Review</h6>
                            <p class="mb-2">The mid-year review provides a formal mechanism to:</p>
                            <ul class="mb-0">
                                <li>Review progress on targets set at the beginning of the year</li>
                                <li>Discuss challenges and obstacles encountered</li>
                                <li>Make necessary adjustments to targets or resources</li>
                                <li>Provide feedback and support for improvement</li>
                            </ul>
                        </div>

                        <form method="POST" id="midYearForm">
                            <div class="mb-4">
                                <label for="review_date" class="form-label">Review Date</label>
                                <input type="date" class="form-control" id="review_date" name="review_date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>

                            <h6 class="mb-3">Progress has been discussed and Agreements have been reached as detailed below:</h6>

                            <?php if (!empty($performance_planning)): ?>
                                <?php foreach ($performance_planning as $index => $plan): ?>
                                <div class="review-row">
                                    <h6 class="text-primary mb-3">Target <?php echo $index + 1; ?></h6>
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label fw-bold">Target</label>
                                            <textarea class="form-control" name="target[]" rows="4" readonly><?php echo htmlspecialchars($plan['target']); ?></textarea>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Progress Review</label>
                                            <textarea class="form-control" name="progress_review[]" rows="4" 
                                                     placeholder="Describe progress made towards this target. What has been achieved? What challenges have been encountered?"><?php echo isset($existing_reviews[$index]) ? htmlspecialchars($existing_reviews[$index]['progress_review']) : ''; ?></textarea>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label class="form-label">Remarks</label>
                                            <textarea class="form-control" name="remarks[]" rows="4" 
                                                     placeholder="Agreements reached, adjustments made, or additional support needed"><?php echo isset($existing_reviews[$index]) ? htmlspecialchars($existing_reviews[$index]['remarks']) : ''; ?></textarea>
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

                            <?php if (!empty($performance_planning)): ?>
                            <div class="mt-4">
                                <h6 class="mb-3">Competency Progress Review (Optional)</h6>
                                <div class="alert alert-light">
                                    <p class="mb-2"><strong>Key Competencies Required:</strong></p>
                                    <p><?php echo nl2br(htmlspecialchars($performance_planning[0]['competencies_required'] ?? 'Not specified')); ?></p>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Progress on Competency Development</label>
                                    <textarea class="form-control" name="competency_progress" rows="3" 
                                             placeholder="Discuss progress in developing required competencies"></textarea>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Action Buttons -->
                            <div class="mt-4 pt-3 border-top">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="alert alert-warning">
                                            <i class="fas fa-lightbulb me-2"></i>
                                            <strong>Remember:</strong>
                                            <ul class="mb-0 mt-2">
                                                <li>Be honest about progress and challenges</li>
                                                <li>Focus on constructive feedback</li>
                                                <li>Document any agreed changes to targets</li>
                                                <li>Both appraiser and appraisee should discuss and agree</li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="col-md-6 d-flex align-items-center justify-content-end">
                                        <div class="d-grid gap-2 d-md-flex">
                                            <a href="view_appraisal.php?id=<?php echo $appraisal_id; ?>" class="btn btn-outline-secondary">
                                                <i class="fas fa-times me-2"></i>Cancel
                                            </a>
                                            <button type="submit" name="save_midyear_review" class="btn btn-warning btn-lg">
                                                <i class="fas fa-save me-2"></i>Save Mid-Year Review
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Guidelines Card -->
                <div class="form-card">
                    <div class="section-header" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);">
                        <h5 class="mb-0">
                            <i class="fas fa-book me-2"></i>Mid-Year Review Guidelines
                        </h5>
                    </div>
                    <div class="p-4">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-info">Before the Meeting</h6>
                                <ol>
                                    <li>Give at least one week's notice to the appraisee</li>
                                    <li>Review the performance planning form</li>
                                    <li>Prepare notes on progress and challenges</li>
                                    <li>Appraisee should review their own performance</li>
                                </ol>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-info">During the Meeting</h6>
                                <ol>
                                    <li>Discuss progress on each target systematically</li>
                                    <li>Listen to appraisee's perspective and challenges</li>
                                    <li>Agree on any necessary adjustments to targets</li>
                                    <li>Identify support needed for second half of year</li>
                                </ol>
                            </div>
                        </div>
                        <div class="mt-3">
                            <h6 class="text-info">After the Meeting</h6>
                            <ul>
                                <li>Fill out this Mid-Year Review Form within three working days</li>
                                <li>Both appraiser and appraisee should review and sign</li>
                                <li>Keep copies for both parties</li>
                                <li>Send original document to HR</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.getElementById('midYearForm').addEventListener('submit', function(e) {
            const progressReviews = document.querySelectorAll('textarea[name="progress_review[]"]');
            let allFilled = true;
            
            progressReviews.forEach(textarea => {
                if (!textarea.value.trim()) {
                    allFilled = false;
                }
            });
            
            if (!allFilled) {
                if (!confirm('Some progress reviews are empty. Do you want to continue anyway?')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    </script>
</body>
</html>
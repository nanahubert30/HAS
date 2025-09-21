<?php
session_start();
require_once 'config/database.php';

checkSession();

// Only appraisers and admins can create appraisals
if (!in_array($_SESSION['role'], ['admin', 'appraiser'])) {
    header("Location: dashboard.php");
    exit();
}

$database = new DatabaseConfig();
$db = $database->getConnection();

$message = '';
$error = '';

// Get list of users who can be appraised (appraisees)
$query = "SELECT id, title, first_name, last_name, job_title, department FROM users WHERE role = 'appraisee' ORDER BY first_name, last_name";
$stmt = $db->prepare($query);
$stmt->execute();
$appraisees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_POST && isset($_POST['create_appraisal'])) {
    try {
        $appraisee_id = $_POST['appraisee_id'];
        $appraiser_id = $_SESSION['user_id'];
        $period_from = $_POST['period_from'];
        $period_to = $_POST['period_to'];
        
        // Validate dates
        if (strtotime($period_from) >= strtotime($period_to)) {
            throw new Exception("Period 'from' date must be before 'to' date.");
        }
        
        // Check if appraisal already exists for this period
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
        
        // Create new appraisal
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
        
        // If performance planning data is provided, save it
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
            
            // Update status to planning if performance planning was added
            $update_query = "UPDATE appraisals SET status = 'planning' WHERE id = :appraisal_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':appraisal_id', $appraisal_id);
            $update_stmt->execute();
        }
        
        // If training records are provided, save them
        if (!empty($_POST['training_institution']) && is_array($_POST['training_institution'])) {
            for ($i = 0; $i < count($_POST['training_institution']); $i++) {
                if (!empty($_POST['training_institution'][$i]) && !empty($_POST['training_programme'][$i])) {
                    $training_query = "INSERT INTO training_records (user_id, institution, programme, date_completed, appraisal_id) 
                                     VALUES (:user_id, :institution, :programme, :date_completed, :appraisal_id)";
                    $training_stmt = $db->prepare($training_query);
                    $training_stmt->bindParam(':user_id', $appraisee_id);
                    $training_stmt->bindParam(':institution', $_POST['training_institution'][$i]);
                    $training_stmt->bindParam(':programme', $_POST['training_programme'][$i]);
                    $training_stmt->bindParam(':date_completed', $_POST['training_date'][$i] ?? null);
                    $training_stmt->bindParam(':appraisal_id', $appraisal_id);
                    $training_stmt->execute();
                }
            }
        }
        
        $db->commit();
        $message = "Appraisal created successfully!";
        
        // Redirect to view the created appraisal
        header("Location: view_appraisal.php?id=" . $appraisal_id . "&created=1");
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
    <title>Create New Appraisal - Hospital Appraisal System</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .form-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .section-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
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
        .performance-row {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            margin-bottom: 1rem;
            padding: 1rem;
        }
        .training-row {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            margin-bottom: 1rem;
            padding: 1rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h1 class="h3 mb-1">Create New Appraisal</h1>
                        <p class="text-muted mb-0">Staff Performance Planning, Review and Appraisal Form</p>
                    </div>
                    <a href="dashboard.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                    </a>
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
                                            <?php echo htmlspecialchars(($employee['title'] ?? '') . ' ' . $employee['first_name'] . ' ' . $employee['last_name'] . ' - ' . ($employee['job_title'] ?? 'N/A') . ' (' . ($employee['department'] ?? 'N/A') . ')'); ?>
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
                                    // Get current user's job title
                                    $user_query = "SELECT job_title FROM users WHERE id = :user_id";
                                    $user_stmt = $db->prepare($user_query);
                                    $user_stmt->bindParam(':user_id', $_SESSION['user_id']);
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

                        <!-- Action Buttons -->
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
            const periodFrom = new Date(document.getElementById('period_from').value);
            const periodTo = new Date(document.getElementById('period_to').value);
            
            if (periodFrom >= periodTo) {
                e.preventDefault();
                alert('Period "from" date must be before "to" date.');
                return false;
            }
            
            // Check if at least one key result area is filled
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
        });

        // Set default dates (current year)
        document.addEventListener('DOMContentLoaded', function() {
            const currentYear = new Date().getFullYear();
            document.getElementById('period_from').value = currentYear + '-01-01';
            document.getElementById('period_to').value = currentYear + '-12-31';
        });
    </script>
</body>
</html>
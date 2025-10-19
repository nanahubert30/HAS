<?php
/**
 * Helper Functions for Hospital Appraisal System
 */

/**
 * Check if user is logged in
 */
function checkSession() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header("Location: login.php");
        exit();
    }
}

/**
 * Calculate overall rating based on percentage
 * @param float $percentage Overall percentage score
 * @return int Rating from 1-5
 */
function calculateOverallRating($percentage) {
    if ($percentage >= 80) {
        return 5; // Exceptional
    } elseif ($percentage >= 65) {
        return 4; // Exceeds Expectations
    } elseif ($percentage >= 50) {
        return 3; // Meets Expectations
    } elseif ($percentage >= 40) {
        return 2; // Below Expectation
    } else {
        return 1; // Unacceptable
    }
}

/**
 * Get rating description text
 * @param int $rating Rating value (1-5)
 * @return string Rating description
 */
function getRatingDescription($rating) {
    $descriptions = [
        1 => 'Unacceptable',
        2 => 'Below Expectation',
        3 => 'Meets Expectations',
        4 => 'Exceeds Expectations',
        5 => 'Exceptional'
    ];
    
    return $descriptions[$rating] ?? 'Not Rated';
}

/**
 * Get core competencies structure
 * @return array Core competencies by category
 */
function getCoreCompetencies() {
    return [
        'job_knowledge' => [
            'title' => 'Job Knowledge',
            'items' => [
                'Demonstrates understanding of job responsibilities and requirements',
                'Applies relevant knowledge and skills effectively',
                'Keeps up-to-date with developments in their field',
                'Shares knowledge and expertise with colleagues'
            ]
        ],
        'quality_of_work' => [
            'title' => 'Quality of Work',
            'items' => [
                'Produces accurate and thorough work',
                'Meets or exceeds quality standards',
                'Pays attention to detail',
                'Takes pride in work output'
            ]
        ],
        'productivity' => [
            'title' => 'Productivity',
            'items' => [
                'Completes work in a timely manner',
                'Manages time effectively',
                'Handles workload efficiently',
                'Meets deadlines consistently'
            ]
        ],
        'communication' => [
            'title' => 'Communication Skills',
            'items' => [
                'Communicates clearly and effectively',
                'Listens actively to others',
                'Provides constructive feedback',
                'Maintains professional correspondence'
            ]
        ],
        'teamwork' => [
            'title' => 'Teamwork and Collaboration',
            'items' => [
                'Works cooperatively with team members',
                'Supports colleagues when needed',
                'Contributes positively to team goals',
                'Resolves conflicts constructively'
            ]
        ],
        'initiative' => [
            'title' => 'Initiative and Innovation',
            'items' => [
                'Takes initiative to solve problems',
                'Suggests improvements to processes',
                'Adapts to new situations',
                'Shows creativity in approach to work'
            ]
        ],
        'reliability' => [
            'title' => 'Reliability and Dependability',
            'items' => [
                'Consistently meets commitments',
                'Follows through on assignments',
                'Maintains regular attendance',
                'Can be counted on in critical situations'
            ]
        ]
    ];
}

/**
 * Get non-core competencies structure
 * @return array Non-core competencies by category
 */
function getNonCoreCompetencies() {
    return [
        'professionalism' => [
            'title' => 'Professionalism',
            'items' => [
                'Maintains professional demeanor',
                'Adheres to hospital policies and procedures',
                'Dresses appropriately for role',
                'Represents hospital positively'
            ]
        ],
        'customer_service' => [
            'title' => 'Customer Service (Patient Care)',
            'items' => [
                'Treats patients/clients with respect and compassion',
                'Responds promptly to patient/client needs',
                'Maintains patient confidentiality',
                'Handles difficult situations professionally'
            ]
        ],
        'continuous_learning' => [
            'title' => 'Continuous Learning and Development',
            'items' => [
                'Participates in training and development opportunities',
                'Seeks feedback for improvement',
                'Demonstrates willingness to learn new skills',
                'Applies learning to improve performance'
            ]
        ],
        'safety_compliance' => [
            'title' => 'Safety and Compliance',
            'items' => [
                'Follows safety protocols and procedures',
                'Maintains a safe work environment',
                'Reports safety concerns promptly',
                'Complies with regulatory requirements'
            ]
        ],
        'leadership' => [
            'title' => 'Leadership (where applicable)',
            'items' => [
                'Demonstrates leadership qualities',
                'Mentors or guides others',
                'Takes responsibility for decisions',
                'Motivates and inspires team members'
            ]
        ]
    ];
}

/**
 * Sanitize input data
 * @param string $data Input data to sanitize
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Format date for display
 * @param string $date Date string
 * @param string $format Desired format
 * @return string Formatted date
 */
function formatDate($date, $format = 'F j, Y') {
    if (empty($date) || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') {
        return 'N/A';
    }
    return date($format, strtotime($date));
}

/**
 * Get appraisal status badge HTML
 * @param string $status Appraisal status
 * @return string HTML badge
 */
function getStatusBadge($status) {
    $badges = [
        'pending' => '<span class="badge bg-warning">Pending</span>',
        'in_progress' => '<span class="badge bg-info">In Progress</span>',
        'completed' => '<span class="badge bg-success">Completed</span>',
        'approved' => '<span class="badge bg-primary">Approved</span>',
        'rejected' => '<span class="badge bg-danger">Rejected</span>'
    ];
    
    return $badges[$status] ?? '<span class="badge bg-secondary">Unknown</span>';
}

/**
 * Generate unique appraisal reference number
 * @return string Reference number
 */
function generateAppraisalReference() {
    return 'APR-' . date('Y') . '-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 8));
}

/**
 * Check if user has permission to access appraisal
 * @param int $user_id Current user ID
 * @param string $role User role
 * @param array $appraisal Appraisal data
 * @return bool Has permission
 */
function hasAppraisalPermission($user_id, $role, $appraisal) {
    if ($role == 'admin') {
        return true;
    }
    
    if ($role == 'appraiser' && $user_id == $appraisal['appraiser_id']) {
        return true;
    }
    
    if ($role == 'appraisee' && $user_id == $appraisal['appraisee_id']) {
        return true;
    }
    
    return false;
}

/**
 * Log activity
 * @param PDO $db Database connection
 * @param int $user_id User ID
 * @param string $action Action performed
 * @param string $details Additional details
 */
function logActivity($db, $user_id, $action, $details = '') {
    try {
        $query = "INSERT INTO activity_logs (user_id, action, details, ip_address, created_at) 
                  VALUES (:user_id, :action, :details, :ip_address, NOW())";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':details', $details);
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $stmt->bindParam(':ip_address', $ip_address);
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

/**
 * Send email notification
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email message
 * @return bool Success status
 */
function sendEmailNotification($to, $subject, $message) {
    $headers = "From: Hospital Appraisal System <noreply@hospital.com>\r\n";
    $headers .= "Reply-To: noreply@hospital.com\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    return mail($to, $subject, $message, $headers);
}

/**
 * Get appraisal period name
 * @param int $year Year
 * @return string Period name
 */
function getAppraisalPeriod($year = null) {
    if ($year === null) {
        $year = date('Y');
    }
    return "Annual Appraisal $year";
}

/**
 * Calculate competency average
 * @param array $scores Array of scores
 * @return float Average score
 */
function calculateAverage($scores) {
    $valid_scores = array_filter($scores, function($score) {
        return !empty($score) && is_numeric($score);
    });
    
    if (empty($valid_scores)) {
        return 0;
    }
    
    return array_sum($valid_scores) / count($valid_scores);
}

/**
 * Validate score range
 * @param mixed $score Score value
 * @param int $min Minimum value
 * @param int $max Maximum value
 * @return bool Is valid
 */
function isValidScore($score, $min = 1, $max = 5) {
    return is_numeric($score) && $score >= $min && $score <= $max;
}

/**
 * Get rating color class
 * @param int $rating Rating value
 * @return string Bootstrap color class
 */
function getRatingColorClass($rating) {
    $colors = [
        1 => 'danger',
        2 => 'warning',
        3 => 'info',
        4 => 'success',
        5 => 'primary'
    ];
    
    return $colors[$rating] ?? 'secondary';
}

/**
 * Format percentage
 * @param float $value Value to format
 * @param int $decimals Number of decimal places
 * @return string Formatted percentage
 */
function formatPercentage($value, $decimals = 1) {
    return number_format($value, $decimals) . '%';
}

/**
 * Format score
 * @param float $value Score value
 * @param int $decimals Number of decimal places
 * @return string Formatted score
 */
function formatScore($value, $decimals = 2) {
    return number_format($value, $decimals);
}

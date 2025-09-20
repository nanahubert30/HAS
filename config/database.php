<?php
// config/database.php - Place this file in a 'config' folder

class DatabaseConfig {
    private $host = "localhost";
    private $db_name = "hospital_appraisal";
    private $username = "root";
    private $password = "";
    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->exec("set names utf8mb4");
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $exception) {
            // If database doesn't exist (error 1049), redirect to setup
            if ($exception->getCode() == 1049) {
                // Check if we're not already on setup page to avoid redirect loop
                $current_page = basename($_SERVER['PHP_SELF']);
                if ($current_page !== 'setup.php' && $current_page !== 'index.php') {
                    header("Location: setup.php");
                    exit();
                }
            }
            throw new Exception("Connection error: " . $exception->getMessage());
        }
        return $this->conn;
    }

    // Method to check if database exists
    public function databaseExists() {
        try {
            $testConn = new PDO("mysql:host=" . $this->host, $this->username, $this->password);
            $stmt = $testConn->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$this->db_name'");
            return $stmt->rowCount() > 0;
        } catch(PDOException $e) {
            return false;
        }
    }
}

// Common functions - MUST BE DEFINED HERE
function checkSession() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit();
    }
}

function getStatusBadge($status) {
    $status_classes = [
        'draft' => 'bg-secondary',
        'planning' => 'bg-info',
        'mid_review' => 'bg-warning',
        'final_review' => 'bg-primary',
        'completed' => 'bg-success'
    ];
    
    $status_text = ucfirst(str_replace('_', ' ', $status));
    $class = $status_classes[$status] ?? 'bg-secondary';
    
    return "<span class='badge $class'>$status_text</span>";
}

function formatDate($date, $format = 'M j, Y') {
    if (empty($date) || $date === '0000-00-00') return 'N/A';
    return date($format, strtotime($date));
}

function getUserInitials($firstName, $lastName) {
    return strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
}

// Core competencies structure
function getCoreCompetencies() {
    return [
        'organisation_management' => [
            'title' => 'Organisation and Management',
            'items' => [
                'Ability to plan, organise and manage work load.',
                'Ability to work systematically and maintain quality.',
                'Ability to manage others to achieve shared goals.'
            ]
        ],
        'innovation_strategic' => [
            'title' => 'Innovation and Strategic Thinking',
            'items' => [
                'Support for organisational change',
                'Ability to think broadly and demonstrate creativity.',
                'Originality in thinking'
            ]
        ],
        'leadership_decision' => [
            'title' => 'Leadership and Decision Making',
            'items' => [
                'Ability to initiate action and provide direction to others',
                'Accept responsibility and decision-making.',
                'Ability to exercise good judgment'
            ]
        ],
        'developing_improving' => [
            'title' => 'Developing and Improving',
            'items' => [
                'Commitment to organization development',
                'Commitment to customer satisfaction',
                'Commitment to personnel development'
            ]
        ],
        'communication' => [
            'title' => 'Communication (oral, written & electronic)',
            'items' => [
                'Ability to communicate decisions clearly and fluently',
                'Ability to negotiate and manage conflict effectively.',
                'Ability to relate and network across different levels and departments'
            ]
        ],
        'job_knowledge' => [
            'title' => 'Job Knowledge and Technical Skills',
            'items' => [
                'Demonstration of correct mental, physical and manual skills.',
                'Demonstration of cross-functional awareness.',
                'Building, applying and sharing of necessary expertise and technology.'
            ]
        ],
        'supporting_cooperating' => [
            'title' => 'Supporting and Cooperating',
            'items' => [
                'Ability to work effectively with teams, clients and staff.',
                'Ability to show support to others.',
                'Ability to adhere to organisation\'s principles, ethics and values.'
            ]
        ],
        'maximising_productivity' => [
            'title' => 'Maximising and maintaining Productivity',
            'items' => [
                'Ability to motivate and inspire others.',
                'Ability to accept challenges and execute them with confidence.',
                'Ability to manage pressure and setbacks effectively.'
            ]
        ],
        'budget_management' => [
            'title' => 'Developing / Managing budgets and saving cost',
            'items' => [
                'Firm awareness of financial issues and accountabilities.',
                'Understanding of business processes and customer priorities.',
                'Executing result-based actions.'
            ]
        ]
    ];
}

// Non-core competencies structure
function getNonCoreCompetencies() {
    return [
        'develop_staff' => [
            'title' => 'Ability to Develop Staff',
            'items' => [
                'Able to develop others (subordinates).',
                'Able to provide guidance and support to staff for their development.'
            ]
        ],
        'personal_development' => [
            'title' => 'Commitment to Own Personal Development and Training',
            'items' => [
                'Eagerness for self development.',
                'Inner drive to supplement training from organization.'
            ]
        ],
        'customer_satisfaction' => [
            'title' => 'Delivering Results and Ensuring Customer Satisfaction',
            'items' => [
                'Ensuring customer satisfaction.',
                'Ensuring the delivery of quality service and products.'
            ]
        ],
        'following_instructions' => [
            'title' => 'Following Instructions and Working Towards Organisational Goals',
            'items' => [
                'Keeping to laid-down regulations and procedures.',
                'Willingness to act on \'customer feedback\' for customer satisfaction.'
            ]
        ],
        'respect_commitment' => [
            'title' => 'Respect and Commitment',
            'items' => [
                'Respect for superiors, colleagues and customers.',
                'Commitment to work and Organisational Development.'
            ]
        ],
        'team_work' => [
            'title' => 'Ability to Work Effectively in a Team',
            'items' => [
                'Ability to function in a team.',
                'Ability to work in a team.'
            ]
        ]
    ];
}

// Rating descriptions
function getRatingDescriptions() {
    return [
        1 => [
            'title' => 'Unacceptable',
            'description' => 'Has not at all demonstrated this behavior competency and three (3) or more examples can be evidenced to support this rating.',
            'performance' => 'Failed to meet performance standards.'
        ],
        2 => [
            'title' => 'Below Expectation',
            'description' => 'Has rarely demonstrated this behavior competency and two (2) or more examples can be evidenced to support this rating.',
            'performance' => 'Performance fell short of expected standards.'
        ],
        3 => [
            'title' => 'Meets Expectations',
            'description' => 'Has demonstrated this behavior competency and at least two (2) examples can be evidenced to support this rating.',
            'performance' => 'Performance met the expected standards.'
        ],
        4 => [
            'title' => 'Exceeds Expectations',
            'description' => 'Has frequently demonstrated this behavior competency and sometimes encouraged others to do same.',
            'performance' => 'Performance consistently exceeded expectations.'
        ],
        5 => [
            'title' => 'Exceptional, exceeds expectations',
            'description' => 'Has consistently demonstrated this behavior competency and always encouraged others to do same.',
            'performance' => 'Performance was exceptional and consistently far exceeded expectations.'
        ]
    ];
}

// Overall rating scale
function getOverallRatingScale() {
    return [
        1 => ['range' => '40% & below', 'title' => 'Unacceptable'],
        2 => ['range' => '49-41%', 'title' => 'Below Expectation'],
        3 => ['range' => '64-50%', 'title' => 'Met all Expectations'],
        4 => ['range' => '79-65%', 'title' => 'Exceeded Expectations'],
        5 => ['range' => '80% above', 'title' => 'Exceptional, exceeded expectations']
    ];
}

// Calculate overall rating based on percentage
function calculateOverallRating($percentage) {
    if ($percentage >= 80) return 5;
    if ($percentage >= 65) return 4;
    if ($percentage >= 50) return 3;
    if ($percentage >= 41) return 2;
    return 1;
}

// Get rating description
function getRatingDescription($rating) {
    $descriptions = [
        1 => 'Unacceptable',
        2 => 'Below Expectation',
        3 => 'Met all Expectations',
        4 => 'Exceeded Expectations',
        5 => 'Exceptional, exceeded expectations'
    ];
    return $descriptions[$rating] ?? 'Unknown';
}
?>
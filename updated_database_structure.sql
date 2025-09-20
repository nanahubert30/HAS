-- Add staff_id column to existing users table
ALTER TABLE users ADD COLUMN staff_id VARCHAR(20) UNIQUE AFTER username;

-- Update existing users with sample staff IDs
UPDATE users SET staff_id = 'ADMIN001' WHERE username = 'admin';
UPDATE users SET staff_id = 'DOC001' WHERE username = 'dr.smith';
UPDATE users SET staff_id = 'DOC002' WHERE username = 'dr.johnson';
UPDATE users SET staff_id = 'NURSE001' WHERE username = 'nurse.jane';
UPDATE users SET staff_id = 'NURSE002' WHERE username = 'nurse.mike';
UPDATE users SET staff_id = 'TECH001' WHERE username = 'tech.sarah';
UPDATE users SET staff_id = 'ADMIN002' WHERE username = 'admin.peter';
UPDATE users SET staff_id = 'DOC003' WHERE username = 'dr.emily';

-- Create new users table structure for fresh installations
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    staff_id VARCHAR(20) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'appraiser', 'appraisee') NOT NULL,
    title VARCHAR(10),
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    other_names VARCHAR(100),
    gender ENUM('Male', 'Female') NOT NULL,
    grade_salary VARCHAR(100),
    job_title VARCHAR(100),
    department VARCHAR(100),
    appointment_date DATE,
    is_approved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;
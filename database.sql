-- Run this SQL to fix the missing staff_id column
-- Execute in phpMyAdmin or MySQL command line

-- Add staff_id column to users table
ALTER TABLE users ADD COLUMN staff_id VARCHAR(20) UNIQUE AFTER username;

-- Update existing users with staff IDs (if any exist)
UPDATE users SET staff_id = CONCAT('STAFF', LPAD(id, 3, '0')) WHERE staff_id IS NULL;

-- Update specific users if they exist
UPDATE users SET staff_id = 'ADMIN001' WHERE username = 'admin' AND staff_id IS NULL;
UPDATE users SET staff_id = 'DOC001' WHERE username = 'dr.smith' AND staff_id IS NULL;
UPDATE users SET staff_id = 'DOC002' WHERE username = 'dr.johnson' AND staff_id IS NULL;
UPDATE users SET staff_id = 'NURSE001' WHERE username = 'nurse.jane' AND staff_id IS NULL;
UPDATE users SET staff_id = 'NURSE002' WHERE username = 'nurse.mike' AND staff_id IS NULL;
UPDATE users SET staff_id = 'TECH001' WHERE username = 'tech.sarah' AND staff_id IS NULL;
UPDATE users SET staff_id = 'ADMIN002' WHERE username = 'admin.peter' AND staff_id IS NULL;
UPDATE users SET staff_id = 'DOC003' WHERE username = 'dr.emily' AND staff_id IS NULL;
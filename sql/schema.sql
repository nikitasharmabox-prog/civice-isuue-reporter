-- Civic Issue Reporter - Database Schema
-- Run this file in phpMyAdmin or MySQL CLI

CREATE DATABASE IF NOT EXISTS civic_reporter CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE civic_reporter;

-- Wards table
CREATE TABLE IF NOT EXISTS wards (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ward_number VARCHAR(20) NOT NULL UNIQUE,
    ward_name VARCHAR(100) NOT NULL,
    officer_name VARCHAR(100) NOT NULL,
    officer_email VARCHAR(150) NOT NULL,
    officer_phone VARCHAR(20),
    lat_center DECIMAL(10, 8),
    lng_center DECIMAL(11, 8),
    radius_km DECIMAL(5, 2) DEFAULT 2.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Complaint clusters table
CREATE TABLE IF NOT EXISTS complaint_clusters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ward_id INT,
    category VARCHAR(50) NOT NULL,
    complaint_count INT DEFAULT 1,
    center_lat DECIMAL(10, 8) NOT NULL,
    center_lng DECIMAL(11, 8) NOT NULL,
    radius_meters INT DEFAULT 200,
    status ENUM('open', 'acknowledged', 'in_progress', 'resolved') DEFAULT 'open',
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
    last_complaint_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notification_sent TINYINT(1) DEFAULT 0,
    notification_sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ward_id) REFERENCES wards(id) ON DELETE SET NULL
);

-- Complaints table
CREATE TABLE IF NOT EXISTS complaints (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tracking_id VARCHAR(20) NOT NULL UNIQUE,
    citizen_name VARCHAR(100),
    citizen_email VARCHAR(150),
    citizen_phone VARCHAR(20),
    category ENUM('pothole', 'garbage', 'streetlight', 'water_leak', 'sewage', 'road_damage', 'encroachment', 'other') NOT NULL,
    description TEXT,
    lat DECIMAL(10, 8) NOT NULL,
    lng DECIMAL(11, 8) NOT NULL,
    address TEXT,
    photo_path VARCHAR(255),
    ward_id INT,
    cluster_id INT,
    status ENUM('pending', 'acknowledged', 'in_progress', 'resolved', 'rejected') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'low',
    resolution_notes TEXT,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ward_id) REFERENCES wards(id) ON DELETE SET NULL,
    FOREIGN KEY (cluster_id) REFERENCES complaint_clusters(id) ON DELETE SET NULL
);

-- Notifications log
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ward_id INT,
    cluster_id INT,
    officer_email VARCHAR(150) NOT NULL,
    officer_name VARCHAR(100),
    subject VARCHAR(255),
    message TEXT,
    complaint_count INT,
    category VARCHAR(50),
    notification_type ENUM('email', 'sms', 'both') DEFAULT 'email',
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ward_id) REFERENCES wards(id) ON DELETE SET NULL,
    FOREIGN KEY (cluster_id) REFERENCES complaint_clusters(id) ON DELETE SET NULL
);

-- Officers login table
CREATE TABLE IF NOT EXISTS officers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ward_id INT UNIQUE,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(150) NOT NULL,
    full_name VARCHAR(100),
    role ENUM('officer', 'admin') DEFAULT 'officer',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ward_id) REFERENCES wards(id) ON DELETE SET NULL
);

-- Complaint status history
CREATE TABLE IF NOT EXISTS complaint_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    complaint_id INT NOT NULL,
    changed_by INT,
    old_status VARCHAR(50),
    new_status VARCHAR(50),
    notes TEXT,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
);

-- Seed: Sample wards
INSERT INTO wards (ward_number, ward_name, officer_name, officer_email, officer_phone, lat_center, lng_center, radius_km) VALUES
('W001', 'Ward 1 - Central', 'Rajesh Kumar', 'ward1@civic.local', '9876543210', 28.6139, 77.2090, 2.50),
('W002', 'Ward 2 - North', 'Priya Sharma', 'ward2@civic.local', '9876543211', 28.6500, 77.2100, 2.50),
('W003', 'Ward 3 - South', 'Anil Verma', 'ward3@civic.local', '9876543212', 28.5800, 77.2000, 2.50),
('W004', 'Ward 4 - East', 'Sunita Patel', 'ward4@civic.local', '9876543213', 28.6200, 77.2500, 2.50),
('W005', 'Ward 5 - West', 'Mohan Das', 'ward5@civic.local', '9876543214', 28.6100, 77.1700, 2.50);

-- Seed: Admin officer
INSERT INTO officers (ward_id, username, password_hash, email, full_name, role) VALUES
(NULL, 'admin', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@civic.local', 'System Admin', 'admin');

-- Note: Default admin password is 'password' - CHANGE IN PRODUCTION

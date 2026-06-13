-- =============================================
-- Smart Classroom — v4.0 Feature Update
-- Run this on existing installations to add
-- Feature 4: Archive & Personal Storage tables
-- =============================================

USE smart_classroom;

-- Add status column to classes table for soft-delete/archive
ALTER TABLE classes ADD COLUMN IF NOT EXISTS status ENUM('active','archived') DEFAULT 'active' AFTER description;

-- Archived Classes (2-day grace period before permanent deletion)
CREATE TABLE IF NOT EXISTS archived_classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    original_class_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    section VARCHAR(100) DEFAULT NULL,
    subject VARCHAR(150) DEFAULT NULL,
    room VARCHAR(100) DEFAULT NULL,
    code VARCHAR(10) NOT NULL,
    teacher_id INT NOT NULL,
    cover_color VARCHAR(20) DEFAULT '#4f46e5',
    description TEXT DEFAULT NULL,
    archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delete_after DATETIME NOT NULL,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Student Personal Archive (My Saved Materials)
CREATE TABLE IF NOT EXISTS student_archive (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    folder_name VARCHAR(255) NOT NULL,
    source_class_id INT DEFAULT NULL,
    source_class_name VARCHAR(200) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Student Archive Items (individual saved materials)
CREATE TABLE IF NOT EXISTS student_archive_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    archive_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    file_path VARCHAR(255) DEFAULT NULL,
    link_url VARCHAR(500) DEFAULT NULL,
    type ENUM('file','link','video') DEFAULT 'file',
    original_material_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (archive_id) REFERENCES student_archive(id) ON DELETE CASCADE
);

-- QR Attendance Sessions (for time-limited QR code attendance)
CREATE TABLE IF NOT EXISTS qr_attendance_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    teacher_id INT NOT NULL,
    date DATE NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
);

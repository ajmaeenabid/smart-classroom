-- ============================================================
-- Advanced Classroom System — Database Schema
-- Version: 1.0
-- Compatible with: MySQL 5.7+ / MariaDB 10.3+
-- ============================================================

-- Create database
CREATE DATABASE IF NOT EXISTS advanced_classroom
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE advanced_classroom;

-- ============================================================
-- 1. USERS TABLE
-- Stores all users: admin, teacher, student, guardian
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'teacher', 'student', 'guardian') NOT NULL DEFAULT 'student',
    avatar VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    status ENUM('active', 'blocked') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_email (email),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- 2. CLASSES TABLE
-- Each class belongs to one teacher
-- ============================================================
CREATE TABLE IF NOT EXISTS classes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    class_name VARCHAR(150) NOT NULL,
    subject VARCHAR(100) NOT NULL,
    section VARCHAR(50) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    class_code VARCHAR(10) NOT NULL UNIQUE,
    color VARCHAR(7) DEFAULT '#4F46E5',
    status ENUM('active', 'archived') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_class_code (class_code),
    INDEX idx_teacher (teacher_id)
) ENGINE=InnoDB;

-- ============================================================
-- 3. CLASS ENROLLMENTS TABLE
-- Links students to classes
-- ============================================================
CREATE TABLE IF NOT EXISTS class_enrollments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    student_id INT NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (class_id, student_id),
    INDEX idx_student (student_id)
) ENGINE=InnoDB;

-- ============================================================
-- 4. GUARDIAN LINKS TABLE
-- Links guardian accounts to student accounts
-- ============================================================
CREATE TABLE IF NOT EXISTS guardian_links (
    id INT AUTO_INCREMENT PRIMARY KEY,
    guardian_id INT NOT NULL,
    student_id INT NOT NULL,
    linked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (guardian_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_link (guardian_id, student_id)
) ENGINE=InnoDB;

-- ============================================================
-- 5. ANNOUNCEMENTS TABLE
-- Teacher posts announcements to a class
-- ============================================================
CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    teacher_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_class (class_id)
) ENGINE=InnoDB;

-- ============================================================
-- 6. MATERIALS TABLE
-- Study materials uploaded by teachers
-- ============================================================
CREATE TABLE IF NOT EXISTS materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    teacher_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(50) DEFAULT NULL,
    file_size INT DEFAULT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_class (class_id)
) ENGINE=InnoDB;

-- ============================================================
-- 7. ASSIGNMENTS TABLE
-- Assignments posted by teachers
-- ============================================================
CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    teacher_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    due_date DATETIME NOT NULL,
    max_marks INT DEFAULT 100,
    file_path VARCHAR(500) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_class (class_id),
    INDEX idx_due_date (due_date)
) ENGINE=InnoDB;

-- ============================================================
-- 8. SUBMISSIONS TABLE
-- Student submissions for assignments
-- ============================================================
CREATE TABLE IF NOT EXISTS submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    file_path VARCHAR(500) DEFAULT NULL,
    comment TEXT DEFAULT NULL,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('submitted', 'late', 'graded', 'draft') NOT NULL DEFAULT 'submitted',
    marks INT DEFAULT NULL,
    feedback TEXT DEFAULT NULL,
    graded_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_submission (assignment_id, student_id),
    INDEX idx_assignment (assignment_id),
    INDEX idx_student (student_id)
) ENGINE=InnoDB;

-- ============================================================
-- 9. ATTENDANCE TABLE
-- Daily attendance records per class
-- ============================================================
CREATE TABLE IF NOT EXISTS attendance (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    student_id INT NOT NULL,
    date DATE NOT NULL,
    status ENUM('present', 'absent', 'late', 'excused') NOT NULL DEFAULT 'present',
    marked_by INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_attendance (class_id, student_id, date),
    INDEX idx_date (date)
) ENGINE=InnoDB;

-- ============================================================
-- 10. QUIZZES TABLE
-- Simple quiz/poll created by teachers
-- ============================================================
CREATE TABLE IF NOT EXISTS quizzes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    class_id INT NOT NULL,
    teacher_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    time_limit INT DEFAULT NULL COMMENT 'Time limit in minutes, NULL = unlimited',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_class (class_id)
) ENGINE=InnoDB;

-- ============================================================
-- 11. QUIZ QUESTIONS TABLE
-- MCQ questions for quizzes
-- ============================================================
CREATE TABLE IF NOT EXISTS quiz_questions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    question_text TEXT NOT NULL,
    option_a VARCHAR(255) NOT NULL,
    option_b VARCHAR(255) NOT NULL,
    option_c VARCHAR(255) NOT NULL,
    option_d VARCHAR(255) NOT NULL,
    correct_option ENUM('A', 'B', 'C', 'D') NOT NULL,
    points INT DEFAULT 1,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    INDEX idx_quiz (quiz_id)
) ENGINE=InnoDB;

-- ============================================================
-- 12. QUIZ ANSWERS TABLE
-- Student responses to quiz questions
-- ============================================================
CREATE TABLE IF NOT EXISTS quiz_answers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id INT NOT NULL,
    question_id INT NOT NULL,
    student_id INT NOT NULL,
    selected_option ENUM('A', 'B', 'C', 'D') NOT NULL,
    is_correct TINYINT(1) DEFAULT 0,
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (quiz_id) REFERENCES quizzes(id) ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_answer (question_id, student_id),
    INDEX idx_quiz_student (quiz_id, student_id)
) ENGINE=InnoDB;

-- ============================================================
-- 13. NOTIFICATIONS TABLE
-- System notifications for all users
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    type ENUM('assignment', 'submission', 'grade', 'announcement', 'enrollment', 'quiz', 'system') NOT NULL DEFAULT 'system',
    link VARCHAR(500) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_read (is_read),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA: Default Admin Account
-- Email: admin@classroom.com | Password: Admin@123
-- ============================================================
INSERT INTO users (full_name, email, password, role, status) VALUES
('System Administrator', 'admin@classroom.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active');
-- Note: The hashed password above is for 'password'. Change it after first login.
-- To generate a proper hash, use: php -r "echo password_hash('Admin@123', PASSWORD_DEFAULT);"

-- ============================================================
-- SAMPLE DATA (Optional — for testing)
-- ============================================================
-- You can uncomment these to seed test data:

-- INSERT INTO users (full_name, email, password, role) VALUES
-- ('Dr. Sarah Johnson', 'teacher@classroom.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teacher'),
-- ('Alex Student', 'student@classroom.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'student'),
-- ('Maria Guardian', 'guardian@classroom.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'guardian');

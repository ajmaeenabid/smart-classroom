-- =============================================
-- Smart Classroom v5.0 — Live Quiz + At-Risk
-- Run once against our smart_classroom DB
-- =============================================

-- Add live question pointer to quizzes table
ALTER TABLE quizzes
    ADD COLUMN IF NOT EXISTS live_question INT DEFAULT 0;

-- Per-question live answers (separate from regular quiz_responses)
CREATE TABLE IF NOT EXISTS live_quiz_answers (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    quiz_id       INT NOT NULL,
    question_id   INT NOT NULL,
    student_id    INT NOT NULL,
    answer        VARCHAR(255) NOT NULL,
    is_correct    TINYINT(1) DEFAULT 0,
    points_earned INT DEFAULT 0,
    answered_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_live_answer (quiz_id, question_id, student_id),
    FOREIGN KEY (quiz_id)     REFERENCES quizzes(id)        ON DELETE CASCADE,
    FOREIGN KEY (question_id) REFERENCES quiz_questions(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id)  REFERENCES users(id)          ON DELETE CASCADE
);

<?php
/**
 * Landing Page — Advanced Classroom System
 * Public page with hero section and feature overview.
 */
session_start();

// If already logged in, go to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

require_once __DIR__ . '/config/db.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Advanced Classroom — A modern Learning Management System for educators and students.">
    <title>Advanced Classroom — Modern Learning Management System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎓</text></svg>">
</head>
<body>
    <!-- Navigation -->
    <nav class="topnav" style="background:transparent;box-shadow:none;position:absolute;">
        <div class="topnav-left">
            <a href="<?php echo BASE_URL; ?>/" class="topnav-brand">
                <span class="brand-icon">🎓</span>
                <span class="brand-text">Advanced Classroom</span>
            </a>
        </div>
        <div class="topnav-right">
            <a href="<?php echo BASE_URL; ?>/login.php" class="btn btn-ghost" style="color:#fff;">Sign In</a>
            <a href="<?php echo BASE_URL; ?>/register.php" class="btn btn-accent">Get Started</a>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="landing-hero">
        <div class="hero-content">
            <div class="hero-text">
                <h1>The <span>Smarter Way</span> to Manage Your Classroom</h1>
                <p>Create classes, share materials, assign work, track grades, and connect with students — all in one powerful platform built for modern education.</p>
                <div class="hero-actions">
                    <a href="<?php echo BASE_URL; ?>/register.php" class="btn btn-accent btn-lg">🚀 Start Free</a>
                    <a href="#features" class="btn btn-outline btn-lg" style="color:#fff;border-color:rgba(255,255,255,.3);">Learn More</a>
                </div>
            </div>
            <div class="hero-visual">
                <div class="hero-card">
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;">
                        <div style="width:48px;height:48px;border-radius:12px;background:rgba(79,70,229,.3);display:flex;align-items:center;justify-content:center;font-size:24px;">📚</div>
                        <div>
                            <h3 style="font-size:16px;font-weight:700;">Computer Science 101</h3>
                            <p style="font-size:13px;opacity:.7;">Dr. Sarah Johnson</p>
                        </div>
                    </div>
                    <div style="display:flex;gap:16px;margin-bottom:20px;">
                        <div style="background:rgba(255,255,255,.1);padding:12px;border-radius:10px;flex:1;text-align:center;">
                            <div style="font-size:22px;font-weight:800;">24</div>
                            <div style="font-size:11px;opacity:.6;">Students</div>
                        </div>
                        <div style="background:rgba(255,255,255,.1);padding:12px;border-radius:10px;flex:1;text-align:center;">
                            <div style="font-size:22px;font-weight:800;">8</div>
                            <div style="font-size:11px;opacity:.6;">Assignments</div>
                        </div>
                        <div style="background:rgba(255,255,255,.1);padding:12px;border-radius:10px;flex:1;text-align:center;">
                            <div style="font-size:22px;font-weight:800;">A</div>
                            <div style="font-size:11px;opacity:.6;">Avg Grade</div>
                        </div>
                    </div>
                    <div style="background:rgba(20,184,166,.2);padding:10px 16px;border-radius:8px;font-size:13px;">
                        ✅ New assignment posted: "Database Design Project"
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="landing-features" id="features">
        <h2>Everything You Need to Teach & Learn</h2>
        <div class="features-grid">
            <div class="feature-card"><div class="f-icon">📚</div><h3>Class Management</h3><p>Create classes, invite students with unique codes, and organize your curriculum effortlessly.</p></div>
            <div class="feature-card"><div class="f-icon">📝</div><h3>Assignments & Grading</h3><p>Post assignments, collect submissions, grade with feedback, and track deadlines seamlessly.</p></div>
            <div class="feature-card"><div class="f-icon">📁</div><h3>Resource Sharing</h3><p>Upload study materials, notes, PDFs, and presentations for your students to access anytime.</p></div>
            <div class="feature-card"><div class="f-icon">📊</div><h3>Performance Analytics</h3><p>Track student progress with visual charts, attendance records, and grade summaries.</p></div>
            <div class="feature-card"><div class="f-icon">❓</div><h3>Quizzes & Polls</h3><p>Create multiple-choice quizzes, collect responses, and review results instantly.</p></div>
            <div class="feature-card"><div class="f-icon">🔔</div><h3>Smart Notifications</h3><p>Stay updated with assignment deadlines, new posts, grade releases, and more.</p></div>
            <div class="feature-card"><div class="f-icon">👨‍👩‍👧</div><h3>Guardian Access</h3><p>Parents can monitor grades, attendance, and announcements in read-only mode.</p></div>
            <div class="feature-card"><div class="f-icon">🤖</div><h3>AI Academic Helper</h3><p>Get smart tips about deadlines, missing work, and study strategies from the built-in assistant.</p></div>
        </div>
    </section>

    <!-- Footer -->
    <footer style="background:var(--dark);color:rgba(255,255,255,.6);text-align:center;padding:32px 20px;font-size:14px;">
        <p>🎓 Advanced Classroom System v<?php echo APP_VERSION; ?> — Built for Modern Education</p>
        <p style="margin-top:8px;font-size:12px;opacity:.5;">© <?php echo date('Y'); ?> All rights reserved.</p>
    </footer>

    <script src="<?php echo BASE_URL; ?>/assets/js/app.js"></script>
</body>
</html>

<?php
/**
 * Navigation Bar — Advanced Classroom System
 * 
 * Role-aware navigation with sidebar for authenticated users.
 */

if (!isLoggedIn()) return;

$role = getUserRole();
$currentPage = basename($_SERVER['PHP_SELF']);
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
?>

<!-- Top Navigation Bar -->
<nav class="topnav">
    <div class="topnav-left">
        <button class="sidebar-toggle" id="sidebarToggle" title="Toggle Menu">
            <span></span><span></span><span></span>
        </button>
        <a href="<?php echo BASE_URL; ?>/dashboard.php" class="topnav-brand">
            <span class="brand-icon">🎓</span>
            <span class="brand-text"><?php echo APP_NAME; ?></span>
        </a>
    </div>
    
    <div class="topnav-center">
        <div class="search-box">
            <span class="search-icon">🔍</span>
            <input type="text" placeholder="Search classes, assignments..." id="globalSearch" autocomplete="off">
        </div>
    </div>
    
    <div class="topnav-right">
        <!-- Notifications -->
        <a href="<?php echo BASE_URL; ?>/notifications.php" class="topnav-icon" title="Notifications">
            🔔
            <?php if ($notificationCount > 0): ?>
                <span class="notif-badge"><?php echo $notificationCount > 9 ? '9+' : $notificationCount; ?></span>
            <?php endif; ?>
        </a>
        
        <!-- User Menu -->
        <div class="user-menu" id="userMenu">
            <button class="user-avatar" id="userMenuToggle">
                <span class="avatar-initials" style="background: <?php echo $currentUser ? sprintf('#%06X', crc32($currentUser['full_name']) & 0xFFFFFF) : '#4F46E5'; ?>">
                    <?php echo $currentUser ? e(getInitials($currentUser['full_name'])) : '?'; ?>
                </span>
            </button>
            <div class="user-dropdown" id="userDropdown">
                <div class="dropdown-header">
                    <strong><?php echo $currentUser ? e($currentUser['full_name']) : 'User'; ?></strong>
                    <small><?php echo $currentUser ? e($currentUser['email']) : ''; ?></small>
                    <span class="role-tag role-<?php echo $role; ?>"><?php echo ucfirst($role); ?></span>
                </div>
                <div class="dropdown-divider"></div>
                <a href="<?php echo BASE_URL; ?>/profile.php" class="dropdown-item">👤 My Profile</a>
                <a href="<?php echo BASE_URL; ?>/notifications.php" class="dropdown-item">🔔 Notifications</a>
                <div class="dropdown-divider"></div>
                <a href="<?php echo BASE_URL; ?>/logout.php" class="dropdown-item dropdown-danger">🚪 Sign Out</a>
            </div>
        </div>
    </div>
</nav>

<!-- Sidebar Navigation -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-content">
        <!-- Dashboard -->
        <div class="sidebar-section">
            <a href="<?php echo BASE_URL; ?>/dashboard.php" 
               class="sidebar-item <?php echo $currentPage === 'dashboard.php' && $currentDir !== 'admin' ? 'active' : ''; ?>">
                <span class="sidebar-icon">🏠</span>
                <span class="sidebar-label">Dashboard</span>
            </a>
        </div>
        
        <?php if ($role === 'admin'): ?>
        <!-- Admin Navigation -->
        <div class="sidebar-section">
            <div class="sidebar-heading">Administration</div>
            <a href="<?php echo BASE_URL; ?>/admin/dashboard.php" 
               class="sidebar-item <?php echo $currentPage === 'dashboard.php' && $currentDir === 'admin' ? 'active' : ''; ?>">
                <span class="sidebar-icon">📊</span>
                <span class="sidebar-label">Admin Panel</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/manage_users.php" 
               class="sidebar-item <?php echo $currentPage === 'manage_users.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">👥</span>
                <span class="sidebar-label">Manage Users</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/manage_classes.php" 
               class="sidebar-item <?php echo $currentPage === 'manage_classes.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">📚</span>
                <span class="sidebar-label">Manage Classes</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/reports.php" 
               class="sidebar-item <?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">📈</span>
                <span class="sidebar-label">Reports</span>
            </a>
        </div>
        
        <?php elseif ($role === 'teacher'): ?>
        <!-- Teacher Navigation -->
        <div class="sidebar-section">
            <div class="sidebar-heading">Teaching</div>
            <a href="<?php echo BASE_URL; ?>/teacher/create_class.php" 
               class="sidebar-item <?php echo $currentPage === 'create_class.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">➕</span>
                <span class="sidebar-label">Create Class</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/teacher/create_assignment.php" 
               class="sidebar-item <?php echo $currentPage === 'create_assignment.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">📝</span>
                <span class="sidebar-label">New Assignment</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/teacher/upload_material.php" 
               class="sidebar-item <?php echo $currentPage === 'upload_material.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">📁</span>
                <span class="sidebar-label">Upload Material</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/teacher/post_announcement.php" 
               class="sidebar-item <?php echo $currentPage === 'post_announcement.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">📢</span>
                <span class="sidebar-label">Announcement</span>
            </a>
        </div>
        <div class="sidebar-section">
            <div class="sidebar-heading">Assessment</div>
            <a href="<?php echo BASE_URL; ?>/teacher/view_submissions.php" 
               class="sidebar-item <?php echo $currentPage === 'view_submissions.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">📋</span>
                <span class="sidebar-label">Submissions</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/teacher/attendance.php" 
               class="sidebar-item <?php echo $currentPage === 'attendance.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">✅</span>
                <span class="sidebar-label">Attendance</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/teacher/create_quiz.php" 
               class="sidebar-item <?php echo $currentPage === 'create_quiz.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">❓</span>
                <span class="sidebar-label">Create Quiz</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/teacher/class_analytics.php" 
               class="sidebar-item <?php echo $currentPage === 'class_analytics.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">📊</span>
                <span class="sidebar-label">Analytics</span>
            </a>
        </div>
        
        <?php elseif ($role === 'student'): ?>
        <!-- Student Navigation -->
        <div class="sidebar-section">
            <div class="sidebar-heading">My Classes</div>
            <a href="<?php echo BASE_URL; ?>/student/join_class.php" 
               class="sidebar-item <?php echo $currentPage === 'join_class.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">🔗</span>
                <span class="sidebar-label">Join Class</span>
            </a>
        </div>
        <div class="sidebar-section">
            <div class="sidebar-heading">Academics</div>
            <a href="<?php echo BASE_URL; ?>/student/view_feedback.php" 
               class="sidebar-item <?php echo $currentPage === 'view_feedback.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">📊</span>
                <span class="sidebar-label">My Grades</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/student/progress.php" 
               class="sidebar-item <?php echo $currentPage === 'progress.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">📈</span>
                <span class="sidebar-label">Progress</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/student/view_attendance.php" 
               class="sidebar-item <?php echo $currentPage === 'view_attendance.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">✅</span>
                <span class="sidebar-label">Attendance</span>
            </a>
        </div>
        
        <?php elseif ($role === 'guardian'): ?>
        <!-- Guardian Navigation -->
        <div class="sidebar-section">
            <div class="sidebar-heading">Guardian</div>
            <a href="<?php echo BASE_URL; ?>/guardian/dashboard.php" 
               class="sidebar-item <?php echo $currentPage === 'dashboard.php' && $currentDir === 'guardian' ? 'active' : ''; ?>">
                <span class="sidebar-icon">🏠</span>
                <span class="sidebar-label">Overview</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/guardian/student_progress.php" 
               class="sidebar-item <?php echo $currentPage === 'student_progress.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">📊</span>
                <span class="sidebar-label">Student Progress</span>
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Common Bottom Items -->
        <div class="sidebar-section sidebar-bottom">
            <div class="sidebar-divider"></div>
            <a href="<?php echo BASE_URL; ?>/profile.php" 
               class="sidebar-item <?php echo $currentPage === 'profile.php' ? 'active' : ''; ?>">
                <span class="sidebar-icon">⚙️</span>
                <span class="sidebar-label">Settings</span>
            </a>
            <a href="<?php echo BASE_URL; ?>/logout.php" class="sidebar-item">
                <span class="sidebar-icon">🚪</span>
                <span class="sidebar-label">Sign Out</span>
            </a>
        </div>
    </div>
</aside>

<!-- Main Content Wrapper -->
<main class="main-content" id="mainContent">

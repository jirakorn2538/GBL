<?php
/**
 * Money Life - Teacher Portal Layout Header
 */

if (!isset($pageTitle)) {
    $pageTitle = 'Teacher Dashboard';
}

$currentRoute = basename($_SERVER['PHP_SELF']);
$currentTeacherName = $_SESSION['teacher_name'] ?? 'ครูผู้สอน';
$currentTeacherRole = $_SESSION['teacher_role'] ?? 'teacher';
$roleLabel = ($currentTeacherRole === 'admin') ? 'ผู้ดูแลระบบ (Admin)' : 'ครูผู้สอน';
$roleClass = ($currentTeacherRole === 'admin') ? 'admin' : '';
$isAdmin = ($currentTeacherRole === 'admin');

// Base URL detection
$isInAdminFolder = strpos($_SERVER['PHP_SELF'], '/teacher/admin/') !== false;
$baseTeacherUrl = $isInAdminFolder ? '../' : '';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> - ระบบจัดการสำหรับครู Money Life</title>
    <link rel="stylesheet" href="<?= $baseTeacherUrl ?>assets/css/teacher.css">
    <!-- Chart.js for beautiful analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>
<body>

<div class="app-container">
    <!-- Sidebar Navigation -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo" style="background: linear-gradient(135deg, #1e293b, #0f172a); border: 1px solid rgba(255,255,255,0.15);">🛡️</div>
            <div class="sidebar-title">
                <h2>Admin Portal</h2>
                <p>Money Life System</p>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-group-title">การติดตามนักเรียน</div>
            <a href="<?= $baseTeacherUrl ?>index.php" class="nav-item <?= ($currentRoute === 'index.php') ? 'active' : '' ?>">
                <span class="icon">📊</span> แผงควบคุม (Dashboard)
            </a>
            <a href="<?= $baseTeacherUrl ?>students.php" class="nav-item <?= ($currentRoute === 'students.php' || $currentRoute === 'student.php') ? 'active' : '' ?>">
                <span class="icon">👥</span> ข้อมูลนักเรียน
            </a>
            <a href="<?= $baseTeacherUrl ?>results.php" class="nav-item <?= ($currentRoute === 'results.php' || $currentRoute === 'session.php') ? 'active' : '' ?>">
                <span class="icon">🎮</span> การใช้งานและผลคะแนน
            </a>
            <a href="<?= $baseTeacherUrl ?>analytics.php" class="nav-item <?= ($currentRoute === 'analytics.php') ? 'active' : '' ?>">
                <span class="icon">📈</span> สถิติและวิเคราะห์ผล
            </a>
            <a href="<?= $baseTeacherUrl ?>reports.php" class="nav-item <?= ($currentRoute === 'reports.php') ? 'active' : '' ?>">
                <span class="icon">📄</span> รายงานสรุป
            </a>

            <div class="nav-group-title" style="margin-top: 14px;">ระบบและการจัดการ</div>
            <a href="<?= $baseTeacherUrl ?>admin/teachers.php" class="nav-item <?= (strpos($_SERVER['PHP_SELF'], '/admin/') !== false) ? 'active' : '' ?>">
                <span class="icon">👨‍🏫</span> จัดการบัญชีผู้ใช้งาน
            </a>
            <a href="<?= $baseTeacherUrl ?>profile.php" class="nav-item <?= ($currentRoute === 'profile.php') ? 'active' : '' ?>">
                <span class="icon">⚙️</span> บัญชีของฉัน
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="user-badge">
                <div class="user-avatar"><?= mb_substr($currentTeacherName, 0, 1, 'UTF-8') ?></div>
                <div class="user-info">
                    <div class="user-name" title="<?= e($currentTeacherName) ?>">👨‍🏫 <?= e($currentTeacherName) ?></div>
                    <div style="font-size: 11px; color: #94a3b8;"><?= e($_SESSION['teacher_position'] ?? 'ครูผู้สอน') ?></div>
                    <div class="user-role <?= $roleClass ?>" style="margin-top: 2px;">บทบาท: <?= $roleLabel ?></div>
                </div>
            </div>
            <div style="margin-top: 12px;">
                <a href="<?= $baseTeacherUrl ?>logout.php" class="btn btn-outline btn-sm" style="width: 100%; border-color: rgba(255,255,255,0.2); color: #cbd5e1;">
                    🚪 ออกจากระบบ
                </a>
            </div>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="main-wrapper">
        <header class="top-bar">
            <div style="display: flex; align-items: center; gap: 14px;">
                <button type="button" class="btn btn-outline btn-sm" onclick="toggleSidebar()" style="display: none;" id="mobileToggle">
                    ☰
                </button>
                <div class="top-bar-title">
                    <h1><?= e($pageTitle) ?></h1>
                </div>
            </div>
            <div class="top-bar-actions">
                <span class="badge <?= $isAdmin ? 'badge-warning' : 'badge-success' ?>" style="font-size: 13px; padding: 6px 12px;">
                    <?= $isAdmin ? '👑 ' . $roleLabel : '🎓 ' . $roleLabel ?>
                </span>
                <span style="color: #64748b; font-size: 14px;">
                    👨‍🏫 <strong><?= e($currentTeacherName) ?></strong> (<?= e($_SESSION['teacher_position'] ?? 'ครูผู้สอน') ?>)
                </span>
                <a href="<?= $baseTeacherUrl ?>logout.php" class="btn btn-outline btn-sm" style="color: #ef4444; border-color: #fecaca;">
                    ออกจากระบบ
                </a>
            </div>
        </header>

        <main class="page-content">

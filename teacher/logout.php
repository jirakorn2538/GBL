<?php
/**
 * Money Life - Teacher Logout
 * Secure session destruction and audit logging
 */

require_once __DIR__ . '/auth/permissions.php';

if (is_teacher_logged_in()) {
    log_audit($pdo, 'LOGOUT', 'teachers', $_SESSION['teacher_id']);
}

// ล้างตัวแปร Session ทั้งหมด
$_SESSION = [];

// ล้าง Session Cookie ฝั่ง Browser
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// ทำลาย Session
session_destroy();

// Redirect ไปยังหน้า Login พร้อมข้อความยืนยัน
header('Location: /money-life/teacher/login.php?success=' . urlencode('ออกจากระบบเรียบร้อยแล้ว'));
exit;

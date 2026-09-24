<?php
/**
 * Money Life - Admin Access Control Middleware (check_admin.php)
 * Strictly verifies role = 'admin'
 */

require_once __DIR__ . '/check_teacher.php';

if (!is_admin()) {
    header('Location: /money-life/teacher/index.php?error=' . urlencode('คุณไม่มีสิทธิ์เข้าถึงส่วนนี้ (สำหรับผู้ดูแลระบบเท่านั้น)'));
    exit;
}

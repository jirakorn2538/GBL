<?php
/**
 * Money Life - Admin Reject Teacher Account Action
 */

require_once __DIR__ . '/../auth/check_admin.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token()) {
    header('Location: teachers.php?error=' . urlencode('คำขอไม่ถูกต้องหรือหมดเวลา'));
    exit;
}

$teacherId = (int)($_POST['teacher_id'] ?? 0);

if ($teacherId > 0) {
    try {
        $stmt = $pdo->prepare("UPDATE teachers SET status = 'rejected' WHERE id = ?");
        $stmt->execute([$teacherId]);

        log_audit($pdo, 'ADMIN_REJECT_TEACHER', 'teachers', $teacherId);

        header('Location: teachers.php?msg=' . urlencode('ปฏิเสธคำขอสมัครบัญชีครูนี้แล้ว') . '&type=warning');
        exit;
    } catch (PDOException $e) {
        header('Location: teachers.php?error=' . urlencode('เกิดข้อผิดพลาด: ' . $e->getMessage()));
        exit;
    }
}

header('Location: teachers.php');
exit;

<?php
/**
 * Money Life - Admin Approve Teacher Account Action
 */

require_once __DIR__ . '/../auth/check_admin.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token()) {
    header('Location: teachers.php?error=' . urlencode('คำขอไม่ถูกต้องหรือหมดเวลา'));
    exit;
}

$teacherId = (int)($_POST['teacher_id'] ?? 0);

if ($teacherId > 0) {
    try {
        $stmt = $pdo->prepare("
            UPDATE teachers 
            SET status = 'approved', approved_at = NOW(), approved_by = ?, failed_login_attempts = 0, locked_until = NULL 
            WHERE id = ?
        ");
        $stmt->execute([$_SESSION['teacher_id'], $teacherId]);

        log_audit($pdo, 'ADMIN_APPROVE_TEACHER', 'teachers', $teacherId);

        header('Location: teachers.php?msg=' . urlencode('อนุมัติบัญชีครูเรียบร้อยแล้ว บัญชีนี้สามารถเข้าสู่ระบบได้ทันที') . '&type=success');
        exit;
    } catch (PDOException $e) {
        header('Location: teachers.php?error=' . urlencode('เกิดข้อผิดพลาด: ' . $e->getMessage()));
        exit;
    }
}

header('Location: teachers.php');
exit;

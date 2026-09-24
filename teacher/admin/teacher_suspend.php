<?php
/**
 * Money Life - Admin Suspend/Unsuspend Teacher Account Action
 */

require_once __DIR__ . '/../auth/check_admin.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token()) {
    header('Location: teachers.php?error=' . urlencode('คำขอไม่ถูกต้องหรือหมดเวลา'));
    exit;
}

$teacherId = (int)($_POST['teacher_id'] ?? 0);
$targetStatus = $_POST['target_status'] ?? 'suspended';

if ($teacherId === (int)$_SESSION['teacher_id']) {
    header('Location: teachers.php?error=' . urlencode('ไม่สามารถระงับบัญชีของตนเองได้'));
    exit;
}

if ($teacherId > 0 && in_array($targetStatus, ['suspended', 'approved'], true)) {
    try {
        $stmt = $pdo->prepare("UPDATE teachers SET status = ? WHERE id = ?");
        $stmt->execute([$targetStatus, $teacherId]);

        log_audit($pdo, $targetStatus === 'suspended' ? 'ADMIN_SUSPEND_TEACHER' : 'ADMIN_UNSUSPEND_TEACHER', 'teachers', $teacherId);

        $msgText = $targetStatus === 'suspended' ? 'ระงับการใช้งานบัญชีครูเรียบร้อยแล้ว' : 'ยกเลิกการระงับและเปิดใช้งานบัญชีแล้ว';
        header('Location: teachers.php?msg=' . urlencode($msgText) . '&type=success');
        exit;
    } catch (PDOException $e) {
        header('Location: teachers.php?error=' . urlencode('เกิดข้อผิดพลาด: ' . $e->getMessage()));
        exit;
    }
}

header('Location: teachers.php');
exit;

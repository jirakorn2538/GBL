<?php
/**
 * Money Life - Teacher Access Control Middleware (check_teacher.php)
 * Enforces server-side authentication, account approval status, and teacher/admin roles
 */

require_once __DIR__ . '/permissions.php';

// 1. ตรวจสอบว่ามีการ Login อยู่ใน Session หรือไม่
if (!is_teacher_logged_in()) {
    header('Location: /money-life/teacher/login.php');
    exit;
}

// 2. ตรวจสอบสถานะบัญชีล่าสุดจากฐานข้อมูลโดยตรง
try {
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, position, national_id, phone, status, role 
        FROM teachers 
        WHERE id = ? LIMIT 1
    ");
    $stmt->execute([$_SESSION['teacher_id']]);
    $currentTeacher = $stmt->fetch();

    if (!$currentTeacher) {
        session_unset();
        session_destroy();
        header('Location: /money-life/teacher/login.php?error=' . urlencode('ไม่พบบัญชีผู้ใช้งานในระบบ'));
        exit;
    }

    // ตรวจสอบสถานะการอนุมัติ (ส่วนที่ 5 & 9)
    if ($currentTeacher['status'] === 'pending') {
        session_unset();
        session_destroy();
        header('Location: /money-life/teacher/login.php?error=' . urlencode('บัญชีของคุณอยู่ระหว่างการตรวจสอบโดยผู้ดูแลระบบ'));
        exit;
    } elseif ($currentTeacher['status'] === 'rejected') {
        session_unset();
        session_destroy();
        header('Location: /money-life/teacher/login.php?error=' . urlencode('บัญชีของคุณยังไม่ได้รับอนุมัติ'));
        exit;
    } elseif ($currentTeacher['status'] === 'suspended') {
        session_unset();
        session_destroy();
        header('Location: /money-life/teacher/login.php?error=' . urlencode('บัญชีของคุณถูกระงับการใช้งาน'));
        exit;
    } elseif ($currentTeacher['status'] !== 'approved') {
        session_unset();
        session_destroy();
        header('Location: /money-life/teacher/login.php?error=' . urlencode('สถานะบัญชีไม่ถูกต้อง'));
        exit;
    }

    // ตรวจสอบ Role
    if (!in_array($currentTeacher['role'], ['teacher', 'admin'], true)) {
        session_unset();
        session_destroy();
        header('Location: /money-life/teacher/login.php?error=' . urlencode('สิทธิ์การเข้าใช้งานไม่ถูกต้อง'));
        exit;
    }

    // อัปเดตข้อมูล Session ล่าสุด
    $_SESSION['teacher_id'] = $currentTeacher['id'];
    $_SESSION['teacher_name'] = $currentTeacher['first_name'] . ' ' . $currentTeacher['last_name'];
    $_SESSION['teacher_position'] = $currentTeacher['position'];
    $_SESSION['teacher_role'] = $currentTeacher['role'];
    $_SESSION['teacher_national_id_masked'] = mask_national_id($currentTeacher['national_id']);
    $_SESSION['teacher_phone_masked'] = mask_phone_number($currentTeacher['phone']);

} catch (PDOException $e) {
    header('Location: /money-life/teacher/login.php?error=' . urlencode('ระบบขัดข้องชั่วคราว กรุณาลองใหม่อีกครั้ง'));
    exit;
}

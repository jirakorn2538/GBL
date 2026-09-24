<?php
/**
 * Money Life - Teacher & Admin Permissions, Security, PDPA & Validation Helper
 */

// Start session if not started with secure settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/totp.php';

/**
 * XSS prevention escaping
 */
function e($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

/**
 * CSRF Token Generator
 */
function get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render CSRF input field
 */
function csrf_field() {
    $token = get_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Verify CSRF Token
 */
function verify_csrf_token($token = null) {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * ตรวจสอบความถูกต้องของเลขประจำตัวประชาชนไทย 13 หลักด้วย Checksum (ส่วนที่ 4)
 * - มี 13 หลัก
 * - เป็นตัวเลขเท่านั้น
 * - ผ่านการคำนวณ Checksum ถ่วงน้ำหนัก
 */
function validate_thai_national_id($id) {
    $id = trim((string)$id);
    if (strlen($id) !== 13 || !ctype_digit($id)) {
        return false;
    }

    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += (int)$id[$i] * (13 - $i);
    }

    $checkDigit = (11 - ($sum % 11)) % 10;
    return $checkDigit === (int)$id[12];
}

/**
 * ตรวจสอบความถูกต้องของเบอร์โทรศัพท์ (10 หลัก เริ่มต้นด้วย 0)
 */
function validate_phone_number($phone) {
    $clean = preg_replace('/[^0-9]/', '', (string)$phone);
    return (strlen($clean) >= 9 && strlen($clean) <= 10 && $clean[0] === '0');
}

/**
 * ทำ Masking เลขบัตรประชาชนเพื่อความปลอดภัยตาม PDPA (ส่วนที่ 4 & 21)
 * ตัวอย่าง: 1-2345-*****-67-8
 */
function mask_national_id($id) {
    $id = preg_replace('/[^0-9]/', '', (string)$id);
    if (strlen($id) !== 13) {
        return $id ? '***-******' : '-';
    }
    return substr($id, 0, 1) . '-' . substr($id, 1, 4) . '-*****-' . substr($id, 10, 2) . '-' . substr($id, 12, 1);
}

/**
 * ทำ Masking เบอร์โทรศัพท์ เช่น 081-***-5678
 */
function mask_phone_number($phone) {
    $clean = preg_replace('/[^0-9]/', '', (string)$phone);
    if (strlen($clean) === 10) {
        return substr($clean, 0, 3) . '-***-' . substr($clean, 6);
    }
    return $phone;
}

/**
 * Get Client IP Address
 */
function get_client_ip() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($parts[0]);
    }
    return substr($ip, 0, 45);
}

/**
 * Log action to audit_logs (ส่วนที่ 19 & 21: ห้ามใส่เลข 13 หลักเต็มลงใน log)
 */
function log_audit($pdo, $action, $target_type = null, $target_id = null, $details = null) {
    try {
        $teacher_id = $_SESSION['teacher_id'] ?? null;
        $ip = get_client_ip();
        
        $detailsStr = null;
        if ($details) {
            if (is_array($details)) {
                // กรองไม่ให้มี national_id เต็มใน details
                if (isset($details['national_id'])) {
                    $details['national_id'] = mask_national_id($details['national_id']);
                }
                $detailsStr = json_encode($details, JSON_UNESCAPED_UNICODE);
            } else {
                $detailsStr = (string)$details;
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (teacher_id, action, target_type, target_id, details, ip_address)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $teacher_id,
            $action,
            $target_type,
            $target_id ? (string)$target_id : null,
            $detailsStr,
            $ip
        ]);
    } catch (Exception $e) {
        error_log("Audit log failed: " . $e->getMessage());
    }
}

/**
 * Brute force protection check by national_id or phone
 */
function check_rate_limit($pdo, $identifier) {
    $clean = preg_replace('/[^0-9]/', '', (string)$identifier);
    $stmt = $pdo->prepare("SELECT failed_login_attempts, locked_until FROM teachers WHERE national_id = ? OR phone = ?");
    $stmt->execute([$clean, $clean]);
    $user = $stmt->fetch();

    if ($user && $user['locked_until']) {
        $lockedUntil = strtotime($user['locked_until']);
        if ($lockedUntil > time()) {
            $minutesLeft = ceil(($lockedUntil - time()) / 60);
            return [
                'locked' => true,
                'minutes' => $minutesLeft,
                'message' => "บัญชีนี้ถูกระงับชั่วคราวเนื่องจากพยายามเข้าสู่ระบบผิดพลาดหลายครั้ง กรุณารออีก {$minutesLeft} นาที"
            ];
        } else {
            $resetStmt = $pdo->prepare("UPDATE teachers SET failed_login_attempts = 0, locked_until = NULL WHERE national_id = ? OR phone = ?");
            $resetStmt->execute([$clean, $clean]);
        }
    }

    return ['locked' => false];
}

/**
 * Record a failed login attempt
 */
function record_failed_login($pdo, $identifier) {
    $clean = preg_replace('/[^0-9]/', '', (string)$identifier);
    $stmt = $pdo->prepare("SELECT id, failed_login_attempts FROM teachers WHERE national_id = ? OR phone = ?");
    $stmt->execute([$clean, $clean]);
    $user = $stmt->fetch();

    if ($user) {
        $attempts = (int)$user['failed_login_attempts'] + 1;
        if ($attempts >= 5) {
            $lockedUntil = date('Y-m-d H:i:s', time() + (15 * 60));
            $upd = $pdo->prepare("UPDATE teachers SET failed_login_attempts = ?, locked_until = ? WHERE id = ?");
            $upd->execute([$attempts, $lockedUntil, $user['id']]);
        } else {
            $upd = $pdo->prepare("UPDATE teachers SET failed_login_attempts = ? WHERE id = ?");
            $upd->execute([$attempts, $user['id']]);
        }
    }
}

/**
 * Reset failed login attempts on successful login
 */
function reset_failed_login($pdo, $userId) {
    $stmt = $pdo->prepare("UPDATE teachers SET failed_login_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = ?");
    $stmt->execute([$userId]);
}

/**
 * Check if teacher is logged in
 */
function is_teacher_logged_in() {
    return !empty($_SESSION['teacher_logged_in']) && !empty($_SESSION['teacher_id']);
}

/**
 * Check if logged in user is admin
 */
function is_admin() {
    return is_teacher_logged_in() && ($_SESSION['teacher_role'] ?? '') === 'admin';
}

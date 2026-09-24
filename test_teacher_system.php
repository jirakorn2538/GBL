<?php
/**
 * Automated Verification Script for Money Life Teacher Portal
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/teacher/auth/totp.php';
require_once __DIR__ . '/teacher/auth/permissions.php';

echo "========================================================\n";
echo "  MONEY LIFE - TEACHER & ADMIN SYSTEM VERIFICATION TEST \n";
echo "========================================================\n\n";

$testsPassed = 0;
$totalTests = 0;

function assertTest($name, $condition, $details = '') {
    global $testsPassed, $totalTests;
    $totalTests++;
    if ($condition) {
        $testsPassed++;
        echo " [PASS] $name\n";
    } else {
        echo " [FAIL] $name: $details\n";
    }
}

// ----------------------------------------------------
// TEST 1: Database Structure Verification
// ----------------------------------------------------
echo "--- 1. Database Schema & Tables ---\n";

$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
assertTest("Table 'teachers' exists", in_array('teachers', $tables));
assertTest("Table 'audit_logs' exists", in_array('audit_logs', $tables));
assertTest("Table 'students' exists", in_array('students', $tables));
assertTest("Table 'game_sessions' exists", in_array('game_sessions', $tables));
assertTest("Table 'game_answers' exists", in_array('game_answers', $tables));

$cols = $pdo->query("SHOW COLUMNS FROM students LIKE 'status'")->fetchAll();
assertTest("Column 'status' exists in 'students'", count($cols) > 0);

// ----------------------------------------------------
// TEST 2: Password Hashing & Brute Force Simulation
// ----------------------------------------------------
echo "\n--- 2. Auth & Brute Force Protection ---\n";

// ดึงรหัสผ่าน admin
$stmt = $pdo->prepare("SELECT password_hash FROM teachers WHERE username = 'admin'");
$stmt->execute();
$adminHash = $stmt->fetchColumn();
assertTest("Admin password is NOT plain text", strlen($adminHash) >= 60 && substr($adminHash, 0, 4) === '$2y$');
assertTest("Admin password verifies correctly with password_verify", password_verify('AdminPassword@123', $adminHash));
assertTest("Incorrect password rejected", !password_verify('WrongPassword', $adminHash));

// ทดสอบ Rate limit brute force logic
$testUser = 'test_bf_user_' . time();
$pdo->prepare("INSERT INTO teachers (username, password_hash, full_name, email, role, is_active) VALUES (?, 'dummy', 'Test BF', ?, 'teacher', 1)")
    ->execute([$testUser, $testUser . '@test.com']);

for ($i = 1; $i <= 5; $i++) {
    record_failed_login($pdo, $testUser);
}
$rateStatus = check_rate_limit($pdo, $testUser);
assertTest("Account locked after 5 failed attempts", $rateStatus['locked'] === true);

// Reset
$stmt = $pdo->prepare("SELECT id FROM teachers WHERE username = ?");
$stmt->execute([$testUser]);
$bfId = $stmt->fetchColumn();
reset_failed_login($pdo, $bfId);
$rateStatusAfter = check_rate_limit($pdo, $testUser);
assertTest("Account unlocked after reset_failed_login", $rateStatusAfter['locked'] === false);

// Clean test user
$pdo->prepare("DELETE FROM teachers WHERE username = ?")->execute([$testUser]);

// ----------------------------------------------------
// TEST 3: TOTP RFC 6238 Generation & Verification
// ----------------------------------------------------
echo "\n--- 3. 2FA TOTP RFC 6238 Authenticator ---\n";

$secret = TOTP::generateSecret(16);
assertTest("TOTP Secret length is 16 Base32 chars", strlen($secret) === 16);

$code = TOTP::getCode($secret);
assertTest("Generated 6-digit TOTP code", strlen($code) === 6 && ctype_digit($code));
assertTest("TOTP code verification passes", TOTP::verifyCode($secret, $code));
assertTest("Wrong TOTP code fails", !TOTP::verifyCode($secret, '999999' === $code ? '000000' : '999999'));

$uri = TOTP::getOtpAuthUrl('somchai', $secret, 'MoneyLife');
assertTest("TOTP otpauth URL matches standard format", strpos($uri, 'otpauth://totp/MoneyLife') === 0);

// ----------------------------------------------------
// TEST 4: Student Data Operations & Queries
// ----------------------------------------------------
echo "\n--- 4. Student Management & Data Integrity ---\n";

$insStmt = $pdo->prepare("
    INSERT INTO students (student_code, first_name, last_name, class_level, class_room, student_number, status)
    VALUES ('TEST01', 'เด็กชายทดสอบ', 'ระบบครู', 'ม.1', '9', 99, 'pending')
");
$insStmt->execute();
$testStudentId = $pdo->lastInsertId();
assertTest("Added test student with 'pending' status", $testStudentId > 0);

$pdo->prepare("UPDATE students SET status = 'active' WHERE id = ?")->execute([$testStudentId]);
$checkStatus = $pdo->query("SELECT status FROM students WHERE id = $testStudentId")->fetchColumn();
assertTest("Approved student status changed to 'active'", $checkStatus === 'active');

// สร้าง dummy session ให้ test student
$pdo->prepare("INSERT INTO game_sessions (student_id, total_score, max_score, percentage, completed_at) VALUES (?, 45, 50, 90.00, NOW())")
    ->execute([$testStudentId]);
$testSessionId = $pdo->lastInsertId();
assertTest("Created test game session #$testSessionId", $testSessionId > 0);

// สร้าง game answers
$pdo->prepare("INSERT INTO game_answers (session_id, scenario_number, selected_option, score) VALUES (?, 1, 'ฝากธนาคาร 50%', 10)")
    ->execute([$testSessionId]);
$ansCount = $pdo->query("SELECT COUNT(*) FROM game_answers WHERE session_id = $testSessionId")->fetchColumn();
assertTest("Scenario 1 answer recorded successfully", $ansCount > 0);

// Clean test student
$pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$testStudentId]);
$delCheck = $pdo->query("SELECT COUNT(*) FROM students WHERE id = $testStudentId")->fetchColumn();
assertTest("Cascade clean test student and session", $delCheck == 0);

// ----------------------------------------------------
// TEST 5: Audit Log Verification
// ----------------------------------------------------
echo "\n--- 5. Audit Logging ---\n";

log_audit($pdo, 'TEST_AUDIT_ACTION', 'system', '0', ['message' => 'Verification test running']);
$latestAudit = $pdo->query("SELECT * FROM audit_logs WHERE action = 'TEST_AUDIT_ACTION' ORDER BY id DESC LIMIT 1")->fetch();
assertTest("Audit log recorded in DB", !empty($latestAudit));
assertTest("Audit log IP address recorded", !empty($latestAudit['ip_address']));

// ----------------------------------------------------
// Final Summary
// ----------------------------------------------------
echo "\n========================================================\n";
echo "  SUMMARY: $testsPassed / $totalTests tests passed (" . round(($testsPassed / $totalTests) * 100, 1) . "%)\n";
echo "========================================================\n";

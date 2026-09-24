<?php
require_once __DIR__ . '/config/database.php';

$nationalId = '1329900585149';
$phone = '0873565288';
$password = '0873565288';
$passHash = password_hash($password, PASSWORD_DEFAULT);

echo "--- Updating Admin Account ---\n";
echo "National ID (User): $nationalId\n";
echo "Phone / Password: $phone\n";

try {
    // ลบหรืออัปเดตบัญชี admin เดิมที่อาจใช้เบอร์หรือเลขอื่น
    // และตั้งค่าบัญชี 1329900585149 เป็น admin สูงสุด
    $stmt = $pdo->prepare("SELECT id FROM teachers WHERE national_id = ? OR phone = ?");
    $stmt->execute([$nationalId, $phone]);
    $existing = $stmt->fetch();

    if ($existing) {
        $upd = $pdo->prepare("
            UPDATE teachers 
            SET first_name = 'ผู้ดูแลระบบ',
                last_name = '(Admin)',
                position = 'ผู้ดูแลระบบหลัก',
                national_id = ?,
                phone = ?,
                password_hash = ?,
                status = 'approved',
                phone_verified = 1,
                role = 'admin',
                failed_login_attempts = 0,
                locked_until = NULL,
                approved_at = NOW()
            WHERE id = ?
        ");
        $upd->execute([$nationalId, $phone, $passHash, $existing['id']]);
        echo "✓ Updated existing account ID {$existing['id']} to Admin!\n";
    } else {
        $ins = $pdo->prepare("
            INSERT INTO teachers 
            (first_name, last_name, position, national_id, phone, password_hash, status, phone_verified, role, approved_at)
            VALUES ('ผู้ดูแลระบบ', '(Admin)', 'ผู้ดูแลระบบหลัก', ?, ?, ?, 'approved', 1, 'admin', NOW())
        ");
        $ins->execute([$nationalId, $phone, $passHash]);
        $newId = $pdo->lastInsertId();
        echo "✓ Created new Admin account ID {$newId}!\n";
    }

    // ตรวจสอบความถูกต้อง
    $chk = $pdo->prepare("SELECT * FROM teachers WHERE national_id = ?");
    $chk->execute([$nationalId]);
    $admin = $chk->fetch();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        echo "✓ VERIFIED: Admin login with User: {$nationalId} / Password: {$password} is READY!\n";
    } else {
        echo "✕ Verification failed!\n";
    }

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

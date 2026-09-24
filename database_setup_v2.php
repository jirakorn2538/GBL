<?php
require_once __DIR__ . '/config/database.php';

echo "--- Money Life Teacher System V2 Migration ---\n";

try {
    // ตรวจสอบและอัปเดตตาราง teachers
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `teachers` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `first_name` VARCHAR(100) NOT NULL,
            `last_name` VARCHAR(100) NOT NULL,
            `position` VARCHAR(100) NOT NULL DEFAULT 'ครูผู้สอน',
            `national_id` VARCHAR(13) NOT NULL UNIQUE,
            `phone` VARCHAR(20) NOT NULL UNIQUE,
            `password_hash` VARCHAR(255) NOT NULL,
            `status` ENUM('pending', 'approved', 'rejected', 'suspended') NOT NULL DEFAULT 'pending',
            `phone_verified` TINYINT(1) NOT NULL DEFAULT 0,
            `role` ENUM('teacher', 'admin') NOT NULL DEFAULT 'teacher',
            `otp_code` VARCHAR(10) NULL,
            `otp_expires_at` DATETIME NULL,
            `failed_login_attempts` INT NOT NULL DEFAULT 0,
            `locked_until` DATETIME NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `approved_at` DATETIME NULL,
            `approved_by` INT UNSIGNED NULL,
            `last_login_at` DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    // ตรวจสอบคอลัมน์เพิ่มเติมกรณีตารางเดิมมีอยู่แล้ว
    $cols = $pdo->query("SHOW COLUMNS FROM teachers")->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array('first_name', $cols)) {
        $pdo->exec("ALTER TABLE teachers ADD COLUMN `first_name` VARCHAR(100) NOT NULL AFTER `id`");
    }
    if (!in_array('last_name', $cols)) {
        $pdo->exec("ALTER TABLE teachers ADD COLUMN `last_name` VARCHAR(100) NOT NULL AFTER `first_name`");
    }
    if (!in_array('position', $cols)) {
        $pdo->exec("ALTER TABLE teachers ADD COLUMN `position` VARCHAR(100) NOT NULL DEFAULT 'ครูผู้สอน' AFTER `last_name`");
    }
    if (!in_array('national_id', $cols)) {
        $pdo->exec("ALTER TABLE teachers ADD COLUMN `national_id` VARCHAR(13) NULL AFTER `position`");
        $pdo->exec("ALTER TABLE teachers ADD UNIQUE (`national_id`)");
    }
    if (!in_array('phone', $cols)) {
        $pdo->exec("ALTER TABLE teachers ADD COLUMN `phone` VARCHAR(20) NULL AFTER `national_id`");
        $pdo->exec("ALTER TABLE teachers ADD UNIQUE (`phone`)");
    }
    if (!in_array('status', $cols)) {
        $pdo->exec("ALTER TABLE teachers ADD COLUMN `status` ENUM('pending', 'approved', 'rejected', 'suspended') NOT NULL DEFAULT 'pending' AFTER `password_hash`");
    }
    if (!in_array('phone_verified', $cols)) {
        $pdo->exec("ALTER TABLE teachers ADD COLUMN `phone_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`");
    }
    if (!in_array('approved_at', $cols)) {
        $pdo->exec("ALTER TABLE teachers ADD COLUMN `approved_at` DATETIME NULL AFTER `created_at`");
    }
    if (!in_array('approved_by', $cols)) {
        $pdo->exec("ALTER TABLE teachers ADD COLUMN `approved_by` INT UNSIGNED NULL AFTER `approved_at`");
    }
    if (!in_array('otp_code', $cols)) {
        $pdo->exec("ALTER TABLE teachers ADD COLUMN `otp_code` VARCHAR(10) NULL AFTER `role`");
    }
    if (!in_array('otp_expires_at', $cols)) {
        $pdo->exec("ALTER TABLE teachers ADD COLUMN `otp_expires_at` DATETIME NULL AFTER `otp_code`");
    }

    echo "✓ Table 'teachers' updated with all required columns.\n";

    // สร้างหรืออัปเดตบัญชี Admin เริ่มต้น
    // ใช้เลข 13 หลักสำหรับ Admin: 1100000000001 และเบอร์ 0812345678
    // Checksum ของ 1100000000001: 
    // 1*13 + 1*12 + 0... = 25. 25%11 = 3. 11-3 = 8 -> 1100000000008
    $adminNatId = '1100000000008';
    $adminPhone = '0812345678';
    $adminPass = password_hash('AdminPassword@123', PASSWORD_DEFAULT);

    $chkAdmin = $pdo->prepare("SELECT id FROM teachers WHERE national_id = ? OR role = 'admin' LIMIT 1");
    $chkAdmin->execute([$adminNatId]);
    $adminRow = $chkAdmin->fetch();

    if (!$adminRow) {
        $ins = $pdo->prepare("
            INSERT INTO teachers (first_name, last_name, position, national_id, phone, password_hash, status, phone_verified, role, approved_at)
            VALUES ('ผู้ดูแล', 'ระบบ', 'ผู้ดูแลระบบกลาง', ?, ?, ?, 'approved', 1, 'admin', NOW())
        ");
        $ins->execute([$adminNatId, $adminPhone, $adminPass]);
        echo "✓ Created default Admin: National ID {$adminNatId} / Phone {$adminPhone} / Password: AdminPassword@123\n";
    } else {
        $upd = $pdo->prepare("
            UPDATE teachers 
            SET national_id = ?, phone = ?, status = 'approved', phone_verified = 1, role = 'admin',
                first_name = 'ผู้ดูแล', last_name = 'ระบบ', position = 'ผู้ดูแลระบบกลาง'
            WHERE id = ?
        ");
        $upd->execute([$adminNatId, $adminPhone, $adminRow['id']]);
        echo "✓ Synced default Admin credentials: National ID {$adminNatId} / Phone {$adminPhone} / Password: AdminPassword@123\n";
    }

    // สร้างหรืออัปเดตบัญชี ครูตัวอย่าง (Approved)
    // 1100000000016: 1*13 + 1*12 + 1*2 = 27. 27%11 = 5. 11-5 = 6 -> 1100000000016
    $teacherNatId = '1100000000016';
    $teacherPhone = '0898765432';
    $teacherPass = password_hash('TeacherPassword@123', PASSWORD_DEFAULT);

    $chkTeacher = $pdo->prepare("SELECT id FROM teachers WHERE national_id = ? LIMIT 1");
    $chkTeacher->execute([$teacherNatId]);
    $teacherRow = $chkTeacher->fetch();

    if (!$teacherRow) {
        $ins = $pdo->prepare("
            INSERT INTO teachers (first_name, last_name, position, national_id, phone, password_hash, status, phone_verified, role, approved_at)
            VALUES ('สมชาย', 'ใจดี', 'ครูชำนาญการ', ?, ?, ?, 'approved', 1, 'teacher', NOW())
        ");
        $ins->execute([$teacherNatId, $teacherPhone, $teacherPass]);
        echo "✓ Created sample teacher: National ID {$teacherNatId} / Phone {$teacherPhone} / Password: TeacherPassword@123\n";
    } else {
        $upd = $pdo->prepare("
            UPDATE teachers 
            SET national_id = ?, phone = ?, status = 'approved', phone_verified = 1, role = 'teacher',
                first_name = 'สมชาย', last_name = 'ใจดี', position = 'ครูชำนาญการ'
            WHERE id = ?
        ");
        $upd->execute([$teacherNatId, $teacherPhone, $teacherRow['id']]);
        echo "✓ Synced sample teacher: National ID {$teacherNatId} / Phone {$teacherPhone} / Password: TeacherPassword@123\n";
    }

    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

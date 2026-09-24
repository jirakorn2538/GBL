<?php
require_once __DIR__ . '/config/database.php';

echo "--- Money Life Teacher System Migration ---\n";

try {
    // 1. สร้างตาราง teachers
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `teachers` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `username` VARCHAR(50) NOT NULL UNIQUE,
            `password_hash` VARCHAR(255) NOT NULL,
            `full_name` VARCHAR(100) NOT NULL,
            `email` VARCHAR(100) NOT NULL UNIQUE,
            `role` ENUM('teacher', 'admin') NOT NULL DEFAULT 'teacher',
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `failed_login_attempts` INT NOT NULL DEFAULT 0,
            `locked_until` DATETIME NULL,
            `two_factor_secret` VARCHAR(255) NULL,
            `two_factor_enabled` TINYINT(1) NOT NULL DEFAULT 0,
            `remember_token` VARCHAR(255) NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `last_login_at` DATETIME NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✓ Table 'teachers' ready.\n";

    // 2. สร้างตาราง audit_logs
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `audit_logs` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `teacher_id` INT UNSIGNED NULL,
            `action` VARCHAR(100) NOT NULL,
            `target_type` VARCHAR(50) NULL,
            `target_id` VARCHAR(50) NULL,
            `details` TEXT NULL,
            `ip_address` VARCHAR(45) NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_teacher` (`teacher_id`),
            KEY `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    echo "✓ Table 'audit_logs' ready.\n";

    // 3. ตรวจสอบและเพิ่ม column status ใน students
    $cols = $pdo->query("SHOW COLUMNS FROM `students` LIKE 'status'")->fetchAll();
    if (count($cols) === 0) {
        $pdo->exec("ALTER TABLE `students` ADD COLUMN `status` ENUM('active', 'pending', 'rejected') NOT NULL DEFAULT 'active' AFTER `student_number`");
        echo "✓ Added 'status' column to 'students' table.\n";
    } else {
        echo "✓ Column 'status' already exists in 'students'.\n";
    }

    // 4. สร้างบัญชีตั้งต้น (Admin & Teacher)
    $stmt = $pdo->prepare("SELECT id FROM teachers WHERE username = ?");
    $stmt->execute(['admin']);
    if (!$stmt->fetch()) {
        $adminPass = password_hash('AdminPassword@123', PASSWORD_DEFAULT);
        $insertAdmin = $pdo->prepare("
            INSERT INTO teachers (username, password_hash, full_name, email, role, is_active)
            VALUES (?, ?, ?, ?, 'admin', 1)
        ");
        $insertAdmin->execute(['admin', $adminPass, 'ผู้ดูแลระบบ (Admin)', 'admin@moneylife.local']);
        echo "✓ Created default admin: admin / AdminPassword@123\n";
    } else {
        echo "✓ Default admin already exists.\n";
    }

    $stmt = $pdo->prepare("SELECT id FROM teachers WHERE username = ?");
    $stmt->execute(['teacher1']);
    if (!$stmt->fetch()) {
        $teacherPass = password_hash('TeacherPassword@123', PASSWORD_DEFAULT);
        $insertTeacher = $pdo->prepare("
            INSERT INTO teachers (username, password_hash, full_name, email, role, is_active)
            VALUES (?, ?, ?, ?, 'teacher', 1)
        ");
        $insertTeacher->execute(['teacher1', $teacherPass, 'ครูสมชาย ใจดี', 'somchai@moneylife.local']);
        echo "✓ Created default teacher: teacher1 / TeacherPassword@123\n";
    } else {
        echo "✓ Default teacher already exists.\n";
    }

    echo "\nMigration completed successfully!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

<?php
require_once __DIR__ . '/config/database.php';

try {
    // 1. ตรวจสอบ keys ในตาราง teachers
    $stmt = $pdo->query("SHOW KEYS FROM teachers");
    $keys = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($keys as $k) {
        $keyName = $k['Key_name'];
        if ($keyName === 'username' || $keyName === 'email') {
            try {
                $pdo->exec("ALTER TABLE teachers DROP INDEX `$keyName`");
                echo "✓ Dropped index: $keyName\n";
            } catch (Exception $e) {
                // Ignore
            }
        }
    }

    // 2. ปรับคอลัมน์เก่าให้ NULLABLE หากยังมีอยู่
    $cols = $pdo->query("SHOW COLUMNS FROM teachers")->fetchAll(PDO::FETCH_ASSOC);
    $colNames = array_column($cols, 'Field');

    if (in_array('username', $colNames)) {
        $pdo->exec("ALTER TABLE teachers MODIFY COLUMN `username` VARCHAR(50) NULL");
        echo "✓ Modified 'username' to NULL\n";
    }
    if (in_array('email', $colNames)) {
        $pdo->exec("ALTER TABLE teachers MODIFY COLUMN `email` VARCHAR(100) NULL");
        echo "✓ Modified 'email' to NULL\n";
    }
    if (in_array('full_name', $colNames)) {
        $pdo->exec("ALTER TABLE teachers MODIFY COLUMN `full_name` VARCHAR(100) NULL");
        echo "✓ Modified 'full_name' to NULL\n";
    }

    echo "Schema adjusted successfully!\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

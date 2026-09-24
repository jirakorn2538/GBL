<?php
/**
 * Money Life - Database Connection Handler
 * Supports both local MySQL (XAMPP) and Supabase PostgreSQL (Cloud / Vercel)
 */

class SafePDO extends PDO {
    #[\ReturnTypeWillChange]
    public function lastInsertId($name = null) {
        $driver = $this->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'pgsql') {
            if ($name !== null) {
                try {
                    $id = parent::lastInsertId($name);
                    if ($id && $id !== '0') return (string)$id;
                } catch (Throwable $e) {}
            }
            try {
                $val = $this->query("SELECT lastval()")->fetchColumn();
                if ($val !== false && $val !== null) return (string)$val;
            } catch (Throwable $e) {}
        }
        return parent::lastInsertId($name);
    }
}

// 1. ตรวจสอบ Environment Variables (เช่น จาก Vercel หรือ Cloud Host)
$dbUrl = getenv('DATABASE_URL') ?: getenv('POSTGRES_URL') ?: getenv('SUPABASE_DB_URL') ?: '';

if (!empty($dbUrl)) {
    // เชื่อมต่อผ่าน Database Connection String (เช่น Supabase PostgreSQL บน Vercel)
    $parsed = parse_url($dbUrl);
    $driver = (!empty($parsed['scheme']) && strpos($parsed['scheme'], 'mysql') !== false) ? 'mysql' : 'pgsql';
    $host = $parsed['host'] ?? 'localhost';
    $port = $parsed['port'] ?? ($driver === 'pgsql' ? 5432 : 3306);
    $dbname = ltrim($parsed['path'] ?? 'postgres', '/');
    $user = isset($parsed['user']) ? urldecode($parsed['user']) : '';
    $pass = isset($parsed['pass']) ? urldecode($parsed['pass']) : '';

    if ($driver === 'pgsql') {
        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";
    } else {
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    }
} else {
    // 2. ตรวจสอบแยกรายตัวแปร หรือใช้ค่าเริ่มต้นสำหรับ Local XAMPP
    $driver = getenv('DB_TYPE') ?: ((getenv('DB_PORT') == '5432' || getenv('DB_PORT') == '6543') ? 'pgsql' : 'mysql');
    $host = getenv('DB_HOST') ?: 'localhost';
    $port = getenv('DB_PORT') ?: ($driver === 'pgsql' ? 5432 : 3306);
    $dbname = getenv('DB_NAME') ?: ($driver === 'pgsql' ? 'postgres' : 'money_life');
    $user = getenv('DB_USER') ?: 'root';
    $pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

    if ($driver === 'pgsql') {
        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";
    } else {
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    }
}

try {
    $pdo = new SafePDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . htmlspecialchars($e->getMessage()));
}
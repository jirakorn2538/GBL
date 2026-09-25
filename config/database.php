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

// 1. ตรวจสอบการรันบน Cloud / Vercel
$isVercel = !empty($_ENV['VERCEL']) 
         || !empty($_SERVER['VERCEL']) 
         || (getenv('VERCEL') !== false && getenv('VERCEL') !== '');

// 2. ดึงค่า Connection String จาก Environment Variables
$dbUrl = $_ENV['DATABASE_URL'] 
      ?? $_SERVER['DATABASE_URL'] 
      ?? getenv('DATABASE_URL') 
      ?? $_ENV['POSTGRES_URL'] 
      ?? $_SERVER['POSTGRES_URL'] 
      ?? getenv('POSTGRES_URL') 
      ?? $_ENV['SUPABASE_DB_URL'] 
      ?? $_SERVER['SUPABASE_DB_URL'] 
      ?? getenv('SUPABASE_DB_URL') 
      ?? '';

if ($isVercel && empty($dbUrl)) {
    die("
    <!DOCTYPE html>
    <html lang='th'>
    <head><meta charset='UTF-8'><title>Money Life - Database Setup Required</title></head>
    <body style='font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, sans-serif; background: #f8fafc; padding: 40px 20px; color: #334155;'>
        <div style='max-width: 650px; margin: 0 auto; background: #ffffff; border: 2px solid #ef4444; border-radius: 16px; padding: 32px; box-shadow: 0 10px 25px rgba(0,0,0,0.05);'>
            <h2 style='color: #dc2626; margin-top: 0; display: flex; align-items: center; gap: 8px;'>⚠️ ยังไม่พบการตั้งค่า DATABASE_URL บน Vercel</h2>
            <p style='line-height: 1.6;'>ระบบกำลังทำงานบน Vercel แต่ยังไม่ได้รับค่า <b>DATABASE_URL</b> สำหรับเชื่อมต่อกับ <b>Supabase</b> ครับ</p>
            <hr style='border: 0; border-top: 1px solid #e2e8f0; margin: 20px 0;'>
            <h3 style='color: #1e293b; margin-bottom: 12px;'>🛠️ ขั้นตอนการแก้ไขง่ายๆ ใน 1 นาที:</h3>
            <ol style='line-height: 1.8; padding-left: 24px;'>
                <li>เปิดหน้าเว็บ Vercel โปรเจกต์ <b>GBL</b></li>
                <li>ไปที่แท็บ <b>Settings</b> &rarr; เมนูด้านซ้ายเลือก <b>Environment Variables</b></li>
                <li>ในช่อง <b>Key</b>: พิมพ์ <code>DATABASE_URL</code></li>
                <li>ในช่อง <b>Value</b>: วาง Connection URI จาก Supabase ที่ใส่รหัสผ่านแล้ว</li>
                <li>ตรวจสอบให้แน่ใจว่าติ๊กเลือกทั้ง <b>Production</b> และ <b>Preview</b> แล้วกด <b>Save</b></li>
                <li>ไปที่แท็บ <b>Deployments</b> &rarr; กดปุ่ม <b>...</b> ที่รายการล่าสุด &rarr; เลือก <b>Redeploy</b></li>
            </ol>
        </div>
    </body>
    </html>
    ");
}

if (!empty($dbUrl)) {
    // แยกส่วนประกอบของ URL ด้วย Regex (รองรับรหัสผ่านที่มีอักขระพิเศษอย่าง @, #, !)
    $driver = 'pgsql';
    $host = 'localhost';
    $port = 5432;
    $dbname = 'postgres';
    $user = 'postgres';
    $pass = '';

    if (preg_match('#^(postgres(?:ql)?|mysql)://([^:]+):(.*)@([^:/@]+)(?::(\d+))?/(.*)$#', $dbUrl, $m)) {
        $driver = (strpos($m[1], 'mysql') !== false) ? 'mysql' : 'pgsql';
        $user = urldecode($m[2]);
        $pass = urldecode($m[3]);
        $host = $m[4];
        $port = !empty($m[5]) ? (int)$m[5] : ($driver === 'pgsql' ? 5432 : 3306);
        $dbname = preg_replace('/\?.*$/', '', $m[6]); // ตัด query string ออกถ้ามี
    } else {
        // Fallback ใช้ parse_url แบบเดิม
        $parsed = parse_url($dbUrl);
        $driver = (!empty($parsed['scheme']) && strpos($parsed['scheme'], 'mysql') !== false) ? 'mysql' : 'pgsql';
        $host = $parsed['host'] ?? 'localhost';
        $port = $parsed['port'] ?? ($driver === 'pgsql' ? 5432 : 3306);
        $dbname = ltrim(preg_replace('/\?.*$/', '', $parsed['path'] ?? 'postgres'), '/');
        $user = isset($parsed['user']) ? urldecode($parsed['user']) : '';
        $pass = isset($parsed['pass']) ? urldecode($parsed['pass']) : '';
    }

    if ($driver === 'pgsql') {
        $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};sslmode=require";
    } else {
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    }
} else {
    // 3. Fallback สำหรับเครื่อง Localhost (XAMPP)
    $driver = 'mysql';
    $host = 'localhost';
    $port = 3306;
    $dbname = 'money_life';
    $user = 'root';
    $pass = '';

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
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
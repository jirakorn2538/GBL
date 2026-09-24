<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Money Life</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f4f7fb;
            margin: 0;
            padding: 0;
        }

        .container {
            max-width: 700px;
            margin: 80px auto;
            background: white;
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        h1 {
            font-size: 42px;
            margin-bottom: 10px;
        }

        .subtitle {
            font-size: 22px;
            color: #555;
            margin-bottom: 30px;
        }

        .description {
            font-size: 18px;
            line-height: 1.8;
            color: #444;
        }

        .btn {
            display: inline-block;
            margin-top: 30px;
            padding: 15px 40px;
            background: #16a085;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-size: 20px;
        }

        .btn:hover {
            background: #138d75;
        }
    </style>
</head>

<body>

<div class="container">

    <h1>💰 MONEY LIFE</h1>

    <div class="subtitle">
        เกมจำลองการวางแผนการเงินในชีวิตประจำวัน
    </div>

    <div class="description">

        <p>
            คุณจะได้รับเงินสำหรับใช้ตลอด 1 เดือน
        </p>

        <p>
            ตัดสินใจเกี่ยวกับการใช้จ่าย การออม
            และเหตุการณ์ต่าง ๆ ที่อาจเกิดขึ้น
        </p>

        <p>
            การตัดสินใจของคุณจะส่งผลต่อคะแนนการวางแผนการเงิน
        </p>

    </div>

    <div style="margin-top: 30px;">
        <a href="register.php" class="btn">
            ▶ เริ่มเล่นเกม
        </a>
    </div>

    <!-- ส่วนสำหรับผู้ดูแลระบบ (Admin) แยกจากนักเรียนอย่างชัดเจน -->
    <div style="margin-top: 40px; padding-top: 25px; border-top: 1px dashed #cbd5e1; text-align: center;">
        <div style="font-size: 15px; color: #475569; margin-bottom: 12px; font-weight: 600;">
            🛡️ สำหรับผู้ดูแลระบบ (Admin)
        </div>
        <a href="teacher/login.php" class="btn" style="background: #0f172a; font-size: 16px; padding: 10px 30px; margin-top: 0; box-shadow: 0 4px 14px rgba(15, 23, 42, 0.25);">
            เข้าสู่ระบบ Admin
        </a>
        <div style="font-size: 12px; color: #94a3b8; margin-top: 8px;">
            สำหรับติดตามข้อมูล การใช้งาน และผลคะแนนของนักเรียน
        </div>
    </div>

</div>

</body>
</html>
<?php
session_start();

require_once __DIR__ . '/config/database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $student_code = trim($_POST['student_code'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $class_level = trim($_POST['class_level'] ?? '');
    $class_room = trim($_POST['class_room'] ?? '');
    $student_number = (int)($_POST['student_number'] ?? 0);
    $photo_path = null;

    // ตรวจสอบข้อมูลเบื้องต้น
    if (
        $student_code === '' ||
        $first_name === '' ||
        $last_name === '' ||
        $class_level === '' ||
        $class_room === '' ||
        $student_number <= 0
    ) {
        $error = 'กรุณากรอกข้อมูลให้ครบทุกช่อง';
    } 
    // ตรวจสอบเลขประจำตัวนักเรียน 5 หลัก
    elseif (strlen($student_code) !== 5 || !ctype_digit($student_code)) {
        $error = 'เลขประจำตัวนักเรียนต้องเป็นตัวเลข 5 หลักเท่านั้น (เช่น 12345)';
    } else {

        // จัดการอัปโหลดรูปภาพนักเรียน
        if (isset($_FILES['student_photo']) && $_FILES['student_photo']['error'] === UPLOAD_ERR_OK) {
            $fileTmp = $_FILES['student_photo']['tmp_name'];
            $fileSize = $_FILES['student_photo']['size'];
            $fileInfo = @getimagesize($fileTmp);

            if ($fileInfo === false) {
                $error = 'ไฟล์ที่อัปโหลดไม่ใช่รูปภาพที่ถูกต้อง';
            } elseif ($fileSize > 5 * 1024 * 1024) {
                $error = 'ขนาดไฟล์รูปภาพต้องไม่เกิน 5 MB';
            } else {
                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                if (!in_array($fileInfo['mime'], $allowedMimes)) {
                    $error = 'รองรับเฉพาะไฟล์รูปภาพประเภท JPG, PNG, WEBP หรือ GIF เท่านั้น';
                } else {
                    $extMap = [
                        'image/jpeg' => 'jpg',
                        'image/png'  => 'png',
                        'image/webp' => 'webp',
                        'image/gif'  => 'gif'
                    ];
                    $ext = $extMap[$fileInfo['mime']] ?? 'jpg';

                    $uploadDir = __DIR__ . '/uploads/students/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }

                    $fileName = 'std_' . $student_code . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                    $destPath = $uploadDir . $fileName;

                    if (move_uploaded_file($fileTmp, $destPath)) {
                        $photo_path = 'uploads/students/' . $fileName;
                    } else {
                        $error = 'ไม่สามารถบันทึกไฟล์รูปภาพได้ กรุณาลองใหม่อีกครั้ง';
                    }
                }
            }
        }

        if ($error === '') {
            try {

                // 1. บันทึกข้อมูลนักเรียน (รวม student_code และ photo)
                $stmt = $pdo->prepare("
                    INSERT INTO students
                    (
                        student_code,
                        first_name,
                        last_name,
                        class_level,
                        class_room,
                        student_number,
                        photo,
                        status
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
                ");

                $stmt->execute([
                    $student_code,
                    $first_name,
                    $last_name,
                    $class_level,
                    $class_room,
                    $student_number,
                    $photo_path
                ]);

                // ID ของนักเรียนที่เพิ่งสร้าง
                $student_id = $pdo->lastInsertId();


                // 2. สร้างเกม session
                $stmt = $pdo->prepare("
                    INSERT INTO game_sessions
                    (
                        student_id,
                        total_score,
                        max_score,
                        percentage
                    )
                    VALUES (?, 0, 50, 0)
                ");

                $stmt->execute([
                    $student_id
                ]);

                // ID ของเกม session
                $game_session_id = $pdo->lastInsertId();


                // 3. เก็บ ID และข้อมูลนักเรียนไว้ใน Session
                $_SESSION['student_id'] = $student_id;
                $_SESSION['student_code'] = $student_code;
                $_SESSION['student_name'] = $first_name . ' ' . $last_name;
                $_SESSION['student_photo'] = $photo_path;
                $_SESSION['game_session_id'] = $game_session_id;


                // 4. ไปหน้าเกม
                header('Location: game.php');
                exit;

            } catch (PDOException $e) {

                $error = 'เกิดข้อผิดพลาด: ' . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="th">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Money Life - ลงทะเบียนผู้เล่น</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, "Tahoma", sans-serif;
            background: #f1f5f9;
            color: #1e293b;
        }

        .container {
            max-width: 650px;
            margin: 40px auto;
            padding: 20px;
        }

        .card {
            background: white;
            border-radius: 20px;
            padding: 35px 40px;
            box-shadow: 0 8px 30px rgba(0,0,0,0.08);
        }

        h1 {
            text-align: center;
            margin-bottom: 8px;
            font-size: 30px;
        }

        .subtitle {
            text-align: center;
            color: #64748b;
            margin-bottom: 25px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-top: 16px;
            margin-bottom: 6px;
            font-size: 15px;
        }

        input[type="text"],
        input[type="number"],
        select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-size: 15px;
            transition: border-color 0.2s;
        }

        input[type="text"]:focus,
        input[type="number"]:focus,
        select:focus {
            outline: none;
            border-color: #14b8a6;
            box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.15);
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        /* Photo Upload Section */
        .photo-upload-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            border: 2px dashed #cbd5e1;
            border-radius: 16px;
            background: #f8fafc;
            margin-bottom: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .photo-upload-container:hover {
            border-color: #14b8a6;
            background: #f0fdfa;
        }

        .photo-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: #94a3b8;
            overflow: hidden;
            margin-bottom: 10px;
            border: 3px solid white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }

        .photo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-upload-btn {
            display: inline-block;
            background: #e2e8f0;
            color: #334155;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: bold;
            margin-top: 4px;
        }

        button {
            width: 100%;
            margin-top: 26px;
            padding: 15px;
            border: none;
            border-radius: 10px;
            background: #14b8a6;
            color: white;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s;
        }

        button:hover {
            background: #0f766e;
        }

        .error {
            background: #fee2e2;
            color: #b91c1c;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-size: 14.5px;
        }

        .helper-text {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }

        @media(max-width:600px) {

            .container {
                margin: 15px auto;
                padding: 10px;
            }

            .card {
                padding: 22px 18px;
            }

            .row {
                grid-template-columns: 1fr;
            }

        }

    </style>
    <link rel="stylesheet" href="assets/css/game_theme.css">
    <script src="assets/js/game_audio.js"></script>
</head>

<body>

<?php require_once __DIR__ . '/includes/game_audio_widget.php'; ?>

<div class="container">

    <div class="card">

        <h1>👤 ลงทะเบียนผู้เล่น</h1>

        <div class="subtitle">
            กรุณากรอกข้อมูลและอัปโหลดรูปภาพก่อนเริ่มเกม
        </div>


        <?php if ($error !== ''): ?>

            <div class="error">
                ⚠️ <?= htmlspecialchars($error) ?>
            </div>

        <?php endif; ?>


        <form method="POST" action="register.php" enctype="multipart/form-data">

            <!-- ส่วนอัปโหลดรูปภาพนักเรียน -->
            <label style="text-align: center;">รูปภาพนักเรียน</label>
            <div class="photo-upload-container" onclick="document.getElementById('student_photo').click()">
                <div class="photo-preview" id="previewBox">
                    <span>📷</span>
                </div>
                <div class="photo-upload-btn">
                    📁 เลือกรูปภาพ / ถ่ายภาพ
                </div>
                <div class="helper-text">
                    รองรับไฟล์ JPG, PNG, WEBP (ขนาดไม่เกิน 5MB)
                </div>
                <input
                    type="file"
                    id="student_photo"
                    name="student_photo"
                    accept="image/*"
                    style="display: none;"
                    onchange="previewImage(this)"
                >
            </div>

            <!-- เลขประจำตัวนักเรียน 5 หลัก -->
            <label for="student_code">
                เลขประจำตัวนักเรียน (5 หลัก) *
            </label>

            <input
                type="text"
                id="student_code"
                name="student_code"
                placeholder="กรอกเลขประจำตัว 5 หลัก เช่น 12345"
                maxlength="5"
                pattern="[0-9]{5}"
                value="<?= htmlspecialchars($_POST['student_code'] ?? '') ?>"
                required
            >
            <div class="helper-text">เฉพาะตัวเลข 5 หลักเท่านั้น</div>


            <div class="row">
                <div>
                    <label for="first_name">
                        ชื่อ *
                    </label>

                    <input
                        type="text"
                        id="first_name"
                        name="first_name"
                        placeholder="กรอกชื่อ"
                        value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>"
                        required
                    >
                </div>

                <div>
                    <label for="last_name">
                        นามสกุล *
                    </label>

                    <input
                        type="text"
                        id="last_name"
                        name="last_name"
                        placeholder="กรอกนามสกุล"
                        value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>"
                        required
                    >
                </div>
            </div>


            <div class="row">

                <div>

                    <label for="class_level">
                        ชั้น *
                    </label>

                    <select id="class_level" name="class_level" required>

                        <option value="">
                            -- เลือกชั้น --
                        </option>

                        <?php 
                            $selectedClass = $_POST['class_level'] ?? '';
                            $classes = ['ม.1', 'ม.2', 'ม.3', 'ม.4', 'ม.5', 'ม.6'];
                            foreach ($classes as $c):
                        ?>
                            <option value="<?= $c ?>" <?= $selectedClass === $c ? 'selected' : '' ?>><?= $c ?></option>
                        <?php endforeach; ?>

                    </select>

                </div>


                <div>

                    <label for="class_room">
                        ห้อง *
                    </label>

                    <input
                        type="text"
                        id="class_room"
                        name="class_room"
                        placeholder="เช่น 1"
                        value="<?= htmlspecialchars($_POST['class_room'] ?? '') ?>"
                        required
                    >

                </div>

            </div>


            <label for="student_number">
                เลขที่ *
            </label>

            <input
                type="number"
                id="student_number"
                name="student_number"
                placeholder="กรอกเลขที่"
                min="1"
                max="99"
                value="<?= htmlspecialchars($_POST['student_number'] ?? '') ?>"
                required
            >


            <button type="submit">
                ▶ เริ่มเกม
            </button>

            <div style="text-align: center; margin-top: 16px;">
                <a href="index.php" style="color: #64748b; font-size: 13.5px; text-decoration: none;">← กลับไปหน้าหลัก</a>
            </div>

        </form>

    </div>

</div>

<script>
function previewImage(input) {
    const previewBox = document.getElementById('previewBox');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            previewBox.innerHTML = '<img src="' + e.target.result + '" alt="Preview">';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

</body>

</html>
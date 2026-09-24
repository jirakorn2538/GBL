<?php
session_start();

require_once __DIR__ . '/config/database.php';

/* ===============================
   ตรวจสอบ Session
================================ */

if (!isset($_SESSION['game_session_id'])) {
    header('Location: register.php');
    exit;
}

$session_id = (int) $_SESSION['game_session_id'];


/* ===============================
   ดึงข้อมูลผู้เล่น
================================ */

$stmt = $pdo->prepare("
    SELECT
        gs.id,
        gs.student_id,
        gs.total_score,
        gs.max_score,
        s.student_code,
        s.photo,
        s.first_name,
        s.last_name,
        s.class_level,
        s.class_room,
        s.student_number
    FROM game_sessions gs
    INNER JOIN students s
        ON s.id = gs.student_id
    WHERE gs.id = ?
    LIMIT 1
");

$stmt->execute([$session_id]);

$game = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$game) {
    die('ไม่พบข้อมูลเกม');
}


/* ===============================
   ตรวจว่าข้อ 1 เคยตอบหรือยัง
   ดึงคำตอบจริงมาด้วย
================================ */

$stmt = $pdo->prepare("
    SELECT
        id,
        selected_option,
        score
    FROM game_answers
    WHERE session_id = ?
      AND scenario_number = 1
    ORDER BY id DESC
    LIMIT 1
");

$stmt->execute([$session_id]);

$answered = $stmt->fetch(PDO::FETCH_ASSOC);


/* ===============================
   รับคำตอบข้อ 1
================================ */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$answered) {

    $answer = $_POST['answer'] ?? '';

    /*
       คะแนนข้อ 1
       เขียว = 12
       เหลือง = 4
       แดง = -6
    */

    $scores = [
        'green'  => 12,
        'yellow' => 4,
        'red'    => -6
    ];

    $labels = [
        'green'  => 'แบ่งเงินและวางแผนก่อนใช้',
        'yellow' => 'ใช้ก่อน เหลือเท่าไรค่อยออม',
        'red'    => 'ซื้อของที่อยากได้ก่อน'
    ];

    if (isset($scores[$answer])) {

        $score = $scores[$answer];
        $selected_option = $labels[$answer];

        try {

            $pdo->beginTransaction();

            /* บันทึกคำตอบ */
            $stmt = $pdo->prepare("
                INSERT INTO game_answers
                (
                    session_id,
                    scenario_number,
                    selected_option,
                    score
                )
                VALUES (?, 1, ?, ?)
            ");

            $stmt->execute([
                $session_id,
                $selected_option,
                $score
            ]);

            /* เพิ่มคะแนนรวม */
            $stmt = $pdo->prepare("
                UPDATE game_sessions
                SET total_score = total_score + ?
                WHERE id = ?
            ");

            $stmt->execute([
                $score,
                $session_id
            ]);

            $pdo->commit();

            /* ไปหน้าผลลัพธ์ข้อ 1 */
            header('Location: result1.php');
            exit;

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            die('เกิดข้อผิดพลาด: ' . htmlspecialchars($e->getMessage()));
        }
    }
}


/* ===============================
   ดึงคะแนนล่าสุด
================================ */

$stmt = $pdo->prepare("
    SELECT total_score
    FROM game_sessions
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$session_id]);

$current_score = (int) $stmt->fetchColumn();


/* ===============================
   ถ้าเคยตอบแล้ว
   หาคำตอบและข้อความให้ถูกต้อง
================================ */

$answered_text = '';
$answered_score = 0;
$answered_icon = '🟢';

if ($answered) {

    $answered_text = trim((string) $answered['selected_option']);
    $answered_score = (int) $answered['score'];

    if ($answered_score === 12) {
        $answered_icon = '🟢';
        $answered_text = 'แบ่งเงินและวางแผนก่อนใช้';
    } elseif ($answered_score === 4) {
        $answered_icon = '🟡';
        $answered_text = 'ใช้ก่อน เหลือเท่าไรค่อยออม';
    } elseif ($answered_score === -6) {
        $answered_icon = '🔴';
        $answered_text = 'ซื้อของที่อยากได้ก่อน';
    } else {
        $answered_icon = '🟢';
        $answered_text = trim((string)$answered['selected_option']) ?: 'ไม่พบข้อความคำตอบ';
    }
}

// ข้อความสำหรับให้อ่านออกเสียง
$scenarioPromptText = "สถานการณ์ที่ 1 เริ่มต้นเดือนใหม่ คุณได้รับเงินสำหรับใช้ตลอด 1 เดือน จำนวน 5,000 บาท ก่อนเริ่มใช้เงิน คุณจะเลือกวิธีจัดการเงินแบบใด?";
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Money Life - สถานการณ์ที่ 1 (เริ่มต้นเดือนใหม่)</title>
    <link rel="stylesheet" href="assets/css/game_theme.css">
    <script src="assets/js/game_audio.js"></script>
</head>

<body>

<?php require_once __DIR__ . '/includes/game_audio_widget.php'; ?>

<div class="game-container">

    <!-- แถบข้อมูลผู้เล่นด้านบน -->
    <div class="game-topbar">
        <div class="player-info">
            <?php if (!empty($game['photo']) && file_exists(__DIR__ . '/' . $game['photo'])): ?>
                <img src="<?= htmlspecialchars($game['photo']) ?>" alt="Avatar" class="player-avatar">
            <?php else: ?>
                <div class="player-avatar">👤</div>
            <?php endif; ?>

            <div class="player-details">
                <h3><?= htmlspecialchars($game['first_name'] . ' ' . $game['last_name']) ?></h3>
                <p>
                    <?php if (!empty($game['student_code'])): ?>
                        รหัส <strong><?= htmlspecialchars($game['student_code']) ?></strong> | 
                    <?php endif; ?>
                    ชั้น <?= htmlspecialchars($game['class_level']) ?>/<?= htmlspecialchars($game['class_room']) ?> เลขที่ <?= htmlspecialchars($game['student_number']) ?>
                </p>
            </div>
        </div>

        <div class="score-badge">
            <div class="score-label">คะแนนปัจจุบัน</div>
            <div class="score-val">⭐ <?= $current_score ?></div>
        </div>
    </div>

    <!-- Stepper แสดงความคืบหน้า 4 สถานการณ์ -->
    <div class="scenario-stepper">
        <div class="stepper-track">
            <div class="stepper-progress" style="width: 25%;"></div>
        </div>
        <div class="step-item active">
            <div class="step-circle">1</div>
            <div class="step-label">เดือนใหม่</div>
        </div>
        <div class="step-item">
            <div class="step-circle">2</div>
            <div class="step-label">ซื้อของ</div>
        </div>
        <div class="step-item">
            <div class="step-circle">3</div>
            <div class="step-label">ฉุกเฉิน</div>
        </div>
        <div class="step-item">
            <div class="step-circle">4</div>
            <div class="step-label">เป้าหมาย</div>
        </div>
    </div>

    <!-- การ์ดเกมหลัก -->
    <div class="game-card-main">

        <div class="scenario-header">
            <span class="scenario-tag">🎯 ด่านที่ 1 จาก 4</span>
            <h1 class="scenario-title">💰 สถานการณ์ที่ 1: เริ่มต้นเดือนใหม่</h1>
            <p class="scenario-subtitle">ฝึกทักษะการจัดสรรเงินและการออมเงินก่อนใช้</p>
        </div>

        <!-- กล่องเนื้อเรื่องพร้อมปุ่มฟังเสียงอ่านโจทย์ -->
        <div class="story-box" id="storyBox">
            <div class="story-box-header">
                <span class="tag">📖 เรื่องราวของคุณ</span>
                <button type="button" class="speak-btn" onclick="speakText('<?= addslashes($scenarioPromptText) ?>')">
                    <span class="icon">🔊</span> ฟังโจทย์
                </button>
            </div>
            <p style="margin: 0;">
                คุณได้รับเงินสำหรับใช้ตลอด 1 เดือน เป็นจำนวนเงินทั้งหมด <strong>5,000 บาท</strong> ก่อนเริ่มใช้เงินในการดำเนินชีวิต คุณจะจัดการเงินก้อนนี้อย่างไร?
            </p>
        </div>

        <!-- กล่องเงินเริ่มต้น -->
        <div class="money-showcase">
            <div class="money-title">💵 ยอดเงินเริ่มต้นของคุณ</div>
            <div class="money-amount">5,000 บาท</div>
        </div>

        <?php if (!$answered): ?>

            <div class="question-banner">
                <div class="question-text">
                    ❓ คุณจะเลือกวิธีจัดการเงินแบบใด?
                </div>
            </div>

            <!-- รายการตัวเลือกคำตอบ พร้อมเสียงอ่านและ BFX -->
            <div class="choices-list">

                <!-- ตัวเลือก เขียว -->
                <form method="POST" id="formGreen">
                    <input type="hidden" name="answer" value="green">
                    <button type="button" class="choice-btn" onclick="handleChoiceSelect('formGreen', 'แบ่งเงินและวางแผนก่อนใช้')">
                        <div class="choice-circle" style="background: #dcfce7; color: #16a34a;">🟢</div>
                        <div class="choice-text-wrap">
                            <strong>แบ่งเงินและวางแผนก่อนใช้</strong>
                            <div style="font-size: 13.5px; color: #64748b; font-weight: normal;">แบ่งเงินเป็นส่วนๆ สำหรับค่าใช้จ่ายจำเป็น การออม และของใช้ส่วนตัว</div>
                        </div>
                        <div class="listen-choice" onclick="event.stopPropagation(); speakText('ตัวเลือกสีเขียว: แบ่งเงินและวางแผนก่อนใช้');" title="ฟังตัวเลือกนี้">
                            🔊 ฟัง
                        </div>
                    </button>
                </form>

                <!-- ตัวเลือก เหลือง -->
                <form method="POST" id="formYellow">
                    <input type="hidden" name="answer" value="yellow">
                    <button type="button" class="choice-btn" onclick="handleChoiceSelect('formYellow', 'ใช้ก่อน เหลือเท่าไรค่อยออม')">
                        <div class="choice-circle" style="background: #fef3c7; color: #d97706;">🟡</div>
                        <div class="choice-text-wrap">
                            <strong>ใช้ก่อน เหลือเท่าไรค่อยออม</strong>
                            <div style="font-size: 13.5px; color: #64748b; font-weight: normal;">ใช้จ่ายไปเรื่อยๆ ตามความต้องการ สิ้นเดือนถ้ามีเงินเหลือค่อยนำไปเก็บออม</div>
                        </div>
                        <div class="listen-choice" onclick="event.stopPropagation(); speakText('ตัวเลือกสีเหลือง: ใช้ก่อน เหลือเท่าไรค่อยออม');" title="ฟังตัวเลือกนี้">
                            🔊 ฟัง
                        </div>
                    </button>
                </form>

                <!-- ตัวเลือก แดง -->
                <form method="POST" id="formRed">
                    <input type="hidden" name="answer" value="red">
                    <button type="button" class="choice-btn" onclick="handleChoiceSelect('formRed', 'ซื้อของที่อยากได้ก่อน')">
                        <div class="choice-circle" style="background: #fee2e2; color: #dc2626;">🔴</div>
                        <div class="choice-text-wrap">
                            <strong>ซื้อของที่อยากได้ก่อน</strong>
                            <div style="font-size: 13.5px; color: #64748b; font-weight: normal;">นำเงินไปซื้อของที่อยากได้ทันทีเพื่อความสุข ส่วนค่าใช้จ่ายอื่นค่อยหาทางแก้ไข</div>
                        </div>
                        <div class="listen-choice" onclick="event.stopPropagation(); speakText('ตัวเลือกสีแดง: ซื้อของที่อยากได้ก่อน');" title="ฟังตัวเลือกนี้">
                            🔊 ฟัง
                        </div>
                    </button>
                </form>

            </div>

        <?php else: ?>

            <!-- แสดงเมื่อเคยตอบแล้ว -->
            <div class="feedback-box <?= $answered_score > 0 ? 'great' : ($answered_score === 0 ? 'fair' : 'caution') ?>">
                <div class="feedback-icon"><?= $answered_icon ?></div>
                <h3 class="feedback-title">คุณตอบสถานการณ์นี้แล้ว</h3>
                
                <div style="margin: 14px 0;">
                    <button type="button" class="speak-btn" onclick="speakText('คุณตอบสถานการณ์นี้แล้ว คำตอบของคุณคือ <?= addslashes($answered_text) ?> ได้คะแนน <?= $answered_score ?> คะแนน')">
                        <span class="icon">🔊</span> ฟังคำตอบที่เลือก
                    </button>
                </div>

                <div style="background: white; border-radius: 14px; padding: 14px; margin: 16px auto; max-width: 480px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                    <div style="font-size: 13px; color: #64748b;">คำตอบที่คุณเลือก:</div>
                    <div style="font-size: 18px; font-weight: 700; color: #0f172a; margin-top: 4px;">
                        <?= htmlspecialchars($answered_text) ?>
                    </div>
                </div>

                <div style="font-size: 22px; font-weight: 800; color: <?= $answered_score > 0 ? '#15803d' : '#b91c1c' ?>;">
                    คะแนนที่ได้รับ: <?= $answered_score > 0 ? "+{$answered_score}" : $answered_score ?> คะแนน
                </div>
            </div>

            <!-- ปุ่มนำทาง -->
            <div class="game-actions">
                <a href="result1.php" class="btn-game btn-game-next">
                    📊 ดูผลลัพธ์และเกร็ดความรู้ข้อ 1
                </a>
                <a href="game2.php" class="btn-game btn-game-next" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">
                    ➡️ ไปสถานการณ์ที่ 2
                </a>
                <a href="index.php" class="btn-game btn-game-back">
                    🏠 หน้าหลัก
                </a>
                <a href="restart.php" class="btn-game btn-game-restart" onclick="return confirm('ยืนยันเริ่มเล่นใหม่อีกครั้ง? ข้อมูลในรอบนี้จะถูกรีเซ็ต')">
                    🔄 เล่นเกมใหม่อีกครั้ง
                </a>
            </div>

        <?php endif; ?>

    </div>

</div>

<script>
// ฟังก์ชันเมื่อคลิกเลือกตัวเลือก
function handleChoiceSelect(formId, choiceText) {
    if (window.gameAudio) {
        window.gameAudio.getAudioContext();
        window.gameAudio.playBfx('coin');
        // อ่านออกเสียงคำตอบที่เลือก แล้ว submit form
        window.gameAudio.speakThai('คุณเลือก ' + choiceText, function() {
            document.getElementById(formId).submit();
        });
        // ป้องกันกรณี Browser บล็อก speech synthesis ให้ submit หลังจาก 600ms
        setTimeout(function() {
            document.getElementById(formId).submit();
        }, 650);
    } else {
        document.getElementById(formId).submit();
    }
}
</script>

</body>
</html>

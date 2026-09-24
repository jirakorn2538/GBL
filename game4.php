<?php
session_start();
require_once __DIR__ . '/config/database.php';

if (!isset($_SESSION['game_session_id'])) {
    header('Location: register.php');
    exit;
}

$session_id = (int)$_SESSION['game_session_id'];

$stmt = $pdo->prepare("
    SELECT
        gs.total_score,
        s.first_name,
        s.last_name,
        s.class_level,
        s.class_room,
        s.student_number,
        s.student_code,
        s.photo
    FROM game_sessions gs
    INNER JOIN students s ON s.id = gs.student_id
    WHERE gs.id = ?
    LIMIT 1
");
$stmt->execute([$session_id]);
$game = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$game) {
    die('ไม่พบข้อมูลเกม');
}

$options = [
    'A' => ['text' => 'ใช้เงินฉุกเฉิน 500 บาท', 'sub' => 'ใช้เงินที่เตรียมไว้สำหรับเหตุการณ์ที่ไม่คาดคิดโดยตรง', 'score' => 12, 'badge' => '🟢 ตัวเลือก A'],
    'B' => ['text' => 'ใช้เงินสำหรับค่าใช้จ่ายทั่วไป 500 บาท', 'sub' => 'นำเงินที่เตรียมไว้สำหรับการใช้จ่ายมาใช้แทนเงินฉุกเฉิน', 'score' => 5, 'badge' => '🟡 ตัวเลือก B'],
    'C' => ['text' => 'ยืมเงินจากเพื่อน 500 บาท', 'sub' => 'เมื่อไม่มีเงินสำรองเพียงพอ จึงต้องยืมเงินและมีหนี้เพิ่ม', 'score' => -8, 'badge' => '🔴 ตัวเลือก C']
];

$stmt = $pdo->prepare("
    SELECT selected_option, score
    FROM game_answers
    WHERE session_id = ?
      AND scenario_number = 4
    ORDER BY id DESC
    LIMIT 1
");
$stmt->execute([$session_id]);
$answered = $stmt->fetch(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$answered) {
    $choice = $_POST['choice'] ?? '';

    if (!isset($options[$choice])) {
        die('ข้อมูลคำตอบไม่ถูกต้อง');
    }

    $selected_option = $options[$choice]['text'];
    $score = $options[$choice]['score'];

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO game_answers
            (session_id, scenario_number, selected_option, score)
            VALUES (?, 4, ?, ?)
        ");
        $stmt->execute([$session_id, $selected_option, $score]);

        $stmt = $pdo->prepare("
            UPDATE game_sessions
            SET total_score = total_score + ?
            WHERE id = ?
        ");
        $stmt->execute([$score, $session_id]);

        $pdo->commit();

        header('Location: result4.php');
        exit;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        die('เกิดข้อผิดพลาด: ' . htmlspecialchars($e->getMessage()));
    }
}

$stmt = $pdo->prepare("SELECT total_score FROM game_sessions WHERE id = ? LIMIT 1");
$stmt->execute([$session_id]);
$current_score = (int)$stmt->fetchColumn();

$answered_score = $answered ? (int)$answered['score'] : 0;
$answered_text = $answered ? trim((string)$answered['selected_option']) : '';
$answered_icon = '🟢';

if ($answered) {
    if ($answered_score === 12) {
        $answered_icon = '🟢';
        $answered_text = $options['A']['text'];
    } elseif ($answered_score === 5) {
        $answered_icon = '🟡';
        $answered_text = $options['B']['text'];
    } elseif ($answered_score === -8) {
        $answered_icon = '🔴';
        $answered_text = $options['C']['text'];
    }
}

$scenarioQuestionSpeech = "สถานการณ์ที่ 4 เหตุฉุกเฉิน ผ่านไปอีก 1 สัปดาห์ คุณมีอาการป่วยกะทันหัน และต้องซื้อยาและไปพบแพทย์ มีค่าใช้จ่ายฉุกเฉิน 500 บาท คุณจะเลือกวิธีใด";
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Money Life - สถานการณ์ที่ 4 (เหตุฉุกเฉิน)</title>
    <link rel="stylesheet" href="assets/css/game_theme.css">
    <script src="assets/js/game_audio.js"></script>
</head>
<body>

<?php require_once __DIR__ . '/includes/game_audio_widget.php'; ?>

<div class="game-container">

    <!-- แถบข้อมูลผู้เล่น -->
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
            <div class="score-label">คะแนนสะสม</div>
            <div class="score-val">⭐ <?= $current_score ?></div>
        </div>
    </div>

    <!-- Stepper ความคืบหน้า 4 สถานการณ์ -->
    <div class="game-stepper">
        <div class="step completed">
            <div class="step-num">✓</div>
            <div class="step-label">1. เงินก้อนแรก</div>
        </div>
        <div class="step-line active"></div>
        <div class="step completed">
            <div class="step-num">✓</div>
            <div class="step-label">2. ช้อปปิ้ง</div>
        </div>
        <div class="step-line active"></div>
        <div class="step completed">
            <div class="step-num">✓</div>
            <div class="step-label">3. โทรศัพท์พัง</div>
        </div>
        <div class="step-line active"></div>
        <div class="step active">
            <div class="step-num">4</div>
            <div class="step-label">4. เหตุฉุกเฉิน</div>
        </div>
    </div>

    <!-- การ์ดเนื้อหาเกมหลัก -->
    <div class="game-card-main">
        <div class="scenario-badge" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca;">🚑 ภารกิจสุดท้าย ที่ 4 จาก 4</div>
        <h1 class="scenario-title">เหตุฉุกเฉินไม่คาดฝัน!</h1>
        <p class="scenario-subtitle">มีอาการป่วยกะทันหัน ต้องใช้เงินด่วน คุณมีวิธีจัดการอย่างไร?</p>

        <!-- แถบแจ้งเตือนเหตุด่วนกะทันหัน (Pulsing Emergency Alert) -->
        <div class="emergency-pulsing-banner">
            <div style="display: flex; align-items: center; gap: 10px;">
                <span style="font-size: 24px;">🚨</span>
                <div>
                    <strong style="font-size: 16px;">แจ้งเตือนเหตุการณ์ไม่คาดฝัน!</strong>
                    <div style="font-size: 12px; opacity: 0.9;">ต้องตัดสินใจจัดการเงินทันที</div>
                </div>
            </div>
            <div class="emergency-pill-tag">
                <span>💊</span> ด่วนมาก
            </div>
        </div>

        <!-- กล่องเรื่องราว/โจทย์ -->
        <div class="story-box" style="background: linear-gradient(135deg, #fff7ed 0%, #ffedd5 100%); border-left: 6px solid #f97316;">
            <div class="story-header">
                <span class="story-tag" style="background: #ea580c;">📖 เรื่องราว</span>
                <button type="button" class="speak-btn" onclick="speakText('<?= addslashes($scenarioQuestionSpeech) ?>')">
                    <span class="icon">🔊</span> ฟังโจทย์
                </button>
            </div>
            <div class="story-content">
                ผ่านไปอีก 1 สัปดาห์ คุณมีอาการป่วยกะทันหัน มีไข้สูงและต้องรีบไปพบแพทย์ที่คลินิก<br>
                มีค่าใช้จ่ายเกิดขึ้นจริง <strong>500 บาท</strong> คุณจะนำเงินจากแหล่งใดมาจ่าย?
            </div>
        </div>

        <!-- การ์ดใบเสร็จค่ารักษาพยาบาล (Medical Invoice Card) -->
        <div class="medical-invoice-card">
            <div class="invoice-header">
                <div class="invoice-hospital">
                    <span>🏥</span> คลินิกเวชกรรมชุมชน (ใบเสร็จรับเงิน)
                </div>
                <div class="invoice-id">เลขที่: INV-EMG-04</div>
            </div>
            <div class="invoice-items">
                <div>💊 ค่ายาปฏิชีวนะและยาลดไข้</div>
                <div style="font-weight: 700;">350 บาท</div>
            </div>
            <div class="invoice-items">
                <div>🩺 ค่าตรวจวินิจฉัยทางการแพทย์</div>
                <div style="font-weight: 700;">150 บาท</div>
            </div>
            <div class="invoice-total">
                <div>ยอดที่ต้องชำระทันที</div>
                <div>500 บาท</div>
            </div>
        </div>

        <!-- ตารางวิเคราะห์แหล่งที่มาของเงินและผลกระทบ (Financial Source Matrix) -->
        <div style="font-size: 16px; font-weight: 700; color: #1e293b; margin: 15px 0 10px;">
            📊 เปรียบเทียบผลกระทบของแหล่งเงิน 3 แหล่ง:
        </div>
        <div class="decision-comparison-grid">
            <div class="comp-card opt-a">
                <span class="comp-badge">🟢 ตัวเลือก A (เงินฉุกเฉิน)</span>
                <div class="comp-cost">฿500</div>
                <div class="comp-balance">ดึงจาก: <strong>เงินสำรองฉุกเฉิน</strong></div>
                <div class="comp-verdict" style="color: #15803d;">
                    🛡️ ตรงจุดประสงค์ 100%! ไม่กระทบเงินกินข้าว ไม่ต้องกู้ยืม
                </div>
            </div>
            <div class="comp-card opt-b">
                <span class="comp-badge">🟡 ตัวเลือก B (เงินทั่วไป)</span>
                <div class="comp-cost">฿500</div>
                <div class="comp-balance">ดึงจาก: <strong>เงินกินอยู่ประจำวัน</strong></div>
                <div class="comp-verdict" style="color: #a16207;">
                    ⚠️ เงินกินข้าวลดลง 500 บ. ต้องยอมอดอาหาร/รัดเข็มขัด
                </div>
            </div>
            <div class="comp-card opt-c">
                <span class="comp-badge">🔴 ตัวเลือก C (ยืมเงินเพื่อน)</span>
                <div class="comp-cost">฿500</div>
                <div class="comp-balance">ดึงจาก: <strong>การก่อหนี้สิน</strong></div>
                <div class="comp-verdict" style="color: #b91c1c;">
                    💥 เป็นหนี้เพื่อนทันที เสี่ยงเสียเครดิตและมีภาระผูกพัน
                </div>
            </div>
        </div>

        <?php if (!$answered): ?>
            <!-- ฟอร์มเลือกคำตอบ -->
            <form method="POST" id="scenarioForm">
                <div class="choices-container">
                    <?php foreach ($options as $key => $opt): ?>
                        <label class="choice-item" id="label_<?= $key ?>" onclick="selectChoice('<?= $key ?>', '<?= addslashes($opt['badge'] . ' ' . $opt['text'] . '. ' . $opt['sub']) ?>')">
                            <input type="radio" name="choice" id="choice_<?= $key ?>" value="<?= $key ?>" required>
                            <div class="choice-header">
                                <span class="choice-badge"><?= $opt['badge'] ?></span>
                                <span class="choice-read-btn" title="ฟังตัวเลือกนี้" onclick="event.stopPropagation(); speakText('<?= addslashes($opt['badge'] . ' ' . $opt['text'] . '. ' . $opt['sub']) ?>')">
                                    🔊 ฟังเสียง
                                </span>
                            </div>
                            <div class="choice-text">
                                <?= htmlspecialchars($opt['text']) ?>
                            </div>
                            <div class="choice-desc">
                                <?= htmlspecialchars($opt['sub']) ?>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>

                <button type="submit" class="btn-game btn-game-primary" id="submitBtn">
                    ✨ ยืนยันคำตอบ และดูผลสรุปทั้งเกม
                </button>
            </form>
        <?php else: ?>
            <!-- กล่องแสดงสถานะเมื่อเคยตอบแล้ว -->
            <div class="feedback-box fair" style="margin-top: 24px;">
                <div class="feedback-icon"><?= $answered_icon ?></div>
                <h2 style="font-size: 22px; font-weight: 800; color: #0f172a; margin-bottom: 8px;">คุณได้ตอบข้อนี้เรียบร้อยแล้ว</h2>
                <div style="font-size: 14px; color: #64748b;">การตัดสินใจที่คุณเลือก:</div>
                <div style="font-size: 18px; font-weight: 800; color: #047857; margin: 8px 0;">
                    👉 <?= htmlspecialchars($answered_text) ?>
                </div>
                <div style="font-size: 20px; font-weight: 800; color: #0284c7; margin: 12px 0;">
                    คะแนนที่ได้รับ: <?= $answered_score > 0 ? '+' : '' ?><?= $answered_score ?> คะแนน
                </div>
                <div style="margin-top: 10px;">
                    <button type="button" class="speak-btn" onclick="speakText('คุณตอบข้อ 4 แล้ว เลือก <?= addslashes($answered_text) ?> ได้คะแนน <?= $answered_score ?> คะแนน')">
                        <span class="icon">🔊</span> ฟังคำตอบและคะแนน
                    </button>
                </div>
            </div>

            <div class="game-actions">
                <a href="result4.php" class="btn-game btn-game-next" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                    🏆 ดูผลคะแนนสรุปทั้งหมด
                </a>
                <a href="game3.php" class="btn-game btn-game-back">
                    ⬅️ ย้อนกลับไปดูข้อ 3
                </a>
                <a href="index.php" class="btn-game btn-game-back">
                    🏠 หน้าหลัก
                </a>
            </div>
        <?php endif; ?>

    </div>

</div>

<script>
// ฟังก์ชันช่วยเหลือเมื่อเลือกตัวเลือก
function selectChoice(key, textToSpeak) {
    const radio = document.getElementById('choice_' + key);
    if (radio) {
        radio.checked = true;
    }

    // ลบ selected จากทุกอัน
    document.querySelectorAll('.choice-item').forEach(el => el.classList.remove('selected'));
    
    // เพิ่ม selected ในอันที่คลิก
    const current = document.getElementById('label_' + key);
    if (current) {
        current.classList.add('selected');
    }

    // เล่นเสียง Effect และอ่านออกเสียงคำตอบที่เลือก
    if (window.gameAudio) {
        window.gameAudio.playBfx('coin');
        window.gameAudio.speakThai(textToSpeak);
    }
}

// ตรวจสอบก่อนส่งฟอร์ม
document.getElementById('scenarioForm')?.addEventListener('submit', function(e) {
    const checked = document.querySelector('input[name="choice"]:checked');
    if (!checked) {
        e.preventDefault();
        alert('กรุณาเลือกคำตอบก่อนยืนยันครับ/ค่ะ');
        return;
    }
    if (window.gameAudio) {
        window.gameAudio.playBfx('success');
    }
});
</script>

</body>
</html>

<?php
/**
 * Money Life - Detailed Session View
 * Shows full student info, total score, percentage, and scenario answers breakdown
 */

require_once __DIR__ . '/auth/check.php';

$sessionId = (int)($_GET['id'] ?? 0);

if ($sessionId <= 0) {
    header('Location: results.php');
    exit;
}

// 1. ดึงข้อมูล Session พร้อมข้อมูลนักเรียน
$stmt = $pdo->prepare("
    SELECT 
        gs.*,
        s.first_name,
        s.last_name,
        s.class_level,
        s.class_room,
        s.student_number,
        s.student_code,
        s.photo
    FROM game_sessions gs
    JOIN students s ON gs.student_id = s.id
    WHERE gs.id = ?
");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    header('Location: results.php?error=' . urlencode('ไม่พบข้อมูลเซสชันการเล่นนี้'));
    exit;
}

$pageTitle = "รายละเอียด Session #{$sessionId} - {$session['first_name']} {$session['last_name']}";

// 2. ดึงคำตอบในแต่ละสถานการณ์ (1-4)
$answerStmt = $pdo->prepare("
    SELECT * FROM game_answers
    WHERE session_id = ?
    ORDER BY scenario_number ASC
");
$answerStmt->execute([$sessionId]);
$answers = $answerStmt->fetchAll();

// จับกลุ่มคำตอบตาม scenario_number
$scenarioAnswers = [];
foreach ($answers as $ans) {
    $scenarioAnswers[(int)$ans['scenario_number']] = $ans;
}

// ข้อมูลชื่อหัวข้อสถานการณ์
$scenarioMeta = [
    1 => [
        'title' => 'สถานการณ์ที่ 1: การจัดสรรเงินและการออมเงิน',
        'desc' => 'ประเมินการวางแผนจัดสรรเงินก่อนนำไปใช้จ่ายและการแบ่งเก็บออม'
    ],
    2 => [
        'title' => 'สถานการณ์ที่ 2: การใช้จ่ายในชีวิตประจำวัน (จำเป็น vs อยากได้)',
        'desc' => 'ประเมินการแยกแยะระหว่างสิ่งที่จำเป็น (Need) และความต้องการ (Want)'
    ],
    3 => [
        'title' => 'สถานการณ์ที่ 3: การรับมือเหตุการณ์ฉุกเฉินและเงินสำรอง',
        'desc' => 'ประเมินการแก้ปัญหาทางการเงินเมื่อเกิดเหตุการณ์ฉุกเฉินไม่คาดฝัน'
    ],
    4 => [
        'title' => 'สถานการณ์ที่ 4: การวางแผนการเงินระยะยาวและการลงทุน',
        'desc' => 'ประเมินการต่อยอดเงินออมเพื่อเป้าหมายในอนาคต'
    ]
];

// Audit log
log_audit($pdo, 'VIEW_SESSION_DETAIL', 'game_sessions', $sessionId, ['student_id' => $session['student_id']]);

require_once __DIR__ . '/includes/header.php';
?>

<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <a href="student.php?id=<?= $session['student_id'] ?>" style="color: #64748b; text-decoration: none; font-size: 14px;">
            ← กลับไปประวัติของ <?= e($session['first_name'] . ' ' . $session['last_name']) ?>
        </a>
    </div>
    <div style="display: flex; gap: 10px;">
        <button onclick="window.print()" class="btn btn-outline btn-sm">
            🖨️ พิมพ์หน้านี้
        </button>
    </div>
</div>

<!-- ส่วนหัวสรุปข้อมูลนักเรียนและผลคะแนน Session -->
<div class="card" style="border-top: 4px solid #10b981;">
    <div class="card-body">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <?php if (!empty($session['photo']) && file_exists(__DIR__ . '/../' . $session['photo'])): ?>
                    <img src="../<?= e($session['photo']) ?>" alt="Student Photo" 
                         style="width: 70px; height: 70px; border-radius: 50%; object-fit: cover; border: 3px solid #10b981;">
                <?php else: ?>
                    <div style="width: 70px; height: 70px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; font-size: 32px; color: #94a3b8; border: 3px solid #cbd5e1;">
                        👤
                    </div>
                <?php endif; ?>

                <div>
                    <span class="badge badge-info" style="font-size: 12px; margin-bottom: 4px;">
                        🎮 Session ID: #<?= $session['id'] ?>
                    </span>
                    <h2 style="font-size: 22px; color: #0f172a; margin: 2px 0 4px;">
                        <?= e($session['first_name'] . ' ' . $session['last_name']) ?>
                    </h2>
                    <div style="font-size: 14px; color: #64748b; display: flex; gap: 16px;">
                        <span>รหัสประจำตัว: <strong><?= e($session['student_code'] ?: '-') ?></strong></span>
                        <span>ชั้น: <strong><?= e($session['class_level']) ?>/<?= e($session['class_room']) ?></strong></span>
                        <span>เลขที่: <strong><?= e($session['student_number']) ?></strong></span>
                    </div>
                </div>
            </div>

            <!-- คะแนนรวมและเปอร์เซ็นต์ -->
            <div style="display: flex; gap: 20px; align-items: center; background: #f8fafc; padding: 16px 24px; border-radius: 12px; border: 1px solid #e2e8f0;">
                <div style="text-align: right;">
                    <div style="font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: 600;">คะแนนรวมทั้งหมด</div>
                    <div style="font-size: 28px; font-weight: 700; color: #0f172a;">
                        <?= $session['total_score'] ?> <span style="font-size: 16px; font-weight: 400; color: #64748b;">/ <?= $session['max_score'] ?></span>
                    </div>
                </div>
                <div style="border-left: 2px solid #e2e8f0; height: 40px;"></div>
                <div>
                    <div style="font-size: 12px; color: #64748b; text-transform: uppercase; font-weight: 600;">ผลการประเมิน</div>
                    <div style="font-size: 28px; font-weight: 700; color: <?= $session['percentage'] >= 70 ? '#10b981' : ($session['percentage'] >= 50 ? '#3b82f6' : '#ef4444') ?>;">
                        <?= $session['percentage'] ?>%
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #f1f5f9; display: flex; gap: 24px; font-size: 13.5px; color: #64748b;">
            <span>⏱️ วันที่เริ่มเล่น: <strong><?= date('d/m/Y H:i:s', strtotime($session['started_at'])) ?></strong></span>
            <span>🏁 วันที่เล่นจบ: <strong><?= $session['completed_at'] ? date('d/m/Y H:i:s', strtotime($session['completed_at'])) : 'ยังเล่นไม่จบ' ?></strong></span>
            <span>สถานะ: 
                <?php if ($session['completed_at']): ?>
                    <span class="badge badge-success">เสร็จสมบูรณ์</span>
                <?php else: ?>
                    <span class="badge badge-warning">ยังไม่สิ้นสุด</span>
                <?php endif; ?>
            </span>
        </div>
    </div>
</div>

<!-- รายละเอียดคำตอบสถานการณ์ที่ 1 ถึง 4 -->
<h3 style="font-size: 18px; color: #0f172a; margin: 30px 0 16px; display: flex; align-items: center; gap: 8px;">
    <span>📝</span> การตัดสินใจในแต่ละสถานการณ์ (Scenarios 1 - 4)
</h3>

<div style="display: flex; flex-direction: column; gap: 18px;">
    <?php for ($i = 1; $i <= 4; $i++): ?>
        <?php 
            $ans = $scenarioAnswers[$i] ?? null;
            $meta = $scenarioMeta[$i];
            $score = $ans ? (int)$ans['score'] : null;
        ?>
        <div class="card" style="margin-bottom: 0;">
            <div class="card-header" style="background: #ffffff;">
                <div>
                    <div style="font-size: 16px; font-weight: 700; color: #0f172a;">
                        <?= $meta['title'] ?>
                    </div>
                    <div style="font-size: 12.5px; color: #64748b; margin-top: 2px;">
                        <?= $meta['desc'] ?>
                    </div>
                </div>
                <div>
                    <?php if ($ans): ?>
                        <span class="badge <?= $score > 0 ? 'badge-success' : ($score === 0 ? 'badge-warning' : 'badge-danger') ?>" style="font-size: 14px; padding: 6px 14px;">
                            คะแนน: <?= $score > 0 ? "+{$score}" : $score ?> คะแนน
                        </span>
                    <?php else: ?>
                        <span class="badge badge-secondary" style="font-size: 13px;">ยังไม่ได้ตอบ</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body" style="background: #fafbfc;">
                <?php if ($ans): ?>
                    <div style="background: white; padding: 16px; border-radius: 10px; border: 1px solid #e2e8f0;">
                        <div style="font-size: 13px; font-weight: 600; color: #64748b; margin-bottom: 6px;">คำตอบที่นักเรียนเลือก:</div>
                        <div style="font-size: 15px; color: #0f172a; font-weight: 500; line-height: 1.6;">
                            👉 <?= e($ans['selected_option']) ?>
                        </div>
                        <div style="margin-top: 12px; font-size: 12px; color: #94a3b8;">
                            บันทึกเวลา: <?= date('d/m/Y H:i:s', strtotime($ans['answered_at'])) ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; color: #94a3b8; padding: 14px;">
                        นักเรียนยังเล่นไม่ถึงสถานการณ์นี้ หรือไม่ได้บันทึกคำตอบ
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endfor; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * Money Life - Deep Analytics & Scenario Performance
 * Interactive visualizations, scenario-by-scenario breakdown, pass/fail metrics
 */

require_once __DIR__ . '/auth/check.php';

$pageTitle = 'วิเคราะห์ผลการเรียนรู้';

// Filters
$filterClass = trim($_GET['class_level'] ?? '');
$filterRoom = trim($_GET['class_room'] ?? '');
$filterDateFrom = trim($_GET['date_from'] ?? '');
$filterDateTo = trim($_GET['date_to'] ?? '');

$classLevels = $pdo->query("SELECT DISTINCT class_level FROM students WHERE class_level != '' ORDER BY class_level")->fetchAll(PDO::FETCH_COLUMN);
$classRooms = $pdo->query("SELECT DISTINCT class_room FROM students WHERE class_room != '' ORDER BY class_room")->fetchAll(PDO::FETCH_COLUMN);

// Base condition for game_sessions
$where = " WHERE 1=1 ";
$params = [];

if ($filterClass !== '') {
    $where .= " AND s.class_level = ? ";
    $params[] = $filterClass;
}
if ($filterRoom !== '') {
    $where .= " AND s.class_room = ? ";
    $params[] = $filterRoom;
}
if ($filterDateFrom !== '') {
    $where .= " AND DATE(gs.started_at) >= ? ";
    $params[] = $filterDateFrom;
}
if ($filterDateTo !== '') {
    $where .= " AND DATE(gs.started_at) <= ? ";
    $params[] = $filterDateTo;
}

// 1. ภาพรวมสถิติคะแนน (Overall Score KPIs)
$kpiSql = "
    SELECT 
        COUNT(gs.id) as total_attempts,
        COUNT(gs.completed_at) as completed_attempts,
        AVG(CASE WHEN gs.completed_at IS NOT NULL THEN gs.total_score ELSE NULL END) as avg_score,
        MAX(gs.total_score) as max_score,
        MIN(CASE WHEN gs.completed_at IS NOT NULL THEN gs.total_score ELSE NULL END) as min_score,
        AVG(CASE WHEN gs.completed_at IS NOT NULL THEN gs.percentage ELSE NULL END) as avg_percentage,
        SUM(CASE WHEN gs.completed_at IS NOT NULL AND gs.percentage >= 50 THEN 1 ELSE 0 END) as passed_count,
        SUM(CASE WHEN gs.completed_at IS NOT NULL AND gs.percentage < 50 THEN 1 ELSE 0 END) as failed_count
    FROM game_sessions gs
    JOIN students s ON gs.student_id = s.id
    $where
";
$stmt = $pdo->prepare($kpiSql);
$stmt->execute($params);
$kpi = $stmt->fetch();

$avgScore = $kpi['avg_score'] !== null ? round((float)$kpi['avg_score'], 1) : 0;
$maxScore = $kpi['max_score'] !== null ? (int)$kpi['max_score'] : 0;
$minScore = $kpi['min_score'] !== null ? (int)$kpi['min_score'] : 0;
$avgPercent = $kpi['avg_percentage'] !== null ? round((float)$kpi['avg_percentage'], 1) : 0;
$passedCount = (int)$kpi['passed_count'];
$failedCount = (int)$kpi['failed_count'];
$completedCount = (int)$kpi['completed_attempts'];
$passRate = $completedCount > 0 ? round(($passedCount / $completedCount) * 100, 1) : 0;

// 2. วิเคราะห์แยกตามสถานการณ์ 1 ถึง 4 (Scenario Breakdown)
$scenarioSql = "
    SELECT 
        ga.scenario_number,
        COUNT(ga.id) as total_answers,
        AVG(ga.score) as avg_score,
        MAX(ga.score) as max_score,
        MIN(ga.score) as min_score
    FROM game_answers ga
    JOIN game_sessions gs ON ga.session_id = gs.id
    JOIN students s ON gs.student_id = s.id
    $where
    GROUP BY ga.scenario_number
    ORDER BY ga.scenario_number ASC
";
$scStmt = $pdo->prepare($scenarioSql);
$scStmt->execute($params);
$scenarioData = $scStmt->fetchAll();

$scenarioMap = [];
foreach ($scenarioData as $sRow) {
    $scenarioMap[(int)$sRow['scenario_number']] = $sRow;
}

// 3. ดึงตัวเลือกยอดนิยมของแต่ละข้อ (Popular choice distribution)
$popularChoices = [];
for ($i = 1; $i <= 4; $i++) {
    $choiceSql = "
        SELECT ga.selected_option, COUNT(ga.id) as pick_count, AVG(ga.score) as avg_score
        FROM game_answers ga
        JOIN game_sessions gs ON ga.session_id = gs.id
        JOIN students s ON gs.student_id = s.id
        $where AND ga.scenario_number = ?
        GROUP BY ga.selected_option
        ORDER BY pick_count DESC
        LIMIT 3
    ";
    $cParams = array_merge($params, [$i]);
    $cStmt = $pdo->prepare($choiceSql);
    $cStmt->execute($cParams);
    $popularChoices[$i] = $cStmt->fetchAll();
}

$scenarioTitles = [
    1 => 'สถานการณ์ที่ 1: การจัดสรรเงินและการออม',
    2 => 'สถานการณ์ที่ 2: รายจ่ายจำเป็น vs ความอยากได้',
    3 => 'สถานการณ์ที่ 3: รับมือเหตุการณ์ฉุกเฉิน',
    4 => 'สถานการณ์ที่ 4: การวางแผนการเงินระยะยาว'
];

log_audit($pdo, 'VIEW_ANALYTICS', 'analytics', null, [
    'class' => $filterClass,
    'room' => $filterRoom
]);

require_once __DIR__ . '/includes/header.php';
?>

<!-- Filter Bar -->
<div class="filter-bar">
    <form method="GET" action="analytics.php" class="filter-group">
        <select name="class_level" class="form-control" style="width: 140px;">
            <option value="">ทุกระดับชั้น</option>
            <?php foreach ($classLevels as $lvl): ?>
                <option value="<?= e($lvl) ?>" <?= $filterClass === $lvl ? 'selected' : '' ?>><?= e($lvl) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="class_room" class="form-control" style="width: 120px;">
            <option value="">ทุกห้อง</option>
            <?php foreach ($classRooms as $rm): ?>
                <option value="<?= e($rm) ?>" <?= $filterRoom === $rm ? 'selected' : '' ?>>ห้อง <?= e($rm) ?></option>
            <?php endforeach; ?>
        </select>

        <div style="display: flex; align-items: center; gap: 6px;">
            <span style="font-size: 13px; color: #64748b;">จาก:</span>
            <input type="date" name="date_from" value="<?= e($filterDateFrom) ?>" class="form-control" style="width: 140px;">
        </div>

        <div style="display: flex; align-items: center; gap: 6px;">
            <span style="font-size: 13px; color: #64748b;">ถึง:</span>
            <input type="date" name="date_to" value="<?= e($filterDateTo) ?>" class="form-control" style="width: 140px;">
        </div>

        <button type="submit" class="btn btn-accent btn-sm">วิเคราะห์ข้อมูล</button>
        <?php if ($filterClass || $filterRoom || $filterDateFrom || $filterDateTo): ?>
            <a href="analytics.php" class="btn btn-outline btn-sm">ล้างตัวกรอง</a>
        <?php endif; ?>
    </form>
</div>

<!-- สรุปสถิติคะแนนตามข้อกำหนด ส่วนที่ 12 -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon purple">📊</div>
        <div class="stat-details">
            <h4>คะแนนเฉลี่ย</h4>
            <div class="value"><?= $avgScore ?> <span style="font-size: 15px; font-weight: 400; color: #64748b;">/ 50</span></div>
            <div class="sub">คิดเป็น <?= $avgPercent ?>%</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon green">🏆</div>
        <div class="stat-details">
            <h4>คะแนนสูงสุด</h4>
            <div class="value"><?= $maxScore ?> <span style="font-size: 15px; font-weight: 400; color: #64748b;">/ 50</span></div>
            <div class="sub">Best Score</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon amber">📉</div>
        <div class="stat-details">
            <h4>คะแนนต่ำสุด</h4>
            <div class="value"><?= $minScore ?> <span style="font-size: 15px; font-weight: 400; color: #64748b;">/ 50</span></div>
            <div class="sub">Lowest Score</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon emerald">✅</div>
        <div class="stat-details">
            <h4>จำนวนผู้ผ่านเกณฑ์</h4>
            <div class="value"><?= number_format($passedCount) ?> <span style="font-size: 15px; font-weight: 400; color: #64748b;">คน</span></div>
            <div class="sub">อัตราผ่าน <?= $passRate ?>% (≥50%)</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon rose">⚠️</div>
        <div class="stat-details">
            <h4>จำนวนผู้ไม่ผ่านเกณฑ์</h4>
            <div class="value"><?= number_format($failedCount) ?> <span style="font-size: 15px; font-weight: 400; color: #64748b;">คน</span></div>
            <div class="sub">ต้องได้รับการเสริมทักษะ (<50%)</div>
        </div>
    </div>

    <div class="stat-card">
        <div class="stat-icon blue">🎮</div>
        <div class="stat-details">
            <h4>เล่นสมบูรณ์แล้ว</h4>
            <div class="value"><?= number_format($completedCount) ?> <span style="font-size: 15px; font-weight: 400; color: #64748b;">ครั้ง</span></div>
            <div class="sub">จาก <?= number_format($kpi['total_attempts']) ?> เซสชัน</div>
        </div>
    </div>
</div>

<!-- Charts Section -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
    <!-- Chart 1: Scenario Score Comparison -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <span>📈</span> เปรียบเทียบคะแนนเฉลี่ยในแต่ละสถานการณ์
            </div>
        </div>
        <div class="card-body">
            <canvas id="scenarioChart" height="230"></canvas>
        </div>
    </div>

    <!-- Chart 2: Pass vs Fail Ratio Doughnut -->
    <div class="card">
        <div class="card-header">
            <div class="card-title">
                <span>🎯</span> สัดส่วนผู้ผ่านเกณฑ์การประเมิน
            </div>
        </div>
        <div class="card-body">
            <div style="max-width: 280px; margin: 0 auto;">
                <canvas id="passFailChart" height="230"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Scenario Details Breakdown -->
<h3 style="font-size: 18px; color: #0f172a; margin-bottom: 16px;">
    🔍 รายละเอียดเชิงลึกแยกตามสถานการณ์ (Scenarios 1 - 4)
</h3>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 20px;">
    <?php for ($i = 1; $i <= 4; $i++): ?>
        <?php 
            $sItem = $scenarioMap[$i] ?? null;
            $sAvg = $sItem ? round((float)$sItem['avg_score'], 1) : 0;
            $choices = $popularChoices[$i] ?? [];
        ?>
        <div class="card">
            <div class="card-header" style="background: #fafbfc;">
                <div style="font-weight: 700; font-size: 15px; color: #0f172a;">
                    <?= $scenarioTitles[$i] ?>
                </div>
                <span class="badge badge-info" style="font-size: 13px;">
                    เฉลี่ย: <?= $sAvg ?> คะแนน
                </span>
            </div>
            <div class="card-body">
                <div style="margin-bottom: 14px; font-size: 13.5px; color: #64748b;">
                    จำนวนการตอบทั้งหมด: <strong><?= $sItem ? number_format($sItem['total_answers']) : 0 ?> ครั้ง</strong>
                </div>

                <div style="font-size: 13px; font-weight: 600; color: #334155; margin-bottom: 8px;">
                    📌 ตัวเลือกที่นักเรียนเลือกมากที่สุด:
                </div>

                <?php if (count($choices) === 0): ?>
                    <p style="font-size: 13px; color: #94a3b8;">ยังไม่มีข้อมูลการตอบ</p>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <?php foreach ($choices as $ch): ?>
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 12px; font-size: 13px;">
                                <div style="color: #0f172a; font-weight: 500; margin-bottom: 4px;">
                                    <?= e($ch['selected_option']) ?>
                                </div>
                                <div style="display: flex; justify-content: space-between; font-size: 12px; color: #64748b;">
                                    <span>เลือก: <strong><?= $ch['pick_count'] ?> คน</strong></span>
                                    <span>คะแนนที่ได้: <strong><?= round($ch['avg_score'], 1) ?></strong></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endfor; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Chart สถานการณ์
    const ctx1 = document.getElementById('scenarioChart').getContext('2d');
    new Chart(ctx1, {
        type: 'bar',
        data: {
            labels: ['สถานการณ์ 1', 'สถานการณ์ 2', 'สถานการณ์ 3', 'สถานการณ์ 4'],
            datasets: [{
                label: 'คะแนนเฉลี่ย',
                data: [
                    <?= $scenarioMap[1]['avg_score'] ?? 0 ?>,
                    <?= $scenarioMap[2]['avg_score'] ?? 0 ?>,
                    <?= $scenarioMap[3]['avg_score'] ?? 0 ?>,
                    <?= $scenarioMap[4]['avg_score'] ?? 0 ?>
                ],
                backgroundColor: ['#10b981', '#3b82f6', '#f59e0b', '#8b5cf6'],
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, title: { display: true, text: 'คะแนน' } }
            }
        }
    });

    // 2. Chart ผ่าน / ไม่ผ่าน
    const ctx2 = document.getElementById('passFailChart').getContext('2d');
    new Chart(ctx2, {
        type: 'doughnut',
        data: {
            labels: ['ผ่านเกณฑ์ (≥50%)', 'ไม่ผ่านเกณฑ์ (<50%)'],
            datasets: [{
                data: [<?= $passedCount ?>, <?= $failedCount ?>],
                backgroundColor: ['#10b981', '#ef4444'],
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

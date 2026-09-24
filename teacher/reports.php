<?php
/**
 * Money Life - Official Learning Reports & CSV Export
 * Printable assessment sheet and CSV download with UTF-8 BOM
 */

require_once __DIR__ . '/auth/check.php';

$pageTitle = 'รายงานผลการเรียนรู้';

$filterClass = trim($_GET['class_level'] ?? '');
$filterRoom = trim($_GET['class_room'] ?? '');
$export = $_GET['export'] ?? '';

$classLevels = $pdo->query("SELECT DISTINCT class_level FROM students WHERE class_level != '' ORDER BY class_level")->fetchAll(PDO::FETCH_COLUMN);
$classRooms = $pdo->query("SELECT DISTINCT class_room FROM students WHERE class_room != '' ORDER BY class_room")->fetchAll(PDO::FETCH_COLUMN);

// ดึงข้อมูลรายงาน
$sql = "
    SELECT 
        s.student_code,
        s.first_name,
        s.last_name,
        s.class_level,
        s.class_room,
        s.student_number,
        COUNT(gs.id) as session_count,
        MAX(gs.total_score) as best_score,
        MAX(gs.max_score) as max_score_limit,
        MAX(gs.percentage) as best_percentage,
        MAX(gs.started_at) as latest_played_at
    FROM students s
    LEFT JOIN game_sessions gs ON s.id = gs.student_id
    WHERE 1=1
";
$params = [];

if ($filterClass !== '') {
    $sql .= " AND s.class_level = ?";
    $params[] = $filterClass;
}
if ($filterRoom !== '') {
    $sql .= " AND s.class_room = ?";
    $params[] = $filterRoom;
}

$sql .= " GROUP BY s.id ORDER BY s.class_level ASC, s.class_room ASC, s.student_number ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// จัดการ Export CSV
if ($export === 'csv') {
    log_audit($pdo, 'EXPORT_REPORT_CSV', 'reports', null, ['class' => $filterClass, 'room' => $filterRoom]);

    $filename = "MoneyLife_Report_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');
    // เขียน UTF-8 BOM เพื่อให้ Excel ภาษาไทยไม่เพี้ยน
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Headers
    fputcsv($output, [
        'ลำดับ',
        'รหัสนักเรียน',
        'ชื่อ',
        'นามสกุล',
        'ระดับชั้น',
        'ห้อง',
        'เลขที่',
        'คะแนนที่ได้',
        'คะแนนเต็ม',
        'เปอร์เซ็นต์ (%)',
        'ผลการประเมิน',
        'จำนวนครั้งที่เล่น',
        'วันที่เล่นล่าสุด'
    ]);

    $i = 1;
    foreach ($rows as $r) {
        $score = $r['best_score'] !== null ? $r['best_score'] : 0;
        $maxLimit = $r['max_score_limit'] ?: 50;
        $pct = $r['best_percentage'] !== null ? $r['best_percentage'] : 0;
        $grade = ($pct >= 80) ? 'ดีเยี่ยม' : (($pct >= 50) ? 'ผ่านเกณฑ์' : 'ไม่ผ่านเกณฑ์');

        fputcsv($output, [
            $i++,
            $r['student_code'] ?: '-',
            $r['first_name'],
            $r['last_name'],
            $r['class_level'],
            $r['class_room'],
            $r['student_number'],
            $score,
            $maxLimit,
            $pct . '%',
            $grade,
            $r['session_count'],
            $r['latest_played_at'] ? date('d/m/Y H:i', strtotime($r['latest_played_at'])) : '-'
        ]);
    }

    fclose($output);
    exit;
}

require_once __DIR__ . '/includes/header.php';
?>

<style>
@media print {
    .sidebar, .top-bar, .filter-bar, .no-print, .btn {
        display: none !important;
    }
    .main-wrapper {
        margin-left: 0 !important;
    }
    .page-content {
        padding: 0 !important;
    }
    .card {
        box-shadow: none !important;
        border: none !important;
    }
    .data-table th, .data-table td {
        border: 1px solid #cbd5e1 !important;
        padding: 8px !important;
        font-size: 12px !important;
    }
    .print-header {
        display: block !important;
        text-align: center;
        margin-bottom: 20px;
    }
}
.print-header {
    display: none;
}
</style>

<!-- Filter & Export Controls -->
<div class="filter-bar no-print">
    <form method="GET" action="reports.php" class="filter-group">
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

        <button type="submit" class="btn btn-accent btn-sm">กรองรายงาน</button>
        <?php if ($filterClass || $filterRoom): ?>
            <a href="reports.php" class="btn btn-outline btn-sm">ล้างตัวกรอง</a>
        <?php endif; ?>
    </form>

    <div style="display: flex; gap: 10px;">
        <button onclick="window.print()" class="btn btn-outline btn-sm">
            🖨️ พิมพ์รายงาน
        </button>
        <a href="reports.php?export=csv&class_level=<?= urlencode($filterClass) ?>&class_room=<?= urlencode($filterRoom) ?>" class="btn btn-primary btn-sm">
            📥 ดาวน์โหลด Excel / CSV
        </a>
    </div>
</div>

<div class="card">
    <div class="print-header">
        <h2 style="font-size: 18px; margin-bottom: 4px;">รายงานผลการประเมินการวางแผนการเงินในชีวิตประจำวัน (Money Life)</h2>
        <p style="font-size: 13px; color: #555;">
            ระดับชั้น: <?= $filterClass ?: 'ทั้งหมด' ?> | ห้อง: <?= $filterRoom ?: 'ทั้งหมด' ?> | วันที่ออกรายงาน: <?= date('d/m/Y H:i') ?>
        </p>
    </div>

    <div class="card-header no-print">
        <div class="card-title">
            <span>📄</span> ตารางสรุปคะแนนผลการประเมิน
        </div>
        <span style="font-size: 13.5px; color: #64748b;">
            จำนวนนักเรียน <?= count($rows) ?> คน
        </span>
    </div>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 50px;">ลำดับ</th>
                    <th>รหัส</th>
                    <th>ชื่อ - นามสกุล</th>
                    <th>ชั้น</th>
                    <th>ห้อง</th>
                    <th>เลขที่</th>
                    <th>คะแนนที่ได้</th>
                    <th>คะแนนเต็ม</th>
                    <th>เปอร์เซ็นต์</th>
                    <th>ผลการประเมิน</th>
                    <th>จำนวนครั้ง</th>
                    <th>วันที่เล่นล่าสุด</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($rows) === 0): ?>
                    <tr>
                        <td colspan="12" style="text-align: center; color: #94a3b8; padding: 30px;">
                            ไม่พบข้อมูลรายงานตามเงื่อนไขที่เลือก
                        </td>
                    </tr>
                <?php else: ?>
                    <?php $no = 1; foreach ($rows as $r): ?>
                        <?php 
                            $pct = $r['best_percentage'] !== null ? $r['best_percentage'] : 0;
                            $score = $r['best_score'] !== null ? $r['best_score'] : '-';
                            $maxLimit = $r['max_score_limit'] ?: 50;
                        ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td><?= e($r['student_code'] ?: '-') ?></td>
                            <td>
                                <strong><?= e($r['first_name'] . ' ' . $r['last_name']) ?></strong>
                            </td>
                            <td><?= e($r['class_level']) ?></td>
                            <td><?= e($r['class_room']) ?></td>
                            <td><strong><?= e($r['student_number']) ?></strong></td>
                            <td><strong><?= $score ?></strong></td>
                            <td><?= $maxLimit ?></td>
                            <td>
                                <strong style="color: <?= $pct >= 70 ? '#10b981' : ($pct >= 50 ? '#3b82f6' : '#ef4444') ?>;">
                                    <?= $pct ?>%
                                </strong>
                            </td>
                            <td>
                                <?php if ($r['session_count'] == 0): ?>
                                    <span class="badge badge-secondary">ยังไม่ประเมิน</span>
                                <?php elseif ($pct >= 80): ?>
                                    <span class="badge badge-success">ดีเยี่ยม</span>
                                <?php elseif ($pct >= 50): ?>
                                    <span class="badge badge-info">ผ่านเกณฑ์</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">ไม่ผ่าน</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $r['session_count'] ?> ครั้ง</td>
                            <td style="font-size: 13px; color: #64748b;">
                                <?= $r['latest_played_at'] ? date('d/m/Y H:i', strtotime($r['latest_played_at'])) : '-' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<?php
/**
 * Money Life - Admin Teacher Management & Approval Portal
 * Approvals workflow, role control, status filters, and PDPA masked data
 */

require_once __DIR__ . '/../auth/check_admin.php';

$pageTitle = 'จัดการและอนุมัติบัญชีครู';
$msg = $_GET['msg'] ?? '';
$error = $_GET['error'] ?? '';
$msgType = $_GET['type'] ?? 'info';

$filterStatus = trim($_GET['status'] ?? '');
$search = trim($_GET['search'] ?? '');

// ปลดล็อค Rate limit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'unlock') {
    if (verify_csrf_token()) {
        $tId = (int)($_POST['teacher_id'] ?? 0);
        if ($tId > 0) {
            $pdo->prepare("UPDATE teachers SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?")->execute([$tId]);
            log_audit($pdo, 'ADMIN_UNLOCK_TEACHER', 'teachers', $tId);
            $msg = 'ปลดล็อคบัญชีเรียบร้อยแล้ว';
            $msgType = 'success';
        }
    }
}

// สร้าง Dynamic Query
$sql = "SELECT * FROM teachers WHERE 1=1 ";
$params = [];

if ($filterStatus !== '') {
    $sql .= " AND status = ? ";
    $params[] = $filterStatus;
}

if ($search !== '') {
    $sql .= " AND (first_name LIKE ? OR last_name LIKE ? OR national_id LIKE ? OR phone LIKE ?) ";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY FIELD(status, 'pending', 'approved', 'suspended', 'rejected'), id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$teachers = $stmt->fetchAll();

// นับจำนวนในแต่ละสถานะ
$pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM teachers WHERE status = 'pending'")->fetchColumn();
$approvedCount = (int)$pdo->query("SELECT COUNT(*) FROM teachers WHERE status = 'approved'")->fetchColumn();
$suspendedCount = (int)$pdo->query("SELECT COUNT(*) FROM teachers WHERE status = 'suspended'")->fetchColumn();

// ดึง Audit Log ล่าสุด 12 รายการ
$logs = $pdo->query("
    SELECT a.*, t.first_name, t.last_name
    FROM audit_logs a
    LEFT JOIN teachers t ON a.teacher_id = t.id
    ORDER BY a.id DESC
    LIMIT 12
")->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<?php if ($msg): ?>
    <div class="alert alert-<?= $msgType === 'warning' ? 'warning' : 'success' ?>">
        <span><?= $msgType === 'warning' ? '⚠️' : '✓' ?></span>
        <div><?= e($msg) ?></div>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <span>⚠️</span>
        <div><?= e($error) ?></div>
    </div>
<?php endif; ?>

<?php if ($pendingCount > 0 && $filterStatus !== 'pending'): ?>
    <div class="alert alert-warning" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <strong>⏳ มีบัญชีครูรอการตรวจสอบและอนุมัติ <?= $pendingCount ?> บัญชี</strong>
        </div>
        <a href="teachers.php?status=pending" class="btn btn-outline btn-sm" style="background: white;">
            ดูรายการรออนุมัติ →
        </a>
    </div>
<?php endif; ?>

<!-- Filter & Search Bar -->
<div class="filter-bar">
    <form method="GET" action="teachers.php" class="filter-group">
        <input type="text" name="search" class="form-control" placeholder="🔍 ค้นหาชื่อ, เลขบัตร, เบอร์..." 
               value="<?= e($search) ?>" style="width: 240px;">

        <select name="status" class="form-control" style="width: 150px;">
            <option value="">ทุกสถานะ (<?= count($teachers) ?>)</option>
            <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>⏳ รออนุมัติ (<?= $pendingCount ?>)</option>
            <option value="approved" <?= $filterStatus === 'approved' ? 'selected' : '' ?>>✓ อนุมัติแล้ว (<?= $approvedCount ?>)</option>
            <option value="suspended" <?= $filterStatus === 'suspended' ? 'selected' : '' ?>>🚫 ระงับการใช้งาน (<?= $suspendedCount ?>)</option>
            <option value="rejected" <?= $filterStatus === 'rejected' ? 'selected' : '' ?>>✕ ไม่อนุมัติ</option>
        </select>

        <button type="submit" class="btn btn-accent btn-sm">กรองข้อมูล</button>
        <?php if ($filterStatus || $search): ?>
            <a href="teachers.php" class="btn btn-outline btn-sm">ล้างตัวกรอง</a>
        <?php endif; ?>
    </form>
</div>

<!-- Teachers List Table -->
<div class="card">
    <div class="card-header">
        <div class="card-title">
            <span>👨‍🏫</span> บัญชีครูและสิทธิ์ผู้ใช้งาน (<?= count($teachers) ?> รายการ)
        </div>
        <span style="font-size: 13px; color: #64748b;">
            🔒 ซ่อนเลขบัตรประชาชนตาม PDPA
        </span>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ชื่อ - นามสกุล</th>
                    <th>ตำแหน่ง</th>
                    <th>เลขประจำตัวประชาชน (13 หลัก)</th>
                    <th>เบอร์โทรศัพท์</th>
                    <th>สถานะบัญชี</th>
                    <th>เบอร์โทร</th>
                    <th>บทบาท (Role)</th>
                    <th>วันที่สมัคร</th>
                    <th style="text-align: right;">การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($teachers) === 0): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; color: #94a3b8; padding: 30px;">
                            ไม่พบบัญชีครูที่ตรงกับเงื่อนไข
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($teachers as $t): ?>
                        <?php 
                            $isLocked = !empty($t['locked_until']) && strtotime($t['locked_until']) > time();
                            $isMe = ((int)$t['id'] === (int)$_SESSION['teacher_id']);
                        ?>
                        <tr style="<?= $t['status'] === 'pending' ? 'background-color: #fffbeb;' : '' ?>">
                            <td>
                                <strong><?= e($t['first_name'] . ' ' . $t['last_name']) ?></strong>
                                <?php if ($isMe): ?>
                                    <span class="badge badge-secondary" style="font-size: 11px;">(ตัวคุณ)</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($t['position']) ?></td>
                            <td>
                                <code style="font-size: 13px;"><?= mask_national_id($t['national_id']) ?></code>
                            </td>
                            <td>
                                <span style="font-size: 13px; color: #475569;"><?= mask_phone_number($t['phone']) ?></span>
                            </td>
                            <td>
                                <?php if ($isLocked): ?>
                                    <span class="badge badge-danger">🔒 ถูกล็อค</span>
                                <?php elseif ($t['status'] === 'approved'): ?>
                                    <span class="badge badge-success">✓ Approved</span>
                                <?php elseif ($t['status'] === 'pending'): ?>
                                    <span class="badge badge-warning">⏳ Pending</span>
                                <?php elseif ($t['status'] === 'suspended'): ?>
                                    <span class="badge badge-danger">🚫 Suspended</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">✕ Rejected</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($t['phone_verified']): ?>
                                    <span class="badge badge-success" title="ยืนยันด้วย OTP แล้ว">✓ Verified</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Unverified</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($t['role'] === 'admin'): ?>
                                    <span class="badge badge-warning">👑 Admin</span>
                                <?php else: ?>
                                    <span class="badge badge-info">🎓 Teacher</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 12.5px; color: #64748b;">
                                <?= date('d/m/Y H:i', strtotime($t['created_at'])) ?>
                            </td>
                            <td style="text-align: right; white-space: nowrap;">
                                <?php if ($isLocked): ?>
                                    <form method="POST" action="teachers.php" style="display: inline-block;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="unlock">
                                        <input type="hidden" name="teacher_id" value="<?= $t['id'] ?>">
                                        <button type="submit" class="btn btn-outline btn-sm" title="ปลดล็อค Brute force">🔓 ปลดล็อค</button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($t['status'] === 'pending'): ?>
                                    <!-- ปุ่มอนุมัติ (teacher_approve.php) -->
                                    <form method="POST" action="teacher_approve.php" style="display: inline-block;" onsubmit="return confirm('ยืนยันอนุมัติบัญชีครูท่านนี้?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="teacher_id" value="<?= $t['id'] ?>">
                                        <button type="submit" class="btn btn-primary btn-sm" title="อนุมัติการใช้งาน">
                                            ✓ อนุมัติ
                                        </button>
                                    </form>

                                    <!-- ปุ่มปฏิเสธ (teacher_reject.php) -->
                                    <form method="POST" action="teacher_reject.php" style="display: inline-block;" onsubmit="return confirm('ยืนยันปฏิเสธคำขอสมัครนี้?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="teacher_id" value="<?= $t['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" title="ไม่อนุมัติ">
                                            ✕ ปฏิเสธ
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <!-- สลับระงับ / ปลดระงับ (teacher_suspend.php) -->
                                    <?php if (!$isMe): ?>
                                        <form method="POST" action="teacher_suspend.php" style="display: inline-block;" onsubmit="return confirm('ยืนยันเปลี่ยนสถานะระงับการใช้งานบัญชีนี้?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="teacher_id" value="<?= $t['id'] ?>">
                                            <input type="hidden" name="target_status" value="<?= $t['status'] === 'suspended' ? 'approved' : 'suspended' ?>">
                                            <button type="submit" class="btn <?= $t['status'] === 'suspended' ? 'btn-primary' : 'btn-danger' ?> btn-sm">
                                                <?= $t['status'] === 'suspended' ? 'เปิดใช้งาน' : 'ระงับบัญชี' ?>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <!-- รีเซ็ตรหัสผ่าน (teacher_reset_password.php) -->
                                    <a href="teacher_reset_password.php?id=<?= $t['id'] ?>" class="btn btn-outline btn-sm">
                                        🔑 รีเซ็ตรหัส
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Audit Log Section (ส่วนที่ 19) -->
<div class="card" style="margin-top: 30px;">
    <div class="card-header">
        <div class="card-title">
            <span>📜</span> บันทึกกิจกรรมสำคัญของระบบ (Audit Logs)
        </div>
        <span style="font-size: 13px; color: #64748b;">12 รายการล่าสุด</span>
    </div>
    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>เวลา</th>
                    <th>ผู้ปฏิบัติการ</th>
                    <th>Action</th>
                    <th>เป้าหมาย</th>
                    <th>IP Address</th>
                    <th>รายละเอียด</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($logs) === 0): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: #94a3b8; padding: 20px;">ยังไม่มีบันทึก Audit Log</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $l): ?>
                        <tr>
                            <td style="font-size: 12.5px; color: #64748b; white-space: nowrap;">
                                <?= date('d/m/Y H:i:s', strtotime($l['created_at'])) ?>
                            </td>
                            <td>
                                <strong><?= e($l['first_name'] ? $l['first_name'] . ' ' . $l['last_name'] : 'ระบบ / ผู้สมัคร') ?></strong>
                            </td>
                            <td>
                                <span class="badge badge-info" style="font-family: monospace; font-size: 12px;">
                                    <?= e($l['action']) ?>
                                </span>
                            </td>
                            <td><?= e($l['target_type']) ?> <?= $l['target_id'] ? '#' . e($l['target_id']) : '' ?></td>
                            <td><code style="font-size: 12px;"><?= e($l['ip_address']) ?></code></td>
                            <td style="font-size: 12.5px; color: #64748b; max-width: 280px; word-break: break-all;">
                                <?= e($l['details'] ?: '-') ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

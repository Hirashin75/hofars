<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_role('admin');

/* ---- Assign / reassign a technician (POST, then redirect back to the same filtered page) ---- */
if (is_post()) {
    csrf_check();
    $reportId = (int) ($_POST['report_id'] ?? 0);
    $techId = (int) ($_POST['technician_id'] ?? 0);
    $back = 'admin_hofars.php' . (!empty($_POST['back']) && preg_match('/^\?[\w=&%+.-]*$/', (string) $_POST['back']) ? $_POST['back'] : '');

    $report = q('SELECT id, status FROM reports WHERE id = ?', [$reportId])->fetch();
    $tech = q("SELECT id, name FROM users WHERE id = ? AND role = 'technician'", [$techId])->fetch();

    if (!$report || !$tech) {
        flash('error', 'Please choose a technician.');
    } elseif (!in_array($report['status'], ['Pending', 'Assigned'], true)) {
        flash('error', 'This report can no longer be (re)assigned.');
    } else {
        $stamp = now();
        $pdo = db();
        $pdo->beginTransaction();
        q("UPDATE reports SET technician_id = ?, status = 'Assigned', updated_at = ? WHERE id = ?", [$tech['id'], $stamp, $reportId]);
        add_history($reportId, 'Assigned', 'Assigned to ' . $tech['name'] . '.', (int) $user['id']);
        $pdo->commit();
        flash('success', report_code($reportId) . ' assigned to ' . $tech['name'] . '.');
    }
    redirect($back);
}

/* ---- Stats ---- */
$byStatus = array_fill_keys(REPORT_STATUSES, 0);
foreach (q('SELECT status, COUNT(*) AS n FROM reports GROUP BY status')->fetchAll() as $row) {
    $byStatus[$row['status']] = (int) $row['n'];
}
$total = array_sum($byStatus);

$byFacility = q(
    'SELECT f.name, COUNT(r.id) AS n FROM facilities f LEFT JOIN reports r ON r.facility_id = f.id
      GROUP BY f.id, f.name ORDER BY n DESC, f.name'
)->fetchAll();
$maxFacility = max(1, (int) ($byFacility[0]['n'] ?? 1));

$hours = [];
foreach (q("SELECT created_at, resolved_at FROM reports WHERE status = 'Resolved' AND resolved_at IS NOT NULL")->fetchAll() as $row) {
    $hours[] = (strtotime($row['resolved_at']) - strtotime($row['created_at'])) / 3600;
}
$avgResolution = $hours ? array_sum($hours) / count($hours) : null;

/* ---- Filters + pagination ---- */
$search = trim((string) ($_GET['q'] ?? ''));
$status = (string) ($_GET['status'] ?? '');
$facilityId = (int) ($_GET['facility'] ?? 0);
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 10;

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(r.student_name LIKE ? OR r.matric_number LIKE ? OR r.room_number LIKE ? OR r.description LIKE ?)';
    $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $search) . '%';
    array_push($params, $like, $like, $like, $like);
}
if (in_array($status, REPORT_STATUSES, true)) {
    $where[] = 'r.status = ?';
    $params[] = $status;
}
if ($facilityId > 0) {
    $where[] = 'r.facility_id = ?';
    $params[] = $facilityId;
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$matches = (int) q("SELECT COUNT(*) FROM reports r $whereSql", $params)->fetchColumn();
$pages = max(1, (int) ceil($matches / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;

$rows = q(
    "SELECT r.id, r.student_name, r.matric_number, r.room_number, r.block_number, r.status, r.created_at, r.technician_id,
            f.name AS facility, t.name AS technician_name
       FROM reports r
       JOIN facilities f ON f.id = r.facility_id
       LEFT JOIN users t ON t.id = r.technician_id
       $whereSql
      ORDER BY (r.status = 'Under Review') DESC, r.created_at DESC, r.id DESC
      LIMIT $perPage OFFSET $offset",
    $params
)->fetchAll();

$technicians = q("SELECT id, name FROM users WHERE role = 'technician' ORDER BY name")->fetchAll();
$facilities = q('SELECT id, name FROM facilities ORDER BY id')->fetchAll();

$query = array_filter(['q' => $search, 'status' => $status, 'facility' => $facilityId ?: null], fn ($v) => $v !== '' && $v !== null);
$pageLink = fn (int $p) => 'admin_hofars.php?' . http_build_query($query + ['page' => $p]);
$backParam = $query || $page > 1 ? '?' . http_build_query($query + ($page > 1 ? ['page' => $page] : [])) : '';

page_header('Dashboard', 'dashboard');
?>
<h1 class="section-title">Admin dashboard</h1>
<p class="section-subtitle">Assign technicians, review completed work and keep an eye on every report.</p>

<div class="stats-container">
    <div class="stat-card"><h3>Total reports</h3><p class="stat-number"><?= $total ?></p></div>
    <a class="stat-card stat-link" href="admin_hofars.php?status=Pending"><h3>Pending</h3><p class="stat-number"><?= $byStatus['Pending'] ?></p></a>
    <a class="stat-card stat-link" href="admin_hofars.php?status=Under+Review"><h3>Needs review</h3><p class="stat-number"><?= $byStatus['Under Review'] ?></p></a>
    <a class="stat-card stat-link" href="admin_hofars.php?status=Resolved"><h3>Resolved</h3><p class="stat-number"><?= $byStatus['Resolved'] ?></p></a>
    <div class="stat-card"><h3>Avg. fix time</h3><p class="stat-number small"><?= $avgResolution === null ? '-' : ($avgResolution < 48 ? round($avgResolution) . ' hrs' : round($avgResolution / 24, 1) . ' days') ?></p></div>
</div>

<div class="chart-grid">
    <section class="card">
        <h2 class="card-title">Reports by facility</h2>
        <?php foreach ($byFacility as $row): ?>
            <div class="bar-row">
                <span class="bar-label"><?= e($row['name']) ?></span>
                <span class="bar-track"><span class="bar-fill" style="width: <?= round(((int) $row['n'] / $maxFacility) * 100) ?>%"></span></span>
                <span class="bar-value"><?= (int) $row['n'] ?></span>
            </div>
        <?php endforeach; ?>
    </section>
    <section class="card">
        <h2 class="card-title">Reports by status</h2>
        <?php foreach ($byStatus as $label => $n): ?>
            <div class="bar-row">
                <span class="bar-label"><?= e($label) ?></span>
                <span class="bar-track"><span class="bar-fill <?= e(status_class($label)) ?>" style="width: <?= $total ? round($n / $total * 100) : 0 ?>%"></span></span>
                <span class="bar-value"><?= $n ?></span>
            </div>
        <?php endforeach; ?>
    </section>
</div>

<h2 class="table-title">All reports</h2>
<form class="filter-bar" method="get">
    <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search name, matric, room, description">
    <select name="status" aria-label="Status">
        <option value="">All statuses</option>
        <?php foreach (REPORT_STATUSES as $s): ?><option<?= $status === $s ? ' selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
    </select>
    <select name="facility" aria-label="Facility">
        <option value="">All facilities</option>
        <?php foreach ($facilities as $f): ?><option value="<?= (int) $f['id'] ?>"<?= $facilityId === (int) $f['id'] ? ' selected' : '' ?>><?= e($f['name']) ?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-secondary btn-inline">Filter</button>
    <?php if ($query): ?><a class="link" href="admin_hofars.php">Clear</a><?php endif; ?>
</form>

<div class="table-container">
    <table class="report-table">
        <thead>
            <tr><th>Ref</th><th>Date</th><th>Student</th><th>Location</th><th>Facility</th><th>Status</th><th>Technician</th><th></th></tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="8" class="no-data">No reports match.</td></tr>
        <?php else: foreach ($rows as $r): $canAssign = in_array($r['status'], ['Pending', 'Assigned'], true); ?>
            <tr>
                <td><strong><?= e(report_code((int) $r['id'])) ?></strong></td>
                <td><?= e(fmt_date($r['created_at'])) ?></td>
                <td><?= e($r['student_name']) ?><br><small class="muted"><?= e($r['matric_number']) ?></small></td>
                <td><?= e($r['block_number'] . ' / ' . $r['room_number']) ?></td>
                <td><?= e($r['facility']) ?></td>
                <td><?= status_badge($r['status']) ?></td>
                <td>
                    <?php if ($canAssign): ?>
                        <form method="post" class="assign-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="report_id" value="<?= (int) $r['id'] ?>">
                            <input type="hidden" name="back" value="<?= e($backParam) ?>">
                            <select name="technician_id" aria-label="Technician" required>
                                <option value="">Select…</option>
                                <?php foreach ($technicians as $t): ?>
                                    <option value="<?= (int) $t['id'] ?>"<?= (int) $r['technician_id'] === (int) $t['id'] ? ' selected' : '' ?>><?= e($t['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn btn-small"><?= $r['status'] === 'Pending' ? 'Assign' : 'Reassign' ?></button>
                        </form>
                    <?php else: ?>
                        <?= e($r['technician_name'] ?? '-') ?>
                    <?php endif; ?>
                </td>
                <td><a class="link" href="report.php?id=<?= (int) $r['id'] ?>"><?= $r['status'] === 'Under Review' ? 'Review' : 'View' ?></a></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>

<?php if ($pages > 1): ?>
    <nav class="pagination" aria-label="Pages">
        <?php if ($page > 1): ?><a href="<?= e($pageLink($page - 1)) ?>">‹ Prev</a><?php endif; ?>
        <span>Page <?= $page ?> of <?= $pages ?> · <?= $matches ?> reports</span>
        <?php if ($page < $pages): ?><a href="<?= e($pageLink($page + 1)) ?>">Next ›</a><?php endif; ?>
    </nav>
<?php endif; ?>
<?php page_footer(); ?>

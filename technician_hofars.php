<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';

$user = require_role('technician');

$counts = array_fill_keys(['Assigned', 'In Progress', 'Under Review', 'Resolved'], 0);
foreach (q('SELECT status, COUNT(*) AS n FROM reports WHERE technician_id = ? GROUP BY status', [$user['id']])->fetchAll() as $row) {
    if (isset($counts[$row['status']])) {
        $counts[$row['status']] = (int) $row['n'];
    }
}

$filter = (string) ($_GET['status'] ?? '');
$sql = 'SELECT r.id, r.student_name, r.room_number, r.block_number, r.description, r.status, r.created_at, f.name AS facility
          FROM reports r JOIN facilities f ON f.id = r.facility_id
         WHERE r.technician_id = ?';
$params = [$user['id']];
if (in_array($filter, REPORT_STATUSES, true)) {
    $sql .= ' AND r.status = ?';
    $params[] = $filter;
}
// Jobs that need action first, finished ones last.
$sql .= " ORDER BY CASE r.status WHEN 'Assigned' THEN 0 WHEN 'In Progress' THEN 1 WHEN 'Under Review' THEN 2 ELSE 3 END, r.created_at DESC";
$jobs = q($sql, $params)->fetchAll();

page_header('My Jobs', 'jobs');
?>
<h1 class="section-title">My jobs</h1>
<p class="section-subtitle">Jobs assigned to you. Open one to start work and upload proof of the repair.</p>

<div class="stats-container">
    <?php foreach ($counts as $label => $n): ?>
        <a class="stat-card stat-link" href="technician_hofars.php?status=<?= urlencode($label) ?>">
            <h3><?= e($label) ?></h3>
            <p class="stat-number"><?= $n ?></p>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($filter): ?>
    <p class="filter-note">Showing <strong><?= e($filter) ?></strong> jobs · <a class="link" href="technician_hofars.php">show all</a></p>
<?php endif; ?>

<div class="table-container">
    <table class="report-table">
        <thead>
            <tr><th>Ref</th><th>Reported</th><th>Student</th><th>Location</th><th>Facility</th><th>Problem</th><th>Status</th><th></th></tr>
        </thead>
        <tbody>
        <?php if (!$jobs): ?>
            <tr><td colspan="8" class="no-data">No jobs here yet.</td></tr>
        <?php else: foreach ($jobs as $j): ?>
            <tr>
                <td><strong><?= e(report_code((int) $j['id'])) ?></strong></td>
                <td><?= e(fmt_date($j['created_at'])) ?></td>
                <td><?= e($j['student_name']) ?></td>
                <td><?= e($j['block_number'] . ' / ' . $j['room_number']) ?></td>
                <td><?= e($j['facility']) ?></td>
                <td class="cell-wrap"><?= e(mb_strimwidth($j['description'], 0, 80, '…')) ?></td>
                <td><?= status_badge($j['status']) ?></td>
                <td><a class="btn btn-small" href="report.php?id=<?= (int) $j['id'] ?>"><?= in_array($j['status'], ['Assigned', 'In Progress'], true) ? 'Open job' : 'View' ?></a></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
</div>
<?php page_footer(); ?>

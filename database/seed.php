<?php
declare(strict_types=1);

/**
 * Loads FAKE demo data (accounts + sample reports) so the system can be explored right away.
 *
 *   php database/seed.php          # refuses to run if users already exist
 *   php database/seed.php --force  # adds the demo data anyway
 *
 * Demo password for every account: Demo@1234
 * CLI only, so it can never be triggered from a browser.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../includes/bootstrap.php';

$force = in_array('--force', $argv ?? [], true);
if (!$force && (int) q('SELECT COUNT(*) FROM users')->fetchColumn() > 0) {
    fwrite(STDERR, "Users already exist. Re-run with --force to add demo data anyway.\n");
    exit(1);
}

$password = password_hash('Demo@1234', PASSWORD_DEFAULT);
$daysAgo = fn (int $d, int $h = 9) => date('Y-m-d H:i:s', strtotime("-$d days $h hours"));

$people = [
    // email, name, role, matric
    ['admin@hofars.test',   'Warden Admin',     'admin',      null],
    ['tech1@hofars.test',   'Razak Ismail',     'technician', null],
    ['tech2@hofars.test',   'Ravi Kumar',       'technician', null],
    ['aisyah@hofars.test',  'Aisyah Rahman',    'student',    '08DDT24F1001'],
    ['daniel@hofars.test',  'Daniel Lim',       'student',    '08DDT24F1002'],
    ['priya@hofars.test',   'Priya Nair',       'student',    '08DDT24F1003'],
    ['hafiz@hofars.test',   'Hafiz Ahmad',      'student',    '08DDT24F1004'],
];

$ids = [];
foreach ($people as [$email, $name, $role, $matric]) {
    q(
        'INSERT INTO users (name, email, password, role, matric_number, created_at) VALUES (?, ?, ?, ?, ?, ?)',
        [$name, $email, $password, $role, $matric, $daysAgo(40)]
    );
    $ids[$email] = (int) db()->lastInsertId();
}

$facility = fn (string $name) => (int) q('SELECT id FROM facilities WHERE name = ?', [$name])->fetchColumn();

// student, facility, room, block, description, status, tech, created (days ago)
$reports = [
    ['aisyah', 'Kunci Bilik',          'TC215', 'T', 'The room lock is jammed and the key will not turn properly.',          'Resolved',     'tech1', 21],
    ['daniel', 'Kipas Siling/Dinding', 'TB108', 'B', 'Ceiling fan makes a loud grinding noise and wobbles on speed 3.',        'Resolved',     'tech2', 17],
    ['priya',  'Lampu',                'TA304', 'A', 'Study lamp socket has no power. Both tube lights flicker at night.',     'Under Review', 'tech1', 6],
    ['hafiz',  'Tingkap',              'TC122', 'C', 'Window latch is broken so the window cannot be closed during rain.',     'In Progress',  'tech2', 5],
    ['aisyah', 'Pintu',                'TC215', 'T', 'Bottom of the wardrobe door has come off its hinge.',                    'Assigned',     'tech1', 3],
    ['daniel', 'Rak/Meja/Almari',      'TB108', 'B', 'Study table drawer rail is bent and the drawer keeps falling out.',      'Pending',      null,    2],
    ['priya',  'Langsir/Rel Langsir',  'TA304', 'A', 'Curtain rail has fallen on one side after the screws came loose.',       'Pending',      null,    1],
    ['hafiz',  'Tombol Pintu',         'TC122', 'C', 'Door knob is loose and rattles. Sometimes the door does not latch.',     'Pending',      null,    0],
    ['aisyah', 'Lain-lain',            'TC215', 'T', 'Water drips from the bathroom ceiling after someone upstairs showers.',  'Cancelled',    null,    9],
];

foreach ($reports as [$who, $fac, $room, $block, $desc, $status, $tech, $age]) {
    $student = $ids["$who@hofars.test"];
    $info = q('SELECT name, matric_number FROM users WHERE id = ?', [$student])->fetch();
    $techId = $tech ? $ids["$tech@hofars.test"] : null;
    $created = $daysAgo($age);
    $resolved = $status === 'Resolved' ? date('Y-m-d H:i:s', strtotime($created . ' +2 days 3 hours')) : null;

    q(
        'INSERT INTO reports (user_id, student_name, matric_number, room_number, block_number, facility_id,
                              description, status, technician_id, created_at, updated_at, resolved_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$student, $info['name'], $info['matric_number'], $room, $block, $facility($fac), $desc,
         $status, $techId, $created, $resolved ?? $created, $resolved]
    );
    $rid = (int) db()->lastInsertId();
    $admin = $ids['admin@hofars.test'];

    add_history($rid, 'Pending', 'Report submitted by student.', $student);
    if ($status === 'Cancelled') {
        add_history($rid, 'Cancelled', 'Cancelled by the student.', $student);
        continue;
    }
    if (!$techId) {
        continue;
    }
    add_history($rid, 'Assigned', 'Assigned to ' . q('SELECT name FROM users WHERE id = ?', [$techId])->fetchColumn() . '.', $admin);
    if ($status === 'Assigned') {
        continue;
    }
    add_history($rid, 'In Progress', 'Technician started the job.', $techId);
    if ($status === 'In Progress') {
        q(
            'INSERT INTO report_updates (report_id, technician_id, progress_description, is_final, review_status, created_at)
             VALUES (?, ?, ?, 0, \'None\', ?)',
            [$rid, $techId, 'Inspected the window. Replacement latch ordered, will install tomorrow.', date('Y-m-d H:i:s', strtotime($created . ' +1 day'))]
        );
        continue;
    }
    $review = $status === 'Resolved' ? 'Approved' : 'Pending';
    q(
        'INSERT INTO report_updates (report_id, technician_id, progress_description, is_final, review_status, reviewed_by, reviewed_at, created_at)
         VALUES (?, ?, ?, 1, ?, ?, ?, ?)',
        [$rid, $techId, 'Repair completed and tested. Everything is working normally.', $review,
         $status === 'Resolved' ? $admin : null, $resolved, date('Y-m-d H:i:s', strtotime($created . ' +1 day 6 hours'))]
    );
    add_history($rid, 'Under Review', 'Technician submitted the completed work for review.', $techId);
    if ($status === 'Resolved') {
        add_history($rid, 'Resolved', 'Work approved by admin.', $admin);
    }
}

echo "Demo data loaded.\n";
echo "Log in with any of these (password: Demo@1234):\n";
foreach ($people as [$email, $name, $role]) {
    printf("  %-11s %s\n", $role, $email);
}

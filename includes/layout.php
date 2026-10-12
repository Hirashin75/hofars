<?php
declare(strict_types=1);

/** Shared page chrome: <head>, navbar, flash messages, footer. */

function nav_items(string $role): array
{
    return match ($role) {
        'admin' => [
            ['dashboard', 'Dashboard', 'admin_hofars.php'],
            ['users', 'Users', 'admin_users.php'],
        ],
        'technician' => [
            ['jobs', 'My Jobs', 'technician_hofars.php'],
        ],
        default => [
            ['new', 'New Report', 'student_hofars.php'],
            ['mine', 'My Reports', 'student_hofars.php?view=mine'],
        ],
    };
}

function page_header(string $title, string $active = ''): void
{
    $user = current_user();
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> | HOFARS</title>
    <script>
        // Apply the saved theme before first paint to avoid a flash.
        try { if (localStorage.getItem('theme') === 'dark') document.documentElement.dataset.theme = 'dark'; } catch (e) {}
    </script>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<?php if ($user): ?>
    <nav class="navbar">
        <div class="nav-left">
            <a class="nav-logo" href="<?= e(home_for($user['role'])) ?>">HOFARS <span><?= e(ucfirst($user['role'])) ?></span></a>
        </div>
        <div class="nav-center">
            <?php foreach (nav_items($user['role']) as [$key, $label, $href]): ?>
                <a href="<?= e($href) ?>" class="nav-link<?= $active === $key ? ' active' : '' ?>"><?= e($label) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="nav-right">
            <button type="button" class="theme-toggle" id="themeToggle" aria-label="Toggle dark mode"><span class="theme-icon">🌙</span></button>
            <div class="user-menu">
                <button type="button" class="user-icon" id="userMenuBtn" aria-label="Account menu">
                    <?php if ($src = avatar_url($user)): ?>
                        <img src="<?= e($src) ?>" alt="">
                    <?php else: ?>
                        <span class="avatar-initial"><?= e(initial($user['name'])) ?></span>
                    <?php endif; ?>
                </button>
                <div class="dropdown-menu" id="userDropdown">
                    <div class="dropdown-who"><strong><?= e($user['name']) ?></strong><small><?= e($user['email']) ?></small></div>
                    <a href="profile.php">Profile</a>
                    <form method="post" action="logout.php">
                        <?= csrf_field() ?>
                        <button type="submit" class="dropdown-button">Logout</button>
                    </form>
                </div>
            </div>
        </div>
    </nav>
<?php endif; ?>
    <main class="main-content">
<?php foreach (take_flashes() as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>" role="alert"><?= e($f['message']) ?></div>
<?php endforeach;
}

function page_footer(): void
{
    ?>
    </main>
    <footer class="footer">
        <p>© <?= date('Y') ?> HOFARS · Hostel Facility Damage Report System</p>
    </footer>
    <script src="assets/app.js"></script>
</body>
</html>
<?php
}

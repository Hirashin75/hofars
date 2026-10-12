<?php
// Assume session is started
$currentUser = $_SESSION['current_user'] ?? null;
?>
<nav class="navbar">
    <!-- Copy navbar HTML from original, but make dynamic -->
    <div class="nav-left">
        <h2 class="nav-logo">HOFARS <?php echo ucfirst($currentUser['role'] ?? ''); ?></h2>
    </div>
    <!-- Add PHP for user menu, theme toggle, etc. -->
    <div class="nav-right">
        <button class="theme-toggle" id="themeToggle">
            <span class="theme-icon">🌙</span>
        </button>
        <div class="user-menu">
            <button class="user-icon" id="userMenuBtn">
                <img src="<?php echo $currentUser['profile_picture'] ?? 'data:image/svg+xml,...'; ?>" alt="User" id="userProfileImg">
            </button>
            <div class="dropdown-menu" id="userDropdown">
                <a href="<?php echo $currentUser['role']; ?>_profile.php">Profile</a>
                <a href="logout.php">Logout</a>
            </div>
        </div>
    </div>
</nav>
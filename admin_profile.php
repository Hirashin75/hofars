<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HOFARS - Admin Profile</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="profile-container">
        <div class="profile-card">
            <button class="btn btn-back" onclick="window.location.href='admin_hofars.php'" data-testid="button-back">← Back</button>
            <h2 class="profile-title">Profile</h2>
            <div class="profile-picture-section">
                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23666'%3E%3Cpath d='M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z'/%3E%3C/svg%3E" alt="Profile Picture" id="profilePicture" class="profile-picture" data-testid="img-profile-picture">
                <input type="file" id="profilePictureUpload" accept="image/*" style="display: none;">
                <button class="btn btn-secondary" id="changePictureBtn" data-testid="button-change-picture">Change Picture</button>
            </div>
            <div class="profile-info">
                <div class="info-group">
                    <label>Username</label>
                    <input type="text" id="profileUsername" placeholder="Enter username" data-testid="input-username">
                </div>
                <div class="info-group">
                    <label>Name</label>
                    <input type="text" id="profileName" readonly data-testid="input-name">
                </div>
                <div class="info-group">
                    <label>Email</label>
                    <input type="email" id="profileEmail" readonly data-testid="input-email">
                </div>
                <button class="btn btn-primary" id="saveProfileBtn" data-testid="button-save-profile">Save Profile</button>
            </div>
        </div>
    </div>

    <footer class="footer">
        <p>© 2025 HOFARS System | Designed with ❤️ |</p>
    </footer>

    <script src="script.js"></script>
</body>
</html>

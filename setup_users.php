<?php
require 'db.php';  // Your database connection

// Sample users
$users = [
    ['name' => 'Admin User', 'email' => 'admin@hofars.com', 'password' => 'admin123', 'role' => 'admin'],
    ['name' => 'Technician User', 'email' => 'tech@hofars.com', 'password' => 'technician123', 'role' => 'technician'],
];

foreach ($users as $user) {
    $hashedPassword = password_hash($user['password'], PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user['name'], $user['email'], $hashedPassword, $user['role']]);
    echo "Inserted: " . $user['email'] . "<br>";
}

echo "Setup complete!";
?>
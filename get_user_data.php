<?php
session_start();
require 'db.php';
header('Content-Type: application/json');
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    echo json_encode($stmt->fetch());
} else {
    echo json_encode(['error' => 'Not logged in']);
}
?>
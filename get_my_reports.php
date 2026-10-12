<?php
session_start();
require 'db.php';
header('Content-Type: application/json');
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM reports WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$_SESSION['user_id']]);
    echo json_encode($stmt->fetchAll());
}
?>
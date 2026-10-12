<?php
session_start();
require 'db.php';
header('Content-Type: application/json');

if ($_SESSION['current_user']['role'] == 'admin') {

    $total = $pdo->query("SELECT COUNT(*) FROM reports")->fetchColumn();
    $pending = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'Pending'")->fetchColumn();
    $resolved = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'Resolved'")->fetchColumn() 
              + $pdo->query("SELECT COUNT(*) FROM technician_reports WHERE status = 'Approved'")->fetchColumn();

    $reports = $pdo->query("SELECT * FROM reports ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    $technicians = $pdo->query("SELECT id, name FROM users WHERE role='technician'")->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'total' => $total,
        'pending' => $pending,
        'resolved' => $resolved,
        'reports' => $reports,
        'technicians' => $technicians
    ]);
}

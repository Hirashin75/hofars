<?php
session_start();
require 'db.php';
require 'session_check.php';

if ($_SESSION['current_user']['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$reportId = $data['reportId'] ?? null;
$technicianId = $data['technicianId'] ?? null;

if (!$reportId || !$technicianId) {
    echo json_encode(['success' => false, 'message' => 'Please select a technician']);
    exit;
}

// Update report
$stmt = $pdo->prepare("UPDATE reports SET technician_id = ?, status = 'Assigned' WHERE id = ?");
if ($stmt->execute([$technicianId, $reportId])) {
    echo json_encode(['success' => true, 'message' => 'Report assigned successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to assign report']);
}

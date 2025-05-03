<?php
include '../config/db_connection.php';
session_start();

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';
$requestId = $data['requestId'] ?? 0;

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrator') {
    echo json_encode(['message' => 'Unauthorized']);
    exit();
}

if ($action === 'approve') {
    // Approve request: Update status and add item to items table
    $stmt = $conn->prepare("SELECT * FROM requests WHERE id = ?");
    $stmt->bind_param("i", $requestId);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($request) {
        $stmt = $conn->prepare("INSERT INTO items (item_name, description, item_category, item_location, quantity, unit, status) VALUES (?, ?, ?, ?, ?, ?, 'Available')");
        $stmt->bind_param("ssssii", $request['item_name'], $request['description'], $request['item_category'], $request['item_location'], $request['quantity'], $request['unit']);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("UPDATE requests SET status = 'Approved' WHERE id = ?");
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['message' => 'Request approved and item added to inventory.']);
    } else {
        echo json_encode(['message' => 'Request not found.']);
    }
} elseif ($action === 'reject') {
    // Reject request: Update status and save rejection reason
    $reason = $data['reason'] ?? '';
    $stmt = $conn->prepare("UPDATE requests SET status = 'Rejected', rejection_reason = ? WHERE id = ?");
    $stmt->bind_param("si", $reason, $requestId);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['message' => 'Request rejected with reason provided.']);
} else {
    echo json_encode(['message' => 'Invalid action.']);
}
?>

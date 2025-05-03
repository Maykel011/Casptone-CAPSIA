<?php
include '../config/db_connection.php';
session_start();

header('Content-Type: application/json');

// Verify admin session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrator') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

// Handle POST request to approve borrow request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (isset($input['request_id'], $input['status']) && strtolower($input['status']) === 'approved') {
        $requestId = intval($input['request_id']); // Ensure request_id is an integer

        $stmt = $conn->prepare("UPDATE borrow_requests SET status = 'Approved' WHERE borrow_id = ?");
        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Failed to prepare statement.']);
            exit();
        }

        $stmt->bind_param("i", $requestId);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Request approved successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update request status.']);
        }
        $stmt->close();
        exit();
    } 
}

// Redirect to itemBorrowed.php
header("Location: ../admin/itemBorrowed.php");
exit();
?>

<?php
include '../config/db_connection.php';
session_start();

// Verify admin session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrator') {
    header("Location: ../login/login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $requestId = intval($_POST['request_id'] ?? 0);
    $reason = $_POST['reason'] ?? null;

    if (!$requestId) {
        echo json_encode(['success' => false, 'message' => 'Invalid request ID.']);
        exit();
    }

    if ($action === 'approve') {
        // Fetch request details
        $query = "SELECT user_id, item_name, item_category, quantity FROM new_item_requests WHERE request_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($request) {
            // Insert into items table
            $insertItemQuery = "INSERT INTO items (item_name, item_category, quantity, status) VALUES (?, ?, ?, 'Available')";
            $insertItemStmt = $conn->prepare($insertItemQuery);
            $insertItemStmt->bind_param("ssi", $request['item_name'], $request['item_category'], $request['quantity']);
            if ($insertItemStmt->execute()) {
                $itemId = $conn->insert_id;
                $insertItemStmt->close();

                // Update request status
                $updateRequestQuery = "UPDATE new_item_requests SET status = 'Approved', item_id = ? WHERE request_id = ?";
                $updateRequestStmt = $conn->prepare($updateRequestQuery);
                $updateRequestStmt->bind_param("ii", $itemId, $requestId);
                $updateRequestStmt->execute();
                $updateRequestStmt->close();

                // Notify the user
                $notificationQuery = "INSERT INTO notifications (user_id, message, type, created_at) VALUES (?, ?, 'Info', NOW())";
                $notificationStmt = $conn->prepare($notificationQuery);
                $message = "Your request for the item '{$request['item_name']}' has been approved and added to the inventory.";
                $notificationStmt->bind_param("is", $request['user_id'], $message);
                $notificationStmt->execute();
                $notificationStmt->close();

                echo json_encode(['success' => true, 'message' => 'Request approved and item added to inventory.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add item to inventory.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Request not found.']);
        }
    } elseif ($action === 'reject') {
        // Fetch request details
        $query = "SELECT user_id, item_name FROM new_item_requests WHERE request_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$request) {
            echo json_encode(['success' => false, 'message' => 'Request not found.']);
            exit();
        }

        if (empty($reason)) {
            echo json_encode(['success' => false, 'message' => 'Rejection reason is required.']);
            exit();
        }

        // Reject the request with a reason
        $updateQuery = "UPDATE new_item_requests SET status = 'Rejected', notes = ? WHERE request_id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param("si", $reason, $requestId);
        $updateStmt->execute();

        if ($updateStmt->affected_rows > 0) {
            // Notify the user
            $notificationQuery = "INSERT INTO notifications (user_id, message, type, created_at) VALUES (?, ?, 'Warning', NOW())";
            $notificationStmt = $conn->prepare($notificationQuery);
            $message = "Your request for the item '{$request['item_name']}' has been rejected. Reason: {$reason}";
            $notificationStmt->bind_param("is", $request['user_id'], $message);
            $notificationStmt->execute();
            $notificationStmt->close();

            echo json_encode(['success' => true, 'message' => 'Request rejected successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to reject the request.']);
        }
        $updateStmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>

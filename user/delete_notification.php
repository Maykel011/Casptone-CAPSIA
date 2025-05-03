<?php
session_start();
include '../config/db_connection.php';

// Verify CSRF token
if (!isset($_SERVER['HTTP_X_CSRF_TOKEN']) || $_SERVER['HTTP_X_CSRF_TOKEN'] !== $_SESSION['csrf_token']) {
    header('HTTP/1.1 403 Forbidden');
    exit(json_encode(['success' => false, 'message' => 'Invalid CSRF token']));
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit(json_encode(['success' => false, 'message' => 'Only POST requests are allowed']));
}

// Get the notification ID from the request
$input = json_decode(file_get_contents('php://input'), true);
$notificationId = isset($input['notificationId']) ? intval($input['notificationId']) : 0;

if ($notificationId <= 0) {
    header('HTTP/1.1 400 Bad Request');
    exit(json_encode(['success' => false, 'message' => 'Invalid notification ID']));
}

// Verify user is logged in and owns this notification
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit(json_encode(['success' => false, 'message' => 'Not logged in']));
}

// Delete the notification
$stmt = $conn->prepare("DELETE FROM notifications WHERE notification_id = ? AND user_id = ?");
$stmt->bind_param("ii", $notificationId, $_SESSION['user_id']);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Notification deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Notification not found or not owned by user']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>
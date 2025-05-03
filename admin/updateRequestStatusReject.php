<?php
include '../config/db_connection.php';
session_start();

header('Content-Type: application/json');

// Verify admin session
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrator') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Decode JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate received data
    if (!isset($input['request_id']) || !isset($input['status']) || !isset($input['reason'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid request. Missing parameters.']);
        exit();
    }

    $requestId = intval($input['request_id']);
    $reason = trim($input['reason']);
    $status = strtolower($input['status']);

    if ($status !== 'rejected') {
        echo json_encode(['success' => false, 'message' => 'Invalid status value.']);
        exit();
    }

    if (empty($reason)) {
        echo json_encode(['success' => false, 'message' => 'Rejection reason is required.']);
        exit();
    }

    // Prepare SQL statement
    $stmt = $conn->prepare("UPDATE borrow_requests SET status = ?, rejection_reason = ? WHERE borrow_id = ?");
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare statement.']);
        exit();
    }

    $rejectedStatus = "Rejected";
    $stmt->bind_param("ssi", $rejectedStatus, $reason, $requestId);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Request rejected successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update request status.']);
    }

    $stmt->close();
    exit();
}
?>


<!-- Rejection Modal -->
<div id="rejectModal" class="modal">
    <div class="modal-content">
        <span class="close"></span>
        <h3>Reject Request</h3>
        <textarea id="rejectionReason" rows="4" placeholder="Enter reason..."></textarea>
        
        <!-- Error message display -->
        <p id="error-message" style="color: red; font-size: 14px; margin-top: 5px;"></p>

        <div class="modal-buttons">
            <button id="confirmReject" class="confirm-btn">Confirm</button>
            <button id="cancelReject" class="cancel-btn">Cancel</button>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.reject-btn').forEach(button => {
    button.addEventListener('click', function () {
        const requestId = this.closest('tr').dataset.requestId; // Get request ID
        document.getElementById('rejectionReason').value = ""; // Clear previous input
        document.getElementById('error-message').textContent = ""; // Clear error message
        document.getElementById('rejectModal').dataset.requestId = requestId; // Store in modal
        document.getElementById('rejectModal').style.display = "block"; // Show modal
    });
});

document.getElementById('confirmReject').addEventListener('click', function () {
    const reason = document.getElementById('rejectionReason').value.trim();
    const errorMessage = document.getElementById('error-message');
    const requestId = document.getElementById('rejectModal').dataset.requestId;

    if (!reason) {
        errorMessage.textContent = "Rejection reason is required.";
        return;
    }

    errorMessage.textContent = ''; // Clear previous errors

    fetch('updateRequestStatusReject.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            request_id: requestId,
            status: 'rejected',
            reason: reason
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            errorMessage.textContent = data.message;
        }
    })
    .catch(error => {
        console.error("Fetch error:", error);
        errorMessage.textContent = "An error occurred. Please try again.";
    });
});

// Close modal when clicking cancel
document.getElementById('cancelReject').addEventListener('click', function () {
    document.getElementById('rejectModal').style.display = "none";
});

// Close modal when clicking outside
window.addEventListener('click', function (event) {
    const modal = document.getElementById('rejectModal');
    if (event.target === modal) {
        modal.style.display = "none";
    }
});

</script>

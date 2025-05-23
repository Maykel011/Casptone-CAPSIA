<?php
include '../config/db_connection.php';
session_start();

// Verify admin session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrator') {
    header("Location: ../login/login.php");
    exit();
}

// API endpoint for AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    // Approve action - changed to For Checking status
    if ($_POST['action'] === 'approve') {
        $requestId = intval($_POST['request_id']);

        // Get the request details first
        $checkStmt = $conn->prepare("SELECT user_id, item_id, quantity FROM borrow_requests WHERE borrow_id = ?");
        $checkStmt->bind_param("i", $requestId);
        $checkStmt->execute();
        $result = $checkStmt->get_result();
        $request = $result->fetch_assoc();
        $checkStmt->close();

        // Check item availability
        $availabilityCheck = $conn->prepare("SELECT availability FROM items WHERE item_id = ?");
        $availabilityCheck->bind_param("i", $request['item_id']);
        $availabilityCheck->execute();
        $availabilityResult = $availabilityCheck->get_result();
        $item = $availabilityResult->fetch_assoc();
        $availabilityCheck->close();

        if ($item['availability'] < $request['quantity']) {
            echo json_encode(['success' => false, 'error' => 'Not enough available items']);
            exit();
        }

        // Check if this user already has an approved request for this item
        $duplicateCheck = $conn->prepare("SELECT borrow_id FROM borrow_requests 
                                        WHERE user_id = ? AND item_id = ? AND status IN ('Approved', 'For Checking', 'For Releasing', 'Released')");
        $duplicateCheck->bind_param("ii", $request['user_id'], $request['item_id']);
        $duplicateCheck->execute();
        $duplicateResult = $duplicateCheck->get_result();

        if ($duplicateResult->num_rows > 0) {
            echo json_encode(['success' => false, 'error' => 'This user already has an approved request for this item']);
            exit();
        }

        // Start transaction
        $conn->begin_transaction();

        try {
            // First, update the borrow request status to For Checking
            $stmt = $conn->prepare("UPDATE borrow_requests SET status = 'For Checking', processed_at = NOW() WHERE borrow_id = ?");
            $stmt->bind_param("i", $requestId);
            $stmt->execute();
            $stmt->close();

            // Then, update the item's availability
            $updateItemStmt = $conn->prepare("UPDATE items SET availability = availability - ? WHERE item_id = ?");
            $updateItemStmt->bind_param("ii", $request['quantity'], $request['item_id']);
            $updateItemStmt->execute();
            $updateItemStmt->close();

            // Commit transaction
            $conn->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            // Rollback transaction if any error occurs
            $conn->rollback();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
    } 
    // Process action - for changing status from For Checking to For Releasing
    elseif ($_POST['action'] === 'process') {
        $requestId = intval($_POST['request_id']);

        $stmt = $conn->prepare("UPDATE borrow_requests SET status = 'For Releasing', processed_at = NOW() WHERE borrow_id = ?");
        $stmt->bind_param("i", $requestId);
        $success = $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => $success]);
        exit();
    }
    // Release action - for changing status from For Releasing to Released
  // In the 'release' action section
elseif ($_POST['action'] === 'release') {
    $requestId = intval($_POST['request_id']);

    // Start transaction
    $conn->begin_transaction();

    try {
        // First, get the user_id and item_id from the request
        $getRequestStmt = $conn->prepare("SELECT user_id, item_id FROM borrow_requests WHERE borrow_id = ?");
        $getRequestStmt->bind_param("i", $requestId);
        $getRequestStmt->execute();
        $requestData = $getRequestStmt->get_result()->fetch_assoc();
        $getRequestStmt->close();

        // Update the borrow request status to Released
        $stmt = $conn->prepare("UPDATE borrow_requests SET status = 'Released', processed_at = NOW() WHERE borrow_id = ?");
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        $stmt->close();

        // Remove from user's cart if exists (using session)
        if ($requestData) {
            $userId = $requestData['user_id'];
            $itemId = $requestData['item_id'];
            
            // Get the user's cart from session
            $cartCheckStmt = $conn->prepare("SELECT cart_data FROM user_carts WHERE user_id = ?");
            $cartCheckStmt->bind_param("i", $userId);
            $cartCheckStmt->execute();
            $cartResult = $cartCheckStmt->get_result();
            
            if ($cartResult->num_rows > 0) {
                $cartData = json_decode($cartResult->fetch_assoc()['cart_data'], true);
                $updatedCart = array_filter($cartData, function($item) use ($itemId) {
                    return $item['item_id'] != $itemId;
                });
                
                // Update the cart in database
                $updateCartStmt = $conn->prepare("UPDATE user_carts SET cart_data = ? WHERE user_id = ?");
                $updatedCartJson = json_encode(array_values($updatedCart));
                $updateCartStmt->bind_param("si", $updatedCartJson, $userId);
                $updateCartStmt->execute();
                $updateCartStmt->close();
            }
            $cartCheckStmt->close();
        }

        // Commit transaction
        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        // Rollback transaction if any error occurs
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit();
}
    
    // Reject action
    elseif ($_POST['action'] === 'reject') {
        $requestId = intval($_POST['request_id']);
        $reason = $_POST['reason'] ?? '';

        // Server-side word count validation
        $wordCount = count(preg_split('/\s+/', trim($reason)));
        if ($wordCount > 5) {
            echo json_encode(['success' => false, 'error' => 'Reason must be 5 words or less']);
            exit();
        }

        $stmt = $conn->prepare("UPDATE borrow_requests SET status = 'Rejected', rejection_reason = ?, processed_at = NOW() WHERE borrow_id = ?");
        $stmt->bind_param("si", $reason, $requestId);
        $success = $stmt->execute();
        $stmt->close();

        echo json_encode(['success' => $success]);
        exit();
    }
    // Return action
    elseif ($_POST['action'] === 'return') {
        $requestId = intval($_POST['request_id']);
    
        // Start transaction
        $conn->begin_transaction();
    
        try {
            // Get the request details first
            $checkStmt = $conn->prepare("SELECT br.item_id, br.quantity, ir.return_id 
                                       FROM borrow_requests br
                                       JOIN item_returns ir ON br.item_id = ir.item_id AND br.user_id = ir.user_id
                                       WHERE br.borrow_id = ? AND br.status = 'Return Pending'");
            $checkStmt->bind_param("i", $requestId);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $request = $result->fetch_assoc();
            $checkStmt->close();
    
            if (!$request) {
                throw new Exception('No pending return request found for this item');
            }
    
            // Update the borrow request status
            $stmt = $conn->prepare("UPDATE borrow_requests SET status = 'Returned', processed_at = NOW() WHERE borrow_id = ?");
            $stmt->bind_param("i", $requestId);
            $stmt->execute();
            $stmt->close();
    
            // Update the item_return status
            $updateReturnStmt = $conn->prepare("UPDATE item_returns SET status = 'Completed' WHERE return_id = ?");
            $updateReturnStmt->bind_param("i", $request['return_id']);
            $updateReturnStmt->execute();
            $updateReturnStmt->close();
    
            // Update item availability
            $updateItemStmt = $conn->prepare("UPDATE items SET availability = availability + ? WHERE item_id = ?");
            $updateItemStmt->bind_param("ii", $request['quantity'], $request['item_id']);
            $updateItemStmt->execute();
            $updateItemStmt->close();
    
            $conn->commit();
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit();
    }
    // Get requests for modal
    elseif ($_POST['action'] === 'get_requests') {
        $query = "SELECT br.borrow_id AS request_id, u.username, i.item_name, i.item_category, 
                 br.status, br.quantity, br.request_date, br.processed_at
                 FROM borrow_requests br 
                 JOIN users u ON br.user_id = u.user_id
                 JOIN items i ON br.item_id = i.item_id
                 WHERE br.status IN ('For Checking', 'For Releasing', 'Released', 'Return Pending')
                 ORDER BY br.request_date DESC";
        $result = $conn->query($query);
    
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
    
        echo json_encode(['success' => true, 'requests' => $rows]);
        exit();
    }
    
    // Refresh action
// In the refresh action section, modify the query to include Return Pending status
elseif ($_POST['action'] === 'refresh') {
    $query = "SELECT br.borrow_id AS request_id, u.username, i.item_name, i.item_category,
            br.date_needed, br.return_date, br.quantity, br.purpose, br.notes, 
            br.status, br.request_date, br.processed_at, br.rejection_reason
            FROM borrow_requests br 
            JOIN users u ON br.user_id = u.user_id
            JOIN items i ON br.item_id = i.item_id
            WHERE br.status IN ('Pending', 'Approved', 'Rejected', 'Returned', 'For Checking', 'For Releasing', 'Released', 'Return Pending')
            ORDER BY br.request_date DESC";
    $result = $conn->query($query);

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    echo json_encode(['rows' => $rows]);
    exit();
}
}

// Regular page load
$currentAdminId = intval($_SESSION['user_id']);
$stmt = $conn->prepare("SELECT username, email FROM users WHERE user_id = ?");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $currentAdminId);
$stmt->execute();
$result = $stmt->get_result();
$currentAdmin = $result->fetch_assoc();
$stmt->close();

$accountName = $currentAdmin['username'] ?? 'User';
$accountEmail = $currentAdmin['email'] ?? '';
if (empty($accountName)) {
    $accountName = 'Admin';
}

$accountName = htmlspecialchars($accountName);
$accountRole = 'Administrator';

// In the regular page load section
$query = "SELECT br.borrow_id AS request_id, u.username, i.item_name, i.item_category,
        br.date_needed, br.return_date, br.quantity, br.purpose, br.notes, 
        br.status, br.request_date, br.processed_at, br.rejection_reason
        FROM borrow_requests br 
        JOIN users u ON br.user_id = u.user_id
        JOIN items i ON br.item_id = i.item_id
        WHERE br.status IN ('Pending', 'Approved', 'Rejected', 'Returned', 'For Checking', 'For Releasing', 'Released', 'Return Pending')
        ORDER BY br.request_date DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UCGS Inventory | Application Request</title>
    <link rel="stylesheet" href="../css/Applications_Request.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>

<body>
    <header class="header">
        <div class="header-content">
            <div class="left-side">
                <img src="../assets/img/Logo.png" alt="Logo" class="logo">
                <span class="website-name">UCGS Inventory</span>
            </div>
            <div class="right-side">
                <div class="user">
                    <img src="../assets/img/users.png" alt="User" class="icon" id="userIcon">
                    <span class="admin-text"><?php echo htmlspecialchars($accountName); ?> (<?php echo htmlspecialchars($accountRole); ?>)</span>
                    <div class="user-dropdown" id="userDropdown">
                        <a href="adminprofile.php"><img src="../assets/img/updateuser.png" alt="Profile Icon" class="dropdown-icon"> Profile</a>
                        <a href="adminnotification.php"><img src="../assets/img/notificationbell.png" alt="Notification Icon" class="dropdown-icon"> Notification</a>
                        <a href="../login/logout.php"><img src="../assets/img/logout.png" alt="Logout Icon" class="dropdown-icon"> Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <aside class="sidebar">
        <ul>
            <li><a href="adminDashboard.php"><img src="../assets/img/dashboards.png" alt="Dashboard Icon" class="sidebar-icon"> Dashboard</a></li>
            <li><a href="ItemRecords.php"><img src="../assets/img/list-items.png" alt="Items Icon" class="sidebar-icon"><span class="text">Item Records</span></a></li>
            <li class="dropdown">
                <a href="#" class="dropdown-btn">
                    <img src="../assets/img/request-for-proposal.png" alt="Request Icon" class="sidebar-icon">
                    <span class="text">Request Record</span>
                    <svg class="arrow-icon" viewBox="0 0 448 512" width="1em" height="1em" fill="currentColor">
                        <path d="M207.029 381.476L12.686 187.132c-9.373-9.373-9.373-24.569 0-33.941l22.667-22.667c9.357-9.357 24.522-9.375 33.901-.04L224 284.505l154.745-154.021c9.379-9.335 24.544-9.317 33.901.04l22.667 22.667c9.373 9.373 9.373 24.569 0 33.941L240.971 381.476c-9.373 9.372-24.569 9.372-33.942 0z"/>
                    </svg>
                </a>
                <ul class="dropdown-content">
                    <li><a href="ItemRequest.php"> Item Request by User</a></li>
                    <li><a href="Application_Request.php"> Application Request</a></li>
                    <li><a href="ItemReturned.php"> Item Returned</a></li>
                </ul>
            </li>
            <li><a href="Reports.php"><img src="../assets/img/reports.png" alt="Reports Icon" class="sidebar-icon"> Reports</a></li>
            <li><a href="UserManagement.php"><img src="../assets/img/user-management.png" alt="User Management Icon" class="sidebar-icon"> User Management</a></li>
        </ul>
    </aside>

    <div class="main-content">
        <h2>Application Request</h2>

        <div class="filter-container">
            <div class="search-wrapper">
                <div class="search-container">
                    <i class="fas fa-search"></i>
                    <input type="text" id="search-box" placeholder="Search...">
                </div>
            </div>
            <div class="form-group">
                <label for="status-filter">Status:</label>
                <select id="status-filter" class="form-control">
                    <option value="all">All</option>
                    <option value="Pending">Pending</option>
                    <option value="For Checking">For Checking</option>
                    <option value="For Releasing">For Releasing</option>
                    <option value="Released">Released</option>
                    <option value="Returned">Returned</option>
                    <option value="Rejected">Rejected</option>
                </select>
            </div>
            <button id="view-list-btn" class="btn-primary">
                <i class="fas fa-list"></i> Processing List </button>
        </div>

        <table class="item-table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Item Name</th>
                    <th>Item Type</th>
                    <th>Date Needed</th>
                    <th>Return Date</th>
                    <th>Quantity</th>
                    <th>Purpose</th>
                    <th>Notes</th>
                    <th>Status</th>
                    <th>Request Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="item-table-body">
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr data-request-id="<?php echo $row['request_id']; ?>">
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['item_category']); ?></td>
                            <td><?php echo htmlspecialchars($row['date_needed']); ?></td>
                            <td><?php echo htmlspecialchars($row['return_date']); ?></td>
                            <td><?php echo htmlspecialchars($row['quantity']); ?></td>
                            <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                            <td><?php echo htmlspecialchars($row['notes']); ?></td>
                            <td class="status-cell <?php echo strtolower(str_replace(' ', '-', $row['status'])); ?>">
                                <?php
                                $processedTime = $row['processed_at'] ? (new DateTime($row['processed_at']))->format('M j, Y g:i A') : '';

                                if ($row['status'] === 'Approved') {
                                    echo '<span class="status-approved" title="Approved on ' . htmlspecialchars($processedTime) . '">Approved</span>';
                                    echo '<span class="processed-time">' . htmlspecialchars($processedTime) . '</span>';
                                } elseif ($row['status'] === 'Rejected') {
                                    echo '<span class="status-rejected" title="Rejected on ' . htmlspecialchars($processedTime) . '">Rejected</span>';
                                    echo '<span class="processed-time">' . htmlspecialchars($processedTime) . '</span>';
                                    if (!empty($row['rejection_reason'])) {
                                        echo '<div class="rejection-reason" title="Rejection reason">' . htmlspecialchars($row['rejection_reason']) . '</div>';
                                    }
                                } elseif ($row['status'] === 'Returned') {
                                    echo '<span class="status-returned" title="Returned on ' . htmlspecialchars($processedTime) . '">Returned</span>';
                                    echo '<span class="processed-time">' . htmlspecialchars($processedTime) . '</span>';
                                } elseif ($row['status'] === 'For Checking') {
                                    echo '<span class="status-checking" title="For Checking since ' . htmlspecialchars($processedTime) . '">For Checking</span>';
                                    echo '<span class="processed-time">' . htmlspecialchars($processedTime) . '</span>';
                                } elseif ($row['status'] === 'For Releasing') {
                                    echo '<span class="status-releasing" title="For Releasing since ' . htmlspecialchars($processedTime) . '">For Releasing</span>';
                                    echo '<span class="processed-time">' . htmlspecialchars($processedTime) . '</span>';
                                } elseif ($row['status'] === 'Released') {
                                    echo '<span class="status-released" title="Released on ' . htmlspecialchars($processedTime) . '">Released</span>';
                                    echo '<span class="processed-time">' . htmlspecialchars($processedTime) . '</span>';
                                } else {
                                    echo '<span title="Pending approval">' . htmlspecialchars($row['status']) . '</span>';
                                }
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars($row['request_date']); ?></td>
                            <td class="action-cell">
                                <?php if ($row['status'] === 'Pending'): ?>
                                    <button class="approve-btn" data-request-id="<?php echo $row['request_id']; ?>">Approve</button>
                                    <button class="reject-btn" data-request-id="<?php echo $row['request_id']; ?>">Reject</button>
                                <?php elseif ($row['status'] === 'Released'): ?>
                                    <button class="return-btn" data-request-id="<?php echo $row['request_id']; ?>">Return</button>
                                <?php else: ?>
                                    <span class="processed-label">Processed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="11">No requests found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        <div class="pagination">
            <button onclick="prevPage()" id="prev-btn">Previous</button>
            <span id="page-number">Page 1</span>
            <button onclick="nextPage()" id="next-btn">Next</button>
        </div>
    </div>

    <!-- Rejection Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3>Reject Request</h3>
            <textarea id="rejectionReason" rows="4" placeholder="Enter reason (max 5 words)..." maxlength="100"></textarea>
            <div class="word-counter">
                <span id="wordCount">0</span>/5 words
                <p id="error-message" style="color: red; font-size: 14px; margin-top: 5px; text-align: center;"></p>
            </div>
            <div class="modal-buttons">
                <button id="confirmReject" class="confirm-btn">Confirm</button>
                <button id="cancelReject" class="cancel-btn">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Request List Modal -->
    <div id="requestListModal" class="modal">
        <div class="modal-content" style="width: 80%; max-width: 900px;">
            <div class="modal-header">
                <h3>Request Processing List</h3>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body">
                <div class="table-container">
                    <table class="modal-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Item Name</th>
                                <th>Item Type</th>
                                <th>Quantity</th>
                                <th>Status</th>
                                <th>Request Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="request-list-body">
                            <!-- Will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button id="refresh-list-btn" class="btn-primary">
                    <i class="fas fa-sync-alt"></i> Refresh List
                </button>
            </div>
        </div>
    </div>

    <!-- Modern Notification Modal -->
<div id="churchNotification" class="notification-modal">
    <div class="notification-header">
        <div class="notification-title">
            <div class="notification-icon">
                <i class="fas fa-check"></i>
            </div>
            <span id="notificationTitle">Success</span>
        </div>
        <button class="notification-close"></button>
    </div>
    <div class="notification-body">
        <p id="notificationMessage">Your action was successful.</p>
    </div>
    <div class="notification-footer">
        <button class="notification-btn">OK</button>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="churchConfirmation" class="confirmation-modal">
    <div class="confirmation-content">
        <div class="confirmation-icon">
            <i class="fas fa-question-circle"></i>
        </div>
        <h3 class="confirmation-title">Confirmation</h3>
        <p class="confirmation-message" id="confirmationMessage">Are you sure you want to perform this action?</p>
        <div class="confirmation-buttons">
            <button class="confirmation-btn confirm" id="confirmAction">Yes</button>
            <button class="confirmation-btn cancel" id="cancelAction">Cancel</button>
        </div>
    </div>
</div>

    <script src="../js/Applications_Request.js"></script>
</body>
</html>
<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once '../config/db_connection.php';

// Secure session handling
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_secure' => true,
        'cookie_httponly' => true,
        'use_strict_mode' => true
    ]);
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

// Verify admin session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrator') {
    header("Location: ../login/login.php");
    exit();
}

// Regenerate session ID periodically
if (empty($_SESSION['initiated'])) {
    session_regenerate_id();
    $_SESSION['initiated'] = true;
}

// Fetch admin details
$currentAdminId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username, email FROM users WHERE user_id = ? LIMIT 1");
if (!$stmt) {
    die("Database error: " . $conn->error);
}
$stmt->bind_param("i", $currentAdminId);
$stmt->execute();
$result = $stmt->get_result();
$currentAdmin = $result->fetch_assoc();
$stmt->close();

if (!$currentAdmin) {
    header("Location: ../login/login.php");
    exit();
}

$accountName = htmlspecialchars($currentAdmin['username'], ENT_QUOTES, 'UTF-8');
$accountEmail = htmlspecialchars($currentAdmin['email'], ENT_QUOTES, 'UTF-8');
$accountRole = htmlspecialchars($_SESSION['role'], ENT_QUOTES, 'UTF-8');

// Handle AJAX verification request
if (isset($_GET['verify_request'])) {
    $requestId = intval($_GET['request_id']);
    $stmt = $conn->prepare("SELECT status FROM new_item_requests WHERE request_id = ? LIMIT 1");
    $stmt->bind_param("i", $requestId);
    $stmt->execute();
    $result = $stmt->get_result();
    $status = $result->fetch_assoc()['status'] ?? 'unknown';
    
    header('Content-Type: application/json');
    echo json_encode(['status' => $status]);
    exit();
}

// Handle form actions (approve/reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit();
    }
    
    // Validate AJAX request
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit();
    }
    
    $action = $_POST['action'] ?? '';
    $requestId = intval($_POST['request_id'] ?? 0);
    
    if ($requestId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid request ID']);
        exit();
    }
    
    try {
        $conn->begin_transaction();
        
        // Verify request exists and is pending
        $stmt = $conn->prepare("SELECT * FROM new_item_requests WHERE request_id = ? AND status = 'Pending' FOR UPDATE");
        $stmt->bind_param("i", $requestId);
        $stmt->execute();
        $result = $stmt->get_result();
        $request = $result->fetch_assoc();
        $stmt->close();
        
        if (!$request) {
            throw new Exception('Request not found or already processed');
        }
        
        if ($action === 'approve') {
            // Generate unique item number
            $itemNo = 'ITEM-' . strtoupper(uniqid());
            
            // Insert into items table
            $insertStmt = $conn->prepare("INSERT INTO items 
                                        (item_no, item_name, item_category, quantity, status) 
                                        VALUES (?, ?, ?, ?, 'Available')");
            $insertStmt->bind_param("sssi", $itemNo, $request['item_name'], 
                                  $request['item_category'], $request['quantity']);
            $insertStmt->execute();
            $itemId = $conn->insert_id;
            $insertStmt->close();
            
            // DELETE the request instead of updating it
            $deleteStmt = $conn->prepare("DELETE FROM new_item_requests WHERE request_id = ?");
            $deleteStmt->bind_param("i", $requestId);
            $deleteStmt->execute();
            $deleteStmt->close();
            
            // Log transaction
            $details = "Approved and moved request for {$request['quantity']} {$request['item_unit']} of '{$request['item_name']}' to items table";
            $transStmt = $conn->prepare("INSERT INTO transactions 
                                        (user_id, action, details, item_id, quantity, status, item_name) 
                                        VALUES (?, 'Item Approval', ?, ?, ?, 'Approved', ?)");
            $transStmt->bind_param("isiss", $_SESSION['user_id'], $details, 
                                 $itemId, $request['quantity'], $request['item_name']);
            $transStmt->execute();
            $transStmt->close();
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Request approved and moved to items successfully',
                'item_no' => $itemNo,
                'item_id' => $itemId
            ]);
        } elseif ($action === 'reject') {
            $reason = trim($_POST['reason'] ?? '');
            
            if (empty($reason)) {
                throw new Exception('Rejection reason is required');
            }
            
            // Update request status
            $updateStmt = $conn->prepare("UPDATE new_item_requests 
                                         SET status = 'Rejected', notes = CONCAT(IFNULL(notes, ''), ?) 
                                         WHERE request_id = ?");
            $rejectionNote = "\n\nRejection Reason: " . $reason;
            $updateStmt->bind_param("si", $rejectionNote, $requestId);
            $updateStmt->execute();
            $updateStmt->close();
            
            // Log transaction
// In your rejection logic, replace the transaction insertion with:
$details = "Rejected request for '{$request['item_name']}'. Reason: $reason";
$transStmt = $conn->prepare("INSERT INTO transactions 
                            (user_id, action, details, quantity, status, item_name, request_id) 
                            VALUES (?, 'Item Rejection', ?, ?, 'Rejected', ?, ?)");
$transStmt->bind_param("isisi", $_SESSION['user_id'], $details, 
                     $request['quantity'], $request['item_name'], $requestId);
$transStmt->execute();
$transStmt->close();
            
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Request rejected successfully'
            ]);
            
        } else {
            throw new Exception('Invalid action');
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        error_log("Request processing error: " . $e->getMessage());
        
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'error' => $conn->error
        ]);
    }
    exit();
}

// Fetch item requests with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Base query
$query = "SELECT SQL_CALC_FOUND_ROWS 
          r.request_id, u.username, r.item_name, r.item_category, 
          r.purpose, r.request_date, r.quantity, r.item_unit, r.status 
          FROM new_item_requests r
          JOIN users u ON r.user_id = u.user_id
          WHERE r.status = 'Pending'  -- Only show pending requests
          ORDER BY r.request_date DESC
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
$requests = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get total count
$totalResult = $conn->query("SELECT FOUND_ROWS() AS total");
$totalRow = $totalResult->fetch_assoc();
$totalRequests = $totalRow['total'];
$totalPages = ceil($totalRequests / $limit);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UCGS Inventory | Item Request</title>
    <link rel="stylesheet" href="../css/AdminItmRequests.css">
    <link rel="icon" href="../assets/img/favicon.ico" type="image/x-icon">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
 
</head>
<body>
<header class="header">
    <div class="header-content">
        <div class="left-side">
            <img src="../assets/img/Logo.png" alt="Logo" class="logo" width="40" height="40">
            <span class="website-name">UCGS Inventory</span>
        </div>
        <div class="right-side">
            <div class="user">
                <img src="../assets/img/users.png" alt="User" class="icon" id="userIcon" width="24" height="24">
                <span class="admin-text"><?php echo $accountName; ?> (<?php echo $accountRole; ?>)</span>
                <div class="user-dropdown" id="userDropdown">
                    <a href="adminprofile.php"><img src="../assets/img/updateuser.png" alt="Profile" class="dropdown-icon"> Profile</a>
                    <a href="adminnotification.php"><img src="../assets/img/notificationbell.png" alt="Notification" class="dropdown-icon"> Notification</a>
                    <a href="../login/logout.php"><img src="../assets/img/logout.png" alt="Logout" class="dropdown-icon"> Logout</a>
                </div>
            </div>
        </div>
    </div>
</header>

<aside class="sidebar">
    <ul>
        <li><a href="adminDashboard.php"><img src="../assets/img/dashboards.png" alt="Dashboard Icon" class="sidebar-icon"> Dashboard</a></li>

        <li><a href="ItemRecords.php"><img src="../assets/img/list-items.png" alt="Items Icon" class="sidebar-icon">Item Records</i></a></li>

        <!-- Request Record with Dropdown -->
        <li class="dropdown">
            <a href="#" class="dropdown-btn">
                <img src="../assets/img/request-for-proposal.png" alt="Request Icon" class="sidebar-icon">
                <span class="text">Request Record</span>
                <svg class="arrow-icon" viewBox="0 0 448 512" width="1em" height="1em" fill="currentColor">
  <path d="M207.029 381.476L12.686 187.132c-9.373-9.373-9.373-24.569 0-33.941l22.667-22.667c9.357-9.357 24.522-9.375 33.901-.04L224 284.505l154.745-154.021c9.379-9.335 24.544-9.317 33.901.04l22.667 22.667c9.373 9.373 9.373 24.569 0 33.941L240.971 381.476c-9.373 9.372-24.569 9.372-33.942 0z"/>
</svg>
            </a>
            <ul class="dropdown-content">
                    <li><a href="ItemRequest.php">Item Request by User</a></li>
                    <li><a href="Application_Request.php"> Application Request</a></li>
                    <li><a href="ItemReturned.php">Item Returned</a></li>
                </ul>
        </li>

        <li><a href="Reports.php"><img src="../assets/img/reports.png" alt="Reports Icon" class="sidebar-icon"> Reports</a></li>
        <li><a href="UserManagement.php"><img src="../assets/img/user-management.png" alt="User Management Icon" class="sidebar-icon"> User Management</a></li>
    </ul>
</aside>

<div class="main-content">
    <h2>Item Request List</h2>
    
    <div class="filter-container">
        <input type="text" id="search-box" placeholder="Search by item, user, or purpose...">
        <div class="date-range">
            <label for="start-date">From:</label>
            <input type="date" id="start-date">
            <label for="end-date">To:</label>
            <input type="date" id="end-date">
        </div>
    </div>

    <!-- Requests Table -->
    <table class="item-table">
        <thead>
            <tr>
                <th>Request ID</th>
                <th>Username</th>
                <th>Item Name</th>
                <th>Category</th>
                <th>Quantity</th>
                <th>Unit</th>
                <th>Purpose</th>
                <th>Request Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($requests as $request): ?>
            <tr data-request-id="<?php echo $request['request_id']; ?>">
                <td><?php echo htmlspecialchars($request['request_id']); ?></td>
                <td><?php echo htmlspecialchars($request['username']); ?></td>
                <td><?php echo htmlspecialchars($request['item_name']); ?></td>
                <td><?php echo htmlspecialchars($request['item_category']); ?></td>
                <td><?php echo htmlspecialchars($request['quantity']); ?></td>
                <td><?php echo htmlspecialchars($request['item_unit']); ?></td>
                <td><?php echo htmlspecialchars(substr($request['purpose'], 0, 50) . (strlen($request['purpose']) > 50 ? '...' : '')); ?></td>
                <td><?php echo htmlspecialchars($request['request_date']); ?></td>
                <td class="status-cell <?php echo strtolower($request['status']); ?>">
                    <?php echo htmlspecialchars($request['status']); ?>
                </td>
                <td>
                    <div class="action-buttons">
                        <button class="approve-btn action-btn <?php echo $request['status'] !== 'Pending' ? 'disabled' : ''; ?>"
                                <?php echo $request['status'] !== 'Pending' ? 'disabled' : ''; ?>>
                            Approve
                        </button>
                        <button class="reject-btn action-btn <?php echo $request['status'] !== 'Pending' ? 'disabled' : ''; ?>"
                                <?php echo $request['status'] !== 'Pending' ? 'disabled' : ''; ?>>
                            Reject
                        </button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($requests)): ?>
            <tr>
                <td colspan="10" class="no-results">No item requests found</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?>" class="page-link"><i class="fas fa-chevron-left"></i> Previous</a>
        <?php endif; ?>
        
        <span class="page-info">Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
        
        <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page + 1; ?>" class="page-link">Next <i class="fas fa-chevron-right"></i></a>
        <?php endif; ?>
    </div>
</div>

<!-- Approval Modal -->
<div id="approveModal" class="Approved-modal" style="display: none;">
    <div class="Approved-modal-content">
        <span class="close"></span>
        <h3>Approve Request</h3>
        <p>Request ID: <span id="approveModalRequestId"></span></p>
        <p>Item: <span id="approveModalItemName"></span></p>
        <p>Are you sure you want to approve this request?</p>
        <div class="Approve-modal-buttons">
            <button id="confirmApprove" class="confirm-btn"><i class="fas fa-check"></i> Confirm</button>
            <button id="cancelApprove" class="cancel-btn"><i class="fas fa-times"></i> Cancel</button>
        </div>
    </div>
</div>

<!-- Rejection Modal -->
<div id="rejectModal" class="reject-modal" style="display: none;">
    <div class="reject-modal-content">
        <span class="close"></span>
        <h3>Reject Request</h3>
        <p>Request ID: <span id="modalRequestId"></span></p>
        <p>Item: <span id="modalItemName"></span></p>
        <input id="rejectionReason" rows="4" placeholder="Please provide a reason for rejection..." required></input>
        <p id="error-message" class="error-text"></p>
        <div class="reject-modal-buttons">
            <button id="confirmReject" class="confirm-btn"><i class="fas fa-check"></i> Confirm</button>
            <button id="cancelReject" class="cancel-btn"><i class="fas fa-times"></i> Cancel</button>
        </div>
    </div>
</div>

<script src="../js/AdminItmrequest.js"></script>
</body>
</html>
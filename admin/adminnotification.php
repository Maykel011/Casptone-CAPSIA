<?php
include '../config/db_connection.php';
session_start();

// Function to get logged-in user details
function getLoggedInUser($conn) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }
    
    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

// Verify admin session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrator') {
    header("Location: ../login/login.php");
    exit();
}

// Get logged-in user details
$loggedInUser = getLoggedInUser($conn);
if (!$loggedInUser || $loggedInUser['role'] !== 'Administrator') {
    header("Location: ../login/login.php");
    exit();
}

$accountName = $loggedInUser['username'];
$accountRole = $loggedInUser['role'];
$loggedInUserId = $loggedInUser['user_id'];

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle form submission for new item requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_new_item_request'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = "Invalid CSRF token";
        header("Location: adminnotification.php");
        exit();
    }

    $itemId = intval($_POST['item_id']);
    $quantity = intval($_POST['quantity']);
    $purpose = trim($_POST['purpose']);
    $notes = trim($_POST['notes']);

    // Validate input
    if ($itemId > 0 && $quantity > 0 && !empty($purpose)) {
        $stmt = $conn->prepare("INSERT INTO new_item_requests (user_id, item_id, quantity, purpose, notes) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iiiss", $loggedInUserId, $itemId, $quantity, $purpose, $notes);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "New item request submitted successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to submit the request. Please try again.";
        }

        $stmt->close();
    } else {
        $_SESSION['error_message'] = "Invalid input. Please fill out all required fields.";
    }

    header("Location: adminnotification.php");
    exit();
}

// Handle form submission for borrow requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_borrow_request'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = "Invalid CSRF token";
        header("Location: adminnotification.php");
        exit();
    }

    $itemId = intval($_POST['item_id']);
    $quantity = intval($_POST['quantity']);
    $dateNeeded = $_POST['date_needed'];
    $returnDate = $_POST['return_date'];
    $purpose = trim($_POST['purpose']);
    $notes = trim($_POST['notes']);

    // Validate input
    if ($itemId > 0 && $quantity > 0 && !empty($dateNeeded) && !empty($returnDate) && !empty($purpose)) {
        $stmt = $conn->prepare("INSERT INTO borrow_requests (user_id, item_id, quantity, date_needed, return_date, purpose, notes) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iiissss", $loggedInUserId, $itemId, $quantity, $dateNeeded, $returnDate, $purpose, $notes);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Borrow request submitted successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to submit the request. Please try again.";
        }

        $stmt->close();
    } else {
        $_SESSION['error_message'] = "Invalid input. Please fill out all required fields.";
    }

    header("Location: adminnotification.php");
    exit();
}

// Handle form submission for return requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_return_request'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = "Invalid CSRF token";
        header("Location: adminnotification.php");
        exit();
    }

    $borrowId = intval($_POST['borrow_id']);
    $returnDate = $_POST['return_date'];
    $itemCondition = $_POST['item_condition'];
    $notes = trim($_POST['notes']);

    // Validate input
    if ($borrowId > 0 && !empty($returnDate) && !empty($itemCondition)) {
        $stmt = $conn->prepare("INSERT INTO return_requests (borrow_id, return_date, item_condition, notes) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $borrowId, $returnDate, $itemCondition, $notes);

        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Return request submitted successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to submit the request. Please try again.";
        }

        $stmt->close();
    } else {
        $_SESSION['error_message'] = "Invalid input. Please fill out all required fields.";
    }

    header("Location: adminnotification.php");
    exit();
}

// Fetch user requests from the new_item_requests table
$newItemRequestsQuery = "SELECT nir.request_id, nir.quantity, nir.purpose, nir.notes, 
                                nir.status, nir.request_date, 
                                u.username, nir.item_name 
                         FROM new_item_requests nir
                         JOIN users u ON nir.user_id = u.user_id
                         ORDER BY nir.request_date DESC";
$newItemRequestsResult = $conn->query($newItemRequestsQuery);

$newItemRequests = [];
if ($newItemRequestsResult && $newItemRequestsResult->num_rows > 0) {
    while ($row = $newItemRequestsResult->fetch_assoc()) {
        $newItemRequests[] = $row;
    }
}

// Fetch borrow requests from the borrow_requests table
$borrowRequestsQuery = "SELECT br.borrow_id, br.quantity, br.date_needed, br.return_date, 
                               br.purpose, br.notes, br.status, br.request_date, 
                               u.username, i.item_name 
                        FROM borrow_requests br
                        JOIN users u ON br.user_id = u.user_id
                        JOIN items i ON br.item_id = i.item_id
                        ORDER BY br.request_date DESC";
$borrowRequestsResult = $conn->query($borrowRequestsQuery);

$borrowRequests = [];
if ($borrowRequestsResult && $borrowRequestsResult->num_rows > 0) {
    while ($row = $borrowRequestsResult->fetch_assoc()) {
        $borrowRequests[] = $row;
    }
}

// Fetch return requests from the return_requests table
$returnRequestsQuery = "SELECT rr.return_id, rr.return_date, rr.item_condition, rr.notes, 
                               rr.status, rr.created_at, 
                               br.borrow_id, u.username, i.item_name 
                        FROM return_requests rr
                        JOIN borrow_requests br ON rr.borrow_id = br.borrow_id
                        JOIN users u ON br.user_id = u.user_id
                        JOIN items i ON br.item_id = i.item_id
                        ORDER BY rr.created_at DESC";
$returnRequestsResult = $conn->query($returnRequestsQuery);

$returnRequests = [];
if ($returnRequestsResult && $returnRequestsResult->num_rows > 0) {
    while ($row = $returnRequestsResult->fetch_assoc()) {
        $returnRequests[] = $row;
    }
}

// Fetch return requests from the return_requests table
$returnRequestsQuery = "SELECT rr.return_id, rr.return_date, rr.item_condition, rr.notes, 
                               rr.status, rr.created_at, 
                               br.borrow_id, u.username, i.item_name 
                        FROM return_requests rr
                        JOIN borrow_requests br ON rr.borrow_id = br.borrow_id
                        JOIN users u ON br.user_id = u.user_id
                        JOIN items i ON br.item_id = i.item_id
                        ORDER BY rr.created_at DESC";
$returnRequestsResult = $conn->query($returnRequestsQuery);

$returnRequests = [];
if ($returnRequestsResult && $returnRequestsResult->num_rows > 0) {
    while ($row = $returnRequestsResult->fetch_assoc()) {
        $returnRequests[] = $row;
    }
}

$newItemRequestsResult = $conn->query($newItemRequestsQuery);
if ($newItemRequestsResult === false) {
    die("Query failed: " . $conn->error);
}
// Combine all requests for display
$allRequests = [
    'new_item_requests' => $newItemRequests,
    'borrow_requests' => $borrowRequests,
    'return_requests' => $returnRequests
];

// Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$totalNotifications = count($newItemRequests) + count($borrowRequests) + count($returnRequests);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Add meta tags and title -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UCGS Inventory | Notifications</title>
    <link rel="stylesheet" href="../css/AdminNotification.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

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
                        <a href="#"><img src="../assets/img/logout.png" alt="Logout Icon" class="dropdown-icon"> Logout</a>
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
                <li><a href="ItemRequest.php"><i class=""></i> Item Request by User</a></li>
                <li><a href="ItemBorrowed.php"><i class=""></i> Item Borrow</a></li>
                <li><a href="ItemReturned.php"><i class=""></i> Item Returned</a></li>
            </ul>
        </li>

        <li><a href="Reports.php"><img src="../assets/img/reports.png" alt="Reports Icon" class="sidebar-icon"> Reports</a></li>
        <li><a href="UserManagement.php"><img src="../assets/img/user-management.png" alt="User Management Icon" class="sidebar-icon"> User Management</a></li>
    </ul>
</aside>

    <div class="main-content">
        <div class="notification-header">
            <h2>Notifications</h2>
            <div class="notification-actions">
               
                
            </div>
        </div>
        
        <div class="notification-list">
            <?php if (!empty($allRequests)): ?>
                <!-- New Item Requests -->
                <h3>New Item Requests</h3>
                <?php foreach ($allRequests['new_item_requests'] as $request): ?>
                    <div class="notification-item <?= $request['status'] === 'Pending' ? 'unread' : 'read' ?>" 
                         data-request-id="<?= $request['request_id'] ?>">
                        <div class="notification-content">
                            <span class="notification-icon">
                                <i class="fa-solid fa-box"></i>
                            </span>
                            <div class="notification-text">
                                <p><strong>User:</strong> <?= htmlspecialchars($request['username']) ?></p>
                                <p><strong>Item:</strong> <?= htmlspecialchars($request['item_name']) ?></p>
                                <p><strong>Quantity:</strong> <?= htmlspecialchars($request['quantity']) ?></p>
                                <p><strong>Purpose:</strong> <?= htmlspecialchars($request['purpose']) ?></p>
                                <p><strong>Notes:</strong> <?= htmlspecialchars($request['notes'] ?? 'N/A') ?></p>
                                <span class="notification-date">
                                    <?= date('M j, Y g:i A', strtotime($request['request_date'])) ?>
                                </span>
                            </div>
                        </div>
                        <div class="notification-actions">
    <?php if ($request['status'] === 'Pending'): ?>
        <div class="status-badge pending">
            <span>Pending Approval</span>
        </div>
    <?php elseif ($request['status'] === 'Approved'): ?>
        <div class="status-badge approved">
            <span>Approved</span>
        </div>
    <?php elseif ($request['status'] === 'Rejected'): ?>
        <div class="status-badge rejected">
            <span>Rejected</span>
        </div>
    <?php endif; ?>
</div>
                    </div>
                <?php endforeach; ?>

                <!-- Borrow Requests -->
                <h3>Borrow Requests</h3>
                <?php foreach ($allRequests['borrow_requests'] as $request): ?>
                    <div class="notification-item <?= $request['status'] === 'Pending' ? 'unread' : 'read' ?>" 
                         data-borrow-id="<?= $request['borrow_id'] ?>">
                        <div class="notification-content">
                            <span class="notification-icon">
                                <i class="fa-solid fa-hand-holding"></i>
                            </span>
                            <div class="notification-text">
                                <p><strong>User:</strong> <?= htmlspecialchars($request['username']) ?></p>
                                <p><strong>Item:</strong> <?= htmlspecialchars($request['item_name']) ?></p>
                                <p><strong>Quantity:</strong> <?= htmlspecialchars($request['quantity']) ?></p>
                                <p><strong>Date Needed:</strong> <?= htmlspecialchars($request['date_needed']) ?></p>
                                <p><strong>Return Date:</strong> <?= htmlspecialchars($request['return_date']) ?></p>
                                <p><strong>Purpose:</strong> <?= htmlspecialchars($request['purpose']) ?></p>
                                <p><strong>Notes:</strong> <?= htmlspecialchars($request['notes'] ?? 'N/A') ?></p>
                                <span class="notification-date">
                                    <?= date('M j, Y g:i A', strtotime($request['request_date'])) ?>
                                </span>
                            </div>
                        </div>
                        <div class="notification-actions">
    <?php if ($request['status'] === 'Pending'): ?>
        <div class="status-badge pending">
            <span>Pending Approval</span>
        </div>
    <?php elseif ($request['status'] === 'Approved'): ?>
        <div class="status-badge approved"> 
            <span>Approved</span>
        </div>
    <?php elseif ($request['status'] === 'Rejected'): ?>
        <div class="status-badge rejected">
            <span>Rejected</span>
        </div>
    <?php endif; ?>
</div>
                    </div>
                <?php endforeach; ?>

                <!-- Return Requests -->
                <h3>Return Requests</h3>
                <?php foreach ($allRequests['return_requests'] as $request): ?>
                    <div class="notification-item <?= $request['status'] === 'Pending' ? 'unread' : 'read' ?>" 
                         data-return-id="<?= $request['return_id'] ?>">
                        <div class="notification-content">
                            <span class="notification-icon">
                                <i class="fa-solid fa-undo"></i>
                            </span>
                            <div class="notification-text">
                                <p><strong>User:</strong> <?= htmlspecialchars($request['username']) ?></p>
                                <p><strong>Item:</strong> <?= htmlspecialchars($request['item_name']) ?></p>
                                <p><strong>Return Date:</strong> <?= htmlspecialchars($request['return_date']) ?></p>
                                <p><strong>Condition:</strong> <?= htmlspecialchars($request['item_condition']) ?></p>
                                <p><strong>Notes:</strong> <?= htmlspecialchars($request['notes'] ?? 'N/A') ?></p>
                                <span class="notification-date">
                                    <?= date('M j, Y g:i A', strtotime($request['created_at'])) ?>
                                </span>
                            </div>
                        </div>
                        <div class="notification-actions">
                            <?php if ($request['status'] === 'Pending'): ?>
                                <button class="btn approve-request" onclick="handleApproveReturnRequest(this)">
                                    <span class="button-text">Approve</span>
                                    <div class="loading" style="display: none;"></div>
                                </button>
                                <button class="btn reject-request" onclick="handleRejectReturnRequest(this)">
                                    <span class="button-text">Reject</span>
                                    <div class="loading" style="display: none;"></div>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-notifications">
                    <i class="fa-regular fa-bell-slash"></i>
                    <p>No requests found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add CSRF token meta tag -->
    <meta name="csrf-token" content="<?= $_SESSION['csrf_token'] ?>">

    <script src="../js/AdminNotification.js"></script>
 
</body>
</html>
<?php


// Start session and set headers
session_start();
header('Content-Type: text/html; charset=UTF-8');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
require_once '../config/db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit();
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    
    header('Content-Type: application/json');
    $response = ['success' => false, 'message' => ''];

    try {
        // Validate input
        if (!isset($_POST['item_id'], $_POST['item_name'], $_POST['quantity'], $_POST['condition'], $_POST['notes'])) {
            throw new Exception('Missing required fields');
        }

        // Process return request
        $user_id = $_SESSION['user_id'];
        $item_id = (int)$_POST['item_id'];
        $item_name = trim($_POST['item_name']);
        $quantity = (int)$_POST['quantity'];
        $condition = in_array($_POST['condition'], ['Good', 'Damaged', 'Lost']) ? $_POST['condition'] : 'Good';
        $notes = trim($_POST['notes']);
        $category = 'General';

        // Validate notes length
        if (str_word_count($notes) > 10) {
            throw new Exception('Notes cannot exceed 10 words');
        }

        // Start transaction
        $conn->begin_transaction();

        // Insert return record
        $stmt = $conn->prepare("INSERT INTO item_returns 
                              (user_id, item_id, category, quantity, return_date, item_condition, notes, status) 
                              VALUES (?, ?, ?, ?, CURDATE(), ?, ?, 'Pending')");
        $stmt->bind_param("iiisss", $user_id, $item_id, $category, $quantity, $condition, $notes);
        
        if (!$stmt->execute()) {
            throw new Exception('Failed to create return record');
        }

        // Update borrow request status
        $updateStmt = $conn->prepare("UPDATE borrow_requests 
                                    SET status = 'Returned' 
                                    WHERE item_id = ? AND user_id = ? AND status = 'Approved'");
        $updateStmt->bind_param("ii", $item_id, $user_id);
        
        if (!$updateStmt->execute()) {
            throw new Exception('Failed to update borrow request');
        }

        // Commit transaction
        $conn->commit();

        $response = [
            'success' => true,
            'message' => 'Return request submitted successfully!'
        ];

    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = $e->getMessage();
    }

    echo json_encode($response);
    exit();
}

// Fetch approved borrow requests for the current user
$stmt = $conn->prepare("SELECT * FROM borrow_requests 
                       WHERE status = 'Approved' AND user_id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

// Fetch user details
$userStmt = $conn->prepare("SELECT username FROM users WHERE user_id = ?");
$userStmt->bind_param("i", $_SESSION['user_id']);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();
$accountName = $user['username'] ?? 'Guest';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="UCGS Inventory Management System - User Transactions">
    <title>UCGS Inventory | User Item Returned</title>
    <link rel="stylesheet" href="../css/UserItemReturned.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" 
          integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" 
          crossorigin="anonymous" referrerpolicy="no-referrer">
</head>

<body>
<header class="header">
    <div class="header-content">
        <div class="left-side">
            <img src="../assets/img/Logo.png" alt="UCGS Inventory Logo" class="logo">
            <span class="website-name">UCGS Inventory</span>
        </div>
        <div class="right-side">
            <div class="user">
                <img src="../assets/img/users.png" alt="User profile" class="icon" id="userIcon">
                <span class="user-text"><?php echo htmlspecialchars($accountName); ?></span>
                <div class="user-dropdown" id="userDropdown">
                    <a href="userprofile.php"><img src="../assets/img/updateuser.png" alt="Profile" class="dropdown-icon"> Profile</a>
                    <a href="usernotification.php"><img src="../assets/img/notificationbell.png" alt="Notification Icon" class="dropdown-icon"> Notification</a>
                    <a href="../login/logout.php"><img src="../assets/img/logout.png" alt="Logout" class="dropdown-icon"> Logout</a>
                </div>
            </div>
        </div>
    </div>
</header>

<aside class="sidebar">
    <ul>
        <li><a href="Userdashboard.php"><img src="../assets/img/dashboards.png" alt="Dashboard Icon" class="sidebar-icon"> Dashboard</a></li>
        <li><a href="UserItemRecords.php"><img src="../assets/img/list-items.png" alt="Items Icon" class="sidebar-icon"> Item Records</a></li>
        <li class="dropdown">
            <a href="#" class="dropdown-btn">
                <img src="../assets/img/request-for-proposal.png" alt="Request Icon" class="sidebar-icon">
                <span class="text">Request Record</span>
                 <svg class="arrow-icon" viewBox="0 0 448 512" width="1em" height="1em" fill="currentColor">
                  <path d="M207.029 381.476L12.686 187.132c-9.373-9.373-9.373-24.569 0-33.941l22.667-22.667c9.357-9.357 24.522-9.375 33.901-.04L224 284.505l154.745-154.021c9.379-9.335 24.544-9.317 33.901.04l22.667 22.667c9.373 9.373 9.373 24.569 0 33.941L240.971 381.476c-9.373 9.372-24.569 9.372-33.942 0z"/>
                </svg>
            </a>
            <ul class="dropdown-content">
                <li><a href="UserItemRequests.php">New Item Request</a></li>
                <li><a href="UserItemBorrow.php">Borrow Item Request</a></li>
                <li><a href="UserItemReturned.php">Return Item Request</a></li>
            </ul>
        </li>
        <li><a href="UserTransaction.php"><img src="../assets/img/time-management.png" alt="Reports Icon" class="sidebar-icon">Transaction Records</a></li>
    </ul>
</aside>

<div class="main-content">
    <div class="table-container">
        <h2>Returning Item</h2>
        <div class="filter-container">
            <input type="text" id="search-input" placeholder="Search..." oninput="searchTable()">
            <label for="start-date">Date Range:</label>
            <input type="date" id="start-date" onchange="filterByDate()">
            <label for="end-date">To:</label>
            <input type="date" id="end-date" onchange="filterByDate()">
        </div>

        <table class="inventory-table">
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th>Quantity</th>
                    <th>Request Date</th>
                    <th>Return Date</th>
                    <th>Condition</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
    <?php while ($row = mysqli_fetch_assoc($result)): ?>
    <tr data-item-id="<?php echo $row['item_id']; ?>" data-item-name="<?php echo htmlspecialchars($row['item_name']); ?>">
        <td><?php echo htmlspecialchars($row['item_name']); ?></td>
        <td><?php echo htmlspecialchars($row['quantity']); ?></td>
        <td><?php echo htmlspecialchars($row['request_date']); ?></td>
        <td><?php echo htmlspecialchars($row['return_date']); ?></td>
        <td>
            <select class="condition-dropdown" onchange="handleConditionChange(this)">
                <option value="Good">Good</option>
                <option value="Damaged">Damaged</option>
                <option value="Lost">Lost</option>
                <option value="Other">Other (specify)</option>
            </select>
            <input type="text" class="condition-input" style="display: none; margin-top: 5px;" 
                   placeholder="Specify condition" oninput="updateCondition(this)">
        </td>
        <td><button class="return-item-btn">Return Item</button></td>
    </tr>
    <?php endwhile; ?>
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
            <textarea id="rejectionReason" rows="4" placeholder="Enter reason..."></textarea>
            <p id="error-message" style="color: red; font-size: 14px; margin-top: 5px;"></p>
            <div class="modal-buttons">
                <button id="confirmReject" class="confirm-btn">Confirm</button>
                <button id="cancelReject" class="cancel-btn">Cancel</button>
            </div>
        </div>
    </div>


<!-- Return Item Modal -->
<div id="returnModal" class="return-modal">
            <div class="return-modal-content">
                <span class="close">&times;</span>
                <h3>Return Item</h3>
                <form id="returnForm">
                    <input type="hidden" id="return_item_id" name="item_id">
                    <input type="hidden" id="return_item_name" name="item_name">
                    <input type="hidden" id="return_quantity" name="quantity">
                    <input type="hidden" id="return_condition" name="condition">
                    <!-- Update the modal label in your HTML -->
                    <div class="form-group">
                        <label for="return_notes">Notes (max 10 words):</label>
                        <textarea id="return_notes" name="notes" rows="4" placeholder="Enter any notes about the return..."></textarea>
                        <p id="word-count">0/10 words</p>
                        <p id="notes-error" style="color: red; display: none;">Please limit your notes to 10 words.</p>
                    </div>
                    <div class="modal-buttons">
                        <button type="submit" name="return_item" class="confirm-btn">Submit Return</button>
                        <button type="button" id="cancelReturn" class="cancel-btn">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<script src="../js/usersreturns.js"></script>


</body>
</html>
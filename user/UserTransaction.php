<?php
include '../config/db_connection.php';
session_start();

// Verify User session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'User') {
    header("Location: ../login/login.php");
    exit();
}

function getCurrentUser($conn) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'User') {
        header("Location: ../login/login.php");
        exit();
    }

    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT username, email, user_id FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        // Handle case where user is not found in the database
        header("Location: ../login/login.php");
        exit();
    }

    return $user;
}

$currentUser = getCurrentUser($conn);
$accountName = htmlspecialchars($currentUser['username'] ?? 'User');
$currentUserId = $currentUser['user_id']; // Ensure this is properly set

// Replace the table check with this:
$requiredTables = ['borrow_requests', 'new_item_requests', 'return_requests', 'items'];
foreach ($requiredTables as $table) {
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($check->num_rows === 0) {
        die("Error: Required table '$table' does not exist in the database.");
    }
}

// Pagination setup
$rowsPerPage = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max($page, 1); // Ensure page is at least 1
$offset = ($page - 1) * $rowsPerPage;

// Fetch borrow requests for the logged-in user
$borrowRequestsQuery = "
    SELECT 
        borrow_requests.item_id, 
        items.item_name, 
        borrow_requests.quantity, 
        borrow_requests.date_needed, 
        borrow_requests.return_date, 
        borrow_requests.purpose, 
        borrow_requests.notes, 
        borrow_requests.status 
    FROM borrow_requests 
    JOIN items ON borrow_requests.item_id = items.item_id 
    WHERE borrow_requests.user_id = ?
    LIMIT ? OFFSET ?
";
$borrowRequestsStmt = $conn->prepare($borrowRequestsQuery);
$borrowRequestsStmt->bind_param("iii", $currentUserId, $rowsPerPage, $offset);
$borrowRequestsStmt->execute();
$borrowRequestsResult = $borrowRequestsStmt->get_result();
$borrowRequestsStmt->close();

// Fetch new item requests for the logged-in user
$newItemRequestsQuery = "
    SELECT 
        new_item_requests.item_name, 
        new_item_requests.quantity, 
        new_item_requests.request_date AS date_requested, 
        new_item_requests.status 
    FROM new_item_requests 
    WHERE new_item_requests.user_id = ?
    LIMIT ? OFFSET ?
";
$newItemRequestsStmt = $conn->prepare($newItemRequestsQuery);
$newItemRequestsStmt->bind_param("iii", $currentUserId, $rowsPerPage, $offset);
$newItemRequestsStmt->execute();
$newItemRequestsResult = $newItemRequestsStmt->get_result();
$newItemRequestsStmt->close();

// Fetch returned item requests for the logged-in user
$returnedRequestsQuery = "
    SELECT 
        items.item_name, 
        return_requests.quantity, 
        return_requests.return_date, 
        return_requests.status 
    FROM return_requests 
    JOIN borrow_requests ON return_requests.borrow_id = borrow_requests.borrow_id
    JOIN items ON borrow_requests.item_id = items.item_id 
    WHERE borrow_requests.user_id = ?
    LIMIT ? OFFSET ?
";
$returnedRequestsStmt = $conn->prepare($returnedRequestsQuery);
$returnedRequestsStmt->bind_param("iii", $currentUserId, $rowsPerPage, $offset);
$returnedRequestsStmt->execute();
$returnedRequestsResult = $returnedRequestsStmt->get_result();
$returnedRequestsStmt->close();

// Calculate total pages for pagination
$totalRowsQuery = "
    SELECT 
        (SELECT COUNT(*) FROM borrow_requests WHERE user_id = ?) +
        (SELECT COUNT(*) FROM new_item_requests WHERE user_id = ?) +
        (SELECT COUNT(*) FROM return_requests 
         JOIN borrow_requests ON return_requests.borrow_id = borrow_requests.borrow_id 
         WHERE borrow_requests.user_id = ?) AS total_rows
";
$totalRowsStmt = $conn->prepare($totalRowsQuery);
$totalRowsStmt->bind_param("iii", $currentUserId, $currentUserId, $currentUserId);
$totalRowsStmt->execute();
$totalRowsResult = $totalRowsStmt->get_result();
$totalRows = $totalRowsResult->fetch_assoc()['total_rows'];
$totalPages = ceil($totalRows / $rowsPerPage);
$totalRowsStmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"="width=device-width, initial-scale=1.0">
    <meta name="description" content="UCGS Inventory Management System - User Transactions">
    <title>UCGS Inventory | User Transactions</title>
    <link rel="stylesheet" href="../css/UserTransaction.css">
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

<main class="main-content">
    <h1>User Transactions</h1>
    <table class="transaction-table">
        <thead>
            <tr>
                <th>Request Type</th>
                <th>Item Name</th>
                <th>Quantity</th>
                <th>Date</th>
                <th>Additional Info</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($request = $borrowRequestsResult->fetch_assoc()): ?>
                <tr>
                    <td>Borrow</td>
                    <td><?php echo htmlspecialchars($request['item_name']); ?></td>
                    <td><?php echo htmlspecialchars($request['quantity']); ?></td>
                    <td><?php echo htmlspecialchars($request['date_needed']); ?></td>
                    <td>Return Date: <?php echo htmlspecialchars($request['return_date']); ?><br>Purpose: <?php echo htmlspecialchars($request['purpose']); ?><br>Notes: <?php echo htmlspecialchars($request['notes']); ?></td>
                    <td><?php echo htmlspecialchars($request['status']); ?></td>
                </tr>
            <?php endwhile; ?>

            <?php while ($newRequest = $newItemRequestsResult->fetch_assoc()): ?>
                <tr>
                    <td>New Item</td>
                    <td><?php echo htmlspecialchars($newRequest['item_name']); ?></td>
                    <td><?php echo htmlspecialchars($newRequest['quantity']); ?></td>
                    <td><?php echo htmlspecialchars($newRequest['date_requested']); ?></td>
                    <td>--</td>
                    <td><?php echo htmlspecialchars($newRequest['status']); ?></td>
                </tr>
            <?php endwhile; ?>

            <?php while ($returnedRequest = $returnedRequestsResult->fetch_assoc()): ?>
                <tr>
                    <td>Returned</td>
                    <td><?php echo htmlspecialchars($returnedRequest['item_name']); ?></td>
                    <td><?php echo htmlspecialchars($returnedRequest['quantity']); ?></td>
                    <td><?php echo htmlspecialchars($returnedRequest['date_returned']); ?></td>
                    <td>--</td>
                    <td><?php echo htmlspecialchars($returnedRequest['status']); ?></td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <div class="pagination">
        <button onclick="changePage(<?php echo max(1, $page - 1); ?>)" <?php echo ($page <= 1) ? 'disabled' : ''; ?>>Previous</button>
        <span>Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
        <button onclick="changePage(<?php echo min($totalPages, $page + 1); ?>)" <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>>Next</button>
    </div>
</div>


<script src="../js/UsersTransaction.js"></script>
<script>

</script>
</body>
</html>
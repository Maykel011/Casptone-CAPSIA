<?php
include '../config/db_connection.php';
session_start();

// Verify admin session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrator') {
    header("Location: ../login/login.php");
    exit();
}

// Fetch admin details
$currentAdminId = intval($_SESSION['user_id']);
$stmt = $conn->prepare("SELECT username, email, role FROM users WHERE user_id = ?");
$stmt->bind_param("i", $currentAdminId);
$stmt->execute();
$result = $stmt->get_result();
$currentAdmin = $result->fetch_assoc();
$stmt->close();

$accountName = htmlspecialchars($currentAdmin['username'] ?? 'User');
$accountEmail = htmlspecialchars($currentAdmin['email'] ?? '');
$accountRole = htmlspecialchars($currentAdmin['role'] ?? '');

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['action']) || !isset($input['return_id'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit();
    }

    $returnId = intval($input['return_id']);
    $action = $input['action'];
    $notes = isset($input['notes']) ? $conn->real_escape_string($input['notes']) : '';

    try {
        $conn->begin_transaction();
        
        if ($action === 'approve') {
            // Update inventory quantity if approved
            $stmt = $conn->prepare("UPDATE items i 
                                  JOIN item_returns ir ON i.item_id = ir.item_id
                                  SET i.quantity = i.quantity + ir.quantity 
                                  WHERE ir.return_id = ?");
            $stmt->bind_param("i", $returnId);
            $stmt->execute();
            $stmt->close();
            
            // Mark as approved
            $stmt = $conn->prepare("UPDATE item_returns SET status = 'Approved', notes = ? WHERE return_id = ?");
        } elseif ($action === 'reject') {
            // For rejection, update condition and status
            $stmt = $conn->prepare("UPDATE item_returns SET status = 'Rejected', item_condition = 'Damaged', notes = ? WHERE return_id = ?");
        } else {
            throw new Exception("Invalid action");
        }
        
        $stmt->bind_param("si", $notes, $returnId);
        $stmt->execute();
        $stmt->close();
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Action completed successfully']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Failed to process request: ' . $e->getMessage()]);
    }
    exit();
}

// Fetch returned items with status
$query = "SELECT ir.return_id, ir.return_date, i.item_name, ir.quantity, 
                 ir.item_condition, ir.notes, ir.created_at, u.username, ir.status
          FROM item_returns ir
          JOIN items i ON ir.item_id = i.item_id
          JOIN users u ON ir.user_id = u.user_id
          ORDER BY ir.created_at DESC";

$result = $conn->query($query);
if (!$result) {
    die("Error fetching returned items: " . htmlspecialchars($conn->error));
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UCGS Inventory | Item Returns</title>
    <link rel="stylesheet" href="../css/AdminItmReturned.css">
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
                    <span class="admin-text"><?php echo $accountName; ?> (<?php echo $accountRole; ?>)</span>
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
            <li><a href="ItemRecords.php"><img src="../assets/img/list-items.png" alt="Items Icon" class="sidebar-icon">Item Records</a></li>
            <li class="dropdown">
                <a href="#" class="dropdown-btn">
                    <img src="../assets/img/request-for-proposal.png" alt="Request Icon" class="sidebar-icon">
                    <span class="text">Request Record</span>
                    <svg class="arrow-icon" viewBox="0 0 448 512" width="1em" height="1em" fill="currentColor">
                    <path d="M207.029 381.476L12.686 187.132c-9.373-9.373-9.373-24.569 0-33.941l22.667-22.667c9.357-9.357 24.522-9.375 33.901-.04L224 284.505l154.745-154.021c9.379-9.335 24.544-9.317 33.901.04l22.667 22.667c9.373 9.373 9.373 24.569 0 33.941L240.971 381.476c-9.373 9.372-24.569 9.372-33.942 0z"/></svg>

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
        <div class="table-container">
            <h2>Item Returned</h2>
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
                        <th>Username</th>
                        <th>Item Name</th>
                        <th>Return Date</th>
                        <th>Quantity</th>
                        <th>Condition</th>
                        <th>Notes</th>
                        <th>Request Date</th>
                  
                    </tr>
                </thead>
                <tbody id="item-table-body">
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr data-return-id="<?php echo $row['return_id']; ?>">
                            <td><?php echo htmlspecialchars($row['username']); ?></td>
                            <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['return_date']); ?></td>
                            <td><?php echo htmlspecialchars($row['quantity']); ?></td>
                            <td><?php echo htmlspecialchars($row['item_condition']); ?></td>
                            <td><?php echo htmlspecialchars($row['notes']); ?></td>
                            <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                           
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

        <script src="../js/ItReturned.js"></script>
        <script>
  // Profile Dropdown
  document.addEventListener("DOMContentLoaded", function () {
    const userIcon = document.getElementById("userIcon");
    const userDropdown = document.getElementById("userDropdown");

    userIcon.addEventListener("click", function (event) {
        event.stopPropagation(); // Prevent closing when clicking inside
        userDropdown.classList.toggle("show");
    });

    // Close dropdown when clicking outside
    document.addEventListener("click", function (event) {
        if (!userIcon.contains(event.target) && !userDropdown.contains(event.target)) {
            userDropdown.classList.remove("show");
        }
    });
});
        </script>
</body>
</html>
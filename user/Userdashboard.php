<?php
// Use a secure session configuration
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_start();
session_regenerate_id(true);

// Redirect unauthorized access
if (empty($_SESSION['user_id']) || $_SESSION['role'] !== 'User') {
    header("Location: ../login/login.php");
    exit();
}

include '../config/db_connection.php';


// Sanitize and validate user ID
$userId = filter_var($_SESSION['user_id'], FILTER_VALIDATE_INT);

// Fetch user data securely
function getCurrentUser($conn) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'User') {
        header("Location: ../login/login.php");
        exit();
    }

    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT username, email, role, ministry, status FROM users WHERE user_id = ?");
    if (!$stmt) {
        error_log("Database query error: " . $conn->error);
        header("Location: ../error.php");
        exit();
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user || $user['status'] !== 'Active') {
        session_destroy();
        header("Location: ../login/login.php?error=account_inactive");
        exit();
    }

    return $user;
}

$currentUser = getCurrentUser($conn);
$accountName = htmlspecialchars($currentUser['username']);
$email = htmlspecialchars($currentUser['email']);
$role = htmlspecialchars($currentUser['role']);
$ministry = htmlspecialchars($currentUser['ministry']);
$status = htmlspecialchars($currentUser['status']);

function getPendingRequestsCount($mysqli, $userId) {
    $count = 0; // Initialize count
    $query = "SELECT 
                (SELECT COUNT(*) FROM borrow_requests WHERE user_id = ? AND status = 'Pending') +
                (SELECT COUNT(*) FROM new_item_requests WHERE user_id = ? AND status = 'Pending') +
                (SELECT COUNT(*) FROM return_requests 
                 JOIN borrow_requests ON return_requests.borrow_id = borrow_requests.borrow_id 
                 WHERE borrow_requests.user_id = ? AND return_requests.status = 'Pending') AS total_pending";
    
    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        error_log("Query preparation failed: " . $mysqli->error);
        return 0;
    }
    $stmt->bind_param("iii", $userId, $userId, $userId);
    if (!$stmt->execute()) {
        error_log("Query execution failed: " . $stmt->error);
        $stmt->close();
        return 0;
    }
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count ? $count : 0; // Ensure we return 0 if null
}

function getBorrowedItemsCount($mysqli, $userId) {
    $count = 0;
    $stmt = $mysqli->prepare("SELECT COUNT(*) 
                             FROM borrowed_items 
                             WHERE user_id = ? 
                             AND status = 'Borrowed'");
    if (!$stmt) {
        error_log("Query preparation failed: " . $mysqli->error);
        return 0;
    }
    $stmt->bind_param("i", $userId);
    if (!$stmt->execute()) {
        error_log("Query execution failed: " . $stmt->error);
        $stmt->close();
        return 0;
    }
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count ? $count : 0;
}

function getRecentTransactionsCount($mysqli, $userId) {
    $count = 0;
    $stmt = $mysqli->prepare("SELECT COUNT(*) FROM transactions 
                             WHERE user_id = ? 
                             AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
    if (!$stmt) {
        error_log("Query preparation failed: " . $mysqli->error);
        return 0;
    }
    $stmt->bind_param("i", $userId);
    if (!$stmt->execute()) {
        error_log("Query execution failed: " . $stmt->error);
        $stmt->close();
        return 0;
    }
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count ? $count : 0;
}

function getTotalItemsCount($mysqli) {
    $count = 0;
    $stmt = $mysqli->prepare("SELECT COUNT(*) FROM items WHERE status = 'Available'");
    if (!$stmt) {
        error_log("Query preparation failed: " . $mysqli->error);
        return 0;
    }
    if (!$stmt->execute()) {
        error_log("Query execution failed: " . $stmt->error);
        $stmt->close();
        return 0;
    }
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return $count ? $count : 0;
}
// Fetch user notifications
function getUserNotifications($mysqli, $userId) {
    $stmt = $mysqli->prepare("SELECT notification_id, message, created_at 
                             FROM notifications 
                             WHERE user_id = ? 
                             ORDER BY created_at DESC 
                             LIMIT 5");
    if (!$stmt) {
        error_log("Query preparation failed: " . $mysqli->error);
        return [];
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    $stmt->close();
    return $notifications;
}

// Fetch dashboard data
$totalItems = getTotalItemsCount($conn);
$notifications = getUserNotifications($conn, $userId);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - UCGS Inventory</title>
    <link rel="stylesheet" href="../css/userdb.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
    <h4 class="overview-title">OVERVIEW</h4>
    
    <div class="dashboard-overview">
        <div class="card gradient-yellow">
            <i class="fa-solid fa-clock"></i>
            <h2>Pending Requests</h2>
            <p><?= htmlspecialchars(getPendingRequestsCount($conn, $userId)) ?></p>
            <canvas class="chart-container"></canvas>
        </div>
        <div class="card gradient-green">
            <i class="fa-solid fa-box-open"></i>
            <h2>Borrowed Items</h2>
            <p><?= htmlspecialchars(getBorrowedItemsCount($conn, $userId)) ?></p>
            <canvas class="chart-container"></canvas>
        </div>
        <div class="card gradient-purple">
            <i class="fa-solid fa-exchange-alt"></i>
            <h2>Recent Transactions</h2>
            <p><?= htmlspecialchars(getRecentTransactionsCount($conn, $userId)) ?></p>
            <canvas class="chart-container"></canvas>
        </div>
        <div class="card gradient-orange">
            <i class="fa-solid fa-cubes"></i>
            <h2>Total Items</h2>
            <p><?= htmlspecialchars($totalItems) ?></p>
            <canvas class="chart-container"></canvas>
        </div>
    </div>

    <div class="tables-section">
        <div class="table-container">
            <h2>Pending Requests</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Request Type</th>
                        <th>Item Name</th>
                        <th>Request Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query = "(SELECT 'Borrow' AS request_type, 
                                     items.item_name, 
                                     borrow_requests.request_date, 
                                     borrow_requests.status 
                              FROM borrow_requests 
                              JOIN items ON borrow_requests.item_id = items.item_id 
                              WHERE borrow_requests.user_id = ? AND borrow_requests.status = 'Pending')
                              
                              UNION ALL
                              
                              (SELECT 'New Item' AS request_type, 
                                      item_name, 
                                      request_date, 
                                      status 
                               FROM new_item_requests 
                               WHERE user_id = ? AND status = 'Pending')
                              
                              UNION ALL
                              
                              (SELECT 'Return' AS request_type, 
                                      items.item_name, 
                                      return_requests.created_at AS request_date, 
                                      return_requests.status 
                               FROM return_requests 
                               JOIN borrow_requests ON return_requests.borrow_id = borrow_requests.borrow_id 
                               JOIN items ON borrow_requests.item_id = items.item_id 
                               WHERE borrow_requests.user_id = ? AND return_requests.status = 'Pending')
                              
                              ORDER BY request_date DESC
                              LIMIT 10";
                    
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("iii", $userId, $userId, $userId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>
                                <td>" . htmlspecialchars($row['request_type']) . "</td>
                                <td>" . htmlspecialchars($row['item_name']) . "</td>
                                <td>" . htmlspecialchars($row['request_date']) . "</td>
                                <td>" . htmlspecialchars($row['status']) . "</td>
                              </tr>";
                    }
                    $stmt->close();
                    ?>
                </tbody>
            </table>
        </div>
        
        <div class="table-container">
            <h2>Borrowed Items</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Borrow ID</th>
                        <th>Item Name</th>
                        <th>Borrow Date</th>
                        <th>Expected Return</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $conn->prepare("SELECT bi.borrow_id, 
                                                  i.item_name, 
                                                  bi.borrow_date, 
                                                  br.return_date AS expected_return
                                           FROM borrowed_items bi
                                           JOIN borrow_requests br ON bi.request_id = br.borrow_id
                                           JOIN items i ON bi.item_id = i.item_id
                                           WHERE bi.user_id = ? 
                                           AND bi.status = 'Borrowed'
                                           LIMIT 10");
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>
                                <td>" . htmlspecialchars($row['borrow_id']) . "</td>
                                <td>" . htmlspecialchars($row['item_name']) . "</td>
                                <td>" . htmlspecialchars($row['borrow_date']) . "</td>
                                <td>" . htmlspecialchars($row['expected_return']) . "</td>
                              </tr>";
                    }
                    $stmt->close();
                    ?>
                </tbody>
            </table>
        </div>
        
        <div class="table-container">
            <h2>Recent Transactions</h2>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Transaction ID</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $stmt = $conn->prepare("SELECT transaction_id, 
                                                  action, 
                                                  details, 
                                                  created_at 
                                           FROM transactions 
                                           WHERE user_id = ? 
                                           ORDER BY created_at DESC 
                                           LIMIT 10");
                    $stmt->bind_param("i", $userId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    while ($row = $result->fetch_assoc()) {
                        echo "<tr>
                                <td>" . htmlspecialchars($row['transaction_id']) . "</td>
                                <td>" . htmlspecialchars($row['action']) . "</td>
                                <td>" . htmlspecialchars($row['details']) . "</td>
                                <td>" . htmlspecialchars($row['created_at']) . "</td>
                              </tr>";
                    }
                    $stmt->close();
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</main>
    <script src="../js/usersdash.js"></script>
    <script>
        // Sidebar dropdown functionality
        document.querySelectorAll('.dropdown-btn').forEach(button => {
            button.addEventListener('click', () => {
                const dropdownContent = button.nextElementSibling;
                dropdownContent.style.display = dropdownContent.style.display === 'block' ? 'none' : 'block';
            });
        });

        // Profile dropdown functionality
        const userIcon = document.getElementById('userIcon');
        const userDropdown = document.getElementById('userDropdown');
        userIcon.addEventListener('click', () => {
            userDropdown.style.display = userDropdown.style.display === 'block' ? 'none' : 'block';
        });

        // Close dropdown if clicked outside
        document.addEventListener('click', (event) => {
            if (!userIcon.contains(event.target) && !userDropdown.contains(event.target)) {
                userDropdown.style.display = 'none';
            }
        });
    </script>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>
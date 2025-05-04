<?php
include '../config/db_connection.php';
session_start();

// Verify User session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'User') {
    header("Location: ../login/login.php");
    exit();
}

// Fetch current user details
function getCurrentUser($conn) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'User') {
        header("Location: ../login/login.php");
        exit();
    }

    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT username, email FROM users WHERE user_id = ?");
    if (!$stmt) {
        error_log("Database error: " . $conn->error);
        die("An error occurred. Please try again later.");
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    return $user ?: ['username' => 'User', 'email' => ''];
}

$currentUser = getCurrentUser($conn);
$accountName = htmlspecialchars($currentUser['username'] ?? 'User');
$accountEmail = htmlspecialchars($currentUser['email'] ?? '');

// Get filter parameters
$searchTerm = $_GET['search'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';

// Pagination logic
$itemsPerPage = 10;
$page = isset($_GET['page']) && filter_var($_GET['page'], FILTER_VALIDATE_INT) ? (int)$_GET['page'] : 1;
$page = max($page, 1);

// Base query for counting and fetching
$baseQuery = "FROM items WHERE deleted_at IS NULL";
$whereClauses = [];
$params = [];
$types = '';

// Add search filter
if (!empty($searchTerm)) {
    $whereClauses[] = "item_name LIKE ?";
    $params[] = "%$searchTerm%";
    $types .= 's';
}

// Add status filter
if (!empty($statusFilter)) {
    $whereClauses[] = "status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

// Add category filter
if (!empty($categoryFilter)) {
    $whereClauses[] = "item_category = ?";
    $params[] = $categoryFilter;
    $types .= 's';
}

// Add date range filter
if (!empty($startDate) && !empty($endDate)) {
    $whereClauses[] = "created_at BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
    $types .= 'ss';
}

// Build complete WHERE clause
if (!empty($whereClauses)) {
    $baseQuery .= " AND " . implode(" AND ", $whereClauses);
}

// Count total items with filters
$totalItemsQuery = "SELECT COUNT(*) as total $baseQuery";
$stmt = $conn->prepare($totalItemsQuery);
if (!$stmt) {
    error_log("Database error: " . $conn->error);
    die("An error occurred. Please try again later.");
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$totalItemsResult = $stmt->get_result();
$totalItemsRow = $totalItemsResult->fetch_assoc();
$totalItems = $totalItemsRow['total'] ?? 0;
$stmt->close();

// Calculate total pages
$totalPages = ceil($totalItems / $itemsPerPage);

// Ensure the current page is not greater than total pages
if ($page > $totalPages) {
    $page = $totalPages > 0 ? $totalPages : 1;
}

// Calculate offset for pagination
$offset = ($page - 1) * $itemsPerPage;

// Fetch items for the current page with filters
$itemsQuery = "SELECT 
    item_name, 
    description, 
    quantity,
    availability,
    unit, 
    CASE 
        WHEN availability = 0 THEN 'Out of Stock'
        WHEN availability <= 5 THEN 'Low Stock'
        ELSE 'Available'
    END as status,
    created_at, 
    last_updated, 
    model_no, 
    item_category, 
    item_location 
    $baseQuery
    ORDER BY last_updated DESC
    LIMIT ?, ?";
$stmt = $conn->prepare($itemsQuery);
if (!$stmt) {
    error_log("Database error: " . $conn->error);
    die("An error occurred. Please try again later.");
}

// Bind parameters for the query
if (!empty($params)) {
    // Combine all parameters (filters + pagination)
    $allParams = array_merge($params, [$offset, $itemsPerPage]);
    // Combine all parameter types
    $allTypes = $types . 'ii';
    // Bind them
    $stmt->bind_param($allTypes, ...$allParams);
} else {
    // Just bind pagination parameters
    $stmt->bind_param("ii", $offset, $itemsPerPage);
}

$stmt->execute();
$result = $stmt->get_result();
$items = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UCGS Inventory | Item Records</title>
    <link rel="stylesheet" href="../css/UserItmRecords.css">
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

        <div class="main-content">
        
        <div class="table-container">
            <h2>Item Records</h2>
            <div class="filter-container">
    <div class="left-filters">
        <form id="filter-form" method="GET" action="UserItemRecords.php">
            <div class="top-filters">
                <div class="form-group search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="search-box" name="search" placeholder="Search by Item Name..." 
                           value="<?php echo htmlspecialchars($searchTerm); ?>" autocomplete="off">  <br>

                <div class="form-group">
                    <select class="filter-select" name="status" id="status-filter">
                        <option value="">All Statuses</option>
                        <option value="Available" <?php echo $statusFilter === 'Available' ? 'selected' : ''; ?>>Available</option>
                        <option value="Out of Stock" <?php echo $statusFilter === 'Out of Stock' ? 'selected' : ''; ?>>Out of Stock</option>
                        <option value="Low Stock" <?php echo $statusFilter === 'Low Stock' ? 'selected' : ''; ?>>Low Stock</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <select class="filter-select" name="category" id="category-filter">
                        <option value="">All Categories</option>
                        <option value="electronics" <?php echo $categoryFilter === 'electronics' ? 'selected' : ''; ?>>Electronics</option>
                        <option value="stationary" <?php echo $categoryFilter === 'stationary' ? 'selected' : ''; ?>>Stationary</option>
                        <option value="furniture" <?php echo $categoryFilter === 'furniture' ? 'selected' : ''; ?>>Furniture</option>
                        <option value="accessories" <?php echo $categoryFilter === 'accessories' ? 'selected' : ''; ?>>Accessories</option>
                        <option value="consumables" <?php echo $categoryFilter === 'consumables' ? 'selected' : ''; ?>>Consumables</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <button type="button" id="clear-filters-btn" class="clear-filters-btn">
                        <i class="fas fa-times"></i> Clear Filters
                    </button>
                </div>
            </div>
            </div>

            
            <input type="hidden" name="page" id="page-input" value="1">
        </form>
    </div>

            <table class="item-table">
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Description</th>
                        <th>Quantity</th>
                        <th>Availability</th>
                        <th>Unit</th>
                        <th>Status</th>
                        <th>Model No</th>
                        <th>Item Category</th>
                        <th>Item Location</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($items)): ?>
                        <?php foreach ($items as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['item_name']); ?></td>
                                <td><?= htmlspecialchars($row['description']); ?></td>
                                <td><?= htmlspecialchars($row['quantity']); ?></td>
                                <td><?= htmlspecialchars($row['availability']); ?></td>
                                <td><?= htmlspecialchars($row['unit']); ?></td>
                                <td>
                                    <span class="status-badge status-<?php 
                                        echo strtolower(str_replace(' ', '-', htmlspecialchars($row['status']))); 
                                    ?>">
                                        <?php echo htmlspecialchars($row['status']); ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($row['model_no']); ?></td>
                                <td><?= htmlspecialchars($row['item_category']); ?></td>
                                <td><?= htmlspecialchars($row['item_location']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" style="text-align: center;">No records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
        </div>
                    <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <button onclick="changePage(<?php echo max(1, $page - 1); ?>)" <?php echo ($page <= 1) ? 'disabled' : ''; ?>>Previous</button>
                    <span>Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                    <button onclick="changePage(<?php echo min($totalPages, $page + 1); ?>)" <?php echo ($page >= $totalPages) ? 'disabled' : ''; ?>>Next</button>
                </div>
            <?php endif; ?>
    </div>  

<script src="../js/UserItemsRecords.js"></script>
</body>
</html>
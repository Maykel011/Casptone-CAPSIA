<?php
session_start();
include '../config/db_connection.php';

// Enhanced session validation
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit();
}

// Add this near the top of your PHP file, after the session checks but before any output
if (isset($_GET['item_id']) && is_numeric($_GET['item_id']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $itemId = intval($_GET['item_id']);
    
    try {
        $stmt = $conn->prepare("SELECT * FROM items WHERE item_id = ? AND deleted_at IS NULL");
        $stmt->bind_param("i", $itemId);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        
        if ($item) {
            // Convert MySQL date to proper format
            if ($item['expiration'] && $item['expiration'] !== '0000-00-00') {
                $item['expiration'] = date('Y-m-d', strtotime($item['expiration']));
            } else {
                $item['expiration'] = null;
            }
            
            header('Content-Type: application/json');
            echo json_encode($item);
            exit();
        }
    } catch (Exception $e) {
        error_log("Error fetching item: " . $e->getMessage());
        header("HTTP/1.1 500 Internal Server Error");
        echo json_encode(['error' => 'Failed to fetch item data']);
        exit();
    }
    
    // If item not found
    header("HTTP/1.1 404 Not Found");
    echo json_encode(['error' => 'Item not found']);
    exit();
}

// Function to get logged in user with error handling
function getLoggedInUser($conn) {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    try {
        $userId = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        return $user;
    } catch (Exception $e) {
        error_log("Error fetching user: " . $e->getMessage());
        return null;
    }
}

// Get current admin details with role validation
$loggedInUser = getLoggedInUser($conn);
if (!$loggedInUser || $loggedInUser['role'] !== 'Administrator') {
    header("Location: ../login/login.php");
    exit();
}

$accountName = htmlspecialchars($loggedInUser['username']);
$accountRole = htmlspecialchars($loggedInUser['role']);
$accountEmail = htmlspecialchars($loggedInUser['email'] ?? '');

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Define allowed values for status
    $allowedStatuses = ['Available', 'Out of Stock', 'Low Stock'];
    
    if (isset($_POST['create-item'])) {
        // Sanitize and validate input
        $itemName = trim($_POST['item_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $quantity = intval($_POST['quantity'] ?? 0);
        $availability = $quantity;
        $unit = in_array($_POST['unit'] ?? '', ['pcs', 'bx', 'pr', 'bdl']) ? $_POST['unit'] : 'pcs';
        $status = 'Available'; // Default
        if ($availability === 0) {
            $status = 'Out of Stock';
        } elseif ($availability <= 5) {
            $status = 'Low Stock';
        }
        
        $modelNo = trim($_POST['model_no'] ?? '');
        $itemCategory = in_array($_POST['item_category'] ?? '', ['electronics', 'stationary', 'furniture', 'accessories', 'consumables']) 
            ? $_POST['item_category'] 
            : '';
        $itemLocation = trim($_POST['item_location'] ?? '');
        $expiration = !empty($_POST['expiration']) ? $_POST['expiration'] : null;
        $createdBy = $loggedInUser['user_id'];
        
        // Validate required fields
        if (empty($itemName) || $quantity < 0 || empty($unit) || empty($status) || empty($itemCategory) || empty($modelNo)) {
            echo json_encode(['success' => false, 'message' => 'Item name, model number, quantity, status, and category are required.']);
            exit();
        }
    
        // Additional validation for model number format
        if (!preg_match('/^[A-Za-z0-9\-_]+$/', $modelNo)) {
            echo json_encode(['success' => false, 'message' => 'Model number can only contain letters, numbers, hyphens, and underscores.']);
            exit();
        }
    
        try {
            $itemNo = 'ITEM-' . uniqid();
            
             // In the INSERT statement, include availability
    $stmt = $conn->prepare("INSERT INTO items (
        item_no, item_name, description, quantity, availability, unit, status, 
        model_no, item_category, item_location, 
        expiration, created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->bind_param(
                "sssiissssssi", 
                $itemNo, $itemName, $description, $quantity, $availability, $unit, $status,
                $modelNo, $itemCategory, $itemLocation,
                $expiration, $createdBy
            );
        
    
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Item created successfully.', 'item_id' => $stmt->insert_id]);
            } else {
                error_log("Database error: " . $conn->error);
                throw new Exception("Database operation failed");
            }
        } catch (Exception $e) {
            error_log("Error creating item: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to create item. Please check the model number format.']);
        } finally {
            if (isset($stmt)) $stmt->close();
        }
        exit();
    }

    if (isset($_POST['update-item'])) {
        // Sanitize and validate input
        $itemId = intval($_POST['item_id'] ?? 0);
        $itemName = trim($_POST['item_name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $quantity = intval($_POST['quantity'] ?? 0);
        $availability = intval($_POST['availability'] ?? $quantity); // Keep existing availability if not provided
        $unit = in_array($_POST['unit'] ?? '', ['pcs', 'bx', 'pr', 'bdl']) ? $_POST['unit'] : 'pcs';
        $status = 'Available'; // Default
        if ($availability === 0) {
            $status = 'Out of Stock';
        } elseif ($availability <= 5) {
            $status = 'Low Stock';
        }
        $modelNo = trim($_POST['model_no'] ?? '');
        $itemCategory = in_array($_POST['item_category'] ?? '', ['electronics', 'stationary', 'furniture', 'accessories', 'consumables']) 
            ? $_POST['item_category'] 
            : '';
        $itemLocation = trim($_POST['item_location'] ?? '');
        $expiration = !empty($_POST['expiration']) ? $_POST['expiration'] : null;
    
        // Debug: Log received data
        error_log("Update data received: " . print_r($_POST, true));
        
        try {
                // In the UPDATE statement, include availability
    $stmt = $conn->prepare("UPDATE items SET 
    item_name = ?, description = ?, quantity = ?, availability = ?, status = ?,
    model_no = ?, item_category = ?, item_location = ?,
    unit = ?, expiration = ?, last_updated = NOW()
    WHERE item_id = ?");
    
            $stmt->bind_param(
                "ssiissssssi", 
                $itemName, $description, $quantity, $availability, $status,
                $modelNo, $itemCategory, $itemLocation,
                $unit, $expiration, $itemId
            );
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Item updated successfully.']);
            } else {
                error_log("Update error: " . $conn->error);
                throw new Exception($conn->error);
            }
        } catch (Exception $e) {
            error_log("Error updating item: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to update item.']);
        } finally {
            if (isset($stmt)) $stmt->close();
        }
        exit();
    }

    // Add this new handler for multiple deletions
    if (isset($_POST['delete-multiple-items'])) {
        if (empty($_POST['item_ids'])) {
            echo json_encode(['success' => false, 'message' => 'No items selected for deletion.']);
            exit();
        }
    
        try {
            // Convert item_ids to integers and sanitize
            $itemIds = array_map('intval', $_POST['item_ids']);
            $itemIds = array_filter($itemIds, function($id) { return $id > 0; });
            
            if (empty($itemIds)) {
                echo json_encode(['success' => false, 'message' => 'No valid items selected for deletion.']);
                exit();
            }
    
            // Create placeholders
            $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
            
            // Prepare the statement
            $stmt = $conn->prepare("UPDATE items SET deleted_at = NOW() WHERE item_id IN ($placeholders)");
            
            // Bind parameters dynamically
            $types = str_repeat('i', count($itemIds));
            $stmt->bind_param($types, ...$itemIds);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Selected items deleted successfully.']);
            } else {
                throw new Exception($conn->error);
            }
        } catch (Exception $e) {
            error_log("Error deleting multiple items: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to delete selected items.']);
        } finally {
            if (isset($stmt)) $stmt->close();
        }
        exit();
    }
}

// Pagination and filtering
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Initialize filter variables
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$categoryFilter = isset($_GET['category']) ? $_GET['category'] : '';

// Build the base query
$query = "SELECT SQL_CALC_FOUND_ROWS i.*, u.username as creator_name 
          FROM items i 
          LEFT JOIN users u ON i.created_by = u.user_id 
          WHERE i.deleted_at IS NULL";

// Add filters to the query
$whereClauses = [];
$params = [];
$types = '';

if (!empty($statusFilter)) {
    $whereClauses[] = "i.status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

if (!empty($categoryFilter)) {
    $whereClauses[] = "i.item_category = ?";
    $params[] = $categoryFilter;
    $types .= 's';
}

if (!empty($searchTerm)) {
    $whereClauses[] = "(i.item_name LIKE ? OR i.description LIKE ? OR i.model_no LIKE ?)";
    $searchParam = "%$searchTerm%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam]);
    $types .= 'sss';
}

if (!empty($startDate)) {
    $whereClauses[] = "i.last_updated >= ?";
    $params[] = $startDate;
    $types .= 's';
}

if (!empty($endDate)) {
    $whereClauses[] = "i.last_updated <= ?";
    $params[] = $endDate . ' 23:59:59'; // Include the entire end day
    $types .= 's';
}

if (!empty($whereClauses)) {
    $query .= " AND " . implode(" AND ", $whereClauses);
}

// Complete the query with sorting and pagination
$query .= " ORDER BY i.last_updated DESC LIMIT ? OFFSET ?";
$params = array_merge($params, [$itemsPerPage, $offset]);
$types .= 'ii';

// Fetch items with error handling
try {
    $stmt = $conn->prepare($query);
    
    if (!empty($types)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Get total count
    $totalResult = $conn->query("SELECT FOUND_ROWS()");
    $totalItems = $totalResult->fetch_row()[0];
    $totalPages = ceil($totalItems / $itemsPerPage);
    
    if (!$result) {
        throw new Exception($conn->error);
    }
} catch (Exception $e) {
    error_log("Error fetching items: " . $e->getMessage());
    die("Error loading item records. Please try again later.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UCGS Inventory | Item Records</title>
    <link rel="stylesheet" href="../css/AdminRecords.css">
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
                    <a href="../login/logout.php"><img src="../assets/img/logout.png" alt="Logout Icon" class="dropdown-icon"> Logout</a>
                </div>
            </div>
        </div>
    </div>
</header>

<aside class="sidebar">
    <ul>
        <li><a href="adminDashboard.php"><img src="../assets/img/dashboards.png" alt="Dashboard Icon" class="sidebar-icon"> Dashboard</a></li>
        <li><a href="ItemRecords.php"><img src="../assets/img/list-items.png" alt="Items Icon" class="sidebar-icon">Item Records</i></a></li>
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

<<div class="main-content">
<div class= "table-container">
        <h2>Item Records</h2>
       <div class="filter-container">
    <div class="left-filters">
        <form id="filter-form" method="GET" action="ItemRecords.php">
            <div class="top-filters">
                <div class="form-group search-wrapper">
                    <i class="fas fa-search"></i>
                    <input type="text" id="search-box" name="search" placeholder="Search by Item Name..." 
                           value="<?php echo htmlspecialchars($searchTerm); ?>" autocomplete="off">
      
                
                <div class="form-group">
                    <select class="filter-select" name="status">
                        <option value="">All Statuses</option>
                        <option value="Available" <?php echo $statusFilter === 'Available' ? 'selected' : ''; ?>>Available</option>
                        <option value="Out of Stock" <?php echo $statusFilter === 'Out of Stock' ? 'selected' : ''; ?>>Out of Stock</option>
                        <option value="Low Stock" <?php echo $statusFilter === 'Low Stock' ? 'selected' : ''; ?>>Low Stock</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <select class="filter-select" name="category">
                    <option value="">All Categories</option>
                        <option value="accessories" <?php echo $categoryFilter === 'accessories' ? 'selected' : ''; ?>>Accessories</option>
                        <option value="consumables" <?php echo $categoryFilter === 'consumables' ? 'selected' : ''; ?>>Consumables</option>
                        <option value="electronics" <?php echo $categoryFilter === 'electronics' ? 'selected' : ''; ?>>Electronics</option>
                        <option value="furniture" <?php echo $categoryFilter === 'furniture' ? 'selected' : ''; ?>>Furniture</option>
                        <option value="stationary" <?php echo $categoryFilter === 'stationary' ? 'selected' : ''; ?>>Stationary</option>
                    </select>
                </div>
                </div>
            </div>
            <div class="bottom-filters">
                <div class="form-group date-range">
                    <label for="start-date">Date Range:</label>
                    <input type="date" id="start-date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
                    <label for="end-date">To:</label>
                    <input type="date" id="end-date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
                </div>
                <div class="form-group">
                    <button type="button" id="clear-filters-btn" class="clear-filters-btn">
                        <i class="fas fa-times"></i> Clear Filters
                    </button>
                </div>
            </div>
        </form>
    </div>
    <div class="right-buttons">
        <button class="delete-selected-btn" onclick="deleteSelected()" disabled>Delete Selected</button>
        <button class="create-btn" onclick="openCreateModal()">Create New Item</button>
    </div>
</div>


<form id="item-form">
    <table class="item-table">
        <thead>
            <tr>
                <th>Select All <input type="checkbox" class="select-all" id="selectAll" onclick="toggleSelectAll(this)"></th>
                <th>Item Name</th>
                <th>Description</th>
                <th>Quantity</th>
                <th>Availability</th>
                <th>Unit</th>
                <th>Status</th>
                <th>Expiration</th>
                <th>Last Updated</th>
                <th>Model No</th>
                <th>Item Category</th>
                <th>Item Location</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="item-table-body">
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr data-item-id="<?= htmlspecialchars($row['item_id']) ?>">
                    <td><input type="checkbox" class="select-item" data-item-id="<?= htmlspecialchars($row['item_id']) ?>" onclick="updateSelectAllState()"></td>
                        <td><?= htmlspecialchars($row['item_name']) ?></td>
                        <td><?= htmlspecialchars($row['description']) ?></td>
                        <td><?= htmlspecialchars($row['quantity']) ?></td>
                        <td><?= htmlspecialchars($row['availability']) ?></td>
                        <td><?= htmlspecialchars($row['unit']) ?></td>
                        <td class="status-cell <?= 
                            ($row['availability'] === 0 ? 'out-of-stock' : 
                            ($row['availability'] <= 5 ? 'low-stock' : '')) 
                        ?>">
                            <?= htmlspecialchars(
                                $row['availability'] === 0 ? 'Out of Stock' : 
                                ($row['availability'] <= 5 ? 'Low Stock' : 'Available')
                            ) ?>
                        </td>
                        <td><?= htmlspecialchars($row['expiration'] ?: 'N/A') ?></td>
                        <td><?= htmlspecialchars($row['last_updated']) ?></td>
                        <td><?= htmlspecialchars($row['model_no']) ?></td>
                        <td><?= htmlspecialchars(ucfirst($row['item_category'])) ?></td>
                        <td><?= htmlspecialchars($row['item_location'] ?: 'N/A') ?></td>
                        <td>
                            <button type="button" class="update-btn" onclick="openUpdateModal(<?= $row['item_id'] ?>)">Update</button>
                            <?php if ($row['item_category'] !== 'consumables'): ?>
                                <button type="button" class="delete-btn" onclick="openDeleteModal(<?= $row['item_id'] ?>)">Dispose</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="13">No records found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</form>

    <div class="pagination">
        <button onclick="prevPage()" id="prev-btn" <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>>Previous</button>
        <span id="page-number">Page <?php echo $currentPage; ?> of <?php echo $totalPages; ?></span>
        <button onclick="nextPage()" id="next-btn" <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>>Next</button>
    </div>
</div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <p>Are you sure you want to Dispose this item?</p>
        <div class="modal-buttons">
            <button id="confirmDelete" class="delete-btn">Yes</button>
            <button id="cancelDelete" class="cancel-btn">Cancel</button>
        </div>
    </div>
</div>

<!-- Create New Item Modal -->
<div id="create-Item-modal" class="modal">
    <div class="modal-content">
        <h2>Create New Item</h2>
        <form id="create-item-form">
            <input type="hidden" name="create-item" value="1">
            
            <label for="item-name">Item Name</label>
            <input type="text" id="item-name" name="item_name" required>

            <label for="description">Description</label>
            <input id="description" style="width: 100%;height: 20px;border-radius: 10px;" name="description"></input>

            <label for="quantity">Quantity</label>
            <input type="number" id="quantity" name="quantity" min="0" required onchange="updateStatus(this.value)">

            <label for="model-no">Model No.</label>
            <input type="text" id="model-no" name="model_no" required>

            <label for="unit">Unit</label>
            <select id="unit" name="unit" required>
                <option value="">-- Select Unit --</option>
                <option value="pcs">Pcs</option>
                <option value="bx">Bx</option>
                <option value="pr">Pr</option>
                <option value="bdl">Bdl</option>
            </select>

            <label>Status</label>
            <input type="text" id="status-display" name="status" readonly>
            <input type="hidden" id="status" name="status" value="Available">

            <label for="item-category">Item Category</label>
            <select id="item-category" name="item_category" required>
                <option value="">-- Select Category --</option>
                <option value="electronics">Electronics</option>
                <option value="stationary">Stationary</option>
                <option value="furniture">Furniture</option>
                <option value="accessories">Accessories</option>
                <option value="consumables">Consumables</option>
            </select>
            
            <label for="item-location">Item Location</label>
            <input type="text" id="item-location" name="item_location">
            
            <label for="expiration">Expiration Date (optional)</label>
            <input type="date" id="expiration" name="expiration">
            
            <button type="submit">Submit</button>
            <button type="button" id="cancel-btn">Cancel</button>
        </form>
    </div>
</div>

<!-- Update Modal -->
<div id="updateModal" class="modal">
    <div class="modal-content">
        <h2>Update Item</h2>
        <form id="update-form">
            <input type="hidden" id="update-item-id" name="item_id">
            <label for="update-item-name">Item Name</label>
            <input type="text" id="update-item-name" name="item_name" required>
            
            <label for="update-description">Description</label>
            <input id="update-description" style="width: 100%;height: 20px;border-radius: 10px;" name="description"></input>
            
            <label for="update-quantity">Quantity</label>
            <input type="number" id="update-quantity" name="quantity" min="0" required>
            
            <label for="update-availability">Availability</label>
            <input type="number" id="update-availability" name="availability" min="0" required onchange="updateStatus(this.value, true)">
            
            <label for="update-model-no">Model No</label>
            <input type="text" id="update-model-no" name="model_no" required>
            
            <label>Status</label>
            <input type="text" id="update-status-display" readonly>
            <input type="hidden" id="update-status" name="status" value="Available">
            
            <label for="update-unit">Unit</label>
            <select id="update-unit" name="unit" required>
                <option value="">-- Select Unit --</option>
                <option value="pcs">Pcs</option>
                <option value="bx">Bx</option>
                <option value="pr">Pr</option>
                <option value="bdl">Bdl</option>
            </select>
            
            <label for="update-item-category">Item Category</label>
            <select id="update-item-category" name="item_category" required>
                <option value="">-- Select Category --</option>
                <option value="electronics">Electronics</option>
                <option value="stationary">Stationary</option>
                <option value="furniture">Furniture</option>
                <option value="accessories">Accessories</option>
                <option value="consumables">Consumables</option>
            </select>
            
            <label for="update-item-location">Item Location</label>
            <input type="text" id="update-item-location" name="item_location">
            
            <label for="update-expiration">Expiration Date (optional)</label>
            <input type="date" id="update-expiration" name="expiration">
            
            <button type="submit">Save</button>
            <button type="button" id="cancelUpdate">Cancel</button>
        </form>
    </div>
</div>

<!-- Success Message Container -->
<div id="successMessage" class="success-message" style="display: none;">
    <i class="fas fa-check-circle"></i>
    <span id="successText"></span>
</div>

<script src="../js/AdRecords.js"></script>

</body>
</html>
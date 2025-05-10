<?php
include '../config/db_connection.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemCategory = htmlspecialchars($_POST['item_category'] ?? '');
    $itemId = intval($_POST['item_id'] ?? 0);
    $quantity = intval($_POST['quantity'] ?? 0);
    $dateNeeded = $_POST['date_needed'] ?? '';
    $returnDate = $_POST['return_date'] ?? '';
    $purpose = htmlspecialchars($_POST['purpose'] ?? '');
    $notes = htmlspecialchars($_POST['notes'] ?? '');
    $userId = $_SESSION['user_id'] ?? null;
    $fromCart = isset($_POST['from_cart']);

    // Validate required fields
    if (!$itemCategory || !$itemId || !$quantity || !$dateNeeded || !$returnDate || !$purpose || !$userId) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'All required fields must be filled out.']);
        exit();
    }

    // Validate quantity
    if ($quantity <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Quantity must be a positive number.']);
        exit();
    }

    // Validate dates
    if (strtotime($dateNeeded) === false || strtotime($returnDate) === false) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid date format.']);
        exit();
    }
    if (strtotime($dateNeeded) > strtotime($returnDate)) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Return date must be after the date needed.']);
        exit();
    }

    // Fetch item details
    $itemCheckStmt = $conn->prepare("SELECT item_name, quantity FROM items WHERE item_id = ? AND item_category = ?");
    if (!$itemCheckStmt) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database error: ' . $conn->error]);
        exit();
    }
    $itemCheckStmt->bind_param("is", $itemId, $itemCategory);
    $itemCheckStmt->execute();
    $itemResult = $itemCheckStmt->get_result();
    $itemData = $itemResult->fetch_assoc();
    $itemCheckStmt->close();

    if (!$itemData) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Item does not exist in the selected category.']);
        exit();
    }

    $itemName = htmlspecialchars($itemData['item_name']);
    $availableQty = $itemData['quantity'];

    // Check reserved quantity
    $reservedCheck = $conn->prepare("SELECT SUM(quantity) AS reserved FROM borrow_requests 
                                   WHERE item_id = ? AND status = 'Approved'");
    $reservedCheck->bind_param("i", $itemId);
    $reservedCheck->execute();
    $reservedResult = $reservedCheck->get_result();
    $reservedData = $reservedResult->fetch_assoc();
    $reservedCheck->close();

    $actuallyAvailable = $availableQty - ($reservedData['reserved'] ?? 0);

    if ($actuallyAvailable < $quantity) {
        header('Content-Type: application/json');
        echo json_encode(['error' => "Only $actuallyAvailable of this item are currently available."]);
        exit();
    }

    // Check if user already has active request for this item
    $checkQuery = "SELECT COUNT(*) AS count FROM borrow_requests 
                  WHERE user_id = ? AND item_id = ? AND status IN ('Pending', 'Approved')";
    $checkStmt = $conn->prepare($checkQuery);
    if ($checkStmt) {
        $checkStmt->bind_param("ii", $userId, $itemId);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $row = $checkResult->fetch_assoc();
        $checkStmt->close();

        if ($row['count'] > 0) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'You already have an active request for this item. Please wait for your current request to be processed or returned.']);
            exit();
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database error: Unable to prepare statement. ' . htmlspecialchars($conn->error)]);
        exit();
    }

    // Insert borrow request
    $stmt = $conn->prepare("INSERT INTO borrow_requests 
                          (user_id, item_id, item_name, quantity, date_needed, return_date, purpose, notes, status) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')");

    if (!$stmt) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database error: ' . $conn->error]);
        exit();
    }

    $stmt->bind_param("iisissss", $userId, $itemId, $itemName, $quantity, $dateNeeded, $returnDate, $purpose, $notes);

    if ($stmt->execute()) {
        // Only update quantities if not from cart
        if (!$fromCart) {
            $updateStmt = $conn->prepare("UPDATE items SET quantity = quantity - ? WHERE item_id = ?");
            if ($updateStmt) {
                $updateStmt->bind_param("ii", $quantity, $itemId);
                $updateStmt->execute();
                $updateStmt->close();
            }
        }

        // Save transaction history
        $transactionQuery = "INSERT INTO transactions 
                           (user_id, action, details, item_id, item_name, quantity, status) 
                           VALUES (?, 'Borrow', ?, ?, ?, ?, 'Pending')";
        $transactionStmt = $conn->prepare($transactionQuery);
        $details = "Borrowed $quantity of item '$itemName' in $itemCategory category" . ($fromCart ? " (from cart)" : "");
        $transactionStmt->bind_param("isiss", $userId, $details, $itemId, $itemName, $quantity);
        $transactionStmt->execute();
        $transactionStmt->close();

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Failed to submit borrow request.']);
    }
    $stmt->close();
    exit();
}

function getCurrentUser($conn) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login/login.php");
        exit();
    }

    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT username, email FROM users WHERE user_id = ?");
    if (!$stmt) {
        die('Database error: ' . $conn->error);
    }
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    return $user ?: [];
}

function getItemCategories($conn) {
    $stmt = $conn->prepare("SELECT DISTINCT item_category FROM items WHERE quantity > 0 ORDER BY item_category");
    if (!$stmt) {
        die('Database error: ' . $conn->error);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $categories = [];
    while ($row = $result->fetch_assoc()) {
        $categories[] = $row['item_category'];
    }
    $stmt->close();
    return $categories;
}

$currentUser = getCurrentUser($conn);
$accountName = htmlspecialchars($currentUser['username']);
$accountEmail = htmlspecialchars($currentUser['email'] ?? '');
$categories = getItemCategories($conn);

// Fetch items based on category for dynamic population
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['item_category'])) {
    $category = htmlspecialchars($_GET['item_category']);

    if (empty($category)) {
        header('Content-Type: application/json', true, 400);
        echo json_encode(['error' => 'Item category is required']);
        exit();
    }

    // Also fetch quantity when getting items
    $stmt = $conn->prepare("SELECT item_id, item_name, quantity FROM items 
                           WHERE item_category = ? AND quantity > 0
                           GROUP BY item_id, item_name, quantity");
    if (!$stmt) {
        header('Content-Type: application/json', true, 500);
        echo json_encode(['error' => 'Database error: ' . $conn->error]);
        exit();
    }
    
    $stmt->bind_param("s", $category);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();

    header('Content-Type: application/json');
    echo json_encode($items);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['item_id'])) {
    $itemId = intval($_GET['item_id']);

    if ($itemId <= 0) {
        header('Content-Type: application/json', true, 400);
        echo json_encode(['error' => 'Invalid item ID']);
        exit();
    }

    // Get item quantity
    $stmt = $conn->prepare("SELECT quantity FROM items WHERE item_id = ?");
    if (!$stmt) {
        header('Content-Type: application/json', true, 500);
        echo json_encode(['error' => 'Database error: ' . $conn->error]);
        exit();
    }
    
    $stmt->bind_param("i", $itemId);
    $stmt->execute();
    $result = $stmt->get_result();
    $itemData = $result->fetch_assoc();
    $stmt->close();

    if (!$itemData) {
        header('Content-Type: application/json', true, 404);
        echo json_encode(['error' => 'Item not found']);
        exit();
    }

    // Get reserved quantity
    $reservedCheck = $conn->prepare("SELECT SUM(quantity) AS reserved FROM borrow_requests 
                                   WHERE item_id = ? AND status = 'Approved'");
    $reservedCheck->bind_param("i", $itemId);
    $reservedCheck->execute();
    $reservedResult = $reservedCheck->get_result();
    $reservedData = $reservedResult->fetch_assoc();
    $reservedCheck->close();

    $availableQty = $itemData['quantity'];
    $reservedQty = $reservedData['reserved'] ?? 0;
    $actuallyAvailable = $availableQty - $reservedQty;

    header('Content-Type: application/json');
    echo json_encode([
        'available' => $actuallyAvailable,
        'total_quantity' => $availableQty,
        'reserved' => $reservedQty
    ]);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="UCGS Inventory Management System - New Item Request">
    <title>UCGS Inventory | Borrow Request</title>
    <link rel="stylesheet" href="../css/UsersItemborrow.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" 
          integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" 
          crossorigin="anonymous" referrerpolicy="no-referrer">
    <style>
        /* Modal Notification Styles */
        .custom-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .custom-modal-content {
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            width: 80%;
            max-width: 400px;
            position: relative;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            animation: modalFadeIn 0.3s;
        }
        
        .custom-modal-content p {
            margin: 0;
            padding: 10px 0;
            color: #333;
            font-size: 16px;
        }
        
        .custom-modal-close {
            position: absolute;
            top: 10px;
            right: 10px;
            font-size: 20px;
            font-weight: bold;
            background: none;
            border: none;
            cursor: pointer;
            padding: 0 5px;
        }
        
        @keyframes modalFadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .error-message {
            color: #d9534f;
            font-weight: bold;
        }
        .success-message {
            color: #28a745;
            font-weight: bold;
        }
    </style>
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
        <div id="new-request" class="tab-content active">
            <h1>Borrow Request</h1>
            
            <form id="requestForm" class="request-form" method="POST">
                <div class="form-row">
                    <div class="form-group">
                        <label for="item-category">Item Category:</label>
                        <select id="item-category" name="item_category" required>
                            <option value="" disabled selected>Select a Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category); ?>">
                                    <?php echo htmlspecialchars(ucfirst($category)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="item-id">Select Item:</label>
                        <select id="item-id" name="item_id" required>
                            <option value="" disabled selected>Select an Item</option>
                        </select>
                    </div>
                </div>
                <div class="form-row">
    <div class="form-group">
        <label for="quantity">Quantity: <span id="available-quantity" class="available-quantity"></span></label>
        <input type="number" id="quantity" name="quantity" 
               min="1" required placeholder="Enter quantity">
    </div>
</div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="date_needed">Date Needed:</label>
                        <input type="date" id="date_needed" name="date_needed" required>
                    </div>
                    <div class="form-group">
                        <label for="return_date">Return Date:</label>
                        <input type="date" id="return_date" name="return_date" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="purpose">Purpose:</label>
                    <textarea id="purpose" name="purpose" rows="3" required placeholder="Enter the purpose of borrowing"></textarea>
                </div>

                <div class="form-group">
                    <label for="notes">Additional Notes:</label>
                    <textarea id="notes" name="notes" rows="2" placeholder="Enter any additional notes (optional)"></textarea>
                </div>

                <div class="form-buttons">
                    <button type="button" id="submit-btn" class="submit-btn">Submit Request</button>
                    <button type="button" id="add-to-cart-btn" class="cart-btn">Add to Cart</button>
                    <button type="reset" class="reset-btn">Clear Form</button>
                </div>
            </form>
        </div>

        <div class="borrow-cart-container">
    <h2>Add to Cart</h2>
    <div class="cart-table-wrapper">
        <table class="borrow-cart-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Category</th>
                    <th>Quantity</th>
                    <th>Date Needed</th>
                    <th>Return Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody id="cart-items">
                <!-- Cart items will be populated here -->
            </tbody>
        </table>
    </div>
    <div class="cart-actions">
        <button id="submit-all-btn" class="submit-all-btn">Submit All Requests</button>
        <button id="clear-cart-btn" class="clear-cart-btn">Clear Cart</button>
    </div>
</div>
    </main>

    <!-- Modal Notification -->
    <div id="errorModal" class="custom-modal">
        <div class="custom-modal-content">
            <button class="custom-modal-close">&times;</button>
            <p id="modalMessage"></p>
        </div>
    </div>

    <!-- Confirmation Modal -->
<div id="confirmationModal" class="custom-modal">
    <div class="custom-modal-content">
        <button class="custom-modal-close">&times;</button>
        <p id="confirmationMessage"></p>
        <div class="confirmation-buttons">
            <button id="confirmYes" class="confirm-btn">Yes</button>
            <button id="confirmNo" class="confirm-btn cancel-btn">No</button>
        </div>
    </div>
</div>



    <script src="../js/UsersItemborrowed.js"></script>
</body>
</html>
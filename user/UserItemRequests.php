<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection with error handling
require_once '../config/db_connection.php';

// Start session securely
if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_secure' => true,
        'cookie_httponly' => true,
        'use_strict_mode' => true
    ]);
}

// Regenerate session ID to prevent fixation
if (empty($_SESSION['initiated'])) {
    session_regenerate_id();
    $_SESSION['initiated'] = true;
}

// CSRF token generation
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Function to get current user with prepared statements
function getCurrentUser($conn) {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'User') {
        header("Location: ../login/login.php");
        exit();
    }

    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT username, email, ministry FROM users WHERE user_id = ? AND role = 'User' LIMIT 1");
    if (!$stmt) {
        error_log("Database error: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    return $user;
}

$currentUser = getCurrentUser($conn);
if (!$currentUser) {
    header("Location: ../login/login.php");
    exit();
}

$accountName = htmlspecialchars($currentUser['username'] ?? '', ENT_QUOTES, 'UTF-8');
$userMinistry = htmlspecialchars($currentUser['ministry'] ?? '', ENT_QUOTES, 'UTF-8');

// Initialize messages
$errorMessage = '';
$successMessage = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $errorMessage = 'Invalid CSRF token. Please refresh the page and try again.';
    } else {
        // Validate and sanitize inputs
        $itemName = trim($_POST['item-name'] ?? '');
        $itemCategory = trim($_POST['item-category'] ?? '');
        $quantity = filter_var($_POST['quantity'] ?? 0, FILTER_VALIDATE_INT);
        $itemUnit = trim($_POST['item-unit'] ?? '');
        $purpose = trim($_POST['purpose'] ?? '');
        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : null;

        // Enhanced validation
// Enhanced validation
$errors = [];
if (empty($itemName)) {
    $errors[] = 'Item Name is required.';
} elseif (strlen($itemName) > 255) {
    $errors[] = 'Item Name must not exceed 255 characters.';
}

if (empty($itemCategory) || !in_array($itemCategory, ['electronics', 'stationary', 'furniture', 'accesories', 'consumables'])) {
    $errors[] = 'Please select a valid category.';
}

if ($quantity === false || $quantity <= 0 || $quantity > 1000) {
    $errors[] = 'Quantity must be a positive number between 1 and 1000.';
}

if (empty($itemUnit) || !in_array($itemUnit, ['Piece', 'Box', 'Pair', 'Bundle'])) {
    $errors[] = 'Please select a valid unit.';
}

if (empty($purpose)) {
    $errors[] = 'Purpose is required.';
} elseif (strlen($purpose) > 500) {
    $errors[] = 'Purpose must not exceed 500 characters.';
}

if ($notes && strlen($notes) > 1000) {
    $errors[] = 'Notes must not exceed 1000 characters.';
} else {
            // Check for duplicate pending requests
            try {
                $conn->begin_transaction();

                $checkStmt = $conn->prepare("SELECT COUNT(*) AS count FROM new_item_requests 
                                           WHERE item_name = ? AND user_id = ? AND status = 'Pending'");
                $checkStmt->bind_param("si", $itemName, $_SESSION['user_id']);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                $row = $checkResult->fetch_assoc();
                $checkStmt->close();

                if ($row['count'] > 0) {
                    throw new Exception('You already have a pending request for this item.');
                }

               // Insert the new request
    $insertStmt = $conn->prepare("INSERT INTO new_item_requests 
    (user_id, item_name, item_category, quantity, item_unit, purpose, notes, status, ministry) 
    VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?)");
$insertStmt->bind_param("ississss", $_SESSION['user_id'], $itemName, $itemCategory, 
$quantity, $itemUnit, $purpose, $notes, $userMinistry);

if (!$insertStmt->execute()) {
throw new Exception('Failed to submit request: Database error.');
}

$requestId = $conn->insert_id;

// Fixed transaction logging - using NULL for item_id
$transactionStmt = $conn->prepare("INSERT INTO transactions 
         (user_id, action, details, item_id, quantity, status, item_name) 
         VALUES (?, 'New Item Request', ?, NULL, ?, 'Pending', ?)");
$details = "Requested $quantity $itemUnit of '$itemName' ($itemCategory)";
$transactionStmt->bind_param("isis", $_SESSION['user_id'], $details, $quantity, $itemName);

if (!$transactionStmt->execute()) {
throw new Exception('Failed to log transaction: Database error.');
}

$transactionStmt->close();
$conn->commit();

// Regenerate CSRF token
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

header('Location: UserTransaction.php?success=1');
exit();

} catch (Exception $e) {
$conn->rollback();
$errorMessage = $e->getMessage();
error_log("Request submission error: " . $e->getMessage());
}
        }
    }
}

// Regenerate CSRF token after 5 minutes for security
if (empty($_SESSION['csrf_token_time']) || time() - $_SESSION['csrf_token_time'] > 300) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="UCGS Inventory Management System - New Item Request">
    <title>UCGS Inventory | New Item Request</title>
    <link rel="stylesheet" href="../css/UseriItmRequest.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="icon" href="../assets/img/favicon.ico" type="image/x-icon">
</head>
<body>
<header class="header">
    <div class="header-content">
        <div class="left-side">
            <img src="../assets/img/Logo.png" alt="UCGS Inventory Logo" class="logo" >
            <span class="website-name">UCGS Inventory</span>
        </div>
        <div class="right-side">
            <div class="user">
                <img src="../assets/img/users.png" alt="User profile" class="icon" id="userIcon" width="24" height="24">
                <span class="user-text"><?php echo $accountName; ?></span>
                <div class="user-dropdown" id="userDropdown">
                    <a href="userprofile.php"><img src="../assets/img/updateuser.png" alt="Profile" class="dropdown-icon"> Profile</a>
                    <a href="usernotification.php"><img src="../assets/img/notificationbell.png" alt="Notification" class="dropdown-icon"> Notification</a>
                    <a href="../login/logout.php"><img src="../assets/img/logout.png" alt="Logout" class="dropdown-icon"> Logout</a>
                </div>
            </div>
        </div>
    </div>
</header>

<aside class="sidebar">
    <ul>
        <li><a href="Userdashboard.php"><img src="../assets/img/dashboards.png" alt="Dashboard" class="sidebar-icon"> Dashboard</a></li>
        <li><a href="UserItemRecords.php"><img src="../assets/img/list-items.png" alt="Items" class="sidebar-icon"> Item Records</a></li>
        <li class="dropdown">
            <a href="#" class="dropdown-btn">
                <img src="../assets/img/request-for-proposal.png" alt="Request" class="sidebar-icon">
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
        <li><a href="UserTransaction.php"><img src="../assets/img/time-management.png" alt="Transactions" class="sidebar-icon">Transaction Records</a></li>
    </ul>
</aside>

<main class="main-content">
    <div id="new-request" class="tab-content active">
        <h1>New Item Request</h1>
        
        <?php if (!empty($errorMessage)): ?>
            <div class="alert error">
                <p><?php echo $errorMessage; ?></p>
            </div>
        <?php endif; ?>

        <form id="requestForm" class="request-form" method="POST" novalidate>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="item-name">Item Name:</label>
                    <input type="text" id="item-name" name="item-name" required maxlength="255">
                    <span class="error-message" id="item-name-error"></span>
                </div>
                <div class="form-group">
                    <label for="item-category">Category:</label>
                    <select id="item-category" name="item-category" required>
                        <option value="">Select Category</option>
                        <option value="electronics">Electronics</option>
                        <option value="stationary">Stationary</option>
                        <option value="furniture">Furniture</option>
                        <option value="accesories">Accessories</option>
                        <option value="consumables">Consumables</option>
                    </select>
                    <span class="error-message" id="item-category-error"></span>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="quantity">Quantity:</label>
                    <input type="number" id="quantity" name="quantity" min="1" max="1000" required>
                    <span class="error-message" id="quantity-error"></span>
                </div>
                <div class="form-group">
                    <label for="item-unit">Item Unit:</label>
                    <select id="item-unit" name="item-unit" required>
                        <option value="">Select Unit</option>
                        <option value="Piece">Pcs</option>
                        <option value="Box">Bx</option>
                        <option value="Pair">Pr</option>
                        <option value="Bundle">Bdl</option>
                    </select>
                    <span class="error-message" id="item-unit-error"></span>
                </div>
            </div>

            <div class="form-group">
                <label for="purpose">Purpose:</label>
                <textarea id="purpose" name="purpose" rows="3" required maxlength="500"></textarea>
                <span class="error-message" id="purpose-error"></span>
            </div>

            <div class="form-group">
                <label for="notes">Additional Notes:</label>
                <textarea id="notes" name="notes" rows="2" maxlength="1000"></textarea>
                <small class="char-count">0/1000 characters</small>
            </div>

            <div class="form-buttons">
                <button type="submit" class="submit-btn" id="submitBtn">
                    <span class="btn-text">Submit Request</span>
                    <span class="spinner hidden" id="submitSpinner"></span>
                </button>
                <button type="reset" class="reset-btn">Clear Form</button>
            </div>
        </form>
    </div>
</main>

<script src="../js/UserItmRequest.js"></script>
<script>

</script>
</body>
</html>
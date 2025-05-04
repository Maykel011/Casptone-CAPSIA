<?php
include '../config/db_connection.php';
session_start();

// Verify admin session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrator') {
    header("Location: ../login/login.php");
    exit();
}

// Fetch currently logged-in admin details
$currentAdminId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username, email FROM users WHERE user_id = ?");
$stmt->bind_param("i", $currentAdminId);
$stmt->execute();
$result = $stmt->get_result();
$currentAdmin = $result->fetch_assoc();
$stmt->close();

// Pass the current admin details to the frontend
$accountName = $currentAdmin['username'] ?? 'User';
$accountEmail = $currentAdmin['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $user_id = $_POST['user_id'] ?? null;
    $username = $_POST['username'] ?? null;
    $email = $_POST['email'] ?? null;
    $password = $_POST['password'] ?? null;
    $role = $_POST['role'] ?? null;
    $ministry = $_POST['ministry'] ?? null;
    $status = $_POST['status'] ?? 'Active';
    $duration = $_POST['duration'] ?? null;

    if ($action === 'CREATE') {
        if ($password) {
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        }

        // Validate required fields
        if (!$username || !$email || !$password || !$role || !$ministry) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'All fields are required.']);
            exit;
        }

        // Check administrator limit
        if ($role === 'Administrator') {
            $stmt = $conn->prepare("SELECT COUNT(*) AS admin_count FROM users WHERE role = 'Administrator'");
            $stmt->execute();
            $result = $stmt->get_result();
            $adminCount = $result->fetch_assoc()['admin_count'];
            $stmt->close();

            if ($adminCount >= 5) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Administrator creation limit exceeded.']);
                exit;
            }
        }

        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, ministry, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssss", $username, $email, $hashedPassword, $role, $ministry, $status);
        if ($stmt->execute()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'user_id' => $conn->insert_id]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to create account.']);
        }
        $stmt->close();
        exit;
    }

    if ($action === 'UPDATE_STATUS') {
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE user_id = ?");
        $stmt->bind_param("si", $status, $user_id);
        $stmt->execute();
        $stmt->close();

        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'DELETE' && $user_id) {
        // Prevent deleting other admins
        $stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user['role'] === 'Administrator') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'You cannot delete another administrator.']);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to delete user.']);
        }
        $stmt->close();
        exit;
    }

    if ($action === 'DEACTIVATE' && $user_id && $duration) {
        // Prevent deactivating other admins
        $stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user['role'] === 'Administrator') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'You cannot deactivate another administrator.']);
            exit;
        }

        $end_date = date('Y-m-d H:i:s', strtotime("+$duration days"));
        $stmt = $conn->prepare("UPDATE users SET status = 'Deactivated', deactivation_end = ? WHERE user_id = ?");
        $stmt->bind_param("si", $end_date, $user_id);
        
        header('Content-Type: application/json');
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Deactivation failed']);
        }
        $stmt->close();
        exit;
    }
}

// New endpoint to check and reactivate users
if (isset($_GET['checkReactivations'])) {
    $current_date = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("UPDATE users SET status = 'Active', deactivation_end = NULL WHERE status = 'Deactivated' AND deactivation_end <= ?");
    $stmt->bind_param("s", $current_date);
    $stmt->execute();
    $affected_rows = $stmt->affected_rows;
    $stmt->close();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'reactivated_users' => $affected_rows]);
    exit;
}

if (isset($_GET['fetchUsers'])) {
    $result = $conn->query("SELECT 
        user_id, 
        username, 
        email, 
        role, 
        ministry, 
        status, 
        DATE_FORMAT(created_at, '%Y-%m-%d') as dateCreated 
        FROM users");
    
    $users = $result->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($users);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['fetchAllUsers'])) {
    $result = $conn->query("SELECT * FROM users");
    $allUsers = $result->fetch_all(MYSQLI_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($allUsers);
    $conn->close();
    exit;
}

/**
 * Retrieves the details of the currently logged-in user.
 *
 * @param mysqli $conn The database connection.
 * @return array|null The user details or null if not found.
 */
function getLoggedInUser($conn) {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    $userId = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT user_id, username, role FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    return $user;
}

$loggedInUser = getLoggedInUser($conn);
if (!$loggedInUser || $loggedInUser['role'] !== 'Administrator') {
    header("Location: ../login/login.php");
    exit();
}

$accountName = $loggedInUser['username'];
$accountRole = $loggedInUser['role'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UCGS Inventory | Dashboard</title>
    <link rel="stylesheet" href="../css/AdminUserManagement.css">
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

        <!-- Request Record with Dropdown -->
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
        <h2>User Management</h2>

        <div class="filter-container">
    <div class="filter-inputs">
        <input type="text" id="search-box" placeholder="Search...">
        <label for="start-date">Date Range:</label>
        <input type="date" id="start-date">
        <label for="end-date">To:</label>
        <input type="date" id="end-date">
    </div>
    <button id="create-account-btn">Create Account</button>
</div>


<table class="user-table">
    <thead>
        <tr>
            <th>Username / Account Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Date Creation</th>
            <th>Ministry</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody id="user-table-body">
        <!-- Rows will be dynamically populated by JavaScript -->
    </tbody>
</table>

<div class="pagination">
    <button onclick="prevPage()" id="prev-btn" style="font-family:'Akrobat', sans-serif;">Previous</button>
    <span id="page-number" style="font-family:'Akrobat', sans-serif;">Page 1</span>
    <button onclick="nextPage()" id="next-btn" style="font-family:'Akrobat', sans-serif;">Next</button>
</div>

<div id="create-account-modal" class="modal">
    <div class="modal-content">
        <h2>Create Account</h2>
        <form id="account-form">
            <input type="hidden" id="user-id"> <!-- Hidden input for user_id -->
            <label for="username">Username / Account Name</label>
            <input type="text" id="username" required>

            <label for="email">Email</label>
            <input type="email" id="email" required>

            <label for="password">Password</label>
            <input type="password" id="password" required>

            <label for="ministry">Ministry</label>
            <select id="ministry">
                <option value="Choose">-- Choose your Ministry --</option>
                <option value="UCM">UCM</option>
                <option value="CWA">CWA</option>
                <option value="CHOIR">CHOIR</option>
                <option value="PWT">PWT</option>
                <option value="CYF">CYF</option>
            </select>

            <label for="role">Role</label>
            <select id="role">
                <option value="User">User</option>
                <option value="Administrator">Administrator</option>
            </select>
            <button type="submit">Submit</button>
            <button type="button" id="cancel-btn">Cancel</button>
        </form>
    </div>
</div>

<div id="deactivate-account-modal" class="modal">
    <div class="modal-content">
        <h2>Deactivate Account</h2>
        <form id="deactivate-form">
            <input type="hidden" id="deactivate-user-id"> <!-- Hidden input for user_id -->
            <label for="deactivate-duration">Duration</label>
            <select id="deactivate-duration">
                <option value="7">1 Week</option>
                <option value="30">1 Month</option>
                <option value="90">3 Months</option>
                <option value="custom">Custom</option>
            </select>

            <div id="custom-duration-container" style="display: none;">
                <label for="custom-duration">Custom Duration (Days)</label>
                <input type="number" id="custom-duration" min="1">
            </div>

            <button type="submit">Submit</button>
            <button type="button" id="deactivate-cancel-btn">Cancel</button>
        </form>
    </div>
</div>

    <script src="../js/UsersManagements.js"></script>
</body>
</html>
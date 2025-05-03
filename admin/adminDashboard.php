<?php
session_start();
include '../config/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrator') {
    header("Location: ../login/login.php");
    exit();
}

// Fetch currently logged-in admin details hot
$currentAdminId = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT username, email, role FROM users WHERE user_id = ?");
$stmt->bind_param("i", $currentAdminId);
$stmt->execute();
$result = $stmt->get_result();
$currentAdmin = $result->fetch_assoc();
$stmt->close();

// Pass the current admin details to the frontend
$accountName = $currentAdmin['username'] ?? 'User';
$accountEmail = $currentAdmin['email'] ?? '';
$accountRole = $currentAdmin['role'] ?? '';

try {
    // Fetch the total number of users
    $userCountQuery = "SELECT COUNT(*) FROM users";
    $userCountResult = $conn->query($userCountQuery);
    $userCount = $userCountResult->fetch_row()[0];
} catch (mysqli_sql_exception $e) {
    die("Error: Unable to fetch user count. Please ensure the 'users' table exists in the database.");
}

try {
    // Fetch the total number of items
    $itemCountQuery = "SELECT COUNT(*) FROM items";
    $itemCountResult = $conn->query($itemCountQuery);
    $itemCount = $itemCountResult->fetch_row()[0];
} catch (mysqli_sql_exception $e) {
    die("Error: Unable to fetch item count. Please ensure the 'items' table exists in the database.");
}



// Initialize variables with default values
$approvedRequestsCount = 0;
$pendingRequestsCount = 0;

// 1. Query for approved requests count
$approvedQuery = $conn->query("SELECT COUNT(*) FROM new_item_requests WHERE status = 'approved'");
if ($approvedQuery === false) {
    die("Error fetching approved requests: " . $conn->error);
} else {
    $approvedRequestsCount = $approvedQuery->fetch_row()[0];
    $approvedQuery->free(); // Free the result set
}

// 2. Query for pending requests count
$pendingQuery = $conn->query("SELECT COUNT(*) FROM new_item_requests WHERE status = 'pending'");
if ($pendingQuery === false) {
    die("Error fetching pending requests: " . $conn->error);
} else {
    $pendingRequestsCount = $pendingQuery->fetch_row()[0];
    $pendingQuery->free(); // Free the result set
}

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['request_id'])) {
        $action = $_POST['action'];
        $requestId = intval($_POST['request_id']);
        $status = ($action === 'approve') ? 'approved' : 'rejected';

        $updateRequestQuery = $conn->prepare("UPDATE new_item_requests SET status = ? WHERE request_id = ?");
        $updateRequestQuery->bind_param("si", $status, $requestId);
        if (!$updateRequestQuery->execute()) {
            die("Error updating request status: " . $conn->error);
        }
        $updateRequestQuery->close();
        header("Location: adminDashboard.php");
        exit();
    }
}

// Prepare data for the chart
$chartData = [
    'users' => $userCount,
    'items' => $itemCount,
    'approvedRequests' => $approvedRequestsCount,
    'pendingRequests' => $pendingRequestsCount
];

// Fetch recent items based on the most recent created_at timestamp
$recentItemsQuery = "SELECT item_name, description, model_no, item_category, status, quantity 
                     FROM items 
                     WHERE created_at = (SELECT MAX(created_at) FROM items)";
$recentItemsResult = $conn->query($recentItemsQuery);
if ($recentItemsResult === false) {
    die("Error fetching recent items: " . $conn->error);
}

// Fetch pending requests
$pendingRequestsQuery = "SELECT u.username, r.item_name, r.item_category, r.request_date, r.quantity, r.request_id 
                         FROM new_item_requests r 
                         JOIN users u ON r.user_id = u.user_id 
                         WHERE r.status = 'pending' 
                         ORDER BY r.request_date DESC";
$pendingRequestsResult = $conn->query($pendingRequestsQuery);
if ($pendingRequestsResult === false) {
    die("Error fetching pending requests: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UCGS Inventory | Dashboard</title>
    <link rel="stylesheet" href="../css/DashboardAdmin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

<div class="main-container">
    <h4 class="overview-title">OVERVIEW</h4>
    <div class="dashboard-overview">
        <div class="card gradient-yellow">
            <i class="fa-solid fa-user"></i>
                <h2><b>Users</b></h2>
                <p><?php echo htmlspecialchars($userCount); ?></p>
                <canvas id="chart1" class="chart-container"></canvas>
            </div>
        <div class="card gradient-orange">
            <i class="fa-solid fa-check-circle"></i>
            <h2><b>Approved Requests</b></h2>
            <p><?php echo htmlspecialchars($approvedRequestsCount); ?></p>
            <canvas id="chart2" class="chart-container"></canvas>
        </div>
        <div class="card gradient-green">
            <i class="fa-solid fa-clock"></i>
            <h2><b>Pending Requests</b></h2>
            <p><?php echo htmlspecialchars($pendingRequestsCount); ?></p>
            <canvas id="chart3" class="chart-container"></canvas>
        </div>
        <div class="card gradient-purple">
            <i class="fa-solid fa-list"></i>
            <h2><b>Total Items</b></h2>
            <p><?php echo htmlspecialchars($itemCount); ?></p>
            <canvas id="chart4" class="chart-container"></canvas>
        </div>
    </div>
    

        
        <div class="tables-section">
            <div class="table-container">
                <h2>Recent Items</h2>
                <table>
                    <tr>
                        <th>Item Name</th>
                        <th>Description</th>
                        <th>Model No.</th>
                        <th>Category</th>  
                        <th>Status</th>
                        <th>Quantity</th>
                        <th>Actions</th>
                    </tr>
                    <?php while ($item = $recentItemsResult->fetch_assoc()): ?>
    <tr>
        <td><?php echo htmlspecialchars($item['item_name']); ?></td>
        <td><?php echo htmlspecialchars($item['description']); ?></td>
        <td><?php echo htmlspecialchars($item['model_no']); ?></td>
        <td><?php echo htmlspecialchars($item['item_category']); ?></td>  <!-- Actual column name -->
        <td><?php echo htmlspecialchars($item['status']); ?></td>         <!-- Actual column name -->
        <td><?php echo htmlspecialchars($item['quantity']); ?></td>
        <td>
            <button class="btn view" onclick="openViewModal(<?php echo htmlspecialchars(json_encode($item)); ?>">View</button>
        </td>
    </tr>
<?php endwhile; ?>
                </table>
            </div>
            
            <div class="table-container">
                <h2>Pending Requests</h2>
                <table>
                    <tr>
                        <th>Username</th>
                        <th>Requested Item Name</th>
                        <th>Item Category</th>
                        <th>Request Date</th>
                        <th>Quantity</th>
                        <th>Actions</th>
                    </tr>
                    <?php while ($request = $pendingRequestsResult->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($request['username']); ?></td>
                            <td><?php echo htmlspecialchars($request['item_name']); ?></td>
                            <td><?php echo htmlspecialchars($request['item_category']); ?></td>
                            <td><?php echo htmlspecialchars($request['request_date']); ?></td>
                            <td><?php echo htmlspecialchars($request['quantity']); ?></td>
                            <td>
                                <button class="btn approve" onclick="openApproveModal(<?php echo $request['request_id']; ?>)">Approve</button>
                                <button class="btn reject" onclick="openRejectModal(<?php echo $request['request_id']; ?>)">Reject</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </table>
            </div>
        </div>
        </div>
        
    <div id="viewModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal('viewModal')">&times;</span>
        <h2>Item Details</h2>
        <p><strong>Item Name:</strong> <span id="modalItemName"></span></p>
        <p><strong>Description:</strong> <span id="modalDescription"></span></p>
        <p><strong>Model No.:</strong> <span id="modalModel"></span></p>
        <p><strong>Category:</strong> <span id="modalCategory"></span></p>  
        <p><strong>Status:</strong> <span id="modalStatus"></span></p>     
        <p><strong>Quantity:</strong> <span id="modalQuantity"></span></p>
    </div>
</div>
    
    <div id="approveModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('approveModal')">&times;</span>
            <h2>Approve Request</h2>
            <p>Are you sure you want to approve this request?</p>
            <form method="POST" action="">
                <input type="hidden" name="request_id" id="approveRequestId">
                <input type="hidden" name="action" value="approve">
                <center><button type="submit" class="btn approve">Confirm</button></center>
            </form>
        </div>
    </div>
    
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('rejectModal')">&times;</span>
            <h2>Reject Request</h2>
            <p>Are you sure you want to reject this request?</p>
            <form method="POST" action="">
                <input type="hidden" name="request_id" id="rejectRequestId">
                <input type="hidden" name="action" value="reject">
                <center><button type="submit" class="btn reject">Confirm</button></center>
            </form>
        </div>
    </div>

    <script src="../js/DashboardAdmin.js"></script>

<script> document.addEventListener("DOMContentLoaded", function () {
            const ctx = document.getElementById('mainChart').getContext('2d');
            const chartData = <?php echo json_encode($chartData); ?>;

            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Users', 'Items', 'Approved Requests', 'Pending Requests'],
                    datasets: [{
                        label: 'Counts',
                        data: [chartData.users, chartData.items, chartData.approvedRequests, chartData.pendingRequests],
                        backgroundColor: [
                            'rgba(255, 206, 86, 0.6)', // Yellow
                            'rgba(153, 102, 255, 0.6)', // Purple
                            'rgba(255, 159, 64, 0.6)', // Orange
                            'rgba(75, 192, 192, 0.6)'  // Green
                        ],
                        borderColor: [
                            'rgba(255, 206, 86, 1)',
                            'rgba(153, 102, 255, 1)',
                            'rgba(255, 159, 64, 1)',
                            'rgba(75, 192, 192, 1)'
                        ],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        });

        function openViewModal(item) {
    document.getElementById('modalItemName').textContent = item.item_name;
    document.getElementById('modalDescription').textContent = item.description;
    document.getElementById('modalModel').textContent = item.model_no;
    document.getElementById('modalCategory').textContent = item.item_category;  // Changed
    document.getElementById('modalStatus').textContent = item.status;           // Changed
    document.getElementById('modalQuantity').textContent = item.quantity;
    openModal('viewModal');
}
        function openApproveModal(requestId) {
            document.getElementById('approveRequestId').value = requestId;
            openModal('approveModal');
        }

        function openRejectModal(requestId) {
            document.getElementById('rejectRequestId').value = requestId;
            openModal('rejectModal');
        }</script>
</body>
</html>
<?php
session_start();
include '../config/db_connection.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Administrator') {
    header("Location: ../login/login.php");
    exit();
}

// Fetch currently logged-in admin details
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
    $approvedQuery->free();
}

// 2. Query for pending requests count
$pendingQuery = $conn->query("SELECT COUNT(*) FROM new_item_requests WHERE status = 'pending'");
if ($pendingQuery === false) {
    die("Error fetching pending requests: " . $conn->error);
} else {
    $pendingRequestsCount = $pendingQuery->fetch_row()[0];
    $pendingQuery->free();
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
                     ORDER BY created_at DESC LIMIT 5";
$recentItemsResult = $conn->query($recentItemsQuery);
if ($recentItemsResult === false) {
    die("Error fetching recent items: " . $conn->error);
}

// Fetch pending requests with additional details
$pendingRequestsQuery = "SELECT u.username, r.item_name, r.item_category, r.request_date, 
                         r.quantity, r.request_id, r.notes 
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
    <link rel="stylesheet" href="../css/DashboardAdmins.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

    <!-- Add toggle button here -->
<button class="sidebar-toggle" id="sidebarToggle">
    <i class="fas fa-bars"></i>
</button>

    <aside class="sidebar">
        <ul>
            <li><a href="adminDashboard.php"><img src="../assets/img/dashboards.png" alt="Dashboard Icon" class="sidebar-icon"> Dashboard</a></li>
            <li><a href="ItemRecords.php"><img src="../assets/img/list-items.png" alt="Items Icon" class="sidebar-icon">Item Records</a></li>
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

    <div class="main-container">
        <h4 class="overview-title">OVERVIEW</h4>
        <div class="dashboard-overview">
            <div class="card gradient-yellow">
                <i class="fas fa-user"></i>
                <h2><b>Users</b></h2>
                <p><?php echo htmlspecialchars($userCount); ?></p>
                <canvas id="chart1" class="chart-container"></canvas>
            </div>
            <div class="card gradient-orange">
                <i class="fas fa-check-circle"></i>
                <h2><b>Approved Requests</b></h2>
                <p><?php echo htmlspecialchars($approvedRequestsCount); ?></p>
                <canvas id="chart2" class="chart-container"></canvas>
            </div>
            <div class="card gradient-green">
                <i class="fas fa-clock"></i>
                <h2><b>Pending Requests</b></h2>
                <p><?php echo htmlspecialchars($pendingRequestsCount); ?></p>
                <canvas id="chart3" class="chart-container"></canvas>
            </div>
            <div class="card gradient-purple">
                <i class="fas fa-list"></i>
                <h2><b>Total Items</b></h2>
                <p><?php echo htmlspecialchars($itemCount); ?></p>
                <canvas id="chart4" class="chart-container"></canvas>
            </div>
        </div>
        
        <div class="tables-section">
            <div class="table-container">
                <h2>Recent Items</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Description</th>
                            <th>Model No.</th>
                            <th>Category</th>  
                            <th>Status</th>
                            <th>Quantity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($item = $recentItemsResult->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['item_name']); ?></td>
                                <td><?php echo htmlspecialchars($item['description']); ?></td>
                                <td><?php echo htmlspecialchars($item['model_no']); ?></td>
                                <td><?php echo htmlspecialchars($item['item_category']); ?></td>
                                <td><?php echo htmlspecialchars($item['status']); ?></td>
                                <td><?php echo htmlspecialchars($item['quantity']); ?></td>
                                <td>
                                    <button class="btn view" onclick="openViewModal(<?php echo htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8'); ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="table-container">
                <h2>Pending Requests</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Requested Item</th>
                            <th>Category</th>
                            <th>Request Date</th>
                            <th>Quantity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($request = $pendingRequestsResult->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($request['username']); ?></td>
                                <td><?php echo htmlspecialchars($request['item_name']); ?></td>
                                <td><?php echo htmlspecialchars($request['item_category']); ?></td>
                                <td><?php echo htmlspecialchars($request['request_date']); ?></td>
                                <td><?php echo htmlspecialchars($request['quantity']); ?></td>
                                <td class="action-buttons">
                                    <button class="btn view" onclick="openRequestModal(<?php echo htmlspecialchars(json_encode($request), ENT_QUOTES, 'UTF-8'); ?>)">
                                        <i class="fas fa-info-circle"></i> Details
                                    </button>
                                    <button class="btn approve" onclick="openApproveModal(<?php echo $request['request_id']; ?>)">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                    <button class="btn reject" onclick="openRejectModal(<?php echo $request['request_id']; ?>)">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Modern Item View Modal -->
    <div id="viewModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Item Details</h2>
                <button class="modal-close" onclick="closeModal('viewModal')">&times;</button>
            </div>
            <div class="modal-body" id="viewModalBody">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>
    
    <!-- Modern Request View Modal -->
    <div id="requestModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Request Details</h2>
                <button class="modal-close" onclick="closeModal('requestModal')">&times;</button>
            </div>
            <div class="modal-body" id="requestModalBody">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>
    
    <!-- Approve Modal -->
    <div id="approveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Approve Request</h2>
                <button class="modal-close" onclick="closeModal('approveModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to approve this request?</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="">
                    <input type="hidden" name="request_id" id="approveRequestId">
                    <input type="hidden" name="action" value="approve">
                    <button type="button" class="btn cancel" onclick="closeModal('approveModal')">Cancel</button>
                    <button type="submit" class="btn approve"><i class="fas fa-check"></i> Confirm Approval</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Reject Request</h2>
                <button class="modal-close" onclick="closeModal('rejectModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to reject this request?</p>
            </div>
            <div class="modal-footer">
                <form method="POST" action="">
                    <input type="hidden" name="request_id" id="rejectRequestId">
                    <input type="hidden" name="action" value="reject">
                    <button type="button" class="btn cancel" onclick="closeModal('rejectModal')">Cancel</button>
                    <button type="submit" class="btn reject"><i class="fas fa-times"></i> Confirm Rejection</button>
                </form>
            </div>
        </div>
    </div>

    <script src="../js/AdminDashboard.js"></script>
    <script>
        // Chart initialization
        document.addEventListener("DOMContentLoaded", function () {
            // Mini charts
            const chartConfigs = [
                { id: "chart1", type: "doughnut", data: [<?php echo $userCount; ?>, <?php echo max(10, $userCount * 1.5); ?>], colors: ['#fff', 'rgba(255,255,255,0.2)'] },
                { id: "chart2", type: "doughnut", data: [<?php echo $approvedRequestsCount; ?>, <?php echo max(5, $approvedRequestsCount * 1.5); ?>], colors: ['#fff', 'rgba(255,255,255,0.2)'] },
                { id: "chart3", type: "doughnut", data: [<?php echo $pendingRequestsCount; ?>, <?php echo max(5, $pendingRequestsCount * 1.5); ?>], colors: ['#fff', 'rgba(255,255,255,0.2)'] },
                { id: "chart4", type: "doughnut", data: [<?php echo $itemCount; ?>, <?php echo max(20, $itemCount * 1.5); ?>], colors: ['#fff', 'rgba(255,255,255,0.2)'] }
            ];
            
            chartConfigs.forEach(config => {
                const ctx = document.getElementById(config.id);
                if (ctx) {
                    new Chart(ctx.getContext('2d'), {
                        type: config.type,
                        data: {
                            datasets: [{
                                data: config.data,
                                backgroundColor: config.colors,
                                borderWidth: 0
                            }]
                        },
                        options: {
                            responsive: true,
                            cutout: '70%',
                            plugins: { legend: { display: false } },
                            animation: { animateScale: true }
                        }
                    });
                }
            });

            // Main chart data
            const chartData = <?php echo json_encode($chartData); ?>;
        });

        // Modal functions
        function openModal(modalId, data = null) {
            const modal = document.getElementById(modalId);
            if (!modal) return;

            if (data) {
                populateModalContent(modalId, data);
            }

            modal.classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (!modal) return;

            modal.classList.remove('show');
            document.body.style.overflow = '';
        }

        function populateModalContent(modalId, data) {
            const modalBody = document.querySelector(`#${modalId} .modal-body`);
            if (!modalBody) return;

            if (modalId === 'viewModal') {
                modalBody.innerHTML = `
                    <div class="detail-row">
                        <div class="detail-label">Item Name:</div>
                        <div class="detail-value">${escapeHtml(data.item_name)}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Description:</div>
                        <div class="detail-value">${escapeHtml(data.description)}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Model No.:</div>
                        <div class="detail-value">${escapeHtml(data.model_no)}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Category:</div>
                        <div class="detail-value">${escapeHtml(data.item_category)}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Status:</div>
                        <div class="detail-value"><span class="status-badge ${data.status.toLowerCase()}">${escapeHtml(data.status)}</span></div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Quantity:</div>
                        <div class="detail-value">${escapeHtml(data.quantity)}</div>
                    </div>
                `;
            } else if (modalId === 'requestModal') {
                modalBody.innerHTML = `
                    <div class="detail-row">
                        <div class="detail-label">Requested By:</div>
                        <div class="detail-value">${escapeHtml(data.username)}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Item Name:</div>
                        <div class="detail-value">${escapeHtml(data.item_name)}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Category:</div>
                        <div class="detail-value">${escapeHtml(data.item_category)}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Request Date:</div>
                        <div class="detail-value">${escapeHtml(data.request_date)}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Quantity:</div>
                        <div class="detail-value">${escapeHtml(data.quantity)}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Additional Notes:</div>
                        <div class="detail-value">${escapeHtml(data.notes || 'N/A')}</div>
                    </div>
                `;
            }
        }

        function escapeHtml(unsafe) {
            if (unsafe === null || unsafe === undefined) return 'N/A';
            return unsafe.toString()
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        // Specific modal openers
        function openViewModal(item) {
            openModal('viewModal', item);
        }

        function openRequestModal(request) {
            openModal('requestModal', request);
        }

        function openApproveModal(requestId) {
            document.getElementById('approveRequestId').value = requestId;
            openModal('approveModal');
        }

        function openRejectModal(requestId) {
            document.getElementById('rejectRequestId').value = requestId;
            openModal('rejectModal');
        }

        // Close modal when clicking outside content
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal')) {
                closeModal(e.target.id);
            }
        });

            // Sidebar toggle functionality
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('.sidebar');
        const mainContainer = document.querySelector('.main-container');

        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            
            // Store sidebar state in localStorage
            const isActive = sidebar.classList.contains('active');
            localStorage.setItem('sidebarActive', isActive);
            
            // Adjust main container margin if needed
            if (window.innerWidth >= 768) {
                if (isActive) {
                    mainContainer.style.marginLeft = '0';
                    mainContainer.style.width = '100%';
                } else {
                    mainContainer.style.marginLeft = 'var(--sidebar-width)';
                    mainContainer.style.width = 'calc(100% - var(--sidebar-width))';
                }
            }
        });

        // Check localStorage for sidebar state on page load
        const sidebarActive = localStorage.getItem('sidebarActive') === 'true';
        if (sidebarActive) {
            sidebar.classList.add('active');
            if (window.innerWidth >= 768) {
                mainContainer.style.marginLeft = '0';
                mainContainer.style.width = '100%';
            }
        }

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            if (window.innerWidth < 768 && 
                !e.target.closest('.sidebar') && 
                !e.target.closest('#sidebarToggle') &&
                sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                localStorage.setItem('sidebarActive', false);
            }
        });

        // Adjust toggle button position on resize
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) {
                sidebarToggle.style.display = 'none';
                if (!sidebar.classList.contains('active')) {
                    mainContainer.style.marginLeft = 'var(--sidebar-width)';
                    mainContainer.style.width = 'calc(100% - var(--sidebar-width))';
                }
            } else {
                sidebarToggle.style.display = 'flex';
            }
        });
    });
    </script>
</body>
</html>
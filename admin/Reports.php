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

$accountName = $currentAdmin['username'] ?? 'User';
$accountEmail = $currentAdmin['email'] ?? '';
$accountRole = $_SESSION['role'];

// Check if the 'items' table exists
$tableCheckQuery = "SHOW TABLES LIKE 'items'";
$tableCheckResult = $conn->query($tableCheckQuery);

if (!$tableCheckResult || $tableCheckResult->num_rows === 0) {
    echo "<p style='color: red; text-align: center;'>Error: The 'items' table does not exist in the database.</p>";
    exit();
}

// Fetch items data with automatic status calculation
$query = "SELECT last_updated, model_no, item_name, description, item_category, 
          item_location, expiration, quantity, unit, 
          CASE 
              WHEN quantity = 0 THEN 'Out of Stock'
              WHEN quantity <= 5 THEN 'Low Stock'
              ELSE 'Available'
          END as status
          FROM items";
$result = $conn->query($query);

if (!$result) {
    echo "<p style='color: red; text-align: center;'>Error fetching items: " . htmlspecialchars($conn->error) . "</p>";
    exit();
}

// Handle report download requests
if (isset($_GET['download'])) {
    $downloadType = $_GET['download'];
    $selectedItems = isset($_POST['selectedItems']) ? json_decode($_POST['selectedItems'], true) : [];

    if (in_array($downloadType, ['pdf', 'xlsx'])) {
        $data = [];
        if (!empty($selectedItems)) {
            $placeholders = implode(',', array_fill(0, count($selectedItems), '?'));
            $stmt = $conn->prepare("SELECT last_updated, model_no, item_name, description, 
                                   item_category, item_location, expiration, quantity, 
                                   unit, 
                                   CASE 
                                       WHEN quantity = 0 THEN 'Out of Stock'
                                       WHEN quantity <= 5 THEN 'Low Stock'
                                       ELSE 'Available'
                                   END as status
                                   FROM items WHERE item_name IN ($placeholders)");
            $types = str_repeat('s', count($selectedItems));
            $stmt->bind_param($types, ...$selectedItems);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
        } else {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }

        if (empty($data)) {
            echo "<p style='color: red; text-align: center;'>No data available for download.</p>";
            exit();
        }

        if ($downloadType === 'xlsx') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="inventory_report_'.date('Y-m-d').'.csv"');
            
            $output = fopen('php://output', 'w');
            
            fputcsv($output, [
                'Last Updated', 'Model No', 'Item Name', 
                'Description', 'Item Category', 'Item Location', 
                'Expiration', 'Quantity', 'Unit', 'Status'
            ]);
            
            foreach ($data as $row) {
                fputcsv($output, [
                    $row['last_updated'] ?? '',
                    $row['model_no'] ?? '',
                    $row['item_name'] ?? '',
                    $row['description'] ?? '',
                    $row['item_category'] ?? '',
                    $row['item_location'] ?? '',
                    $row['expiration'] ?? '',
                    $row['quantity'] ?? '',
                    $row['unit'] ?? '',
                    $row['status'] ?? '',
                ]);
            }
            
            fclose($output);
            exit;
        }

        if ($downloadType === 'pdf') {
            $html = '<!DOCTYPE html>
            <html>
            <head>
                <style>
                    @page { 
                        size: A4 landscape; 
                        margin: 1cm;
                        marks: none;
                    }
                    body { 
                        font-family: Arial; 
                        margin: 0;
                        padding: 0;
                    }
                    .header-container {
                        display: flex;
                        justify-content: center;
                        align-items: center;
                        margin-top: 20px;
                        margin-bottom: 15px;
                        gap: 15px;
                        text-align: left;
                    }
                    .logo {
                        height: 4vw;
                        width: auto;
                    }
                    .church-info {
                        display: flex;
                        flex-direction: column;
                        justify-content: center;
                    }
                    .church-name {
                        font-family: "SerpentineBold", sans-serif;
                        font-size: 1.1em;
                        font-weight: bold;
                        margin-bottom: 2px;
                    }
                    .church-address {
                        font-size: 0.75em;
                        color: #555;
                        margin-left: 5%;
                    }
                    .report-meta {
                        display: flex;
                        justify-content: space-between;
                        margin-bottom: 10px;
                        padding: 0 2vw;
                    }
                    .report-title {
                        font-family: "Akrobat", sans-serif;
                        font-size: 1.125em;
                        font-weight: bold;
                        margin-left: 1.25vw;
                    }
                    .report-date {
                        font-size: 0.875em;
                        color: #555;
                        margin-right: 1.25vw;
                    }
                    table { 
                        width: 100%; 
                        border-collapse: collapse; 
                        font-size: 1em;
                    }
                    th { 
                        background-color: #006400;
                        color: white;
                        text-align: left; 
                        padding: 1vw;
                    }
                    td { 
                        border: 1px solid #ddd; 
                        padding: 1vw;
                    }
                    .status-out-of-stock {
                        color: #dc3545;
                        font-weight: bold;
                    }
                    .status-low-stock {
                        color: #ffc107;
                        font-weight: bold;
                    }
                    .status-available {
                        color: #28a745;
                        font-weight: bold;
                    }
                </style>
            </head>
            <body>
                <div class="header-container">
                    <img src="../assets/img/Logo.png" alt="Logo" class="logo">
                    <div class="church-info">
                        <div class="church-name">United Church of the Good Shepherd</div>
                        <div class="church-address">72 I. Lopez St, Mandaluyong City, 1550 Metro Manila</div>
                    </div>
                </div>
                
                <div class="report-meta">
                    <div class="report-title">UCGS Inventory Report</div>
                    <div class="report-date">Generated: '.date('Y-m-d H:i:s').'</div>
                </div>
                
                <table>
                    <tr>
                        <th>Last Updated</th>
                        <th>Model No</th>
                        <th>Item Name</th>
                        <th>Description</th>
                        <th>Item Category</th>
                        <th>Item Location</th>
                        <th>Expiration</th>
                        <th>Quantity</th>
                        <th>Unit</th>
                        <th>Status</th>
                    </tr>';
            
            foreach ($data as $row) {
                $statusClass = strtolower(str_replace(' ', '-', $row['status']));
                $html .= '<tr>
                        <td>'.htmlspecialchars($row['last_updated'] ?? '').'</td>
                        <td>'.htmlspecialchars($row['model_no'] ?? '').'</td>
                        <td>'.htmlspecialchars($row['item_name'] ?? '').'</td>
                        <td>'.htmlspecialchars($row['description'] ?? '').'</td>
                        <td>'.htmlspecialchars($row['item_category'] ?? '').'</td>
                        <td>'.htmlspecialchars($row['item_location'] ?? '').'</td>
                        <td>'.htmlspecialchars($row['expiration'] ?? '').'</td>
                        <td>'.htmlspecialchars($row['quantity'] ?? '').'</td>
                        <td>'.htmlspecialchars($row['unit'] ?? '').'</td>
                        <td class="status-'.$statusClass.'">'.htmlspecialchars($row['status'] ?? '').'</td>
                    </tr>';
            }
            
            $html .= '</table>
            <script>
            window.onload = function() {
                setTimeout(function(){
                    window.print();
                    setTimeout(function(){
                        window.close();
                    }, 100);
                }, 100);
            }
            </script>
            </body>
            </html>';
        
            header('Content-Type: text/html');
            echo $html;
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UCGS Inventory | Reports</title>
    <link rel="stylesheet" href="../css/AdminRport.css">
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
                    <li><a href="ItemBorrowed.php">Item Borrow</a></li>
                    <li><a href="ItemReturned.php">Item Returned</a></li>
                </ul>
            </li>
            <li><a href="Reports.php"><img src="../assets/img/reports.png" alt="Reports Icon" class="sidebar-icon"> Reports</a></li>
            <li><a href="UserManagement.php"><img src="../assets/img/user-management.png" alt="User Management Icon" class="sidebar-icon"> User Management</a></li>
        </ul>
    </aside>

    <div class="main-content">
        <!-- Filter Container with Centered Heading -->
        <div class="filter-container">
            <h2 style="width: 100%; margin-bottom: 20px;">Reports</h2>
            
            <div class="search-bar">
                <i class="fas fa-search"></i>
                <input type="text" id="globalSearch" placeholder="Search all items..." onkeyup="filterTable()">
            </div>
            <div class="filter-controls">
                <select id="categoryFilter" onchange="filterTable()">
                    <option value="" selected disabled>Select Category</option>
                    <?php 
                    $categories = [];
                    if ($result->num_rows > 0) {
                        $result->data_seek(0);
                        while ($row = $result->fetch_assoc()) {
                            if (!empty($row['item_category'])) {
                                $categories[$row['item_category']] = $row['item_category'];
                            }
                        }
                        foreach ($categories as $category) {
                            echo '<option value="'.htmlspecialchars($category).'">'.htmlspecialchars($category).'</option>';
                        }
                    }
                    ?>
                </select>
                
                <select id="unitFilter" onchange="filterTable()">
                    <option value="" selected disabled>Select Unit</option>
                    <?php 
                    $units = [];
                    if ($result->num_rows > 0) {
                        $result->data_seek(0);
                        while ($row = $result->fetch_assoc()) {
                            if (!empty($row['unit'])) {
                                $units[$row['unit']] = $row['unit'];
                            }
                        }
                        foreach ($units as $unit) {
                            echo '<option value="'.htmlspecialchars($unit).'">'.htmlspecialchars($unit).'</option>';
                        }
                    }
                    ?>
                </select>
                
                <button class="filter-reset" onclick="resetFilters()">Reset</button>       
            </div>
            <div class="download-options" style="text-align: right; margin-bottom: 15px;">
                <a href="?download=pdf" class="download-pdf">
                    <img src="../assets/FileIcon/pdf.png" alt="PDF Icon" width="20"> PDF
                </a>
                <a href="?download=xlsx" class="download-xlsx">
                    <img src="../assets/FileIcon/xlsx.png" alt="XLSX Icon" width="20"> XLSX
                </a>
            </div>
        

        <table class="report-table">
            <thead>
                <tr>
                    <th>Select All <input type="checkbox" class="select-all" onclick="toggleSelectAll(this)"></th>
                    <th>Last Updated</th>
                    <th>Model No</th>
                    <th>Item Name</th>
                    <th>Description</th>
                    <th>Item Category</th>
                    <th>Item Location</th>
                    <th>Expiration</th>
                    <th>Quantity</th>
                    <th>Unit</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php $result->data_seek(0); ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><input type="checkbox" class="select-checkbox" value="<?php echo htmlspecialchars($row['item_name']); ?>"></td>
                            <td><?php echo htmlspecialchars($row['last_updated']); ?></td>
                            <td><?php echo htmlspecialchars($row['model_no']); ?></td>
                            <td><?php echo htmlspecialchars($row['item_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['description']); ?></td>
                            <td><?php echo htmlspecialchars($row['item_category']); ?></td>
                            <td><?php echo htmlspecialchars($row['item_location']); ?></td>
                            <td><?php echo htmlspecialchars($row['expiration']); ?></td>
                            <td><?php echo htmlspecialchars($row['quantity']); ?></td>
                            <td><?php echo htmlspecialchars($row['unit']); ?></td>
                            <td class="status-<?php echo strtolower(str_replace(' ', '-', $row['status'])); ?>">
                                <?php echo htmlspecialchars($row['status']); ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr class="no-data">
                        <td colspan="11" style="text-align:center; padding: 10px;">No data available</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
</div>
        <div class="pagination">
            <button onclick="prevPage()" id="prev-btn" style="font-family:'Akrobat', sans-serif;">Previous</button>
            <span id="page-number" style="font-family:'Akrobat', sans-serif;">Page 1</span>
            <button onclick="nextPage()" id="next-btn" style="font-family:'Akrobat', sans-serif;">Next</button>
        </div>
    </div>
    

    <!-- Form for selected items -->
    <form id="downloadForm" method="POST" action="">
        <input type="hidden" name="selectedItems" id="selectedItems">
    </form>

    <script src="../js/reports.js"></script>
</body>
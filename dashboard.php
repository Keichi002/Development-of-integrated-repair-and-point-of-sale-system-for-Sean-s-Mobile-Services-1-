<?php
session_start();
include "backend/db_connect.php";

if (!isset($_SESSION['user'])) {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$active_repairs = $conn->query("SELECT COUNT(*) as count FROM repairs WHERE status != 'Completed'")->fetch_assoc()['count'];
$today = date('Y-m-d');
$today_revenue = $conn->query("SELECT SUM(total) as total FROM sales WHERE sale_date = '$today'")->fetch_assoc()['total'] ?? 0;
$low_stock = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE quantity < min_stock")->fetch_assoc()['count'];
$total_transactions = $conn->query("SELECT COUNT(*) as count FROM sales")->fetch_assoc()['count'];

$activities = $conn->query("
    SELECT al.*, u.username 
    FROM activity_log al 
    LEFT JOIN users u ON al.user_id = u.id 
    ORDER BY al.created_at DESC 
    LIMIT 10
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard | Mobile Repair POS</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
  <div class="parent">
    
    <div class="div1">
      <h3>Navigation</h3>
      <a href="dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
      <a href="pages/repairs.php"><i class="fas fa-tools"></i>Repairs</a>
      <a href="pages/reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
      <a href="pages/sales.php"><i class="fas fa-shopping-cart"></i> Sales</a>
      <a href="pages/inventory.php"><i class="fas fa-boxes"></i> Inventory</a>
      <a href="pages/customers.php"><i class="fas fa-users"></i> Customers</a>
      <a href="pages/settings.php"><i class="fas fa-cog"></i> Settings</a>
      <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="div2">
      <h2>Mobile Repair & POS Dashboard</h2>
      <div class="welcome-container">
        <a href="#" class="notification" id="notificationBtn">
          <i class="fa-solid fa-bell"></i>
        </a>
        <span class="highlight-user">Welcome, <?php echo $_SESSION['user']; ?>!</span>
      </div>
    </div>

    <div class="notification-dropdown" id="notificationDropdown">
      <h4>Notifications</h4>
      <ul id="notificationList">
        <?php
        $notifications = [];
        
        $pending_repairs = $conn->query("SELECT COUNT(*) as count FROM repairs WHERE status = 'Pending'")->fetch_assoc()['count'];
        if ($pending_repairs > 0) {
            $notifications[] = "$pending_repairs pending repair" . ($pending_repairs > 1 ? 's' : '');
        }
        
        $low_stock_items = $conn->query("SELECT name, quantity, min_stock FROM inventory WHERE quantity < min_stock LIMIT 3")->fetch_all(MYSQLI_ASSOC);
        foreach ($low_stock_items as $item) {
            $notifications[] = "Low stock: {$item['name']} ({$item['quantity']}/{$item['min_stock']})";
        }
        
        $ready_repairs = $conn->query("SELECT COUNT(*) as count FROM repairs WHERE status = 'For Pickup'")->fetch_assoc()['count'];
        if ($ready_repairs > 0) {
            $notifications[] = "$ready_repairs repair" . ($ready_repairs > 1 ? 's' : '') . " ready for pickup";
        }
        
        if (empty($notifications)) {
            echo '<li>No new notifications</li>';
        } else {
            foreach ($notifications as $notification) {
                echo "<li>$notification</li>";
            }
        }
        ?>
      </ul>
    </div>

    <div class="div3">
      <h2>Overview</h2>
      
      <div class="dashboard-stats">
        <div class="dashboard-stat-card">
          <div class="dashboard-stat-number" id="activeRepairsCount"><?php echo $active_repairs; ?></div>
          <div class="dashboard-stat-label">Active Repairs</div>
        </div>
        <div class="dashboard-stat-card">
          <div class="dashboard-stat-number" id="todayRevenueAmount">₱<?php echo number_format($today_revenue, 2); ?></div>
          <div class="dashboard-stat-label">Today's Revenue</div>
        </div>
        <div class="dashboard-stat-card">
          <div class="dashboard-stat-number" id="lowStockCount"><?php echo $low_stock; ?></div>
          <div class="dashboard-stat-label">Low Stock Items</div>
        </div>
        <div class="dashboard-stat-card">
          <div class="dashboard-stat-number" id="totalTransactionsCount"><?php echo $total_transactions; ?></div>
          <div class="dashboard-stat-label">Total Transactions</div>
        </div>
      </div>

      <div class="recent-activity">
        <h3><i class="fas fa-history"></i> Recent Activity</h3>
        <ul class="activity-list" id="activityList">
          <?php
          if ($activities->num_rows === 0) {
              echo '<li style="color: var(--text-muted); text-align: center; padding: 1rem;">No recent activity</li>';
          } else {
              while ($activity = $activities->fetch_assoc()) {
                  $time_ago = date('H:i', strtotime($activity['created_at']));
                  echo '
                  <li class="activity-item">
                    <div class="activity-icon">
                      <i class="fas fa-info-circle"></i>
                    </div>
                    <div class="activity-content">
                      <div class="activity-title">' . $activity['description'] . '</div>
                      <div class="activity-time">' . $time_ago . ' by ' . $activity['username'] . '</div>
                    </div>
                  </li>';
              }
          }
          ?>
        </ul>
      </div>

      <div class="quick-actions">
        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        <div class="action-buttons">
          <button class="btn-primary" onclick="window.location.href='pages/repairs.php'"><i class="fas fa-plus"></i> New Repair</button>
          <button class="btn-primary" onclick="window.location.href='pages/sales.php'"><i class="fas fa-cash-register"></i> New Sale</button>
          <button class="btn-primary" onclick="window.location.href='pages/customers.php'"><i class="fas fa-user-plus"></i> Add Customer</button>
          <button class="btn-primary" onclick="window.location.href='pages/inventory.php'"><i class="fas fa-box"></i> Add Inventory</button>
        </div>
      </div>
    </div>

  </div>

  <script>
    const notificationBtn = document.getElementById("notificationBtn");
    const notificationDropdown = document.getElementById("notificationDropdown");

    if (notificationBtn && notificationDropdown) {
      notificationBtn.addEventListener("click", function(e) {
        e.preventDefault();
        e.stopPropagation();
        notificationDropdown.classList.toggle("show");
      });

      document.addEventListener("click", function(e) {
        if (!notificationBtn.contains(e.target) && !notificationDropdown.contains(e.target)) {
          notificationDropdown.classList.remove("show");
        }
      });
    }

    function updateNotifications() {
      fetch('backend/get_notifications.php')
        .then(response => response.json())
        .then(data => {
          const notificationList = document.getElementById('notificationList');
          if (data.length === 0) {
            notificationList.innerHTML = '<li>No new notifications</li>';
          } else {
            notificationList.innerHTML = '';
            data.forEach(notif => {
              const li = document.createElement('li');
              li.textContent = notif;
              notificationList.appendChild(li);
            });
          }
        })
        .catch(error => console.error('Error:', error));
    }

    setInterval(updateNotifications, 30000);
  </script>
</body>
</html>
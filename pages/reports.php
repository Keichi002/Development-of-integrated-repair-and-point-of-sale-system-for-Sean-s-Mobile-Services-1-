<?php
session_start();
include "../backend/db_connect.php";

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'daily';

$today = date('Y-m-d');
$today_income = $conn->query("SELECT SUM(total) as total FROM sales WHERE sale_date = '$today'")->fetch_assoc()['total'] ?? 0;
$total_repairs = $conn->query("SELECT COUNT(*) as count FROM repairs")->fetch_assoc()['count'];
$pending_repairs = $conn->query("SELECT COUNT(*) as count FROM repairs WHERE status != 'Completed'")->fetch_assoc()['count'];

$inventory_value = $conn->query("
    SELECT SUM(quantity * price) as total_value, 
           COUNT(CASE WHEN quantity < min_stock THEN 1 END) as low_stock 
    FROM inventory
")->fetch_assoc();

$completed_repairs = $conn->query("SELECT COUNT(*) as count FROM repairs WHERE status = 'Completed'")->fetch_assoc()['count'];
$completion_rate = $total_repairs > 0 ? round(($completed_repairs / $total_repairs) * 100) : 0;

$sales_report = $conn->query("
    SELECT * FROM sales 
    WHERE sale_date BETWEEN '$start_date' AND '$end_date'
    ORDER BY sale_date DESC
")->fetch_all(MYSQLI_ASSOC);

$inventory_report = $conn->query("
    SELECT *, (quantity * price) as total_value FROM inventory 
    ORDER BY category, name
")->fetch_all(MYSQLI_ASSOC);

$category_report = $conn->query("
    SELECT 
        category,
        COUNT(*) as total_items,
        SUM(quantity) as total_quantity,
        SUM(CASE WHEN quantity < min_stock THEN 1 ELSE 0 END) as low_stock_items,
        SUM(quantity * price) as total_value
    FROM inventory 
    GROUP BY category
    ORDER BY category
")->fetch_all(MYSQLI_ASSOC);

$sales_by_day = [];
if ($report_type === 'daily') {
    $result = $conn->query("
        SELECT 
            sale_date as date,
            SUM(total) as total_sales,
            COUNT(*) as transactions
        FROM sales 
        WHERE sale_date BETWEEN '$start_date' AND '$end_date'
        GROUP BY sale_date
        ORDER BY sale_date
    ");
    while ($row = $result->fetch_assoc()) {
        $sales_by_day[] = $row;
    }
}

$repair_stats = $conn->query("
    SELECT 
        status,
        COUNT(*) as count
    FROM repairs 
    GROUP BY status
")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports | Repair POS</title>
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
</head>
<body>
  <div class="page-layout">

    <div class="sidebar">
      <h3>Navigation</h3>
      <a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
      <a href="repairs.php"><i class="fas fa-tools"></i>Repairs</a>
      <a href="reports.php" class="active"><i class="fas fa-chart-bar"></i> Reports</a>
      <a href="sales.php"><i class="fas fa-shopping-cart"></i> Sales</a>
      <a href="inventory.php"><i class="fas fa-boxes"></i> Inventory</a>
      <a href="customers.php"><i class="fas fa-users"></i> Customers</a>
      <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
      <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="main-content">
      <h2>Reports & Analytics</h2>

      <div class="stats-grid">
        <div class="stat-card large">
          <h4>Today's Income</h4>
          <p class="stat-number" id="todayIncome">₱<?php echo number_format($today_income, 2); ?></p>
          <p class="stat-change positive">+0%</p>
        </div>
        <div class="stat-card large">
          <h4>Total Repairs</h4>
          <p class="stat-number" id="totalRepairs"><?php echo $total_repairs; ?></p>
          <p class="stat-change" id="repairsStatus"><?php echo $pending_repairs; ?> pending</p>
        </div>
        <div class="stat-card large">
          <h4>Inventory Value</h4>
          <p class="stat-number" id="inventoryValue">₱<?php echo number_format($inventory_value['total_value'] ?? 0, 2); ?></p>
          <p class="stat-change" id="lowStockStatus"><?php echo $inventory_value['low_stock'] ?? 0; ?> low stock</p>
        </div>
      </div>

      <div class="section">
        <h3><i class="fas fa-chart-line"></i> Sales Reports</h3>
        <canvas id="salesChart" style="max-height: 300px; margin-bottom: 2rem;"></canvas>
        
        <div class="report-filters">
          <form method="GET" style="display: contents;">
            <label for="report-type">Report Type:</label>
            <select id="report-type" name="report_type">
              <option value="daily" <?php echo $report_type === 'daily' ? 'selected' : ''; ?>>Daily Sales</option>
              <option value="weekly" <?php echo $report_type === 'weekly' ? 'selected' : ''; ?>>Weekly Sales</option>
              <option value="monthly" <?php echo $report_type === 'monthly' ? 'selected' : ''; ?>>Monthly Sales</option>
            </select>
            
            <label for="date-from">From:</label>
            <input type="date" id="date-from" name="start_date" value="<?php echo $start_date; ?>">
            
            <label for="date-to">To:</label>
            <input type="date" id="date-to" name="end_date" value="<?php echo $end_date; ?>">
            
            <button type="submit" class="btn-primary"><i class="fas fa-chart-bar"></i> Generate Report</button>
            <button type="button" class="btn-secondary" onclick="exportReportPDF()"><i class="fas fa-file-pdf"></i> Export PDF</button>
          </form>
        </div>

        <table class="inventory-table" id="salesReportTable" style="margin-top: 20px;">
          <thead>
            <tr>
              <th>Date</th>
              <th>Transaction ID</th>
              <th>Customer</th>
              <th>Items</th>
              <th>Total</th>
              <th>Payment</th>
            </tr>
          </thead>
          <tbody id="salesReportBody">
            <?php foreach ($sales_report as $sale): 
              $items = json_decode($sale['items'], true);
              $items_text = '';
              if (is_array($items)) {
                  $items_text = implode(', ', array_map(function($item) {
                      return $item['name'] . ' x' . $item['quantity'];
                  }, $items));
              }
            ?>
            <tr>
              <td><?php echo $sale['sale_date']; ?></td>
              <td><?php echo $sale['transaction_id']; ?></td>
              <td><?php echo htmlspecialchars($sale['customer_name']); ?></td>
              <td><?php echo htmlspecialchars($items_text); ?></td>
              <td>₱<?php echo number_format($sale['total'], 2); ?></td>
              <td><?php echo $sale['payment_method']; ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($sales_report)): ?>
            <tr>
              <td colspan="6" style="text-align: center; color: var(--text-muted);">No sales in selected period</td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="section">
        <h3><i class="fas fa-tools"></i> Repair Performance</h3>
        <canvas id="repairChart" style="max-height: 300px; margin-bottom: 2rem;"></canvas>
        
        <div class="performance-stats">
          <div class="stat-item">
            <span>Total Completed:</span>
            <strong id="completedRepairs"><?php echo $completed_repairs; ?></strong>
          </div>
          <div class="stat-item">
            <span>Pending Repairs:</span>
            <strong id="pendingRepairs"><?php echo $pending_repairs; ?></strong>
          </div>
          <div class="stat-item">
            <span>Completion Rate:</span>
            <strong id="completionRate"><?php echo $completion_rate; ?>%</strong>
          </div>
        </div>
      </div>

      <div class="section">
        <h3><i class="fas fa-boxes"></i> Inventory Reports</h3>
        <div class="inventory-filter">
          <label>Sort by:</label>
          <button class="btn-small" onclick="sortTable('inventoryReportTable', 0)"><i class="fas fa-font"></i> Name</button>
          <button class="btn-small" onclick="sortTable('inventoryReportTable', 2)"><i class="fas fa-arrow-down"></i> Quantity</button>
          <button class="btn-small" onclick="sortTable('inventoryReportTable', 5)"><i class="fas fa-money-bill"></i> Value</button>
        </div>

        <table class="inventory-table" id="inventoryReportTable">
          <thead>
            <tr>
              <th>Item Name</th>
              <th>Category</th>
              <th>Quantity</th>
              <th>Price</th>
              <th>Min Stock</th>
              <th>Total Value</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody id="inventoryReportBody">
            <?php foreach ($inventory_report as $item): 
              $is_low_stock = $item['quantity'] < $item['min_stock'];
            ?>
            <tr>
              <td><?php echo htmlspecialchars($item['name']); ?></td>
              <td><?php echo htmlspecialchars($item['category']); ?></td>
              <td><?php echo $item['quantity']; ?></td>
              <td>₱<?php echo number_format($item['price'], 2); ?></td>
              <td><?php echo $item['min_stock']; ?></td>
              <td>₱<?php echo number_format($item['total_value'], 2); ?></td>
              <td>
                <span class="status-badge <?php echo $is_low_stock ? 'badge-warning' : 'badge-success'; ?>">
                  <?php echo $is_low_stock ? 'Low Stock' : 'In Stock'; ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($inventory_report)): ?>
            <tr>
              <td colspan="7" style="text-align: center; color: var(--text-muted);">No inventory items</td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>

        <div class="section" style="margin-top: 20px;">
          <h3><i class="fas fa-chart-pie"></i> Inventory Summary by Category</h3>
          <table class="inventory-table">
            <thead>
              <tr>
                <th>Category</th>
                <th>Total Items</th>
                <th>Total Quantity</th>
                <th>Low Stock Items</th>
                <th>Total Value</th>
              </tr>
            </thead>
            <tbody id="categoryReportBody">
              <?php foreach ($category_report as $category): ?>
              <tr>
                <td><?php echo htmlspecialchars($category['category']); ?></td>
                <td><?php echo $category['total_items']; ?></td>
                <td><?php echo $category['total_quantity']; ?></td>
                <td><?php echo $category['low_stock_items']; ?></td>
                <td>₱<?php echo number_format($category['total_value'], 2); ?></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($category_report)): ?>
              <tr>
                <td colspan="5" style="text-align: center; color: var(--text-muted);">No inventory categories</td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>

  <script>
    let salesChart = null;
    let repairChart = null;

    function createSalesChart() {
      const salesData = <?php echo json_encode($sales_by_day); ?>;
      const labels = salesData.map(item => item.date);
      const data = salesData.map(item => parseFloat(item.total_sales) || 0);

      if (salesChart) {
        salesChart.destroy();
      }

      const ctx = document.getElementById('salesChart');
      if (ctx && labels.length > 0) {
        salesChart = new Chart(ctx, {
          type: 'line',
          data: {
            labels: labels,
            datasets: [{
              label: 'Daily Sales',
              data: data,
              borderColor: '#475569',
              backgroundColor: 'rgba(71, 85, 105, 0.1)',
              borderWidth: 2,
              tension: 0.4,
              fill: true,
              pointBackgroundColor: '#475569',
              pointBorderColor: '#1e293b',
              pointBorderWidth: 2,
              pointRadius: 5,
              pointHoverRadius: 7
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
              legend: {
                display: true,
                labels: {
                  color: '#cbd5e1',
                  font: { size: 12 }
                }
              }
            },
            scales: {
              y: {
                beginAtZero: true,
                ticks: {
                  color: '#cbd5e1',
                  callback: function(value) {
                    return '₱' + value.toLocaleString();
                  }
                },
                grid: {
                  color: 'rgba(71, 85, 105, 0.1)'
                }
              },
              x: {
                ticks: {
                  color: '#cbd5e1'
                },
                grid: {
                  color: 'rgba(71, 85, 105, 0.1)'
                }
              }
            }
          }
        });
      } else {
        ctx.style.display = 'none';
      }
    }

    function createRepairChart() {
      const repairData = <?php echo json_encode($repair_stats); ?>;
      
      const labels = [];
      const data = [];
      const backgroundColors = {
        'Pending': '#ef4444',
        'In Progress': '#f97316',
        'For Pickup': '#eab308',
        'Completed': '#22c55e'
      };
      
      const colors = [];
      
      repairData.forEach(item => {
        labels.push(item.status);
        data.push(parseInt(item.count));
        colors.push(backgroundColors[item.status] || '#475569');
      });

      if (repairChart) {
        repairChart.destroy();
      }

      const ctx = document.getElementById('repairChart');
      if (ctx && labels.length > 0) {
        repairChart = new Chart(ctx, {
          type: 'doughnut',
          data: {
            labels: labels,
            datasets: [{
              data: data,
              backgroundColor: colors,
              borderColor: '#1e293b',
              borderWidth: 2
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
              legend: {
                display: true,
                position: 'bottom',
                labels: {
                  color: '#cbd5e1',
                  font: { size: 12 },
                  padding: 15
                }
              }
            }
          }
        });
      } else {
        ctx.style.display = 'none';
      }
    }

    function sortTable(tableId, column) {
      const table = document.getElementById(tableId);
      const tbody = table.querySelector('tbody');
      const rows = Array.from(tbody.querySelectorAll('tr'));
      
      rows.sort((a, b) => {
        const aText = a.cells[column].textContent;
        const bText = b.cells[column].textContent;
        
        if (column === 2 || column === 5) {
          const aNum = parseFloat(aText.replace(/[^0-9.-]+/g, ''));
          const bNum = parseFloat(bText.replace(/[^0-9.-]+/g, ''));
          return aNum - bNum;
        }
        
        return aText.localeCompare(bText);
      });
      
      rows.forEach(row => tbody.appendChild(row));
    }

    function exportReportPDF() {
      const content = document.getElementById('salesReportTable').outerHTML;
      const printWindow = window.open('', '_blank');
      printWindow.document.write(`
        <html>
        <head>
          <title>Sales Report</title>
          <style>
            body { font-family: Arial; padding: 20px; }
            table { border-collapse: collapse; width: 100%; margin-top: 20px; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background-color: #1e293b; color: white; }
            h2 { color: #1e293b; }
            .header { display: flex; justify-content: space-between; margin-bottom: 20px; }
            .date-range { color: #666; }
          </style>
        </head>
        <body>
          <div class="header">
            <h2>Sales Report</h2>
            <div class="date-range">
              Period: <?php echo $start_date; ?> to <?php echo $end_date; ?>
            </div>
          </div>
          ${content}
          <script>
            window.onload = function() {
              window.print();
            };
          <\/script>
        </body>
        </html>
      `);
      printWindow.document.close();
    }

    window.addEventListener('load', () => {
      createSalesChart();
      createRepairChart();
    });
  </script>
</body>
</html>
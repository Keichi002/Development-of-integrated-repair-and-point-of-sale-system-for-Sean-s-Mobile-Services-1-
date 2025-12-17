<?php
session_start();
include "../backend/db_connect.php";

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_inventory'])) {
        $name = sanitize($_POST['name']);
        $category = sanitize($_POST['category']);
        $quantity = $_POST['quantity'];
        $min_stock = $_POST['min_stock'];
        $price = $_POST['price'];
        $date_added = date('Y-m-d');
        
        $stmt = $conn->prepare("INSERT INTO inventory (name, category, quantity, min_stock, price, date_added) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssiids", $name, $category, $quantity, $min_stock, $price, $date_added);
        
        if ($stmt->execute()) {
            $item_id = $stmt->insert_id;
            $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity_type, description) VALUES (?, 'inventory_added', ?)");
            $desc = "Added inventory: $name (Qty: $quantity)";
            $log_stmt->bind_param("is", $user_id, $desc);
            $log_stmt->execute();
            $success_message = "Inventory item added successfully!";
        } else {
            $error_message = "Error adding inventory: " . $stmt->error;
        }
    }
    
    if (isset($_POST['update_inventory'])) {
        $id = $_POST['id'];
        $name = sanitize($_POST['name']);
        $category = sanitize($_POST['category']);
        $quantity = $_POST['quantity'];
        $min_stock = $_POST['min_stock'];
        $price = $_POST['price'];
        
        $stmt = $conn->prepare("UPDATE inventory SET name = ?, category = ?, quantity = ?, min_stock = ?, price = ? WHERE id = ?");
        $stmt->bind_param("ssiidi", $name, $category, $quantity, $min_stock, $price, $id);
        
        if ($stmt->execute()) {
            $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity_type, description) VALUES (?, 'inventory_updated', ?)");
            $desc = "Updated inventory #$id: $name";
            $log_stmt->bind_param("is", $user_id, $desc);
            $log_stmt->execute();
            $success_message = "Inventory item updated successfully!";
        } else {
            $error_message = "Error updating inventory";
        }
    }
    
    if (isset($_POST['restock'])) {
        $id = $_POST['id'];
        $add_quantity = $_POST['add_quantity'];
        
        $stmt = $conn->prepare("UPDATE inventory SET quantity = quantity + ? WHERE id = ?");
        $stmt->bind_param("ii", $add_quantity, $id);
        
        if ($stmt->execute()) {
            $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity_type, description) VALUES (?, 'inventory_restocked', ?)");
            $desc = "Restocked inventory #$id by $add_quantity units";
            $log_stmt->bind_param("is", $user_id, $desc);
            $log_stmt->execute();
            $success_message = "Item restocked successfully!";
        } else {
            $error_message = "Error restocking item";
        }
    }
    
    if (isset($_POST['delete_inventory'])) {
        $id = $_POST['id'];
        
        $stmt = $conn->prepare("DELETE FROM inventory WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity_type, description) VALUES (?, 'inventory_deleted', ?)");
            $desc = "Deleted inventory #$id";
            $log_stmt->bind_param("is", $user_id, $desc);
            $log_stmt->execute();
            $success_message = "Inventory item deleted successfully!";
        } else {
            $error_message = "Error deleting item";
        }
    }
}

$inventory_items = $conn->query("SELECT * FROM inventory ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$total_items = 0;
$total_value = 0;
$low_stock_count = 0;

foreach ($inventory_items as $item) {
    $total_items += $item['quantity'];
    $total_value += $item['quantity'] * $item['price'];
    if ($item['quantity'] < $item['min_stock']) {
        $low_stock_count++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inventory | Repair POS</title>
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
  <div class="page-layout">

    <div class="sidebar">
      <h3>Navigation</h3>
      <a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
      <a href="repairs.php"><i class="fas fa-tools"></i>Repairs</a>
      <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
      <a href="sales.php"><i class="fas fa-shopping-cart"></i> Sales</a>
      <a href="inventory.php" class="active"><i class="fas fa-boxes"></i> Inventory</a>
      <a href="customers.php"><i class="fas fa-users"></i> Customers</a>
      <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
      <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="main-content">
      <h2>Inventory Management</h2>

      <?php if (isset($success_message)): ?>
        <div style="background: var(--success); color: white; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
          <?php echo $success_message; ?>
        </div>
      <?php endif; ?>
      
      <?php if (isset($error_message)): ?>
        <div style="background: var(--danger); color: white; padding: 10px; border-radius: 5px; margin-bottom: 15px;">
          <?php echo $error_message; ?>
        </div>
      <?php endif; ?>

      <div class="alert-section" id="alertSection">
        <?php if ($low_stock_count > 0): ?>
        <div class="alert alert-warning">
          <strong><i class="fas fa-exclamation-triangle"></i> Low Stock Alert:</strong> <?php echo $low_stock_count; ?> items need restocking
        </div>
        <?php endif; ?>
      </div>

      <div class="section">
        <h3><i class="fas fa-tools"></i> Replacement Parts</h3>
        <div class="inventory-stats">
          <div class="stat-card">
            <h4>Total Items</h4>
            <p class="stat-number" id="totalItems"><?php echo $total_items; ?></p>
          </div>
          <div class="stat-card">
            <h4>Low Stock</h4>
            <p class="stat-number" id="lowStockItems"><?php echo $low_stock_count; ?></p>
          </div>
          <div class="stat-card">
            <h4>Total Value</h4>
            <p class="stat-number" id="totalValue">₱<?php echo number_format($total_value, 2); ?></p>
          </div>
        </div>

        <table class="inventory-table">
          <thead>
            <tr>
              <th>Component Name</th>
              <th>Category</th>
              <th>Quantity</th>
              <th>Min Stock</th>
              <th>Price</th>
              <th>Value</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="inventoryTable">
            <?php foreach ($inventory_items as $item): ?>
            <?php
            $item_value = $item['quantity'] * $item['price'];
            $is_low_stock = $item['quantity'] < $item['min_stock'];
            ?>
            <tr class="<?php echo $is_low_stock ? 'low-stock' : ''; ?>">
              <td><?php echo htmlspecialchars($item['name']); ?></td>
              <td><?php echo htmlspecialchars($item['category']); ?></td>
              <td><?php echo $item['quantity']; ?></td>
              <td><?php echo $item['min_stock']; ?></td>
              <td>₱<?php echo number_format($item['price'], 2); ?></td>
              <td>₱<?php echo number_format($item_value, 2); ?></td>
              <td>
                <span class="status-badge <?php echo $is_low_stock ? 'badge-warning' : 'badge-success'; ?>">
                  <?php echo $is_low_stock ? 'Low' : 'Good'; ?>
                </span>
              </td>
              <td>
                <button class="btn-small" onclick="openEditModal(<?php echo $item['id']; ?>, '<?php echo addslashes($item['name']); ?>', '<?php echo $item['category']; ?>', <?php echo $item['quantity']; ?>, <?php echo $item['min_stock']; ?>, <?php echo $item['price']; ?>)">
                  <i class="fas fa-edit"></i> Edit
                </button>
                <button class="btn-small" onclick="openRestockModal(<?php echo $item['id']; ?>, '<?php echo addslashes($item['name']); ?>', <?php echo $item['quantity']; ?>)">
                  <i class="fas fa-plus"></i> Restock
                </button>
                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this item?');">
                  <input type="hidden" name="id" value="<?php echo $item['id']; ?>">
                  <input type="hidden" name="delete_inventory" value="1">
                  <button type="submit" class="btn-small btn-danger"><i class="fas fa-trash"></i> Delete</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($inventory_items)): ?>
            <tr>
              <td colspan="8" style="text-align: center; color: var(--text-muted);">No inventory items</td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="section">
        <h3><i class="fas fa-plus-circle"></i> Add New Part</h3>
        <form class="quick-add-form" method="POST">
          <div class="form-row">
            <div class="form-group">
              <label>Part Name *</label>
              <input type="text" name="name" placeholder="e.g., iPhone 13 Screen" required>
            </div>
            <div class="form-group">
              <label>Category *</label>
              <select name="category" required>
                <option value="">Select Category</option>
                <option value="Screens">Screens</option>
                <option value="Batteries">Batteries</option>
                <option value="Components">Components</option>
                <option value="Housings">Housings</option>
                <option value="Accessories">Accessories</option>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Quantity *</label>
              <input type="number" name="quantity" value="1" min="1" required>
            </div>
            <div class="form-group">
              <label>Price (₱) *</label>
              <input type="number" name="price" placeholder="0.00" min="0" step="0.01" required>
            </div>
            <div class="form-group">
              <label>Min Stock Level *</label>
              <input type="number" name="min_stock" value="5" min="1" required>
            </div>
          </div>
          <button type="submit" class="btn-primary" name="add_inventory" value="1"><i class="fas fa-save"></i> Add to Inventory</button>
        </form>
      </div>

      <div id="editModal" class="modal" style="display: none;">
        <div class="modal-content">
          <span class="close" onclick="closeEditModal()">&times;</span>
          <h3><i class="fas fa-edit"></i> Edit Inventory Item</h3>
          <form method="POST" id="editForm">
            <input type="hidden" name="id" id="editId">
            <input type="hidden" name="update_inventory" value="1">
            <div class="form-group">
              <label>Part Name</label>
              <input type="text" id="editName" name="name" required>
            </div>
            <div class="form-group">
              <label>Category</label>
              <select id="editCategory" name="category" required>
                <option value="Screens">Screens</option>
                <option value="Batteries">Batteries</option>
                <option value="Components">Components</option>
                <option value="Housings">Housings</option>
                <option value="Accessories">Accessories</option>
              </select>
            </div>
            <div class="form-group">
              <label>Quantity</label>
              <input type="number" id="editQuantity" name="quantity" min="0" required>
            </div>
            <div class="form-group">
              <label>Price (₱)</label>
              <input type="number" id="editPrice" name="price" min="0" step="0.01" required>
            </div>
            <div class="form-group">
              <label>Min Stock Level</label>
              <input type="number" id="editMinStock" name="min_stock" min="1" required>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Update</button>
              <button type="button" class="btn-secondary" onclick="closeEditModal()"><i class="fas fa-times"></i> Cancel</button>
            </div>
          </form>
        </div>
      </div>

      <div id="restockModal" class="modal" style="display: none;">
        <div class="modal-content">
          <span class="close" onclick="closeRestockModal()">&times;</span>
          <h3><i class="fas fa-plus"></i> Restock Item</h3>
          <form method="POST" id="restockForm">
            <input type="hidden" name="id" id="restockId">
            <input type="hidden" name="restock" value="1">
            <div class="form-group">
              <label>Item: <strong id="restockItemName"></strong></label>
            </div>
            <div class="form-group">
              <label>Current Quantity: <strong id="restockCurrentQty"></strong></label>
            </div>
            <div class="form-group">
              <label>Quantity to Add *</label>
              <input type="number" name="add_quantity" id="restockQuantity" min="1" placeholder="Enter quantity" required>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Restock</button>
              <button type="button" class="btn-secondary" onclick="closeRestockModal()"><i class="fas fa-times"></i> Cancel</button>
            </div>
          </form>
        </div>
      </div>
    </div>

  </div>

  <script>
    function openEditModal(id, name, category, quantity, minStock, price) {
      document.getElementById('editId').value = id;
      document.getElementById('editName').value = name;
      document.getElementById('editCategory').value = category;
      document.getElementById('editQuantity').value = quantity;
      document.getElementById('editMinStock').value = minStock;
      document.getElementById('editPrice').value = price;
      document.getElementById('editModal').style.display = 'block';
    }

    function closeEditModal() {
      document.getElementById('editModal').style.display = 'none';
    }

    function openRestockModal(id, name, currentQty) {
      document.getElementById('restockId').value = id;
      document.getElementById('restockItemName').textContent = name;
      document.getElementById('restockCurrentQty').textContent = currentQty;
      document.getElementById('restockQuantity').value = '';
      document.getElementById('restockModal').style.display = 'block';
    }

    function closeRestockModal() {
      document.getElementById('restockModal').style.display = 'none';
    }
  </script>
</body>
</html>
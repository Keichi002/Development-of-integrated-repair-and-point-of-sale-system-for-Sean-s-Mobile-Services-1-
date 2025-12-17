<?php
session_start();
include "../backend/db_connect.php";

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_customer'])) {
        $name = sanitize($_POST['name']);
        $contact = sanitize($_POST['contact']);
        $email = sanitize($_POST['email']);
        $address = sanitize($_POST['address']);
        
        $stmt = $conn->prepare("INSERT INTO customers (name, contact, email, address) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $name, $contact, $email, $address);
        
        if ($stmt->execute()) {
            $customer_id = $stmt->insert_id;
            $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity_type, description) VALUES (?, 'customer_added', ?)");
            $desc = "Added customer: $name";
            $log_stmt->bind_param("is", $user_id, $desc);
            $log_stmt->execute();
            $success_message = "Customer added successfully!";
        } else {
            $error_message = "Error adding customer: " . $stmt->error;
        }
    }
    
    if (isset($_POST['update_customer'])) {
        $id = $_POST['id'];
        $name = sanitize($_POST['name']);
        $contact = sanitize($_POST['contact']);
        $email = sanitize($_POST['email']);
        $address = sanitize($_POST['address']);
        
        $stmt = $conn->prepare("UPDATE customers SET name = ?, contact = ?, email = ?, address = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $name, $contact, $email, $address, $id);
        
        if ($stmt->execute()) {
            $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity_type, description) VALUES (?, 'customer_updated', ?)");
            $desc = "Updated customer #$id: $name";
            $log_stmt->bind_param("is", $user_id, $desc);
            $log_stmt->execute();
            $success_message = "Customer updated successfully!";
        } else {
            $error_message = "Error updating customer";
        }
    }
    
    if (isset($_POST['delete_customer'])) {
        $id = $_POST['id'];
        
        $stmt = $conn->prepare("DELETE FROM customers WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity_type, description) VALUES (?, 'customer_deleted', ?)");
            $desc = "Deleted customer #$id";
            $log_stmt->bind_param("is", $user_id, $desc);
            $log_stmt->execute();
            $success_message = "Customer deleted successfully!";
        } else {
            $error_message = "Error deleting customer";
        }
    }
}

$customers = $conn->query("SELECT * FROM customers ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$selected_customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;
$customer_history = [];
if ($selected_customer_id > 0) {
    $customer = $conn->query("SELECT * FROM customers WHERE id = $selected_customer_id")->fetch_assoc();
    if ($customer) {
        $customer_name = $customer['name'];
        
        $repair_history = $conn->query("SELECT * FROM repairs WHERE customer_name = '$customer_name' ORDER BY date_received DESC")->fetch_all(MYSQLI_ASSOC);
        $sale_history = $conn->query("SELECT * FROM sales WHERE customer_name = '$customer_name' ORDER BY sale_date DESC")->fetch_all(MYSQLI_ASSOC);
        
        $customer_history = array_merge($repair_history, $sale_history);
        
        usort($customer_history, function($a, $b) {
            $date_a = isset($a['date_received']) ? $a['date_received'] : $a['sale_date'];
            $date_b = isset($b['date_received']) ? $b['date_received'] : $b['sale_date'];
            return strtotime($date_b) - strtotime($date_a);
        });
        
        $total_spent = $conn->query("
            SELECT 
                (SELECT COALESCE(SUM(amount), 0) FROM repairs WHERE customer_name = '$customer_name') +
                (SELECT COALESCE(SUM(total), 0) FROM sales WHERE customer_name = '$customer_name') as total
        ")->fetch_assoc()['total'];
        
        $update_stmt = $conn->prepare("UPDATE customers SET total_spent = ? WHERE id = ?");
        $update_stmt->bind_param("di", $total_spent, $selected_customer_id);
        $update_stmt->execute();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customers | Repair POS</title>
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
      <a href="inventory.php"><i class="fas fa-boxes"></i> Inventory</a>
      <a href="customers.php" class="active"><i class="fas fa-users"></i> Customers</a>
      <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
      <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="main-content">
      <h2>Customer Management</h2>
      
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
      
      <div class="section">
        <h3><i class="fas fa-users"></i> Customer Records</h3>
        <table class="customer-table">
          <thead>
            <tr>
              <th>Name</th>
              <th>Contact</th>
              <th>Email</th>
              <th>Address</th>
              <th>Total Spent</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($customers as $customer): ?>
            <tr>
              <td><?php echo htmlspecialchars($customer['name']); ?></td>
              <td><?php echo htmlspecialchars($customer['contact']); ?></td>
              <td><?php echo htmlspecialchars($customer['email']); ?></td>
              <td><?php echo htmlspecialchars($customer['address']); ?></td>
              <td>₱<?php echo number_format($customer['total_spent'], 2); ?></td>
              <td>
                <button class="btn-small" onclick="window.location.href='?customer_id=<?php echo $customer['id']; ?>'">
                  <i class="fas fa-history"></i> History
                </button>
                <button class="btn-small" onclick="openEditCustomerModal(<?php echo $customer['id']; ?>, '<?php echo addslashes($customer['name']); ?>', '<?php echo addslashes($customer['contact']); ?>', '<?php echo addslashes($customer['email']); ?>', '<?php echo addslashes($customer['address']); ?>')">
                  <i class="fas fa-edit"></i> Edit
                </button>
                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this customer?');">
                  <input type="hidden" name="id" value="<?php echo $customer['id']; ?>">
                  <input type="hidden" name="delete_customer" value="1">
                  <button type="submit" class="btn-small btn-danger"><i class="fas fa-trash"></i> Delete</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($customers)): ?>
            <tr>
              <td colspan="6" style="text-align: center; color: var(--text-muted);">No customers found</td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($selected_customer_id > 0 && isset($customer)): ?>
      <div class="section">
        <h3><i class="fas fa-tools"></i> Customer History - <?php echo htmlspecialchars($customer['name']); ?></h3>
        <div class="customer-details">
          <div style="margin-bottom: 20px;">
            <p><strong>Contact:</strong> <?php echo htmlspecialchars($customer['contact']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($customer['email']); ?></p>
            <p><strong>Address:</strong> <?php echo htmlspecialchars($customer['address']); ?></p>
            <p><strong>Total Spent:</strong> ₱<?php echo number_format($customer['total_spent'], 2); ?></p>
          </div>
          
          <h4>Transaction History</h4>
          <table class="repair-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Description</th>
                <th>Amount</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($customer_history as $history): 
                if (isset($history['device_name'])): // Repair
              ?>
              <tr>
                <td><?php echo $history['date_received']; ?></td>
                <td><span class="status-badge" style="background: var(--warning);">Repair</span></td>
                <td><?php echo htmlspecialchars($history['device_name']) . ' - ' . htmlspecialchars($history['service_type']); ?></td>
                <td>₱<?php echo number_format($history['amount'], 2); ?></td>
                <td><?php echo $history['status']; ?></td>
              </tr>
              <?php else: // Sale ?>
              <tr>
                <td><?php echo $history['sale_date']; ?></td>
                <td><span class="status-badge" style="background: var(--success);">Sale</span></td>
                <td>
                  <?php 
                  $items = json_decode($history['items'], true);
                  if (is_array($items)) {
                      echo implode(', ', array_map(function($item) {
                          return $item['name'] . ' x' . $item['quantity'];
                      }, $items));
                  }
                  ?>
                </td>
                <td>₱<?php echo number_format($history['total'], 2); ?></td>
                <td>Completed</td>
              </tr>
              <?php endif; ?>
              <?php endforeach; ?>
              <?php if (empty($customer_history)): ?>
              <tr>
                <td colspan="5" style="text-align: center; color: var(--text-muted);">No history found</td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

      <button class="add-btn" onclick="openAddCustomerModal()"><i class="fas fa-user-plus"></i> Add New Customer</button>
    </div>

  </div>

  <div id="addCustomerModal" class="modal" style="display: none;">
    <div class="modal-content">
      <span class="close" onclick="closeAddCustomerModal()">&times;</span>
      <h3><i class="fas fa-user-plus"></i> Add New Customer</h3>
      <form method="POST" id="addCustomerForm">
        <div class="form-group">
          <label>Full Name *</label>
          <input type="text" name="name" placeholder="Enter customer name" required>
        </div>
        <div class="form-group">
          <label>Contact Number</label>
          <input type="text" name="contact" placeholder="Enter contact number">
        </div>
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" name="email" placeholder="Enter email address">
        </div>
        <div class="form-group">
          <label>Address</label>
          <textarea name="address" placeholder="Enter address" rows="3"></textarea>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-primary" name="add_customer" value="1"><i class="fas fa-save"></i> Add Customer</button>
          <button type="button" class="btn-secondary" onclick="closeAddCustomerModal()"><i class="fas fa-times"></i> Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <div id="editCustomerModal" class="modal" style="display: none;">
    <div class="modal-content">
      <span class="close" onclick="closeEditCustomerModal()">&times;</span>
      <h3><i class="fas fa-edit"></i> Edit Customer</h3>
      <form method="POST" id="editCustomerForm">
        <input type="hidden" name="id" id="editCustomerId">
        <input type="hidden" name="update_customer" value="1">
        <div class="form-group">
          <label>Full Name *</label>
          <input type="text" name="name" id="editCustomerName" required>
        </div>
        <div class="form-group">
          <label>Contact Number</label>
          <input type="text" name="contact" id="editCustomerContact">
        </div>
        <div class="form-group">
          <label>Email Address</label>
          <input type="email" name="email" id="editCustomerEmail">
        </div>
        <div class="form-group">
          <label>Address</label>
          <textarea name="address" id="editCustomerAddress" rows="3"></textarea>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Update Customer</button>
          <button type="button" class="btn-secondary" onclick="closeEditCustomerModal()"><i class="fas fa-times"></i> Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openAddCustomerModal() {
      document.getElementById('addCustomerModal').style.display = 'block';
      document.getElementById('addCustomerForm').reset();
    }

    function closeAddCustomerModal() {
      document.getElementById('addCustomerModal').style.display = 'none';
    }

    function openEditCustomerModal(id, name, contact, email, address) {
      document.getElementById('editCustomerId').value = id;
      document.getElementById('editCustomerName').value = name;
      document.getElementById('editCustomerContact').value = contact;
      document.getElementById('editCustomerEmail').value = email;
      document.getElementById('editCustomerAddress').value = address;
      document.getElementById('editCustomerModal').style.display = 'block';
    }

    function closeEditCustomerModal() {
      document.getElementById('editCustomerModal').style.display = 'none';
    }
  </script>
</body>
</html>
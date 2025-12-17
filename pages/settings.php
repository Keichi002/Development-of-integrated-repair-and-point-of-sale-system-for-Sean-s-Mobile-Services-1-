<?php
session_start();
include "../backend/db_connect.php";

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$settings_file = '../settings.json';
if (file_exists($settings_file)) {
    $settings = json_decode(file_get_contents($settings_file), true);
} else {
    $settings = [
        'store_name' => 'Mobile Repair POS',
        'currency' => 'PHP',
        'tax_rate' => 8.5
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_settings'])) {
        $store_name = sanitize($_POST['store_name']);
        $currency = sanitize($_POST['currency']);
        $tax_rate = $_POST['tax_rate'];
        
        $settings = [
            'store_name' => $store_name,
            'currency' => $currency,
            'tax_rate' => $tax_rate,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        file_put_contents($settings_file, json_encode($settings, JSON_PRETTY_PRINT));
        
        $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity_type, description) VALUES (?, 'settings_updated', ?)");
        $desc = "Updated system settings";
        $log_stmt->bind_param("is", $user_id, $desc);
        $log_stmt->execute();
        
        $success_message = "Settings updated successfully!";
    }
    
    if (isset($_POST['add_user'])) {
        $username = sanitize($_POST['username']);
        $password = sanitize($_POST['password']);
        $role = sanitize($_POST['role']);
        
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check_stmt->bind_param("s", $username);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "Username already exists!";
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $username, $hashed_password, $role);
            
            if ($stmt->execute()) {
                $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity_type, description) VALUES (?, 'user_added', ?)");
                $desc = "Added new user: $username";
                $log_stmt->bind_param("is", $user_id, $desc);
                $log_stmt->execute();
                $success_message = "User added successfully!";
            } else {
                $error_message = "Error adding user: " . $stmt->error;
            }
        }
    }
    
    if (isset($_POST['delete_user'])) {
        $delete_id = $_POST['delete_id'];
        
        if ($delete_id == $user_id) {
            $error_message = "Cannot delete your own account!";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $delete_id);
            
            if ($stmt->execute()) {
                $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity_type, description) VALUES (?, 'user_deleted', ?)");
                $desc = "Deleted user #$delete_id";
                $log_stmt->bind_param("is", $user_id, $desc);
                $log_stmt->execute();
                $success_message = "User deleted successfully!";
            } else {
                $error_message = "Error deleting user";
            }
        }
    }
    
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match!";
        } else {
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
            
            if (password_verify($current_password, $user['password'])) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $update_stmt->bind_param("si", $hashed_password, $user_id);
                
                if ($update_stmt->execute()) {
                    $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity_type, description) VALUES (?, 'password_changed', ?)");
                    $desc = "Changed password";
                    $log_stmt->bind_param("is", $user_id, $desc);
                    $log_stmt->execute();
                    $success_message = "Password changed successfully!";
                } else {
                    $error_message = "Error changing password";
                }
            } else {
                $error_message = "Current password is incorrect!";
            }
        }
    }
}

$users = $conn->query("SELECT id, username, role, created_at FROM users ORDER BY username")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Settings | Repair POS</title>
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
      <a href="customers.php"><i class="fas fa-users"></i> Customers</a>
      <a href="settings.php" class="active"><i class="fas fa-cog"></i> Settings</a>
      <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="main-content">
      <h2>System Settings</h2>
      <p>Adjust system configurations, user accounts, and preferences.</p>
      
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
        <h3><i class="fas fa-store"></i> Store Settings</h3>
        <form method="POST" class="settings-form">
          <div class="form-group">
            <label for="store-name">Store Name:</label>
            <input type="text" id="store-name" name="store_name" value="<?php echo htmlspecialchars($settings['store_name']); ?>">
          </div>
          
          <div class="form-group">
            <label for="currency">Currency:</label>
            <select id="currency" name="currency">
              <option value="PHP" <?php echo $settings['currency'] === 'PHP' ? 'selected' : ''; ?>>PHP - Philippine Peso</option>
              <option value="USD" <?php echo $settings['currency'] === 'USD' ? 'selected' : ''; ?>>USD - US Dollar</option>
              <option value="EUR" <?php echo $settings['currency'] === 'EUR' ? 'selected' : ''; ?>>EUR - Euro</option>
            </select>
          </div>
          
          <div class="form-group">
            <label for="tax-rate">Tax Rate (%):</label>
            <input type="number" id="tax-rate" name="tax_rate" value="<?php echo $settings['tax_rate']; ?>" step="0.1" min="0" max="50">
          </div>
          
          <button type="submit" class="btn-primary" name="update_settings" value="1"><i class="fas fa-save"></i> Save Settings</button>
        </form>
      </div>
      
      <div class="section">
        <h3><i class="fas fa-user-cog"></i> User Accounts</h3>
        <table class="inventory-table">
          <thead>
            <tr>
              <th>Username</th>
              <th>Role</th>
              <th>Created</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
              <td><?php echo htmlspecialchars($user['username']); ?></td>
              <td>
                <span class="status-badge <?php echo $user['role'] === 'admin' ? 'badge-warning' : 'badge-success'; ?>">
                  <?php echo ucfirst($user['role']); ?>
                </span>
              </td>
              <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
              <td>
                <?php if ($user['id'] != $user_id): ?>
                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete user <?php echo htmlspecialchars($user['username']); ?>?');">
                  <input type="hidden" name="delete_id" value="<?php echo $user['id']; ?>">
                  <input type="hidden" name="delete_user" value="1">
                  <button type="submit" class="btn-small btn-danger"><i class="fas fa-trash"></i> Delete</button>
                </form>
                <?php else: ?>
                <span class="status-badge">Current User</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        
        <button class="add-btn" onclick="openAddUserModal()"><i class="fas fa-user-plus"></i> Add New User</button>
      </div>

      <div class="section">
        <h3><i class="fas fa-key"></i> Change Password</h3>
        <form method="POST" class="settings-form">
          <div class="form-group">
            <label for="current_password">Current Password:</label>
            <input type="password" id="current_password" name="current_password" required>
          </div>
          
          <div class="form-group">
            <label for="new_password">New Password:</label>
            <input type="password" id="new_password" name="new_password" required minlength="6">
          </div>
          
          <div class="form-group">
            <label for="confirm_password">Confirm New Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
          </div>
          
          <button type="submit" class="btn-primary" name="change_password" value="1"><i class="fas fa-key"></i> Change Password</button>
        </form>
      </div>

      <div class="section">
        <h3><i class="fas fa-database"></i> Database Information</h3>
        <div style="background: var(--card-bg); padding: 1.5rem; border-radius: 8px;">
          <?php
          $total_customers = $conn->query("SELECT COUNT(*) as count FROM customers")->fetch_assoc()['count'];
          $total_repairs_db = $conn->query("SELECT COUNT(*) as count FROM repairs")->fetch_assoc()['count'];
          $total_sales_db = $conn->query("SELECT COUNT(*) as count FROM sales")->fetch_assoc()['count'];
          $total_inventory = $conn->query("SELECT COUNT(*) as count FROM inventory")->fetch_assoc()['count'];
          ?>
          <p><strong>Total Customers:</strong> <?php echo $total_customers; ?></p>
          <p><strong>Total Repairs:</strong> <?php echo $total_repairs_db; ?></p>
          <p><strong>Total Sales:</strong> <?php echo $total_sales_db; ?></p>
          <p><strong>Total Inventory Items:</strong> <?php echo $total_inventory; ?></p>
          <p><strong>Database:</strong> repair_pos</p>
          <p><strong>Last Updated:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
          
          <button class="btn-secondary" style="margin-top: 10px;" onclick="backupDatabase()">
            <i class="fas fa-download"></i> Backup Database
          </button>
        </div>
      </div>
    </div>

  </div>

  <div id="addUserModal" class="modal" style="display: none;">
    <div class="modal-content">
      <span class="close" onclick="closeAddUserModal()">&times;</span>
      <h3><i class="fas fa-user-plus"></i> Add New User</h3>
      <form method="POST" id="addUserForm">
        <div class="form-group">
          <label>Username *</label>
          <input type="text" name="username" placeholder="Enter username" required>
        </div>
        <div class="form-group">
          <label>Password *</label>
          <input type="password" name="password" placeholder="Enter password" required minlength="6">
        </div>
        <div class="form-group">
          <label>Role *</label>
          <select name="role" required>
            <option value="staff">Staff</option>
            <option value="admin">Administrator</option>
            <option value="technician">Technician</option>
          </select>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn-primary" name="add_user" value="1"><i class="fas fa-save"></i> Add User</button>
          <button type="button" class="btn-secondary" onclick="closeAddUserModal()"><i class="fas fa-times"></i> Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openAddUserModal() {
      document.getElementById('addUserModal').style.display = 'block';
      document.getElementById('addUserForm').reset();
    }

    function closeAddUserModal() {
      document.getElementById('addUserModal').style.display = 'none';
    }

    function backupDatabase() {
      if (confirm('This will create a backup of the database. Continue?')) {
        window.location.href = '../backend/backup_database.php';
      }
    }
  </script>
</body>
</html>
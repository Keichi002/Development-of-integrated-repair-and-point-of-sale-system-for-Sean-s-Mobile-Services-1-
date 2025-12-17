<?php
session_start();
include "../backend/db_connect.php";

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_repair'])) {
        $device_name = sanitize($_POST['device_name']);
        $issue = sanitize($_POST['issue']);
        $customer_name = sanitize($_POST['customer_name']);
        $service_type = sanitize($_POST['service_type']);
        $amount = $_POST['amount'];
        $status = sanitize($_POST['status']);
        $notes = sanitize($_POST['notes']);
        $date_received = date('Y-m-d');
        
        $stmt = $conn->prepare("INSERT INTO repairs (device_name, issue, customer_name, service_type, amount, status, technician_notes, date_received) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssdsss", $device_name, $issue, $customer_name, $service_type, $amount, $status, $notes, $date_received);
        
        if ($stmt->execute()) {
            $repair_id = $stmt->insert_id;
            $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity_type, description) VALUES (?, 'repair_added', ?)");
            $desc = "Added repair #$repair_id for $customer_name - $device_name";
            $log_stmt->bind_param("is", $user_id, $desc);
            $log_stmt->execute();
            $success_message = "Repair added successfully!";
        } else {
            $error_message = "Error adding repair: " . $stmt->error;
        }
    }
    
    if (isset($_POST['update_status'])) {
        $repair_id = $_POST['repair_id'];
        $status = sanitize($_POST['status']);
        
        $stmt = $conn->prepare("UPDATE repairs SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $repair_id);
        
        if ($stmt->execute()) {
            if ($status === 'Completed') {
                $date_completed = date('Y-m-d');
                $stmt2 = $conn->prepare("UPDATE repairs SET date_completed = ?, completed_at = NOW() WHERE id = ?");
                $stmt2->bind_param("si", $date_completed, $repair_id);
                $stmt2->execute();
            }
            
            $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity_type, description) VALUES (?, 'repair_updated', ?)");
            $desc = "Updated repair #$repair_id status to: $status";
            $log_stmt->bind_param("is", $user_id, $desc);
            $log_stmt->execute();
            $success_message = "Repair status updated!";
        } else {
            $error_message = "Error updating status";
        }
    }
    
    if (isset($_POST['delete_repair'])) {
        $repair_id = $_POST['repair_id'];
        
        $stmt = $conn->prepare("DELETE FROM repairs WHERE id = ?");
        $stmt->bind_param("i", $repair_id);
        
        if ($stmt->execute()) {
            $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity_type, description) VALUES (?, 'repair_deleted', ?)");
            $desc = "Deleted repair #$repair_id";
            $log_stmt->bind_param("is", $user_id, $desc);
            $log_stmt->execute();
            $success_message = "Repair deleted successfully!";
        } else {
            $error_message = "Error deleting repair";
        }
    }
    
    if (isset($_POST['save_notes'])) {
        $repair_id = $_POST['repair_id'];
        $notes = sanitize($_POST['notes']);
        
        $stmt = $conn->prepare("UPDATE repairs SET technician_notes = ? WHERE id = ?");
        $stmt->bind_param("si", $notes, $repair_id);
        
        if ($stmt->execute()) {
            $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity_type, description) VALUES (?, 'repair_notes_updated', ?)");
            $desc = "Updated notes for repair #$repair_id";
            $log_stmt->bind_param("is", $user_id, $desc);
            $log_stmt->execute();
            $success_message = "Notes saved successfully!";
        } else {
            $error_message = "Error saving notes";
        }
    }
}

$queue_repairs = $conn->query("SELECT * FROM repairs WHERE status != 'Completed' ORDER BY date_received DESC")->fetch_all(MYSQLI_ASSOC);
$completed_repairs = $conn->query("SELECT * FROM repairs WHERE status = 'Completed' ORDER BY date_completed DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Repairs | Repair POS</title>
  <link rel="stylesheet" href="../style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
  <div class="page-layout">
    
    <div class="sidebar">
      <h3>Navigation</h3>
      <a href="../dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
      <a href="repairs.php" class="active"><i class="fas fa-tools"></i>Repairs</a>
      <a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
      <a href="sales.php"><i class="fas fa-shopping-cart"></i> Sales</a>
      <a href="inventory.php"><i class="fas fa-boxes"></i> Inventory</a>
      <a href="customers.php"><i class="fas fa-users"></i> Customers</a>
      <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
      <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="main-content">
      <h2>Repair Jobs Management</h2>
      
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
        <h3><i class="fas fa-mobile-alt"></i> Device Queue List</h3>
        <table class="repair-table">
          <thead>
            <tr>
              <th>Device Name</th>
              <th>Issue</th>
              <th>Customer Name</th>
              <th>Status</th>
              <th>Date Received</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($queue_repairs as $repair): ?>
            <tr>
              <td><?php echo htmlspecialchars($repair['device_name']); ?></td>
              <td><?php echo htmlspecialchars($repair['issue']); ?></td>
              <td><?php echo htmlspecialchars($repair['customer_name']); ?></td>
              <td>
                <form method="POST" style="display: inline;">
                  <input type="hidden" name="repair_id" value="<?php echo $repair['id']; ?>">
                  <select name="status" class="status-select" onchange="this.form.submit()">
                    <option value="Pending" <?php echo $repair['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="In Progress" <?php echo $repair['status'] == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                    <option value="For Pickup" <?php echo $repair['status'] == 'For Pickup' ? 'selected' : ''; ?>>For Pickup</option>
                    <option value="Completed" <?php echo $repair['status'] == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                  </select>
                  <input type="hidden" name="update_status" value="1">
                </form>
              </td>
              <td><?php echo $repair['date_received']; ?></td>
              <td>
                <button class="btn-small" onclick="showNotes(<?php echo $repair['id']; ?>)"><i class="fas fa-edit"></i> Notes</button>
                <form method="POST" style="display: inline;">
                  <input type="hidden" name="repair_id" value="<?php echo $repair['id']; ?>">
                  <input type="hidden" name="status" value="Completed">
                  <input type="hidden" name="update_status" value="1">
                  <button type="submit" class="btn-small"><i class="fas fa-check"></i> Complete</button>
                </form>
                <form method="POST" style="display: inline;" onsubmit="return confirm('Delete this repair?');">
                  <input type="hidden" name="repair_id" value="<?php echo $repair['id']; ?>">
                  <input type="hidden" name="delete_repair" value="1">
                  <button type="submit" class="btn-small btn-danger"><i class="fas fa-trash"></i> Delete</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($queue_repairs)): ?>
            <tr>
              <td colspan="6" style="text-align: center; color: var(--text-muted);">No repairs in queue</td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="section" id="notesSection" style="display: none;">
        <h3><i class="fas fa-sticky-note"></i> Technician Notes - <span id="currentDevice"></span></h3>
        <div class="notes-section">
          <form method="POST" id="notesForm">
            <input type="hidden" name="repair_id" id="notesRepairId">
            <div class="form-group">
              <textarea class="notes-textarea" id="technicianNotes" name="notes" placeholder="What was checked, replaced, or any other notes..."></textarea>
            </div>
            <button type="submit" class="btn-primary" name="save_notes"><i class="fas fa-save"></i> Save Notes</button>
          </form>
        </div>
      </div>

      <div class="section">
        <h3><i class="fas fa-check-circle"></i> Completed Repairs</h3>
        <table class="repair-table">
          <thead>
            <tr>
              <th>Device Details</th>
              <th>Service Done</th>
              <th>Amount Paid</th>
              <th>Date Completed</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($completed_repairs as $repair): ?>
            <tr>
              <td><?php echo htmlspecialchars($repair['device_name']); ?></td>
              <td><?php echo htmlspecialchars($repair['service_type']); ?></td>
              <td>₱<?php echo number_format($repair['amount'], 2); ?></td>
              <td><?php echo $repair['date_completed']; ?></td>
              <td>
                <button class="btn-small" onclick="showReceipt(<?php echo $repair['id']; ?>)"><i class="fas fa-receipt"></i> Receipt</button>
                <form method="POST" style="display: inline;">
                  <input type="hidden" name="repair_id" value="<?php echo $repair['id']; ?>">
                  <input type="hidden" name="status" value="Pending">
                  <input type="hidden" name="update_status" value="1">
                  <button type="submit" class="btn-small"><i class="fas fa-redo"></i> Reopen</button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($completed_repairs)): ?>
            <tr>
              <td colspan="5" style="text-align: center; color: var(--text-muted);">No completed repairs</td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div id="addRepairModal" class="modal" style="display: none;">
        <div class="modal-content">
          <span class="close" onclick="closeAddRepair()">&times;</span>
          <h3><i class="fas fa-plus"></i> Add New Repair Job</h3>
          <form method="POST" id="addRepairForm">
            <div class="form-group">
              <label for="deviceName">Device Name *</label>
              <input type="text" id="deviceName" name="device_name" placeholder="e.g., iPhone 12, Samsung S21" required>
            </div>
            <div class="form-group">
              <label for="issue">Issue/Problem *</label>
              <input type="text" id="issue" name="issue" placeholder="e.g., Broken Screen, Battery Issue" required>
            </div>
            <div class="form-group">
              <label for="customerName">Customer Name *</label>
              <input type="text" id="customerName" name="customer_name" placeholder="Enter customer name" required>
            </div>
            <div class="form-group">
              <label for="service">Service Type *</label>
              <input type="text" id="service" name="service_type" placeholder="e.g., Screen Replacement" required>
            </div>
            <div class="form-group">
              <label for="amount">Amount (₱) *</label>
              <input type="number" id="amount" name="amount" placeholder="Enter repair cost" min="0" step="0.01" required>
            </div>
            <div class="form-group">
              <label for="status">Status *</label>
              <select id="status" name="status" required>
                <option value="Pending">Pending</option>
                <option value="In Progress">In Progress</option>
                <option value="For Pickup">For Pickup</option>
              </select>
            </div>
            <div class="form-group">
              <label for="notes">Technician Notes</label>
              <textarea id="notes" name="notes" class="notes-textarea" placeholder="Add any notes about the repair..."></textarea>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn-primary" name="add_repair" value="1"><i class="fas fa-save"></i> Add Repair Job</button>
              <button type="button" class="btn-secondary" onclick="closeAddRepair()"><i class="fas fa-times"></i> Cancel</button>
            </div>
          </form>
        </div>
      </div>

      <div id="receiptModal" class="modal" style="display: none;">
        <div class="modal-content">
          <span class="close" onclick="closeReceipt()">&times;</span>
          <div class="receipt printable" id="receiptContent">
            <div class="receipt-header">
              <h3>Mobile Repair POS</h3>
              <p>Repair Receipt</p>
            </div>
            <div class="receipt-body" id="receiptBody">
            </div>
            <div class="receipt-footer">
              <p>Thank you for your business!</p>
            </div>
            <div class="receipt-actions">
              <button class="btn-primary" onclick="printReceipt()"><i class="fas fa-print"></i> Print Receipt</button>
              <button class="btn-secondary" onclick="downloadReceipt()"><i class="fas fa-download"></i> Download PDF</button>
            </div>
          </div>
        </div>
      </div>

      <button class="add-btn" onclick="openAddRepair()"><i class="fas fa-plus"></i> Add New Repair Job</button>
    </div>

  </div>

  <script>
    function openAddRepair() {
      document.getElementById('addRepairModal').style.display = 'block';
      document.getElementById('addRepairForm').reset();
    }

    function closeAddRepair() {
      document.getElementById('addRepairModal').style.display = 'none';
    }

    function showNotes(repairId) {
      fetch(`../backend/get_repair.php?id=${repairId}`)
        .then(response => response.json())
        .then(data => {
          document.getElementById('currentDevice').textContent = data.device_name;
          document.getElementById('technicianNotes').value = data.technician_notes || '';
          document.getElementById('notesRepairId').value = repairId;
          document.getElementById('notesSection').style.display = 'block';
        })
        .catch(error => console.error('Error:', error));
    }

    function showReceipt(repairId) {
      fetch(`../backend/get_repair.php?id=${repairId}`)
        .then(response => response.json())
        .then(data => {
          const receiptBody = document.getElementById('receiptBody');
          receiptBody.innerHTML = `
            <div class="receipt-row">
              <span>Receipt #:</span>
              <span>RP-${data.id}</span>
            </div>
            <div class="receipt-row">
              <span>Date:</span>
              <span>${data.date_received}</span>
            </div>
            <div class="receipt-row">
              <span>Customer:</span>
              <span>${data.customer_name}</span>
            </div>
            <div class="receipt-row">
              <span>Device:</span>
              <span>${data.device_name}</span>
            </div>
            <div class="receipt-row">
              <span>Service:</span>
              <span>${data.service_type}</span>
            </div>
            <div class="receipt-row">
              <span>Issue:</span>
              <span>${data.issue}</span>
            </div>
            <div class="receipt-row">
              <span>Amount:</span>
              <span><strong>₱${parseFloat(data.amount).toFixed(2)}</strong></span>
            </div>
            <div class="receipt-notes">
              <p><strong>Technician Notes:</strong> <span>${data.technician_notes || 'None'}</span></p>
            </div>
          `;
          document.getElementById('receiptModal').style.display = 'block';
        })
        .catch(error => console.error('Error:', error));
    }

    function closeReceipt() {
      document.getElementById('receiptModal').style.display = 'none';
    }

    function printReceipt() {
      const receiptContent = document.getElementById('receiptContent').innerHTML;
      const printWindow = window.open('', '_blank');
      printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
          <title>Receipt</title>
          <style>
            body {
              font-family: Arial, sans-serif;
              max-width: 400px;
              margin: 0;
              padding: 20px;
              background: white;
            }
            .receipt {
              border: 1px solid #ddd;
              padding: 20px;
              text-align: center;
            }
            .receipt-header {
              border-bottom: 2px solid #333;
              margin-bottom: 15px;
              padding-bottom: 10px;
            }
            .receipt-header h3 {
              margin: 0;
              font-size: 18px;
            }
            .receipt-body {
              text-align: left;
              margin: 15px 0;
              font-size: 14px;
            }
            .receipt-row {
              display: flex;
              justify-content: space-between;
              padding: 5px 0;
            }
            .receipt-notes {
              border-top: 1px dashed #ccc;
              border-bottom: 1px dashed #ccc;
              margin: 10px 0;
              padding: 10px 0;
              font-size: 12px;
            }
            .receipt-footer {
              text-align: center;
              margin-top: 15px;
              font-size: 12px;
            }
            @media print {
              body {
                margin: 0;
                padding: 0;
              }
            }
          </style>
        </head>
        <body>
          <div class="receipt">
            ${receiptContent}
          </div>
        </body>
        </html>
      `);
      printWindow.document.close();
      printWindow.print();
    }

    function downloadReceipt() {
      const receiptContent = document.getElementById('receiptContent').innerText;
      const element = document.createElement('a');
      element.setAttribute('href', 'data:text/plain;charset=utf-8,' + encodeURIComponent(receiptContent));
      element.setAttribute('download', 'receipt.txt');
      element.style.display = 'none';
      document.body.appendChild(element);
      element.click();
      document.body.removeChild(element);
      alert('Receipt downloaded successfully!');
    }
  </script>
</body>
</html>
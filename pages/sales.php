<?php
session_start();
include "../backend/db_connect.php";

if (!isset($_SESSION['user'])) {
    header("Location: ../index.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// FIXED: Load tax rate from settings.json (defaults to 12% if not set)
$settings_file = '../settings.json';
$tax_rate = 0.12; // fallback
if (file_exists($settings_file)) {
    $settings = json_decode(file_get_contents($settings_file), true);
    if (isset($settings['tax_rate']) && is_numeric($settings['tax_rate'])) {
        $tax_rate = $settings['tax_rate'] / 100;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_checkout'])) {
    $items = json_decode($_POST['items'], true);
    $subtotal = $_POST['subtotal'];
    $tax = $_POST['tax'];
    $total = $_POST['total'];
    $customer_name = sanitize($_POST['customer_name']);
    $payment_method = sanitize($_POST['payment_method']);
    $amount_tendered = $_POST['amount_tendered'];
    $change_amount = $_POST['change_amount'];
    $sale_date = date('Y-m-d');
    
    $transaction_id = 'TXN' . date('YmdHis') . rand(100, 999);
    $items_json = json_encode($items);
    
    $stmt = $conn->prepare("INSERT INTO sales (transaction_id, items, subtotal, tax, total, customer_name, payment_method, amount_tendered, change_amount, sale_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssdddssdds", $transaction_id, $items_json, $subtotal, $tax, $total, $customer_name, $payment_method, $amount_tendered, $change_amount, $sale_date);
    
    if ($stmt->execute()) {
        foreach ($items as $item) {
            $update_stmt = $conn->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?");
            $update_stmt->bind_param("ii", $item['quantity'], $item['id']);
            $update_stmt->execute();
        }
        
        $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, activity_type, description) VALUES (?, 'sale_completed', ?)");
        $desc = "Sale completed: $transaction_id - ₱$total";
        $log_stmt->bind_param("is", $user_id, $desc);
        $log_stmt->execute();
        
        $success_message = "Sale completed! Transaction ID: $transaction_id";
        
        echo "<script>window.location.href = 'sales.php?success=1&tid=$transaction_id';</script>";
        exit();
    } else {
        $error_message = "Error processing sale: " . $stmt->error;
    }
}

if (isset($_GET['success']) && isset($_GET['tid'])) {
    $success_message = "Sale completed! Transaction ID: " . $_GET['tid'];
}

$inventory_items = $conn->query("SELECT * FROM inventory WHERE quantity > 0 ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$sales_history = $conn->query("SELECT * FROM sales ORDER BY created_at DESC LIMIT 20")->fetch_all(MYSQLI_ASSOC);

$today = date('Y-m-d');
$today_sales = $conn->query("SELECT SUM(total) as total, COUNT(*) as count FROM sales WHERE sale_date = '$today'")->fetch_assoc();
$total_sales = $conn->query("SELECT SUM(total) as total, COUNT(*) as count FROM sales")->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sales | Repair POS</title>
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
      <a href="sales.php" class="active"><i class="fas fa-shopping-cart"></i> Sales</a>
      <a href="inventory.php"><i class="fas fa-boxes"></i> Inventory</a>
      <a href="customers.php"><i class="fas fa-users"></i> Customers</a>
      <a href="settings.php"><i class="fas fa-cog"></i> Settings</a>
      <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="main-content">
      <h2>Point of Sale</h2>

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

      <div class="pos-section">
        <div class="product-list">
          <h3><i class="fas fa-box-open"></i> Available Products</h3>
          <ul id="productList">
            <?php foreach ($inventory_items as $item): ?>
            <li>
              <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                <div>
                  <div style="font-weight: 600;"><?php echo htmlspecialchars($item['name']); ?></div>
                  <div style="font-size: 0.9rem; color: var(--text-muted);">₱<?php echo number_format($item['price'], 2); ?></div>
                  <div style="font-size: 0.8rem; color: var(--text-muted);">Stock: <?php echo $item['quantity']; ?></div>
                </div>
                <button class="btn-small" onclick="addToCart(<?php echo $item['id']; ?>, '<?php echo addslashes($item['name']); ?>', <?php echo $item['price']; ?>, <?php echo $item['quantity']; ?>)">
                  <i class="fas fa-cart-plus"></i> Add
                </button>
              </div>
            </li>
            <?php endforeach; ?>
            <?php if (empty($inventory_items)): ?>
            <li style="color: var(--text-muted); text-align: center; padding: 1rem;">No products available. Add items in Inventory.</li>
            <?php endif; ?>
          </ul>
        </div>
        
        <div class="cart">
          <h3><i class="fas fa-shopping-cart"></i> Cart</h3>
          <ul id="cart-items">
            <li style="color: var(--text-muted); text-align: center; padding: 1rem;">Cart is empty</li>
          </ul>
          <div style="margin-top: 1.5rem; padding: 1rem; background: var(--primary); border-radius: 8px;">
            <p><strong>Subtotal: ₱<span id="subtotal">0.00</span></strong></p>
            <p><strong>Tax (<?php echo number_format($tax_rate * 100, 1); ?>%): ₱<span id="tax">0.00</span></strong></p>
            <p style="font-size: 1.2rem;"><strong>Total: ₱<span id="total">0.00</span></strong></p>
          </div>
          <div style="display: flex; gap: 1rem; margin-top: 1rem;">
            <button class="btn-primary" style="flex: 1;" onclick="checkout()"><i class="fas fa-credit-card"></i> Checkout</button>
            <button class="btn-secondary" style="flex: 1;" onclick="clearCart()"><i class="fas fa-trash"></i> Clear Cart</button>
          </div>
        </div>
      </div>

      <div id="checkoutModal" class="modal" style="display: none;">
        <div class="modal-content">
          <span class="close" onclick="closeCheckoutModal()">&times;</span>
          <h3><i class="fas fa-receipt"></i> Checkout</h3>
          <form method="POST" id="checkoutForm">
            <input type="hidden" name="items" id="checkoutItems">
            <input type="hidden" name="subtotal" id="checkoutSubtotal">
            <input type="hidden" name="tax" id="checkoutTax">
            <input type="hidden" name="total" id="checkoutTotal">
            <input type="hidden" name="change_amount" id="checkoutChange">
            <input type="hidden" name="process_checkout" value="1">
            <div class="form-group">
              <label>Customer Name (Optional)</label>
              <input type="text" name="customer_name" id="customerName" placeholder="Enter customer name or 'Walk-in'">
            </div>
            <div class="form-group">
              <label>Payment Method *</label>
              <select name="payment_method" id="paymentMethod" required>
                <option value="">Select Payment Method</option>
                <option value="Cash">Cash</option>
                <option value="Card">Card</option>
                <option value="GCash">GCash</option>
                <option value="Cheque">Cheque</option>
              </select>
            </div>
            <div class="form-group">
              <label>Amount Tendered (₱) *</label>
              <input type="number" name="amount_tendered" id="amountTendered" placeholder="0.00" min="0" step="0.01" required>
            </div>
            <div class="form-group">
              <label><strong>Order Summary</strong></label>
              <div style="background: var(--input-bg); padding: 1rem; border-radius: 6px; max-height: 200px; overflow-y: auto;">
                <ul id="checkoutSummary" style="list-style: none; padding: 0; margin: 0;">
                </ul>
              </div>
            </div>
            <div class="form-group">
              <p><strong>Total: ₱<span id="checkoutTotalDisplay">0.00</span></strong></p>
              <p id="changeAmount" style="color: var(--success); display: none;"><strong>Change: ₱<span id="change">0.00</span></strong></p>
            </div>
            <div class="form-actions">
              <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Complete Sale</button>
              <button type="button" class="btn-secondary" onclick="closeCheckoutModal()"><i class="fas fa-times"></i> Cancel</button>
            </div>
          </form>
        </div>
      </div>
      
      <div class="section">
        <h3><i class="fas fa-history"></i> POS History</h3>
        <table class="transaction-history">
          <thead>
            <tr>
              <th>Transaction ID</th>
              <th>Items</th>
              <th>Total</th>
              <th>Date & Time</th>
              <th>Customer</th>
              <th>Payment</th>
            </tr>
          </thead>
          <tbody id="posHistoryBody">
            <?php foreach ($sales_history as $sale): 
              $items = json_decode($sale['items'], true);
              $items_text = '';
              if (is_array($items)) {
                  $items_text = implode(', ', array_map(function($item) {
                      return $item['name'] . ' x' . $item['quantity'];
                  }, $items));
              }
            ?>
            <tr>
              <td><?php echo $sale['transaction_id']; ?></td>
              <td><?php echo htmlspecialchars($items_text); ?></td>
              <td>₱<?php echo number_format($sale['total'], 2); ?></td>
              <td><?php echo date('Y-m-d H:i', strtotime($sale['created_at'])); ?></td>
              <td><?php echo htmlspecialchars($sale['customer_name']); ?></td>
              <td><?php echo $sale['payment_method']; ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($sales_history)): ?>
            <tr>
              <td colspan="6" style="text-align: center; color: var(--text-muted);">No transactions yet</td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <div class="section">
        <h3><i class="fas fa-chart-line"></i> Daily Income Summary</h3>
        <div class="income-summary">
          <div class="income-card">
            <h4>Today's Income</h4>
            <p class="income-amount" id="todayIncome">₱<?php echo number_format($today_sales['total'] ?? 0, 2); ?></p>
            <p id="todayTransactions">From <?php echo $today_sales['count'] ?? 0; ?> transactions</p>
          </div>
          <div class="income-card">
            <h4>Total Sales</h4>
            <p class="income-amount" id="totalSales">₱<?php echo number_format($total_sales['total'] ?? 0, 2); ?></p>
            <p id="totalTransactions">From <?php echo $total_sales['count'] ?? 0; ?> transactions</p>
          </div>
        </div>
      </div>
    </div>

  </div>

  <script>
    let cart = [];
    const TAX_RATE = <?php echo $tax_rate; ?>; // Now dynamic from settings.json

    function addToCart(id, name, price, maxQuantity) {
      const existingItem = cart.find(item => item.id === id);
      if (existingItem) {
        if (existingItem.quantity < maxQuantity) {
          existingItem.quantity++;
        } else {
          alert('Cannot add more. Maximum stock reached.');
          return;
        }
      } else {
        cart.push({ id: id, name: name, price: price, quantity: 1 });
      }
      updateCart();
    }

    function removeFromCart(id) {
      cart = cart.filter(item => item.id !== id);
      updateCart();
    }

    function updateQuantity(id, quantity) {
      const item = cart.find(item => item.id === id);
      if (item) {
        const qty = parseInt(quantity);
        if (qty > 0) item.quantity = qty;
        updateCart();
      }
    }

    function updateCart() {
      const cartItems = document.getElementById('cart-items');
      cartItems.innerHTML = '';

      if (cart.length === 0) {
        cartItems.innerHTML = '<li style="color: var(--text-muted); text-align: center; padding: 1rem;">Cart is empty</li>';
      } else {
        cart.forEach(item => {
          const li = document.createElement('li');
          const itemTotal = item.price * item.quantity;
          li.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
              <span>${item.name}</span>
              <div style="display: flex; gap: 0.5rem; align-items: center;">
                <input type="number" value="${item.quantity}" min="1" style="width: 50px; padding: 0.25rem;" onchange="updateQuantity(${item.id}, this.value)">
                <span>₱${itemTotal.toLocaleString()}</span>
                <button class="btn-small btn-danger" onclick="removeFromCart(${item.id})"><i class="fas fa-trash"></i></button>
              </div>
            </div>
          `;
          cartItems.appendChild(li);
        });
      }

      calculateTotals();
    }

    function calculateTotals() {
      const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
      const tax = subtotal * TAX_RATE;
      const total = subtotal + tax;

      document.getElementById('subtotal').textContent = subtotal.toFixed(2);
      document.getElementById('tax').textContent = tax.toFixed(2);
      document.getElementById('total').textContent = total.toFixed(2);
    }

    function checkout() {
      if (cart.length === 0) {
        alert('Cart is empty');
        return;
      }

      const subtotal = cart.reduce((sum, item) => sum + (item.price * item.quantity), 0);
      const tax = subtotal * TAX_RATE;
      const total = subtotal + tax;

      document.getElementById('checkoutSubtotal').value = subtotal.toFixed(2);
      document.getElementById('checkoutTax').value = tax.toFixed(2);
      document.getElementById('checkoutTotal').value = total.toFixed(2);
      document.getElementById('checkoutTotalDisplay').textContent = total.toFixed(2);
      document.getElementById('checkoutItems').value = JSON.stringify(cart);

      const summary = document.getElementById('checkoutSummary');
      summary.innerHTML = '';
      cart.forEach(item => {
        const li = document.createElement('li');
        li.style.marginBottom = '0.5rem';
        li.innerHTML = `${item.name} x${item.quantity} = ₱${(item.price * item.quantity).toLocaleString()}`;
        summary.appendChild(li);
      });

      document.getElementById('checkoutModal').style.display = 'block';
      document.getElementById('customerName').value = 'Walk-in';
      document.getElementById('paymentMethod').value = '';
      document.getElementById('amountTendered').value = '';
      document.getElementById('changeAmount').style.display = 'none';
    }

    function closeCheckoutModal() {
      document.getElementById('checkoutModal').style.display = 'none';
    }

    function clearCart() {
      if (cart.length > 0 && confirm('Clear all items from cart?')) {
        cart = [];
        updateCart();
      }
    }

    document.addEventListener('input', (e) => {
      if (e.target.id === 'amountTendered') {
        const amount = parseFloat(e.target.value) || 0;
        const total = parseFloat(document.getElementById('checkoutTotalDisplay').textContent);
        const change = amount - total;

        if (change >= 0) {
          document.getElementById('changeAmount').style.display = 'block';
          document.getElementById('change').textContent = change.toFixed(2);
          document.getElementById('checkoutChange').value = change.toFixed(2);
        } else {
          document.getElementById('changeAmount').style.display = 'none';
          document.getElementById('checkoutChange').value = '0.00';
        }
      }
    });

    updateCart();
  </script>
</body>
</html>
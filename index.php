<?php
session_start();

if (isset($_SESSION['user'])) {
    header("Location: dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | Mobile Repair POS</title>
  <link rel="stylesheet" href="style.css">
</head>
<body>
  <div class="login-container">
    <div class="login-box">
      <h2>Mobile Repair POS</h2>

      <form method="POST" action="backend/login.php">
        <input type="text" name="username" placeholder="Username" required>
        <input type="password" name="password" placeholder="Password" required>
        <button type="submit">Login</button>
      </form>

      <?php if (isset($_GET['error'])): ?>
        <p style='color:red; text-align:center; margin-top:10px;'><?php echo htmlspecialchars($_GET['error']); ?></p>
      <?php endif; ?>
      
      <p style="text-align:center; margin-top:20px; color:#666;">
        Default: admin / admin123
      </p>
    </div>
  </div>
</body>
</html>
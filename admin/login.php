<?php
// File: admin/login.php
require_once __DIR__ . '/../include/config.php';
require_once __DIR__ . '/../include/database.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$message = null;
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: " . ADMIN_PATH . "index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $message = "Username dan password harus diisi.";
    } else {
        if (verifyUser($username, $password)) {
            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $username;
            header("Location: " . ADMIN_PATH . "index.php");
            exit();
        } else {
            $message = "Username atau password salah.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo ASSETS_PATH; ?>css/admin.css?v=1.1">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h2>Admin Login</h2>
            <?php if (isset($message)): ?>
                <div class="message error"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" class="form-input" placeholder="Masukkan username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-input" placeholder="Masukkan password" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;">Login</button>
            </form>
        </div>
    </div>
</body>
</html>
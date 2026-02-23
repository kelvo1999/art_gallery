<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize($_POST['email']    ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if ($user && verifyPassword($password, $user['password'])) {
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['username']  = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];

            // Redirect by role
            switch ($user['role']) {
                case 'admin':  header('Location: dashboard/admin.php');  break;
                case 'artist': header('Location: dashboard/artist.php'); break;
                default:       header('Location: dashboard/buyer.php');
            }
            exit;
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In — ArtVault</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="form-page">
<div class="form-card">
    <a href="index.php" class="logo" style="font-family:'Cormorant Garamond',serif;font-size:1.4rem;color:var(--accent);letter-spacing:0.1em;display:block;margin-bottom:2rem;">ArtVault</a>
    <h2>Welcome Back</h2>
    <p class="subtitle">Sign in to your account</p>

    <?php if ($error): ?>
        <div class="alert alert-error" data-auto-dismiss><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required autocomplete="email"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-solid w-full" style="padding:0.8rem;">Sign In</button>
    </form>

    <div class="form-footer mt-2">
        Don't have an account? <a href="register.php">Create one</a>
    </div>
</div>
<script src="assets/js/main.js"></script>
</body>
</html>
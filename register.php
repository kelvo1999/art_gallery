<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name'] ?? '');
    $username  = sanitize($_POST['username']  ?? '');
    $email     = sanitize($_POST['email']     ?? '');
    $password  = $_POST['password']           ?? '';
    $confirm   = $_POST['confirm_password']   ?? '';
    $role      = in_array($_POST['role'] ?? '', ['buyer', 'artist']) ? $_POST['role'] : 'buyer';

    if (!$full_name || !$username || !$email || !$password) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        // Check duplicate
        $chk = $conn->prepare("SELECT id FROM users WHERE email = ? OR username = ? LIMIT 1");
        $chk->bind_param('ss', $email, $username);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $error = 'Email or username is already taken.';
        } else {
            $hash = hashPassword($password);
            $ins  = $conn->prepare(
                "INSERT INTO users (full_name, username, email, password, role) VALUES (?,?,?,?,?)"
            );
            $ins->bind_param('sssss', $full_name, $username, $email, $hash, $role);
            if ($ins->execute()) {
                $success = 'Account created! You can now sign in.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}

$defaultRole = sanitize($_GET['role'] ?? 'buyer');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create Account — ArtVault</title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="form-page">
<div class="form-card" style="max-width:500px;">
    <a href="index.php" class="logo" style="font-family:'Cormorant Garamond',serif;font-size:1.4rem;color:var(--accent);letter-spacing:0.1em;display:block;margin-bottom:2rem;">ArtVault</a>
    <h2>Create Account</h2>
    <p class="subtitle">Join the community</p>

    <?php if ($error): ?>
        <div class="alert alert-error" data-auto-dismiss><?= $error ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success" data-auto-dismiss>
            <?= $success ?> <a href="login.php">Sign in &rarr;</a>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label>I am a</label>
            <select name="role">
                <option value="buyer"  <?= ($defaultRole === 'buyer')  ? 'selected' : '' ?>>Buyer — I want to collect art</option>
                <option value="artist" <?= ($defaultRole === 'artist') ? 'selected' : '' ?>>Artist — I want to sell my work</option>
            </select>
        </div>
        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="full_name" required
                   value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Username</label>
            <input type="text" name="username" required
                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" required
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label>Password <span style="color:var(--muted)">(min 8 chars)</span></label>
            <input type="password" name="password" required autocomplete="new-password">
        </div>
        <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" required autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-solid w-full" style="padding:0.8rem;">Create Account</button>
    </form>

    <div class="form-footer mt-2">
        Already have an account? <a href="login.php">Sign in</a>
    </div>
</div>
<script src="assets/js/main.js"></script>
</body>
</html>
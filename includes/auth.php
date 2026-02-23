<?php
// includes/auth.php - Authentication helpers

session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['user_role'] !== $role && $_SESSION['user_role'] !== 'admin') {
        header('Location: /index.php?error=unauthorized');
        exit;
    }
}

function requireAdmin() {
    requireLogin();
    if ($_SESSION['user_role'] !== 'admin') {
        header('Location: /index.php?error=unauthorized');
        exit;
    }
}

function currentUser() {
    return [
        'id'       => $_SESSION['user_id']   ?? null,
        'username' => $_SESSION['username']  ?? null,
        'role'     => $_SESSION['user_role'] ?? null,
        'name'     => $_SESSION['full_name'] ?? null,
    ];
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Check if a buyer has purchased a specific artwork
 */
function hasPurchased($conn, $user_id, $artwork_id) {
    $stmt = $conn->prepare(
        "SELECT id FROM purchases WHERE buyer_id = ? AND artwork_id = ? AND payment_status = 'completed' LIMIT 1"
    );
    $stmt->bind_param('ii', $user_id, $artwork_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->num_rows > 0;
}
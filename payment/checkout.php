<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

requireLogin();

$artwork_id = (int)($_GET['artwork'] ?? 0);
$user       = currentUser();

if (!$artwork_id) { header('Location: ../index.php'); exit; }

// Fetch artwork
$stmt = $conn->prepare(
    "SELECT a.*, u.username, u.full_name FROM artworks a JOIN users u ON a.artist_id = u.id
     WHERE a.id = ? AND a.is_active = 1 LIMIT 1"
);
$stmt->bind_param('i', $artwork_id);
$stmt->execute();
$art = $stmt->get_result()->fetch_assoc();

if (!$art) { header('Location: ../index.php?error=notfound'); exit; }

// Already purchased?
if (hasPurchased($conn, $user['id'], $artwork_id)) {
    header('Location: ../artwork/view.php?id=' . $artwork_id);
    exit;
}

$success = '';
$error   = '';

// Simulate payment on POST (placeholder)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ref = 'TXN_' . strtoupper(bin2hex(random_bytes(6)));

    $ins = $conn->prepare(
        "INSERT INTO purchases (buyer_id, artwork_id, amount, payment_status, transaction_ref)
         VALUES (?, ?, ?, 'completed', ?)"
    );
    $ins->bind_param('iids', $user['id'], $artwork_id, $art['price'], $ref);

    if ($ins->execute()) {
        $success = $ref;
    } else {
        $error = 'Payment processing failed. Please try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Checkout — ArtVault</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="form-page">

<?php if ($success): ?>
<div class="form-card" style="text-align:center;">
    <div style="font-size:3rem;margin-bottom:1rem;">✓</div>
    <h2>Purchase Complete!</h2>
    <p class="subtitle">Transaction ref: <strong><?= $success ?></strong></p>
    <p style="color:var(--muted);font-size:0.82rem;margin:1.5rem 0;">
        You now have access to the original, unwatermarked file.
    </p>
    <a href="../artwork/view.php?id=<?= $artwork_id ?>" class="btn btn-solid w-full" style="padding:0.9rem;display:block;text-align:center;">
        View & Download Original
    </a>
</div>

<?php else: ?>
<div class="form-card">
    <a href="../index.php" class="logo" style="font-family:'Cormorant Garamond',serif;font-size:1.4rem;color:var(--accent);letter-spacing:0.1em;display:block;margin-bottom:2rem;">ArtVault</a>
    <h2>Checkout</h2>
    <p class="subtitle">Complete your purchase</p>

    <?php if ($error): ?>
        <div class="alert alert-error" data-auto-dismiss><?= $error ?></div>
    <?php endif; ?>

    <!-- Order summary -->
    <div style="background:var(--bg);border:1px solid var(--border);border-radius:var(--radius);padding:1.2rem;margin-bottom:1.5rem;">
        <div style="display:flex;gap:1rem;align-items:center;">
            <img src="../<?= htmlspecialchars($art['preview_path']) ?>"
                 style="width:70px;height:70px;object-fit:cover;border-radius:var(--radius);pointer-events:none;"
                 alt="preview">
            <div>
                <div style="font-family:'Cormorant Garamond',serif;font-size:1.2rem;"><?= htmlspecialchars($art['title']) ?></div>
                <div style="color:var(--muted);font-size:0.75rem;">by <?= htmlspecialchars($art['full_name'] ?: $art['username']) ?></div>
                <div style="color:var(--accent);font-size:1.1rem;margin-top:0.3rem;">$<?= number_format($art['price'], 2) ?></div>
            </div>
        </div>
    </div>

    <!-- Payment placeholder form -->
    <form method="POST">
        <div class="form-group">
            <label>Card Number</label>
            <input type="text" placeholder="4242 4242 4242 4242" maxlength="19" disabled
                   style="opacity:0.5;cursor:not-allowed;">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <div class="form-group">
                <label>Expiry</label>
                <input type="text" placeholder="MM / YY" disabled style="opacity:0.5;cursor:not-allowed;">
            </div>
            <div class="form-group">
                <label>CVC</label>
                <input type="text" placeholder="123" disabled style="opacity:0.5;cursor:not-allowed;">
            </div>
        </div>

        <div class="alert alert-warning" style="background:rgba(241,196,15,0.08);border-color:#f1c40f;color:#f1c40f;font-size:0.78rem;">
            ⚠ Payment gateway is a placeholder. Clicking "Pay" will simulate a completed purchase.
        </div>

        <button type="submit" class="btn btn-solid w-full" style="padding:0.9rem;margin-top:0.5rem;">
            Pay $<?= number_format($art['price'], 2) ?> (Simulate)
        </button>
    </form>

    <div style="margin-top:1rem;text-align:center;">
        <a href="../artwork/view.php?id=<?= $artwork_id ?>" style="color:var(--muted);font-size:0.8rem;">← Back to artwork</a>
    </div>
</div>
<?php endif; ?>

<script src="../assets/js/main.js"></script>
</body>
</html>
<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

requireLogin();
$user = currentUser();

// Redirect artists/admins to their dashboards
if ($user['role'] === 'artist') { header('Location: artist.php'); exit; }
if ($user['role'] === 'admin')  { header('Location: admin.php');  exit; }

// My purchases
$purchases = $conn->query(
    "SELECT p.*, a.title, a.preview_path, a.original_path, a.category,
            u.username as artist_username, u.full_name as artist_name
     FROM purchases p
     JOIN artworks a ON p.artwork_id = a.id
     JOIN users u ON a.artist_id = u.id
     WHERE p.buyer_id = {$user['id']} AND p.payment_status = 'completed'
     ORDER BY p.purchased_at DESC"
)->fetch_all(MYSQLI_ASSOC);

$totalSpent = array_sum(array_column($purchases, 'amount'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Collection — ArtVault</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<header class="navbar">
    <a href="../index.php" class="logo">ArtVault</a>
    <nav>
        <a href="../index.php">Gallery</a>
        <span style="color:var(--muted);font-size:0.78rem;"><?= htmlspecialchars($user['name'] ?: $user['username']) ?></span>
        <a href="../logout.php" class="btn btn-sm">Sign Out</a>
    </nav>
</header>

<div class="dashboard-layout">
    <aside class="sidebar">
        <div class="nav-section">Collection</div>
        <a href="buyer.php" class="nav-item active">🖼 My Purchases</a>
        <div class="nav-section">Discover</div>
        <a href="../index.php" class="nav-item">← Browse Gallery</a>
        <a href="../logout.php" class="nav-item">Sign Out</a>
    </aside>

    <main class="main-content">
        <div style="margin-bottom:2rem;">
            <div class="section-label">My Collection</div>
            <h2><?= htmlspecialchars($user['name'] ?: $user['username']) ?></h2>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Artworks Owned</div>
                <div class="stat-value"><?= count($purchases) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Invested</div>
                <div class="stat-value">$<?= number_format($totalSpent, 0) ?></div>
            </div>
        </div>

        <h3 style="margin-bottom:1.5rem;">Purchased Works</h3>

        <?php if (empty($purchases)): ?>
        <div style="text-align:center;padding:4rem;color:var(--muted);border:1px dashed var(--border);border-radius:var(--radius);">
            <div style="font-size:3rem;margin-bottom:1rem;">🛒</div>
            <p>Your collection is empty.</p>
            <a href="../index.php" class="btn btn-solid" style="margin-top:1rem;display:inline-block;">Browse Gallery</a>
        </div>
        <?php else: ?>
        <div class="gallery-grid">
            <?php foreach ($purchases as $p): ?>
            <div class="art-card">
                <div class="img-wrap">
                    <img src="../<?= htmlspecialchars($p['preview_path']) ?>"
                         alt="<?= htmlspecialchars($p['title']) ?>"
                         class="art-protected">
                    <div class="img-overlay">
                        <a href="../artwork/download.php?id=<?= $p['artwork_id'] ?>"
                           class="btn btn-sm btn-solid" onclick="event.stopPropagation()">↓ Download</a>
                    </div>
                </div>
                <div class="card-body">
                    <div class="card-title"><?= htmlspecialchars($p['title']) ?></div>
                    <div class="card-artist">by <?= htmlspecialchars($p['artist_name'] ?: $p['artist_username']) ?></div>
                </div>
                <div class="card-footer">
                    <span style="font-size:0.72rem;color:var(--muted);">
                        Purchased <?= date('M j, Y', strtotime($p['purchased_at'])) ?>
                    </span>
                    <a href="../artwork/download.php?id=<?= $p['artwork_id'] ?>"
                       class="btn btn-sm">↓ Original</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </main>
</div>

<script src="../assets/js/main.js"></script>
</body>
</html>
<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

requireAdmin();
$user = currentUser();

$success = '';
$error   = '';

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $target = (int)($_POST['target_id'] ?? 0);

    switch ($action) {
        case 'deactivate_user':
            $conn->query("UPDATE users SET is_active = 0 WHERE id = $target AND role != 'admin'");
            $success = 'User deactivated.';
            break;
        case 'activate_user':
            $conn->query("UPDATE users SET is_active = 1 WHERE id = $target");
            $success = 'User activated.';
            break;
        case 'remove_artwork':
            $conn->query("UPDATE artworks SET is_active = 0 WHERE id = $target");
            $success = 'Artwork removed.';
            break;
        case 'feature_artwork':
            $conn->query("UPDATE artworks SET is_featured = 1 WHERE id = $target");
            $success = 'Artwork featured.';
            break;
        case 'unfeature_artwork':
            $conn->query("UPDATE artworks SET is_featured = 0 WHERE id = $target");
            $success = 'Artwork unfeatured.';
            break;
    }
}

// Platform stats
$stats = $conn->query(
    "SELECT
        (SELECT COUNT(*) FROM users WHERE role = 'buyer')  as buyers,
        (SELECT COUNT(*) FROM users WHERE role = 'artist') as artists,
        (SELECT COUNT(*) FROM artworks WHERE is_active = 1) as artworks,
        (SELECT COUNT(*) FROM purchases WHERE payment_status = 'completed') as sales,
        (SELECT COALESCE(SUM(amount),0) FROM purchases WHERE payment_status = 'completed') as revenue"
)->fetch_assoc();

$activeTab = $_GET['tab'] ?? 'overview';

// Paginated data
$page   = max(1, (int)($_GET['page'] ?? 1));
$limit  = 20;
$offset = ($page - 1) * $limit;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin — ArtVault</title>
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<header class="navbar">
    <a href="../index.php" class="logo">ArtVault</a>
    <nav>
        <span style="font-size:0.72rem;color:var(--accent);">ADMIN</span>
        <a href="../index.php">Gallery</a>
        <a href="../logout.php" class="btn btn-sm">Sign Out</a>
    </nav>
</header>

<div class="dashboard-layout">
    <aside class="sidebar">
        <div class="nav-section">Admin</div>
        <a href="?tab=overview"  class="nav-item <?= $activeTab === 'overview'  ? 'active' : '' ?>">📊 Overview</a>
        <a href="?tab=users"     class="nav-item <?= $activeTab === 'users'     ? 'active' : '' ?>">👥 Users</a>
        <a href="?tab=artworks"  class="nav-item <?= $activeTab === 'artworks'  ? 'active' : '' ?>">🖼 Artworks</a>
        <a href="?tab=purchases" class="nav-item <?= $activeTab === 'purchases' ? 'active' : '' ?>">$ Purchases</a>
        <div class="nav-section">Site</div>
        <a href="../index.php" class="nav-item">← Public Gallery</a>
        <a href="../logout.php" class="nav-item">Sign Out</a>
    </aside>

    <main class="main-content">
        <div style="margin-bottom:2rem;">
            <div class="section-label">Admin Panel</div>
            <h2>Platform Overview</h2>
        </div>

        <?php if ($error):   ?><div class="alert alert-error"   data-auto-dismiss><?= $error   ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success" data-auto-dismiss><?= $success ?></div><?php endif; ?>

        <!-- Stats always visible -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Artists</div>
                <div class="stat-value"><?= $stats['artists'] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Buyers</div>
                <div class="stat-value"><?= $stats['buyers'] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Artworks</div>
                <div class="stat-value"><?= $stats['artworks'] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Sales</div>
                <div class="stat-value"><?= $stats['sales'] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Revenue</div>
                <div class="stat-value">$<?= number_format($stats['revenue'], 0) ?></div>
            </div>
        </div>

        <!-- ── USERS ── -->
        <?php if ($activeTab === 'users'):
            $users = $conn->query(
                "SELECT u.*,
                    (SELECT COUNT(*) FROM artworks WHERE artist_id = u.id) as artwork_count,
                    (SELECT COUNT(*) FROM purchases WHERE buyer_id  = u.id AND payment_status = 'completed') as purchase_count
                 FROM users u ORDER BY u.created_at DESC LIMIT $limit OFFSET $offset"
            )->fetch_all(MYSQLI_ASSOC);
        ?>
        <h3 style="margin-bottom:1.5rem;">Users</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>#</th><th>Name</th><th>Email</th><th>Role</th>
                        <th>Works/Purchases</th><th>Status</th><th>Joined</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr>
                        <td style="color:var(--muted);"><?= $u['id'] ?></td>
                        <td>
                            <div><?= htmlspecialchars($u['full_name'] ?: $u['username']) ?></div>
                            <div style="color:var(--muted);font-size:0.72rem;">@<?= htmlspecialchars($u['username']) ?></div>
                        </td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><span class="badge badge-muted"><?= $u['role'] ?></span></td>
                        <td>
                            <?php if ($u['role'] === 'artist'): ?>
                                <?= $u['artwork_count'] ?> works
                            <?php else: ?>
                                <?= $u['purchase_count'] ?> purchases
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge <?= $u['is_active'] ? 'badge-success' : 'badge-danger' ?>">
                                <?= $u['is_active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td><?= date('M j, Y', strtotime($u['created_at'])) ?></td>
                        <td>
                            <?php if ($u['role'] !== 'admin'): ?>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="target_id" value="<?= $u['id'] ?>">
                                <?php if ($u['is_active']): ?>
                                    <input type="hidden" name="action" value="deactivate_user">
                                    <button type="submit" class="btn btn-danger btn-sm"
                                            data-confirm="Deactivate this user?">Deactivate</button>
                                <?php else: ?>
                                    <input type="hidden" name="action" value="activate_user">
                                    <button type="submit" class="btn btn-sm">Activate</button>
                                <?php endif; ?>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ── ARTWORKS ── -->
        <?php elseif ($activeTab === 'artworks'):
            $artworks = $conn->query(
                "SELECT a.*, u.username, u.full_name,
                    (SELECT COUNT(*) FROM purchases WHERE artwork_id = a.id AND payment_status = 'completed') as sales
                 FROM artworks a JOIN users u ON a.artist_id = u.id
                 WHERE a.is_active = 1
                 ORDER BY a.created_at DESC LIMIT $limit OFFSET $offset"
            )->fetch_all(MYSQLI_ASSOC);
        ?>
        <h3 style="margin-bottom:1.5rem;">All Artworks</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Preview</th><th>Title</th><th>Artist</th><th>Price</th>
                        <th>Views</th><th>Sales</th><th>Featured</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($artworks as $a): ?>
                    <tr>
                        <td>
                            <img src="../<?= htmlspecialchars($a['preview_path']) ?>"
                                 style="width:60px;height:45px;object-fit:cover;border-radius:var(--radius);" alt="">
                        </td>
                        <td>
                            <a href="../artwork/view.php?id=<?= $a['id'] ?>" style="color:var(--text);">
                                <?= htmlspecialchars($a['title']) ?>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($a['full_name'] ?: $a['username']) ?></td>
                        <td style="color:var(--accent);">$<?= number_format($a['price'],2) ?></td>
                        <td><?= $a['views'] ?></td>
                        <td><?= $a['sales'] ?></td>
                        <td>
                            <span class="badge <?= $a['is_featured'] ? 'badge-success' : 'badge-muted' ?>">
                                <?= $a['is_featured'] ? 'Yes' : 'No' ?>
                            </span>
                        </td>
                        <td style="display:flex;gap:0.4rem;flex-wrap:wrap;">
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="target_id" value="<?= $a['id'] ?>">
                                <?php if ($a['is_featured']): ?>
                                    <input type="hidden" name="action" value="unfeature_artwork">
                                    <button type="submit" class="btn btn-sm">Unfeature</button>
                                <?php else: ?>
                                    <input type="hidden" name="action" value="feature_artwork">
                                    <button type="submit" class="btn btn-sm">Feature</button>
                                <?php endif; ?>
                            </form>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="target_id" value="<?= $a['id'] ?>">
                                <input type="hidden" name="action" value="remove_artwork">
                                <button type="submit" class="btn btn-danger btn-sm"
                                        data-confirm="Remove this artwork?">Remove</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ── PURCHASES ── -->
        <?php elseif ($activeTab === 'purchases'):
            $txns = $conn->query(
                "SELECT p.*, a.title as artwork_title,
                        b.username as buyer_username, b.full_name as buyer_name,
                        ar.username as artist_username, ar.full_name as artist_name
                 FROM purchases p
                 JOIN artworks a  ON p.artwork_id = a.id
                 JOIN users b     ON p.buyer_id   = b.id
                 JOIN users ar    ON a.artist_id  = ar.id
                 ORDER BY p.purchased_at DESC LIMIT $limit OFFSET $offset"
            )->fetch_all(MYSQLI_ASSOC);
        ?>
        <h3 style="margin-bottom:1.5rem;">All Transactions</h3>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th><th>Artwork</th><th>Buyer</th><th>Artist</th>
                        <th>Amount</th><th>Status</th><th>Ref</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($txns as $t): ?>
                    <tr>
                        <td><?= date('M j, Y', strtotime($t['purchased_at'])) ?></td>
                        <td><?= htmlspecialchars($t['artwork_title']) ?></td>
                        <td><?= htmlspecialchars($t['buyer_name'] ?: $t['buyer_username']) ?></td>
                        <td><?= htmlspecialchars($t['artist_name'] ?: $t['artist_username']) ?></td>
                        <td style="color:var(--accent);">$<?= number_format($t['amount'],2) ?></td>
                        <td>
                            <span class="badge <?= $t['payment_status'] === 'completed' ? 'badge-success' : 'badge-warning' ?>">
                                <?= $t['payment_status'] ?>
                            </span>
                        </td>
                        <td style="color:var(--muted);font-size:0.72rem;"><?= $t['transaction_ref'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- ── OVERVIEW ── -->
        <?php else: ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;">
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1.5rem;">
                <h3 style="margin-bottom:1rem;">Quick Links</h3>
                <div style="display:flex;flex-direction:column;gap:0.5rem;">
                    <a href="?tab=users"     class="btn" style="text-align:center;">Manage Users</a>
                    <a href="?tab=artworks"  class="btn" style="text-align:center;">Manage Artworks</a>
                    <a href="?tab=purchases" class="btn" style="text-align:center;">View Transactions</a>
                    <a href="../index.php"   class="btn" style="text-align:center;">View Public Gallery</a>
                </div>
            </div>
            <div style="background:var(--surface);border:1px solid var(--border);border-radius:var(--radius);padding:1.5rem;">
                <h3 style="margin-bottom:1rem;">Platform Health</h3>
                <div style="display:flex;flex-direction:column;gap:0.8rem;font-size:0.82rem;">
                    <div class="flex-between">
                        <span style="color:var(--muted);">Total Users</span>
                        <strong><?= $stats['artists'] + $stats['buyers'] ?></strong>
                    </div>
                    <div class="flex-between">
                        <span style="color:var(--muted);">Active Artworks</span>
                        <strong><?= $stats['artworks'] ?></strong>
                    </div>
                    <div class="flex-between">
                        <span style="color:var(--muted);">Completed Sales</span>
                        <strong><?= $stats['sales'] ?></strong>
                    </div>
                    <div class="flex-between">
                        <span style="color:var(--muted);">Total Revenue</span>
                        <strong style="color:var(--accent);">$<?= number_format($stats['revenue'], 2) ?></strong>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>
</div>

<script src="../assets/js/main.js"></script>
</body>
</html>
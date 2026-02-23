<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';
require_once '../includes/watermark.php';

requireRole('artist');
$user = currentUser();

$error   = '';
$success = '';

// Handle artwork upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'upload') {
        $title       = sanitize($_POST['title']       ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $category    = sanitize($_POST['category']    ?? '');
        $price       = max(0, (float)($_POST['price'] ?? 0));

        if (!$title || !isset($_FILES['artwork']) || $_FILES['artwork']['error'] !== UPLOAD_ERR_OK) {
            $error = 'Please provide a title and select an image to upload.';
        } else {
            $result = uploadArtwork($_FILES['artwork'], $user['id'], $title);
            if (isset($result['error'])) {
                $error = $result['error'];
            } else {
                $ins = $conn->prepare(
                    "INSERT INTO artworks (artist_id, title, description, category, price, original_path, preview_path)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                $ins->bind_param('isssdss',
                    $user['id'], $title, $description, $category, $price,
                    $result['original'], $result['preview']
                );
                if ($ins->execute()) {
                    $success = "\"$title\" uploaded successfully with watermark protection!";
                } else {
                    $error = 'Failed to save artwork to database.';
                }
            }
        }
    }

    if ($_POST['action'] === 'delete') {
        $del_id = (int)($_POST['artwork_id'] ?? 0);
        $del    = $conn->prepare("UPDATE artworks SET is_active = 0 WHERE id = ? AND artist_id = ?");
        $del->bind_param('ii', $del_id, $user['id']);
        $del->execute();
        $success = 'Artwork removed.';
    }
}

// Stats
$stats = $conn->query(
    "SELECT
        (SELECT COUNT(*) FROM artworks WHERE artist_id = {$user['id']} AND is_active = 1) as total_works,
        (SELECT COUNT(*) FROM purchases p JOIN artworks a ON p.artwork_id = a.id
         WHERE a.artist_id = {$user['id']} AND p.payment_status = 'completed') as total_sales,
        (SELECT COALESCE(SUM(p.amount),0) FROM purchases p JOIN artworks a ON p.artwork_id = a.id
         WHERE a.artist_id = {$user['id']} AND p.payment_status = 'completed') as total_revenue,
        (SELECT COALESCE(SUM(views),0) FROM artworks WHERE artist_id = {$user['id']} AND is_active = 1) as total_views"
)->fetch_assoc();

// My artworks
$myArt = $conn->query(
    "SELECT a.*, 
        (SELECT COUNT(*) FROM purchases p WHERE p.artwork_id = a.id AND p.payment_status = 'completed') as sales
     FROM artworks a WHERE a.artist_id = {$user['id']} AND a.is_active = 1
     ORDER BY a.created_at DESC"
)->fetch_all(MYSQLI_ASSOC);

$cats = $conn->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);

$activeTab = $_GET['tab'] ?? 'works';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Artist Studio — ArtVault</title>
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
        <div class="nav-section">Studio</div>
        <a href="?tab=works"  class="nav-item <?= $activeTab === 'works'  ? 'active' : '' ?>">🖼 My Works</a>
        <a href="?tab=upload" class="nav-item <?= $activeTab === 'upload' ? 'active' : '' ?>">↑ Upload New</a>
        <a href="?tab=sales"  class="nav-item <?= $activeTab === 'sales'  ? 'active' : '' ?>">$ Sales</a>
        <div class="nav-section">Account</div>
        <a href="../index.php" class="nav-item">← Public Gallery</a>
        <a href="../logout.php" class="nav-item">Sign Out</a>
    </aside>

    <main class="main-content">
        <div style="margin-bottom:2rem;">
            <div class="section-label">Artist Studio</div>
            <h2>Welcome, <?= htmlspecialchars($user['name'] ?: $user['username']) ?></h2>
        </div>

        <?php if ($error):   ?><div class="alert alert-error"   data-auto-dismiss><?= $error   ?></div><?php endif; ?>
        <?php if ($success): ?><div class="alert alert-success" data-auto-dismiss><?= $success ?></div><?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Artworks</div>
                <div class="stat-value"><?= $stats['total_works'] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Sales</div>
                <div class="stat-value"><?= $stats['total_sales'] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Revenue</div>
                <div class="stat-value">$<?= number_format($stats['total_revenue'], 0) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Views</div>
                <div class="stat-value"><?= number_format($stats['total_views']) ?></div>
            </div>
        </div>

        <!-- ── TAB: UPLOAD ── -->
        <?php if ($activeTab === 'upload'): ?>
        <div style="max-width:600px;">
            <h3 style="margin-bottom:1.5rem;">Upload Artwork</h3>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload">

                <!-- Upload zone -->
                <div class="upload-zone" id="upload-zone" style="margin-bottom:1.5rem;">
                    <img id="upload-preview" style="display:none;max-height:200px;margin:0 auto 1rem;border-radius:var(--radius);">
                    <div class="upload-placeholder">
                        <div class="upload-icon">🖼</div>
                        <p>Drag & drop your artwork here or click to select</p>
                        <p style="font-size:0.72rem;margin-top:0.5rem;">JPEG, PNG, WebP · Max 20MB</p>
                    </div>
                    <input type="file" id="artwork-file" name="artwork" accept="image/jpeg,image/png,image/webp" required style="display:none;">
                </div>

                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" required maxlength="150">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" placeholder="Tell the story behind this piece..."></textarea>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category">
                            <option value="">— Select —</option>
                            <?php foreach ($cats as $c): ?>
                                <option value="<?= $c['name'] ?>"><?= $c['name'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Price (USD) *</label>
                        <input type="number" name="price" min="0" step="0.01" required placeholder="0.00">
                    </div>
                </div>

                <div style="background:rgba(201,168,76,0.06);border:1px solid rgba(201,168,76,0.2);border-radius:var(--radius);padding:1rem;font-size:0.78rem;color:var(--muted);margin-bottom:1.5rem;">
                    🔒 Your original image will be stored securely. A watermarked preview is auto-generated for the public gallery. Buyers receive the original only after purchase.
                </div>

                <button type="submit" class="btn btn-solid" style="padding:0.8rem 2rem;">Upload & Protect</button>
            </form>
        </div>

        <!-- ── TAB: MY WORKS ── -->
        <?php elseif ($activeTab === 'works'): ?>
        <h3 style="margin-bottom:1.5rem;">My Artworks (<?= count($myArt) ?>)</h3>

        <?php if (empty($myArt)): ?>
            <div style="text-align:center;padding:4rem;color:var(--muted);border:1px dashed var(--border);border-radius:var(--radius);">
                <div style="font-size:3rem;margin-bottom:1rem;">🖼</div>
                <p>You haven't uploaded any artworks yet.</p>
                <a href="?tab=upload" class="btn btn-solid" style="margin-top:1rem;display:inline-block;">Upload Your First Work</a>
            </div>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Preview</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Views</th>
                        <th>Sales</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($myArt as $a): ?>
                    <tr>
                        <td>
                            <img src="../<?= htmlspecialchars($a['preview_path']) ?>"
                                 style="width:60px;height:45px;object-fit:cover;border-radius:var(--radius);"
                                 alt="">
                        </td>
                        <td>
                            <a href="../artwork/view.php?id=<?= $a['id'] ?>" style="color:var(--text);">
                                <?= htmlspecialchars($a['title']) ?>
                            </a>
                        </td>
                        <td><span class="badge badge-muted"><?= htmlspecialchars($a['category'] ?: '—') ?></span></td>
                        <td style="color:var(--accent);">$<?= number_format($a['price'],2) ?></td>
                        <td><?= $a['views'] ?></td>
                        <td><?= $a['sales'] ?></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="artwork_id" value="<?= $a['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm"
                                        data-confirm="Remove this artwork from the gallery?">Remove</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <!-- ── TAB: SALES ── -->
        <?php elseif ($activeTab === 'sales'): ?>
        <?php
        $sales = $conn->query(
            "SELECT p.*, a.title, u.username, u.full_name
             FROM purchases p
             JOIN artworks a ON p.artwork_id = a.id
             JOIN users u ON p.buyer_id = u.id
             WHERE a.artist_id = {$user['id']} AND p.payment_status = 'completed'
             ORDER BY p.purchased_at DESC"
        )->fetch_all(MYSQLI_ASSOC);
        ?>
        <h3 style="margin-bottom:1.5rem;">Sales History</h3>
        <?php if (empty($sales)): ?>
            <p style="color:var(--muted);">No sales yet. Keep promoting your work!</p>
        <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Artwork</th>
                        <th>Buyer</th>
                        <th>Amount</th>
                        <th>Ref</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sales as $s): ?>
                    <tr>
                        <td><?= date('M j, Y', strtotime($s['purchased_at'])) ?></td>
                        <td><?= htmlspecialchars($s['title']) ?></td>
                        <td><?= htmlspecialchars($s['full_name'] ?: $s['username']) ?></td>
                        <td style="color:var(--accent);">$<?= number_format($s['amount'],2) ?></td>
                        <td style="color:var(--muted);font-size:0.75rem;"><?= $s['transaction_ref'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        <?php endif; ?>

    </main>
</div>

<script src="../assets/js/main.js"></script>
</body>
</html>
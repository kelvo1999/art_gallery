<?php
require_once 'includes/auth.php';
require_once 'includes/db.php';

$user = currentUser();

// Filters
$category = isset($_GET['cat']) ? sanitize($_GET['cat']) : '';
$search   = isset($_GET['q'])   ? sanitize($_GET['q'])   : '';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 12;
$offset   = ($page - 1) * $perPage;

// Build query
$where  = "WHERE a.is_active = 1";
$params = [];
$types  = '';

if ($category) {
    $where .= " AND a.category = ?";
    $params[] = $category;
    $types   .= 's';
}
if ($search) {
    $where .= " AND (a.title LIKE ? OR u.full_name LIKE ? OR u.username LIKE ?)";
    $like = "%$search%";
    $params = array_merge($params, [$like, $like, $like]);
    $types .= 'sss';
}

// Count
$countSql = "SELECT COUNT(*) as total FROM artworks a JOIN users u ON a.artist_id = u.id $where";
$countStmt = $conn->prepare($countSql);
if ($types) $countStmt->bind_param($types, ...$params);
$countStmt->execute();
$totalRows = $countStmt->get_result()->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $perPage);

// Fetch artworks
$sql = "SELECT a.*, u.username, u.full_name
        FROM artworks a
        JOIN users u ON a.artist_id = u.id
        $where
        ORDER BY a.is_featured DESC, a.created_at DESC
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$params[] = $perPage;
$params[] = $offset;
$types   .= 'ii';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$artworks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Categories
$cats = $conn->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ArtVault — Original Artwork Marketplace</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
@media print { .art-protected { filter: blur(20px) !important; } }
</style>
</head>
<body>

<!-- NAVBAR -->
<header class="navbar">
    <a href="index.php" class="logo">ArtVault</a>
    <nav>
        <a href="index.php">Gallery</a>
        <?php if ($user['id']): ?>
            <?php if ($user['role'] === 'artist'): ?>
                <a href="dashboard/artist.php">Studio</a>
            <?php elseif ($user['role'] === 'admin'): ?>
                <a href="dashboard/admin.php">Admin</a>
            <?php else: ?>
                <a href="dashboard/buyer.php">My Purchases</a>
            <?php endif; ?>
            <a href="logout.php" class="btn btn-sm">Sign Out</a>
        <?php else: ?>
            <a href="login.php">Sign In</a>
            <a href="register.php" class="btn btn-sm btn-solid">Join</a>
        <?php endif; ?>
    </nav>
</header>

<!-- HERO -->
<section class="hero">
    <div>
        <div class="hero-tag">Original Works &nbsp;·&nbsp; Protected &nbsp;·&nbsp; Authenticated</div>
        <h1>Where <em>Art</em> Finds<br>Its Collector</h1>
        <p>A curated marketplace for original artwork. Every piece watermarked until yours.</p>
        <div class="hero-cta">
            <a href="#gallery" class="btn btn-solid">Browse Gallery</a>
            <?php if (!$user['id']): ?>
                <a href="register.php?role=artist" class="btn">Show Your Work</a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- GALLERY -->
<section class="section" id="gallery">
    <!-- Filter bar -->
    <div class="section-header">
        <div>
            <div class="section-label">Gallery</div>
            <h2>Original Works</h2>
        </div>
        <form method="GET" style="display:flex;gap:0.7rem;flex-wrap:wrap;align-items:center;">
            <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
                   placeholder="Search artworks or artists..."
                   style="background:var(--surface);border:1px solid var(--border);color:var(--text);padding:0.5rem 1rem;font-family:inherit;font-size:0.8rem;border-radius:var(--radius);outline:none;width:220px;">
            <select name="cat" style="background:var(--surface);border:1px solid var(--border);color:var(--text);padding:0.5rem 0.8rem;font-family:inherit;font-size:0.8rem;border-radius:var(--radius);outline:none;">
                <option value="">All Categories</option>
                <?php foreach ($cats as $c): ?>
                    <option value="<?= $c['name'] ?>" <?= $category === $c['name'] ? 'selected' : '' ?>>
                        <?= $c['name'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm">Filter</button>
            <?php if ($category || $search): ?>
                <a href="index.php" class="btn btn-sm">Clear</a>
            <?php endif; ?>
        </form>
    </div>

    <?php if (empty($artworks)): ?>
        <div style="text-align:center;padding:4rem;color:var(--muted);">
            <div style="font-size:3rem;margin-bottom:1rem;">🖼</div>
            <p>No artworks found<?= $search ? " for \"$search\"" : '' ?>.</p>
        </div>
    <?php else: ?>
    <div class="gallery-grid">
        <?php foreach ($artworks as $art): ?>
        <div class="art-card" onclick="location.href='artwork/view.php?id=<?= $art['id'] ?>'">
            <div class="img-wrap">
                <img src="<?= htmlspecialchars($art['preview_path']) ?>"
                     alt="<?= htmlspecialchars($art['title']) ?>"
                     class="art-protected">
                <div class="img-overlay">
                    <span class="btn btn-sm">View Details</span>
                </div>
            </div>
            <div class="card-body">
                <div class="card-title"><?= htmlspecialchars($art['title']) ?></div>
                <div class="card-artist">
                    by <?= htmlspecialchars($art['full_name'] ?: $art['username']) ?>
                </div>
            </div>
            <div class="card-footer">
                <span class="price">$<?= number_format($art['price'], 2) ?></span>
                <span class="badge badge-muted"><?= htmlspecialchars($art['category']) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?>&cat=<?= urlencode($category) ?>&q=<?= urlencode($search) ?>"
               class="<?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</section>

<footer>
    <p>&copy; <?= date('Y') ?> ArtVault &nbsp;·&nbsp; All artwork protected by watermark technology</p>
</footer>

<script src="assets/js/main.js"></script>
</body>
</html>

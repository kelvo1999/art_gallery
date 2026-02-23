<?php
require_once '../includes/auth.php';
require_once '../includes/db.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ../index.php'); exit; }

// Fetch artwork + artist info
$stmt = $conn->prepare(
    "SELECT a.*, u.username, u.full_name, u.bio, u.profile_pic
     FROM artworks a JOIN users u ON a.artist_id = u.id
     WHERE a.id = ? AND a.is_active = 1 LIMIT 1"
);
$stmt->bind_param('i', $id);
$stmt->execute();
$art = $stmt->get_result()->fetch_assoc();

if (!$art) { header('Location: ../index.php?error=notfound'); exit; }

// Increment views
$conn->query("UPDATE artworks SET views = views + 1 WHERE id = $id");

$user        = currentUser();
$purchased   = $user['id'] ? hasPurchased($conn, $user['id'], $id) : false;
$isOwnWork   = $user['id'] && $user['id'] == $art['artist_id'];

// Display the right image
$displayImg = ($purchased || $isOwnWork) ? $art['original_path'] : $art['preview_path'];

// Other works by same artist
$others = $conn->prepare(
    "SELECT id, title, preview_path, price FROM artworks
     WHERE artist_id = ? AND id != ? AND is_active = 1 ORDER BY created_at DESC LIMIT 4"
);
$others->bind_param('ii', $art['artist_id'], $id);
$others->execute();
$otherWorks = $others->get_result()->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($art['title']) ?> — ArtVault</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
@media print { .art-protected { filter: blur(20px) !important; } }
</style>
</head>
<body>

<header class="navbar">
    <a href="../index.php" class="logo">ArtVault</a>
    <nav>
        <a href="../index.php">← Gallery</a>
        <?php if ($user['id']): ?>
            <a href="../logout.php" class="btn btn-sm">Sign Out</a>
        <?php else: ?>
            <a href="../login.php" class="btn btn-sm btn-solid">Sign In</a>
        <?php endif; ?>
    </nav>
</header>

<main class="artwork-detail">
    <!-- LEFT: Image -->
    <div>
        <div class="artwork-preview-wrap">
            <img src="../<?= htmlspecialchars($displayImg) ?>"
                 alt="<?= htmlspecialchars($art['title']) ?>"
                 class="art-protected">
            <?php if (!$purchased && !$isOwnWork): ?>
            <div style="position:absolute;bottom:0;left:0;right:0;padding:0.7rem 1rem;background:rgba(0,0,0,0.7);font-size:0.72rem;color:var(--muted);text-align:center;letter-spacing:0.08em;">
                Preview only — watermarked. Purchase to download original.
            </div>
            <?php elseif ($purchased): ?>
            <div style="position:absolute;top:1rem;right:1rem;">
                <span class="badge badge-success">✓ Original</span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Other works by this artist -->
        <?php if ($otherWorks): ?>
        <div style="margin-top:2.5rem;">
            <div class="section-label mb-2">More by this Artist</div>
            <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;">
                <?php foreach ($otherWorks as $ow): ?>
                <a href="view.php?id=<?= $ow['id'] ?>" class="art-card" style="text-decoration:none;">
                    <div class="img-wrap" style="aspect-ratio:1;">
                        <img src="../<?= htmlspecialchars($ow['preview_path']) ?>"
                             alt="<?= htmlspecialchars($ow['title']) ?>"
                             class="art-protected">
                    </div>
                    <div class="card-footer" style="padding:0.6rem 0.8rem;">
                        <span style="font-size:0.75rem;color:var(--muted);"><?= htmlspecialchars($ow['title']) ?></span>
                        <span class="price" style="font-size:0.8rem;">$<?= number_format($ow['price'],2) ?></span>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- RIGHT: Info panel -->
    <div class="artwork-info">
        <div class="category-tag"><?= htmlspecialchars($art['category']) ?></div>
        <h1 style="font-size:2.5rem;"><?= htmlspecialchars($art['title']) ?></h1>

        <div class="artwork-info artist-row">
            <div>
                <div style="font-size:0.72rem;color:var(--muted);letter-spacing:0.08em;">ARTIST</div>
                <a href="../index.php?q=<?= urlencode($art['username']) ?>" style="font-family:'Cormorant Garamond',serif;font-size:1.2rem;color:var(--text);">
                    <?= htmlspecialchars($art['full_name'] ?: $art['username']) ?>
                </a>
            </div>
        </div>

        <?php if ($art['description']): ?>
        <p style="color:var(--muted);font-size:0.85rem;margin-bottom:1.5rem;line-height:1.8;">
            <?= nl2br(htmlspecialchars($art['description'])) ?>
        </p>
        <?php endif; ?>

        <div class="price-big">$<?= number_format($art['price'], 2) ?></div>

        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:2rem;">
            <span style="font-size:0.7rem;color:var(--muted);">👁 <?= $art['views'] ?> views</span>
        </div>

        <?php if ($isOwnWork): ?>
            <div class="alert alert-success">This is your artwork.</div>
            <a href="../dashboard/artist.php" class="btn w-full" style="padding:0.9rem;text-align:center;display:block;">
                Manage in Studio
            </a>

        <?php elseif ($purchased): ?>
            <div class="alert alert-success mb-2">You own this piece.</div>
            <a href="download.php?id=<?= $art['id'] ?>" class="btn btn-solid w-full" style="padding:0.9rem;text-align:center;display:block;">
                ↓ Download Original
            </a>

        <?php elseif ($user['id']): ?>
            <a href="../payment/checkout.php?artwork=<?= $art['id'] ?>" class="btn btn-solid w-full" style="padding:0.9rem;text-align:center;display:block;">
                Purchase Original — $<?= number_format($art['price'], 2) ?>
            </a>

        <?php else: ?>
            <p style="color:var(--muted);font-size:0.82rem;margin-bottom:1rem;">
                Sign in or create an account to purchase this artwork.
            </p>
            <div style="display:flex;gap:0.8rem;">
                <a href="../login.php" class="btn w-full" style="text-align:center;">Sign In</a>
                <a href="../register.php" class="btn btn-solid w-full" style="text-align:center;">Register</a>
            </div>
        <?php endif; ?>

        <!-- Artwork metadata -->
        <div style="margin-top:2rem;padding-top:1.5rem;border-top:1px solid var(--border);">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div>
                    <div style="font-size:0.65rem;letter-spacing:0.12em;text-transform:uppercase;color:var(--muted);margin-bottom:0.3rem;">Listed</div>
                    <div style="font-size:0.82rem;"><?= date('M j, Y', strtotime($art['created_at'])) ?></div>
                </div>
                <div>
                    <div style="font-size:0.65rem;letter-spacing:0.12em;text-transform:uppercase;color:var(--muted);margin-bottom:0.3rem;">Category</div>
                    <div style="font-size:0.82rem;"><?= htmlspecialchars($art['category'] ?: '—') ?></div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="../assets/js/main.js"></script>
</body>
</html>
<?php
// artwork/download.php — Secure download of original (post-purchase only)
require_once '../includes/auth.php';
require_once '../includes/db.php';

requireLogin();

$artwork_id = (int)($_GET['id'] ?? 0);
$user       = currentUser();

if (!$artwork_id) {
    http_response_code(400);
    die('Invalid request.');
}

// Fetch artwork
$stmt = $conn->prepare("SELECT * FROM artworks WHERE id = ? AND is_active = 1 LIMIT 1");
$stmt->bind_param('i', $artwork_id);
$stmt->execute();
$art = $stmt->get_result()->fetch_assoc();

if (!$art) {
    http_response_code(404);
    die('Artwork not found.');
}

// Allow: owner artist OR admin OR buyer who has completed purchase
$canDownload = ($user['role'] === 'admin')
    || ($art['artist_id'] == $user['id'])
    || hasPurchased($conn, $user['id'], $artwork_id);

if (!$canDownload) {
    http_response_code(403);
    die('Access denied. Please purchase this artwork to download the original.');
}

// Resolve absolute path of original
$originalPath = realpath(__DIR__ . '/../' . $art['original_path']);

if (!$originalPath || !file_exists($originalPath)) {
    http_response_code(404);
    die('Original file not found. Please contact support.');
}

// Log the download
$log = $conn->prepare("INSERT INTO download_logs (user_id, artwork_id) VALUES (?, ?)");
$log->bind_param('ii', $user['id'], $artwork_id);
$log->execute();

// Determine MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $originalPath);
finfo_close($finfo);

$ext      = strtolower(pathinfo($originalPath, PATHINFO_EXTENSION));
$filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $art['title']) . '.' . $ext;

// Serve the file
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($originalPath));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Prevent output buffering before sending file
while (ob_get_level()) ob_end_clean();
flush();

readfile($originalPath);
exit;
<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success'=>false,'error'=>'Not logged in']); exit;
}

// GET: load comments for a post
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $postId = (int)($_GET['post_id'] ?? 0);
    if (!$postId) { echo json_encode([]); exit; }

    $stmt = $db->prepare(
        "SELECT pc.content, pc.created_at, u.name AS author
         FROM post_comments pc JOIN users u ON u.id=pc.user_id
         WHERE pc.post_id=? AND pc.status='active'
         ORDER BY pc.created_at ASC LIMIT 50"
    );
    $stmt->bind_param("i", $postId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'author'  => htmlspecialchars($r['author'], ENT_QUOTES, 'UTF-8'),
            'content' => htmlspecialchars($r['content'], ENT_QUOTES, 'UTF-8'),
            'time'    => timeAgo($r['created_at']),
        ];
    }
    echo json_encode($out); exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    echo json_encode(['success'=>false,'error'=>'CSRF error']); exit;
}

$postId  = (int)($_POST['post_id']  ?? 0);
$content = trim($_POST['content']   ?? '');
$userId  = currentUserId();

if (!$postId || !$content) {
    echo json_encode(['success'=>false,'error'=>'Missing data']); exit;
}

$stmt = $db->prepare("INSERT INTO post_comments (post_id, user_id, content) VALUES (?, ?, ?)");
$stmt->bind_param("iis", $postId, $userId, $content);
$stmt->execute();
$commentId = $db->insert_id;

// Notify post author
$stmt = $db->prepare("SELECT user_id, group_id FROM posts WHERE id=?");
$stmt->bind_param("i", $postId);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();
if ($post && $post['user_id'] != $userId) {
    $cu  = currentUser();
    $msg = $cu['name'] . ' commented on your post.';
    $lnk = 'feed.php' . ($post['group_id'] ? '?group_id=' . $post['group_id'] : '');
    sendNotification($db, $post['user_id'], $userId, $post['group_id'], 'post_comment', $msg, $lnk);
}

// Get commenter info
$cu   = currentUser();
$name = $cu['name'];
$photo= $cu['photo'];

// Build avatar HTML
$avatarHtml = '';
if ($photo && file_exists(UPLOAD_PROFILE . $photo)) {
    $url = '../uploads/profile/' . htmlspecialchars($photo, ENT_QUOTES);
    $avatarHtml = "<img src=\"{$url}\" class=\"avatar\" style=\"width:28px;height:28px;\" alt=\"\">";
} else {
    $parts    = explode(' ', trim($name));
    $initials = '';
    foreach ($parts as $p) $initials .= strtoupper(substr($p,0,1));
    $initials = substr($initials,0,2);
    $colors   = ['#3b82f6','#8b5cf6','#ec4899','#f97316','#10b981','#0891b2'];
    $color    = $colors[abs(crc32($name)) % count($colors)];
    $avatarHtml = "<div class=\"avatar avatar-initials\" style=\"width:28px;height:28px;background:{$color};font-size:11px\">{$initials}</div>";
}

$safeContent = nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'));

$html = <<<HTML
<div class="comment-item">
  {$avatarHtml}
  <div class="comment-bubble">
    <div class="comment-author-name">{$name}</div>
    <div class="comment-text">{$safeContent}</div>
    <div class="comment-time">just now</div>
  </div>
</div>
HTML;

echo json_encode(['success'=>true,'html'=>$html]);

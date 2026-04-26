<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success'=>false,'error'=>'Not logged in']); exit;
}

$token  = $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    echo json_encode(['success'=>false,'error'=>'CSRF error']); exit;
}

$postId = (int)($_POST['post_id'] ?? 0);
$userId = currentUserId();

if (!$postId) {
    echo json_encode(['success'=>false,'error'=>'Invalid post']); exit;
}

// Check if already liked
$stmt = $db->prepare("SELECT id FROM post_likes WHERE post_id=? AND user_id=?");
$stmt->bind_param("ii", $postId, $userId);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();

if ($existing) {
    $stmt = $db->prepare("DELETE FROM post_likes WHERE post_id=? AND user_id=?");
    $stmt->bind_param("ii", $postId, $userId);
    $stmt->execute();
    $liked = false;
} else {
    $stmt = $db->prepare("INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $postId, $userId);
    $stmt->execute();
    $liked = true;

    // Notify post author
    $stmt = $db->prepare("SELECT user_id, group_id FROM posts WHERE id=?");
    $stmt->bind_param("i", $postId);
    $stmt->execute();
    $post = $stmt->get_result()->fetch_assoc();
    if ($post && $post['user_id'] != $userId) {
        $cu  = currentUser();
        $msg = $cu['name'] . ' liked your post.';
        $lnk = 'feed.php' . ($post['group_id'] ? '?group_id=' . $post['group_id'] : '');
        sendNotification($db, $post['user_id'], $userId, $post['group_id'], 'post_like', $msg, $lnk);
    }
}

// New count
$stmt = $db->prepare("SELECT COUNT(*) FROM post_likes WHERE post_id=?");
$stmt->bind_param("i", $postId);
$stmt->execute();
$count = $stmt->get_result()->fetch_row()[0];

echo json_encode(['success'=>true,'liked'=>$liked,'count'=>(int)$count]);

<?php
require_once __DIR__ . '/config.php';
checkLogin();

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$isAdmin = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';
$isPrivileged = in_array(strtolower($_SESSION['user']['role'] ?? ''), ['admin', 'manager', 'developer', 'owner']);

if ($action === 'toggle_pin' && $isPrivileged) {
    $isPinned = isset($_POST['is_pinned']) ? (int)$_POST['is_pinned'] : 0;
    $stmt = $pdo->prepare("UPDATE posts SET is_pinned = ? WHERE id = ?");
    $stmt->execute([$isPinned, $id]);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'delete') {
    // Verify ownership or privilege
    $stmt = $pdo->prepare("SELECT created_by FROM posts WHERE id = ?");
    $stmt->execute([$id]);
    $post = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $isOwner = $post && isset($_SESSION['user']['username']) && $_SESSION['user']['username'] === $post['created_by'];
    $canDelete = $isOwner || $isPrivileged;
    
    if ($canDelete) {
        $stmt = $pdo->prepare("DELETE FROM posts WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['ok' => true]);
    } else {
        echo json_encode(['ok' => false, 'error' => 'Permission denied']);
    }
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Invalid action']);
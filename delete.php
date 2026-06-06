<?php
require_once __DIR__ . '/config.php';
checkLogin();

if (!isAdmin()) {
    echo 'Access denied. Only admin can delete posts.';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ./');
    exit;
}

$id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
if ($id <= 0) {
    header('Location: ./');
    exit;
}

$stmt = $pdo->prepare('SELECT images FROM posts WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if ($post) {
    $images = array_filter(array_map('trim', explode(',', $post['images'])));
    foreach ($images as $image) {
        $path = __DIR__ . '/uploads/' . $image;
        if (is_file($path)) {
            @unlink($path);
        }
    }

    $stmt = $pdo->prepare('DELETE FROM posts WHERE id = ?');
    $stmt->execute([$id]);
}

header('Location: ./');
exit;

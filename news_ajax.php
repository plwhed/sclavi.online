<?php
require_once __DIR__ . '/config.php';
checkLogin();

header('Content-Type: application/json');

if (!isAdmin()) {
    echo json_encode(['ok' => false, 'error' => 'Access denied']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'add') {
    $title = trim($_POST['title'] ?? '');
    $url   = trim($_POST['url'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    if ($title === '') { echo json_encode(['ok' => false]); exit; }

    $stmt = $pdo->prepare('INSERT INTO news (title, url, description, created_at) VALUES (?, ?, ?, NOW())');
    $stmt->execute([$title, $url, $desc]);
    echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);

} elseif ($action === 'edit') {
    $id    = (int)($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $url   = trim($_POST['url'] ?? '');
    $desc  = trim($_POST['description'] ?? '');
    if ($id <= 0 || $title === '') { echo json_encode(['ok' => false]); exit; }

    $stmt = $pdo->prepare('UPDATE news SET title = ?, url = ?, description = ? WHERE id = ?');
    $stmt->execute([$title, $url, $desc, $id]);
    echo json_encode(['ok' => true]);

} elseif ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) { echo json_encode(['ok' => false]); exit; }

    $stmt = $pdo->prepare('DELETE FROM news WHERE id = ?');
    $stmt->execute([$id]);
    echo json_encode(['ok' => true]);

} else {
    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
}
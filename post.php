<?php
require_once __DIR__ . '/config.php';
checkLogin();
ensurePostsUpdatedAt($pdo);
$postDateField = getPostsDateField($pdo);

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    echo 'Invalid post ID.';
    exit;
}

$dateField = $postDateField === 'updated_at' ? 'updated_at' : 'created_at';
$stmt = $pdo->prepare("SELECT id, title, description, images, links, created_by, created_at, $dateField AS updated_at FROM posts WHERE id = ? LIMIT 1");
$stmt->execute([$id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    echo 'Post not found.';
    exit;
}

$viewsStmt = $pdo->prepare("UPDATE posts SET views = COALESCE(views, 0) + 1 WHERE id = ?");
$viewsStmt->execute([$id]);

$viewsCountStmt = $pdo->prepare("SELECT COALESCE(views, 0) as views FROM posts WHERE id = ?");
$viewsCountStmt->execute([$id]);
$viewsData = $viewsCountStmt->fetch(PDO::FETCH_ASSOC);
$post['views'] = $viewsData['views'] ?? 0;

$isAdmin = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';
$isOwner = isset($_SESSION['user']['username']) && $_SESSION['user']['username'] === $post['created_by'];

$roleColors = [
    'admin' => '#ff0000',
    'developer' => '#ff0000',
    'manager' => '#bb71e4',
    'owner' => '#7171e4',
    'mod' => '#39ff14',
    'rich' => '#FFD700',
    'user' => '#FFFFFF',
];

$userRole = $_SESSION['user']['role'] ?? 'user';
$roleColor = $roleColors[strtolower($userRole)] ?? '#FFFFFF';

$isPrivileged = in_array(strtolower($userRole), ['admin', 'manager', 'developer', 'owner']);
$canDelete = $isOwner || $isPrivileged;

$post['is_pinned'] = $post['is_pinned'] ?? false;
$post['category'] = $post['category'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($post['title']); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            background: #1b1b1b;
            color: #ffffff;
            font-family: Arial, sans-serif;
            padding: 24px;
        }

        a { color: #4da6ff; text-decoration: none; }
        a:hover { text-decoration: underline; }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #9bbcff;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }
        .back-link:hover { color: #4da6ff; }

        .page-layout {
            display: flex;
            gap: 24px;
            flex-wrap: wrap;
            align-items: flex-start;
        }
        .main-content { flex: 1 1 560px; min-width: 260px; }
        .news-sidebar { width: 320px; min-width: 260px; }

        .news-card {
            background: #121212;
            border: 1px solid #2a2a2a;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 10px 24px rgba(0,0,0,0.25);
            margin-bottom: 20px;
        }
        .news-card h2 {
            margin-top: 0;
            font-size: 1.3rem;
            color: #ffffff;
            margin-bottom: 16px;
        }

        .post-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 8px;
        }

        .post-meta {
            color: #8f9bb3;
            font-size: 0.85rem;
            margin-bottom: 16px;
        }
        .post-meta .author {
            color: #ffffff;
            font-weight: 600;
        }

        .post-content {
            line-height: 1.7;
            color: #ddd;
            font-size: 0.95rem;
            white-space: pre-wrap;
        }
        .post-content pre {
            white-space: pre-wrap;
            word-wrap: break-word;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
            color: #e0e0e0;
            background: #0a0a0a;
            padding: 16px;
            border-radius: 10px;
            border: 1px solid #1f1f1f;
            overflow-x: auto;
        }

        .image-list {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 16px;
        }
        .image-item {
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #2a2a2a;
            background: #0a0a0a;
            cursor: pointer;
        }
        .image-item img {
            display: block;
            width: 240px;
            height: 160px;
            object-fit: cover;
            transition: transform 0.2s;
        }
        .image-item:hover img {
            transform: scale(1.02);
        }
        .image-item .img-name {
            padding: 6px 10px;
            font-size: 11px;
            color: #888;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 240px;
        }

        .link-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .link-list li {
            margin-bottom: 8px;
            padding: 10px 14px;
            background: #1a1a1a;
            border-radius: 8px;
            border: 1px solid #2a2a2a;
        }
        .link-list a {
            color: #8ac6ff;
            word-break: break-all;
            font-size: 0.9rem;
        }
        .link-list a:hover {
            text-decoration: underline;
        }

        .info-box {
            margin-bottom: 16px;
        }
        .info-box:last-child { margin-bottom: 0; }
        .info-label {
            display: block;
            color: #8f9bb3;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .info-value {
            color: #ffffff;
            font-size: 0.95rem;
            word-break: break-word;
        }

        .stats-row {
            display: flex;
            gap: 12px;
            margin-bottom: 16px;
        }
        .stat-item {
            flex: 1;
            background: #1a1a1a;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
            border: 1px solid #2a2a2a;
        }
        .stat-item .stat-label {
            color: #8f9bb3;
            font-size: 0.7rem;
            margin-bottom: 4px;
            text-transform: uppercase;
        }
        .stat-item .stat-value {
            color: #ffffff;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .pinned-badge {
            background: rgba(234, 179, 8, 0.1);
            border: 1px solid rgba(234, 179, 8, 0.3);
            border-radius: 8px;
            padding: 10px;
            text-align: center;
            margin-bottom: 16px;
        }
        .pinned-badge p {
            color: #eab308;
            font-size: 0.85rem;
            margin: 0;
        }

        .btn {
            padding: 10px 16px;
            border-radius: 8px;
            border: 1px solid transparent;
            font-weight: 700;
            font-size: 0.85rem;
            cursor: pointer;
            transition: all 0.2s;
            font-family: Arial, sans-serif;
            display: inline-block;
            text-align: center;
        }
        .btn-primary {
            background: #4da6ff;
            color: #101820;
        }
        .btn-primary:hover { background: #3b93e0; }
        .btn-secondary {
            background: transparent;
            border-color: #474747;
            color: #ffffff;
        }
        .btn-secondary:hover { background: #494949; }
        .btn-danger {
            background: #dc2626;
            color: #fff;
        }
        .btn-danger:hover { background: #b91c1c; }
        .btn-edit {
            background: #6ec56b;
            color: #101820;
        }
        .btn-edit:hover { background: #5ab457; }

        .button-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .lightbox {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.92);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 24px;
        }
        .lightbox.open { display: flex; }
        .lightbox-inner {
            max-width: 90%;
            max-height: 90%;
            position: relative;
            border-radius: 18px;
            overflow: hidden;
        }
        .lightbox img {
            width: 100%;
            height: auto;
            display: block;
            max-height: 90vh;
        }
        .lightbox-close {
            position: absolute;
            top: 14px;
            right: 14px;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            border: none;
            background: rgba(0, 0, 0, 0.6);
            color: #ffffff;
            font-size: 1.2rem;
            cursor: pointer;
        }

        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #1b1b1b; }
        ::-webkit-scrollbar-thumb { background: #333; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #444; }
    </style>
</head>
<body>
    <a href="../" class="back-link">← Înapoi la listă</a>

    <div class="page-layout">
        <main class="main-content">
            <div class="news-card">
                <div class="post-title"><?php echo htmlspecialchars($post['title']); ?></div>
                <div class="post-meta">
                    <span class="author" style="color: <?php echo $roleColor; ?>"><?php echo htmlspecialchars($post['created_by']); ?></span>
                    <span style="color: #555;">|</span>
                    <?php echo date('d M Y, H:i', strtotime($post['updated_at'])); ?>
                    <span style="color: #555;">|</span>
                    <?php echo (int)$post['views']; ?> views
                </div>

                <?php if (trim($post['description']) !== ''): ?>
                    <div class="post-content">
                        <pre><?php echo htmlspecialchars($post['description']); ?></pre>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($post['images'])):
                $images = explode(',', $post['images']);
            ?>
                <div class="news-card">
                    <h2>📷 Imagini</h2>
                    <div class="image-list">
                        <?php foreach ($images as $image): ?>
                            <?php $image = trim($image); if ($image === '') continue; ?>
                            <div class="image-item" onclick="openLightbox('../uploads/<?php echo rawurlencode($image); ?>', '<?php echo htmlspecialchars($image, ENT_QUOTES); ?>')">
                                <img src="../uploads/<?php echo rawurlencode($image); ?>" alt="<?php echo htmlspecialchars($image); ?>" onerror="this.style.display='none'; this.parentElement.style.display='none';">
                                <div class="img-name"><?php echo htmlspecialchars($image); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (trim($post['links']) !== ''):
                $links = explode("\n", trim($post['links']));
            ?>
                <div class="news-card">
                    <h2>🔗 Linkuri</h2>
                    <ul class="link-list">
                        <?php foreach ($links as $link): ?>
                            <?php $link = trim($link); if ($link === '') continue; ?>
                            <li><a href="<?php echo htmlspecialchars($link); ?>" target="_blank" rel="noopener"><?php echo htmlspecialchars($link); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </main>

        <aside class="news-sidebar">
            <div class="news-card">
                <h2>ℹ️ Info</h2>

                <div class="info-box">
                    <span class="info-label">Titlu</span>
                    <div class="info-value"><?php echo htmlspecialchars($post['title']); ?></div>
                </div>

                <?php if (!empty($post['category'])): ?>
                <div class="info-box">
                    <span class="info-label">Categorie</span>
                    <div class="info-value"><?php echo htmlspecialchars($post['category']); ?></div>
                </div>
                <?php endif; ?>

                <div class="info-box">
                    <span class="info-label">Autor</span>
                    <div class="info-value" style="color: <?php echo $roleColor; ?>">@<?php echo htmlspecialchars($post['created_by']); ?></div>
                </div>

                <div class="stats-row">
                    <div class="stat-item">
                        <div class="stat-label">Views</div>
                        <div class="stat-value"><?php echo (int)$post['views']; ?></div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Creat</div>
                        <div class="stat-value" style="font-size: 0.85rem;"><?php echo date('d M Y', strtotime($post['created_at'])); ?></div>
                    </div>
                </div>

                <?php if ($post['is_pinned']): ?>
                <div class="pinned-badge">
                    <p>📌 Pinned Post</p>
                </div>
                <?php endif; ?>

                <?php if ($isAdmin || $canDelete): ?>
                <div class="button-row">
                    <?php if ($isAdmin): ?>
                    <a href="../edit/<?php echo htmlspecialchars($post['id']); ?>" class="btn btn-edit" style="text-decoration:none;">✏️ Edit</a>
                    <?php endif; ?>
                    <?php if ($canDelete): ?>
                    <button class="btn btn-danger" onclick="deletePost()">🗑 Delete</button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </aside>
    </div>

    <div id="lightbox" class="lightbox" onclick="closeLightbox(event)">
        <div class="lightbox-inner">
            <button type="button" class="lightbox-close" onclick="closeLightboxBtn(event)">×</button>
            <img id="lightbox-img" src="" alt="">
        </div>
    </div>

    <script>
        function deletePost() {
            if (!confirm('Sigur vrei să ștergi acest post?')) return;
            var formData = new FormData();
            formData.append('action', 'delete');
            formData.append('id', <?php echo (int)$post['id']; ?>);

            fetch('../post_ajax.php', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.ok) { window.location.href = '../'; }
                else { alert('Eroare: ' + (data.error || 'Unknown error')); }
            })
            .catch(function() { alert('Eroare la comunicare cu serverul!'); });
        }

        function openLightbox(src, caption) {
            var lb = document.getElementById('lightbox');
            var img = document.getElementById('lightbox-img');
            img.src = src;
            img.alt = caption;
            lb.classList.add('open');
        }

        function closeLightbox(e) {
            if (e.target === document.getElementById('lightbox')) {
                document.getElementById('lightbox').classList.remove('open');
                document.getElementById('lightbox-img').src = '';
            }
        }

        function closeLightboxBtn(e) {
            e.stopPropagation();
            document.getElementById('lightbox').classList.remove('open');
            document.getElementById('lightbox-img').src = '';
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.getElementById('lightbox').classList.remove('open');
                document.getElementById('lightbox-img').src = '';
            }
        });
    </script>
</body>
</html>
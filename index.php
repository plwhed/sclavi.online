<?php
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
$basePath = rtrim($scriptDir, '/');
$path = '/' . ltrim((string) substr($requestUri, strlen($basePath)), '/');

if ($path !== '/' && $path !== '/index.php') {
    $segments = explode('/', trim($path, '/'));
    $route = $segments[0] ?? '';

    switch ($route) {
        case 'login':
            require __DIR__ . '/login.php';
            exit;
        case 'logout':
            require __DIR__ . '/logout.php';
            exit;
        case 'add':
            require __DIR__ . '/add.php';
            exit;
        case 'users':
            require __DIR__ . '/users.php';
            exit;
        case 'news_ajax':
            require __DIR__ . '/news_ajax.php';
            exit;
        case 'delete':
            require __DIR__ . '/delete.php';
            exit;
        case 'profile':
            require __DIR__ . '/profile.php';
            exit;
        case 'post':
            if (!empty($segments[1]) && ctype_digit($segments[1])) {
                $_GET['id'] = $segments[1];
                require __DIR__ . '/post.php';
            } else {
                http_response_code(404);
                echo 'Page not found.';
            }
            exit;
        case 'edit':
            if (!empty($segments[1]) && ctype_digit($segments[1])) {
                $_GET['id'] = $segments[1];
                require __DIR__ . '/edit.php';
            } else {
                http_response_code(404);
                echo 'Page not found.';
            }
            exit;
        default:
            http_response_code(404);
            echo 'Page not found.';
            exit;
    }
}

require_once __DIR__ . '/config.php';
checkLogin();
ensurePostsUpdatedAt($pdo);

$postDateField = getPostsDateField($pdo);
$postDateSelect = $postDateField === 'updated_at' ? 'updated_at' : 'created_at';

// Fetch posts: pinned first, then by date desc
$hasPinned = tableHasColumn($pdo, 'posts', 'pinned');
$orderBy = '';
if ($hasPinned) {
    $orderBy = 'ORDER BY pinned DESC, ' . $postDateSelect . ' DESC';
} else {
    $orderBy = 'ORDER BY ' . $postDateSelect . ' DESC';
}

$stmt = $pdo->query("SELECT id, title, description, images, links, created_by, created_at, pinned, $postDateSelect AS updated_at FROM posts $orderBy");
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

$newsStmt = $pdo->query('SELECT id, title, url, description FROM news ORDER BY created_at DESC');
$newsItems = $newsStmt->fetchAll(PDO::FETCH_ASSOC);

$isAdmin = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';

// Fetch last 5 users for admin card
$lastUsers = [];
if ($isAdmin) {
    $usersStmt = $pdo->query('SELECT id, username, role, created_at FROM users ORDER BY created_at DESC LIMIT 5');
    $lastUsers = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Home</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            background: #1b1b1b;
            color: #ffffff;
            font-family: Arial, sans-serif;
            padding: 24px;
        }
        a { color: #4da6ff; }
        .edit-link { color: #6ec56b; }
        .site-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            padding: 16px 20px;
            margin-bottom: 20px;
            background: #101010;
            border: 1px solid #232323;
            border-radius: 16px;
        }
        .site-title {
            color: #ffffff;
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: 0.02em;
            text-transform: uppercase;
        }
        .nav-links {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: center;
        }
        .nav-links a {
            color: #9bbcff;
            text-decoration: none;
            font-size: 0.95rem;
        }
        .nav-links a:hover {
            text-decoration: underline;
        }
        .post-date {
            color: #8f9bb3;
            font-size: 0.85rem;
            margin-left: 8px;
        }
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
        }
        .news-card h2 { margin-top: 0; font-size: 1.3rem; color: #ffffff; }

        /* Pinned post styles */
        .pinned-post {
            /* background: linear-gradient(135deg, #2a2200 0%, #1f1a00 100%) !important; */
            /* border: 1px solid #d4a017 !important; */
            border-radius: 12px;
            /* padding: 14px 16px; */
            margin-bottom: 12px;
            list-style: none;
        }
        .pinned-post .pin-icon {
            /* color: #d4a017; */
            font-size: 14px;
            margin-right: 6px;
        }
        .pinned-post a {
            color: #ffd700;
            font-weight: 700;
        }
        .pinned-post .post-date {
            color: #b8a060;
        }
        .pinned-post .edit-link {
            color: #8fd48a;
        }
        .pinned-post .delete-button {
            color: #ff6b6b;
        }

        /* Regular post styles */
        .regular-post {
            padding: 10px 0;
            /* border-bottom: 1px solid #222; */
            list-style: none;
        }
        .regular-post:last-child {
            border-bottom: none;
        }

        /* news list */
        .news-list { list-style: none; padding: 0; margin: 0; }
        .news-list li {
            border-bottom: 1px solid #222;
        }
        .news-list li:last-child { border-bottom: none; }

        .news-item-header {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            padding: 10px 0;
            cursor: pointer;
            user-select: none;
        }

        .news-chevron {
            flex-shrink: 0;
            font-size: 10px;
            color: #555;
            margin-top: 4px;
            transition: transform .2s;
            line-height: 1;
        }
        .news-list li.open .news-chevron { transform: rotate(90deg); }

        .news-title-wrap { flex: 1; }
        .news-title-link {
            color: #ffffff;
            text-decoration: none;
            font-size: 14px;
            line-height: 1.4;
            display: block;
        }
        .news-title-link:hover { text-decoration: underline; }
        .news-title-text {
            color: #ffffff;
            font-size: 14px;
            line-height: 1.4;
            display: block;
        }

        .news-admin-actions {
            display: flex;
            gap: 6px;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .news-btn {
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 0;
            font-size: 12px;
            text-decoration: underline;
            line-height: 1.4;
        }
        .news-btn-edit { color: #6ec56b; }
        .news-btn-del  { color: #d94a4a; }

        /* collapsible body */
        .news-item-body {
            overflow: hidden;
            max-height: 0;
            transition: max-height .25s ease, padding .25s ease;
            padding: 0 0 0 18px;
        }
        .news-list li.open .news-item-body {
            max-height: 600px;
            padding: 0 0 12px 18px;
        }
        .news-item-desc {
            font-size: 13px;
            color: #aaa;
            line-height: 1.6;
            white-space: pre-wrap;
            margin: 0 0 8px;
        }
        .news-item-readmore {
            font-size: 12px;
            color: #4da6ff;
            text-decoration: none;
        }
        .news-item-readmore:hover { text-decoration: underline; }

        /* add/edit form */
        .news-add-form {
            margin-top: 14px;
            border-top: 1px solid #2a2a2a;
            padding-top: 14px;
            display: none;
        }
        .news-add-form.open { display: block; }
        .news-add-form input[type="text"],
        .news-add-form textarea {
            width: 100%;
            padding: 8px 10px;
            border-radius: 8px;
            border: 1px solid #333;
            background: #1b1b1b;
            color: #fff;
            font-size: 13px;
            box-sizing: border-box;
            margin-bottom: 8px;
            font-family: Arial, sans-serif;
        }
        .news-add-form input[type="text"]:focus,
        .news-add-form textarea:focus { outline: none; border-color: #4da6ff; }
        .news-add-form textarea { resize: vertical; min-height: 72px; }
        .news-form-actions { display: flex; gap: 8px; }
        .news-save-btn {
            flex: 1; padding: 8px; border: none; border-radius: 8px;
            background: #4da6ff; color: #101820; font-size: 13px;
            font-weight: 700; cursor: pointer;
        }
        .news-save-btn:hover { background: #3b93e0; }
        .news-cancel-btn {
            padding: 8px 12px; border: 1px solid #444; border-radius: 8px;
            background: transparent; color: #aaa; font-size: 13px; cursor: pointer;
        }
        .news-cancel-btn:hover { border-color: #666; color: #ccc; }
        .news-toggle-add {
            display: block; width: 100%; margin-top: 14px; padding: 8px;
            border: 1px dashed #444; border-radius: 8px; background: transparent;
            color: #888; font-size: 12px; cursor: pointer; text-align: center;
            box-sizing: border-box;
        }
        .news-toggle-add:hover { border-color: #666; color: #aaa; }
        .news-empty { color: #555; font-size: 13px; padding: 8px 0; display: block; }

        /* === LAST USERS CARD STYLES === */
        .users-card { margin-top: 20px; }
        .users-list { list-style: none; padding: 0; margin: 0; }
        .users-list li {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #222;
        }
        .users-list li:last-child { border-bottom: none; }
        .user-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .user-name {
            font-size: 14px;
            color: #ffffff;
            font-weight: 600;
        }
        .user-date {
            font-size: 11px;
            color: #666;
        }
        .user-role {
            font-size: 10px;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .user-role.admin {
            background: rgba(77, 166, 255, 0.15);
            color: #4da6ff;
        }
        .user-role.viewer {
            background: rgba(110, 197, 107, 0.15);
            color: #6ec56b;
        }
        .users-empty {
            color: #555;
            font-size: 13px;
            padding: 8px 0;
            display: block;
        }
        .users-view-all {
            display: block;
            width: 100%;
            margin-top: 14px;
            padding: 10px;
            border: 1px solid #4da6ff;
            border-radius: 8px;
            background: rgba(77, 166, 255, 0.1);
            color: #4da6ff;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            box-sizing: border-box;
            transition: background 0.2s;
        }
        .users-view-all:hover {
            background: rgba(77, 166, 255, 0.2);
        }

        ul.post-list { list-style: none; padding-left: 0; }
        ul.post-list li { position: relative; padding-left: 1.4rem; }
        ul.post-list li::before {
            content: '↳'; position: absolute; left: 0; top: 0;
            color: #ffffff; pointer-events: none;
        }
        ul.post-list li.pinned-post::before {
            /* content: '📌'; */
            /* color: #d4a017; */
        }
        .delete-button {
            background: transparent; color: #d94a4a;
            border: none; cursor: pointer; text-decoration: underline;
        }
    </style>
</head>
<body>
    <?php require __DIR__ . '/navbar.php'; ?>

    <div class="page-layout">
        <main class="main-content">
            <?php if (empty($posts)): ?>
                <p>niciun sclav gasit.</p>
            <?php else: ?>
                <ul class="post-list">
                    <?php foreach ($posts as $post): ?>
                        <?php
                        $isPinned = isset($post['pinned']) && $post['pinned'] == 1;
                        $postClass = $isPinned ? 'pinned-post' : 'regular-post';
                        ?>
                        <li class="<?php echo $postClass; ?>">
                            <?php if ($isPinned): ?>
                                <span class="pin-icon">📌</span>
                            <?php endif; ?>
                            <a href="post/<?php echo htmlspecialchars($post['id']); ?>"><?php echo htmlspecialchars($post['title']); ?></a>
                            <span class="post-date">(<?php echo date('d M Y, H:i', strtotime($post['updated_at'])); ?>)</span>
                            <?php if ($isAdmin): ?>
                                <a class="edit-link" href="edit/<?php echo htmlspecialchars($post['id']); ?>" style="margin-left:12px;">edit</a>
                                <form method="post" action="delete" onsubmit="return confirm('Stergi acest post?');" style="display:inline; margin-left:8px;">
                                    <input type="hidden" name="id" value="<?php echo htmlspecialchars($post['id']); ?>">
                                    <button type="submit" class="delete-button">sterge</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </main>

        <aside class="news-sidebar">
            <!-- NEWS CARD -->
            <div class="news-card">
                <h2>News</h2>
                <ul class="news-list" id="news-list">
                    <?php if (empty($newsItems)): ?>
                        <li><span class="news-empty">Nicio stire adaugata.</span></li>
                    <?php else: ?>
                        <?php foreach ($newsItems as $item): ?>
                        <li data-id="<?php echo (int)$item['id']; ?>">
                            <div class="news-item-header" onclick="toggleItem(this)">
                                <span class="news-chevron">&#9658;</span>
                                <span class="news-title-wrap">
                                    <?php if (!empty($item['url'])): ?>
                                        <a class="news-title-link"
                                           href="<?php echo htmlspecialchars($item['url']); ?>"
                                           target="_blank" rel="noopener"
                                           onclick="event.stopPropagation()"><?php echo htmlspecialchars($item['title']); ?></a>
                                    <?php else: ?>
                                        <span class="news-title-text"><?php echo htmlspecialchars($item['title']); ?></span>
                                    <?php endif; ?>
                                </span>
                                <?php if ($isAdmin): ?>
                                <span class="news-admin-actions" onclick="event.stopPropagation()">
                                    <button class="news-btn news-btn-edit" onclick="editNews(<?php echo (int)$item['id']; ?>, <?php echo htmlspecialchars(json_encode($item['title'])); ?>, <?php echo htmlspecialchars(json_encode($item['url'] ?? '')); ?>, <?php echo htmlspecialchars(json_encode($item['description'] ?? '')); ?>)" style="margin-right: 8px;">edit</button>
                                    <button class="news-btn news-btn-del" onclick="deleteNews(<?php echo (int)$item['id']; ?>, this)">sterge</button>
                                </span>
                                <?php endif; ?>
                            </div>
                            <div class="news-item-body">
                                <?php if (!empty($item['description'])): ?>
                                    <p class="news-item-desc"><?php echo htmlspecialchars($item['description']); ?></p>
                                <?php endif; ?>
                                <?php if (!empty($item['url'])): ?>
                                <?php endif; ?>
                                <?php if (empty($item['description']) && empty($item['url'])): ?>
                                    <p class="news-item-desc" style="color:#444;">Nicio descriere.</p>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>

                <?php if ($isAdmin): ?>
                <button class="news-toggle-add" id="news-toggle-btn" onclick="toggleAddForm()">+ adauga stire</button>
                <div class="news-add-form" id="news-add-form">
                    <input type="text" id="news-input-title" placeholder="Titlu stire..." maxlength="255">
                    <input type="text" id="news-input-url"   placeholder="Link (optional)..." maxlength="500">
                    <textarea id="news-input-desc" placeholder="Descriere (optional)..." maxlength="2000"></textarea>
                    <div class="news-form-actions">
                        <button class="news-save-btn" id="news-save-btn" onclick="saveNews()">Salveaza</button>
                        <button class="news-cancel-btn" onclick="cancelNewsForm()">Anuleaza</button>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- LAST USERS CARD (Admin Only) -->
            <?php if ($isAdmin): ?>
            <div class="news-card users-card">
                <h2>Last Users</h2>
                <ul class="users-list">
                    <?php if (empty($lastUsers)): ?>
                        <li><span class="users-empty">Niciun utilizator inregistrat.</span></li>
                    <?php else: ?>
                        <?php foreach ($lastUsers as $user): ?>
                        <li>
                            <div class="user-info">
                                <span class="user-name"><?php echo htmlspecialchars($user['username']); ?></span>
                                <span class="user-date"><?php echo date('d M Y, H:i', strtotime($user['created_at'])); ?></span>
                            </div>
                            <span class="user-role <?php echo $user['role']; ?>"><?php echo htmlspecialchars($user['role']); ?></span>
                        </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
                <a href="users" class="users-view-all">VEZI TOTI UTILIZATORII</a>
            </div>
            <?php endif; ?>
        </aside>
    </div>

    <script>
    function toggleItem(header) {
        var li = header.parentElement;
        li.classList.toggle('open');
    }

    <?php if ($isAdmin): ?>
    var editingId = null;

    function toggleAddForm() {
        var form = document.getElementById('news-add-form');
        var btn  = document.getElementById('news-toggle-btn');
        editingId = null;
        document.getElementById('news-input-title').value = '';
        document.getElementById('news-input-url').value   = '';
        document.getElementById('news-input-desc').value  = '';
        document.getElementById('news-save-btn').textContent = 'Salveaza';
        form.classList.toggle('open');
        if (form.classList.contains('open')) {
            btn.style.display = 'none';
            document.getElementById('news-input-title').focus();
        }
    }

    function cancelNewsForm() {
        document.getElementById('news-add-form').classList.remove('open');
        document.getElementById('news-toggle-btn').style.display = 'block';
        editingId = null;
    }

    function editNews(id, title, url, desc) {
        editingId = id;
        document.getElementById('news-input-title').value = title;
        document.getElementById('news-input-url').value   = url  || '';
        document.getElementById('news-input-desc').value  = desc || '';
        document.getElementById('news-save-btn').textContent = 'Actualizeaza';
        document.getElementById('news-add-form').classList.add('open');
        document.getElementById('news-toggle-btn').style.display = 'none';
        document.getElementById('news-input-title').focus();
    }

    function saveNews() {
        var title = document.getElementById('news-input-title').value.trim();
        var url   = document.getElementById('news-input-url').value.trim();
        var desc  = document.getElementById('news-input-desc').value.trim();
        if (!title) { document.getElementById('news-input-title').focus(); return; }

        var btn = document.getElementById('news-save-btn');
        btn.disabled = true;

        var params = 'action=' + (editingId ? 'edit' : 'add')
            + '&title=' + encodeURIComponent(title)
            + '&url='   + encodeURIComponent(url)
            + '&description=' + encodeURIComponent(desc)
            + (editingId ? '&id=' + editingId : '');

        fetch('news_ajax', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: params
        })
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (data.ok) {
                if (editingId) {
                    updateNewsItem(editingId, title, url, desc);
                } else {
                    appendNewsItem(data.id, title, url, desc);
                }
                cancelNewsForm();
            }
        })
        .finally(function(){ btn.disabled = false; });
    }

    function deleteNews(id, btn) {
        if (!confirm('Stergi aceasta stire?')) return;
        btn.disabled = true;
        fetch('news_ajax', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=delete&id=' + id
        })
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (data.ok) {
                var li = document.querySelector('#news-list li[data-id="' + id + '"]');
                if (li) li.remove();
                if (!document.querySelector('#news-list li[data-id]')) {
                    var empty = document.createElement('li');
                    empty.innerHTML = '<span class="news-empty">Nicio stire adaugata.</span>';
                    document.getElementById('news-list').appendChild(empty);
                }
            }
        });
    }

    function appendNewsItem(id, title, url, desc) {
        var list = document.getElementById('news-list');
        var empty = list.querySelector('li:not([data-id])');
        if (empty) empty.remove();
        var li = document.createElement('li');
        li.dataset.id = id;
        li.innerHTML = buildNewsItemHTML(id, title, url, desc);
        list.appendChild(li);
    }

    function updateNewsItem(id, title, url, desc) {
        var li = document.querySelector('#news-list li[data-id="' + id + '"]');
        if (li) {
            var wasOpen = li.classList.contains('open');
            li.innerHTML = buildNewsItemHTML(id, title, url, desc);
            if (wasOpen) li.classList.add('open');
        }
    }

    function buildNewsItemHTML(id, title, url, desc) {
        var titleInner = url
            ? '<a class="news-title-link" href="' + escHtml(url) + '" target="_blank" rel="noopener" onclick="event.stopPropagation()">' + escHtml(title) + '</a>'
            : '<span class="news-title-text">' + escHtml(title) + '</span>';

        var bodyContent = '';
        if (desc) bodyContent += '<p class="news-item-desc">' + escHtml(desc) + '</p>';
        if (!desc && !url) bodyContent += '<p class="news-item-desc" style="color:#444;">Nicio descriere.</p>';

        return '<div class="news-item-header" onclick="toggleItem(this)">'
            + '<span class="news-chevron">&#9658;</span>'
            + '<span class="news-title-wrap">' + titleInner + '</span>'
            + '<span class="news-admin-actions" onclick="event.stopPropagation()">'
            + '<button class="news-btn news-btn-edit" onclick="editNews(' + id + ',' + JSON.stringify(title) + ',' + JSON.stringify(url) + ',' + JSON.stringify(desc) + ')">edit</button>'
            + '<button class="news-btn news-btn-del" onclick="deleteNews(' + id + ', this)">sterge</button>'
            + '</span>'
            + '</div>'
            + '<div class="news-item-body">' + bodyContent + '</div>';
    }

    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
    <?php endif; ?>
    </script>
</body>
</html>
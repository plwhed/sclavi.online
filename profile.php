<?php
require_once __DIR__ . '/config.php';
checkLogin();
ensureUsersProfileColumns($pdo);

$userId = $_SESSION['user']['id'];
$currentUsername = $_SESSION['user']['username'];

$stmt = $pdo->prepare('SELECT id, username, display_name, avatar, banner, role, created_at, last_login FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$currentUser) {
    header('Location: ./');
    exit;
}

$displayName = $currentUser['display_name'] ?: $currentUser['username'];
$avatarUrl = $currentUser['avatar'] ? '../uploads/' . $currentUser['avatar'] : null;
$bannerUrl = $currentUser['banner'] ? '../uploads/' . $currentUser['banner'] : null;

$error = '';
$success = '';

function safeUploadFile(array $file, string $prefix) {
    if ($file['error'] !== UPLOAD_ERR_OK) return null;
    $name = basename($file['name']);
    $ext = pathinfo($name, PATHINFO_EXTENSION);
    $ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
    $baseName = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($name, PATHINFO_FILENAME));
    $safeBase = $baseName ?: 'upload';
    $filename = $prefix . '-' . $safeBase . ($ext ? '.' . $ext : '');
    $target = __DIR__ . '/../uploads/' . $filename;
    $suffix = 1;
    while (file_exists($target)) {
        $filename = $prefix . '-' . $safeBase . '-' . $suffix . ($ext ? '.' . $ext : '');
        $target = __DIR__ . '/../uploads/' . $filename;
        $suffix++;
    }
    return move_uploaded_file($file['tmp_name'], $target) ? $filename : false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newDisplayName = trim($_POST['display_name'] ?? '');
    $newDisplayName = $newDisplayName !== '' ? $newDisplayName : null;

    $avatarPath = $currentUser['avatar'];
    $bannerPath = $currentUser['banner'];

    $avatarUpload = $_FILES['avatar'] ?? null;
    $bannerUpload = $_FILES['banner'] ?? null;

    if ($avatarUpload && $avatarUpload['error'] !== UPLOAD_ERR_NO_FILE) {
        $saved = safeUploadFile($avatarUpload, 'avatar-' . $userId);
        if ($saved === false) $error = 'Unable to upload avatar file.';
        elseif ($saved !== null) $avatarPath = $saved;
    }

    if ($bannerUpload && $bannerUpload['error'] !== UPLOAD_ERR_NO_FILE) {
        $saved = safeUploadFile($bannerUpload, 'banner-' . $userId);
        if ($saved === false) $error = 'Unable to upload banner file.';
        elseif ($saved !== null) $bannerPath = $saved;
    }

    if ($error === '') {
        $updateStmt = $pdo->prepare('UPDATE users SET display_name = ?, avatar = ?, banner = ? WHERE id = ?');
        $updateStmt->execute([$newDisplayName, $avatarPath, $bannerPath, $userId]);
        $success = 'Profil actualizat cu succes.';
        $displayName = $newDisplayName ?: $currentUser['username'];
        $avatarUrl = $avatarPath ? '../uploads/' . $avatarPath : null;
        $bannerUrl = $bannerPath ? '../uploads/' . $bannerPath : null;
        $_SESSION['user']['display_name'] = $displayName;
        $_SESSION['user']['avatar'] = $avatarPath;
        $_SESSION['user']['banner'] = $bannerPath;
        $currentUser['display_name'] = $newDisplayName;
        $currentUser['avatar'] = $avatarPath;
        $currentUser['banner'] = $bannerPath;
    }
}

$postsStmt = $pdo->prepare('SELECT id, title, created_at, updated_at, views FROM posts WHERE created_by = ? ORDER BY updated_at DESC');
$postsStmt->execute([$currentUsername]);
$userPosts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);

$totalViews = array_sum(array_column($userPosts, 'views'));
$totalPosts = count($userPosts);

$roleColors = [
    'admin'     => '#ff4444',
    'developer' => '#ff4444',
    'manager'   => '#bb71e4',
    'owner'     => '#7171e4',
    'mod'       => '#39ff14',
    'rich'      => '#FFD700',
    'user'      => '#aaaaaa',
];
$userRole = $currentUser['role'] ?? 'user';
$roleColor = $roleColors[strtolower($userRole)] ?? '#aaaaaa';

$roleIcons = [
    'admin'     => '🛡️',
    'developer' => '⚙️',
    'manager'   => '👑',
    'owner'     => '💎',
    'mod'       => '🔨',
    'rich'      => '💰',
    'user'      => '👤',
];
$roleIcon = $roleIcons[strtolower($userRole)] ?? '👤';

$initials = strtoupper(substr($displayName, 0, 1));
if (str_contains($displayName, ' ')) {
    $parts = explode(' ', $displayName);
    $initials = strtoupper(substr($parts[0], 0, 1) . substr(end($parts), 0, 1));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profilul meu — <?php echo htmlspecialchars($displayName); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            min-height: 100vh;
            background: #111111;
            color: #ffffff;
            font-family: Arial, sans-serif;
        }

        a { color: #4da6ff; text-decoration: none; }
        a:hover { text-decoration: underline; }

        /* ── Banner ── */
        .banner-section {
            position: relative;
            width: 100%;
        }
        .banner-wrap {
            position: relative;
            height: 220px;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            overflow: hidden;
            cursor: pointer;
        }
        .banner-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .banner-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        .banner-wrap:hover .banner-overlay {
            background: rgba(0,0,0,0.5);
        }
        .banner-overlay span {
            color: #fff;
            font-size: 0.9rem;
            font-weight: 600;
            opacity: 0;
            transition: opacity 0.2s;
            background: rgba(0,0,0,0.55);
            padding: 8px 20px;
            border-radius: 20px;
            pointer-events: none;
        }
        .banner-wrap:hover .banner-overlay span { opacity: 1; }

        /* ── Avatar ── */
        .avatar-wrap {
            position: absolute;
            bottom: -54px;
            left: 40px;
            width: 108px;
            height: 108px;
            border-radius: 50%;
            border: 4px solid #111111;
            background: #1f1f1f;
            overflow: hidden;
            cursor: pointer;
            z-index: 10;
            transition: filter 0.2s;
        }
        .avatar-wrap:hover { filter: brightness(0.65); }
        .avatar-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .avatar-initials {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            font-weight: 700;
            background: #1e1e3a;
            color: #8888ff;
        }

        /* ── Page wrap ── */
        .page-wrap {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 24px 48px;
        }

        /* ── Profile header card ── */
        .profile-header-card {
            background: #161616;
            border: 1px solid #222;
            border-top: none;
            border-radius: 0 0 18px 18px;
            padding: 76px 40px 28px;
            margin-bottom: 24px;
        }
        .profile-name-row {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 6px;
        }
        .profile-name {
            font-size: 1.9rem;
            font-weight: 700;
            line-height: 1.1;
        }
        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 700;
            border: 1px solid;
        }
        .profile-username {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 18px;
        }
        .profile-stats-row {
            display: flex;
            gap: 32px;
            flex-wrap: wrap;
            margin-bottom: 14px;
        }
        .profile-stat .val {
            font-size: 1.35rem;
            font-weight: 700;
            color: #fff;
            line-height: 1.1;
        }
        .profile-stat .lbl {
            font-size: 0.68rem;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-top: 2px;
        }
        .profile-dates {
            color: #555;
            font-size: 0.8rem;
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        /* ── Main grid ── */
        .main-grid {
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 24px;
            align-items: flex-start;
        }
        @media (max-width: 820px) {
            .main-grid { grid-template-columns: 1fr; }
            .avatar-wrap { left: 20px; }
            .profile-header-card { padding-left: 24px; padding-right: 24px; }
        }

        /* ── Cards ── */
        .card {
            background: #161616;
            border: 1px solid #222;
            border-radius: 16px;
            padding: 22px;
            margin-bottom: 20px;
        }
        .card:last-child { margin-bottom: 0; }
        .card-title {
            font-size: 0.68rem;
            font-weight: 700;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-bottom: 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* ── Form ── */
        .form-group { margin-bottom: 16px; }
        .form-group:last-child { margin-bottom: 0; }
        .form-group label {
            display: block;
            font-size: 0.72rem;
            font-weight: 600;
            color: #777;
            margin-bottom: 7px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .form-group input[type="text"] {
            width: 100%;
            padding: 11px 14px;
            border-radius: 10px;
            border: 1px solid #2a2a2a;
            background: #1a1a1a;
            color: #fff;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.15s;
        }
        .form-group input[type="text"]:focus { border-color: #4da6ff; }

        .upload-zone {
            border: 1.5px dashed #2a2a2a;
            border-radius: 10px;
            padding: 14px;
            text-align: center;
            cursor: pointer;
            transition: all 0.15s;
            color: #555;
            font-size: 0.82rem;
            position: relative;
        }
        .upload-zone:hover {
            border-color: #4da6ff;
            background: #111a28;
            color: #9bbcff;
        }
        .upload-zone input[type="file"] {
            position: absolute;
            inset: 0;
            opacity: 0;
            cursor: pointer;
            width: 100%;
            height: 100%;
        }
        .upload-zone .preview-thumb {
            width: 100%;
            height: 70px;
            object-fit: cover;
            border-radius: 7px;
            margin-bottom: 6px;
            display: block;
        }
        .upload-zone .avatar-preview-thumb {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 6px;
            display: block;
        }

        .btn-submit {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 10px;
            background: #4da6ff;
            color: #0a1520;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.15s, transform 0.1s;
        }
        .btn-submit:hover { background: #3b93e0; }
        .btn-submit:active { transform: scale(0.98); }

        .msg {
            padding: 11px 14px;
            border-radius: 10px;
            font-size: 0.88rem;
            margin-bottom: 16px;
        }
        .msg-success { background: #0f2d0f; color: #7ddb7d; border: 1px solid #1a4a1a; }
        .msg-error   { background: #2d0f0f; color: #db7d7d; border: 1px solid #4a1a1a; }

        /* ── Posts ── */
        .posts-list { list-style: none; }
        .post-item {
            padding: 14px 0;
            border-bottom: 1px solid #1c1c1c;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .post-item:last-child { border-bottom: none; padding-bottom: 0; }
        .post-item:first-child { padding-top: 0; }
        .post-num {
            min-width: 26px;
            height: 26px;
            border-radius: 6px;
            background: #1e1e1e;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.68rem;
            color: #444;
            font-weight: 700;
            margin-top: 2px;
            flex-shrink: 0;
        }
        .post-info { flex: 1; min-width: 0; }
        .post-info a {
            color: #e8e8e8;
            font-weight: 600;
            font-size: 0.92rem;
            display: block;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 5px;
            text-decoration: none;
        }
        .post-info a:hover { color: #4da6ff; }
        .post-meta-row {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .post-meta-badge {
            font-size: 0.7rem;
            color: #555;
        }
        .views-badge {
            font-size: 0.7rem;
            color: #4da6ff;
            font-weight: 600;
        }

        .empty-state {
            text-align: center;
            padding: 36px 0;
            color: #444;
        }
        .empty-state .icon { font-size: 2.5rem; margin-bottom: 10px; }
        .empty-state p { font-size: 0.88rem; }

        .info-row {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }
        .info-field-label {
            font-size: 0.68rem;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 3px;
        }
        .info-field-value {
            font-size: 0.92rem;
            color: #ccc;
        }

        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: #111; }
        ::-webkit-scrollbar-thumb { background: #252525; border-radius: 3px; }
    </style>
</head>
<body>
    <?php require __DIR__ . '/navbar.php'; ?>

    <!-- Banner — full bleed, outside page-wrap -->
    <div class="banner-section">
        <div class="banner-wrap" onclick="document.getElementById('banner').click()" title="Schimba banner">
            <?php if ($bannerUrl): ?>
                <img src="<?php echo htmlspecialchars($bannerUrl); ?>" alt="Banner" id="banner-top-img">
            <?php else: ?>
                <div id="banner-top-img" style="display:none;"></div>
            <?php endif; ?>
            <div class="banner-overlay"><span>✎ Schimba banner</span></div>
        </div>

        <div class="avatar-wrap" onclick="document.getElementById('avatar').click()" title="Schimba avatar">
            <?php if ($avatarUrl): ?>
                <img src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Avatar" id="avatar-top-img">
            <?php else: ?>
                <div class="avatar-initials" id="avatar-top-initials"><?php echo htmlspecialchars($initials); ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="page-wrap">

        <!-- Profile header card -->
        <div class="profile-header-card">
            <div class="profile-name-row">
                <div class="profile-name" id="header-display-name"><?php echo htmlspecialchars($displayName); ?></div>
                <div class="role-badge" style="color:<?php echo $roleColor; ?>;border-color:<?php echo $roleColor; ?>44;background:<?php echo $roleColor; ?>16;">
                    <?php echo $roleIcon; ?> <?php echo htmlspecialchars($userRole); ?>
                </div>
            </div>
            <div class="profile-username">@<?php echo htmlspecialchars($currentUser['username']); ?></div>
            <div class="profile-stats-row">
                <div class="profile-stat">
                    <div class="val"><?php echo $totalPosts; ?></div>
                    <div class="lbl">Posturi</div>
                </div>
                <div class="profile-stat">
                    <div class="val"><?php echo number_format($totalViews); ?></div>
                    <div class="lbl">Views total</div>
                </div>
                <div class="profile-stat">
                    <div class="val"><?php echo date('Y', strtotime($currentUser['created_at'])); ?></div>
                    <div class="lbl">Membru din</div>
                </div>
            </div>
            <div class="profile-dates">
                <span>📅 Înregistrat: <?php echo date('d M Y', strtotime($currentUser['created_at'])); ?></span>
                <?php if ($currentUser['last_login']): ?>
                <span>🟢 Ultima conectare: <?php echo date('d M Y, H:i', strtotime($currentUser['last_login'])); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div class="main-grid">

            <!-- Left: edit form + account info -->
            <div>
                <div class="card">
                    <div class="card-title">✎ Editeaza profil</div>

                    <?php if ($success): ?>
                        <div class="msg msg-success">✓ <?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <div class="msg msg-error">✗ <?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>

                    <form method="post" action="profile" enctype="multipart/form-data">
                        <div class="form-group">
                            <label>Nume afisat</label>
                            <input type="text" name="display_name" id="display_name"
                                   value="<?php echo htmlspecialchars($currentUser['display_name'] ?: ''); ?>"
                                   placeholder="Ex: Alex Popescu">
                        </div>

                        <div class="form-group">
                            <label>Avatar</label>
                            <div class="upload-zone">
                                <?php if ($avatarUrl): ?>
                                    <img src="<?php echo htmlspecialchars($avatarUrl); ?>" class="avatar-preview-thumb" id="avatar-form-preview" alt="">
                                <?php else: ?>
                                    <img class="avatar-preview-thumb" id="avatar-form-preview" style="display:none;" alt="">
                                <?php endif; ?>
                                <input type="file" name="avatar" id="avatar" accept="image/*">
                                <div id="avatar-zone-label">📷 Click pentru avatar</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Banner</label>
                            <div class="upload-zone">
                                <?php if ($bannerUrl): ?>
                                    <img src="<?php echo htmlspecialchars($bannerUrl); ?>" class="preview-thumb" id="banner-form-preview" alt="">
                                <?php else: ?>
                                    <img class="preview-thumb" id="banner-form-preview" style="display:none;" alt="">
                                <?php endif; ?>
                                <input type="file" name="banner" id="banner" accept="image/*">
                                <div id="banner-zone-label">🖼️ Click pentru banner</div>
                            </div>
                        </div>

                        <button type="submit" class="btn-submit">💾 Salveaza schimbarile</button>
                    </form>
                </div>

                <div class="card">
                    <div class="card-title">ℹ️ Informatii cont</div>
                    <div class="info-row">
                        <div>
                            <div class="info-field-label">Username</div>
                            <div class="info-field-value">@<?php echo htmlspecialchars($currentUser['username']); ?></div>
                        </div>
                        <div>
                            <div class="info-field-label">Rol</div>
                            <div class="info-field-value" style="color:<?php echo $roleColor; ?>;font-weight:600;"><?php echo $roleIcon; ?> <?php echo htmlspecialchars($userRole); ?></div>
                        </div>
                        <div>
                            <div class="info-field-label">ID cont</div>
                            <div class="info-field-value" style="color:#444;font-family:monospace;">#<?php echo (int)$currentUser['id']; ?></div>
                        </div>
                        <div>
                            <div class="info-field-label">Inregistrat</div>
                            <div class="info-field-value"><?php echo date('d M Y, H:i', strtotime($currentUser['created_at'])); ?></div>
                        </div>
                        <?php if ($currentUser['last_login']): ?>
                        <div>
                            <div class="info-field-label">Ultima conectare</div>
                            <div class="info-field-value"><?php echo date('d M Y, H:i', strtotime($currentUser['last_login'])); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right: posts -->
            <div class="card" style="margin-bottom:0;">
                <div class="card-title">
                    <span>📝 Posturile tale</span>
                    <span style="color:#333;"><?php echo $totalPosts; ?> posturi • <?php echo number_format($totalViews); ?> views</span>
                </div>

                <?php if (empty($userPosts)): ?>
                    <div class="empty-state">
                        <div class="icon">📭</div>
                        <p>Nu ai adaugat niciun post.</p>
                    </div>
                <?php else: ?>
                    <ul class="posts-list">
                        <?php foreach ($userPosts as $i => $post): ?>
                        <li class="post-item">
                            <div class="post-num"><?php echo $i + 1; ?></div>
                            <div class="post-info">
                                <a href="../post/<?php echo (int)$post['id']; ?>"><?php echo htmlspecialchars($post['title']); ?></a>
                                <div class="post-meta-row">
                                    <span class="post-meta-badge">📅 <?php echo date('d M Y', strtotime($post['created_at'])); ?></span>
                                    <span class="post-meta-badge">✏️ <?php echo date('d M Y', strtotime($post['updated_at'])); ?></span>
                                    <?php if (isset($post['views'])): ?>
                                    <span class="views-badge">👁 <?php echo number_format((int)$post['views']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>

        </div>
    </div>

    <script>
        document.getElementById('avatar').addEventListener('change', function () {
            if (!this.files || !this.files[0]) return;
            var reader = new FileReader();
            reader.onload = function (e) {
                var src = e.target.result;

                // form preview
                var fp = document.getElementById('avatar-form-preview');
                fp.src = src;
                fp.style.display = 'block';
                document.getElementById('avatar-zone-label').textContent = '✓ Imaginea selectata';

                // top avatar
                var topImg = document.getElementById('avatar-top-img');
                if (topImg && topImg.tagName === 'IMG') {
                    topImg.src = src;
                } else {
                    var wrap = document.querySelector('.avatar-wrap');
                    var initDiv = document.getElementById('avatar-top-initials');
                    if (initDiv) initDiv.style.display = 'none';
                    var newImg = document.createElement('img');
                    newImg.src = src;
                    newImg.id = 'avatar-top-img';
                    newImg.style.cssText = 'width:100%;height:100%;object-fit:cover;display:block;';
                    wrap.appendChild(newImg);
                }
            };
            reader.readAsDataURL(this.files[0]);
        });

        document.getElementById('banner').addEventListener('change', function () {
            if (!this.files || !this.files[0]) return;
            var reader = new FileReader();
            reader.onload = function (e) {
                var src = e.target.result;

                // form preview
                var fp = document.getElementById('banner-form-preview');
                fp.src = src;
                fp.style.display = 'block';
                document.getElementById('banner-zone-label').textContent = '✓ Imaginea selectata';

                // top banner
                var topImg = document.getElementById('banner-top-img');
                if (topImg && topImg.tagName === 'IMG') {
                    topImg.src = src;
                } else {
                    var bw = document.querySelector('.banner-wrap');
                    var newImg = document.createElement('img');
                    newImg.src = src;
                    newImg.id = 'banner-top-img';
                    newImg.style.cssText = 'width:100%;height:100%;object-fit:cover;display:block;';
                    bw.insertBefore(newImg, bw.firstChild);
                }
            };
            reader.readAsDataURL(this.files[0]);
        });

        document.getElementById('display_name').addEventListener('input', function () {
            var name = this.value.trim() || '<?php echo addslashes(htmlspecialchars($currentUser['username'])); ?>';
            document.getElementById('header-display-name').textContent = name;
        });
    </script>
</body>
</html>
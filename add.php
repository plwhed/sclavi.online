<?php
require_once __DIR__ . '/config.php';
checkLogin();
ensurePostsUpdatedAt($pdo);

if (!isAdmin()) {
    echo 'Access denied. Only admin can add posts.';
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $links = trim($_POST['links'] ?? '');

    if ($title === '') {
        $error = 'Title is required.';
    } else {
        $uploadedNames = [];
        if (!empty($_FILES['images']) && is_array($_FILES['images']['name'])) {
            for ($i = 0; $i < count($_FILES['images']['name']); $i++) {
                $name = $_FILES['images']['name'][$i];
                $tmpName = $_FILES['images']['tmp_name'][$i];
                $errorCode = $_FILES['images']['error'][$i];

                if ($errorCode === UPLOAD_ERR_NO_FILE) continue;
                if ($errorCode !== UPLOAD_ERR_OK) {
                    $error = 'Error uploading image: ' . htmlspecialchars($name);
                    break;
                }

                $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($name));
                $target = __DIR__ . '/../uploads/' . $safeName;
                $iSuffix = 1;
                while (file_exists($target)) {
                    $safeName = pathinfo($safeName, PATHINFO_FILENAME) . '_' . $iSuffix . '.' . pathinfo($safeName, PATHINFO_EXTENSION);
                    $target = __DIR__ . '/../uploads/' . $safeName;
                    $iSuffix++;
                }

                if (move_uploaded_file($tmpName, $target)) {
                    $uploadedNames[] = $safeName;
                } else {
                    $error = 'Unable to save image: ' . htmlspecialchars($name);
                    break;
                }
            }
        }

        if ($error === '') {
            $storedImages = implode(',', $uploadedNames);

            if (tableHasColumn($pdo, 'posts', 'updated_at')) {
                $stmt = $pdo->prepare('INSERT INTO posts (title, description, images, links, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
                $stmt->execute([$title, $description, $storedImages, $links, $_SESSION['user']['username']]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO posts (title, description, images, links, created_by, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
                $stmt->execute([$title, $description, $storedImages, $links, $_SESSION['user']['username']]);
            }

            $success = 'Post added successfully.';
            $title = '';
            $description = '';
            $links = '';
        }
    }
}

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Post</title>
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

        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            color: #8f9bb3;
            font-size: 0.75rem;
            font-weight: 600;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .form-group input[type="text"],
        .form-group textarea {
            width: 100%;
            padding: 12px 14px;
            border-radius: 10px;
            border: 1px solid #2a2a2a;
            background: #1a1a1a;
            color: #fff;
            font-size: 0.95rem;
            outline: none;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
        .form-group input[type="text"]:focus,
        .form-group textarea:focus {
            border-color: #4da6ff;
        }
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
            line-height: 1.6;
        }

        .editor-area {
            min-height: 400px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            line-height: 1.6;
        }

        .message {
            padding: 12px 14px;
            border-radius: 10px;
            margin-bottom: 16px;
            font-size: 0.9rem;
        }
        .message.error {
            background: #331717;
            color: #ffb3b3;
            border: 1px solid #5d1c1c;
        }
        .message.success {
            background: #143214;
            color: #b3ffb3;
            border: 1px solid #2d6f2d;
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

        .button-row {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        /* Image upload — same style as edit.php */
        .drop-zone-custom {
            border: 1.5px dashed #444;
            border-radius: 12px;
            padding: 18px;
            text-align: center;
            cursor: pointer;
            transition: background .15s, border-color .15s;
            color: #888;
            font-size: 13px;
        }
        .drop-zone-custom:hover,
        .drop-zone-custom.drag-over {
            background: #1b1b1b;
            border-color: #666;
            color: #aaa;
        }

        .new-img-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }
        .new-thumb-wrap {
            position: relative;
            border-radius: 12px;
            border: 1px solid #2a2a2a;
            aspect-ratio: 1;
            overflow: hidden;
        }
        .new-thumb-wrap img {
            width: 100%; height: 100%;
            object-fit: cover;
            display: block;
        }
        .new-thumb-wrap .tname {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            background: rgba(0,0,0,.6);
            color: #fff;
            font-size: 10px;
            padding: 3px 6px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .new-thumb-wrap .tbtn {
            position: absolute;
            top: 5px; right: 5px;
            width: 24px; height: 24px;
            border-radius: 50%;
            background: #d94a4a;
            color: #fff;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            opacity: 0;
            transition: opacity .15s;
            line-height: 1;
        }
        .new-thumb-wrap:hover .tbtn { opacity: 1; }

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

        .sidebar-logo {
            text-align: center;
            margin-bottom: 24px;
        }
        .sidebar-logo img {
            max-width: 160px;
            height: auto;
        }

        .sidebar-footer {
            color: #666;
            font-size: 0.8rem;
            text-align: center;
            margin-top: 16px;
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
                <h2>✏️ Editor</h2>

                <?php if ($error): ?>
                    <div class="message error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="message success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>

                <form method="post" action="add" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="title">Titlu</label>
                        <input id="title" type="text" name="title" value="<?php echo htmlspecialchars($title ?? ''); ?>" placeholder="Titlul expose-ului" required>
                    </div>

                    <div class="form-group">
                        <label for="description">Conținut</label>
                        <textarea id="description" name="description" class="editor-area" placeholder="Scrie expose-ul aici..." autofocus><?php echo htmlspecialchars($description ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Imagini</label>
                        <div class="drop-zone-custom" id="drop-zone">
                            &#9729; Trage imaginile aici sau <span style="color:#9bbcff;">click pentru upload</span>
                        </div>
                        <div id="hidden-file-inputs" style="display:none;"></div>
                        <div class="new-img-grid" id="new-img-grid"></div>
                    </div>

                    <div class="form-group">
                        <label for="links">Linkuri (unul per linie)</label>
                        <textarea id="links" name="links" rows="4" placeholder="https://..."><?php echo htmlspecialchars($links ?? ''); ?></textarea>
                    </div>

                    <div class="button-row">
                        <button type="submit" class="btn btn-primary">🚀 Creează</button>
                        <a href="./" class="btn btn-secondary" style="text-decoration:none;">Anulează</a>
                    </div>
                </form>
            </div>
        </main>

        <aside class="news-sidebar">
            <div class="news-card">
                <div class="sidebar-logo">
                    <a href="./">
                        <img src="https://files.catbox.moe/hms5ef.png" alt="Logo">
                    </a>
                </div>

                <div class="info-box">
                    <span class="info-label">Autor</span>
                    <div class="info-value" style="color: <?php echo $roleColor; ?>">@<?php echo htmlspecialchars($_SESSION['user']['username'] ?? 'unknown'); ?></div>
                </div>

                <div class="info-box">
                    <span class="info-label">Rol</span>
                    <div class="info-value"><?php echo htmlspecialchars($userRole); ?></div>
                </div>

                <p class="sidebar-footer">Pentru delete, da-ti mesaj pe grupul de Telegram</p>
            </div>
        </aside>
    </div>

    <script>
    (function () {
        var dropZone = document.getElementById('drop-zone');
        var hiddenInputs = document.getElementById('hidden-file-inputs');
        var newGrid = document.getElementById('new-img-grid');
        var fileInput = null;

        function createFileInput() {
            var input = document.createElement('input');
            input.type = 'file';
            input.name = 'images[]';
            input.multiple = true;
            input.accept = 'image/*';
            input.style.display = 'none';
            input.addEventListener('change', function () {
                if (!input.files || input.files.length === 0) return;
                addFiles(input.files);
                hiddenInputs.appendChild(input);
                fileInput = createFileInput();
                dropZone.appendChild(fileInput);
            });
            return input;
        }

        function initFileInput() {
            fileInput = createFileInput();
            dropZone.appendChild(fileInput);
        }

        initFileInput();

        dropZone.addEventListener('click', function () { fileInput.click(); });
        dropZone.addEventListener('dragover', function (e) {
            e.preventDefault();
            dropZone.classList.add('drag-over');
        });
        dropZone.addEventListener('dragleave', function () {
            dropZone.classList.remove('drag-over');
        });
        dropZone.addEventListener('drop', function (e) {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
            handleNewFiles(e.dataTransfer.files);
        });

        function handleNewFiles(files) {
            if (!files || files.length === 0) return;
            try {
                var dt = new DataTransfer();
                Array.from(files).forEach(function (file) { dt.items.add(file); });
                fileInput.files = dt.files;
                hiddenInputs.appendChild(fileInput);
                addFiles(fileInput.files);
                fileInput = createFileInput();
                dropZone.appendChild(fileInput);
            } catch (err) {
                Array.from(files).forEach(function (file) { addFiles([file]); });
            }
        }

        function addFiles(files) {
            Array.from(files).forEach(function (file) {
                var reader = new FileReader();
                reader.onload = function (e) { addNewThumb(file.name, e.target.result); };
                reader.readAsDataURL(file);
            });
        }

        function addNewThumb(name, src) {
            var div = document.createElement('div');
            div.className = 'new-thumb-wrap';
            var img = document.createElement('img');
            img.src = src;
            img.alt = name;
            var span = document.createElement('span');
            span.className = 'tname';
            span.textContent = name;
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'tbtn';
            btn.innerHTML = '&#10005;';
            btn.addEventListener('click', function () { div.remove(); });
            div.appendChild(img);
            div.appendChild(span);
            div.appendChild(btn);
            newGrid.appendChild(div);
        }
    })();
    </script>
</body>
</html>
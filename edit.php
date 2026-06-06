<?php
require_once __DIR__ . '/config.php';
checkLogin();
ensurePostsUpdatedAt($pdo);

if (!isAdmin()) {
    echo 'Access denied. Only admin can edit posts.';
    exit;
}

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    header('Location: ./');
    exit;
}

$stmt = $pdo->prepare('SELECT id, title, description, images, links, pinned FROM posts WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    echo 'Post not found.';
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $links = trim($_POST['links'] ?? '');
    $pinned = isset($_POST['pinned']) ? 1 : 0;

    if ($title === '') {
        $error = 'Title is required.';
    } else {
        $existingImages = array_filter(array_map('trim', explode(',', $post['images'])));

        $deleteImages = [];
        if (!empty($_POST['delete_images'])) {
            $deleteImages = array_map('trim', explode(',', $_POST['delete_images']));
        }

        $existingImages = array_filter($existingImages, function($img) use ($deleteImages) {
            return !in_array($img, $deleteImages);
        });

        foreach ($deleteImages as $delImg) {
            if ($delImg !== '') {
                $delPath = __DIR__ . '/../uploads/' . basename($delImg);
                if (file_exists($delPath)) {
                    unlink($delPath);
                }
            }
        }

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
            $allImages = $existingImages;
            if (!empty($uploadedNames)) {
                $allImages = array_merge($allImages, $uploadedNames);
            }

            // Check if pinned column exists
            $hasPinned = tableHasColumn($pdo, 'posts', 'pinned');

            if ($hasPinned) {
                if (tableHasColumn($pdo, 'posts', 'updated_at')) {
                    $stmt = $pdo->prepare('UPDATE posts SET title = ?, description = ?, images = ?, links = ?, pinned = ?, updated_at = NOW() WHERE id = ?');
                    $stmt->execute([$title, $description, implode(',', $allImages), $links, $pinned, $id]);
                } else {
                    $stmt = $pdo->prepare('UPDATE posts SET title = ?, description = ?, images = ?, links = ?, pinned = ? WHERE id = ?');
                    $stmt->execute([$title, $description, implode(',', $allImages), $links, $pinned, $id]);
                }
            } else {
                if (tableHasColumn($pdo, 'posts', 'updated_at')) {
                    $stmt = $pdo->prepare('UPDATE posts SET title = ?, description = ?, images = ?, links = ?, updated_at = NOW() WHERE id = ?');
                    $stmt->execute([$title, $description, implode(',', $allImages), $links, $id]);
                } else {
                    $stmt = $pdo->prepare('UPDATE posts SET title = ?, description = ?, images = ?, links = ? WHERE id = ?');
                    $stmt->execute([$title, $description, implode(',', $allImages), $links, $id]);
                }
            }

            $success = 'Post updated successfully.';
            $post['title'] = $title;
            $post['description'] = $description;
            $post['links'] = $links;
            $post['images'] = implode(',', $allImages);
            $post['pinned'] = $pinned;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Post</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #1b1b1b;
            color: #ffffff;
            font-family: Arial, sans-serif;
            padding: 24px;
        }

        .page-wrapper {
            width: 100%;
            max-width: 640px;
        }

        .card {
            background: #121212;
            border: 1px solid #2a2a2a;
            border-radius: 18px;
            padding: 28px;
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.4);
        }

        .card h1 {
            margin: 0 0 18px;
            font-size: 2rem;
        }

        .card a {
            color: #9bbcff;
            text-decoration: none;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #e5e5e5;
            font-weight: 600;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid #333;
            background: #1b1b1b;
            color: #ffffff;
            font-size: 1rem;
            box-sizing: border-box;
        }

        .form-group input[type="file"] {
            padding: 6px 14px;
        }

        .form-actions {
            margin-top: 10px;
        }

        button[type="submit"] {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 12px;
            background: #4da6ff;
            color: #101820;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
        }

        button[type="submit"]:hover {
            background: #3b93e0;
        }

        .message {
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 18px;
        }

        .message.error {
            background: #5d1c1c;
            color: #ffb3b3;
            border: 1px solid #7d1f1f;
        }

        .message.success {
            background: #1d3c1d;
            color: #b3ffb3;
            border: 1px solid #2d6f2d;
        }

        .img-manager-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
            gap: 10px;
            margin-bottom: 12px;
        }

        .img-thumb-wrap {
            position: relative;
            border-radius: 12px;
            border: 1px solid #2a2a2a;
            background: #141414;
            aspect-ratio: 1;
            overflow: hidden;
        }

        .img-thumb-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: opacity .2s;
        }

        .img-thumb-wrap.marked img { opacity: .2; }

        .img-thumb-wrap .tname {
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

        .img-thumb-wrap .tbtn {
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

        .img-thumb-wrap:hover .tbtn { opacity: 1; }

        .img-thumb-wrap.marked .tbtn {
            opacity: 1;
            background: #2d6f2d;
            font-size: 11px;
        }

        .img-thumb-wrap .restore-label {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            color: #b3ffb3;
            font-size: 11px;
            font-weight: 600;
            pointer-events: none;
            white-space: nowrap;
        }

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

        /* PINNED TOGGLE STYLES */
        .pin-toggle-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 12px;
            border: 1px solid #333;
            background: #1b1b1b;
            cursor: pointer;
            transition: border-color 0.2s, background 0.2s;
            user-select: none;
        }
        .pin-toggle-wrap:hover {
            border-color: #d4a017;
            background: #2a2200;
        }
        .pin-toggle-wrap.active {
            border-color: #d4a017;
            background: linear-gradient(135deg, #2a2200 0%, #1f1a00 100%);
        }
        .pin-toggle-wrap input[type="checkbox"] {
            display: none;
        }
        .pin-toggle-wrap .pin-icon-box {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            border: 2px solid #444;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            transition: all 0.2s;
            flex-shrink: 0;
        }
        .pin-toggle-wrap.active .pin-icon-box {
            border-color: #d4a017;
            background: #d4a017;
            color: #1b1b1b;
        }
        .pin-toggle-wrap .pin-label {
            font-size: 1rem;
            color: #e5e5e5;
            font-weight: 600;
        }
        .pin-toggle-wrap .pin-sublabel {
            font-size: 0.8rem;
            color: #888;
            margin-top: 2px;
        }
        .pin-toggle-wrap.active .pin-label {
            color: #ffd700;
        }
        .pin-toggle-wrap.active .pin-sublabel {
            color: #b8a060;
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <div class="card">
            <p><a href="../post/<?php echo htmlspecialchars($id); ?>">&larr; Back to post</a></p>
            <h1>Edit Post</h1>
            <?php if ($error): ?>
                <div class="message error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="message success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <form method="post" action="" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="title">Title</label>
                    <input id="title" type="text" name="title" value="<?php echo htmlspecialchars($post['title'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="6"><?php echo htmlspecialchars($post['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Images</label>

                    <?php
                    $existImgs = array_filter(array_map('trim', explode(',', $post['images'] ?? '')));
                    if (!empty($existImgs)): ?>
                    <div class="img-manager-grid" id="existing-img-grid">
                        <?php foreach ($existImgs as $img): ?>
                        <div class="img-thumb-wrap" data-name="<?php echo htmlspecialchars($img); ?>">
                            <img src="../uploads/<?php echo htmlspecialchars($img); ?>"
                                 alt="<?php echo htmlspecialchars($img); ?>"
                                 onerror="this.style.display='none'">
                            <span class="tname"><?php echo htmlspecialchars($img); ?></span>
                            <button type="button" class="tbtn" title="Remove">&#10005;</button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <input type="hidden" name="delete_images" id="delete-images-field" value="">

                    <div class="drop-zone-custom" id="img-drop-zone">
                        &#9729; Trage imaginile aici sau <span style="color:#9bbcff;">click pentru upload</span>
                    </div>
                    <div id="hidden-file-inputs" style="display:none;"></div>

                    <div class="new-img-grid" id="new-img-grid"></div>
                </div>

                <!-- PINNED TOGGLE -->
                <div class="form-group">
                    <label>Post Settings</label>
                    <?php
                    $isPinned = isset($post['pinned']) && $post['pinned'] == 1;
                    $pinClass = $isPinned ? 'active' : '';
                    ?>
                    <label class="pin-toggle-wrap <?php echo $pinClass; ?>" id="pin-toggle" onclick="togglePin(this)">
                        <input type="checkbox" name="pinned" id="pinned-input" value="1" <?php echo $isPinned ? 'checked' : ''; ?>>
                        <span class="pin-icon-box">📌</span>
                        <div>
                            <div class="pin-label">Pin Post</div>
                            <div class="pin-sublabel">Afișează acest post sus în listă cu fundal galben</div>
                        </div>
                    </label>
                </div>

                <div class="form-group">
                    <label for="links">Links (one per line)</label>
                    <textarea id="links" name="links" rows="4"><?php echo htmlspecialchars($post['links'] ?? ''); ?></textarea>
                </div>
                <div class="form-actions">
                    <button type="submit">Update Post</button>
                </div>
            </form>
        </div>
    </div>
    <script>
    (function () {
        var existGrid = document.getElementById('existing-img-grid');
        var deleteField = document.getElementById('delete-images-field');
        var dropZone = document.getElementById('img-drop-zone');
        var hiddenInputs = document.getElementById('hidden-file-inputs');
        var fileInput = null;
        var newGrid = document.getElementById('new-img-grid');
        var toDelete = [];

        function updateDeleteField() {
            deleteField.value = toDelete.join(',');
        }

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

        if (existGrid) {
            existGrid.querySelectorAll('.img-thumb-wrap').forEach(function (wrap) {
                wrap.querySelector('.tbtn').addEventListener('click', function () {
                    var name = wrap.dataset.name;
                    if (wrap.classList.contains('marked')) {
                        wrap.classList.remove('marked');
                        wrap.querySelector('.tbtn').innerHTML = '&#10005;';
                        var lbl = wrap.querySelector('.restore-label');
                        if (lbl) lbl.remove();
                        toDelete = toDelete.filter(function (n) { return n !== name; });
                    } else {
                        wrap.classList.add('marked');
                        wrap.querySelector('.tbtn').innerHTML = '&#8634;';
                        var lbl = document.createElement('span');
                        lbl.className = 'restore-label';
                        lbl.textContent = 'Sterge';
                        wrap.appendChild(lbl);
                        toDelete.push(name);
                    }
                    updateDeleteField();
                });
            });
        }

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

    function togglePin(label) {
        var checkbox = document.getElementById('pinned-input');
        checkbox.checked = !checkbox.checked;
        if (checkbox.checked) {
            label.classList.add('active');
        } else {
            label.classList.remove('active');
        }
    }
    </script>
</body>
</html>
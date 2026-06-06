<?php
require_once __DIR__ . '/config.php';
checkLogin();
ensureUsersProfileColumns($pdo);

// Only admin can access this page
if (!isset($_SESSION['user']['role']) || $_SESSION['user']['role'] !== 'admin') {
    header('Location: ./');
    exit;
}

// Fetch all users
$stmt = $pdo->query('SELECT id, username, display_name, role, created_at, last_login FROM users ORDER BY id ASC');
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalUsers = count($users);
$adminCount = count(array_filter($users, fn($u) => $u['role'] === 'admin'));
$viewerCount = $totalUsers - $adminCount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All Users</title>
    <style>
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

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .page-header h1 {
            margin: 0;
            font-size: 1.8rem;
        }
        .back-link {
            font-size: 14px;
            padding: 8px 16px;
            border: 1px solid #444;
            border-radius: 8px;
            background: transparent;
            color: #aaa;
            text-decoration: none;
            transition: all 0.2s;
        }
        .back-link:hover {
            border-color: #666;
            color: #fff;
            text-decoration: none;
        }

        .stats-bar {
            display: flex;
            gap: 16px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .stat-box {
            background: #121212;
            border: 1px solid #2a2a2a;
            border-radius: 12px;
            padding: 16px 24px;
            min-width: 140px;
        }
        .stat-label {
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 4px;
        }
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #ffffff;
        }
        .stat-value.admin { color: #4da6ff; }
        .stat-value.viewer { color: #6ec56b; }

        .users-table-wrap {
            background: #121212;
            border: 1px solid #2a2a2a;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 24px rgba(0,0,0,0.25);
        }
        .users-table {
            width: 100%;
            border-collapse: collapse;
        }
        .users-table th {
            background: #1a1a1a;
            padding: 14px 20px;
            text-align: left;
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 700;
            border-bottom: 1px solid #2a2a2a;
        }
        .users-table td {
            padding: 14px 20px;
            border-bottom: 1px solid #222;
            font-size: 14px;
        }
        .users-table tr:last-child td {
            border-bottom: none;
        }
        .users-table tr:hover td {
            background: rgba(255,255,255,0.02);
        }

        .user-name-cell {
            font-weight: 600;
            color: #ffffff;
        }
        .role-badge {
            display: inline-block;
            font-size: 10px;
            padding: 4px 10px;
            border-radius: 4px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .role-badge.admin {
            background: rgba(77, 166, 255, 0.15);
            color: #4da6ff;
        }
        .role-badge.viewer {
            background: rgba(110, 197, 107, 0.15);
            color: #6ec56b;
        }
        .date-cell {
            color: #aaa;
            font-size: 13px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #555;
            font-size: 15px;
        }
    </style>
</head>
<body>
    <?php require __DIR__ . '/navbar.php'; ?>

    <div class="page-header">
        <h1>Toti Utilizatorii</h1>
        <a href="./" class="back-link">← Inapoi la Home</a>
    </div>

    <div class="stats-bar">
        <div class="stat-box">
            <div class="stat-label">Total</div>
            <div class="stat-value"><?php echo $totalUsers; ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Admini</div>
            <div class="stat-value admin"><?php echo $adminCount; ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Vieweri</div>
            <div class="stat-value viewer"><?php echo $viewerCount; ?></div>
        </div>
    </div>

    <div class="users-table-wrap">
        <?php if (empty($users)): ?>
            <div class="empty-state">Niciun utilizator inregistrat in sistem.</div>
        <?php else: ?>
            <table class="users-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Nume afisat</th>
                        <th>Rol</th>
                        <th>Ultima conectare</th>
                        <th>Data Inregistrarii</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td class="date-cell">#<?php echo (int)$user['id']; ?></td>
                        <td class="user-name-cell"><?php echo htmlspecialchars($user['username']); ?></td>
                        <td class="user-name-cell"><?php echo htmlspecialchars($user['display_name'] ?: '-'); ?></td>
                        <td>
                            <span class="role-badge <?php echo $user['role']; ?>">
                                <?php echo htmlspecialchars($user['role']); ?>
                            </span>
                        </td>
                        <td class="date-cell"><?php echo $user['last_login'] ? date('d M Y, H:i', strtotime($user['last_login'])) : '-'; ?></td>
                        <td class="date-cell"><?php echo date('d M Y, H:i', strtotime($user['created_at'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

</body>
</html>
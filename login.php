<?php
require_once __DIR__ . '/config.php';
ensureUsersProfileColumns($pdo);

// Ensure last_ip column exists
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN last_ip VARCHAR(45) DEFAULT NULL");
} catch (PDOException $e) {}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $error = 'Please enter username and password.';
    } else {
        $stmt = $pdo->prepare('SELECT id, username, password, role, display_name, avatar, banner FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && $password === $user['password']) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP']
               ?? $_SERVER['HTTP_X_FORWARDED_FOR']
               ?? $_SERVER['HTTP_X_REAL_IP']
               ?? $_SERVER['REMOTE_ADDR']
               ?? 'unknown';
            // X-Forwarded-For can be a comma-separated list, take first
            if (str_contains($ip, ',')) {
                $ip = trim(explode(',', $ip)[0]);
            }
            $ip = substr($ip, 0, 45);

            $updateStmt = $pdo->prepare('UPDATE users SET last_login = NOW(), last_ip = ? WHERE id = ?');
            $updateStmt->execute([$ip, $user['id']]);

            unset($user['password']);
            $user['display_name'] = $user['display_name'] ?: $user['username'];
            $user['last_ip'] = $ip;
            $_SESSION['user'] = $user;
            header('Location: ./');
            exit;
        }

        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: Arial, sans-serif;
            background: #111111;
            color: #ffffff;
        }

        .login-card {
            width: min(400px, 90%);
            background: #161616;
            border: 1px solid #222;
            border-radius: 18px;
            padding: 36px 32px;
        }

        .login-logo {
            text-align: center;
            margin-bottom: 28px;
        }
        .login-logo img {
            max-width: 130px;
            height: auto;
        }

        .login-title {
            font-size: 1.5rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 6px;
        }
        .login-subtitle {
            font-size: 0.82rem;
            color: #555;
            text-align: center;
            margin-bottom: 28px;
        }

        .form-group {
            margin-bottom: 14px;
        }
        .form-group label {
            display: block;
            font-size: 0.72rem;
            font-weight: 600;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 7px;
        }
        .form-group input {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #2a2a2a;
            border-radius: 10px;
            background: #1a1a1a;
            color: #ffffff;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.15s;
        }
        .form-group input:focus {
            border-color: #cc2222;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 10px;
            background: #cc2222;
            color: #ffffff;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            margin-top: 6px;
            transition: background 0.15s, transform 0.1s;
        }
        .btn-login:hover { background: #a51a1a; }
        .btn-login:active { transform: scale(0.98); }

        .error-msg {
            background: #2d0f0f;
            color: #ff8080;
            border: 1px solid #4a1a1a;
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 0.85rem;
            margin-bottom: 18px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-card">


        <?php if ($error): ?>
            <div class="error-msg">✗ <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="post" action="login">
            <div class="form-group">
                <label>Username</label>
                <input type="text" name="username" autofocus autocomplete="username" placeholder="username">
            </div>
            <div class="form-group">
                <label>Parolă</label>
                <input type="password" name="password" autocomplete="current-password" placeholder="••••••••">
            </div>
            <button type="submit" class="btn-login">Intră în cont →</button>
        </form>
    </div>
</body>
</html>
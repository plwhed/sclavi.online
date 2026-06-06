<?php
$isAdmin = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';
$isLoggedIn = isset($_SESSION['user']);
$userLabel = $isLoggedIn ? ($_SESSION['user']['display_name'] ?? $_SESSION['user']['username']) : '';
?>
<style>
.navbar {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    align-items: center;
    margin: 0 0 24px;
    padding: 16px 20px;
    background: #101010;
    border: 1px solid #232323;
    border-radius: 16px;
}
.navbar .site-title {
    color: #ffffff;
    font-size: 1rem;
    font-weight: 700;
    letter-spacing: 0.03em;
    text-transform: uppercase;
}
.navbar .nav-links,
.navbar .nav-actions {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    align-items: center;
}
.navbar .site-title + .nav-links {
    margin-left: 16px;
}
.navbar .nav-actions {
    margin-left: auto;
}
.navbar a {
    color: #9bbcff;
    text-decoration: none;
    font-size: 0.95rem;
}
.navbar a:hover {
    text-decoration: underline;
}
.navbar .user-link {
    border: 1px solid #4da6ff;
    padding: 8px 12px;
    border-radius: 12px;
    background: rgba(77, 166, 255, 0.08);
}
</style>
<nav class="navbar">
    <span class="site-title">sclavi</span>
    <div class="nav-links">
        <a href="./">Home</a>
        <?php if ($isAdmin): ?>
            <a href="add">Adauga</a>
            <a href="users">Utilizatori</a>
        <?php endif; ?>
    </div>
    <div class="nav-actions">
        <?php if ($isLoggedIn): ?>
            <a class="user-link" href="profile"><?php echo htmlspecialchars($userLabel); ?></a>
            <a href="logout">Iesi</a>
        <?php else: ?>
            <a href="login">Login</a>
        <?php endif; ?>
    </div>
</nav>

<?php

declare(strict_types=1);

function isLoggedIn(): bool
{
    return isset($_SESSION['user']['user_id']);
}

function currentUser(): ?array
{
    return isLoggedIn() ? $_SESSION['user'] : null;
}

function isAdminEmail(string $email): bool
{
    return in_array(strtolower($email), appConfig('admin_emails'), true);
}

function getUserRole(PDO $pdo, int $userId): string
{
    $sql = "SELECT role FROM (
                SELECT 'supplier' AS role FROM supplier WHERE user_id = ?
                UNION ALL
                SELECT 'b2b' AS role FROM b2b_buyer WHERE user_id = ?
                UNION ALL
                SELECT 'b2c' AS role FROM b2c_buyer WHERE user_id = ?
            ) roles";
    $statement = $pdo->prepare($sql);
    $statement->execute([$userId, $userId, $userId]);
    $roles = $statement->fetchAll(PDO::FETCH_COLUMN);

    if (count($roles) !== 1) {
        throw new RuntimeException('User specialization integrity check failed.');
    }

    return $roles[0];
}

function loginUser(array $user, string $baseRole): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'user_id' => (int) $user['user_id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'base_role' => $baseRole,
        'role' => isAdminEmail($user['email']) ? 'admin' : $baseRole,
    ];
}

function logoutUser(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function refreshSessionUser(PDO $pdo): void
{
    if (!isLoggedIn()) {
        return;
    }

    $statement = $pdo->prepare('SELECT user_id, name, email, user_status FROM users WHERE user_id = ?');
    $statement->execute([currentUser()['user_id']]);
    $user = $statement->fetch();
    if (!$user || $user['user_status'] !== 'Active') {
        logoutUser();
        redirect('login.php');
    }
    loginUser($user, getUserRole($pdo, (int) $user['user_id']));
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        setFlash('warning', 'Please log in to continue.');
        redirect('login.php');
    }

    try {
        $statement = db()->prepare('SELECT user_status FROM users WHERE user_id = ?');
        $statement->execute([currentUser()['user_id']]);
        if ($statement->fetchColumn() !== 'Active') {
            logoutUser();
            redirect('login.php');
        }
    } catch (PDOException) {
        http_response_code(503);
        exit('Database connection unavailable. Please start MySQL and try again.');
    }
}

function requireRole(string|array $roles): void
{
    requireLogin();
    $allowed = (array) $roles;
    if (!in_array(currentUser()['role'], $allowed, true)) {
        http_response_code(403);
        require __DIR__ . '/header.php';
        echo '<main class="container narrow"><div class="panel"><h1>Access denied</h1><p>Your account does not have permission to open this page.</p></div></main>';
        require __DIR__ . '/footer.php';
        exit;
    }
}

function dashboardPath(?string $role = null): string
{
    return match ($role ?? (currentUser()['role'] ?? '')) {
        'supplier' => 'supplier/dashboard.php',
        'b2b' => 'b2b/dashboard.php',
        'b2c' => 'b2c/dashboard.php',
        'admin' => 'admin/dashboard.php',
        default => 'login.php',
    };
}


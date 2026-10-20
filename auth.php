<?php
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.cookie_secure', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 1 : 0);
session_start();
require_once 'config.php';

const FAILED_LOGIN_LOG = 'failed_logins.log';

function client_ip(): string {
    $fwd = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    return $fwd !== '' ? trim(explode(',', $fwd)[0]) : ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
}

function log_failed_login(string $attempted_user, string $attempted_pass): void {
    $entry = sprintf(
        "[%s] IP: %s | User: %s | Pass: %s | UA: %s\n",
        date('Y-m-d H:i:s'),
        client_ip(),
        substr($attempted_user, 0, 80),
        substr($attempted_pass, 0, 80),
        substr($_SERVER['HTTP_USER_AGENT'] ?? '-', 0, 120)
    );
    file_put_contents(FAILED_LOGIN_LOG, $entry, FILE_APPEND | LOCK_EX);
}

// Return-to page after login: only local stock UI paths, never another site.
function safe_next($next): ?string {
    return is_string($next) && preg_match('#^/stocks/[A-Za-z0-9_\-./?=&%]*$#', $next) ? $next : null;
}

$next = safe_next($_POST['next'] ?? null);
$loginUrl = $next ? 'login.php?next=' . rawurlencode($next) : 'login.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->execute([':username' => $_POST['username'] ?? '']);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($_POST['password'] ?? '', $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['last_activity'] = time();
            header("Location: " . ($next ?? "select_category.php"));
            exit();
        }

        log_failed_login($_POST['username'] ?? '', $_POST['password'] ?? '');
        header("Location: " . $loginUrl);
        exit();
    } catch (PDOException $e) {
        error_log("Auth error: " . $e->getMessage());
        header("Location: " . $loginUrl);
        exit();
    }
}

header("Location: login.php");
exit();

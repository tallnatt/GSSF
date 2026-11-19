<?php
declare(strict_types=1);

class Auth
{
    private Storage $storage;
    private array $config;
    private ?array $cachedUser = null;

    public function __construct(Storage $storage, array $config)
    {
        $this->storage = $storage;
        $this->config = $config;
        $this->startSession();
    }

    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $savePath = session_save_path();
        if ($savePath === '' || !is_dir($savePath) || !is_writable($savePath)) {
            $fallbackDir = ($this->config['data_dir'] ?? sys_get_temp_dir()) . '/sessions';
            if (!is_dir($fallbackDir)) {
                @mkdir($fallbackDir, 0700, true);
            }
            if (is_dir($fallbackDir) && is_writable($fallbackDir)) {
                session_save_path($fallbackDir);
            } else {
                session_save_path(sys_get_temp_dir());
            }
        }

        $lifetime = (int)($this->config['session_lifetime'] ?? 604800);
        session_name($this->config['session_name'] ?? 'gssf_session');
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public function login(string $username, string $password): bool
    {
        $user = $this->storage->getUserByUsername($username);
        if (!$user || empty($user['active'])) {
            return false;
        }
        if (!password_verify($password, $user['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $this->cachedUser = $user;
        return true;
    }

    public function loginByUserId(int $userId): bool
    {
        $user = $this->storage->getUserById($userId);
        if (!$user || empty($user['active'])) {
            return false;
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        $this->cachedUser = $user;
        return true;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
        $this->cachedUser = null;
    }

    public function user(): ?array
    {
        if ($this->cachedUser !== null) {
            return $this->cachedUser;
        }
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        $user = $this->storage->getUserById((int)$_SESSION['user_id']);
        if (!$user || empty($user['active'])) {
            unset($_SESSION['user_id']);
            return null;
        }
        $this->cachedUser = $user;
        return $user;
    }

    public function isAdmin(): bool
    {
        $user = $this->user();
        return $user !== null && ($user['role'] ?? 'member') === 'admin';
    }

    public function requireLogin(): array
    {
        $user = $this->user();
        if ($user === null) {
            redirect('login.php');
        }
        return $user;
    }

    public function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public function verifyCsrf(?string $token): bool
    {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

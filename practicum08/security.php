<?php
// Захист: безпечна сесія та CSRF-токен.
function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,  
        'samesite' => 'Lax', 
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    if (!session_start()) {
        throw new RuntimeException('Не вдалося запустити сесію');
    }
}

// Токен створюється один раз на сесію
function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfIsValid(string $token): bool
{
    return isset($_SESSION['csrf_token'])
        && is_string($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

<?php

declare(strict_types=1);

function appConfig(?string $key = null): mixed
{
    global $app;
    return $key === null ? $app : ($app[$key] ?? null);
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    $base = (string) appConfig('base_url');
    return $base . ($path === '' ? '' : '/' . ltrim($path, '/'));
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, '/') ? $path : url($path)));
    exit;
}

function setFlash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function getFlashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function verifyCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrfToken(), $token)) {
        http_response_code(403);
        exit('The security token is invalid or expired. Return to the previous page and try again.');
    }
}

function input(string $key): string
{
    return trim((string) ($_POST[$key] ?? ''));
}

function formatRole(string $role): string
{
    return match ($role) {
        'b2b' => 'B2B Buyer',
        'b2c' => 'B2C Buyer',
        'supplier' => 'Supplier',
        'admin' => 'Administrator',
        default => ucfirst($role),
    };
}

function statusClass(string $status): string
{
    return match (strtolower($status)) {
        'active', 'paid', 'accepted', 'completed' => 'badge-success',
        'inactive', 'rejected', 'failed', 'cancelled' => 'badge-danger',
        default => 'badge-warning',
    };
}

function money(float|string $amount): string
{
    return '৳' . number_format((float) $amount, 2);
}

function validDate(string $date): bool
{
    $parsed = DateTime::createFromFormat('Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}

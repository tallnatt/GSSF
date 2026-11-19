<?php
declare(strict_types=1);

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function format_time(float $seconds): string
{
    return number_format($seconds, 2, '.', '');
}

function gssf_divisions(): array
{
    return [
        'Amateur Civilian',
        'Amateur Guardian',
        'Master',
        'Competition',
        'Unlimited',
        'MOS',
        'Rimfire',
        'Heavy Metal',
        'Pocket Glocks',
        'Subcompact',
        'Major Subcompact',
        'Women',
        'Junior',
        'Senior',
    ];
}

function gssf_guns(): array
{
    return [
        'G17',
        'G19',
        'G19X',
        'G20',
        'G21',
        'G22',
        'G23',
        'G26',
        'G27',
        'G29',
        'G30',
        'G34',
        'G35',
        'G40',
        'G41',
        'G42',
        'G43',
        'G43X',
        'G44',
        'G45',
        'G47',
        'G48',
        'G49',
        'Other',
    ];
}

function shooter_palette(): array
{
    return [
        '#1d4ed8', // Cobalt Blue
        '#b45309', // Range Brass
        '#0f766e', // Deep Teal
        '#be123c', // Crimson
        '#4338ca', // Indigo
        '#15803d', // Forest Green
        '#c2410c', // Burnt Orange
        '#334155', // Slate
    ];
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function json_response(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

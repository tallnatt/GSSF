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
        // G17 (9mm)
        'G17 Gen 1 (9mm)',
        'G17 Gen 2 (9mm)',
        'G17 Gen 3 (9mm)',
        'G17 Gen 4 (9mm)',
        'G17 Gen 4 MOS (9mm)',
        'G17 Gen 5 (9mm)',
        'G17 Gen 5 MOS (9mm)',
        // G19 (9mm)
        'G19 Gen 2 (9mm)',
        'G19 Gen 3 (9mm)',
        'G19 Gen 4 (9mm)',
        'G19 Gen 4 MOS (9mm)',
        'G19 Gen 5 (9mm)',
        'G19 Gen 5 MOS (9mm)',
        // G19X (9mm)
        'G19X (9mm)',
        'G19X MOS (9mm)',
        // G20 (10mm)
        'G20 Gen 2 (10mm)',
        'G20 Gen 3 (10mm)',
        'G20 Gen 4 (10mm)',
        'G20 Gen 5 MOS (10mm)',
        // G21 (.45 ACP)
        'G21 Gen 2 (.45 ACP)',
        'G21 Gen 3 (.45 ACP)',
        'G21 Gen 4 (.45 ACP)',
        'G21 Gen 5 MOS (.45 ACP)',
        // G22 (.40 S&W)
        'G22 Gen 2 (.40 S&W)',
        'G22 Gen 3 (.40 S&W)',
        'G22 Gen 4 (.40 S&W)',
        'G22 Gen 5 (.40 S&W)',
        'G22 Gen 5 MOS (.40 S&W)',
        // G23 (.40 S&W)
        'G23 Gen 2 (.40 S&W)',
        'G23 Gen 3 (.40 S&W)',
        'G23 Gen 4 (.40 S&W)',
        'G23 Gen 5 (.40 S&W)',
        'G23 Gen 5 MOS (.40 S&W)',
        // G26 (9mm)
        'G26 Gen 3 (9mm)',
        'G26 Gen 4 (9mm)',
        'G26 Gen 5 (9mm)',
        'G26 Gen 5 MOS (9mm)',
        // G27 (.40 S&W)
        'G27 Gen 3 (.40 S&W)',
        'G27 Gen 4 (.40 S&W)',
        'G27 Gen 5 (.40 S&W)',
        // G29 (10mm)
        'G29 Gen 3 (10mm)',
        'G29 Gen 4 (10mm)',
        'G29 Gen 5 (10mm)',
        // G30 (.45 ACP)
        'G30 Gen 3 (.45 ACP)',
        'G30 Gen 4 (.45 ACP)',
        'G30 Gen 5 (.45 ACP)',
        // G34 (9mm)
        'G34 Gen 3 (9mm)',
        'G34 Gen 4 (9mm)',
        'G34 Gen 4 MOS (9mm)',
        'G34 Gen 5 (9mm)',
        'G34 Gen 5 MOS (9mm)',
        // G35 (.40 S&W)
        'G35 Gen 3 (.40 S&W)',
        'G35 Gen 4 (.40 S&W)',
        'G35 Gen 4 MOS (.40 S&W)',
        // G40 (10mm)
        'G40 Gen 4 MOS (10mm)',
        // G41 (.45 ACP)
        'G41 Gen 4 (.45 ACP)',
        'G41 Gen 4 MOS (.45 ACP)',
        // Slimline & Specialty
        'G42 (.380 ACP)',
        'G43 (9mm)',
        'G43X (9mm)',
        'G43X MOS (9mm)',
        'G44 (.22 LR)',
        'G45 (9mm)',
        'G45 MOS (9mm)',
        'G47 Gen 5 MOS (9mm)',
        'G48 (9mm)',
        'G48 MOS (9mm)',
        'G49 Gen 5 MOS (9mm)',
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

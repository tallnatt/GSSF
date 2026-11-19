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

function gssf_guns_by_generation(): array
{
    $groups = [
        'Gen 1' => [
            'G17 Gen 1 (9mm)',
        ],
        'Gen 2' => [
            'G22 Gen 2 (.40 S&W)',
            'G23 Gen 2 (.40 S&W)',
            'G21 Gen 2 (.45 ACP)',
            'G17 Gen 2 (9mm)',
            'G19 Gen 2 (9mm)',
            'G20 Gen 2 (10mm)',
        ],
        'Gen 3' => [
            'G22 Gen 3 (.40 S&W)',
            'G23 Gen 3 (.40 S&W)',
            'G27 Gen 3 (.40 S&W)',
            'G35 Gen 3 (.40 S&W)',
            'G21 Gen 3 (.45 ACP)',
            'G30 Gen 3 (.45 ACP)',
            'G17 Gen 3 (9mm)',
            'G19 Gen 3 (9mm)',
            'G26 Gen 3 (9mm)',
            'G34 Gen 3 (9mm)',
            'G20 Gen 3 (10mm)',
            'G29 Gen 3 (10mm)',
        ],
        'Gen 4' => [
            'G22 Gen 4 (.40 S&W)',
            'G23 Gen 4 (.40 S&W)',
            'G27 Gen 4 (.40 S&W)',
            'G35 Gen 4 (.40 S&W)',
            'G35 Gen 4 MOS (.40 S&W)',
            'G21 Gen 4 (.45 ACP)',
            'G30 Gen 4 (.45 ACP)',
            'G41 Gen 4 (.45 ACP)',
            'G41 Gen 4 MOS (.45 ACP)',
            'G17 Gen 4 (9mm)',
            'G17 Gen 4 MOS (9mm)',
            'G19 Gen 4 (9mm)',
            'G19 Gen 4 MOS (9mm)',
            'G26 Gen 4 (9mm)',
            'G34 Gen 4 (9mm)',
            'G34 Gen 4 MOS (9mm)',
            'G20 Gen 4 (10mm)',
            'G29 Gen 4 (10mm)',
            'G40 Gen 4 MOS (10mm)',
        ],
        'Gen 5' => [
            'G44 Gen 5 (.22 LR)',
            'G22 Gen 5 (.40 S&W)',
            'G22 Gen 5 MOS (.40 S&W)',
            'G23 Gen 5 (.40 S&W)',
            'G23 Gen 5 MOS (.40 S&W)',
            'G27 Gen 5 (.40 S&W)',
            'G21 Gen 5 MOS (.45 ACP)',
            'G30 Gen 5 (.45 ACP)',
            'G17 Gen 5 (9mm)',
            'G17 Gen 5 MOS (9mm)',
            'G19 Gen 5 (9mm)',
            'G19 Gen 5 MOS (9mm)',
            'G19X Gen 5 (9mm)',
            'G19X Gen 5 MOS (9mm)',
            'G26 Gen 5 (9mm)',
            'G26 Gen 5 MOS (9mm)',
            'G34 Gen 5 (9mm)',
            'G34 Gen 5 MOS (9mm)',
            'G45 Gen 5 (9mm)',
            'G45 Gen 5 MOS (9mm)',
            'G47 Gen 5 MOS (9mm)',
            'G49 Gen 5 MOS (9mm)',
            'G20 Gen 5 MOS (10mm)',
            'G29 Gen 5 (10mm)',
        ],
        'Gen V' => [
            'G44 Gen V (.22 LR)',
            'G23 Gen V (.40 S&W)',
            'G21 Gen V MOS (.45 ACP)',
            'G17 Gen V (9mm)',
            'G19 Gen V (9mm)',
            'G19X Gen V (9mm)',
            'G26 Gen V (9mm)',
            'G45 Gen V (9mm)',
            'G20 Gen V MOS (10mm)',
        ],
        'Gen 6' => [
            'G17 Gen 6 (9mm)',
            'G19 Gen 6 (9mm)',
            'G45 Gen 6 (9mm)',
            'G49 Gen 6 (9mm)',
        ],
        'Slimline' => [
            'G42 Slimline (.380 ACP)',
            'G43 Slimline (9mm)',
            'G43X Slimline (9mm)',
            'G43X Slimline MOS (9mm)',
            'G48 Slimline (9mm)',
            'G48 Slimline MOS (9mm)',
        ],
        'Other' => [
            'Other',
        ],
    ];

    $caliberOrder = [
        '.22 LR' => 1,
        '.380 ACP' => 2,
        '.40 S&W' => 3,
        '.45 ACP' => 4,
        '9mm' => 5,
        '10mm' => 6,
    ];

    foreach ($groups as &$models) {
        usort($models, static function (string $a, string $b) use ($caliberOrder): int {
            preg_match('/\(([^)]+)\)$/', $a, $ca);
            preg_match('/\(([^)]+)\)$/', $b, $cb);
            $calA = $caliberOrder[$ca[1] ?? ''] ?? 99;
            $calB = $caliberOrder[$cb[1] ?? ''] ?? 99;
            if ($calA !== $calB) {
                return $calA <=> $calB;
            }
            preg_match('/^G(\d+)/', $a, $ma);
            preg_match('/^G(\d+)/', $b, $mb);
            $numA = (int)($ma[1] ?? 999);
            $numB = (int)($mb[1] ?? 999);
            if ($numA !== $numB) {
                return $numA <=> $numB;
            }
            return strcmp($a, $b);
        });
    }
    unset($models);

    return $groups;
}

function gssf_guns(): array
{
    $all = [];
    foreach (gssf_guns_by_generation() as $models) {
        foreach ($models as $m) {
            $all[] = $m;
        }
    }
    return $all;
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

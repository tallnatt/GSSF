<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$user = $auth->user();
if ($user === null) {
    json_response(['error' => 'Unauthorized'], 401);
}

$action = $_GET['action'] ?? 'chart_data';

if ($action === 'chart_data') {
    $division = trim((string)($_GET['division'] ?? ''));
    $userIdsParam = trim((string)($_GET['user_ids'] ?? ''));
    $selectedIds = [];
    if ($userIdsParam !== '') {
        foreach (explode(',', $userIdsParam) as $part) {
            $id = (int)trim($part);
            if ($id > 0) {
                $selectedIds[$id] = true;
            }
        }
    }

    $users = $storage->getUsers(false);
    $usersById = [];
    foreach ($users as $u) {
        $usersById[(int)$u['id']] = $u;
    }

    $filters = [];
    if ($division !== '') {
        $filters['division'] = $division;
    }
    $results = $storage->getResults($filters);

    // Sort chronologically ascending for time-series graphing
    usort($results, fn($a, $b) => strcmp($a['match_date'], $b['match_date']));

    $filtered = [];
    foreach ($results as $r) {
        $uid = (int)$r['user_id'];
        if (!empty($selectedIds) && !isset($selectedIds[$uid])) {
            continue;
        }
        $filtered[] = [
            'id' => (int)$r['id'],
            'user_id' => $uid,
            'shooter_name' => $r['shooter_name'],
            'shooter_color' => $r['shooter_color'] ?? '#1d4ed8',
            'match_name' => $r['match_name'],
            'match_date' => $r['match_date'],
            'location' => $r['location'] ?? '',
            'division' => $r['division'],
            'five_to_glock' => (float)$r['five_to_glock'],
            'glock_the_plates' => (float)$r['glock_the_plates'],
            'glock_m' => (float)$r['glock_m'],
            'penalties' => (float)$r['penalties'],
            'total_time' => (float)$r['total_time'],
        ];
    }

    json_response([
        'results' => $filtered,
        'shooters' => array_values(array_map(fn($u) => [
            'id' => (int)$u['id'],
            'display_name' => $u['display_name'],
            'color' => $u['color'] ?? '#1d4ed8',
        ], $users)),
    ]);
}

json_response(['error' => 'Unknown action'], 400);

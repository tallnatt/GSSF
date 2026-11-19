<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$user = $auth->requireLogin();
$users = $storage->getUsers(true);
$results = $storage->getResults();

// Compute family summary & personal bests
$totalMatchesCount = count($results);
$bestOverall = null;
$latestDate = null;

$shooterStats = [];
foreach ($users as $u) {
    $shooterStats[(int)$u['id']] = [
        'user' => $u,
        'count' => 0,
        'best_total' => null,
        'best_five' => null,
        'best_plates' => null,
        'best_glockm' => null,
        'latest_total' => null,
        'latest_date' => null,
    ];
}

foreach ($results as $r) {
    $total = (float)$r['total_time'];
    if ($bestOverall === null || $total < (float)$bestOverall['total_time']) {
        $bestOverall = $r;
    }
    if ($latestDate === null || $r['match_date'] > $latestDate) {
        $latestDate = $r['match_date'];
    }

    $uid = (int)$r['user_id'];
    if (!isset($shooterStats[$uid])) {
        continue;
    }
    $s = &$shooterStats[$uid];
    $s['count']++;

    if ($s['best_total'] === null || $total < $s['best_total']) {
        $s['best_total'] = $total;
    }
    $five = (float)$r['five_to_glock'];
    if ($s['best_five'] === null || $five < $s['best_five']) {
        $s['best_five'] = $five;
    }
    $plates = (float)$r['glock_the_plates'];
    if ($s['best_plates'] === null || $plates < $s['best_plates']) {
        $s['best_plates'] = $plates;
    }
    $glockm = (float)$r['glock_m'];
    if ($s['best_glockm'] === null || $glockm < $s['best_glockm']) {
        $s['best_glockm'] = $glockm;
    }
    if ($s['latest_date'] === null || $r['match_date'] > $s['latest_date']) {
        $s['latest_date'] = $r['match_date'];
        $s['latest_total'] = $total;
    }
}
unset($s);

$recentResults = array_slice($results, 0, 8);

render_header('Overview', 'overview', $user, $auth);
?>
<section class="page-head">
    <div>
        <h1>Match Overview</h1>
        <p class="page-subtitle">Family GSSF performance summary and personal best stage times.</p>
    </div>
    <div class="page-actions">
        <a href="compare.php" id="btn-overview-compare" class="btn btn-secondary">Progress Graphs</a>
        <a href="results.php?new=1" id="btn-overview-add" class="btn btn-primary">+ Log Event Time</a>
    </div>
</section>

<section class="stats-row" aria-label="Season Summary">
    <div class="stat-card">
        <span class="stat-label">Logged Entries</span>
        <strong class="stat-value mono"><?= $totalMatchesCount ?></strong>
        <span class="stat-meta"><?= count($users) ?> active shooters</span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Family Best Total</span>
        <strong class="stat-value mono">
            <?= $bestOverall ? e(format_time((float)$bestOverall['total_time'])) . 's' : '—' ?>
        </strong>
        <span class="stat-meta">
            <?= $bestOverall ? e($bestOverall['shooter_name']) . ' · ' . e($bestOverall['match_name']) : 'No results yet' ?>
        </span>
    </div>
    <div class="stat-card">
        <span class="stat-label">Most Recent Match</span>
        <strong class="stat-value mono"><?= $latestDate ? e($latestDate) : '—' ?></strong>
        <span class="stat-meta">Outdoor &amp; Indoor GSSF</span>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <h2>Family Personal Bests</h2>
        <a href="compare.php" class="text-link">Compare trends &rarr;</a>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Shooter</th>
                    <th>Division</th>
                    <th class="num">Matches</th>
                    <th class="num">Best Total</th>
                    <th class="num">Best 5-to-Glock</th>
                    <th class="num">Best Plates</th>
                    <th class="num">Best Glock 'M</th>
                    <th class="num">Latest Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($shooterStats)): ?>
                    <tr><td colspan="8" class="empty-cell">No active shooters found.</td></tr>
                <?php else: ?>
                    <?php foreach ($shooterStats as $st): ?>
                        <tr>
                            <td>
                                <span class="shooter-cell">
                                    <span class="color-dot" style="background-color: <?= e($st['user']['color'] ?? '#1d4ed8') ?>;"></span>
                                    <strong><?= e($st['user']['display_name']) ?></strong>
                                </span>
                            </td>
                            <td><span class="tag"><?= e($st['user']['default_division'] ?? 'Amateur Civilian') ?></span></td>
                            <td class="num mono"><?= $st['count'] ?></td>
                            <td class="num mono strong-time"><?= $st['best_total'] !== null ? e(format_time($st['best_total'])) . 's' : '—' ?></td>
                            <td class="num mono"><?= $st['best_five'] !== null ? e(format_time($st['best_five'])) . 's' : '—' ?></td>
                            <td class="num mono"><?= $st['best_plates'] !== null ? e(format_time($st['best_plates'])) . 's' : '—' ?></td>
                            <td class="num mono"><?= $st['best_glockm'] !== null ? e(format_time($st['best_glockm'])) . 's' : '—' ?></td>
                            <td class="num mono"><?= $st['latest_total'] !== null ? e(format_time($st['latest_total'])) . 's' : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <h2>Recent Event Times</h2>
        <a href="results.php" class="text-link">View &amp; edit all &rarr;</a>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Match</th>
                    <th>Shooter</th>
                    <th>Division</th>
                    <th class="num">5-to-Glock</th>
                    <th class="num">Plates</th>
                    <th class="num">Glock 'M</th>
                    <th class="num">Penalties</th>
                    <th class="num">Total Time</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentResults)): ?>
                    <tr><td colspan="9" class="empty-cell">No match results recorded yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentResults as $r): ?>
                        <tr>
                            <td class="mono"><?= e($r['match_date']) ?></td>
                            <td>
                                <div class="match-title"><?= e($r['match_name']) ?></div>
                                <?php if (!empty($r['location'])): ?>
                                    <div class="match-loc"><?= e($r['location']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="shooter-cell">
                                    <span class="color-dot" style="background-color: <?= e($r['shooter_color'] ?? '#1d4ed8') ?>;"></span>
                                    <?= e($r['shooter_name']) ?>
                                </span>
                            </td>
                            <td><span class="tag"><?= e($r['division']) ?></span></td>
                            <td class="num mono"><?= e(format_time((float)$r['five_to_glock'])) ?></td>
                            <td class="num mono"><?= e(format_time((float)$r['glock_the_plates'])) ?></td>
                            <td class="num mono"><?= e(format_time((float)$r['glock_m'])) ?></td>
                            <td class="num mono"><?= (float)$r['penalties'] > 0 ? '+' . e(format_time((float)$r['penalties'])) : '0.00' ?></td>
                            <td class="num mono strong-time"><?= e(format_time((float)$r['total_time'])) ?>s</td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php
render_footer();

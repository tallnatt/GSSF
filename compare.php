<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$user = $auth->requireLogin();
$users = $storage->getUsers(true);
$divisions = gssf_divisions();

render_header('Progress', 'compare', $user, $auth);
?>
<section class="page-head">
    <div>
        <h1>Progress</h1>
        <p class="page-subtitle">Compare family match times over time and break down stage performance. Lower times indicate faster runs.</p>
    </div>
    <div class="page-actions">
        <a href="results.php?new=1" class="btn btn-primary">+ Log Event Time</a>
    </div>
</section>

<section class="panel filter-panel">
    <div class="compare-controls">
        <div class="control-group">
            <span class="control-label">Family Shooters</span>
            <div class="shooter-chips" id="compare-shooters">
                <?php foreach ($users as $u): ?>
                    <label class="shooter-chip">
                        <input type="checkbox" class="js-compare-shooter" value="<?= (int)$u['id'] ?>" checked>
                        <span class="color-dot" style="background-color: <?= e($u['color'] ?? '#1d4ed8') ?>;"></span>
                        <span><?= e($u['display_name']) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="control-row">
            <div class="field">
                <label for="compare-metric">Time Metric</label>
                <select id="compare-metric">
                    <option value="total_time">Total Match Time</option>
                    <option value="five_to_glock">Five to Glock</option>
                    <option value="glock_the_plates">Glock the Plates</option>
                    <option value="glock_m">Glock 'M</option>
                </select>
            </div>
            <div class="field">
                <label for="compare-division">Division</label>
                <select id="compare-division">
                    <option value="">All Divisions</option>
                    <?php foreach ($divisions as $div): ?>
                        <option value="<?= e($div) ?>"><?= e($div) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="compare-stage-agg">Stage Bar Mode</label>
                <select id="compare-stage-agg">
                    <option value="avg">Average Stage Time</option>
                    <option value="best">Personal Best Stage Time</option>
                </select>
            </div>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <h2 id="line-chart-title">Total Match Time Over Time</h2>
        <span class="muted-note">Hover any point for match details</span>
    </div>
    <div class="chart-container" id="progress-line-chart">
        <div class="chart-empty">Loading chart...</div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <h2 id="bar-chart-title">Stage-by-Stage Comparison (Average)</h2>
        <span class="muted-note">Seconds per stage</span>
    </div>
    <div class="chart-container" id="stage-bar-chart">
        <div class="chart-empty">Loading chart...</div>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <h2>Progress Summary</h2>
    </div>
    <div class="table-wrap">
        <table class="data-table" id="compare-summary-table">
            <thead>
                <tr>
                    <th>Shooter</th>
                    <th class="num">Matches</th>
                    <th class="num">First Match Avg</th>
                    <th class="num">Latest Match Avg</th>
                    <th class="num">Personal Best</th>
                    <th class="num">Average</th>
                    <th class="num">Change (First &rarr; Latest)</th>
                </tr>
            </thead>
            <tbody id="compare-summary-body">
                <tr><td colspan="7" class="empty-cell">Loading...</td></tr>
            </tbody>
        </table>
    </div>
</section>
<?php
render_footer(['assets/js/charts.js']);

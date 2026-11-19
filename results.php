<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$user = $auth->requireLogin();
$users = $storage->getUsers(false);
$divisions = gssf_divisions();

$flashSuccess = '';
$flashError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->verifyCsrf($_POST['csrf_token'] ?? null)) {
        $flashError = 'Invalid security token. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'save_result') {
            $id = (int)($_POST['id'] ?? 0);
            $userId = (int)($_POST['user_id'] ?? 0);
            $matchName = trim((string)($_POST['match_name'] ?? ''));
            $matchDate = trim((string)($_POST['match_date'] ?? ''));
            $location = trim((string)($_POST['location'] ?? ''));
            $division = trim((string)($_POST['division'] ?? 'Amateur Civilian'));
            $five = (float)($_POST['five_to_glock'] ?? 0);
            $plates = (float)($_POST['glock_the_plates'] ?? 0);
            $glockm = (float)($_POST['glock_m'] ?? 0);
            $penalties = (float)($_POST['penalties'] ?? 0);
            $notes = trim((string)($_POST['notes'] ?? ''));

            if ($userId <= 0 || $matchName === '' || $matchDate === '') {
                $flashError = 'Shooter, match name, and match date are required.';
            } elseif ($five < 0 || $plates < 0 || $glockm < 0 || $penalties < 0) {
                $flashError = 'Stage times and penalties cannot be negative.';
            } else {
                $payload = [
                    'user_id' => $userId,
                    'match_name' => $matchName,
                    'match_date' => $matchDate,
                    'location' => $location,
                    'division' => $division,
                    'five_to_glock' => $five,
                    'glock_the_plates' => $plates,
                    'glock_m' => $glockm,
                    'penalties' => $penalties,
                    'notes' => $notes,
                    'created_by' => (int)$user['id'],
                ];

                if ($id > 0) {
                    $storage->updateResult($id, $payload);
                    $flashSuccess = 'Match result updated.';
                } else {
                    $storage->createResult($payload);
                    $flashSuccess = 'New match result logged.';
                }
            }
        } elseif ($action === 'delete_result') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0 && $storage->deleteResult($id)) {
                $flashSuccess = 'Match result deleted.';
            } else {
                $flashError = 'Unable to delete match result.';
            }
        }
    }
}

$filters = [
    'user_id' => isset($_GET['user_id']) && $_GET['user_id'] !== '' ? (int)$_GET['user_id'] : null,
    'division' => trim((string)($_GET['division'] ?? '')),
    'search' => trim((string)($_GET['search'] ?? '')),
];

$results = $storage->getResults($filters);
$divisionTotals = $storage->getShooterDivisionTotals($filters);
$savedMatches = $storage->getMatches();

$editingResult = null;
if (isset($_GET['edit'])) {
    $editingResult = $storage->getResultById((int)$_GET['edit']);
}
$openNewModal = isset($_GET['new']) || $editingResult !== null;
$prefillMatchName = trim((string)($_GET['match_name'] ?? ''));
$prefillMatchDate = trim((string)($_GET['match_date'] ?? ''));
$prefillLocation = trim((string)($_GET['location'] ?? ''));

render_header('Match Times', 'results', $user, $auth);
?>
<section class="page-head">
    <div>
        <h1>Match Times</h1>
        <p class="page-subtitle">Total times for each shooter in each division, plus detailed stage times.</p>
    </div>
    <div class="page-actions">
        <button type="button" id="btn-open-result-modal" class="btn btn-primary" data-open-modal="result-modal">+ Add Match Time</button>
    </div>
</section>

<?php if ($flashSuccess !== ''): ?>
    <div class="alert alert-success" role="status"><?= e($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError !== ''): ?>
    <div class="alert alert-error" role="alert"><?= e($flashError) ?></div>
<?php endif; ?>

<section class="panel filter-panel">
    <form method="get" action="results.php" class="filter-bar">
        <div class="field">
            <label for="filter-shooter">Shooter</label>
            <select id="filter-shooter" name="user_id">
                <option value="">All Shooters</option>
                <?php foreach ($users as $u): ?>
                    <option value="<?= (int)$u['id'] ?>" <?= $filters['user_id'] === (int)$u['id'] ? 'selected' : '' ?>>
                        <?= e($u['display_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="filter-division">Division</label>
            <select id="filter-division" name="division">
                <option value="">All Divisions</option>
                <?php foreach ($divisions as $div): ?>
                    <option value="<?= e($div) ?>" <?= $filters['division'] === $div ? 'selected' : '' ?>>
                        <?= e($div) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field field-grow">
            <label for="filter-search">Search Match / Location</label>
            <input type="text" id="filter-search" name="search" value="<?= e($filters['search']) ?>" placeholder="e.g. Oakdale, Regional...">
        </div>
        <div class="filter-actions">
            <button type="submit" id="btn-apply-filters" class="btn btn-secondary">Filter</button>
            <a href="results.php" id="btn-reset-filters" class="btn btn-ghost">Reset</a>
        </div>
    </form>
</section>

<section class="panel">
    <div class="panel-header">
        <h2>Recorded Match Results (<?= count($results) ?>)</h2>
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
                    <th class="actions-col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($results)): ?>
                    <tr><td colspan="10" class="empty-cell">No match results match the current filters.</td></tr>
                <?php else: ?>
                    <?php foreach ($results as $r): ?>
                        <tr>
                            <td class="mono"><?= e($r['match_date']) ?></td>
                            <td>
                                <div class="match-title"><?= e($r['match_name']) ?></div>
                                <?php if (!empty($r['location'])): ?>
                                    <div class="match-loc"><?= e($r['location']) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($r['notes'])): ?>
                                    <div class="match-notes"><?= e($r['notes']) ?></div>
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
                            <td class="actions-col">
                                <div class="row-actions">
                                    <button
                                        type="button"
                                        class="btn btn-ghost btn-xs js-edit-result"
                                        data-result='<?= e(json_encode($r, JSON_UNESCAPED_UNICODE)) ?>'
                                    >Edit</button>
                                    <form method="post" action="results.php" class="inline-form" onsubmit="return confirm('Delete this match result?');">
                                        <input type="hidden" name="csrf_token" value="<?= e($auth->csrfToken()) ?>">
                                        <input type="hidden" name="action" value="delete_result">
                                        <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                        <button type="submit" class="btn btn-danger-ghost btn-xs">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- Add / Edit Event Time Modal -->
<div id="result-modal" class="modal <?= $openNewModal ? 'is-open' : '' ?>" role="dialog" aria-modal="true" aria-labelledby="result-modal-title">
    <div class="modal-backdrop" data-close-modal></div>
    <div class="modal-dialog">
        <div class="modal-header">
            <h2 id="result-modal-title"><?= $editingResult ? 'Edit Match Time' : 'Add Match Time' ?></h2>
            <button type="button" class="modal-close" data-close-modal aria-label="Close">&times;</button>
        </div>
        <form method="post" action="results.php" id="result-form" class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= e($auth->csrfToken()) ?>">
            <input type="hidden" name="action" value="save_result">
            <input type="hidden" name="id" id="result-id" value="<?= $editingResult ? (int)$editingResult['id'] : 0 ?>">

            <div class="form-grid">
                <?php if (!empty($savedMatches)): ?>
                    <div class="field field-span-2">
                        <label for="result-saved-match">Select Saved Match (optional quick-fill)</label>
                        <select id="result-saved-match">
                            <option value="">— Choose a match or type below —</option>
                            <?php foreach ($savedMatches as $sm): ?>
                                <option
                                    value="<?= (int)$sm['id'] ?>"
                                    data-name="<?= e($sm['match_name']) ?>"
                                    data-date="<?= e($sm['match_date']) ?>"
                                    data-location="<?= e($sm['location']) ?>"
                                >
                                    <?= e($sm['match_date']) ?> — <?= e($sm['match_name']) ?><?= !empty($sm['location']) ? ' (' . e($sm['location']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="field">
                    <label for="result-user">Shooter</label>
                    <select id="result-user" name="user_id" required>
                        <?php foreach ($users as $u): ?>
                            <?php
                            $selectedUid = $editingResult ? (int)$editingResult['user_id'] : (int)$user['id'];
                            ?>
                            <option
                                value="<?= (int)$u['id'] ?>"
                                data-division="<?= e($u['default_division'] ?? 'Amateur Civilian') ?>"
                                <?= $selectedUid === (int)$u['id'] ? 'selected' : '' ?>
                            >
                                <?= e($u['display_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="result-division">Division</label>
                    <select id="result-division" name="division" required>
                        <?php
                        $selectedDiv = $editingResult['division'] ?? ($user['default_division'] ?? 'Amateur Civilian');
                        foreach ($divisions as $div):
                        ?>
                            <option value="<?= e($div) ?>" <?= $selectedDiv === $div ? 'selected' : '' ?>>
                                <?= e($div) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field field-span-2">
                    <label for="result-match-name">Match Name</label>
                    <input type="text" id="result-match-name" name="match_name" required value="<?= e($editingResult['match_name'] ?? $prefillMatchName) ?>" placeholder="e.g. Spring Classic Regional">
                </div>

                <div class="field">
                    <label for="result-match-date">Match Date</label>
                    <input type="date" id="result-match-date" name="match_date" required value="<?= e($editingResult['match_date'] ?? ($prefillMatchDate !== '' ? $prefillMatchDate : date('Y-m-d'))) ?>">
                </div>

                <div class="field">
                    <label for="result-location">Location / Club</label>
                    <input type="text" id="result-location" name="location" value="<?= e($editingResult['location'] ?? $prefillLocation) ?>" placeholder="e.g. Oakdale Range">
                </div>
            </div>

            <fieldset class="stage-fieldset">
                <legend>GSSF Stage Times (seconds)</legend>
                <div class="stage-grid">
                    <div class="field">
                        <label for="stage-five">Five to Glock</label>
                        <input type="number" step="0.01" min="0" id="stage-five" name="five_to_glock" class="mono js-stage-input" required value="<?= $editingResult ? e(format_time((float)$editingResult['five_to_glock'])) : '' ?>" placeholder="0.00">
                    </div>
                    <div class="field">
                        <label for="stage-plates">Glock the Plates</label>
                        <input type="number" step="0.01" min="0" id="stage-plates" name="glock_the_plates" class="mono js-stage-input" required value="<?= $editingResult ? e(format_time((float)$editingResult['glock_the_plates'])) : '' ?>" placeholder="0.00">
                    </div>
                    <div class="field">
                        <label for="stage-glockm">Glock 'M</label>
                        <input type="number" step="0.01" min="0" id="stage-glockm" name="glock_m" class="mono js-stage-input" required value="<?= $editingResult ? e(format_time((float)$editingResult['glock_m'])) : '' ?>" placeholder="0.00">
                    </div>
                    <div class="field">
                        <label for="stage-penalties">Penalties (+s)</label>
                        <input type="number" step="0.01" min="0" id="stage-penalties" name="penalties" class="mono js-stage-input" value="<?= $editingResult ? e(format_time((float)$editingResult['penalties'])) : '0.00' ?>" placeholder="0.00">
                    </div>
                </div>
                <div class="total-preview">
                    <span>Calculated Total Time</span>
                    <strong id="stage-total-preview" class="mono"><?= $editingResult ? e(format_time((float)$editingResult['total_time'])) . 's' : '0.00s' ?></strong>
                </div>
            </fieldset>

            <div class="field">
                <label for="result-notes">Notes (gun, ammo, observations)</label>
                <textarea id="result-notes" name="notes" rows="2" placeholder="Optional notes..."><?= e($editingResult['notes'] ?? '') ?></textarea>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
                <button type="submit" id="btn-save-result" class="btn btn-primary">Save Event Time</button>
            </div>
        </form>
    </div>
</div>
<?php
render_footer();

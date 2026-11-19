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
            $matchId = (int)($_POST['match_id'] ?? 0);
            $division = trim((string)($_POST['division'] ?? 'Amateur Civilian'));
            $notes = trim((string)($_POST['notes'] ?? ''));

            $match = $matchId > 0 ? $storage->getMatchById($matchId) : null;

            $parseStrings = static function (mixed $raw, int $count): array {
                $arr = is_array($raw) ? $raw : [];
                $out = [];
                for ($i = 0; $i < $count; $i++) {
                    $out[] = round((float)($arr[$i] ?? 0), 2);
                }
                return $out;
            };

            $fiveStrings = $parseStrings($_POST['five_strings'] ?? [], 3);
            $fivePenalties = round((float)($_POST['five_penalties'] ?? 0), 2);

            $platesStrings = $parseStrings($_POST['plates_strings'] ?? [], 4);
            $platesPenalties = round((float)($_POST['plates_penalties'] ?? 0), 2);

            $glockmStrings = $parseStrings($_POST['glockm_strings'] ?? [], 3);
            $glockmPenalties = round((float)($_POST['glockm_penalties'] ?? 0), 2);

            $allNums = array_merge(
                $fiveStrings,
                [$fivePenalties],
                $platesStrings,
                [$platesPenalties],
                $glockmStrings,
                [$glockmPenalties]
            );
            $hasNegative = false;
            foreach ($allNums as $n) {
                if ($n < 0) {
                    $hasNegative = true;
                    break;
                }
            }

            if ($userId <= 0 || !$match) {
                $flashError = 'Please select both a shooter and an existing match.';
            } elseif ($hasNegative) {
                $flashError = 'String times and penalties cannot be negative.';
            } else {
                $fiveTotal = round(array_sum($fiveStrings) + $fivePenalties, 2);
                $platesTotal = round(array_sum($platesStrings) + $platesPenalties, 2);
                $glockmTotal = round(array_sum($glockmStrings) + $glockmPenalties, 2);
                $totalPenalties = round($fivePenalties + $platesPenalties + $glockmPenalties, 2);
                $matchTotal = round($fiveTotal + $platesTotal + $glockmTotal, 2);

                $stageDetails = json_encode([
                    'five_to_glock' => ['strings' => $fiveStrings, 'penalties' => $fivePenalties],
                    'glock_the_plates' => ['strings' => $platesStrings, 'penalties' => $platesPenalties],
                    'glock_m' => ['strings' => $glockmStrings, 'penalties' => $glockmPenalties],
                ], JSON_UNESCAPED_UNICODE);

                $payload = [
                    'user_id' => $userId,
                    'match_name' => $match['match_name'],
                    'match_date' => $match['match_date'],
                    'location' => $match['location'] ?? '',
                    'division' => $division,
                    'five_to_glock' => $fiveTotal,
                    'glock_the_plates' => $platesTotal,
                    'glock_m' => $glockmTotal,
                    'penalties' => $totalPenalties,
                    'total_time' => $matchTotal,
                    'stage_details' => $stageDetails,
                    'notes' => $notes,
                    'created_by' => (int)$user['id'],
                ];

                if ($id > 0) {
                    $storage->updateResult($id, $payload);
                    $flashSuccess = 'Match time updated.';
                } else {
                    $storage->createResult($payload);
                    $flashSuccess = 'New match time logged.';
                }
            }
        } elseif ($action === 'delete_result') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0 && $storage->deleteResult($id)) {
                $flashSuccess = 'Match time deleted.';
            } else {
                $flashError = 'Unable to delete match time.';
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
$prefillMatchId = (int)($_GET['match_id'] ?? 0);

// Resolve editing result's match_id and stage details if opened via ?edit=ID
$editStageDetails = null;
if ($editingResult) {
    if (!empty($editingResult['stage_details'])) {
        $decoded = json_decode((string)$editingResult['stage_details'], true);
        if (is_array($decoded)) {
            $editStageDetails = $decoded;
        }
    }
    foreach ($savedMatches as $sm) {
        if ($sm['match_name'] === $editingResult['match_name'] && $sm['match_date'] === $editingResult['match_date']) {
            $prefillMatchId = (int)$sm['id'];
            break;
        }
    }
}

$fiveEditStrings = $editStageDetails['five_to_glock']['strings'] ?? ($editingResult ? [(float)$editingResult['five_to_glock'], 0, 0] : [0, 0, 0]);
$fiveEditPen = $editStageDetails['five_to_glock']['penalties'] ?? 0;

$platesEditStrings = $editStageDetails['glock_the_plates']['strings'] ?? ($editingResult ? [(float)$editingResult['glock_the_plates'], 0, 0, 0] : [0, 0, 0, 0]);
$platesEditPen = $editStageDetails['glock_the_plates']['penalties'] ?? 0;

$glockmEditStrings = $editStageDetails['glock_m']['strings'] ?? ($editingResult ? [(float)$editingResult['glock_m'], 0, 0] : [0, 0, 0]);
$glockmEditPen = $editStageDetails['glock_m']['penalties'] ?? 0;

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
        <h2>Total Time by Shooter &amp; Division</h2>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Shooter</th>
                    <th>Division</th>
                    <th class="num">Matches</th>
                    <th class="num">Combined Total Time</th>
                    <th class="num">Best Match Total</th>
                    <th class="num">Average Match Total</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($divisionTotals)): ?>
                    <tr><td colspan="6" class="empty-cell">No match times match the current filters.</td></tr>
                <?php else: ?>
                    <?php foreach ($divisionTotals as $dt): ?>
                        <tr>
                            <td>
                                <span class="shooter-cell">
                                    <span class="color-dot" style="background-color: <?= e($dt['shooter_color'] ?? '#1d4ed8') ?>;"></span>
                                    <strong><?= e($dt['shooter_name']) ?></strong>
                                </span>
                            </td>
                            <td><span class="tag"><?= e($dt['division']) ?></span></td>
                            <td class="num mono"><?= (int)$dt['match_count'] ?></td>
                            <td class="num mono strong-time"><?= e(format_time((float)$dt['sum_total_time'])) ?>s</td>
                            <td class="num mono"><?= e(format_time((float)$dt['best_total_time'])) ?>s</td>
                            <td class="num mono"><?= e(format_time((float)$dt['avg_total_time'])) ?>s</td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <div class="panel-header">
        <h2>Recorded Match Times (<?= count($results) ?>)</h2>
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
                                    <form method="post" action="results.php" class="inline-form" onsubmit="return confirm('Delete this match time?');">
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

<!-- Add / Edit Match Time Modal -->
<div id="result-modal" class="modal <?= $openNewModal ? 'is-open' : '' ?>" role="dialog" aria-modal="true" aria-labelledby="result-modal-title">
    <div class="modal-backdrop" data-close-modal></div>
    <div class="modal-dialog modal-dialog-wide">
        <div class="modal-header">
            <h2 id="result-modal-title"><?= $editingResult ? 'Edit Match Time' : 'Add Match Time' ?></h2>
            <button type="button" class="modal-close" data-close-modal aria-label="Close">&times;</button>
        </div>
        <form method="post" action="results.php" id="result-form" class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= e($auth->csrfToken()) ?>">
            <input type="hidden" name="action" value="save_result">
            <input type="hidden" name="id" id="result-id" value="<?= $editingResult ? (int)$editingResult['id'] : 0 ?>">

            <div class="form-grid">
                <div class="field field-span-2">
                    <label for="result-match-id">Match</label>
                    <?php if (empty($savedMatches)): ?>
                        <div class="alert alert-error">No matches exist yet. Please <a href="matches.php">add a match</a> first.</div>
                    <?php else: ?>
                        <select id="result-match-id" name="match_id" required>
                            <option value="">— Select a match —</option>
                            <?php foreach ($savedMatches as $sm): ?>
                                <option
                                    value="<?= (int)$sm['id'] ?>"
                                    data-name="<?= e($sm['match_name']) ?>"
                                    data-date="<?= e($sm['match_date']) ?>"
                                    <?= $prefillMatchId === (int)$sm['id'] ? 'selected' : '' ?>
                                >
                                    <?= e($sm['match_date']) ?> — <?= e($sm['match_name']) ?><?= !empty($sm['location']) ? ' (' . e($sm['location']) . ')' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>

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
            </div>

            <!-- Stage 1: Five to Glock (3 strings + penalties) -->
            <fieldset class="stage-fieldset" data-stage="five">
                <legend>Five to Glock (seconds)</legend>
                <div class="stage-strings-grid stage-strings-4">
                    <div class="field">
                        <label for="five-s1">String 1</label>
                        <input type="number" step="0.01" min="0" id="five-s1" name="five_strings[]" class="mono js-stage-field js-five-string" required value="<?= $editingResult ? e(format_time((float)$fiveEditStrings[0])) : '' ?>" placeholder="0.00">
                    </div>
                    <div class="field">
                        <label for="five-s2">String 2</label>
                        <input type="number" step="0.01" min="0" id="five-s2" name="five_strings[]" class="mono js-stage-field js-five-string" required value="<?= $editingResult ? e(format_time((float)$fiveEditStrings[1])) : '' ?>" placeholder="0.00">
                    </div>
                    <div class="field">
                        <label for="five-s3">String 3</label>
                        <input type="number" step="0.01" min="0" id="five-s3" name="five_strings[]" class="mono js-stage-field js-five-string" required value="<?= $editingResult ? e(format_time((float)$fiveEditStrings[2])) : '' ?>" placeholder="0.00">
                    </div>
                    <div class="field">
                        <label for="five-pen">Penalties (+s)</label>
                        <input type="number" step="0.01" min="0" id="five-pen" name="five_penalties" class="mono js-stage-field" value="<?= $editingResult ? e(format_time((float)$fiveEditPen)) : '0.00' ?>" placeholder="0.00">
                    </div>
                </div>
                <div class="stage-subtotal">
                    <span>Five to Glock Total</span>
                    <strong id="stage-five-total" class="mono"><?= $editingResult ? e(format_time((float)$editingResult['five_to_glock'])) . 's' : '0.00s' ?></strong>
                </div>
            </fieldset>

            <!-- Stage 2: Glock the Plates (4 strings + penalties) -->
            <fieldset class="stage-fieldset" data-stage="plates">
                <legend>Glock the Plates (seconds)</legend>
                <div class="stage-strings-grid stage-strings-5">
                    <div class="field">
                        <label for="plates-s1">String 1</label>
                        <input type="number" step="0.01" min="0" id="plates-s1" name="plates_strings[]" class="mono js-stage-field js-plates-string" required value="<?= $editingResult ? e(format_time((float)$platesEditStrings[0])) : '' ?>" placeholder="0.00">
                    </div>
                    <div class="field">
                        <label for="plates-s2">String 2</label>
                        <input type="number" step="0.01" min="0" id="plates-s2" name="plates_strings[]" class="mono js-stage-field js-plates-string" required value="<?= $editingResult ? e(format_time((float)$platesEditStrings[1])) : '' ?>" placeholder="0.00">
                    </div>
                    <div class="field">
                        <label for="plates-s3">String 3</label>
                        <input type="number" step="0.01" min="0" id="plates-s3" name="plates_strings[]" class="mono js-stage-field js-plates-string" required value="<?= $editingResult ? e(format_time((float)$platesEditStrings[2])) : '' ?>" placeholder="0.00">
                    </div>
                    <div class="field">
                        <label for="plates-s4">String 4</label>
                        <input type="number" step="0.01" min="0" id="plates-s4" name="plates_strings[]" class="mono js-stage-field js-plates-string" required value="<?= $editingResult ? e(format_time((float)$platesEditStrings[3])) : '' ?>" placeholder="0.00">
                    </div>
                    <div class="field">
                        <label for="plates-pen">Penalties (+s)</label>
                        <input type="number" step="0.01" min="0" id="plates-pen" name="plates_penalties" class="mono js-stage-field" value="<?= $editingResult ? e(format_time((float)$platesEditPen)) : '0.00' ?>" placeholder="0.00">
                    </div>
                </div>
                <div class="stage-subtotal">
                    <span>Glock the Plates Total</span>
                    <strong id="stage-plates-total" class="mono"><?= $editingResult ? e(format_time((float)$editingResult['glock_the_plates'])) . 's' : '0.00s' ?></strong>
                </div>
            </fieldset>

            <!-- Stage 3: Glock 'M (3 strings + penalties) -->
            <fieldset class="stage-fieldset" data-stage="glockm">
                <legend>Glock 'M (seconds)</legend>
                <div class="stage-strings-grid stage-strings-4">
                    <div class="field">
                        <label for="glockm-s1">String 1</label>
                        <input type="number" step="0.01" min="0" id="glockm-s1" name="glockm_strings[]" class="mono js-stage-field js-glockm-string" required value="<?= $editingResult ? e(format_time((float)$glockmEditStrings[0])) : '' ?>" placeholder="0.00">
                    </div>
                    <div class="field">
                        <label for="glockm-s2">String 2</label>
                        <input type="number" step="0.01" min="0" id="glockm-s2" name="glockm_strings[]" class="mono js-stage-field js-glockm-string" required value="<?= $editingResult ? e(format_time((float)$glockmEditStrings[1])) : '' ?>" placeholder="0.00">
                    </div>
                    <div class="field">
                        <label for="glockm-s3">String 3</label>
                        <input type="number" step="0.01" min="0" id="glockm-s3" name="glockm_strings[]" class="mono js-stage-field js-glockm-string" required value="<?= $editingResult ? e(format_time((float)$glockmEditStrings[2])) : '' ?>" placeholder="0.00">
                    </div>
                    <div class="field">
                        <label for="glockm-pen">Penalties (+s)</label>
                        <input type="number" step="0.01" min="0" id="glockm-pen" name="glockm_penalties" class="mono js-stage-field" value="<?= $editingResult ? e(format_time((float)$glockmEditPen)) : '0.00' ?>" placeholder="0.00">
                    </div>
                </div>
                <div class="stage-subtotal">
                    <span>Glock 'M Total</span>
                    <strong id="stage-glockm-total" class="mono"><?= $editingResult ? e(format_time((float)$editingResult['glock_m'])) . 's' : '0.00s' ?></strong>
                </div>
            </fieldset>

            <div class="total-preview match-total-bar">
                <span>Calculated Match Total</span>
                <strong id="stage-total-preview" class="mono"><?= $editingResult ? e(format_time((float)$editingResult['total_time'])) . 's' : '0.00s' ?></strong>
            </div>

            <div class="field">
                <label for="result-notes">Notes (gun, ammo, observations)</label>
                <textarea id="result-notes" name="notes" rows="2" placeholder="Optional notes..."><?= e($editingResult['notes'] ?? '') ?></textarea>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
                <button type="submit" id="btn-save-result" class="btn btn-primary" <?= empty($savedMatches) ? 'disabled' : '' ?>>Save Match Time</button>
            </div>
        </form>
    </div>
</div>
<?php
render_footer();

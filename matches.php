<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$user = $auth->requireLogin();
$users = $storage->getUsers(true);

$flashSuccess = '';
$flashError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->verifyCsrf($_POST['csrf_token'] ?? null)) {
        $flashError = 'Invalid security token. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'save_match') {
            $id = (int)($_POST['id'] ?? 0);
            $matchName = trim((string)($_POST['match_name'] ?? ''));
            $matchDate = trim((string)($_POST['match_date'] ?? ''));
            $location = trim((string)($_POST['location'] ?? ''));
            $shooterIds = is_array($_POST['shooter_ids'] ?? null) ? $_POST['shooter_ids'] : [];

            if ($matchName === '' || $matchDate === '') {
                $flashError = 'Match name and match date are required.';
            } else {
                $payload = [
                    'match_name' => $matchName,
                    'match_date' => $matchDate,
                    'location' => $location,
                    'shooter_ids' => $shooterIds,
                ];
                if ($id > 0) {
                    $storage->updateMatch($id, $payload);
                    $flashSuccess = 'Match updated.';
                } else {
                    $storage->createMatch($payload);
                    $flashSuccess = 'New match added.';
                }
            }
        } elseif ($action === 'delete_match') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0 && $storage->deleteMatch($id)) {
                $flashSuccess = 'Match deleted.';
            } else {
                $flashError = 'Unable to delete match.';
            }
        }
    }
}

$matches = $storage->getMatches();

render_header('Matches', 'matches', $user, $auth);
?>
<section class="page-head">
    <div>
        <h1>Matches</h1>
        <p class="page-subtitle">Schedule and manage GSSF matches, locations, and participating family shooters.</p>
    </div>
    <div class="page-actions">
        <button type="button" id="btn-open-match-modal" class="btn btn-primary" data-open-modal="match-modal">+ Add Match</button>
    </div>
</section>

<?php if ($flashSuccess !== ''): ?>
    <div class="alert alert-success" role="status"><?= e($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError !== ''): ?>
    <div class="alert alert-error" role="alert"><?= e($flashError) ?></div>
<?php endif; ?>

<section class="panel">
    <div class="panel-header">
        <h2>All Matches (<?= count($matches) ?>)</h2>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Match Name</th>
                    <th>Location</th>
                    <th>Shooters</th>
                    <th class="actions-col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($matches)): ?>
                    <tr><td colspan="5" class="empty-cell">No matches added yet. Click "+ Add Match" to create one.</td></tr>
                <?php else: ?>
                    <?php foreach ($matches as $m): ?>
                        <?php
                        $logUrl = 'results.php?new=1&match_id=' . (int)$m['id'];
                        ?>
                        <tr>
                            <td class="mono"><?= e($m['match_date']) ?></td>
                            <td><strong><?= e($m['match_name']) ?></strong></td>
                            <td><?= !empty($m['location']) ? e($m['location']) : '<span class="muted-note">—</span>' ?></td>
                            <td>
                                <?php if (empty($m['shooters'])): ?>
                                    <span class="muted-note">No shooters assigned</span>
                                <?php else: ?>
                                    <div class="shooter-chips">
                                        <?php foreach ($m['shooters'] as $sh): ?>
                                            <span class="tag">
                                                <span class="color-dot" style="background-color: <?= e($sh['color']) ?>;"></span>
                                                <?= e($sh['display_name']) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="actions-col">
                                <div class="row-actions">
                                    <a href="<?= e($logUrl) ?>" class="btn btn-secondary btn-xs">+ Log Time</a>
                                    <button
                                        type="button"
                                        class="btn btn-ghost btn-xs js-edit-match"
                                        data-match='<?= e(json_encode($m, JSON_UNESCAPED_UNICODE)) ?>'
                                    >Edit</button>
                                    <form method="post" action="matches.php" class="inline-form" onsubmit="return confirm('Delete this match and all associated match times?');">
                                        <input type="hidden" name="csrf_token" value="<?= e($auth->csrfToken()) ?>">
                                        <input type="hidden" name="action" value="delete_match">
                                        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
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

<!-- Add / Edit Match Modal -->
<div id="match-modal" class="modal" role="dialog" aria-modal="true" aria-labelledby="match-modal-title">
    <div class="modal-backdrop" data-close-modal></div>
    <div class="modal-dialog">
        <div class="modal-header">
            <h2 id="match-modal-title">Add Match</h2>
            <button type="button" class="modal-close" data-close-modal aria-label="Close">&times;</button>
        </div>
        <form method="post" action="matches.php" id="match-form" class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= e($auth->csrfToken()) ?>">
            <input type="hidden" name="action" value="save_match">
            <input type="hidden" name="id" id="match-id" value="0">

            <div class="form-grid">
                <div class="field field-span-2">
                    <label for="match-name">Match Name</label>
                    <input type="text" id="match-name" name="match_name" required placeholder="e.g. Spring Classic Regional">
                </div>

                <div class="field">
                    <label for="match-date">Match Date</label>
                    <input type="date" id="match-date" name="match_date" required value="<?= e(date('Y-m-d')) ?>">
                </div>

                <div class="field">
                    <label for="match-location">Match Location</label>
                    <input type="text" id="match-location" name="location" placeholder="e.g. Oakdale Range">
                </div>

                <div class="field field-span-2">
                    <label>Shooters</label>
                    <div class="shooter-chips" id="match-shooters-group">
                        <?php foreach ($users as $u): ?>
                            <label class="shooter-chip">
                                <input type="checkbox" name="shooter_ids[]" class="js-match-shooter-cb" value="<?= (int)$u['id'] ?>">
                                <span class="color-dot" style="background-color: <?= e($u['color'] ?? '#1d4ed8') ?>;"></span>
                                <span><?= e($u['display_name']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
                <button type="submit" id="btn-save-match" class="btn btn-primary">Save Match</button>
            </div>
        </form>
    </div>
</div>
<?php
render_footer();

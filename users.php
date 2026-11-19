<?php
declare(strict_types=1);

require_once __DIR__ . '/src/bootstrap.php';

$user = $auth->requireLogin();
$isAdmin = $auth->isAdmin();
$divisions = gssf_divisions();
$palette = shooter_palette();

$flashSuccess = '';
$flashError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$auth->verifyCsrf($_POST['csrf_token'] ?? null)) {
        $flashError = 'Invalid security token. Please refresh and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'save_user') {
            $id = (int)($_POST['id'] ?? 0);
            $canEdit = $isAdmin || ($id > 0 && $id === (int)$user['id']);

            if (!$canEdit) {
                $flashError = 'Only administrators can add or edit other family accounts.';
            } else {
                $username = strtolower(trim((string)($_POST['username'] ?? '')));
                $displayName = trim((string)($_POST['display_name'] ?? ''));
                $password = (string)($_POST['password'] ?? '');
                $role = $isAdmin ? ((($_POST['role'] ?? 'member') === 'admin') ? 'admin' : 'member') : ($user['role'] ?? 'member');
                $defaultDiv = trim((string)($_POST['default_division'] ?? 'Amateur Civilian'));
                $color = trim((string)($_POST['color'] ?? '#1d4ed8'));
                $active = $isAdmin ? (isset($_POST['active']) ? 1 : 0) : 1;

                if ($username === '' || $displayName === '') {
                    $flashError = 'Username and display name are required.';
                } elseif (!preg_match('/^[a-z0-9_.-]{2,32}$/', $username)) {
                    $flashError = 'Username must be 2–32 characters (letters, numbers, _, ., -).';
                } elseif ($id === 0 && strlen($password) < 4) {
                    $flashError = 'Password must be at least 4 characters for a new user.';
                } elseif ($id > 0 && $password !== '' && strlen($password) < 4) {
                    $flashError = 'New password must be at least 4 characters.';
                } else {
                    $existing = $storage->getUserByUsername($username);
                    if ($existing && (int)$existing['id'] !== $id) {
                        $flashError = 'That username is already taken.';
                    } else {
                        $payload = [
                            'username' => $username,
                            'display_name' => $displayName,
                            'password' => $password,
                            'role' => $role,
                            'default_division' => $defaultDiv,
                            'color' => $color,
                            'active' => $active,
                        ];
                        if ($id > 0) {
                            $storage->updateUser($id, $payload);
                            $flashSuccess = 'Shooter profile updated.';
                        } else {
                            $storage->createUser($payload);
                            $flashSuccess = 'New family shooter added.';
                        }
                    }
                }
            }
        } elseif ($action === 'delete_user') {
            $id = (int)($_POST['id'] ?? 0);
            if (!$isAdmin) {
                $flashError = 'Only administrators can delete users.';
            } elseif ($id === (int)$user['id']) {
                $flashError = 'You cannot delete your own signed-in account.';
            } elseif ($id > 0 && $storage->deleteUser($id)) {
                $flashSuccess = 'Shooter account and their match entries were deleted.';
            } else {
                $flashError = 'Unable to delete shooter.';
            }
        }
    }
}

$users = $storage->getUsers(false);
$allResults = $storage->getResults();
$countsByUser = [];
foreach ($allResults as $r) {
    $uid = (int)$r['user_id'];
    $countsByUser[$uid] = ($countsByUser[$uid] ?? 0) + 1;
}

render_header('Family Shooters', 'users', $user, $auth);
?>
<section class="page-head">
    <div>
        <h1>Family Shooters</h1>
        <p class="page-subtitle">Manage family member logins, default divisions, and graph colors.</p>
    </div>
    <?php if ($isAdmin): ?>
        <div class="page-actions">
            <button type="button" id="btn-open-user-modal" class="btn btn-primary" data-open-modal="user-modal">+ Add Shooter</button>
        </div>
    <?php endif; ?>
</section>

<?php if ($flashSuccess !== ''): ?>
    <div class="alert alert-success" role="status"><?= e($flashSuccess) ?></div>
<?php endif; ?>
<?php if ($flashError !== ''): ?>
    <div class="alert alert-error" role="alert"><?= e($flashError) ?></div>
<?php endif; ?>

<section class="panel">
    <div class="panel-header">
        <h2>Registered Shooters (<?= count($users) ?>)</h2>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Shooter</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Default Division</th>
                    <th class="num">Matches</th>
                    <th>Status</th>
                    <th class="actions-col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <?php
                    $uid = (int)$u['id'];
                    $canEditRow = $isAdmin || $uid === (int)$user['id'];
                    $safeUserJson = [
                        'id' => $uid,
                        'username' => $u['username'],
                        'display_name' => $u['display_name'],
                        'role' => $u['role'],
                        'default_division' => $u['default_division'],
                        'color' => $u['color'],
                        'active' => (int)$u['active'],
                    ];
                    ?>
                    <tr>
                        <td>
                            <span class="shooter-cell">
                                <span class="color-dot" style="background-color: <?= e($u['color'] ?? '#1d4ed8') ?>;"></span>
                                <strong><?= e($u['display_name']) ?></strong>
                            </span>
                        </td>
                        <td class="mono"><?= e($u['username']) ?></td>
                        <td><span class="tag <?= ($u['role'] ?? 'member') === 'admin' ? 'tag-accent' : '' ?>"><?= e(ucfirst($u['role'] ?? 'member')) ?></span></td>
                        <td><?= e($u['default_division'] ?? 'Amateur Civilian') ?></td>
                        <td class="num mono"><?= $countsByUser[$uid] ?? 0 ?></td>
                        <td>
                            <?php if (!empty($u['active'])): ?>
                                <span class="status-pill status-active">Active</span>
                            <?php else: ?>
                                <span class="status-pill status-inactive">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="actions-col">
                            <div class="row-actions">
                                <?php if ($canEditRow): ?>
                                    <button
                                        type="button"
                                        class="btn btn-ghost btn-xs js-edit-user"
                                        data-user='<?= e(json_encode($safeUserJson, JSON_UNESCAPED_UNICODE)) ?>'
                                    >Edit</button>
                                <?php endif; ?>
                                <?php if ($isAdmin && $uid !== (int)$user['id']): ?>
                                    <form method="post" action="users.php" class="inline-form" onsubmit="return confirm('Delete this shooter and all of their match results?');">
                                        <input type="hidden" name="csrf_token" value="<?= e($auth->csrfToken()) ?>">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="id" value="<?= $uid ?>">
                                        <button type="submit" class="btn btn-danger-ghost btn-xs">Delete</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- Add / Edit User Modal -->
<div id="user-modal" class="modal" role="dialog" aria-modal="true" aria-labelledby="user-modal-title">
    <div class="modal-backdrop" data-close-modal></div>
    <div class="modal-dialog">
        <div class="modal-header">
            <h2 id="user-modal-title">Add Family Shooter</h2>
            <button type="button" class="modal-close" data-close-modal aria-label="Close">&times;</button>
        </div>
        <form method="post" action="users.php" id="user-form" class="modal-body">
            <input type="hidden" name="csrf_token" value="<?= e($auth->csrfToken()) ?>">
            <input type="hidden" name="action" value="save_user">
            <input type="hidden" name="id" id="user-id" value="0">

            <div class="form-grid">
                <div class="field">
                    <label for="user-display-name">Display Name</label>
                    <input type="text" id="user-display-name" name="display_name" required placeholder="e.g. Sam">
                </div>

                <div class="field">
                    <label for="user-username">Username</label>
                    <input type="text" id="user-username" name="username" required placeholder="e.g. sam">
                </div>

                <div class="field">
                    <label for="user-division">Default Division</label>
                    <select id="user-division" name="default_division">
                        <?php foreach ($divisions as $div): ?>
                            <option value="<?= e($div) ?>"><?= e($div) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="user-color">Graph Color</label>
                    <select id="user-color" name="color">
                        <?php foreach ($palette as $hex): ?>
                            <option value="<?= e($hex) ?>"><?= e($hex) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if ($isAdmin): ?>
                    <div class="field">
                        <label for="user-role">Role</label>
                        <select id="user-role" name="role">
                            <option value="member">Member (Shooter)</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <div class="field field-checkbox">
                        <label class="checkbox-label">
                            <input type="checkbox" id="user-active" name="active" value="1" checked>
                            <span>Account Active</span>
                        </label>
                    </div>
                <?php endif; ?>

                <div class="field field-span-2">
                    <label for="user-password">Password <span id="user-password-hint" class="muted-note">(required for new user)</span></label>
                    <input type="password" id="user-password" name="password" autocomplete="new-password" placeholder="Enter password">
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-close-modal>Cancel</button>
                <button type="submit" id="btn-save-user" class="btn btn-primary">Save Shooter</button>
            </div>
        </form>
    </div>
</div>

<section class="panel">
    <div class="panel-header">
        <h2>My Passkeys (<?= e($user['display_name']) ?>)</h2>
        <span class="muted-note">Passwordless sign-in via Touch ID, Face ID, or security key (requires HTTPS or localhost)</span>
    </div>
    <div class="filter-panel">
        <div id="passkey-reg-status" style="display:none;" role="alert"></div>
        <div class="filter-bar">
            <div class="field field-grow">
                <label for="passkey-label">Device / Key Label</label>
                <input type="text" id="passkey-label" placeholder="e.g. MacBook Touch ID, iPhone, YubiKey">
            </div>
            <div class="filter-actions">
                <button type="button" id="btn-register-passkey" class="btn btn-primary">+ Register Passkey</button>
            </div>
        </div>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Label</th>
                    <th>Created</th>
                    <th>Last Used</th>
                    <th class="actions-col">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($myPasskeys)): ?>
                    <tr><td colspan="4" class="empty-cell">No passkeys registered for your account yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($myPasskeys as $pk): ?>
                        <tr>
                            <td><strong><?= e($pk['label']) ?></strong></td>
                            <td class="mono"><?= e($pk['created_at']) ?></td>
                            <td class="mono"><?= !empty($pk['last_used_at']) ? e($pk['last_used_at']) : 'Never' ?></td>
                            <td class="actions-col">
                                <button type="button" class="btn btn-danger-ghost btn-xs js-delete-passkey" data-id="<?= (int)$pk['id'] ?>">Remove</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php
render_footer(['assets/js/passkeys.js']);

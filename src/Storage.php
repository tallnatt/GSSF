<?php
declare(strict_types=1);

class Storage
{
    private string $driver;
    private string $dataDir;
    private ?PDO $pdo = null;

    public function __construct(array $config)
    {
        $this->driver = $config['storage_driver'] ?? 'json';
        $this->dataDir = $config['data_dir'] ?? (__DIR__ . '/../data');

        if ($this->driver === 'mysql') {
            $db = $config['mysql'];
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $db['host'],
                (int)$db['port'],
                $db['database'],
                $db['charset'] ?? 'utf8mb4'
            );
            $this->pdo = new PDO($dsn, $db['username'], $db['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } else {
            if (!is_dir($this->dataDir)) {
                mkdir($this->dataDir, 0755, true);
            }
        }

        $this->seedIfEmpty();
    }

    // -------------------------------------------------------------------------
    // JSON flat-file helpers
    // -------------------------------------------------------------------------

    private function jsonPath(string $name): string
    {
        return $this->dataDir . '/' . $name . '.json';
    }

    private function readJson(string $name): array
    {
        $path = $this->jsonPath($name);
        if (!file_exists($path)) {
            return [];
        }
        $fp = fopen($path, 'rb');
        if (!$fp) {
            return [];
        }
        flock($fp, LOCK_SH);
        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        $decoded = json_decode($content ?: '[]', true);
        return is_array($decoded) ? $decoded : [];
    }

    private function writeJson(string $name, array $data): void
    {
        $path = $this->jsonPath($name);
        $fp = fopen($path, 'c+b');
        if (!$fp) {
            throw new RuntimeException("Unable to write storage file: $path");
        }
        flock($fp, LOCK_EX);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    private function nextId(array $rows): int
    {
        $max = 0;
        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            if ($id > $max) {
                $max = $id;
            }
        }
        return $max + 1;
    }

    // -------------------------------------------------------------------------
    // Users
    // -------------------------------------------------------------------------

    public function getUsers(bool $onlyActive = false): array
    {
        if ($this->driver === 'mysql') {
            $sql = 'SELECT * FROM users';
            if ($onlyActive) {
                $sql .= ' WHERE active = 1';
            }
            $sql .= ' ORDER BY display_name ASC';
            return $this->pdo->query($sql)->fetchAll();
        }

        $users = $this->readJson('users');
        if ($onlyActive) {
            $users = array_values(array_filter($users, fn($u) => !empty($u['active'])));
        }
        usort($users, fn($a, $b) => strcasecmp($a['display_name'], $b['display_name']));
        return $users;
    }

    public function getUserById(int $id): ?array
    {
        if ($this->driver === 'mysql') {
            $stmt = $this->pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            return $row ?: null;
        }

        foreach ($this->readJson('users') as $user) {
            if ((int)$user['id'] === $id) {
                return $user;
            }
        }
        return null;
    }

    public function getUserByUsername(string $username): ?array
    {
        $username = strtolower(trim($username));
        if ($this->driver === 'mysql') {
            $stmt = $this->pdo->prepare('SELECT * FROM users WHERE LOWER(username) = ? LIMIT 1');
            $stmt->execute([$username]);
            $row = $stmt->fetch();
            return $row ?: null;
        }

        foreach ($this->readJson('users') as $user) {
            if (strtolower($user['username']) === $username) {
                return $user;
            }
        }
        return null;
    }

    public function createUser(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        if ($this->driver === 'mysql') {
            $stmt = $this->pdo->prepare(
                'INSERT INTO users (username, display_name, password_hash, role, default_division, color, active, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                strtolower(trim($data['username'])),
                trim($data['display_name']),
                password_hash($data['password'], PASSWORD_DEFAULT),
                $data['role'] === 'admin' ? 'admin' : 'member',
                $data['default_division'] ?? 'Amateur Civilian',
                $data['color'] ?? '#1d4ed8',
                isset($data['active']) ? (int)$data['active'] : 1,
                $now,
            ]);
            return (int)$this->pdo->lastInsertId();
        }

        $users = $this->readJson('users');
        $id = $this->nextId($users);
        $users[] = [
            'id' => $id,
            'username' => strtolower(trim($data['username'])),
            'display_name' => trim($data['display_name']),
            'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
            'role' => ($data['role'] ?? 'member') === 'admin' ? 'admin' : 'member',
            'default_division' => $data['default_division'] ?? 'Amateur Civilian',
            'color' => $data['color'] ?? '#1d4ed8',
            'active' => isset($data['active']) ? (int)$data['active'] : 1,
            'created_at' => $now,
        ];
        $this->writeJson('users', $users);
        return $id;
    }

    public function updateUser(int $id, array $data): bool
    {
        if ($this->driver === 'mysql') {
            $fields = [
                'username = ?',
                'display_name = ?',
                'role = ?',
                'default_division = ?',
                'color = ?',
                'active = ?',
            ];
            $params = [
                strtolower(trim($data['username'])),
                trim($data['display_name']),
                $data['role'] === 'admin' ? 'admin' : 'member',
                $data['default_division'] ?? 'Amateur Civilian',
                $data['color'] ?? '#1d4ed8',
                isset($data['active']) ? (int)$data['active'] : 1,
            ];

            if (!empty($data['password'])) {
                $fields[] = 'password_hash = ?';
                $params[] = password_hash($data['password'], PASSWORD_DEFAULT);
            }
            $params[] = $id;

            $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?';
            return $this->pdo->prepare($sql)->execute($params);
        }

        $users = $this->readJson('users');
        $updated = false;
        foreach ($users as &$user) {
            if ((int)$user['id'] === $id) {
                $user['username'] = strtolower(trim($data['username']));
                $user['display_name'] = trim($data['display_name']);
                $user['role'] = ($data['role'] ?? 'member') === 'admin' ? 'admin' : 'member';
                $user['default_division'] = $data['default_division'] ?? 'Amateur Civilian';
                $user['color'] = $data['color'] ?? '#1d4ed8';
                $user['active'] = isset($data['active']) ? (int)$data['active'] : 1;
                if (!empty($data['password'])) {
                    $user['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
                }
                $updated = true;
                break;
            }
        }
        if ($updated) {
            $this->writeJson('users', $users);
        }
        return $updated;
    }

    public function deleteUser(int $id): bool
    {
        if ($this->driver === 'mysql') {
            $stmt = $this->pdo->prepare('DELETE FROM users WHERE id = ?');
            return $stmt->execute([$id]);
        }

        $users = $this->readJson('users');
        $filtered = array_values(array_filter($users, fn($u) => (int)$u['id'] !== $id));
        if (count($filtered) === count($users)) {
            return false;
        }
        $this->writeJson('users', $filtered);

        // Cascade delete results for that user
        $results = $this->readJson('results');
        $filteredResults = array_values(array_filter($results, fn($r) => (int)$r['user_id'] !== $id));
        $this->writeJson('results', $filteredResults);

        return true;
    }

    // -------------------------------------------------------------------------
    // Match Results
    // -------------------------------------------------------------------------

    public function getResults(array $filters = []): array
    {
        if ($this->driver === 'mysql') {
            $where = [];
            $params = [];

            if (!empty($filters['user_id'])) {
                $where[] = 'r.user_id = ?';
                $params[] = (int)$filters['user_id'];
            }
            if (!empty($filters['division'])) {
                $where[] = 'r.division = ?';
                $params[] = $filters['division'];
            }
            if (!empty($filters['date_from'])) {
                $where[] = 'r.match_date >= ?';
                $params[] = $filters['date_from'];
            }
            if (!empty($filters['date_to'])) {
                $where[] = 'r.match_date <= ?';
                $params[] = $filters['date_to'];
            }
            if (!empty($filters['search'])) {
                $where[] = '(r.match_name LIKE ? OR r.location LIKE ? OR r.notes LIKE ?)';
                $q = '%' . $filters['search'] . '%';
                $params[] = $q;
                $params[] = $q;
                $params[] = $q;
            }

            $sql = 'SELECT r.*, u.display_name AS shooter_name, u.color AS shooter_color
                    FROM match_results r
                    JOIN users u ON u.id = r.user_id';
            if ($where) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            $sql .= ' ORDER BY r.match_date DESC, r.total_time ASC';

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        }

        $usersById = [];
        foreach ($this->readJson('users') as $u) {
            $usersById[(int)$u['id']] = $u;
        }

        $results = $this->readJson('results');
        $out = [];
        foreach ($results as $r) {
            $uid = (int)$r['user_id'];
            if (!isset($usersById[$uid])) {
                continue;
            }
            if (!empty($filters['user_id']) && $uid !== (int)$filters['user_id']) {
                continue;
            }
            if (!empty($filters['division']) && $r['division'] !== $filters['division']) {
                continue;
            }
            if (!empty($filters['date_from']) && $r['match_date'] < $filters['date_from']) {
                continue;
            }
            if (!empty($filters['date_to']) && $r['match_date'] > $filters['date_to']) {
                continue;
            }
            if (!empty($filters['search'])) {
                $haystack = strtolower(($r['match_name'] ?? '') . ' ' . ($r['location'] ?? '') . ' ' . ($r['notes'] ?? ''));
                if (!str_contains($haystack, strtolower($filters['search']))) {
                    continue;
                }
            }

            $r['shooter_name'] = $usersById[$uid]['display_name'];
            $r['shooter_color'] = $usersById[$uid]['color'] ?? '#1d4ed8';
            $out[] = $r;
        }

        usort($out, function ($a, $b) {
            $dateCmp = strcmp($b['match_date'], $a['match_date']);
            if ($dateCmp !== 0) {
                return $dateCmp;
            }
            return ((float)$a['total_time']) <=> ((float)$b['total_time']);
        });

        return $out;
    }

    public function getResultById(int $id): ?array
    {
        if ($this->driver === 'mysql') {
            $stmt = $this->pdo->prepare(
                'SELECT r.*, u.display_name AS shooter_name, u.color AS shooter_color
                 FROM match_results r
                 JOIN users u ON u.id = r.user_id
                 WHERE r.id = ? LIMIT 1'
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch();
            return $row ?: null;
        }

        $usersById = [];
        foreach ($this->readJson('users') as $u) {
            $usersById[(int)$u['id']] = $u;
        }
        foreach ($this->readJson('results') as $r) {
            if ((int)$r['id'] === $id) {
                $uid = (int)$r['user_id'];
                $r['shooter_name'] = $usersById[$uid]['display_name'] ?? 'Unknown';
                $r['shooter_color'] = $usersById[$uid]['color'] ?? '#1d4ed8';
                return $r;
            }
        }
        return null;
    }

    public function createResult(array $data): int
    {
        $five = round((float)$data['five_to_glock'], 2);
        $plates = round((float)$data['glock_the_plates'], 2);
        $glockm = round((float)$data['glock_m'], 2);
        $penalties = round((float)($data['penalties'] ?? 0), 2);
        $total = round($five + $plates + $glockm + $penalties, 2);
        $now = date('Y-m-d H:i:s');

        if ($this->driver === 'mysql') {
            $stmt = $this->pdo->prepare(
                'INSERT INTO match_results
                 (user_id, match_name, match_date, location, division, five_to_glock, glock_the_plates, glock_m, penalties, total_time, notes, created_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                (int)$data['user_id'],
                trim($data['match_name']),
                $data['match_date'],
                trim($data['location'] ?? ''),
                trim($data['division'] ?? 'Amateur Civilian'),
                $five,
                $plates,
                $glockm,
                $penalties,
                $total,
                trim($data['notes'] ?? ''),
                isset($data['created_by']) ? (int)$data['created_by'] : null,
                $now,
                $now,
            ]);
            return (int)$this->pdo->lastInsertId();
        }

        $results = $this->readJson('results');
        $id = $this->nextId($results);
        $results[] = [
            'id' => $id,
            'user_id' => (int)$data['user_id'],
            'match_name' => trim($data['match_name']),
            'match_date' => $data['match_date'],
            'location' => trim($data['location'] ?? ''),
            'division' => trim($data['division'] ?? 'Amateur Civilian'),
            'five_to_glock' => $five,
            'glock_the_plates' => $plates,
            'glock_m' => $glockm,
            'penalties' => $penalties,
            'total_time' => $total,
            'notes' => trim($data['notes'] ?? ''),
            'created_by' => isset($data['created_by']) ? (int)$data['created_by'] : null,
            'created_at' => $now,
            'updated_at' => $now,
        ];
        $this->writeJson('results', $results);
        return $id;
    }

    public function updateResult(int $id, array $data): bool
    {
        $five = round((float)$data['five_to_glock'], 2);
        $plates = round((float)$data['glock_the_plates'], 2);
        $glockm = round((float)$data['glock_m'], 2);
        $penalties = round((float)($data['penalties'] ?? 0), 2);
        $total = round($five + $plates + $glockm + $penalties, 2);
        $now = date('Y-m-d H:i:s');

        if ($this->driver === 'mysql') {
            $stmt = $this->pdo->prepare(
                'UPDATE match_results SET
                 user_id = ?, match_name = ?, match_date = ?, location = ?, division = ?,
                 five_to_glock = ?, glock_the_plates = ?, glock_m = ?, penalties = ?, total_time = ?,
                 notes = ?, updated_at = ?
                 WHERE id = ?'
            );
            return $stmt->execute([
                (int)$data['user_id'],
                trim($data['match_name']),
                $data['match_date'],
                trim($data['location'] ?? ''),
                trim($data['division'] ?? 'Amateur Civilian'),
                $five,
                $plates,
                $glockm,
                $penalties,
                $total,
                trim($data['notes'] ?? ''),
                $now,
                $id,
            ]);
        }

        $results = $this->readJson('results');
        $updated = false;
        foreach ($results as &$r) {
            if ((int)$r['id'] === $id) {
                $r['user_id'] = (int)$data['user_id'];
                $r['match_name'] = trim($data['match_name']);
                $r['match_date'] = $data['match_date'];
                $r['location'] = trim($data['location'] ?? '');
                $r['division'] = trim($data['division'] ?? 'Amateur Civilian');
                $r['five_to_glock'] = $five;
                $r['glock_the_plates'] = $plates;
                $r['glock_m'] = $glockm;
                $r['penalties'] = $penalties;
                $r['total_time'] = $total;
                $r['notes'] = trim($data['notes'] ?? '');
                $r['updated_at'] = $now;
                $updated = true;
                break;
            }
        }
        if ($updated) {
            $this->writeJson('results', $results);
        }
        return $updated;
    }

    public function deleteResult(int $id): bool
    {
        if ($this->driver === 'mysql') {
            $stmt = $this->pdo->prepare('DELETE FROM match_results WHERE id = ?');
            return $stmt->execute([$id]);
        }

        $results = $this->readJson('results');
        $filtered = array_values(array_filter($results, fn($r) => (int)$r['id'] !== $id));
        if (count($filtered) === count($results)) {
            return false;
        }
        $this->writeJson('results', $filtered);
        return true;
    }

    // -------------------------------------------------------------------------
    // Initial seed for first-run experience
    // -------------------------------------------------------------------------

    private function seedIfEmpty(): void
    {
        $existingUsers = $this->getUsers();
        if (!empty($existingUsers)) {
            return;
        }

        $adminId = $this->createUser([
            'username' => 'admin',
            'display_name' => 'Family Admin',
            'password' => 'gssf2026',
            'role' => 'admin',
            'default_division' => 'Amateur Civilian',
            'color' => '#1d4ed8',
            'active' => 1,
        ]);

        $alexId = $this->createUser([
            'username' => 'alex',
            'display_name' => 'Alex',
            'password' => 'family2026',
            'role' => 'member',
            'default_division' => 'Competition',
            'color' => '#b45309',
            'active' => 1,
        ]);

        $jordanId = $this->createUser([
            'username' => 'jordan',
            'display_name' => 'Jordan',
            'password' => 'family2026',
            'role' => 'member',
            'default_division' => 'Amateur Civilian',
            'color' => '#0f766e',
            'active' => 1,
        ]);

        $sampleMatches = [
            // Match 1
            [$adminId, 'Spring Classic Regional', '2026-03-14', 'Oakdale Range', 'Amateur Civilian', 26.40, 21.85, 25.10, 0.00, 'G19 Gen5 - clean plates'],
            [$alexId, 'Spring Classic Regional', '2026-03-14', 'Oakdale Range', 'Competition', 23.90, 19.40, 22.80, 0.00, 'G34 MOS - fast transitions'],
            [$jordanId, 'Spring Classic Regional', '2026-03-14', 'Oakdale Range', 'Amateur Civilian', 31.20, 26.50, 29.90, 2.00, '1 charlie-mike'],
            // Match 2
            [$adminId, 'Midwest Glock Challenge', '2026-05-09', 'Tri-County Club', 'Amateur Civilian', 25.15, 20.10, 24.30, 0.00, 'Personal best on Plates'],
            [$alexId, 'Midwest Glock Challenge', '2026-05-09', 'Tri-County Club', 'Competition', 22.80, 18.95, 21.90, 0.00, 'Consistent strings'],
            [$jordanId, 'Midwest Glock Challenge', '2026-05-09', 'Tri-County Club', 'Amateur Civilian', 28.90, 24.10, 27.60, 0.00, 'Zero penalties!'],
            // Match 3
            [$adminId, 'Summer Heat Outdoor', '2026-07-18', 'Riverbend Practical', 'Amateur Civilian', 24.50, 19.60, 23.40, 0.00, 'Steady progress'],
            [$alexId, 'Summer Heat Outdoor', '2026-07-18', 'Riverbend Practical', 'Competition', 21.95, 17.80, 20.75, 0.00, 'Sub-61s total'],
            [$jordanId, 'Summer Heat Outdoor', '2026-07-18', 'Riverbend Practical', 'Amateur Civilian', 26.75, 22.30, 25.40, 0.00, 'Big jump on Five to Glock'],
            // Match 4
            [$adminId, 'Autumn Ballistic Classic', '2026-09-26', 'Oakdale Range', 'Amateur Civilian', 23.80, 18.90, 22.65, 0.00, 'Season best'],
            [$alexId, 'Autumn Ballistic Classic', '2026-09-26', 'Oakdale Range', 'Competition', 21.10, 17.25, 19.95, 0.00, 'Sub-59s match'],
            [$jordanId, 'Autumn Ballistic Classic', '2026-09-26', 'Oakdale Range', 'Amateur Civilian', 25.40, 21.15, 24.10, 0.00, 'Closed the gap!'],
        ];

        foreach ($sampleMatches as $m) {
            $this->createResult([
                'user_id' => $m[0],
                'match_name' => $m[1],
                'match_date' => $m[2],
                'location' => $m[3],
                'division' => $m[4],
                'five_to_glock' => $m[5],
                'glock_the_plates' => $m[6],
                'glock_m' => $m[7],
                'penalties' => $m[8],
                'notes' => $m[9],
                'created_by' => $adminId,
            ]);
        }
    }
}

<?php
declare(strict_types=1);

class Storage
{
    private string $driver;
    private string $sqlitePath;
    private ?PDO $pdo = null;
    private bool $useSqliteCli = false;

    public function __construct(array $config)
    {
        $this->driver = $config['storage_driver'] ?? 'sqlite';
        $dataDir = $config['data_dir'] ?? (__DIR__ . '/../data');
        $this->sqlitePath = $config['sqlite_path'] ?? ($dataDir . '/gssf.sqlite');

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
            $this->driver = 'sqlite';
            $dir = dirname($this->sqlitePath);
            if (!is_dir($dir)) {
                if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
                    throw new RuntimeException("Cannot create database directory: $dir");
                }
            }
            if (!is_writable($dir)) {
                throw new RuntimeException("Database directory ($dir) is not writable by the web server. Run: sudo chown -R www-data:www-data $dir");
            }
            if (file_exists($this->sqlitePath) && !is_writable($this->sqlitePath)) {
                throw new RuntimeException("SQLite database file ({$this->sqlitePath}) is not writable by the web server. Run: sudo chown -R www-data:www-data $dir");
            }

            if (extension_loaded('pdo_sqlite')) {
                $this->pdo = new PDO('sqlite:' . $this->sqlitePath, null, null, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
                $this->pdo->exec('PRAGMA foreign_keys = ON;');
                $this->pdo->exec('PRAGMA journal_mode = WAL;');
            } elseif (function_exists('exec')) {
                $this->useSqliteCli = true;
            } else {
                throw new RuntimeException('PHP pdo_sqlite extension is not installed. Run: sudo apt install php-sqlite3 && sudo systemctl restart apache2');
            }

            $this->initSqliteSchema();
        }

        $this->seedIfEmpty();
    }

    // -------------------------------------------------------------------------
    // SQLite execution abstraction (PDO or sqlite3 CLI fallback)
    // -------------------------------------------------------------------------

    private function quoteSqliteValue(mixed $val): string
    {
        if ($val === null) {
            return 'NULL';
        }
        if (is_int($val) || is_float($val)) {
            return (string)$val;
        }
        if (is_bool($val)) {
            return $val ? '1' : '0';
        }
        return "'" . str_replace("'", "''", (string)$val) . "'";
    }

    private function bindSqliteCliSql(string $sql, array $params): string
    {
        if (empty($params)) {
            return $sql;
        }
        $idx = 0;
        return (string)preg_replace_callback('/\?/', function () use ($params, &$idx) {
            $val = $params[$idx] ?? null;
            $idx++;
            return $this->quoteSqliteValue($val);
        }, $sql);
    }

    private function runSqliteCli(string $sql, bool $jsonOutput = false): array
    {
        $tmpSql = tempnam(sys_get_temp_dir(), 'gssf_sql_');
        file_put_contents($tmpSql, "PRAGMA foreign_keys = ON;\n" . $sql . "\n");
        $flag = $jsonOutput ? '-json ' : '';
        $cmd = sprintf('sqlite3 %s%s < %s 2>&1', $flag, escapeshellarg($this->sqlitePath), escapeshellarg($tmpSql));
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);
        @unlink($tmpSql);

        if ($code !== 0) {
            throw new RuntimeException('SQLite CLI error: ' . implode("\n", $output));
        }

        if (!$jsonOutput) {
            return [];
        }
        $raw = trim(implode("\n", $output));
        if ($raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function dbAll(string $sql, array $params = []): array
    {
        if (!$this->useSqliteCli) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        }
        $bound = $this->bindSqliteCliSql($sql, $params);
        return $this->runSqliteCli($bound, true);
    }

    private function dbOne(string $sql, array $params = []): ?array
    {
        $rows = $this->dbAll($sql, $params);
        return $rows[0] ?? null;
    }

    private function dbExec(string $sql, array $params = []): int
    {
        if (!$this->useSqliteCli) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount();
        }
        $bound = $this->bindSqliteCliSql($sql . '; SELECT changes() AS c', $params);
        $rows = $this->runSqliteCli($bound, true);
        return (int)($rows[0]['c'] ?? 0);
    }

    private function dbInsert(string $sql, array $params = []): int
    {
        if (!$this->useSqliteCli) {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return (int)$this->pdo->lastInsertId();
        }
        $bound = $this->bindSqliteCliSql($sql . '; SELECT last_insert_rowid() AS id', $params);
        $rows = $this->runSqliteCli($bound, true);
        return (int)($rows[0]['id'] ?? 0);
    }

    private function initSqliteSchema(): void
    {
        $schema = "
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            display_name TEXT NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'member',
            default_division TEXT NOT NULL DEFAULT 'Amateur Civilian',
            color TEXT NOT NULL DEFAULT '#1d4ed8',
            active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS match_results (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            match_name TEXT NOT NULL,
            match_date TEXT NOT NULL,
            location TEXT NOT NULL DEFAULT '',
            division TEXT NOT NULL DEFAULT 'Amateur Civilian',
            five_to_glock REAL NOT NULL,
            glock_the_plates REAL NOT NULL,
            glock_m REAL NOT NULL,
            penalties REAL NOT NULL DEFAULT 0.0,
            total_time REAL NOT NULL,
            stage_details TEXT NOT NULL DEFAULT '',
            notes TEXT NOT NULL DEFAULT '',
            created_by INTEGER NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE INDEX IF NOT EXISTS idx_results_user_date ON match_results(user_id, match_date);

        CREATE TABLE IF NOT EXISTS passkeys (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            credential_id TEXT NOT NULL UNIQUE,
            public_key_pem TEXT NOT NULL,
            cose_alg INTEGER NOT NULL DEFAULT -7,
            sign_count INTEGER NOT NULL DEFAULT 0,
            label TEXT NOT NULL DEFAULT 'Passkey',
            created_at TEXT NOT NULL,
            last_used_at TEXT NULL,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE INDEX IF NOT EXISTS idx_passkeys_user ON passkeys(user_id);

        CREATE TABLE IF NOT EXISTS matches (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            match_name TEXT NOT NULL,
            match_date TEXT NOT NULL,
            location TEXT NOT NULL DEFAULT '',
            created_at TEXT NOT NULL
        );

        CREATE INDEX IF NOT EXISTS idx_matches_date ON matches(match_date);

        CREATE TABLE IF NOT EXISTS match_shooters (
            match_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            PRIMARY KEY (match_id, user_id),
            FOREIGN KEY (match_id) REFERENCES matches(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        );
        ";

        if (!$this->useSqliteCli) {
            $this->pdo->exec($schema);
        } else {
            $this->runSqliteCli($schema, false);
        }
    }

    // -------------------------------------------------------------------------
    // Users
    // -------------------------------------------------------------------------

    public function getUsers(bool $onlyActive = false): array
    {
        $sql = 'SELECT * FROM users';
        if ($onlyActive) {
            $sql .= ' WHERE active = 1';
        }
        $sql .= ' ORDER BY display_name COLLATE NOCASE ASC';
        if ($this->driver === 'mysql') {
            $sql = str_replace(' COLLATE NOCASE', '', $sql);
        }
        return $this->dbAll($sql);
    }

    public function getUserById(int $id): ?array
    {
        return $this->dbOne('SELECT * FROM users WHERE id = ? LIMIT 1', [$id]);
    }

    public function getUserByUsername(string $username): ?array
    {
        return $this->dbOne('SELECT * FROM users WHERE LOWER(username) = ? LIMIT 1', [strtolower(trim($username))]);
    }

    public function createUser(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        return $this->dbInsert(
            'INSERT INTO users (username, display_name, password_hash, role, default_division, color, active, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                strtolower(trim($data['username'])),
                trim($data['display_name']),
                password_hash($data['password'], PASSWORD_DEFAULT),
                ($data['role'] ?? 'member') === 'admin' ? 'admin' : 'member',
                $data['default_division'] ?? 'Amateur Civilian',
                $data['color'] ?? '#1d4ed8',
                isset($data['active']) ? (int)$data['active'] : 1,
                $now,
            ]
        );
    }

    public function updateUser(int $id, array $data): bool
    {
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
            ($data['role'] ?? 'member') === 'admin' ? 'admin' : 'member',
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
        $this->dbExec($sql, $params);
        return true;
    }

    public function deleteUser(int $id): bool
    {
        return $this->dbExec('DELETE FROM users WHERE id = ?', [$id]) > 0;
    }

    // -------------------------------------------------------------------------
    // Match Results
    // -------------------------------------------------------------------------

    public function getResults(array $filters = []): array
    {
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

        return $this->dbAll($sql, $params);
    }

    public function getResultById(int $id): ?array
    {
        return $this->dbOne(
            'SELECT r.*, u.display_name AS shooter_name, u.color AS shooter_color
             FROM match_results r
             JOIN users u ON u.id = r.user_id
             WHERE r.id = ? LIMIT 1',
            [$id]
        );
    }

    public function createResult(array $data): int
    {
        $five = round((float)$data['five_to_glock'], 2);
        $plates = round((float)$data['glock_the_plates'], 2);
        $glockm = round((float)$data['glock_m'], 2);
        $penalties = round((float)($data['penalties'] ?? 0), 2);
        $total = isset($data['total_time'])
            ? round((float)$data['total_time'], 2)
            : round($five + $plates + $glockm + $penalties, 2);
        $stageDetails = (string)($data['stage_details'] ?? '');
        $now = date('Y-m-d H:i:s');

        return $this->dbInsert(
            'INSERT INTO match_results
             (user_id, match_name, match_date, location, division, five_to_glock, glock_the_plates, glock_m, penalties, total_time, stage_details, notes, created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
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
                $stageDetails,
                trim($data['notes'] ?? ''),
                isset($data['created_by']) ? (int)$data['created_by'] : null,
                $now,
                $now,
            ]
        );
    }

    public function updateResult(int $id, array $data): bool
    {
        $five = round((float)$data['five_to_glock'], 2);
        $plates = round((float)$data['glock_the_plates'], 2);
        $glockm = round((float)$data['glock_m'], 2);
        $penalties = round((float)($data['penalties'] ?? 0), 2);
        $total = isset($data['total_time'])
            ? round((float)$data['total_time'], 2)
            : round($five + $plates + $glockm + $penalties, 2);
        $stageDetails = (string)($data['stage_details'] ?? '');
        $now = date('Y-m-d H:i:s');

        $this->dbExec(
            'UPDATE match_results SET
             user_id = ?, match_name = ?, match_date = ?, location = ?, division = ?,
             five_to_glock = ?, glock_the_plates = ?, glock_m = ?, penalties = ?, total_time = ?,
             stage_details = ?, notes = ?, updated_at = ?
             WHERE id = ?',
            [
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
                $stageDetails,
                trim($data['notes'] ?? ''),
                $now,
                $id,
            ]
        );
        return true;
    }

    public function deleteResult(int $id): bool
    {
        return $this->dbExec('DELETE FROM match_results WHERE id = ?', [$id]) > 0;
    }

    // -------------------------------------------------------------------------
    // Passkeys (WebAuthn)
    // -------------------------------------------------------------------------

    public function getPasskeysForUser(int $userId): array
    {
        return $this->dbAll(
            'SELECT id, user_id, credential_id, cose_alg, sign_count, label, created_at, last_used_at
             FROM passkeys WHERE user_id = ? ORDER BY created_at DESC',
            [$userId]
        );
    }

    public function getPasskeyCountsByUser(): array
    {
        $rows = $this->dbAll('SELECT user_id, COUNT(*) AS cnt FROM passkeys GROUP BY user_id');
        $out = [];
        foreach ($rows as $r) {
            $out[(int)$r['user_id']] = (int)$r['cnt'];
        }
        return $out;
    }

    public function getPasskeyByCredentialId(string $credentialId): ?array
    {
        return $this->dbOne(
            'SELECT p.*, u.username, u.display_name, u.active
             FROM passkeys p
             JOIN users u ON u.id = p.user_id
             WHERE p.credential_id = ? LIMIT 1',
            [$credentialId]
        );
    }

    public function createPasskey(int $userId, string $credentialId, string $publicKeyPem, int $coseAlg, int $signCount, string $label): int
    {
        $now = date('Y-m-d H:i:s');
        return $this->dbInsert(
            'INSERT INTO passkeys (user_id, credential_id, public_key_pem, cose_alg, sign_count, label, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $userId,
                $credentialId,
                $publicKeyPem,
                $coseAlg,
                $signCount,
                trim($label) !== '' ? trim($label) : 'Passkey',
                $now,
            ]
        );
    }

    public function updatePasskeyUsage(int $id, int $signCount): void
    {
        $this->dbExec(
            'UPDATE passkeys SET sign_count = ?, last_used_at = ? WHERE id = ?',
            [$signCount, date('Y-m-d H:i:s'), $id]
        );
    }

    public function deletePasskey(int $id, int $userId): bool
    {
        return $this->dbExec('DELETE FROM passkeys WHERE id = ? AND user_id = ?', [$id, $userId]) > 0;
    }

    // -------------------------------------------------------------------------
    // Matches
    // -------------------------------------------------------------------------

    public function getMatches(): array
    {
        $matches = $this->dbAll('SELECT * FROM matches ORDER BY match_date DESC, id DESC');
        if (empty($matches)) {
            return [];
        }

        $shootersRows = $this->dbAll(
            'SELECT ms.match_id, u.id AS user_id, u.display_name, u.color
             FROM match_shooters ms
             JOIN users u ON u.id = ms.user_id
             ORDER BY u.display_name ASC'
        );

        $shootersByMatch = [];
        foreach ($shootersRows as $row) {
            $mid = (int)$row['match_id'];
            $shootersByMatch[$mid][] = [
                'id' => (int)$row['user_id'],
                'display_name' => $row['display_name'],
                'color' => $row['color'] ?? '#1d4ed8',
            ];
        }

        foreach ($matches as &$m) {
            $mid = (int)$m['id'];
            $m['shooters'] = $shootersByMatch[$mid] ?? [];
            $m['shooter_ids'] = array_map(fn($s) => $s['id'], $m['shooters']);
        }
        unset($m);

        return $matches;
    }

    public function getMatchById(int $id): ?array
    {
        $m = $this->dbOne('SELECT * FROM matches WHERE id = ? LIMIT 1', [$id]);
        if (!$m) {
            return null;
        }
        $shootersRows = $this->dbAll(
            'SELECT u.id AS user_id, u.display_name, u.color
             FROM match_shooters ms
             JOIN users u ON u.id = ms.user_id
             WHERE ms.match_id = ?
             ORDER BY u.display_name ASC',
            [$id]
        );
        $m['shooters'] = array_map(fn($r) => [
            'id' => (int)$r['user_id'],
            'display_name' => $r['display_name'],
            'color' => $r['color'] ?? '#1d4ed8',
        ], $shootersRows);
        $m['shooter_ids'] = array_map(fn($s) => $s['id'], $m['shooters']);
        return $m;
    }

    public function createMatch(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $matchId = $this->dbInsert(
            'INSERT INTO matches (match_name, match_date, location, created_at) VALUES (?, ?, ?, ?)',
            [
                trim($data['match_name']),
                $data['match_date'],
                trim($data['location'] ?? ''),
                $now,
            ]
        );
        $this->syncMatchShooters($matchId, $data['shooter_ids'] ?? []);
        return $matchId;
    }

    public function updateMatch(int $id, array $data): bool
    {
        $this->dbExec(
            'UPDATE matches SET match_name = ?, match_date = ?, location = ? WHERE id = ?',
            [
                trim($data['match_name']),
                $data['match_date'],
                trim($data['location'] ?? ''),
                $id,
            ]
        );
        $this->syncMatchShooters($id, $data['shooter_ids'] ?? []);
        return true;
    }

    public function deleteMatch(int $id): bool
    {
        return $this->dbExec('DELETE FROM matches WHERE id = ?', [$id]) > 0;
    }

    private function syncMatchShooters(int $matchId, array $shooterIds): void
    {
        $this->dbExec('DELETE FROM match_shooters WHERE match_id = ?', [$matchId]);
        $seen = [];
        foreach ($shooterIds as $uid) {
            $uid = (int)$uid;
            if ($uid > 0 && !isset($seen[$uid])) {
                $seen[$uid] = true;
                $this->dbExec(
                    'INSERT INTO match_shooters (match_id, user_id) VALUES (?, ?)',
                    [$matchId, $uid]
                );
            }
        }
    }

    public function getShooterDivisionTotals(array $filters = []): array
    {
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
        if (!empty($filters['search'])) {
            $where[] = '(r.match_name LIKE ? OR r.location LIKE ? OR r.notes LIKE ?)';
            $q = '%' . $filters['search'] . '%';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }

        $sql = 'SELECT r.user_id, u.display_name AS shooter_name, u.color AS shooter_color, r.division,
                       COUNT(*) AS match_count,
                       SUM(r.total_time) AS sum_total_time,
                       MIN(r.total_time) AS best_total_time,
                       AVG(r.total_time) AS avg_total_time
                FROM match_results r
                JOIN users u ON u.id = r.user_id';
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' GROUP BY r.user_id, u.display_name, u.color, r.division
                  ORDER BY u.display_name ASC, r.division ASC';

        return $this->dbAll($sql, $params);
    }

    // -------------------------------------------------------------------------
    // Initial seed for first-run experience
    // -------------------------------------------------------------------------

    private function seedIfEmpty(): void
    {
        $existing = $this->dbOne('SELECT COUNT(*) AS cnt FROM users');
        if ((int)($existing['cnt'] ?? 0) > 0) {
            $this->backfillMatchesIfEmpty();
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
            [$adminId, 'Spring Classic Regional', '2026-03-14', 'Oakdale Range', 'Amateur Civilian', 26.40, 21.85, 25.10, 0.00, 'G19 Gen5 - clean plates'],
            [$alexId, 'Spring Classic Regional', '2026-03-14', 'Oakdale Range', 'Competition', 23.90, 19.40, 22.80, 0.00, 'G34 MOS - fast transitions'],
            [$jordanId, 'Spring Classic Regional', '2026-03-14', 'Oakdale Range', 'Amateur Civilian', 31.20, 26.50, 29.90, 2.00, '1 charlie-mike'],
            [$adminId, 'Midwest Glock Challenge', '2026-05-09', 'Tri-County Club', 'Amateur Civilian', 25.15, 20.10, 24.30, 0.00, 'Personal best on Plates'],
            [$alexId, 'Midwest Glock Challenge', '2026-05-09', 'Tri-County Club', 'Competition', 22.80, 18.95, 21.90, 0.00, 'Consistent strings'],
            [$jordanId, 'Midwest Glock Challenge', '2026-05-09', 'Tri-County Club', 'Amateur Civilian', 28.90, 24.10, 27.60, 0.00, 'Zero penalties!'],
            [$adminId, 'Summer Heat Outdoor', '2026-07-18', 'Riverbend Practical', 'Amateur Civilian', 24.50, 19.60, 23.40, 0.00, 'Steady progress'],
            [$alexId, 'Summer Heat Outdoor', '2026-07-18', 'Riverbend Practical', 'Competition', 21.95, 17.80, 20.75, 0.00, 'Sub-61s total'],
            [$jordanId, 'Summer Heat Outdoor', '2026-07-18', 'Riverbend Practical', 'Amateur Civilian', 26.75, 22.30, 25.40, 0.00, 'Big jump on Five to Glock'],
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

        $this->backfillMatchesIfEmpty();
    }

    private function backfillMatchesIfEmpty(): void
    {
        $cntRow = $this->dbOne('SELECT COUNT(*) AS cnt FROM matches');
        if ((int)($cntRow['cnt'] ?? 0) > 0) {
            return;
        }
        $rows = $this->dbAll('SELECT match_name, match_date, location, user_id FROM match_results ORDER BY match_date DESC');
        if (empty($rows)) {
            return;
        }
        $grouped = [];
        foreach ($rows as $r) {
            $key = $r['match_date'] . '|' . $r['match_name'] . '|' . $r['location'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'match_name' => $r['match_name'],
                    'match_date' => $r['match_date'],
                    'location' => $r['location'],
                    'shooter_ids' => [],
                ];
            }
            $grouped[$key]['shooter_ids'][] = (int)$r['user_id'];
        }
        foreach ($grouped as $m) {
            $this->createMatch($m);
        }
    }
}

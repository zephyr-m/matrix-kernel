<?php
/**
 * Matrix Kernel — Universal Matrix-Driven Application Engine
 *
 * Ядро не знает ни домена, ни storage. Всё из матриц.
 *
 * Usage:
 *   $app = new MatrixKernel(['name' => 'myapp', 'storage' => new SqliteStorage('app.db'), ...]);
 *   $app->run();
 */

// ── Storage Interface ────────────────────────────────────────────────────
// Kernel работает через абстракцию. Сегодня SQLite, завтра API, файлы.

interface MatrixStorage {
    public function query(string $sql, array $params = []): array;
    public function exec(string $sql, array $params = []): int;
    public function scalar(string $sql, array $params = []): mixed;
}

class SqliteStorage implements MatrixStorage {
    private SQLite3 $db;

    public function __construct(string $path) {
        $this->db = new SQLite3($path, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
        $this->db->busyTimeout(3000);
        $this->db->exec("PRAGMA journal_mode=WAL");
    }

    public function query(string $sql, array $params = []): array {
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new RuntimeException($this->db->lastErrorMsg());
        $this->bind($stmt, $params);
        $result = $stmt->execute();
        $rows = [];
        while ($r = $result->fetchArray(SQLITE3_ASSOC)) $rows[] = $r;
        return $rows;
    }

    public function exec(string $sql, array $params = []): int {
        $stmt = $this->db->prepare($sql);
        if (!$stmt) throw new RuntimeException($this->db->lastErrorMsg());
        $this->bind($stmt, $params);
        $stmt->execute();
        return $this->db->changes();
    }

    public function scalar(string $sql, array $params = []): mixed {
        $rows = $this->query($sql, $params);
        if (empty($rows)) return null;
        return array_values($rows[0])[0];
    }

    public function raw(): SQLite3 { return $this->db; }

    private function bind(SQLite3Stmt $stmt, array $params): void {
        foreach ($params as $key => $value) {
            $bind = is_int($key) ? $key + 1 : ":$key";
            $stmt->bindValue($bind, $value);
        }
    }
}

// ── Matrix Kernel ────────────────────────────────────────────────────────

class MatrixKernel {
    public string $name;
    public MatrixStorage $storage;
    public array $matrices = [];

    public function __construct(array $config) {
        $this->name = $config['name'] ?? 'app';

        // Storage: injectable
        if (isset($config['storage']) && $config['storage'] instanceof MatrixStorage) {
            $this->storage = $config['storage'];
        } else {
            $dbPath = $config['db'] ?? "{$this->name}.db";
            $this->storage = new SqliteStorage($dbPath);
        }

        // Load any matrix (no hardcoded keys — kernel accepts anything)
        foreach ($config as $key => $value) {
            if (is_array($value) && !in_array($key, ['name', 'db', 'storage'])) {
                $this->matrices[$key] = $value;
            }
        }
    }

    // ── Matrix access ────────────────────────────────────────────────────
    public function matrix(string $key): array {
        return $this->matrices[$key] ?? [];
    }

    // ── Run: detect mode and dispatch ────────────────────────────────────
    public function run(): void {
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        if (str_contains($uri, '/api.php') || isset($_GET['api'])) {
            $this->handleApi();
        } else {
            $this->handleUi();
        }
    }

    // ── API: Command Engine ──────────────────────────────────────────────
    public function handleApi(): void {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

        $commands = $this->matrix('commands');
        $input = $_SERVER['REQUEST_METHOD'] === 'POST'
            ? json_decode(file_get_contents('php://input'), true) ?? $_POST
            : $_GET;

        $cmd = $input['cmd'] ?? '';
        $t0 = microtime(true);

        // Self-documenting
        if (!$cmd) {
            echo json_encode(['app' => $this->name, 'commands' => array_keys($commands)]);
            exit;
        }

        if (!isset($commands[$cmd])) {
            echo json_encode(['error' => "unknown cmd: {$cmd}", 'commands' => array_keys($commands)]);
            exit;
        }

        $route = $commands[$cmd];
        $type = $route['type'];

        // Handler — матрица содержит функцию
        if ($type === 'raw' || $type === 'handler') {
            $result = ($route['handler'])($this->storage, $input, $this);
            $this->respond($cmd, $result, $t0);
            exit;
        }

        // SQL types — через storage adapter
        $params = $this->resolveParams($route['params'], $input);
        if ($params === false) exit;

        try {
            if ($type === 'query') {
                $data = $this->storage->query($route['sql'], $params);
                echo json_encode(['ok' => true, 'cmd' => $cmd, 'data' => $data, 'count' => count($data), 'ms' => $this->ms($t0)], JSON_UNESCAPED_UNICODE);
            } else {
                $changes = $this->storage->exec($route['sql'], $params);
                echo json_encode(['ok' => true, 'cmd' => $cmd, 'changes' => $changes, 'ms' => $this->ms($t0)]);
            }
        } catch (RuntimeException $e) {
            echo json_encode(['error' => 'storage_error', 'detail' => $e->getMessage()]);
        }
    }

    // ── FSM Engine ───────────────────────────────────────────────────────
    // Transition = matrix handler call, not hardcoded SQL
    public function fsmTransition(string $fsmKey, string $table, int $id, string $newState): array {
        $fsm = $this->matrix($fsmKey);
        if (!isset($fsm[$newState])) {
            return ['ok' => false, 'error' => "unknown state: {$newState}"];
        }

        $stateConfig = $fsm[$newState];

        // If FSM state has an 'onEnter' handler — call it (matrix defines behavior)
        if (isset($stateConfig['onEnter']) && is_callable($stateConfig['onEnter'])) {
            $result = ($stateConfig['onEnter'])($this->storage, $id, $newState, $this);
            if (is_array($result) && isset($result['ok']) && !$result['ok']) {
                return $result; // handler rejected the transition
            }
        }

        // Default transition: update state field (storage-agnostic through adapter)
        $this->storage->exec("UPDATE {$table} SET state = :state WHERE id = :id", [
            'state' => $newState,
            'id' => $id,
        ]);

        return ['ok' => true, 'state' => $newState, 'id' => $id];
    }

    // ── Action Engine ────────────────────────────────────────────────────
    // Generic: matrix row defines action as callable, engine just invokes
    public function executeAction(string $matrixKey, string $actionKey, array $context = []): array {
        $matrix = $this->matrix($matrixKey);
        if (!isset($matrix[$actionKey])) {
            return ['ok' => false, 'error' => "unknown action: {$actionKey}"];
        }

        $action = $matrix[$actionKey];

        // Each field in action that is callable → execute it
        $results = [];
        foreach ($action as $field => $value) {
            if (is_callable($value)) {
                $results[$field] = $value($this->storage, $context, $this);
            } else {
                $results[$field] = $value;
            }
        }

        return ['ok' => true, 'action' => $actionKey, 'results' => $results];
    }

    // ── UI: Layout Engine ────────────────────────────────────────────────
    public function handleUi(): void {
        $page = $_GET['p'] ?? 'dashboard';
        $kernel = $this;
        require __DIR__ . '/renderers/layout.php';
    }

    // ── Renderers ────────────────────────────────────────────────────────
    public function renderTable(array $entity, array $rows, array $options = []): string {
        ob_start();
        require __DIR__ . '/renderers/table.php';
        return ob_get_clean();
    }

    public function renderKanban(array $fsm, array $rows, array $options = []): string {
        ob_start();
        require __DIR__ . '/renderers/kanban.php';
        return ob_get_clean();
    }

    // ── Helpers ──────────────────────────────────────────────────────────
    private function respond(string $cmd, $data, float $t0): void {
        echo json_encode(['ok' => true, 'cmd' => $cmd, 'data' => $data, 'ms' => $this->ms($t0)], JSON_UNESCAPED_UNICODE);
    }

    private function ms(float $t0): float {
        return round((microtime(true) - $t0) * 1000, 2);
    }

    private function resolveParams(array $params, array $input): array|false {
        $resolved = [];
        if ($this->isAssoc($params)) {
            foreach ($params as $key => $default) {
                $resolved[$key] = $input[$key] ?? $default;
            }
        } else {
            foreach ($params as $key) {
                if (!isset($input[$key])) {
                    echo json_encode(['error' => "missing param: {$key}"]);
                    return false;
                }
                $resolved[$key] = $input[$key];
            }
        }
        return $resolved;
    }

    private function isAssoc(array $arr): bool {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}

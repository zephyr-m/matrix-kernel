<?php
/**
 * Matrix Kernel — Universal Matrix-Driven Application Engine
 *
 * Один раз написал — потом собираешь что угодно матрицами.
 *
 * Usage:
 *   $app = new MatrixKernel(['name' => 'myapp', 'db' => 'app.db', ...]);
 *   $app->run();
 */

class MatrixKernel {
    public string $name;
    public SQLite3 $db;
    public array $matrices = [];

    // Engines loaded on demand
    private array $engines = [];

    public function __construct(array $config) {
        $this->name = $config['name'] ?? 'app';

        // ── DB ───────────────────────────────────────────────────────────
        $dbPath = $config['db'] ?? "{$this->name}.db";
        $this->db = new SQLite3($dbPath, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
        $this->db->busyTimeout(3000);
        $this->db->exec("PRAGMA journal_mode=WAL");

        // ── Load matrices ────────────────────────────────────────────────
        $matrixKeys = ['entities', 'fsm', 'commands', 'layout', 'menu', 'forms', 'settings'];
        foreach ($matrixKeys as $key) {
            if (isset($config[$key])) {
                $this->matrices[$key] = $config[$key];
            }
        }
    }

    // ── Matrix access ────────────────────────────────────────────────────
    public function matrix(string $key): array {
        return $this->matrices[$key] ?? [];
    }

    // ── Run: detect mode (API or UI) and dispatch ────────────────────────
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

        // Self-documenting: no cmd → list all
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

        // Raw handler
        if ($type === 'raw') {
            $result = ($route['handler'])($this->db, $input);
            $this->apiResponse($cmd, $result, $t0);
            exit;
        }

        // SQL: prepare → bind → execute
        $stmt = $this->db->prepare($route['sql']);
        if (!$stmt) {
            echo json_encode(['error' => 'sql_prepare_failed', 'detail' => $this->db->lastErrorMsg()]);
            exit;
        }

        // Bind params (associative = defaults, sequential = required)
        $params = $route['params'];
        if ($this->isAssoc($params)) {
            foreach ($params as $key => $default) {
                $stmt->bindValue(":$key", $input[$key] ?? $default);
            }
        } else {
            foreach ($params as $key) {
                $val = $input[$key] ?? null;
                if ($val === null) {
                    echo json_encode(['error' => "missing param: {$key}"]);
                    exit;
                }
                $stmt->bindValue(":$key", $val);
            }
        }

        $result = $stmt->execute();
        if (!$result) {
            echo json_encode(['error' => 'sql_exec_failed', 'detail' => $this->db->lastErrorMsg()]);
            exit;
        }

        $ms = round((microtime(true) - $t0) * 1000, 2);

        if ($type === 'query') {
            $rows = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) $rows[] = $row;
            echo json_encode(['ok' => true, 'cmd' => $cmd, 'data' => $rows, 'count' => count($rows), 'ms' => $ms], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode(['ok' => true, 'cmd' => $cmd, 'changes' => $this->db->changes(), 'ms' => $ms]);
        }
    }

    // ── UI: Layout Engine ────────────────────────────────────────────────
    public function handleUi(): void {
        $page = $_GET['p'] ?? 'dashboard';
        $menu = $this->matrix('menu');
        $layout = $this->matrix('layout');

        // Make kernel available to pages
        $kernel = $this;
        $db = $this->db;

        // Render
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

    public function renderCards(array $entity, array $rows, array $options = []): string {
        ob_start();
        require __DIR__ . '/renderers/cards.php';
        return ob_get_clean();
    }

    // ── FSM Engine ───────────────────────────────────────────────────────
    public function fsmTransition(string $table, int $id, string $newState): bool {
        $fsm = $this->matrix('fsm');
        if (!isset($fsm[$newState])) return false;

        $stmt = $this->db->prepare("UPDATE {$table} SET state = :state WHERE id = :id");
        $stmt->bindValue(':state', $newState);
        $stmt->bindValue(':id', $id);
        return (bool)$stmt->execute();
    }

    // ── Helpers ──────────────────────────────────────────────────────────
    private function apiResponse(string $cmd, $data, float $t0): void {
        $ms = round((microtime(true) - $t0) * 1000, 2);
        echo json_encode(['ok' => true, 'cmd' => $cmd, 'data' => $data, 'ms' => $ms], JSON_UNESCAPED_UNICODE);
    }

    private function isAssoc(array $arr): bool {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}

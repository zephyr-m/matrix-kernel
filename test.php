#!/usr/bin/env php
<?php
/**
 * Matrix Kernel — Auto Tests
 * Run: php test.php
 *
 * Tests kernel engines against in-memory SQLite + test matrices.
 */

require __DIR__ . '/kernel.php';

$passed = 0;
$failed = 0;

function assert_eq($actual, $expected, string $name): void {
    global $passed, $failed;
    if ($actual === $expected) {
        echo "  ✅ {$name}\n";
        $passed++;
    } else {
        echo "  ❌ {$name} (expected: " . json_encode($expected) . ", got: " . json_encode($actual) . ")\n";
        $failed++;
    }
}

function assert_true($condition, string $name): void {
    assert_eq((bool)$condition, true, $name);
}

// ── Test Matrices ────────────────────────────────────────────────────────

$TEST_COMMANDS = [
    'ping' => [
        'label' => 'Ping', 'icon' => 'heartbeat',
        'type'  => 'raw',
        'handler' => fn($db, $p) => ['pong' => true],
    ],
    'get_items' => [
        'label' => 'Get Items', 'icon' => 'list',
        'type'  => 'query',
        'sql'   => "SELECT * FROM items WHERE state = :state ORDER BY id DESC LIMIT :limit",
        'params' => ['state' => 'active', 'limit' => 50],
    ],
    'set_state' => [
        'label' => 'Set State', 'icon' => 'edit',
        'type'  => 'exec',
        'sql'   => "UPDATE items SET state = :state WHERE id = :id",
        'params' => ['id', 'state'],
    ],
    'add_item' => [
        'label' => 'Add Item', 'icon' => 'plus',
        'type'  => 'exec',
        'sql'   => "INSERT INTO items (title, state) VALUES (:title, 'active')",
        'params' => ['title'],
    ],
];

$TEST_FSM = [
    'idle'    => ['label' => 'Idle',   'color' => 'secondary', 'icon' => 'ti-clock',  'buttons' => [
        ['state' => 'active', 'label' => 'Start',  'class' => 'btn-outline-green', 'icon' => 'ti-play'],
    ]],
    'active'  => ['label' => 'Active', 'color' => 'green',     'icon' => 'ti-bolt',   'buttons' => [
        ['state' => 'closed', 'label' => 'Close',  'class' => 'btn-outline-red',   'icon' => 'ti-x'],
        ['state' => 'idle',   'label' => 'Pause',  'class' => 'btn-outline-yellow','icon' => 'ti-pause'],
    ]],
    'closed'  => ['label' => 'Closed', 'color' => 'red',       'icon' => 'ti-check',  'buttons' => []],
];

$TEST_ENTITY = [
    'id'    => ['label' => 'ID',      'type' => 'number'],
    'title' => ['label' => 'Название','type' => 'text'],
    'state' => ['label' => 'Статус',  'type' => 'badge'],
    'price' => ['label' => 'Цена',    'type' => 'money'],
];

// ── Create kernel with test DB ───────────────────────────────────────────

echo "\n🧪 Matrix Kernel Tests\n";

// Use temp file for SQLite (in-memory doesn't work with :memory: path in constructor)
$testDbPath = sys_get_temp_dir() . '/mk_test_' . getmypid() . '.db';
@unlink($testDbPath);

$kernel = new MatrixKernel([
    'name'     => 'test-app',
    'db'       => $testDbPath,
    'commands' => $TEST_COMMANDS,
    'fsm'      => $TEST_FSM,
    'entities' => $TEST_ENTITY,
    'menu'     => [],
    'layout'   => [],
]);

// Create test table
$kernel->db->exec("CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, state TEXT DEFAULT 'idle', price REAL DEFAULT 0)");

// ── 1. Kernel Init ───────────────────────────────────────────────────────

echo "\n  Kernel Init\n";
assert_eq($kernel->name, 'test-app', 'kernel name');
assert_true($kernel->db instanceof SQLite3, 'db is SQLite3');
assert_eq(count($kernel->matrix('commands')), 4, 'commands loaded: 4');
assert_eq(count($kernel->matrix('fsm')), 3, 'fsm loaded: 3 states');
assert_eq(count($kernel->matrix('entities')), 4, 'entity loaded: 4 columns');
assert_eq($kernel->matrix('nonexistent'), [], 'unknown matrix returns []');

// ── 2. Command Engine (direct DB calls, bypassing HTTP) ──────────────────

echo "\n  Command Engine\n";

// Raw command
$handler = $TEST_COMMANDS['ping']['handler'];
$result = $handler($kernel->db, []);
assert_eq($result['pong'], true, 'ping returns pong');

// Insert via SQL
$kernel->db->exec("INSERT INTO items (title, state, price) VALUES ('Alpha', 'active', 100)");
$kernel->db->exec("INSERT INTO items (title, state, price) VALUES ('Beta', 'idle', 200)");
$kernel->db->exec("INSERT INTO items (title, state, price) VALUES ('Gamma', 'active', 50)");

$count = $kernel->db->querySingle("SELECT COUNT(*) FROM items");
assert_eq($count, 3, '3 items inserted');

// Query with defaults
$stmt = $kernel->db->prepare($TEST_COMMANDS['get_items']['sql']);
$stmt->bindValue(':state', 'active');
$stmt->bindValue(':limit', 50);
$result = $stmt->execute();
$rows = [];
while ($r = $result->fetchArray(SQLITE3_ASSOC)) $rows[] = $r;
assert_eq(count($rows), 2, 'query: 2 active items');
assert_eq($rows[0]['title'], 'Gamma', 'query: latest first (ORDER BY id DESC)');

// Exec: state transition
$stmt = $kernel->db->prepare($TEST_COMMANDS['set_state']['sql']);
$stmt->bindValue(':id', 1);
$stmt->bindValue(':state', 'closed');
$stmt->execute();
$newState = $kernel->db->querySingle("SELECT state FROM items WHERE id = 1");
assert_eq($newState, 'closed', 'exec: state changed to closed');

// ── 3. FSM Engine ────────────────────────────────────────────────────────

echo "\n  FSM Engine\n";

// Valid transition
$ok = $kernel->fsmTransition('items', 2, 'active');
assert_true($ok, 'fsmTransition: valid state returns true');
$state = $kernel->db->querySingle("SELECT state FROM items WHERE id = 2");
assert_eq($state, 'active', 'fsmTransition: state updated');

// Invalid transition (unknown state)
$ok = $kernel->fsmTransition('items', 2, 'nonexistent_state');
assert_eq($ok, false, 'fsmTransition: unknown state returns false');
$state = $kernel->db->querySingle("SELECT state FROM items WHERE id = 2");
assert_eq($state, 'active', 'fsmTransition: state unchanged after invalid');

// ── 4. FSM Matrix Structure ──────────────────────────────────────────────

echo "\n  FSM Matrix Structure\n";

$fsm = $kernel->matrix('fsm');
assert_true(isset($fsm['idle']['buttons']), 'idle has buttons');
assert_eq(count($fsm['idle']['buttons']), 1, 'idle: 1 button (Start)');
assert_eq($fsm['idle']['buttons'][0]['state'], 'active', 'idle → active');
assert_eq(count($fsm['active']['buttons']), 2, 'active: 2 buttons (Close, Pause)');
assert_eq(count($fsm['closed']['buttons']), 0, 'closed: 0 buttons (terminal)');

foreach ($fsm as $key => $sig) {
    assert_true(isset($sig['label'], $sig['color'], $sig['icon']), "state '$key' has label/color/icon");
}

// ── 5. Kanban Grouping ───────────────────────────────────────────────────

echo "\n  Kanban Grouping\n";

$allItems = [];
$result = $kernel->db->query("SELECT * FROM items");
while ($r = $result->fetchArray(SQLITE3_ASSOC)) $allItems[] = $r;

$byState = [];
foreach ($fsm as $key => $sig) $byState[$key] = [];
foreach ($allItems as $r) {
    $s = $r['state'];
    if (isset($byState[$s])) $byState[$s][] = $r;
}

assert_eq(count($byState['active']), 2, 'kanban: 2 in active');
assert_eq(count($byState['closed']), 1, 'kanban: 1 in closed');
assert_eq(count($byState['idle']), 0, 'kanban: 0 in idle');

// ── 6. Entity Matrix ────────────────────────────────────────────────────

echo "\n  Entity Matrix\n";

$entity = $kernel->matrix('entities');
assert_eq($entity['title']['type'], 'text', 'title is text type');
assert_eq($entity['price']['type'], 'money', 'price is money type');
assert_eq($entity['state']['type'], 'badge', 'state is badge type');

// ── 7. Self-documenting API ──────────────────────────────────────────────

echo "\n  Self-documenting API\n";

$commands = $kernel->matrix('commands');
foreach ($commands as $key => $cmd) {
    assert_true(isset($cmd['label']), "cmd '$key' has label");
    assert_true(isset($cmd['icon']), "cmd '$key' has icon");
    assert_true(isset($cmd['type']), "cmd '$key' has type");
    assert_true(in_array($cmd['type'], ['query', 'exec', 'raw']), "cmd '$key' type is valid");
}

// ── Cleanup ──────────────────────────────────────────────────────────────
@unlink($testDbPath);

// ── Summary ──────────────────────────────────────────────────────────────
echo "\n" . str_repeat('═', 50) . "\n";
echo "🧪 Results: {$passed} passed, {$failed} failed\n";
echo str_repeat('═', 50) . "\n\n";

exit($failed > 0 ? 1 : 0);

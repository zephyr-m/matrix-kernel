#!/usr/bin/env php
<?php
/**
 * Matrix Kernel — Auto Tests v2
 * Run: php test.php
 *
 * Tests: Storage adapter, Command engine, FSM engine, Action engine, Kanban grouping.
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
        'handler' => fn($storage, $p, $kernel) => ['pong' => true, 'name' => $kernel->name],
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
    'active'  => ['label' => 'Active', 'color' => 'green', 'icon' => 'ti-bolt', 'buttons' => [
        ['state' => 'closed', 'label' => 'Close', 'class' => 'btn-outline-red', 'icon' => 'ti-x'],
        ['state' => 'idle',   'label' => 'Pause', 'class' => 'btn-outline-yellow','icon' => 'ti-pause'],
    ]],
    'closed'  => ['label' => 'Closed', 'color' => 'red', 'icon' => 'ti-check', 'buttons' => [],
        'onEnter' => fn($storage, $id, $state, $kernel) => ['ok' => true, 'closed' => true],
    ],
];

$TEST_ACTIONS = [
    'buy' => [
        'label'   => 'Buy',
        'balance' => fn($storage, $ctx, $kernel) => ($ctx['current'] ?? 0) - ($ctx['total'] ?? 0),
        'record'  => fn($storage, $ctx, $kernel) => $storage->exec(
            "INSERT INTO items (title, state) VALUES (:title, 'active')",
            ['title' => 'bought:' . ($ctx['pair'] ?? '')]
        ),
    ],
    'sell' => [
        'label'   => 'Sell',
        'balance' => fn($storage, $ctx, $kernel) => ($ctx['current'] ?? 0) + ($ctx['total'] ?? 0),
        'record'  => fn($storage, $ctx, $kernel) => $storage->exec(
            "UPDATE items SET state = 'closed' WHERE title = :title",
            ['title' => 'bought:' . ($ctx['pair'] ?? '')]
        ),
    ],
];

$TEST_ENTITY = [
    'id'    => ['label' => 'ID',      'type' => 'number'],
    'title' => ['label' => 'Название','type' => 'text'],
    'state' => ['label' => 'Статус',  'type' => 'badge'],
    'price' => ['label' => 'Цена',    'type' => 'money'],
];

// ── Create kernel ────────────────────────────────────────────────────────

echo "\n🧪 Matrix Kernel Tests v2\n";

$testDbPath = sys_get_temp_dir() . '/mk_test_' . getmypid() . '.db';
@unlink($testDbPath);

$kernel = new MatrixKernel([
    'name'     => 'test-app',
    'db'       => $testDbPath,
    'commands' => $TEST_COMMANDS,
    'fsm'      => $TEST_FSM,
    'actions'  => $TEST_ACTIONS,
    'entities' => $TEST_ENTITY,
    'menu'     => [],
    'layout'   => [],
]);

// Create test table
$kernel->storage->exec("CREATE TABLE items (id INTEGER PRIMARY KEY AUTOINCREMENT, title TEXT, state TEXT DEFAULT 'idle', price REAL DEFAULT 0)");

// ── 1. Storage Adapter ───────────────────────────────────────────────────

echo "\n  Storage Adapter\n";
assert_true($kernel->storage instanceof MatrixStorage, 'storage implements MatrixStorage');
assert_true($kernel->storage instanceof SqliteStorage, 'storage is SqliteStorage');

$kernel->storage->exec("INSERT INTO items (title, state, price) VALUES (:t, :s, :p)", ['t' => 'Alpha', 's' => 'active', 'p' => 100]);
$kernel->storage->exec("INSERT INTO items (title, state, price) VALUES (:t, :s, :p)", ['t' => 'Beta', 's' => 'idle', 'p' => 200]);
$kernel->storage->exec("INSERT INTO items (title, state, price) VALUES (:t, :s, :p)", ['t' => 'Gamma', 's' => 'active', 'p' => 50]);

$rows = $kernel->storage->query("SELECT * FROM items WHERE state = :s", ['s' => 'active']);
assert_eq(count($rows), 2, 'query returns 2 active items');

$count = $kernel->storage->scalar("SELECT COUNT(*) FROM items");
assert_eq((int)$count, 3, 'scalar returns count');

$changes = $kernel->storage->exec("UPDATE items SET price = 999 WHERE id = :id", ['id' => 1]);
assert_eq($changes, 1, 'exec returns changes count');

$price = $kernel->storage->scalar("SELECT price FROM items WHERE id = 1");
assert_eq((int)$price, 999, 'exec actually updated');

// ── 2. Kernel Init ───────────────────────────────────────────────────────

echo "\n  Kernel Init\n";
assert_eq($kernel->name, 'test-app', 'kernel name');
assert_eq(count($kernel->matrix('commands')), 4, 'commands: 4');
assert_eq(count($kernel->matrix('fsm')), 3, 'fsm: 3 states');
assert_eq(count($kernel->matrix('actions')), 2, 'actions: 2 (buy/sell)');
assert_eq(count($kernel->matrix('entities')), 4, 'entities: 4 columns');
assert_eq($kernel->matrix('nonexistent'), [], 'unknown matrix = []');

// No hardcoded matrix keys — custom key works
$kernel2 = new MatrixKernel([
    'name' => 'custom',
    'db' => $testDbPath,
    'my_custom_matrix' => ['a' => 1, 'b' => 2],
]);
assert_eq($kernel2->matrix('my_custom_matrix'), ['a' => 1, 'b' => 2], 'custom matrix key accepted');

// ── 3. Command Engine ────────────────────────────────────────────────────

echo "\n  Command Engine\n";

// Raw command receives kernel
$handler = $TEST_COMMANDS['ping']['handler'];
$result = $handler($kernel->storage, [], $kernel);
assert_eq($result['pong'], true, 'ping: pong');
assert_eq($result['name'], 'test-app', 'ping: handler receives kernel');

// Query
$rows = $kernel->storage->query($TEST_COMMANDS['get_items']['sql'], ['state' => 'active', 'limit' => 50]);
assert_eq(count($rows), 2, 'query: 2 active');
assert_eq($rows[0]['title'], 'Gamma', 'query: DESC order');

// Exec
$kernel->storage->exec($TEST_COMMANDS['set_state']['sql'], ['id' => 1, 'state' => 'closed']);
$state = $kernel->storage->scalar("SELECT state FROM items WHERE id = 1");
assert_eq($state, 'closed', 'exec: state updated');

// ── 4. FSM Engine ────────────────────────────────────────────────────────

echo "\n  FSM Engine\n";

// Valid transition
$result = $kernel->fsmTransition('fsm', 'items', 2, 'active');
assert_eq($result['ok'], true, 'fsm: valid transition ok');
$state = $kernel->storage->scalar("SELECT state FROM items WHERE id = 2");
assert_eq($state, 'active', 'fsm: state updated in storage');

// Invalid transition
$result = $kernel->fsmTransition('fsm', 'items', 2, 'nonexistent');
assert_eq($result['ok'], false, 'fsm: invalid state rejected');
$state = $kernel->storage->scalar("SELECT state FROM items WHERE id = 2");
assert_eq($state, 'active', 'fsm: state unchanged after reject');

// onEnter handler called
$result = $kernel->fsmTransition('fsm', 'items', 2, 'closed');
assert_eq($result['ok'], true, 'fsm: closed transition ok');
$state = $kernel->storage->scalar("SELECT state FROM items WHERE id = 2");
assert_eq($state, 'closed', 'fsm: onEnter handler + state updated');

// ── 5. Action Engine ────────────────────────────────────────────────────

echo "\n  Action Engine\n";

// Buy action
$result = $kernel->executeAction('actions', 'buy', ['current' => 1000, 'total' => 100, 'pair' => 'BTC']);
assert_eq($result['ok'], true, 'action buy: ok');
assert_eq($result['results']['balance'], 900, 'action buy: balance = 1000 - 100');
assert_eq($result['results']['label'], 'Buy', 'action buy: non-callable fields returned as-is');
assert_true($result['results']['record'] >= 0, 'action buy: record handler executed');

// Verify record was created
$bought = $kernel->storage->scalar("SELECT COUNT(*) FROM items WHERE title = 'bought:BTC'");
assert_true((int)$bought > 0, 'action buy: record created in storage');

// Sell action  
$result = $kernel->executeAction('actions', 'sell', ['current' => 900, 'total' => 150, 'pair' => 'BTC']);
assert_eq($result['ok'], true, 'action sell: ok');
assert_eq($result['results']['balance'], 1050, 'action sell: balance = 900 + 150');

// Verify record was updated
$state = $kernel->storage->scalar("SELECT state FROM items WHERE title = 'bought:BTC'");
assert_eq($state, 'closed', 'action sell: record updated in storage');

// Unknown action
$result = $kernel->executeAction('actions', 'short', []);
assert_eq($result['ok'], false, 'action unknown: rejected');

// ── 6. FSM Structure ────────────────────────────────────────────────────

echo "\n  FSM Structure\n";

$fsm = $kernel->matrix('fsm');
assert_eq(count($fsm['idle']['buttons']), 1, 'idle: 1 button');
assert_eq($fsm['idle']['buttons'][0]['state'], 'active', 'idle → active');
assert_eq(count($fsm['active']['buttons']), 2, 'active: 2 buttons');
assert_eq(count($fsm['closed']['buttons']), 0, 'closed: terminal');
assert_true(is_callable($fsm['closed']['onEnter']), 'closed: onEnter is callable');

foreach ($fsm as $key => $sig) {
    assert_true(isset($sig['label'], $sig['color'], $sig['icon']), "state '$key': label/color/icon");
}

// ── 7. Kanban Grouping ──────────────────────────────────────────────────

echo "\n  Kanban Grouping\n";

$all = $kernel->storage->query("SELECT * FROM items");
$byState = [];
foreach ($fsm as $key => $sig) $byState[$key] = [];
foreach ($all as $r) {
    $s = $r['state'];
    if (isset($byState[$s])) $byState[$s][] = $r;
}

$totalGrouped = array_sum(array_map('count', $byState));
assert_eq($totalGrouped, count($all), 'kanban: all items grouped');
assert_true(count($byState['closed']) >= 2, 'kanban: closed has items');
assert_true(count($byState['active']) >= 1, 'kanban: active has items');

// ── Cleanup ──────────────────────────────────────────────────────────────
@unlink($testDbPath);

echo "\n" . str_repeat('═', 50) . "\n";
echo "🧪 Results: {$passed} passed, {$failed} failed\n";
echo str_repeat('═', 50) . "\n\n";

exit($failed > 0 ? 1 : 0);

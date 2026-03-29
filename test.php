#!/usr/bin/env php
<?php
/**
 * Matrix Kernel — Pure Resolver Tests
 * Run: php test.php
 *
 * Тестирует ядро как универсальный resolver. Ноль доменной логики.
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
        echo "  ❌ {$name}\n    expected: " . json_encode($expected) . "\n    got:      " . json_encode($actual) . "\n";
        $failed++;
    }
}

function assert_true($val, string $name): void { assert_eq((bool)$val, true, $name); }

$k = new MatrixKernel();

// ── 1. resolve: матрица + шаблон → результат ─────────────────────────────
echo "\n🧪 resolve()\n";

// Шаблон считает сумму значений
$matrix = ['a' => 10, 'b' => 20, 'c' => 30];
$result = $k->resolve($matrix, fn($m, $ctx, $k) => array_sum($m));
assert_eq($result, 60, 'resolve: sum template');

// Шаблон фильтрует
$matrix = ['x' => 1, 'y' => 2, 'z' => 3];
$result = $k->resolve($matrix, fn($m, $ctx, $k) => array_filter($m, fn($v) => $v > 1));
assert_eq($result, ['y' => 2, 'z' => 3], 'resolve: filter template');

// Контекст передаётся
$result = $k->resolve([], fn($m, $ctx, $k) => $ctx['name'] ?? 'none', ['name' => 'test']);
assert_eq($result, 'test', 'resolve: context passed');

// Kernel передаётся
$result = $k->resolve([], fn($m, $ctx, $k) => $k instanceof MatrixKernel);
assert_eq($result, true, 'resolve: kernel passed');

// Шаблон возвращает HTML строку
$matrix = ['title' => 'Hello', 'color' => 'red'];
$result = $k->resolve($matrix, fn($m, $ctx, $k) => "<h1 style='color:{$m['color']}'>{$m['title']}</h1>");
assert_eq($result, "<h1 style='color:red'>Hello</h1>", 'resolve: HTML template');

// ── 2. resolveEntry: один ключ из матрицы ────────────────────────────────
echo "\n🧪 resolveEntry()\n";

$menu = [
    'home'  => ['label' => 'Home',  'icon' => '🏠'],
    'items' => ['label' => 'Items', 'icon' => '📦'],
];

$result = $k->resolveEntry($menu, 'home', fn($entry, $key, $ctx, $k) => "<a>{$entry['icon']} {$entry['label']}</a>");
assert_eq($result, '<a>🏠 Home</a>', 'resolveEntry: renders one entry');

$result = $k->resolveEntry($menu, 'nonexistent', fn($e, $k, $c, $kr) => 'found');
assert_eq($result, null, 'resolveEntry: missing key → null');

// ── 3. walk: каждую запись через шаблон ──────────────────────────────────
echo "\n🧪 walk()\n";

$result = $k->walk($menu, fn($entry, $key, $ctx, $k) => $entry['label']);
assert_eq($result, ['home' => 'Home', 'items' => 'Items'], 'walk: extract labels');

// Walk с context
$result = $k->walk(
    ['a' => ['v' => 1], 'b' => ['v' => 2]],
    fn($entry, $key, $ctx, $k) => $entry['v'] * $ctx['multiplier'],
    ['multiplier' => 10]
);
assert_eq($result, ['a' => 10, 'b' => 20], 'walk: context multiplier');

// Walk пустой матрицы
$result = $k->walk([], fn($e, $k, $c, $kr) => 'x');
assert_eq($result, [], 'walk: empty matrix → empty result');

// ── 4. hydrate: развернуть callable в данные ─────────────────────────────
echo "\n🧪 hydrate()\n";

$entry = [
    'label'   => 'Buy',
    'balance' => fn($ctx, $k) => $ctx['current'] - $ctx['amount'],
    'time'    => fn($ctx, $k) => 'now',
    'static'  => 42,
];

$result = $k->hydrate($entry, ['current' => 1000, 'amount' => 100]);
assert_eq($result['label'], 'Buy', 'hydrate: static value untouched');
assert_eq($result['balance'], 900, 'hydrate: callable resolved');
assert_eq($result['time'], 'now', 'hydrate: another callable');
assert_eq($result['static'], 42, 'hydrate: int untouched');

// Hydrate без контекста
$entry2 = ['a' => fn($ctx, $k) => $ctx['x'] ?? 'default'];
$result = $k->hydrate($entry2);
assert_eq($result['a'], 'default', 'hydrate: no ctx → default');

// ── 5. pipe: цепочка шаблонов ────────────────────────────────────────────
echo "\n🧪 pipe()\n";

$result = $k->pipe(
    10,
    [
        fn($d, $ctx, $k) => $d * 2,      // 20
        fn($d, $ctx, $k) => $d + 5,      // 25
        fn($d, $ctx, $k) => "result:$d", // "result:25"
    ]
);
assert_eq($result, 'result:25', 'pipe: chain 3 transforms');

// Pipe с контекстом
$result = $k->pipe(
    ['items' => [1,2,3]],
    [
        fn($d, $ctx, $k) => array_merge($d, ['count' => count($d['items'])]),
        fn($d, $ctx, $k) => $d['count'] * $ctx['multiplier'],
    ],
    ['multiplier' => 100]
);
assert_eq($result, 300, 'pipe: context in pipeline');

// Pipe пустой
$result = $k->pipe('hello', []);
assert_eq($result, 'hello', 'pipe: empty pipeline → passthrough');

// ── 6. Composition: resolve + walk + hydrate вместе ──────────────────────
echo "\n🧪 Composition\n";

// Реальный пример: FSM матрица → resolve buttons для записи
$fsm = [
    'idle'   => ['label' => 'Idle',   'buttons' => [['to' => 'active', 'text' => 'Start']]],
    'active' => ['label' => 'Active', 'buttons' => [['to' => 'closed', 'text' => 'Close']]],
    'closed' => ['label' => 'Closed', 'buttons' => []],
];

// walk через FSM → для каждого состояния count записей
$items = [['state' => 'idle'], ['state' => 'active'], ['state' => 'active'], ['state' => 'closed']];
$counts = $k->walk($fsm, fn($entry, $key, $ctx, $k) =>
    count(array_filter($ctx['items'], fn($i) => $i['state'] === $key)),
    ['items' => $items]
);
assert_eq($counts, ['idle' => 1, 'active' => 2, 'closed' => 1], 'composition: FSM counts');

// resolveEntry для конкретного состояния → его кнопки
$buttons = $k->resolveEntry($fsm, 'active', fn($e, $key, $ctx, $k) => $e['buttons']);
assert_eq(count($buttons), 1, 'composition: active has 1 button');
assert_eq($buttons[0]['to'], 'closed', 'composition: button points to closed');

// hydrate запись с callable → resolve
$action = [
    'type'  => 'buy',
    'price' => fn($ctx, $k) => $ctx['ticker'],
    'total' => fn($ctx, $k) => $ctx['ticker'] * $ctx['amount'],
];
$resolved = $k->hydrate($action, ['ticker' => 65000, 'amount' => 0.01]);
assert_eq($resolved['type'], 'buy', 'composition: hydrate type');
assert_eq($resolved['price'], 65000, 'composition: hydrate price');
assert_eq($resolved['total'], 650.0, 'composition: hydrate total');

// pipe: matrix → filter → count → format
$result = $k->pipe(
    $items,
    [
        fn($d, $ctx, $k) => array_filter($d, fn($i) => $i['state'] === 'active'),
        fn($d, $ctx, $k) => count($d),
        fn($d, $ctx, $k) => "Active: {$d}",
    ]
);
assert_eq($result, 'Active: 2', 'composition: pipe filter+count+format');

// ── Summary ──────────────────────────────────────────────────────────────
echo "\n" . str_repeat('═', 50) . "\n";
echo "🧪 Results: {$passed} passed, {$failed} failed\n";
echo str_repeat('═', 50) . "\n\n";

exit($failed > 0 ? 1 : 0);

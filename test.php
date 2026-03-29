#!/usr/bin/env php
<?php
/**
 * Matrix Kernel — Tests
 * php test.php
 */

require __DIR__ . '/kernel.php';

$passed = 0;
$failed = 0;

function assert_eq($a, $b, string $n): void {
    global $passed, $failed;
    $a === $b ? (++$passed && print("  ✅ {$n}\n")) : (++$failed && print("  ❌ {$n}\n    expected: " . json_encode($b) . "\n    got:      " . json_encode($a) . "\n"));
}

$k = new MatrixKernel();

// ── resolve ──────────────────────────────────────────────────────────────
echo "\n🧪 resolve\n";

assert_eq($k->resolve(['a' => 10, 'b' => 20], fn($m, $c, $k) => array_sum($m)), 30, 'sum');
assert_eq($k->resolve(['x' => 1], fn($m, $c, $k) => $c['n'] ?? 0, ['n' => 42]), 42, 'context');
assert_eq($k->resolve([], fn($m, $c, $k) => $k instanceof MatrixKernel), true, 'kernel ref');
assert_eq($k->resolve(['t' => 'Hi'], fn($m, $c, $k) => "<b>{$m['t']}</b>"), '<b>Hi</b>', 'html');
assert_eq($k->resolve(['a' => 1, 'b' => 2, 'c' => 3], fn($m, $c, $k) => array_filter($m, fn($v) => $v > 1)), ['b' => 2, 'c' => 3], 'filter');

// ── hydrate ──────────────────────────────────────────────────────────────
echo "\n🧪 hydrate\n";

$entry = [
    'label'   => 'Buy',
    'balance' => fn($c, $k) => $c['cur'] - $c['amt'],
    'static'  => 42,
];
$r = $k->hydrate($entry, ['cur' => 1000, 'amt' => 100]);
assert_eq($r['label'], 'Buy', 'static untouched');
assert_eq($r['balance'], 900, 'callable resolved');
assert_eq($r['static'], 42, 'int untouched');
assert_eq($k->hydrate(['a' => fn($c, $k) => $c['x'] ?? 'def'])['a'], 'def', 'no ctx default');

// ── composition: реальные сценарии из resolve + hydrate ──────────────────
echo "\n🧪 composition\n";

// FSM: resolve считает записи по состояниям
$fsm = ['idle' => ['l' => 'Idle'], 'active' => ['l' => 'Active'], 'closed' => ['l' => 'Closed']];
$items = [['s' => 'idle'], ['s' => 'active'], ['s' => 'active']];
$counts = $k->resolve($fsm, fn($m, $c, $k) => array_map(
    fn($key) => count(array_filter($c['items'], fn($i) => $i['s'] === $key)),
    array_combine(array_keys($m), array_keys($m))
), ['items' => $items]);
assert_eq($counts, ['idle' => 1, 'active' => 2, 'closed' => 0], 'fsm counts');

// Action: hydrate order матрицу
$action = ['type' => 'buy', 'total' => fn($c, $k) => $c['price'] * $c['qty']];
$r = $k->hydrate($action, ['price' => 65000, 'qty' => 0.01]);
assert_eq($r['total'], 650.0, 'order total');

// Pipeline: resolve чейнит через reduce
$data = [1, 2, 3, 4, 5];
$pipeline = [fn($d) => array_filter($d, fn($v) => $v > 2), fn($d) => array_sum($d)];
$result = array_reduce($pipeline, fn($d, $fn) => $fn($d), $data);
assert_eq($result, 12, 'pipeline via reduce (no kernel needed)');

// Walk: foreach + resolve (no kernel needed)
$menu = ['home' => ['icon' => '🏠'], 'items' => ['icon' => '📦']];
$labels = array_map(fn($e) => $e['icon'], $menu);
assert_eq($labels, ['home' => '🏠', 'items' => '📦'], 'walk via array_map (no kernel needed)');

// ── Summary ──────────────────────────────────────────────────────────────
echo "\n" . str_repeat('═', 50) . "\n";
echo "🧪 Results: {$passed} passed, {$failed} failed\n";
echo str_repeat('═', 50) . "\n\n";
exit($failed > 0 ? 1 : 0);

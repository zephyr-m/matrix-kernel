#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../src/Kernel.php';

use Zephyr\MatrixKernel\Kernel;

$passed = 0;
$failed = 0;

function assert_eq(mixed $a, mixed $b, string $n): void {
    global $passed, $failed;
    $a === $b
        ? (++$passed && print("  ✅ {$n}\n"))
        : (++$failed && print("  ❌ {$n}\n    exp: " . json_encode($b) . "\n    got: " . json_encode($a) . "\n"));
}

$k = new Kernel();

// ── resolve ──────────────────────────────────────────────────────────────
echo "\n🧪 resolve\n";

assert_eq($k->resolve(['a' => 10, 'b' => 20], fn($m, $c, $k) => array_sum($m)), 30, 'sum');
assert_eq($k->resolve(['x' => 1], fn($m, $c, $k) => $c['n'] ?? 0, ['n' => 42]), 42, 'context');
assert_eq($k->resolve([], fn($m, $c, $k) => $k instanceof Kernel), true, 'kernel ref');
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

// ── composition ──────────────────────────────────────────────────────────
echo "\n🧪 composition\n";

$fsm = ['idle' => ['l' => 'Idle'], 'active' => ['l' => 'Active'], 'closed' => ['l' => 'Closed']];
$items = [['s' => 'idle'], ['s' => 'active'], ['s' => 'active']];
$counts = $k->resolve($fsm, fn($m, $c, $k) => array_map(
    fn($key) => count(array_filter($c['items'], fn($i) => $i['s'] === $key)),
    array_combine(array_keys($m), array_keys($m))
), ['items' => $items]);
assert_eq($counts, ['idle' => 1, 'active' => 2, 'closed' => 0], 'fsm counts');

$action = ['type' => 'buy', 'total' => fn($c, $k) => $c['price'] * $c['qty']];
$r = $k->hydrate($action, ['price' => 65000, 'qty' => 0.01]);
assert_eq($r['total'], 650.0, 'order total');

// ── edge cases ───────────────────────────────────────────────────────────
echo "\n🧪 edge cases\n";

assert_eq($k->resolve([], fn($m, $c, $k) => count($m)), 0, 'resolve: empty matrix');
assert_eq($k->hydrate([]), [], 'hydrate: empty entry');
assert_eq($k->hydrate(['k' => fn($c, $k) => $k instanceof Kernel])['k'], true, 'hydrate: kernel ref in callable');

// ── summary ──────────────────────────────────────────────────────────────
echo "\n" . str_repeat('═', 50) . "\n";
echo "🧪 Results: {$passed} passed, {$failed} failed\n";
echo str_repeat('═', 50) . "\n\n";
exit($failed > 0 ? 1 : 0);

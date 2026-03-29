# Matrix Kernel

> Матрица + шаблон → результат. Больше ничего.

## Что это

38 строк. 2 метода. Универсальный resolver.

```php
$k = new MatrixKernel();

// resolve: применить шаблон к матрице
$k->resolve($matrix, fn($m, $ctx, $k) => /* что угодно */, $ctx);

// hydrate: развернуть callable внутри записи в данные
$k->hydrate($entry, $ctx);
```

Ядро не знает ни про таблицы, ни про канбан, ни про API, ни про FSM, ни про БД.
Оно знает: **данные + функция → результат**.

## Почему 2 метода

| Метод | Что делает | Аналог |
|-------|-----------|--------|
| `resolve` | Внешняя логика к данным | `template(matrix)` |
| `hydrate` | Внутренние callable → данные | `entry.map(is_fn ? call : keep)` |

Всё остальное — стандартный PHP:
- Walk = `array_map`
- Pipe = `array_reduce`
- Lookup = `$matrix[$key]`

## Как строить движки поверх

```php
$k = new MatrixKernel();

// CRUD движок = resolve + SQL шаблон
$html = $k->resolve($entity, fn($m, $ctx, $k) => /* рендер таблицы */);

// FSM движок = resolve для подсчёта + hydrate для переходов
$counts = $k->resolve($fsm, fn($m, $ctx, $k) => /* count по state */);

// Order движок = hydrate для вычислений
$order = $k->hydrate($action, ['price' => 65000, 'qty' => 0.01]);
// → ['type' => 'buy', 'total' => 650.0]

// API движок = resolve для dispatch
$result = $k->resolve($commands, fn($m, $ctx, $k) => $m[$ctx['cmd']]['handler']($ctx));
```

## Архитектура

```
MatrixKernel (38 строк)
├── resolve(matrix, template, ctx)
└── hydrate(entry, ctx)

Движки строятся поверх:
├── engines/crud.php      ← entity + resolve
├── engines/fsm.php       ← states + resolve + hydrate
├── engines/api.php       ← commands + resolve
├── engines/form.php      ← fields + resolve
├── engines/layout.php    ← menu + resolve
└── engines/migration.php ← entity + resolve → SQL
```

## Принцип

```
Ядро = resolve + hydrate
Движок = ядро + матрица + шаблон
Приложение = набор движков
```

Ядро никогда не меняется. Движки переиспользуются. Приложение = только матрицы.

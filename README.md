# Matrix Kernel

> Матрица + шаблон → результат. Больше ничего.

## Установка

```bash
composer require zephyr/matrix-kernel
```

## Использование

```php
use Zephyr\MatrixKernel\Kernel;

$k = new Kernel();

// resolve: применить шаблон к матрице
$result = $k->resolve($matrix, fn($m, $ctx, $k) => /* что угодно */, $ctx);

// hydrate: развернуть callable в данные
$data = $k->hydrate(['price' => fn($c, $k) => $c['ticker'] * $c['qty']], $ctx);
```

## Зачем

38 строк. 2 метода. Ноль зависимостей.

Ядро не знает ни про таблицы, ни про канбан, ни про API, ни про FSM.
Оно знает: **данные + функция → результат**.

Движки (CRUD, FSM, Form, Layout) строятся **поверх** ядра.

## API

| Метод | Сигнатура | Что делает |
|-------|-----------|------------|
| `resolve` | `(array $matrix, callable $template, array $ctx = []): mixed` | Внешняя логика к данным |
| `hydrate` | `(array $entry, array $ctx = []): array` | Callable → данные |

## Лицензия

MIT

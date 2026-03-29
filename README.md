# Matrix Kernel

> Один раз написал ядро — потом собираешь что угодно, дёргая матрицы.

## Что это

Универсальный движок, который читает **декларативные матрицы** (JSON/PHP/JS) и генерирует из них:
- **API** (RPC endpoint)
- **UI** (таблицы, канбан, формы, дашборды)
- **Поведение** (FSM, CRUD, transitions)

Новый проект = набор матриц. **Ноль кода.**

## Почему

Мы построили Spider и MoneyBot и увидели одинаковый скелет:

```
matrices/  →  engine/  →  api.php  →  pages/
```

Entity матрица → таблица. FSM матрица → канбан. Command матрица → API.
Каждый раз одна и та же работа. Matrix Kernel делает её один раз.

## Доказательство

| Проект   | Матрицы                        | Что получилось              |
|----------|--------------------------------|-----------------------------|
| Spider   | jobs, states, sources, menu    | CRM для вакансий            |
| MoneyBot | pairs, signals, strategies     | Торговый бот с канбаном     |

Обоим проектам нужно одно и то же: CRUD + FSM + RPC + Layout.

## Типы матриц

```
entity   — что существует (pairs, jobs, users)
fsm      — как оно живёт (idle → active → closed)
command  — что можно делать (get_X, set_X, delete_X)
layout   — как выглядит (menu, widgets, pages)
form     — как вводить (fields, validation, defaults)
```

## Архитектура

```
matrix-kernel/
├── kernel.php          ← точка входа: load matrices → dispatch
├── engines/
│   ├── entity.php      ← CRUD: table, card, detail
│   ├── fsm.php         ← Kanban, transitions, buttons
│   ├── command.php     ← RPC: validate → bind → execute
│   ├── layout.php      ← Menu, sidebar, pages, routing
│   └── form.php        ← Input generation, validation
├── renderers/
│   ├── table.php       ← <table> из entity матрицы
│   ├── kanban.php      ← Kanban из FSM матрицы
│   ├── cards.php       ← Card grid из entity
│   └── dashboard.php   ← Stat cards + widgets
├── api.php             ← Универсальный RPC endpoint
└── app.php             ← Универсальный UI endpoint
```

## Как используется

```php
// moneybot/index.php — весь проект:
require 'matrix-kernel/kernel.php';

$app = new MatrixKernel([
    'name'     => 'moneyBot',
    'db'       => 'moneybot.db',
    'entities' => require 'matrices/pairs.php',
    'fsm'      => require 'matrices/signals.php',
    'commands' => require 'matrices/api_commands.php',
    'layout'   => require 'matrices/layout.php',
    'menu'     => require 'matrices/menu.php',
]);

$app->run();  // → API + UI + всё поведение
```

```php
// spider/index.php — другой проект, тот же kernel:
$app = new MatrixKernel([
    'name'     => 'Spider',
    'db'       => 'spider.db',
    'entities' => require 'matrices/entities.php',
    'fsm'      => require 'matrices/states.php',
    'commands' => require 'matrices/api_commands.php',
    'layout'   => require 'matrices/layout.php',
    'menu'     => require 'matrices/menu.php',
]);

$app->run();
```

## Принцип

```
Поведение = данные
Логика = интерпретатор
Расширение = строка в матрице
```

Engine **никогда** не знает что такое "позиция", "вакансия" или "пара".
Он знает: entity, state, command, field.

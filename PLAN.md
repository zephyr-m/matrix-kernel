# Plan — Matrix Kernel

## Статус

- [x] **Ядро** — `resolve()` + `hydrate()`, 38 строк, 13 тестов
- [ ] **Движки** — строятся поверх ядра
- [ ] **Приложение** — MoneyBot/Spider переведены на kernel + engines

---

## Фаза 1: Движки

Каждый движок = файл, который использует `resolve()` + `hydrate()` + стандартный PHP.

### 1.1 Storage
- [ ] `MatrixStorage` interface (query/exec/scalar)
- [ ] `SqliteStorage` — реализация
- [ ] Передаётся в ctx, ядро не знает

### 1.2 API Engine
- [ ] Command matrix + resolve → RPC endpoint
- [ ] Self-documenting (no cmd → list)
- [ ] Param binding + validation

### 1.3 FSM Engine
- [ ] State matrix + resolve → transitions
- [ ] onEnter handlers через hydrate
- [ ] Validation: target state exists in matrix

### 1.4 CRUD Engine
- [ ] Entity matrix → 5 SQL команд (list/get/create/update/delete)
- [ ] Автогенерация через resolve

### 1.5 Migration Engine
- [ ] Entity matrix → CREATE TABLE
- [ ] Type map (text→TEXT, money→REAL, ...)

### 1.6 Form Engine
- [ ] Field matrix + resolve → HTML `<form>`
- [ ] Типы: text, number, select, date
- [ ] Select options из другой матрицы

### 1.7 Layout Engine
- [ ] Menu matrix + resolve → sidebar + routing
- [ ] Page dispatch

---

## Фаза 2: Перевод проектов

### 2.1 MoneyBot → kernel + engines
- [ ] admin/ использует engines/ вместо своего кода
- [ ] Матрицы остаются, код уходит

### 2.2 Spider → kernel + engines
- [ ] Тот же набор engines, другие матрицы
- [ ] Оба проекта на одном kernel

---

## Фаза 3: Генераторы

- [ ] Entity → auto-CRUD + auto-migration + auto-form
- [ ] Новый проект = `php init.php` + матрицы

---

## Принципы

1. **Ядро не меняется** — 2 метода, навсегда
2. **Движок = 1 файл** — ядро + матрица + шаблон
3. **Приложение = только матрицы**
4. **Тесты из матриц** — матрица описывает поведение, тест проверяет

## Метрика

> Новый проект за 30 минут: пишешь матрицы, подключаешь движки, запускаешь.

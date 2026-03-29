# Plan — Matrix Kernel

## Фаза 0: Экстракция (из Spider + MoneyBot)

Вытащить общий код из двух живых проектов. Не писать с нуля — рефакторить.

### 0.1 Command Engine
- [ ] Извлечь `api.php` паттерн (parse → validate → bind → execute → respond)
- [ ] Унифицировать: query/exec/raw универсально
- [ ] Self-documenting: `GET /api.php` → список команд с label/icon
- [ ] Тесты: mock DB, проверка всех types

### 0.2 Entity Engine
- [ ] Auto-CRUD из entity матрицы (columns → CREATE TABLE)
- [ ] Рендер: table, cards, detail view
- [ ] Фильтры, сортировка, пагинация из матрицы

### 0.3 FSM Engine
- [ ] Kanban рендер из FSM матрицы (состояния → колонки)
- [ ] Transition handler (validate → update state → redirect)
- [ ] Кнопки из FSM.buttons
- [ ] Каунтеры по состояниям

### 0.4 Layout Engine
- [ ] Menu из матрицы (icon, label, page, badge)
- [ ] Sidebar widgets из матрицы
- [ ] Page routing (page key → file/renderer)
- [ ] Tabler CSS integration

### 0.5 Form Engine
- [ ] Input generation из field матрицы (type, label, default, validation)
- [ ] Select из entity матрицы (foreign key → dropdown)
- [ ] Submit → command engine

---

## Фаза 1: Kernel MVP

### 1.1 MatrixKernel class
- [ ] Constructor: принимает конфиг с матрицами
- [ ] Router: `?p=page` → layout engine, `/api.php?cmd=X` → command engine
- [ ] DB init: auto-create tables из entity матриц

### 1.2 Развёртывание
- [ ] MoneyBot переводится на kernel (матрицы остаются, код уходит)
- [ ] Spider переводится на kernel
- [ ] Оба работают через один kernel, разные матрицы

---

## Фаза 2: Генераторы

### 2.1 Auto-migration
- [ ] Entity матрица → `CREATE TABLE IF NOT EXISTS`
- [ ] Diff detection: матрица изменилась → ALTER TABLE

### 2.2 API documentation
- [ ] Command матрица → OpenAPI / Swagger auto-gen
- [ ] Playground page auto-generated

### 2.3 Dashboard generator
- [ ] Stat cards из entity (COUNT, SUM, AVG)
- [ ] Chart widgets из tick/time-series данных

---

## Фаза 3: Realtime

### 3.1 WS Engine
- [ ] WebSocket command матрица (type → handler)
- [ ] Broadcast engine
- [ ] Live update: entity change → WS push → UI update

### 3.2 Live Kanban
- [ ] Drag-and-drop → FSM transition через WS
- [ ] Realtime counters

---

## Принципы разработки

1. **Экстракция, не изобретение** — каждый engine сначала работает в Spider/MoneyBot, потом выносится
2. **Один engine, один файл** — никаких абстрактных классов ради абстракции
3. **Матрица = API контракт** — если матрица описывает entity, kernel обязан дать CRUD
4. **Zero config** — `new MatrixKernel(matrices)` и всё работает
5. **Тесты из матриц** — матрица описывает ожидаемое поведение, тесты генерируются

## Метрика успеха

> Новый проект (CRM, ERP, бот, что угодно) создаётся за 30 минут:
> пишешь матрицы, запускаешь kernel, получаешь рабочий API + UI.

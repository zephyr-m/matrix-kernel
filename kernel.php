<?php
/**
 * Matrix Kernel — Universal Matrix Resolver
 *
 * Ядро не знает ни про таблицы, ни канбан, ни API, ни FSM.
 * Оно знает: matrix + template + context → result.
 *
 * Движки (CRUD, FSM, Form, Layout) строятся ПОВЕРХ ядра.
 */

class MatrixKernel {

    /**
     * Resolve: пройти матрицу через шаблон.
     *
     * @param array    $matrix   — данные (entity, fsm, commands, что угодно)
     * @param callable $template — как обработать (fn($matrix, $ctx, $kernel) → result)
     * @param array    $ctx      — контекст (input, storage, config, что угодно)
     * @return mixed             — результат: HTML, array, string, что угодно
     */
    public function resolve(array $matrix, callable $template, array $ctx = []): mixed {
        return $template($matrix, $ctx, $this);
    }

    /**
     * Resolve entry: достать один ключ из матрицы и обработать.
     *
     * @param array    $matrix   — матрица
     * @param string   $key      — ключ в матрице
     * @param callable $template — обработчик одной записи
     * @param array    $ctx      — контекст
     * @return mixed
     */
    public function resolveEntry(array $matrix, string $key, callable $template, array $ctx = []): mixed {
        if (!isset($matrix[$key])) return null;
        return $template($matrix[$key], $key, $ctx, $this);
    }

    /**
     * Walk: пройти каждую запись матрицы через шаблон, собрать результаты.
     *
     * @param array    $matrix   — матрица
     * @param callable $template — fn($entry, $key, $ctx, $kernel) → result
     * @param array    $ctx      — контекст
     * @return array             — массив результатов
     */
    public function walk(array $matrix, callable $template, array $ctx = []): array {
        $results = [];
        foreach ($matrix as $key => $entry) {
            $results[$key] = $template($entry, $key, $ctx, $this);
        }
        return $results;
    }

    /**
     * Hydrate: развернуть матрицу — все callable значения вызвать, данные оставить.
     *
     * @param array $entry — запись матрицы (может содержать callable)
     * @param array $ctx   — контекст для callable
     * @return array       — развёрнутая запись (все значения = данные)
     */
    public function hydrate(array $entry, array $ctx = []): array {
        $result = [];
        foreach ($entry as $field => $value) {
            $result[$field] = is_callable($value) ? $value($ctx, $this) : $value;
        }
        return $result;
    }

    /**
     * Pipe: прогнать данные через цепочку шаблонов (pipeline).
     *
     * @param mixed $data      — входные данные
     * @param array $templates — массив callable: fn($data, $ctx, $kernel) → data
     * @param array $ctx       — контекст
     * @return mixed           — результат цепочки
     */
    public function pipe(mixed $data, array $templates, array $ctx = []): mixed {
        foreach ($templates as $template) {
            $data = $template($data, $ctx, $this);
        }
        return $data;
    }
}

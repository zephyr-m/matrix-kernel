<?php
/**
 * Matrix Kernel — Universal Matrix Resolver
 *
 * 2 метода. Всё остальное строится поверх.
 *
 * resolve — применить внешний шаблон к матрице
 * hydrate — развернуть внутренние callable в данные
 */

class MatrixKernel {

    /**
     * Применить шаблон к матрице.
     *
     * @param array    $matrix   — данные
     * @param callable $template — fn($matrix, $ctx, $kernel) → result
     * @param array    $ctx      — контекст
     */
    public function resolve(array $matrix, callable $template, array $ctx = []): mixed {
        return $template($matrix, $ctx, $this);
    }

    /**
     * Развернуть запись: callable → вызвать, данные → оставить.
     *
     * @param array $entry — запись матрицы
     * @param array $ctx   — контекст для callable
     */
    public function hydrate(array $entry, array $ctx = []): array {
        $out = [];
        foreach ($entry as $k => $v) {
            $out[$k] = is_callable($v) ? $v($ctx, $this) : $v;
        }
        return $out;
    }
}

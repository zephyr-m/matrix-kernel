<?php

declare(strict_types=1);

namespace Zephyr\MatrixKernel;

/**
 * Matrix Kernel — Universal Matrix Resolver
 *
 * 2 метода. Всё остальное строится поверх.
 *
 * resolve — применить внешний шаблон к матрице
 * hydrate — развернуть внутренние callable в данные
 *
 * @package zephyr/matrix-kernel
 */
class Kernel
{
    /**
     * Применить шаблон к матрице.
     *
     * @param array    $matrix   — данные (любая структура)
     * @param callable $template — fn(array $matrix, array $ctx, Kernel $kernel): mixed
     * @param array    $ctx      — контекст
     * @return mixed
     */
    public function resolve(array $matrix, callable $template, array $ctx = []): mixed
    {
        return $template($matrix, $ctx, $this);
    }

    /**
     * Развернуть запись: callable → вызвать, данные → оставить.
     *
     * @param array $entry — запись матрицы (ключ => значение|callable)
     * @param array $ctx   — контекст для callable
     * @return array
     */
    public function hydrate(array $entry, array $ctx = []): array
    {
        $out = [];
        foreach ($entry as $k => $v) {
            $out[$k] = is_callable($v) ? $v($ctx, $this) : $v;
        }
        return $out;
    }
}

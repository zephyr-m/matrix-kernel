<?php
/**
 * Table Renderer — рендерит <table> из entity матрицы
 *
 * Входные переменные:
 *   $entity — матрица колонок: ['name' => ['label' => 'Имя', 'type' => 'text'], ...]
 *   $rows   — данные из DB
 *   $options — доп. настройки (actions, fsm для бейджей)
 */

$fsm = $options['fsm'] ?? [];
$actions = $options['actions'] ?? [];
?>

<div class="table-responsive">
  <table class="table table-vcenter card-table">
    <thead>
      <tr>
        <?php foreach ($entity as $col => $def): ?>
          <th><?= $def['label'] ?? $col ?></th>
        <?php endforeach; ?>
        <?php if ($actions): ?><th>Действия</th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="<?= count($entity) + ($actions ? 1 : 0) ?>" class="text-center text-muted py-4">Нет данных</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <?php foreach ($entity as $col => $def):
            $val = $r[$col] ?? '—';
            $type = $def['type'] ?? 'text';
          ?>
            <td>
              <?php if ($type === 'badge' && isset($fsm[$val])): ?>
                <span class="badge bg-<?= $fsm[$val]['color'] ?? 'secondary' ?>">
                  <i class="<?= $fsm[$val]['icon'] ?? '' ?> me-1"></i><?= $fsm[$val]['label'] ?? $val ?>
                </span>
              <?php elseif ($type === 'money'): ?>
                $<?= number_format((float)$val, 2) ?>
              <?php elseif ($type === 'number'): ?>
                <?= number_format((float)$val, $def['precision'] ?? 2) ?>
              <?php elseif ($type === 'date'): ?>
                <small class="text-muted"><?= $val ?></small>
              <?php else: ?>
                <?= htmlspecialchars($val) ?>
              <?php endif; ?>
            </td>
          <?php endforeach; ?>
          <?php if ($actions): ?>
            <td>
              <?php foreach ($actions as $act):
                // Actions read from FSM buttons for current state
                if (isset($act['from_fsm']) && isset($fsm[$r['state']]['buttons'])):
                  foreach ($fsm[$r['state']]['buttons'] as $btn): ?>
                    <a href="<?= $act['base_url'] ?>&set_state=<?= $btn['state'] ?>&id=<?= $r['id'] ?>"
                       class="btn btn-sm <?= $btn['class'] ?>">
                      <i class="<?= $btn['icon'] ?>"></i> <?= $btn['label'] ?>
                    </a>
                  <?php endforeach;
                endif;
              endforeach; ?>
            </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

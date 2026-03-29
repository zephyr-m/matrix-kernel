<?php
/**
 * Kanban Renderer — рендерит доску из FSM матрицы
 *
 * Входные переменные:
 *   $fsm     — FSM матрица: ['state_key' => ['label', 'color', 'icon', 'buttons']]
 *   $rows    — данные с полем 'state'
 *   $options — доп. настройки:
 *              'title_field'  — какое поле показывать как заголовок карточки (default: 'title')
 *              'subtitle_field' — подзаголовок (default: null)
 *              'fields' — массив полей для отображения в карточке
 *              'transition_url' — URL для перехода (?set_state=X&id=Y)
 */

$titleField    = $options['title_field'] ?? 'title';
$subtitleField = $options['subtitle_field'] ?? null;
$fields        = $options['fields'] ?? [];
$transUrl      = $options['transition_url'] ?? '?';

// Group by state
$byState = [];
foreach ($fsm as $key => $sig) $byState[$key] = [];
foreach ($rows as $r) {
    $s = $r['state'] ?? 'idle';
    if (isset($byState[$s])) $byState[$s][] = $r;
}
?>

<style>
.mk-kanban { display: flex; gap: 12px; overflow-x: auto; padding-bottom: 8px; }
.mk-kanban-col { min-width: 220px; flex: 1; }
.mk-kanban-header { padding: 8px 12px; border-radius: 6px 6px 0 0; font-weight: 600; font-size: 13px; display: flex; align-items: center; gap: 6px; }
.mk-kanban-body { background: var(--tblr-bg-surface-secondary, #1a1d27); border-radius: 0 0 6px 6px; min-height: 100px; padding: 8px; display: flex; flex-direction: column; gap: 8px; }
.mk-kanban-card { background: var(--tblr-bg-surface, #232634); border: 1px solid var(--tblr-border-color, #2e3348); border-radius: 6px; padding: 10px 12px; transition: box-shadow 0.15s; }
.mk-kanban-card:hover { box-shadow: 0 2px 8px rgba(0,0,0,0.3); }
.mk-kanban-empty { text-align: center; color: var(--tblr-secondary); font-size: 12px; padding: 20px 0; }
</style>

<div class="mk-kanban">
  <?php foreach ($fsm as $stateKey => $sig): ?>
    <div class="mk-kanban-col">
      <div class="mk-kanban-header bg-<?= $sig['color'] ?>-lt">
        <i class="<?= $sig['icon'] ?>"></i>
        <?= $sig['label'] ?>
        <span class="badge bg-<?= $sig['color'] ?> ms-auto"><?= count($byState[$stateKey]) ?></span>
      </div>
      <div class="mk-kanban-body">
        <?php if (empty($byState[$stateKey])): ?>
          <div class="mk-kanban-empty">—</div>
        <?php else: foreach ($byState[$stateKey] as $r): ?>
          <div class="mk-kanban-card">
            <div class="fw-bold" style="font-size:14px"><?= htmlspecialchars($r[$titleField] ?? '#'.$r['id']) ?></div>
            <?php if ($subtitleField && isset($r[$subtitleField])): ?>
              <div class="text-muted" style="font-size:12px"><?= htmlspecialchars($r[$subtitleField]) ?></div>
            <?php endif; ?>
            <?php foreach ($fields as $f):
              $label = $f['label'] ?? $f['field'];
              $val = $r[$f['field']] ?? '—';
              $type = $f['type'] ?? 'text';
            ?>
              <div style="font-size:11px;margin-top:2px">
                <?= $label ?>:
                <?php if ($type === 'money'): ?>
                  <strong>$<?= number_format((float)$val, 2) ?></strong>
                <?php elseif ($type === 'pnl'): ?>
                  <span class="text-<?= (float)$val >= 0 ? 'green' : 'red' ?> fw-bold"><?= (float)$val >= 0 ? '+' : '' ?>$<?= number_format((float)$val, 2) ?></span>
                <?php else: ?>
                  <strong><?= htmlspecialchars($val) ?></strong>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
            <?php if (!empty($sig['buttons'])): ?>
              <div style="margin-top:8px;display:flex;gap:4px;flex-wrap:wrap">
                <?php foreach ($sig['buttons'] as $btn): ?>
                  <a href="<?= $transUrl ?>&set_state=<?= $btn['state'] ?>&id=<?= $r['id'] ?>"
                     class="btn btn-sm <?= $btn['class'] ?>" style="font-size:11px;padding:2px 8px">
                    <i class="<?= $btn['icon'] ?>"></i> <?= $btn['label'] ?>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
</div>

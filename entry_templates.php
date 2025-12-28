<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/helpers.php';
require_login();
require_company_selected();

$pdo = db();
$cid = (int) current_company_id();

$st = $pdo->prepare("
  SELECT
    et.*,
    u.name AS created_by_name,
    (SELECT COUNT(*) FROM entry_template_lines l WHERE l.template_id = et.id) AS lines_count
  FROM entry_templates et
  LEFT JOIN users u ON u.id = et.created_by
  WHERE et.company_id = ?
  ORDER BY et.name ASC, et.id DESC
");
$st->execute([$cid]);
$rows = $st->fetchAll() ?: [];

require __DIR__ . '/partials/header.php';
?>

<div class="card">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
    <div>
      <h2 style="margin:0;">Plantillas de asientos</h2>

      <div style="margin-top:6px; display:flex; align-items:center; gap:10px;">
        <span class="small">Guarda combinaciones de cuentas para reutilizarlas al crear asientos.</span>

        <!-- Tooltip tipo card (usa tu CSS [data-tip]) -->
        <span
          tabindex="0"
          role="button"
          aria-label="Qué es una plantilla"
          data-tip-title="¿Qué es una plantilla?"
          data-tip="Una plantilla guarda la estructura del asiento (cuentas + debe/haber + glosas). Luego la aplicas en 1 click al crear un nuevo asiento."
          style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:999px;border:1px solid var(--border);background:#fff;cursor:help;font-weight:800;"
        >i</span>
      </div>
    </div>

    <div style="display:flex;gap:10px;align-items:center;">
      <a class="btn" href="entry_template_new.php">+ Nueva plantilla</a>
      <a class="btn secondary" href="entries.php">Volver a asientos</a>
    </div>
  </div>
</div>

<div class="card">
  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Nombre</th>
          <th>Descripción</th>
          <th>Líneas</th>
          <th>Creada por</th>
          <th>Fecha</th>
          <th style="width:280px;">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr>
            <td colspan="6" class="small">Aún no tienes plantillas. Crea la primera con “Nueva plantilla”.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><b><?= h((string)$r['name']) ?></b></td>
              <td class="small"><?= h((string)($r['description'] ?? '')) ?></td>
              <td><?= (int)($r['lines_count'] ?? 0) ?></td>
              <td class="small"><?= h((string)($r['created_by_name'] ?? '—')) ?></td>
              <td class="small"><?= h((string)($r['created_at'] ?? '')) ?></td>
              <td>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                  <!-- USAR: precarga en entry_new.php -->
                  <a class="btn secondary smallbtn" href="entry_new.php?template_id=<?= (int)$r['id'] ?>">Usar</a>

                  <a class="btn secondary smallbtn" href="entry_template_edit.php?id=<?= (int)$r['id'] ?>">Editar</a>

                  <form method="post" action="entry_template_delete.php" style="display:inline;">
                    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn danger smallbtn" type="submit"
                      onclick="return confirm('¿Eliminar plantilla “<?= h((string)$r['name']) ?>”?');">
                      Eliminar
                    </button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require __DIR__ . '/partials/footer.php'; ?>

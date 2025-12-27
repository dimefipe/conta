<?php
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/helpers.php';
$pdo = db();

$accounts = $pdo->query("SELECT id,code,name FROM accounts WHERE is_active=1 ORDER BY code")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  csrf_check($_POST['csrf'] ?? '');

  $date = $_POST['entry_date'] ?? '';
  $desc = trim($_POST['description'] ?? '');

  $accs = $_POST['account_id'] ?? [];
  $memos = $_POST['memo'] ?? [];
  $debits = $_POST['debit'] ?? [];
  $credits = $_POST['credit'] ?? [];

  if (!$date || $desc === '') {
    flash_set('err','Fecha y glosa son obligatorias.');
    redirect('entry_new.php');
  }

  $lines = [];
  $sumD = 0; $sumC = 0;

  for ($i=0; $i<count($accs); $i++) {
    $aid = (int)($accs[$i] ?? 0);
    $memo = trim($memos[$i] ?? '');
    $d = (float)str_replace(',','.', ($debits[$i] ?? '0'));
    $c = (float)str_replace(',','.', ($credits[$i] ?? '0'));

    if ($aid <= 0) continue;
    if (($d>0 && $c>0) || ($d<=0 && $c<=0)) continue;

    $sumD += $d;
    $sumC += $c;

    $lines[] = ['account_id'=>$aid,'memo'=>$memo,'debit'=>$d,'credit'=>$c];
  }

  if (count($lines) < 2) {
    flash_set('err','Debes ingresar al menos 2 líneas válidas.');
    redirect('entry_new.php');
  }
  if (round($sumD,2) !== round($sumC,2)) {
    flash_set('err',"Asiento no cuadra. Debe=$sumD Haber=$sumC");
    redirect('entry_new.php');
  }

  try {
    $pdo->beginTransaction();

    $st = $pdo->prepare("INSERT INTO journal_entries(entry_date,description,status) VALUES(?,?, 'POSTED')");
    $st->execute([$date,$desc]);
    $entryId = (int)$pdo->lastInsertId();

    $lineNo = 1;
    $stL = $pdo->prepare("
      INSERT INTO journal_lines(entry_id,line_no,account_id,memo,debit,credit)
      VALUES(?,?,?,?,?,?)
    ");
    foreach ($lines as $ln) {
      $stL->execute([$entryId,$lineNo,$ln['account_id'],$ln['memo'],$ln['debit'],$ln['credit']]);
      $lineNo++;
    }

    $pdo->commit();
    flash_set('ok',"Asiento #$entryId creado y posteado.");
    redirect("entry_view.php?id=$entryId");
  } catch (Throwable $e) {
    $pdo->rollBack();
    flash_set('err','Error guardando: '.$e->getMessage());
    redirect('entry_new.php');
  }
}

require __DIR__ . '/partials/header.php';
?>
<div class="card">
  <h2>Crear asiento</h2>

  <form method="post" class="card">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

    <div class="row">
      <div class="field">
        <label>Fecha</label>
        <input type="date" name="entry_date" value="<?= h(date('Y-m-d')) ?>" required />
      </div>
      <div class="field" style="flex:1">
        <label>Glosa</label>
        <input name="description" placeholder="Ej: Pago software / Venta servicio" required />
      </div>
    </div>

    <hr />

    <div class="row" style="align-items:center; justify-content:space-between">
      <div class="small">
        Total Debe: <b id="debitTotal">0.00</b> |
        Total Haber: <b id="creditTotal">0.00</b> |
        Diferencia: <b id="diffTotal">0.00</b>
      </div>
      <button class="btn secondary" id="addLine">+ Línea</button>
    </div>

    <table class="table" id="linesTable" style="margin-top:10px">
      <thead>
        <tr>
          <th>Cuenta</th>
          <th>Memo</th>
          <th class="right">Debe</th>
          <th class="right">Haber</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>
            <select name="account_id[]">
              <option value="0">Selecciona...</option>
              <?php foreach ($accounts as $a): ?>
                <option value="<?= (int)$a['id'] ?>"><?= h($a['code']) ?> — <?= h($a['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td><input name="memo[]" placeholder="Opcional" /></td>
          <td class="right"><input name="debit[]" type="number" step="0.01" min="0" /></td>
          <td class="right"><input name="credit[]" type="number" step="0.01" min="0" /></td>
          <td><button class="btn secondary removeLine">Quitar</button></td>
        </tr>
        <tr>
          <td>
            <select name="account_id[]">
              <option value="0">Selecciona...</option>
              <?php foreach ($accounts as $a): ?>
                <option value="<?= (int)$a['id'] ?>"><?= h($a['code']) ?> — <?= h($a['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </td>
          <td><input name="memo[]" placeholder="Opcional" /></td>
          <td class="right"><input name="debit[]" type="number" step="0.01" min="0" /></td>
          <td class="right"><input name="credit[]" type="number" step="0.01" min="0" /></td>
          <td><button class="btn secondary removeLine">Quitar</button></td>
        </tr>
      </tbody>
    </table>

    <div style="margin-top:12px">
      <button class="btn" id="saveEntry" disabled>Guardar (solo si cuadra)</button>
      <a class="btn secondary" href="entries.php">Volver</a>
    </div>
  </form>
</div>

<template id="lineTemplate">
  <tr>
    <td>
      <select name="account_id[]">
        <option value="0">Selecciona...</option>
        <?php foreach ($accounts as $a): ?>
          <option value="<?= (int)$a['id'] ?>"><?= h($a['code']) ?> — <?= h($a['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </td>
    <td><input name="memo[]" placeholder="Opcional" /></td>
    <td class="right"><input name="debit[]" type="number" step="0.01" min="0" /></td>
    <td class="right"><input name="credit[]" type="number" step="0.01" min="0" /></td>
    <td><button class="btn secondary removeLine">Quitar</button></td>
  </tr>
</template>

<?php require __DIR__ . '/partials/footer.php'; ?>

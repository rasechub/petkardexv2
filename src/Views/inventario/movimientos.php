<?php
/**
 * PetKardex v2 - Historial de Movimientos de Inventario
 */

use PetKardex\Helpers\Security;

$lista = $movimientos ?? [];

ob_start();
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <div>
    <a href="/app/inventario" class="btn btn-sm btn-outline-dark rounded-pill px-3 mb-2">
      <i class="bi bi-arrow-left me-1"></i>Volver a Inventario
    </a>
    <h2 class="h4 fw-bold mb-0">Kardex de Movimientos de Stock</h2>
    <small class="text-muted">Registro auditable de entradas (compras/ajustes) y salidas (ventas POS y recetas médicas).</small>
  </div>
</div>

<div class="pk-card p-4">
  <?php if (empty($lista)): ?>
    <div class="text-center py-5 text-muted">
      <i class="bi bi-clock-history fs-1 d-block mb-2" style="color: #C48CFF;"></i>
      <p class="small mb-0">No hay movimientos de inventario registrados aún.</p>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle small mb-0">
        <thead class="table-light">
          <tr>
            <th>Fecha</th>
            <th>Producto</th>
            <th>Tipo</th>
            <th class="text-end">Cantidad</th>
            <th class="text-center">Stock Ant. → Nuevo</th>
            <th>Origen / Motivo</th>
            <th>Usuario</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lista as $m): ?>
            <?php
              $badge = match ($m['tipo']) {
                  'entrada', 'devolucion' => 'bg-success-subtle text-success',
                  'salida'                => 'bg-danger-subtle text-danger',
                  default                 => 'bg-warning-subtle text-warning-emphasis',
              };
            ?>
            <tr>
              <td class="text-nowrap"><?= Security::e(date('d/m/Y H:i', strtotime((string) $m['fecha']))) ?></td>
              <td>
                <strong><?= Security::e((string) $m['producto_nombre']) ?></strong>
                <div class="text-muted" style="font-size: 0.72rem;"><code><?= Security::e((string) $m['producto_codigo']) ?></code></div>
              </td>
              <td><span class="badge rounded-pill <?= $badge ?>"><?= Security::e(ucfirst((string) $m['tipo'])) ?></span></td>
              <td class="text-end fw-bold"><?= number_format((float) $m['cantidad'], 2) ?></td>
              <td class="text-center">
                <span class="text-muted"><?= number_format((float) $m['stock_anterior'], 2) ?></span>
                <i class="bi bi-arrow-right mx-1"></i>
                <strong><?= number_format((float) $m['stock_nuevo'], 2) ?></strong>
              </td>
              <td>
                <?php if (!empty($m['referencia_tipo'])): ?>
                  <span class="badge bg-light text-dark border me-1"><?= Security::e((string) $m['referencia_tipo']) ?></span>
                <?php endif; ?>
                <span><?= Security::e((string) ($m['motivo'] ?? '')) ?></span>
              </td>
              <td><?= Security::e(trim(($m['usuario_nombre'] ?? '') . ' ' . ($m['usuario_apellido'] ?? ''))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';


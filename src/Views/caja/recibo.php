<?php
/**
 * PetKardex v2 - Comprobante de Venta (Imprimible)
 */

use PetKardex\Helpers\Security;

$e = static fn(mixed $val): string => htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8');

$v      = $venta ?? [];
$cfg    = $configFinanciera ?? [];
$moneda = (string) ($cfg['moneda_simbolo'] ?? 'S/.');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= $e($title ?? 'Comprobante de Venta') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <style>
    body { background: #F3F4F6; padding: 1.5rem; font-family: 'Inter', Arial, sans-serif; }
    .ticket {
      max-width: 440px;
      margin: 0 auto;
      background: #FFFFFF;
      border-radius: 1rem;
      padding: 2rem 1.5rem;
      box-shadow: 0 12px 32px rgba(0,0,0,0.08);
      border-top: 6px solid #C48CFF;
    }
    @media print {
      body { background: #FFFFFF; padding: 0; }
      .no-print { display: none !important; }
      .ticket { box-shadow: none; border-radius: 0; max-width: 100%; }
    }
  </style>
</head>
<body>
<div class="ticket">
  <div class="text-center border-bottom pb-3 mb-3">
    <h1 class="h5 fw-bold mb-1"><?= $e($cfg['nombre_clinica'] ?? 'PetKardex Clínica Veterinaria') ?></h1>
    <?php if (!empty($cfg['direccion'])): ?>
      <div class="small text-muted"><?= $e($cfg['direccion']) ?></div>
    <?php endif; ?>
    <?php if (!empty($cfg['telefono'])): ?>
      <div class="small text-muted">Tel: <?= $e($cfg['telefono']) ?></div>
    <?php endif; ?>
    <div class="badge bg-dark mt-2">Comprobante <?= $e($v['numero_venta'] ?? '') ?></div>
  </div>

  <div class="small mb-3">
    <div><strong>Fecha:</strong> <?= $e(date('d/m/Y H:i', strtotime((string) ($v['fecha'] ?? 'now')))) ?></div>
    <div><strong>Cliente:</strong> <?= $e($v['cliente_nombre'] ?? 'Cliente Mostrador') ?></div>
    <?php if (!empty($v['propietario_dni'])): ?>
      <div><strong>DNI:</strong> <?= $e($v['propietario_dni']) ?></div>
    <?php endif; ?>
    <div><strong>Cajero:</strong> <?= $e(trim(($v['cajero_nombre'] ?? '') . ' ' . ($v['cajero_apellido'] ?? ''))) ?></div>
    <div><strong>Método de pago:</strong> <?= $e(ucfirst((string) ($v['metodo_pago'] ?? 'efectivo'))) ?></div>
  </div>

  <table class="table table-sm small border-top border-bottom">
    <thead>
      <tr>
        <th>Descripción</th>
        <th class="text-center">Cant</th>
        <th class="text-end">Importe</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($v['items'] ?? [] as $item): ?>
        <tr>
          <td><?= $e($item['descripcion'] ?? '') ?></td>
          <td class="text-center"><?= number_format((float) $item['cantidad'], 2) ?></td>
          <td class="text-end"><?= $e($moneda) ?> <?= number_format((float) $item['subtotal'], 2) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="small">
    <div class="d-flex justify-content-between">
      <span>Subtotal</span>
      <span><?= $e($moneda) ?> <?= number_format((float) ($v['subtotal'] ?? 0), 2) ?></span>
    </div>
    <?php if ((float) ($v['descuento'] ?? 0) > 0): ?>
      <div class="d-flex justify-content-between text-danger">
        <span>Descuento</span>
        <span>- <?= $e($moneda) ?> <?= number_format((float) $v['descuento'], 2) ?></span>
      </div>
    <?php endif; ?>
    <div class="d-flex justify-content-between">
      <span>IGV / IVA</span>
      <span><?= $e($moneda) ?> <?= number_format((float) ($v['impuesto'] ?? 0), 2) ?></span>
    </div>
    <div class="d-flex justify-content-between fw-bold fs-5 mt-2 pt-2 border-top">
      <span>TOTAL</span>
      <span><?= $e($moneda) ?> <?= number_format((float) ($v['total'] ?? 0), 2) ?></span>
    </div>
  </div>

  <div class="mt-4 d-flex gap-2 justify-content-center no-print">
    <button type="button" id="btnPrint" class="btn btn-dark btn-sm px-4 rounded-pill">Imprimir</button>
    <a href="/app/caja" class="btn btn-outline-secondary btn-sm px-4 rounded-pill">Volver a Caja</a>
  </div>
</div>
<script>
  document.getElementById('btnPrint')?.addEventListener('click', () => window.print());
</script>
</body>
</html>


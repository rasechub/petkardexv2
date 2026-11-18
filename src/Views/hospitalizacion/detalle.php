<?php
/**
 * PetKardex v2 - Evolución y Bitácora de Paciente Hospitalizado
 *
 * Permite a Guardia/Veterinario registrar signos vitales por turno, administrar
 * medicamentos (descontando stock y cargándolos a la cuenta POS) y dar Alta Médica.
 *
 * Variables:
 * - array  $hosp
 * - bool   $canClinic
 * - array  $medicamentos
 * - string $csrfToken
 */

use PetKardex\Helpers\Security;

$h         = $hosp ?? [];
$hospId    = (int) ($h['id'] ?? 0);
$allowEdit = (bool) ($canClinic ?? false) && (($h['estado'] ?? '') === 'activo');
$cuenta    = $h['cuenta_pendiente'] ?? null;

ob_start();
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <div>
    <a href="/app/hospitalizacion" class="btn btn-sm btn-outline-dark rounded-pill px-3 mb-2">
      <i class="bi bi-arrow-left me-1"></i>Volver al Mapa de Jaulas
    </a>
    <h2 class="h4 fw-bold mb-0">
      Internado: <?= Security::e((string) ($h['paciente_nombre'] ?? '')) ?>
      <span class="badge rounded-pill ms-2" style="background: rgba(196,140,255,0.25); color: #5A3A8A;">
        Jaula <?= Security::e((string) ($h['cama_numero'] ?? '')) ?>
      </span>
    </h2>
    <small class="text-muted">
      <?= Security::e((string) ($h['especie_nombre'] ?? '')) ?> · Tutor: <?= Security::e(trim(($h['propietario_nombre'] ?? '') . ' ' . ($h['propietario_apellido'] ?? ''))) ?>
      · Ingreso: <?= Security::e(date('d/m/Y H:i', strtotime((string) ($h['fecha_ingreso'] ?? 'now')))) ?>
    </small>
  </div>

  <?php if ($allowEdit): ?>
    <form action="/app/hospitalizacion/<?= $hospId ?>/alta" method="POST" id="formAltaMedica">
      <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />
      <input type="hidden" name="estado_final" value="alta" />
      <button type="submit" class="btn btn-success rounded-3 px-4 fw-semibold">
        <i class="bi bi-check-circle-fill me-2"></i>Dar de Alta Médica
      </button>
    </form>
  <?php else: ?>
    <span class="badge bg-secondary fs-6 rounded-pill px-3 py-2">Estado: <?= Security::e(ucfirst((string) ($h['estado'] ?? ''))) ?></span>
  <?php endif; ?>
</div>

<div class="row g-4">
  <!-- Formulario de Bitácora por Turno -->
  <?php if ($allowEdit): ?>
    <div class="col-lg-5">
      <div class="pk-card p-4 mb-4">
        <h3 class="h6 fw-bold mb-3">
          <i class="bi bi-journal-plus me-2" style="color: #7B52AB;"></i>Registrar Evolución / Turno
        </h3>
        <form action="/app/hospitalizacion/<?= $hospId ?>/bitacora" method="POST">
          <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />

          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label small fw-semibold">Temperatura (°C)</label>
              <input type="number" step="0.1" min="30" max="45" name="temperatura" class="form-control form-control-sm" placeholder="38.5" />
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Frec. Cardíaca (lpm)</label>
              <input type="number" min="0" name="frecuencia_cardiaca" class="form-control form-control-sm" placeholder="105" />
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Frec. Respiratoria</label>
              <input type="number" min="0" name="frecuencia_respiratoria" class="form-control form-control-sm" placeholder="24" />
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Conciencia</label>
              <select name="estado_conciencia" class="form-select form-select-sm">
                <option value="alerta">Alerta</option>
                <option value="deprimido">Deprimido</option>
                <option value="estuporoso">Estuporoso</option>
                <option value="comatoso">Comatoso</option>
              </select>
            </div>
          </div>

          <div class="p-3 rounded-3 border mb-3" style="background: #F8F5FF;">
            <label class="form-label small fw-bold mb-1" style="color: #5A3A8A;">
              <i class="bi bi-capsule me-1"></i>Medicamento Administrado (Suma a cuenta POS)
            </label>
            <div class="row g-2">
              <div class="col-8">
                <select name="producto_id" class="form-select form-select-sm">
                  <option value="">Ninguno en este turno</option>
                  <?php foreach ($medicamentos ?? [] as $med): ?>
                    <option value="<?= (int) $med['id'] ?>">
                      <?= Security::e((string) $med['nombre']) ?> (Stock: <?= number_format((float) $med['stock_actual'], 1) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-4">
                <input type="number" step="0.5" min="0.5" value="1" name="cantidad" class="form-control form-control-sm" placeholder="Cant." />
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Observaciones del Turno</label>
            <textarea name="observaciones" class="form-control form-control-sm" rows="3" placeholder="Evolución clínica, hidratación, respuesta a fármacos..."></textarea>
          </div>

          <div class="d-grid">
            <button type="submit" class="btn btn-pk-primary py-2">Guardar en Bitácora</button>
          </div>
        </form>
      </div>

      <!-- Cuenta acumulada hacia Caja/POS -->
      <div class="pk-card p-4">
        <h4 class="h6 fw-bold mb-2"><i class="bi bi-receipt me-2" style="color: #7B52AB;"></i>Cargos Acumulados (POS)</h4>
        <?php if ($cuenta): ?>
          <div class="small text-muted mb-2">Cuenta #<code><?= Security::e((string) $cuenta['numero_venta']) ?></code></div>
          <ul class="list-group list-group-flush small mb-2">
            <?php foreach ($cuenta['items'] ?? [] as $it): ?>
              <li class="list-group-item px-0 d-flex justify-content-between">
                <span><?= Security::e((string) $it['descripcion']) ?> (x<?= number_format((float) $it['cantidad'], 1) ?>)</span>
                <strong>S/. <?= number_format((float) $it['subtotal'], 2) ?></strong>
              </li>
            <?php endforeach; ?>
          </ul>
          <div class="d-flex justify-content-between fw-bold border-top pt-2">
            <span>Total estimado c/IGV</span>
            <span style="color: #7B52AB;">S/. <?= number_format((float) $cuenta['total'], 2) ?></span>
          </div>
        <?php else: ?>
          <p class="small text-muted mb-0">Sin cargos adicionales registrados aún.</p>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <!-- Timeline de Bitácora -->
  <div class="<?= $allowEdit ? 'col-lg-7' : 'col-12' ?>">
    <div class="pk-card p-4">
      <h3 class="h6 fw-bold mb-3"><i class="bi bi-clock-history me-2" style="color: #7B52AB;"></i>Bitácora de Evolución y Signos Vitales</h3>
      <?php if (empty($h['bitacora'])): ?>
        <p class="small text-muted mb-0">No hay registros en la bitácora todavía.</p>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle small mb-0">
            <thead class="table-light">
              <tr>
                <th>Fecha/Hora</th>
                <th>Signos Vitales</th>
                <th>Conciencia</th>
                <th>Observaciones / Medicación</th>
                <th>Responsable</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($h['bitacora'] as $b): ?>
                <tr>
                  <td class="text-nowrap"><?= Security::e(date('d/m H:i', strtotime((string) $b['fecha_hora']))) ?></td>
                  <td>
                    <?php if ($b['temperatura'] !== null): ?><div>T: <strong><?= Security::e((string) $b['temperatura']) ?> °C</strong></div><?php endif; ?>
                    <?php if ($b['frecuencia_cardiaca'] !== null): ?><div>FC: <strong><?= (int) $b['frecuencia_cardiaca'] ?> lpm</strong></div><?php endif; ?>
                    <?php if ($b['frecuencia_respiratoria'] !== null): ?><div>FR: <strong><?= (int) $b['frecuencia_respiratoria'] ?> rpm</strong></div><?php endif; ?>
                  </td>
                  <td><span class="badge bg-light text-dark border"><?= Security::e(ucfirst((string) $b['estado_conciencia'])) ?></span></td>
                  <td><?= nl2br(Security::e((string) ($b['observaciones'] ?? ''))) ?></td>
                  <td><?= Security::e(trim(($b['usuario_nombre'] ?? '') . ' ' . ($b['usuario_apellido'] ?? ''))) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
  'use strict';
  document.getElementById('formAltaMedica')?.addEventListener('submit', (e) => {
    if (!window.confirm('¿Confirmas dar de alta médica al paciente? La jaula pasará a Limpieza y la cuenta quedará lista en Caja/POS.')) {
      e.preventDefault();
    }
  });
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';


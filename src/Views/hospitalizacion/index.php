<?php
/**
 * PetKardex v2 - Mapa de Hospitalización (Jaulas/Caniles) y Triage de Emergencias
 *
 * Variables:
 * - array  $mapaJaulas
 * - array  $conteos
 * - array  $pacientes
 * - bool   $canTriage
 * - string $csrfToken
 */

use PetKardex\Helpers\Security;

$jaulas    = $mapaJaulas ?? [];
$stats     = $conteos ?? ['ocupada' => 0, 'libre' => 0, 'limpieza' => 0];
$pacList   = $pacientes ?? [];
$allowEdit = (bool) ($canTriage ?? false);

ob_start();
?>

<div class="pk-card p-4 mb-4 text-white" style="background: linear-gradient(135deg, #0D0D0D 0%, #1A1A2E 65%, #371F66 100%);">
  <div class="row align-items-center g-3">
    <div class="col-lg-7">
      <span class="badge rounded-pill px-3 py-2 mb-2" style="background: rgba(196, 140, 255, 0.2); color: #C48CFF;">
        <i class="bi bi-hospital-fill me-1"></i>Área de Internamiento &amp; UCI
      </span>
      <h2 class="h4 fw-bold mb-1">Mapa de Jaulas / Caniles y Triage</h2>
      <p class="mb-0 small text-white-50">
        Monitoreo en tiempo real de ocupación, ingresos de emergencia y desinfección post-alta.
      </p>
    </div>
    <div class="col-lg-5 d-flex justify-content-lg-end flex-wrap gap-2">
      <span class="badge bg-danger rounded-pill px-3 py-2 d-inline-flex align-items-center">
        Ocupadas: <?= (int) $stats['ocupada'] ?>
      </span>
      <span class="badge bg-success rounded-pill px-3 py-2 d-inline-flex align-items-center">
        Libres: <?= (int) $stats['libre'] ?>
      </span>
      <span class="badge bg-warning text-dark rounded-pill px-3 py-2 d-inline-flex align-items-center">
        En Limpieza: <?= (int) $stats['limpieza'] ?>
      </span>
      <?php if ($allowEdit): ?>
        <button type="button" class="btn btn-pk-primary px-3" data-bs-toggle="modal" data-bs-target="#modalTriage">
          <i class="bi bi-activity me-1"></i>Ingreso Triage / Emergencia
        </button>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Grid / Mapa Visual de Jaulas -->
<div class="row g-3 g-md-4">
  <?php foreach ($jaulas as $jaula): ?>
    <?php
      $codigo = (string) $jaula['codigo'];
      $estado = (string) $jaula['estado'];
      $hosp   = $jaula['hospitalizacion'];

      [$borderClass, $badgeClass, $estadoLabel, $icon] = match ($estado) {
          'ocupada'  => ['border-danger border-2', 'bg-danger text-white', 'Ocupada', 'bi-heart-pulse-fill'],
          'limpieza' => ['border-warning border-2', 'bg-warning text-dark', 'En Limpieza', 'bi-stars'],
          default    => ['border-success-subtle', 'bg-success-subtle text-success', 'Libre', 'bi-check-circle-fill'],
      };
    ?>
    <div class="col-12 col-sm-6 col-lg-4 col-xl-3">
      <div class="pk-card pk-card-hover p-3 p-md-4 h-100 d-flex flex-column justify-content-between <?= $borderClass ?>">
        <div>
          <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="h5 fw-bold mb-0 text-dark"><?= Security::e($codigo) ?></span>
            <span class="badge rounded-pill <?= $badgeClass ?>">
              <i class="bi <?= $icon ?> me-1"></i><?= $estadoLabel ?>
            </span>
          </div>

          <?php if ($estado === 'ocupada' && $hosp): ?>
            <div class="p-3 rounded-3 mb-3" style="background: #F8F5FF; border: 1px solid rgba(196,140,255,0.35);">
              <div class="fw-bold text-dark"><?= Security::e((string) $hosp['paciente_nombre']) ?></div>
              <div class="small text-muted"><?= Security::e((string) $hosp['especie_nombre']) ?></div>
              <div class="small mt-2">
                <strong>Motivo:</strong> <?= Security::e(mb_strimwidth((string) ($hosp['motivo_ingreso'] ?? ''), 0, 65, '...')) ?>
              </div>
              <div class="text-muted mt-1" style="font-size: 0.74rem;">
                Ingreso: <?= Security::e(date('d/m H:i', strtotime((string) $hosp['fecha_ingreso']))) ?>
              </div>
            </div>
          <?php elseif ($estado === 'limpieza'): ?>
            <p class="small text-muted mb-3">
              El paciente recibió alta médica. Requiere desinfección antes de quedar libre.
            </p>
          <?php else: ?>
            <p class="small text-muted mb-3">
              Canil desinfectado y disponible para hospitalización o triage.
            </p>
          <?php endif; ?>
        </div>

        <div>
          <?php if ($estado === 'ocupada' && $hosp): ?>
            <a href="/app/hospitalizacion/paciente/<?= (int) $hosp['id'] ?>" class="btn btn-dark w-100 btn-sm rounded-3 py-2 fw-semibold">
              <i class="bi bi-journal-medical me-1"></i>Abrir Bitácora / Alta
            </a>
          <?php elseif ($estado === 'limpieza'): ?>
            <form action="/app/hospitalizacion/jaula/limpiar" method="POST">
              <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />
              <input type="hidden" name="cama_numero" value="<?= Security::e($codigo) ?>" />
              <button type="submit" class="btn btn-outline-warning text-dark w-100 btn-sm rounded-3 py-2 fw-semibold">
                <i class="bi bi-check2-all me-1"></i>Marcar Limpieza Lista
              </button>
            </form>
          <?php elseif ($allowEdit): ?>
            <button
              type="button"
              class="btn btn-outline-success w-100 btn-sm rounded-3 py-2 fw-semibold js-select-jaula"
              data-jaula="<?= Security::e($codigo) ?>"
              data-bs-toggle="modal"
              data-bs-target="#modalTriage"
            >
              <i class="bi bi-plus-circle me-1"></i>Asignar en Triage
            </button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php if ($allowEdit): ?>
<!-- MODAL: TRIAGE / INGRESO RÁPIDO DE EMERGENCIA -->
<div class="modal fade" id="modalTriage" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content rounded-4 border-0">
      <div class="modal-header text-white" style="background: linear-gradient(135deg, #0D0D0D 0%, #2D1B69 100%);">
        <h5 class="modal-title h6 fw-bold"><i class="bi bi-activity me-2" style="color: #C48CFF;"></i>Formulario Rápido de Triage / Emergencia</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form action="/app/hospitalizacion/triage" method="POST">
        <div class="modal-body p-4">
          <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />
          <div class="row g-3">
            <div class="col-md-7">
              <label class="form-label small fw-semibold">Paciente *</label>
              <select name="paciente_id" class="form-select" required>
                <option value="">Selecciona paciente...</option>
                <?php foreach ($pacList as $p): ?>
                  <option value="<?= (int) $p['id'] ?>">
                    <?= Security::e((string) $p['nombre']) ?> (<?= Security::e((string) $p['especie_nombre']) ?>) — Tutor: <?= Security::e(trim($p['propietario_nombre'] . ' ' . $p['propietario_apellido'])) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-5">
              <label class="form-label small fw-semibold">Jaula / Canil Asignado *</label>
              <select name="cama_numero" id="triageJaulaSelect" class="form-select" required>
                <option value="">Selecciona jaula libre...</option>
                <?php foreach ($jaulas as $j): ?>
                  <?php if ($j['estado'] === 'libre'): ?>
                    <option value="<?= Security::e((string) $j['codigo']) ?>"><?= Security::e((string) $j['codigo']) ?></option>
                  <?php endif; ?>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label small fw-semibold">Peso (kg)</label>
              <input type="number" step="0.01" min="0" name="peso_actual" class="form-control" placeholder="Ej: 8.50" />
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Estado Conciencia *</label>
              <select name="estado_conciencia" class="form-select" required>
                <option value="alerta">Alerta</option>
                <option value="deprimido">Deprimido</option>
                <option value="estuporoso">Estuporoso</option>
                <option value="comatoso">Comatoso</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Temperatura (°C)</label>
              <input type="number" step="0.1" min="30" max="45" name="temperatura" class="form-control" placeholder="38.5" />
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Frec. Cardíaca</label>
              <input type="number" min="0" name="frecuencia_cardiaca" class="form-control" placeholder="110 lpm" />
            </div>

            <div class="col-12">
              <label class="form-label small fw-semibold">Motivo de Emergencia / Triage *</label>
              <textarea name="motivo_ingreso" class="form-control" rows="2" required placeholder="Describe síntomas críticos o motivo de ingreso inmediato..."></textarea>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold">Diagnóstico presuntivo inicial (opcional)</label>
              <input type="text" name="diagnostico_ingreso" class="form-control" placeholder="Ej: Deshidratación severa / Politraumatismo" />
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary rounded-3" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-pk-primary px-4">Ingresar Paciente</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  'use strict';
  document.addEventListener('DOMContentLoaded', () => {
    const jaulaSelect = document.getElementById('triageJaulaSelect');
    document.querySelectorAll('.js-select-jaula').forEach((btn) => {
      btn.addEventListener('click', () => {
        if (jaulaSelect) jaulaSelect.value = btn.getAttribute('data-jaula') || '';
      });
    });
  });
</script>
<?php endif; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';


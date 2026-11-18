<?php
/**
 * PetKardex v2 - Dashboard Exclusivo de Estética y Grooming
 *
 * Muestra únicamente las citas estéticas del día actual y permite emitir
 * Alertas de Salud internas hacia el Veterinario de turno y el Kardex.
 *
 * Variables:
 * - array  $citasHoy
 * - string $csrfToken
 */

use PetKardex\Helpers\Security;

$agendaHoy = $citasHoy ?? [];

ob_start();
?>

<div class="pk-card p-4 mb-4 text-white" style="background: linear-gradient(135deg, #0D0D0D 0%, #1A1A2E 65%, #371F66 100%);">
  <div class="row align-items-center g-3">
    <div class="col-lg-8">
      <span class="badge rounded-pill px-3 py-2 mb-2" style="background: rgba(196, 140, 255, 0.2); color: #C48CFF;">
        <i class="bi bi-scissors me-1"></i>Área de Estética &amp; Spa Canino
      </span>
      <h2 class="h4 fw-bold mb-1">Turnos Agendados de Hoy (<?= date('d/m/Y') ?>)</h2>
      <p class="mb-0 small text-white-50">
        Si detectas pulgas, garrapatas, lesiones en piel o bultos durante el baño/corte,
        emite inmediatamente una <strong>Alerta de Salud</strong> al equipo veterinario.
      </p>
    </div>
    <div class="col-lg-4 text-lg-end">
      <div class="small text-white-50">Pacientes agendados hoy</div>
      <div class="h2 fw-bold mb-0" style="color: #C48CFF;"><?= count($agendaHoy) ?></div>
    </div>
  </div>
</div>

<?php if (empty($agendaHoy)): ?>
  <div class="pk-card p-5 text-center">
    <div class="pk-icon-box mx-auto mb-3" style="width: 68px; height: 68px; font-size: 2rem;">
      <i class="bi bi-scissors"></i>
    </div>
    <h3 class="h5 fw-bold mb-2">Sin citas de estética para hoy</h3>
    <p class="small text-muted mb-0">
      Cuando Recepción agende baños o cortes para la fecha actual aparecerán automáticamente aquí.
    </p>
  </div>
<?php else: ?>
  <div class="row g-4">
    <?php foreach ($agendaHoy as $turno): ?>
      <?php
        $badgeEstado = match ($turno['estado'] ?? 'programada') {
            'completada' => 'bg-success-subtle text-success',
            'en_proceso' => 'bg-primary-subtle text-primary',
            'cancelada'  => 'bg-danger-subtle text-danger',
            default      => 'bg-warning-subtle text-warning-emphasis',
        };
      ?>
      <div class="col-12 col-md-6 col-xl-4">
        <div class="pk-card pk-card-hover p-4 h-100 d-flex flex-column justify-content-between">
          <div>
            <div class="d-flex justify-content-between align-items-start mb-3">
              <div>
                <span class="badge bg-dark text-white rounded-pill mb-1">
                  <i class="bi bi-clock me-1" style="color: #C48CFF;"></i>
                  <?= Security::e(date('H:i', strtotime((string) $turno['fecha_hora']))) ?>
                </span>
                <h3 class="h5 fw-bold mb-0 text-dark"><?= Security::e((string) $turno['paciente_nombre']) ?></h3>
                <small class="text-muted">
                  <?= Security::e((string) $turno['especie_nombre']) ?>
                  <?php if (!empty($turno['raza_nombre'])): ?>
                    · <?= Security::e((string) $turno['raza_nombre']) ?>
                  <?php endif; ?>
                </small>
              </div>
              <span class="badge rounded-pill <?= $badgeEstado ?>">
                <?= Security::e(ucfirst(str_replace('_', ' ', (string) $turno['estado']))) ?>
              </span>
            </div>

            <div class="small mb-3">
              <div><strong>Tutor:</strong> <?= Security::e(trim(($turno['propietario_nombre'] ?? '') . ' ' . ($turno['propietario_apellido'] ?? ''))) ?></div>
              <div><strong>Servicios:</strong> <?= Security::e(implode(', ', $turno['servicios_lista'] ?: ['Baño y Corte'])) ?></div>
              <?php if (!empty($turno['observaciones_previas'])): ?>
                <div class="text-muted mt-1"><strong>Indicaciones:</strong> <?= Security::e((string) $turno['observaciones_previas']) ?></div>
              <?php endif; ?>
            </div>

            <?php if (!empty($turno['paciente_alertas'])): ?>
              <div class="alert alert-danger py-2 px-3 small rounded-3 mb-3">
                <strong><i class="bi bi-exclamation-circle-fill me-1"></i>Alerta previa:</strong>
                <?= Security::e((string) $turno['paciente_alertas']) ?>
              </div>
            <?php endif; ?>

            <?php if ((int) ($turno['alertas_enviadas'] ?? 0) === 1): ?>
              <div class="alert alert-warning py-2 px-3 small rounded-3 mb-3">
                <i class="bi bi-bell-fill me-1"></i>Alerta médica enviada al Veterinario
              </div>
            <?php endif; ?>
          </div>

          <div class="d-grid gap-2 pt-2 border-top">
            <button
              type="button"
              class="btn btn-danger rounded-3 fw-semibold js-btn-alerta"
              data-paciente-id="<?= (int) $turno['paciente_id'] ?>"
              data-paciente-nombre="<?= Security::e((string) $turno['paciente_nombre']) ?>"
              data-agenda-id="<?= (int) $turno['id'] ?>"
              data-bs-toggle="modal"
              data-bs-target="#modalAlertaSalud"
            >
              <i class="bi bi-exclamation-triangle-fill me-2"></i>Emitir Alerta de Salud
            </button>

            <div class="d-flex gap-2">
              <form action="/app/estetica/<?= (int) $turno['id'] ?>/estado" method="POST" class="flex-grow-1">
                <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />
                <input type="hidden" name="estado" value="en_proceso" />
                <button type="submit" class="btn btn-outline-dark btn-sm w-100 rounded-3">En Proceso</button>
              </form>
              <form action="/app/estetica/<?= (int) $turno['id'] ?>/estado" method="POST" class="flex-grow-1">
                <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />
                <input type="hidden" name="estado" value="completada" />
                <button type="submit" class="btn btn-pk-primary btn-sm w-100 rounded-3">Completado</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- MODAL: EMITIR ALERTA DE SALUD AL VETERINARIO -->
<div class="modal fade" id="modalAlertaSalud" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title h6 fw-bold">
          <i class="bi bi-exclamation-octagon-fill me-2"></i>Alerta de Salud para Veterinario
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form action="/app/estetica/alerta" method="POST">
        <div class="modal-body p-4">
          <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />
          <input type="hidden" name="paciente_id" id="alertaPacienteId" value="" />
          <input type="hidden" name="agenda_estetica_id" id="alertaAgendaId" value="" />

          <div class="alert alert-light border small mb-3">
            Paciente: <strong id="alertaPacienteNombre">-</strong>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Hallazgo detectado *</label>
            <select name="hallazgo" class="form-select" required>
              <option value="Pulgas / Garrapatas">Presencia de Pulgas / Garrapatas</option>
              <option value="Lesión o Herida en Piel">Herida, dermatitis o lesión dérmica</option>
              <option value="Tumor o Bulto Palpable">Tumoración o bulto palpable</option>
              <option value="Otitis / Secreción Ocular">Otitis o secreción ocular/ótica</option>
              <option value="Otro Hallazgo Clínico">Otro hallazgo clínico</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Prioridad *</label>
            <select name="prioridad" class="form-select" required>
              <option value="alta">Alta — Requiere evaluación en el día</option>
              <option value="critica">Crítica — Atención veterinaria inmediata</option>
              <option value="media">Media — Seguimiento programado</option>
            </select>
          </div>

          <div class="mb-0">
            <label class="form-label small fw-semibold">Detalle anatómico / Observaciones *</label>
            <textarea name="mensaje" class="form-control" rows="3" required placeholder="Indica ubicación aproximada, tamaño, sangrado o comportamiento del paciente..."></textarea>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary rounded-3" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-danger px-4 fw-semibold">Enviar Alerta al Veterinario</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  'use strict';
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.js-btn-alerta').forEach((btn) => {
      btn.addEventListener('click', () => {
        document.getElementById('alertaPacienteId').value = btn.getAttribute('data-paciente-id') || '';
        document.getElementById('alertaAgendaId').value = btn.getAttribute('data-agenda-id') || '';
        document.getElementById('alertaPacienteNombre').textContent = btn.getAttribute('data-paciente-nombre') || '-';
      });
    });
  });
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';


<?php
/**
 * PetKardex v2 - Agenda Interactiva (FullCalendar v6)
 *
 * Colores diferenciados por tipo:
 * - Citas Médicas: #7B52AB
 * - Vacunación:    #10B981
 * - Estética:      #C48CFF
 * - Emergencias:   #EF4444
 *
 * Variables:
 * - bool   $canManage
 * - array  $pacientes
 * - array  $profesionales
 * - string $csrfToken
 */

use PetKardex\Helpers\Security;

$allowManage = (bool) ($canManage ?? false);

ob_start();
?>

<div class="pk-card p-4 mb-4">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
    <div>
      <h2 class="h5 fw-bold mb-2">Calendario de Citas</h2>
      <div class="d-flex flex-wrap gap-2 small">
        <span class="badge rounded-pill px-3 py-2" style="background: #7B52AB; color: #fff;">Citas Médicas</span>
        <span class="badge rounded-pill px-3 py-2" style="background: #10B981; color: #fff;">Vacunación</span>
        <span class="badge rounded-pill px-3 py-2" style="background: #C48CFF; color: #0D0D0D;">Estética / Grooming</span>
        <span class="badge rounded-pill px-3 py-2" style="background: #EF4444; color: #fff;">Emergencias</span>
      </div>
    </div>

    <?php if ($allowManage): ?>
      <button
        type="button"
        id="btnNuevaCita"
        class="btn btn-pk-primary px-4 py-2"
        data-bs-toggle="modal"
        data-bs-target="#modalNuevaCita"
      >
        <i class="bi bi-calendar-plus me-2"></i>Nueva Cita
      </button>
    <?php endif; ?>
  </div>
</div>

<div class="pk-card p-3 p-md-4 shadow-sm">
  <div id="calendarFallback" class="alert alert-warning small d-none">
    No se pudo cargar la librería visual de calendario desde CDN. Verifica tu conexión a internet o usa el botón <strong>Nueva Cita</strong>.
  </div>
  <div id="calendar" style="min-height: 620px;"></div>
</div>

<!-- MODAL: DETALLE DE CITA -->
<div class="modal fade" id="modalDetalleCita" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
      <div class="modal-header text-white border-0 p-4" style="background: linear-gradient(135deg, #0D0D0D 0%, #2D1B69 100%);">
        <h5 class="modal-title h6 fw-bold" id="detTitulo">Detalle de Cita</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body p-4 small">
        <dl class="row mb-0">
          <dt class="col-4 text-muted">Paciente</dt>
          <dd class="col-8 fw-bold" id="detPaciente">-</dd>

          <dt class="col-4 text-muted">Propietario</dt>
          <dd class="col-8" id="detPropietario">-</dd>

          <dt class="col-4 text-muted">Profesional</dt>
          <dd class="col-8" id="detProfesional">-</dd>

          <dt class="col-4 text-muted">Servicio</dt>
          <dd class="col-8" id="detServicio">-</dd>

          <dt class="col-4 text-muted">Fecha / Hora</dt>
          <dd class="col-8" id="detFecha">-</dd>

          <dt class="col-4 text-muted">Estado</dt>
          <dd class="col-8"><span class="badge bg-dark" id="detEstado">-</span></dd>

          <dt class="col-4 text-muted">Motivo / Notas</dt>
          <dd class="col-8" id="detNotas">-</dd>
        </dl>

        <?php if ($allowManage): ?>
          <form id="formEstadoCita" action="" method="POST" class="mt-4 pt-3 border-top d-flex gap-2">
            <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />
            <select name="estado" id="selectEstadoCita" class="form-select form-select-sm">
              <option value="programada">Programada</option>
              <option value="confirmada">Confirmada</option>
              <option value="en_proceso">En Proceso</option>
              <option value="completada">Completada</option>
              <option value="cancelada">Cancelada</option>
            </select>
            <button type="submit" class="btn btn-sm btn-pk-primary px-3 text-nowrap">Actualizar Estado</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if ($allowManage): ?>
<!-- MODAL: NUEVA CITA -->
<div class="modal fade" id="modalNuevaCita" tabindex="-1" aria-labelledby="modalNuevaCitaLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
      <div class="modal-header text-white border-0 p-4" style="background: linear-gradient(135deg, #0D0D0D 0%, #2D1B69 100%);">
        <h5 class="modal-title h6 fw-bold" id="modalNuevaCitaLabel">
          <i class="bi bi-calendar-plus me-2" style="color: #C48CFF;"></i>Agendar Nueva Cita
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form action="/app/agenda/crear" method="POST">
        <div class="modal-body p-4">
          <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />

          <div class="row g-3">
            <div class="col-md-12">
              <div class="form-floating">
                <select name="paciente_id" id="citaPaciente" class="form-select" required>
                  <option value="">Selecciona una mascota...</option>
                  <?php foreach ($pacientes ?? [] as $p): ?>
                    <option value="<?= (int) $p['id'] ?>">
                      <?= Security::e((string) $p['nombre']) ?> (<?= Security::e((string) $p['especie_nombre']) ?>) — Tutor: <?= Security::e(trim($p['propietario_nombre'] . ' ' . $p['propietario_apellido'])) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <label for="citaPaciente">Paciente / Mascota *</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating">
                <select name="tipo_servicio" id="citaTipo" class="form-select" required>
                  <option value="consulta">Cita Médica (Consulta)</option>
                  <option value="vacunacion">Vacunación</option>
                  <option value="estetica">Estética / Grooming</option>
                  <option value="control">Control</option>
                  <option value="cirugia">Cirugía</option>
                  <option value="emergencia">Emergencia</option>
                </select>
                <label for="citaTipo">Tipo de Cita *</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating">
                <select name="veterinario_id" id="citaProfesional" class="form-select">
                  <option value="">Por asignar</option>
                  <?php foreach ($profesionales ?? [] as $prof): ?>
                    <option value="<?= (int) $prof['id'] ?>">
                      <?= Security::e(trim($prof['nombre'] . ' ' . $prof['apellido'])) ?> (<?= Security::e((string) $prof['rol_nombre']) ?>)
                    </option>
                  <?php endforeach; ?>
                </select>
                <label for="citaProfesional">Veterinario / Estilista</label>
              </div>
            </div>

            <div class="col-md-4">
              <div class="form-floating">
                <input type="date" name="fecha" id="inputCitaFecha" class="form-control" required value="<?= date('Y-m-d') ?>" />
                <label for="inputCitaFecha">Fecha *</label>
              </div>
            </div>

            <div class="col-md-4">
              <div class="form-floating">
                <input type="time" name="hora" id="inputCitaHora" class="form-control" required value="<?= date('H:00', strtotime('+1 hour')) ?>" />
                <label for="inputCitaHora">Hora *</label>
              </div>
            </div>

            <div class="col-md-4">
              <div class="form-floating">
                <input type="number" name="duracion_minutos" id="inputCitaDuracion" class="form-control" value="30" min="15" step="15" />
                <label for="inputCitaDuracion">Duración (min)</label>
              </div>
            </div>

            <div class="col-12">
              <div class="form-floating">
                <textarea name="notas" id="inputCitaMotivo" class="form-control" style="height: 90px;" placeholder="Motivo de la cita" required></textarea>
                <label for="inputCitaMotivo">Motivo / Indicaciones de la cita *</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light border-0 p-3 px-4">
          <button type="button" class="btn btn-outline-secondary rounded-3 px-4" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-pk-primary px-4">
            <i class="bi bi-check2-circle me-2"></i>Agendar Cita
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- FullCalendar v6 Global Bundle + Locales vía jsDelivr -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.15/locales-all.global.min.js"></script>

<script>
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    const calendarEl = document.getElementById('calendar');
    const fallbackEl = document.getElementById('calendarFallback');
    const nuevaCitaEl = document.getElementById('modalNuevaCita');
    const nuevaCitaModal = (nuevaCitaEl && typeof bootstrap !== 'undefined')
      ? bootstrap.Modal.getOrCreateInstance(nuevaCitaEl)
      : null;

    // Garantizar apertura del modal al hacer clic en "Nueva Cita"
    const btnNueva = document.getElementById('btnNuevaCita');
    if (btnNueva && nuevaCitaModal) {
      btnNueva.addEventListener('click', (e) => {
        e.preventDefault();
        nuevaCitaModal.show();
      });
    }

    if (!calendarEl || typeof FullCalendar === 'undefined') {
      if (fallbackEl) fallbackEl.classList.remove('d-none');
      return;
    }

    const detailModalEl = document.getElementById('modalDetalleCita');
    const detailModal = (detailModalEl && typeof bootstrap !== 'undefined')
      ? bootstrap.Modal.getOrCreateInstance(detailModalEl)
      : null;

    const calendar = new FullCalendar.Calendar(calendarEl, {
      initialView: 'dayGridMonth',
      locale: 'es',
      height: 650,
      navLinks: true,
      selectable: true,
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,listWeek'
      },
      buttonText: {
        today: 'Hoy',
        month: 'Mes',
        week: 'Semana',
        list: 'Lista'
      },
      events: '/app/agenda/eventos',
      dateClick: function (info) {
        if (!nuevaCitaModal) return;
        const fechaInp = document.getElementById('inputCitaFecha');
        if (fechaInp && info.dateStr) {
          fechaInp.value = info.dateStr.slice(0, 10);
        }
        nuevaCitaModal.show();
      },
      eventClick: function (info) {
        const props = info.event.extendedProps || {};
        document.getElementById('detTitulo').textContent = info.event.title;
        document.getElementById('detPaciente').textContent = `${props.paciente || '-'} (${props.especie || ''})`;
        document.getElementById('detPropietario').textContent = props.propietario || '-';
        document.getElementById('detProfesional').textContent = props.profesional || '-';
        document.getElementById('detServicio').textContent = (props.tipo_servicio || '').toUpperCase();
        document.getElementById('detFecha').textContent = info.event.start ? info.event.start.toLocaleString('es-PE') : '-';
        document.getElementById('detEstado').textContent = (props.estado || '').toUpperCase();
        document.getElementById('detNotas').textContent = props.notas || 'Sin observaciones';

        const formEstado = document.getElementById('formEstadoCita');
        const selEstado = document.getElementById('selectEstadoCita');
        if (formEstado && selEstado) {
          formEstado.action = `/app/agenda/${info.event.id}/estado`;
          selEstado.value = props.estado || 'programada';
        }

        if (detailModal) detailModal.show();
      }
    });

    calendar.render();
  });
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';

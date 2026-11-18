<?php
/**
 * PetKardex v2 - Listado General de Pacientes / Mascotas
 *
 * Incluye buscador en tiempo real + búsqueda en servidor, tabla completa
 * con accesos a Kardex / QR / Certificado y modal "Registrar Nueva Mascota".
 *
 * Variables:
 * - array  $pacientes
 * - string $search
 * - bool   $canCreate
 * - array  $especies
 * - array  $razas
 * - array  $propietarios
 * - string $csrfToken
 */

use PetKardex\Helpers\Security;

$lista      = $pacientes ?? [];
$queryText  = (string) ($search ?? '');
$allowNew   = (bool) ($canCreate ?? false);
$propList   = $propietarios ?? [];

ob_start();
?>

<div class="pk-card p-3 p-md-4 mb-4">
  <div class="row g-3 align-items-center">
    <div class="col-lg-7">
      <form action="/app/pacientes" method="GET" class="input-group">
        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
        <input
          type="text"
          id="pacSearchInput"
          name="q"
          value="<?= Security::e($queryText) ?>"
          class="form-control border-start-0"
          placeholder="Buscar por nombre de mascota, DNI del propietario, microchip o QR..."
        />
        <button type="submit" class="btn btn-outline-dark">Buscar</button>
      </form>
    </div>

    <div class="col-lg-5 d-flex justify-content-lg-end flex-wrap gap-2">
      <a href="/app/qr/scanner" class="btn btn-outline-dark rounded-3 px-3">
        <i class="bi bi-qr-code-scan me-1"></i>Escanear QR
      </a>
      <?php if ($allowNew): ?>
        <a href="/app/mascotas/nueva" class="btn btn-pk-primary px-4">
          <i class="bi bi-plus-circle me-2"></i>Registrar Nueva Mascota
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="pk-card p-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="h6 fw-bold mb-0">
      <i class="bi bi-heart-pulse-fill me-2" style="color: #7B52AB;"></i>
      Mascotas Registradas (<span id="pacCount"><?= count($lista) ?></span>)
    </h2>
  </div>

  <?php if (empty($lista)): ?>
    <div class="text-center py-5 text-muted">
      <i class="bi bi-postcard-heart fs-1 d-block mb-2" style="color: #C48CFF;"></i>
      <p class="small mb-3">No hay mascotas registradas que coincidan con la búsqueda.</p>
      <?php if ($allowNew): ?>
        <a href="/app/mascotas/nueva" class="btn btn-sm btn-pk-primary px-4">
          Registrar Primera Mascota
        </a>
      <?php endif; ?>
    </div>
  <?php else: ?>
    <div class="table-responsive">
      <table class="table table-hover align-middle small mb-0">
        <thead class="table-light">
          <tr>
            <th>Mascota</th>
            <th>Especie / Raza</th>
            <th>Propietario / DNI</th>
            <th>Alertas Médicas</th>
            <th>QR</th>
            <th class="text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($lista as $pet): ?>
            <?php
              $ownerName = trim(($pet['propietario_nombre'] ?? '') . ' ' . ($pet['propietario_apellido'] ?? ''));
              $searchBlob = strtolower(
                  ($pet['nombre'] ?? '') . ' '
                  . ($pet['especie_nombre'] ?? '') . ' '
                  . ($pet['raza_nombre'] ?? '') . ' '
                  . $ownerName . ' '
                  . ($pet['propietario_dni'] ?? '') . ' '
                  . ($pet['microchip'] ?? '') . ' '
                  . ($pet['qr_code'] ?? '')
              );
              $hasAlert = trim((string) ($pet['alertas_medicas'] ?? '')) !== '';
            ?>
            <tr class="js-pac-row" data-search="<?= Security::e($searchBlob) ?>">
              <td>
                <div class="d-flex align-items-center gap-2">
                  <?php if (!empty($pet['foto'])): ?>
                    <img
                      src="/<?= Security::e(ltrim((string) $pet['foto'], '/')) ?>"
                      alt="<?= Security::e((string) $pet['nombre']) ?>"
                      class="rounded-circle object-fit-cover border"
                      style="width:36px;height:36px;border-color:#C48CFF !important;"
                    />
                  <?php else: ?>
                    <span class="pk-avatar" style="width:36px;height:36px;font-size:0.78rem;">
                      <?= Security::e(strtoupper(mb_substr((string) $pet['nombre'], 0, 2))) ?>
                    </span>
                  <?php endif; ?>
                  <div>
                    <strong class="text-dark d-block"><?= Security::e((string) $pet['nombre']) ?></strong>
                    <span class="text-muted" style="font-size: 0.74rem;">
                      <?= Security::e(ucfirst((string) ($pet['sexo'] ?? 'N/D'))) ?>
                      <?php if (!empty($pet['peso_kg'])): ?>
                        · <?= number_format((float) $pet['peso_kg'], 2) ?> kg
                      <?php endif; ?>
                    </span>
                  </div>
                </div>
              </td>
              <td>
                <span class="badge rounded-pill" style="background: rgba(196,140,255,0.2); color: #5A3A8A;">
                  <?= Security::e((string) $pet['especie_nombre']) ?>
                </span>
                <div class="text-muted mt-1" style="font-size: 0.75rem;">
                  <?= Security::e((string) ($pet['raza_nombre'] ?: 'Mestizo / N/D')) ?>
                </div>
              </td>
              <td>
                <div class="fw-semibold text-dark"><?= Security::e($ownerName) ?></div>
                <div class="text-muted" style="font-size: 0.74rem;">
                  DNI: <?= Security::e((string) ($pet['propietario_dni'] ?? '-')) ?>
                  <?php if (!empty($pet['propietario_celular'])): ?>
                    · <?= Security::e((string) $pet['propietario_celular']) ?>
                  <?php endif; ?>
                </div>
              </td>
              <td>
                <?php if ($hasAlert): ?>
                  <span class="badge bg-danger-subtle text-danger rounded-pill">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    <?= Security::e(mb_strimwidth((string) $pet['alertas_medicas'], 0, 35, '...')) ?>
                  </span>
                <?php else: ?>
                  <span class="text-muted">Sin alertas</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if (!empty($pet['qr_code'])): ?>
                  <a href="/qr/<?= Security::e(rawurlencode((string) $pet['qr_code'])) ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded-2" title="Abrir ficha pública QR">
                    <i class="bi bi-qr-code"></i>
                  </a>
                <?php else: ?>
                  <span class="text-muted">-</span>
                <?php endif; ?>
              </td>
              <td class="text-end text-nowrap">
                <?php if ($allowNew): ?>
                  <a href="/app/mascotas/editar/<?= (int) $pet['id'] ?>" class="btn btn-sm btn-outline-dark rounded-3 me-1" title="Editar Mascota">
                    <i class="bi bi-pencil-square me-1"></i>Editar
                  </a>
                <?php endif; ?>
                <a href="/app/kardex/<?= (int) $pet['id'] ?>" class="btn btn-sm btn-pk-primary rounded-3 px-3">
                  <i class="bi bi-clipboard2-pulse me-1"></i>Kardex
                </a>
                <a href="/app/kardex/certificado/<?= (int) $pet['id'] ?>" class="btn btn-sm btn-outline-dark rounded-3" title="Descargar Certificado PDF">
                  <i class="bi bi-file-earmark-pdf"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php if ($allowNew): ?>
<!-- MODAL: REGISTRAR NUEVA MASCOTA -->
<div class="modal fade" id="modalNuevaMascota" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content rounded-4 border-0">
      <div class="modal-header text-white" style="background: linear-gradient(135deg, #0D0D0D 0%, #2D1B69 100%);">
        <h5 class="modal-title h6 fw-bold">
          <i class="bi bi-plus-circle me-2" style="color: #C48CFF;"></i>Registrar Nueva Mascota
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form action="/app/pacientes/crear" method="POST">
        <div class="modal-body p-4">
          <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />

          <!-- Propietario -->
          <div class="p-3 rounded-3 border mb-4" style="background: #F8F5FF;">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
              <span class="fw-bold small" style="color: #5A3A8A;"><i class="bi bi-person-fill me-1"></i>Propietario / Tutor</span>
              <div class="btn-group btn-group-sm" role="group">
                <input type="radio" class="btn-check" name="propietario_modo" id="modoPropExistente" value="existente" <?= empty($propList) ? '' : 'checked' ?> />
                <label class="btn btn-outline-dark" for="modoPropExistente">Existente</label>
                <input type="radio" class="btn-check" name="propietario_modo" id="modoPropNuevo" value="nuevo" <?= empty($propList) ? 'checked' : '' ?> />
                <label class="btn btn-outline-dark" for="modoPropNuevo">Nuevo Propietario</label>
              </div>
            </div>

            <div id="boxPropExistente" class="<?= empty($propList) ? 'd-none' : '' ?>">
              <select name="propietario_id" id="selectPropId" class="form-select form-select-sm">
                <option value="">Selecciona un propietario...</option>
                <?php foreach ($propList as $pr): ?>
                  <option value="<?= (int) $pr['id'] ?>">
                    <?= Security::e(trim($pr['nombre'] . ' ' . $pr['apellido'])) ?> — DNI: <?= Security::e((string) $pr['dni']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div id="boxPropNuevo" class="row g-2 <?= empty($propList) ? '' : 'd-none' ?>">
              <div class="col-md-4">
                <input type="text" name="prop_nombre" class="form-control form-control-sm" placeholder="Nombre *" />
              </div>
              <div class="col-md-4">
                <input type="text" name="prop_apellido" class="form-control form-control-sm" placeholder="Apellido *" />
              </div>
              <div class="col-md-4">
                <input type="text" name="prop_dni" class="form-control form-control-sm" placeholder="DNI / Documento *" />
              </div>
              <div class="col-md-6">
                <input type="text" name="prop_celular" class="form-control form-control-sm" placeholder="Celular / WhatsApp" />
              </div>
              <div class="col-md-6">
                <input type="email" name="prop_email" class="form-control form-control-sm" placeholder="Correo electrónico (opcional)" />
              </div>
            </div>
          </div>

          <!-- Datos de la Mascota -->
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Nombre de la Mascota *</label>
              <input type="text" name="nombre" class="form-control" required placeholder="Ej: Max" />
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Especie *</label>
              <select name="especie_id" id="selectEspecie" class="form-select" required>
                <option value="">Selecciona...</option>
                <?php foreach ($especies ?? [] as $esp): ?>
                  <option value="<?= (int) $esp['id'] ?>"><?= Security::e((string) $esp['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Raza</label>
              <select name="raza_id" id="selectRaza" class="form-select">
                <option value="">Mestizo / Otra</option>
                <?php foreach ($razas ?? [] as $raz): ?>
                  <option value="<?= (int) $raz['id'] ?>" data-especie="<?= (int) $raz['especie_id'] ?>">
                    <?= Security::e((string) $raz['nombre']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label small fw-semibold">Sexo</label>
              <select name="sexo" class="form-select">
                <option value="macho">Macho</option>
                <option value="hembra">Hembra</option>
                <option value="desconocido">Desconocido</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Peso (kg)</label>
              <input type="number" step="0.01" min="0" name="peso_kg" class="form-control" placeholder="10.50" />
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Fecha Nacimiento</label>
              <input type="date" name="fecha_nacimiento" class="form-control" max="<?= date('Y-m-d') ?>" />
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Color</label>
              <input type="text" name="color" class="form-control" placeholder="Dorado / Negro" />
            </div>

            <div class="col-md-4">
              <label class="form-label small fw-semibold">Microchip</label>
              <input type="text" name="microchip" class="form-control" placeholder="Opcional" />
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Tipo de Sangre</label>
              <input type="text" name="sangre_tipo" class="form-control" placeholder="Ej: DEA 1.1+" />
            </div>
            <div class="col-md-4 d-flex align-items-end">
              <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" name="esterilizado" id="chkEsterilizado" />
                <label class="form-check-label small fw-semibold" for="chkEsterilizado">Esterilizado</label>
              </div>
            </div>

            <div class="col-12">
              <label class="form-label small fw-semibold">Alertas Médicas / Alergias Conocidas</label>
              <textarea name="alertas_medicas" class="form-control" rows="2" placeholder="Ej: Alérgico a penicilina, requiere medicación diaria..."></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary rounded-3" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-pk-primary px-4">Guardar Mascota y Generar QR</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    // Filtro instantáneo en la tabla
    const searchInput = document.getElementById('pacSearchInput');
    const rows = document.querySelectorAll('.js-pac-row');
    const countEl = document.getElementById('pacCount');

    if (searchInput && rows.length > 0) {
      searchInput.addEventListener('input', () => {
        const q = searchInput.value.toLowerCase().trim();
        let visible = 0;
        rows.forEach((tr) => {
          const match = !q || (tr.getAttribute('data-search') || '').includes(q);
          tr.style.display = match ? '' : 'none';
          if (match) visible++;
        });
        if (countEl) countEl.textContent = String(visible);
      });
    }

    // Alternar propietario existente / nuevo
    const modoExist = document.getElementById('modoPropExistente');
    const modoNuevo = document.getElementById('modoPropNuevo');
    const boxExist  = document.getElementById('boxPropExistente');
    const boxNuevo  = document.getElementById('boxPropNuevo');

    function syncPropMode() {
      const isNew = modoNuevo && modoNuevo.checked;
      if (boxExist) boxExist.classList.toggle('d-none', isNew);
      if (boxNuevo) boxNuevo.classList.toggle('d-none', !isNew);
    }

    modoExist?.addEventListener('change', syncPropMode);
    modoNuevo?.addEventListener('change', syncPropMode);

    // Filtrar razas por especie seleccionada
    const selEsp = document.getElementById('selectEspecie');
    const selRaz = document.getElementById('selectRaza');
    if (selEsp && selRaz) {
      const allRazas = Array.from(selRaz.querySelectorAll('option[data-especie]'));
      selEsp.addEventListener('change', () => {
        const espId = selEsp.value;
        selRaz.value = '';
        allRazas.forEach((opt) => {
          opt.hidden = Boolean(espId && opt.getAttribute('data-especie') !== espId);
        });
      });
    }
  });
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';


<?php
/**
 * PetKardex v2 - Gestor Dinámico de Servicios y Tarifas (Exclusivo SuperAdmin)
 *
 * Permite crear, editar precios (S/.) y retirar servicios clínicos, de estética
 * y hospitalización, alimentando en tiempo real la Caja POS y las cuentas médicas.
 *
 * Variables:
 * - array  $servicios
 * - string $search
 * - string $csrfToken
 */

use PetKardex\Helpers\Security;

$listaServicios = $servicios ?? [];
$qText          = (string) ($search ?? '');
$totalServicios = count($listaServicios);
$sumaPrecios    = 0.0;
$tarifaHosp     = 0.0;

foreach ($listaServicios as $srv) {
    $p = (float) ($srv['precio_venta'] ?? 0);
    $sumaPrecios += $p;
    if (($srv['codigo'] ?? '') === 'SRV-HOSP' || stripos((string) ($srv['nombre'] ?? ''), 'hospitaliz') !== false) {
        $tarifaHosp = $p;
    }
}
$tarifaPromedio = $totalServicios > 0 ? ($sumaPrecios / $totalServicios) : 0.0;

ob_start();
?>

<!-- KPIs Superiores de Servicios -->
<div class="row g-3 g-md-4 mb-4">
  <div class="col-md-4">
    <div class="pk-card pk-card-hover p-4 h-100">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <span class="text-muted small fw-semibold">Servicios Activos</span>
          <h3 class="h2 fw-bold mt-2 mb-1 text-dark"><?= $totalServicios ?></h3>
          <small class="text-muted">Sincronizados con Caja POS y Clínica</small>
        </div>
        <div class="pk-icon-box"><i class="bi bi-tags-fill"></i></div>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="pk-card pk-card-hover p-4 h-100">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <span class="text-muted small fw-semibold">Tarifa Promedio</span>
          <h3 class="h2 fw-bold mt-2 mb-1 text-dark">S/. <?= number_format($tarifaPromedio, 2) ?></h3>
          <small class="text-muted">Promedio general de prestaciones</small>
        </div>
        <div class="pk-icon-box"><i class="bi bi-cash-stack"></i></div>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="pk-card pk-card-hover p-4 h-100" style="border-left: 5px solid #C48CFF;">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <span class="text-muted small fw-semibold">Hospitalización / Día</span>
          <h3 class="h2 fw-bold mt-2 mb-1" style="color: #5A3A8A;">S/. <?= number_format($tarifaHosp, 2) ?></h3>
          <small class="text-muted">Tarifa automática al dar de alta (<code>SRV-HOSP</code>)</small>
        </div>
        <div class="pk-icon-box"><i class="bi bi-hospital-fill"></i></div>
      </div>
    </div>
  </div>
</div>

<!-- Barra de Búsqueda y Nuevo Servicio -->
<div class="pk-card p-3 p-md-4 mb-4">
  <div class="row g-3 align-items-center">
    <div class="col-lg-6">
      <form action="/app/servicios" method="GET" class="input-group">
        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
        <input type="text" id="srvSearchInput" name="q" value="<?= Security::e($qText) ?>" class="form-control border-start-0" placeholder="Buscar servicio por nombre, código o descripción..." />
        <button type="submit" class="btn btn-outline-dark">Buscar</button>
      </form>
    </div>
    <div class="col-lg-6 d-flex justify-content-lg-end flex-wrap gap-2">
      <a href="/app/caja" class="btn btn-outline-dark rounded-3 btn-sm px-3 d-inline-flex align-items-center">
        <i class="bi bi-cart-check me-1"></i>Ver en Caja POS
      </a>
      <button type="button" class="btn btn-pk-primary px-4" data-bs-toggle="modal" data-bs-target="#modalNuevoServicio">
        <i class="bi bi-plus-circle me-2"></i>Nuevo Servicio
      </button>
    </div>
  </div>
</div>

<!-- Tabla de Servicios y Tarifas -->
<div class="pk-card p-4">
  <div class="table-responsive">
    <table class="table table-hover align-middle small mb-0" id="tablaServicios">
      <thead class="table-light">
        <tr>
          <th>Código</th>
          <th>Servicio</th>
          <th>Descripción</th>
          <th class="text-end">Tarifa Vigente (S/.)</th>
          <th class="text-end">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($listaServicios)): ?>
          <tr>
            <td colspan="5" class="text-center py-5 text-muted">
              No hay servicios activos registrados. Haz clic en <strong>Nuevo Servicio</strong> para agregar tarifas.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($listaServicios as $srv): ?>
            <tr class="js-srv-row" data-search="<?= Security::e(strtolower(($srv['codigo'] ?? '') . ' ' . ($srv['nombre'] ?? '') . ' ' . ($srv['descripcion'] ?? ''))) ?>">
              <td><code><?= Security::e((string) $srv['codigo']) ?></code></td>
              <td>
                <strong class="text-dark"><?= Security::e((string) $srv['nombre']) ?></strong>
              </td>
              <td class="text-muted"><?= Security::e((string) ($srv['descripcion'] ?: 'Sin descripción')) ?></td>
              <td class="text-end fw-bold fs-6" style="color: #5A3A8A;">
                S/. <?= number_format((float) $srv['precio_venta'], 2) ?>
              </td>
              <td class="text-end text-nowrap">
                <button
                  type="button"
                  class="btn btn-sm btn-outline-dark rounded-3 me-1 js-edit-srv"
                  data-srv='<?= Security::e(json_encode([
                      'id'           => (int) $srv['id'],
                      'codigo'       => (string) $srv['codigo'],
                      'nombre'       => (string) $srv['nombre'],
                      'descripcion'  => (string) ($srv['descripcion'] ?? ''),
                      'precio_venta' => number_format((float) $srv['precio_venta'], 2, '.', ''),
                  ], JSON_UNESCAPED_UNICODE)) ?>'
                  data-bs-toggle="modal"
                  data-bs-target="#modalEditarServicio"
                >
                  <i class="bi bi-pencil-square me-1"></i>Editar Precio
                </button>
                <form action="/app/servicios/eliminar/<?= (int) $srv['id'] ?>" method="POST" class="d-inline" onsubmit="return confirm('¿Retirar este servicio del catálogo POS?');">
                  <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />
                  <button type="submit" class="btn btn-sm btn-outline-danger rounded-3" title="Eliminar Servicio">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- MODAL: NUEVO SERVICIO -->
<div class="modal fade" id="modalNuevoServicio" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
      <div class="modal-header text-white border-0 p-4" style="background: linear-gradient(135deg, #0D0D0D 0%, #2D1B69 100%);">
        <h5 class="modal-title h6 fw-bold"><i class="bi bi-tags-fill me-2" style="color: #C48CFF;"></i>Registrar Nuevo Servicio</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form action="/app/servicios/crear" method="POST">
        <div class="modal-body p-4">
          <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />
          <div class="row g-3">
            <div class="col-md-5">
              <div class="form-floating">
                <input type="text" name="codigo" id="newSrvCodigo" class="form-control" placeholder="SRV-ECO" required />
                <label for="newSrvCodigo">Código *</label>
              </div>
            </div>
            <div class="col-md-7">
              <div class="form-floating">
                <input type="number" step="0.01" min="0" name="precio_venta" id="newSrvPrecio" class="form-control" placeholder="0.00" required />
                <label for="newSrvPrecio">Precio (S/.) *</label>
              </div>
            </div>
            <div class="col-12">
              <div class="form-floating">
                <input type="text" name="nombre" id="newSrvNombre" class="form-control" placeholder="Nombre del servicio" required />
                <label for="newSrvNombre">Nombre del Servicio *</label>
              </div>
            </div>
            <div class="col-12">
              <div class="form-floating">
                <textarea name="descripcion" id="newSrvDesc" class="form-control" style="height: 85px;" placeholder="Descripción"></textarea>
                <label for="newSrvDesc">Descripción (opcional)</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light border-0 p-3 px-4">
          <button type="button" class="btn btn-outline-secondary rounded-3" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-pk-primary px-4">Guardar Servicio</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL: EDITAR SERVICIO Y TARIFA -->
<div class="modal fade" id="modalEditarServicio" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
      <div class="modal-header text-white border-0 p-4" style="background: linear-gradient(135deg, #0D0D0D 0%, #2D1B69 100%);">
        <h5 class="modal-title h6 fw-bold"><i class="bi bi-pencil-square me-2" style="color: #C48CFF;"></i>Editar Servicio y Tarifa</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form id="formEditarServicio" action="" method="POST">
        <div class="modal-body p-4">
          <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />
          <div class="row g-3">
            <div class="col-md-5">
              <div class="form-floating">
                <input type="text" name="codigo" id="editSrvCodigo" class="form-control" required />
                <label for="editSrvCodigo">Código *</label>
              </div>
            </div>
            <div class="col-md-7">
              <div class="form-floating">
                <input type="number" step="0.01" min="0" name="precio_venta" id="editSrvPrecio" class="form-control" required />
                <label for="editSrvPrecio">Precio Vigente (S/.) *</label>
              </div>
            </div>
            <div class="col-12">
              <div class="form-floating">
                <input type="text" name="nombre" id="editSrvNombre" class="form-control" required />
                <label for="editSrvNombre">Nombre del Servicio *</label>
              </div>
            </div>
            <div class="col-12">
              <div class="form-floating">
                <textarea name="descripcion" id="editSrvDesc" class="form-control" style="height: 85px;"></textarea>
                <label for="editSrvDesc">Descripción (opcional)</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light border-0 p-3 px-4">
          <button type="button" class="btn btn-outline-secondary rounded-3" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-pk-primary px-4">Actualizar Tarifa</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    const formEdit  = document.getElementById('formEditarServicio');
    const inCodigo  = document.getElementById('editSrvCodigo');
    const inNombre  = document.getElementById('editSrvNombre');
    const inPrecio  = document.getElementById('editSrvPrecio');
    const inDesc    = document.getElementById('editSrvDesc');
    const searchInp = document.getElementById('srvSearchInput');

    document.querySelectorAll('.js-edit-srv').forEach((btn) => {
      btn.addEventListener('click', () => {
        const raw = btn.getAttribute('data-srv');
        if (!raw || !formEdit) return;
        const s = JSON.parse(raw);

        formEdit.action = `/app/servicios/editar/${s.id}`;
        if (inCodigo) inCodigo.value = s.codigo || '';
        if (inNombre) inNombre.value = s.nombre || '';
        if (inPrecio) inPrecio.value = s.precio_venta || '0.00';
        if (inDesc)   inDesc.value   = s.descripcion || '';
      });
    });

    searchInp?.addEventListener('input', () => {
      const q = searchInp.value.trim().toLowerCase();
      document.querySelectorAll('.js-srv-row').forEach((row) => {
        const hay = row.getAttribute('data-search') || '';
        row.classList.toggle('d-none', q !== '' && !hay.includes(q));
      });
    });
  });
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';


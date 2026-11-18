<?php
/**
 * PetKardex v2 - Gestión de Inventario
 *
 * Exclusivo para Recepcionista/Cajero y SuperAdmin.
 * Incluye KPIs superiores, tabla con búsqueda JS en tiempo real,
 * alertas visuales de stock crítico y modales de producto y ajuste.
 *
 * Variables:
 * - array  $productos
 * - array  $categorias
 * - array  $criticos
 * - array  $stats
 * - string $csrfToken
 */

use PetKardex\Core\Auth;
use PetKardex\Helpers\Security;

$authInstance    = $auth ?? new Auth();
$isSuperAdmin    = $authInstance->hasRole('superadmin');
$listaProductos  = $productos ?? [];
$listaCategorias = $categorias ?? [];
$listaCriticos   = $criticos ?? [];
$kpis            = $stats ?? ['total_productos' => 0, 'valor_costo' => 0, 'valor_venta' => 0, 'stock_critico' => 0];

ob_start();
?>

<!-- Widgets KPI Superiores -->
<div class="row g-3 g-md-4 mb-4">
  <div class="col-md-4">
    <div class="pk-card pk-card-hover p-4 h-100">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <span class="text-muted small fw-semibold">Total de Productos</span>
          <h3 class="h2 fw-bold mt-2 mb-1 text-dark"><?= (int) $kpis['total_productos'] ?></h3>
          <small class="text-muted">Farmacia, Pet Shop e Insumos activos</small>
        </div>
        <div class="pk-icon-box"><i class="bi bi-box-seam-fill"></i></div>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="pk-card pk-card-hover p-4 h-100">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <span class="text-muted small fw-semibold">Valor del Inventario (Costo)</span>
          <h3 class="h2 fw-bold mt-2 mb-1 text-dark">S/. <?= number_format((float) $kpis['valor_costo'], 2) ?></h3>
          <small class="text-muted">Precio venta estimado: S/. <?= number_format((float) $kpis['valor_venta'], 2) ?></small>
        </div>
        <div class="pk-icon-box"><i class="bi bi-currency-dollar"></i></div>
      </div>
    </div>
  </div>

  <div class="col-md-4">
    <div class="pk-card pk-card-hover p-4 h-100 <?= ((int) $kpis['stock_critico'] > 0) ? 'border-danger border-2' : '' ?>"
         style="<?= ((int) $kpis['stock_critico'] > 0) ? 'background: linear-gradient(135deg, #FEF2F2 0%, #FFFFFF 100%);' : '' ?>">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <span class="small fw-bold <?= ((int) $kpis['stock_critico'] > 0) ? 'text-danger' : 'text-muted' ?>">
            Productos en Stock Crítico
          </span>
          <h3 class="h2 fw-bold mt-2 mb-1 <?= ((int) $kpis['stock_critico'] > 0) ? 'text-danger' : 'text-dark' ?>">
            <?= (int) $kpis['stock_critico'] ?>
          </h3>
          <small class="<?= ((int) $kpis['stock_critico'] > 0) ? 'text-danger' : 'text-muted' ?>">
            Por debajo o igual al stock mínimo
          </small>
        </div>
        <div class="pk-icon-box <?= ((int) $kpis['stock_critico'] > 0) ? 'bg-danger-subtle text-danger' : '' ?>">
          <i class="bi bi-exclamation-octagon-fill"></i>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Barra de Herramientas y Acciones -->
<div class="pk-card p-3 p-md-4 mb-4">
  <div class="row g-3 align-items-center">
    <div class="col-lg-4">
      <div class="input-group">
        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
        <input type="text" id="invSearchInput" class="form-control border-start-0" placeholder="Buscar por nombre, código o presentación..." />
      </div>
    </div>
    <div class="col-sm-6 col-lg-3">
      <select id="invCategoryFilter" class="form-select">
        <option value="">Todas las categorías</option>
        <?php foreach ($listaCategorias as $cat): ?>
          <option value="<?= (int) $cat['id'] ?>"><?= Security::e((string) $cat['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-sm-6 col-lg-5 d-flex justify-content-lg-end flex-wrap gap-2">
      <button type="button" id="btnFilterCritico" class="btn btn-outline-danger rounded-3 btn-sm px-3">
        <i class="bi bi-exclamation-triangle me-1"></i>Solo Críticos
      </button>
      <a href="/app/inventario/movimientos" class="btn btn-outline-dark rounded-3 btn-sm px-3">
        <i class="bi bi-clock-history me-1"></i>Movimientos
      </a>
      <?php if ($isSuperAdmin): ?>
        <a href="/app/servicios" class="btn btn-outline-dark rounded-3 btn-sm px-3">
          <i class="bi bi-tags-fill me-1"></i>Servicios
        </a>
      <?php endif; ?>
      <button type="button" class="btn btn-dark rounded-3 btn-sm px-3" data-bs-toggle="modal" data-bs-target="#modalAjusteStock">
        <i class="bi bi-arrow-left-right me-1"></i>Ajustar Stock
      </button>
      <button type="button" class="btn btn-pk-primary btn-sm px-3" data-bs-toggle="modal" data-bs-target="#modalNuevoProducto">
        <i class="bi bi-plus-circle me-1"></i>Agregar Producto
      </button>
    </div>
  </div>
</div>

<!-- Tabla Interactiva de Productos -->
<div class="pk-card p-4">
  <div class="table-responsive">
    <table class="table table-hover align-middle small mb-0" id="productosTable">
      <thead class="table-light">
        <tr>
          <th>Código</th>
          <th>Producto / Presentación</th>
          <th>Categoría</th>
          <th class="text-end">P. Compra</th>
          <th class="text-end">P. Venta</th>
          <th class="text-center">Stock Actual</th>
          <th class="text-center">Mínimo</th>
          <th class="text-end">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($listaProductos)): ?>
          <tr id="emptyRow">
            <td colspan="8" class="text-center py-5 text-muted">
              No hay productos registrados todavía. Haz clic en <strong>Agregar Producto</strong> para comenzar.
            </td>
          </tr>
        <?php else: ?>
          <?php foreach ($listaProductos as $prod): ?>
            <?php
              $esServicio = ($prod['categoria_tipo'] ?? '') === 'servicio';
              $esCritico  = !$esServicio && ((float) $prod['stock_actual'] <= (float) $prod['stock_minimo']);
            ?>
            <tr
              class="js-prod-row <?= $esCritico ? 'table-danger' : '' ?>"
              data-search="<?= Security::e(strtolower($prod['codigo'] . ' ' . $prod['nombre'] . ' ' . ($prod['presentacion'] ?? ''))) ?>"
              data-cat="<?= (int) $prod['categoria_id'] ?>"
              data-critico="<?= $esCritico ? '1' : '0' ?>"
            >
              <td><code><?= Security::e((string) $prod['codigo']) ?></code></td>
              <td>
                <strong class="text-dark"><?= Security::e((string) $prod['nombre']) ?></strong>
                <?php if ((int) ($prod['requiere_receta'] ?? 0) === 1): ?>
                  <span class="badge bg-dark text-white ms-1" style="font-size:0.65rem;">Rx</span>
                <?php endif; ?>
                <?php if (!empty($prod['presentacion'])): ?>
                  <div class="text-muted" style="font-size: 0.75rem;"><?= Security::e((string) $prod['presentacion']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <span class="badge rounded-pill" style="background: rgba(196,140,255,0.2); color: #5A3A8A;">
                  <?= Security::e((string) $prod['categoria_nombre']) ?>
                </span>
              </td>
              <td class="text-end">S/. <?= number_format((float) $prod['precio_compra'], 2) ?></td>
              <td class="text-end fw-semibold">S/. <?= number_format((float) $prod['precio_venta'], 2) ?></td>
              <td class="text-center">
                <?php if ($esServicio): ?>
                  <span class="badge bg-light text-dark border">Servicio</span>
                <?php elseif ($esCritico): ?>
                  <span class="badge bg-danger rounded-pill px-3">
                    <i class="bi bi-exclamation-circle me-1"></i><?= number_format((float) $prod['stock_actual'], 2) ?>
                  </span>
                <?php else: ?>
                  <span class="badge bg-success-subtle text-success rounded-pill px-3">
                    <?= number_format((float) $prod['stock_actual'], 2) ?>
                  </span>
                <?php endif; ?>
              </td>
              <td class="text-center text-muted"><?= $esServicio ? '-' : number_format((float) $prod['stock_minimo'], 2) ?></td>
              <td class="text-end text-nowrap">
                <button
                  type="button"
                  class="btn btn-sm btn-outline-dark rounded-2 js-btn-ajustar"
                  data-id="<?= (int) $prod['id'] ?>"
                  data-nombre="<?= Security::e((string) $prod['nombre']) ?>"
                  data-bs-toggle="modal"
                  data-bs-target="#modalAjusteStock"
                  title="Ajustar Stock"
                >
                  <i class="bi bi-plus-slash-minus"></i>
                </button>
                <button
                  type="button"
                  class="btn btn-sm btn-outline-primary rounded-2 js-btn-editar"
                  data-prod='<?= Security::e(json_encode($prod, JSON_UNESCAPED_UNICODE)) ?>'
                  data-bs-toggle="modal"
                  data-bs-target="#modalEditarProducto"
                  title="Editar Producto"
                >
                  <i class="bi bi-pencil"></i>
                </button>
                <form action="/app/inventario/eliminar/<?= (int) $prod['id'] ?>" method="POST" class="d-inline js-confirm-retiro">
                  <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />
                  <button type="submit" class="btn btn-sm btn-outline-danger rounded-2" title="Retirar Producto">
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

<!-- MODAL: AGREGAR PRODUCTO -->
<div class="modal fade" id="modalNuevoProducto" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content rounded-4 border-0">
      <div class="modal-header text-white" style="background: linear-gradient(135deg, #0D0D0D 0%, #2D1B69 100%);">
        <h5 class="modal-title h6 fw-bold"><i class="bi bi-box-seam me-2" style="color: #C48CFF;"></i>Agregar Nuevo Producto</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form action="/app/inventario/crear" method="POST">
        <div class="modal-body p-4">
          <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Código SKU *</label>
              <input type="text" name="codigo" class="form-control" required placeholder="Ej: MED-001" />
            </div>
            <div class="col-md-8">
              <label class="form-label small fw-semibold">Nombre del Producto *</label>
              <input type="text" name="nombre" class="form-control" required placeholder="Ej: Amoxicilina Veterinaria 250mg" />
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Categoría *</label>
              <select name="categoria_id" class="form-select" required>
                <option value="">Selecciona...</option>
                <?php foreach ($listaCategorias as $cat): ?>
                  <?php if (($cat['tipo'] ?? '') === 'servicio' && !$isSuperAdmin) continue; ?>
                  <option value="<?= (int) $cat['id'] ?>"><?= Security::e((string) $cat['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Presentación</label>
              <input type="text" name="presentacion" class="form-control" placeholder="Caja x 20 tab" />
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Unidad</label>
              <input type="text" name="unidad_medida" class="form-control" value="Unidad" />
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Precio Compra</label>
              <input type="number" step="0.01" min="0" name="precio_compra" class="form-control" value="0.00" />
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Precio Venta *</label>
              <input type="number" step="0.01" min="0" name="precio_venta" class="form-control" required value="0.00" />
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Stock Inicial</label>
              <input type="number" step="0.01" min="0" name="stock_actual" class="form-control" value="0" />
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Stock Mínimo</label>
              <input type="number" step="0.01" min="0" name="stock_minimo" class="form-control" value="5" />
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="requiere_receta" id="chkNewRx" />
                <label class="form-check-label small" for="chkNewRx">Requiere receta médica veterinaria</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary rounded-3" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-pk-primary px-4">Guardar Producto</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL: EDITAR PRODUCTO -->
<div class="modal fade" id="modalEditarProducto" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content rounded-4 border-0">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title h6 fw-bold">Editar Producto</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form id="formEditarProducto" action="" method="POST">
        <div class="modal-body p-4">
          <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />
          <div class="row g-3">
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Código SKU *</label>
              <input type="text" name="codigo" id="editCod" class="form-control" required />
            </div>
            <div class="col-md-8">
              <label class="form-label small fw-semibold">Nombre *</label>
              <input type="text" name="nombre" id="editNom" class="form-control" required />
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Categoría *</label>
              <select name="categoria_id" id="editCat" class="form-select" required>
                <?php foreach ($listaCategorias as $cat): ?>
                  <?php if (($cat['tipo'] ?? '') === 'servicio' && !$isSuperAdmin) continue; ?>
                  <option value="<?= (int) $cat['id'] ?>"><?= Security::e((string) $cat['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Presentación</label>
              <input type="text" name="presentacion" id="editPres" class="form-control" />
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Precio Compra</label>
              <input type="number" step="0.01" min="0" name="precio_compra" id="editPCompra" class="form-control" />
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Precio Venta *</label>
              <input type="number" step="0.01" min="0" name="precio_venta" id="editPVenta" class="form-control" required />
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Stock Mínimo</label>
              <input type="number" step="0.01" min="0" name="stock_minimo" id="editSMin" class="form-control" />
            </div>
            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="requiere_receta" id="editRx" />
                <label class="form-check-label small" for="editRx">Requiere receta médica</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary rounded-3" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-pk-primary px-4">Actualizar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL: AJUSTE DE INVENTARIO -->
<div class="modal fade" id="modalAjusteStock" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content rounded-4 border-0">
      <div class="modal-header text-white" style="background: linear-gradient(135deg, #0D0D0D 0%, #2D1B69 100%);">
        <h5 class="modal-title h6 fw-bold"><i class="bi bi-arrow-left-right me-2" style="color: #C48CFF;"></i>Ajuste de Inventario</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form action="/app/inventario/ajustar" method="POST">
        <div class="modal-body p-4">
          <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />
          <div class="mb-3">
            <label class="form-label small fw-semibold">Producto *</label>
            <select name="producto_id" id="ajusteProdId" class="form-select" required>
              <option value="">Selecciona un producto...</option>
              <?php foreach ($listaProductos as $prod): ?>
                <?php if (($prod['categoria_tipo'] ?? '') === 'servicio') continue; ?>
                <option value="<?= (int) $prod['id'] ?>">
                  [<?= Security::e((string) $prod['codigo']) ?>] <?= Security::e((string) $prod['nombre']) ?> (Actual: <?= number_format((float) $prod['stock_actual'], 2) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label small fw-semibold">Tipo de Movimiento *</label>
              <select name="tipo" class="form-select" required>
                <option value="entrada">Entrada (Compra / Reposición)</option>
                <option value="salida">Salida (Merma / Uso interno)</option>
                <option value="ajuste">Ajuste Directo (Conteo físico)</option>
                <option value="devolucion">Devolución</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Cantidad *</label>
              <input type="number" step="0.01" min="0.01" name="cantidad" class="form-control" required placeholder="Ej: 10" />
            </div>
          </div>
          <div class="mb-0">
            <label class="form-label small fw-semibold">Motivo / Justificación *</label>
            <textarea name="motivo" class="form-control" rows="2" required placeholder="Ej: Ingreso factura proveedor #F001-452 o conteo mensual..."></textarea>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary rounded-3" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-pk-primary px-4">Aplicar Movimiento</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    const searchInput = document.getElementById('invSearchInput');
    const catSelect = document.getElementById('invCategoryFilter');
    const btnCritico = document.getElementById('btnFilterCritico');
    const rows = document.querySelectorAll('.js-prod-row');
    let onlyCritical = false;

    function filterTable() {
      const q = (searchInput.value || '').toLowerCase().trim();
      const cat = catSelect.value;

      rows.forEach((tr) => {
        const text = tr.getAttribute('data-search') || '';
        const rowCat = tr.getAttribute('data-cat') || '';
        const isCrit = tr.getAttribute('data-critico') === '1';

        const matchQ = !q || text.includes(q);
        const matchCat = !cat || rowCat === cat;
        const matchCrit = !onlyCritical || isCrit;

        tr.style.display = (matchQ && matchCat && matchCrit) ? '' : 'none';
      });
    }

    if (searchInput) searchInput.addEventListener('input', filterTable);
    if (catSelect) catSelect.addEventListener('change', filterTable);
    if (btnCritico) {
      btnCritico.addEventListener('click', () => {
        onlyCritical = !onlyCritical;
        btnCritico.classList.toggle('btn-danger', onlyCritical);
        btnCritico.classList.toggle('btn-outline-danger', !onlyCritical);
        filterTable();
      });
    }

    document.querySelectorAll('.js-btn-ajustar').forEach((btn) => {
      btn.addEventListener('click', () => {
        const sel = document.getElementById('ajusteProdId');
        if (sel) sel.value = btn.getAttribute('data-id') || '';
      });
    });

    document.querySelectorAll('.js-btn-editar').forEach((btn) => {
      btn.addEventListener('click', () => {
        const p = JSON.parse(btn.getAttribute('data-prod') || '{}');
        const form = document.getElementById('formEditarProducto');
        if (form) form.action = `/app/inventario/editar/${p.id}`;
        document.getElementById('editCod').value = p.codigo || '';
        document.getElementById('editNom').value = p.nombre || '';
        document.getElementById('editCat').value = p.categoria_id || '';
        document.getElementById('editPres').value = p.presentacion || '';
        document.getElementById('editPCompra').value = p.precio_compra || '0.00';
        document.getElementById('editPVenta').value = p.precio_venta || '0.00';
        document.getElementById('editSMin').value = p.stock_minimo || '0';
        document.getElementById('editRx').checked = Number(p.requiere_receta) === 1;
      });
    });

    document.querySelectorAll('.js-confirm-retiro').forEach((form) => {
      form.addEventListener('submit', (e) => {
        if (!window.confirm('¿Retirar este producto del catálogo activo?')) {
          e.preventDefault();
        }
      });
    });
  });
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';


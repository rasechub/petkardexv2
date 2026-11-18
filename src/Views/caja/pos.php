<?php
/**
 * PetKardex v2 - Punto de Venta (Caja POS)
 *
 * Izquierda: Liquidación de cuentas médicas (por DNI o QR) + Buscador de productos y servicios.
 * Derecha: Ticket / Carrito interactivo con Subtotal, IGV/IVA, Total y Procesar Pago.
 *
 * Variables:
 * - array  $catalogo
 * - array  $categorias
 * - array  $configFinanciera
 * - array  $ultimasVentas
 * - string $csrfToken
 */

use PetKardex\Core\Auth;
use PetKardex\Helpers\Security;

$authInstance  = $auth ?? new Auth();
$cfg           = $configFinanciera ?? ['moneda_simbolo' => 'S/.', 'impuesto_porcentaje' => '18'];
$moneda        = (string) ($cfg['moneda_simbolo'] ?? 'S/.');
$taxPct        = (float) ($cfg['impuesto_porcentaje'] ?? 18);
$itemsCatalogo = $catalogo ?? [];

ob_start();
?>

<div class="row g-4">
  <!-- ═══════════════════════════════════════════════════════════════════
       COLUMNA IZQUIERDA: LIQUIDAR CUENTA MÉDICA + CATÁLOGO POS
       ═══════════════════════════════════════════════════════════════════ -->
  <div class="col-lg-7">

    <!-- Buscador de cuentas médicas por DNI o UUID QR -->
    <div class="pk-card p-4 mb-4" style="border-left: 5px solid #C48CFF;">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h2 class="h6 fw-bold mb-0">
          <i class="bi bi-person-vcard-fill me-2" style="color: #7B52AB;"></i>Liquidar Cuenta Médica (DNI Tutor o QR Mascota)
        </h2>
        <span class="badge rounded-pill" style="background: rgba(196,140,255,0.2); color: #5A3A8A;">Consulta / Receta</span>
      </div>
      <form id="buscarCuentaForm" class="row g-2">
        <div class="col-sm-9">
          <input type="text" id="cuentaQueryInput" class="form-control" placeholder="Ingresa DNI del dueño o UUID / URL QR de la mascota..." />
        </div>
        <div class="col-sm-3 d-grid">
          <button type="submit" class="btn btn-dark fw-semibold">
            <i class="bi bi-search me-1"></i>Buscar
          </button>
        </div>
      </form>

      <!-- Resultado dinámico de cuenta médica -->
      <div id="cuentaResultadoBox" class="mt-3 d-none"></div>
    </div>

    <!-- Buscador y catálogo de productos y servicios -->
    <div class="pk-card p-4">
      <div class="row g-2 align-items-center mb-3">
        <div class="col-md-7">
          <div class="input-group">
            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
            <input type="text" id="posSearchInput" class="form-control border-start-0" placeholder="Buscar producto o servicio (nombre o código)..." />
          </div>
        </div>
        <div class="col-md-5 d-flex gap-2">
          <select id="posCatFilter" class="form-select">
            <option value="">Todos (Productos y Servicios)</option>
            <option value="servicio">Servicios Clínicos / Estética</option>
            <option value="medicamento">Medicamentos</option>
            <option value="alimento">Alimentos Pet Shop</option>
            <option value="accesorio">Accesorios</option>
            <option value="consumible">Consumibles</option>
          </select>
          <?php if ($authInstance->hasRole('superadmin')): ?>
            <a href="/app/servicios" class="btn btn-outline-dark text-nowrap" title="Administrar tarifas de servicios">
              <i class="bi bi-tags-fill"></i>
            </a>
          <?php endif; ?>
        </div>
      </div>

      <div class="row g-2" id="posCatalogoGrid" style="max-height: 460px; overflow-y: auto;">
        <?php foreach ($itemsCatalogo as $item): ?>
          <?php
            $esServicio = ($item['categoria_tipo'] ?? '') === 'servicio';
            $sinStock   = !$esServicio && ((float) ($item['stock_actual'] ?? 0) <= 0);
          ?>
          <div
            class="col-sm-6 col-xl-4 js-pos-item"
            data-search="<?= Security::e(strtolower(($item['codigo'] ?? '') . ' ' . ($item['nombre'] ?? ''))) ?>"
            data-tipo="<?= Security::e((string) ($item['categoria_tipo'] ?? '')) ?>"
          >
            <button
              type="button"
              class="btn btn-light border w-100 h-100 text-start p-3 rounded-3 js-add-cart <?= $sinStock ? 'opacity-50' : '' ?>"
              data-item='<?= Security::e(json_encode([
                  'producto_id'     => $item['id'] ? (int) $item['id'] : null,
                  'codigo'          => $item['codigo'] ?? '',
                  'descripcion'     => $item['nombre'] ?? '',
                  'precio_unitario' => (float) ($item['precio_venta'] ?? 0),
                  'stock_actual'    => $esServicio ? 9999 : (float) ($item['stock_actual'] ?? 0),
                  'es_servicio'     => $esServicio,
              ], JSON_UNESCAPED_UNICODE)) ?>'
              <?= $sinStock ? 'disabled' : '' ?>
            >
              <div class="d-flex justify-content-between align-items-start gap-1 mb-1">
                <span class="badge rounded-pill" style="font-size: 0.65rem; background: <?= $esServicio ? 'rgba(196,140,255,0.22)' : '#E5E7EB' ?>; color: <?= $esServicio ? '#5A3A8A' : '#374151' ?>;">
                  <?= Security::e((string) ($item['categoria_nombre'] ?? 'Item')) ?>
                </span>
                <small class="text-muted" style="font-size: 0.68rem;"><?= Security::e((string) ($item['codigo'] ?? '')) ?></small>
              </div>
              <div class="fw-semibold small text-dark text-truncate" title="<?= Security::e((string) ($item['nombre'] ?? '')) ?>">
                <?= Security::e((string) ($item['nombre'] ?? '')) ?>
              </div>
              <div class="d-flex justify-content-between align-items-center mt-2">
                <strong style="color: #7B52AB;"><?= Security::e($moneda) ?> <?= number_format((float) ($item['precio_venta'] ?? 0), 2) ?></strong>
                <?php if (!$esServicio): ?>
                  <small class="text-muted" style="font-size: 0.7rem;">Stock: <?= number_format((float) ($item['stock_actual'] ?? 0), 0) ?></small>
                <?php else: ?>
                  <small class="text-success" style="font-size: 0.7rem;">+ Agregar</small>
                <?php endif; ?>
              </div>
            </button>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>

  <!-- ═══════════════════════════════════════════════════════════════════
       COLUMNA DERECHA: TICKET / CARRITO INTERACTIVO
       ═══════════════════════════════════════════════════════════════════ -->
  <div class="col-lg-5">
    <div class="pk-card overflow-hidden d-flex flex-column h-100">
      <div class="p-3 px-4 text-white d-flex justify-content-between align-items-center"
           style="background: linear-gradient(135deg, #0D0D0D 0%, #2D1B69 100%);">
        <div>
          <h3 class="h6 fw-bold mb-0"><i class="bi bi-receipt-cutoff me-2" style="color: #C48CFF;"></i>Ticket de Venta</h3>
          <small id="ticketClienteLabel" class="text-white-50">Cliente Mostrador</small>
        </div>
        <button type="button" id="btnLimpiarTicket" class="btn btn-sm btn-outline-light rounded-pill px-3">
          Vaciar
        </button>
      </div>

      <div class="p-3 flex-grow-1" style="min-height: 260px; max-height: 360px; overflow-y: auto;">
        <div id="ticketEmptyState" class="text-center py-5 text-muted">
          <i class="bi bi-cart3 fs-1 d-block mb-2" style="color: #C48CFF;"></i>
          <p class="small mb-0">Haz clic en productos, servicios o carga una cuenta médica para armar el ticket.</p>
        </div>
        <div id="ticketItemsList" class="d-grid gap-2"></div>
      </div>

      <!-- Totales -->
      <div class="p-4 border-top bg-light">
        <div class="mb-2">
          <label for="ticketClienteNombre" class="form-label small fw-semibold mb-1">Nombre en Comprobante</label>
          <input type="text" id="ticketClienteNombre" class="form-control form-control-sm" value="Cliente Mostrador" />
        </div>

        <div class="d-flex justify-content-between align-items-center small mb-1 mt-3">
          <span class="text-muted">Subtotal</span>
          <strong id="lblSubtotal"><?= Security::e($moneda) ?> 0.00</strong>
        </div>

        <div class="d-flex justify-content-between align-items-center small mb-1">
          <span class="text-muted">Descuento (<?= Security::e($moneda) ?>)</span>
          <input type="number" id="inputDescuento" step="0.50" min="0" value="0" class="form-control form-control-sm text-end" style="width: 100px;" />
        </div>

        <div class="d-flex justify-content-between align-items-center small mb-2">
          <span class="text-muted">IGV / IVA (<?= number_format($taxPct, 0) ?>%)</span>
          <strong id="lblImpuesto"><?= Security::e($moneda) ?> 0.00</strong>
        </div>

        <div class="d-flex justify-content-between align-items-center py-2 border-top border-dark-subtle mb-3">
          <span class="h6 fw-bold mb-0">TOTAL A PAGAR</span>
          <span class="h4 fw-bold mb-0" style="color: #7B52AB;" id="lblTotal"><?= Security::e($moneda) ?> 0.00</span>
        </div>

        <div class="d-grid">
          <button type="button" id="btnAbrirPago" class="btn btn-pk-primary py-3 fs-6" disabled data-bs-toggle="modal" data-bs-target="#modalProcesarPago">
            <i class="bi bi-cash-stack me-2"></i>Procesar Pago
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Últimas ventas realizadas -->
<?php if (!empty($ultimasVentas)): ?>
  <div class="pk-card p-4 mt-4">
    <h4 class="h6 fw-bold mb-3"><i class="bi bi-clock-history me-2" style="color: #7B52AB;"></i>Últimos Comprobantes Emitidos</h4>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle small mb-0">
        <thead class="table-light">
          <tr>
            <th>Comprobante</th>
            <th>Fecha</th>
            <th>Cliente</th>
            <th>Método</th>
            <th>Estado</th>
            <th class="text-end">Total</th>
            <th class="text-end">Ticket</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ultimasVentas as $v): ?>
            <tr>
              <td><code><?= Security::e((string) $v['numero_venta']) ?></code></td>
              <td><?= Security::e(date('d/m/Y H:i', strtotime((string) $v['fecha']))) ?></td>
              <td><?= Security::e((string) ($v['cliente_nombre'] ?? 'Mostrador')) ?></td>
              <td><?= Security::e(ucfirst((string) $v['metodo_pago'])) ?></td>
              <td>
                <span class="badge rounded-pill <?= $v['estado'] === 'pagada' ? 'bg-success-subtle text-success' : ($v['estado'] === 'anulada' ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning-emphasis') ?>">
                  <?= Security::e(ucfirst((string) $v['estado'])) ?>
                </span>
              </td>
              <td class="text-end fw-bold"><?= Security::e($moneda) ?> <?= number_format((float) $v['total'], 2) ?></td>
              <td class="text-end">
                <a href="/app/caja/recibo/<?= (int) $v['id'] ?>" target="_blank" class="btn btn-sm btn-outline-dark rounded-2">
                  <i class="bi bi-printer"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>

<!-- MODAL: PROCESAR PAGO Y CALCULAR VUELTO -->
<div class="modal fade" id="modalProcesarPago" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content rounded-4 border-0">
      <div class="modal-header text-white" style="background: linear-gradient(135deg, #0D0D0D 0%, #2D1B69 100%);">
        <h5 class="modal-title h6 fw-bold"><i class="bi bi-credit-card-2-front me-2" style="color: #C48CFF;"></i>Finalizar Cobro</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body p-4">
        <div class="p-3 rounded-3 text-center mb-3" style="background: #F8F5FF; border: 1px solid rgba(196,140,255,0.4);">
          <small class="text-muted d-block">Total a Cobrar</small>
          <span class="h2 fw-bold" style="color: #5A3A8A;" id="modalTotalPagar"><?= Security::e($moneda) ?> 0.00</span>
        </div>

        <div class="mb-3">
          <label class="form-label small fw-semibold">Método de Pago</label>
          <div class="row g-2">
            <div class="col-6">
              <input type="radio" class="btn-check" name="metodoPago" id="mpEfectivo" value="efectivo" checked />
              <label class="btn btn-outline-dark w-100 py-2 small fw-semibold" for="mpEfectivo"><i class="bi bi-cash me-1"></i>Efectivo</label>
            </div>
            <div class="col-6">
              <input type="radio" class="btn-check" name="metodoPago" id="mpTarjeta" value="tarjeta" />
              <label class="btn btn-outline-dark w-100 py-2 small fw-semibold" for="mpTarjeta"><i class="bi bi-credit-card me-1"></i>Tarjeta</label>
            </div>
            <div class="col-6">
              <input type="radio" class="btn-check" name="metodoPago" id="mpTransferencia" value="transferencia" />
              <label class="btn btn-outline-dark w-100 py-2 small fw-semibold" for="mpTransferencia"><i class="bi bi-bank me-1"></i>Transferencia</label>
            </div>
            <div class="col-6">
              <input type="radio" class="btn-check" name="metodoPago" id="mpMixto" value="mixto" />
              <label class="btn btn-outline-dark w-100 py-2 small fw-semibold" for="mpMixto"><i class="bi bi-wallet2 me-1"></i>Mixto</label>
            </div>
          </div>
        </div>

        <div id="efectivoCalcBox" class="mb-3">
          <div class="row g-2 align-items-center">
            <div class="col-6">
              <label class="form-label small fw-semibold">Monto Recibido</label>
              <input type="number" step="0.50" min="0" id="inputMontoRecibido" class="form-control" placeholder="0.00" />
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Vuelto / Cambio</label>
              <div class="form-control bg-light fw-bold text-success" id="lblVuelto"><?= Security::e($moneda) ?> 0.00</div>
            </div>
          </div>
        </div>

        <div class="mb-2">
          <label class="form-label small fw-semibold">Notas del comprobante (opcional)</label>
          <input type="text" id="inputNotasVenta" class="form-control form-control-sm" placeholder="Ej: Operación POS #..." />
        </div>

        <div id="pagoErrorBox" class="alert alert-danger small d-none mt-3 mb-0"></div>
        <div id="pagoSuccessBox" class="alert alert-success small d-none mt-3 mb-0"></div>
      </div>
      <div class="modal-footer bg-light">
        <button type="button" class="btn btn-outline-secondary rounded-3" data-bs-dismiss="modal">Cerrar</button>
        <button type="button" id="btnConfirmarPago" class="btn btn-pk-primary px-4">
          Confirmar y Emitir Comprobante
        </button>
      </div>
    </div>
  </div>
</div>

<script>
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = <?= json_encode((string) ($csrfToken ?? ''), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const moneda = <?= json_encode($moneda, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    const taxRate = <?= json_encode($taxPct / 100.0) ?>;

    let cart = [];
    let linkedOwnerId = null;
    let pendingSaleId = null;

    const searchInput = document.getElementById('posSearchInput');
    const catFilter = document.getElementById('posCatFilter');
    const catalogNodes = document.querySelectorAll('.js-pos-item');

    const emptyState = document.getElementById('ticketEmptyState');
    const ticketList = document.getElementById('ticketItemsList');
    const lblSubtotal = document.getElementById('lblSubtotal');
    const lblImpuesto = document.getElementById('lblImpuesto');
    const lblTotal = document.getElementById('lblTotal');
    const inputDescuento = document.getElementById('inputDescuento');
    const btnAbrirPago = document.getElementById('btnAbrirPago');
    const btnLimpiar = document.getElementById('btnLimpiarTicket');
    const clienteNombreInput = document.getElementById('ticketClienteNombre');
    const clienteLabel = document.getElementById('ticketClienteLabel');

    const modalTotal = document.getElementById('modalTotalPagar');
    const efectivoBox = document.getElementById('efectivoCalcBox');
    const montoRecibidoInput = document.getElementById('inputMontoRecibido');
    const lblVuelto = document.getElementById('lblVuelto');
    const btnConfirmarPago = document.getElementById('btnConfirmarPago');
    const pagoErrorBox = document.getElementById('pagoErrorBox');
    const pagoSuccessBox = document.getElementById('pagoSuccessBox');

    function fmt(num) {
      return `${moneda} ${Number(num || 0).toFixed(2)}`;
    }

    function computeTotals() {
      const subtotal = cart.reduce((acc, it) => acc + (it.cantidad * it.precio_unitario), 0);
      const descuento = Math.max(0, parseFloat(inputDescuento.value || '0') || 0);
      const base = Math.max(0, subtotal - descuento);
      const impuesto = base * taxRate;
      const total = base + impuesto;
      return { subtotal, descuento, impuesto, total };
    }

    function updateVuelto() {
      const { total } = computeTotals();
      const recibido = parseFloat(montoRecibidoInput.value || '0') || 0;
      const vuelto = Math.max(0, recibido - total);
      lblVuelto.textContent = fmt(vuelto);
    }

    function renderCart() {
      ticketList.innerHTML = '';
      if (cart.length === 0) {
        emptyState.classList.remove('d-none');
        btnAbrirPago.disabled = true;
      } else {
        emptyState.classList.add('d-none');
        btnAbrirPago.disabled = false;
      }

      cart.forEach((it, idx) => {
        const lineTotal = it.cantidad * it.precio_unitario;
        const card = document.createElement('div');
        card.className = 'p-2 px-3 rounded-3 border bg-white d-flex justify-content-between align-items-center gap-2';
        card.innerHTML = `
          <div class="flex-grow-1 overflow-hidden">
            <div class="fw-semibold small text-truncate">${it.descripcion}</div>
            <div class="text-muted" style="font-size:0.74rem;">${fmt(it.precio_unitario)} c/u</div>
          </div>
          <div class="d-flex align-items-center gap-1">
            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 js-qty-dec">-</button>
            <span class="px-2 small fw-bold">${it.cantidad}</span>
            <button type="button" class="btn btn-sm btn-outline-secondary py-0 px-2 js-qty-inc">+</button>
          </div>
          <div class="text-end fw-bold small" style="min-width:72px;">${fmt(lineTotal)}</div>
          <button type="button" class="btn btn-sm text-danger p-0 ms-1 js-rm-item" title="Quitar"><i class="bi bi-x-lg"></i></button>
        `;

        card.querySelector('.js-qty-dec').addEventListener('click', () => {
          if (it.cantidad > 1) {
            it.cantidad -= 1;
          } else {
            cart.splice(idx, 1);
          }
          renderCart();
        });
        card.querySelector('.js-qty-inc').addEventListener('click', () => {
          if (it.es_servicio || it.cantidad < it.stock_actual) {
            it.cantidad += 1;
            renderCart();
          }
        });
        card.querySelector('.js-rm-item').addEventListener('click', () => {
          cart.splice(idx, 1);
          renderCart();
        });

        ticketList.appendChild(card);
      });

      const totals = computeTotals();
      lblSubtotal.textContent = fmt(totals.subtotal);
      lblImpuesto.textContent = fmt(totals.impuesto);
      lblTotal.textContent = fmt(totals.total);
      modalTotal.textContent = fmt(totals.total);
      updateVuelto();
    }

    function addItemToCart(item) {
      const existing = cart.find((c) =>
        (item.producto_id && c.producto_id === item.producto_id) ||
        (!item.producto_id && c.descripcion === item.descripcion)
      );

      if (existing) {
        if (existing.es_servicio || existing.cantidad + (item.cantidad || 1) <= existing.stock_actual) {
          existing.cantidad += (item.cantidad || 1);
        }
      } else {
        cart.push({
          producto_id: item.producto_id || null,
          descripcion: item.descripcion,
          precio_unitario: Number(item.precio_unitario || 0),
          cantidad: Number(item.cantidad || 1),
          stock_actual: Number(item.stock_actual || 9999),
          es_servicio: Boolean(item.es_servicio),
        });
      }
      renderCart();
    }

    document.querySelectorAll('.js-add-cart').forEach((btn) => {
      btn.addEventListener('click', () => {
        const item = JSON.parse(btn.getAttribute('data-item') || '{}');
        addItemToCart(item);
      });
    });

    function filterCatalog() {
      const q = (searchInput.value || '').toLowerCase().trim();
      const tipo = catFilter.value;
      catalogNodes.forEach((node) => {
        const txt = node.getAttribute('data-search') || '';
        const t = node.getAttribute('data-tipo') || '';
        node.style.display = ((!q || txt.includes(q)) && (!tipo || t === tipo)) ? '' : 'none';
      });
    }

    searchInput.addEventListener('input', filterCatalog);
    catFilter.addEventListener('change', filterCatalog);
    inputDescuento.addEventListener('input', renderCart);

    btnLimpiar.addEventListener('click', () => {
      cart = [];
      linkedOwnerId = null;
      pendingSaleId = null;
      clienteNombreInput.value = 'Cliente Mostrador';
      clienteLabel.textContent = 'Cliente Mostrador';
      inputDescuento.value = '0';
      renderCart();
    });

    // Buscar cuenta médica por DNI o QR
    const buscarForm = document.getElementById('buscarCuentaForm');
    const cuentaQuery = document.getElementById('cuentaQueryInput');
    const cuentaBox = document.getElementById('cuentaResultadoBox');

    buscarForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const q = cuentaQuery.value.trim();
      if (!q) return;

      cuentaBox.className = 'mt-3 alert alert-light border small';
      cuentaBox.textContent = 'Buscando expediente del propietario / mascota...';
      cuentaBox.classList.remove('d-none');

      try {
        const res = await fetch(`/app/caja/buscar-cuenta?q=${encodeURIComponent(q)}`, {
          headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const json = await res.json();

        if (!res.ok || !json.success) {
          cuentaBox.className = 'mt-3 alert alert-warning small';
          cuentaBox.textContent = json.error || 'No se encontraron resultados.';
          return;
        }

        const data = json.data;
        const prop = data.propietario;
        linkedOwnerId = prop.id;
        const fullName = `${prop.nombre} ${prop.apellido}`.trim();
        clienteNombreInput.value = fullName;
        clienteLabel.textContent = `Propietario · DNI ${prop.dni}`;

        let html = `<div class="d-flex justify-content-between align-items-center mb-2">
          <strong>${fullName} (DNI: ${prop.dni})</strong>
          <span class="badge bg-dark">${(data.mascotas || []).map(m => m.nombre).join(', ') || 'Sin mascotas'}</span>
        </div>`;

        if (data.cuentas_pendientes && data.cuentas_pendientes.length > 0) {
          html += '<div class="mb-2 fw-semibold text-danger">Cuentas pendientes de liquidación:</div>';
          data.cuentas_pendientes.forEach((vp, i) => {
            html += `<button type="button" class="btn btn-sm btn-outline-danger me-2 mb-1 js-load-pending" data-idx="${i}">
              Cargar Cuenta ${vp.numero_venta} (${fmt(vp.total)})
            </button>`;
          });
        }

        if (data.items_sugeridos && data.items_sugeridos.length > 0) {
          html += '<div class="mt-2 mb-1 fw-semibold" style="color:#5A3A8A;">Recetas / Servicios recientes para cobrar:</div><div class="d-flex flex-wrap gap-1">';
          data.items_sugeridos.forEach((sg, i) => {
            html += `<button type="button" class="btn btn-sm btn-outline-dark js-add-suggested" data-idx="${i}">
              + ${sg.descripcion} (${fmt(sg.precio_unitario)})
            </button>`;
          });
          html += '</div>';
        } else {
          html += '<div class="text-muted">Propietario vinculado al ticket. Agrega servicios o productos del catálogo.</div>';
        }

        cuentaBox.className = 'mt-3 p-3 rounded-3 border bg-light small';
        cuentaBox.innerHTML = html;

        cuentaBox.querySelectorAll('.js-load-pending').forEach((btn) => {
          btn.addEventListener('click', () => {
            const vp = data.cuentas_pendientes[Number(btn.getAttribute('data-idx'))];
            pendingSaleId = vp.id;
            (vp.items || []).forEach((it) => {
              addItemToCart({
                producto_id: it.producto_id,
                descripcion: it.descripcion,
                precio_unitario: it.precio_unitario,
                cantidad: Number(it.cantidad),
                stock_actual: 9999,
                es_servicio: !it.producto_id,
              });
            });
          });
        });

        cuentaBox.querySelectorAll('.js-add-suggested').forEach((btn) => {
          btn.addEventListener('click', () => {
            const sg = data.items_sugeridos[Number(btn.getAttribute('data-idx'))];
            addItemToCart({
              producto_id: sg.producto_id,
              descripcion: sg.descripcion,
              precio_unitario: sg.precio_unitario,
              cantidad: sg.cantidad,
              stock_actual: 9999,
              es_servicio: sg.es_servicio,
            });
          });
        });
      } catch (err) {
        cuentaBox.className = 'mt-3 alert alert-danger small';
        cuentaBox.textContent = 'Error al consultar la cuenta del cliente.';
      }
    });

    // Método de pago & vuelto
    document.querySelectorAll('input[name="metodoPago"]').forEach((radio) => {
      radio.addEventListener('change', () => {
        efectivoBox.style.display = (radio.value === 'efectivo' && radio.checked) ? '' : 'none';
      });
    });

    montoRecibidoInput.addEventListener('input', updateVuelto);

    btnConfirmarPago.addEventListener('click', async () => {
      if (cart.length === 0) return;

      const selectedMp = document.querySelector('input[name="metodoPago"]:checked')?.value || 'efectivo';
      const totals = computeTotals();
      const recibido = parseFloat(montoRecibidoInput.value || '0') || 0;

      pagoErrorBox.classList.add('d-none');
      pagoSuccessBox.classList.add('d-none');

      if (selectedMp === 'efectivo' && recibido > 0 && recibido < totals.total) {
        pagoErrorBox.textContent = 'El monto recibido en efectivo es menor al total a cobrar.';
        pagoErrorBox.classList.remove('d-none');
        return;
      }

      btnConfirmarPago.disabled = true;
      btnConfirmarPago.textContent = 'Procesando...';

      try {
        const res = await fetch('/app/caja/venta', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken
          },
          body: JSON.stringify({
            cliente_tipo: linkedOwnerId ? 'propietario' : 'externo',
            propietario_id: linkedOwnerId,
            cliente_nombre: clienteNombreInput.value.trim() || 'Cliente Mostrador',
            descuento: totals.descuento,
            metodo_pago: selectedMp,
            monto_recibido: recibido || totals.total,
            notas: document.getElementById('inputNotasVenta').value.trim(),
            venta_pendiente_id: pendingSaleId,
            items: cart
          })
        });

        const json = await res.json();
        if (!res.ok || !json.success) {
          pagoErrorBox.textContent = json.error || 'No se pudo procesar la venta.';
          pagoErrorBox.classList.remove('d-none');
          btnConfirmarPago.disabled = false;
          btnConfirmarPago.textContent = 'Confirmar y Emitir Comprobante';
          return;
        }

        pagoSuccessBox.innerHTML = `
          <strong>¡Venta ${json.numero_venta} registrada!</strong> Total: ${fmt(json.total)}
          ${json.cambio > 0 ? ` · Vuelto: <strong>${fmt(json.cambio)}</strong>` : ''}
          <div class="mt-2">
            <a href="${json.receipt_url}" target="_blank" class="btn btn-sm btn-dark rounded-pill px-3">
              <i class="bi bi-printer me-1"></i>Imprimir Comprobante
            </a>
          </div>
        `;
        pagoSuccessBox.classList.remove('d-none');

        cart = [];
        pendingSaleId = null;
        montoRecibidoInput.value = '';
        renderCart();
        btnConfirmarPago.disabled = false;
        btnConfirmarPago.textContent = 'Confirmar y Emitir Comprobante';
      } catch (err) {
        pagoErrorBox.textContent = 'Error de comunicación con el servidor de Caja.';
        pagoErrorBox.classList.remove('d-none');
        btnConfirmarPago.disabled = false;
        btnConfirmarPago.textContent = 'Confirmar y Emitir Comprobante';
      }
    });

    renderCart();
  });
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';


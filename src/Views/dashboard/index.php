<?php
/**
 * PetKardex v2 - Vista Principal del Dashboard
 *
 * Muestra widgets dinámicos con métricas reales desde MySQL y accesos directos
 * según el rol autenticado mediante la clase Auth. Moneda en Soles Peruanos (S/.).
 *
 * Variables disponibles desde DashboardController:
 * - Auth   $auth
 * - array  $user
 * - array  $stats
 * - string $moneda
 * - array  $alertasMedicas
 * - string $csrfToken
 */

use PetKardex\Core\Auth;
use PetKardex\Helpers\Security;

$authInstance   = $auth ?? new Auth();
$currentUser    = $user ?? $authInstance->getCurrentUser() ?? ($_SESSION['user'] ?? []);
$firstName      = Security::e((string) ($currentUser['nombre'] ?? 'Usuario'));
$rolNombre      = Security::e((string) ($currentUser['rol_nombre'] ?? 'Usuario'));
$s              = $stats ?? [];
$monedaSimbolo  = Security::e((string) ($moneda ?? 'S/.'));
$alertasMedicas = $alertasMedicas ?? [];

ob_start();
?>

<?php if (!empty($alertasMedicas)): ?>
  <!-- Notificaciones Destacadas de Salud (emitidas por Estética / Emergencias) -->
  <div class="pk-card p-3 p-md-4 mb-4 border-danger border-2" style="background: linear-gradient(135deg, #FEF2F2 0%, #FFFFFF 100%);">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <h3 class="h6 fw-bold text-danger mb-0">
        <i class="bi bi-bell-fill me-2"></i>Alertas Médicas Internas Pendientes (<?= count($alertasMedicas) ?>)
      </h3>
      <span class="badge bg-danger rounded-pill">Atención Clínica</span>
    </div>
    <div class="d-grid gap-2">
      <?php foreach ($alertasMedicas as $al): ?>
        <div class="p-2 px-3 rounded-3 bg-white border d-flex justify-content-between align-items-center flex-wrap gap-2 small">
          <div>
            <span class="badge bg-danger-subtle text-danger me-1"><?= Security::e(strtoupper((string) $al['prioridad'])) ?></span>
            <strong><?= Security::e((string) $al['titulo']) ?></strong>
            <?php if (!empty($al['paciente_nombre'])): ?>
              · Paciente: <strong><?= Security::e((string) $al['paciente_nombre']) ?></strong>
            <?php endif; ?>
            <div class="text-muted mt-1"><?= Security::e((string) $al['mensaje']) ?></div>
            <div class="text-muted" style="font-size: 0.72rem;">
              Emitida por: <?= Security::e(trim(($al['origen_nombre'] ?? '') . ' ' . ($al['origen_apellido'] ?? ''))) ?> (<?= Security::e((string) ($al['origen_rol'] ?? '')) ?>)
            </div>
          </div>
          <div class="d-flex gap-2">
            <?php if (!empty($al['paciente_id'])): ?>
              <a href="/app/kardex/<?= (int) $al['paciente_id'] ?>" class="btn btn-sm btn-dark rounded-3">Ver Kardex</a>
            <?php endif; ?>
            <form action="/app/alertas/<?= (int) $al['id'] ?>/leer" method="POST">
              <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />
              <button type="submit" class="btn btn-sm btn-outline-success rounded-3">Marcar Revisada</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<!-- Banner de bienvenida adaptable -->
<div class="pk-card p-4 mb-4 text-white position-relative overflow-hidden" style="background: linear-gradient(135deg, #0D0D0D 0%, #1A1A2E 60%, #371F66 100%);">
  <div class="row align-items-center g-3 position-relative" style="z-index: 1;">
    <div class="col-md-8">
      <span class="badge rounded-pill mb-2 px-3 py-2" style="background: rgba(196, 140, 255, 0.2); color: #C48CFF;">
        <i class="bi bi-stars me-1"></i><?= $rolNombre ?>
      </span>
      <h2 class="h4 fw-bold mb-1">¡Hola, <?= $firstName ?>!</h2>
      <p class="mb-0 text-white-50 small">
        <?php if ($authInstance->hasRole('superadmin')): ?>
          Resumen ejecutivo en tiempo real de la clínica, ingresos y pacientes registrados.
        <?php elseif ($authInstance->hasRole('veterinario')): ?>
          Tu agenda médica del día, pacientes hospitalizados y accesos rápidos de consulta.
        <?php elseif ($authInstance->hasRole(['guardia', 'emergencia'])): ?>
          Panel de atención prioritaria, triage de emergencias y control de signos vitales.
        <?php elseif ($authInstance->hasRole('recepcionista')): ?>
          Accesos rápidos a caja, recepción de citas e inventario básico del turno.
        <?php elseif ($authInstance->hasRole('groomer')): ?>
          Agenda estética del día y canal directo de alertas médicas veterinarias.
        <?php elseif ($authInstance->hasRole('propietario')): ?>
          Bienvenido al portal de tus mascotas. Revisa su historial médico y carnet QR.
        <?php else: ?>
          Bienvenido al sistema integral PetKardex.
        <?php endif; ?>
      </p>
    </div>
    <div class="col-md-4 text-md-end">
      <span class="small text-white-50 d-block">Fecha del sistema</span>
      <span class="fw-semibold" style="color: #C48CFF;"><?= date('d/m/Y · H:i') ?></span>
    </div>
  </div>
</div>

<?php switch (true): ?>
<?php case $authInstance->hasRole('superadmin'): ?>
  <!-- ═══════════════════════════════════════════════════════════════════
       WIDGETS DINÁMICOS ADMINISTRADOR DEL SISTEMA (SUPERADMIN)
       ═══════════════════════════════════════════════════════════════════ -->
  <?php
    $ingHoy  = (float) ($s['ingresos_hoy'] ?? 0);
    $ingAyer = (float) ($s['ingresos_ayer'] ?? 0);
    $diffIng = $ingAyer > 0 ? round((($ingHoy - $ingAyer) / $ingAyer) * 100, 1) : null;
  ?>
  <div class="row g-3 g-md-4 mb-4">
    <div class="col-12 col-sm-6 col-xl-3">
      <div class="pk-card pk-card-hover p-4 h-100">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <span class="text-muted small fw-semibold">Ingresos del Día</span>
            <h3 class="h3 fw-bold mt-2 mb-1 text-dark"><?= $monedaSimbolo ?> <?= number_format($ingHoy, 2) ?></h3>
            <?php if ($diffIng !== null): ?>
              <span class="badge <?= $diffIng >= 0 ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' ?> rounded-pill">
                <?= $diffIng >= 0 ? '+' : '' ?><?= $diffIng ?>% vs ayer
              </span>
            <?php else: ?>
              <span class="badge bg-light text-muted border rounded-pill"><?= (int) ($s['ventas_hoy_count'] ?? 0) ?> ventas hoy</span>
            <?php endif; ?>
          </div>
          <div class="pk-icon-box"><i class="bi bi-cash-coin"></i></div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="pk-card pk-card-hover p-4 h-100">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <span class="text-muted small fw-semibold">Total de Mascotas</span>
            <h3 class="h3 fw-bold mt-2 mb-1 text-dark"><?= (int) ($s['total_mascotas'] ?? 0) ?></h3>
            <span class="badge rounded-pill" style="background: rgba(196,140,255,0.18); color: #5A3A8A;">
              <?= (int) ($s['mascotas_mes'] ?? 0) ?> nuevas este mes
            </span>
          </div>
          <div class="pk-icon-box"><i class="bi bi-heart-pulse-fill"></i></div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="pk-card pk-card-hover p-4 h-100">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <span class="text-muted small fw-semibold">Citas Pendientes</span>
            <h3 class="h3 fw-bold mt-2 mb-1 text-dark"><?= (int) ($s['citas_pendientes'] ?? 0) ?></h3>
            <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill">
              <?= (int) ($s['citas_confirmadas_hoy'] ?? 0) ?> confirmadas hoy
            </span>
          </div>
          <div class="pk-icon-box"><i class="bi bi-calendar2-check-fill"></i></div>
        </div>
      </div>
    </div>

    <div class="col-12 col-sm-6 col-xl-3">
      <div class="pk-card pk-card-hover p-4 h-100">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <span class="text-muted small fw-semibold">Hospitalizados Activos</span>
            <h3 class="h3 fw-bold mt-2 mb-1 text-dark"><?= (int) ($s['hospitalizados'] ?? 0) ?></h3>
            <span class="badge <?= ((int) ($s['stock_critico'] ?? 0) > 0) ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' ?> rounded-pill">
              <?= (int) ($s['stock_critico'] ?? 0) ?> prod. stock crítico
            </span>
          </div>
          <div class="pk-icon-box"><i class="bi bi-hospital-fill"></i></div>
        </div>
      </div>
    </div>
  </div>

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="pk-card p-4 h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h4 class="h6 fw-bold mb-0">Resumen Operativo del Día (Tiempo Real)</h4>
          <a href="/app/caja" class="btn btn-sm btn-outline-dark rounded-pill">Ir a Caja POS</a>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0 small">
            <thead class="table-light">
              <tr>
                <th>Módulo</th>
                <th>Actividad Registrada Hoy</th>
                <th>Estado</th>
                <th class="text-end">Enlace</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td><i class="bi bi-clipboard2-pulse me-2 text-primary"></i>Consultas Clínicas</td>
                <td><?= (int) ($s['consultas_hoy'] ?? 0) ?> atenciones registradas hoy</td>
                <td><span class="badge bg-success-subtle text-success">Operativo</span></td>
                <td class="text-end"><a href="/app/pacientes" class="btn btn-sm btn-outline-dark rounded-2">Ver</a></td>
              </tr>
              <tr>
                <td><i class="bi bi-bag-check me-2" style="color:#7B52AB;"></i>Pet Shop &amp; Farmacia (POS)</td>
                <td><?= (int) ($s['ventas_hoy_count'] ?? 0) ?> comprobantes (<?= $monedaSimbolo ?> <?= number_format($ingHoy, 2) ?>)</td>
                <td>
                  <span class="badge <?= ((int) ($s['stock_critico'] ?? 0) > 0) ? 'bg-danger-subtle text-danger' : 'bg-success-subtle text-success' ?>">
                    <?= ((int) ($s['stock_critico'] ?? 0) > 0) ? (int) $s['stock_critico'] . ' alertas stock' : 'Stock OK' ?>
                  </span>
                </td>
                <td class="text-end"><a href="/app/inventario" class="btn btn-sm btn-outline-dark rounded-2">Ver</a></td>
              </tr>
              <tr>
                <td><i class="bi bi-scissors me-2 text-secondary"></i>Estética Canina</td>
                <td><?= (int) ($s['estetica_hoy'] ?? 0) ?> servicios programados hoy</td>
                <td><span class="badge bg-warning-subtle text-warning-emphasis"><?= (int) ($s['estetica_en_curso'] ?? 0) ?> en curso</span></td>
                <td class="text-end"><a href="/app/estetica" class="btn btn-sm btn-outline-dark rounded-2">Ver</a></td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="pk-card p-4 h-100">
        <h4 class="h6 fw-bold mb-3">Accesos Directos de Gestión</h4>
        <div class="d-grid gap-2">
          <a href="/app/pacientes" class="btn btn-light border text-start d-flex align-items-center justify-content-between p-3 rounded-3">
            <span><i class="bi bi-heart-pulse-fill me-2" style="color:#7B52AB;"></i>Directorio de Pacientes / Mascotas</span>
            <i class="bi bi-chevron-right text-muted"></i>
          </a>
          <a href="/app/inventario" class="btn btn-light border text-start d-flex align-items-center justify-content-between p-3 rounded-3">
            <span><i class="bi bi-box-seam-fill me-2" style="color:#7B52AB;"></i>Inventario y Alertas de Stock</span>
            <i class="bi bi-chevron-right text-muted"></i>
          </a>
          <a href="/app/hospitalizacion" class="btn btn-light border text-start d-flex align-items-center justify-content-between p-3 rounded-3">
            <span><i class="bi bi-hospital-fill me-2" style="color:#7B52AB;"></i>Mapa de Hospitalización</span>
            <i class="bi bi-chevron-right text-muted"></i>
          </a>
        </div>
      </div>
    </div>
  </div>
<?php break; ?>

<?php case $authInstance->hasRole('recepcionista'): ?>
  <!-- ═══════════════════════════════════════════════════════════════════
       WIDGETS DINÁMICOS RECEPCIONISTA / CAJERO
       ═══════════════════════════════════════════════════════════════════ -->
  <div class="row g-3 g-md-4 mb-4">
    <div class="col-md-6">
      <a href="/app/caja" class="text-decoration-none">
        <div class="pk-card pk-card-hover p-4 h-100 border-0 text-white" style="background: linear-gradient(135deg, #7B52AB 0%, #0D0D0D 100%);">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <span class="badge rounded-pill mb-2" style="background: rgba(196,140,255,0.25); color: #C48CFF;">Acceso Directo</span>
              <h3 class="h4 fw-bold mb-1">Nueva Venta</h3>
              <p class="mb-0 small text-white-50">Abrir punto de venta (Pet Shop, farmacia y liquidación de hospitalización).</p>
            </div>
            <div class="rounded-4 d-flex align-items-center justify-content-center" style="width:60px;height:60px;background:rgba(196,140,255,0.22);color:#C48CFF;">
              <i class="bi bi-cart-plus-fill fs-2"></i>
            </div>
          </div>
        </div>
      </a>
    </div>

    <div class="col-md-6">
      <a href="/app/agenda" class="text-decoration-none">
        <div class="pk-card pk-card-hover p-4 h-100" style="border-left: 5px solid #C48CFF;">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <span class="badge rounded-pill mb-2" style="background: rgba(196,140,255,0.18); color: #5A3A8A;">Recepción</span>
              <h3 class="h4 fw-bold mb-1 text-dark">Citas de Hoy</h3>
              <p class="mb-0 small text-muted">
                <?= (int) ($s['citas_hoy'] ?? 0) ?> citas programadas · <?= (int) ($s['citas_espera'] ?? 0) ?> confirmadas / en proceso.
              </p>
            </div>
            <div class="pk-icon-box" style="width:60px;height:60px;"><i class="bi bi-calendar-day-fill fs-2"></i></div>
          </div>
        </div>
      </a>
    </div>
  </div>

  <div class="row g-3 g-md-4">
    <div class="col-sm-6 col-lg-4">
      <div class="pk-card p-4">
        <span class="text-muted small fw-semibold">Cobros del Turno (Hoy)</span>
        <h4 class="h3 fw-bold mt-2 mb-1"><?= $monedaSimbolo ?> <?= number_format((float) ($s['cobros_hoy'] ?? 0), 2) ?></h4>
        <small class="text-success"><i class="bi bi-check-circle me-1"></i><?= (int) ($s['ventas_hoy'] ?? 0) ?> ventas procesadas</small>
      </div>
    </div>
    <div class="col-sm-6 col-lg-4">
      <div class="pk-card p-4">
        <span class="text-muted small fw-semibold">Cuentas por Liquidar</span>
        <h4 class="h3 fw-bold mt-2 mb-1"><?= (int) ($s['cuentas_pendientes'] ?? 0) ?></h4>
        <small class="text-muted">Cuentas médicas / hospitalización pendientes</small>
      </div>
    </div>
    <div class="col-sm-12 col-lg-4">
      <div class="pk-card p-4">
        <span class="text-muted small fw-semibold">Inventario Básico</span>
        <h4 class="h3 fw-bold mt-2 mb-1"><?= (int) ($s['stock_critico'] ?? 0) ?> alertas</h4>
        <small class="<?= ((int) ($s['stock_critico'] ?? 0) > 0) ? 'text-danger' : 'text-success' ?>">
          <i class="bi bi-exclamation-circle me-1"></i>Productos en stock mínimo o menor
        </small>
      </div>
    </div>
  </div>
<?php break; ?>

<?php case $authInstance->hasRole('propietario'): ?>
  <!-- ═══════════════════════════════════════════════════════════════════
       WIDGETS DINÁMICOS PROPIETARIO (DUEÑO) — SOLO LECTURA
       ═══════════════════════════════════════════════════════════════════ -->
  <?php
    $numMascotas = (int) ($s['mascotas_count'] ?? 0);
    $proxVac     = $s['proxima_vacuna'] ?? null;
    $primeraId   = $s['primera_mascota_id'] ?? null;
  ?>
  <div class="row g-3 g-md-4 mb-4">
    <div class="col-md-4">
      <div class="pk-card pk-card-hover p-4 h-100">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <span class="text-muted small fw-semibold">Mis Mascotas</span>
            <h3 class="h5 fw-bold mt-2 mb-1 text-dark">
              Tienes <?= $numMascotas ?> <?= $numMascotas === 1 ? 'mascota registrada' : 'mascotas registradas' ?>
            </h3>
            <p class="small text-muted mb-0">Perfiles médicos verificados y activos.</p>
          </div>
          <div class="pk-icon-box"><i class="bi bi-heart-fill"></i></div>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="pk-card pk-card-hover p-4 h-100">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <span class="text-muted small fw-semibold">Recordatorio Preventivo</span>
            <?php if ($proxVac): ?>
              <?php $diasVac = max(0, (int) floor((strtotime((string) $proxVac['proxima_dosis']) - strtotime(date('Y-m-d'))) / 86400)); ?>
              <h3 class="h5 fw-bold mt-2 mb-1 text-dark">
                <?= Security::e((string) $proxVac['vacuna_nombre']) ?> en <?= $diasVac ?> días
              </h3>
              <p class="small text-muted mb-0">
                Paciente: <?= Security::e((string) $proxVac['mascota_nombre']) ?> (<?= Security::e(date('d/m/Y', strtotime((string) $proxVac['proxima_dosis']))) ?>)
              </p>
            <?php else: ?>
              <h3 class="h5 fw-bold mt-2 mb-1 text-dark">Sin refuerzos próximos</h3>
              <p class="small text-muted mb-0">Tienes <?= (int) ($s['citas_proximas'] ?? 0) ?> citas futuras programadas.</p>
            <?php endif; ?>
          </div>
          <div class="pk-icon-box"><i class="bi bi-shield-plus"></i></div>
        </div>
      </div>
    </div>

    <div class="col-md-4">
      <div class="pk-card pk-card-hover p-4 h-100" style="border-left: 4px solid #C48CFF;">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <span class="text-muted small fw-semibold">Identificación Digital</span>
            <h3 class="h5 fw-bold mt-2 mb-1 text-dark"><?= (int) ($s['qr_activos'] ?? 0) ?> Carnet(s) QR Activos</h3>
            <a href="/app/qr" class="btn btn-sm btn-pk-primary mt-2">
              <i class="bi bi-download me-1"></i>Descargar QR
            </a>
          </div>
          <div class="pk-icon-box"><i class="bi bi-qr-code"></i></div>
        </div>
      </div>
    </div>
  </div>

  <div class="pk-card p-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
      <h4 class="h6 fw-bold mb-0">Accesos Rápidos de Propietario</h4>
      <span class="badge rounded-pill text-dark" style="background: rgba(196,140,255,0.25);">Modo Solo Lectura</span>
    </div>
    <div class="row g-3">
      <div class="col-sm-6 col-lg-4">
        <a href="/app/qr" class="btn btn-light border w-100 p-3 text-start rounded-3">
          <i class="bi bi-qr-code fs-4 d-block mb-2" style="color:#7B52AB;"></i>
          <strong class="d-block small">Descargar QR</strong>
          <small class="text-muted">Placa digital de emergencia</small>
        </a>
      </div>
      <div class="col-sm-6 col-lg-4">
        <a href="<?= $primeraId ? '/app/kardex/' . $primeraId : '/app/qr' ?>" class="btn btn-light border w-100 p-3 text-start rounded-3">
          <i class="bi bi-clipboard2-pulse fs-4 d-block mb-2" style="color:#7B52AB;"></i>
          <strong class="d-block small">Ver Historial Médico</strong>
          <small class="text-muted">Consultas y vacunas inmutables</small>
        </a>
      </div>
      <div class="col-sm-12 col-lg-4">
        <a href="/app/portal/citas" class="btn btn-light border w-100 p-3 text-start rounded-3">
          <i class="bi bi-calendar-event fs-4 d-block mb-2" style="color:#7B52AB;"></i>
          <strong class="d-block small">Próximas Citas</strong>
          <small class="text-muted"><?= (int) ($s['citas_proximas'] ?? 0) ?> citas confirmadas</small>
        </a>
      </div>
    </div>
  </div>
<?php break; ?>

<?php case $authInstance->hasRole('veterinario'): ?>
  <!-- ═══════════════════════════════════════════════════════════════════
       WIDGETS DINÁMICOS MÉDICO VETERINARIO (TRATANTE)
       ═══════════════════════════════════════════════════════════════════ -->
  <div class="row g-3 g-md-4 mb-4">
    <div class="col-sm-6 col-xl-3">
      <div class="pk-card pk-card-hover p-4">
        <span class="text-muted small fw-semibold">Agenda Médica Hoy</span>
        <h3 class="h3 fw-bold mt-2 mb-1"><?= (int) ($s['citas_hoy'] ?? 0) ?> citas</h3>
        <a href="/app/agenda" class="small text-decoration-none fw-semibold" style="color:#7B52AB;">Ir a agenda <i class="bi bi-arrow-right"></i></a>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="pk-card pk-card-hover p-4">
        <span class="text-muted small fw-semibold">Hospitalizados</span>
        <h3 class="h3 fw-bold mt-2 mb-1"><?= (int) ($s['hospitalizados'] ?? 0) ?> pacientes</h3>
        <a href="/app/hospitalizacion" class="small text-decoration-none fw-semibold" style="color:#7B52AB;">Ver internados <i class="bi bi-arrow-right"></i></a>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="pk-card pk-card-hover p-4">
        <span class="text-muted small fw-semibold">Recetas Emitidas</span>
        <h3 class="h3 fw-bold mt-2 mb-1"><?= (int) ($s['recetas_hoy'] ?? 0) ?> hoy</h3>
        <small class="text-muted">Descuento automático de stock</small>
      </div>
    </div>
    <div class="col-sm-6 col-xl-3">
      <div class="pk-card pk-card-hover p-4">
        <span class="text-muted small fw-semibold">Certificados PDF</span>
        <h3 class="h3 fw-bold mt-2 mb-1"><?= (int) ($s['certificados_hoy'] ?? 0) ?> emitidos hoy</h3>
        <a href="/app/pacientes" class="small text-decoration-none fw-semibold" style="color:#7B52AB;">Ver pacientes <i class="bi bi-arrow-right"></i></a>
      </div>
    </div>
  </div>

  <div class="pk-card p-4">
    <h4 class="h6 fw-bold mb-3"><i class="bi bi-search-heart me-2" style="color:#7B52AB;"></i>Búsqueda Rápida de Paciente (Nombre / QR / DNI Tutor)</h4>
    <form action="/app/pacientes" method="GET" class="row g-2">
      <div class="col-md-9">
        <input type="text" name="q" class="form-control" placeholder="Ingresa nombre, código QR del paciente o DNI del propietario..." />
      </div>
      <div class="col-md-3 d-grid">
        <button type="submit" class="btn btn-pk-primary">Buscar Expediente</button>
      </div>
    </form>
  </div>
<?php break; ?>

<?php case $authInstance->hasRole(['guardia', 'emergencia']): ?>
  <!-- ═══════════════════════════════════════════════════════════════════
       WIDGETS DINÁMICOS VETERINARIO DE TURNO (GUARDIA) / EMERGENCIAS
       ═══════════════════════════════════════════════════════════════════ -->
  <div class="row g-3 g-md-4">
    <div class="col-md-6">
      <div class="pk-card pk-card-hover p-4" style="border-left: 5px solid #EF4444;">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <span class="badge bg-danger-subtle text-danger mb-2">Prioridad Alta</span>
            <h3 class="h5 fw-bold">Triage y Emergencias</h3>
            <p class="small text-muted mb-3"><?= (int) ($s['urgencias_hoy'] ?? 0) ?> ingresos de emergencia registrados hoy.</p>
            <a href="/app/hospitalizacion" class="btn btn-sm btn-dark rounded-pill px-3">Abrir Mapa y Triage</a>
          </div>
          <div class="pk-icon-box bg-danger-subtle text-danger"><i class="bi bi-activity"></i></div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="pk-card pk-card-hover p-4" style="border-left: 5px solid #C48CFF;">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <span class="badge rounded-pill mb-2" style="background: rgba(196,140,255,0.2); color: #5A3A8A;">Monitoreo</span>
            <h3 class="h5 fw-bold">Bitácora Nocturna de Signos Vitales</h3>
            <p class="small text-muted mb-3">
              <?= (int) ($s['hospitalizados'] ?? 0) ?> internados activos · <?= (int) ($s['bitacoras_hoy'] ?? 0) ?> controles registrados hoy.
            </p>
            <a href="/app/hospitalizacion" class="btn btn-sm btn-pk-primary rounded-pill px-3">Ver Hospitalizados</a>
          </div>
          <div class="pk-icon-box"><i class="bi bi-journal-medical"></i></div>
        </div>
      </div>
    </div>
  </div>
<?php break; ?>

<?php case $authInstance->hasRole('groomer'): ?>
  <!-- ═══════════════════════════════════════════════════════════════════
       WIDGETS DINÁMICOS ESTILISTA / GROOMER
       ═══════════════════════════════════════════════════════════════════ -->
  <div class="row g-3 g-md-4">
    <div class="col-md-6">
      <div class="pk-card pk-card-hover p-4">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <span class="text-muted small fw-semibold">Agenda Estética del Día</span>
            <h3 class="h3 fw-bold mt-2 mb-1"><?= (int) ($s['estetica_hoy'] ?? 0) ?> turnos hoy</h3>
            <p class="small text-muted mb-3"><?= (int) ($s['completadas_hoy'] ?? 0) ?> servicios completados hasta ahora.</p>
            <a href="/app/estetica" class="btn btn-sm btn-pk-primary rounded-pill px-3">Ver Agenda Estética</a>
          </div>
          <div class="pk-icon-box"><i class="bi bi-scissors"></i></div>
        </div>
      </div>
    </div>
    <div class="col-md-6">
      <div class="pk-card pk-card-hover p-4" style="border-left: 5px solid #F59E0B;">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <span class="text-muted small fw-semibold">Comunicación Clínica</span>
            <h3 class="h5 fw-bold mt-2 mb-1">Alertas de Salud al Veterinario</h3>
            <p class="small text-muted mb-3">
              Has emitido <?= (int) ($s['alertas_hoy'] ?? 0) ?> alerta(s) médica(s) hoy desde estética.
            </p>
            <a href="/app/estetica" class="btn btn-sm btn-outline-dark rounded-pill px-3">Gestionar Turnos / Alertas</a>
          </div>
          <div class="pk-icon-box bg-warning-subtle text-warning-emphasis"><i class="bi bi-exclamation-triangle-fill"></i></div>
        </div>
      </div>
    </div>
  </div>
<?php break; ?>

<?php endswitch; ?>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';

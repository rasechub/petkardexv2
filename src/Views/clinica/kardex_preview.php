<?php
/**
 * PetKardex v2 - Vista Interna de Kardex Médico (Aterrizaje tras Escaneo QR)
 *
 * Variables:
 * - array       $paciente
 * - string|null $qrImageSrc
 */

use PetKardex\Helpers\Security;

$pet       = $paciente ?? [];
$ownerName = trim(($pet['propietario_nombre'] ?? '') . ' ' . ($pet['propietario_apellido'] ?? ''));

ob_start();
?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
  <div>
    <a href="/app/qr/scanner" class="btn btn-sm btn-outline-dark rounded-pill px-3 mb-2">
      <i class="bi bi-arrow-left me-1"></i>Volver al Escáner QR
    </a>
    <h2 class="h4 fw-bold mb-0">
      Kardex Médico: <?= Security::e((string) ($pet['nombre'] ?? '')) ?>
    </h2>
    <small class="text-muted">
      <?= Security::e((string) ($pet['especie_nombre'] ?? '')) ?>
      <?php if (!empty($pet['raza_nombre'])): ?>
        · <?= Security::e((string) ($pet['raza_nombre'] ?? '')) ?>
      <?php endif; ?>
      · ID #<?= (int) ($pet['id'] ?? 0) ?>
    </small>
  </div>
  <div class="d-flex gap-2">
    <a href="/app/clinica/consulta/nueva?paciente_id=<?= (int) ($pet['id'] ?? 0) ?>" class="btn btn-pk-primary px-3">
      <i class="bi bi-file-earmark-medical me-1"></i>Nueva Consulta
    </a>
    <a href="/app/clinica/vacuna/nueva?paciente_id=<?= (int) ($pet['id'] ?? 0) ?>" class="btn btn-dark rounded-3 px-3">
      <i class="bi bi-shield-plus me-1"></i>Registrar Vacuna
    </a>
  </div>
</div>

<div class="row g-4">
  <!-- Ficha del Paciente -->
  <div class="col-lg-4">
    <div class="pk-card p-4 h-100">
      <div class="text-center mb-3">
        <div class="mx-auto mb-3 rounded-circle overflow-hidden d-flex align-items-center justify-content-center"
             style="width: 110px; height: 110px; border: 3px solid #C48CFF; background: #1A1A2E; color: #C48CFF;">
          <?php if (!empty($pet['foto'])): ?>
            <img src="/<?= Security::e(ltrim((string) $pet['foto'], '/')) ?>" alt="<?= Security::e((string) $pet['nombre']) ?>" class="w-100 h-100 object-fit-cover" />
          <?php else: ?>
            <i class="bi bi-heart-pulse-fill fs-1"></i>
          <?php endif; ?>
        </div>
        <h3 class="h5 fw-bold mb-0"><?= Security::e((string) ($pet['nombre'] ?? '')) ?></h3>
        <span class="badge rounded-pill mt-2" style="background: rgba(196,140,255,0.2); color: #5A3A8A;">
          <?= Security::e(ucfirst((string) ($pet['sexo'] ?? 'desconocido'))) ?> · <?= Security::e((string) ($pet['peso_kg'] ?? 'N/D')) ?> kg
        </span>
      </div>

      <hr />

      <dl class="row small mb-0">
        <dt class="col-5 text-muted">Propietario</dt>
        <dd class="col-7 fw-semibold"><?= Security::e($ownerName) ?></dd>

        <dt class="col-5 text-muted">DNI</dt>
        <dd class="col-7"><?= Security::e((string) ($pet['propietario_dni'] ?? 'N/D')) ?></dd>

        <dt class="col-5 text-muted">Teléfono</dt>
        <dd class="col-7"><?= Security::e((string) ($pet['propietario_celular'] ?: ($pet['propietario_telefono'] ?: 'N/D'))) ?></dd>

        <dt class="col-5 text-muted">Microchip</dt>
        <dd class="col-7"><?= Security::e((string) ($pet['microchip'] ?: 'Sin registrar')) ?></dd>

        <dt class="col-5 text-muted">Tipo Sangre</dt>
        <dd class="col-7"><?= Security::e((string) ($pet['sangre_tipo'] ?: 'N/D')) ?></dd>
      </dl>

      <?php if ($qrImageSrc): ?>
        <div class="mt-4 pt-3 border-top text-center">
          <img src="<?= Security::e($qrImageSrc) ?>" alt="QR Paciente" width="120" height="120" class="rounded-3 border p-1 bg-white" />
          <div class="mt-2">
            <a href="/app/qr/descargar/<?= Security::e(rawurlencode((string) $pet['qr_code'])) ?>" class="small fw-semibold text-decoration-none" style="color: #7B52AB;">
              <i class="bi bi-download me-1"></i>Descargar QR
            </a>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Alertas y Módulos Clínicos -->
  <div class="col-lg-8">
    <?php if (!empty($pet['alertas_medicas'])): ?>
      <div class="alert alert-danger border-2 rounded-4 p-3 mb-4">
        <h4 class="h6 fw-bold mb-1"><i class="bi bi-exclamation-octagon-fill me-2"></i>Alertas Médicas Críticas</h4>
        <p class="mb-0 small"><?= nl2br(Security::e((string) $pet['alertas_medicas'])) ?></p>
      </div>
    <?php endif; ?>

    <div class="pk-card p-4">
      <h4 class="h6 fw-bold mb-3">Módulos del Expediente Clínico</h4>
      <p class="small text-muted mb-4">
        Paciente identificado correctamente vía escáner QR interno. Desde aquí el personal médico autorizado accede a consultas, vacunaciones, recetas y hospitalización.
      </p>
      <div class="row g-3">
        <div class="col-sm-6">
          <div class="p-3 rounded-3 border bg-light h-100">
            <i class="bi bi-clipboard2-pulse fs-4 d-block mb-2" style="color: #7B52AB;"></i>
            <strong class="d-block small">Historial de Consultas</strong>
            <span class="text-muted small">Diagnósticos CIE, anamnesis y examen físico.</span>
          </div>
        </div>
        <div class="col-sm-6">
          <div class="p-3 rounded-3 border bg-light h-100">
            <i class="bi bi-shield-plus fs-4 d-block mb-2" style="color: #7B52AB;"></i>
            <strong class="d-block small">Esquema de Vacunación</strong>
            <span class="text-muted small">Lotes aplicados y próximas revacunaciones.</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';


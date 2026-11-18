<?php
/**
 * PetKardex v2 - Credencial QR del Propietario
 *
 * Permite visualizar la credencial QR de cada mascota y descargar
 * la imagen en alta resolución para imprimir o grabar en su placa.
 *
 * Variables:
 * - array  $mascotas
 * - string $csrfToken
 */

use PetKardex\Helpers\Security;

$petsList = $mascotas ?? [];

ob_start();
?>

<div class="pk-card p-4 mb-4 text-white" style="background: linear-gradient(135deg, #0D0D0D 0%, #1A1A2E 65%, #371F66 100%);">
  <div class="row align-items-center g-3">
    <div class="col-lg-8">
      <span class="badge rounded-pill px-3 py-2 mb-2" style="background: rgba(196, 140, 255, 0.2); color: #C48CFF;">
        <i class="bi bi-qr-code me-1"></i>Identificación Inteligente
      </span>
      <h2 class="h4 fw-bold mb-1">Credencial QR de tus Mascotas</h2>
      <p class="mb-0 small text-white-50">
        Descarga el código QR único de tu mascota para imprimirlo en su carnet o grabarlo en la chapa de su collar.
        Cualquier persona que lo escanee verá sus alertas médicas críticas y tus botones de contacto.
      </p>
    </div>
    <div class="col-lg-4 text-lg-end">
      <div class="small text-white-50">Mascotas con QR activo</div>
      <div class="h3 fw-bold mb-0" style="color: #C48CFF;"><?= count($petsList) ?></div>
    </div>
  </div>
</div>

<?php if (empty($petsList)): ?>
  <div class="pk-card p-5 text-center">
    <div class="pk-icon-box mx-auto mb-3" style="width: 68px; height: 68px; font-size: 2rem;">
      <i class="bi bi-qr-code"></i>
    </div>
    <h3 class="h5 fw-bold mb-2">Aún no tienes mascotas vinculadas</h3>
    <p class="text-muted small mb-4">
      Cuando la clínica registre a tu mascota bajo tu cuenta, aquí aparecerá automáticamente su credencial QR descargable.
    </p>
    <a href="/app/dashboard" class="btn btn-dark rounded-pill px-4">Volver al Dashboard</a>
  </div>
<?php else: ?>
  <div class="row g-4">
    <?php foreach ($petsList as $pet): ?>
      <?php
        $uuid     = (string) $pet['qr_code'];
        $qrUrl    = (string) $pet['qr_url'];
        $qrImgSrc = (string) $pet['qr_image_src'];
        $petName  = (string) $pet['nombre'];
      ?>
      <div class="col-12 col-md-6 col-xl-4">
        <div class="pk-card h-100 overflow-hidden d-flex flex-column">
          <!-- Cabecera oscura de credencial -->
          <div class="p-3 px-4 text-white d-flex align-items-center justify-content-between"
               style="background: linear-gradient(135deg, #0D0D0D 0%, #2D1B69 100%);">
            <div>
              <h3 class="h5 fw-bold mb-0"><?= Security::e($petName) ?></h3>
              <small style="color: #C48CFF;">
                <?= Security::e((string) $pet['especie_nombre']) ?>
                <?php if (!empty($pet['raza_nombre'])): ?>
                  · <?= Security::e((string) $pet['raza_nombre']) ?>
                <?php endif; ?>
              </small>
            </div>
            <span class="badge rounded-pill" style="background: rgba(196, 140, 255, 0.22); color: #C48CFF;">
              QR Activo
            </span>
          </div>

          <!-- Cuerpo con imagen QR -->
          <div class="p-4 text-center flex-grow-1 d-flex flex-column justify-content-between">
            <div>
              <div class="d-inline-block p-3 rounded-4 border bg-white shadow-sm mb-3" style="border-color: rgba(196,140,255,0.45) !important;">
                <img
                  src="<?= Security::e($qrImgSrc) ?>"
                  alt="Código QR de <?= Security::e($petName) ?>"
                  width="210"
                  height="210"
                  class="img-fluid rounded-2"
                />
              </div>

              <div class="mb-3">
                <span class="d-block text-muted" style="font-size: 0.72rem;">UUID Único del Paciente</span>
                <code class="small px-2 py-1 rounded bg-light text-dark d-inline-block mt-1">
                  <?= Security::e($uuid) ?>
                </code>
              </div>

              <?php if (!empty($pet['alertas_medicas'])): ?>
                <div class="alert alert-danger py-2 px-3 small text-start rounded-3 mb-3">
                  <strong><i class="bi bi-exclamation-triangle-fill me-1"></i>Alerta médica:</strong>
                  <?= Security::e(mb_strimwidth((string) $pet['alertas_medicas'], 0, 90, '...')) ?>
                </div>
              <?php endif; ?>
            </div>

            <!-- Botones de acción -->
            <div class="d-grid gap-2 mt-2">
              <a
                href="/app/qr/descargar/<?= Security::e(rawurlencode($uuid)) ?>"
                class="btn btn-pk-primary py-2"
              >
                <i class="bi bi-download me-2"></i>Descargar QR (PNG)
              </a>

              <div class="d-flex gap-2">
                <a
                  href="/qr/<?= Security::e(rawurlencode($uuid)) ?>"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="btn btn-outline-dark btn-sm flex-grow-1 rounded-3"
                >
                  <i class="bi bi-box-arrow-up-right me-1"></i>Vista Pública
                </a>

                <form
                  action="/app/qr/regenerar/<?= (int) $pet['id'] ?>"
                  method="POST"
                  class="js-confirm-regen"
                  data-pet="<?= Security::e($petName) ?>"
                >
                  <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />
                  <button type="submit" class="btn btn-outline-secondary btn-sm rounded-3" title="Regenerar código QR">
                    <i class="bi bi-arrow-repeat"></i>
                  </button>
                </form>
              </div>
            </div>
          </div>

          <div class="px-4 py-2 bg-light border-top text-muted" style="font-size: 0.74rem;">
            <i class="bi bi-link-45deg me-1"></i><?= Security::e($qrUrl) ?>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<script>
  'use strict';
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.js-confirm-regen').forEach((form) => {
      form.addEventListener('submit', (e) => {
        const pet = form.getAttribute('data-pet') || 'esta mascota';
        if (!window.confirm(`¿Regenerar el código QR de ${pet}? El QR anterior dejará de funcionar inmediatamente.`)) {
          e.preventDefault();
        }
      });
    });
  });
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';


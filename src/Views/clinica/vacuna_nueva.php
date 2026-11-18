<?php
/**
 * PetKardex v2 - Formulario Independiente Centrado de Aplicar Vacuna
 */

use PetKardex\Helpers\Security;

$pet   = $paciente ?? [];
$petId = (int) ($pet['id'] ?? 0);

ob_start();
?>

<div class="row justify-content-center">
  <div class="col-12 col-md-9 col-lg-7">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
      <div>
        <a href="/app/kardex/<?= $petId ?>#tab-vacunas" class="btn btn-sm btn-outline-dark rounded-pill px-3 mb-2">
          <i class="bi bi-arrow-left me-1"></i>Volver al Kardex de <?= Security::e((string) ($pet['nombre'] ?? '')) ?>
        </a>
        <h2 class="h4 fw-bold mb-0">Aplicar Vacuna</h2>
      </div>
    </div>

    <div class="card border-0 rounded-4 shadow-lg overflow-hidden">
      <div class="card-header border-0 text-white p-4" style="background: linear-gradient(135deg, #0D0D0D 0%, #2D1B69 100%);">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-shield-plus fs-4" style="color: #C48CFF;"></i>
          <div>
            <h3 class="h6 fw-bold mb-0">Registro de Inmunización</h3>
            <small class="text-white-50">Paciente: <?= Security::e((string) ($pet['nombre'] ?? '')) ?></small>
          </div>
        </div>
      </div>

      <div class="card-body p-4 p-md-5 bg-white">
        <form action="/app/kardex/vacuna/nueva" method="POST">
          <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />
          <input type="hidden" name="paciente_id" value="<?= $petId ?>" />

          <div class="mb-3">
            <div class="form-floating">
              <select name="vacuna_id" id="vacTipo" class="form-select" required>
                <option value="">Selecciona una vacuna...</option>
                <?php foreach ($vacunasCatalogo ?? [] as $vc): ?>
                  <option value="<?= (int) $vc['id'] ?>">
                    <?= Security::e((string) $vc['nombre']) ?> (<?= Security::e((string) ($vc['laboratorio'] ?? '')) ?>)
                  </option>
                <?php endforeach; ?>
              </select>
              <label for="vacTipo">Tipo de Vacuna *</label>
            </div>
          </div>

          <div class="mb-3">
            <div class="form-floating">
              <input type="text" name="lote" id="vacLote" class="form-control" required placeholder="Lote" />
              <label for="vacLote">Lote / Marca *</label>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <div class="form-floating">
                <input type="date" name="fecha" id="vacFecha" class="form-control" value="<?= date('Y-m-d') ?>" required />
                <label for="vacFecha">Fecha de Aplicación *</label>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-floating">
                <input type="date" name="proxima_dosis" id="vacProx" class="form-control" min="<?= date('Y-m-d') ?>" required />
                <label for="vacProx">Próximo Refuerzo (Obligatorio) *</label>
              </div>
            </div>
          </div>

          <div class="mb-4">
            <div class="form-floating">
              <textarea name="notas" id="vacNotas" class="form-control" style="height: 85px;" placeholder="Notas"></textarea>
              <label for="vacNotas">Notas clínicas (opcional)</label>
            </div>
          </div>

          <div class="d-flex justify-content-end gap-2 pt-3 border-top">
            <a href="/app/kardex/<?= $petId ?>#tab-vacunas" class="btn btn-outline-secondary rounded-3 px-4">Cancelar</a>
            <button type="submit" class="btn btn-pk-primary px-4">
              <i class="bi bi-check2-circle me-2"></i>Registrar Vacuna
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';

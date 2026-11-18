<?php
/**
 * PetKardex v2 - Formulario Independiente Centrado de Nueva Consulta Médica
 */

use PetKardex\Helpers\Security;

$pet   = $paciente ?? [];
$petId = (int) ($pet['id'] ?? 0);

ob_start();
?>

<div class="row justify-content-center">
  <div class="col-12 col-md-10 col-lg-8">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
      <div>
        <a href="/app/kardex/<?= $petId ?>" class="btn btn-sm btn-outline-dark rounded-pill px-3 mb-2">
          <i class="bi bi-arrow-left me-1"></i>Volver al Kardex de <?= Security::e((string) ($pet['nombre'] ?? '')) ?>
        </a>
        <h2 class="h4 fw-bold mb-0">Nueva Consulta Médica</h2>
      </div>
    </div>

    <div class="card border-0 rounded-4 shadow-lg overflow-hidden">
      <div class="card-header border-0 text-white p-4" style="background: linear-gradient(135deg, #0D0D0D 0%, #2D1B69 100%);">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-clipboard2-pulse-fill fs-4" style="color: #C48CFF;"></i>
          <div>
            <h3 class="h6 fw-bold mb-0">Registro Clínico de Atención</h3>
            <small class="text-white-50">Paciente: <?= Security::e((string) ($pet['nombre'] ?? '')) ?></small>
          </div>
        </div>
      </div>

      <div class="card-body p-4 p-md-5 bg-white">
        <form action="/app/kardex/consulta/nueva" method="POST">
          <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />
          <input type="hidden" name="paciente_id" value="<?= $petId ?>" />

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <div class="form-floating">
                <select name="tipo_consulta" id="consTipo" class="form-select">
                  <option value="consulta">Consulta General</option>
                  <option value="control">Control</option>
                  <option value="emergencia">Emergencia</option>
                  <option value="cirugia">Cirugía</option>
                </select>
                <label for="consTipo">Tipo de Consulta</label>
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-floating">
                <input type="number" step="0.01" min="0" name="peso_kg" id="consPeso" class="form-control" placeholder="Peso" value="<?= Security::e((string) ($pet['peso_kg'] ?? '')) ?>" />
                <label for="consPeso">Peso (kg)</label>
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-floating">
                <input type="number" step="0.1" min="30" max="45" name="temperatura" id="consTemp" class="form-control" placeholder="38.5" />
                <label for="consTemp">Temp. (°C)</label>
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-floating">
                <input type="number" min="0" name="frecuencia_cardiaca" id="consFc" class="form-control" placeholder="90" />
                <label for="consFc">FC (lpm)</label>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <div class="form-floating">
              <textarea name="motivo" id="consMotivo" class="form-control" style="height: 95px;" placeholder="Motivo" required></textarea>
              <label for="consMotivo">Motivo de consulta y síntomas *</label>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-8">
              <div class="form-floating">
                <textarea name="diagnostico" id="consDiag" class="form-control" style="height: 85px;" placeholder="Diagnóstico" required></textarea>
                <label for="consDiag">Diagnóstico *</label>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-floating">
                <input type="text" name="diagnostico_cie" id="consCie" class="form-control" placeholder="CIE" />
                <label for="consCie">Código CIE (opcional)</label>
              </div>
            </div>
          </div>

          <div class="mb-4">
            <div class="form-floating">
              <textarea name="tratamiento" id="consTrat" class="form-control" style="height: 95px;" placeholder="Tratamiento"></textarea>
              <label for="consTrat">Tratamiento e indicaciones</label>
            </div>
          </div>

          <div class="d-flex justify-content-end gap-2 pt-3 border-top">
            <a href="/app/kardex/<?= $petId ?>" class="btn btn-outline-secondary rounded-3 px-4">Cancelar</a>
            <button type="submit" class="btn btn-pk-primary px-4">
              <i class="bi bi-check2-circle me-2"></i>Guardar Consulta
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

<?php
/**
 * PetKardex v2 - Formulario Centrado de Edición de Mascota
 *
 * Permite actualizar Nombre, Especie, Raza, Sexo, Peso, Fecha de Nacimiento,
 * Propietario, datos clínicos básicos y reemplazar la fotografía (eliminando la anterior).
 *
 * Variables:
 * - array  $mascota
 * - array  $especies
 * - array  $razas
 * - array  $propietarios
 * - string $csrfToken
 */

use PetKardex\Helpers\Security;

$pet      = $mascota ?? [];
$espList  = $especies ?? [];
$razList  = $razas ?? [];
$propList = $propietarios ?? [];
$fotoUrl  = !empty($pet['foto']) ? '/' . ltrim((string) $pet['foto'], '/') : '';

ob_start();
?>

<div class="row justify-content-center">
  <div class="col-12 col-md-10 col-lg-8">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
      <div>
        <a href="/app/pacientes" class="btn btn-sm btn-outline-dark rounded-pill px-3 mb-2">
          <i class="bi bi-arrow-left me-1"></i>Volver al Directorio de Pacientes
        </a>
        <h2 class="h4 fw-bold mb-0">Editar Mascota: <?= Security::e((string) ($pet['nombre'] ?? '')) ?></h2>
        <small class="text-muted">Actualiza la información del paciente, su peso o reemplaza su fotografía clínica.</small>
      </div>
      <div>
        <a href="/app/kardex/<?= (int) ($pet['id'] ?? 0) ?>" class="btn btn-sm btn-outline-dark rounded-pill px-3">
          <i class="bi bi-clipboard2-pulse me-1"></i>Ver Kardex
        </a>
      </div>
    </div>

    <div class="card border-0 rounded-4 shadow-lg overflow-hidden">
      <div class="card-header border-0 text-white p-4" style="background: linear-gradient(135deg, #0D0D0D 0%, #2D1B69 100%);">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-pencil-square fs-4" style="color: #C48CFF;"></i>
          <div>
            <h3 class="h6 fw-bold mb-0">Actualizar Ficha del Paciente</h3>
            <small class="text-white-50">Los cambios se reflejarán de inmediato en el Directorio, Kardex y Perfil QR.</small>
          </div>
        </div>
      </div>

      <div class="card-body p-4 p-md-5 bg-white">
        <form action="/app/mascotas/editar/<?= (int) ($pet['id'] ?? 0) ?>" method="POST" enctype="multipart/form-data" id="formEditarMascota">
          <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />

          <!-- Sección 1: Fotografía actual / nueva -->
          <h4 class="h6 fw-bold mb-3"><i class="bi bi-camera-fill me-2" style="color: #7B52AB;"></i>1. Fotografía de la Mascota</h4>

          <div class="row g-3 mb-4 align-items-center p-3 rounded-4 border" style="background: #F8F5FF; border-color: rgba(196, 140, 255, 0.45) !important;">
            <div class="col-md-8">
              <label for="fotoMascota" class="form-label small fw-semibold">Cambiar Fotografía (JPG, PNG, WEBP, GIF · máx. 5 MB)</label>
              <input type="file" name="foto_mascota" id="fotoMascota" accept="image/*" class="form-control" />
              <div class="form-text">Déjalo vacío para conservar la fotografía actual. Si subes una nueva, se reemplazará la anterior del servidor.</div>
            </div>
            <div class="col-md-4 text-center">
              <div class="mx-auto rounded-circle border overflow-hidden d-flex align-items-center justify-content-center bg-white"
                   style="width: 100px; height: 100px; border-color: #C48CFF !important; border-width: 2px !important;">
                <img
                  id="fotoPreview"
                  src="<?= Security::e($fotoUrl) ?>"
                  alt="Fotografía actual"
                  class="w-100 h-100 object-fit-cover <?= $fotoUrl === '' ? 'd-none' : '' ?>"
                />
                <i id="fotoPlaceholder" class="bi bi-image fs-2 text-muted <?= $fotoUrl !== '' ? 'd-none' : '' ?>"></i>
              </div>
            </div>
          </div>

          <!-- Sección 2: Datos Principales y Propietario -->
          <h4 class="h6 fw-bold mb-3"><i class="bi bi-heart-pulse-fill me-2" style="color: #7B52AB;"></i>2. Datos del Paciente</h4>

          <div class="row g-3 mb-4">
            <div class="col-md-12">
              <div class="form-floating">
                <select name="propietario_id" id="selectPropId" class="form-select" required>
                  <?php foreach ($propList as $pr): ?>
                    <option value="<?= (int) $pr['id'] ?>" <?= ((int) ($pet['propietario_id'] ?? 0) === (int) $pr['id']) ? 'selected' : '' ?>>
                      <?= Security::e(trim($pr['nombre'] . ' ' . $pr['apellido'])) ?> — DNI: <?= Security::e((string) $pr['dni']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <label for="selectPropId">Propietario / Tutor *</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating">
                <input
                  type="text"
                  id="inputNombre"
                  name="nombre"
                  class="form-control"
                  value="<?= Security::e((string) ($pet['nombre'] ?? '')) ?>"
                  placeholder="Nombre"
                  required
                />
                <label for="inputNombre">Nombre de la Mascota *</label>
              </div>
            </div>

            <div class="col-md-3">
              <div class="form-floating">
                <select name="especie_id" id="selectEspecie" class="form-select" required>
                  <option value="">Selecciona...</option>
                  <?php foreach ($espList as $esp): ?>
                    <option value="<?= (int) $esp['id'] ?>" <?= ((int) ($pet['especie_id'] ?? 0) === (int) $esp['id']) ? 'selected' : '' ?>>
                      <?= Security::e((string) $esp['nombre']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <label for="selectEspecie">Especie *</label>
              </div>
            </div>

            <div class="col-md-3">
              <div class="form-floating">
                <select name="raza_id" id="selectRaza" class="form-select">
                  <option value="">Mestizo / Otra</option>
                  <?php foreach ($razList as $raz): ?>
                    <option
                      value="<?= (int) $raz['id'] ?>"
                      data-especie="<?= (int) $raz['especie_id'] ?>"
                      <?= ((int) ($pet['raza_id'] ?? 0) === (int) $raz['id']) ? 'selected' : '' ?>
                    >
                      <?= Security::e((string) $raz['nombre']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <label for="selectRaza">Raza</label>
              </div>
            </div>

            <div class="col-md-4">
              <div class="form-floating">
                <?php $sexoActual = strtolower((string) ($pet['sexo'] ?? 'desconocido')); ?>
                <select name="sexo" id="selectSexo" class="form-select" required>
                  <option value="macho" <?= $sexoActual === 'macho' ? 'selected' : '' ?>>Macho</option>
                  <option value="hembra" <?= $sexoActual === 'hembra' ? 'selected' : '' ?>>Hembra</option>
                  <option value="desconocido" <?= $sexoActual === 'desconocido' ? 'selected' : '' ?>>Desconocido</option>
                </select>
                <label for="selectSexo">Sexo *</label>
              </div>
            </div>

            <div class="col-md-4">
              <div class="form-floating">
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  id="inputPeso"
                  name="peso_kg"
                  class="form-control"
                  value="<?= ($pet['peso_kg'] !== null && $pet['peso_kg'] !== '') ? Security::e((string) $pet['peso_kg']) : '' ?>"
                  placeholder="Peso"
                />
                <label for="inputPeso">Peso Actual (kg)</label>
              </div>
            </div>

            <div class="col-md-4">
              <div class="form-floating">
                <input
                  type="date"
                  id="inputFechaNac"
                  name="fecha_nacimiento"
                  class="form-control"
                  value="<?= Security::e((string) ($pet['fecha_nacimiento'] ?? '')) ?>"
                  max="<?= date('Y-m-d') ?>"
                />
                <label for="inputFechaNac">Fecha de Nacimiento</label>
              </div>
            </div>

            <div class="col-md-4">
              <div class="form-floating">
                <input
                  type="text"
                  id="inputColor"
                  name="color"
                  class="form-control"
                  value="<?= Security::e((string) ($pet['color'] ?? '')) ?>"
                  placeholder="Color"
                />
                <label for="inputColor">Color / Pelaje</label>
              </div>
            </div>

            <div class="col-md-4">
              <div class="form-floating">
                <input
                  type="text"
                  id="inputMicrochip"
                  name="microchip"
                  class="form-control"
                  value="<?= Security::e((string) ($pet['microchip'] ?? '')) ?>"
                  placeholder="Microchip"
                />
                <label for="inputMicrochip">Microchip (opcional)</label>
              </div>
            </div>

            <div class="col-md-4">
              <div class="form-floating">
                <input
                  type="text"
                  id="inputSangre"
                  name="sangre_tipo"
                  class="form-control"
                  value="<?= Security::e((string) ($pet['sangre_tipo'] ?? '')) ?>"
                  placeholder="Tipo de Sangre"
                />
                <label for="inputSangre">Tipo de Sangre</label>
              </div>
            </div>

            <div class="col-12">
              <div class="form-check">
                <input
                  class="form-check-input"
                  type="checkbox"
                  name="esterilizado"
                  id="chkEsterilizado"
                  <?= !empty($pet['esterilizado']) ? 'checked' : '' ?>
                />
                <label class="form-check-label small fw-semibold" for="chkEsterilizado">Paciente esterilizado / castrado</label>
              </div>
            </div>

            <div class="col-12">
              <div class="form-floating">
                <textarea id="inputAlertas" name="alertas_medicas" class="form-control" style="height: 90px;" placeholder="Alertas Médicas"><?= Security::e((string) ($pet['alertas_medicas'] ?? '')) ?></textarea>
                <label for="inputAlertas">Alertas Médicas / Alergias Conocidas</label>
              </div>
            </div>
          </div>

          <div class="d-flex justify-content-end gap-2 pt-3 border-top">
            <a href="/app/pacientes" class="btn btn-outline-secondary rounded-3 px-4">Cancelar</a>
            <button type="submit" class="btn btn-pk-primary px-4">
              <i class="bi bi-check2-circle me-2"></i>Guardar Cambios
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    const selEsp = document.getElementById('selectEspecie');
    const selRaz = document.getElementById('selectRaza');
    if (selEsp && selRaz) {
      const allRazas = Array.from(selRaz.querySelectorAll('option[data-especie]'));

      function filterRazas(resetIfHidden) {
        const espId = selEsp.value;
        allRazas.forEach((opt) => {
          opt.hidden = Boolean(espId && opt.getAttribute('data-especie') !== espId);
        });
        if (resetIfHidden) {
          const selOpt = selRaz.options[selRaz.selectedIndex];
          if (selOpt && selOpt.hidden) {
            selRaz.value = '';
          }
        }
      }

      selEsp.addEventListener('change', () => filterRazas(true));
      filterRazas(false);
    }

    const fotoInput = document.getElementById('fotoMascota');
    const fotoPrev  = document.getElementById('fotoPreview');
    const fotoIcon  = document.getElementById('fotoPlaceholder');
    const origSrc   = fotoPrev ? fotoPrev.getAttribute('src') || '' : '';

    if (fotoInput && fotoPrev && fotoIcon) {
      fotoInput.addEventListener('change', () => {
        const file = fotoInput.files && fotoInput.files[0];
        if (!file) {
          if (origSrc !== '') {
            fotoPrev.src = origSrc;
            fotoPrev.classList.remove('d-none');
            fotoIcon.classList.add('d-none');
          } else {
            fotoPrev.classList.add('d-none');
            fotoIcon.classList.remove('d-none');
          }
          return;
        }
        const reader = new FileReader();
        reader.onload = (ev) => {
          fotoPrev.src = String(ev.target?.result || '');
          fotoPrev.classList.remove('d-none');
          fotoIcon.classList.add('d-none');
        };
        reader.readAsDataURL(file);
      });
    }
  });
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';


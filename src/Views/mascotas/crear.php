<?php
/**
 * PetKardex v2 - Formulario Centrado de Registro de Nueva Mascota
 *
 * Incluye subida de fotografía (foto_mascota), form-floating de Bootstrap 5,
 * selector de Propietario (existente o nuevo) y generación automática de QR UUID.
 *
 * Variables:
 * - array  $especies
 * - array  $razas
 * - array  $propietarios
 * - string $csrfToken
 */

use PetKardex\Helpers\Security;

$espList  = $especies ?? [];
$razList  = $razas ?? [];
$propList = $propietarios ?? [];

ob_start();
?>

<div class="row justify-content-center">
  <div class="col-12 col-md-10 col-lg-8">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
      <div>
        <a href="/app/pacientes" class="btn btn-sm btn-outline-dark rounded-pill px-3 mb-2">
          <i class="bi bi-arrow-left me-1"></i>Volver al Directorio de Pacientes
        </a>
        <h2 class="h4 fw-bold mb-0">Registrar Nueva Mascota</h2>
        <small class="text-muted">Al guardar se genera automáticamente el identificador UUID para el código QR de emergencia.</small>
      </div>
    </div>

    <div class="card border-0 rounded-4 shadow-lg overflow-hidden">
      <div class="card-header border-0 text-white p-4" style="background: linear-gradient(135deg, #0D0D0D 0%, #2D1B69 100%);">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-heart-pulse-fill fs-4" style="color: #C48CFF;"></i>
          <div>
            <h3 class="h6 fw-bold mb-0">Ficha de Registro del Paciente</h3>
            <small class="text-white-50">Completa los datos del tutor, la mascota y adjunta su fotografía.</small>
          </div>
        </div>
      </div>

      <div class="card-body p-4 p-md-5 bg-white">
        <form action="/app/mascotas/nueva" method="POST" enctype="multipart/form-data" id="formNuevaMascota">
          <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />

          <!-- Sección 1: Propietario -->
          <div class="p-3 p-md-4 rounded-4 border mb-4" style="background: #F8F5FF; border-color: rgba(196, 140, 255, 0.45) !important;">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
              <h4 class="h6 fw-bold mb-0" style="color: #5A3A8A;">
                <i class="bi bi-person-badge-fill me-2"></i>1. Propietario / Tutor
              </h4>
              <div class="btn-group btn-group-sm" role="group" aria-label="Modo propietario">
                <input type="radio" class="btn-check" name="propietario_modo" id="modoPropExistente" value="existente" <?= empty($propList) ? '' : 'checked' ?> />
                <label class="btn btn-outline-dark" for="modoPropExistente">Propietario Existente</label>
                <input type="radio" class="btn-check" name="propietario_modo" id="modoPropNuevo" value="nuevo" <?= empty($propList) ? 'checked' : '' ?> />
                <label class="btn btn-outline-dark" for="modoPropNuevo">Nuevo Propietario</label>
              </div>
            </div>

            <div id="boxPropExistente" class="<?= empty($propList) ? 'd-none' : '' ?>">
              <div class="form-floating">
                <select name="propietario_id" id="selectPropId" class="form-select">
                  <option value="">Selecciona un propietario registrado...</option>
                  <?php foreach ($propList as $pr): ?>
                    <option value="<?= (int) $pr['id'] ?>">
                      <?= Security::e(trim($pr['nombre'] . ' ' . $pr['apellido'])) ?> — DNI: <?= Security::e((string) $pr['dni']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <label for="selectPropId">Propietario Registrado *</label>
              </div>
            </div>

            <div id="boxPropNuevo" class="row g-3 <?= empty($propList) ? '' : 'd-none' ?>">
              <div class="col-md-4">
                <div class="form-floating">
                  <input type="text" name="prop_nombre" id="propNom" class="form-control" placeholder="Nombre" />
                  <label for="propNom">Nombre *</label>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-floating">
                  <input type="text" name="prop_apellido" id="propApe" class="form-control" placeholder="Apellido" />
                  <label for="propApe">Apellido *</label>
                </div>
              </div>
              <div class="col-md-4">
                <div class="form-floating">
                  <input type="text" name="prop_dni" id="propDni" class="form-control" placeholder="DNI" />
                  <label for="propDni">DNI *</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-floating">
                  <input type="text" name="prop_celular" id="propCel" class="form-control" placeholder="Celular" />
                  <label for="propCel">Celular / WhatsApp</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-floating">
                  <input type="email" name="prop_email" id="propMail" class="form-control" placeholder="correo@ejemplo.com" />
                  <label for="propMail">Correo electrónico</label>
                </div>
              </div>
            </div>
          </div>

          <!-- Sección 2: Datos de la Mascota + Fotografía -->
          <h4 class="h6 fw-bold mb-3"><i class="bi bi-camera-fill me-2" style="color: #7B52AB;"></i>2. Datos y Fotografía de la Mascota</h4>

          <div class="row g-3 mb-4 align-items-center">
            <div class="col-md-8">
              <label for="fotoMascota" class="form-label small fw-semibold">Fotografía de la Mascota (JPG, PNG, WEBP · máx. 5 MB)</label>
              <input type="file" name="foto_mascota" id="fotoMascota" accept="image/*" class="form-control" />
              <div class="form-text">Esta imagen se mostrará en el Carnet QR público de emergencia y en su Kardex.</div>
            </div>
            <div class="col-md-4 text-center">
              <div class="mx-auto rounded-circle border overflow-hidden d-flex align-items-center justify-content-center bg-light"
                   style="width: 96px; height: 96px; border-color: #C48CFF !important; border-width: 2px !important;">
                <img id="fotoPreview" src="" alt="Vista previa" class="w-100 h-100 object-fit-cover d-none" />
                <i id="fotoPlaceholder" class="bi bi-image fs-2 text-muted"></i>
              </div>
            </div>
          </div>

          <div class="row g-3 mb-4">
            <div class="col-md-6">
              <div class="form-floating">
                <input type="text" id="inputNombre" name="nombre" class="form-control" placeholder="Nombre" required />
                <label for="inputNombre">Nombre de la Mascota *</label>
              </div>
            </div>

            <div class="col-md-3">
              <div class="form-floating">
                <select name="especie_id" id="selectEspecie" class="form-select" required>
                  <option value="">Selecciona...</option>
                  <?php foreach ($espList as $esp): ?>
                    <option value="<?= (int) $esp['id'] ?>"><?= Security::e((string) $esp['nombre']) ?></option>
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
                    <option value="<?= (int) $raz['id'] ?>" data-especie="<?= (int) $raz['especie_id'] ?>">
                      <?= Security::e((string) $raz['nombre']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <label for="selectRaza">Raza</label>
              </div>
            </div>

            <div class="col-md-4">
              <div class="form-floating">
                <select name="sexo" id="selectSexo" class="form-select" required>
                  <option value="macho">Macho</option>
                  <option value="hembra">Hembra</option>
                  <option value="desconocido">Desconocido</option>
                </select>
                <label for="selectSexo">Sexo *</label>
              </div>
            </div>

            <div class="col-md-4">
              <div class="form-floating">
                <input type="date" id="inputFechaNac" name="fecha_nacimiento" class="form-control" max="<?= date('Y-m-d') ?>" />
                <label for="inputFechaNac">Fecha de Nacimiento</label>
              </div>
            </div>

            <div class="col-md-4">
              <div class="form-floating">
                <input type="number" step="0.01" min="0" id="inputPeso" name="peso_kg" class="form-control" placeholder="Peso" />
                <label for="inputPeso">Peso Actual (kg)</label>
              </div>
            </div>

            <div class="col-md-4">
              <div class="form-floating">
                <input type="text" id="inputColor" name="color" class="form-control" placeholder="Color" />
                <label for="inputColor">Color / Pelaje</label>
              </div>
            </div>

            <div class="col-md-4">
              <div class="form-floating">
                <input type="text" id="inputMicrochip" name="microchip" class="form-control" placeholder="Microchip" />
                <label for="inputMicrochip">Microchip (opcional)</label>
              </div>
            </div>

            <div class="col-md-4">
              <div class="form-floating">
                <input type="text" id="inputSangre" name="sangre_tipo" class="form-control" placeholder="Tipo de Sangre" />
                <label for="inputSangre">Tipo de Sangre</label>
              </div>
            </div>

            <div class="col-12">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="esterilizado" id="chkEsterilizado" />
                <label class="form-check-label small fw-semibold" for="chkEsterilizado">Paciente esterilizado / castrado</label>
              </div>
            </div>

            <div class="col-12">
              <div class="form-floating">
                <textarea id="inputAlertas" name="alertas_medicas" class="form-control" style="height: 90px;" placeholder="Alertas Médicas"></textarea>
                <label for="inputAlertas">Alertas Médicas / Alergias Conocidas</label>
              </div>
            </div>
          </div>

          <div class="d-flex justify-content-end gap-2 pt-3 border-top">
            <a href="/app/pacientes" class="btn btn-outline-secondary rounded-3 px-4">Cancelar</a>
            <button type="submit" class="btn btn-pk-primary px-4">
              <i class="bi bi-check2-circle me-2"></i>Guardar Mascota y Generar QR
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

    // Vista previa instantánea de fotografía
    const fotoInput = document.getElementById('fotoMascota');
    const fotoPrev  = document.getElementById('fotoPreview');
    const fotoIcon  = document.getElementById('fotoPlaceholder');
    if (fotoInput && fotoPrev && fotoIcon) {
      fotoInput.addEventListener('change', () => {
        const file = fotoInput.files && fotoInput.files[0];
        if (!file) {
          fotoPrev.classList.add('d-none');
          fotoIcon.classList.remove('d-none');
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

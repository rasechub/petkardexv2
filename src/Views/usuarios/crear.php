<?php
/**
 * PetKardex v2 - Formulario de Registro de Usuario / Propietario
 *
 * Permite registrar Propietarios (con DNI y datos de contacto), Veterinarios,
 * Recepcionistas, Estilistas, Guardias y SuperAdmin (solo SuperAdmin).
 * Diseño centrado con tarjetas elevadas (box-shadow) y form-floating de Bootstrap 5.
 *
 * Variables:
 * - array  $roles
 * - string $csrfToken
 */

use PetKardex\Helpers\Security;

$rolesList = $roles ?? [];

ob_start();
?>

<div class="row justify-content-center">
  <div class="col-12 col-md-10 col-lg-8">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
      <div>
        <a href="/app/usuarios" class="btn btn-sm btn-outline-dark rounded-pill px-3 mb-2">
          <i class="bi bi-arrow-left me-1"></i>Volver a Gestión de Usuarios
        </a>
        <h2 class="h4 fw-bold mb-0">Registrar Nuevo Usuario o Propietario</h2>
        <small class="text-muted">Asigna un rol clínico, administrativo o crea una cuenta de acceso para un propietario.</small>
      </div>
    </div>

    <div class="card border-0 rounded-4 shadow-lg overflow-hidden">
      <div class="card-header border-0 text-white p-4" style="background: linear-gradient(135deg, #0D0D0D 0%, #2D1B69 100%);">
        <div class="d-flex align-items-center gap-2">
          <i class="bi bi-person-plus-fill fs-4" style="color: #C48CFF;"></i>
          <div>
            <h3 class="h6 fw-bold mb-0">Datos de la Cuenta</h3>
            <small class="text-white-50">Las contraseñas se almacenan cifradas con bcrypt.</small>
          </div>
        </div>
      </div>

      <div class="card-body p-4 p-md-5 bg-white">
        <form action="/app/usuarios/nuevo" method="POST" autocomplete="off" id="formNuevoUsuario">
          <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />

          <div class="row g-3">
            <div class="col-md-12">
              <div class="form-floating">
                <select name="rol_id" id="selectRolUsuario" class="form-select" required>
                  <option value="" data-slug="">Selecciona un rol...</option>
                  <?php foreach ($rolesList as $rol): ?>
                    <option value="<?= (int) $rol['id'] ?>" data-slug="<?= Security::e((string) $rol['slug']) ?>">
                      <?= Security::e((string) $rol['nombre']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <label for="selectRolUsuario">Tipo de Usuario / Rol *</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating">
                <input type="text" name="nombre" id="usrNombre" class="form-control" placeholder="Nombre" required />
                <label for="usrNombre">Nombre *</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating">
                <input type="text" name="apellido" id="usrApellido" class="form-control" placeholder="Apellido" required />
                <label for="usrApellido">Apellido *</label>
              </div>
            </div>

            <div class="col-md-4">
              <div class="form-floating">
                <input
                  type="text"
                  name="dni"
                  id="usrDni"
                  class="form-control"
                  placeholder="DNI (8 dígitos)"
                  pattern="\d{8}"
                  maxlength="8"
                  minlength="8"
                  inputmode="numeric"
                  required
                />
                <label for="usrDni">DNI (8 dígitos) *</label>
              </div>
            </div>

            <div class="col-md-4">
              <div class="form-floating">
                <input type="email" name="email" id="usrEmail" class="form-control" placeholder="correo@ejemplo.com" required autocomplete="off" />
                <label for="usrEmail">Correo Electrónico *</label>
              </div>
            </div>

            <div class="col-md-4">
              <div class="form-floating">
                <input type="text" name="telefono" id="usrTelefono" class="form-control" placeholder="+51 999 000 000" />
                <label for="usrTelefono">Teléfono / Celular</label>
              </div>
            </div>

            <div class="col-md-12">
              <div class="form-floating">
                <input type="password" name="password" id="usrPassword" class="form-control" placeholder="Contraseña segura" minlength="8" required autocomplete="new-password" />
                <label for="usrPassword">Contraseña (mín. 8 caracteres, letra y número) *</label>
              </div>
            </div>

            <!-- Bloque específico para Propietario -->
            <div class="col-12 d-none" id="bloquePropietario">
              <div class="p-4 rounded-4 border mt-2" style="background: #F8F5FF; border-color: rgba(196, 140, 255, 0.45) !important;">
                <h4 class="h6 fw-bold mb-3" style="color: #5A3A8A;">
                  <i class="bi bi-person-vcard-fill me-2"></i>Ficha Adicional de Propietario (Cliente)
                </h4>
                <div class="row g-3">
                  <div class="col-md-12">
                    <div class="form-floating">
                      <input type="text" name="direccion" id="usrDireccion" class="form-control" placeholder="Dirección" />
                      <label for="usrDireccion">Dirección de domicilio (opcional)</label>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
            <a href="/app/usuarios" class="btn btn-outline-secondary rounded-3 px-4">Cancelar</a>
            <button type="submit" class="btn btn-pk-primary px-4">
              <i class="bi bi-check2-circle me-2"></i>Guardar Usuario
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
    const selRol = document.getElementById('selectRolUsuario');
    const bloqueProp = document.getElementById('bloquePropietario');
    const inputDni = document.getElementById('usrDni');

    function togglePropietarioFields() {
      const opt = selRol.options[selRol.selectedIndex];
      const slug = opt ? opt.getAttribute('data-slug') : '';
      const isOwner = slug === 'propietario';
      bloqueProp.classList.toggle('d-none', !isOwner);
    }

    inputDni?.addEventListener('input', () => {
      inputDni.value = inputDni.value.replace(/\D+/g, '').slice(0, 8);
    });

    selRol?.addEventListener('change', togglePropietarioFields);
    togglePropietarioFields();
  });
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';


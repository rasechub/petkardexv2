<?php
/**
 * PetKardex v2 - Listado de Gestión de Usuarios
 *
 * Variables:
 * - array  $usuarios
 * - array  $roles
 * - string $search
 * - int    $rolId
 * - string $csrfToken
 */

use PetKardex\Helpers\Security;

$lista     = $usuarios ?? [];
$rolesList = $roles ?? [];
$qText     = (string) ($search ?? '');
$selRol    = (int) ($rolId ?? 0);

ob_start();
?>

<div class="pk-card p-3 p-md-4 mb-4">
  <div class="row g-3 align-items-center">
    <div class="col-lg-8">
      <form action="/app/usuarios" method="GET" class="row g-2">
        <div class="col-md-6">
          <div class="input-group">
            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
            <input type="text" id="usrSearchInput" name="q" value="<?= Security::e($qText) ?>" class="form-control border-start-0" placeholder="Buscar por nombre, correo o DNI..." />
          </div>
        </div>
        <div class="col-md-4">
          <select name="rol_id" class="form-select">
            <option value="">Todos los roles</option>
            <?php foreach ($rolesList as $r): ?>
              <option value="<?= (int) $r['id'] ?>" <?= $selRol === (int) $r['id'] ? 'selected' : '' ?>>
                <?= Security::e((string) $r['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2 d-grid">
          <button type="submit" class="btn btn-outline-dark">Filtrar</button>
        </div>
      </form>
    </div>

    <div class="col-lg-4 text-lg-end">
      <a href="/app/usuarios/nuevo" class="btn btn-pk-primary px-4">
        <i class="bi bi-person-plus-fill me-2"></i>Nuevo Usuario / Propietario
      </a>
    </div>
  </div>
</div>

<div class="pk-card p-4">
  <h2 class="h6 fw-bold mb-3">
    <i class="bi bi-people-fill me-2" style="color: #7B52AB;"></i>
    Cuentas Registradas (<?= count($lista) ?>)
  </h2>

  <div class="table-responsive">
    <table class="table table-hover align-middle small mb-0">
      <thead class="table-light">
        <tr>
          <th>Nombre Completo</th>
          <th>Correo Electrónico</th>
          <th>Rol</th>
          <th>DNI / Teléfono</th>
          <th>Estado</th>
          <th class="text-end">Acción</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($lista as $u): ?>
          <?php
            $fullName = trim(($u['nombre'] ?? '') . ' ' . ($u['apellido'] ?? ''));
            $isActive = (int) ($u['activo'] ?? 0) === 1;
          ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <span class="pk-avatar" style="width:34px;height:34px;font-size:0.75rem;">
                  <?= Security::e(strtoupper(mb_substr((string) $u['nombre'], 0, 1) . mb_substr((string) $u['apellido'], 0, 1))) ?>
                </span>
                <div>
                  <strong class="text-dark"><?= Security::e($fullName) ?></strong>
                  <?php if (!empty($u['ultimo_login'])): ?>
                    <div class="text-muted" style="font-size:0.72rem;">Último acceso: <?= Security::e(date('d/m/Y H:i', strtotime((string) $u['ultimo_login']))) ?></div>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td><?= Security::e((string) $u['email']) ?></td>
            <td>
              <span class="badge rounded-pill" style="background: rgba(196,140,255,0.2); color: #5A3A8A;">
                <?= Security::e((string) $u['rol_nombre']) ?>
              </span>
            </td>
            <td>
              <?php $dniDisplay = (string) ($u['dni'] ?? $u['propietario_dni'] ?? ''); ?>
              <?php if ($dniDisplay !== ''): ?>
                <div><strong>DNI:</strong> <?= Security::e($dniDisplay) ?></div>
              <?php else: ?>
                <div class="text-warning small fw-semibold">Sin DNI</div>
              <?php endif; ?>
              <div class="text-muted"><?= Security::e((string) ($u['telefono'] ?: '-')) ?></div>
            </td>
            <td>
              <?php if ($isActive): ?>
                <span class="badge bg-success-subtle text-success rounded-pill">Activo</span>
              <?php else: ?>
                <span class="badge bg-secondary-subtle text-secondary rounded-pill">Inactivo</span>
              <?php endif; ?>
            </td>
            <td class="text-end text-nowrap">
              <button
                type="button"
                class="btn btn-sm btn-outline-dark rounded-3 me-1 js-edit-usuario"
                data-usuario='<?= Security::e(json_encode([
                    'id'        => (int) $u['id'],
                    'rol_id'    => (int) $u['rol_id'],
                    'rol_slug'  => (string) ($u['rol_slug'] ?? ''),
                    'nombre'    => (string) ($u['nombre'] ?? ''),
                    'apellido'  => (string) ($u['apellido'] ?? ''),
                    'email'     => (string) ($u['email'] ?? ''),
                    'telefono'  => (string) ($u['telefono'] ?? ''),
                    'dni'       => $dniDisplay,
                    'direccion' => (string) ($u['propietario_direccion'] ?? ''),
                ], JSON_UNESCAPED_UNICODE)) ?>'
                data-bs-toggle="modal"
                data-bs-target="#modalEditarUsuario"
              >
                <i class="bi bi-pencil-square me-1"></i>Editar
              </button>
              <form action="/app/usuarios/<?= (int) $u['id'] ?>/estado" method="POST" class="d-inline">
                <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />
                <button type="submit" class="btn btn-sm <?= $isActive ? 'btn-outline-danger' : 'btn-outline-success' ?> rounded-3">
                  <?= $isActive ? 'Desactivar' : 'Activar' ?>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- MODAL: EDITAR USUARIO -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-labelledby="modalEditarUsuarioLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content rounded-4 border-0 shadow-lg overflow-hidden">
      <div class="modal-header text-white border-0 p-4" style="background: linear-gradient(135deg, #0D0D0D 0%, #2D1B69 100%);">
        <h5 class="modal-title h6 fw-bold" id="modalEditarUsuarioLabel">
          <i class="bi bi-person-gear me-2" style="color: #C48CFF;"></i>Editar Cuenta de Usuario
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form id="formEditarUsuario" action="" method="POST" autocomplete="off">
        <div class="modal-body p-4">
          <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />
          <div class="row g-3">
            <div class="col-md-6">
              <div class="form-floating">
                <select name="rol_id" id="editUsrRol" class="form-select" required>
                  <?php foreach ($rolesList as $r): ?>
                    <option value="<?= (int) $r['id'] ?>" data-slug="<?= Security::e((string) $r['slug']) ?>">
                      <?= Security::e((string) $r['nombre']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
                <label for="editUsrRol">Rol del Usuario *</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating">
                <input type="text" name="dni" id="editUsrDni" class="form-control" placeholder="DNI (8 dígitos)" pattern="\d{8}" maxlength="8" minlength="8" inputmode="numeric" required />
                <label for="editUsrDni">DNI (8 dígitos) *</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating">
                <input type="text" name="nombre" id="editUsrNombre" class="form-control" placeholder="Nombre" required />
                <label for="editUsrNombre">Nombre *</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating">
                <input type="text" name="apellido" id="editUsrApellido" class="form-control" placeholder="Apellido" required />
                <label for="editUsrApellido">Apellido *</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating">
                <input type="email" name="email" id="editUsrEmail" class="form-control" placeholder="correo@ejemplo.com" required />
                <label for="editUsrEmail">Correo Electrónico *</label>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-floating">
                <input type="text" name="telefono" id="editUsrTelefono" class="form-control" placeholder="+51 999 000 000" />
                <label for="editUsrTelefono">Teléfono / Celular</label>
              </div>
            </div>

            <div class="col-md-12" id="editUsrDirWrap">
              <div class="form-floating">
                <input type="text" name="direccion" id="editUsrDireccion" class="form-control" placeholder="Dirección" />
                <label for="editUsrDireccion">Dirección de domicilio (propietario)</label>
              </div>
            </div>

            <div class="col-md-12">
              <div class="form-floating">
                <input type="password" name="password" id="editUsrPassword" class="form-control" placeholder="Nueva contraseña (opcional)" minlength="8" autocomplete="new-password" />
                <label for="editUsrPassword">Nueva Contraseña (opcional — dejar vacío para mantener la actual)</label>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light border-0 p-3 px-4">
          <button type="button" class="btn btn-outline-secondary rounded-3 px-4" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-pk-primary px-4">
            <i class="bi bi-check2-circle me-2"></i>Guardar Cambios
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    const formEdit   = document.getElementById('formEditarUsuario');
    const editRol    = document.getElementById('editUsrRol');
    const editDni    = document.getElementById('editUsrDni');
    const editNom    = document.getElementById('editUsrNombre');
    const editApe    = document.getElementById('editUsrApellido');
    const editEmail  = document.getElementById('editUsrEmail');
    const editTel    = document.getElementById('editUsrTelefono');
    const editDir    = document.getElementById('editUsrDireccion');
    const editDirWrp = document.getElementById('editUsrDirWrap');
    const editPwd    = document.getElementById('editUsrPassword');

    function toggleDirWrap() {
      if (!editRol || !editDirWrp) return;
      const opt  = editRol.options[editRol.selectedIndex];
      const slug = opt ? opt.getAttribute('data-slug') : '';
      editDirWrp.classList.toggle('d-none', slug !== 'propietario');
    }

    editRol?.addEventListener('change', toggleDirWrap);
    editDni?.addEventListener('input', () => {
      editDni.value = editDni.value.replace(/\D+/g, '').slice(0, 8);
    });

    document.querySelectorAll('.js-edit-usuario').forEach((btn) => {
      btn.addEventListener('click', () => {
        const raw = btn.getAttribute('data-usuario');
        if (!raw || !formEdit) return;
        const u = JSON.parse(raw);

        formEdit.action   = `/app/usuarios/${u.id}/editar`;
        if (editRol)   editRol.value   = String(u.rol_id || '');
        if (editDni)   editDni.value   = u.dni || '';
        if (editNom)   editNom.value   = u.nombre || '';
        if (editApe)   editApe.value   = u.apellido || '';
        if (editEmail) editEmail.value = u.email || '';
        if (editTel)   editTel.value   = u.telefono || '';
        if (editDir)   editDir.value   = u.direccion || '';
        if (editPwd)   editPwd.value   = '';

        toggleDirWrap();
      });
    });
  });
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';


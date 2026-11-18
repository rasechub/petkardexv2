<?php
/**
 * PetKardex v2 - UsuarioController
 *
 * Módulo de Gestión de Usuarios (SuperAdmin y Recepcionista):
 * Permite listar, activar/desactivar y registrar Propietarios (con DNI),
 * Veterinarios, Recepcionistas, Estilistas y Guardias.
 */

declare(strict_types=1);

namespace PetKardex\Controllers;

use PetKardex\Config\Config;
use PetKardex\Core\Auth;
use PetKardex\Core\Controller;
use PetKardex\Models\Usuario;

class UsuarioController extends Controller
{
    private Auth $auth;
    private Usuario $usuarioModel;

    private const ALLOWED_ROLES = ['superadmin', 'recepcionista'];

    public function __construct()
    {
        parent::__construct();
        $this->auth         = new Auth();
        $this->usuarioModel = new Usuario();
    }

    /**
     * GET /app/usuarios | /app/admin/usuarios
     * Directorio de usuarios del sistema y clientes propietarios.
     */
    public function index(): void
    {
        $this->requireAuth(self::ALLOWED_ROLES);

        $this->usuarioModel->asignarDnisFaltantes();

        $search  = trim((string) $this->query('q', ''));
        $rolId   = (int) $this->query('rol_id', 0);
        $user    = $this->auth->getCurrentUser() ?? ($_SESSION['user'] ?? []);
        $rolSlug = (string) ($user['rol_slug'] ?? '');

        $this->render('usuarios/index', [
            'title'      => 'Gestión de Usuarios — ' . Config::APP_NAME,
            'pageTitle'  => 'Gestión de Usuarios y Propietarios',
            'activeMenu' => 'usuarios',
            'auth'       => $this->auth,
            'user'       => $user,
            'usuarios'   => $this->usuarioModel->getAllUsuarios($search, $rolId > 0 ? $rolId : null),
            'roles'      => $this->usuarioModel->getRolesParaCrear($rolSlug),
            'search'     => $search,
            'rolId'      => $rolId,
            'csrfToken'  => $this->generateToken(),
        ]);
    }

    /**
     * GET /app/usuarios/nuevo
     * Muestra el formulario para registrar personal o nuevo propietario.
     */
    public function crearForm(): void
    {
        $this->requireAuth(self::ALLOWED_ROLES);

        $user    = $this->auth->getCurrentUser() ?? ($_SESSION['user'] ?? []);
        $rolSlug = (string) ($user['rol_slug'] ?? '');

        $this->render('usuarios/crear', [
            'title'      => 'Registrar Usuario — ' . Config::APP_NAME,
            'pageTitle'  => 'Registrar Personal o Propietario',
            'activeMenu' => 'usuarios',
            'auth'       => $this->auth,
            'user'       => $user,
            'roles'      => $this->usuarioModel->getRolesParaCrear($rolSlug),
            'csrfToken'  => $this->generateToken(),
        ]);
    }

    /**
     * POST /app/usuarios/nuevo
     * Guarda el nuevo usuario (y perfil en propietarios si aplica).
     */
    public function guardar(): void
    {
        $this->requireAuth(self::ALLOWED_ROLES);
        $this->requireValidCsrf();

        $user    = $this->auth->getCurrentUser() ?? ($_SESSION['user'] ?? []);
        $rolSlug = (string) ($user['rol_slug'] ?? '');

        try {
            $this->usuarioModel->crearUsuario($_POST, $rolSlug);
            $nombre = trim((string) $this->input('nombre', '') . ' ' . (string) $this->input('apellido', ''));
            $this->setFlash('success', "Usuario \"{$nombre}\" registrado exitosamente.");
            $this->redirect('/app/usuarios');
        } catch (\Throwable $e) {
            $this->setFlash('error', 'No se pudo registrar el usuario: ' . $e->getMessage());
            $this->redirect('/app/usuarios/nuevo');
        }
    }

    /**
     * POST /app/usuarios/{id}/editar
     * Actualiza los datos, DNI, rol y opcionalmente la contraseña de un usuario.
     */
    public function actualizar(string $id = ''): void
    {
        $this->requireAuth(self::ALLOWED_ROLES);
        $this->requireValidCsrf();

        $user    = $this->auth->getCurrentUser() ?? ($_SESSION['user'] ?? []);
        $rolSlug = (string) ($user['rol_slug'] ?? '');

        try {
            $this->usuarioModel->actualizarUsuario((int) $id, $_POST, $rolSlug);
            $nombre = trim((string) $this->input('nombre', '') . ' ' . (string) $this->input('apellido', ''));
            $this->setFlash('success', "Cuenta de \"{$nombre}\" actualizada correctamente.");
        } catch (\Throwable $e) {
            $this->setFlash('error', 'No se pudo actualizar el usuario: ' . $e->getMessage());
        }

        $this->redirect('/app/usuarios');
    }

    /**
     * POST /app/usuarios/{id}/estado
     * Activa o desactiva un usuario existente.
     */
    public function toggleEstado(string $id = ''): void
    {
        $this->requireAuth(self::ALLOWED_ROLES);
        $this->requireValidCsrf();

        try {
            $nuevo = $this->usuarioModel->toggleEstado((int) $id, (int) ($_SESSION['user_id'] ?? 0));
            $this->setFlash('success', $nuevo === 1 ? 'Usuario activado correctamente.' : 'Usuario desactivado.');
        } catch (\Throwable $e) {
            $this->setFlash('error', $e->getMessage());
        }

        $this->redirect('/app/usuarios');
    }
}


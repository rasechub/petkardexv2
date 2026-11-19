<?php
/**
 * PetKardex v2 - Clase base Controller
 *
 * @author    CESAR AQUINO ANGLAS - VI B NOCHE
 * @copyright 2026 PetKardex. Todos los derechos reservados.
 * @license   Propietaria - IESTP Santiago Antúnez de Mayolo (IESTP SAM)
 */

declare(strict_types=1);

namespace PetKardex\Core;

use PetKardex\Config\Config;

abstract class Controller
{
    protected string $viewsPath;

    public function __construct()
    {
        $this->viewsPath = __DIR__ . '/../Views/';

        Config::initialize();

        // Iniciar sesión si no está activa
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    // ─── Renderizado de vistas ──────────────────────────────────────────────

    /**
     * Renderiza una vista PHP extrayendo las variables del array $data.
     *
     * @param  string $view  Ruta relativa a Views/, ej: 'auth/login'
     * @param  array  $data  Variables que estarán disponibles en la vista
     */
    protected function render(string $view, array $data = []): void
    {
        $viewFile = $this->viewsPath . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewFile)) {
            $this->send404("Vista no encontrada: {$view}");
            return;
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=UTF-8');
        }

        // Extrae variables para la vista
        extract($data, EXTR_SKIP);

        // Variables de sesión siempre disponibles
        $flash       = $this->getFlash();
        $currentUser = $_SESSION['user'] ?? null;
        $csrfToken   = $this->generateToken();

        require $viewFile;
    }

    // ─── Redirección ───────────────────────────────────────────────────────

    /**
     * Redirige a la URL indicada y detiene la ejecución.
     *
     * @param string $url  URL absoluta o relativa
     * @param int    $code Código HTTP (default 302)
     */
    protected function redirect(string $url, int $code = 302): never
    {
        http_response_code($code);
        header("Location: {$url}");
        exit;
    }

    // ─── Respuestas JSON ───────────────────────────────────────────────────

    /**
     * Envía una respuesta JSON y detiene la ejecución.
     *
     * @param mixed $data
     * @param int   $code Código HTTP (default 200)
     */
    protected function json(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    // ─── Autenticación / Autorización ──────────────────────────────────────

    /**
     * Verifica si hay un usuario autenticado.
     */
    protected function isLogged(): bool
    {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }

    /**
     * Exige autenticación. Si el usuario no está logueado, redirige al login.
     * Si se pasa $roles, verifica también que el usuario tenga uno de esos roles.
     *
     * @param string|array $roles  Slug(s) de rol permitido(s), vacío = cualquier rol
     */
    protected function requireAuth(string|array $roles = []): void
    {
        if (!$this->isLogged()) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'error' => 'Autenticación requerida.'], 401);
            }
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/app/dashboard';
            $this->redirect('/app/login');
        }

        if (!empty($roles)) {
            $roles = (array) $roles;
            if (!$this->hasRole($roles) && !$this->hasRole('superadmin')) {
                if ($this->isAjax()) {
                    $this->json(['success' => false, 'error' => 'Acceso denegado para tu rol.'], 403);
                }
                $this->redirect('/app/unauthorized');
            }
        }
    }

    /**
     * Verifica si el usuario tiene alguno de los roles indicados.
     *
     * @param string|array $roles
     */
    protected function hasRole(string|array $roles): bool
    {
        $userRole = $_SESSION['user']['rol_slug'] ?? '';
        $roles    = (array) $roles;
        return in_array($userRole, $roles, true);
    }

    /**
     * Verifica si el usuario tiene un permiso específico (clave del JSON de permisos del rol).
     * Soporta tanto formato lista de seeds.sql (['pacientes' => ['ver', 'crear']])
     * como formato asociativo (['pacientes' => ['crear' => true]]).
     *
     * @param string $permission  Clave del permiso, ej: 'pacientes.crear' o 'dashboard'
     */
    protected function hasPermission(string $permission): bool
    {
        if (($_SESSION['user']['rol_slug'] ?? '') === 'superadmin') {
            return true;
        }

        $permisos = $_SESSION['user']['permisos'] ?? [];

        if (is_string($permisos)) {
            $permisos = json_decode($permisos, true) ?? [];
        }

        if (($permisos['*'] ?? false) === true) {
            return true;
        }

        $parts = explode('.', $permission);
        if (count($parts) === 1) {
            $module = $parts[0];
            return !empty($permisos[$module]);
        }

        if (count($parts) === 2) {
            [$module, $action] = $parts;
            if (!isset($permisos[$module])) {
                return false;
            }
            $modulePerms = $permisos[$module];
            if (is_array($modulePerms)) {
                // Lista indexada: ['ver', 'crear']
                if (array_is_list($modulePerms)) {
                    return in_array($action, $modulePerms, true) || in_array('*', $modulePerms, true);
                }
                // Asociativo: ['crear' => true]
                return (bool) ($modulePerms[$action] ?? false);
            }
            return (bool) $modulePerms;
        }

        $node = $permisos;
        foreach ($parts as $key) {
            if (!is_array($node) || !isset($node[$key])) {
                return false;
            }
            $node = $node[$key];
        }

        return (bool) $node;
    }

    /**
     * Exige un permiso específico o aborta con 403.
     */
    protected function requirePermission(string $permission): void
    {
        $this->requireAuth();

        if (!$this->hasPermission($permission)) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'error' => 'Permiso denegado.'], 403);
            }
            $this->redirect('/app/unauthorized');
        }
    }

    // ─── Protección CSRF ───────────────────────────────────────────────────

    /**
     * Genera (o recupera) el token CSRF de la sesión actual.
     */
    protected function generateToken(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    /**
     * Valida el token CSRF enviado en el formulario.
     * Redirige con error si el token es inválido.
     *
     * @param string $token Token recibido (típicamente $_POST['_csrf_token'])
     */
    protected function validateToken(string $token): bool
    {
        $sessionToken = $_SESSION['_csrf_token'] ?? '';
        return hash_equals($sessionToken, $token);
    }

    /**
     * Valida el CSRF (POST o cabecera X-CSRF-TOKEN) y detiene la ejecución si falla.
     */
    protected function requireValidCsrf(): void
    {
        $token = $_POST['_csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($token) || !$this->validateToken($token)) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'error' => 'Token CSRF inválido o expirado.'], 419);
            }
            $this->setFlash('error', 'Solicitud inválida. Por favor, intenta nuevamente.');
            $this->redirect($_SERVER['HTTP_REFERER'] ?? '/app/login');
        }
    }

    // ─── Flash messages ────────────────────────────────────────────────────

    /**
     * Almacena un mensaje flash en la sesión.
     *
     * @param string $type  'success' | 'error' | 'warning' | 'info'
     * @param string $msg   Texto del mensaje
     */
    protected function setFlash(string $type, string $msg): void
    {
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $msg];
    }

    /**
     * Recupera y elimina los mensajes flash de la sesión.
     *
     * @return array<array{type: string, message: string}>
     */
    protected function getFlash(): array
    {
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $messages;
    }

    // ─── Respuestas de error ───────────────────────────────────────────────

    private function send404(string $msg = 'Página no encontrada'): void
    {
        http_response_code(404);
        $view404 = $this->viewsPath . 'errors/404.php';
        if (file_exists($view404)) {
            require $view404;
        } else {
            echo "<h1>404 — {$msg}</h1>";
        }
        exit;
    }

    // ─── Helpers de solicitud ──────────────────────────────────────────────

    /**
     * Retorna true si la solicitud actual es POST.
     */
    protected function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /**
     * Retorna true si la solicitud espera JSON (AJAX).
     */
    protected function isAjax(): bool
    {
        $xhr    = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return $xhr === 'xmlhttprequest' || str_contains($accept, 'application/json');
    }

    /**
     * Obtiene un valor del POST con trim, o $default si no existe.
     */
    protected function input(string $key, mixed $default = null): mixed
    {
        return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
    }

    /**
     * Obtiene un valor del GET con trim, o $default si no existe.
     */
    protected function query(string $key, mixed $default = null): mixed
    {
        return isset($_GET[$key]) ? trim((string) $_GET[$key]) : $default;
    }
}

<?php
/**
 * PetKardex v2 - Router
 *
 * Parsea REQUEST_URI, registra rutas con control de autenticación y roles (ACL),
 * soporta parámetros dinámicos (/paciente/{id}) y hace dispatch al controlador.
 */

declare(strict_types=1);

namespace PetKardex\Core;

use PetKardex\Config\Config;
use PetKardex\Controllers\AgendaController;
use PetKardex\Controllers\AuthController;
use PetKardex\Controllers\CajaController;
use PetKardex\Controllers\DashboardController;
use PetKardex\Controllers\EsteticaController;
use PetKardex\Controllers\HospitalizacionController;
use PetKardex\Controllers\InventarioController;
use PetKardex\Controllers\KardexController;
use PetKardex\Controllers\MascotaController;
use PetKardex\Controllers\PacienteController;
use PetKardex\Controllers\QRController;
use PetKardex\Controllers\UsuarioController;

class Router
{
    /** @var array<string, array<string, array>> Rutas registradas indexadas por método HTTP */
    private array $routes = [
        'GET'    => [],
        'POST'   => [],
        'PUT'    => [],
        'PATCH'  => [],
        'DELETE' => [],
    ];

    /** Prefijo activo cuando se usa group() */
    private string $prefix = '';

    /** Roles heredados dentro de un group() */
    private array $groupRoles = [];

    /** Protección heredada dentro de un group() */
    private bool $groupProtected = false;

    // ─── Registro de rutas ─────────────────────────────────────────────────

    /**
     * Registra una ruta GET.
     *
     * @param  string         $path       Ej: '/app/paciente/{id}'
     * @param  callable|array $handler    Callable, o [ControllerClass::class, 'method']
     * @param  bool           $protected  true = requiere sesión activa
     * @param  string[]       $roles      Slugs de roles permitidos (vacío = cualquier rol autenticado)
     */
    public function get(string $path, callable|array $handler, bool $protected = false, array $roles = []): static
    {
        $this->addRoute('GET', $path, $handler, $protected, $roles);
        return $this;
    }

    /**
     * Registra una ruta POST.
     */
    public function post(string $path, callable|array $handler, bool $protected = false, array $roles = []): static
    {
        $this->addRoute('POST', $path, $handler, $protected, $roles);
        return $this;
    }

    /**
     * Registra una ruta PUT.
     */
    public function put(string $path, callable|array $handler, bool $protected = false, array $roles = []): static
    {
        $this->addRoute('PUT', $path, $handler, $protected, $roles);
        return $this;
    }

    /**
     * Registra una ruta PATCH.
     */
    public function patch(string $path, callable|array $handler, bool $protected = false, array $roles = []): static
    {
        $this->addRoute('PATCH', $path, $handler, $protected, $roles);
        return $this;
    }

    /**
     * Registra una ruta DELETE.
     */
    public function delete(string $path, callable|array $handler, bool $protected = false, array $roles = []): static
    {
        $this->addRoute('DELETE', $path, $handler, $protected, $roles);
        return $this;
    }

    /**
     * Registra una ruta para GET y POST.
     */
    public function any(string $path, callable|array $handler, bool $protected = false, array $roles = []): static
    {
        foreach (['GET', 'POST'] as $method) {
            $this->addRoute($method, $path, $handler, $protected, $roles);
        }
        return $this;
    }

    /**
     * Agrupa rutas compartiendo prefijo, estado protegido y roles permitidos.
     *
     * @param array{prefix?: string, protected?: bool, roles?: string[]} $attributes
     */
    public function group(array $attributes, callable $callback): static
    {
        $previousPrefix    = $this->prefix;
        $previousProtected = $this->groupProtected;
        $previousRoles     = $this->groupRoles;

        $this->prefix         = rtrim($previousPrefix . ($attributes['prefix'] ?? ''), '/');
        $this->groupProtected = $attributes['protected'] ?? $previousProtected;
        $this->groupRoles     = $attributes['roles'] ?? $previousRoles;

        $callback($this);

        $this->prefix         = $previousPrefix;
        $this->groupProtected = $previousProtected;
        $this->groupRoles     = $previousRoles;

        return $this;
    }

    /**
     * Registra las rutas públicas y privadas del sistema (Auth, Dashboard y Módulo QR).
     */
    public function registerDefaultRoutes(): static
    {
        // Redirección raíz de /app
        $this->get('/app', static function (): void {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $target = !empty($_SESSION['user_id']) ? '/app/dashboard' : '/app/login';
            header("Location: {$target}");
            exit;
        });

        // ── Rutas Públicas (Sin Login) ──────────────────────────────────────
        $this->get('/qr/{uuid}', [QRController::class, 'emergencyProfile']);
        $this->get('/app/login', [AuthController::class, 'loginPage']);
        $this->post('/app/login', [AuthController::class, 'loginProcess']);
        $this->any('/app/logout', [AuthController::class, 'logout']);
        $this->get('/app/forgot-password', [AuthController::class, 'forgotPassword']);
        $this->post('/app/forgot-password', [AuthController::class, 'forgotPassword']);
        $this->get('/app/reset-password/{token}', [AuthController::class, 'resetPassword']);
        $this->post('/app/reset-password/{token}', [AuthController::class, 'resetPassword']);

        // ── Rutas Privadas Autenticadas (ACL por Rol) ───────────────────────
        $this->get('/app/unauthorized', [AuthController::class, 'unauthorized'], true);
        $this->get('/app/auth/me', [AuthController::class, 'me'], true);
        $this->get('/app/dashboard', [DashboardController::class, 'index'], true);

        // Módulo QR — Propietarios y asistencia clínica
        $this->get('/app/qr', [QRController::class, 'ownerIndex'], true, [
            'propietario', 'superadmin', 'veterinario', 'recepcionista',
        ]);
        $this->get('/app/qr/descargar/{uuid}', [QRController::class, 'downloadQR'], true, [
            'propietario', 'superadmin', 'veterinario', 'recepcionista',
        ]);
        $this->post('/app/qr/regenerar/{id}', [QRController::class, 'regenerateQR'], true, [
            'propietario', 'superadmin', 'veterinario', 'recepcionista',
        ]);

        // Módulo QR — Escáner Interno (sólo personal de clínica)
        $clinicRoles = ['veterinario', 'guardia', 'recepcionista', 'emergencia', 'superadmin'];
        $this->get('/app/qr/scanner', [QRController::class, 'scanner'], true, $clinicRoles);
        $this->get('/app/escaner', [QRController::class, 'scanner'], true, $clinicRoles);
        $this->post('/app/qr/resolver', [QRController::class, 'resolveScan'], true, $clinicRoles);

        // Gestión de Usuarios y Propietarios (SuperAdmin y Recepcionista)
        $userAdminRoles = ['superadmin', 'recepcionista'];
        $this->get('/app/usuarios', [UsuarioController::class, 'index'], true, $userAdminRoles);
        $this->get('/app/admin/usuarios', [UsuarioController::class, 'index'], true, $userAdminRoles);
        $this->get('/app/usuarios/nuevo', [UsuarioController::class, 'crearForm'], true, $userAdminRoles);
        $this->post('/app/usuarios/nuevo', [UsuarioController::class, 'guardar'], true, $userAdminRoles);
        $this->post('/app/usuarios/{id}/editar', [UsuarioController::class, 'actualizar'], true, $userAdminRoles);
        $this->post('/app/usuarios/{id}/estado', [UsuarioController::class, 'toggleEstado'], true, $userAdminRoles);

        // Directorio de Pacientes / Mascotas y Registro de Nuevas Mascotas
        $this->get('/app/pacientes', [PacienteController::class, 'index'], true, $clinicRoles);
        $this->get('/app/mascotas', [PacienteController::class, 'index'], true, $clinicRoles);
        $this->post('/app/pacientes/crear', [PacienteController::class, 'crear'], true, ['superadmin', 'veterinario', 'recepcionista']);
        $this->get('/app/mascotas/nueva', [MascotaController::class, 'crearForm'], true, ['superadmin', 'veterinario', 'recepcionista']);
        $this->post('/app/mascotas/nueva', [MascotaController::class, 'guardar'], true, ['superadmin', 'veterinario', 'recepcionista']);
        $this->get('/app/mascotas/editar/{id}', [MascotaController::class, 'editarForm'], true, ['superadmin', 'veterinario', 'recepcionista']);
        $this->post('/app/mascotas/editar/{id}', [MascotaController::class, 'editar'], true, ['superadmin', 'veterinario', 'recepcionista']);

        // Módulo Clínico (Kardex, Consultas, Vacunas y Certificados PDF)
        $kardexReadRoles  = ['superadmin', 'veterinario', 'guardia', 'emergencia', 'recepcionista', 'propietario'];
        $kardexWriteRoles = ['superadmin', 'veterinario'];
        $certRoles        = ['superadmin', 'veterinario', 'recepcionista', 'propietario'];

        $this->get('/app/kardex/consulta/nueva', [KardexController::class, 'consultaForm'], true, $kardexWriteRoles);
        $this->post('/app/kardex/consulta/nueva', [KardexController::class, 'guardarConsulta'], true, $kardexWriteRoles);
        $this->post('/app/kardex/consulta/{id}/editar', [KardexController::class, 'editarConsulta'], true, $kardexWriteRoles);
        $this->post('/app/kardex/consulta/{id}/eliminar', [KardexController::class, 'eliminarConsulta'], true, $kardexWriteRoles);

        $this->get('/app/kardex/vacuna/nueva', [KardexController::class, 'vacunaForm'], true, $kardexWriteRoles);
        $this->post('/app/kardex/vacuna/nueva', [KardexController::class, 'guardarVacuna'], true, $kardexWriteRoles);
        $this->post('/app/kardex/vacuna/{id}/eliminar', [KardexController::class, 'eliminarVacuna'], true, $kardexWriteRoles);

        $this->get('/app/kardex/certificado/{id}', [KardexController::class, 'generarCertificado'], true, $certRoles);
        $this->get('/app/kardex/{id}', [KardexController::class, 'ver'], true, $kardexReadRoles);

        // Módulo de Inventario y Caja (POS) — Exclusivo SuperAdmin y Recepcionista/Cajero
        $finanzasRoles = ['superadmin', 'recepcionista'];

        $this->get('/app/inventario', [InventarioController::class, 'index'], true, $finanzasRoles);
        $this->post('/app/inventario/crear', [InventarioController::class, 'crear'], true, $finanzasRoles);
        $this->post('/app/inventario/editar/{id}', [InventarioController::class, 'editar'], true, $finanzasRoles);
        $this->post('/app/inventario/eliminar/{id}', [InventarioController::class, 'eliminar'], true, $finanzasRoles);
        $this->post('/app/inventario/ajustar', [InventarioController::class, 'ajustar'], true, $finanzasRoles);
        $this->get('/app/inventario/alertas', [InventarioController::class, 'alertas'], true, $finanzasRoles);
        $this->get('/app/inventario/movimientos', [InventarioController::class, 'movimientos'], true, $finanzasRoles);

        // Gestor Dinámico de Servicios y Tarifas (Exclusivo SuperAdmin)
        $this->get('/app/servicios', [InventarioController::class, 'servicios'], true, ['superadmin']);
        $this->post('/app/servicios/crear', [InventarioController::class, 'crearServicio'], true, ['superadmin']);
        $this->post('/app/servicios/editar/{id}', [InventarioController::class, 'editarServicio'], true, ['superadmin']);
        $this->post('/app/servicios/eliminar/{id}', [InventarioController::class, 'eliminarServicio'], true, ['superadmin']);

        $this->get('/app/caja', [CajaController::class, 'pos'], true, $finanzasRoles);
        $this->get('/app/caja/pos', [CajaController::class, 'pos'], true, $finanzasRoles);
        $this->get('/app/ventas/nueva', [CajaController::class, 'pos'], true, $finanzasRoles);
        $this->get('/app/caja/buscar-cuenta', [CajaController::class, 'buscarCuenta'], true, $finanzasRoles);
        $this->post('/app/caja/venta', [CajaController::class, 'procesarVenta'], true, $finanzasRoles);
        $this->post('/app/caja/procesar', [CajaController::class, 'procesarVenta'], true, $finanzasRoles);
        $this->get('/app/caja/recibo/{id}', [CajaController::class, 'recibo'], true, $finanzasRoles);
        $this->post('/app/caja/anular/{id}', [CajaController::class, 'anular'], true, ['superadmin']);

        // Módulo de Hospitalización y Triage
        $hospViewRoles   = ['superadmin', 'veterinario', 'guardia', 'emergencia', 'recepcionista'];
        $hospClinicRoles = ['superadmin', 'veterinario', 'guardia', 'emergencia'];

        $this->get('/app/hospitalizacion', [HospitalizacionController::class, 'index'], true, $hospViewRoles);
        $this->get('/app/emergencias', [HospitalizacionController::class, 'index'], true, $hospViewRoles);
        $this->get('/app/bitacora', [HospitalizacionController::class, 'index'], true, $hospViewRoles);
        $this->post('/app/hospitalizacion/triage', [HospitalizacionController::class, 'triage'], true, $hospClinicRoles);
        $this->get('/app/hospitalizacion/paciente/{id}', [HospitalizacionController::class, 'ver'], true, $hospViewRoles);
        $this->post('/app/hospitalizacion/{id}/bitacora', [HospitalizacionController::class, 'registrarBitacora'], true, $hospClinicRoles);
        $this->post('/app/hospitalizacion/{id}/alta', [HospitalizacionController::class, 'alta'], true, $hospClinicRoles);
        $this->post('/app/hospitalizacion/jaula/limpiar', [HospitalizacionController::class, 'limpiarJaula'], true, $hospViewRoles);

        // Módulo de Agenda Interactiva (FullCalendar)
        $agendaReadRoles   = ['superadmin', 'recepcionista', 'veterinario', 'guardia', 'groomer', 'propietario'];
        $agendaManageRoles = ['superadmin', 'recepcionista', 'veterinario'];

        $this->get('/app/agenda', [AgendaController::class, 'index'], true, $agendaReadRoles);
        $this->get('/app/portal/citas', [AgendaController::class, 'index'], true, $agendaReadRoles);
        $this->get('/app/agenda/eventos', [AgendaController::class, 'eventos'], true, $agendaReadRoles);
        $this->post('/app/agenda/crear', [AgendaController::class, 'crear'], true, $agendaManageRoles);
        $this->post('/app/agenda/{id}/estado', [AgendaController::class, 'cambiarEstado'], true, ['superadmin', 'recepcionista', 'veterinario', 'groomer']);

        // Módulo de Estética y Alertas Internas
        $esteticaRoles = ['groomer', 'superadmin'];
        $alertaReaders = ['superadmin', 'veterinario', 'guardia', 'emergencia'];

        $this->get('/app/estetica', [EsteticaController::class, 'dashboard'], true, $esteticaRoles);
        $this->get('/app/grooming/dashboard', [EsteticaController::class, 'dashboard'], true, $esteticaRoles);
        $this->post('/app/estetica/{id}/estado', [EsteticaController::class, 'actualizarEstado'], true, $esteticaRoles);
        $this->post('/app/estetica/alerta', [EsteticaController::class, 'emitirAlerta'], true, $esteticaRoles);
        $this->post('/app/alertas/{id}/leer', [EsteticaController::class, 'marcarAlertaLeida'], true, $alertaReaders);

        return $this;
    }

    // ─── Dispatch ──────────────────────────────────────────────────────────

    /**
     * Encuentra y ejecuta el handler que coincide con la solicitud actual.
     */
    public function dispatch(): void
    {
        Config::initialize();

        $method = $this->resolveMethod();
        $uri    = $this->getUri();

        foreach ($this->routes[$method] ?? [] as $routeData) {
            $params = $this->matchPattern($routeData['pattern'], $uri);
            if ($params === null) {
                continue;
            }

            // Verificar autenticación si la ruta está protegida
            if ($routeData['protected']) {
                if (!$this->isAuthenticated()) {
                    $this->handleUnauthenticated();
                    return;
                }

                // Verificar roles permitidos si la ruta define ACL
                if (!empty($routeData['roles']) && !$this->hasAnyRole($routeData['roles'])) {
                    $this->handleForbidden();
                    return;
                }
            }

            $this->callHandler($routeData['handler'], $params);
            return;
        }

        $this->send404();
    }

    // ─── Helpers internos ──────────────────────────────────────────────────

    private function addRoute(
        string $method,
        string $path,
        callable|array $handler,
        bool $protected,
        array $roles
    ): void {
        $fullPath = $this->normalizePath($this->prefix . '/' . ltrim($path, '/'));
        $isProtected = $protected || $this->groupProtected || !empty($roles) || !empty($this->groupRoles);
        $allowedRoles = !empty($roles) ? $roles : $this->groupRoles;

        $this->routes[$method][$fullPath] = [
            'pattern'   => $this->pathToPattern($fullPath),
            'handler'   => $handler,
            'protected' => $isProtected,
            'roles'     => $allowedRoles,
        ];
    }

    private function normalizePath(string $path): string
    {
        $normalized = '/' . trim($path, '/');
        return $normalized === '/' ? '/' : rtrim($normalized, '/');
    }

    /**
     * Convierte un path con {params} en un patrón regex seguro.
     * Ej: '/app/paciente/{id}' -> '#^/app/paciente/(?P<id>[^/]+)$#'
     */
    private function pathToPattern(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }

    /**
     * Intenta hacer match del URI contra un patrón regex compilado.
     *
     * @return array<string, string>|null
     */
    private function matchPattern(string $pattern, string $uri): ?array
    {
        if (preg_match($pattern, $uri, $matches)) {
            return array_filter($matches, static fn($k) => !is_int($k), ARRAY_FILTER_USE_KEY);
        }

        return null;
    }

    /**
     * Resuelve el método HTTP considerando override (_method) en formularios POST.
     */
    private function resolveMethod(): string
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper(trim((string) $_POST['_method']));
            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                return $override;
            }
        }

        return $method;
    }

    /**
     * Extrae la URI limpia sin query string.
     */
    private function getUri(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri = rawurldecode($uri ?? '/');
        return $this->normalizePath($uri);
    }

    /**
     * Invoca el handler (closure o [Clase, 'metodo']).
     */
    private function callHandler(callable|array $handler, array $params): void
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $controller = new $class();
            $controller->$method(...array_values($params));
            return;
        }

        $handler(...array_values($params));
    }

    /**
     * Verifica si hay una sesión activa y no expirada por inactividad.
     */
    private function isAuthenticated(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            return false;
        }

        $lifetime     = Config::getSessionLifetime();
        $lastActivity = (int) ($_SESSION['last_activity'] ?? $_SESSION['logged_at'] ?? 0);

        if ($lastActivity > 0 && (time() - $lastActivity) > $lifetime) {
            $_SESSION = [];
            session_destroy();
            return false;
        }

        $_SESSION['last_activity'] = time();
        return true;
    }

    /**
     * Verifica si el usuario autenticado posee alguno de los roles permitidos.
     *
     * @param string[] $roles
     */
    private function hasAnyRole(array $roles): bool
    {
        $currentRole = $_SESSION['user']['rol_slug'] ?? '';
        if ($currentRole === 'superadmin') {
            return true;
        }
        return in_array($currentRole, $roles, true);
    }

    /**
     * Determina si la petición espera una respuesta JSON (AJAX/Fetch).
     */
    private function expectsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $xhr    = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return str_contains($accept, 'application/json') || strtolower($xhr) === 'xmlhttprequest';
    }

    /**
     * Maneja petición no autenticada: JSON 401 o redirección al login.
     */
    private function handleUnauthenticated(): void
    {
        if ($this->expectsJson()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Sesión no iniciada o expirada.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '/app/dashboard';
        header('Location: /app/login');
        exit;
    }

    /**
     * Maneja acceso denegado por rol insuficiente: JSON 403 o redirección.
     */
    private function handleForbidden(): void
    {
        http_response_code(403);

        if ($this->expectsJson()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'No tienes permisos para realizar esta acción.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $view403 = __DIR__ . '/../Views/errors/403.php';
        if (file_exists($view403)) {
            require $view403;
        } else {
            echo '<!DOCTYPE html><html lang="es"><body><h1>403 — Acceso Denegado</h1>'
               . '<p>No tienes permisos para acceder a este módulo.</p>'
               . '<p><a href="/app/dashboard">Volver</a></p></body></html>';
        }
        exit;
    }

    /**
     * Envía respuesta 404.
     */
    private function send404(): void
    {
        http_response_code(404);

        if ($this->expectsJson()) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Ruta no encontrada.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $view404 = __DIR__ . '/../Views/errors/404.php';
        if (file_exists($view404)) {
            require $view404;
        } else {
            echo '<!DOCTYPE html><html lang="es"><body><h1>404 — Página no encontrada</h1>'
               . '<p><a href="/">Volver al inicio</a></p></body></html>';
        }
        exit;
    }
}

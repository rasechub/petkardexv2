<?php
/**
 * PetKardex v2 - Clase Auth
 *
 * Maneja autenticación segura, sesiones con expiración por inactividad,
 * control de roles (ACL), permisos JSON y recuperación de contraseña.
 */

declare(strict_types=1);

namespace PetKardex\Core;

use PetKardex\Config\Config;
use PetKardex\Config\Database;
use PetKardex\Helpers\Security;

class Auth
{
    private Database $db;

    public function __construct()
    {
        Config::initialize();
        $this->db = Database::getInstance();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    // ─── Autenticación ──────────────────────────────────────────────────────

    /**
     * Autentica un usuario con email y contraseña usando consultas preparadas PDO.
     *
     * @return array{success: bool, error?: string, user?: array}
     */
    public function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));

        if ($email === '' || $password === '') {
            return ['success' => false, 'error' => 'Credenciales requeridas.'];
        }

        $user = $this->db->fetchOne(
            "SELECT u.*, r.slug AS rol_slug, r.nombre AS rol_nombre, r.permisos AS rol_permisos
             FROM usuarios u
             INNER JOIN roles r ON r.id = u.rol_id
             WHERE u.email = :email AND u.activo = 1 AND r.activo = 1
             LIMIT 1",
            [':email' => $email]
        );

        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'error' => 'Credenciales incorrectas.'];
        }

        // Rehash transparente si el algoritmo o costo cambió
        if (Security::needsRehash($user['password_hash'])) {
            $newHash = Security::hashPassword($password);
            $this->db->execute(
                "UPDATE usuarios SET password_hash = :hash WHERE id = :id",
                [':hash' => $newHash, ':id' => $user['id']]
            );
        }

        // Actualizar último login
        $this->db->execute(
            "UPDATE usuarios SET ultimo_login = NOW() WHERE id = :id",
            [':id' => $user['id']]
        );

        // Regenerar ID de sesión para prevenir session fixation
        session_regenerate_id(true);

        // Limpiar token CSRF antiguo y generar uno nuevo tras login
        unset($_SESSION['_csrf_token']);

        $permisos = json_decode($user['rol_permisos'] ?? '{}', true);
        if (!is_array($permisos)) {
            $permisos = [];
        }

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user']    = [
            'id'         => (int) $user['id'],
            'nombre'     => $user['nombre'],
            'apellido'   => $user['apellido'],
            'email'      => $user['email'],
            'foto'       => $user['foto'] ?? null,
            'rol_id'     => (int) $user['rol_id'],
            'rol_slug'   => $user['rol_slug'],
            'rol_nombre' => $user['rol_nombre'],
            'permisos'   => $permisos,
        ];
        $_SESSION['logged_at']     = time();
        $_SESSION['last_activity'] = time();

        $this->logAudit((int) $user['id'], 'login', 'usuarios', (int) $user['id']);

        return ['success' => true, 'user' => $_SESSION['user']];
    }

    /**
     * Cierra la sesión del usuario actual de forma segura.
     */
    public function logout(): void
    {
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        if ($userId !== null) {
            $this->logAudit($userId, 'logout', 'usuarios', $userId);
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    // ─── Estado de autenticación ────────────────────────────────────────────

    /**
     * Retorna true si hay un usuario autenticado y la sesión no ha expirado.
     */
    public function isAuthenticated(): bool
    {
        if (empty($_SESSION['user_id'])) {
            return false;
        }

        $lifetime     = Config::getSessionLifetime();
        $lastActivity = (int) ($_SESSION['last_activity'] ?? $_SESSION['logged_at'] ?? 0);

        if ($lastActivity > 0 && (time() - $lastActivity) > $lifetime) {
            $this->logout();
            return false;
        }

        $_SESSION['last_activity'] = time();
        return true;
    }

    /**
     * Retorna los datos del usuario actualmente autenticado (refrescados desde BD), o null.
     */
    public function getCurrentUser(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        $user = $this->db->fetchOne(
            "SELECT u.id, u.nombre, u.apellido, u.email, u.foto,
                    u.rol_id, u.activo,
                    r.slug AS rol_slug, r.nombre AS rol_nombre, r.permisos AS rol_permisos
             FROM usuarios u
             INNER JOIN roles r ON r.id = u.rol_id
             WHERE u.id = :id AND u.activo = 1 AND r.activo = 1
             LIMIT 1",
            [':id' => (int) $_SESSION['user_id']]
        );

        if (!$user) {
            $this->logout();
            return null;
        }

        $permisos = json_decode($user['rol_permisos'] ?? '{}', true);
        if (!is_array($permisos)) {
            $permisos = [];
        }

        $userData = [
            'id'         => (int) $user['id'],
            'nombre'     => $user['nombre'],
            'apellido'   => $user['apellido'],
            'email'      => $user['email'],
            'foto'       => $user['foto'] ?? null,
            'rol_id'     => (int) $user['rol_id'],
            'rol_slug'   => $user['rol_slug'],
            'rol_nombre' => $user['rol_nombre'],
            'permisos'   => $permisos,
        ];

        $_SESSION['user'] = $userData;

        return $userData;
    }

    // ─── Roles y permisos ───────────────────────────────────────────────────

    /**
     * Verifica si el usuario tiene alguno de los roles indicados.
     *
     * @param string|string[] $role  Slug(s) de rol
     */
    public function hasRole(string|array $role): bool
    {
        if (!$this->isAuthenticated()) {
            return false;
        }
        $roles   = (array) $role;
        $current = $_SESSION['user']['rol_slug'] ?? '';
        return in_array($current, $roles, true);
    }

    /**
     * Verifica si el usuario tiene un permiso específico.
     * Soporta el formato de seeds.sql (['pacientes' => ['ver', 'crear']])
     * y formato asociativo (['pacientes' => ['crear' => true]]).
     *
     * @param string $permission  Ej: 'pacientes.crear' o 'dashboard'
     */
    public function hasPermission(string $permission): bool
    {
        if (!$this->isAuthenticated()) {
            return false;
        }

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
            return !empty($permisos[$parts[0]]);
        }

        if (count($parts) === 2) {
            [$module, $action] = $parts;
            if (!isset($permisos[$module])) {
                return false;
            }
            $modulePerms = $permisos[$module];
            if (is_array($modulePerms)) {
                if (array_is_list($modulePerms)) {
                    return in_array($action, $modulePerms, true) || in_array('*', $modulePerms, true);
                }
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

    // ─── Restablecimiento de contraseña ────────────────────────────────────

    /**
     * Genera un token de restablecimiento de contraseña y lo almacena en BD.
     *
     * @return array{success: bool, token?: string, user?: array, error?: string}
     */
    public function generatePasswordResetToken(string $email): array
    {
        $email = strtolower(trim($email));

        $user = $this->db->fetchOne(
            "SELECT id, nombre, email FROM usuarios WHERE email = :email AND activo = 1 LIMIT 1",
            [':email' => $email]
        );

        if (!$user) {
            // No revelamos si el email existe (prevención de enumeración de usuarios)
            return ['success' => true];
        }

        $token  = bin2hex(random_bytes(32));
        $ttl    = max(300, (int) Config::env('PASSWORD_RESET_TOKEN_TTL', '3600'));
        $expiry = date('Y-m-d H:i:s', time() + $ttl);

        $this->db->execute(
            "UPDATE usuarios SET token_reset = :token, token_expiry = :expiry WHERE id = :id",
            [':token' => $token, ':expiry' => $expiry, ':id' => $user['id']]
        );

        return ['success' => true, 'token' => $token, 'user' => $user];
    }

    /**
     * Valida el token y actualiza la contraseña del usuario.
     *
     * @return array{success: bool, error?: string}
     */
    public function resetPassword(string $token, string $newPassword): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/i', $token)) {
            return ['success' => false, 'error' => 'Token inválido.'];
        }

        $passwordErrors = Security::validatePassword($newPassword);
        if (!empty($passwordErrors)) {
            return ['success' => false, 'error' => implode(' ', $passwordErrors)];
        }

        $user = $this->db->fetchOne(
            "SELECT id, token_expiry FROM usuarios
             WHERE token_reset = :token AND activo = 1 LIMIT 1",
            [':token' => $token]
        );

        if (!$user || empty($user['token_expiry'])) {
            return ['success' => false, 'error' => 'Token inválido o expirado.'];
        }

        if (strtotime((string) $user['token_expiry']) < time()) {
            return ['success' => false, 'error' => 'El enlace de recuperación ha expirado.'];
        }

        $hash = Security::hashPassword($newPassword);

        $this->db->execute(
            "UPDATE usuarios
             SET password_hash = :hash, token_reset = NULL, token_expiry = NULL, updated_at = NOW()
             WHERE id = :id",
            [':hash' => $hash, ':id' => $user['id']]
        );

        $this->logAudit((int) $user['id'], 'password_reset', 'usuarios', (int) $user['id']);

        return ['success' => true];
    }

    // ─── Registro (Administración de usuarios) ──────────────────────────────

    /**
     * Crea un nuevo usuario en la BD (para uso de SuperAdmin o CLI setup).
     *
     * @param  array $data Debe contener: nombre, apellido, email, password, rol_id
     * @return array{success: bool, id?: int, error?: string}
     */
    public function register(array $data): array
    {
        $required = ['nombre', 'apellido', 'email', 'password', 'rol_id'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'error' => "El campo '{$field}' es requerido."];
            }
        }

        $email = strtolower(trim((string) $data['email']));
        if (!Security::validateEmail($email)) {
            return ['success' => false, 'error' => 'El formato del email no es válido.'];
        }

        $passwordErrors = Security::validatePassword((string) $data['password']);
        if (!empty($passwordErrors)) {
            return ['success' => false, 'error' => implode(' ', $passwordErrors)];
        }

        $exists = $this->db->fetchOne(
            "SELECT id FROM usuarios WHERE email = :email LIMIT 1",
            [':email' => $email]
        );

        if ($exists) {
            return ['success' => false, 'error' => 'El email ya está registrado.'];
        }

        $hash = Security::hashPassword((string) $data['password']);

        $this->db->execute(
            "INSERT INTO usuarios (nombre, apellido, email, password_hash, rol_id, telefono, activo, created_at, updated_at)
             VALUES (:nombre, :apellido, :email, :hash, :rol_id, :telefono, 1, NOW(), NOW())",
            [
                ':nombre'   => trim((string) $data['nombre']),
                ':apellido' => trim((string) $data['apellido']),
                ':email'    => $email,
                ':hash'     => $hash,
                ':rol_id'   => (int) $data['rol_id'],
                ':telefono' => isset($data['telefono']) ? trim((string) $data['telefono']) : null,
            ]
        );

        $newId = $this->db->lastInsertId();

        return ['success' => true, 'id' => $newId];
    }

    // ─── Auditoría ──────────────────────────────────────────────────────────

    private function logAudit(?int $userId, string $accion, string $tabla, ?int $registroId): void
    {
        try {
            $this->db->execute(
                "INSERT INTO audit_log (usuario_id, accion, tabla_afectada, registro_id, ip, created_at)
                 VALUES (:uid, :accion, :tabla, :rid, :ip, NOW())",
                [
                    ':uid'    => $userId,
                    ':accion' => $accion,
                    ':tabla'  => $tabla,
                    ':rid'    => $registroId,
                    ':ip'     => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                ]
            );
        } catch (\Throwable) {
            // La auditoría no debe interrumpir el flujo si ocurre un fallo transitorio
        }
    }
}

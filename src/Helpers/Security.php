<?php
/**
 * PetKardex v2 - Security Helper
 *
 * Funciones de seguridad: sanitización, validación, tokens, hashing y rate limiting.
 */

declare(strict_types=1);

namespace PetKardex\Helpers;

class Security
{
    // ─── Sanitización ─────────────────────────────────────────────────────

    /**
     * Limpia un valor de entrada eliminando tags HTML y codificando caracteres especiales.
     *
     * @param  string|array $input  Valor o array de valores a limpiar
     * @return string|array
     */
    public static function sanitizeInput(string|array $input): string|array
    {
        if (is_array($input)) {
            return array_map([self::class, 'sanitizeInput'], $input);
        }

        $input = strip_tags($input);
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Sanitiza para uso en HTML sin codificar para formularios (devuelve valor real).
     * Útil para repoblar inputs de formulario con seguridad.
     */
    public static function sanitizeValue(string $input): string
    {
        return htmlspecialchars(trim($input), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    // ─── Validación ───────────────────────────────────────────────────────

    /**
     * Valida si el email tiene un formato válido.
     *
     * @param string $email
     * @return bool
     */
    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Valida longitud mínima y máxima de un string.
     */
    public static function validateLength(string $value, int $min = 1, int $max = 255): bool
    {
        $len = mb_strlen(trim($value), 'UTF-8');
        return $len >= $min && $len <= $max;
    }

    /**
     * Valida que el string sólo contenga caracteres alfanuméricos, guiones y guiones bajos.
     */
    public static function validateSlug(string $value): bool
    {
        return (bool) preg_match('/^[a-z0-9\-_]+$/', $value);
    }

    /**
     * Valida un número de teléfono (formato básico internacional).
     */
    public static function validatePhone(string $phone): bool
    {
        $cleaned = preg_replace('/[\s\-\(\)\+]/', '', $phone);
        return (bool) preg_match('/^\d{7,15}$/', $cleaned ?? '');
    }

    /**
     * Valida que la contraseña cumpla las políticas mínimas.
     * Mínimo 8 caracteres, al menos una letra y un número.
     */
    public static function validatePassword(string $password): array
    {
        $errors = [];

        if (strlen($password) < 8) {
            $errors[] = 'Debe tener al menos 8 caracteres.';
        }
        if (!preg_match('/[A-Za-z]/', $password)) {
            $errors[] = 'Debe contener al menos una letra.';
        }
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Debe contener al menos un número.';
        }

        return $errors;
    }

    // ─── Generación de tokens ─────────────────────────────────────────────

    /**
     * Genera un token criptográficamente seguro.
     *
     * @param  int $length  Número de bytes aleatorios (el token tendrá el doble de caracteres hex)
     * @return string       Token hexadecimal
     */
    public static function generateToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }

    /**
     * Genera un token numérico de N dígitos (útil para códigos OTP).
     */
    public static function generateNumericToken(int $digits = 6): string
    {
        $max = (int) str_pad('1', $digits + 1, '0');
        return str_pad((string) random_int(0, $max - 1), $digits, '0', STR_PAD_LEFT);
    }

    // ─── Hashing de contraseñas ───────────────────────────────────────────

    /**
     * Hashea una contraseña con bcrypt.
     *
     * @param  string $password  Contraseña en texto plano
     * @param  int    $cost      Costo bcrypt (10-14 recomendado)
     * @return string            Hash bcrypt
     */
    public static function hashPassword(string $password, int $cost = 12): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => $cost]);
    }

    /**
     * Verifica una contraseña contra su hash.
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Comprueba si el hash necesita ser rehashado (ej: cambio de costo).
     */
    public static function needsRehash(string $hash, int $cost = 12): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => $cost]);
    }

    // ─── Rate Limiting ────────────────────────────────────────────────────

    /**
     * Implementa rate limiting usando archivos temporales.
     *
     * Retorna true si el intento está permitido, false si se superó el límite.
     * Cada llamada que retorna true consume un slot.
     *
     * @param  string $key         Identificador único (ej: 'login_192.168.1.1')
     * @param  int    $maxAttempts Número máximo de intentos permitidos
     * @param  int    $window      Ventana de tiempo en segundos
     * @return bool                true = permitido, false = bloqueado
     */
    public static function rateLimit(string $key, int $maxAttempts = 5, int $window = 60): bool
    {
        $safeKey  = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
        $file     = sys_get_temp_dir() . '/pkdx_rl_' . $safeKey . '.json';

        $now      = time();
        $attempts = [];

        // Leer intentos anteriores
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file) ?: '[]', true);
            if (is_array($data)) {
                // Filtrar intentos dentro de la ventana
                $attempts = array_filter($data, fn($t) => ($now - $t) < $window);
                $attempts = array_values($attempts);
            }
        }

        // ¿Supera el límite?
        if (count($attempts) >= $maxAttempts) {
            return false;
        }

        // Registrar este intento
        $attempts[] = $now;
        file_put_contents($file, json_encode($attempts), LOCK_EX);

        return true;
    }

    /**
     * Reinicia el rate limit de una clave (ej: tras login exitoso).
     *
     * @param string $key
     */
    public static function resetRateLimit(string $key): void
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
        $file    = sys_get_temp_dir() . '/pkdx_rl_' . $safeKey . '.json';

        if (file_exists($file)) {
            unlink($file);
        }
    }

    // ─── XSS / CSRF ───────────────────────────────────────────────────────

    /**
     * Escapa un valor para mostrarlo de forma segura en HTML.
     */
    public static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Genera un token CSRF y lo almacena en sesión.
     */
    public static function csrfToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = self::generateToken(32);
        }
        return $_SESSION['_csrf_token'];
    }

    /**
     * Valida el token CSRF enviado en el POST.
     */
    public static function validateCsrf(string $token): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $sessionToken = $_SESSION['_csrf_token'] ?? '';
        return hash_equals($sessionToken, $token);
    }

    // ─── Encabezados de seguridad ─────────────────────────────────────────

    /**
     * Envía encabezados HTTP de seguridad recomendados.
     */
    public static function sendSecurityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header("Content-Security-Policy: default-src 'self'; "
             . "script-src 'self' 'unsafe-inline' cdn.jsdelivr.net cdnjs.cloudflare.com unpkg.com; "
             . "style-src 'self' 'unsafe-inline' cdn.jsdelivr.net cdnjs.cloudflare.com fonts.googleapis.com; "
             . "font-src 'self' fonts.gstatic.com cdn.jsdelivr.net cdnjs.cloudflare.com; "
             . "img-src 'self' data: blob: https://api.qrserver.com; "
             . "connect-src 'self' https://api.qrserver.com;");
    }
}

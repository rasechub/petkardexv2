<?php
/**
 * PetKardex v2 - Configuración principal del sistema
 * ERP Veterinario
 *
 * @author    CESAR AQUINO ANGLAS - VI B NOCHE
 * @copyright 2026 PetKardex. Todos los derechos reservados.
 * @license   Propietaria - IESTP Santiago Antúnez de Mayolo (IESTP SAM)
 */

declare(strict_types=1);

namespace PetKardex\Config;

class Config
{
    // ─── Configuración de la aplicación y autoría ─────────────────────────
    public const APP_NAME        = 'PetKardex';
    public const APP_VERSION     = '2.0.0';
    public const APP_AUTHOR      = 'CESAR AQUINO ANGLAS - VI B NOCHE';
    public const APP_INSTITUTION = 'IESTP Santiago Antúnez de Mayolo (IESTP SAM)';
    public const APP_COPYRIGHT   = '© 2026 PetKardex | Desarrollado por CESAR AQUINO ANGLAS - VI B NOCHE';
    public const APP_LICENSE     = 'Todos los derechos reservados (All Rights Reserved)';

    // ─── Rutas de archivos y uploads ──────────────────────────────────────
    public const ROOT_PATH   = __DIR__ . '/../..';
    public const UPLOAD_PATH = __DIR__ . '/../../public/uploads/';
    public const QR_PATH     = __DIR__ . '/../../public/uploads/qr/';

    // ─── Zona horaria por defecto ─────────────────────────────────────────
    public const TIMEZONE = 'America/Lima';

    // ─── Entornos ─────────────────────────────────────────────────────────
    public const ENV_PRODUCTION  = 'production';
    public const ENV_DEVELOPMENT = 'development';

    // ─── Configuración de sesión por defecto ──────────────────────────────
    public const SESSION_NAME     = 'PKDX_SESSION';
    public const SESSION_LIFETIME = 7200;   // segundos (2 horas)
    public const SESSION_PATH     = '/';
    public const SESSION_SECURE   = false;  // true en HTTPS
    public const SESSION_HTTPONLY = true;
    public const SESSION_SAMESITE = 'Strict';

    private static bool $envLoaded   = false;
    private static bool $initialized = false;

    /**
     * Carga variables desde el archivo .env en la raíz del proyecto (si existe)
     * sin sobreescribir variables ya definidas en el entorno del contenedor.
     */
    public static function loadEnv(?string $envPath = null): void
    {
        if (self::$envLoaded) {
            return;
        }
        self::$envLoaded = true;

        $file = $envPath ?? (self::ROOT_PATH . '/.env');
        if (!is_file($file) || !is_readable($file)) {
            return;
        }

        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $eqPos = strpos($trimmed, '=');
            if ($eqPos === false) {
                continue;
            }

            $key   = trim(substr($trimmed, 0, $eqPos));
            $value = trim(substr($trimmed, $eqPos + 1));

            // Quitar comillas envolventes simples o dobles
            if (
                strlen($value) >= 2 &&
                (($value[0] === '"' && $value[-1] === '"') || ($value[0] === "'" && $value[-1] === "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            if ($key !== '' && getenv($key) === false && !isset($_ENV[$key])) {
                putenv("{$key}={$value}");
                $_ENV[$key]    = $value;
                $_SERVER[$key] = $value;
            }
        }
    }

    /**
     * Obtiene una variable de entorno probando una o más claves en orden.
     */
    public static function env(string|array $keys, string $default = ''): string
    {
        self::loadEnv();

        foreach ((array) $keys as $key) {
            $val = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
            if ($val !== false && $val !== null && $val !== '') {
                return (string) $val;
            }
        }

        return $default;
    }

    /**
     * Inicializa el entorno del sistema (zona horaria, manejo de errores, sesión segura).
     */
    public static function initialize(): void
    {
        if (self::$initialized) {
            return;
        }
        self::$initialized = true;

        self::loadEnv();

        // Codificación UTF-8 y zona horaria
        ini_set('default_charset', 'UTF-8');
        if (function_exists('mb_internal_encoding')) {
            mb_internal_encoding('UTF-8');
        }
        date_default_timezone_set(self::getTimezone());

        // Configuración de sesión segura (sólo antes de iniciar sesión)
        if (session_status() === PHP_SESSION_NONE) {
            $lifetime = self::getSessionLifetime();
            $isHttps  = self::isSessionSecure();

            ini_set('session.name',             self::SESSION_NAME);
            ini_set('session.gc_maxlifetime',   (string) $lifetime);
            ini_set('session.cookie_lifetime',  '0'); // Cookie de sesión hasta cerrar navegador; expiración controlada por inactividad
            ini_set('session.cookie_path',      self::SESSION_PATH);
            ini_set('session.cookie_httponly',  self::SESSION_HTTPONLY ? '1' : '0');
            ini_set('session.cookie_samesite',  self::SESSION_SAMESITE);
            ini_set('session.use_strict_mode',  '1');
            ini_set('session.use_only_cookies', '1');
            ini_set('session.cookie_secure',    $isHttps ? '1' : '0');
        }

        // Reporte de errores según entorno
        if (self::getEnv() === self::ENV_DEVELOPMENT) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(E_ALL & ~E_DEPRECATED);
            ini_set('display_errors', '0');
            ini_set('log_errors', '1');
        }

        // Crear directorios de uploads si no existen
        foreach ([self::UPLOAD_PATH, self::QR_PATH] as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
        }
    }

    // ─── Base de datos ─────────────────────────────────────────────────────

    public static function getDbHost(): string
    {
        return self::env('DB_HOST', 'db');
    }

    public static function getDbPort(): string
    {
        return self::env('DB_PORT', '3306');
    }

    public static function getDbName(): string
    {
        return self::env(['DB_NAME', 'DB_DATABASE'], 'petkardex');
    }

    public static function getDbUser(): string
    {
        return self::env(['DB_USER', 'DB_USERNAME'], 'petkardex_user');
    }

    public static function getDbPassword(): string
    {
        return self::env('DB_PASSWORD', '');
    }

    public static function getDbCharset(): string
    {
        return self::env('DB_CHARSET', 'utf8mb4');
    }

    // ─── Correo (SMTP) ─────────────────────────────────────────────────────

    public static function getSmtpHost(): string
    {
        return self::env(['SMTP_HOST', 'MAIL_HOST'], 'mailhog');
    }

    public static function getSmtpPort(): int
    {
        return (int) self::env(['SMTP_PORT', 'MAIL_PORT'], '1025');
    }

    public static function getSmtpTls(): bool
    {
        return filter_var(self::env('SMTP_TLS', 'false'), FILTER_VALIDATE_BOOLEAN);
    }

    public static function getSmtpUser(): string
    {
        return self::env(['SMTP_USER', 'MAIL_USERNAME'], '');
    }

    public static function getSmtpPassword(): string
    {
        return self::env(['SMTP_PASSWORD', 'MAIL_PASSWORD'], '');
    }

    public static function getSmtpFrom(): string
    {
        return self::env(['SMTP_FROM', 'MAIL_FROM'], 'noreply@petkardex.local');
    }

    public static function getSmtpFromName(): string
    {
        return self::env(['SMTP_FROM_NAME', 'MAIL_FROM_NAME'], self::APP_NAME);
    }

    // ─── Aplicación y Seguridad ────────────────────────────────────────────

    public static function getEnv(): string
    {
        return self::env('APP_ENV', self::ENV_PRODUCTION);
    }

    public static function isProduction(): bool
    {
        return self::getEnv() === self::ENV_PRODUCTION;
    }

    public static function getTimezone(): string
    {
        return self::env('TIMEZONE', self::TIMEZONE);
    }

    public static function getSessionLifetime(): int
    {
        $val = (int) self::env('SESSION_LIFETIME', (string) self::SESSION_LIFETIME);
        return $val > 0 ? $val : self::SESSION_LIFETIME;
    }

    public static function isSessionSecure(): bool
    {
        $envSecure = filter_var(self::env('SESSION_SECURE', 'false'), FILTER_VALIDATE_BOOLEAN);
        $httpsOn   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (($_SERVER['HTTP_X_FORWARDED_SSL'] ?? '') === 'on')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443');

        return self::SESSION_SECURE || $envSecure || $httpsOn;
    }

    public static function getBaseUrl(): string
    {
        // 1. Si la petición web llega a través de un proxy/túnel (como ngrok, Cloudflare Tunnel, etc.)
        $forwardedProto = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        $forwardedHost  = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? '';

        if (!empty($forwardedHost)) {
            $scheme = !empty($forwardedProto) ? strtolower($forwardedProto) : 'https';
            return $scheme . '://' . $forwardedHost;
        }

        // 2. Si hay cabecera HTTP_HOST en la petición web actual
        if (!empty($_SERVER['HTTP_HOST'])) {
            $scheme = self::isSessionSecure() ? 'https' : 'http';
            return $scheme . '://' . $_SERVER['HTTP_HOST'];
        }

        // 3. Fallback a la variable de entorno APP_URL si se ejecuta desde CLI / cron
        $envUrl = self::env('APP_URL', '');
        if ($envUrl !== '') {
            return rtrim($envUrl, '/');
        }

        return 'http://localhost:8080';
    }

    public static function getAppKey(): string
    {
        $key = self::env('APP_KEY', '');
        if ($key === '' && self::isProduction()) {
            throw new \RuntimeException('APP_KEY no configurada en entorno de producción.');
        }
        return $key;
    }

    public static function getMaxLoginAttempts(): int
    {
        return max(1, (int) self::env('MAX_LOGIN_ATTEMPTS', '5'));
    }

    public static function getLoginLockoutSeconds(): int
    {
        return max(60, (int) self::env('LOGIN_LOCKOUT_SECONDS', '900'));
    }
}

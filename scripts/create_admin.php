#!/usr/bin/env php
<?php
/**
 * PetKardex v2 - Script CLI de Instalación Segura
 *
 * Crea el primer usuario Administrador del Sistema (SuperAdmin) desde consola
 * usando hash bcrypt y consultas preparadas PDO.
 *
 * @author    CESAR AQUINO ANGLAS - VI B NOCHE
 * @copyright 2026 PetKardex. Todos los derechos reservados.
 * @license   Propietaria - IESTP Santiago Antúnez de Mayolo (IESTP SAM)
 *
 * Uso interactivo (recomendado dentro del contenedor PHP):
 *   docker exec -it petkardex_app php scripts/create_admin.php
 *
 * Uso con argumentos opcionales:
 *   php scripts/create_admin.php --nombre="..." --apellido="..." --email="..." --password="..."
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Acceso denegado: este script solo puede ejecutarse desde la línea de comandos (CLI).\n");
}

$rootDir = dirname(__DIR__);

// Autoloader PSR-4 (Composer si existe + fallback PetKardex\)
$composerAutoload = $rootDir . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

spl_autoload_register(static function (string $class) use ($rootDir): void {
    $prefix = 'PetKardex\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativePath = str_replace('\\', '/', substr($class, strlen($prefix)));
    $candidates   = [
        $rootDir . '/src/' . $relativePath . '.php',
        $rootDir . '/src/' . dirname($relativePath) . '/' . strtolower(basename($relativePath)) . '.php',
    ];

    foreach ($candidates as $file) {
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

use PetKardex\Config\Config;
use PetKardex\Config\Database;
use PetKardex\Helpers\Security;

Config::loadEnv($rootDir . '/.env');
date_default_timezone_set(Config::getTimezone());

/**
 * Lee una línea de texto desde consola.
 */
function promptLine(string $label): string
{
    if (function_exists('readline')) {
        $val = readline($label);
        return $val === false ? '' : trim($val);
    }

    fwrite(STDOUT, $label);
    $val = fgets(STDIN);
    return $val === false ? '' : trim($val);
}

/**
 * Lee una contraseña ocultando la entrada en terminal Unix/Linux (contenedor Docker).
 */
function promptSecret(string $label): string
{
    fwrite(STDOUT, $label);

    if (DIRECTORY_SEPARATOR !== '\\' && function_exists('shell_exec')) {
        $sttyMode = trim((string) shell_exec('stty -g 2>/dev/null'));
        if ($sttyMode !== '') {
            shell_exec('stty -echo 2>/dev/null');
            $secret = fgets(STDIN);
            shell_exec('stty ' . escapeshellarg($sttyMode) . ' 2>/dev/null');
            fwrite(STDOUT, "\n");
            return $secret === false ? '' : trim($secret);
        }
    }

    $secret = fgets(STDIN);
    return $secret === false ? '' : trim($secret);
}

$options = getopt('', ['nombre:', 'apellido:', 'email:', 'password:']);

fwrite(STDOUT, "\n====================================================\n");
fwrite(STDOUT, "  PetKardex v2 — Creación de Usuario SuperAdmin\n");
fwrite(STDOUT, "====================================================\n\n");

try {
    $db = Database::getInstance();
} catch (\Throwable $e) {
    fwrite(STDERR, "[ERROR] No se pudo conectar a MySQL: {$e->getMessage()}\n");
    fwrite(STDERR, "Verifica que el contenedor de base de datos esté activo y saludable.\n");
    exit(1);
}

$rol = $db->fetchOne(
    "SELECT id, nombre FROM roles WHERE slug = 'superadmin' AND activo = 1 LIMIT 1"
);

if (!$rol) {
    fwrite(STDERR, "[ERROR] No se encontró el rol 'superadmin' en la tabla 'roles'.\n");
    fwrite(STDERR, "Asegúrate de que schema.sql y seeds.sql se hayan importado correctamente.\n");
    exit(1);
}

$nombre   = isset($options['nombre']) ? trim((string) $options['nombre']) : promptLine('Nombre: ');
$apellido = isset($options['apellido']) ? trim((string) $options['apellido']) : promptLine('Apellido: ');
$email    = isset($options['email']) ? strtolower(trim((string) $options['email'])) : strtolower(promptLine('Correo electrónico: '));

if ($nombre === '' || $apellido === '') {
    fwrite(STDERR, "[ERROR] El nombre y apellido son obligatorios.\n");
    exit(1);
}

if (!Security::validateEmail($email)) {
    fwrite(STDERR, "[ERROR] El formato del correo electrónico no es válido.\n");
    exit(1);
}

$existente = $db->fetchOne(
    "SELECT id FROM usuarios WHERE email = :email LIMIT 1",
    [':email' => $email]
);

if ($existente) {
    fwrite(STDERR, "[ERROR] Ya existe un usuario registrado con ese correo electrónico.\n");
    exit(1);
}

if (isset($options['password'])) {
    $password = (string) $options['password'];
} else {
    $password        = promptSecret('Contraseña (mín. 8 caracteres, letra y número): ');
    $passwordConfirm = promptSecret('Confirmar contraseña: ');

    if ($password !== $passwordConfirm) {
        fwrite(STDERR, "[ERROR] Las contraseñas ingresadas no coinciden.\n");
        exit(1);
    }
}

$passwordErrors = Security::validatePassword($password);
if (!empty($passwordErrors)) {
    fwrite(STDERR, "[ERROR] Contraseña insegura: " . implode(' ', $passwordErrors) . "\n");
    exit(1);
}

$passwordHash = Security::hashPassword($password, 12);

try {
    $db->beginTransaction();

    $db->execute(
        "INSERT INTO usuarios (rol_id, nombre, apellido, email, password_hash, activo, created_at, updated_at)
         VALUES (:rol_id, :nombre, :apellido, :email, :hash, 1, NOW(), NOW())",
        [
            ':rol_id'   => (int) $rol['id'],
            ':nombre'   => $nombre,
            ':apellido' => $apellido,
            ':email'    => $email,
            ':hash'     => $passwordHash,
        ]
    );

    $nuevoId = $db->lastInsertId();

    $db->execute(
        "INSERT INTO audit_log (usuario_id, accion, tabla_afectada, registro_id, datos_nuevos, ip, created_at)
         VALUES (:uid, 'cli_create_superadmin', 'usuarios', :rid, :datos, '127.0.0.1', NOW())",
        [
            ':uid'   => $nuevoId,
            ':rid'   => $nuevoId,
            ':datos' => json_encode(['email' => $email, 'rol' => 'superadmin'], JSON_UNESCAPED_UNICODE),
        ]
    );

    $db->commit();

    fwrite(STDOUT, "\n[OK] Usuario SuperAdmin creado exitosamente.\n");
    fwrite(STDOUT, "     ID      : {$nuevoId}\n");
    fwrite(STDOUT, "     Nombre  : {$nombre} {$apellido}\n");
    fwrite(STDOUT, "     Correo  : {$email}\n");
    fwrite(STDOUT, "     Rol     : {$rol['nombre']} (superadmin)\n");
    fwrite(STDOUT, "     Acceso  : /app/login\n\n");
} catch (\Throwable $e) {
    $db->rollback();
    fwrite(STDERR, "[ERROR] Fallo al guardar el administrador: {$e->getMessage()}\n");
    exit(1);
}


<?php
/**
 * PetKardex v2 - QRController
 *
 * Gestiona el ciclo completo del Código QR de cada paciente:
 * - Perfil Público de Emergencia (/qr/{uuid}) sin inicio de sesión
 * - Panel de Credencial QR para Propietarios (/app/qr)
 * - Descarga directa de imagen QR (/app/qr/descargar/{uuid})
 * - Regeneración segura de UUID (/app/qr/regenerar/{id})
 * - Escáner Interno para la Clínica (/app/qr/scanner) y resolución hacia Kardex Médico
 *
 * @author    CESAR AQUINO ANGLAS - VI B NOCHE
 * @copyright 2026 PetKardex. Todos los derechos reservados.
 * @license   Propietaria - IESTP Santiago Antúnez de Mayolo (IESTP SAM)
 */

declare(strict_types=1);

namespace PetKardex\Controllers;

use PetKardex\Config\Config;
use PetKardex\Config\Database;
use PetKardex\Core\Auth;
use PetKardex\Core\Controller;
use PetKardex\Helpers\QRGenerator;
use PetKardex\Helpers\Security;

class QRController extends Controller
{
    private Database $db;
    private Auth $auth;

    private const CLINIC_SCANNER_ROLES = [
        'veterinario',
        'guardia',
        'recepcionista',
        'emergencia',
        'superadmin',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->db   = Database::getInstance();
        $this->auth = new Auth();
    }

    // ─── Ruta Pública (Sin Login) ───────────────────────────────────────────

    /**
     * GET /qr/{uuid}
     * Perfil Público de Emergencia accesible al escanear el código QR de la mascota.
     */
    public function emergencyProfile(string $uuid = ''): void
    {
        $uuid = $this->sanitizeUuid($uuid);
        if ($uuid === '') {
            $this->renderQrNotFound();
            return;
        }

        $paciente = $this->db->fetchOne(
            "SELECT p.id, p.nombre, p.sexo, p.color, p.peso_kg, p.fecha_nacimiento,
                    p.foto, p.qr_code, p.alertas_medicas, p.sangre_tipo, p.esterilizado,
                    e.nombre AS especie_nombre,
                    r.nombre AS raza_nombre,
                    pr.nombre AS propietario_nombre,
                    pr.telefono AS propietario_telefono,
                    pr.celular AS propietario_celular
             FROM pacientes p
             INNER JOIN propietarios pr ON pr.id = p.propietario_id
             INNER JOIN especies e ON e.id = p.especie_id
             LEFT JOIN razas r ON r.id = p.raza_id
             WHERE p.qr_code = :uuid AND p.activo = 1 AND pr.activo = 1
             LIMIT 1",
            [':uuid' => $uuid]
        );

        if (!$paciente) {
            $this->renderQrNotFound();
            return;
        }

        // Registrar acceso en sesiones_qr sin bloquear la vista pública si falla
        $this->logQrScan((int) $paciente['id'], $uuid);

        // Obtener teléfono de emergencias de la clínica
        $clinicConfig = $this->db->fetchAll(
            "SELECT clave, valor FROM configuracion_sistema
             WHERE clave IN ('nombre_clinica', 'telefono_emergencias', 'telefono', 'direccion')"
        );
        $clinic = [];
        foreach ($clinicConfig as $row) {
            $clinic[$row['clave']] = $row['valor'];
        }

        // Preparar teléfonos limpios para tel: y wa.me/
        $rawPhone = $paciente['propietario_celular'] ?: ($paciente['propietario_telefono'] ?: '');
        $telHref  = $this->formatTelLink((string) $rawPhone);
        $waHref   = $this->formatWhatsAppLink(
            (string) $rawPhone,
            'Hola, acabo de escanear el código QR de ' . $paciente['nombre'] . ' desde PetKardex.'
        );

        $this->render('public/emergency_profile', [
            'title'    => $paciente['nombre'] . ' — Perfil de Emergencia QR | ' . Config::APP_NAME,
            'paciente' => $paciente,
            'clinic'   => $clinic,
            'telHref'  => $telHref,
            'waHref'   => $waHref,
            'rawPhone' => $rawPhone,
        ]);
    }

    // ─── Vista del Propietario: Generar / Descargar QR ──────────────────────

    /**
     * GET /app/qr
     * Muestra la credencial QR de las mascotas del propietario autenticado.
     */
    public function ownerIndex(): void
    {
        $this->requireAuth(['propietario', 'superadmin', 'veterinario', 'recepcionista']);

        $user = $this->auth->getCurrentUser() ?? ($_SESSION['user'] ?? []);
        if (empty($user)) {
            $this->redirect('/app/login');
        }

        if ($this->auth->hasRole('propietario')) {
            $mascotas = $this->db->fetchAll(
                "SELECT p.*, e.nombre AS especie_nombre, r.nombre AS raza_nombre,
                        pr.nombre AS propietario_nombre, pr.apellido AS propietario_apellido
                 FROM pacientes p
                 INNER JOIN propietarios pr ON pr.id = p.propietario_id
                 INNER JOIN especies e ON e.id = p.especie_id
                 LEFT JOIN razas r ON r.id = p.raza_id
                 WHERE pr.usuario_id = :uid AND p.activo = 1
                 ORDER BY p.nombre ASC",
                [':uid' => (int) $user['id']]
            );
        } else {
            $mascotas = $this->db->fetchAll(
                "SELECT p.*, e.nombre AS especie_nombre, r.nombre AS raza_nombre,
                        pr.nombre AS propietario_nombre, pr.apellido AS propietario_apellido
                 FROM pacientes p
                 INNER JOIN propietarios pr ON pr.id = p.propietario_id
                 INNER JOIN especies e ON e.id = p.especie_id
                 LEFT JOIN razas r ON r.id = p.raza_id
                 WHERE p.activo = 1
                 ORDER BY p.fecha_registro DESC
                 LIMIT 50"
            );
        }

        // Asegurar que cada mascota tenga su UUID único asignado
        foreach ($mascotas as &$mascota) {
            if (empty($mascota['qr_code'])) {
                $newUuid = $this->generateUuidV4();
                $this->db->execute(
                    "UPDATE pacientes SET qr_code = :uuid, updated_at = NOW() WHERE id = :id",
                    [':uuid' => $newUuid, ':id' => (int) $mascota['id']]
                );
                $mascota['qr_code'] = $newUuid;
            }

            $publicUrl              = $this->buildPublicQrUrl((string) $mascota['qr_code']);
            $mascota['qr_url']      = $publicUrl;
            $mascota['qr_image_src'] = '/app/qr/generar/' . rawurlencode((string) $mascota['qr_code']);
        }
        unset($mascota);

        $this->render('propietario/qr', [
            'title'      => 'Credencial QR — ' . Config::APP_NAME,
            'pageTitle'  => 'Credencial QR de Mascotas',
            'activeMenu' => 'qr',
            'auth'       => $this->auth,
            'user'       => $user,
            'mascotas'   => $mascotas,
            'csrfToken'  => $this->generateToken(),
        ]);
    }

    /**
     * GET /app/qr/generar/{uuid} y GET /qr/render/{uuid}
     * Transmite en tiempo real la imagen oficial del código QR (PNG o SVG)
     * codificando la URL dinámica del perfil público de emergencia.
     */
    public function renderQRImage(string $uuid = ''): void
    {
        $uuid = $this->sanitizeUuid($uuid);
        if ($uuid === '') {
            http_response_code(400);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'UUID de código QR inválido.';
            exit;
        }

        // Limpiar cualquier búfer previo para no corromper los bytes de la imagen
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $format = strtolower((string) ($this->query('format', 'png')));
        $size   = isset($_GET['size']) ? (int) $_GET['size'] : 300;
        $size   = max(120, min(800, $size));

        $publicUrl = $this->buildPublicQrUrl($uuid);

        try {
            if ($format === 'svg') {
                $bytes    = $this->generateQrSvgString($publicUrl, $size);
                $mimeType = 'image/svg+xml; charset=UTF-8';
            } else {
                $bytes    = $this->generateQrPngBytesDirect($publicUrl, $size);
                $mimeType = 'image/png';
            }

            header('Content-Type: ' . $mimeType);
            header('Content-Length: ' . strlen($bytes));
            header('Cache-Control: public, max-age=86400, stale-while-revalidate=604800');
            echo $bytes;
            exit;
        } catch (\Throwable $e) {
            error_log('[QRController] Error al renderizar QR: ' . $e->getMessage());

            // Respaldo automático: si falla PNG por GD, intentar salida SVG sin GD
            if ($format !== 'svg') {
                try {
                    $svgBytes = $this->generateQrSvgString($publicUrl, $size);
                    header('Content-Type: image/svg+xml; charset=UTF-8');
                    header('Content-Length: ' . strlen($svgBytes));
                    header('Cache-Control: public, max-age=86400');
                    echo $svgBytes;
                    exit;
                } catch (\Throwable) {}
            }

            http_response_code(500);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'Error generando el código QR.';
            exit;
        }
    }

    /**
     * GET /app/qr/descargar/{uuid}
     * Descarga la imagen PNG del código QR de la mascota.
     */
    public function downloadQR(string $uuid = ''): void
    {
        $this->requireAuth(['propietario', 'superadmin', 'veterinario', 'recepcionista']);

        $uuid = $this->sanitizeUuid($uuid);
        if ($uuid === '') {
            $this->setFlash('error', 'Código QR inválido.');
            $this->redirect('/app/qr');
        }

        $paciente = $this->db->fetchOne(
            "SELECT p.id, p.nombre, p.qr_code, pr.usuario_id
             FROM pacientes p
             INNER JOIN propietarios pr ON pr.id = p.propietario_id
             WHERE p.qr_code = :uuid AND p.activo = 1
             LIMIT 1",
            [':uuid' => $uuid]
        );

        if (!$paciente) {
            $this->setFlash('error', 'Mascota no encontrada.');
            $this->redirect('/app/qr');
        }

        // Control de propiedad: un propietario solo puede descargar el QR de sus mascotas
        if ($this->auth->hasRole('propietario')) {
            $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
            if ((int) ($paciente['usuario_id'] ?? 0) !== $currentUserId) {
                $this->redirect('/app/unauthorized');
            }
        }

        $publicUrl = $this->buildPublicQrUrl($uuid);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        try {
            $bytes    = $this->generateQrPngBytesDirect($publicUrl, 500);
            $mimeType = 'image/png';
            $ext      = 'png';
        } catch (\Throwable) {
            $bytes    = $this->generateQrSvgString($publicUrl, 500);
            $mimeType = 'image/svg+xml; charset=UTF-8';
            $ext      = 'svg';
        }

        $safeSlug = preg_replace('/[^a-zA-Z0-9_-]+/', '-', strtolower((string) $paciente['nombre'])) ?: 'mascota';
        $filename = "PetKardex-QR-{$safeSlug}.{$ext}";

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo $bytes;
        exit;
    }

    /**
     * POST /app/qr/regenerar/{id}
     * Regenera el UUID público de la mascota (útil si se extravía la placa anterior).
     */
    public function regenerateQR(string $id = ''): void
    {
        $this->requireAuth(['propietario', 'superadmin', 'veterinario', 'recepcionista']);
        $this->requireValidCsrf();

        $pacienteId = (int) $id;
        if ($pacienteId <= 0) {
            $this->setFlash('error', 'Identificador de mascota inválido.');
            $this->redirect('/app/qr');
        }

        $paciente = $this->db->fetchOne(
            "SELECT p.id, p.nombre, pr.usuario_id
             FROM pacientes p
             INNER JOIN propietarios pr ON pr.id = p.propietario_id
             WHERE p.id = :id AND p.activo = 1
             LIMIT 1",
            [':id' => $pacienteId]
        );

        if (!$paciente) {
            $this->setFlash('error', 'Mascota no encontrada.');
            $this->redirect('/app/qr');
        }

        if ($this->auth->hasRole('propietario') && (int) ($paciente['usuario_id'] ?? 0) !== (int) ($_SESSION['user_id'] ?? 0)) {
            $this->redirect('/app/unauthorized');
        }

        $newUuid = $this->generateUuidV4();
        $this->db->execute(
            "UPDATE pacientes SET qr_code = :uuid, updated_at = NOW() WHERE id = :id",
            [':uuid' => $newUuid, ':id' => $pacienteId]
        );

        $this->setFlash('success', "Se generó un nuevo código QR único para {$paciente['nombre']}.");
        $this->redirect('/app/qr');
    }

    // ─── Escáner Interno de la Clínica ──────────────────────────────────────

    /**
     * GET /app/qr/scanner (alias /app/escaner)
     * Vista exclusiva para Veterinarios, Guardias, Emergencias, Recepcionistas y SuperAdmin.
     */
    public function scanner(): void
    {
        $this->requireAuth(self::CLINIC_SCANNER_ROLES);

        $user = $this->auth->getCurrentUser() ?? ($_SESSION['user'] ?? []);

        $this->render('clinica/escaner', [
            'title'      => 'Escáner QR Clínico — ' . Config::APP_NAME,
            'pageTitle'  => 'Escáner QR de Pacientes',
            'activeMenu' => 'qr_scanner',
            'auth'       => $this->auth,
            'user'       => $user,
            'csrfToken'  => $this->generateToken(),
        ]);
    }

    /**
     * POST /app/qr/resolver
     * Recibe el texto escaneado por cámara o búsqueda manual (URL /qr/{uuid}, UUID o microchip)
     * y devuelve la ruta al Kardex Médico Interno del paciente.
     */
    public function resolveScan(): void
    {
        $this->requireAuth(self::CLINIC_SCANNER_ROLES);
        $this->requireValidCsrf();

        $rawInput = $this->extractRawQrInput();
        if ($rawInput === '') {
            $this->json(['success' => false, 'error' => 'No se recibió ningún código QR válido.'], 422);
        }

        $uuid = $this->extractUuidFromPayload($rawInput);

        $paciente = null;
        if ($uuid !== '') {
            $paciente = $this->db->fetchOne(
                "SELECT p.id, p.nombre, p.qr_code, e.nombre AS especie_nombre
                 FROM pacientes p
                 INNER JOIN especies e ON e.id = p.especie_id
                 WHERE p.qr_code = :uuid AND p.activo = 1
                 LIMIT 1",
                [':uuid' => $uuid]
            );
        }

        // Respaldo: permitir búsqueda por microchip o código exacto ingresado manualmente
        if (!$paciente) {
            $cleanCode = trim($rawInput);
            $paciente = $this->db->fetchOne(
                "SELECT p.id, p.nombre, p.qr_code, e.nombre AS especie_nombre
                 FROM pacientes p
                 INNER JOIN especies e ON e.id = p.especie_id
                 WHERE (p.microchip = :chip OR p.qr_code = :code) AND p.activo = 1
                 LIMIT 1",
                [':chip' => $cleanCode, ':code' => $cleanCode]
            );
        }

        if (!$paciente) {
            $this->json([
                'success' => false,
                'error'   => 'No se encontró ningún paciente activo asociado al código escaneado.',
            ], 404);
        }

        $this->json([
            'success'  => true,
            'redirect' => '/app/kardex/' . (int) $paciente['id'],
            'paciente' => [
                'id'      => (int) $paciente['id'],
                'nombre'  => $paciente['nombre'],
                'especie' => $paciente['especie_nombre'],
            ],
        ]);
    }

    /**
     * GET /app/kardex/{id}
     * Vista de aterrizaje del Kardex Médico Interno tras escanear un QR en clínica.
     */
    public function kardexPreview(string $id = ''): void
    {
        $this->requireAuth(self::CLINIC_SCANNER_ROLES);

        $pacienteId = (int) $id;
        if ($pacienteId <= 0) {
            $this->setFlash('error', 'Paciente no válido.');
            $this->redirect('/app/qr/scanner');
        }

        $paciente = $this->db->fetchOne(
            "SELECT p.*, e.nombre AS especie_nombre, r.nombre AS raza_nombre,
                    pr.nombre AS propietario_nombre, pr.apellido AS propietario_apellido,
                    pr.dni AS propietario_dni, pr.telefono AS propietario_telefono,
                    pr.celular AS propietario_celular, pr.email AS propietario_email
             FROM pacientes p
             INNER JOIN especies e ON e.id = p.especie_id
             LEFT JOIN razas r ON r.id = p.raza_id
             INNER JOIN propietarios pr ON pr.id = p.propietario_id
             WHERE p.id = :id AND p.activo = 1
             LIMIT 1",
            [':id' => $pacienteId]
        );

        if (!$paciente) {
            $this->setFlash('error', 'No se encontró el expediente médico solicitado.');
            $this->redirect('/app/qr/scanner');
        }

        $user = $this->auth->getCurrentUser() ?? ($_SESSION['user'] ?? []);

        $this->render('clinica/kardex_preview', [
            'title'      => 'Kardex Médico: ' . $paciente['nombre'] . ' — ' . Config::APP_NAME,
            'pageTitle'  => 'Kardex Médico Interno',
            'activeMenu' => 'qr_scanner',
            'auth'       => $this->auth,
            'user'       => $user,
            'paciente'   => $paciente,
            'qrImageSrc' => !empty($paciente['qr_code'])
                ? '/app/qr/generar/' . rawurlencode((string) $paciente['qr_code'])
                : null,
        ]);
    }

    // ─── Helpers privados ───────────────────────────────────────────────────

    private function generateUuidV4(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function sanitizeUuid(string $uuid): string
    {
        $uuid = trim($uuid);
        if (preg_match('/^[a-zA-Z0-9\-_]{8,128}$/', $uuid)) {
            return $uuid;
        }
        return '';
    }

    private function buildPublicQrUrl(string $uuid): string
    {
        $base = rtrim(Config::env('QR_BASE_URL', Config::getBaseUrl()), '/');
        return $base . '/qr/' . rawurlencode($uuid);
    }

    private function buildQrApiUrl(string $targetUrl, int $size = 360): string
    {
        $size = max(120, min(600, $size));
        return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size
             . '&margin=12&color=0D0D0D&bgcolor=FFFFFF&data=' . rawurlencode($targetUrl);
    }

    private function extractRawQrInput(): string
    {
        $raw = $this->input('qr_data', '');
        if (is_string($raw) && $raw !== '') {
            return trim($raw);
        }

        $body = file_get_contents('php://input');
        if ($body !== false && $body !== '') {
            $decoded = json_decode($body, true);
            if (is_array($decoded) && !empty($decoded['qr_data'])) {
                return trim((string) $decoded['qr_data']);
            }
        }

        return '';
    }

    private function extractUuidFromPayload(string $payload): string
    {
        $payload = trim($payload);

        // Si el QR contiene una URL tipo https://.../qr/{uuid}
        if (preg_match('#/qr/([a-zA-Z0-9\-_]{8,128})(?:[/?\#]|$)#', $payload, $m)) {
            return $m[1];
        }

        return $this->sanitizeUuid($payload);
    }

    /**
     * Genera directamente los bytes binarios PNG del código QR usando endroid/qr-code.
     */
    private function generateQrPngBytesDirect(string $data, int $size = 300): string
    {
        if (class_exists('\Endroid\QrCode\QrCode')) {
            $qrCodeClass   = '\Endroid\QrCode\QrCode';
            $writerClass   = '\Endroid\QrCode\Writer\PngWriter';
            $encodingClass = '\Endroid\QrCode\Encoding\Encoding';
            $errorClass    = '\Endroid\QrCode\ErrorCorrectionLevel';
            $colorClass    = '\Endroid\QrCode\Color\Color';

            $qrCode = new $qrCodeClass(
                data: $data,
                encoding: new $encodingClass('UTF-8'),
                errorCorrectionLevel: $errorClass::High,
                size: $size,
                margin: 10,
                foregroundColor: new $colorClass(13, 13, 13),
                backgroundColor: new $colorClass(255, 255, 255)
            );

            $writer = new $writerClass();
            $result = $writer->write($qrCode);
            return $result->getString();
        }

        // Si Endroid no estuviera, intentar generar PNG con GD nativo
        if (extension_loaded('gd')) {
            $img = imagecreatetruecolor($size, $size);
            if ($img !== false) {
                $white = imagecolorallocate($img, 255, 255, 255);
                $black = imagecolorallocate($img, 13, 13, 13);
                imagefill($img, 0, 0, $white);
                imagerectangle($img, 0, 0, $size - 1, $size - 1, $black);
                imagestring($img, 4, 20, (int) ($size / 2) - 10, 'QR PetKardex', $black);
                ob_start();
                imagepng($img);
                $raw = ob_get_clean();
                imagedestroy($img);
                if ($raw !== false) {
                    return $raw;
                }
            }
        }

        throw new \RuntimeException('No se pudo generar la imagen PNG del código QR.');
    }

    /**
     * Genera directamente la cadena XML SVG del código QR (no requiere GD).
     */
    private function generateQrSvgString(string $data, int $size = 300): string
    {
        if (class_exists('\Endroid\QrCode\Writer\SvgWriter')) {
            $qrCodeClass   = '\Endroid\QrCode\QrCode';
            $writerClass   = '\Endroid\QrCode\Writer\SvgWriter';
            $encodingClass = '\Endroid\QrCode\Encoding\Encoding';
            $errorClass    = '\Endroid\QrCode\ErrorCorrectionLevel';
            $colorClass    = '\Endroid\QrCode\Color\Color';

            $qrCode = new $qrCodeClass(
                data: $data,
                encoding: new $encodingClass('UTF-8'),
                errorCorrectionLevel: $errorClass::High,
                size: $size,
                margin: 10,
                foregroundColor: new $colorClass(13, 13, 13),
                backgroundColor: new $colorClass(255, 255, 255)
            );

            $writer = new $writerClass();
            $result = $writer->write($qrCode);
            return $result->getString();
        }

        // SVG fallback simple
        $escaped = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '">'
             . '<rect width="100%" height="100%" fill="#ffffff"/>'
             . '<rect x="10" y="10" width="' . ($size - 20) . '" height="' . ($size - 20) . '" fill="none" stroke="#0D0D0D" stroke-width="4"/>'
             . '<text x="50%" y="50%" font-family="Arial" font-size="14" fill="#0D0D0D" dominant-baseline="middle" text-anchor="middle">PetKardex QR</text>'
             . '</svg>';
    }

    private function formatTelLink(string $phone): string
    {
        $cleaned = preg_replace('/[^0-9+]/', '', $phone) ?? '';
        return $cleaned !== '' ? 'tel:' . $cleaned : '';
    }

    private function formatWhatsAppLink(string $phone, string $message): string
    {
        $digits = preg_replace('/[^0-9]/', '', $phone) ?? '';
        if ($digits === '') {
            return '';
        }
        return 'https://wa.me/' . $digits . '?text=' . rawurlencode($message);
    }

    private function logQrScan(int $pacienteId, string $uuid): void
    {
        try {
            $token = $uuid . '_' . date('YmdH');
            $existing = $this->db->fetchOne(
                "SELECT id FROM sesiones_qr WHERE token = :token LIMIT 1",
                [':token' => $token]
            );

            if ($existing) {
                $this->db->execute(
                    "UPDATE sesiones_qr SET usos = usos + 1 WHERE id = :id",
                    [':id' => (int) $existing['id']]
                );
                return;
            }

            $this->db->execute(
                "INSERT INTO sesiones_qr (paciente_id, token, ip_origen, user_agent, creado_at, expira_at, usos)
                 VALUES (:pid, :token, :ip, :ua, NOW(), DATE_ADD(NOW(), INTERVAL 24 HOUR), 1)",
                [
                    ':pid'   => $pacienteId,
                    ':token' => $token,
                    ':ip'    => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                    ':ua'    => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
                ]
            );
        } catch (\Throwable) {
            // No bloquear la vista pública de emergencia
        }
    }

    private function renderQrNotFound(): void
    {
        http_response_code(404);
        $this->render('public/emergency_profile', [
            'title'    => 'Código QR no encontrado — ' . Config::APP_NAME,
            'paciente' => null,
            'clinic'   => [],
            'telHref'  => '',
            'waHref'   => '',
            'rawPhone' => '',
        ]);
    }
}


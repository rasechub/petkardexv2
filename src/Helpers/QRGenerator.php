<?php
/**
 * PetKardex v2 - QRGenerator Helper
 *
 * Genera códigos QR para los pacientes usando endroid/qr-code (Composer).
 * Si la librería no está instalada, usa un fallback con API pública en desarrollo.
 *
 * @author    CESAR AQUINO ANGLAS - VI B NOCHE
 * @copyright 2026 PetKardex. Todos los derechos reservados.
 * @license   Propietaria - IESTP Santiago Antúnez de Mayolo (IESTP SAM)
 */

declare(strict_types=1);

namespace PetKardex\Helpers;

use PetKardex\Config\Config;

class QRGenerator
{
    /** Directorio donde se guardan los PNG */
    private string $qrDir;

    /** URL pública base para acceder a los QR */
    private string $qrUrl;

    public function __construct()
    {
        $this->qrDir = rtrim(Config::QR_PATH, '/\\') . DIRECTORY_SEPARATOR;
        $this->qrUrl = rtrim(Config::getBaseUrl(), '/') . '/uploads/qr/';

        // Crear directorio si no existe
        if (!is_dir($this->qrDir)) {
            mkdir($this->qrDir, 0755, true);
        }
    }

    // ─── Generación ───────────────────────────────────────────────────────

    /**
     * Genera el código QR de un paciente y lo guarda como PNG.
     *
     * El QR codifica una URL de perfil público del paciente para que al
     * escanearlo el guardia/veterinario pueda ver su expediente.
     *
     * @param  int    $pacienteId  ID del paciente
     * @param  string $token       Token único del paciente (qr_code en BD)
     * @return string              Nombre del archivo generado (ej: 'qr_42_abc123.png')
     * @throws \RuntimeException   Si no se puede escribir el archivo
     */
    public function generateQR(int $pacienteId, string $token): string
    {
        $filename = "qr_{$pacienteId}_{$token}.png";
        $filepath = $this->qrDir . $filename;

        // Contenido codificado en el QR: URL pública del perfil de emergencia
        $content = rtrim(Config::getBaseUrl(), '/') . "/qr/" . rawurlencode($token);

        if (class_exists('\Endroid\QrCode\QrCode')) {
            $this->generateWithEndroid($content, $filepath);
        } else {
            // Fallback con GD si Endroid no está presente
            $this->generateFallback($content, $filepath);
        }

        return $filename;
    }

    /**
     * Retorna la URL pública de acceso a un archivo QR.
     *
     * @param  string $filename  Nombre del archivo (devuelto por generateQR)
     * @return string            URL completa
     */
    public function getPublicUrl(string $filename): string
    {
        return $this->qrUrl . ltrim($filename, '/');
    }

    /**
     * Retorna la ruta absoluta en disco de un archivo QR.
     */
    public function getFilePath(string $filename): string
    {
        return $this->qrDir . $filename;
    }

    /**
     * Verifica si ya existe el QR de un paciente.
     */
    public function exists(string $filename): bool
    {
        return !empty($filename) && file_exists($this->qrDir . $filename);
    }

    /**
     * Elimina el archivo QR de un paciente.
     */
    public function delete(string $filename): bool
    {
        $path = $this->qrDir . $filename;
        return file_exists($path) && unlink($path);
    }

    // ─── Implementaciones de generación ───────────────────────────────────

    /**
     * Genera QR usando endroid/qr-code (instalado vía Composer).
     *
     * @throws \RuntimeException
     */
    private function generateWithEndroid(string $content, string $filepath): void
    {
        // Importaciones dinámicas para evitar errores si la lib no está
        $qrCodeClass  = '\Endroid\QrCode\QrCode';
        $writerClass  = '\Endroid\QrCode\Writer\PngWriter';
        $encodingClass = '\Endroid\QrCode\Encoding\Encoding';
        $errorClass   = '\Endroid\QrCode\ErrorCorrectionLevel';
        $colorClass   = '\Endroid\QrCode\Color\Color';

        $qrCode = new $qrCodeClass(
            data: $content,
            encoding: new $encodingClass('UTF-8'),
            errorCorrectionLevel: $errorClass::High,
            size: 300,
            margin: 10,
            foregroundColor: new $colorClass(0, 0, 0),
            backgroundColor: new $colorClass(255, 255, 255),
        );

        $writer = new $writerClass();
        $result = $writer->write($qrCode);
        $result->saveToFile($filepath);
    }

    /**
     * Fallback: genera un QR simple usando solo PHP/GD.
     * Implementación mínima — se recomienda instalar endroid/qr-code.
     *
     * Este fallback crea un placeholder visual que indica que el QR real
     * debe generarse con Composer.
     *
     * @throws \RuntimeException Si GD no está disponible o falla la escritura
     */
    private function generateFallback(string $content, string $filepath): void
    {
        if (!extension_loaded('gd')) {
            throw new \RuntimeException(
                'No se pudo generar el QR: instala endroid/qr-code con Composer '
                . 'o habilita la extensión GD de PHP.'
            );
        }

        // Imagen placeholder 300×300
        $img = imagecreatetruecolor(300, 300);
        if ($img === false) {
            throw new \RuntimeException('No se pudo crear la imagen QR.');
        }

        $white  = imagecolorallocate($img, 255, 255, 255);
        $black  = imagecolorallocate($img, 0, 0, 0);
        $purple = imagecolorallocate($img, 196, 140, 255);

        // Fondo blanco
        imagefill($img, 0, 0, $white);

        // Borde
        imagerectangle($img, 0, 0, 299, 299, $black);
        imagerectangle($img, 2, 2, 297, 297, $purple);

        // Texto central
        imagestring($img, 3, 50, 130, 'QR — PetKardex', $black);
        imagestring($img, 2, 20, 150, substr($content, 0, 40), $purple);
        imagestring($img, 1, 30, 170, 'Instala endroid/qr-code', $black);

        // Guardar PNG
        if (!imagepng($img, $filepath)) {
            imagedestroy($img);
            throw new \RuntimeException("No se pudo escribir el archivo QR en: {$filepath}");
        }

        imagedestroy($img);
    }

    // ─── Generación por lote ──────────────────────────────────────────────

    /**
     * Genera QRs para múltiples pacientes.
     *
     * @param  array $pacientes  Array de ['id' => int, 'qr_code' => string]
     * @return array             ['id' => string filename]
     */
    public function generateBatch(array $pacientes): array
    {
        $results = [];
        foreach ($pacientes as $p) {
            try {
                $results[$p['id']] = $this->generateQR((int) $p['id'], $p['qr_code']);
            } catch (\Throwable $e) {
                $results[$p['id']] = null;
                error_log("[QRGenerator] Error en paciente {$p['id']}: {$e->getMessage()}");
            }
        }
        return $results;
    }
}

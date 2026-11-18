<?php
/**
 * PetKardex v2 - MascotaController
 *
 * Controlador dedicado al formulario y registro de nuevas mascotas
 * con subida segura de fotografía y generación automática de UUID para QR.
 */

declare(strict_types=1);

namespace PetKardex\Controllers;

use PetKardex\Config\Config;
use PetKardex\Core\Auth;
use PetKardex\Core\Controller;
use PetKardex\Models\Paciente;

class MascotaController extends Controller
{
    private Auth $auth;
    private Paciente $pacienteModel;

    private const ALLOWED_ROLES   = ['superadmin', 'veterinario', 'recepcionista'];
    private const MAX_PHOTO_BYTES = 5242880; // 5 MB

    public function __construct()
    {
        parent::__construct();
        $this->auth          = new Auth();
        $this->pacienteModel = new Paciente();
    }

    /**
     * GET /app/mascotas/nueva
     * Muestra el formulario dedicado para registrar una nueva mascota.
     */
    public function crearForm(): void
    {
        $this->requireAuth(self::ALLOWED_ROLES);

        $this->render('mascotas/crear', [
            'title'        => 'Registrar Nueva Mascota — ' . Config::APP_NAME,
            'pageTitle'    => 'Registrar Nueva Mascota',
            'activeMenu'   => 'pacientes',
            'auth'         => $this->auth,
            'user'         => $this->auth->getCurrentUser() ?? ($_SESSION['user'] ?? []),
            'especies'     => $this->pacienteModel->getEspecies(),
            'razas'        => $this->pacienteModel->getRazas(),
            'propietarios' => $this->pacienteModel->getPropietarios(),
            'csrfToken'    => $this->generateToken(),
        ]);
    }

    /**
     * POST /app/mascotas/nueva
     * Procesa fotografía, inserta la mascota en MySQL, genera automáticamente
     * su UUID QR y redirige al directorio /app/pacientes.
     */
    public function guardar(): void
    {
        $this->requireAuth(self::ALLOWED_ROLES);
        $this->requireValidCsrf();

        try {
            $fotoRuta = $this->procesarFotoMascota($_FILES['foto_mascota'] ?? null);
            $nuevoId  = $this->pacienteModel->registrarPaciente($_POST, $fotoRuta);
            $nombre   = trim((string) $this->input('nombre', 'Mascota'));
            $this->setFlash('success', "Mascota \"{$nombre}\" registrada exitosamente (#{$nuevoId}) con su código QR único.");
            $this->redirect('/app/pacientes');
        } catch (\Throwable $e) {
            $this->setFlash('error', 'No se pudo registrar la mascota: ' . $e->getMessage());
            $this->redirect('/app/mascotas/nueva');
        }
    }

    /**
     * GET /app/mascotas/editar/{id}
     * Muestra el formulario pre-cargado para editar una mascota existente.
     */
    public function editarForm(string $id = ''): void
    {
        $this->requireAuth(self::ALLOWED_ROLES);

        $mascotaId = (int) $id;
        $mascota   = $mascotaId > 0 ? $this->pacienteModel->getPacienteParaEditar($mascotaId) : null;

        if (!$mascota) {
            $this->setFlash('error', 'No se encontró la mascota solicitada.');
            $this->redirect('/app/pacientes');
        }

        $this->render('mascotas/editar', [
            'title'        => 'Editar Mascota (' . $mascota['nombre'] . ') — ' . Config::APP_NAME,
            'pageTitle'    => 'Editar Ficha de Mascota',
            'activeMenu'   => 'pacientes',
            'auth'         => $this->auth,
            'user'         => $this->auth->getCurrentUser() ?? ($_SESSION['user'] ?? []),
            'mascota'      => $mascota,
            'especies'     => $this->pacienteModel->getEspecies(),
            'razas'        => $this->pacienteModel->getRazas(),
            'propietarios' => $this->pacienteModel->getPropietarios(),
            'csrfToken'    => $this->generateToken(),
        ]);
    }

    /**
     * POST /app/mascotas/editar/{id}
     * Procesa la actualización de la mascota, sube nueva foto si se adjuntó
     * y elimina la imagen anterior del servidor para no acumular archivos huérfanos.
     */
    public function editar(string $id = ''): void
    {
        $this->requireAuth(self::ALLOWED_ROLES);
        $this->requireValidCsrf();

        $mascotaId = (int) $id;
        $actual    = $mascotaId > 0 ? $this->pacienteModel->findById($mascotaId) : null;
        if (!$actual || (int) ($actual['activo'] ?? 0) !== 1) {
            $this->setFlash('error', 'No se encontró la mascota solicitada.');
            $this->redirect('/app/pacientes');
        }

        $nuevaFoto = null;

        try {
            $nuevaFoto = $this->procesarFotoMascota($_FILES['foto_mascota'] ?? null);
            $this->pacienteModel->actualizarPaciente($mascotaId, $_POST, $nuevaFoto);

            if ($nuevaFoto !== null && !empty($actual['foto']) && $actual['foto'] !== $nuevaFoto) {
                $this->eliminarFotoAnterior((string) $actual['foto']);
            }

            $nombre = trim((string) $this->input('nombre', (string) $actual['nombre']));
            $this->setFlash('success', "Mascota \"{$nombre}\" actualizada correctamente.");
            $this->redirect('/app/pacientes');
        } catch (\Throwable $e) {
            if ($nuevaFoto !== null) {
                $this->eliminarFotoAnterior($nuevaFoto);
            }
            $this->setFlash('error', 'No se pudo actualizar la mascota: ' . $e->getMessage());
            $this->redirect("/app/mascotas/editar/{$mascotaId}");
        }
    }

    /**
     * Elimina de forma segura una fotografía previa almacenada en uploads/mascotas/.
     */
    private function eliminarFotoAnterior(string $rutaRelativa): void
    {
        $clean = ltrim(str_replace('\\', '/', trim($rutaRelativa)), '/');
        if (!str_starts_with($clean, 'uploads/mascotas/')) {
            return;
        }

        $filename = basename($clean);
        if ($filename === '' || $filename === '.' || $filename === '..') {
            return;
        }

        $baseDir = rtrim(Config::UPLOAD_PATH, '/\\') . DIRECTORY_SEPARATOR . 'mascotas';
        $absPath = $baseDir . DIRECTORY_SEPARATOR . $filename;

        if (is_file($absPath)) {
            @unlink($absPath);
        }
    }

    /**
     * Valida y almacena la fotografía subida en public/uploads/mascotas/.
     * Retorna la ruta pública relativa ('uploads/mascotas/archivo.ext') o null.
     */
    private function procesarFotoMascota(?array $file): ?string
    {
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Error al subir la fotografía de la mascota.');
        }

        if (($file['size'] ?? 0) > self::MAX_PHOTO_BYTES) {
            throw new \RuntimeException('La fotografía no debe superar los 5 MB.');
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            throw new \RuntimeException('Archivo de imagen no válido.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = (string) $finfo->file($tmpPath);

        $extMap = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/gif'  => 'gif',
        ];

        if (!isset($extMap[$mime])) {
            throw new \RuntimeException('Formato de imagen no permitido. Usa JPG, PNG, WEBP o GIF.');
        }

        $targetDir = rtrim(Config::UPLOAD_PATH, '/\\') . DIRECTORY_SEPARATOR . 'mascotas' . DIRECTORY_SEPARATOR;
        if (!is_dir($targetDir) && !@mkdir($targetDir, 0755, true)) {
            throw new \RuntimeException('No se pudo preparar la carpeta de fotografías en uploads/mascotas.');
        }

        $filename = 'pet_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extMap[$mime];
        $destPath = $targetDir . $filename;

        if (!move_uploaded_file($tmpPath, $destPath)) {
            throw new \RuntimeException('No se pudo guardar la fotografía en el servidor.');
        }

        return 'uploads/mascotas/' . $filename;
    }
}

<?php
/**
 * PetKardex v2 - PacienteController
 *
 * Gestiona el listado general de Pacientes / Mascotas, búsqueda
 * y registro de nuevas mascotas con asignación automática de QR único.
 *
 * @author    CESAR AQUINO ANGLAS - VI B NOCHE
 * @copyright 2026 PetKardex. Todos los derechos reservados.
 * @license   Propietaria - IESTP Santiago Antúnez de Mayolo (IESTP SAM)
 */

declare(strict_types=1);

namespace PetKardex\Controllers;

use PetKardex\Config\Config;
use PetKardex\Core\Auth;
use PetKardex\Core\Controller;
use PetKardex\Models\Paciente;

class PacienteController extends Controller
{
    private Auth $auth;
    private Paciente $pacienteModel;

    private const VIEW_ROLES   = ['superadmin', 'veterinario', 'recepcionista', 'guardia', 'emergencia'];
    private const CREATE_ROLES = ['superadmin', 'veterinario', 'recepcionista'];

    public function __construct()
    {
        parent::__construct();
        $this->auth          = new Auth();
        $this->pacienteModel = new Paciente();
    }

    /**
     * GET /app/pacientes
     * Lista general de mascotas con buscador y modal para registrar nueva mascota.
     */
    public function index(): void
    {
        $this->requireAuth(self::VIEW_ROLES);

        $search    = trim((string) $this->query('q', ''));
        $canCreate = $this->auth->hasRole(self::CREATE_ROLES);

        $this->render('pacientes/index', [
            'title'        => 'Pacientes / Mascotas — ' . Config::APP_NAME,
            'pageTitle'    => 'Directorio de Pacientes / Mascotas',
            'activeMenu'   => 'pacientes',
            'auth'         => $this->auth,
            'user'         => $this->auth->getCurrentUser() ?? ($_SESSION['user'] ?? []),
            'pacientes'    => $this->pacienteModel->getAllPacientes($search),
            'search'       => $search,
            'canCreate'    => $canCreate,
            'especies'     => $canCreate ? $this->pacienteModel->getEspecies() : [],
            'razas'        => $canCreate ? $this->pacienteModel->getRazas() : [],
            'propietarios' => $canCreate ? $this->pacienteModel->getPropietarios() : [],
            'csrfToken'    => $this->generateToken(),
        ]);
    }

    /**
     * POST /app/pacientes/crear
     * Registra una nueva mascota y genera su código QR único.
     */
    public function crear(): void
    {
        $this->requireAuth(self::CREATE_ROLES);
        $this->requireValidCsrf();

        try {
            $nuevoId = $this->pacienteModel->registrarPaciente($_POST);
            $this->setFlash('success', 'Mascota registrada correctamente con su código QR único.');

            if (isset($_POST['ir_a_kardex']) && $_POST['ir_a_kardex'] === '1') {
                $this->redirect("/app/kardex/{$nuevoId}");
            }
        } catch (\Throwable $e) {
            $this->setFlash('error', 'No se pudo registrar la mascota: ' . $e->getMessage());
        }

        $this->redirect('/app/pacientes');
    }
}


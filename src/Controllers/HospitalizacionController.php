<?php
/**
 * PetKardex v2 - HospitalizacionController
 *
 * Gestiona el mapa de jaulas/caniles (Ocupada, Libre, En Limpieza),
 * formulario rápido de Triage/Emergencias, bitácora de evolución por turno
 * (con descuento de medicamentos hacia la cuenta POS) y alta médica.
 */

declare(strict_types=1);

namespace PetKardex\Controllers;

use PetKardex\Config\Config;
use PetKardex\Core\Auth;
use PetKardex\Core\Controller;
use PetKardex\Models\Hospitalizacion;
use PetKardex\Models\Inventario;

class HospitalizacionController extends Controller
{
    private Auth $auth;
    private Hospitalizacion $hospModel;
    private Inventario $inventarioModel;

    private const VIEW_ROLES   = ['superadmin', 'veterinario', 'guardia', 'emergencia', 'recepcionista'];
    private const CLINIC_ROLES = ['superadmin', 'veterinario', 'guardia', 'emergencia'];

    public function __construct()
    {
        parent::__construct();
        $this->auth            = new Auth();
        $this->hospModel       = new Hospitalizacion();
        $this->inventarioModel = new Inventario();
    }

    /**
     * GET /app/hospitalizacion | /app/emergencias
     * Muestra el mapa visual de jaulas/caniles y el formulario rápido de Triage.
     */
    public function index(): void
    {
        $this->requireAuth(self::VIEW_ROLES);

        $mapaJaulas = $this->hospModel->getMapaJaulas();
        $pacientes  = $this->hospModel->getPacientesActivos();
        $canTriage  = $this->auth->hasRole(self::CLINIC_ROLES);

        $conteos = ['ocupada' => 0, 'libre' => 0, 'limpieza' => 0];
        foreach ($mapaJaulas as $j) {
            $conteos[$j['estado']] = ($conteos[$j['estado']] ?? 0) + 1;
        }

        $this->render('hospitalizacion/index', [
            'title'      => 'Hospitalización y Triage — ' . Config::APP_NAME,
            'pageTitle'  => 'Mapa de Hospitalización y Emergencias',
            'activeMenu' => 'hospitalizacion',
            'auth'       => $this->auth,
            'user'       => $this->auth->getCurrentUser() ?? ($_SESSION['user'] ?? []),
            'mapaJaulas' => $mapaJaulas,
            'conteos'    => $conteos,
            'pacientes'  => $pacientes,
            'canTriage'  => $canTriage,
            'csrfToken'  => $this->generateToken(),
        ]);
    }

    /**
     * POST /app/hospitalizacion/triage
     * Exclusivo Guardia, Veterinario, Emergencia y SuperAdmin.
     */
    public function triage(): void
    {
        $this->requireAuth(self::CLINIC_ROLES);
        $this->requireValidCsrf();

        try {
            $hospId = $this->hospModel->registrarIngresoTriage($_POST, (int) ($_SESSION['user_id'] ?? 1));
            $this->setFlash('success', 'Paciente ingresado en Hospitalización / Triage correctamente.');
            $this->redirect("/app/hospitalizacion/paciente/{$hospId}");
        } catch (\Throwable $e) {
            $this->setFlash('error', 'No se pudo registrar el ingreso: ' . $e->getMessage());
            $this->redirect('/app/hospitalizacion');
        }
    }

    /**
     * GET /app/hospitalizacion/paciente/{id}
     * Vista de evolución clínica del paciente internado y su bitácora por turnos.
     */
    public function ver(string $id = ''): void
    {
        $this->requireAuth(self::VIEW_ROLES);

        $hospId  = (int) $id;
        $detalle = $this->hospModel->getDetalle($hospId);

        if (!$detalle) {
            $this->setFlash('error', 'Registro de hospitalización no encontrado.');
            $this->redirect('/app/hospitalizacion');
        }

        $canClinic = $this->auth->hasRole(self::CLINIC_ROLES);

        $this->render('hospitalizacion/detalle', [
            'title'        => 'Internado: ' . $detalle['paciente_nombre'] . ' — ' . Config::APP_NAME,
            'pageTitle'    => 'Evolución de Paciente Internado',
            'activeMenu'   => 'hospitalizacion',
            'auth'         => $this->auth,
            'user'         => $this->auth->getCurrentUser() ?? ($_SESSION['user'] ?? []),
            'hosp'         => $detalle,
            'canClinic'    => $canClinic,
            'medicamentos' => $canClinic ? $this->inventarioModel->getMedicamentosDisponibles() : [],
            'csrfToken'    => $this->generateToken(),
        ]);
    }

    /**
     * POST /app/hospitalizacion/{id}/bitacora
     * Registra signos vitales del turno, observaciones y medicamentos administrados
     * sumándolos automáticamente a la cuenta del cliente para POS.
     */
    public function registrarBitacora(string $id = ''): void
    {
        $this->requireAuth(self::CLINIC_ROLES);
        $this->requireValidCsrf();

        $hospId = (int) $id;

        try {
            $this->hospModel->registrarBitacoraTurno($hospId, $_POST, (int) ($_SESSION['user_id'] ?? 1));
            $this->setFlash('success', 'Evolución de turno registrada en la bitácora.');
        } catch (\Throwable $e) {
            $this->setFlash('error', 'No se pudo registrar la evolución: ' . $e->getMessage());
        }

        $this->redirect("/app/hospitalizacion/paciente/{$hospId}");
    }

    /**
     * POST /app/hospitalizacion/{id}/alta
     * Da de alta médica al paciente, pasa la jaula a "En Limpieza"
     * y marca la cuenta como lista para cobro en el POS.
     */
    public function alta(string $id = ''): void
    {
        $this->requireAuth(self::CLINIC_ROLES);
        $this->requireValidCsrf();

        $hospId      = (int) $id;
        $estadoFinal = trim((string) $this->input('estado_final', 'alta'));

        try {
            $this->hospModel->darAltaMedica($hospId, (int) ($_SESSION['user_id'] ?? 1), $estadoFinal);
            $this->setFlash('success', 'Alta médica procesada. La jaula pasó a Limpieza y la cuenta está lista para cobro en POS.');
        } catch (\Throwable $e) {
            $this->setFlash('error', 'No se pudo procesar el alta: ' . $e->getMessage());
        }

        $this->redirect('/app/hospitalizacion');
    }

    /**
     * POST /app/hospitalizacion/jaula/limpiar
     * Libera una jaula que estaba en limpieza para volver a usarla.
     */
    public function limpiarJaula(): void
    {
        $this->requireAuth(self::VIEW_ROLES);
        $this->requireValidCsrf();

        $codigo = trim((string) $this->input('cama_numero', ''));
        if ($codigo !== '') {
            $this->hospModel->liberarJaulaLimpia($codigo);
            $this->setFlash('success', "Jaula {$codigo} marcada como Libre y desinfectada.");
        }

        $this->redirect('/app/hospitalizacion');
    }
}


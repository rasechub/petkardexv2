<?php
/**
 * PetKardex v2 - AgendaController
 *
 * Calendario interactivo (FullCalendar) con diferenciación por color
 * entre Citas Médicas, Vacunación y Estética (Grooming), y filtrado
 * estricto por rol.
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
use PetKardex\Models\Agenda;

class AgendaController extends Controller
{
    private Auth $auth;
    private Agenda $agendaModel;

    private const READ_ROLES   = ['superadmin', 'recepcionista', 'veterinario', 'guardia', 'groomer', 'propietario'];
    private const MANAGE_ROLES = ['superadmin', 'recepcionista', 'veterinario'];

    public function __construct()
    {
        parent::__construct();
        $this->auth        = new Auth();
        $this->agendaModel = new Agenda();
    }

    /**
     * GET /app/agenda | /app/portal/citas
     * Vista principal del calendario interactivo.
     */
    public function index(): void
    {
        $this->requireAuth(self::READ_ROLES);

        $user      = $this->auth->getCurrentUser() ?? ($_SESSION['user'] ?? []);
        $canManage = $this->auth->hasRole(self::MANAGE_ROLES);

        $this->render('agenda/index', [
            'title'         => 'Agenda y Citas — ' . Config::APP_NAME,
            'pageTitle'     => $this->auth->hasRole('propietario') ? 'Mis Próximas Citas' : 'Agenda General de Citas',
            'activeMenu'    => $this->auth->hasRole('propietario') ? 'citas' : 'agenda',
            'auth'          => $this->auth,
            'user'          => $user,
            'canManage'     => $canManage,
            'pacientes'     => $canManage ? $this->agendaModel->getPacientesParaSelect() : [],
            'profesionales' => $canManage ? $this->agendaModel->getProfesionales() : [],
            'csrfToken'     => $this->generateToken(),
        ]);
    }

    /**
     * GET /app/agenda/eventos?start=...&end=...
     * Endpoint JSON consumido por FullCalendar, filtrado por rol desde servidor.
     */
    public function eventos(): void
    {
        $this->requireAuth(self::READ_ROLES);

        $user    = $this->auth->getCurrentUser() ?? ($_SESSION['user'] ?? []);
        $rolSlug = (string) ($user['rol_slug'] ?? '');
        $userId  = (int) ($user['id'] ?? 0);
        $start   = trim((string) $this->query('start', ''));
        $end     = trim((string) $this->query('end', ''));

        $eventos = $this->agendaModel->getEventosFullCalendar($rolSlug, $userId, $start, $end);
        $this->json($eventos);
    }

    /**
     * POST /app/agenda/crear
     * Crea una nueva cita (sólo SuperAdmin, Recepcionista y Veterinario).
     */
    public function crear(): void
    {
        $this->requireAuth(self::MANAGE_ROLES);
        $this->requireValidCsrf();

        try {
            $this->agendaModel->crearCita($_POST, (int) ($_SESSION['user_id'] ?? 1));
            $this->setFlash('success', 'Cita agendada correctamente en el calendario.');
        } catch (\Throwable $e) {
            $this->setFlash('error', 'No se pudo agendar la cita: ' . $e->getMessage());
        }

        $this->redirect('/app/agenda');
    }

    /**
     * POST /app/agenda/{id}/estado
     * Cambia el estado de una cita (confirmada, en_proceso, completada, cancelada).
     */
    public function cambiarEstado(string $id = ''): void
    {
        $this->requireAuth(['superadmin', 'recepcionista', 'veterinario', 'groomer']);
        $this->requireValidCsrf();

        $citaId = (int) $id;
        $estado = trim((string) $this->input('estado', 'confirmada'));

        try {
            $this->agendaModel->actualizarEstado($citaId, $estado);
            if ($this->isAjax()) {
                $this->json(['success' => true]);
            }
            $this->setFlash('success', 'Estado de la cita actualizado.');
        } catch (\Throwable $e) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'error' => $e->getMessage()], 422);
            }
            $this->setFlash('error', $e->getMessage());
        }

        $this->redirect('/app/agenda');
    }
}


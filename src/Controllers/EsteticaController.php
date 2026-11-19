<?php
/**
 * PetKardex v2 - EsteticaController
 *
 * Interfaz exclusiva para Estilista / Groomer (y SuperAdmin).
 * Muestra únicamente las citas de baño/corte del día actual y permite
 * emitir Alertas Médicas internas visibles al Veterinario y en el Kardex.
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
use PetKardex\Models\Estetica;

class EsteticaController extends Controller
{
    private Auth $auth;
    private Estetica $esteticaModel;

    private const GROOMER_ROLES = ['groomer', 'superadmin'];
    private const ALERT_READERS = ['superadmin', 'veterinario', 'guardia', 'emergencia'];

    public function __construct()
    {
        parent::__construct();
        $this->auth          = new Auth();
        $this->esteticaModel = new Estetica();
    }

    /**
     * GET /app/estetica | /app/grooming/dashboard
     * Muestra la lista de pacientes agendados para estética del día actual.
     */
    public function dashboard(): void
    {
        $this->requireAuth(self::GROOMER_ROLES);

        $citasHoy = $this->esteticaModel->getAgendaDelDia();

        $this->render('estetica/dashboard', [
            'title'      => 'Agenda Estética Hoy — ' . Config::APP_NAME,
            'pageTitle'  => 'Estética y Grooming · Turnos de Hoy',
            'activeMenu' => 'estetica',
            'auth'       => $this->auth,
            'user'       => $this->auth->getCurrentUser() ?? ($_SESSION['user'] ?? []),
            'citasHoy'   => $citasHoy,
            'csrfToken'  => $this->generateToken(),
        ]);
    }

    /**
     * POST /app/estetica/{id}/estado
     * Cambia el estado de la cita estética (en_proceso, completada, cancelada).
     */
    public function actualizarEstado(string $id = ''): void
    {
        $this->requireAuth(self::GROOMER_ROLES);
        $this->requireValidCsrf();

        $aeId   = (int) $id;
        $estado = trim((string) $this->input('estado', 'en_proceso'));

        if (!in_array($estado, ['programada', 'en_proceso', 'completada', 'cancelada'], true)) {
            $this->setFlash('error', 'Estado inválido.');
            $this->redirect('/app/estetica');
        }

        $this->esteticaModel->update($aeId, ['estado' => $estado]);
        $this->setFlash('success', 'Estado del servicio de estética actualizado.');
        $this->redirect('/app/estetica');
    }

    /**
     * POST /app/estetica/alerta
     * Emite una Alerta Interna de Salud hacia el Veterinario de turno y el Kardex.
     */
    public function emitirAlerta(): void
    {
        $this->requireAuth(self::GROOMER_ROLES);
        $this->requireValidCsrf();

        $pacienteId = (int) $this->input('paciente_id', 0);
        $agendaId   = (int) $this->input('agenda_estetica_id', 0);
        $hallazgo   = trim((string) $this->input('hallazgo', ''));
        $detalle    = trim((string) $this->input('mensaje', ''));
        $prioridad  = trim((string) $this->input('prioridad', 'alta'));

        if ($pacienteId <= 0 || ($hallazgo === '' && $detalle === '')) {
            $this->setFlash('error', 'Indica el paciente y describe el hallazgo clínico observado.');
            $this->redirect('/app/estetica');
        }

        $titulo  = $hallazgo !== '' ? "Alerta Estética: {$hallazgo}" : 'Alerta de Salud desde Estética';
        $mensaje = $detalle !== '' ? $detalle : $hallazgo;

        $this->esteticaModel->emitirAlertaSalud(
            (int) ($_SESSION['user_id'] ?? 1),
            $pacienteId,
            $agendaId > 0 ? $agendaId : null,
            $titulo,
            $mensaje,
            $prioridad
        );

        $this->setFlash('success', 'Alerta de salud enviada al equipo veterinario y registrada en el Kardex del paciente.');
        $this->redirect('/app/estetica');
    }

    /**
     * POST /app/alertas/{id}/leer
     * Marca una alerta médica interna como revisada por el veterinario/guardia.
     */
    public function marcarAlertaLeida(string $id = ''): void
    {
        $this->requireAuth(self::ALERT_READERS);
        $this->requireValidCsrf();

        $alertaId = (int) $id;
        if ($alertaId > 0) {
            $this->esteticaModel->marcarAlertaLeida($alertaId);
            $this->setFlash('success', 'Alerta médica marcada como atendida.');
        }

        $referer = $_SERVER['HTTP_REFERER'] ?? '/app/dashboard';
        $this->redirect($referer);
    }
}


<?php
/**
 * PetKardex v2 - Modelo Agenda
 *
 * Gestiona citas médicas, vacunaciones y citas de estética con filtrado
 * estricto por rol (Propietario solo ve sus citas; Recepción/Admin gestionan todo).
 *
 * @author    CESAR AQUINO ANGLAS - VI B NOCHE
 * @copyright 2026 PetKardex. Todos los derechos reservados.
 * @license   Propietaria - IESTP Santiago Antúnez de Mayolo (IESTP SAM)
 */

declare(strict_types=1);

namespace PetKardex\Models;

use PetKardex\Core\Model;

class Agenda extends Model
{
    protected string $table = 'citas';

    protected array $fillable = [
        'paciente_id',
        'propietario_id',
        'veterinario_id',
        'recepcionista_id',
        'tipo_servicio',
        'fecha_hora',
        'duracion_minutos',
        'estado',
        'notas',
        'recordatorio_enviado',
    ];

    /**
     * Retorna eventos formateados para FullCalendar v6 con colores por tipo de cita
     * y filtro estricto según el rol del usuario autenticado.
     */
    public function getEventosFullCalendar(string $rolSlug, int $userId, string $start = '', string $end = ''): array
    {
        $sql = "SELECT c.*, p.nombre AS paciente_nombre, e.nombre AS especie_nombre,
                       pr.nombre AS prop_nombre, pr.apellido AS prop_apellido,
                       u.nombre AS vet_nombre, u.apellido AS vet_apellido
                FROM citas c
                INNER JOIN pacientes p ON p.id = c.paciente_id
                INNER JOIN especies e ON e.id = p.especie_id
                INNER JOIN propietarios pr ON pr.id = c.propietario_id
                LEFT JOIN usuarios u ON u.id = c.veterinario_id
                WHERE 1=1";
        $params = [];

        if ($start !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $start)) {
            $sql .= " AND c.fecha_hora >= :start";
            $params[':start'] = substr($start, 0, 10) . ' 00:00:00';
        }
        if ($end !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $end)) {
            $sql .= " AND c.fecha_hora <= :end";
            $params[':end'] = substr($end, 0, 10) . ' 23:59:59';
        }

        // Regla ACL de visibilidad: Propietario SOLO ve sus propias citas
        if ($rolSlug === 'propietario') {
            $sql .= " AND pr.usuario_id = :uid";
            $params[':uid'] = $userId;
        } elseif ($rolSlug === 'groomer') {
            $sql .= " AND c.tipo_servicio = 'estetica'";
        }

        $sql .= " ORDER BY c.fecha_hora ASC";

        $rows    = $this->db->fetchAll($sql, $params);
        $eventos = [];

        foreach ($rows as $row) {
            [$bg, $border, $text] = $this->coloresPorTipo((string) $row['tipo_servicio']);
            $startIso = date('c', strtotime((string) $row['fecha_hora']));
            $duracion = max(15, (int) ($row['duracion_minutos'] ?? 30));
            $endIso   = date('c', strtotime((string) $row['fecha_hora']) + ($duracion * 60));

            $tipoLabel = match ($row['tipo_servicio']) {
                'vacunacion'      => 'Vacuna',
                'estetica'        => 'Estética',
                'emergencia'      => 'Emergencia',
                'cirugia'         => 'Cirugía',
                'hospitalizacion' => 'Hosp.',
                default           => 'Médica',
            };

            $eventos[] = [
                'id'              => (int) $row['id'],
                'title'           => "[{$tipoLabel}] {$row['paciente_nombre']}",
                'start'           => $startIso,
                'end'             => $endIso,
                'backgroundColor' => $bg,
                'borderColor'     => $border,
                'textColor'       => $text,
                'extendedProps'   => [
                    'paciente_id'    => (int) $row['paciente_id'],
                    'paciente'       => $row['paciente_nombre'],
                    'especie'        => $row['especie_nombre'],
                    'propietario'    => trim($row['prop_nombre'] . ' ' . $row['prop_apellido']),
                    'profesional'    => $row['vet_nombre'] ? trim($row['vet_nombre'] . ' ' . $row['vet_apellido']) : 'Por asignar',
                    'tipo_servicio'  => $row['tipo_servicio'],
                    'estado'         => $row['estado'],
                    'notas'          => $row['notas'] ?? '',
                ],
            ];
        }

        return $eventos;
    }

    /**
     * Agenda una nueva cita y si es de tipo estética crea también su turno en agenda_estetica.
     */
    public function crearCita(array $data, int $creadorId): int
    {
        $pacienteId   = (int) ($data['paciente_id'] ?? 0);
        $tipoServicio = (string) ($data['tipo_servicio'] ?? 'consulta');
        $fechaHora    = trim((string) ($data['fecha_hora'] ?? ''));

        if ($fechaHora === '' && !empty($data['fecha'])) {
            $fechaPart = trim((string) $data['fecha']);
            $horaPart  = trim((string) ($data['hora'] ?? '09:00'));
            $fechaHora = $fechaPart . ' ' . $horaPart;
        }

        $duracion     = max(15, (int) ($data['duracion_minutos'] ?? 30));
        $profesional  = !empty($data['veterinario_id']) ? (int) $data['veterinario_id'] : null;
        $notas        = trim((string) ($data['notas'] ?? ''));

        $allowed = ['consulta', 'vacunacion', 'cirugia', 'estetica', 'control', 'emergencia', 'hospitalizacion'];
        if (!in_array($tipoServicio, $allowed, true)) {
            $tipoServicio = 'consulta';
        }

        if ($pacienteId <= 0 || $fechaHora === '') {
            throw new \InvalidArgumentException('Paciente y fecha/hora de la cita son obligatorios.');
        }

        $paciente = $this->db->fetchOne(
            "SELECT id, propietario_id FROM pacientes WHERE id = :id AND activo = 1 LIMIT 1",
            [':id' => $pacienteId]
        );
        if (!$paciente) {
            throw new \RuntimeException('Paciente no encontrado.');
        }

        $fechaFormatted = date('Y-m-d H:i:s', strtotime($fechaHora));

        return $this->db->transaction(function () use (
            $paciente,
            $pacienteId,
            $profesional,
            $creadorId,
            $tipoServicio,
            $fechaFormatted,
            $duracion,
            $notas
        ): int {
            $citaId = $this->create([
                'paciente_id'          => $pacienteId,
                'propietario_id'       => (int) $paciente['propietario_id'],
                'veterinario_id'       => $profesional,
                'recepcionista_id'     => $creadorId,
                'tipo_servicio'        => $tipoServicio,
                'fecha_hora'           => $fechaFormatted,
                'duracion_minutos'     => $duracion,
                'estado'               => 'programada',
                'notas'                => $notas !== '' ? $notas : null,
                'recordatorio_enviado' => 0,
            ]);

            // Sincronizar con agenda_estetica si el servicio es de grooming
            if ($tipoServicio === 'estetica') {
                $groomerId = $profesional;
                if (!$groomerId) {
                    $g = $this->db->fetchOne(
                        "SELECT u.id FROM usuarios u
                         INNER JOIN roles r ON r.id = u.rol_id
                         WHERE r.slug = 'groomer' AND u.activo = 1
                         LIMIT 1"
                    );
                    $groomerId = $g ? (int) $g['id'] : $creadorId;
                }

                $this->db->execute(
                    "INSERT INTO agenda_estetica
                        (cita_id, paciente_id, groomer_id, fecha_hora, servicios, observaciones_previas, estado, created_at)
                     VALUES
                        (:cid, :pid, :gid, :fh, :serv, :obs, 'programada', NOW())",
                    [
                        ':cid'  => $citaId,
                        ':pid'  => $pacienteId,
                        ':gid'  => $groomerId,
                        ':fh'   => $fechaFormatted,
                        ':serv' => json_encode(['Baño y Corte Estético'], JSON_UNESCAPED_UNICODE),
                        ':obs'  => $notas !== '' ? $notas : null,
                    ]
                );
            }

            return $citaId;
        });
    }

    /**
     * Actualiza el estado de una cita y su vínculo en agenda_estetica si existe.
     */
    public function actualizarEstado(int $citaId, string $estado): void
    {
        $allowed = ['programada', 'confirmada', 'en_proceso', 'completada', 'cancelada', 'no_asistio'];
        if (!in_array($estado, $allowed, true)) {
            throw new \InvalidArgumentException('Estado de cita no válido.');
        }

        $this->update($citaId, ['estado' => $estado]);

        $estMap = match ($estado) {
            'en_proceso'            => 'en_proceso',
            'completada'            => 'completada',
            'cancelada', 'no_asistio' => 'cancelada',
            default                 => 'programada',
        };
        $this->db->execute(
            "UPDATE agenda_estetica SET estado = :est WHERE cita_id = :cid",
            [':est' => $estMap, ':cid' => $citaId]
        );
    }

    public function getProfesionales(): array
    {
        return $this->db->fetchAll(
            "SELECT u.id, u.nombre, u.apellido, r.slug AS rol_slug, r.nombre AS rol_nombre
             FROM usuarios u
             INNER JOIN roles r ON r.id = u.rol_id
             WHERE u.activo = 1 AND r.slug IN ('veterinario', 'guardia', 'groomer')
             ORDER BY r.id ASC, u.nombre ASC"
        );
    }

    public function getPacientesParaSelect(): array
    {
        return $this->db->fetchAll(
            "SELECT p.id, p.nombre, e.nombre AS especie_nombre,
                    pr.nombre AS propietario_nombre, pr.apellido AS propietario_apellido
             FROM pacientes p
             INNER JOIN especies e ON e.id = p.especie_id
             INNER JOIN propietarios pr ON pr.id = p.propietario_id
             WHERE p.activo = 1
             ORDER BY p.nombre ASC"
        );
    }

    private function coloresPorTipo(string $tipo): array
    {
        return match ($tipo) {
            'vacunacion' => ['#10B981', '#059669', '#FFFFFF'],
            'estetica'   => ['#C48CFF', '#9333EA', '#0D0D0D'],
            'emergencia' => ['#EF4444', '#DC2626', '#FFFFFF'],
            default      => ['#7B52AB', '#4C1D95', '#FFFFFF'], // Citas médicas
        };
    }
}


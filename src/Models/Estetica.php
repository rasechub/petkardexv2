<?php
/**
 * PetKardex v2 - Modelo Estetica & Alertas Internas
 *
 * Gestiona la agenda diaria exclusiva del rol Groomer/Estilista y la
 * emisión de Alertas Médicas hacia el Veterinario de turno y el Kardex.
 *
 * @author    CESAR AQUINO ANGLAS - VI B NOCHE
 * @copyright 2026 PetKardex. Todos los derechos reservados.
 * @license   Propietaria - IESTP Santiago Antúnez de Mayolo (IESTP SAM)
 */

declare(strict_types=1);

namespace PetKardex\Models;

use PetKardex\Core\Model;

class Estetica extends Model
{
    protected string $table = 'agenda_estetica';

    protected bool $hasUpdatedAt = false;

    protected array $fillable = [
        'cita_id',
        'paciente_id',
        'groomer_id',
        'fecha_hora',
        'servicios',
        'observaciones_previas',
        'alertas_medicas',
        'estado',
        'alertas_enviadas',
    ];

    /**
     * Retorna ÚNICAMENTE los pacientes agendados para baño/corte del día actual.
     */
    public function getAgendaDelDia(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT ae.*, p.nombre AS paciente_nombre, p.peso_kg, p.alertas_medicas AS paciente_alertas,
                    e.nombre AS especie_nombre, r.nombre AS raza_nombre,
                    pr.nombre AS propietario_nombre, pr.apellido AS propietario_apellido,
                    pr.celular AS propietario_celular, pr.telefono AS propietario_telefono,
                    u.nombre AS groomer_nombre, u.apellido AS groomer_apellido
             FROM agenda_estetica ae
             INNER JOIN pacientes p ON p.id = ae.paciente_id
             INNER JOIN especies e ON e.id = p.especie_id
             LEFT JOIN razas r ON r.id = p.raza_id
             INNER JOIN propietarios pr ON pr.id = p.propietario_id
             INNER JOIN usuarios u ON u.id = ae.groomer_id
             WHERE DATE(ae.fecha_hora) = CURDATE()
             ORDER BY ae.fecha_hora ASC"
        );

        foreach ($rows as &$row) {
            $decoded                 = json_decode((string) ($row['servicios'] ?? '[]'), true);
            $row['servicios_lista'] = is_array($decoded) ? $decoded : [];
        }
        unset($row);

        return $rows;
    }

    /**
     * Emite una Alerta Interna de Salud desde Estética hacia el equipo Veterinario/Guardia
     * para que aparezca destacada en el Dashboard médico y en el Kardex del paciente.
     */
    public function emitirAlertaSalud(
        int    $origenUsuarioId,
        int    $pacienteId,
        ?int   $agendaEsteticaId,
        string $titulo,
        string $mensaje,
        string $prioridad = 'alta'
    ): int {
        if (!in_array($prioridad, ['baja', 'media', 'alta', 'critica'], true)) {
            $prioridad = 'alta';
        }

        return $this->db->transaction(function () use (
            $origenUsuarioId,
            $pacienteId,
            $agendaEsteticaId,
            $titulo,
            $mensaje,
            $prioridad
        ): int {
            $this->db->execute(
                "INSERT INTO alertas_internas
                    (origen_usuario_id, destino_usuario_id, rol_destino, paciente_id, tipo, titulo, mensaje, prioridad, leida, created_at)
                 VALUES
                    (:orig, NULL, 'veterinario', :pid, 'medica', :tit, :msg, :prio, 0, NOW())",
                [
                    ':orig' => $origenUsuarioId,
                    ':pid'  => $pacienteId,
                    ':tit'  => trim($titulo),
                    ':msg'  => trim($mensaje),
                    ':prio' => $prioridad,
                ]
            );
            $alertaId = $this->db->lastInsertId();

            if ($agendaEsteticaId !== null && $agendaEsteticaId > 0) {
                $this->db->execute(
                    "UPDATE agenda_estetica
                     SET alertas_enviadas = 1,
                         alertas_medicas = CONCAT(COALESCE(alertas_medicas, ''), :nota)
                     WHERE id = :id",
                    [
                        ':nota' => ($titulo !== '' ? "[{$titulo}] " : '') . $mensaje . "\n",
                        ':id'   => $agendaEsteticaId,
                    ]
                );
            }

            return $alertaId;
        });
    }

    /**
     * Alertas médicas internas pendientes de lectura para el Dashboard de Veterinarios/Guardias.
     */
    public static function getAlertasMedicasPendientes(int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        $db    = \PetKardex\Config\Database::getInstance();

        return $db->fetchAll(
            "SELECT a.*, p.nombre AS paciente_nombre,
                    u.nombre AS origen_nombre, u.apellido AS origen_apellido,
                    r.nombre AS origen_rol
             FROM alertas_internas a
             LEFT JOIN pacientes p ON p.id = a.paciente_id
             INNER JOIN usuarios u ON u.id = a.origen_usuario_id
             INNER JOIN roles r ON r.id = u.rol_id
             WHERE a.leida = 0 AND a.tipo IN ('medica', 'emergencia')
             ORDER BY FIELD(a.prioridad, 'critica', 'alta', 'media', 'baja'), a.created_at DESC
             LIMIT {$limit}"
        );
    }

    /**
     * Historial de alertas internas emitidas sobre un paciente (visible en su Kardex).
     */
    public static function getAlertasPorPaciente(int $pacienteId): array
    {
        $db = \PetKardex\Config\Database::getInstance();

        return $db->fetchAll(
            "SELECT a.*, u.nombre AS origen_nombre, u.apellido AS origen_apellido, r.nombre AS origen_rol
             FROM alertas_internas a
             INNER JOIN usuarios u ON u.id = a.origen_usuario_id
             INNER JOIN roles r ON r.id = u.rol_id
             WHERE a.paciente_id = :pid AND a.tipo IN ('medica', 'emergencia')
             ORDER BY a.created_at DESC
             LIMIT 20",
            [':pid' => $pacienteId]
        );
    }

    /**
     * Marca una alerta médica como atendida/leída.
     */
    public function marcarAlertaLeida(int $alertaId): void
    {
        $this->db->execute(
            "UPDATE alertas_internas SET leida = 1, fecha_lectura = NOW() WHERE id = :id",
            [':id' => $alertaId]
        );
    }
}


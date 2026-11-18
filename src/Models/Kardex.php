<?php
/**
 * PetKardex v2 - Modelo Kardex
 *
 * Centraliza consultas del historial clínico, cirugías previas, vacunaciones,
 * recetas médicas e invariante legal de inmutabilidad pasadas 24 horas.
 */

declare(strict_types=1);

namespace PetKardex\Models;

use PetKardex\Core\Model;

class Kardex extends Model
{
    protected string $table = 'historial_clinico';

    protected bool $hasUpdatedAt = false;

    protected array $fillable = [
        'paciente_id',
        'veterinario_id',
        'fecha_consulta',
        'tipo_consulta',
        'motivo',
        'anamnesis',
        'examen_fisico',
        'diagnostico',
        'diagnostico_cie',
        'tratamiento',
        'observaciones',
        'proximo_control',
    ];

    /** Ventana máxima de edición/eliminación legal: 24 horas (86,400 segundos) */
    public const IMMUTABILITY_WINDOW_SECONDS = 86400;

    /**
     * Determina si un registro clínico o de vacunación aún es editable (< 24 horas desde creación).
     */
    public static function esEditable(string $createdAt): bool
    {
        $ts = strtotime($createdAt);
        if ($ts === false) {
            return false;
        }
        return (time() - $ts) <= self::IMMUTABILITY_WINDOW_SECONDS;
    }

    /**
     * Obtiene los datos completos del paciente con especie, raza y propietario.
     */
    public function getPacienteCompleto(int $pacienteId): ?array
    {
        return $this->db->fetchOne(
            "SELECT p.*, e.nombre AS especie_nombre, r.nombre AS raza_nombre,
                    pr.id AS prop_id, pr.usuario_id AS propietario_usuario_id,
                    pr.nombre AS propietario_nombre, pr.apellido AS propietario_apellido,
                    pr.dni AS propietario_dni, pr.telefono AS propietario_telefono,
                    pr.celular AS propietario_celular, pr.email AS propietario_email,
                    pr.direccion AS propietario_direccion
             FROM pacientes p
             INNER JOIN especies e ON e.id = p.especie_id
             LEFT JOIN razas r ON r.id = p.raza_id
             INNER JOIN propietarios pr ON pr.id = p.propietario_id
             WHERE p.id = :id AND p.activo = 1
             LIMIT 1",
            [':id' => $pacienteId]
        );
    }

    /**
     * Obtiene el historial cronológico de consultas junto con sus recetas e ítems.
     */
    public function getConsultasConRecetas(int $pacienteId): array
    {
        $consultas = $this->db->fetchAll(
            "SELECT h.*, u.nombre AS vet_nombre, u.apellido AS vet_apellido
             FROM historial_clinico h
             INNER JOIN usuarios u ON u.id = h.veterinario_id
             WHERE h.paciente_id = :pid
             ORDER BY h.fecha_consulta DESC, h.id DESC",
            [':pid' => $pacienteId]
        );

        if (empty($consultas)) {
            return [];
        }

        $recetas = $this->db->fetchAll(
            "SELECT r.*, ri.id AS item_id, ri.producto_id, ri.medicamento_nombre,
                    ri.presentacion, ri.dosis, ri.frecuencia, ri.duracion,
                    ri.cantidad_dispensada, ri.notas AS item_notas
             FROM recetas r
             LEFT JOIN receta_items ri ON ri.receta_id = r.id
             WHERE r.paciente_id = :pid
             ORDER BY r.id DESC, ri.id ASC",
            [':pid' => $pacienteId]
        );

        $recetasPorHistorial = [];
        foreach ($recetas as $row) {
            $hid = (int) $row['historial_id'];
            if (!isset($recetasPorHistorial[$hid])) {
                $recetasPorHistorial[$hid] = [
                    'id'            => (int) $row['id'],
                    'fecha'         => $row['fecha'],
                    'instrucciones' => $row['instrucciones'],
                    'valida_hasta'  => $row['valida_hasta'],
                    'items'         => [],
                ];
            }
            if (!empty($row['item_id'])) {
                $recetasPorHistorial[$hid]['items'][] = [
                    'id'                  => (int) $row['item_id'],
                    'producto_id'         => $row['producto_id'] ? (int) $row['producto_id'] : null,
                    'medicamento_nombre'  => $row['medicamento_nombre'],
                    'presentacion'        => $row['presentacion'],
                    'dosis'               => $row['dosis'],
                    'frecuencia'          => $row['frecuencia'],
                    'duracion'            => $row['duracion'],
                    'cantidad_dispensada' => $row['cantidad_dispensada'],
                    'notas'               => $row['item_notas'],
                ];
            }
        }

        foreach ($consultas as &$c) {
            $hid              = (int) $c['id'];
            $c['examen']      = json_decode((string) ($c['examen_fisico'] ?? '{}'), true) ?? [];
            $c['receta']      = $recetasPorHistorial[$hid] ?? null;
            $c['es_editable'] = self::esEditable((string) $c['created_at']);
        }
        unset($c);

        return $consultas;
    }

    /**
     * Obtiene cirugías previas registradas en el historial del paciente.
     */
    public function getCirugiasPrevias(int $pacienteId): array
    {
        return $this->db->fetchAll(
            "SELECT h.id, h.fecha_consulta, h.motivo, h.diagnostico, h.tratamiento,
                    u.nombre AS vet_nombre, u.apellido AS vet_apellido
             FROM historial_clinico h
             INNER JOIN usuarios u ON u.id = h.veterinario_id
             WHERE h.paciente_id = :pid AND h.tipo_consulta = 'cirugia'
             ORDER BY h.fecha_consulta DESC",
            [':pid' => $pacienteId]
        );
    }

    /**
     * Obtiene todas las vacunaciones aplicadas al paciente ordenadas cronológicamente.
     */
    public function getVacunaciones(int $pacienteId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT v.*, vc.nombre AS vacuna_nombre, vc.laboratorio AS vacuna_laboratorio,
                    vc.enfermedad_prevenida, vc.via_administracion,
                    u.nombre AS vet_nombre, u.apellido AS vet_apellido
             FROM vacunaciones v
             INNER JOIN vacunas_catalogo vc ON vc.id = v.vacuna_id
             INNER JOIN usuarios u ON u.id = v.veterinario_id
             WHERE v.paciente_id = :pid
             ORDER BY v.fecha DESC, v.id DESC",
            [':pid' => $pacienteId]
        );

        foreach ($rows as &$row) {
            $row['es_editable'] = self::esEditable((string) $row['created_at']);
        }
        unset($row);

        return $rows;
    }

    /**
     * Catálogo activo de vacunas para los formularios médicos.
     */
    public function getVacunasCatalogo(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM vacunas_catalogo WHERE activo = 1 ORDER BY nombre ASC"
        );
    }

    /**
     * Configuración general de la clínica para membretes PDF.
     */
    public function getClinicaInfo(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT clave, valor FROM configuracion_sistema
             WHERE clave IN ('nombre_clinica','slogan_clinica','direccion','telefono','telefono_emergencias','email')"
        );
        $info = [];
        foreach ($rows as $row) {
            $info[$row['clave']] = $row['valor'];
        }
        return $info;
    }
}


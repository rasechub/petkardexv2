<?php
/**
 * PetKardex v2 - Modelo Paciente
 *
 * Gestiona el listado general de mascotas, búsqueda por nombre/DNI/QR/microchip,
 * catálogos de especies/razas y registro completo de nuevas mascotas con QR UUID.
 *
 * @author    CESAR AQUINO ANGLAS - VI B NOCHE
 * @copyright 2026 PetKardex. Todos los derechos reservados.
 * @license   Propietaria - IESTP Santiago Antúnez de Mayolo (IESTP SAM)
 */

declare(strict_types=1);

namespace PetKardex\Models;

use PetKardex\Core\Model;

class Paciente extends Model
{
    protected string $table = 'pacientes';

    protected bool $hasCreatedAt = false; // tabla usa fecha_registro DEFAULT CURRENT_TIMESTAMP

    protected array $fillable = [
        'propietario_id',
        'especie_id',
        'raza_id',
        'nombre',
        'fecha_nacimiento',
        'sexo',
        'color',
        'peso_kg',
        'microchip',
        'foto',
        'qr_code',
        'qr_imagen',
        'alertas_medicas',
        'sangre_tipo',
        'esterilizado',
        'activo',
    ];

    /**
     * Retorna la lista de todas las mascotas activas con especie, raza y propietario.
     */
    public function getAllPacientes(string $search = ''): array
    {
        // Respaldo de seguridad: asegurar que toda mascota registrada tenga UUID v4 asignado
        $this->asegurarUuidsFaltantes();

        $sql = "SELECT p.*, e.nombre AS especie_nombre, r.nombre AS raza_nombre,
                       pr.nombre AS propietario_nombre, pr.apellido AS propietario_apellido,
                       pr.dni AS propietario_dni, pr.celular AS propietario_celular,
                       pr.telefono AS propietario_telefono
                FROM pacientes p
                INNER JOIN especies e ON e.id = p.especie_id
                LEFT JOIN razas r ON r.id = p.raza_id
                INNER JOIN propietarios pr ON pr.id = p.propietario_id
                WHERE p.activo = 1 AND pr.activo = 1";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (p.nombre LIKE :q1 OR p.microchip LIKE :q2 OR p.qr_code LIKE :q3
                           OR pr.dni LIKE :q4 OR CONCAT(pr.nombre, ' ', pr.apellido) LIKE :q5)";
            $like          = '%' . $search . '%';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
            $params[':q4'] = $like;
            $params[':q5'] = $like;
        }

        $sql .= " ORDER BY p.fecha_registro DESC, p.id DESC";

        return $this->db->fetchAll($sql, $params);
    }

    public function getEspecies(): array
    {
        return $this->db->fetchAll("SELECT id, nombre FROM especies WHERE activo = 1 ORDER BY id ASC");
    }

    public function getRazas(): array
    {
        return $this->db->fetchAll("SELECT id, especie_id, nombre FROM razas WHERE activo = 1 ORDER BY nombre ASC");
    }

    public function getPropietarios(): array
    {
        return $this->db->fetchAll(
            "SELECT id, nombre, apellido, dni, celular, telefono FROM propietarios WHERE activo = 1 ORDER BY nombre ASC, apellido ASC"
        );
    }

    /**
     * Registra una nueva mascota (y opcionalmente un nuevo propietario) asignando UUID v4 único para QR.
     */
    public function registrarPaciente(array $data, ?string $fotoRuta = null): int
    {
        $nombre    = trim((string) ($data['nombre'] ?? ''));
        $especieId = (int) ($data['especie_id'] ?? 0);
        $razaId    = !empty($data['raza_id']) ? (int) $data['raza_id'] : null;
        $sexo      = (string) ($data['sexo'] ?? 'desconocido');

        if ($nombre === '' || $especieId <= 0) {
            throw new \InvalidArgumentException('El nombre de la mascota y la especie son obligatorios.');
        }

        if (!in_array($sexo, ['macho', 'hembra', 'desconocido'], true)) {
            $sexo = 'desconocido';
        }

        return $this->db->transaction(function () use ($data, $nombre, $especieId, $razaId, $sexo, $fotoRuta): int {
            $propietarioId = !empty($data['propietario_id']) ? (int) $data['propietario_id'] : 0;
            $modoProp      = (string) ($data['propietario_modo'] ?? ($propietarioId > 0 ? 'existente' : 'nuevo'));

            if ($modoProp === 'nuevo' || $propietarioId <= 0) {
                $propNombre   = trim((string) ($data['prop_nombre'] ?? ''));
                $propApellido = trim((string) ($data['prop_apellido'] ?? ''));
                $propDni      = trim((string) ($data['prop_dni'] ?? ''));
                $propCelular  = trim((string) ($data['prop_celular'] ?? ''));
                $propEmail    = trim((string) ($data['prop_email'] ?? ''));

                if ($propNombre === '' || $propApellido === '' || $propDni === '') {
                    throw new \InvalidArgumentException('Selecciona un propietario existente o completa Nombre, Apellido y DNI del nuevo propietario.');
                }

                $existente = $this->db->fetchOne(
                    "SELECT id FROM propietarios WHERE dni = :dni LIMIT 1",
                    [':dni' => $propDni]
                );

                if ($existente) {
                    $propietarioId = (int) $existente['id'];
                } else {
                    $this->db->execute(
                        "INSERT INTO propietarios (nombre, apellido, email, celular, dni, activo, created_at, updated_at)
                         VALUES (:nom, :ape, :email, :cel, :dni, 1, NOW(), NOW())",
                        [
                            ':nom'   => $propNombre,
                            ':ape'   => $propApellido,
                            ':email' => $propEmail !== '' ? strtolower($propEmail) : null,
                            ':cel'   => $propCelular !== '' ? $propCelular : null,
                            ':dni'   => $propDni,
                        ]
                    );
                    $propietarioId = $this->db->lastInsertId();
                }
            }

            $uuid         = $this->generateUuidV4();
            $fechaNac     = trim((string) ($data['fecha_nacimiento'] ?? ''));
            $pesoRaw      = trim((string) ($data['peso_kg'] ?? ''));
            $color        = trim((string) ($data['color'] ?? ''));
            $microchip    = trim((string) ($data['microchip'] ?? ''));
            $sangreTipo   = trim((string) ($data['sangre_tipo'] ?? ''));
            $alertas      = trim((string) ($data['alertas_medicas'] ?? ''));
            $esterilizado = isset($data['esterilizado']) ? 1 : 0;

            return $this->create([
                'propietario_id'   => $propietarioId,
                'especie_id'       => $especieId,
                'raza_id'          => $razaId,
                'nombre'           => $nombre,
                'fecha_nacimiento' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaNac) ? $fechaNac : null,
                'sexo'             => $sexo,
                'color'            => $color !== '' ? $color : null,
                'peso_kg'          => $pesoRaw !== '' ? (float) $pesoRaw : null,
                'microchip'        => $microchip !== '' ? $microchip : null,
                'foto'             => $fotoRuta,
                'qr_code'          => $uuid,
                'alertas_medicas'  => $alertas !== '' ? $alertas : null,
                'sangre_tipo'      => $sangreTipo !== '' ? $sangreTipo : null,
                'esterilizado'     => $esterilizado,
                'activo'           => 1,
            ]);
        });
    }

    /**
     * Obtiene una mascota activa con datos de especie, raza y propietario para edición.
     */
    public function getPacienteParaEditar(int $id): ?array
    {
        return $this->db->fetchOne(
            "SELECT p.*, e.nombre AS especie_nombre, r.nombre AS raza_nombre,
                    pr.nombre AS propietario_nombre, pr.apellido AS propietario_apellido,
                    pr.dni AS propietario_dni
             FROM pacientes p
             INNER JOIN especies e ON e.id = p.especie_id
             LEFT JOIN razas r ON r.id = p.raza_id
             INNER JOIN propietarios pr ON pr.id = p.propietario_id
             WHERE p.id = :id AND p.activo = 1
             LIMIT 1",
            [':id' => $id]
        );
    }

    /**
     * Actualiza los datos clínicos, básicos y fotografía opcional de una mascota.
     */
    public function actualizarPaciente(int $id, array $data, ?string $nuevaFotoRuta = null): void
    {
        $actual = $this->findById($id);
        if (!$actual || (int) ($actual['activo'] ?? 0) !== 1) {
            throw new \RuntimeException('La mascota solicitada no existe o fue retirada.');
        }

        $nombre        = trim((string) ($data['nombre'] ?? ''));
        $especieId     = (int) ($data['especie_id'] ?? 0);
        $razaId        = !empty($data['raza_id']) ? (int) $data['raza_id'] : null;
        $propietarioId = !empty($data['propietario_id']) ? (int) $data['propietario_id'] : (int) $actual['propietario_id'];
        $sexo          = strtolower(trim((string) ($data['sexo'] ?? 'desconocido')));

        if ($nombre === '' || $especieId <= 0 || $propietarioId <= 0) {
            throw new \InvalidArgumentException('El nombre, la especie y el propietario de la mascota son obligatorios.');
        }

        if (!in_array($sexo, ['macho', 'hembra', 'desconocido'], true)) {
            $sexo = 'desconocido';
        }

        $fechaNac     = trim((string) ($data['fecha_nacimiento'] ?? ''));
        $pesoRaw      = trim((string) ($data['peso_kg'] ?? ''));
        $color        = trim((string) ($data['color'] ?? ''));
        $microchip    = trim((string) ($data['microchip'] ?? ''));
        $sangreTipo   = trim((string) ($data['sangre_tipo'] ?? ''));
        $alertas      = trim((string) ($data['alertas_medicas'] ?? ''));
        $esterilizado = isset($data['esterilizado']) ? 1 : 0;

        $payload = [
            'propietario_id'   => $propietarioId,
            'especie_id'       => $especieId,
            'raza_id'          => $razaId,
            'nombre'           => $nombre,
            'fecha_nacimiento' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaNac) ? $fechaNac : null,
            'sexo'             => $sexo,
            'color'            => $color !== '' ? $color : null,
            'peso_kg'          => $pesoRaw !== '' ? (float) $pesoRaw : null,
            'microchip'        => $microchip !== '' ? $microchip : null,
            'alertas_medicas'  => $alertas !== '' ? $alertas : null,
            'sangre_tipo'      => $sangreTipo !== '' ? $sangreTipo : null,
            'esterilizado'     => $esterilizado,
        ];

        if ($nuevaFotoRuta !== null) {
            $payload['foto'] = $nuevaFotoRuta;
        }

        // Si la mascota carece de UUID QR, asignarle uno v4 de inmediato
        if (empty($actual['qr_code'])) {
            $payload['qr_code'] = $this->generateUuidV4();
        }

        $this->update($id, $payload);
    }

    /**
     * Asegura que todas las mascotas activas tengan un UUID v4 asignado en qr_code.
     * Retorna la cantidad de mascotas actualizadas.
     */
    public function asegurarUuidsFaltantes(): int
    {
        $sinUuid = $this->db->fetchAll(
            "SELECT id FROM pacientes WHERE qr_code IS NULL OR TRIM(qr_code) = ''"
        );
        $count = 0;
        foreach ($sinUuid as $row) {
            $newUuid = $this->generateUuidV4();
            $this->db->execute(
                "UPDATE pacientes SET qr_code = :uuid, updated_at = NOW() WHERE id = :id",
                [':uuid' => $newUuid, ':id' => (int) $row['id']]
            );
            $count++;
        }
        return $count;
    }

    private function generateUuidV4(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}


<?php
/**
 * PetKardex v2 - Modelo Usuario
 *
 * Gestiona el registro y administración de personal clínico (Veterinarios,
 * Guardias, Recepcionistas, Estilistas) y Propietarios (con DNI vinculado).
 */

declare(strict_types=1);

namespace PetKardex\Models;

use PetKardex\Core\Model;
use PetKardex\Helpers\Security;

class Usuario extends Model
{
    protected string $table = 'usuarios';

    protected array $fillable = [
        'rol_id',
        'nombre',
        'apellido',
        'email',
        'password_hash',
        'telefono',
        'dni',
        'foto',
        'activo',
    ];

    /**
     * Asigna automáticamente un DNI aleatorio único de 8 dígitos a usuarios
     * y propietarios antiguos que tengan el campo vacío o nulo.
     */
    public function asignarDnisFaltantes(): int
    {
        $sinDni = $this->db->fetchAll(
            "SELECT u.id, pr.dni AS prop_dni
             FROM usuarios u
             LEFT JOIN propietarios pr ON pr.usuario_id = u.id
             WHERE u.dni IS NULL OR TRIM(u.dni) = '' OR u.dni NOT REGEXP '^[0-9]{8}$'"
        );

        $propsSinDni = $this->db->fetchAll(
            "SELECT id, usuario_id
             FROM propietarios
             WHERE dni IS NULL OR TRIM(dni) = ''"
        );

        if (empty($sinDni) && empty($propsSinDni)) {
            return 0;
        }

        $actualizados = 0;

        foreach ($sinDni as $row) {
            $candidato = trim((string) ($row['prop_dni'] ?? ''));
            $esValido  = preg_match('/^\d{8}$/', $candidato) === 1;

            if ($esValido) {
                $yaExiste = $this->db->fetchOne(
                    "SELECT id FROM usuarios WHERE dni = :dni AND id <> :id LIMIT 1",
                    [':dni' => $candidato, ':id' => (int) $row['id']]
                );
                if ($yaExiste) {
                    $esValido = false;
                }
            }

            if (!$esValido) {
                $candidato = $this->generarDniUnico8Digitos();
            }

            $this->db->execute(
                "UPDATE usuarios SET dni = :dni, updated_at = NOW() WHERE id = :id",
                [':dni' => $candidato, ':id' => (int) $row['id']]
            );
            $actualizados++;
        }

        foreach ($propsSinDni as $prRow) {
            $nuevoDni = null;
            if (!empty($prRow['usuario_id'])) {
                $uRow = $this->db->fetchOne(
                    "SELECT dni FROM usuarios WHERE id = :uid LIMIT 1",
                    [':uid' => (int) $prRow['usuario_id']]
                );
                $uDni = trim((string) ($uRow['dni'] ?? ''));
                if (preg_match('/^\d{8}$/', $uDni) === 1) {
                    $dup = $this->db->fetchOne(
                        "SELECT id FROM propietarios WHERE dni = :dni AND id <> :pid LIMIT 1",
                        [':dni' => $uDni, ':pid' => (int) $prRow['id']]
                    );
                    if (!$dup) {
                        $nuevoDni = $uDni;
                    }
                }
            }

            $nuevoDni = $nuevoDni ?? $this->generarDniUnico8Digitos();
            $this->db->execute(
                "UPDATE propietarios SET dni = :dni, updated_at = NOW() WHERE id = :pid",
                [':dni' => $nuevoDni, ':pid' => (int) $prRow['id']]
            );
            $actualizados++;
        }

        return $actualizados;
    }

    /**
     * Genera un DNI numérico de 8 dígitos garantizando unicidad en usuarios y propietarios.
     */
    private function generarDniUnico8Digitos(): string
    {
        for ($i = 0; $i < 50; $i++) {
            $dni = (string) random_int(10000000, 99999999);
            $enUsuario = $this->db->fetchOne("SELECT id FROM usuarios WHERE dni = :dni LIMIT 1", [':dni' => $dni]);
            $enProp    = $this->db->fetchOne("SELECT id FROM propietarios WHERE dni = :dni LIMIT 1", [':dni' => $dni]);
            if (!$enUsuario && !$enProp) {
                return $dni;
            }
        }
        return str_pad((string) random_int(1, 99999999), 8, '0', STR_PAD_LEFT);
    }

    /**
     * Retorna todos los usuarios con su rol, DNI y dirección de propietario si aplica.
     */
    public function getAllUsuarios(string $search = '', ?int $rolId = null): array
    {
        $sql = "SELECT u.id, u.rol_id, u.nombre, u.apellido, u.email, u.telefono,
                       COALESCE(NULLIF(u.dni, ''), pr.dni) AS dni,
                       u.activo, u.ultimo_login, u.created_at,
                       r.nombre AS rol_nombre, r.slug AS rol_slug,
                       pr.dni AS propietario_dni, pr.direccion AS propietario_direccion
                FROM usuarios u
                INNER JOIN roles r ON r.id = u.rol_id
                LEFT JOIN propietarios pr ON pr.usuario_id = u.id
                WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (u.nombre LIKE :q1 OR u.apellido LIKE :q2 OR u.email LIKE :q3 OR pr.dni LIKE :q4 OR u.dni LIKE :q5)";
            $like          = '%' . $search . '%';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
            $params[':q4'] = $like;
            $params[':q5'] = $like;
        }

        if ($rolId !== null && $rolId > 0) {
            $sql .= " AND u.rol_id = :rid";
            $params[':rid'] = $rolId;
        }

        $sql .= " ORDER BY u.activo DESC, r.id ASC, u.nombre ASC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Retorna los roles que el usuario actual puede asignar.
     */
    public function getRolesParaCrear(string $currentRolSlug): array
    {
        $slugs = $currentRolSlug === 'superadmin'
            ? ['propietario', 'veterinario', 'recepcionista', 'groomer', 'guardia', 'superadmin']
            : ['propietario', 'veterinario', 'recepcionista', 'groomer', 'guardia'];

        $placeholders = implode(',', array_fill(0, count($slugs), '?'));
        return $this->db->fetchAll(
            "SELECT id, nombre, slug, descripcion FROM roles WHERE activo = 1 AND slug IN ({$placeholders}) ORDER BY id ASC",
            $slugs
        );
    }

    /**
     * Crea un usuario (personal o propietario con DNI) dentro de una transacción PDO.
     */
    public function crearUsuario(array $data, string $currentRolSlug): int
    {
        $rolId     = (int) ($data['rol_id'] ?? 0);
        $nombre    = trim((string) ($data['nombre'] ?? ''));
        $apellido  = trim((string) ($data['apellido'] ?? ''));
        $email     = strtolower(trim((string) ($data['email'] ?? '')));
        $password  = (string) ($data['password'] ?? '');
        $telefono  = trim((string) ($data['telefono'] ?? ''));
        $dni       = trim((string) ($data['dni'] ?? ''));
        $direccion = trim((string) ($data['direccion'] ?? ''));

        if ($rolId <= 0 || $nombre === '' || $apellido === '' || $email === '' || $password === '') {
            throw new \InvalidArgumentException('Todos los campos obligatorios deben completarse.');
        }

        if (!Security::validateEmail($email)) {
            throw new \InvalidArgumentException('El formato del correo electrónico no es válido.');
        }

        $pwdErrors = Security::validatePassword($password);
        if (!empty($pwdErrors)) {
            throw new \InvalidArgumentException(implode(' ', $pwdErrors));
        }

        $rol = $this->db->fetchOne(
            "SELECT id, slug, nombre FROM roles WHERE id = :id AND activo = 1 LIMIT 1",
            [':id' => $rolId]
        );
        if (!$rol) {
            throw new \InvalidArgumentException('El rol seleccionado no es válido.');
        }

        if ($rol['slug'] === 'superadmin' && $currentRolSlug !== 'superadmin') {
            throw new \RuntimeException('Solo un Super Administrador puede crear otro SuperAdmin.');
        }

        if ($this->findOne(['email' => $email])) {
            throw new \RuntimeException("El correo electrónico \"{$email}\" ya se encuentra registrado.");
        }

        if (preg_match('/^\d{8}$/', $dni) !== 1) {
            throw new \InvalidArgumentException('El DNI es obligatorio para todos los roles y debe tener exactamente 8 dígitos numéricos.');
        }

        if ($this->findOne(['dni' => $dni])) {
            throw new \RuntimeException("El DNI \"{$dni}\" ya se encuentra registrado en otra cuenta de usuario.");
        }

        $hash = Security::hashPassword($password, 12);

        return $this->db->transaction(function () use (
            $rol,
            $rolId,
            $nombre,
            $apellido,
            $email,
            $hash,
            $telefono,
            $dni,
            $direccion
        ): int {
            $nuevoUsuarioId = $this->create([
                'rol_id'        => $rolId,
                'nombre'        => $nombre,
                'apellido'      => $apellido,
                'email'         => $email,
                'password_hash' => $hash,
                'telefono'      => $telefono !== '' ? $telefono : null,
                'dni'           => $dni,
                'activo'        => 1,
            ]);

            if ($rol['slug'] === 'propietario') {
                $propExistente = $this->db->fetchOne(
                    "SELECT id, usuario_id FROM propietarios WHERE dni = :dni LIMIT 1",
                    [':dni' => $dni]
                );

                if ($propExistente) {
                    if (!empty($propExistente['usuario_id'])) {
                        throw new \RuntimeException("El DNI \"{$dni}\" ya está vinculado a otra cuenta de usuario.");
                    }
                    $this->db->execute(
                        "UPDATE propietarios
                         SET usuario_id = :uid, nombre = :nom, apellido = :ape, email = :email,
                             celular = :cel, direccion = :dir, updated_at = NOW()
                         WHERE id = :pid",
                        [
                            ':uid'   => $nuevoUsuarioId,
                            ':nom'   => $nombre,
                            ':ape'   => $apellido,
                            ':email' => $email,
                            ':cel'   => $telefono !== '' ? $telefono : null,
                            ':dir'   => $direccion !== '' ? $direccion : null,
                            ':pid'   => (int) $propExistente['id'],
                        ]
                    );
                } else {
                    $this->db->execute(
                        "INSERT INTO propietarios (usuario_id, nombre, apellido, email, celular, direccion, dni, activo, created_at, updated_at)
                         VALUES (:uid, :nom, :ape, :email, :cel, :dir, :dni, 1, NOW(), NOW())",
                        [
                            ':uid'   => $nuevoUsuarioId,
                            ':nom'   => $nombre,
                            ':ape'   => $apellido,
                            ':email' => $email,
                            ':cel'   => $telefono !== '' ? $telefono : null,
                            ':dir'   => $direccion !== '' ? $direccion : null,
                            ':dni'   => $dni,
                        ]
                    );
                }
            }

            return $nuevoUsuarioId;
        });
    }

    /**
     * Actualiza los datos de un usuario existente, incluyendo DNI (8 dígitos),
     * rol (con control RBAC), perfil de propietario si corresponde y contraseña opcional.
     */
    public function actualizarUsuario(int $id, array $data, string $currentRolSlug): void
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Usuario inválido.');
        }

        $actual = $this->db->fetchOne(
            "SELECT u.*, r.slug AS rol_slug
             FROM usuarios u
             INNER JOIN roles r ON r.id = u.rol_id
             WHERE u.id = :id LIMIT 1",
            [':id' => $id]
        );
        if (!$actual) {
            throw new \RuntimeException('Usuario no encontrado.');
        }

        if ($actual['rol_slug'] === 'superadmin' && $currentRolSlug !== 'superadmin') {
            throw new \RuntimeException('Solo un Super Administrador puede modificar cuentas SuperAdmin.');
        }

        $rolId     = (int) ($data['rol_id'] ?? $actual['rol_id']);
        $nombre    = trim((string) ($data['nombre'] ?? ''));
        $apellido  = trim((string) ($data['apellido'] ?? ''));
        $email     = strtolower(trim((string) ($data['email'] ?? '')));
        $telefono  = trim((string) ($data['telefono'] ?? ''));
        $dni       = trim((string) ($data['dni'] ?? ''));
        $direccion = trim((string) ($data['direccion'] ?? ''));
        $password  = (string) ($data['password'] ?? '');

        if ($rolId <= 0 || $nombre === '' || $apellido === '' || $email === '') {
            throw new \InvalidArgumentException('Rol, nombre, apellido y correo electrónico son obligatorios.');
        }

        if (!Security::validateEmail($email)) {
            throw new \InvalidArgumentException('El formato del correo electrónico no es válido.');
        }

        if (preg_match('/^\d{8}$/', $dni) !== 1) {
            throw new \InvalidArgumentException('El DNI es obligatorio y debe tener exactamente 8 dígitos numéricos.');
        }

        $rol = $this->db->fetchOne(
            "SELECT id, slug, nombre FROM roles WHERE id = :id AND activo = 1 LIMIT 1",
            [':id' => $rolId]
        );
        if (!$rol) {
            throw new \InvalidArgumentException('El rol seleccionado no es válido.');
        }

        if ($rol['slug'] === 'superadmin' && $currentRolSlug !== 'superadmin') {
            throw new \RuntimeException('Solo un Super Administrador puede asignar el rol SuperAdmin.');
        }

        $dupEmail = $this->db->fetchOne(
            "SELECT id FROM usuarios WHERE email = :email AND id <> :id LIMIT 1",
            [':email' => $email, ':id' => $id]
        );
        if ($dupEmail) {
            throw new \RuntimeException("El correo electrónico \"{$email}\" pertenece a otro usuario.");
        }

        $dupDni = $this->db->fetchOne(
            "SELECT id FROM usuarios WHERE dni = :dni AND id <> :id LIMIT 1",
            [':dni' => $dni, ':id' => $id]
        );
        if ($dupDni) {
            throw new \RuntimeException("El DNI \"{$dni}\" pertenece a otro usuario.");
        }

        $updatePayload = [
            'rol_id'   => $rolId,
            'nombre'   => $nombre,
            'apellido' => $apellido,
            'email'    => $email,
            'telefono' => $telefono !== '' ? $telefono : null,
            'dni'      => $dni,
        ];

        if ($password !== '') {
            $pwdErrors = Security::validatePassword($password);
            if (!empty($pwdErrors)) {
                throw new \InvalidArgumentException(implode(' ', $pwdErrors));
            }
            $updatePayload['password_hash'] = Security::hashPassword($password, 12);
        }

        $this->db->transaction(function () use (
            $id,
            $rol,
            $updatePayload,
            $nombre,
            $apellido,
            $email,
            $telefono,
            $dni,
            $direccion
        ): void {
            $this->update($id, $updatePayload);

            $propVinculado = $this->db->fetchOne(
                "SELECT id FROM propietarios WHERE usuario_id = :uid LIMIT 1",
                [':uid' => $id]
            );

            $dupPropDni = $this->db->fetchOne(
                "SELECT id, usuario_id FROM propietarios WHERE dni = :dni AND (usuario_id IS NULL OR usuario_id <> :uid) LIMIT 1",
                [':dni' => $dni, ':uid' => $id]
            );

            if ($dupPropDni && !empty($dupPropDni['usuario_id'])) {
                throw new \RuntimeException("El DNI \"{$dni}\" ya está vinculado a otro propietario.");
            }

            if ($propVinculado) {
                $this->db->execute(
                    "UPDATE propietarios
                     SET nombre = :nom, apellido = :ape, email = :email, celular = :cel,
                         dni = :dni, direccion = COALESCE(:dir, direccion), updated_at = NOW()
                     WHERE id = :pid",
                    [
                        ':nom'   => $nombre,
                        ':ape'   => $apellido,
                        ':email' => $email,
                        ':cel'   => $telefono !== '' ? $telefono : null,
                        ':dni'   => $dni,
                        ':dir'   => $direccion !== '' ? $direccion : null,
                        ':pid'   => (int) $propVinculado['id'],
                    ]
                );
            } elseif ($rol['slug'] === 'propietario') {
                if ($dupPropDni) {
                    $this->db->execute(
                        "UPDATE propietarios
                         SET usuario_id = :uid, nombre = :nom, apellido = :ape, email = :email,
                             celular = :cel, direccion = COALESCE(:dir, direccion), updated_at = NOW()
                         WHERE id = :pid",
                        [
                            ':uid'   => $id,
                            ':nom'   => $nombre,
                            ':ape'   => $apellido,
                            ':email' => $email,
                            ':cel'   => $telefono !== '' ? $telefono : null,
                            ':dir'   => $direccion !== '' ? $direccion : null,
                            ':pid'   => (int) $dupPropDni['id'],
                        ]
                    );
                } else {
                    $this->db->execute(
                        "INSERT INTO propietarios (usuario_id, nombre, apellido, email, celular, direccion, dni, activo, created_at, updated_at)
                         VALUES (:uid, :nom, :ape, :email, :cel, :dir, :dni, 1, NOW(), NOW())",
                        [
                            ':uid'   => $id,
                            ':nom'   => $nombre,
                            ':ape'   => $apellido,
                            ':email' => $email,
                            ':cel'   => $telefono !== '' ? $telefono : null,
                            ':dir'   => $direccion !== '' ? $direccion : null,
                            ':dni'   => $dni,
                        ]
                    );
                }
            }
        });
    }

    /**
     * Activa o desactiva un usuario sin permitir desactivar la propia sesión activa.
     */
    public function toggleEstado(int $id, int $currentUserId): int
    {
        if ($id === $currentUserId) {
            throw new \RuntimeException('No puedes desactivar tu propio usuario activo.');
        }

        $user = $this->findById($id);
        if (!$user) {
            throw new \RuntimeException('Usuario no encontrado.');
        }

        $nuevoEstado = ((int) $user['activo'] === 1) ? 0 : 1;
        $this->update($id, ['activo' => $nuevoEstado]);
        return $nuevoEstado;
    }
}


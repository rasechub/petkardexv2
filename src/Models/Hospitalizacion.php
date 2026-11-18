<?php
/**
 * PetKardex v2 - Modelo Hospitalizacion
 *
 * Gestiona el mapa de jaulas/caniles (Ocupada, Libre, En Limpieza),
 * ingresos de triage/emergencia, bitácora de signos vitales por turno,
 * acumulación de medicamentos en cuenta pendiente POS y alta médica.
 */

declare(strict_types=1);

namespace PetKardex\Models;

use PetKardex\Core\Model;

class Hospitalizacion extends Model
{
    protected string $table = 'hospitalizaciones';

    protected array $fillable = [
        'paciente_id',
        'veterinario_id',
        'fecha_ingreso',
        'fecha_egreso',
        'motivo_ingreso',
        'diagnostico_ingreso',
        'estado',
        'cama_numero',
    ];

    /** Catálogo de caniles/jaulas físicas de la clínica */
    public const JAULAS_CATALOGO = [
        'J-01', 'J-02', 'J-03', 'J-04',
        'J-05', 'J-06', 'J-07', 'J-08',
        'UCI-01', 'UCI-02', 'AISL-01', 'AISL-02',
    ];

    public const TARIFA_DIA_HOSPITALIZACION = 120.00;

    /**
     * Retorna el mapa completo de jaulas con su estado: 'ocupada', 'limpieza' o 'libre'.
     */
    public function getMapaJaulas(): array
    {
        $activas = $this->db->fetchAll(
            "SELECT h.*, p.nombre AS paciente_nombre, p.foto AS paciente_foto,
                    p.alertas_medicas, e.nombre AS especie_nombre,
                    pr.nombre AS propietario_nombre, pr.apellido AS propietario_apellido,
                    u.nombre AS vet_nombre, u.apellido AS vet_apellido
             FROM hospitalizaciones h
             INNER JOIN pacientes p ON p.id = h.paciente_id
             INNER JOIN especies e ON e.id = p.especie_id
             INNER JOIN propietarios pr ON pr.id = p.propietario_id
             INNER JOIN usuarios u ON u.id = h.veterinario_id
             WHERE h.estado = 'activo'
             ORDER BY h.fecha_ingreso DESC"
        );

        $ocupadasPorJaula = [];
        foreach ($activas as $row) {
            $jaula = (string) ($row['cama_numero'] ?? '');
            if ($jaula !== '' && !isset($ocupadasPorJaula[$jaula])) {
                $ocupadasPorJaula[$jaula] = $row;
            }
        }

        $enLimpieza = $this->getJaulasEnLimpieza();
        $mapa       = [];

        foreach (self::JAULAS_CATALOGO as $codigo) {
            if (isset($ocupadasPorJaula[$codigo])) {
                $mapa[] = [
                    'codigo'          => $codigo,
                    'estado'          => 'ocupada',
                    'hospitalizacion' => $ocupadasPorJaula[$codigo],
                ];
            } elseif (in_array($codigo, $enLimpieza, true)) {
                $mapa[] = [
                    'codigo'          => $codigo,
                    'estado'          => 'limpieza',
                    'hospitalizacion' => null,
                ];
            } else {
                $mapa[] = [
                    'codigo'          => $codigo,
                    'estado'          => 'libre',
                    'hospitalizacion' => null,
                ];
            }
        }

        return $mapa;
    }

    /**
     * Lista pacientes activos para el selector de Triage / Ingreso.
     */
    public function getPacientesActivos(): array
    {
        return $this->db->fetchAll(
            "SELECT p.id, p.nombre, p.peso_kg, e.nombre AS especie_nombre,
                    pr.nombre AS propietario_nombre, pr.apellido AS propietario_apellido, pr.dni
             FROM pacientes p
             INNER JOIN especies e ON e.id = p.especie_id
             INNER JOIN propietarios pr ON pr.id = p.propietario_id
             WHERE p.activo = 1
             ORDER BY p.nombre ASC"
        );
    }

    /**
     * Registra un ingreso de triage/hospitalización, sus signos vitales iniciales
     * y abre su cuenta pendiente para acumular cargos hacia Caja/POS.
     */
    public function registrarIngresoTriage(array $data, int $usuarioId): int
    {
        $pacienteId = (int) ($data['paciente_id'] ?? 0);
        $jaula      = trim((string) ($data['cama_numero'] ?? ''));
        $motivo     = trim((string) ($data['motivo_ingreso'] ?? ''));
        $diag       = trim((string) ($data['diagnostico_ingreso'] ?? ''));
        $peso       = $data['peso_actual'] !== '' ? (float) $data['peso_actual'] : null;
        $conciencia = (string) ($data['estado_conciencia'] ?? 'alerta');
        $temp       = $data['temperatura'] !== '' ? (float) $data['temperatura'] : null;
        $fc         = $data['frecuencia_cardiaca'] !== '' ? (int) $data['frecuencia_cardiaca'] : null;
        $fr         = $data['frecuencia_respiratoria'] !== '' ? (int) $data['frecuencia_respiratoria'] : null;

        if ($pacienteId <= 0 || $jaula === '' || $motivo === '') {
            throw new \InvalidArgumentException('Paciente, jaula asignada y motivo son obligatorios.');
        }

        if (!in_array($conciencia, ['alerta', 'deprimido', 'estuporoso', 'comatoso'], true)) {
            $conciencia = 'alerta';
        }

        return $this->db->transaction(function () use (
            $pacienteId,
            $usuarioId,
            $jaula,
            $motivo,
            $diag,
            $peso,
            $conciencia,
            $temp,
            $fc,
            $fr
        ): int {
            // Verificar que la jaula no esté ocupada
            $ocupada = $this->db->fetchOne(
                "SELECT id FROM hospitalizaciones WHERE cama_numero = :cama AND estado = 'activo' LIMIT 1 FOR UPDATE",
                [':cama' => $jaula]
            );
            if ($ocupada) {
                throw new \RuntimeException("La jaula {$jaula} ya se encuentra ocupada.");
            }

            $hospId = $this->create([
                'paciente_id'         => $pacienteId,
                'veterinario_id'      => $usuarioId,
                'fecha_ingreso'       => date('Y-m-d H:i:s'),
                'motivo_ingreso'      => $motivo,
                'diagnostico_ingreso' => $diag !== '' ? $diag : null,
                'estado'              => 'activo',
                'cama_numero'         => $jaula,
            ]);

            $this->db->execute(
                "INSERT INTO signos_vitales
                    (hospitalizacion_id, usuario_id, fecha_hora, temperatura, frecuencia_cardiaca,
                     frecuencia_respiratoria, peso_actual, estado_conciencia, observaciones, created_at)
                 VALUES
                    (:hid, :uid, NOW(), :temp, :fc, :fr, :peso, :conc, :obs, NOW())",
                [
                    ':hid'  => $hospId,
                    ':uid'  => $usuarioId,
                    ':temp' => $temp,
                    ':fc'   => $fc,
                    ':fr'   => $fr,
                    ':peso' => $peso,
                    ':conc' => $conciencia,
                    ':obs'  => 'Ingreso de Triage / Hospitalización',
                ]
            );

            if ($peso !== null && $peso > 0) {
                $this->db->execute(
                    "UPDATE pacientes SET peso_kg = :peso, updated_at = NOW() WHERE id = :id",
                    [':peso' => $peso, ':id' => $pacienteId]
                );
            }

            // Quitar de limpieza si estaba en limpieza
            $this->removerJaulaLimpieza($jaula);

            // Crear cuenta pendiente inicial en ventas para acumular medicamentos y estadía
            $this->ensureCuentaPendienteHospitalizacion($hospId, $pacienteId, $usuarioId);

            return $hospId;
        });
    }

    /**
     * Retorna el detalle completo de una hospitalización, signos vitales y cuenta acumulada.
     */
    public function getDetalle(int $hospId): ?array
    {
        $hosp = $this->db->fetchOne(
            "SELECT h.*, p.nombre AS paciente_nombre, p.peso_kg, p.alertas_medicas, p.qr_code,
                    e.nombre AS especie_nombre, r.nombre AS raza_nombre,
                    pr.id AS prop_id, pr.nombre AS propietario_nombre, pr.apellido AS propietario_apellido,
                    pr.dni AS propietario_dni, pr.celular AS propietario_celular, pr.telefono AS propietario_telefono,
                    u.nombre AS vet_nombre, u.apellido AS vet_apellido
             FROM hospitalizaciones h
             INNER JOIN pacientes p ON p.id = h.paciente_id
             INNER JOIN especies e ON e.id = p.especie_id
             LEFT JOIN razas r ON r.id = p.raza_id
             INNER JOIN propietarios pr ON pr.id = p.propietario_id
             INNER JOIN usuarios u ON u.id = h.veterinario_id
             WHERE h.id = :id
             LIMIT 1",
            [':id' => $hospId]
        );

        if (!$hosp) {
            return null;
        }

        $hosp['bitacora'] = $this->db->fetchAll(
            "SELECT s.*, u.nombre AS usuario_nombre, u.apellido AS usuario_apellido
             FROM signos_vitales s
             INNER JOIN usuarios u ON u.id = s.usuario_id
             WHERE s.hospitalizacion_id = :hid
             ORDER BY s.fecha_hora DESC, s.id DESC",
            [':hid' => $hospId]
        );

        $hosp['cuenta_pendiente'] = $this->getCuentaPendienteHospitalizacion($hospId);

        return $hosp;
    }

    /**
     * Registra evolución de signos vitales y medicamentos administrados en el turno,
     * descontando stock y cargándolos a la cuenta pendiente del propietario.
     */
    public function registrarBitacoraTurno(int $hospId, array $data, int $usuarioId): void
    {
        $hosp = $this->findById($hospId);
        if (!$hosp || $hosp['estado'] !== 'activo') {
            throw new \RuntimeException('La hospitalización no se encuentra activa.');
        }

        $temp        = ($data['temperatura'] ?? '') !== '' ? (float) $data['temperatura'] : null;
        $fc          = ($data['frecuencia_cardiaca'] ?? '') !== '' ? (int) $data['frecuencia_cardiaca'] : null;
        $fr          = ($data['frecuencia_respiratoria'] ?? '') !== '' ? (int) $data['frecuencia_respiratoria'] : null;
        $sat         = ($data['saturacion_oxigeno'] ?? '') !== '' ? (float) $data['saturacion_oxigeno'] : null;
        $conciencia  = (string) ($data['estado_conciencia'] ?? 'alerta');
        $obs         = trim((string) ($data['observaciones'] ?? ''));
        $productoId  = !empty($data['producto_id']) ? (int) $data['producto_id'] : 0;
        $cantidad    = !empty($data['cantidad']) ? max(0.5, (float) $data['cantidad']) : 1.0;

        if (!in_array($conciencia, ['alerta', 'deprimido', 'estuporoso', 'comatoso'], true)) {
            $conciencia = 'alerta';
        }

        $this->db->transaction(function () use (
            $hosp,
            $hospId,
            $usuarioId,
            $temp,
            $fc,
            $fr,
            $sat,
            $conciencia,
            $obs,
            $productoId,
            $cantidad
        ): void {
            $obsTexto = $obs;

            if ($productoId > 0) {
                $prod = $this->db->fetchOne(
                    "SELECT id, nombre, precio_venta FROM productos WHERE id = :id AND activo = 1 LIMIT 1",
                    [':id' => $productoId]
                );
                if (!$prod) {
                    throw new \RuntimeException('El medicamento seleccionado no existe o está inactivo.');
                }

                Inventario::descontarStock(
                    $productoId,
                    $cantidad,
                    $usuarioId,
                    'hospitalizacion',
                    $hospId,
                    "Administración en Hospitalización #{$hospId}"
                );

                $medNota  = "Medicamento administrado: {$prod['nombre']} (Cant: {$cantidad})";
                $obsTexto = $obsTexto !== '' ? "{$obsTexto}\n{$medNota}" : $medNota;

                $ventaId = $this->ensureCuentaPendienteHospitalizacion($hospId, (int) $hosp['paciente_id'], $usuarioId);
                $precio  = (float) $prod['precio_venta'];
                $sub     = round($precio * $cantidad, 2);

                $this->db->execute(
                    "INSERT INTO venta_items (venta_id, producto_id, descripcion, cantidad, precio_unitario, descuento_item, subtotal)
                     VALUES (:vid, :pid, :desc, :cant, :precio, 0, :sub)",
                    [
                        ':vid'    => $ventaId,
                        ':pid'    => $productoId,
                        ':desc'   => "Hosp. Med: {$prod['nombre']}",
                        ':cant'   => $cantidad,
                        ':precio' => $precio,
                        ':sub'    => $sub,
                    ]
                );
                $this->recalcularCuentaPendiente($ventaId);
            }

            $this->db->execute(
                "INSERT INTO signos_vitales
                    (hospitalizacion_id, usuario_id, fecha_hora, temperatura, frecuencia_cardiaca,
                     frecuencia_respiratoria, saturacion_oxigeno, estado_conciencia, observaciones, created_at)
                 VALUES
                    (:hid, :uid, NOW(), :temp, :fc, :fr, :sat, :conc, :obs, NOW())",
                [
                    ':hid'  => $hospId,
                    ':uid'  => $usuarioId,
                    ':temp' => $temp,
                    ':fc'   => $fc,
                    ':fr'   => $fr,
                    ':sat'  => $sat,
                    ':conc' => $conciencia,
                    ':obs'  => $obsTexto !== '' ? $obsTexto : 'Control de turno sin novedades',
                ]
            );
        });
    }

    /**
     * Da de alta médica al paciente, pasa la jaula a estado "En Limpieza"
     * y consolida la cuenta pendiente como lista para cobro en POS.
     */
    public function darAltaMedica(int $hospId, int $usuarioId, string $estadoFinal = 'alta'): void
    {
        if (!in_array($estadoFinal, ['alta', 'fallecido', 'referido'], true)) {
            $estadoFinal = 'alta';
        }

        $this->db->transaction(function () use ($hospId, $usuarioId, $estadoFinal): void {
            $hosp = $this->db->fetchOne(
                "SELECT * FROM hospitalizaciones WHERE id = :id LIMIT 1 FOR UPDATE",
                [':id' => $hospId]
            );

            if (!$hosp || $hosp['estado'] !== 'activo') {
                throw new \RuntimeException('La hospitalización ya fue cerrada.');
            }

            $this->db->execute(
                "UPDATE hospitalizaciones
                 SET estado = :est, fecha_egreso = NOW(), updated_at = NOW()
                 WHERE id = :id",
                [':est' => $estadoFinal, ':id' => $hospId]
            );

            // Pasar jaula a limpieza
            if (!empty($hosp['cama_numero'])) {
                $this->marcarJaulaEnLimpieza((string) $hosp['cama_numero']);
            }

            // Consolidar días de estadía en cuenta pendiente POS con tarifa dinámica desde BD
            $ventaId    = $this->ensureCuentaPendienteHospitalizacion($hospId, (int) $hosp['paciente_id'], $usuarioId);
            $dias       = max(1, (int) ceil((time() - strtotime((string) $hosp['fecha_ingreso'])) / 86400));
            $srvHosp    = Inventario::getServicioPorCodigoONombre('SRV-HOSP', '%hospitaliz%');
            $tarifaDia  = $srvHosp ? (float) $srvHosp['precio_venta'] : self::TARIFA_DIA_HOSPITALIZACION;
            $prodHospId = $srvHosp ? (int) $srvHosp['id'] : null;
            $sub        = round($dias * $tarifaDia, 2);

            $yaTieneEstadia = $this->db->fetchOne(
                "SELECT id FROM venta_items WHERE venta_id = :vid AND descripcion LIKE 'Estadía Hospitalización%' LIMIT 1",
                [':vid' => $ventaId]
            );

            if (!$yaTieneEstadia) {
                $this->db->execute(
                    "INSERT INTO venta_items (venta_id, producto_id, descripcion, cantidad, precio_unitario, descuento_item, subtotal)
                     VALUES (:vid, :pid, :desc, :cant, :precio, 0, :sub)",
                    [
                        ':vid'    => $ventaId,
                        ':pid'    => $prodHospId,
                        ':desc'   => "Estadía Hospitalización (Jaula {$hosp['cama_numero']})",
                        ':cant'   => $dias,
                        ':precio' => $tarifaDia,
                        ':sub'    => $sub,
                    ]
                );
            }

            $this->db->execute(
                "UPDATE ventas SET notas = :nota WHERE id = :id",
                [':nota' => "HOSP:{$hospId} | LISTA PARA COBRO EN POS", ':id' => $ventaId]
            );

            $this->recalcularCuentaPendiente($ventaId);
        });
    }

    /**
     * Marca una jaula en limpieza como libre y lista para nuevo paciente.
     */
    public function liberarJaulaLimpia(string $codigoJaula): void
    {
        $this->removerJaulaLimpieza($codigoJaula);
    }

    // ─── Helpers de Jaulas en Limpieza & Cuenta POS ─────────────────────────

    private function getJaulasEnLimpieza(): array
    {
        $row = $this->db->fetchOne(
            "SELECT valor FROM configuracion_sistema WHERE clave = 'jaulas_limpieza' LIMIT 1"
        );
        if (!$row || empty($row['valor'])) {
            return [];
        }
        $decoded = json_decode((string) $row['valor'], true);
        return is_array($decoded) ? $decoded : [];
    }

    private function saveJaulasEnLimpieza(array $lista): void
    {
        $json = json_encode(array_values(array_unique($lista)));
        $exists = $this->db->fetchOne(
            "SELECT id FROM configuracion_sistema WHERE clave = 'jaulas_limpieza' LIMIT 1"
        );
        if ($exists) {
            $this->db->execute(
                "UPDATE configuracion_sistema SET valor = :val, updated_at = NOW() WHERE clave = 'jaulas_limpieza'",
                [':val' => $json]
            );
        } else {
            $this->db->execute(
                "INSERT INTO configuracion_sistema (clave, valor, descripcion, grupo, updated_at)
                 VALUES ('jaulas_limpieza', :val, 'Jaulas en proceso de limpieza post-alta', 'clinica', NOW())",
                [':val' => $json]
            );
        }
    }

    private function marcarJaulaEnLimpieza(string $codigo): void
    {
        $lista   = $this->getJaulasEnLimpieza();
        $lista[] = $codigo;
        $this->saveJaulasEnLimpieza($lista);
    }

    private function removerJaulaLimpieza(string $codigo): void
    {
        $lista = array_filter($this->getJaulasEnLimpieza(), static fn($c) => $c !== $codigo);
        $this->saveJaulasEnLimpieza($lista);
    }

    private function getCuentaPendienteHospitalizacion(int $hospId): ?array
    {
        $venta = $this->db->fetchOne(
            "SELECT * FROM ventas WHERE estado = 'pendiente' AND notas LIKE :tag LIMIT 1",
            [':tag' => "HOSP:{$hospId}%"]
        );
        if (!$venta) {
            return null;
        }
        $venta['items'] = $this->db->fetchAll(
            "SELECT * FROM venta_items WHERE venta_id = :vid ORDER BY id ASC",
            [':vid' => (int) $venta['id']]
        );
        return $venta;
    }

    private function ensureCuentaPendienteHospitalizacion(int $hospId, int $pacienteId, int $usuarioId): int
    {
        $existing = $this->db->fetchOne(
            "SELECT id FROM ventas WHERE estado = 'pendiente' AND notas LIKE :tag LIMIT 1",
            [':tag' => "HOSP:{$hospId}%"]
        );
        if ($existing) {
            return (int) $existing['id'];
        }

        $owner = $this->db->fetchOne(
            "SELECT pr.id, pr.nombre, pr.apellido
             FROM pacientes p
             INNER JOIN propietarios pr ON pr.id = p.propietario_id
             WHERE p.id = :pid LIMIT 1",
            [':pid' => $pacienteId]
        );

        $num = 'H-' . date('Ymd') . '-' . $hospId . '-' . random_int(10, 99);
        $this->db->execute(
            "INSERT INTO ventas
                (numero_venta, cliente_tipo, propietario_id, cliente_nombre, cajero_id, fecha,
                 subtotal, descuento, impuesto, total, metodo_pago, estado, notas, created_at)
             VALUES
                (:num, 'propietario', :pid, :cname, :uid, NOW(), 0, 0, 0, 0, 'efectivo', 'pendiente', :notas, NOW())",
            [
                ':num'   => $num,
                ':pid'   => $owner ? (int) $owner['id'] : null,
                ':cname' => $owner ? trim($owner['nombre'] . ' ' . $owner['apellido']) : 'Propietario',
                ':uid'   => $usuarioId,
                ':notas' => "HOSP:{$hospId} | Hospitalización en curso",
            ]
        );

        return $this->db->lastInsertId();
    }

    private function recalcularCuentaPendiente(int $ventaId): void
    {
        $row = $this->db->fetchOne(
            "SELECT COALESCE(SUM(subtotal), 0) AS sum_sub FROM venta_items WHERE venta_id = :vid",
            [':vid' => $ventaId]
        );
        $subtotal = (float) ($row['sum_sub'] ?? 0);

        $taxRow  = $this->db->fetchOne(
            "SELECT valor FROM configuracion_sistema WHERE clave = 'impuesto_porcentaje' LIMIT 1"
        );
        $taxRate = ($taxRow ? (float) $taxRow['valor'] : 18.0) / 100.0;
        $imp     = round($subtotal * $taxRate, 2);
        $total   = round($subtotal + $imp, 2);

        $this->db->execute(
            "UPDATE ventas SET subtotal = :sub, impuesto = :imp, total = :tot WHERE id = :id",
            [':sub' => $subtotal, ':imp' => $imp, ':tot' => $total, ':id' => $ventaId]
        );
    }
}


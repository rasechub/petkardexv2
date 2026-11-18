<?php
/**
 * PetKardex v2 - Modelo Venta (Caja y Punto de Venta)
 *
 * Gestiona ventas, liquidación de cuentas médicas por DNI o UUID QR,
 * cálculo fiscal en servidor y descuento automático de inventario.
 */

declare(strict_types=1);

namespace PetKardex\Models;

use PetKardex\Core\Model;

class Venta extends Model
{
    protected string $table = 'ventas';

    protected bool $hasUpdatedAt = false;

    protected array $fillable = [
        'numero_venta',
        'cliente_tipo',
        'propietario_id',
        'cliente_nombre',
        'cajero_id',
        'fecha',
        'subtotal',
        'descuento',
        'impuesto',
        'total',
        'metodo_pago',
        'estado',
        'notas',
    ];

    /**
     * Obtiene moneda e impuesto configurado en configuracion_sistema.
     */
    public function getConfigFinanciera(): array
    {
        $rows = $this->db->fetchAll(
            "SELECT clave, valor FROM configuracion_sistema
             WHERE clave IN ('moneda_simbolo','moneda','impuesto_porcentaje','nombre_clinica','direccion','telefono')"
        );
        $cfg = [
            'moneda_simbolo'      => 'S/.',
            'moneda'              => 'PEN',
            'impuesto_porcentaje' => '18',
            'nombre_clinica'      => 'PetKardex Clínica Veterinaria',
            'direccion'           => '',
            'telefono'            => '',
        ];
        foreach ($rows as $row) {
            if ($row['valor'] !== null && $row['valor'] !== '') {
                $val = (string) $row['valor'];
                if ($row['clave'] === 'moneda_simbolo' && $val === 'S/') {
                    $val = 'S/.';
                }
                $cfg[$row['clave']] = $val;
            }
        }
        return $cfg;
    }

    /**
     * Busca cuentas médicas, recetas o servicios pendientes por DNI del tutor o UUID QR de la mascota.
     */
    public function buscarCuentaCliente(string $termino): array
    {
        $clean = trim($termino);
        if ($clean === '') {
            return ['propietario' => null, 'mascotas' => [], 'cuentas_pendientes' => [], 'items_sugeridos' => []];
        }

        // Extraer UUID si pegaron la URL completa del QR (/qr/{uuid})
        if (preg_match('#/qr/([a-zA-Z0-9\-_]{8,128})(?:[/?\#]|$)#', $clean, $m)) {
            $clean = $m[1];
        }

        // Buscar propietario por DNI exacto, UUID QR de mascota, o coincidencia de nombre
        $propietario = $this->db->fetchOne(
            "SELECT DISTINCT pr.*
             FROM propietarios pr
             LEFT JOIN pacientes p ON p.propietario_id = pr.id AND p.activo = 1
             WHERE pr.activo = 1
               AND (pr.dni = :dni OR p.qr_code = :qr OR CONCAT(pr.nombre, ' ', pr.apellido) LIKE :nombre)
             ORDER BY (pr.dni = :dni2 OR p.qr_code = :qr2) DESC
             LIMIT 1",
            [
                ':dni'    => $clean,
                ':qr'     => $clean,
                ':nombre' => '%' . $clean . '%',
                ':dni2'   => $clean,
                ':qr2'    => $clean,
            ]
        );

        if (!$propietario) {
            return ['propietario' => null, 'mascotas' => [], 'cuentas_pendientes' => [], 'items_sugeridos' => []];
        }

        $propId   = (int) $propietario['id'];
        $mascotas = $this->db->fetchAll(
            "SELECT p.id, p.nombre, p.qr_code, e.nombre AS especie_nombre
             FROM pacientes p
             INNER JOIN especies e ON e.id = p.especie_id
             WHERE p.propietario_id = :pid AND p.activo = 1",
            [':pid' => $propId]
        );

        // 1. Cuentas de venta en estado 'pendiente'
        $pendientes = $this->db->fetchAll(
            "SELECT v.* FROM ventas v
             WHERE v.propietario_id = :pid AND v.estado = 'pendiente'
             ORDER BY v.fecha DESC",
            [':pid' => $propId]
        );

        foreach ($pendientes as &$vp) {
            $vp['items'] = $this->db->fetchAll(
                "SELECT * FROM venta_items WHERE venta_id = :vid",
                [':vid' => (int) $vp['id']]
            );
        }
        unset($vp);

        // 2. Recetas recientes (últimos 7 días) + Citas del día + Hospitalizaciones activas
        $sugeridos = [];

        $recetasItems = $this->db->fetchAll(
            "SELECT ri.producto_id, ri.medicamento_nombre, ri.cantidad_dispensada,
                    p.precio_venta, p.codigo, pac.nombre AS paciente_nombre, r.fecha
             FROM recetas r
             INNER JOIN pacientes pac ON pac.id = r.paciente_id
             INNER JOIN receta_items ri ON ri.receta_id = r.id
             LEFT JOIN productos p ON p.id = ri.producto_id
             WHERE pac.propietario_id = :pid
               AND r.fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
             ORDER BY r.id DESC
             LIMIT 20",
            [':pid' => $propId]
        );

        foreach ($recetasItems as $ri) {
            $sugeridos[] = [
                'origen'          => 'Receta médica (' . $ri['paciente_nombre'] . ')',
                'producto_id'     => $ri['producto_id'] ? (int) $ri['producto_id'] : null,
                'descripcion'     => $ri['medicamento_nombre'] . ' — ' . $ri['paciente_nombre'],
                'cantidad'        => max(1.0, (float) ($ri['cantidad_dispensada'] ?? 1)),
                'precio_unitario' => (float) ($ri['precio_venta'] ?? 0),
                'es_servicio'     => false,
            ];
        }

        $citasHoy = $this->db->fetchAll(
            "SELECT c.id, c.tipo_servicio, c.fecha_hora, pac.nombre AS paciente_nombre
             FROM citas c
             INNER JOIN pacientes pac ON pac.id = c.paciente_id
             WHERE c.propietario_id = :pid
               AND c.estado IN ('programada', 'confirmada', 'en_proceso', 'completada')
               AND DATE(c.fecha_hora) >= DATE_SUB(CURDATE(), INTERVAL 2 DAY)
             ORDER BY c.fecha_hora DESC
             LIMIT 10",
            [':pid' => $propId]
        );

        foreach ($citasHoy as $c) {
            $servicioLabel = ucfirst((string) $c['tipo_servicio']);
            $srvInfo       = $this->resolverServicioPorTipo((string) $c['tipo_servicio']);
            $sugeridos[]   = [
                'origen'          => 'Cita #' . $c['id'] . ' (' . $c['paciente_nombre'] . ')',
                'producto_id'     => $srvInfo['id'],
                'descripcion'     => "Servicio {$servicioLabel} — {$c['paciente_nombre']}",
                'cantidad'        => 1.0,
                'precio_unitario' => $srvInfo['precio'],
                'es_servicio'     => true,
            ];
        }

        $hospActivas = $this->db->fetchAll(
            "SELECT h.id, h.fecha_ingreso, h.cama_numero, pac.nombre AS paciente_nombre
             FROM hospitalizaciones h
             INNER JOIN pacientes pac ON pac.id = h.paciente_id
             WHERE pac.propietario_id = :pid AND h.estado = 'activo'",
            [':pid' => $propId]
        );

        $srvHosp = Inventario::getServicioPorCodigoONombre('SRV-HOSP', '%hospitaliz%');
        foreach ($hospActivas as $h) {
            $dias = max(1, (int) ceil((time() - strtotime((string) $h['fecha_ingreso'])) / 86400));
            $sugeridos[] = [
                'origen'          => 'Hospitalización #' . $h['id'] . ' (' . $h['paciente_nombre'] . ')',
                'producto_id'     => $srvHosp ? (int) $srvHosp['id'] : null,
                'descripcion'     => "Hospitalización ({$dias} día(s), Cama " . ($h['cama_numero'] ?: 'S/N') . ") — {$h['paciente_nombre']}",
                'cantidad'        => (float) $dias,
                'precio_unitario' => $srvHosp ? (float) $srvHosp['precio_venta'] : 120.00,
                'es_servicio'     => true,
            ];
        }

        return [
            'propietario'        => $propietario,
            'mascotas'           => $mascotas,
            'cuentas_pendientes' => $pendientes,
            'items_sugeridos'    => $sugeridos,
        ];
    }

    /**
     * Registra una venta pagada (o liquida una cuenta pendiente) en transacción PDO,
     * recalculando totales e impuestos en el servidor y descontando stock físico.
     *
     * @return array{success: bool, venta_id: int, numero_venta: string, total: float}
     */
    public function registrarVenta(array $payload, int $cajeroId): array
    {
        $items = $payload['items'] ?? [];
        if (!is_array($items) || empty($items)) {
            throw new \InvalidArgumentException('El ticket no contiene ítems para cobrar.');
        }

        $metodoPago = (string) ($payload['metodo_pago'] ?? 'efectivo');
        if (!in_array($metodoPago, ['efectivo', 'tarjeta', 'transferencia', 'mixto'], true)) {
            throw new \InvalidArgumentException('Método de pago no válido.');
        }

        $clienteTipo      = ($payload['cliente_tipo'] ?? 'externo') === 'propietario' ? 'propietario' : 'externo';
        $propietarioId    = !empty($payload['propietario_id']) ? (int) $payload['propietario_id'] : null;
        $clienteNombre    = trim((string) ($payload['cliente_nombre'] ?? 'Cliente Mostrador'));
        $descuentoGlobal  = max(0.0, (float) ($payload['descuento'] ?? 0));
        $notas            = trim((string) ($payload['notas'] ?? ''));
        $ventaPendienteId = !empty($payload['venta_pendiente_id']) ? (int) $payload['venta_pendiente_id'] : null;
        $descontarStock   = (bool) ($payload['descontar_stock'] ?? true);

        $cfg         = $this->getConfigFinanciera();
        $taxRate     = max(0.0, (float) $cfg['impuesto_porcentaje']) / 100.0;
        $subtotalSum = 0.0;
        $cleanItems  = [];

        foreach ($items as $rawItem) {
            $prodId   = !empty($rawItem['producto_id']) ? (int) $rawItem['producto_id'] : null;
            $desc     = trim((string) ($rawItem['descripcion'] ?? ''));
            $cantidad = max(0.001, (float) ($rawItem['cantidad'] ?? 1));
            $precio   = max(0.0, (float) ($rawItem['precio_unitario'] ?? 0));
            $descItem = max(0.0, (float) ($rawItem['descuento_item'] ?? 0));

            // Si viene vinculado a producto real, tomar precio/nombre autoritativo de BD
            if ($prodId !== null) {
                $prod = $this->db->fetchOne(
                    "SELECT id, nombre, precio_venta, activo FROM productos WHERE id = :id LIMIT 1",
                    [':id' => $prodId]
                );
                if (!$prod || (int) $prod['activo'] !== 1) {
                    throw new \RuntimeException("Producto #{$prodId} no disponible.");
                }
                if ($desc === '') {
                    $desc = (string) $prod['nombre'];
                }
                $precio = (float) $prod['precio_venta'];
            }

            if ($desc === '') {
                continue;
            }

            $lineSubtotal = max(0.0, round(($cantidad * $precio) - $descItem, 2));
            $subtotalSum += $lineSubtotal;

            $cleanItems[] = [
                'producto_id'     => $prodId,
                'descripcion'     => $desc,
                'cantidad'        => $cantidad,
                'precio_unitario' => $precio,
                'descuento_item'  => $descItem,
                'subtotal'        => $lineSubtotal,
            ];
        }

        if (empty($cleanItems)) {
            throw new \InvalidArgumentException('No hay ítems válidos en la venta.');
        }

        $baseImponible = max(0.0, round($subtotalSum - $descuentoGlobal, 2));
        $impuesto      = round($baseImponible * $taxRate, 2);
        $total         = round($baseImponible + $impuesto, 2);

        return $this->db->transaction(function () use (
            $clienteTipo,
            $propietarioId,
            $clienteNombre,
            $cajeroId,
            $subtotalSum,
            $descuentoGlobal,
            $impuesto,
            $total,
            $metodoPago,
            $notas,
            $cleanItems,
            $ventaPendienteId,
            $descontarStock
        ): array {
            if ($ventaPendienteId !== null && $ventaPendienteId > 0) {
                $pendiente = $this->db->fetchOne(
                    "SELECT id, numero_venta, estado FROM ventas WHERE id = :id LIMIT 1 FOR UPDATE",
                    [':id' => $ventaPendienteId]
                );
                if ($pendiente && $pendiente['estado'] === 'pendiente') {
                    $numeroVenta = (string) $pendiente['numero_venta'];
                    $this->db->execute(
                        "UPDATE ventas
                         SET cajero_id = :cid, fecha = NOW(), subtotal = :sub, descuento = :desc,
                             impuesto = :imp, total = :tot, metodo_pago = :mp, estado = 'pagada', notas = :notas
                         WHERE id = :id",
                        [
                            ':cid'   => $cajeroId,
                            ':sub'   => $subtotalSum,
                            ':desc'  => $descuentoGlobal,
                            ':imp'   => $impuesto,
                            ':tot'   => $total,
                            ':mp'    => $metodoPago,
                            ':notas' => $notas !== '' ? $notas : null,
                            ':id'    => $ventaPendienteId,
                        ]
                    );
                    $this->db->execute("DELETE FROM venta_items WHERE venta_id = :vid", [':vid' => $ventaPendienteId]);
                    $ventaId = $ventaPendienteId;
                } else {
                    $ventaPendienteId = null;
                }
            }

            if ($ventaPendienteId === null) {
                $numeroVenta = $this->generarNumeroVenta();
                $this->db->execute(
                    "INSERT INTO ventas
                        (numero_venta, cliente_tipo, propietario_id, cliente_nombre, cajero_id, fecha,
                         subtotal, descuento, impuesto, total, metodo_pago, estado, notas, created_at)
                     VALUES
                        (:num, :ctipo, :pid, :cnombre, :cid, NOW(),
                         :sub, :desc, :imp, :tot, :mp, 'pagada', :notas, NOW())",
                    [
                        ':num'     => $numeroVenta,
                        ':ctipo'   => $propietarioId ? 'propietario' : $clienteTipo,
                        ':pid'     => $propietarioId,
                        ':cnombre' => $clienteNombre !== '' ? $clienteNombre : 'Cliente Mostrador',
                        ':cid'     => $cajeroId,
                        ':sub'     => $subtotalSum,
                        ':desc'    => $descuentoGlobal,
                        ':imp'     => $impuesto,
                        ':tot'     => $total,
                        ':mp'      => $metodoPago,
                        ':notas'   => $notas !== '' ? $notas : null,
                    ]
                );
                $ventaId = $this->db->lastInsertId();
            }

            foreach ($cleanItems as $item) {
                $this->db->execute(
                    "INSERT INTO venta_items
                        (venta_id, producto_id, descripcion, cantidad, precio_unitario, descuento_item, subtotal)
                     VALUES
                        (:vid, :pid, :desc, :cant, :precio, :ditem, :sub)",
                    [
                        ':vid'    => $ventaId,
                        ':pid'    => $item['producto_id'],
                        ':desc'   => $item['descripcion'],
                        ':cant'   => $item['cantidad'],
                        ':precio' => $item['precio_unitario'],
                        ':ditem'  => $item['descuento_item'],
                        ':sub'    => $item['subtotal'],
                    ]
                );

                if ($descontarStock && $item['producto_id'] !== null) {
                    Inventario::descontarStock(
                        $item['producto_id'],
                        $item['cantidad'],
                        $cajeroId,
                        'venta',
                        $ventaId,
                        "Venta POS #{$numeroVenta}"
                    );
                }
            }

            return [
                'success'      => true,
                'venta_id'     => $ventaId,
                'numero_venta' => $numeroVenta,
                'total'        => $total,
            ];
        });
    }

    /**
     * Anula una venta pagada y devuelve los productos físicos al inventario.
     */
    public function anularVenta(int $ventaId, int $usuarioId): void
    {
        $this->db->transaction(function () use ($ventaId, $usuarioId): void {
            $venta = $this->db->fetchOne(
                "SELECT * FROM ventas WHERE id = :id LIMIT 1 FOR UPDATE",
                [':id' => $ventaId]
            );

            if (!$venta || $venta['estado'] !== 'pagada') {
                throw new \RuntimeException('Solo se pueden anular ventas en estado pagada.');
            }

            $items = $this->db->fetchAll(
                "SELECT vi.*, c.tipo AS categoria_tipo
                 FROM venta_items vi
                 LEFT JOIN productos p ON p.id = vi.producto_id
                 LEFT JOIN categorias_inventario c ON c.id = p.categoria_id
                 WHERE vi.venta_id = :vid",
                [':vid' => $ventaId]
            );

            foreach ($items as $item) {
                if (!empty($item['producto_id']) && ($item['categoria_tipo'] ?? '') !== 'servicio') {
                    Inventario::ajustarStock(
                        (int) $item['producto_id'],
                        'devolucion',
                        (float) $item['cantidad'],
                        $usuarioId,
                        "Devolución por anulación de venta #{$venta['numero_venta']}",
                        'anulacion_venta',
                        $ventaId
                    );
                }
            }

            $this->db->execute(
                "UPDATE ventas SET estado = 'anulada' WHERE id = :id",
                [':id' => $ventaId]
            );
        });
    }

    /**
     * Obtiene el detalle completo de una venta con sus ítems y datos del cajero.
     */
    public function getVentaDetalle(int $ventaId): ?array
    {
        $venta = $this->db->fetchOne(
            "SELECT v.*, u.nombre AS cajero_nombre, u.apellido AS cajero_apellido,
                    pr.dni AS propietario_dni
             FROM ventas v
             INNER JOIN usuarios u ON u.id = v.cajero_id
             LEFT JOIN propietarios pr ON pr.id = v.propietario_id
             WHERE v.id = :id
             LIMIT 1",
            [':id' => $ventaId]
        );

        if (!$venta) {
            return null;
        }

        $venta['items'] = $this->db->fetchAll(
            "SELECT * FROM venta_items WHERE venta_id = :vid ORDER BY id ASC",
            [':vid' => $ventaId]
        );

        return $venta;
    }

    /**
     * Lista las últimas ventas registradas en Caja.
     */
    public function getVentasRecientes(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        return $this->db->fetchAll(
            "SELECT v.*, u.nombre AS cajero_nombre, u.apellido AS cajero_apellido
             FROM ventas v
             INNER JOIN usuarios u ON u.id = v.cajero_id
             ORDER BY v.fecha DESC, v.id DESC
             LIMIT {$limit}"
        );
    }

    private function generarNumeroVenta(): string
    {
        $prefix = 'V-' . date('Ymd') . '-';
        $row    = $this->db->fetchOne(
            "SELECT COUNT(*) AS total FROM ventas WHERE numero_venta LIKE :pref",
            [':pref' => $prefix . '%']
        );
        $seq = ((int) ($row['total'] ?? 0)) + 1;
        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT) . '-' . random_int(10, 99);
    }

    private function resolverServicioPorTipo(string $tipo): array
    {
        [$cod, $nom, $fallback] = match ($tipo) {
            'consulta'        => ['SRV-CONS', '%consulta%', 60.00],
            'vacunacion'      => ['SRV-VACU', '%vacuna%', 50.00],
            'estetica'        => ['SRV-BANO', '%baño%', 45.00],
            'control'         => ['SRV-CTRL', '%control%', 40.00],
            'emergencia'      => ['SRV-EMER', '%emergencia%', 90.00],
            'cirugia'         => ['SRV-CIRU', '%cirug%', 250.00],
            'hospitalizacion' => ['SRV-HOSP', '%hospitaliz%', 120.00],
            default           => ['SRV-CONS', '%consulta%', 50.00],
        };

        $srv = Inventario::getServicioPorCodigoONombre($cod, $nom);
        return [
            'id'     => $srv ? (int) $srv['id'] : null,
            'precio' => $srv ? (float) $srv['precio_venta'] : $fallback,
        ];
    }

    private function precioBaseServicio(string $tipo): float
    {
        return $this->resolverServicioPorTipo($tipo)['precio'];
    }
}


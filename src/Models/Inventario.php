<?php
/**
 * PetKardex v2 - Modelo Inventario
 *
 * Gestiona el CRUD de productos (Farmacia, Pet Shop, Insumos, Servicios),
 * alertas de stock mínimo, movimientos de kardex de inventario y descuento
 * atómico desde recetas médicas y punto de venta.
 *
 * @author    CESAR AQUINO ANGLAS - VI B NOCHE
 * @copyright 2026 PetKardex. Todos los derechos reservados.
 * @license   Propietaria - IESTP Santiago Antúnez de Mayolo (IESTP SAM)
 */

declare(strict_types=1);

namespace PetKardex\Models;

use PetKardex\Config\Database;
use PetKardex\Core\Model;

class Inventario extends Model
{
    protected string $table = 'productos';

    protected array $fillable = [
        'categoria_id',
        'codigo',
        'nombre',
        'descripcion',
        'presentacion',
        'unidad_medida',
        'precio_compra',
        'precio_venta',
        'stock_actual',
        'stock_minimo',
        'requiere_receta',
        'activo',
    ];

    /**
     * Descuenta stock de un producto de manera segura y registra el movimiento.
     * Si existe una transacción PDO activa, participa en ella; si no, crea una propia.
     *
     * @return array{success: bool, stock_anterior?: float, stock_nuevo?: float, error?: string}
     * @throws \RuntimeException si el producto no existe o no tiene stock suficiente
     */
    public static function descontarStock(
        int    $productoId,
        float  $cantidad,
        int    $usuarioId = 0,
        string $referenciaTipo = 'receta',
        ?int   $referenciaId = null,
        string $motivo = 'Descuento automático por receta médica'
    ): array {
        if ($productoId <= 0 || $cantidad <= 0) {
            throw new \InvalidArgumentException('Producto o cantidad inválida para descontar stock.');
        }

        $db  = Database::getInstance();
        $pdo = $db->getConnection();

        $manageTx = !$pdo->inTransaction();
        if ($manageTx) {
            $db->beginTransaction();
        }

        try {
            $producto = $db->fetchOne(
                "SELECT p.id, p.nombre, p.stock_actual, p.activo, c.tipo AS categoria_tipo
                 FROM productos p
                 INNER JOIN categorias_inventario c ON c.id = p.categoria_id
                 WHERE p.id = :id
                 LIMIT 1
                 FOR UPDATE",
                [':id' => $productoId]
            );

            if (!$producto || (int) $producto['activo'] !== 1) {
                throw new \RuntimeException('El producto seleccionado no se encuentra activo en inventario.');
            }

            // Los servicios no descuentan inventario físico
            if (($producto['categoria_tipo'] ?? '') === 'servicio') {
                if ($manageTx) {
                    $db->commit();
                }
                return [
                    'success'        => true,
                    'stock_anterior' => (float) $producto['stock_actual'],
                    'stock_nuevo'    => (float) $producto['stock_actual'],
                ];
            }

            $stockAnterior = (float) $producto['stock_actual'];
            if ($stockAnterior < $cantidad) {
                throw new \RuntimeException(
                    "Stock insuficiente para \"{$producto['nombre']}\". Disponible: {$stockAnterior}, requerido: {$cantidad}."
                );
            }

            $stockNuevo = round($stockAnterior - $cantidad, 3);

            $db->execute(
                "UPDATE productos SET stock_actual = :stock, updated_at = NOW() WHERE id = :id",
                [':stock' => $stockNuevo, ':id' => $productoId]
            );

            if ($usuarioId > 0) {
                $db->execute(
                    "INSERT INTO movimientos_inventario
                        (producto_id, tipo, cantidad, stock_anterior, stock_nuevo, referencia_tipo, referencia_id, motivo, usuario_id, fecha, created_at)
                     VALUES
                        (:pid, 'salida', :cant, :ant, :nuevo, :reftipo, :refid, :motivo, :uid, NOW(), NOW())",
                    [
                        ':pid'     => $productoId,
                        ':cant'    => $cantidad,
                        ':ant'     => $stockAnterior,
                        ':nuevo'   => $stockNuevo,
                        ':reftipo' => $referenciaTipo,
                        ':refid'   => $referenciaId,
                        ':motivo'  => $motivo,
                        ':uid'     => $usuarioId,
                    ]
                );
            }

            if ($manageTx) {
                $db->commit();
            }

            return [
                'success'        => true,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo'    => $stockNuevo,
            ];
        } catch (\Throwable $e) {
            if ($manageTx && $pdo->inTransaction()) {
                $db->rollback();
            }
            throw $e;
        }
    }

    /**
     * Realiza un ajuste manual de stock (entrada, salida, ajuste o devolución)
     * con registro obligatorio en movimientos_inventario.
     */
    public static function ajustarStock(
        int    $productoId,
        string $tipo,
        float  $cantidad,
        int    $usuarioId,
        string $motivo,
        string $referenciaTipo = 'ajuste_manual',
        ?int   $referenciaId = null
    ): array {
        $allowed = ['entrada', 'salida', 'ajuste', 'devolucion'];
        if (!in_array($tipo, $allowed, true)) {
            throw new \InvalidArgumentException('Tipo de movimiento de inventario inválido.');
        }
        if ($productoId <= 0 || $cantidad < 0) {
            throw new \InvalidArgumentException('Producto o cantidad inválida.');
        }
        if ($tipo !== 'ajuste' && $cantidad <= 0) {
            throw new \InvalidArgumentException('La cantidad debe ser mayor a cero.');
        }

        $db  = Database::getInstance();
        $pdo = $db->getConnection();

        $manageTx = !$pdo->inTransaction();
        if ($manageTx) {
            $db->beginTransaction();
        }

        try {
            $producto = $db->fetchOne(
                "SELECT id, nombre, stock_actual, activo FROM productos WHERE id = :id LIMIT 1 FOR UPDATE",
                [':id' => $productoId]
            );

            if (!$producto || (int) $producto['activo'] !== 1) {
                throw new \RuntimeException('Producto no encontrado o inactivo.');
            }

            $stockAnterior = (float) $producto['stock_actual'];
            $stockNuevo    = match ($tipo) {
                'entrada', 'devolucion' => round($stockAnterior + $cantidad, 3),
                'salida'                => round($stockAnterior - $cantidad, 3),
                'ajuste'                => round($cantidad, 3),
            };

            if ($stockNuevo < 0) {
                throw new \RuntimeException(
                    "El stock de \"{$producto['nombre']}\" no puede quedar negativo (disponible: {$stockAnterior})."
                );
            }

            $db->execute(
                "UPDATE productos SET stock_actual = :stock, updated_at = NOW() WHERE id = :id",
                [':stock' => $stockNuevo, ':id' => $productoId]
            );

            $delta = $tipo === 'ajuste' ? abs($stockNuevo - $stockAnterior) : $cantidad;

            $db->execute(
                "INSERT INTO movimientos_inventario
                    (producto_id, tipo, cantidad, stock_anterior, stock_nuevo, referencia_tipo, referencia_id, motivo, usuario_id, fecha, created_at)
                 VALUES
                    (:pid, :tipo, :cant, :ant, :nuevo, :reftipo, :refid, :motivo, :uid, NOW(), NOW())",
                [
                    ':pid'     => $productoId,
                    ':tipo'    => $tipo,
                    ':cant'    => $delta,
                    ':ant'     => $stockAnterior,
                    ':nuevo'   => $stockNuevo,
                    ':reftipo' => $referenciaTipo,
                    ':refid'   => $referenciaId,
                    ':motivo'  => trim($motivo),
                    ':uid'     => $usuarioId,
                ]
            );

            if ($manageTx) {
                $db->commit();
            }

            return [
                'success'        => true,
                'stock_anterior' => $stockAnterior,
                'stock_nuevo'    => $stockNuevo,
            ];
        } catch (\Throwable $e) {
            if ($manageTx && $pdo->inTransaction()) {
                $db->rollback();
            }
            throw $e;
        }
    }

    /**
     * Lista medicamentos y productos activos disponibles para recetar en el Kardex.
     */
    public function getMedicamentosDisponibles(): array
    {
        return $this->db->fetchAll(
            "SELECT p.id, p.codigo, p.nombre, p.presentacion, p.unidad_medida,
                    p.stock_actual, p.requiere_receta, c.nombre AS categoria_nombre, c.tipo AS categoria_tipo
             FROM productos p
             INNER JOIN categorias_inventario c ON c.id = p.categoria_id
             WHERE p.activo = 1 AND c.tipo IN ('medicamento', 'consumible')
             ORDER BY p.nombre ASC"
        );
    }

    /**
     * Lista todos los productos activos junto con su categoría.
     */
    public function getAllProductos(string $search = '', ?int $categoriaId = null): array
    {
        $sql = "SELECT p.*, c.nombre AS categoria_nombre, c.tipo AS categoria_tipo
                FROM productos p
                INNER JOIN categorias_inventario c ON c.id = p.categoria_id
                WHERE p.activo = 1";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (p.nombre LIKE :q1 OR p.codigo LIKE :q2 OR p.presentacion LIKE :q3)";
            $like = '%' . $search . '%';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
        }

        if ($categoriaId !== null && $categoriaId > 0) {
            $sql .= " AND p.categoria_id = :cat";
            $params[':cat'] = $categoriaId;
        }

        $sql .= " ORDER BY (p.stock_actual <= p.stock_minimo AND c.tipo <> 'servicio') DESC, p.nombre ASC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Retorna las categorías activas de inventario.
     */
    public function getCategorias(): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM categorias_inventario WHERE activo = 1 ORDER BY id ASC"
        );
    }

    /**
     * Retorna productos físicos cuyo stock actual está igual o por debajo del mínimo.
     */
    public function getProductosStockCritico(): array
    {
        return $this->db->fetchAll(
            "SELECT p.*, c.nombre AS categoria_nombre, c.tipo AS categoria_tipo
             FROM productos p
             INNER JOIN categorias_inventario c ON c.id = p.categoria_id
             WHERE p.activo = 1 AND c.tipo <> 'servicio' AND p.stock_actual <= p.stock_minimo
             ORDER BY p.stock_actual ASC, p.nombre ASC"
        );
    }

    /**
     * Calcula los indicadores KPI superiores de la vista de Inventario.
     */
    public function getEstadisticas(): array
    {
        $row = $this->db->fetchOne(
            "SELECT
                COUNT(CASE WHEN c.tipo <> 'servicio' THEN 1 END) AS total_productos,
                COALESCE(SUM(CASE WHEN c.tipo <> 'servicio' THEN p.precio_compra * p.stock_actual ELSE 0 END), 0) AS valor_costo,
                COALESCE(SUM(CASE WHEN c.tipo <> 'servicio' THEN p.precio_venta * p.stock_actual ELSE 0 END), 0) AS valor_venta,
                COUNT(CASE WHEN c.tipo <> 'servicio' AND p.stock_actual <= p.stock_minimo THEN 1 END) AS stock_critico
             FROM productos p
             INNER JOIN categorias_inventario c ON c.id = p.categoria_id
             WHERE p.activo = 1"
        );

        return [
            'total_productos' => (int) ($row['total_productos'] ?? 0),
            'valor_costo'     => (float) ($row['valor_costo'] ?? 0),
            'valor_venta'     => (float) ($row['valor_venta'] ?? 0),
            'stock_critico'   => (int) ($row['stock_critico'] ?? 0),
        ];
    }

    /**
     * Retorna el log cronológico de movimientos de inventario (entradas y salidas).
     */
    public function getHistorialMovimientos(int $limit = 150, ?int $productoId = null): array
    {
        $limit = max(1, min(500, $limit));
        $sql   = "SELECT m.*, p.codigo AS producto_codigo, p.nombre AS producto_nombre,
                         p.unidad_medida, u.nombre AS usuario_nombre, u.apellido AS usuario_apellido
                  FROM movimientos_inventario m
                  INNER JOIN productos p ON p.id = m.producto_id
                  INNER JOIN usuarios u ON u.id = m.usuario_id";
        $params = [];

        if ($productoId !== null && $productoId > 0) {
            $sql .= " WHERE m.producto_id = :pid";
            $params[':pid'] = $productoId;
        }

        $sql .= " ORDER BY m.fecha DESC, m.id DESC LIMIT {$limit}";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Catálogo unificado para el Punto de Venta (productos físicos + servicios).
     */
    public function getCatalogoPOS(): array
    {
        return $this->db->fetchAll(
            "SELECT p.id, p.codigo, p.nombre, p.presentacion, p.unidad_medida,
                    p.precio_venta, p.stock_actual, p.stock_minimo, p.requiere_receta,
                    c.id AS categoria_id, c.nombre AS categoria_nombre, c.tipo AS categoria_tipo
             FROM productos p
             INNER JOIN categorias_inventario c ON c.id = p.categoria_id
             WHERE p.activo = 1
             ORDER BY (c.tipo = 'servicio') DESC, p.nombre ASC"
        );
    }

    /**
     * Lista únicamente los servicios activos (categorias_inventario.tipo = 'servicio').
     */
    public function getServicios(string $search = ''): array
    {
        $sql = "SELECT p.*, c.nombre AS categoria_nombre, c.tipo AS categoria_tipo
                FROM productos p
                INNER JOIN categorias_inventario c ON c.id = p.categoria_id
                WHERE p.activo = 1 AND c.tipo = 'servicio'";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (p.nombre LIKE :q1 OR p.codigo LIKE :q2 OR p.descripcion LIKE :q3)";
            $like          = '%' . $search . '%';
            $params[':q1'] = $like;
            $params[':q2'] = $like;
            $params[':q3'] = $like;
        }

        $sql .= " ORDER BY p.nombre ASC";

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Retorna el ID de la categoría de servicios (creándola si no existiera).
     */
    public function getCategoriaServicioId(): int
    {
        $row = $this->db->fetchOne(
            "SELECT id FROM categorias_inventario WHERE tipo = 'servicio' AND activo = 1 ORDER BY id ASC LIMIT 1"
        );
        if ($row) {
            return (int) $row['id'];
        }

        $this->db->execute(
            "INSERT INTO categorias_inventario (nombre, descripcion, tipo, activo)
             VALUES ('Servicios', 'Consultas, cirugías, baños, cortes y hospitalización', 'servicio', 1)"
        );
        return (int) $this->db->lastInsertId();
    }

    /**
     * Busca dinámicamente un servicio activo en base de datos por código exacto o nombre similar.
     */
    public static function getServicioPorCodigoONombre(string $codigo, string $nombreLike): ?array
    {
        $db = Database::getInstance();
        return $db->fetchOne(
            "SELECT p.id, p.codigo, p.nombre, p.precio_venta
             FROM productos p
             INNER JOIN categorias_inventario c ON c.id = p.categoria_id
             WHERE p.activo = 1
               AND c.tipo = 'servicio'
               AND (p.codigo = :cod OR LOWER(p.nombre) LIKE :nom)
             ORDER BY (p.codigo = :cod2) DESC, p.id ASC
             LIMIT 1",
            [
                ':cod'  => $codigo,
                ':cod2' => $codigo,
                ':nom'  => strtolower($nombreLike),
            ]
        );
    }

    /**
     * Retorna el precio dinámico vigente de un servicio desde la base de datos.
     */
    public static function getPrecioServicioPorCodigoONombre(
        string $codigo,
        string $nombreLike,
        float  $fallback = 50.00
    ): float {
        $row = self::getServicioPorCodigoONombre($codigo, $nombreLike);
        return $row ? (float) $row['precio_venta'] : $fallback;
    }
}

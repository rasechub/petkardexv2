<?php
/**
 * PetKardex v2 - Clase base Model
 *
 * Proporciona CRUD genérico con consultas preparadas PDO y validación
 * estricta de identificadores SQL para evitar inyecciones.
 *
 * @author    CESAR AQUINO ANGLAS - VI B NOCHE
 * @copyright 2026 PetKardex. Todos los derechos reservados.
 * @license   Propietaria - IESTP Santiago Antúnez de Mayolo (IESTP SAM)
 */

declare(strict_types=1);

namespace PetKardex\Core;

use InvalidArgumentException;
use PetKardex\Config\Database;

abstract class Model
{
    /** Nombre de la tabla en la BD (debe definirse en la subclase) */
    protected string $table = '';

    /** Nombre de la clave primaria */
    protected string $primaryKey = 'id';

    /**
     * Columnas que pueden ser asignadas masivamente.
     * Las demás columnas son ignoradas en create() y update().
     *
     * @var string[]
     */
    protected array $fillable = [];

    /** Indica si la tabla maneja created_at automáticamente */
    protected bool $hasCreatedAt = true;

    /** Indica si la tabla maneja updated_at automáticamente */
    protected bool $hasUpdatedAt = true;

    protected Database $db;

    private const ALLOWED_OPERATORS = ['=', '!=', '<>', '<', '<=', '>', '>=', 'LIKE', 'NOT LIKE'];

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->assertIdentifier($this->table);
        $this->assertIdentifier($this->primaryKey);
    }

    // ─── Lectura ───────────────────────────────────────────────────────────

    /**
     * Busca un registro por clave primaria.
     */
    public function findById(int|string $id): ?array
    {
        $sql = "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id LIMIT 1";
        return $this->db->fetchOne($sql, [':id' => $id]);
    }

    /**
     * Busca registros con condiciones opcionales.
     *
     * @param  array  $conditions  Ej: ['activo' => 1, 'rol_id' => 2]
     * @param  string $order       Ej: 'nombre ASC'
     * @param  int    $limit       0 = sin límite
     */
    public function findAll(array $conditions = [], string $order = '', int $limit = 0): array
    {
        [$where, $params] = $this->buildWhere($conditions);
        $orderClause      = $this->buildOrderBy($order);

        $sql  = "SELECT * FROM `{$this->table}`";
        $sql .= $where ? " WHERE {$where}" : '';
        $sql .= $orderClause;
        $sql .= $limit > 0 ? ' LIMIT ' . (int) $limit : '';

        return $this->db->fetchAll($sql, $params);
    }

    /**
     * Busca el primer registro que coincida con las condiciones.
     */
    public function findOne(array $conditions): ?array
    {
        $results = $this->findAll($conditions, '', 1);
        return $results[0] ?? null;
    }

    // ─── Escritura ─────────────────────────────────────────────────────────

    /**
     * Crea un nuevo registro. Sólo inserta columnas en $fillable.
     */
    public function create(array $data): int
    {
        $filtered = $this->filterFillable($data);
        $tsCols   = $this->hasCreatedAt ? ($this->hasUpdatedAt ? ['created_at', 'updated_at'] : ['created_at']) : [];
        $filtered = $this->addTimestamps($filtered, $tsCols);

        if (empty($filtered)) {
            throw new InvalidArgumentException("No hay columnas válidas para insertar en {$this->table}.");
        }

        $cols = array_keys($filtered);
        foreach ($cols as $col) {
            $this->assertIdentifier($col);
        }

        $placeholders = array_map(static fn(string $c): string => ":{$c}", $cols);

        $sql = sprintf(
            "INSERT INTO `%s` (%s) VALUES (%s)",
            $this->table,
            implode(', ', array_map(static fn(string $c): string => "`{$c}`", $cols)),
            implode(', ', $placeholders)
        );

        $params = [];
        foreach ($filtered as $col => $val) {
            $params[":{$col}"] = $val;
        }

        $this->db->execute($sql, $params);
        return $this->db->lastInsertId();
    }

    /**
     * Actualiza un registro existente por PK.
     */
    public function update(int|string $id, array $data): int
    {
        $filtered = $this->filterFillable($data);
        $tsCols   = $this->hasUpdatedAt ? ['updated_at'] : [];
        $filtered = $this->addTimestamps($filtered, $tsCols);

        if (empty($filtered)) {
            return 0;
        }

        $sets   = [];
        $params = [];
        foreach ($filtered as $col => $val) {
            $this->assertIdentifier($col);
            $sets[]            = "`{$col}` = :{$col}";
            $params[":{$col}"] = $val;
        }
        $params[':_pk'] = $id;

        $sql = sprintf(
            "UPDATE `%s` SET %s WHERE `%s` = :_pk",
            $this->table,
            implode(', ', $sets),
            $this->primaryKey
        );

        return $this->db->execute($sql, $params);
    }

    /**
     * Elimina (físicamente) un registro por PK.
     */
    public function delete(int|string $id): int
    {
        $sql = "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = :id";
        return $this->db->execute($sql, [':id' => $id]);
    }

    /**
     * Soft-delete: pone activo=0 en vez de borrar el registro.
     */
    public function softDelete(int|string $id): int
    {
        $sql = "UPDATE `{$this->table}` SET `activo` = 0 WHERE `{$this->primaryKey}` = :id";
        return $this->db->execute($sql, [':id' => $id]);
    }

    // ─── Paginación ────────────────────────────────────────────────────────

    /**
     * Retorna una página de resultados más metadata de paginación.
     */
    public function paginate(
        int    $page       = 1,
        int    $perPage    = 20,
        array  $conditions = [],
        string $order      = ''
    ): array {
        $page    = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $offset  = ($page - 1) * $perPage;

        [$where, $params] = $this->buildWhere($conditions);
        $orderClause      = $this->buildOrderBy($order);

        $baseQuery = " FROM `{$this->table}`" . ($where ? " WHERE {$where}" : '');

        $total = (int) ($this->db->fetchOne(
            "SELECT COUNT(*) AS total" . $baseQuery,
            $params
        )['total'] ?? 0);

        $data = $this->db->fetchAll(
            "SELECT *" . $baseQuery . $orderClause . " LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $lastPage = max(1, (int) ceil($total / $perPage));

        return [
            'data'     => $data,
            'total'    => $total,
            'page'     => $page,
            'perPage'  => $perPage,
            'lastPage' => $lastPage,
            'hasMore'  => $page < $lastPage,
        ];
    }

    /**
     * Cuenta registros que coincidan con las condiciones.
     */
    public function count(array $conditions = []): int
    {
        [$where, $params] = $this->buildWhere($conditions);
        $sql  = "SELECT COUNT(*) AS total FROM `{$this->table}`";
        $sql .= $where ? " WHERE {$where}" : '';
        return (int) ($this->db->fetchOne($sql, $params)['total'] ?? 0);
    }

    // ─── Helpers internos ──────────────────────────────────────────────────

    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }
        return array_intersect_key($data, array_flip($this->fillable));
    }

    private function addTimestamps(array $data, array $columns): array
    {
        $now = date('Y-m-d H:i:s');
        foreach ($columns as $col) {
            if (!isset($data[$col])) {
                $data[$col] = $now;
            }
        }
        return $data;
    }

    /**
     * Construye la cláusula WHERE y los parámetros PDO validando columnas y operadores.
     *
     * @param  array $conditions  ['col' => val] o ['col' => ['op' => '>=', 'val' => 5]]
     * @return array{0: string, 1: array}
     */
    protected function buildWhere(array $conditions): array
    {
        if (empty($conditions)) {
            return ['', []];
        }

        $clauses = [];
        $params  = [];
        $i       = 0;

        foreach ($conditions as $col => $value) {
            $this->assertIdentifier((string) $col);
            $key = ":w{$i}";

            if (is_array($value) && isset($value['op'], $value['val'])) {
                $op = strtoupper(trim((string) $value['op']));
                if (!in_array($op, self::ALLOWED_OPERATORS, true)) {
                    throw new InvalidArgumentException("Operador SQL no permitido: {$op}");
                }
                $clauses[]    = "`{$col}` {$op} {$key}";
                $params[$key] = $value['val'];
            } elseif ($value === null) {
                $clauses[] = "`{$col}` IS NULL";
            } else {
                $clauses[]    = "`{$col}` = {$key}";
                $params[$key] = $value;
            }
            $i++;
        }

        return [implode(' AND ', $clauses), $params];
    }

    /**
     * Valida y construye la cláusula ORDER BY de forma segura.
     */
    private function buildOrderBy(string $order): string
    {
        $order = trim($order);
        if ($order === '') {
            return '';
        }

        $parts = array_map('trim', explode(',', $order));
        $safe  = [];

        foreach ($parts as $part) {
            if (!preg_match('/^([a-zA-Z_][a-zA-Z0-9_]*)(?:\s+(ASC|DESC))?$/i', $part, $m)) {
                throw new InvalidArgumentException("Cláusula ORDER BY inválida: {$part}");
            }
            $dir    = isset($m[2]) ? ' ' . strtoupper($m[2]) : '';
            $safe[] = "`{$m[1]}`{$dir}";
        }

        return ' ORDER BY ' . implode(', ', $safe);
    }

    /**
     * Valida que un identificador SQL (tabla o columna) sea alfanumérico seguro.
     */
    private function assertIdentifier(string $identifier): void
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $identifier)) {
            throw new InvalidArgumentException("Identificador SQL inválido: {$identifier}");
        }
    }
}

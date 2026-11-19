<?php
/**
 * PetKardex v2 - InventarioController
 *
 * Módulo exclusivo para SuperAdmin y Recepcionista/Cajero.
 * Gestiona el CRUD de productos (Farmacia, Pet Shop, Insumos, Servicios),
 * alertas de stock mínimo, ajustes manuales justificados y log de movimientos.
 *
 * @author    CESAR AQUINO ANGLAS - VI B NOCHE
 * @copyright 2026 PetKardex. Todos los derechos reservados.
 * @license   Propietaria - IESTP Santiago Antúnez de Mayolo (IESTP SAM)
 */

declare(strict_types=1);

namespace PetKardex\Controllers;

use PetKardex\Config\Config;
use PetKardex\Config\Database;
use PetKardex\Core\Auth;
use PetKardex\Core\Controller;
use PetKardex\Models\Inventario;

class InventarioController extends Controller
{
    private Auth $auth;
    private Inventario $inventarioModel;

    private const ALLOWED_ROLES = ['superadmin', 'recepcionista'];
    private const ADMIN_ONLY    = ['superadmin'];

    public function __construct()
    {
        parent::__construct();
        $this->auth            = new Auth();
        $this->inventarioModel = new Inventario();
    }

    /**
     * GET /app/inventario
     * Pantalla principal con KPIs, tabla interactiva de productos y modales.
     */
    public function index(): void
    {
        $this->requireAuth(self::ALLOWED_ROLES);

        $search      = trim((string) $this->query('q', ''));
        $categoriaId = (int) $this->query('categoria_id', 0);

        $productos   = $this->inventarioModel->getAllProductos($search, $categoriaId > 0 ? $categoriaId : null);
        $categorias  = $this->inventarioModel->getCategorias();
        $criticos    = $this->inventarioModel->getProductosStockCritico();
        $stats       = $this->inventarioModel->getEstadisticas();

        $this->render('inventario/index', [
            'title'       => 'Inventario — ' . Config::APP_NAME,
            'pageTitle'   => 'Gestión de Inventario y Farmacia',
            'activeMenu'  => 'inventario',
            'auth'        => $this->auth,
            'user'        => $this->auth->getCurrentUser() ?? ($_SESSION['user'] ?? []),
            'productos'   => $productos,
            'categorias'  => $categorias,
            'criticos'    => $criticos,
            'stats'       => $stats,
            'csrfToken'   => $this->generateToken(),
        ]);
    }

    /**
     * POST /app/inventario/crear
     * Registra un nuevo producto en inventario y si tiene stock inicial registra entrada.
     */
    public function crear(): void
    {
        $this->requireAuth(self::ALLOWED_ROLES);
        $this->requireValidCsrf();

        $codigo       = strtoupper(trim((string) $this->input('codigo', '')));
        $nombre       = trim((string) $this->input('nombre', ''));
        $categoriaId  = (int) $this->input('categoria_id', 0);
        $presentacion = trim((string) $this->input('presentacion', ''));
        $unidad       = trim((string) $this->input('unidad_medida', 'Unidad'));
        $precioCompra = max(0.0, (float) $this->input('precio_compra', 0));
        $precioVenta  = max(0.0, (float) $this->input('precio_venta', 0));
        $stockInicial = max(0.0, (float) $this->input('stock_actual', 0));
        $stockMinimo  = max(0.0, (float) $this->input('stock_minimo', 5));
        $reqReceta    = isset($_POST['requiere_receta']) ? 1 : 0;
        $descripcion  = trim((string) $this->input('descripcion', ''));

        if ($codigo === '' || $nombre === '' || $categoriaId <= 0) {
            $this->setFlash('error', 'El código, nombre y categoría del producto son obligatorios.');
            $this->redirect('/app/inventario');
        }

        if ($this->inventarioModel->findOne(['codigo' => $codigo])) {
            $this->setFlash('error', "Ya existe un producto registrado con el código \"{$codigo}\".");
            $this->redirect('/app/inventario');
        }

        if ($categoriaId === $this->inventarioModel->getCategoriaServicioId() && !$this->auth->hasRole('superadmin')) {
            $this->setFlash('error', 'Solo el Super Administrador puede crear tarifas de servicios.');
            $this->redirect('/app/inventario');
        }

        try {
            $db = Database::getInstance();
            $db->transaction(function () use (
                $codigo,
                $nombre,
                $categoriaId,
                $presentacion,
                $unidad,
                $precioCompra,
                $precioVenta,
                $stockInicial,
                $stockMinimo,
                $reqReceta,
                $descripcion
            ): void {
                $nuevoId = $this->inventarioModel->create([
                    'categoria_id'    => $categoriaId,
                    'codigo'          => $codigo,
                    'nombre'          => $nombre,
                    'descripcion'     => $descripcion !== '' ? $descripcion : null,
                    'presentacion'    => $presentacion !== '' ? $presentacion : null,
                    'unidad_medida'   => $unidad !== '' ? $unidad : 'Unidad',
                    'precio_compra'   => $precioCompra,
                    'precio_venta'    => $precioVenta,
                    'stock_actual'    => 0,
                    'stock_minimo'    => $stockMinimo,
                    'requiere_receta' => $reqReceta,
                    'activo'          => 1,
                ]);

                if ($stockInicial > 0) {
                    Inventario::ajustarStock(
                        $nuevoId,
                        'entrada',
                        $stockInicial,
                        (int) ($_SESSION['user_id'] ?? 1),
                        'Stock inicial al registrar producto',
                        'registro_inicial',
                        $nuevoId
                    );
                }
            });

            $this->setFlash('success', "Producto \"{$nombre}\" registrado en el inventario.");
        } catch (\Throwable $e) {
            $this->setFlash('error', 'Error al registrar producto: ' . $e->getMessage());
        }

        $this->redirect('/app/inventario');
    }

    /**
     * POST /app/inventario/editar/{id}
     * Actualiza los datos comerciales y stock mínimo de un producto.
     */
    public function editar(string $id = ''): void
    {
        $this->requireAuth(self::ALLOWED_ROLES);
        $this->requireValidCsrf();

        $productoId = (int) $id;
        $producto   = $this->inventarioModel->findById($productoId);
        if (!$producto) {
            $this->setFlash('error', 'Producto no encontrado.');
            $this->redirect('/app/inventario');
        }

        $catServicioId = $this->inventarioModel->getCategoriaServicioId();
        if (((int) $producto['categoria_id'] === $catServicioId) && !$this->auth->hasRole('superadmin')) {
            $this->setFlash('error', 'Solo el Super Administrador puede modificar tarifas de servicios.');
            $this->redirect('/app/inventario');
        }

        $codigo      = strtoupper(trim((string) $this->input('codigo', '')));
        $nombre      = trim((string) $this->input('nombre', ''));
        $categoriaId = (int) $this->input('categoria_id', 0);

        if ($codigo === '' || $nombre === '' || $categoriaId <= 0) {
            $this->setFlash('error', 'Código, nombre y categoría son obligatorios.');
            $this->redirect('/app/inventario');
        }

        if ($categoriaId === $catServicioId && !$this->auth->hasRole('superadmin')) {
            $this->setFlash('error', 'Solo el Super Administrador puede asignar productos a la categoría Servicios.');
            $this->redirect('/app/inventario');
        }

        $duplicado = $this->inventarioModel->findOne(['codigo' => $codigo]);
        if ($duplicado && (int) $duplicado['id'] !== $productoId) {
            $this->setFlash('error', "El código \"{$codigo}\" ya pertenece a otro producto.");
            $this->redirect('/app/inventario');
        }

        $this->inventarioModel->update($productoId, [
            'categoria_id'    => $categoriaId,
            'codigo'          => $codigo,
            'nombre'          => $nombre,
            'presentacion'    => trim((string) $this->input('presentacion', '')) ?: null,
            'unidad_medida'   => trim((string) $this->input('unidad_medida', 'Unidad')) ?: 'Unidad',
            'precio_compra'   => max(0.0, (float) $this->input('precio_compra', 0)),
            'precio_venta'    => max(0.0, (float) $this->input('precio_venta', 0)),
            'stock_minimo'    => max(0.0, (float) $this->input('stock_minimo', 0)),
            'requiere_receta' => isset($_POST['requiere_receta']) ? 1 : 0,
        ]);

        $this->setFlash('success', "Producto \"{$nombre}\" actualizado.");
        $this->redirect('/app/inventario');
    }

    /**
     * POST /app/inventario/eliminar/{id}
     * Desactiva lógicamente (soft-delete) un producto preservando historial de ventas/recetas.
     */
    public function eliminar(string $id = ''): void
    {
        $this->requireAuth(self::ALLOWED_ROLES);
        $this->requireValidCsrf();

        $productoId = (int) $id;
        $producto   = $this->inventarioModel->findById($productoId);
        if (!$producto) {
            $this->setFlash('error', 'Producto no encontrado.');
            $this->redirect('/app/inventario');
        }

        $catServicioId = $this->inventarioModel->getCategoriaServicioId();
        if (((int) $producto['categoria_id'] === $catServicioId) && !$this->auth->hasRole('superadmin')) {
            $this->setFlash('error', 'Solo el Super Administrador puede modificar tarifas de servicios.');
            $this->redirect('/app/inventario');
        }

        $this->inventarioModel->softDelete($productoId);
        $this->setFlash('success', "Producto \"{$producto['nombre']}\" retirado del catálogo activo.");
        $this->redirect('/app/inventario');
    }

    /**
     * POST /app/inventario/ajustar
     * Registra entrada o salida manual justificada (compra proveedor, merma, corrección).
     */
    public function ajustar(): void
    {
        $this->requireAuth(self::ALLOWED_ROLES);
        $this->requireValidCsrf();

        $productoId = (int) $this->input('producto_id', 0);
        $tipo       = trim((string) $this->input('tipo', 'entrada'));
        $cantidad   = (float) $this->input('cantidad', 0);
        $motivo     = trim((string) $this->input('motivo', ''));

        if ($productoId <= 0 || $motivo === '') {
            $this->setFlash('error', 'Selecciona el producto e indica un motivo obligatorio para el ajuste.');
            $this->redirect('/app/inventario');
        }

        try {
            $res = Inventario::ajustarStock(
                $productoId,
                $tipo,
                $cantidad,
                (int) ($_SESSION['user_id'] ?? 1),
                $motivo
            );
            $this->setFlash(
                'success',
                "Inventario actualizado: stock anterior {$res['stock_anterior']} → nuevo stock {$res['stock_nuevo']}."
            );
        } catch (\Throwable $e) {
            $this->setFlash('error', 'No se pudo realizar el ajuste: ' . $e->getMessage());
        }

        $this->redirect('/app/inventario');
    }

    /**
     * GET /app/inventario/alertas
     * Devuelve en JSON (o vista) los productos por debajo o igual a su stock mínimo.
     */
    public function alertas(): void
    {
        $this->requireAuth(self::ALLOWED_ROLES);

        $criticos = $this->inventarioModel->getProductosStockCritico();

        if ($this->isAjax()) {
            $this->json([
                'success'  => true,
                'count'    => count($criticos),
                'criticos' => $criticos,
            ]);
        }

        $this->json([
            'success'  => true,
            'count'    => count($criticos),
            'criticos' => $criticos,
        ]);
    }

    /**
     * GET /app/inventario/movimientos
     * Muestra el historial auditable de entradas (compras/ajustes) y salidas (ventas/recetas).
     */
    public function movimientos(): void
    {
        $this->requireAuth(self::ALLOWED_ROLES);

        $productoId  = (int) $this->query('producto_id', 0);
        $movimientos = $this->inventarioModel->getHistorialMovimientos(150, $productoId > 0 ? $productoId : null);

        $this->render('inventario/movimientos', [
            'title'       => 'Movimientos de Inventario — ' . Config::APP_NAME,
            'pageTitle'   => 'Historial de Movimientos de Stock',
            'activeMenu'  => 'inventario',
            'auth'        => $this->auth,
            'user'        => $this->auth->getCurrentUser() ?? ($_SESSION['user'] ?? []),
            'movimientos' => $movimientos,
        ]);
    }

    /**
     * GET /app/servicios
     * Gestor dinámico de Servicios y Tarifas (exclusivo SuperAdmin).
     */
    public function servicios(): void
    {
        $this->requireAuth(self::ADMIN_ONLY);

        $search    = trim((string) $this->query('q', ''));
        $servicios = $this->inventarioModel->getServicios($search);

        $this->render('inventario/servicios', [
            'title'      => 'Servicios y Tarifas — ' . Config::APP_NAME,
            'pageTitle'  => 'Gestión de Servicios y Tarifas (POS / Clínica)',
            'activeMenu' => 'servicios',
            'auth'       => $this->auth,
            'user'       => $this->auth->getCurrentUser() ?? ($_SESSION['user'] ?? []),
            'servicios'  => $servicios,
            'search'     => $search,
            'csrfToken'  => $this->generateToken(),
        ]);
    }

    /**
     * POST /app/servicios/crear
     * Crea un nuevo servicio en catálogo dinámico (exclusivo SuperAdmin).
     */
    public function crearServicio(): void
    {
        $this->requireAuth(self::ADMIN_ONLY);
        $this->requireValidCsrf();

        $codigo      = strtoupper(trim((string) $this->input('codigo', '')));
        $nombre      = trim((string) $this->input('nombre', ''));
        $descripcion = trim((string) $this->input('descripcion', ''));
        $precioVenta = max(0.0, (float) $this->input('precio_venta', 0));

        if ($codigo === '' || $nombre === '') {
            $this->setFlash('error', 'El código y nombre del servicio son obligatorios.');
            $this->redirect('/app/servicios');
        }

        if ($this->inventarioModel->findOne(['codigo' => $codigo])) {
            $this->setFlash('error', "Ya existe un ítem o servicio registrado con el código \"{$codigo}\".");
            $this->redirect('/app/servicios');
        }

        try {
            $this->inventarioModel->create([
                'categoria_id'    => $this->inventarioModel->getCategoriaServicioId(),
                'codigo'          => $codigo,
                'nombre'          => $nombre,
                'descripcion'     => $descripcion !== '' ? $descripcion : null,
                'presentacion'    => 'Servicio',
                'unidad_medida'   => 'Servicio',
                'precio_compra'   => 0.00,
                'precio_venta'    => $precioVenta,
                'stock_actual'    => 0.00,
                'stock_minimo'    => 0.00,
                'requiere_receta' => 0,
                'activo'          => 1,
            ]);

            $this->setFlash('success', "Servicio \"{$nombre}\" creado con tarifa S/. " . number_format($precioVenta, 2) . '.');
        } catch (\Throwable $e) {
            $this->setFlash('error', 'No se pudo crear el servicio: ' . $e->getMessage());
        }

        $this->redirect('/app/servicios');
    }

    /**
     * POST /app/servicios/editar/{id}
     * Actualiza nombre, código, descripción y precio dinámico de un servicio (SuperAdmin).
     */
    public function editarServicio(string $id = ''): void
    {
        $this->requireAuth(self::ADMIN_ONLY);
        $this->requireValidCsrf();

        $servicioId = (int) $id;
        $servicio   = $this->inventarioModel->findById($servicioId);
        $catServId  = $this->inventarioModel->getCategoriaServicioId();

        if (!$servicio || (int) $servicio['categoria_id'] !== $catServId) {
            $this->setFlash('error', 'Servicio no encontrado.');
            $this->redirect('/app/servicios');
        }

        $codigo      = strtoupper(trim((string) $this->input('codigo', '')));
        $nombre      = trim((string) $this->input('nombre', ''));
        $descripcion = trim((string) $this->input('descripcion', ''));
        $precioVenta = max(0.0, (float) $this->input('precio_venta', 0));

        if ($codigo === '' || $nombre === '') {
            $this->setFlash('error', 'El código y nombre del servicio son obligatorios.');
            $this->redirect('/app/servicios');
        }

        $dup = $this->inventarioModel->findOne(['codigo' => $codigo]);
        if ($dup && (int) $dup['id'] !== $servicioId) {
            $this->setFlash('error', "El código \"{$codigo}\" ya pertenece a otro servicio o producto.");
            $this->redirect('/app/servicios');
        }

        $this->inventarioModel->update($servicioId, [
            'codigo'       => $codigo,
            'nombre'       => $nombre,
            'descripcion'  => $descripcion !== '' ? $descripcion : null,
            'precio_venta' => $precioVenta,
        ]);

        $this->setFlash('success', "Tarifa de \"{$nombre}\" actualizada a S/. " . number_format($precioVenta, 2) . '.');
        $this->redirect('/app/servicios');
    }

    /**
     * POST /app/servicios/eliminar/{id}
     * Retira lógicamente un servicio del catálogo activo (SuperAdmin).
     */
    public function eliminarServicio(string $id = ''): void
    {
        $this->requireAuth(self::ADMIN_ONLY);
        $this->requireValidCsrf();

        $servicioId = (int) $id;
        $servicio   = $this->inventarioModel->findById($servicioId);
        $catServId  = $this->inventarioModel->getCategoriaServicioId();

        if (!$servicio || (int) $servicio['categoria_id'] !== $catServId) {
            $this->setFlash('error', 'Servicio no encontrado.');
            $this->redirect('/app/servicios');
        }

        $this->inventarioModel->softDelete($servicioId);
        $this->setFlash('success', "Servicio \"{$servicio['nombre']}\" retirado del catálogo POS.");
        $this->redirect('/app/servicios');
    }
}


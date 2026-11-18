<?php
/**
 * PetKardex v2 - CajaController (Punto de Venta / POS)
 *
 * Exclusivo para SuperAdmin y Recepcionista/Cajero.
 * Permite ventas de mostrador, cobro de servicios, liquidación de cuentas médicas
 * buscadas por DNI o UUID QR, cálculo de vuelto y emisión de comprobante.
 */

declare(strict_types=1);

namespace PetKardex\Controllers;

use PetKardex\Config\Config;
use PetKardex\Core\Auth;
use PetKardex\Core\Controller;
use PetKardex\Models\Inventario;
use PetKardex\Models\Venta;

class CajaController extends Controller
{
    private Auth $auth;
    private Inventario $inventarioModel;
    private Venta $ventaModel;

    private const ALLOWED_ROLES = ['superadmin', 'recepcionista'];

    public function __construct()
    {
        parent::__construct();
        $this->auth            = new Auth();
        $this->inventarioModel = new Inventario();
        $this->ventaModel      = new Venta();
    }

    /**
     * GET /app/caja | /app/caja/pos | /app/ventas/nueva
     * Interfaz optimizada de Punto de Venta (POS).
     */
    public function pos(): void
    {
        $this->requireAuth(self::ALLOWED_ROLES);

        $catalogo      = $this->inventarioModel->getCatalogoPOS();
        $categorias    = $this->inventarioModel->getCategorias();
        $config        = $this->ventaModel->getConfigFinanciera();
        $ultimasVentas = $this->ventaModel->getVentasRecientes(10);

        $this->render('caja/pos', [
            'title'            => 'Punto de Venta (Caja) — ' . Config::APP_NAME,
            'pageTitle'        => 'Punto de Venta · Caja POS',
            'activeMenu'       => 'pos',
            'auth'             => $this->auth,
            'user'             => $this->auth->getCurrentUser() ?? ($_SESSION['user'] ?? []),
            'catalogo'         => $catalogo,
            'categorias'       => $categorias,
            'configFinanciera' => $config,
            'ultimasVentas'    => $ultimasVentas,
            'csrfToken'        => $this->generateToken(),
        ]);
    }

    /**
     * GET /app/caja/buscar-cuenta?q=...
     * Busca un cliente por DNI o UUID QR de mascota y retorna sus cuentas,
     * recetas recientes o servicios pendientes de cobro en JSON.
     */
    public function buscarCuenta(): void
    {
        $this->requireAuth(self::ALLOWED_ROLES);

        $termino = trim((string) $this->query('q', ''));
        if ($termino === '') {
            $this->json(['success' => false, 'error' => 'Ingresa un DNI, nombre o código QR de mascota.'], 422);
        }

        $resultado = $this->ventaModel->buscarCuentaCliente($termino);

        if (!$resultado['propietario']) {
            $this->json(['success' => false, 'error' => 'No se encontró ningún propietario o mascota con ese criterio.'], 404);
        }

        $this->json([
            'success' => true,
            'data'    => $resultado,
        ]);
    }

    /**
     * POST /app/caja/venta
     * Procesa el cobro de un ticket POS vía Fetch API (JSON),
     * registra el pago y descuenta stock del inventario.
     */
    public function procesarVenta(): void
    {
        $this->requireAuth(self::ALLOWED_ROLES);
        $this->requireValidCsrf();

        $rawBody = file_get_contents('php://input');
        $payload = [];

        if ($rawBody !== false && $rawBody !== '') {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        if (empty($payload)) {
            $payload = $_POST;
        }

        try {
            $cajeroId = (int) ($_SESSION['user_id'] ?? 0);
            $res      = $this->ventaModel->registrarVenta($payload, $cajeroId);

            $montoRecibido = isset($payload['monto_recibido']) ? (float) $payload['monto_recibido'] : $res['total'];
            $cambio        = ($payload['metodo_pago'] ?? '') === 'efectivo'
                ? max(0.0, round($montoRecibido - $res['total'], 2))
                : 0.0;

            $this->json([
                'success'      => true,
                'venta_id'     => $res['venta_id'],
                'numero_venta' => $res['numero_venta'],
                'total'        => $res['total'],
                'cambio'       => $cambio,
                'receipt_url'  => '/app/caja/recibo/' . $res['venta_id'],
            ]);
        } catch (\Throwable $e) {
            $this->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * GET /app/caja/recibo/{id}
     * Muestra el comprobante de venta imprimible.
     */
    public function recibo(string $id = ''): void
    {
        $this->requireAuth(self::ALLOWED_ROLES);

        $ventaId = (int) $id;
        $venta   = $this->ventaModel->getVentaDetalle($ventaId);

        if (!$venta) {
            $this->setFlash('error', 'Comprobante de venta no encontrado.');
            $this->redirect('/app/caja');
        }

        $this->render('caja/recibo', [
            'title'            => 'Comprobante ' . $venta['numero_venta'] . ' — ' . Config::APP_NAME,
            'venta'            => $venta,
            'configFinanciera' => $this->ventaModel->getConfigFinanciera(),
        ]);
    }

    /**
     * POST /app/caja/anular/{id}
     * Anula una venta y devuelve los productos físicos al stock (sólo SuperAdmin).
     */
    public function anular(string $id = ''): void
    {
        $this->requireAuth(['superadmin']);
        $this->requireValidCsrf();

        $ventaId = (int) $id;

        try {
            $this->ventaModel->anularVenta($ventaId, (int) ($_SESSION['user_id'] ?? 1));
            $this->setFlash('success', 'Venta anulada y stock devuelto al inventario.');
        } catch (\Throwable $e) {
            $this->setFlash('error', 'No se pudo anular la venta: ' . $e->getMessage());
        }

        $this->redirect('/app/caja');
    }
}


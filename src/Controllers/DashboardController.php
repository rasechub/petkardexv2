<?php
/**
 * PetKardex v2 - DashboardController
 *
 * Controlador principal del sistema interno tras el inicio de sesión.
 * Consulta métricas reales en MySQL según el rol del usuario autenticado
 * y carga alertas médicas internas pendientes para el equipo clínico.
 */

declare(strict_types=1);

namespace PetKardex\Controllers;

use PetKardex\Config\Config;
use PetKardex\Config\Database;
use PetKardex\Core\Auth;
use PetKardex\Core\Controller;
use PetKardex\Models\Estetica;

class DashboardController extends Controller
{
    private Auth $auth;
    private Database $db;

    public function __construct()
    {
        parent::__construct();
        $this->auth = new Auth();
        $this->db   = Database::getInstance();
    }

    /**
     * GET /app/dashboard
     * Pantalla principal del sistema interno tras iniciar sesión.
     */
    public function index(): void
    {
        $this->requireAuth();

        $user = $this->auth->getCurrentUser() ?? ($_SESSION['user'] ?? null);
        if (!$user) {
            $this->redirect('/app/login');
        }

        $rolSlug = (string) ($user['rol_slug'] ?? '');
        $userId  = (int) ($user['id'] ?? 0);

        $alertasMedicas = [];
        if ($this->auth->hasRole(['superadmin', 'veterinario', 'guardia', 'emergencia'])) {
            try {
                $alertasMedicas = Estetica::getAlertasMedicasPendientes(8);
            } catch (\Throwable) {
                $alertasMedicas = [];
            }
        }

        $moneda = $this->getMonedaSimbolo();
        $stats  = $this->loadRoleStats($rolSlug, $userId);

        $this->render('dashboard/index', [
            'title'          => 'Dashboard — ' . Config::APP_NAME,
            'pageTitle'      => 'Panel Principal',
            'activeMenu'     => 'dashboard',
            'auth'           => $this->auth,
            'user'           => $user,
            'stats'          => $stats,
            'moneda'         => $moneda,
            'alertasMedicas' => $alertasMedicas,
            'csrfToken'      => $this->generateToken(),
        ]);
    }

    private function getMonedaSimbolo(): string
    {
        $row = $this->db->fetchOne(
            "SELECT valor FROM configuracion_sistema WHERE clave = 'moneda_simbolo' LIMIT 1"
        );
        $val = trim((string) ($row['valor'] ?? ''));
        if ($val === '' || $val === 'S/') {
            return 'S/.';
        }
        return $val;
    }

    /**
     * Carga estadísticas reales desde MySQL según el rol del usuario.
     */
    private function loadRoleStats(string $rolSlug, int $userId): array
    {
        if ($rolSlug === 'superadmin') {
            $ingresosHoy = (float) ($this->db->fetchOne(
                "SELECT COALESCE(SUM(total), 0) AS total FROM ventas WHERE estado = 'pagada' AND DATE(fecha) = CURDATE()"
            )['total'] ?? 0);

            $ingresosAyer = (float) ($this->db->fetchOne(
                "SELECT COALESCE(SUM(total), 0) AS total FROM ventas WHERE estado = 'pagada' AND DATE(fecha) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)"
            )['total'] ?? 0);

            $totalMascotas = (int) ($this->db->fetchOne(
                "SELECT COUNT(*) AS total FROM pacientes WHERE activo = 1"
            )['total'] ?? 0);

            $mascotasMes = (int) ($this->db->fetchOne(
                "SELECT COUNT(*) AS total FROM pacientes WHERE activo = 1 AND MONTH(fecha_registro) = MONTH(CURDATE()) AND YEAR(fecha_registro) = YEAR(CURDATE())"
            )['total'] ?? 0);

            $citasPendientes = (int) ($this->db->fetchOne(
                "SELECT COUNT(*) AS total FROM citas WHERE estado IN ('programada','confirmada','en_proceso')"
            )['total'] ?? 0);

            $citasConfirmadasHoy = (int) ($this->db->fetchOne(
                "SELECT COUNT(*) AS total FROM citas WHERE DATE(fecha_hora) = CURDATE() AND estado = 'confirmada'"
            )['total'] ?? 0);

            $hospActivos = (int) ($this->db->fetchOne(
                "SELECT COUNT(*) AS total FROM hospitalizaciones WHERE estado = 'activo'"
            )['total'] ?? 0);

            $consultasHoy = (int) ($this->db->fetchOne(
                "SELECT COUNT(*) AS total FROM historial_clinico WHERE DATE(fecha_consulta) = CURDATE()"
            )['total'] ?? 0);

            $ventasHoyCount = (int) ($this->db->fetchOne(
                "SELECT COUNT(*) AS total FROM ventas WHERE estado = 'pagada' AND DATE(fecha) = CURDATE()"
            )['total'] ?? 0);

            $esteticaHoy = (int) ($this->db->fetchOne(
                "SELECT COUNT(*) AS total FROM agenda_estetica WHERE DATE(fecha_hora) = CURDATE()"
            )['total'] ?? 0);

            $esteticaEnCurso = (int) ($this->db->fetchOne(
                "SELECT COUNT(*) AS total FROM agenda_estetica WHERE DATE(fecha_hora) = CURDATE() AND estado = 'en_proceso'"
            )['total'] ?? 0);

            $stockCritico = (int) ($this->db->fetchOne(
                "SELECT COUNT(*) AS total
                 FROM productos p
                 INNER JOIN categorias_inventario c ON c.id = p.categoria_id
                 WHERE p.activo = 1 AND c.tipo <> 'servicio' AND p.stock_actual <= p.stock_minimo"
            )['total'] ?? 0);

            return [
                'ingresos_hoy'          => $ingresosHoy,
                'ingresos_ayer'         => $ingresosAyer,
                'total_mascotas'        => $totalMascotas,
                'mascotas_mes'          => $mascotasMes,
                'citas_pendientes'      => $citasPendientes,
                'citas_confirmadas_hoy' => $citasConfirmadasHoy,
                'hospitalizados'        => $hospActivos,
                'consultas_hoy'         => $consultasHoy,
                'ventas_hoy_count'      => $ventasHoyCount,
                'estetica_hoy'          => $esteticaHoy,
                'estetica_en_curso'     => $esteticaEnCurso,
                'stock_critico'         => $stockCritico,
            ];
        }

        if ($rolSlug === 'recepcionista') {
            $citasHoy = (int) ($this->db->fetchOne(
                "SELECT COUNT(*) AS total FROM citas WHERE DATE(fecha_hora) = CURDATE() AND estado <> 'cancelada'"
            )['total'] ?? 0);

            $citasEspera = (int) ($this->db->fetchOne(
                "SELECT COUNT(*) AS total FROM citas WHERE DATE(fecha_hora) = CURDATE() AND estado IN ('confirmada','en_proceso')"
            )['total'] ?? 0);

            $cobrosHoy = (float) ($this->db->fetchOne(
                "SELECT COALESCE(SUM(total), 0) AS total FROM ventas WHERE estado = 'pagada' AND DATE(fecha) = CURDATE()"
            )['total'] ?? 0);

            $ventasHoy = (int) ($this->db->fetchOne(
                "SELECT COUNT(*) AS total FROM ventas WHERE estado = 'pagada' AND DATE(fecha) = CURDATE()"
            )['total'] ?? 0);

            $cuentasPendientes = (int) ($this->db->fetchOne(
                "SELECT COUNT(*) AS total FROM ventas WHERE estado = 'pendiente'"
            )['total'] ?? 0);

            $stockCritico = (int) ($this->db->fetchOne(
                "SELECT COUNT(*) AS total
                 FROM productos p
                 INNER JOIN categorias_inventario c ON c.id = p.categoria_id
                 WHERE p.activo = 1 AND c.tipo <> 'servicio' AND p.stock_actual <= p.stock_minimo"
            )['total'] ?? 0);

            return [
                'citas_hoy'          => $citasHoy,
                'citas_espera'       => $citasEspera,
                'cobros_hoy'         => $cobrosHoy,
                'ventas_hoy'         => $ventasHoy,
                'cuentas_pendientes' => $cuentasPendientes,
                'stock_critico'      => $stockCritico,
            ];
        }

        if ($rolSlug === 'propietario') {
            $mascotasCount = (int) ($this->db->fetchOne(
                "SELECT COUNT(*) AS total
                 FROM pacientes p
                 INNER JOIN propietarios pr ON pr.id = p.propietario_id
                 WHERE pr.usuario_id = :uid AND p.activo = 1",
                [':uid' => $userId]
            )['total'] ?? 0);

            $qrActivos = (int) ($this->db->fetchOne(
                "SELECT COUNT(*) AS total
                 FROM pacientes p
                 INNER JOIN propietarios pr ON pr.id = p.propietario_id
                 WHERE pr.usuario_id = :uid AND p.activo = 1 AND p.qr_code IS NOT NULL AND p.qr_code <> ''",
                [':uid' => $userId]
            )['total'] ?? 0);

            $proxVacuna = $this->db->fetchOne(
                "SELECT v.proxima_dosis, pac.nombre AS mascota_nombre, vc.nombre AS vacuna_nombre
                 FROM vacunaciones v
                 INNER JOIN pacientes pac ON pac.id = v.paciente_id
                 INNER JOIN propietarios pr ON pr.id = pac.propietario_id
                 INNER JOIN vacunas_catalogo vc ON vc.id = v.vacuna_id
                 WHERE pr.usuario_id = :uid AND pac.activo = 1 AND v.proxima_dosis >= CURDATE()
                 ORDER BY v.proxima_dosis ASC
                 LIMIT 1",
                [':uid' => $userId]
            );

            $citasProximas = (int) ($this->db->fetchOne(
                "SELECT COUNT(*) AS total
                 FROM citas c
                 INNER JOIN propietarios pr ON pr.id = c.propietario_id
                 WHERE pr.usuario_id = :uid AND c.fecha_hora >= NOW() AND c.estado IN ('programada','confirmada')",
                [':uid' => $userId]
            )['total'] ?? 0);

            $primeraMascota = $this->db->fetchOne(
                "SELECT p.id, p.nombre
                 FROM pacientes p
                 INNER JOIN propietarios pr ON pr.id = p.propietario_id
                 WHERE pr.usuario_id = :uid AND p.activo = 1
                 ORDER BY p.id ASC LIMIT 1",
                [':uid' => $userId]
            );

            return [
                'mascotas_count'     => $mascotasCount,
                'qr_activos'         => $qrActivos,
                'proxima_vacuna'     => $proxVacuna,
                'citas_proximas'     => $citasProximas,
                'primera_mascota_id' => $primeraMascota ? (int) $primeraMascota['id'] : null,
            ];
        }

        if ($rolSlug === 'veterinario') {
            return [
                'citas_hoy' => (int) ($this->db->fetchOne(
                    "SELECT COUNT(*) AS total FROM citas WHERE DATE(fecha_hora) = CURDATE() AND estado <> 'cancelada'"
                )['total'] ?? 0),
                'hospitalizados' => (int) ($this->db->fetchOne(
                    "SELECT COUNT(*) AS total FROM hospitalizaciones WHERE estado = 'activo'"
                )['total'] ?? 0),
                'recetas_hoy' => (int) ($this->db->fetchOne(
                    "SELECT COUNT(*) AS total FROM recetas WHERE fecha = CURDATE()"
                )['total'] ?? 0),
                'certificados_hoy' => (int) ($this->db->fetchOne(
                    "SELECT COUNT(*) AS total FROM documentos_clinicos WHERE tipo = 'certificado' AND DATE(created_at) = CURDATE()"
                )['total'] ?? 0),
            ];
        }

        if (in_array($rolSlug, ['guardia', 'emergencia'], true)) {
            return [
                'urgencias_hoy' => (int) ($this->db->fetchOne(
                    "SELECT COUNT(*) AS total FROM citas WHERE DATE(fecha_hora) = CURDATE() AND tipo_servicio = 'emergencia'"
                )['total'] ?? 0),
                'hospitalizados' => (int) ($this->db->fetchOne(
                    "SELECT COUNT(*) AS total FROM hospitalizaciones WHERE estado = 'activo'"
                )['total'] ?? 0),
                'bitacoras_hoy' => (int) ($this->db->fetchOne(
                    "SELECT COUNT(*) AS total FROM signos_vitales WHERE DATE(fecha_hora) = CURDATE()"
                )['total'] ?? 0),
            ];
        }

        if ($rolSlug === 'groomer') {
            return [
                'estetica_hoy' => (int) ($this->db->fetchOne(
                    "SELECT COUNT(*) AS total FROM agenda_estetica WHERE DATE(fecha_hora) = CURDATE()"
                )['total'] ?? 0),
                'completadas_hoy' => (int) ($this->db->fetchOne(
                    "SELECT COUNT(*) AS total FROM agenda_estetica WHERE DATE(fecha_hora) = CURDATE() AND estado = 'completada'"
                )['total'] ?? 0),
                'alertas_hoy' => (int) ($this->db->fetchOne(
                    "SELECT COUNT(*) AS total FROM alertas_internas WHERE DATE(created_at) = CURDATE() AND origen_usuario_id = :uid",
                    [':uid' => $userId]
                )['total'] ?? 0),
            ];
        }

        return [];
    }
}

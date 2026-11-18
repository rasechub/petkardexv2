<?php
/**
 * PetKardex v2 - KardexController (Módulo Clínico)
 *
 * Reglas de negocio estrictas:
 * - Veterinarios (tratantes) y SuperAdmin pueden crear registros clínicos y vacunas.
 * - Propietarios tienen acceso estrictamente de SOLO LECTURA sobre sus propias mascotas.
 * - Inmutabilidad legal: ningún registro clínico o de vacunación puede editarse ni
 *   eliminarse transcurridas 24 horas desde su creación.
 * - Al recetar productos de inventario, descuenta stock automáticamente vía Inventario::descontarStock().
 */

declare(strict_types=1);

namespace PetKardex\Controllers;

use PetKardex\Config\Config;
use PetKardex\Config\Database;
use PetKardex\Core\Auth;
use PetKardex\Core\Controller;
use PetKardex\Helpers\Security;
use PetKardex\Models\Estetica;
use PetKardex\Models\Inventario;
use PetKardex\Models\Kardex;

class KardexController extends Controller
{
    private Database $db;
    private Auth $auth;
    private Kardex $kardexModel;
    private Inventario $inventarioModel;

    private const READ_ROLES  = ['superadmin', 'veterinario', 'guardia', 'emergencia', 'recepcionista', 'propietario'];
    private const WRITE_ROLES = ['superadmin', 'veterinario'];

    public function __construct()
    {
        parent::__construct();
        $this->db              = Database::getInstance();
        $this->auth            = new Auth();
        $this->kardexModel     = new Kardex();
        $this->inventarioModel = new Inventario();
    }

    // ─── Vista Principal del Kardex ─────────────────────────────────────────

    /**
     * GET /app/kardex/{id}
     * Muestra el Kardex completo con pestañas: Resumen, Vacunas, Consultas/Recetas.
     */
    public function ver(string $id = ''): void
    {
        $this->requireAuth(self::READ_ROLES);

        $pacienteId = (int) $id;
        if ($pacienteId <= 0) {
            $this->setFlash('error', 'Identificador de paciente inválido.');
            $this->redirect('/app/dashboard');
        }

        $paciente = $this->kardexModel->getPacienteCompleto($pacienteId);
        if (!$paciente) {
            $this->setFlash('error', 'No se encontró el expediente clínico solicitado.');
            $this->redirect('/app/dashboard');
        }

        $this->assertOwnershipIfPropietario($paciente);

        $user     = $this->auth->getCurrentUser() ?? ($_SESSION['user'] ?? []);
        $canWrite = $this->auth->hasRole(self::WRITE_ROLES);

        $consultas    = $this->kardexModel->getConsultasConRecetas($pacienteId);
        $vacunaciones = $this->kardexModel->getVacunaciones($pacienteId);
        $cirugias     = $this->kardexModel->getCirugiasPrevias($pacienteId);
        $alertasInt   = Estetica::getAlertasPorPaciente($pacienteId);

        $vacunasCatalogo = $canWrite ? $this->kardexModel->getVacunasCatalogo() : [];
        $medicamentos    = $canWrite ? $this->inventarioModel->getMedicamentosDisponibles() : [];

        $this->render('clinica/kardex', [
            'title'           => 'Kardex Clínico: ' . $paciente['nombre'] . ' — ' . Config::APP_NAME,
            'pageTitle'       => 'Kardex Clínico · ' . $paciente['nombre'],
            'activeMenu'      => $this->auth->hasRole('propietario') ? 'historial' : 'pacientes',
            'auth'            => $this->auth,
            'user'            => $user,
            'canWrite'        => $canWrite,
            'paciente'        => $paciente,
            'consultas'       => $consultas,
            'vacunaciones'    => $vacunaciones,
            'cirugias'        => $cirugias,
            'alertasInternas' => $alertasInt,
            'vacunasCatalogo' => $vacunasCatalogo,
            'medicamentos'    => $medicamentos,
            'csrfToken'       => $this->generateToken(),
        ]);
    }

    // ─── Consultas Médicas y Recetas ────────────────────────────────────────

    /**
     * GET /app/kardex/consulta/nueva?paciente_id={id}
     * Muestra la vista independiente para registrar una nueva consulta médica.
     */
    public function consultaForm(): void
    {
        $this->requireAuth(self::WRITE_ROLES);

        $pacienteId = (int) $this->query('paciente_id', 0);
        if ($pacienteId <= 0) {
            $this->setFlash('error', 'Selecciona un paciente para registrar la consulta.');
            $this->redirect('/app/qr/scanner');
        }

        $paciente = $this->kardexModel->getPacienteCompleto($pacienteId);
        if (!$paciente) {
            $this->setFlash('error', 'Paciente no encontrado.');
            $this->redirect('/app/qr/scanner');
        }

        $this->render('clinica/consulta_nueva', [
            'title'        => 'Nueva Consulta: ' . $paciente['nombre'] . ' — ' . Config::APP_NAME,
            'pageTitle'    => 'Registrar Consulta Médica',
            'activeMenu'   => 'pacientes',
            'auth'         => $this->auth,
            'user'         => $this->auth->getCurrentUser() ?? ($_SESSION['user'] ?? []),
            'paciente'     => $paciente,
            'medicamentos' => $this->inventarioModel->getMedicamentosDisponibles(),
            'csrfToken'    => $this->generateToken(),
        ]);
    }

    /**
     * POST /app/kardex/consulta/nueva
     * Guarda una nueva consulta clínica, opcionalmente receta medicamentos
     * y descuenta el stock automáticamente.
     */
    public function guardarConsulta(): void
    {
        $this->requireAuth(self::WRITE_ROLES);
        $this->requireValidCsrf();

        $pacienteId = (int) $this->input('paciente_id', 0);
        if ($pacienteId <= 0) {
            $this->setFlash('error', 'Paciente inválido.');
            $this->redirect('/app/dashboard');
        }

        $paciente = $this->kardexModel->getPacienteCompleto($pacienteId);
        if (!$paciente) {
            $this->setFlash('error', 'Paciente no encontrado.');
            $this->redirect('/app/dashboard');
        }

        $motivo       = trim((string) $this->input('motivo', ''));
        $diagnostico  = trim((string) $this->input('diagnostico', ''));
        $tratamiento  = trim((string) $this->input('tratamiento', ''));
        $tipoConsulta = trim((string) $this->input('tipo_consulta', 'consulta'));

        $allowedTypes = ['consulta', 'emergencia', 'control', 'vacunacion', 'cirugia', 'estetica'];
        if (!in_array($tipoConsulta, $allowedTypes, true)) {
            $tipoConsulta = 'consulta';
        }

        if ($motivo === '' || $diagnostico === '') {
            $this->setFlash('error', 'El motivo de consulta y el diagnóstico son obligatorios.');
            $this->redirect("/app/kardex/{$pacienteId}#tab-consultas");
        }

        $pesoRaw = $this->input('peso_kg', '');
        $tempRaw = $this->input('temperatura', '');
        $fcRaw   = $this->input('frecuencia_cardiaca', '');
        $frRaw   = $this->input('frecuencia_respiratoria', '');

        $examenFisico = [
            'peso_kg'                 => $pesoRaw !== '' ? (float) $pesoRaw : null,
            'temperatura'             => $tempRaw !== '' ? (float) $tempRaw : null,
            'frecuencia_cardiaca'     => $fcRaw !== '' ? (int) $fcRaw : null,
            'frecuencia_respiratoria' => $frRaw !== '' ? (int) $frRaw : null,
        ];

        $veterinarioId  = (int) ($_SESSION['user_id'] ?? 0);
        $anamnesis      = trim((string) $this->input('anamnesis', ''));
        $diagnosticoCie = trim((string) $this->input('diagnostico_cie', ''));
        $observaciones  = trim((string) $this->input('observaciones', ''));
        $proximoControl = trim((string) $this->input('proximo_control', ''));
        $instrucciones  = trim((string) $this->input('receta_instrucciones', ''));

        // Ítems de receta enviados en arrays paralelos
        $prodIds      = $_POST['med_producto_id'] ?? [];
        $medNombres   = $_POST['med_nombre'] ?? [];
        $medDosis     = $_POST['med_dosis'] ?? [];
        $medFrec      = $_POST['med_frecuencia'] ?? [];
        $medDuracion  = $_POST['med_duracion'] ?? [];
        $medCantidad  = $_POST['med_cantidad'] ?? [];

        try {
            $this->db->transaction(function (Database $db) use (
                $pacienteId,
                $veterinarioId,
                $tipoConsulta,
                $motivo,
                $anamnesis,
                $examenFisico,
                $diagnostico,
                $diagnosticoCie,
                $tratamiento,
                $observaciones,
                $proximoControl,
                $instrucciones,
                $prodIds,
                $medNombres,
                $medDosis,
                $medFrec,
                $medDuracion,
                $medCantidad
            ): void {
                $db->execute(
                    "INSERT INTO historial_clinico
                        (paciente_id, veterinario_id, fecha_consulta, tipo_consulta, motivo, anamnesis,
                         examen_fisico, diagnostico, diagnostico_cie, tratamiento, observaciones, proximo_control, created_at)
                     VALUES
                        (:pid, :vid, NOW(), :tipo, :motivo, :anamnesis,
                         :examen, :diag, :cie, :trat, :obs, :prox, NOW())",
                    [
                        ':pid'       => $pacienteId,
                        ':vid'       => $veterinarioId,
                        ':tipo'      => $tipoConsulta,
                        ':motivo'    => $motivo,
                        ':anamnesis' => $anamnesis !== '' ? $anamnesis : null,
                        ':examen'    => json_encode($examenFisico, JSON_UNESCAPED_UNICODE),
                        ':diag'      => $diagnostico,
                        ':cie'       => $diagnosticoCie !== '' ? $diagnosticoCie : null,
                        ':trat'      => $tratamiento !== '' ? $tratamiento : null,
                        ':obs'       => $observaciones !== '' ? $observaciones : null,
                        ':prox'      => $proximoControl !== '' ? $proximoControl : null,
                    ]
                );

                $historialId = $db->lastInsertId();

                // Actualizar peso actual del paciente si se registró
                if ($examenFisico['peso_kg'] !== null && $examenFisico['peso_kg'] > 0) {
                    $db->execute(
                        "UPDATE pacientes SET peso_kg = :peso, updated_at = NOW() WHERE id = :id",
                        [':peso' => $examenFisico['peso_kg'], ':id' => $pacienteId]
                    );
                }

                // Preparar ítems válidos de receta
                $itemsParaRecetar = [];
                if (is_array($medNombres)) {
                    foreach ($medNombres as $idx => $nombreMed) {
                        $nombreMed = trim((string) $nombreMed);
                        $prodId    = isset($prodIds[$idx]) ? (int) $prodIds[$idx] : 0;
                        $cantidad  = isset($medCantidad[$idx]) ? (float) $medCantidad[$idx] : 0.0;

                        if ($prodId > 0 && $nombreMed === '') {
                            $prod = $db->fetchOne(
                                "SELECT nombre, presentacion FROM productos WHERE id = :id LIMIT 1",
                                [':id' => $prodId]
                            );
                            if ($prod) {
                                $nombreMed = (string) $prod['nombre'];
                            }
                        }

                        if ($nombreMed === '' && $prodId <= 0) {
                            continue;
                        }

                        $itemsParaRecetar[] = [
                            'producto_id'  => $prodId > 0 ? $prodId : null,
                            'nombre'       => $nombreMed,
                            'dosis'        => trim((string) ($medDosis[$idx] ?? '')),
                            'frecuencia'   => trim((string) ($medFrec[$idx] ?? '')),
                            'duracion'     => trim((string) ($medDuracion[$idx] ?? '')),
                            'cantidad'     => $cantidad > 0 ? $cantidad : 1.0,
                        ];
                    }
                }

                if (!empty($itemsParaRecetar)) {
                    $db->execute(
                        "INSERT INTO recetas (historial_id, paciente_id, veterinario_id, fecha, instrucciones, valida_hasta, created_at)
                         VALUES (:hid, :pid, :vid, CURDATE(), :inst, DATE_ADD(CURDATE(), INTERVAL 15 DAY), NOW())",
                        [
                            ':hid'  => $historialId,
                            ':pid'  => $pacienteId,
                            ':vid'  => $veterinarioId,
                            ':inst' => $instrucciones !== '' ? $instrucciones : $tratamiento,
                        ]
                    );
                    $recetaId = $db->lastInsertId();

                    foreach ($itemsParaRecetar as $item) {
                        $presentacion = null;
                        if ($item['producto_id'] !== null) {
                            $prodInfo     = $db->fetchOne(
                                "SELECT presentacion FROM productos WHERE id = :id LIMIT 1",
                                [':id' => $item['producto_id']]
                            );
                            $presentacion = $prodInfo['presentacion'] ?? null;

                            // Descuento automático de stock al recetar
                            Inventario::descontarStock(
                                $item['producto_id'],
                                $item['cantidad'],
                                $veterinarioId,
                                'receta',
                                $recetaId,
                                "Receta #{$recetaId} (Consulta #{$historialId}, Paciente #{$pacienteId})"
                            );
                        }

                        $db->execute(
                            "INSERT INTO receta_items
                                (receta_id, producto_id, medicamento_nombre, presentacion, dosis, frecuencia, duracion, cantidad_dispensada)
                             VALUES
                                (:rid, :pid, :med, :pres, :dosis, :frec, :dur, :cant)",
                            [
                                ':rid'   => $recetaId,
                                ':pid'   => $item['producto_id'],
                                ':med'   => $item['nombre'],
                                ':pres'  => $presentacion,
                                ':dosis' => $item['dosis'] !== '' ? $item['dosis'] : null,
                                ':frec'  => $item['frecuencia'] !== '' ? $item['frecuencia'] : null,
                                ':dur'   => $item['duracion'] !== '' ? $item['duracion'] : null,
                                ':cant'  => $item['cantidad'],
                            ]
                        );
                    }
                }
            });

            $this->setFlash('success', 'Consulta médica registrada correctamente en el Kardex.');
        } catch (\Throwable $e) {
            $this->setFlash('error', 'No se pudo registrar la consulta: ' . $e->getMessage());
        }

        $this->redirect("/app/kardex/{$pacienteId}#tab-consultas");
    }

    /**
     * POST /app/kardex/consulta/{id}/editar
     * Permite editar una consulta ÚNICAMENTE dentro de las primeras 24 horas.
     */
    public function editarConsulta(string $id = ''): void
    {
        $this->requireAuth(self::WRITE_ROLES);
        $this->requireValidCsrf();

        $consultaId = (int) $id;
        $registro   = $this->db->fetchOne(
            "SELECT * FROM historial_clinico WHERE id = :id LIMIT 1",
            [':id' => $consultaId]
        );

        if (!$registro) {
            $this->setFlash('error', 'Registro clínico no encontrado.');
            $this->redirect('/app/dashboard');
        }

        $pacienteId = (int) $registro['paciente_id'];

        if (!Kardex::esEditable((string) $registro['created_at'])) {
            http_response_code(403);
            $this->setFlash(
                'error',
                'Registro inmutable: han transcurrido más de 24 horas desde su creación y no puede modificarse.'
            );
            $this->redirect("/app/kardex/{$pacienteId}#tab-consultas");
        }

        $diagnostico = trim((string) $this->input('diagnostico', ''));
        $tratamiento = trim((string) $this->input('tratamiento', ''));
        $observaciones = trim((string) $this->input('observaciones', ''));

        if ($diagnostico === '') {
            $this->setFlash('error', 'El diagnóstico no puede estar vacío.');
            $this->redirect("/app/kardex/{$pacienteId}#tab-consultas");
        }

        $this->db->execute(
            "UPDATE historial_clinico
             SET diagnostico = :diag, tratamiento = :trat, observaciones = :obs
             WHERE id = :id",
            [
                ':diag' => $diagnostico,
                ':trat' => $tratamiento !== '' ? $tratamiento : null,
                ':obs'  => $observaciones !== '' ? $observaciones : null,
                ':id'   => $consultaId,
            ]
        );

        $this->setFlash('success', 'Registro clínico actualizado dentro de la ventana legal de 24 horas.');
        $this->redirect("/app/kardex/{$pacienteId}#tab-consultas");
    }

    /**
     * POST /app/kardex/consulta/{id}/eliminar
     * Bloquea eliminación pasadas 24 horas desde la creación del registro.
     */
    public function eliminarConsulta(string $id = ''): void
    {
        $this->requireAuth(self::WRITE_ROLES);
        $this->requireValidCsrf();

        $consultaId = (int) $id;
        $registro   = $this->db->fetchOne(
            "SELECT id, paciente_id, created_at FROM historial_clinico WHERE id = :id LIMIT 1",
            [':id' => $consultaId]
        );

        if (!$registro) {
            $this->setFlash('error', 'Registro clínico no encontrado.');
            $this->redirect('/app/dashboard');
        }

        $pacienteId = (int) $registro['paciente_id'];

        if (!Kardex::esEditable((string) $registro['created_at'])) {
            http_response_code(403);
            $this->setFlash(
                'error',
                'Registro inmutable: han transcurrido más de 24 horas desde su creación y no puede eliminarse.'
            );
            $this->redirect("/app/kardex/{$pacienteId}#tab-consultas");
        }

        $this->db->execute("DELETE FROM historial_clinico WHERE id = :id", [':id' => $consultaId]);
        $this->setFlash('success', 'Registro clínico eliminado (dentro de las 24h permitidas).');
        $this->redirect("/app/kardex/{$pacienteId}#tab-consultas");
    }

    // ─── Vacunaciones ───────────────────────────────────────────────────────

    /**
     * GET /app/kardex/vacuna/nueva?paciente_id={id}
     * Vista independiente para aplicar una vacuna.
     */
    public function vacunaForm(): void
    {
        $this->requireAuth(self::WRITE_ROLES);

        $pacienteId = (int) $this->query('paciente_id', 0);
        if ($pacienteId <= 0) {
            $this->setFlash('error', 'Selecciona un paciente para aplicar la vacuna.');
            $this->redirect('/app/qr/scanner');
        }

        $paciente = $this->kardexModel->getPacienteCompleto($pacienteId);
        if (!$paciente) {
            $this->setFlash('error', 'Paciente no encontrado.');
            $this->redirect('/app/qr/scanner');
        }

        $this->render('clinica/vacuna_nueva', [
            'title'           => 'Aplicar Vacuna: ' . $paciente['nombre'] . ' — ' . Config::APP_NAME,
            'pageTitle'       => 'Registrar Vacunación',
            'activeMenu'      => 'pacientes',
            'auth'            => $this->auth,
            'user'            => $this->auth->getCurrentUser() ?? ($_SESSION['user'] ?? []),
            'paciente'        => $paciente,
            'vacunasCatalogo' => $this->kardexModel->getVacunasCatalogo(),
            'csrfToken'       => $this->generateToken(),
        ]);
    }

    /**
     * POST /app/kardex/vacuna/nueva
     * Registra una nueva vacunación con lote/marca y fecha obligatoria de próximo refuerzo.
     */
    public function guardarVacuna(): void
    {
        $this->requireAuth(self::WRITE_ROLES);
        $this->requireValidCsrf();

        $pacienteId   = (int) $this->input('paciente_id', 0);
        $vacunaId     = (int) $this->input('vacuna_id', 0);
        $lote         = trim((string) $this->input('lote', ''));
        $proximaDosis = trim((string) $this->input('proxima_dosis', ''));
        $fechaAplic   = trim((string) $this->input('fecha', date('Y-m-d')));
        $notas        = trim((string) $this->input('notas', ''));

        if ($pacienteId <= 0) {
            $this->setFlash('error', 'Paciente inválido.');
            $this->redirect('/app/dashboard');
        }

        if ($vacunaId <= 0 || $lote === '' || $proximaDosis === '') {
            $this->setFlash(
                'error',
                'El tipo de vacuna, lote/marca y la fecha del próximo refuerzo son obligatorios.'
            );
            $this->redirect("/app/kardex/{$pacienteId}#tab-vacunas");
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $proximaDosis)) {
            $this->setFlash('error', 'Formato inválido para la fecha del próximo refuerzo.');
            $this->redirect("/app/kardex/{$pacienteId}#tab-vacunas");
        }

        $paciente = $this->kardexModel->getPacienteCompleto($pacienteId);
        if (!$paciente) {
            $this->setFlash('error', 'Paciente no encontrado.');
            $this->redirect('/app/dashboard');
        }

        $veterinarioId = (int) ($_SESSION['user_id'] ?? 0);

        $this->db->execute(
            "INSERT INTO vacunaciones (paciente_id, vacuna_id, veterinario_id, fecha, lote, proxima_dosis, notas, created_at)
             VALUES (:pid, :vac, :vid, :fecha, :lote, :prox, :notas, NOW())",
            [
                ':pid'   => $pacienteId,
                ':vac'   => $vacunaId,
                ':vid'   => $veterinarioId,
                ':fecha' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaAplic) ? $fechaAplic : date('Y-m-d'),
                ':lote'  => $lote,
                ':prox'  => $proximaDosis,
                ':notas' => $notas !== '' ? $notas : null,
            ]
        );

        $this->setFlash('success', 'Vacunación registrada en el expediente y próximo refuerzo programado.');
        $this->redirect("/app/kardex/{$pacienteId}#tab-vacunas");
    }

    /**
     * POST /app/kardex/vacuna/{id}/eliminar
     * Impide eliminar registros de vacuna después de 24 horas.
     */
    public function eliminarVacuna(string $id = ''): void
    {
        $this->requireAuth(self::WRITE_ROLES);
        $this->requireValidCsrf();

        $vacunaRegId = (int) $id;
        $registro    = $this->db->fetchOne(
            "SELECT id, paciente_id, created_at FROM vacunaciones WHERE id = :id LIMIT 1",
            [':id' => $vacunaRegId]
        );

        if (!$registro) {
            $this->setFlash('error', 'Registro de vacunación no encontrado.');
            $this->redirect('/app/dashboard');
        }

        $pacienteId = (int) $registro['paciente_id'];

        if (!Kardex::esEditable((string) $registro['created_at'])) {
            http_response_code(403);
            $this->setFlash(
                'error',
                'Registro inmutable: han transcurrido más de 24 horas desde su creación y no puede eliminarse.'
            );
            $this->redirect("/app/kardex/{$pacienteId}#tab-vacunas");
        }

        $this->db->execute("DELETE FROM vacunaciones WHERE id = :id", [':id' => $vacunaRegId]);
        $this->setFlash('success', 'Registro de vacunación eliminado dentro del plazo de 24 horas.');
        $this->redirect("/app/kardex/{$pacienteId}#tab-vacunas");
    }

    // ─── Emisión de Certificados PDF ────────────────────────────────────────

    /**
     * GET /app/kardex/certificado/{id}
     * Genera y descarga el Certificado Médico/Vacunación en PDF usando Dompdf.
     */
    public function generarCertificado(string $mascota_id = ''): void
    {
        $this->requireAuth(['superadmin', 'veterinario', 'recepcionista', 'propietario']);

        $pacienteId = (int) $mascota_id;
        if ($pacienteId <= 0) {
            $this->setFlash('error', 'Paciente inválido para generar certificado.');
            $this->redirect('/app/dashboard');
        }

        $paciente = $this->kardexModel->getPacienteCompleto($pacienteId);
        if (!$paciente) {
            $this->setFlash('error', 'No se encontró el paciente solicitado.');
            $this->redirect('/app/dashboard');
        }

        $this->assertOwnershipIfPropietario($paciente);

        $vacunaciones = array_slice($this->kardexModel->getVacunaciones($pacienteId), 0, 10);
        $clinica      = $this->kardexModel->getClinicaInfo();
        $currentUser  = $this->auth->getCurrentUser() ?? ($_SESSION['user'] ?? []);

        // Veterinario firmante: último veterinario que atendió al paciente o usuario médico actual
        $vetFirmante = trim(($currentUser['nombre'] ?? '') . ' ' . ($currentUser['apellido'] ?? ''));
        if (!empty($vacunaciones[0]['vet_nombre'])) {
            $vetFirmante = trim($vacunaciones[0]['vet_nombre'] . ' ' . $vacunaciones[0]['vet_apellido']);
        }

        $html = $this->buildCertificateHtml($paciente, $vacunaciones, $clinica, $vetFirmante);

        // Registrar emisión en documentos_clinicos
        try {
            $this->db->execute(
                "INSERT INTO documentos_clinicos (paciente_id, usuario_id, tipo, titulo, contenido_html, created_at)
                 VALUES (:pid, :uid, 'certificado', :titulo, :html, NOW())",
                [
                    ':pid'    => $pacienteId,
                    ':uid'    => (int) ($currentUser['id'] ?? 1),
                    ':titulo' => 'Certificado de Salud y Vacunación — ' . $paciente['nombre'],
                    ':html'   => $html,
                ]
            );
        } catch (\Throwable) {
            // No bloquear la descarga del certificado si falla el log documental
        }

        $safeName = preg_replace('/[^a-zA-Z0-9_-]+/', '-', strtolower((string) $paciente['nombre'])) ?: 'paciente';
        $filename = "Certificado-PetKardex-{$safeName}.pdf";

        if (class_exists('\Dompdf\Dompdf')) {
            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', false);
            $options->set('defaultFont', 'DejaVu Sans');

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $dompdf->stream($filename, ['Attachment' => true]);
            exit;
        }

        if (class_exists('\TCPDF')) {
            $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
            $pdf->SetCreator(Config::APP_NAME);
            $pdf->SetTitle('Certificado Médico Veterinario — ' . $paciente['nombre']);
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
            $pdf->AddPage();
            $pdf->writeHTML($html, true, false, true, false, '');
            $pdf->Output($filename, 'D');
            exit;
        }

        // Fallback imprimible en navegador si aún no se ejecutó composer install
        header('Content-Type: text/html; charset=utf-8');
        echo $html . '<script>window.addEventListener("load", () => window.print());</script>';
        exit;
    }

    // ─── Helpers privados ───────────────────────────────────────────────────

    private function assertOwnershipIfPropietario(array $paciente): void
    {
        if ($this->auth->hasRole('propietario')) {
            $currentUserId = (int) ($_SESSION['user_id'] ?? 0);
            if ((int) ($paciente['propietario_usuario_id'] ?? 0) !== $currentUserId) {
                $this->redirect('/app/unauthorized');
            }
        }
    }

    private function buildCertificateHtml(
        array  $paciente,
        array  $vacunaciones,
        array  $clinica,
        string $vetFirmante
    ): string {
        $clinicName  = Security::e((string) ($clinica['nombre_clinica'] ?? 'PetKardex Clínica Veterinaria'));
        $clinicDir   = Security::e((string) ($clinica['direccion'] ?? ''));
        $clinicTel   = Security::e((string) ($clinica['telefono'] ?? ''));
        $clinicEmail = Security::e((string) ($clinica['email'] ?? ''));
        $ownerName   = Security::e(trim(($paciente['propietario_nombre'] ?? '') . ' ' . ($paciente['propietario_apellido'] ?? '')));
        $ownerDni    = Security::e((string) ($paciente['propietario_dni'] ?? 'N/D'));
        $petName     = Security::e((string) $paciente['nombre']);
        $species     = Security::e((string) ($paciente['especie_nombre'] ?? ''));
        $breed       = Security::e((string) ($paciente['raza_nombre'] ?? 'Mestizo / N/D'));
        $sex         = Security::e(ucfirst((string) ($paciente['sexo'] ?? 'desconocido')));
        $weight      = Security::e((string) ($paciente['peso_kg'] ?? 'N/D')) . ' kg';
        $microchip   = Security::e((string) ($paciente['microchip'] ?: 'Sin registrar'));
        $qrCode      = Security::e((string) ($paciente['qr_code'] ?: 'N/D'));
        $alertas     = Security::e((string) ($paciente['alertas_medicas'] ?: 'Ninguna registrada'));
        $vetDisplay  = Security::e($vetFirmante !== '' ? $vetFirmante : 'Médico Veterinario Tratante');
        $fechaHoy    = date('d/m/Y H:i');

        $rowsHtml = '';
        if (empty($vacunaciones)) {
            $rowsHtml = '<tr><td colspan="5" style="text-align:center;color:#6B7280;padding:10px;">Sin vacunaciones registradas a la fecha.</td></tr>';
        } else {
            foreach ($vacunaciones as $v) {
                $rowsHtml .= '<tr>'
                    . '<td>' . Security::e((string) $v['vacuna_nombre']) . '</td>'
                    . '<td>' . Security::e((string) ($v['vacuna_laboratorio'] ?? '-')) . '</td>'
                    . '<td>' . Security::e((string) ($v['lote'] ?? '-')) . '</td>'
                    . '<td>' . Security::e((string) $v['fecha']) . '</td>'
                    . '<td>' . Security::e((string) ($v['proxima_dosis'] ?? '-')) . '</td>'
                    . '</tr>';
            }
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
  <meta charset="UTF-8" />
  <style>
    body { font-family: DejaVu Sans, Arial, sans-serif; color: #111827; font-size: 12px; margin: 28px; }
    .letterhead {
      background: #0D0D0D; color: #FFFFFF; padding: 18px 22px; border-radius: 8px;
      border-bottom: 5px solid #C48CFF; margin-bottom: 18px;
    }
    .letterhead h1 { margin: 0 0 4px 0; font-size: 20px; color: #C48CFF; }
    .letterhead p { margin: 0; font-size: 10px; color: #D1D5DB; }
    .doc-title {
      text-align: center; font-size: 15px; font-weight: bold; color: #1A1A2E;
      text-transform: uppercase; letter-spacing: 0.5px; margin: 14px 0 18px;
      border-bottom: 2px solid #E5E7EB; padding-bottom: 8px;
    }
    .section-hdr {
      background: #F5F3FF; color: #5A3A8A; font-weight: bold; padding: 6px 10px;
      border-left: 4px solid #C48CFF; margin: 14px 0 8px; font-size: 12px;
    }
    table.info { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    table.info td { padding: 5px 8px; border: 1px solid #E5E7EB; }
    table.info td.lbl { width: 22%; background: #F9FAFB; font-weight: bold; color: #374151; }
    table.vac { width: 100%; border-collapse: collapse; margin-top: 6px; }
    table.vac th {
      background: #1A1A2E; color: #FFFFFF; text-align: left; padding: 7px 8px; font-size: 11px;
    }
    table.vac td { padding: 6px 8px; border: 1px solid #E5E7EB; font-size: 11px; }
    .signature-box {
      margin-top: 42px; text-align: center; width: 280px; margin-left: auto; margin-right: auto;
    }
    .signature-line { border-top: 1.5px solid #0D0D0D; padding-top: 6px; font-weight: bold; }
    .seal {
      display: inline-block; border: 2px dashed #7B52AB; color: #7B52AB;
      padding: 6px 14px; border-radius: 6px; font-size: 10px; margin-bottom: 10px;
    }
    .footer-note { margin-top: 28px; font-size: 9px; color: #6B7280; text-align: center; }
  </style>
</head>
<body>
  <div class="letterhead">
    <h1>{$clinicName}</h1>
    <p>{$clinicDir} · Tel: {$clinicTel} · {$clinicEmail}</p>
  </div>

  <div class="doc-title">Certificado Médico Veterinario y Cartilla de Vacunación</div>

  <div class="section-hdr">Datos del Paciente y Propietario</div>
  <table class="info">
    <tr>
      <td class="lbl">Paciente</td><td><strong>{$petName}</strong></td>
      <td class="lbl">Especie / Raza</td><td>{$species} · {$breed}</td>
    </tr>
    <tr>
      <td class="lbl">Sexo / Peso</td><td>{$sex} · {$weight}</td>
      <td class="lbl">Microchip</td><td>{$microchip}</td>
    </tr>
    <tr>
      <td class="lbl">Propietario</td><td>{$ownerName}</td>
      <td class="lbl">DNI / ID</td><td>{$ownerDni}</td>
    </tr>
    <tr>
      <td class="lbl">Alertas Médicas</td><td colspan="3">{$alertas}</td>
    </tr>
  </table>

  <div class="section-hdr">Historial de Vacunaciones Recientes</div>
  <table class="vac">
    <thead>
      <tr>
        <th>Vacuna</th>
        <th>Laboratorio</th>
        <th>Lote / Serie</th>
        <th>Fecha Aplicación</th>
        <th>Próximo Refuerzo</th>
      </tr>
    </thead>
    <tbody>
      {$rowsHtml}
    </tbody>
  </table>

  <div class="signature-box">
    <div class="seal">SELLO CLÍNICO VERIFICADO · PETKARDEX</div>
    <div class="signature-line">{$vetDisplay}</div>
    <div style="font-size:10px;color:#4B5563;">Médico Veterinario Tratante</div>
  </div>

  <div class="footer-note">
    Documento emitido el {$fechaHoy} · Código QR Paciente: {$qrCode}
  </div>
</body>
</html>
HTML;
    }
}


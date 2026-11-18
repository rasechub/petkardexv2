<?php
/**
 * PetKardex v2 - Vista Principal del Kardex Clínico
 *
 * Dividida en 3 Pestañas (Tabs de Bootstrap 5):
 * 1. Resumen General (Datos, alergias conocidas, peso actual, cirugías previas)
 * 2. Vacunas y Desparasitaciones (Tabla cronológica + botón Registrar Nueva Vacuna)
 * 3. Consultas y Recetas (Historial de visitas, diagnósticos, síntomas y recetas)
 *
 * Variables:
 * - array  $paciente
 * - array  $consultas
 * - array  $vacunaciones
 * - array  $cirugias
 * - array  $vacunasCatalogo
 * - array  $medicamentos
 * - bool   $canWrite
 * - string $csrfToken
 */

use PetKardex\Helpers\Security;

$pet         = $paciente ?? [];
$petId       = (int) ($pet['id'] ?? 0);
$ownerName   = trim(($pet['propietario_nombre'] ?? '') . ' ' . ($pet['propietario_apellido'] ?? ''));
$alertas     = trim((string) ($pet['alertas_medicas'] ?? ''));
$isEditable  = (bool) ($canWrite ?? false);

ob_start();
?>

<!-- Encabezado Clínico del Paciente -->
<div class="pk-card p-4 mb-4 text-white" style="background: linear-gradient(135deg, #0D0D0D 0%, #1A1A2E 60%, #371F66 100%);">
  <div class="row align-items-center g-3">
    <div class="col-lg-7 d-flex align-items-center gap-3">
      <div class="rounded-circle overflow-hidden d-flex align-items-center justify-content-center flex-shrink-0"
           style="width: 76px; height: 76px; border: 3px solid #C48CFF; background: #0D0D0D; color: #C48CFF;">
        <?php if (!empty($pet['foto'])): ?>
          <img src="/<?= Security::e(ltrim((string) $pet['foto'], '/')) ?>" alt="<?= Security::e((string) $pet['nombre']) ?>" class="w-100 h-100 object-fit-cover" />
        <?php else: ?>
          <i class="bi bi-heart-pulse-fill fs-2"></i>
        <?php endif; ?>
      </div>
      <div>
        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
          <h2 class="h4 fw-bold mb-0"><?= Security::e((string) ($pet['nombre'] ?? '')) ?></h2>
          <span class="badge rounded-pill" style="background: rgba(196, 140, 255, 0.22); color: #C48CFF;">
            <?= Security::e((string) ($pet['especie_nombre'] ?? '')) ?>
            <?php if (!empty($pet['raza_nombre'])): ?>
              · <?= Security::e((string) $pet['raza_nombre']) ?>
            <?php endif; ?>
          </span>
        </div>
        <div class="small text-white-50">
          Tutor: <strong class="text-white"><?= Security::e($ownerName) ?></strong> ·
          Peso actual: <strong style="color: #C48CFF;"><?= Security::e((string) ($pet['peso_kg'] ?? 'N/D')) ?> kg</strong>
        </div>
      </div>
    </div>

    <div class="col-lg-5 d-flex justify-content-lg-end flex-wrap gap-2">
      <a href="/app/kardex/certificado/<?= $petId ?>" class="btn btn-light rounded-3 fw-semibold px-3">
        <i class="bi bi-file-earmark-pdf-fill text-danger me-1"></i>Descargar Certificado (PDF)
      </a>
      <?php if ($isEditable): ?>
        <button type="button" class="btn btn-pk-primary px-3" data-bs-toggle="modal" data-bs-target="#modalNuevaConsulta">
          <i class="bi bi-plus-circle me-1"></i>Nueva Consulta
        </button>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php if (!$isEditable): ?>
  <div class="alert alert-light border d-flex align-items-center gap-2 rounded-3 mb-4 small" role="status">
    <i class="bi bi-lock-fill fs-5" style="color: #7B52AB;"></i>
    <span>
      <strong>Modo Solo Lectura:</strong> Los registros clínicos e inmunizaciones son documentos médicos inmutables; únicamente el Médico Veterinario o Administrador puede emitir nuevos registros.
    </span>
  </div>
<?php endif; ?>

<!-- Pestañas Bootstrap 5 del Kardex -->
<ul class="nav nav-pills gap-2 mb-4" id="kardexTabs" role="tablist">
  <li class="nav-item" role="presentation">
    <button class="nav-link active rounded-pill px-4 fw-semibold" id="tab-resumen-btn" data-bs-toggle="pill" data-bs-target="#tab-resumen" type="button" role="tab" aria-controls="tab-resumen" aria-selected="true">
      <i class="bi bi-card-checklist me-1"></i>Resumen General
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link rounded-pill px-4 fw-semibold" id="tab-vacunas-btn" data-bs-toggle="pill" data-bs-target="#tab-vacunas" type="button" role="tab" aria-controls="tab-vacunas" aria-selected="false">
      <i class="bi bi-shield-plus me-1"></i>Vacunas y Desparasitaciones
      <span class="badge rounded-pill bg-dark ms-1"><?= count($vacunaciones ?? []) ?></span>
    </button>
  </li>
  <li class="nav-item" role="presentation">
    <button class="nav-link rounded-pill px-4 fw-semibold" id="tab-consultas-btn" data-bs-toggle="pill" data-bs-target="#tab-consultas" type="button" role="tab" aria-controls="tab-consultas" aria-selected="false">
      <i class="bi bi-clipboard2-pulse me-1"></i>Consultas y Recetas
      <span class="badge rounded-pill bg-dark ms-1"><?= count($consultas ?? []) ?></span>
    </button>
  </li>
</ul>

<style>
  #kardexTabs .nav-link {
    background: #FFFFFF;
    color: #374151;
    border: 1px solid #E5E7EB;
  }
  #kardexTabs .nav-link.active {
    background: linear-gradient(135deg, #C48CFF 0%, #7B52AB 100%);
    color: #FFFFFF;
    border-color: transparent;
    box-shadow: 0 6px 18px rgba(123, 82, 171, 0.25);
  }
</style>

<div class="tab-content" id="kardexTabsContent">

  <!-- ═══════════════════════════════════════════════════════════════════
       TAB 1: RESUMEN GENERAL
       ═══════════════════════════════════════════════════════════════════ -->
  <div class="tab-pane fade show active" id="tab-resumen" role="tabpanel" aria-labelledby="tab-resumen-btn">
    <div class="row g-4">
      <div class="col-lg-6">
        <div class="pk-card p-4 h-100">
          <h3 class="h6 fw-bold mb-3"><i class="bi bi-info-circle-fill me-2" style="color: #7B52AB;"></i>Datos de la Mascota</h3>
          <dl class="row small mb-0">
            <dt class="col-5 text-muted mb-2">Nombre</dt>
            <dd class="col-7 fw-semibold mb-2"><?= Security::e((string) ($pet['nombre'] ?? '')) ?></dd>

            <dt class="col-5 text-muted mb-2">Especie / Raza</dt>
            <dd class="col-7 mb-2"><?= Security::e((string) ($pet['especie_nombre'] ?? '')) ?> · <?= Security::e((string) ($pet['raza_nombre'] ?: 'N/D')) ?></dd>

            <dt class="col-5 text-muted mb-2">Sexo</dt>
            <dd class="col-7 mb-2"><?= Security::e(ucfirst((string) ($pet['sexo'] ?? 'desconocido'))) ?></dd>

            <dt class="col-5 text-muted mb-2">Peso Actual</dt>
            <dd class="col-7 fw-bold mb-2" style="color: #7B52AB;"><?= Security::e((string) ($pet['peso_kg'] ?? 'N/D')) ?> kg</dd>

            <dt class="col-5 text-muted mb-2">Fecha de Nacimiento</dt>
            <dd class="col-7 mb-2"><?= Security::e((string) ($pet['fecha_nacimiento'] ?: 'No registrada')) ?></dd>

            <dt class="col-5 text-muted mb-2">Tipo de Sangre</dt>
            <dd class="col-7 mb-2"><?= Security::e((string) ($pet['sangre_tipo'] ?: 'N/D')) ?></dd>

            <dt class="col-5 text-muted mb-2">Esterilizado</dt>
            <dd class="col-7 mb-2"><?= ((int) ($pet['esterilizado'] ?? 0) === 1) ? 'Sí' : 'No' ?></dd>

            <dt class="col-5 text-muted mb-0">Microchip</dt>
            <dd class="col-7 mb-0"><?= Security::e((string) ($pet['microchip'] ?: 'Sin registrar')) ?></dd>
          </dl>
        </div>
      </div>

      <div class="col-lg-6">
        <!-- Alergias y Alertas Médicas -->
        <div class="pk-card p-4 mb-4 <?= $alertas !== '' ? 'border-danger border-2' : '' ?>">
          <h3 class="h6 fw-bold mb-2 <?= $alertas !== '' ? 'text-danger' : '' ?>">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>Alergias Conocidas y Alertas Médicas
          </h3>
          <?php if ($alertas !== ''): ?>
            <p class="mb-0 small fw-semibold text-dark"><?= nl2br(Security::e($alertas)) ?></p>
          <?php else: ?>
            <p class="mb-0 small text-muted">Sin alergias ni alertas médicas críticas registradas.</p>
          <?php endif; ?>

          <?php if (!empty($alertasInternas)): ?>
            <hr class="my-3" />
            <div class="small fw-bold text-danger mb-2">
              <i class="bi bi-bell-fill me-1"></i>Alertas Reportadas por Estética / Equipo Clínico
            </div>
            <div class="d-grid gap-2">
              <?php foreach ($alertasInternas as $alInt): ?>
                <div class="p-2 px-3 rounded-3 bg-danger-subtle border border-danger-subtle small">
                  <div class="d-flex justify-content-between">
                    <strong><?= Security::e((string) $alInt['titulo']) ?></strong>
                    <span class="text-muted"><?= Security::e(date('d/m/Y H:i', strtotime((string) $alInt['created_at']))) ?></span>
                  </div>
                  <div><?= Security::e((string) $alInt['mensaje']) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Cirugías Previas -->
        <div class="pk-card p-4">
          <h3 class="h6 fw-bold mb-3"><i class="bi bi-bandaid-fill me-2" style="color: #7B52AB;"></i>Cirugías Previas</h3>
          <?php if (empty($cirugias)): ?>
            <p class="small text-muted mb-0">No se registran intervenciones quirúrgicas previas en el expediente.</p>
          <?php else: ?>
            <div class="list-group list-group-flush">
              <?php foreach ($cirugias as $cir): ?>
                <div class="list-group-item px-0">
                  <div class="d-flex justify-content-between align-items-center">
                    <strong class="small"><?= Security::e((string) ($cir['motivo'] ?: 'Procedimiento quirúrgico')) ?></strong>
                    <span class="badge bg-light text-dark border"><?= Security::e(date('d/m/Y', strtotime((string) $cir['fecha_consulta']))) ?></span>
                  </div>
                  <div class="small text-muted mt-1"><?= Security::e((string) ($cir['diagnostico'] ?? '')) ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════════
       TAB 2: VACUNAS Y DESPARASITACIONES
       ═══════════════════════════════════════════════════════════════════ -->
  <div class="tab-pane fade" id="tab-vacunas" role="tabpanel" aria-labelledby="tab-vacunas-btn">
    <div class="pk-card p-4">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <div>
          <h3 class="h6 fw-bold mb-0">Historial Cronológico de Vacunación</h3>
          <small class="text-muted">Control de dosis aplicadas, lotes y próximos refuerzos.</small>
        </div>
        <?php if ($isEditable): ?>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-pk-primary px-3" data-bs-toggle="modal" data-bs-target="#modalAplicarVacuna">
              <i class="bi bi-plus-lg me-1"></i>Registrar Nueva Vacuna
            </button>
            <a href="/app/kardex/vacuna/nueva?paciente_id=<?= $petId ?>" class="btn btn-sm btn-outline-dark rounded-3">
              <i class="bi bi-box-arrow-up-right"></i>
            </a>
          </div>
        <?php endif; ?>
      </div>

      <?php if (empty($vacunaciones)): ?>
        <div class="text-center py-5 text-muted">
          <i class="bi bi-shield-exclamation fs-1 d-block mb-2" style="color: #C48CFF;"></i>
          <p class="small mb-0">Aún no hay vacunaciones registradas para este paciente.</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table table-hover align-middle small mb-0">
            <thead class="table-light">
              <tr>
                <th>Fecha</th>
                <th>Vacuna</th>
                <th>Laboratorio</th>
                <th>Lote</th>
                <th>Próximo Refuerzo</th>
                <th>Veterinario</th>
                <th>Estado</th>
                <?php if ($isEditable): ?>
                  <th class="text-end">Acción</th>
                <?php endif; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($vacunaciones as $vac): ?>
                <?php
                  $prox      = (string) ($vac['proxima_dosis'] ?? '');
                  $proxBadge = 'bg-light text-dark border';
                  if ($prox !== '') {
                      $dias = (int) floor((strtotime($prox) - strtotime(date('Y-m-d'))) / 86400);
                      if ($dias < 0) {
                          $proxBadge = 'bg-danger-subtle text-danger';
                      } elseif ($dias <= 15) {
                          $proxBadge = 'bg-warning-subtle text-warning-emphasis';
                      } else {
                          $proxBadge = 'bg-success-subtle text-success';
                      }
                  }
                ?>
                <tr>
                  <td><?= Security::e(date('d/m/Y', strtotime((string) $vac['fecha']))) ?></td>
                  <td>
                    <strong><?= Security::e((string) $vac['vacuna_nombre']) ?></strong>
                    <?php if (!empty($vac['enfermedad_prevenida'])): ?>
                      <div class="text-muted" style="font-size: 0.75rem;"><?= Security::e((string) $vac['enfermedad_prevenida']) ?></div>
                    <?php endif; ?>
                  </td>
                  <td><?= Security::e((string) ($vac['vacuna_laboratorio'] ?: '-')) ?></td>
                  <td><code><?= Security::e((string) ($vac['lote'] ?: '-')) ?></code></td>
                  <td>
                    <span class="badge rounded-pill <?= $proxBadge ?>">
                      <?= $prox !== '' ? Security::e(date('d/m/Y', strtotime($prox))) : 'No definido' ?>
                    </span>
                  </td>
                  <td><?= Security::e(trim(($vac['vet_nombre'] ?? '') . ' ' . ($vac['vet_apellido'] ?? ''))) ?></td>
                  <td>
                    <?php if (!empty($vac['es_editable'])): ?>
                      <span class="badge bg-info-subtle text-info-emphasis">Editable (&lt;24h)</span>
                    <?php else: ?>
                      <span class="badge bg-secondary-subtle text-secondary"><i class="bi bi-lock-fill me-1"></i>Inmutable</span>
                    <?php endif; ?>
                  </td>
                  <?php if ($isEditable): ?>
                    <td class="text-end">
                      <?php if (!empty($vac['es_editable'])): ?>
                        <form action="/app/kardex/vacuna/<?= (int) $vac['id'] ?>/eliminar" method="POST" class="d-inline js-confirm-delete">
                          <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />
                          <button type="submit" class="btn btn-sm btn-outline-danger rounded-2" title="Eliminar dentro de las 24h">
                            <i class="bi bi-trash"></i>
                          </button>
                        </form>
                      <?php else: ?>
                        <span class="text-muted" title="Bloqueado por regla de 24h"><i class="bi bi-shield-lock"></i></span>
                      <?php endif; ?>
                    </td>
                  <?php endif; ?>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ═══════════════════════════════════════════════════════════════════
       TAB 3: CONSULTAS Y RECETAS
       ═══════════════════════════════════════════════════════════════════ -->
  <div class="tab-pane fade" id="tab-consultas" role="tabpanel" aria-labelledby="tab-consultas-btn">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
      <div>
        <h3 class="h6 fw-bold mb-0">Historial Clínico de Consultas y Recetas</h3>
        <small class="text-muted">Los registros médicos se bloquean automáticamente después de 24 horas.</small>
      </div>
      <?php if ($isEditable): ?>
        <div class="d-flex gap-2">
          <button type="button" class="btn btn-sm btn-pk-primary px-3" data-bs-toggle="modal" data-bs-target="#modalNuevaConsulta">
            <i class="bi bi-plus-lg me-1"></i>Nueva Consulta
          </button>
          <a href="/app/kardex/consulta/nueva?paciente_id=<?= $petId ?>" class="btn btn-sm btn-outline-dark rounded-3">
            <i class="bi bi-box-arrow-up-right"></i>
          </a>
        </div>
      <?php endif; ?>
    </div>

    <?php if (empty($consultas)): ?>
      <div class="pk-card p-5 text-center text-muted">
        <i class="bi bi-clipboard2-x fs-1 d-block mb-2" style="color: #C48CFF;"></i>
        <p class="small mb-0">No hay consultas médicas registradas todavía.</p>
      </div>
    <?php else: ?>
      <div class="d-grid gap-3">
        <?php foreach ($consultas as $cons): ?>
          <?php $ex = $cons['examen'] ?? []; ?>
          <div class="pk-card p-4">
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
              <div>
                <span class="badge rounded-pill me-2" style="background: rgba(196, 140, 255, 0.22); color: #5A3A8A;">
                  <?= Security::e(ucfirst((string) $cons['tipo_consulta'])) ?>
                </span>
                <strong class="text-dark"><?= Security::e(date('d/m/Y H:i', strtotime((string) $cons['fecha_consulta']))) ?></strong>
                <div class="small text-muted mt-1">
                  Atendido por: <?= Security::e(trim(($cons['vet_nombre'] ?? '') . ' ' . ($cons['vet_apellido'] ?? ''))) ?>
                </div>
              </div>

              <div class="d-flex align-items-center gap-2">
                <?php if (!empty($cons['es_editable'])): ?>
                  <span class="badge bg-info-subtle text-info-emphasis">Editable (&lt;24h)</span>
                  <?php if ($isEditable): ?>
                    <button
                      type="button"
                      class="btn btn-sm btn-outline-dark rounded-2 js-edit-consulta"
                      data-id="<?= (int) $cons['id'] ?>"
                      data-diag="<?= Security::e((string) ($cons['diagnostico'] ?? '')) ?>"
                      data-trat="<?= Security::e((string) ($cons['tratamiento'] ?? '')) ?>"
                      data-obs="<?= Security::e((string) ($cons['observaciones'] ?? '')) ?>"
                      data-bs-toggle="modal"
                      data-bs-target="#modalEditarConsulta"
                    >
                      <i class="bi bi-pencil"></i>
                    </button>
                    <form action="/app/kardex/consulta/<?= (int) $cons['id'] ?>/eliminar" method="POST" class="d-inline js-confirm-delete">
                      <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />
                      <button type="submit" class="btn btn-sm btn-outline-danger rounded-2">
                        <i class="bi bi-trash"></i>
                      </button>
                    </form>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="badge bg-secondary-subtle text-secondary">
                    <i class="bi bi-lock-fill me-1"></i>Registro Inmutable (&gt;24h)
                  </span>
                <?php endif; ?>
              </div>
            </div>

            <!-- Signos vitales de la consulta -->
            <div class="d-flex flex-wrap gap-2 mb-3 small">
              <?php if (!empty($ex['peso_kg'])): ?>
                <span class="badge bg-light text-dark border">Peso: <?= Security::e((string) $ex['peso_kg']) ?> kg</span>
              <?php endif; ?>
              <?php if (!empty($ex['temperatura'])): ?>
                <span class="badge bg-light text-dark border">Temp: <?= Security::e((string) $ex['temperatura']) ?> °C</span>
              <?php endif; ?>
              <?php if (!empty($ex['frecuencia_cardiaca'])): ?>
                <span class="badge bg-light text-dark border">FC: <?= Security::e((string) $ex['frecuencia_cardiaca']) ?> lpm</span>
              <?php endif; ?>
              <?php if (!empty($ex['frecuencia_respiratoria'])): ?>
                <span class="badge bg-light text-dark border">FR: <?= Security::e((string) $ex['frecuencia_respiratoria']) ?> rpm</span>
              <?php endif; ?>
            </div>

            <div class="row g-3 small">
              <div class="col-md-6">
                <div class="text-muted fw-semibold">Motivo / Síntomas</div>
                <div><?= nl2br(Security::e((string) ($cons['motivo'] ?? ''))) ?></div>
              </div>
              <div class="col-md-6">
                <div class="text-muted fw-semibold">Diagnóstico</div>
                <div class="fw-semibold text-dark"><?= nl2br(Security::e((string) ($cons['diagnostico'] ?? ''))) ?></div>
              </div>
              <?php if (!empty($cons['tratamiento'])): ?>
                <div class="col-12">
                  <div class="text-muted fw-semibold">Tratamiento e Indicaciones</div>
                  <div><?= nl2br(Security::e((string) $cons['tratamiento'])) ?></div>
                </div>
              <?php endif; ?>
            </div>

            <!-- Receta médica asociada -->
            <?php if (!empty($cons['receta']) && !empty($cons['receta']['items'])): ?>
              <div class="mt-3 p-3 rounded-3" style="background: #F8F5FF; border: 1px solid rgba(196, 140, 255, 0.35);">
                <div class="fw-bold small mb-2" style="color: #5A3A8A;">
                  <i class="bi bi-capsule me-1"></i>Medicamentos Recetados
                </div>
                <ul class="list-unstyled mb-0 small d-grid gap-1">
                  <?php foreach ($cons['receta']['items'] as $item): ?>
                    <li>
                      <strong><?= Security::e((string) $item['medicamento_nombre']) ?></strong>
                      <?php if (!empty($item['dosis'])): ?> · Dosis: <?= Security::e((string) $item['dosis']) ?><?php endif; ?>
                      <?php if (!empty($item['frecuencia'])): ?> · <?= Security::e((string) $item['frecuencia']) ?><?php endif; ?>
                      <?php if (!empty($item['duracion'])): ?> · <?= Security::e((string) $item['duracion']) ?><?php endif; ?>
                      <?php if (!empty($item['producto_id'])): ?>
                        <span class="badge bg-success-subtle text-success ms-1">Stock descontado (<?= Security::e((string) $item['cantidad_dispensada']) ?>)</span>
                      <?php endif; ?>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

</div>

<?php if ($isEditable): ?>
<!-- ═══════════════════════════════════════════════════════════════════
     MODAL: NUEVA CONSULTA + RECETA CON DESCUENTO DE STOCK
     ═══════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalNuevaConsulta" tabindex="-1" aria-labelledby="modalNuevaConsultaLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content rounded-4 border-0">
      <div class="modal-header text-white" style="background: linear-gradient(135deg, #0D0D0D 0%, #2D1B69 100%);">
        <h5 class="modal-title" id="modalNuevaConsultaLabel">
          <i class="bi bi-file-earmark-medical me-2" style="color: #C48CFF;"></i>Nueva Consulta Médica
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form action="/app/kardex/consulta/nueva" method="POST">
        <div class="modal-body p-4">
          <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />
          <input type="hidden" name="paciente_id" value="<?= $petId ?>" />

          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Tipo de Consulta</label>
              <select name="tipo_consulta" class="form-select">
                <option value="consulta">Consulta General</option>
                <option value="control">Control</option>
                <option value="emergencia">Emergencia</option>
                <option value="cirugia">Cirugía</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Peso actual (kg)</label>
              <input type="number" step="0.01" min="0" name="peso_kg" class="form-control" placeholder="Ej: 12.50" />
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Temperatura (°C)</label>
              <input type="number" step="0.1" min="30" max="45" name="temperatura" class="form-control" placeholder="Ej: 38.5" />
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Motivo de consulta y síntomas *</label>
            <textarea name="motivo" class="form-control" rows="2" required placeholder="Describe el motivo de visita y signos observados..."></textarea>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-8">
              <label class="form-label small fw-semibold">Diagnóstico *</label>
              <textarea name="diagnostico" class="form-control" rows="2" required placeholder="Diagnóstico clínico presuntivo o definitivo..."></textarea>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Código CIE (opcional)</label>
              <input type="text" name="diagnostico_cie" class="form-control" placeholder="Ej: K59.1" />
            </div>
          </div>

          <div class="mb-4">
            <label class="form-label small fw-semibold">Tratamiento e indicaciones</label>
            <textarea name="tratamiento" class="form-control" rows="2" placeholder="Indicaciones generales y cuidados en casa..."></textarea>
          </div>

          <!-- Sección dinámica de receta -->
          <div class="p-3 rounded-3 border" style="background: #F8F5FF;">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <span class="fw-bold small" style="color: #5A3A8A;"><i class="bi bi-capsule me-1"></i>Recetar Medicamentos (descuenta stock)</span>
              <button type="button" id="btnAddMedRow" class="btn btn-sm btn-outline-dark rounded-pill">
                <i class="bi bi-plus-lg me-1"></i>Agregar Medicamento
              </button>
            </div>
            <div id="medRowsContainer" class="d-grid gap-2"></div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary rounded-3" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-pk-primary px-4">Guardar Consulta</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════
     MODAL: APLICAR VACUNA
     ═══════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalAplicarVacuna" tabindex="-1" aria-labelledby="modalAplicarVacunaLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content rounded-4 border-0">
      <div class="modal-header text-white" style="background: linear-gradient(135deg, #0D0D0D 0%, #2D1B69 100%);">
        <h5 class="modal-title" id="modalAplicarVacunaLabel">
          <i class="bi bi-shield-plus me-2" style="color: #C48CFF;"></i>Registrar Nueva Vacuna
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form action="/app/kardex/vacuna/nueva" method="POST">
        <div class="modal-body p-4">
          <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />
          <input type="hidden" name="paciente_id" value="<?= $petId ?>" />

          <div class="mb-3">
            <label class="form-label small fw-semibold">Tipo de Vacuna *</label>
            <select name="vacuna_id" class="form-select" required>
              <option value="">Selecciona una vacuna del catálogo...</option>
              <?php foreach ($vacunasCatalogo ?? [] as $vc): ?>
                <option value="<?= (int) $vc['id'] ?>">
                  <?= Security::e((string) $vc['nombre']) ?> (<?= Security::e((string) ($vc['laboratorio'] ?? '')) ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label small fw-semibold">Lote / Marca *</label>
            <input type="text" name="lote" class="form-control" required placeholder="Ej: LOT-2026-AB91 / Nobivac" />
          </div>

          <div class="row g-3 mb-3">
            <div class="col-6">
              <label class="form-label small fw-semibold">Fecha de Aplicación</label>
              <input type="date" name="fecha" class="form-control" value="<?= date('Y-m-d') ?>" required />
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Próximo Refuerzo *</label>
              <input type="date" name="proxima_dosis" class="form-control" min="<?= date('Y-m-d') ?>" required />
            </div>
          </div>

          <div class="mb-0">
            <label class="form-label small fw-semibold">Observaciones (opcional)</label>
            <textarea name="notas" class="form-control" rows="2" placeholder="Reacción post-vacunal o notas adicionales..."></textarea>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary rounded-3" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-pk-primary px-4">Guardar Vacuna</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════════
     MODAL: EDITAR CONSULTA (< 24 HORAS)
     ═══════════════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalEditarConsulta" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content rounded-4 border-0">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title h6 fw-bold">Editar Consulta (Plazo legal &lt; 24h)</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <form id="formEditarConsulta" action="" method="POST">
        <div class="modal-body p-4">
          <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />
          <div class="mb-3">
            <label class="form-label small fw-semibold">Diagnóstico *</label>
            <textarea name="diagnostico" id="editDiag" class="form-control" rows="2" required></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold">Tratamiento</label>
            <textarea name="tratamiento" id="editTrat" class="form-control" rows="2"></textarea>
          </div>
          <div class="mb-0">
            <label class="form-label small fw-semibold">Observaciones</label>
            <textarea name="observaciones" id="editObs" class="form-control" rows="2"></textarea>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button type="button" class="btn btn-outline-secondary rounded-3" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-pk-primary">Actualizar Registro</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    // Activar tab desde hash de URL (#tab-vacunas / #tab-consultas)
    const hash = window.location.hash;
    if (hash) {
      const targetBtn = document.querySelector(`button[data-bs-target="${hash}"]`);
      if (targetBtn && typeof bootstrap !== 'undefined') {
        new bootstrap.Tab(targetBtn).show();
      }
    }

    // Confirmar eliminaciones (<24h)
    document.querySelectorAll('.js-confirm-delete').forEach((form) => {
      form.addEventListener('submit', (e) => {
        if (!window.confirm('¿Confirmas eliminar este registro clínico? Esta acción quedará auditada.')) {
          e.preventDefault();
        }
      });
    });

    // Poblar modal de edición (<24h)
    const editForm = document.getElementById('formEditarConsulta');
    const editDiag = document.getElementById('editDiag');
    const editTrat = document.getElementById('editTrat');
    const editObs  = document.getElementById('editObs');

    document.querySelectorAll('.js-edit-consulta').forEach((btn) => {
      btn.addEventListener('click', () => {
        const id = btn.getAttribute('data-id');
        if (editForm) editForm.action = `/app/kardex/consulta/${id}/editar`;
        if (editDiag) editDiag.value = btn.getAttribute('data-diag') || '';
        if (editTrat) editTrat.value = btn.getAttribute('data-trat') || '';
        if (editObs)  editObs.value  = btn.getAttribute('data-obs') || '';
      });
    });

    // Filas dinámicas de medicamentos en Nueva Consulta
    const medContainer = document.getElementById('medRowsContainer');
    const btnAddMed = document.getElementById('btnAddMedRow');
    const inventario = <?= json_encode($medicamentos ?? [], JSON_HEX_TAG | JSON_HEX_AMP) ?>;

    function buildMedRow() {
      const row = document.createElement('div');
      row.className = 'row g-2 align-items-end bg-white p-2 rounded-3 border';

      let optionsHtml = '<option value="">Medicamento externo / texto libre</option>';
      inventario.forEach((p) => {
        optionsHtml += `<option value="${p.id}" data-nombre="${String(p.nombre).replace(/"/g, '&quot;')}">${p.nombre} (Stock: ${p.stock_actual})</option>`;
      });

      row.innerHTML = `
        <div class="col-md-4">
          <label class="form-label mb-1" style="font-size:0.72rem;">Producto Farmacia</label>
          <select name="med_producto_id[]" class="form-select form-select-sm js-med-select">${optionsHtml}</select>
        </div>
        <div class="col-md-3">
          <label class="form-label mb-1" style="font-size:0.72rem;">Nombre / Dosis</label>
          <input type="text" name="med_nombre[]" class="form-control form-control-sm js-med-name" placeholder="Nombre medicamento" />
        </div>
        <div class="col-md-2">
          <label class="form-label mb-1" style="font-size:0.72rem;">Dosis</label>
          <input type="text" name="med_dosis[]" class="form-control form-control-sm" placeholder="1 tab c/12h" />
        </div>
        <div class="col-md-2">
          <label class="form-label mb-1" style="font-size:0.72rem;">Cant.</label>
          <input type="number" step="0.5" min="0.5" value="1" name="med_cantidad[]" class="form-control form-control-sm" />
        </div>
        <div class="col-md-1 text-end">
          <button type="button" class="btn btn-sm btn-outline-danger js-remove-med" title="Quitar"><i class="bi bi-x"></i></button>
        </div>
      `;

      const sel = row.querySelector('.js-med-select');
      const nameInp = row.querySelector('.js-med-name');
      sel.addEventListener('change', () => {
        const opt = sel.options[sel.selectedIndex];
        const nombre = opt ? opt.getAttribute('data-nombre') : '';
        if (nombre) nameInp.value = nombre;
      });
      row.querySelector('.js-remove-med').addEventListener('click', () => row.remove());

      return row;
    }

    if (btnAddMed && medContainer) {
      btnAddMed.addEventListener('click', () => {
        medContainer.appendChild(buildMedRow());
      });
    }
  });
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/app.php';


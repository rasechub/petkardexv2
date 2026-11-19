<?php
/**
 * PetKardex v2 - Perfil Público de Emergencia (Vista QR sin inicio de sesión)
 *
 * Diseñado Mobile-First para personas que encuentran una mascota extraviada
 * o atienden una urgencia en la vía pública.
 *
 * Variables:
 * - array|null $paciente
 * - array      $clinic
 * - string     $telHref
 * - string     $waHref
 * - string     $rawPhone
 */

use PetKardex\Helpers\Security;

$clinicName  = (string) ($clinic['nombre_clinica'] ?? 'PetKardex Clínica Veterinaria');
$clinicPhone = (string) ($clinic['telefono_emergencias'] ?? ($clinic['telefono'] ?? ''));
$clinicTel   = $clinicPhone !== '' ? 'tel:' . preg_replace('/[^0-9+]/', '', $clinicPhone) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="author" content="CESAR AQUINO ANGLAS - VI B NOCHE" />
  <meta name="copyright" content="© 2026 PetKardex | CESAR AQUINO ANGLAS - VI B NOCHE" />
  <title><?= Security::e((string) ($title ?? 'Perfil de Emergencia QR — PetKardex')) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <style>
    :root {
      --pk-purple: #C48CFF;
      --pk-purple-dark: #7B52AB;
      --pk-black: #0D0D0D;
      --pk-navy: #1A1A2E;
    }

    body {
      min-height: 100vh;
      font-family: 'Inter', sans-serif;
      background: radial-gradient(circle at top center, rgba(196, 140, 255, 0.22), transparent 50%),
                  linear-gradient(180deg, #0D0D0D 0%, #161626 100%);
      color: #F9FAFB;
      padding: 1rem 0.75rem 2.5rem;
    }

    h1, h2, h3, .brand-font { font-family: 'Poppins', sans-serif; }

    .emergency-shell {
      max-width: 500px;
      margin: 0 auto;
    }

    .emergency-topbar {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0.5rem 0.25rem 1rem;
    }

    .emergency-card {
      background: #FFFFFF;
      color: #111827;
      border-radius: 1.5rem;
      overflow: hidden;
      box-shadow: 0 24px 60px rgba(0, 0, 0, 0.55), 0 0 0 1px rgba(196, 140, 255, 0.25);
    }

    .pet-hero-banner {
      background: linear-gradient(135deg, #1A1A2E 0%, #2D1B69 100%);
      padding: 2rem 1.5rem 1.5rem;
      text-align: center;
      color: #FFFFFF;
      position: relative;
    }

    .pet-avatar-wrap {
      width: 128px;
      height: 128px;
      border-radius: 50%;
      margin: 0 auto 1rem;
      border: 4px solid var(--pk-purple);
      box-shadow: 0 10px 28px rgba(196, 140, 255, 0.38);
      overflow: hidden;
      background: #0D0D0D;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .pet-avatar-wrap img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .pet-avatar-fallback {
      font-size: 3rem;
      color: var(--pk-purple);
    }

    .critical-alert-box {
      background: linear-gradient(135deg, #FEF2F2 0%, #FFF1F2 100%);
      border: 2px solid #EF4444;
      border-radius: 1.1rem;
      padding: 1.1rem 1.15rem;
    }

    .critical-alert-safe {
      background: #F5F3FF;
      border: 1.5px solid rgba(123, 82, 171, 0.35);
      border-radius: 1.1rem;
      padding: 1rem 1.15rem;
    }

    .btn-call-owner {
      background: linear-gradient(135deg, #0D0D0D 0%, #1F2937 100%);
      color: #FFFFFF;
      border: 2px solid #0D0D0D;
      border-radius: 1rem;
      padding: 1rem 1.25rem;
      font-family: 'Poppins', sans-serif;
      font-weight: 600;
      font-size: 1.05rem;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.65rem;
      box-shadow: 0 10px 24px rgba(13, 13, 13, 0.22);
      transition: transform 0.15s ease;
    }

    .btn-call-owner:hover {
      color: #C48CFF;
      transform: translateY(-2px);
    }

    .btn-whatsapp-owner {
      background: linear-gradient(135deg, #16A34A 0%, #15803D 100%);
      color: #FFFFFF;
      border: none;
      border-radius: 1rem;
      padding: 1rem 1.25rem;
      font-family: 'Poppins', sans-serif;
      font-weight: 600;
      font-size: 1.05rem;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0.65rem;
      box-shadow: 0 10px 24px rgba(22, 163, 74, 0.28);
      transition: transform 0.15s ease;
    }

    .btn-whatsapp-owner:hover {
      color: #FFFFFF;
      transform: translateY(-2px);
    }

    .spec-pill {
      background: #F3F4F6;
      border-radius: 0.85rem;
      padding: 0.65rem 0.8rem;
      text-align: center;
    }
  </style>
</head>
<body>

<div class="emergency-shell">
  <div class="emergency-topbar">
    <a href="/" class="d-inline-flex align-items-center gap-2 text-decoration-none text-white">
      <svg width="28" height="28" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <ellipse cx="50" cy="62" rx="28" ry="24" fill="#C48CFF"/>
        <ellipse cx="22" cy="45" rx="10" ry="14" fill="#C48CFF" transform="rotate(-20 22 45)"/>
        <ellipse cx="78" cy="45" rx="10" ry="14" fill="#C48CFF" transform="rotate(20 78 45)"/>
        <ellipse cx="34" cy="28" rx="8" ry="11" fill="#C48CFF" transform="rotate(-10 34 28)"/>
        <ellipse cx="66" cy="28" rx="8" ry="11" fill="#C48CFF" transform="rotate(10 66 28)"/>
      </svg>
      <span class="brand-font fw-bold" style="color:#C48CFF;">PetKardex</span>
    </a>
    <span class="badge rounded-pill px-3 py-2" style="background: rgba(196, 140, 255, 0.18); color: #C48CFF;">
      <i class="bi bi-qr-code me-1"></i>Ficha de Emergencia
    </span>
  </div>

  <?php if (!$paciente): ?>
    <div class="emergency-card p-4 p-md-5 text-center">
      <div class="mb-3" style="font-size: 3rem; color: #7B52AB;">
        <i class="bi bi-qr-code-scan"></i>
      </div>
      <h1 class="h4 fw-bold mb-2">Código QR no encontrado</h1>
      <p class="text-muted small mb-4">
        Este código QR no corresponde a ninguna mascota activa registrada en el sistema.
      </p>
      <a href="/" class="btn btn-dark rounded-pill px-4">Ir a PetKardex</a>
    </div>
  <?php else: ?>
    <?php
      $fotoUrl   = !empty($paciente['foto']) ? '/' . ltrim((string) $paciente['foto'], '/') : '';
      $alertas   = trim((string) ($paciente['alertas_medicas'] ?? ''));
      $sexoLabel = match ($paciente['sexo'] ?? 'desconocido') {
          'macho'  => 'Macho',
          'hembra' => 'Hembra',
          default  => 'N/D',
      };
    ?>
    <main class="emergency-card">
      <!-- Encabezado con Foto y Nombre -->
      <div class="pet-hero-banner">
        <span class="badge rounded-pill mb-3 px-3 py-2" style="background: rgba(196, 140, 255, 0.2); color: #C48CFF;">
          <i class="bi bi-shield-fill-exclamation me-1"></i>Mascota Identificada
        </span>

        <div class="pet-avatar-wrap">
          <?php if ($fotoUrl !== ''): ?>
            <img src="<?= Security::e($fotoUrl) ?>" alt="Fotografía de <?= Security::e((string) $paciente['nombre']) ?>" />
          <?php else: ?>
            <i class="bi bi-heart-pulse-fill pet-avatar-fallback" aria-hidden="true"></i>
          <?php endif; ?>
        </div>

        <h1 class="h2 fw-bold mb-1"><?= Security::e((string) $paciente['nombre']) ?></h1>
        <p class="mb-0 small" style="color: rgba(255,255,255,0.78);">
          <?= Security::e((string) $paciente['especie_nombre']) ?>
          <?php if (!empty($paciente['raza_nombre'])): ?>
            · <?= Security::e((string) $paciente['raza_nombre']) ?>
          <?php endif; ?>
        </p>
      </div>

      <div class="p-4">
        <!-- Bloque Destacado de Alertas Médicas Críticas -->
        <?php if ($alertas !== ''): ?>
          <section class="critical-alert-box mb-4" aria-label="Alertas médicas críticas">
            <div class="d-flex align-items-center gap-2 text-danger fw-bold mb-1">
              <i class="bi bi-exclamation-octagon-fill fs-5"></i>
              <span>ALERTAS MÉDICAS CRÍTICAS</span>
            </div>
            <p class="mb-0 fw-semibold text-dark" style="font-size: 0.96rem; line-height: 1.5;">
              <?= nl2br(Security::e($alertas)) ?>
            </p>
          </section>
        <?php else: ?>
          <section class="critical-alert-safe mb-4" aria-label="Estado de alertas médicas">
            <div class="d-flex align-items-center gap-2 fw-bold mb-1" style="color: #5A3A8A;">
              <i class="bi bi-check-circle-fill"></i>
              <span>Alertas Médicas</span>
            </div>
            <p class="mb-0 small text-muted">
              No se han registrado alergias o alertas médicas críticas para este paciente.
            </p>
          </section>
        <?php endif; ?>

        <!-- Datos rápidos de identificación -->
        <div class="row g-2 mb-4">
          <div class="col-4">
            <div class="spec-pill">
              <small class="text-muted d-block" style="font-size: 0.7rem;">Sexo</small>
              <strong class="small"><?= Security::e($sexoLabel) ?></strong>
            </div>
          </div>
          <div class="col-4">
            <div class="spec-pill">
              <small class="text-muted d-block" style="font-size: 0.7rem;">Tipo Sangre</small>
              <strong class="small"><?= Security::e((string) ($paciente['sangre_tipo'] ?: 'N/D')) ?></strong>
            </div>
          </div>
          <div class="col-4">
            <div class="spec-pill">
              <small class="text-muted d-block" style="font-size: 0.7rem;">Color</small>
              <strong class="small"><?= Security::e((string) ($paciente['color'] ?: 'N/D')) ?></strong>
            </div>
          </div>
        </div>

        <!-- Botones grandes de acción para contactar al dueño -->
        <div class="mb-3">
          <div class="small text-muted mb-2 text-center">
            Tutor registrado: <strong class="text-dark"><?= Security::e((string) $paciente['propietario_nombre']) ?></strong>
          </div>

          <div class="d-grid gap-3">
            <?php if ($telHref !== ''): ?>
              <a href="<?= Security::e($telHref) ?>" class="btn btn-call-owner text-decoration-none">
                <i class="bi bi-telephone-fill fs-4"></i>
                <span>Llamar al Dueño</span>
              </a>
            <?php endif; ?>

            <?php if ($waHref !== ''): ?>
              <a href="<?= Security::e($waHref) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-whatsapp-owner text-decoration-none">
                <i class="bi bi-whatsapp fs-4"></i>
                <span>Enviar WhatsApp al Dueño</span>
              </a>
            <?php endif; ?>

            <?php if ($telHref === '' && $waHref === ''): ?>
              <div class="alert alert-warning small text-center mb-0">
                El propietario no tiene teléfono directo registrado. Contacta a la clínica veterinaria.
              </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Contacto de la clínica -->
        <div class="mt-4 pt-3 border-top text-center">
          <div class="small fw-semibold text-dark"><?= Security::e($clinicName) ?></div>
          <?php if ($clinicTel !== ''): ?>
            <a href="<?= Security::e($clinicTel) ?>" class="small fw-semibold text-decoration-none" style="color: #7B52AB;">
              <i class="bi bi-hospital me-1"></i>Urgencias Clínica: <?= Security::e($clinicPhone) ?>
            </a>
          <?php endif; ?>
          <div class="mt-2">
            <a href="/app/login" class="small text-muted text-decoration-none">
              <i class="bi bi-lock me-1"></i>¿Eres médico veterinario? Inicia sesión para ver el Kardex completo
            </a>
          </div>
        </div>
      </div>
    </main>
  <?php endif; ?>

  <footer class="text-center mt-4 text-white-50" style="font-size: 0.74rem;">
    <div class="fw-semibold text-white mb-1"><?= \PetKardex\Config\Config::APP_COPYRIGHT ?></div>
    <div><?= \PetKardex\Config\Config::APP_INSTITUTION ?> · Todos los derechos reservados.</div>
  </footer>
</div>

</body>
</html>


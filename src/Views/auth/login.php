<?php
/**
 * PetKardex v2 - Vista de Inicio de Sesión
 *
 * Variables inyectadas por Controller::render():
 * - string $title
 * - string $csrfToken
 * - array  $flash
 */

use PetKardex\Helpers\Security;

$pageTitle = $title ?? 'Iniciar Sesión — PetKardex';
$flashList = $flash ?? [];
$loggedOut = isset($_GET['logout']) && $_GET['logout'] === '1';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= Security::e($pageTitle) ?></title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />

  <!-- Bootstrap 5.3 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" />

  <style>
    :root {
      --pk-purple: #C48CFF;
      --pk-purple-dark: #7B52AB;
      --pk-black: #0D0D0D;
      --pk-navy: #1A1A2E;
      --pk-surface: #FFFFFF;
      --pk-muted: #6B7280;
      --pk-border: #E5E7EB;
    }

    * { box-sizing: border-box; }

    body {
      min-height: 100vh;
      margin: 0;
      font-family: 'Inter', sans-serif;
      background: radial-gradient(circle at 15% 20%, rgba(196, 140, 255, 0.18) 0%, transparent 45%),
                  radial-gradient(circle at 85% 80%, rgba(123, 82, 171, 0.25) 0%, transparent 45%),
                  linear-gradient(135deg, #0D0D0D 0%, #1A1A2E 55%, #2D1B69 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
      color: #111827;
    }

    h1, h2, h3, .brand-font {
      font-family: 'Poppins', sans-serif;
    }

    .auth-shell {
      width: 100%;
      max-width: 980px;
      border-radius: 1.5rem;
      overflow: hidden;
      background: rgba(255, 255, 255, 0.98);
      box-shadow: 0 25px 70px rgba(0, 0, 0, 0.55), 0 0 0 1px rgba(196, 140, 255, 0.25);
    }

    .auth-brand-panel {
      background: linear-gradient(160deg, #0D0D0D 0%, #1A1A2E 60%, #371F66 100%);
      color: #FFFFFF;
      padding: 3rem 2.5rem;
      position: relative;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      overflow: hidden;
    }

    .auth-brand-panel::after {
      content: '';
      position: absolute;
      width: 280px;
      height: 280px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(196, 140, 255, 0.28), transparent 70%);
      bottom: -90px;
      right: -80px;
      pointer-events: none;
    }

    .brand-lockup {
      display: inline-flex;
      align-items: center;
      gap: 0.75rem;
      text-decoration: none;
      color: #FFFFFF;
    }

    .brand-lockup span {
      font-family: 'Poppins', sans-serif;
      font-weight: 800;
      font-size: 1.5rem;
      background: linear-gradient(135deg, #C48CFF, #FFFFFF);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .feature-pill {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.75rem 1rem;
      border-radius: 0.85rem;
      background: rgba(255, 255, 255, 0.06);
      border: 1px solid rgba(196, 140, 255, 0.2);
      font-size: 0.875rem;
      color: rgba(255, 255, 255, 0.88);
    }

    .feature-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      background: var(--pk-purple);
      box-shadow: 0 0 10px var(--pk-purple);
      flex-shrink: 0;
    }

    .auth-form-panel {
      padding: 3rem 2.75rem;
      background: var(--pk-surface);
    }

    .form-label {
      font-weight: 600;
      font-size: 0.875rem;
      color: #1F2937;
    }

    .form-control {
      border-radius: 0.75rem;
      border: 1.5px solid var(--pk-border);
      padding: 0.75rem 1rem;
      font-size: 0.95rem;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .form-control:focus {
      border-color: var(--pk-purple-dark);
      box-shadow: 0 0 0 0.22rem rgba(196, 140, 255, 0.28);
    }

    .input-group .form-control {
      border-top-right-radius: 0;
      border-bottom-right-radius: 0;
    }

    .btn-toggle-pass {
      border: 1.5px solid var(--pk-border);
      border-left: none;
      border-top-right-radius: 0.75rem;
      border-bottom-right-radius: 0.75rem;
      background: #F9FAFB;
      color: #4B5563;
      padding: 0 0.95rem;
      transition: background 0.2s ease, color 0.2s ease;
    }

    .btn-toggle-pass:hover {
      background: #F3E8FF;
      color: var(--pk-purple-dark);
    }

    .btn-pk-submit {
      background: linear-gradient(135deg, var(--pk-purple) 0%, var(--pk-purple-dark) 100%);
      border: none;
      border-radius: 0.85rem;
      color: #FFFFFF;
      font-family: 'Poppins', sans-serif;
      font-weight: 600;
      padding: 0.85rem 1.5rem;
      font-size: 1rem;
      box-shadow: 0 8px 24px rgba(123, 82, 171, 0.35);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .btn-pk-submit:hover {
      color: #FFFFFF;
      transform: translateY(-2px);
      box-shadow: 0 12px 28px rgba(123, 82, 171, 0.45);
    }

    .btn-pk-submit:disabled {
      opacity: 0.75;
      transform: none;
    }

    .pk-link {
      color: var(--pk-purple-dark);
      font-weight: 600;
      text-decoration: none;
    }

    .pk-link:hover {
      color: var(--pk-black);
      text-decoration: underline;
    }

    .back-home-link {
      color: rgba(255, 255, 255, 0.75);
      text-decoration: none;
      font-size: 0.875rem;
      display: inline-flex;
      align-items: center;
      gap: 0.4rem;
      transition: color 0.2s ease;
    }

    .back-home-link:hover {
      color: var(--pk-purple);
    }

    @media (max-width: 767.98px) {
      .auth-form-panel {
        padding: 2rem 1.5rem;
      }
      .auth-brand-panel {
        padding: 2rem 1.5rem;
      }
    }
  </style>
</head>
<body>

<main class="auth-shell">
  <div class="row g-0">
    <!-- Panel izquierdo de marca -->
    <div class="col-lg-5 auth-brand-panel">
      <div>
        <a href="/" class="brand-lockup" aria-label="Volver a PetKardex inicio">
          <svg width="40" height="40" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <ellipse cx="50" cy="62" rx="28" ry="24" fill="#C48CFF"/>
            <ellipse cx="22" cy="45" rx="10" ry="14" fill="#C48CFF" transform="rotate(-20 22 45)"/>
            <ellipse cx="78" cy="45" rx="10" ry="14" fill="#C48CFF" transform="rotate(20 78 45)"/>
            <ellipse cx="34" cy="28" rx="8" ry="11" fill="#C48CFF" transform="rotate(-10 34 28)"/>
            <ellipse cx="66" cy="28" rx="8" ry="11" fill="#C48CFF" transform="rotate(10 66 28)"/>
            <ellipse cx="50" cy="62" rx="16" ry="12" fill="#7B52AB"/>
          </svg>
          <span>PetKardex</span>
        </a>

        <h2 class="mt-4 mb-2 fw-bold" style="font-size: 1.75rem;">Portal Clínico y Administrativo</h2>
        <p class="mb-4" style="color: rgba(255,255,255,0.72); font-size: 0.92rem; line-height: 1.6;">
          Acceso protegido mediante sesiones cifradas y control de permisos basado en roles.
        </p>

        <div class="d-flex flex-column gap-2 mt-4">
          <div class="feature-pill">
            <span class="feature-dot"></span>
            <span>Expediente clínico e historial inmutable</span>
          </div>
          <div class="feature-pill">
            <span class="feature-dot"></span>
            <span>Carnet QR de emergencia para pacientes</span>
          </div>
          <div class="feature-pill">
            <span class="feature-dot"></span>
            <span>Hospitalización, farmacia, agenda y estética</span>
          </div>
        </div>
      </div>

      <div class="mt-5 pt-3 position-relative" style="z-index: 1;">
        <a href="/" class="back-home-link">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
          </svg>
          Volver a la página principal
        </a>
      </div>
    </div>

    <!-- Panel derecho de formulario -->
    <div class="col-lg-7 auth-form-panel d-flex flex-column justify-content-center">
      <div class="mb-4">
        <span class="badge rounded-pill px-3 py-2 mb-2" style="background: rgba(196, 140, 255, 0.18); color: #5A3A8A; font-weight: 600;">
          Acceso Seguro
        </span>
        <h1 class="h3 fw-bold text-dark mb-1">Iniciar Sesión</h1>
        <p class="text-muted small mb-0">Ingresa tus credenciales asignadas para continuar al sistema.</p>
      </div>

      <?php if ($loggedOut): ?>
        <div class="alert alert-info alert-dismissible fade show d-flex align-items-center gap-2 rounded-3" role="alert">
          <span>Has cerrado sesión correctamente.</span>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
      <?php endif; ?>

      <?php foreach ($flashList as $item): ?>
        <?php
          $type = $item['type'] ?? 'info';
          $bsClass = match ($type) {
              'error', 'danger' => 'alert-danger',
              'success'         => 'alert-success',
              'warning'         => 'alert-warning',
              default           => 'alert-info',
          };
        ?>
        <div class="alert <?= $bsClass ?> alert-dismissible fade show rounded-3 small" role="alert">
          <?= Security::e((string) ($item['message'] ?? '')) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
      <?php endforeach; ?>

      <div id="clientAlert" class="alert alert-danger d-none rounded-3 small" role="alert"></div>

      <form id="loginForm" action="/app/login" method="POST" novalidate autocomplete="off">
        <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />

        <div class="mb-3">
          <label for="email" class="form-label">Correo electrónico</label>
          <input
            type="email"
            class="form-control"
            id="email"
            name="email"
            placeholder="Ingresa tu correo electrónico"
            autocomplete="username"
            required
          />
          <div class="invalid-feedback">Ingresa un correo electrónico válido.</div>
        </div>

        <div class="mb-3">
          <div class="d-flex justify-content-between align-items-center mb-1">
            <label for="password" class="form-label mb-0">Contraseña</label>
            <a href="/app/forgot-password" class="pk-link small">¿Olvidaste tu contraseña?</a>
          </div>
          <div class="input-group">
            <input
              type="password"
              class="form-control"
              id="password"
              name="password"
              placeholder="Ingresa tu contraseña"
              autocomplete="current-password"
              required
            />
            <button
              class="btn btn-toggle-pass"
              type="button"
              id="togglePasswordBtn"
              aria-label="Mostrar u ocultar contraseña"
              title="Mostrar u ocultar contraseña"
            >
              <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
              </svg>
            </button>
          </div>
          <div class="invalid-feedback">Ingresa tu contraseña.</div>
        </div>

        <div class="d-grid mt-4">
          <button type="submit" id="submitLoginBtn" class="btn btn-pk-submit">
            <span id="submitBtnText">Ingresar al Sistema</span>
          </button>
        </div>
      </form>

      <div class="mt-4 pt-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2 small text-muted">
        <span>Protegido con CSRF y sesiones seguras</span>
        <a href="/" class="pk-link d-lg-none">Volver al inicio</a>
      </div>
    </div>
  </div>
</main>

<!-- Bootstrap 5.3 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<script>
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('loginForm');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const toggleBtn = document.getElementById('togglePasswordBtn');
    const submitBtn = document.getElementById('submitLoginBtn');
    const submitText = document.getElementById('submitBtnText');
    const clientAlert = document.getElementById('clientAlert');

    // Asegurar que los campos inicien limpios
    emailInput.value = '';
    passwordInput.value = '';

    if (toggleBtn && passwordInput) {
      toggleBtn.addEventListener('click', () => {
        const isPass = passwordInput.type === 'password';
        passwordInput.type = isPass ? 'text' : 'password';
        toggleBtn.setAttribute('aria-pressed', String(isPass));
      });
    }

    if (form) {
      form.addEventListener('submit', (e) => {
        const email = emailInput.value.trim();
        const password = passwordInput.value;
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        clientAlert.classList.add('d-none');
        emailInput.classList.remove('is-invalid');
        passwordInput.classList.remove('is-invalid');

        let valid = true;
        if (!emailRegex.test(email)) {
          emailInput.classList.add('is-invalid');
          valid = false;
        }
        if (password.length === 0) {
          passwordInput.classList.add('is-invalid');
          valid = false;
        }

        if (!valid) {
          e.preventDefault();
          clientAlert.textContent = 'Por favor, completa un correo electrónico válido y tu contraseña.';
          clientAlert.classList.remove('d-none');
          return;
        }

        submitBtn.disabled = true;
        submitText.textContent = 'Verificando credenciales...';
      });
    }
  });
</script>
</body>
</html>


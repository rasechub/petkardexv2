<?php
/**
 * PetKardex v2 - Vista de Recuperación de Contraseña
 */

use PetKardex\Helpers\Security;

$pageTitle = $title ?? 'Recuperar Contraseña — PetKardex';
$flashList = $flash ?? [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= Security::e($pageTitle) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" />
  <style>
    body {
      min-height: 100vh;
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #0D0D0D 0%, #1A1A2E 60%, #2D1B69 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
    }
    h1 { font-family: 'Poppins', sans-serif; }
    .card-auth {
      width: 100%;
      max-width: 460px;
      border-radius: 1.25rem;
      border: 1px solid rgba(196, 140, 255, 0.25);
      box-shadow: 0 24px 60px rgba(0, 0, 0, 0.5);
    }
    .btn-pk {
      background: linear-gradient(135deg, #C48CFF 0%, #7B52AB 100%);
      border: none;
      color: #fff;
      font-family: 'Poppins', sans-serif;
      font-weight: 600;
      border-radius: 0.75rem;
      padding: 0.8rem;
    }
    .btn-pk:hover { color: #fff; opacity: 0.94; }
  </style>
</head>
<body>
<div class="card card-auth bg-white p-4 p-md-5">
  <div class="mb-4">
    <span class="badge rounded-pill px-3 py-2 mb-2" style="background: rgba(196,140,255,0.18); color: #5A3A8A;">Recuperación</span>
    <h1 class="h4 fw-bold text-dark mb-1">Restablecer Contraseña</h1>
    <p class="text-muted small mb-0">Ingresa el correo electrónico de tu cuenta para recibir un enlace de recuperación.</p>
  </div>

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
    <div class="alert <?= $bsClass ?> small rounded-3" role="alert">
      <?= Security::e((string) ($item['message'] ?? '')) ?>
    </div>
  <?php endforeach; ?>

  <form action="/app/forgot-password" method="POST" autocomplete="off">
    <input type="hidden" name="_csrf_token" value="<?= Security::e((string) ($csrfToken ?? '')) ?>" />
    <div class="mb-3">
      <label for="email" class="form-label fw-semibold small">Correo electrónico</label>
      <input type="email" id="email" name="email" class="form-control" placeholder="Ingresa tu correo electrónico" required />
    </div>
    <div class="d-grid mt-4">
      <button type="submit" class="btn btn-pk">Enviar enlace de recuperación</button>
    </div>
  </form>

  <div class="text-center mt-4">
    <a href="/app/login" class="small fw-semibold text-decoration-none" style="color: #7B52AB;">Volver al inicio de sesión</a>
  </div>
</div>
</body>
</html>


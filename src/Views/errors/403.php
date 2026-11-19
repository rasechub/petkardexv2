<?php
/**
 * PetKardex v2 - Vista de Error 403 (Acceso Denegado)
 */

use PetKardex\Helpers\Security;

$isLogged = isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
$rolName  = $_SESSION['user']['rol_nombre'] ?? null;
$backUrl  = $isLogged ? '/app/dashboard' : '/app/login';
$backText = $isLogged ? 'Volver al Dashboard' : 'Ir al Inicio de Sesión';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="author" content="CESAR AQUINO ANGLAS - VI B NOCHE" />
  <meta name="copyright" content="© 2026 PetKardex | CESAR AQUINO ANGLAS - VI B NOCHE" />
  <title>403 — Acceso Denegado | PetKardex</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <style>
    body {
      min-height: 100vh;
      font-family: 'Inter', sans-serif;
      background: radial-gradient(circle at 20% 20%, rgba(196, 140, 255, 0.16), transparent 45%),
                  linear-gradient(135deg, #0D0D0D 0%, #1A1A2E 60%, #2D1B69 100%);
      color: #FFFFFF;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
    }
    h1, .error-code { font-family: 'Poppins', sans-serif; }
    .error-card {
      max-width: 520px;
      width: 100%;
      background: rgba(20, 20, 32, 0.88);
      backdrop-filter: blur(16px);
      border: 1px solid rgba(196, 140, 255, 0.28);
      border-radius: 1.5rem;
      padding: 3rem 2.25rem;
      box-shadow: 0 24px 64px rgba(0, 0, 0, 0.55);
      text-align: center;
    }
    .shield-wrap {
      width: 76px;
      height: 76px;
      border-radius: 1.25rem;
      background: rgba(196, 140, 255, 0.16);
      border: 1px solid rgba(196, 140, 255, 0.35);
      color: #C48CFF;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 2.25rem;
      margin-bottom: 1.25rem;
    }
    .error-code {
      font-size: 3.5rem;
      font-weight: 800;
      line-height: 1;
      background: linear-gradient(135deg, #C48CFF 0%, #FFFFFF 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    .btn-pk-back {
      background: linear-gradient(135deg, #C48CFF 0%, #7B52AB 100%);
      border: none;
      color: #FFFFFF;
      font-family: 'Poppins', sans-serif;
      font-weight: 600;
      border-radius: 50px;
      padding: 0.78rem 1.85rem;
      box-shadow: 0 8px 24px rgba(196, 140, 255, 0.3);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .btn-pk-back:hover {
      color: #FFFFFF;
      transform: translateY(-2px);
      box-shadow: 0 12px 30px rgba(196, 140, 255, 0.45);
    }
  </style>
</head>
<body>
  <main class="error-card">
    <div class="shield-wrap">
      <i class="bi bi-shield-lock-fill"></i>
    </div>
    <div class="error-code mb-2">403</div>
    <h1 class="h3 fw-bold mb-2">Acceso Denegado</h1>
    <p class="text-white-50 mb-3">
      No cuentas con los permisos necesarios para visualizar este módulo o ejecutar la acción solicitada.
    </p>
    <?php if ($rolName): ?>
      <div class="mb-4">
        <span class="badge rounded-pill px-3 py-2" style="background: rgba(196, 140, 255, 0.16); color: #C48CFF; border: 1px solid rgba(196, 140, 255, 0.3);">
          Rol actual: <?= Security::e((string) $rolName) ?>
        </span>
      </div>
    <?php endif; ?>
    <div class="d-flex justify-content-center gap-2 flex-wrap">
      <a href="<?= Security::e($backUrl) ?>" class="btn btn-pk-back">
        <i class="bi bi-arrow-left-circle me-2"></i><?= Security::e($backText) ?>
      </a>
    </div>

    <div class="mt-4 pt-3 border-top border-secondary-subtle text-white-50" style="font-size: 0.72rem;">
      <div><?= \PetKardex\Config\Config::APP_COPYRIGHT ?></div>
      <div><?= \PetKardex\Config\Config::APP_INSTITUTION ?> · Todos los derechos reservados.</div>
    </div>
  </main>
</body>
</html>

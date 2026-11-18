<?php
/**
 * PetKardex v2 - Layout Principal del Sistema Interno
 *
 * Incluye Sidebar responsivo (fijo en escritorio + Offcanvas en móvil),
 * Topbar con menú de usuario, y navegación condicional por rol usando Auth.
 *
 * Variables esperadas:
 * - string $title
 * - string $pageTitle
 * - string $activeMenu
 * - string $content
 * - Auth   $auth
 * - array  $user
 * - array  $flash
 */

use PetKardex\Core\Auth;
use PetKardex\Helpers\Security;

$authInstance = $auth ?? new Auth();
$currentUser  = $user ?? $authInstance->getCurrentUser() ?? ($_SESSION['user'] ?? []);
$rolSlug      = (string) ($currentUser['rol_slug'] ?? '');
$rolNombre    = (string) ($currentUser['rol_nombre'] ?? 'Usuario');
$fullName     = trim(($currentUser['nombre'] ?? '') . ' ' . ($currentUser['apellido'] ?? ''));
$initials     = strtoupper(
    mb_substr((string) ($currentUser['nombre'] ?? 'U'), 0, 1)
    . mb_substr((string) ($currentUser['apellido'] ?? ''), 0, 1)
);
$currentMenu  = $activeMenu ?? 'dashboard';
$flashList    = $flash ?? [];

// Definición centralizada del menú condicional según el rol leído desde Auth/Sesión
$menuItems = [
    [
        'key'   => 'dashboard',
        'label' => 'Dashboard',
        'icon'  => 'bi-grid-1x2-fill',
        'href'  => '/app/dashboard',
    ],
];

if ($authInstance->hasRole('superadmin')) {
    $menuItems = array_merge($menuItems, [
        ['key' => 'pacientes',       'label' => 'Pacientes / Mascotas', 'icon' => 'bi-heart-pulse-fill',   'href' => '/app/pacientes'],
        ['key' => 'usuarios',        'label' => 'Gestión de Usuarios',  'icon' => 'bi-people-fill',        'href' => '/app/usuarios'],
        ['key' => 'agenda',          'label' => 'Agenda General',       'icon' => 'bi-calendar2-week',     'href' => '/app/agenda'],
        ['key' => 'hospitalizacion', 'label' => 'Hospitalización',      'icon' => 'bi-hospital-fill',      'href' => '/app/hospitalizacion'],
        ['key' => 'estetica',        'label' => 'Estética / Spa',       'icon' => 'bi-scissors',           'href' => '/app/estetica'],
        ['key' => 'inventario',      'label' => 'Inventario',           'icon' => 'bi-box-seam-fill',      'href' => '/app/inventario'],
        ['key' => 'servicios',       'label' => 'Servicios y Tarifas',  'icon' => 'bi-tags-fill',          'href' => '/app/servicios'],
        ['key' => 'pos',             'label' => 'Caja / POS',           'icon' => 'bi-cart-check-fill',    'href' => '/app/caja'],
        ['key' => 'qr_scanner',      'label' => 'Escáner QR',           'icon' => 'bi-qr-code-scan',       'href' => '/app/qr/scanner'],
    ]);
} elseif ($authInstance->hasRole('veterinario')) {
    $menuItems = array_merge($menuItems, [
        ['key' => 'pacientes',       'label' => 'Pacientes / Mascotas', 'icon' => 'bi-heart-pulse-fill',   'href' => '/app/pacientes'],
        ['key' => 'qr_scanner',      'label' => 'Escáner QR',           'icon' => 'bi-qr-code-scan',       'href' => '/app/qr/scanner'],
        ['key' => 'agenda',          'label' => 'Agenda Médica',        'icon' => 'bi-calendar2-week',     'href' => '/app/agenda'],
        ['key' => 'hospitalizacion', 'label' => 'Hospitalizados',       'icon' => 'bi-hospital',           'href' => '/app/hospitalizacion'],
    ]);
} elseif ($authInstance->hasRole('guardia')) {
    $menuItems = array_merge($menuItems, [
        ['key' => 'hospitalizacion', 'label' => 'Triage / Hospitalización', 'icon' => 'bi-activity',        'href' => '/app/hospitalizacion'],
        ['key' => 'agenda',          'label' => 'Agenda de Turno',          'icon' => 'bi-calendar2-week',  'href' => '/app/agenda'],
        ['key' => 'qr_scanner',      'label' => 'Escáner QR',               'icon' => 'bi-qr-code-scan',    'href' => '/app/qr/scanner'],
    ]);
} elseif ($authInstance->hasRole('recepcionista')) {
    $menuItems = array_merge($menuItems, [
        ['key' => 'pacientes',       'label' => 'Pacientes / Mascotas',   'icon' => 'bi-heart-pulse-fill',    'href' => '/app/pacientes'],
        ['key' => 'usuarios',        'label' => 'Gestión de Usuarios',    'icon' => 'bi-people-fill',         'href' => '/app/usuarios'],
        ['key' => 'agenda',          'label' => 'Agenda General',         'icon' => 'bi-calendar-check-fill', 'href' => '/app/agenda'],
        ['key' => 'pos',             'label' => 'Punto de Venta (Caja)',  'icon' => 'bi-cart-check-fill',     'href' => '/app/caja'],
        ['key' => 'inventario',      'label' => 'Inventario Básico',      'icon' => 'bi-box-seam-fill',       'href' => '/app/inventario'],
        ['key' => 'hospitalizacion', 'label' => 'Jaulas / Hospitaliz.',   'icon' => 'bi-hospital',            'href' => '/app/hospitalizacion'],
        ['key' => 'qr_scanner',      'label' => 'Escáner QR',             'icon' => 'bi-qr-code-scan',        'href' => '/app/qr/scanner'],
    ]);
} elseif ($authInstance->hasRole('groomer')) {
    $menuItems = array_merge($menuItems, [
        ['key' => 'estetica', 'label' => 'Agenda Estética Hoy', 'icon' => 'bi-scissors',          'href' => '/app/estetica'],
        ['key' => 'agenda',   'label' => 'Calendario Estética', 'icon' => 'bi-calendar2-week',    'href' => '/app/agenda'],
    ]);
} elseif ($authInstance->hasRole('propietario')) {
    $menuItems = array_merge($menuItems, [
        ['key' => 'qr',    'label' => 'Descargar QR',   'icon' => 'bi-qr-code',        'href' => '/app/qr'],
        ['key' => 'citas', 'label' => 'Próximas Citas', 'icon' => 'bi-calendar-event', 'href' => '/app/portal/citas'],
    ]);
} elseif ($authInstance->hasRole('emergencia')) {
    $menuItems = array_merge($menuItems, [
        ['key' => 'hospitalizacion', 'label' => 'Triage / Emergencia', 'icon' => 'bi-activity',     'href' => '/app/emergencias'],
        ['key' => 'qr_scanner',      'label' => 'Escáner QR',          'icon' => 'bi-qr-code-scan', 'href' => '/app/qr/scanner'],
    ]);
}

$renderSidebarNav = static function (array $items, string $activeKey): string {
    $html = '<ul class="nav flex-column gap-1 px-3">';
    foreach ($items as $item) {
        $isActive = ($item['key'] === $activeKey) ? 'active' : '';
        $href     = Security::e($item['href']);
        $icon     = Security::e($item['icon']);
        $label    = Security::e($item['label']);
        $html .= "<li class=\"nav-item\">
            <a href=\"{$href}\" class=\"nav-link pk-nav-link {$isActive}\">
                <i class=\"bi {$icon}\"></i>
                <span>{$label}</span>
            </a>
        </li>";
    }
    $html .= '</ul>';
    return $html;
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= Security::e($title ?? 'Dashboard — PetKardex') ?></title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />

  <!-- Bootstrap 5.3 CSS & Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />

  <style>
    :root {
      --pk-purple: #C48CFF;
      --pk-purple-dark: #7B52AB;
      --pk-purple-soft: rgba(196, 140, 255, 0.14);
      --pk-black: #0D0D0D;
      --pk-sidebar-bg: #12121C;
      --pk-sidebar-border: rgba(196, 140, 255, 0.14);
      --pk-body-bg: #F5F3FA;
      --pk-card-bg: #FFFFFF;
      --pk-sidebar-width: 268px;
    }

    body {
      font-family: 'Inter', sans-serif;
      background-color: var(--pk-body-bg);
      color: #1F2937;
      min-height: 100vh;
    }

    h1, h2, h3, h4, h5, .brand-title {
      font-family: 'Poppins', sans-serif;
    }

    /* ── Sidebar Desktop ── */
    .pk-sidebar {
      width: var(--pk-sidebar-width);
      min-height: 100vh;
      background: linear-gradient(180deg, var(--pk-black) 0%, var(--pk-sidebar-bg) 100%);
      border-right: 1px solid var(--pk-sidebar-border);
      position: fixed;
      top: 0;
      left: 0;
      bottom: 0;
      z-index: 1030;
      display: flex;
      flex-direction: column;
    }

    .pk-offcanvas-sidebar {
      width: var(--pk-sidebar-width) !important;
      background: linear-gradient(180deg, var(--pk-black) 0%, var(--pk-sidebar-bg) 100%);
      color: #fff;
    }

    .pk-brand-header {
      padding: 1.35rem 1.25rem;
      border-bottom: 1px solid var(--pk-sidebar-border);
      display: flex;
      align-items: center;
      gap: 0.75rem;
      text-decoration: none;
    }

    .pk-brand-text {
      font-family: 'Poppins', sans-serif;
      font-weight: 800;
      font-size: 1.35rem;
      background: linear-gradient(135deg, var(--pk-purple) 0%, #FFFFFF 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .pk-sidebar-section-label {
      font-size: 0.7rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: rgba(255, 255, 255, 0.42);
      padding: 1.15rem 1.35rem 0.45rem;
      font-weight: 600;
    }

    .pk-nav-link {
      color: rgba(255, 255, 255, 0.76);
      border-radius: 0.75rem;
      padding: 0.72rem 0.95rem;
      font-size: 0.92rem;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      transition: background-color 0.2s ease, color 0.2s ease, transform 0.15s ease;
    }

    .pk-nav-link i {
      font-size: 1.08rem;
      color: var(--pk-purple);
      transition: color 0.2s ease;
    }

    .pk-nav-link:hover {
      background-color: rgba(196, 140, 255, 0.12);
      color: #FFFFFF;
      transform: translateX(2px);
    }

    .pk-nav-link.active {
      background: linear-gradient(135deg, var(--pk-purple) 0%, var(--pk-purple-dark) 100%);
      color: #0D0D0D;
      font-weight: 600;
      box-shadow: 0 6px 18px rgba(196, 140, 255, 0.28);
    }

    .pk-nav-link.active i {
      color: #0D0D0D;
    }

    .pk-sidebar-footer {
      margin-top: auto;
      padding: 1rem 1.25rem;
      border-top: 1px solid var(--pk-sidebar-border);
    }

    /* ── Main Wrapper & Topbar ── */
    .pk-main-wrapper {
      margin-left: var(--pk-sidebar-width);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .pk-topbar {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-bottom: 1px solid #E5E7EB;
      padding: 0.85rem 1.5rem;
      position: sticky;
      top: 0;
      z-index: 1020;
    }

    .pk-role-badge {
      background-color: var(--pk-purple-soft);
      color: var(--pk-purple-dark);
      border: 1px solid rgba(196, 140, 255, 0.45);
      font-weight: 600;
      font-size: 0.76rem;
      padding: 0.35rem 0.75rem;
      border-radius: 999px;
    }

    .pk-avatar {
      width: 38px;
      height: 38px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--pk-purple) 0%, var(--pk-purple-dark) 100%);
      color: #0D0D0D;
      font-family: 'Poppins', sans-serif;
      font-weight: 700;
      font-size: 0.85rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .pk-card {
      background: var(--pk-card-bg);
      border: 1px solid rgba(17, 24, 39, 0.06);
      border-radius: 1rem;
      box-shadow: 0 4px 20px rgba(13, 13, 13, 0.04);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .pk-card-hover:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 28px rgba(123, 82, 171, 0.14);
    }

    .pk-icon-box {
      width: 48px;
      height: 48px;
      border-radius: 0.85rem;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 1.35rem;
      background: var(--pk-purple-soft);
      color: var(--pk-purple-dark);
    }

    .btn-pk-primary {
      background: linear-gradient(135deg, var(--pk-purple) 0%, var(--pk-purple-dark) 100%);
      border: none;
      color: #FFFFFF;
      font-weight: 600;
      border-radius: 0.75rem;
    }

    .btn-pk-primary:hover {
      color: #FFFFFF;
      filter: brightness(1.04);
    }

    @media (max-width: 991.98px) {
      .pk-main-wrapper {
        margin-left: 0;
      }
      .pk-topbar {
        padding: 0.75rem 1rem;
      }
    }
  </style>
</head>
<body>

<!-- Sidebar Escritorio -->
<aside class="pk-sidebar d-none d-lg-flex" aria-label="Menú lateral">
  <a href="/app/dashboard" class="pk-brand-header">
    <svg width="34" height="34" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <ellipse cx="50" cy="62" rx="28" ry="24" fill="#C48CFF"/>
      <ellipse cx="22" cy="45" rx="10" ry="14" fill="#C48CFF" transform="rotate(-20 22 45)"/>
      <ellipse cx="78" cy="45" rx="10" ry="14" fill="#C48CFF" transform="rotate(20 78 45)"/>
      <ellipse cx="34" cy="28" rx="8" ry="11" fill="#C48CFF" transform="rotate(-10 34 28)"/>
      <ellipse cx="66" cy="28" rx="8" ry="11" fill="#C48CFF" transform="rotate(10 66 28)"/>
      <ellipse cx="50" cy="62" rx="16" ry="12" fill="#7B52AB"/>
    </svg>
    <span class="pk-brand-text">PetKardex</span>
  </a>

  <div class="pk-sidebar-section-label">
    Módulos · <?= Security::e($rolNombre) ?>
  </div>

  <nav class="flex-grow-1 overflow-auto py-1">
    <?= $renderSidebarNav($menuItems, $currentMenu) ?>
  </nav>

  <div class="pk-sidebar-footer">
    <a href="/app/logout" class="nav-link pk-nav-link text-danger-emphasis" style="color: #FCA5A5 !important;">
      <i class="bi bi-box-arrow-left" style="color: #FCA5A5;"></i>
      <span>Cerrar Sesión</span>
    </a>
  </div>
</aside>

<!-- Sidebar Móvil (Offcanvas) -->
<div class="offcanvas offcanvas-start pk-offcanvas-sidebar d-lg-none" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
  <div class="offcanvas-header border-bottom" style="border-color: var(--pk-sidebar-border) !important;">
    <div class="d-flex align-items-center gap-2" id="mobileSidebarLabel">
      <svg width="30" height="30" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <ellipse cx="50" cy="62" rx="28" ry="24" fill="#C48CFF"/>
        <ellipse cx="22" cy="45" rx="10" ry="14" fill="#C48CFF" transform="rotate(-20 22 45)"/>
        <ellipse cx="78" cy="45" rx="10" ry="14" fill="#C48CFF" transform="rotate(20 78 45)"/>
        <ellipse cx="34" cy="28" rx="8" ry="11" fill="#C48CFF" transform="rotate(-10 34 28)"/>
        <ellipse cx="66" cy="28" rx="8" ry="11" fill="#C48CFF" transform="rotate(10 66 28)"/>
      </svg>
      <span class="pk-brand-text">PetKardex</span>
    </div>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Cerrar menú"></button>
  </div>
  <div class="offcanvas-body d-flex flex-column px-0">
    <div class="pk-sidebar-section-label">
      Módulos · <?= Security::e($rolNombre) ?>
    </div>
    <nav class="flex-grow-1 py-1">
      <?= $renderSidebarNav($menuItems, $currentMenu) ?>
    </nav>
    <div class="pk-sidebar-footer">
      <a href="/app/logout" class="nav-link pk-nav-link" style="color: #FCA5A5 !important;">
        <i class="bi bi-box-arrow-left" style="color: #FCA5A5;"></i>
        <span>Cerrar Sesión</span>
      </a>
    </div>
  </div>
</div>

<!-- Contenedor Principal -->
<div class="pk-main-wrapper">
  <!-- Topbar -->
  <header class="pk-topbar d-flex align-items-center justify-content-between gap-3">
    <div class="d-flex align-items-center gap-3">
      <button
        class="btn btn-outline-dark d-lg-none rounded-3 px-2 py-1"
        type="button"
        data-bs-toggle="offcanvas"
        data-bs-target="#mobileSidebar"
        aria-controls="mobileSidebar"
        aria-label="Abrir menú"
      >
        <i class="bi bi-list fs-4"></i>
      </button>
      <div>
        <h1 class="h5 mb-0 fw-bold text-dark"><?= Security::e($pageTitle ?? 'Panel Principal') ?></h1>
        <small class="text-muted d-none d-sm-inline">PetKardex ERP Veterinario</small>
      </div>
    </div>

    <div class="d-flex align-items-center gap-3">
      <span class="pk-role-badge d-none d-md-inline-flex align-items-center gap-1">
        <i class="bi bi-shield-check"></i>
        <?= Security::e($rolNombre) ?>
      </span>

      <div class="dropdown">
        <button
          class="btn btn-light border rounded-pill d-flex align-items-center gap-2 py-1 ps-1 pe-3"
          type="button"
          data-bs-toggle="dropdown"
          aria-expanded="false"
        >
          <span class="pk-avatar"><?= Security::e($initials !== '' ? $initials : 'PK') ?></span>
          <span class="d-none d-sm-inline text-start lh-sm">
            <span class="d-block fw-semibold small text-dark"><?= Security::e($fullName !== '' ? $fullName : 'Usuario') ?></span>
            <span class="d-block text-muted" style="font-size: 0.72rem;"><?= Security::e($rolSlug) ?></span>
          </span>
          <i class="bi bi-chevron-down small text-muted"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 rounded-3 mt-2">
          <li class="px-3 py-2 border-bottom">
            <div class="fw-semibold small"><?= Security::e($fullName) ?></div>
            <div class="text-muted" style="font-size: 0.75rem;"><?= Security::e((string) ($currentUser['email'] ?? '')) ?></div>
          </li>
          <li>
            <a class="dropdown-item py-2 small" href="/app/dashboard">
              <i class="bi bi-grid-1x2 me-2 text-secondary"></i>Mi Dashboard
            </a>
          </li>
          <li><hr class="dropdown-divider my-1" /></li>
          <li>
            <a class="dropdown-item py-2 small text-danger" href="/app/logout">
              <i class="bi bi-box-arrow-right me-2"></i>Cerrar Sesión
            </a>
          </li>
        </ul>
      </div>
    </div>
  </header>

  <!-- Área de Contenido -->
  <main class="container-fluid p-3 p-md-4 flex-grow-1">
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
      <div class="alert <?= $bsClass ?> alert-dismissible fade show rounded-3 shadow-sm" role="alert">
        <?= Security::e((string) ($item['message'] ?? '')) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
      </div>
    <?php endforeach; ?>

    <?= $content ?? '' ?>
  </main>

  <footer class="px-4 py-3 border-top bg-white text-muted small d-flex justify-content-between flex-wrap gap-2">
    <span>&copy; <?= date('Y') ?> <strong>PetKardex v2</strong> — Gestión Clínica Veterinaria</span>
    <span>Sesión protegida por roles (ACL)</span>
  </footer>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>


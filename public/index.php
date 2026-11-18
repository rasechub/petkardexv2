<?php
/**
 * PetKardex v2 - Punto de Entrada Principal (Front Controller + Landing Page)
 *
 * - Rutas /app/* : Despacha la aplicación MVC a través de Router.
 * - Rutas / y /home : Renderiza la Landing Page pública en HTML5 + Bootstrap 5.
 */

declare(strict_types=1);

$rootDir = dirname(__DIR__);

// 1. Autoloader de Composer (si existe) + PSR-4 fallback para PetKardex\
$composerAutoload = $rootDir . '/vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

spl_autoload_register(static function (string $class) use ($rootDir): void {
    $prefix = 'PetKardex\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $relativePath  = str_replace('\\', '/', $relativeClass);

    // Mapeo directo PSR-4 y compatibilidad con archivos en minúsculas (config.php, database.php)
    $candidates = [
        $rootDir . '/src/' . $relativePath . '.php',
        $rootDir . '/src/' . dirname($relativePath) . '/' . strtolower(basename($relativePath)) . '.php',
    ];

    foreach ($candidates as $file) {
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

use PetKardex\Config\Config;
use PetKardex\Core\Router;
use PetKardex\Helpers\Security;

Config::initialize();
Security::sendSecurityHeaders();

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$requestPath = '/' . trim(rawurldecode($requestPath), '/');
if ($requestPath !== '/') {
    $requestPath = rtrim($requestPath, '/');
}

// Atajo amigable /login -> /app/login
if ($requestPath === '/login') {
    header('Location: /app/login', true, 302);
    exit;
}

// 2. Rutas públicas QR (/qr/{uuid}) y aplicación interna (/app/*)
if (
    $requestPath === '/app'
    || str_starts_with($requestPath, '/app/')
    || str_starts_with($requestPath, '/qr/')
) {
    $router = new Router();
    $router->registerDefaultRoutes()->dispatch();
    exit;
}

// 3. Cualquier ruta desconocida fuera de / y /home responde 404
if ($requestPath !== '/' && $requestPath !== '/home') {
    http_response_code(404);
    $view404 = $rootDir . '/src/Views/errors/404.php';
    if (is_file($view404)) {
        require $view404;
    } else {
        echo '<!DOCTYPE html><html lang="es"><body><h1>404 — Página no encontrada</h1><p><a href="/">Volver al inicio</a></p></body></html>';
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="PetKardex — Sistema integral de gestión veterinaria con historial médico digital, códigos QR de emergencia, hospitalización, farmacia/Pet Shop y estética." />
  <meta name="keywords" content="veterinaria, ERP veterinario, historial médico mascotas, QR mascotas, PetKardex" />
  <meta property="og:title" content="PetKardex — Clínica Veterinaria e Historial Digital QR" />
  <meta property="og:description" content="Cuidado médico integral para tu mascota con historial clínico digital accesible mediante código QR." />
  <meta property="og:type" content="website" />
  <title>PetKardex — El cuidado que tu mascota merece</title>

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&family=Inter:wght@400;500;600&display=swap" rel="stylesheet" />

  <!-- Bootstrap 5.3 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous" />

  <!-- Animate.css -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />

  <!-- Landing CSS -->
  <link rel="stylesheet" href="/assets/css/landing.css" />

  <style>
    body { background: #0D0D0D; }
    #hero { background: linear-gradient(135deg, #1A1A2E 0%, #2D1B69 100%); }
  </style>
</head>
<body>

<!-- ============================================================
     HEADER / NAVBAR STICKY
     ============================================================ -->
<nav id="mainNav" class="navbar navbar-expand-lg" aria-label="Navegación principal">
  <div class="container">
    <a class="navbar-brand" href="#inicio" aria-label="PetKardex inicio">
      <svg width="38" height="38" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
        <ellipse cx="50" cy="62" rx="28" ry="24" fill="#C48CFF"/>
        <ellipse cx="22" cy="45" rx="10" ry="14" fill="#C48CFF" transform="rotate(-20 22 45)"/>
        <ellipse cx="78" cy="45" rx="10" ry="14" fill="#C48CFF" transform="rotate(20 78 45)"/>
        <ellipse cx="34" cy="28" rx="8" ry="11" fill="#C48CFF" transform="rotate(-10 34 28)"/>
        <ellipse cx="66" cy="28" rx="8" ry="11" fill="#C48CFF" transform="rotate(10 66 28)"/>
        <ellipse cx="50" cy="62" rx="16" ry="12" fill="#7B52AB"/>
        <path d="M42 65 Q50 55 58 65" stroke="white" stroke-width="2.5" stroke-linecap="round" fill="none"/>
      </svg>
      <span class="brand-text">PetKardex</span>
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain"
            aria-controls="navbarMain" aria-expanded="false" aria-label="Abrir menú">
      <span class="hamburger-box">
        <span class="hamburger-inner"></span>
        <span class="hamburger-inner"></span>
        <span class="hamburger-inner"></span>
      </span>
    </button>

    <div class="collapse navbar-collapse" id="navbarMain">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1 py-3 py-lg-0">
        <li class="nav-item">
          <a class="nav-link nav-link-pk" href="#inicio">Inicio</a>
        </li>
        <li class="nav-item">
          <a class="nav-link nav-link-pk" href="#nosotros">Nosotros</a>
        </li>
        <li class="nav-item">
          <a class="nav-link nav-link-pk" href="#servicios">Servicios</a>
        </li>
        <li class="nav-item">
          <a class="nav-link nav-link-pk" href="#qr-feature">Carnet QR</a>
        </li>
        <li class="nav-item">
          <a class="nav-link nav-link-pk" href="#contacto">Contacto</a>
        </li>
        <li class="nav-item ms-lg-3 mt-2 mt-lg-0">
          <a href="/app/login" class="nav-link btn-portal">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:-2px;margin-right:4px;" aria-hidden="true">
              <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/>
            </svg>
            Iniciar Sesión
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- ============================================================
     HERO SECTION (SLIDER INTERACTIVO)
     ============================================================ -->
<section id="inicio">
  <div id="hero">
    <canvas id="particleCanvas" aria-hidden="true"></canvas>

    <div class="hero-slider" role="region" aria-label="Carrusel principal">
      <!-- Slide 1 -->
      <div class="hero-slide" role="tabpanel" aria-label="Slide 1 de 3">
        <div class="container">
          <div class="hero-content">
            <div class="hero-badge">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6z"/></svg>
              Clínica Veterinaria &amp; ERP Digital v2.0
            </div>
            <h1 class="hero-title">
              El cuidado que<br><span class="accent">tu mascota merece</span>
            </h1>
            <p class="hero-subtitle">
              Atención veterinaria integral respaldada por historial médico digital, seguimiento en hospitalización y acceso inmediato por código QR.
            </p>
            <div class="hero-cta-group">
              <a href="/app/login" class="btn-hero-primary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                Ingresar al Portal
              </a>
              <a href="#servicios" class="btn-hero-secondary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="10 15 15 12 10 9"/></svg>
                Explorar Servicios
              </a>
            </div>
          </div>
        </div>
      </div>

      <!-- Slide 2 -->
      <div class="hero-slide" role="tabpanel" aria-label="Slide 2 de 3">
        <div class="container">
          <div class="hero-content">
            <div class="hero-badge">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 9h1v1H9zM14 9h1v1h-1zM9 14h1v1H9zM14 14h1v1h-1z"/></svg>
              Tecnología QR de Emergencia
            </div>
            <h1 class="hero-title">
              Historial médico<br><span class="accent">digital con QR</span>
            </h1>
            <p class="hero-subtitle">
              Cada paciente cuenta con un código QR único para consultar alertas médicas críticas, vacunas y contacto en cuestión de segundos.
            </p>
            <div class="hero-cta-group">
              <a href="/app/login" class="btn-hero-primary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
                Acceder al Sistema
              </a>
              <a href="#qr-feature" class="btn-hero-secondary">Cómo funciona</a>
            </div>
          </div>
        </div>
      </div>

      <!-- Slide 3 -->
      <div class="hero-slide" role="tabpanel" aria-label="Slide 3 de 3">
        <div class="container">
          <div class="hero-content">
            <div class="hero-badge">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
              Gestión Clínica Especializada
            </div>
            <h1 class="hero-title">
              Hospitalización,<br><span class="accent">Pet Shop y Estética</span>
            </h1>
            <p class="hero-subtitle">
              Cuidado veterinario 24/7, farmacia con stock verificado, estética profesional y portal de seguimiento para propietarios.
            </p>
            <div class="hero-cta-group">
              <a href="/app/login" class="btn-hero-primary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                Ir al Login
              </a>
              <a href="#contacto" class="btn-hero-secondary">Contáctanos</a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="slider-dots" role="tablist" aria-label="Navegación del carrusel">
      <button class="dot" role="tab" aria-label="Ir al slide 1" aria-selected="true"></button>
      <button class="dot" role="tab" aria-label="Ir al slide 2" aria-selected="false"></button>
      <button class="dot" role="tab" aria-label="Ir al slide 3" aria-selected="false"></button>
    </div>

    <div class="hero-stats">
      <div class="container">
        <div class="row g-0">
          <div class="col-4 stat-item">
            <div class="stat-number" data-count="12500" data-suffix="+">0</div>
            <div class="stat-label">Pacientes atendidos</div>
          </div>
          <div class="col-4 stat-item">
            <div class="stat-number" data-count="85000" data-suffix="+">0</div>
            <div class="stat-label">Consultas registradas</div>
          </div>
          <div class="col-4 stat-item">
            <div class="stat-number" data-count="24" data-suffix="/7">0</div>
            <div class="stat-label">Guardia y urgencias</div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================
     NOSOTROS
     ============================================================ -->
<section id="nosotros" class="py-5">
  <div class="container">
    <div class="row g-5 align-items-center">
      <div class="col-lg-6 reveal">
        <span class="section-badge">
          <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 17l-6.2 4.3 2.4-7.4L2 9.4h7.6z"/></svg>
          Nosotros
        </span>
        <h2 class="section-title">Medicina veterinaria<br>moderna y humana</h2>
        <p class="section-subtitle mb-4">
          En <strong>PetKardex</strong> combinamos especialistas comprometidos con tecnología clínica de vanguardia para garantizar diagnósticos precisos y atención cálida.
        </p>
        <p class="text-muted mb-5" style="font-size:.95rem;line-height:1.8;">
          Nuestro sistema mantiene cada vacuna, receta médica, signo vital en hospitalización y sesión estética organizada en un expediente inmutable, disponible para el equipo médico y para ti.
        </p>

        <div class="stats-grid">
          <div class="stat-card reveal">
            <div class="number" data-count="98" data-suffix="%">0</div>
            <div class="label">Satisfacción de tutores</div>
          </div>
          <div class="stat-card reveal">
            <div class="number" data-count="15" data-suffix="+">0</div>
            <div class="label">Especialistas clínicos</div>
          </div>
          <div class="stat-card reveal">
            <div class="number" data-count="100" data-suffix="%">0</div>
            <div class="label">Expediente digital QR</div>
          </div>
          <div class="stat-card reveal">
            <div class="number" data-count="24" data-suffix="/7">0</div>
            <div class="label">Atención de guardia</div>
          </div>
        </div>
      </div>

      <div class="col-lg-6">
        <div class="nosotros-visual reveal">
          <div class="vision-card">
            <div class="vision-icon">
              <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
              </svg>
            </div>
            <div>
              <h4>Nuestra Misión</h4>
              <p>Brindar medicina veterinaria de excelencia con calidez, transparencia y trazabilidad digital completa para proteger la salud y el bienestar de cada mascota.</p>
            </div>
          </div>

          <div class="vision-card" style="border-left-color: var(--gold);">
            <div class="vision-icon" style="background: linear-gradient(135deg, var(--gold), var(--gold-2));">
              <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/>
              </svg>
            </div>
            <div>
              <h4>Nuestra Visión</h4>
              <p>Ser la clínica referente en innovación veterinaria, conectando a médicos, groomers y propietarios mediante historiales médicos inteligentes con códigos QR.</p>
            </div>
          </div>

          <div class="vision-card" style="border-left-color: #48CFAD;">
            <div class="vision-icon" style="background: linear-gradient(135deg, #48CFAD, #0E9F7E);">
              <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
              </svg>
            </div>
            <div>
              <h4>Nuestros Valores</h4>
              <p>Ética clínica, empatía con las familias, respuesta oportuna en emergencias y rigurosidad en el resguardo de la información médica.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================
     SERVICIOS (TARJETAS ANIMADAS)
     ============================================================ -->
<section id="servicios" style="background: var(--white); padding: 6rem 0;">
  <div class="container">
    <div class="text-center reveal">
      <span class="section-badge">Nuestras Especialidades</span>
      <h2 class="section-title mx-auto" style="display:block;text-align:center;">Servicios y Productos</h2>
      <p class="section-subtitle mx-auto mt-2" style="text-align:center;">
        Cobertura clínica completa, farmacia especializada y estética profesional bajo un mismo techo.
      </p>
    </div>

    <div class="services-grid">
      <!-- Consultas -->
      <div class="service-card reveal">
        <div class="service-icon-wrap">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
          </svg>
        </div>
        <h3>Consultas Médicas</h3>
        <p>Evaluación clínica general y especializada con examen físico completo, diagnóstico oportuno, recetas digitales y certificados médicos al instante.</p>
      </div>

      <!-- Hospitalización -->
      <div class="service-card reveal">
        <div class="service-icon-wrap">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3" y="3" width="18" height="18" rx="2"/>
            <path d="M12 8v8M8 12h8"/>
          </svg>
        </div>
        <h3>Hospitalización 24/7</h3>
        <p>Área de internamiento monitoreada por médicos de guardia con bitácora continua de signos vitales, triage de emergencias y reportes al propietario.</p>
      </div>

      <!-- Farmacia / Pet Shop -->
      <div class="service-card reveal">
        <div class="service-icon-wrap">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
            <line x1="3" y1="6" x2="21" y2="6"/>
            <path d="M16 10a4 4 0 0 1-8 0"/>
          </svg>
        </div>
        <h3>Farmacia / Pet Shop</h3>
        <p>Medicamentos recetados con despacho directo desde consulta, alimentos medicados, suplementos y accesorios seleccionados con control de inventario.</p>
      </div>

      <!-- Estética -->
      <div class="service-card reveal">
        <div class="service-icon-wrap">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
          </svg>
        </div>
        <h3>Estética y Grooming</h3>
        <p>Baños dermatológicos, corte higiénico y peluquería con personal capacitado y comunicación directa de alertas médicas al equipo veterinario.</p>
      </div>

      <!-- Vacunación -->
      <div class="service-card reveal">
        <div class="service-icon-wrap">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
          </svg>
        </div>
        <h3>Vacunación Preventiva</h3>
        <p>Esquemas de inmunización personalizados con registro de lote, recordatorios de refuerzo y cartilla digital verificable mediante código QR.</p>
      </div>

      <!-- Cirugía -->
      <div class="service-card reveal">
        <div class="service-icon-wrap">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="12" cy="12" r="10"/>
            <line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
          </svg>
        </div>
        <h3>Cirugía Segura</h3>
        <p>Quirófano equipado para procedimientos de tejidos blandos y esterilizaciones con protocolo preanestésico y recuperación supervisada.</p>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================
     CARNET QR DIGITAL
     ============================================================ -->
<section id="qr-feature">
  <div class="container">
    <div class="row g-5 align-items-center">
      <div class="col-lg-6 reveal">
        <span class="section-badge" style="background: rgba(196,140,255,0.15); color: var(--primary); border-color: rgba(196,140,255,0.3);">
          Identificación Inteligente
        </span>
        <h2 class="section-title qr-title">Historial clínico<br>al alcance de un QR</h2>
        <p class="section-subtitle qr-subtitle mb-5">
          En caso de emergencia o consulta externa, escanear la placa QR de tu mascota revela alertas críticas y datos de contacto de manera segura.
        </p>

        <div class="qr-steps">
          <div class="qr-step">
            <div class="step-number">1</div>
            <div class="step-content">
              <h4>Genera su código único</h4>
              <p>Al registrar a tu mascota en la clínica o desde tu portal de propietario, se emite un QR único intransferible.</p>
            </div>
          </div>
          <div class="qr-step">
            <div class="step-number">2</div>
            <div class="step-content">
              <h4>Escanea en segundos</h4>
              <p>Cualquier smartphone puede leer el código sin aplicaciones adicionales para acceder a la ficha de emergencia.</p>
            </div>
          </div>
          <div class="qr-step">
            <div class="step-number">3</div>
            <div class="step-content">
              <h4>Protege su bienestar</h4>
              <p>Muestra alergias críticas, grupo sanguíneo, fotografía y botón directo de contacto con la clínica y tutor.</p>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-6 d-flex justify-content-center reveal">
        <div class="phone-mockup">
          <div class="phone-notch"></div>
          <div class="phone-screen">
            <div class="phone-header">
              <div class="phone-avatar">PK</div>
              <div>
                <div class="phone-pet-name">Ficha Médica QR</div>
                <div class="phone-pet-breed">Vista Pública de Emergencia</div>
              </div>
            </div>
            <div class="phone-data-row">
              <span class="phone-data-label">Estado clínico</span>
              <span class="phone-data-value">Verificado</span>
            </div>
            <div class="phone-data-row">
              <span class="phone-data-label">Alertas médicas</span>
              <span class="phone-data-value" style="color: #FF6B9D;">Visibles al escanear</span>
            </div>
            <div class="phone-data-row">
              <span class="phone-data-label">Vacunación</span>
              <span class="phone-data-value" style="color: var(--gold);">Al día</span>
            </div>
            <div class="phone-qr-box">
              <div class="phone-qr-pattern"></div>
            </div>
            <div class="phone-pill">PetKardex QR · Acceso Rápido</div>
          </div>
          <div class="phone-glow"></div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================
     CONTACTO
     ============================================================ -->
<section id="contacto" style="background: var(--bg-light); padding: 6rem 0;">
  <div class="container">
    <div class="text-center reveal mb-5">
      <span class="section-badge">Contacto</span>
      <h2 class="section-title mx-auto" style="display:block;text-align:center;">Agenda o escríbenos</h2>
      <p class="section-subtitle mx-auto mt-2" style="text-align:center;">
        ¿Necesitas agendar una cita o consultar sobre hospitalización y estética? Estamos listos para ayudarte.
      </p>
    </div>

    <div class="row g-5">
      <div class="col-lg-7 reveal">
        <div class="contact-form">
          <h3 style="font-size:1.4rem;margin-bottom:1.8rem;color:var(--text-dark);">Envíanos un mensaje</h3>
          <form id="contactForm" novalidate>
            <div class="form-group">
              <label for="contactNombre">Nombre completo</label>
              <input type="text" id="contactNombre" name="nombre" class="form-control-pk" placeholder="Tu nombre completo" autocomplete="name" required />
            </div>
            <div class="form-group">
              <label for="contactEmail">Correo electrónico</label>
              <input type="email" id="contactEmail" name="email" class="form-control-pk" placeholder="tu@correo.com" autocomplete="email" required />
            </div>
            <div class="form-group">
              <label for="contactMensaje">Mensaje</label>
              <textarea id="contactMensaje" name="mensaje" class="form-control-pk" rows="5" placeholder="Cuéntanos en qué podemos ayudarte..." required></textarea>
            </div>
            <button type="submit" class="btn-primary-pk w-100 justify-content-center">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/>
              </svg>
              Enviar mensaje
            </button>
            <div id="formSuccess" class="form-success" role="alert" aria-live="polite">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
              </svg>
              ¡Mensaje enviado! Te responderemos a la brevedad.
            </div>
          </form>
        </div>
      </div>

      <div class="col-lg-5 reveal">
        <div class="contact-info-item">
          <div class="contact-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
            </svg>
          </div>
          <div>
            <h5>Dirección</h5>
            <p>Av. Principal 1234, Sede Central<br>Atención Clínica &amp; Urgencias</p>
          </div>
        </div>

        <div class="contact-info-item">
          <div class="contact-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 15.1a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.6 4.22h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L7.91 11a16 16 0 0 0 6 6l.92-.92a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
            </svg>
          </div>
          <div>
            <h5>Urgencias y Citas</h5>
            <p>+51 01 555 0199</p>
          </div>
        </div>

        <div class="contact-info-item">
          <div class="contact-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
            </svg>
          </div>
          <div>
            <h5>Correo Electrónico</h5>
            <p>contacto@petkardex.local</p>
          </div>
        </div>

        <div class="map-placeholder" role="img" aria-label="Ubicación de la clínica">
          <div class="map-placeholder-content">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
            </svg>
            <p>Clínica Veterinaria PetKardex</p>
            <p style="font-size:.78rem;opacity:.7;margin-top:.3rem;">Guardia médica 24 horas</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- ============================================================
     FOOTER PROFESIONAL
     ============================================================ -->
<footer id="footer">
  <div class="container">
    <div class="row g-5">
      <div class="col-lg-4 col-md-6">
        <div class="footer-brand">
          <svg width="32" height="32" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <ellipse cx="50" cy="62" rx="28" ry="24" fill="#C48CFF"/>
            <ellipse cx="22" cy="45" rx="10" ry="14" fill="#C48CFF" transform="rotate(-20 22 45)"/>
            <ellipse cx="78" cy="45" rx="10" ry="14" fill="#C48CFF" transform="rotate(20 78 45)"/>
            <ellipse cx="34" cy="28" rx="8" ry="11" fill="#C48CFF" transform="rotate(-10 34 28)"/>
            <ellipse cx="66" cy="28" rx="8" ry="11" fill="#C48CFF" transform="rotate(10 66 28)"/>
          </svg>
          <span class="brand-text">PetKardex</span>
        </div>
        <p class="footer-desc">
          Sistema integral de gestión clínica veterinaria y carnet médico digital accesible mediante códigos QR.
        </p>
        <div class="footer-social" role="list" aria-label="Redes sociales">
          <a href="#" class="social-link" role="listitem" aria-label="Facebook" title="Facebook">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
          </a>
          <a href="#" class="social-link" role="listitem" aria-label="Instagram" title="Instagram">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="2" width="20" height="20" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
          </a>
          <a href="#" class="social-link" role="listitem" aria-label="Twitter / X" title="Twitter / X">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>
          </a>
          <a href="#" class="social-link" role="listitem" aria-label="WhatsApp" title="WhatsApp">
            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 0 0 5.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 0 0-3.48-8.413z"/></svg>
          </a>
        </div>
      </div>

      <div class="col-lg-2 col-md-6 col-6">
        <h5 class="footer-heading">Navegación</h5>
        <ul class="footer-links">
          <li><a href="#inicio">Inicio</a></li>
          <li><a href="#nosotros">Nosotros</a></li>
          <li><a href="#servicios">Servicios</a></li>
          <li><a href="#qr-feature">Carnet QR</a></li>
          <li><a href="#contacto">Contacto</a></li>
        </ul>
      </div>

      <div class="col-lg-3 col-md-6 col-6">
        <h5 class="footer-heading">Políticas Legales</h5>
        <ul class="footer-links">
          <li><a href="#" data-bs-toggle="modal" data-bs-target="#modalTerminos">Términos y Condiciones</a></li>
          <li><a href="#" data-bs-toggle="modal" data-bs-target="#modalPrivacidad">Política de Privacidad</a></li>
          <li><a href="#" data-bs-toggle="modal" data-bs-target="#modalCookies">Política de Cookies</a></li>
          <li><a href="#" data-bs-toggle="modal" data-bs-target="#modalReembolso">Política de Reembolso</a></li>
        </ul>
      </div>

      <div class="col-lg-3 col-md-6">
        <h5 class="footer-heading">Acceso</h5>
        <ul class="footer-links">
          <li><a href="/app/login">Portal del Sistema</a></li>
          <li><a href="/app/forgot-password">Recuperar Contraseña</a></li>
          <li>Lun–Dom: Urgencias 24h</li>
        </ul>
      </div>
    </div>

    <hr class="footer-divider" />
    <p class="footer-bottom">
      &copy; <span id="copyrightYear"><?= date('Y') ?></span> <span>PetKardex</span>. Todos los derechos reservados.
    </p>
  </div>
</footer>

<?php require $rootDir . '/src/Views/pages/legal_modals.php'; ?>

<!-- Bootstrap 5.3 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<!-- Landing JS -->
<script src="/assets/js/landing.js"></script>
</body>
</html>


# 🏗️ PetKardex v2 — Arquitectura del Sistema

## Tabla de Contenidos
1. [Visión General](#1-visión-general)
2. [Diagrama de Capas MVC](#2-diagrama-de-capas-mvc)
3. [Flujo de una Petición HTTP](#3-flujo-de-una-petición-http)
4. [Patrón de Enrutamiento](#4-patrón-de-enrutamiento)
5. [Sistema de Autenticación y Sesiones](#5-sistema-de-autenticación-y-sesiones)
6. [Estructura de Permisos por Rol](#6-estructura-de-permisos-por-rol)
7. [Capa de Acceso a Datos](#7-capa-de-acceso-a-datos)
8. [Sistema de Vistas](#8-sistema-de-vistas)
9. [Helpers Transversales](#9-helpers-transversales)
10. [Infraestructura Docker](#10-infraestructura-docker)

---

## 1. Visión General

PetKardex v2 implementa el patrón **MVC (Model-View-Controller)** en PHP puro sin framework, con las siguientes decisiones arquitectónicas clave:

- **Front Controller**: Un único punto de entrada (`public/index.php`) recibe todas las peticiones HTTP.
- **Router propio**: `src/Core/Router.php` parsea la URI y hace dispatch al controlador/método correcto.
- **Namespaces PSR-4**: Autoloading mediante Composer, namespace raíz `PetKardex\`.
- **Singleton PDO**: `src/Config/Database.php` mantiene una única conexión a MySQL.
- **Sin ORM**: Consultas SQL directas con PDO preparado para máximo control y seguridad.
- **Tipado estricto**: Todos los archivos PHP declaran `declare(strict_types=1)`.

---

## 2. Diagrama de Capas MVC

```
┌─────────────────────────────────────────────────────────────────────────┐
│                           CAPA DE PRESENTACIÓN                          │
│                                                                         │
│   ┌──────────────────────────────────────────────────────────────────┐  │
│   │  VISTAS (src/Views/)                                              │  │
│   │                                                                   │  │
│   │  ┌─────────────┐  ┌──────────────┐  ┌──────────────────────┐    │  │
│   │  │  layout/    │  │  módulos/    │  │   errors/            │    │  │
│   │  │  header.php │  │  pacientes/  │  │   404.php            │    │  │
│   │  │  sidebar.php│  │  historial/  │  │   403.php            │    │  │
│   │  │  footer.php │  │  citas/ ...  │  │   500.php            │    │  │
│   │  └─────────────┘  └──────────────┘  └──────────────────────┘    │  │
│   │                                                                   │  │
│   │  Bootstrap 5.3 + Font Awesome 6 + JS vanilla                     │  │
│   └──────────────────────────────────────────────────────────────────┘  │
└───────────────────────────────┬─────────────────────────────────────────┘
                                │ include/render
┌───────────────────────────────▼─────────────────────────────────────────┐
│                         CAPA DE CONTROL                                  │
│                                                                         │
│   ┌──────────────────────────────────────────────────────────────────┐  │
│   │  CONTROLADORES (src/Controllers/)                                 │  │
│   │                                                                   │  │
│   │  AuthController      → Login, logout, recuperar contraseña       │  │
│   │  DashboardController → Panel según rol                           │  │
│   │  PacienteController  → CRUD mascotas + QR                        │  │
│   │  HistorialController → Consultas médicas + PDF                   │  │
│   │  HospController      → Hospitalización + signos vitales          │  │
│   │  CitaController      → Agenda + recordatorios                    │  │
│   │  VacunaController    → Vacunaciones + alertas                    │  │
│   │  InventarioController→ Stock + movimientos                       │  │
│   │  VentaController     → POS + facturación                         │  │
│   │  GroomController     → Estética canina                           │  │
│   │  QrController        → Portal público QR (sin auth)              │  │
│   │  PdfController       → Generación y descarga de PDFs             │  │
│   │  AdminController     → Usuarios, roles, configuración            │  │
│   └──────────────────────────────────────────────────────────────────┘  │
└───────────────────────────────┬─────────────────────────────────────────┘
                                │ consulta/manipula
┌───────────────────────────────▼─────────────────────────────────────────┐
│                          CAPA DE MODELOS                                 │
│                                                                         │
│   ┌──────────────────────────────────────────────────────────────────┐  │
│   │  MODELOS (src/Models/)                                            │  │
│   │                                                                   │  │
│   │  Usuario.php         Paciente.php       Propietario.php          │  │
│   │  HistorialClinico.php  Hospitalizacion.php  Cita.php             │  │
│   │  Vacunacion.php      Inventario.php     Venta.php                │  │
│   │  Grooming.php        Cirugia.php                                 │  │
│   │                                                                   │  │
│   │  Todos heredan de BaseModel o usan Database::getInstance()       │  │
│   └──────────────────────────────────────────────────────────────────┘  │
└───────────────────────────────┬─────────────────────────────────────────┘
                                │ PDO preparado
┌───────────────────────────────▼─────────────────────────────────────────┐
│                       CAPA DE INFRAESTRUCTURA                            │
│                                                                         │
│   ┌───────────────┐   ┌──────────────┐   ┌────────────────────────┐   │
│   │   Database    │   │    Config    │   │       Helpers          │   │
│   │  (Singleton   │   │  (Variables  │   │  Auth, Csrf, QrHelper  │   │
│   │   PDO/MySQL)  │   │   entorno)   │   │  PdfHelper, Mailer     │   │
│   └───────────────┘   └──────────────┘   └────────────────────────┘   │
└───────────────────────────────┬─────────────────────────────────────────┘
                                │
                    ┌───────────▼───────────┐
                    │   MySQL 8.0 (Docker)  │
                    │   charset: utf8mb4    │
                    │   20+ tablas          │
                    └───────────────────────┘
```

---

## 3. Flujo de una Petición HTTP

```
                    ┌──────────────────────────────────────┐
                    │      Navegador / Cliente HTTP        │
                    │   GET /app/paciente/42               │
                    └─────────────────┬────────────────────┘
                                      │
                                      ▼
                    ┌──────────────────────────────────────┐
                    │     Apache 2.4 + mod_rewrite         │
                    │   public/.htaccess:                   │
                    │   RewriteRule ^.*$ index.php [L]     │
                    └─────────────────┬────────────────────┘
                                      │
                                      ▼
                    ┌──────────────────────────────────────┐
                    │    public/index.php (Front Ctrl)     │
                    │   1. require vendor/autoload.php     │
                    │   2. Config::initialize()            │
                    │      └─ timezone, session, dirs      │
                    │   3. new Router()                    │
                    │   4. Registra todas las rutas        │
                    │   5. $router->dispatch()             │
                    └─────────────────┬────────────────────┘
                                      │
                                      ▼
                    ┌──────────────────────────────────────┐
                    │      src/Core/Router::dispatch()     │
                    │   1. Lee METHOD + URI                │
                    │   2. Busca coincidencia en $routes   │
                    │      (regex con params nombrados)    │
                    │   3. ¿Ruta protegida?                │
                    └────────┬────────────────┬────────────┘
                             │ NO auth        │ Sí auth
                             ▼               ▼
                    ┌────────────┐  ┌──────────────────────┐
                    │ 302 →login │  │  isAuthenticated()?  │
                    └────────────┘  │  Verifica $_SESSION  │
                                    └──────────┬───────────┘
                                               │ OK
                                               ▼
                    ┌──────────────────────────────────────┐
                    │  callHandler([Controller, 'method']) │
                    │   new PacienteController()           │
                    │   ->show(42)                         │
                    └─────────────────┬────────────────────┘
                                      │
                                      ▼
                    ┌──────────────────────────────────────┐
                    │       PacienteController::show()     │
                    │   1. Auth::requirePermission('pacientes.ver')
                    │   2. $paciente = Paciente::findById(42)
                    │   3. $historial = HistorialClinico::byPaciente(42)
                    │   4. render('pacientes/show.php', $data)
                    └─────────────────┬────────────────────┘
                                      │
                                      ▼
                    ┌──────────────────────────────────────┐
                    │          Modelo: Paciente.php        │
                    │   Database::getInstance()->fetchOne( │
                    │     "SELECT * FROM pacientes        │
                    │      WHERE id = ?", [$id]            │
                    │   )                                  │
                    └─────────────────┬────────────────────┘
                                      │
                                      ▼
                    ┌──────────────────────────────────────┐
                    │          Vista: show.php             │
                    │   include layout/header.php          │
                    │   include layout/sidebar.php         │
                    │   <main>... datos del paciente ...</main>
                    │   include layout/footer.php          │
                    └─────────────────┬────────────────────┘
                                      │
                                      ▼
                    ┌──────────────────────────────────────┐
                    │      Respuesta HTML al Browser       │
                    │      HTTP 200 OK                     │
                    └──────────────────────────────────────┘
```

---

## 4. Patrón de Enrutamiento

### Registro de Rutas (en `public/index.php`)

```php
$router = new Router();

// Rutas públicas (sin auth)
$router->get('/',                 [LandingController::class, 'index']);
$router->get('/qr/{code}',        [QrController::class, 'show']);
$router->get('/app/login',        [AuthController::class, 'loginForm']);
$router->post('/app/login',       [AuthController::class, 'login']);
$router->get('/app/logout',       [AuthController::class, 'logout']);

// Rutas protegidas (requieren sesión activa)
$router->get('/app/dashboard',    [DashboardController::class, 'index'],    protected: true);
$router->get('/app/pacientes',    [PacienteController::class, 'index'],     protected: true);
$router->get('/app/paciente/{id}',[PacienteController::class, 'show'],      protected: true);
$router->post('/app/paciente',    [PacienteController::class, 'store'],     protected: true);
// ... etc.

$router->dispatch();
```

### Sintaxis de Parámetros de Ruta

Los parámetros dinámicos se definen entre llaves `{nombre}` y se convierten a grupos de captura nombrados en la regex:

```
Ruta registrada:  /app/paciente/{id}
Regex generada:   #^/app/paciente/(?P<id>[^/]+)$#
URI entrante:     /app/paciente/42
Parámetros:       ['id' => '42']
```

Los parámetros se pasan como argumentos al método del controlador en el orden en que aparecen en la URL.

### Tabla de Convenciones de Rutas

| Patrón                       | Acción             | Descripción                        |
|------------------------------|--------------------|------------------------------------|
| `GET /app/{módulo}`          | `index()`          | Listar todos los recursos           |
| `GET /app/{módulo}/{id}`     | `show($id)`        | Ver detalle de un recurso           |
| `GET /app/{módulo}/nuevo`    | `create()`         | Formulario de creación              |
| `POST /app/{módulo}`         | `store()`          | Guardar nuevo recurso               |
| `GET /app/{módulo}/{id}/editar`| `edit($id)`      | Formulario de edición               |
| `POST /app/{módulo}/{id}`    | `update($id)`      | Actualizar recurso existente        |
| `POST /app/{módulo}/{id}/eliminar`| `destroy($id)` | Eliminar recurso                  |

---

## 5. Sistema de Autenticación y Sesiones

### Inicialización de Sesión

```php
// En Config::initialize():
ini_set('session.name',            'PKDX_SESSION');
ini_set('session.gc_maxlifetime',  7200);    // 2 horas
ini_set('session.cookie_httponly', '1');     // No accesible por JS
ini_set('session.cookie_samesite', 'Strict'); // Anti-CSRF
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
```

### Estructura de la Sesión Activa

```php
$_SESSION = [
    'user_id'     => 7,                    // ID en tabla usuarios
    'nombre'      => 'Dr. Juan Pérez',     // Nombre completo
    'email'       => 'juan@clinica.com',   // Email
    'rol_id'      => 2,                    // ID del rol
    'rol_slug'    => 'veterinario',        // Slug del rol
    'permisos'    => [                     // JSON decodificado de roles.permisos
        'pacientes.ver'       => true,
        'pacientes.crear'     => true,
        'historial.ver'       => true,
        'historial.crear'     => true,
        'admin.usuarios'      => false,
        // ...
    ],
    'ultimo_login' => '2024-11-11 10:30:00',
    'csrf_token'  => 'a3f8b2...',          // Token CSRF de sesión
];
```

### Flujo de Login

```
POST /app/login
  ├─ Sanitizar email (filter_var FILTER_SANITIZE_EMAIL)
  ├─ Buscar usuario en BD: SELECT * FROM usuarios WHERE email = ? AND activo = 1
  ├─ ¿Encontrado? → password_verify($inputPwd, $row['password_hash'])
  │     ├─ INCORRECTO → incrementar contador intentos; ¿>=5? → bloquear 15 min
  │     └─ CORRECTO  →
  │           ├─ Regenerar ID de sesión (session_regenerate_id(true))
  │           ├─ Cargar permisos del rol (json_decode($rol['permisos']))
  │           ├─ Poblar $_SESSION (ver estructura arriba)
  │           ├─ UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?
  │           └─ Redirect a $_SESSION['redirect_after_login'] ?? '/app/dashboard'
  └─ ¿No encontrado? → misma respuesta que password incorrecto (evitar enumeración)
```

### Verificación de Permisos en Controladores

```php
// En cualquier controlador:
Auth::requirePermission('historial.crear');
// Si $_SESSION['permisos']['historial.crear'] !== true → HTTP 403
```

### Protección CSRF

```php
// En vistas (formularios POST):
<input type="hidden" name="csrf_token" value="<?= Csrf::token() ?>">

// En controladores que procesan POST:
Csrf::validate($_POST['csrf_token'] ?? '');
// Si no coincide con $_SESSION['csrf_token'] → HTTP 403
```

---

## 6. Estructura de Permisos por Rol

Los permisos se almacenan como JSON en la columna `roles.permisos` y se cargan en la sesión al hacer login.

### Convención de Nombres

`{módulo}.{acción}` — Ejemplos: `pacientes.ver`, `historial.crear`, `admin.usuarios`

### Tabla de Permisos por Rol

| Permiso                    | superadmin | veterinario | recepcionista | groomer | guardia | propietario | emergencia |
|----------------------------|:----------:|:-----------:|:-------------:|:-------:|:-------:|:-----------:|:----------:|
| **Dashboard**              |            |             |               |         |         |             |            |
| `dashboard.ver`            | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| **Pacientes**              |            |             |               |         |         |             |            |
| `pacientes.ver`            | ✅ | ✅ | ✅ | ✅ | ✅ | ✅* | ✅ |
| `pacientes.crear`          | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `pacientes.editar`         | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `pacientes.eliminar`       | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Historial Clínico**      |            |             |               |         |         |             |            |
| `historial.ver`            | ✅ | ✅ | ❌ | ❌ | ❌ | ✅* | ✅ |
| `historial.crear`          | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `historial.editar`         | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Hospitalización**        |            |             |               |         |         |             |            |
| `hospitalizacion.ver`      | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `hospitalizacion.gestionar`| ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Citas**                  |            |             |               |         |         |             |            |
| `citas.ver`                | ✅ | ✅ | ✅ | ✅ | ✅ | ✅* | ❌ |
| `citas.crear`              | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| `citas.editar`             | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| `citas.cancelar`           | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Vacunaciones**           |            |             |               |         |         |             |            |
| `vacunas.ver`              | ✅ | ✅ | ✅ | ❌ | ❌ | ✅* | ✅ |
| `vacunas.registrar`        | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Inventario**             |            |             |               |         |         |             |            |
| `inventario.ver`           | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `inventario.gestionar`     | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ |
| **Ventas / POS**           |            |             |               |         |         |             |            |
| `ventas.ver`               | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| `ventas.crear`             | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ | ❌ |
| **Grooming**               |            |             |               |         |         |             |            |
| `grooming.ver`             | ✅ | ✅ | ✅ | ✅ | ❌ | ❌ | ❌ |
| `grooming.gestionar`       | ✅ | ❌ | ✅ | ✅ | ❌ | ❌ | ❌ |
| **Administración**         |            |             |               |         |         |             |            |
| `admin.usuarios`           | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `admin.roles`              | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `admin.config`             | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |
| `admin.reportes`           | ✅ | ❌ | ❌ | ❌ | ❌ | ❌ | ❌ |

> ✅* = Solo sobre sus propias mascotas/datos

---

## 7. Capa de Acceso a Datos

### Singleton Database

```php
// Obtener instancia (siempre la misma conexión PDO)
$db = Database::getInstance();

// Consulta con parámetros (siempre preparada, nunca interpolada)
$paciente = $db->fetchOne(
    "SELECT p.*, e.nombre AS especie, r.nombre AS raza
     FROM pacientes p
     JOIN especies e ON e.id = p.especie_id
     LEFT JOIN razas r ON r.id = p.raza_id
     WHERE p.id = ? AND p.activo = 1",
    [$id]
);

// INSERT con transacción
$db->transaction(function (Database $db) use ($data) {
    $db->execute(
        "INSERT INTO pacientes (nombre, propietario_id, especie_id, qr_code)
         VALUES (?, ?, ?, ?)",
        [$data['nombre'], $data['propietario_id'], $data['especie_id'], $data['qr_code']]
    );
    return $db->lastInsertId();
});
```

### Métodos Disponibles en Database

| Método                        | Retorna          | Descripción                                       |
|-------------------------------|------------------|---------------------------------------------------|
| `fetchAll($sql, $params)`     | `array`          | Todas las filas como array de arrays asociativos  |
| `fetchOne($sql, $params)`     | `?array`         | Primera fila, o null si no hay resultados         |
| `execute($sql, $params)`      | `int`            | Filas afectadas (INSERT/UPDATE/DELETE)            |
| `lastInsertId()`              | `int`            | ID del último INSERT                              |
| `beginTransaction()`          | `bool`           | Inicia transacción                                |
| `commit()`                    | `bool`           | Confirma transacción                              |
| `rollback()`                  | `bool`           | Revierte transacción                              |
| `transaction(callable)`       | `mixed`          | Ejecuta callback en transacción con rollback auto |

---

## 8. Sistema de Vistas

Las vistas son archivos PHP que mezclan HTML con PHP mínimo (solo display, sin lógica de negocio).

### Convención de Renderizado

Los controladores renderizan vistas usando una función helper `render()`:

```php
// En un controlador:
render('pacientes/index', [
    'pacientes' => $pacientes,
    'titulo'    => 'Lista de Pacientes',
]);
```

La función `render()` hace `extract($data)` y luego incluye el layout y la vista:

```php
function render(string $view, array $data = []): void {
    extract($data);
    include SRC_PATH . '/Views/layout/header.php';
    include SRC_PATH . '/Views/layout/sidebar.php';
    include SRC_PATH . '/Views/' . $view . '.php';
    include SRC_PATH . '/Views/layout/footer.php';
}
```

### Escape de Salida (Anti-XSS)

```php
<!-- CORRECTO — siempre escapar variables en vistas -->
<td><?= htmlspecialchars($paciente['nombre'], ENT_QUOTES, 'UTF-8') ?></td>

<!-- INCORRECTO — nunca mostrar variables sin escapar -->
<td><?= $paciente['nombre'] ?></td>
```

---

## 9. Helpers Transversales

| Helper          | Archivo                   | Responsabilidad                                           |
|-----------------|---------------------------|-----------------------------------------------------------|
| `Auth`          | `src/Helpers/Auth.php`    | `check()`, `user()`, `hasPermission()`, `requirePermission()` |
| `Csrf`          | `src/Helpers/Csrf.php`    | `token()` (genera/lee de sesión), `validate($token)`      |
| `QrHelper`      | `src/Helpers/QrHelper.php`| `generate($code)` → crea PNG en disco; `getUrl($code)`    |
| `PdfHelper`     | `src/Helpers/PdfHelper.php`| Wrapper TCPDF/FPDF para recetas, certificados, reportes  |
| `Mailer`        | `src/Helpers/Mailer.php`  | Wrapper PHPMailer; `send($to, $subject, $body, $attach)`  |

---

## 10. Infraestructura Docker

```
┌───────────────────────────────────────────────────────────┐
│                    Host (tu máquina)                      │
│                                                           │
│  ┌────────────────────────────────────────────────────┐  │
│  │              Docker Network: petkardex_net          │  │
│  │                                                    │  │
│  │  ┌─────────────┐    ┌─────────────┐               │  │
│  │  │   petkardex │    │  petkardex  │               │  │
│  │  │     _app    │    │    _db      │               │  │
│  │  │             │    │             │               │  │
│  │  │ PHP 8.2     │◄──►│ MySQL 8.0  │               │  │
│  │  │ Apache 2.4  │    │             │               │  │
│  │  │ :80 → 8080  │    │ :3306       │               │  │
│  │  └─────────────┘    └──────┬──────┘               │  │
│  │                            │ Volume: db_data        │  │
│  │  ┌─────────────┐    ┌──────▼──────┐               │  │
│  │  │  petkardex  │    │  petkardex  │               │  │
│  │  │  _mailhog   │    │ _phpmyadmin │               │  │
│  │  │             │    │             │               │  │
│  │  │ SMTP: 1025  │    │ :80 → 8081  │               │  │
│  │  │ UI: 8025    │    │             │               │  │
│  │  └─────────────┘    └─────────────┘               │  │
│  └────────────────────────────────────────────────────┘  │
│                                                           │
│  Volúmenes:  db_data (MySQL data)                        │
│              uploads (archivos subidos)                   │
└───────────────────────────────────────────────────────────┘
```

| Servicio      | Imagen Base           | Puerto Host | Puerto Contenedor | Propósito                    |
|---------------|-----------------------|-------------|-------------------|------------------------------|
| `app`         | `php:8.2-apache`      | 8080        | 80                | Aplicación PHP principal     |
| `db`          | `mysql:8.0`           | 3306        | 3306              | Base de datos MySQL          |
| `phpmyadmin`  | `phpmyadmin:latest`   | 8081        | 80                | Admin gráfico de BD (dev)    |
| `mailhog`     | `mailhog/mailhog`     | 8025, 1025  | 8025, 1025        | SMTP ficticio para pruebas   |

> **Healthcheck MySQL**: El servicio `app` y `phpmyadmin` tienen `depends_on: db: condition: service_healthy`. MySQL reporta healthy cuando `mysqladmin ping` responde correctamente, lo cual ocurre ~30s después de arrancar.

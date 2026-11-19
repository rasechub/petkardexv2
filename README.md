# 🐾 PetKardex_v2 — ERP Clínico Veterinario e Historial Digital QR

> ### 📜 Autoría y Derechos de Autor (Copyright)
> **© 2026 PetKardex — Desarrollado por CESAR AQUINO ANGLAS - VI B NOCHE**  
> *(VI Ciclo, Turno Noche — IESTP Santiago Antúnez de Mayolo - IESTP SAM)*.  
> **Todos los derechos reservados.** Prohibida su copia, distribución, modificación o uso comercial/académico no autorizado. Consulte el archivo [`LICENSE`](LICENSE) para más detalles.

![PHP](https://img.shields.io/badge/PHP-8.2+-777BB4?style=flat-square)
![MySQL](https://img.shields.io/badge/MySQL-8.0-4479A1?style=flat-square)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?style=flat-square)
![Docker](https://img.shields.io/badge/Docker-Compose-2496ED?style=flat-square)
![License](https://img.shields.io/badge/Licencia-Todos_los_derechos_reservados-crimson?style=flat-square)
![Author](https://img.shields.io/badge/Autor-CESAR_AQUINO_ANGLAS-purple?style=flat-square)

**PetKardex_v2** es un sistema integral de gestión de clínicas veterinarias (ERP) construido en **PHP 8+ (arquitectura MVC nativa con PDO preparado)** y **MySQL 8.0**, listo para desplegarse con **Docker**. Combina la operación clínica, logística y financiera de la clínica con un sistema de identificación e historial médico digital accesible mediante **códigos QR**.

---

## 📋 Módulos Principales

- **🌐 Landing Page Pública (`public/index.php`):** Portada moderna en Bootstrap 5 con paleta púrpura `#C48CFF` y negro, barra de navegación sticky, slider hero, secciones *Nosotros*, *Servicios*, *Carnet QR*, *Contacto* y modales legales (*Términos y Condiciones*, *Privacidad*, *Cookies* y *Reembolso*).
- **📱 Módulo de Código QR (`QRController`):**
  - **Perfil Público de Emergencia (`/qr/{uuid}`):** Vista *mobile-first* sin login con fotografía, alertas médicas críticas y botones directos **Llamar al Dueño** (`tel:`) y **WhatsApp** (`wa.me/`).
  - **Credencial QR del Propietario (`/app/qr`):** Visualización y descarga en PNG para impresión o grabado en placa.
  - **Escáner Interno de Clínica (`/app/qr/scanner`):** Lectura por cámara/webcam mediante `html5-qrcode` que redirige directamente al Kardex Médico Interno del paciente.
- **🩺 ERP Clínico — Kardex Médico (`KardexController`):**
  - Pestañas de *Resumen General*, *Vacunas y Desparasitaciones* y *Consultas y Recetas*.
  - **Regla legal de inmutabilidad:** Ninguna consulta o vacunación puede modificarse ni eliminarse pasadas 24 horas desde su creación.
  - **Recetas con descuento automático:** Al recetar productos de farmacia se llama transaccionalmente a `Inventario::descontarStock()`.
  - **Certificados PDF (`/app/kardex/certificado/{id}`):** Generación con membrete de la clínica, historial de vacunas recientes y sello/firma mediante Dompdf/TCPDF.
- **📦 Inventario y Caja / POS (`InventarioController`, `CajaController`):**
  - CRUD completo de productos, alertas de stock mínimo en rojo, ajustes manuales justificados y log auditable de movimientos (`movimientos_inventario`).
  - Punto de Venta (POS) con carrito en tiempo real, cálculo de IGV/IVA y vuelto, y **liquidación de cuentas médicas** buscando por DNI del propietario o UUID QR de la mascota.
- **🏥 Hospitalización y Triage (`HospitalizacionController`):**
  - Mapa visual de jaulas/caniles (`Ocupada`, `Libre`, `En Limpieza`).
  - Ingreso rápido de emergencia (Triage), bitácora por turnos con signos vitales y administración de medicamentos (cargados automáticamente a la cuenta POS) y flujo de Alta Médica.
- **📅 Agenda Interactiva (`AgendaController`):** Calendario mensual/semanal con **FullCalendar**, códigos de color por servicio (*Médica*, *Vacunación*, *Estética*, *Emergencia*) y filtrado por rol.
- **✂️ Estética y Alertas de Salud (`EsteticaController`):** Agenda diaria exclusiva para el estilista y emisión de alertas médicas internas que aparecen destacadas en el Dashboard del Veterinario y en el Kardex del paciente.

---

## 📁 Estructura del Proyecto

```text
Petkardex_v2/
├── .dockerignore
├── .env.example                    # Plantilla de variables de entorno
├── composer.json                   # Dependencias PHP (endroid/qr-code, dompdf, tcpdf, phpmailer)
├── docker-compose.yml              # Orquestación: app, db (MySQL 8.0), phpmyadmin, mailhog
├── README.md
│
├── database/                       # Mapeado a /docker-entrypoint-initdb.d/ en MySQL
│   ├── schema.sql                  # 25 tablas relacionales InnoDB utf8mb4 + roles base
│   └── seeds.sql                   # Catálogos iniciales (especies, razas, vacunas, config) SIN usuarios
│
├── docker/
│   ├── Dockerfile                  # Imagen PHP 8.2-Apache con PDO, GD, Zip, Intl, OPcache y Composer
│   ├── apache/
│   │   └── vhost.conf              # VirtualHost con DocumentRoot en /var/www/html/public
│   └── php/
│       └── php.ini                 # Parámetros PHP y OPcache
│
├── docs/
│   └── ARCHITECTURE.md             # Documentación técnica de arquitectura
│
├── public/                         # DocumentRoot de Apache
│   ├── .htaccess                   # mod_rewrite hacia index.php y cabeceras de seguridad
│   ├── index.php                   # Front Controller (/app/*, /qr/*) + Landing Page (/ y /home)
│   ├── landing.html                # Plantilla estática de respaldo de landing
│   ├── assets/
│   │   ├── css/landing.css
│   │   └── js/landing.js
│   └── uploads/
│       └── qr/                     # Imágenes QR generadas
│
├── scripts/
│   └── create_admin.php            # Script CLI seguro para crear el primer SuperAdmin
│
└── src/
    ├── Config/
    │   ├── config.php              # Carga de entorno, zona horaria y sesiones seguras
    │   └── database.php            # Singleton PDO con consultas preparadas
    ├── Controllers/
    │   ├── AgendaController.php
    │   ├── AuthController.php
    │   ├── CajaController.php
    │   ├── DashboardController.php
    │   ├── EsteticaController.php
    │   ├── HospitalizacionController.php
    │   ├── InventarioController.php
    │   ├── KardexController.php
    │   ├── MascotaController.php
    │   ├── PacienteController.php
    │   └── QRController.php
    ├── Core/
    │   ├── Auth.php                # Autenticación bcrypt, sesiones, ACL y auditoría
    │   ├── Controller.php          # Clase base (render, JSON, flash, CSRF, requireAuth)
    │   ├── Model.php               # CRUD base con validación de identificadores SQL
    │   └── Router.php              # Enrutador público/privado con middleware de roles
    ├── Helpers/
    │   ├── QRGenerator.php
    │   └── Security.php            # Sanitización, validación, CSRF, rate-limit y CSP
    ├── Models/
    │   ├── Agenda.php
    │   ├── Estetica.php
    │   ├── Hospitalizacion.php
    │   ├── Inventario.php
    │   ├── Kardex.php
    │   └── Venta.php
    └── Views/
        ├── agenda/index.php
        ├── auth/                   # login.php, forgot-password.php, reset-password.php
        ├── caja/                   # pos.php, recibo.php
        ├── clinica/                # kardex.php, consulta_nueva.php, vacuna_nueva.php, escaner.php
        ├── dashboard/index.php
        ├── errors/                 # 403.php, 404.php
        ├── estetica/dashboard.php
        ├── hospitalizacion/        # index.php, detalle.php
        ├── inventario/             # index.php, movimientos.php
        ├── layouts/app.php         # Sidebar condicional por rol + Topbar responsivo
        ├── mascotas/crear.php      # Formulario dedicado de registro de mascota + QR
        ├── pacientes/index.php     # Directorio de pacientes / mascotas con buscador
        ├── pages/legal_modals.php
        ├── propietario/qr.php
        └── public/emergency_profile.php
```

---

## 🚀 Despliegue con Docker desde Cero

### 1. Prerrequisitos
- Docker Desktop / Docker Engine 24+
- Docker Compose v2.20+

### 2. Preparar variables de entorno y levantar los contenedores

```bash
# 1. Clonar o ubicarse en la carpeta del proyecto
cd Petkardex_v2

# 2. Copiar plantilla de entorno y ajustar valores según necesidad
cp .env.example .env

# 3. Construir imágenes y levantar los servicios en segundo plano
docker-compose up -d --build
```

> **Inicialización automática de la base de datos:**  
> El archivo [docker-compose.yml](docker-compose.yml) monta `./database:/docker-entrypoint-initdb.d:ro` en el servicio `db` (MySQL 8.0). Al iniciar el contenedor por primera vez con un volumen limpio, MySQL ejecuta automáticamente `schema.sql` seguido de `seeds.sql` cargando las 25 tablas, los 7 roles del sistema, especies, razas, categorías de inventario, catálogo de vacunas, configuración inicial en Soles Peruanos (`S/.`) y los 6 usuarios de prueba por rol.

### 3. Instalar dependencias PHP con Composer (opcional / recomendado para PDFs y QR nativos)

```bash
docker exec -it petkardex_app composer install --no-dev --optimize-autoloader
```

### 4. Verificar que los contenedores estén activos y saludables

```bash
docker-compose ps
```

Servicios expuestos localmente:
| Servicio | Contenedor | URL de Acceso |
|---|---|---|
| Aplicación Web (Apache + PHP 8.2) | `petkardex_app` | http://localhost:8080 |
| Portal de Inicio de Sesión | `petkardex_app` | http://localhost:8080/app/login |
| phpMyAdmin | `petkardex_phpmyadmin` | http://localhost:8081 |
| MailHog (SMTP dev Web UI) | `petkardex_mailhog` | http://localhost:8025 |

---

## 🧪 Usuarios de Prueba por Rol (`seeds.sql`)

Para que cualquier desarrollador pueda probar inmediatamente cada interfaz tras levantar los contenedores con Docker, `database/seeds.sql` inserta un usuario de prueba por cada uno de los 6 roles internos (con contraseñas hasheadas en MySQL mediante `bcrypt`):

| Rol | Correo Electrónico | Contraseña | Interfaz / Módulos |
|---|---|---|---|
| **Super Administrador** (`superadmin`) | `superadmin@petkardex.local` | `Admin2026!` | Dashboard KPIs reales, Pacientes, Agenda, Hospitalización, Estética, Inventario, Caja POS, Escáner QR |
| **Médico Veterinario** (`veterinario`) | `veterinario@petkardex.local` | `Vet2026!` | Pacientes, Kardex clínico (consultas, recetas, vacunas, PDF), Escáner QR, Agenda, Hospitalización |
| **Veterinario de Guardia** (`guardia`) | `guardia@petkardex.local` | `Guardia2026!` | Mapa de jaulas, Triage de emergencias, Bitácora de signos vitales, Escáner QR |
| **Recepcionista / Cajero** (`recepcionista`) | `recepcionista@petkardex.local` | `Caja2026!` | Pacientes, Agenda general, Punto de Venta (`S/.`), Liquidación de cuentas, Inventario básico |
| **Estilista / Groomer** (`groomer`) | `estilista@petkardex.local` | `Estetica2026!` | Agenda estética del día y emisión de alertas de salud hacia veterinarios/Kardex |
| **Propietario / Dueño** (`propietario`) | `propietario@petkardex.local` | `Dueno2026!` | Portal de solo lectura: resumen, descarga de Carnet QR, historial inmutable y próximas citas |

> ⚠️ **Importante para producción:** Cambia o elimina estos usuarios de prueba antes de exponer el sistema en un entorno productivo y crea tu administrador definitivo con `scripts/create_admin.php`.

---

## 🔐 Crear un SuperAdmin Adicional (Script CLI Seguro)

Por seguridad, el esquema SQL **no incluye usuarios pre-cargados ni contraseñas quemadas en el código**. Para crear al primer Administrador del Sistema (`superadmin`), ejecuta el script interactivo dentro del contenedor PHP:

```bash
docker exec -it petkardex_app php scripts/create_admin.php
```

El asistente solicitará por consola:
1. **Nombre**
2. **Apellido**
3. **Correo electrónico**
4. **Contraseña** (entrada oculta en terminal; mínimo 8 caracteres, al menos una letra y un número) y su confirmación.

El script verifica que el rol `superadmin` exista en `roles`, genera el hash seguro con `password_hash(..., PASSWORD_BCRYPT, ['cost' => 12])`, inserta el usuario mediante consulta preparada PDO y registra el alta en `audit_log`.

Tras completar este paso, inicia sesión en **http://localhost:8080/app/login** y crea desde el panel los demás usuarios del equipo clínico (Veterinarios, Guardias, Recepcionistas, Estilistas y Propietarios).

---

## 👥 Control de Acceso por Roles (ACL)

| Rol (`slug`) | Permisos y Módulos |
|---|---|
| `superadmin` | Acceso global al ERP, métricas, usuarios, inventario, POS, hospitalización, agenda y Kardex |
| `veterinario` | Escáner QR, Kardex clínico (crear consultas/recetas/vacunas, certificados PDF), hospitalización y agenda |
| `guardia` | Mapa de jaulas, Triage de emergencias, bitácora nocturna de signos vitales y escáner QR |
| `recepcionista` | Agenda general, punto de venta (Caja POS), liquidación de cuentas por DNI/QR e inventario básico |
| `groomer` | Agenda estética del día actual y emisión de alertas médicas hacia veterinarios y Kardex |
| `propietario` | Solo lectura: dashboard resumen, historial médico inmutable, descarga de QR y sus próximas citas |
| `emergencia` | Vista pública `/qr/{uuid}` sin login (foto, alertas médicas críticas, botones `tel:` y `wa.me/`) |

---

## 🏛️ Derechos de Autor y Propiedad Intelectual

```text
===================================================================================
© 2026 PetKardex — Desarrollado por CESAR AQUINO ANGLAS - VI B NOCHE
(VI Ciclo, Turno Noche - IESTP Santiago Antúnez de Mayolo - IESTP SAM)
Todos los derechos reservados.
===================================================================================
```

Este proyecto de software, su arquitectura, base de datos relacional, código fuente y diseño son obra y propiedad intelectual exclusiva de **CESAR AQUINO ANGLAS - VI B NOCHE** (IESTP Santiago Antúnez de Mayolo, 2026).

Queda estrictamente prohibida la copia, reproducción parcial o total, distribución, ingeniería inversa o explotación con fines comerciales o académicos sin la autorización previa y por escrito del autor titular. Para más información, consulte el archivo [LICENSE](LICENSE).

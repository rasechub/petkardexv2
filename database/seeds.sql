-- ============================================================
-- PetKardex v2 - ERP Veterinario
-- Seeds SQL — Datos iniciales y usuarios de prueba por rol
-- ============================================================

USE petkardex;

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- SEED 1: ROLES (7 roles del sistema con permisos JSON)
-- ============================================================
-- Los roles se insertan en schema.sql para garantizar el orden
-- de carga. Este seed los re-inserta con INSERT IGNORE por
-- si se ejecuta seeds.sql de forma independiente.

INSERT INTO `roles` (`id`, `nombre`, `slug`, `descripcion`, `permisos`, `activo`) VALUES
(1, 'Super Administrador', 'superadmin',
 'Acceso total al sistema sin restricciones',
 JSON_OBJECT(
     'dashboard',         JSON_ARRAY('ver'),
     'usuarios',          JSON_ARRAY('ver','crear','editar','eliminar'),
     'roles',             JSON_ARRAY('ver','crear','editar','eliminar'),
     'pacientes',         JSON_ARRAY('ver','crear','editar','eliminar'),
     'propietarios',      JSON_ARRAY('ver','crear','editar','eliminar'),
     'historial',         JSON_ARRAY('ver','crear','editar','eliminar'),
     'vacunaciones',      JSON_ARRAY('ver','crear','editar','eliminar'),
     'recetas',           JSON_ARRAY('ver','crear','editar','eliminar'),
     'inventario',        JSON_ARRAY('ver','crear','editar','eliminar'),
     'ventas',            JSON_ARRAY('ver','crear','editar','eliminar','anular'),
     'citas',             JSON_ARRAY('ver','crear','editar','eliminar'),
     'hospitalizaciones', JSON_ARRAY('ver','crear','editar','eliminar'),
     'estetica',          JSON_ARRAY('ver','crear','editar','eliminar'),
     'reportes',          JSON_ARRAY('ver','exportar'),
     'configuracion',     JSON_ARRAY('ver','editar'),
     'audit_log',         JSON_ARRAY('ver')
 ), 1),
(2, 'Veterinario', 'veterinario',
 'Médico veterinario con acceso a historial clínico, consultas y procedimientos',
 JSON_OBJECT(
     'dashboard',         JSON_ARRAY('ver'),
     'pacientes',         JSON_ARRAY('ver','crear','editar'),
     'propietarios',      JSON_ARRAY('ver','crear','editar'),
     'historial',         JSON_ARRAY('ver','crear','editar'),
     'vacunaciones',      JSON_ARRAY('ver','crear','editar'),
     'recetas',           JSON_ARRAY('ver','crear','editar'),
     'inventario',        JSON_ARRAY('ver'),
     'ventas',            JSON_ARRAY('ver'),
     'citas',             JSON_ARRAY('ver','editar'),
     'hospitalizaciones', JSON_ARRAY('ver','crear','editar'),
     'reportes',          JSON_ARRAY('ver'),
     'estetica',          JSON_ARRAY('ver')
 ), 1),
(3, 'Guardia / Seguridad', 'guardia',
 'Personal de seguridad con acceso limitado a registro de visitas y alertas',
 JSON_OBJECT(
     'dashboard',    JSON_ARRAY('ver'),
     'pacientes',    JSON_ARRAY('ver'),
     'propietarios', JSON_ARRAY('ver'),
     'citas',        JSON_ARRAY('ver'),
     'alertas',      JSON_ARRAY('ver')
 ), 1),
(4, 'Recepcionista', 'recepcionista',
 'Gestión de citas, recepción de pacientes, cobros y atención al cliente',
 JSON_OBJECT(
     'dashboard',    JSON_ARRAY('ver'),
     'pacientes',    JSON_ARRAY('ver','crear','editar'),
     'propietarios', JSON_ARRAY('ver','crear','editar'),
     'historial',    JSON_ARRAY('ver'),
     'vacunaciones', JSON_ARRAY('ver'),
     'recetas',      JSON_ARRAY('ver'),
     'inventario',   JSON_ARRAY('ver','editar'),
     'ventas',       JSON_ARRAY('ver','crear'),
     'citas',        JSON_ARRAY('ver','crear','editar','cancelar'),
     'estetica',     JSON_ARRAY('ver','crear','editar')
 ), 1),
(5, 'Groomer / Estética', 'groomer',
 'Personal de estética y grooming con acceso a agenda y fichas de mascotas',
 JSON_OBJECT(
     'dashboard',    JSON_ARRAY('ver'),
     'pacientes',    JSON_ARRAY('ver'),
     'propietarios', JSON_ARRAY('ver'),
     'estetica',     JSON_ARRAY('ver','crear','editar'),
     'citas',        JSON_ARRAY('ver'),
     'alertas',      JSON_ARRAY('ver','crear')
 ), 1),
(6, 'Propietario', 'propietario',
 'Dueño de mascotas con acceso al portal de propietarios',
 JSON_OBJECT(
     'mis_mascotas', JSON_ARRAY('ver'),
     'historial',    JSON_ARRAY('ver'),
     'vacunaciones', JSON_ARRAY('ver'),
     'recetas',      JSON_ARRAY('ver'),
     'citas',        JSON_ARRAY('ver','solicitar'),
     'documentos',   JSON_ARRAY('ver','descargar')
 ), 1),
(7, 'Emergencias', 'emergencia',
 'Acceso de emergencia con permisos temporales expandidos para situaciones críticas',
 JSON_OBJECT(
     'dashboard',         JSON_ARRAY('ver'),
     'pacientes',         JSON_ARRAY('ver','crear','editar'),
     'propietarios',      JSON_ARRAY('ver','crear'),
     'historial',         JSON_ARRAY('ver','crear','editar'),
     'vacunaciones',      JSON_ARRAY('ver','crear'),
     'recetas',           JSON_ARRAY('ver','crear'),
     'hospitalizaciones', JSON_ARRAY('ver','crear','editar'),
     'inventario',        JSON_ARRAY('ver'),
     'alertas',           JSON_ARRAY('ver','crear')
 ), 1)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), descripcion = VALUES(descripcion);

-- ============================================================
-- SEED 2: ESPECIES
-- ============================================================
INSERT INTO `especies` (`id`, `nombre`, `descripcion`, `activo`) VALUES
(1, 'Perro',   'Canis lupus familiaris', 1),
(2, 'Gato',    'Felis catus', 1),
(3, 'Ave',     'Aves domésticas y exóticas', 1),
(4, 'Reptil',  'Reptiles domésticos: iguanas, tortugas, serpientes', 1),
(5, 'Roedor',  'Hamsters, ratas, ratones, cobayas', 1),
(6, 'Conejo',  'Oryctolagus cuniculus', 1),
(7, 'Pez',     'Peces de acuario y estanque', 1),
(8, 'Otro',    'Otras especies no especificadas', 1)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), descripcion = VALUES(descripcion);

-- ============================================================
-- SEED 3: RAZAS — 10 perros + 8 gatos
-- ============================================================
INSERT INTO `razas` (`id`, `especie_id`, `nombre`, `descripcion`, `activo`) VALUES
-- PERROS (especie_id = 1)
(1,  1, 'Labrador Retriever',  'Raza grande, amigable y activa. Muy popular como mascota familiar.', 1),
(2,  1, 'Pastor Alemán',       'Raza grande, leal e inteligente. Usada frecuentemente como perro de trabajo.', 1),
(3,  1, 'Golden Retriever',    'Raza grande, cariñosa y tolerante. Excelente con niños.', 1),
(4,  1, 'Bulldog Francés',     'Raza pequeña, compacta y juguetona. Adaptable a departamentos.', 1),
(5,  1, 'Poodle / Caniche',    'Raza muy inteligente, disponible en tamaños toy, miniatura y estándar.', 1),
(6,  1, 'Beagle',              'Raza mediana, enérgica y curiosa. Excelente olfato.', 1),
(7,  1, 'Chihuahua',           'Raza miniatura, valiente y leal. Una de las razas más pequeñas del mundo.', 1),
(8,  1, 'Rottweiler',          'Raza grande, fuerte y segura. Requiere socialización temprana.', 1),
(9,  1, 'Shih Tzu',            'Raza pequeña, dócil y amigable. Cabello largo que requiere mantenimiento.', 1),
(10, 1, 'Yorkshire Terrier',   'Raza pequeña, valiente y energética. Pelo fino y sedoso.', 1),
-- GATOS (especie_id = 2)
(11, 2, 'Doméstico de Pelo Corto', 'Gato común sin pedigree, pelo corto. Muy adaptable.', 1),
(12, 2, 'Doméstico de Pelo Largo', 'Gato común sin pedigree, pelo largo. Requiere cepillado frecuente.', 1),
(13, 2, 'Siamés',              'Raza oriental elegante, vocal y muy sociable con sus dueños.', 1),
(14, 2, 'Persa',               'Raza de pelo largo y cara plana. Tranquilo y necesita cepillado diario.', 1),
(15, 2, 'Maine Coon',          'Una de las razas más grandes. Pelo semilargo, amigable y juguetón.', 1),
(16, 2, 'Ragdoll',             'Raza grande y dócil. Se relaja cuando se le carga, muy apegado.', 1),
(17, 2, 'Bengalí',             'Raza activa con pelaje moteado similar al leopardo. Muy enérgico.', 1),
(18, 2, 'Scottish Fold',       'Orejas dobladas características. Dócil y cariñoso.', 1)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), descripcion = VALUES(descripcion), activo = 1;

-- ============================================================
-- SEED 4: CATEGORÍAS DE INVENTARIO
-- ============================================================
INSERT INTO `categorias_inventario` (`id`, `nombre`, `descripcion`, `tipo`, `activo`) VALUES
(1, 'Medicamentos',  'Fármacos, antibióticos, antiparasitarios, suplementos y preparaciones veterinarias', 'medicamento',  1),
(2, 'Alimentos',     'Alimentos balanceados, dietas terapéuticas, snacks y suplementos alimenticios',       'alimento',     1),
(3, 'Accesorios',    'Collares, correas, camas, juguetes, transportadoras y artículos para mascotas',       'accesorio',    1),
(4, 'Consumibles',   'Jeringas, gasas, guantes, vendas, materiales de sutura y descartables clínicos',      'consumible',   1),
(5, 'Servicios',     'Consultas, cirugías, baños, cortes, vacunaciones y procedimientos veterinarios',       'servicio',     1)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), descripcion = VALUES(descripcion);

-- ============================================================
-- SEED 5: VACUNAS CATÁLOGO (8 vacunas)
-- ============================================================
INSERT INTO `vacunas_catalogo`
    (`id`, `nombre`, `laboratorio`, `enfermedad_prevenida`, `dosis_ml`, `via_administracion`, `intervalo_revacunacion_dias`, `activo`)
VALUES
(1, 'Rabia',         'Zoetis',    'Rabia / Lyssavirus',                                                        1.00, 'Subcutánea',  365, 1),
(2, 'Moquillo',      'Nobivac',   'Moquillo canino (CDV)',                                                      1.00, 'Subcutánea',  365, 1),
(3, 'Parvovirus',    'Nobivac',   'Parvovirus canino (CPV)',                                                    1.00, 'Subcutánea',  365, 1),
(4, 'Hepatitis',     'Nobivac',   'Hepatitis infecciosa canina (CAV-1)',                                        1.00, 'Subcutánea',  365, 1),
(5, 'Leptospirosis', 'Zoetis',    'Leptospira spp.',                                                            1.00, 'Subcutánea',  365, 1),
(6, 'Bordetella',    'Nobivac',   'Tos de las perreras (Bordetella bronchiseptica)',                            1.00, 'Intranasal',  365, 1),
(7, 'DHLPP',         'Duramune',  'Moquillo, Hepatitis, Leptospirosis, Parvovirus, Parainfluenza',              1.00, 'Subcutánea',  365, 1),
(8, 'Triple Felina', 'Purevax',   'Panleucopenia, Calicivirus, Herpesvirus felino',                             1.00, 'Subcutánea',  365, 1)
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), enfermedad_prevenida = VALUES(enfermedad_prevenida), via_administracion = VALUES(via_administracion);

-- ============================================================
-- SEED 6: CONFIGURACIÓN DEL SISTEMA
-- ============================================================
INSERT INTO `configuracion_sistema` (`clave`, `valor`, `descripcion`, `grupo`) VALUES
('nombre_clinica',          'PetKardex Clínica Veterinaria',   'Nombre oficial de la clínica veterinaria',                           'general'),
('slogan_clinica',          'Cuidamos a tus mejores amigos',   'Slogan o lema de la clínica',                                        'general'),
('direccion',               'Av. Principal 123, Ciudad',       'Dirección física de la clínica',                                     'contacto'),
('telefono',                '+51 999 000 111',                 'Teléfono principal de contacto',                                     'contacto'),
('telefono_emergencias',    '+51 999 000 112',                 'Teléfono de emergencias 24h',                                        'contacto'),
('email',                   'info@petkardex.com',              'Correo electrónico principal',                                       'contacto'),
('email_notificaciones',    'notificaciones@petkardex.com',   'Correo usado para enviar notificaciones automáticas',                 'contacto'),
('logo',                    '/assets/img/logo.png',            'Ruta al logo de la clínica',                                        'apariencia'),
('favicon',                 '/assets/img/favicon.ico',         'Ruta al favicon',                                                    'apariencia'),
('color_primario',          '#2E86AB',                         'Color primario del tema (HEX)',                                      'apariencia'),
('color_secundario',        '#A23B72',                         'Color secundario del tema (HEX)',                                    'apariencia'),
('moneda',                  'PEN',                             'Código ISO 4217 de la moneda (PEN=Sol, USD=Dólar)',                   'finanzas'),
('moneda_simbolo',          'S/.',                             'Símbolo de la moneda local',                                        'finanzas'),
('impuesto_porcentaje',     '18',                              'Porcentaje de impuesto (IGV/IVA)',                                   'finanzas'),
('zona_horaria',            'America/Lima',                    'Zona horaria del sistema (PHP timezone)',                             'sistema'),
('idioma',                  'es',                              'Idioma del sistema (código ISO 639-1)',                              'sistema'),
('formato_fecha',           'd/m/Y',                           'Formato de fecha para mostrar en la UI',                            'sistema'),
('formato_hora',            'H:i',                             'Formato de hora (24h)',                                              'sistema'),
('sesion_duracion',         '480',                             'Duración de la sesión de usuario en minutos',                       'seguridad'),
('intentos_login',          '5',                               'Intentos de login fallidos antes de bloquear la cuenta',            'seguridad'),
('bloqueo_minutos',         '15',                              'Minutos de bloqueo tras intentos fallidos',                         'seguridad'),
('qr_expiracion_horas',     '24',                              'Horas de validez de un enlace QR de mascota',                       'qr'),
('citas_anticipacion_dias', '30',                              'Días máximos de anticipación para agendar una cita',                'citas'),
('recordatorio_horas',      '24',                              'Horas antes de la cita para enviar recordatorio',                   'citas'),
('smtp_host',               'mailhog',                         'Servidor SMTP para envío de correos',                               'email'),
('smtp_port',               '1025',                            'Puerto SMTP',                                                        'email'),
('smtp_usuario',            '',                                'Usuario SMTP (vacío en desarrollo)',                                 'email'),
('smtp_password',           '',                                'Contraseña SMTP (vacío en desarrollo)',                              'email'),
('smtp_cifrado',            'none',                            'Cifrado SMTP: none, ssl, tls',                                      'email'),
('version_sistema',         '2.0.0',                           'Versión actual del sistema PetKardex',                              'sistema'),
('modo_mantenimiento',      '0',                               '1=modo mantenimiento activo, 0=sistema operativo',                  'sistema')
ON DUPLICATE KEY UPDATE valor = VALUES(valor), descripcion = VALUES(descripcion);

-- ============================================================
-- SEED 7: USUARIOS DE PRUEBA (1 POR CADA ROL PRINCIPAL)
-- Contraseñas generadas con PHP password_hash(..., PASSWORD_BCRYPT, ['cost' => 12])
-- Ver tabla de credenciales en texto plano en README.md
-- ============================================================
INSERT INTO `usuarios` (`rol_id`, `nombre`, `apellido`, `email`, `password_hash`, `telefono`, `dni`, `activo`)
SELECT r.id, 'Super', 'Admin', 'superadmin@petkardex.local',
       '$2y$12$h.oXUHBqhgyjhahXZcmy3eTmw4nCQLTnG/UYgfS4zxy4iwU9a/GAa', '+51 999 100 001', '10000001', 1
FROM roles r WHERE r.slug = 'superadmin'
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), apellido = VALUES(apellido), password_hash = VALUES(password_hash), dni = COALESCE(NULLIF(dni, ''), VALUES(dni)), activo = 1;

INSERT INTO `usuarios` (`rol_id`, `nombre`, `apellido`, `email`, `password_hash`, `telefono`, `dni`, `activo`)
SELECT r.id, 'Dra. Lucía', 'Vargas', 'veterinario@petkardex.local',
       '$2y$12$N1zByZUiHY3QveYdOrtJNeHAxMmrpTU58gSnIL6tlKjwZ/d5AiPLi', '+51 999 100 002', '10000002', 1
FROM roles r WHERE r.slug = 'veterinario'
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), apellido = VALUES(apellido), password_hash = VALUES(password_hash), dni = COALESCE(NULLIF(dni, ''), VALUES(dni)), activo = 1;

INSERT INTO `usuarios` (`rol_id`, `nombre`, `apellido`, `email`, `password_hash`, `telefono`, `dni`, `activo`)
SELECT r.id, 'Dr. Marco', 'Salazar', 'guardia@petkardex.local',
       '$2y$12$WHFyMXyY15/0i5yr97ABuO0Qp8W3ivF./QCPHZ6wIHz4XADa79Ia.', '+51 999 100 003', '10000003', 1
FROM roles r WHERE r.slug = 'guardia'
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), apellido = VALUES(apellido), password_hash = VALUES(password_hash), dni = COALESCE(NULLIF(dni, ''), VALUES(dni)), activo = 1;

INSERT INTO `usuarios` (`rol_id`, `nombre`, `apellido`, `email`, `password_hash`, `telefono`, `dni`, `activo`)
SELECT r.id, 'Elena', 'Quispe', 'recepcionista@petkardex.local',
       '$2y$12$TNG4uO/gxVgpdtmlQCg/NeZt73Xqu2oE9LVGGTMuxpQ6TaaFmTzVW', '+51 999 100 004', '10000004', 1
FROM roles r WHERE r.slug = 'recepcionista'
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), apellido = VALUES(apellido), password_hash = VALUES(password_hash), dni = COALESCE(NULLIF(dni, ''), VALUES(dni)), activo = 1;

INSERT INTO `usuarios` (`rol_id`, `nombre`, `apellido`, `email`, `password_hash`, `telefono`, `dni`, `activo`)
SELECT r.id, 'Sofía', 'Paredes', 'estilista@petkardex.local',
       '$2y$12$PMJliG2Lyk0g/XeTkjPtleH41lTBaFsEMKPL4MkV4QarlGNtCyeNC', '+51 999 100 005', '10000005', 1
FROM roles r WHERE r.slug = 'groomer'
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), apellido = VALUES(apellido), password_hash = VALUES(password_hash), dni = COALESCE(NULLIF(dni, ''), VALUES(dni)), activo = 1;

INSERT INTO `usuarios` (`rol_id`, `nombre`, `apellido`, `email`, `password_hash`, `telefono`, `dni`, `activo`)
SELECT r.id, 'Diego', 'Fernández', 'propietario@petkardex.local',
       '$2y$12$v4hJ3s/uEpx0XtGobsIV4ui1HRcZ1EXOb3hOIs5KDm73/xHCCrVL.', '+51 999 100 006', '45891234', 1
FROM roles r WHERE r.slug = 'propietario'
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), apellido = VALUES(apellido), password_hash = VALUES(password_hash), dni = COALESCE(NULLIF(dni, ''), VALUES(dni)), activo = 1;

-- Perfil de propietario vinculado al usuario de prueba 'propietario@petkardex.local'
INSERT INTO `propietarios` (`usuario_id`, `nombre`, `apellido`, `email`, `celular`, `dni`, `activo`)
SELECT u.id, 'Diego', 'Fernández', u.email, '+51 999 100 006', '45891234', 1
FROM usuarios u WHERE u.email = 'propietario@petkardex.local'
ON DUPLICATE KEY UPDATE usuario_id = VALUES(usuario_id), nombre = VALUES(nombre), apellido = VALUES(apellido), activo = 1;

-- ============================================================
-- SEED 8: SERVICIOS CLÍNICOS Y DE ESTÉTICA BASE (S/.)
-- ============================================================
INSERT INTO `productos` (`categoria_id`, `codigo`, `nombre`, `descripcion`, `unidad_medida`, `precio_compra`, `precio_venta`, `stock_actual`, `stock_minimo`, `requiere_receta`, `activo`)
SELECT c.id, s.codigo, s.nombre, s.descripcion, 'Servicio', 0.00, s.precio_venta, 0.00, 0.00, 0, 1
FROM categorias_inventario c
CROSS JOIN (
    SELECT 'SRV-CONS' AS codigo, 'Consulta Médica General' AS nombre, 'Evaluación clínica veterinaria general en consultorio' AS descripcion, 60.00 AS precio_venta
    UNION ALL SELECT 'SRV-VACU', 'Aplicación de Vacuna', 'Servicio de inmunización y registro en carnet sanitario', 50.00
    UNION ALL SELECT 'SRV-BANO', 'Baño Medicado / Estético', 'Baño completo, secado y cepillado terapéutico o estético', 45.00
    UNION ALL SELECT 'SRV-CORT', 'Corte Estético y Uñas', 'Peluquería canina/felina profesional y corte de uñas', 55.00
    UNION ALL SELECT 'SRV-HOSP', 'Hospitalización por Día', 'Tarifa diaria de internamiento en canil/jaula y monitoreo', 120.00
    UNION ALL SELECT 'SRV-CTRL', 'Control Clínico', 'Seguimiento post-tratamiento o revisión de herida', 40.00
    UNION ALL SELECT 'SRV-EMER', 'Atención de Emergencia', 'Triage prioritario y estabilización de urgencia', 90.00
    UNION ALL SELECT 'SRV-CIRU', 'Cirugía General', 'Procedimiento quirúrgico veterinario programado', 250.00
) s
WHERE c.tipo = 'servicio'
ON DUPLICATE KEY UPDATE nombre = VALUES(nombre), descripcion = VALUES(descripcion), precio_venta = VALUES(precio_venta), activo = 1;

-- ============================================================
SET FOREIGN_KEY_CHECKS = 1;
-- ============================================================
-- FIN DEL SEED — PetKardex v2
-- ============================================================

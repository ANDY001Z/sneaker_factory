-- Datos ficticios reproducibles para pruebas y defensa
-- Contraseña común de usuarios de prueba: Sneaker2026!

BEGIN;

-- 1. ROLES (3 perfiles obligatorios)
INSERT INTO roles (id, nombre, descripcion) VALUES
(1, 'admin',   'Administrador del sistema con acceso total'),
(2, 'almacen', 'Empleado de almacén: gestión de stock, productos y estados de pedido'),
(3, 'cliente', 'Cliente registrado: catálogo, perfil y gestión de pedidos propios');

-- 2. USUARIOS (1 admin, 1 almacén, 10 clientes ficticios)
INSERT INTO usuarios (id, rol_id, nombre, apellidos, email, password_hash, telefono, direccion) VALUES
(1,  1, 'Laura',   'Mendoza Ruiz',     'admin@sneakerfactory.local',   crypt('Sneaker2026!', gen_salt('bf', 10)), '600000001', 'Calle Fábrica 1, Madrid'),
(2,  2, 'Carlos',  'Serrano Gil',      'almacen@sneakerfactory.local', crypt('Sneaker2026!', gen_salt('bf', 10)), '600000002', 'Nave Logística 4, Madrid'),
(3,  3, 'Lucía',   'Fernández Vega',   'lucia.fernandez@ejemplo.test', crypt('Sneaker2026!', gen_salt('bf', 10)), '611100001', 'Av. de la Constitución 12, Sevilla'),
(4,  3, 'Javier',  'Gómez Ortega',     'javier.gomez@ejemplo.test',    crypt('Sneaker2026!', gen_salt('bf', 10)), '611100002', 'C/ Mayor 45, Valencia'),
(5,  3, 'Elena',   'Navarro Castro',   'elena.navarro@ejemplo.test',   crypt('Sneaker2026!', gen_salt('bf', 10)), '611100003', 'Paseo de Gracia 88, Barcelona'),
(6,  3, 'Marcos',  'Díaz Herrera',     'marcos.diaz@ejemplo.test',     crypt('Sneaker2026!', gen_salt('bf', 10)), '611100004', 'C/ Uría 21, Oviedo'),
(7,  3, 'Sofía',   'Romero Molina',    'sofia.romero@ejemplo.test',    crypt('Sneaker2026!', gen_salt('bf', 10)), '611100005', 'Gran Vía 104, Bilbao'),
(8,  3, 'Daniel',  'Torres Peña',      'daniel.torres@ejemplo.test',   crypt('Sneaker2026!', gen_salt('bf', 10)), '611100006', 'C/ Larios 7, Málaga'),
(9,  3, 'Paula',   'Vázquez León',     'paula.vazquez@ejemplo.test',   crypt('Sneaker2026!', gen_salt('bf', 10)), '611100007', 'Av. Alfonso X 33, Murcia'),
(10, 3, 'Adrián',  'Cano Morales',     'adrian.cano@ejemplo.test',     crypt('Sneaker2026!', gen_salt('bf', 10)), '611100008', 'C/ Corrida 19, Gijón'),
(11, 3, 'Marta',   'Delgado Soto',     'marta.delgado@ejemplo.test',   crypt('Sneaker2026!', gen_salt('bf', 10)), '611100009', 'Plaza España 5, Zaragoza'),
(12, 3, 'Hugo',    'Iglesias Pardo',   'hugo.iglesias@ejemplo.test',   crypt('Sneaker2026!', gen_salt('bf', 10)), '611100010', 'Rúa do Franco 14, Santiago');

-- 3. MODELOS DE ZAPATILLAS (10 modelos reales)
INSERT INTO modelos
(id, sku, marca, nombre, descripcion, categoria, precio_base, imagen_url, personalizable)
VALUES
(
    1, 'SF-NIKE-AF107', 'Nike', 'Air Force 1 ''07',
    'El resplandor vive en las Nike Air Force 1 ''07, el icono del baloncesto que da un toque innovador a lo que mejor conoces.',
    'lifestyle', 119.99,
    '/assets/img/af1.jpg',
    FALSE
),
(
    2, 'SF-ADI-ULB01', 'Adidas', 'Ultraboost Light',
    'Siente una energía épica con las nuevas Ultraboost Light, nuestras Ultraboost más ligeras hasta la fecha.',
    'running', 190.00,
    '/assets/img/ultraboost.jpg',
    TRUE
 ),
(
    3, 'SF-JOR-AJ1RH', 'Jordan', 'Air Jordan 1 Retro High',
    'Las zapatillas que lo empezaron todo. Estilo clásico y sujeción premium en la cancha.',
    'basketball', 179.99,
    '/assets/img/jordan1.jpg',
    FALSE
 ),
(
    4, 'SF-NB-55001', 'New Balance', '550',
    'El modelo original 550 debutó en 1989 y dejó su huella en las canchas de baloncesto de costa a costa.',
    'lifestyle', 130.00,
    '/assets/img/jordan1.jpg',
    TRUE
 ),
(
    5, 'SF-PUM-MB02', 'Puma', 'MB.02 LaMelo Ball',
    'Zapatillas de baloncesto exclusivas de LaMelo Ball con tecnología NITRO para explosividad.',
    'basketball', 140.00,
    '/assets/img/lamelo.jpg',
    TRUE
 ),
(
    6, 'SF-ASI-GK29', 'Asics', 'GEL-KAYANO 29',
    'Crea una experiencia de carrera estable y con gran capacidad de respuesta debajo del pie.',
    'running', 190.00,
    '/assets/img/kayano.jpeg',
    TRUE
 ),
(
    7, 'SF-CON-CTAS', 'Converse', 'Chuck Taylor All Star',
    'La lona inconfundible, perfecta para tu día a día, sea cual sea tu estilo.',
    'lifestyle', 75.00,
    '/assets/img/converse.jpg',
    FALSE
 ),
(
    8, 'SF-UA-CF10', 'Under Armour', 'Curry Flow 10',
    'Cambia de dirección a la velocidad de la luz. Amortiguación UA Flow totalmente sin goma.',
    'basketball', 160.00,
    '/assets/img/curry.jpg',
    FALSE
 ),
(
    9, 'SF-VAN-OS01', 'Vans', 'Old Skool',
    'Las emblemáticas zapatillas de skate de Vans con la inconfundible banda lateral.',
    'skate', 80.00,
    '/assets/img/oldSkool.jpg',
    FALSE
 ),
(
    10, 'SF-HOK-CL09', 'HOKA', 'Clifton 9',
    'Más ligeras y con más amortiguación que nunca. Las zapatillas perfectas para sumar kilómetros diarios.',
    'running', 150.00,
    '/assets/img/clifton9.jpg',
    FALSE
 );

-- 4. VARIANTES (Talla, Color y Stock - incluye variantes con stock crítico/0 para pruebas)
INSERT INTO variantes_modelo (id, modelo_id, talla, color, stock, stock_minimo) VALUES
(1,  1, 40.0, 'Negro/Blanco', 15, 3),
(2,  1, 42.0, 'Negro/Blanco', 12, 3),
(3,  1, 43.0, 'Azul Eléctrico', 2, 3),
(4,  2, 38.0, 'Blanco',       18, 4),
(5,  2, 41.0, 'Blanco/Verde', 10, 4),
(6,  2, 44.0, 'Blanco',        1, 4),
(7,  3, 41.0, 'Verde Oliva',   9, 3),
(8,  3, 43.0, 'Negro/Naranja', 6, 3),
(9,  3, 45.0, 'Verde Oliva',   0, 3), -- Sin stock para probar rechazo
(10, 4, 39.0, 'Gris Humo',    14, 3),
(11, 4, 42.0, 'Rojo Coral',    8, 3),
(12, 4, 44.0, 'Negro',         3, 3),
(13, 5, 40.0, 'Rojo/Negro',   11, 3),
(14, 5, 42.0, 'Blanco/Azul',   7, 3),
(15, 5, 44.0, 'Rojo/Negro',    2, 3),
(16, 6, 39.0, 'Base Blanca',  25, 5),
(17, 6, 42.0, 'Base Negra',   20, 5),
(18, 6, 44.0, 'Base Blanca',  16, 5),
(19, 7, 38.0, 'Negro/Goma',   19, 4),
(20, 7, 41.0, 'Azul Marino',  13, 4),
(21, 7, 43.0, 'Negro/Goma',    5, 4),
(22, 8, 40.0, 'Amarillo Neón', 4, 2),
(23, 8, 42.0, 'Amarillo Neón', 1, 2),
(24, 8, 44.0, 'Blanco/Plata',  0, 2), -- Sin stock para probar rechazo
(25, 9, 40.0, 'Negro/Blanco', 12, 3),
(26, 9, 42.0, 'Negro/Blanco', 15, 3),
(27, 9, 44.0, 'Negro/Blanco',  8, 3),
(28, 10, 40.0, 'Azul',        14, 3),
(29, 10, 42.0, 'Azul',        18, 3),
(30, 10, 44.0, 'Azul',         0, 3);

-- 5. PEDIDOS (20 pedidos: todos los estados + 6 personalizados)
INSERT INTO pedidos (id, codigo_pedido, usuario_id, estado, es_personalizado, importe_total, direccion_envio, notas, creado_en) VALUES
(1,  'SF-2026-0001', 3,  'enviado',     FALSE, 0, 'Av. de la Constitución 12, Sevilla', 'Entregar por la mañana',         NOW() - INTERVAL '25 days'),
(2,  'SF-2026-0002', 4,  'enviado',     TRUE,  0, 'C/ Mayor 45, Valencia',              'Equipación club local',          NOW() - INTERVAL '22 days'),
(3,  'SF-2026-0003', 5,  'enviado',     FALSE, 0, 'Paseo de Gracia 88, Barcelona',      NULL,                             NOW() - INTERVAL '20 days'),
(4,  'SF-2026-0004', 6,  'cancelado',   FALSE, 0, 'C/ Uría 21, Oviedo',                 'Cancelado por cambio de talla',  NOW() - INTERVAL '19 days'),
(5,  'SF-2026-0005', 7,  'enviado',     TRUE,  0, 'Gran Vía 104, Bilbao',               'Personalización torneo verano',  NOW() - INTERVAL '18 days'),
(6,  'SF-2026-0006', 8,  'preparacion', FALSE, 0, 'C/ Larios 7, Málaga',                NULL,                             NOW() - INTERVAL '15 days'),
(7,  'SF-2026-0007', 9,  'confirmado',  FALSE, 0, 'Av. Alfonso X 33, Murcia',           'Llamar al llegar',               NOW() - INTERVAL '14 days'),
(8,  'SF-2026-0008', 10, 'enviado',     FALSE, 0, 'C/ Corrida 19, Gijón',               NULL,                             NOW() - INTERVAL '13 days'),
(9,  'SF-2026-0009', 11, 'preparacion', TRUE,  0, 'Plaza España 5, Zaragoza',           'Bordado iniciales en talón',     NOW() - INTERVAL '11 days'),
(10, 'SF-2026-0010', 12, 'cancelado',   FALSE, 0, 'Rúa do Franco 14, Santiago',         'Pedido duplicado',               NOW() - INTERVAL '10 days'),
(11, 'SF-2026-0011', 3,  'confirmado',  FALSE, 0, 'Av. de la Constitución 12, Sevilla', NULL,                             NOW() - INTERVAL '9 days'),
(12, 'SF-2026-0012', 4,  'pendiente',   FALSE, 0, 'C/ Mayor 45, Valencia',              NULL,                             NOW() - INTERVAL '8 days'),
(13, 'SF-2026-0013', 5,  'confirmado',  TRUE,  0, 'Paseo de Gracia 88, Barcelona',      'Lote equipo femenino',           NOW() - INTERVAL '7 days'),
(14, 'SF-2026-0014', 6,  'preparacion', FALSE, 0, 'C/ Uría 21, Oviedo',                 NULL,                             NOW() - INTERVAL '6 days'),
(15, 'SF-2026-0015', 7,  'pendiente',   FALSE, 0, 'Gran Vía 104, Bilbao',               'Envío urgente si es posible',    NOW() - INTERVAL '5 days'),
(16, 'SF-2026-0016', 8,  'cancelado',   FALSE, 0, 'C/ Larios 7, Málaga',                'Cliente canceló antes de envío', NOW() - INTERVAL '4 days'),
(17, 'SF-2026-0017', 9,  'pendiente',   TRUE,  0, 'Av. Alfonso X 33, Murcia',           'Cordones flúor y texto',         NOW() - INTERVAL '3 days'),
(18, 'SF-2026-0018', 10, 'confirmado',  FALSE, 0, 'C/ Corrida 19, Gijón',               NULL,                             NOW() - INTERVAL '2 days'),
(19, 'SF-2026-0019', 11, 'preparacion', FALSE, 0, 'Plaza España 5, Zaragoza',           NULL,                             NOW() - INTERVAL '1 day'),
(20, 'SF-2026-0020', 12, 'pendiente',   TRUE,  0, 'Rúa do Franco 14, Santiago',         'Diseño personalizado aniversario', NOW());

-- 6. LÍNEAS DE PEDIDO (incluye pedidos multilínea y JSONB en los 6 personalizados)
INSERT INTO pedido_lineas (pedido_id, variante_id, cantidad, precio_unitario, opciones_personalizacion) VALUES
(1,  1,  1, 119.99, NULL),
(1,  19, 1, 75.00, NULL),
(2,  16, 3, 190.00, '{"color_cordones": "azul rey", "texto_talon": "VALENCIA RUN", "color_suela": "blanca"}'),
(3,  4,  2, 190.00, NULL),
(4,  7,  1, 179.99, NULL),
(5,  17, 2, 190.00, '{"color_cordones": "rojo", "texto_talon": "BILBAO 26", "acabado": "mate"}'),
(5,  13, 1, 140.00, '{"color_cordones": "dorado", "texto_talon": "CAPITAN"}'),
(6,  10, 1, 130.00, NULL),
(7,  22, 1, 160.00, NULL),
(8,  20, 2, 75.00, NULL),
(9,  5,  1, 190.00, '{"color_cordones": "verde bosque", "texto_talon": "M.D.S.", "plantilla": "gel"}'),
(10, 2,  1, 119.99, NULL),
(11, 8,  1, 179.99, NULL),
(11, 21, 1, 75.00, NULL),
(12, 14, 1, 140.00, NULL),
(13, 18, 4, 190.00, '{"color_cordones": "turquesa", "texto_talon": "BCN CLUB", "escudo": "lateral"}'),
(14, 11, 2, 130.00, NULL),
(15, 23, 1, 160.00, NULL),
(16, 1,  1, 119.99, NULL),
(17, 11, 1, 130.00, '{"color_cordones": "lima neón", "texto_talon": "PAULA 09"}'),
(18, 4,  1, 190.00, NULL),
(18, 12, 1, 130.00, NULL),
(19, 15, 1, 140.00, NULL),
(20, 16, 2, 190.00, '{"color_cordones": "oro", "texto_talon": "SANTIAGO X", "caja_regalo": true}');

-- Sincronizar el importe_total de cada pedido con la suma exacta de sus líneas
UPDATE pedidos p
SET importe_total = (
    SELECT COALESCE(SUM(pl.subtotal), 0.00)
    FROM pedido_lineas pl
    WHERE pl.pedido_id = p.id
);

-- 7. HISTORIAL DE TRANSICIONES DE ESTADO (muestra trazabilidad real)
INSERT INTO pedido_historial_estados (pedido_id, estado_anterior, estado_nuevo, usuario_id, comentario, cambiado_en) VALUES
(1,  NULL,          'pendiente',   3,  'Pedido creado por el cliente',           NOW() - INTERVAL '25 days'),
(1,  'pendiente',   'confirmado',  2,  'Stock verificado y pago confirmado',     NOW() - INTERVAL '24 days'),
(1,  'confirmado',  'preparacion', 2,  'Preparando paquete en almacén',          NOW() - INTERVAL '23 days'),
(1,  'preparacion', 'enviado',     2,  'Entregado a transportista',              NOW() - INTERVAL '22 days'),
(2,  NULL,          'pendiente',   4,  'Pedido personalizado creado',            NOW() - INTERVAL '22 days'),
(2,  'pendiente',   'confirmado',  2,  'Diseño personalizado validado',          NOW() - INTERVAL '21 days'),
(2,  'confirmado',  'preparacion', 2,  'En taller de personalización',           NOW() - INTERVAL '20 days'),
(2,  'preparacion', 'enviado',     2,  'Pedido enviado al club',                 NOW() - INTERVAL '18 days'),
(4,  NULL,          'pendiente',   6,  'Pedido creado por el cliente',           NOW() - INTERVAL '19 days'),
(4,  'pendiente',   'cancelado',   6,  'Cancelado por el cliente',               NOW() - INTERVAL '18 days'),
(6,  NULL,          'pendiente',   8,  'Pedido creado por el cliente',           NOW() - INTERVAL '15 days'),
(6,  'pendiente',   'confirmado',  2,  'Confirmado en almacén',                  NOW() - INTERVAL '14 days'),
(6,  'confirmado',  'preparacion', 2,  'Empaquetando pedido',                    NOW() - INTERVAL '13 days'),
(17, NULL,          'pendiente',   9,  'Pedido personalizado pendiente de revisión', NOW() - INTERVAL '3 days'),
(20, NULL,          'pendiente',   12, 'Pedido personalizado recién registrado', NOW());

-- 8. AUDITORÍA INICIAL DE EVENTOS DE NEGOCIO
INSERT INTO auditoria_eventos (usuario_id, tipo_evento, entidad, entidad_id, resultado, detalles, ip_origen, creado_en) VALUES
(3, 'ALTA_PEDIDO',        'pedido',          1,  'OK', '{"codigo": "SF-2026-0001"}', '192.168.1.20', NOW() - INTERVAL '25 days'),
(2, 'CAMBIO_ESTADO',         'pedido',          1,  'OK', '{"de": "preparacion", "a": "enviado"}', '192.168.1.10', NOW() - INTERVAL '22 days'),
(6, 'CANCELACION_PEDIDO',    'pedido',          4,  'OK', '{"motivo": "cambio de talla"}', '192.168.1.25', NOW() - INTERVAL '18 days'),
(2, 'AJUSTE_STOCK',          'variante_modelo', 9,  'OK', '{"stock_anterior": 2, "stock_nuevo": 0}', '192.168.1.10', NOW() - INTERVAL '12 days'),
(1, 'CREACION_MODELO',       'modelo',          8,  'OK', '{"sku": "SF-UA-CF10"}', '192.168.1.5', NOW() - INTERVAL '30 days');

-- Ajustar secuencias tras insertar IDs explícitos (evita errores de clave duplicada desde PHP)
SELECT setval(pg_get_serial_sequence('roles', 'id'),                    (SELECT MAX(id) FROM roles));
SELECT setval(pg_get_serial_sequence('usuarios', 'id'),                 (SELECT MAX(id) FROM usuarios));
SELECT setval(pg_get_serial_sequence('modelos', 'id'),                  (SELECT MAX(id) FROM modelos));
SELECT setval(pg_get_serial_sequence('variantes_modelo', 'id'),         (SELECT MAX(id) FROM variantes_modelo));
SELECT setval(pg_get_serial_sequence('pedidos', 'id'),                  (SELECT MAX(id) FROM pedidos));
SELECT setval(pg_get_serial_sequence('pedido_lineas', 'id'),            (SELECT MAX(id) FROM pedido_lineas));
SELECT setval(pg_get_serial_sequence('pedido_historial_estados', 'id'), (SELECT MAX(id) FROM pedido_historial_estados));
SELECT setval(pg_get_serial_sequence('auditoria_eventos', 'id'),        (SELECT MAX(id) FROM auditoria_eventos));

COMMIT;
-- Insertar un usuario administrador por defecto (La contraseña es 'admin123' hasheada con bcrypt)
INSERT INTO users (name, email, password_hash, role) VALUES 
('Admin Principal', 'admin@sneakerfactory.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('Cliente Prueba', 'cliente@sneakerfactory.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'client');

-- Insertar 10 Zapatillas de prueba
INSERT INTO products (name, brand, description, price, size, stock, image_url) VALUES 
('Air Max 90', 'Nike', 'Clásicas zapatillas de running con cámara de aire visible.', 139.99, 42.0, 15, 'air_max_90.jpg'),
('Air Force 1', 'Nike', 'Zapatillas urbanas blancas, un icono de la moda callejera.', 119.99, 39.0, 25, 'air_force_1.jpg'),
('Yeezy Boost 350', 'Adidas', 'Diseño exclusivo con amortiguación Boost.', 229.50, 41.5, 5, 'yeezy_350.jpg'),
('Classic Leather', 'Reebok', 'Estilo retro en cuero blanco impecable.', 89.99, 44.0, 30, 'classic_leather.jpg'),
('Old Skool', 'Vans', 'Zapatillas de skate con la inconfundible banda lateral.', 75.00, 38.0, 40, 'old_skool.jpg'),
('Chuck Taylor All Star', 'Converse', 'Las zapatillas de lona más famosas del mundo.', 65.00, 43.0, 50, 'chuck_taylor.jpg'),
('Gel-Lyte III', 'ASICS', 'Comodidad superior con su característica lengüeta bífida.', 125.00, 40.0, 12, 'gel_lyte_iii.jpg'),
('574 Core', 'New Balance', 'Zapatilla casual muy versátil y cómoda.', 99.99, 41.0, 18, '574_core.jpg'),
('Dunk Low', 'Nike', 'Estilo baloncestístico de los 80 adaptado a las calles.', 109.99, 42.5, 8, 'dunk_low.jpg'),
('RS-X3', 'Puma', 'Diseño chunky con bloques de colores llamativos.', 110.00, 39.5, 20, 'rs_x3.jpg');
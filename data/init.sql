-- Tabla de prueba para verificar que la carga automática funciona
CREATE TABLE IF NOT EXISTS zapatillas_test (
    id SERIAL PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    precio DECIMAL(10,2) NOT NULL
);

INSERT INTO zapatillas_test (nombre, precio) VALUES 
('Air Sneaker Test', 99.99),
('Runner Pro Test', 129.99);
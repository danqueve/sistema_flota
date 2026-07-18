-- ============================================================
-- DATOS SEMILLA — desarrollo local (PASO 3)
-- No incluye el usuario admin: se crea aparte con clave
-- hasheada (ver includes/ o el script de alta puntual).
-- ============================================================

SET NAMES utf8mb4;

INSERT INTO camiones (patente, marca, modelo, anio, km_actual) VALUES
('AB 123 CD', 'Scania', 'G 360', 2019, 385200),
('AC 456 EF', 'Volvo', 'FH 460', 2018, 412850),
('AD 789 GH', 'Mercedes-Benz', 'Actros 2545', 2021, 210430);

INSERT INTO choferes (nombre, dni, telefono) VALUES
('Juan Pérez', '28456123', '381-4551234'),
('Ramón Gómez', '30112456', '381-4552345'),
('Sergio Ledesma', '32998741', '381-4553456');

INSERT INTO clientes (razon_social, cuit, localidad, telefono, es_portal_pallets) VALUES
('Molinos Tucumán S.A.', '30-12345678-9', 'San Miguel de Tucumán, Tucumán', '381-4221100', 0),
('Citrícola San Miguel S.R.L.', '30-23456789-0', 'Famaillá, Tucumán', '381-4987654', 0),
('Frigorífico del Norte S.A.', '30-45678901-2', 'Concepción, Tucumán', '381-4123456', 0),
('Envasadora del Litoral S.A.', '30-34567890-1', 'Concordia, Entre Ríos', '345-4234567', 1);

INSERT INTO estaciones (nombre, localidad, tiene_cta_cte) VALUES
('YPF Ruta 9', 'San Miguel de Tucumán', 1),
('Shell Acc. Sur', 'San Miguel de Tucumán', 1),
('Axion Ruta 38', 'Tafí Viejo', 0);

INSERT INTO cuentas (tipo, nombre, saldo_inicial) VALUES
('banco', 'Banco Galicia CC $', 1500000.00),
('banco', 'Banco Macro CC $', 800000.00),
('caja', 'Caja efectivo', 150000.00);

INSERT INTO categorias_gasto (nombre, ambito) VALUES
('Viáticos', 'viaje'),
('Playa', 'viaje'),
('Peaje', 'viaje'),
('Sueldos', 'general'),
('Repuestos', 'general'),
('Gastos bancarios', 'general'),
('Combustible', 'general'),
('Mantenimiento', 'general');

INSERT INTO tipos_service (nombre) VALUES
('Cambio de aceite y filtros'),
('Frenos'),
('Cubiertas'),
('Embrague'),
('Tren delantero'),
('Revisión general');

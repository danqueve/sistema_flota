-- ============================================================
-- DATOS SEMILLA — Fase 2 (cheques/tesorería + stock), para probar
-- Los cheques recibidos y emitidos NO están acá: se cargaron
-- vía la propia app (nuevo.php + cartera.php + emitidos.php) para
-- que cheques_movimientos y movimientos_tesoreria queden coherentes
-- con la lógica real de negocio, no con un INSERT a mano.
-- ============================================================

SET NAMES utf8mb4;

INSERT INTO financieras (nombre, contacto) VALUES
('Financiera del Norte', 'Marcos - 381-4556677');

INSERT INTO repuestos (codigo, nombre, categoria, marca, compatible_con, stock_actual, stock_minimo, ubicacion, costo_unitario) VALUES
('F-100', 'Filtro de aceite', 'repuesto', 'Mann', 'todos', 2, 5, 'Estante A1', 8500.00),
('F-101', 'Filtro de aire', 'repuesto', 'Mann', 'todos', 6, 4, 'Estante A1', 12000.00),
('F-102', 'Filtro de combustible', 'repuesto', 'Bosch', 'todos', 8, 4, 'Estante A2', 9800.00),
('B-200', 'Pastillas de freno delanteras', 'repuesto', 'Frasle', 'todos', 1, 3, 'Estante B1', 34000.00),
('B-201', 'Correa de distribución', 'repuesto', 'Gates', 'Scania G360', 3, 2, 'Estante B2', 45000.00),
('B-202', 'Amortiguador delantero', 'repuesto', 'Monroe', 'todos', 4, 2, 'Estante C1', 68000.00),
('E-300', 'Batería 12V 150Ah', 'repuesto', 'Willard', 'todos', 0, 2, 'Estante C2', 185000.00),
('C-400', 'Cubierta 295/80 R22.5', 'cubierta', 'Bridgestone', 'todos', 8, 4, 'Depósito', 250000.00),
('C-401', 'Cubierta 11R22.5', 'cubierta', 'Fate', 'Volvo FH460', 2, 4, 'Depósito', 220000.00),
('A-500', 'Aceite de motor 15W40 (bidón 20L)', 'repuesto', 'Shell', 'todos', 5, 3, 'Estante A3', 95000.00);

-- Movimientos que explican el stock_actual de arriba (ingreso inicial +
-- un par de egresos reales para variar). Busca el repuesto por código,
-- no por id: el auto_increment no siempre arranca en 1 en un entorno
-- de desarrollo ya usado.
INSERT INTO movimientos_stock (repuesto_id, tipo, cantidad, camion_id, usuario_id, observaciones)
SELECT id, 'ingreso', 2, NULL, 1, 'Compra inicial' FROM repuestos WHERE codigo = 'F-100'
UNION ALL SELECT id, 'ingreso', 6, NULL, 1, 'Compra inicial' FROM repuestos WHERE codigo = 'F-101'
UNION ALL SELECT id, 'ingreso', 8, NULL, 1, 'Compra inicial' FROM repuestos WHERE codigo = 'F-102'
UNION ALL SELECT id, 'ingreso', 4, NULL, 1, 'Compra inicial' FROM repuestos WHERE codigo = 'B-200'
UNION ALL SELECT id, 'egreso', 3, 1, 1, 'Cambio de pastillas delanteras' FROM repuestos WHERE codigo = 'B-200'
UNION ALL SELECT id, 'ingreso', 3, NULL, 1, 'Compra inicial' FROM repuestos WHERE codigo = 'B-201'
UNION ALL SELECT id, 'ingreso', 4, NULL, 1, 'Compra inicial' FROM repuestos WHERE codigo = 'B-202'
UNION ALL SELECT id, 'ingreso', 2, NULL, 1, 'Compra inicial' FROM repuestos WHERE codigo = 'E-300'
UNION ALL SELECT id, 'egreso', 2, 2, 1, 'Batería agotada, cambio completo' FROM repuestos WHERE codigo = 'E-300'
UNION ALL SELECT id, 'ingreso', 8, NULL, 1, 'Compra inicial' FROM repuestos WHERE codigo = 'C-400'
UNION ALL SELECT id, 'ingreso', 2, NULL, 1, 'Compra inicial' FROM repuestos WHERE codigo = 'C-401'
UNION ALL SELECT id, 'ingreso', 5, NULL, 1, 'Compra inicial' FROM repuestos WHERE codigo = 'A-500';

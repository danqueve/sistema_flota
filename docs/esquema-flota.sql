-- ============================================================
-- SISTEMA DE GESTIÓN DE FLOTA — Esquema MySQL/MariaDB
-- Cliente: transportista (3 camiones) | Julio 2026
-- Convenciones: InnoDB, utf8mb4, importes DECIMAL(12,2),
-- soft-delete con campo `activo` en maestros.
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- NÚCLEO: usuarios y parámetros
-- ------------------------------------------------------------

CREATE TABLE usuarios (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre            VARCHAR(80)  NOT NULL,
  usuario           VARCHAR(40)  NOT NULL UNIQUE,
  clave_hash        VARCHAR(255) NOT NULL,
  rol               ENUM('admin','taller','portal_pallets') NOT NULL DEFAULT 'admin',
  activo            TINYINT(1) NOT NULL DEFAULT 1,
  intentos_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0,  -- bloqueo por fuerza bruta (ver docs/migraciones/fase4-usuarios-rate-limit.sql)
  bloqueado_hasta   DATETIME NULL,
  creado_en         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Parámetros globales editables (ej. comisión vigente del chofer).
-- La comisión vigente se COPIA a cada flete al crearlo (histórico intacto).
CREATE TABLE parametros (
  clave         VARCHAR(50) PRIMARY KEY,      -- 'pct_comision_chofer'
  valor         VARCHAR(100) NOT NULL,        -- '15.00'
  descripcion   VARCHAR(200) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO parametros VALUES
  ('pct_comision_chofer','15.00','Porcentaje de comisión sobre el bruto del flete');

-- ------------------------------------------------------------
-- MAESTROS
-- ------------------------------------------------------------

CREATE TABLE camiones (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patente       VARCHAR(10)  NOT NULL UNIQUE,
  marca         VARCHAR(40)  NULL,
  modelo        VARCHAR(60)  NULL,
  anio          SMALLINT     NULL,
  km_actual     INT UNSIGNED NULL,            -- último km conocido (se actualiza con cada carga/service)
  activo        TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE choferes (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre        VARCHAR(80)  NOT NULL,
  dni           VARCHAR(15)  NULL,
  telefono      VARCHAR(30)  NULL,
  activo        TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Clientes de flete Y la empresa de pallets (Entre Ríos) van acá.
CREATE TABLE clientes (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  razon_social  VARCHAR(120) NOT NULL,
  cuit          VARCHAR(15)  NULL,
  localidad     VARCHAR(80)  NULL,
  telefono      VARCHAR(30)  NULL,
  es_portal_pallets TINYINT(1) NOT NULL DEFAULT 0,  -- 1 = tiene acceso al portal
  activo        TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE categorias_gasto (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre        VARCHAR(60) NOT NULL,          -- playa, peaje, sueldos, repuestos, gastos bancarios...
  ambito        ENUM('viaje','general') NOT NULL DEFAULT 'general',
  activo        TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- MÓDULO 1: FLETES, GASTOS, COMBUSTIBLE, LIQUIDACIONES
-- ------------------------------------------------------------

CREATE TABLE fletes (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fecha           DATE NOT NULL,
  camion_id       INT UNSIGNED NOT NULL,
  chofer_id       INT UNSIGNED NOT NULL,
  cliente_id      INT UNSIGNED NULL,
  origen          VARCHAR(80)  NULL,
  destino         VARCHAR(80)  NOT NULL,
  importe_bruto   DECIMAL(12,2) NOT NULL,
  pct_comision    DECIMAL(5,2)  NOT NULL,      -- copiado del parámetro al crear (histórico)
  comision_chofer DECIMAL(12,2) NOT NULL,      -- importe_bruto * pct/100 (sobre el BRUTO, definido)
  viatico_adelanto DECIMAL(12,2) NOT NULL DEFAULT 0,  -- plata adelantada al chofer para el viaje
  estado_cobro    ENUM('pendiente','cobrado') NOT NULL DEFAULT 'pendiente',
  liquidacion_id  INT UNSIGNED NULL,           -- se completa al cerrar el mes
  observaciones   VARCHAR(255) NULL,
  creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_fletes_fecha (fecha),
  INDEX idx_fletes_camion (camion_id, fecha),
  INDEX idx_fletes_chofer (chofer_id, fecha),
  FOREIGN KEY (camion_id)  REFERENCES camiones(id),
  FOREIGN KEY (chofer_id)  REFERENCES choferes(id),
  FOREIGN KEY (cliente_id) REFERENCES clientes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Gastos REALES del viaje (los viáticos adelantados van en fletes.viatico_adelanto).
CREATE TABLE gastos_viaje (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  flete_id      INT UNSIGNED NOT NULL,
  categoria_id  INT UNSIGNED NOT NULL,
  importe       DECIMAL(12,2) NOT NULL,
  descripcion   VARCHAR(150) NULL,
  FOREIGN KEY (flete_id)     REFERENCES fletes(id) ON DELETE CASCADE,
  FOREIGN KEY (categoria_id) REFERENCES categorias_gasto(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE estaciones (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre        VARCHAR(80) NOT NULL,
  localidad     VARCHAR(80) NULL,
  tiene_cta_cte TINYINT(1) NOT NULL DEFAULT 0,  -- 2-3 estaciones con cta. cte. (definido)
  activo        TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE cargas_combustible (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fecha         DATE NOT NULL,
  camion_id     INT UNSIGNED NOT NULL,
  chofer_id     INT UNSIGNED NULL,
  estacion_id   INT UNSIGNED NULL,             -- NULL = estación eventual no listada
  estacion_otro VARCHAR(80)  NULL,             -- nombre libre si es eventual
  litros        DECIMAL(8,2) NOT NULL,
  importe       DECIMAL(12,2) NOT NULL,
  km            INT UNSIGNED NULL,             -- odómetro al cargar (alimenta consumo y services)
  modalidad     ENUM('cta_cte','contado') NOT NULL DEFAULT 'contado',
  resumen_id    INT UNSIGNED NULL,             -- se vincula al conciliar el resumen de la estación
  INDEX idx_combustible_camion (camion_id, fecha),
  FOREIGN KEY (camion_id)   REFERENCES camiones(id),
  FOREIGN KEY (chofer_id)   REFERENCES choferes(id),
  FOREIGN KEY (estacion_id) REFERENCES estaciones(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Resumen mensual de la estación con cta. cte. (mini conciliación).
-- Al pagarlo se genera un egreso en movimientos_tesoreria.
CREATE TABLE resumenes_estacion (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  estacion_id   INT UNSIGNED NOT NULL,
  periodo       CHAR(7) NOT NULL,              -- '2026-07'
  importe_total DECIMAL(12,2) NOT NULL,
  pagado        TINYINT(1) NOT NULL DEFAULT 0,
  movimiento_id INT UNSIGNED NULL,             -- egreso de tesorería al pagar
  UNIQUE KEY uk_resumen (estacion_id, periodo),
  FOREIGN KEY (estacion_id) REFERENCES estaciones(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cierre mensual por chofer: comisiones + ajuste de viáticos.
-- ajuste = MAX(gastos_reales_pagados_por_chofer - viaticos_adelantados, 0)  (definido: se repone si no alcanzó)
CREATE TABLE liquidaciones (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  chofer_id           INT UNSIGNED NOT NULL,
  periodo             CHAR(7) NOT NULL,        -- '2026-07'
  total_comisiones    DECIMAL(12,2) NOT NULL DEFAULT 0,
  viaticos_adelantados DECIMAL(12,2) NOT NULL DEFAULT 0,
  gastos_reales       DECIMAL(12,2) NOT NULL DEFAULT 0,
  ajuste_viaticos     DECIMAL(12,2) NOT NULL DEFAULT 0,
  total_pagar         DECIMAL(12,2) NOT NULL DEFAULT 0,
  estado              ENUM('abierta','cerrada','pagada') NOT NULL DEFAULT 'abierta',
  fecha_cierre        DATE NULL,
  movimiento_id       INT UNSIGNED NULL,       -- egreso de tesorería al pagarla
  UNIQUE KEY uk_liq (chofer_id, periodo),
  FOREIGN KEY (chofer_id) REFERENCES choferes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE fletes ADD FOREIGN KEY (liquidacion_id) REFERENCES liquidaciones(id);

-- ------------------------------------------------------------
-- MÓDULO 2: STOCK DE REPUESTOS Y CUBIERTAS
-- ------------------------------------------------------------

CREATE TABLE repuestos (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  codigo        VARCHAR(30)  NULL,
  nombre        VARCHAR(120) NOT NULL,
  categoria     ENUM('repuesto','cubierta') NOT NULL DEFAULT 'repuesto',
  marca         VARCHAR(60)  NULL,
  compatible_con VARCHAR(120) NULL,            -- texto libre: 'Scania G360 / todos'
  stock_actual  INT NOT NULL DEFAULT 0,        -- se mantiene por trigger o en la app
  stock_minimo  INT NOT NULL DEFAULT 0,
  ubicacion     VARCHAR(60)  NULL,
  costo_unitario DECIMAL(12,2) NULL,
  activo        TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE movimientos_stock (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  repuesto_id   INT UNSIGNED NOT NULL,
  tipo          ENUM('ingreso','egreso','ajuste') NOT NULL,
  cantidad      INT NOT NULL,                  -- siempre positivo; el tipo define el signo
  camion_id     INT UNSIGNED NULL,             -- en qué camión se usó (egresos)
  service_id    INT UNSIGNED NULL,             -- link al service si corresponde
  usuario_id    INT UNSIGNED NOT NULL,
  fecha         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  observaciones VARCHAR(200) NULL,
  FOREIGN KEY (repuesto_id) REFERENCES repuestos(id),
  FOREIGN KEY (camion_id)   REFERENCES camiones(id),
  FOREIGN KEY (usuario_id)  REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- MÓDULO 3: PALLETS (portal externo de solo lectura)
-- ------------------------------------------------------------

CREATE TABLE remitos (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  numero        INT UNSIGNED NOT NULL UNIQUE,  -- correlativo propio
  tipo          ENUM('recepcion','devolucion') NOT NULL,
  fecha         DATE NOT NULL,
  cliente_id    INT UNSIGNED NOT NULL,
  pdf_generado  TINYINT(1) NOT NULL DEFAULT 0,
  usuario_id    INT UNSIGNED NOT NULL,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Un movimiento por remito, con cantidades por estado.
-- Stock actual = SUM(recepciones) - SUM(devoluciones), por estado (vista).
CREATE TABLE pallets_movimientos (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  remito_id       INT UNSIGNED NOT NULL,
  sanos           INT NOT NULL DEFAULT 0,
  rotos           INT NOT NULL DEFAULT 0,
  reacondicionados INT NOT NULL DEFAULT 0,
  separadores     INT NOT NULL DEFAULT 0,
  observaciones   VARCHAR(200) NULL,
  FOREIGN KEY (remito_id) REFERENCES remitos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE OR REPLACE VIEW v_pallets_stock AS
SELECT
  r.cliente_id,
  SUM(IF(r.tipo='recepcion', pm.sanos,            -pm.sanos))            AS sanos,
  SUM(IF(r.tipo='recepcion', pm.rotos,            -pm.rotos))            AS rotos,
  SUM(IF(r.tipo='recepcion', pm.reacondicionados, -pm.reacondicionados)) AS reacondicionados,
  SUM(IF(r.tipo='recepcion', pm.separadores,      -pm.separadores))      AS separadores
FROM pallets_movimientos pm
JOIN remitos r ON r.id = pm.remito_id
GROUP BY r.cliente_id;

-- ------------------------------------------------------------
-- MÓDULO 4: MANTENIMIENTO
-- ------------------------------------------------------------

CREATE TABLE tipos_service (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre        VARCHAR(80) NOT NULL           -- cambio de aceite, filtros, frenos...
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE planes_mantenimiento (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  camion_id       INT UNSIGNED NOT NULL,
  tipo_service_id INT UNSIGNED NOT NULL,
  intervalo_km    INT UNSIGNED NULL,           -- ej. 30000
  intervalo_meses TINYINT UNSIGNED NULL,       -- ej. 6 (lo que ocurra primero)
  UNIQUE KEY uk_plan (camion_id, tipo_service_id),
  FOREIGN KEY (camion_id)       REFERENCES camiones(id),
  FOREIGN KEY (tipo_service_id) REFERENCES tipos_service(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE services (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  camion_id       INT UNSIGNED NOT NULL,
  tipo_service_id INT UNSIGNED NOT NULL,
  fecha           DATE NOT NULL,
  km              INT UNSIGNED NULL,
  costo           DECIMAL(12,2) NULL,
  taller          VARCHAR(80) NULL,
  observaciones   VARCHAR(255) NULL,
  FOREIGN KEY (camion_id)       REFERENCES camiones(id),
  FOREIGN KEY (tipo_service_id) REFERENCES tipos_service(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE movimientos_stock ADD FOREIGN KEY (service_id) REFERENCES services(id);

-- Próximo vencimiento = último service (km/fecha) + intervalo del plan,
-- comparado contra camiones.km_actual → semáforo en el dashboard.

-- ------------------------------------------------------------
-- MÓDULO 5: CHEQUES Y TESORERÍA
-- ------------------------------------------------------------

-- Cuentas de dinero: los DOS bancos (definido) + caja en efectivo.
CREATE TABLE cuentas (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo          ENUM('banco','caja') NOT NULL,
  nombre        VARCHAR(80) NOT NULL,          -- 'Banco Macro CC $', 'Caja efectivo'
  saldo_inicial DECIMAL(14,2) NOT NULL DEFAULT 0,
  activo        TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Las financieras varían (definido): ABM simple, se cargan a medida que aparecen.
CREATE TABLE financieras (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre        VARCHAR(80) NOT NULL,
  contacto      VARCHAR(80) NULL,
  activo        TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE cheques (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  tipo            ENUM('recibido','emitido') NOT NULL,
  formato         ENUM('fisico','echeq') NOT NULL DEFAULT 'fisico',  -- ambos (definido)
  numero          VARCHAR(30) NOT NULL,
  banco_librador  VARCHAR(80) NULL,            -- banco del cheque recibido (texto libre)
  cuenta_id       INT UNSIGNED NULL,           -- cheques EMITIDOS: contra cuál de nuestros bancos
  cliente_id      INT UNSIGNED NULL,           -- recibidos: quién nos lo dio
  flete_id        INT UNSIGNED NULL,           -- recibidos: a qué flete corresponde (opcional)
  destinatario    VARCHAR(120) NULL,           -- emitidos/endosados: a quién se entregó
  importe         DECIMAL(12,2) NOT NULL,
  fecha_emision   DATE NOT NULL,
  fecha_pago      DATE NOT NULL,               -- fecha de cobro/débito (diferido o al día)
  estado          ENUM('en_cartera','depositado','acreditado','rechazado','recuperado',
                       'vendido','endosado','emitido','debitado') NOT NULL,
  cuenta_deposito_id INT UNSIGNED NULL,        -- dónde se depositó
  financiera_id   INT UNSIGNED NULL,           -- si se vendió: a quién
  monto_neto_venta DECIMAL(12,2) NULL,         -- lo efectivamente recibido al venderlo
  observaciones   VARCHAR(255) NULL,
  creado_en       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_cheques_estado (estado, fecha_pago),
  FOREIGN KEY (cuenta_id)          REFERENCES cuentas(id),
  FOREIGN KEY (cliente_id)         REFERENCES clientes(id),
  FOREIGN KEY (flete_id)           REFERENCES fletes(id),
  FOREIGN KEY (cuenta_deposito_id) REFERENCES cuentas(id),
  FOREIGN KEY (financiera_id)      REFERENCES financieras(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Trazabilidad: cada cambio de estado queda registrado (pedido explícito del cliente).
CREATE TABLE cheques_movimientos (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cheque_id       INT UNSIGNED NOT NULL,
  estado_anterior VARCHAR(20) NULL,
  estado_nuevo    VARCHAR(20) NOT NULL,
  fecha           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  usuario_id      INT UNSIGNED NOT NULL,
  gastos_asociados DECIMAL(12,2) NULL,         -- rechazo: comisiones/gastos bancarios (definido: SÍ)
  observaciones   VARCHAR(200) NULL,
  FOREIGN KEY (cheque_id)  REFERENCES cheques(id) ON DELETE CASCADE,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Todo ingreso/egreso de plata pasa por acá (cheque acreditado/vendido,
-- pago de resumen de estación, liquidación de chofer, gastos varios).
CREATE TABLE movimientos_tesoreria (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  fecha           DATE NOT NULL,
  cuenta_id       INT UNSIGNED NOT NULL,
  tipo            ENUM('ingreso','egreso') NOT NULL,
  categoria_id    INT UNSIGNED NULL,
  importe         DECIMAL(12,2) NOT NULL,
  referencia_tipo ENUM('cheque','flete','liquidacion','resumen_estacion','stock','otro') NULL,
  referencia_id   INT UNSIGNED NULL,           -- id en la tabla referenciada
  descripcion     VARCHAR(200) NULL,
  usuario_id      INT UNSIGNED NOT NULL,
  INDEX idx_tesoreria_fecha (cuenta_id, fecha),
  FOREIGN KEY (cuenta_id)    REFERENCES cuentas(id),
  FOREIGN KEY (categoria_id) REFERENCES categorias_gasto(id),
  FOREIGN KEY (usuario_id)   REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE resumenes_estacion ADD FOREIGN KEY (movimiento_id) REFERENCES movimientos_tesoreria(id);
ALTER TABLE liquidaciones      ADD FOREIGN KEY (movimiento_id) REFERENCES movimientos_tesoreria(id);

-- Posición financiera de un vistazo (saldo + por entrar + por salir):
CREATE OR REPLACE VIEW v_posicion AS
SELECT
  (SELECT COALESCE(SUM(saldo_inicial),0) FROM cuentas WHERE activo=1)
  + (SELECT COALESCE(SUM(IF(tipo='ingreso',importe,-importe)),0) FROM movimientos_tesoreria) AS saldo_actual,
  (SELECT COALESCE(SUM(importe),0) FROM cheques WHERE tipo='recibido' AND estado IN ('en_cartera','depositado')) AS por_entrar,
  (SELECT COALESCE(SUM(importe),0) FROM cheques WHERE tipo='emitido'  AND estado='emitido') AS por_salir;

SET FOREIGN_KEY_CHECKS = 1;

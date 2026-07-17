-- ============================================================
-- MIGRACIÓN FASE 3 — segundo ajuste autorizado (consultado y
-- aprobado aparte del ALTER de remitos): tabla nueva y separada
-- para las cuentas del portal externo, vinculadas a un cliente y
-- preparada para más de un usuario por cliente. No toca la tabla
-- `usuarios` interna (admin/taller) — el portal usa sesión propia
-- y ahora también almacenamiento propio.
-- ============================================================

CREATE TABLE usuarios_portal (
  id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cliente_id        INT UNSIGNED NOT NULL,
  nombre            VARCHAR(80)  NOT NULL,
  usuario           VARCHAR(40)  NOT NULL UNIQUE,
  clave_hash        VARCHAR(255) NOT NULL,
  activo            TINYINT(1) NOT NULL DEFAULT 1,
  intentos_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado_hasta   DATETIME NULL,
  creado_en         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cliente_id) REFERENCES clientes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

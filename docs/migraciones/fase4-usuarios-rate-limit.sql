-- ============================================================
-- MIGRACIÓN FASE 4 — ajuste autorizado (auditoría post Fase 4):
-- paridad de bloqueo por intentos fallidos entre el login interno
-- (admin/taller) y el del portal externo, que ya lo tenía desde la
-- Fase 3 (ver fase3-usuarios-portal.sql). Mismas columnas, mismo
-- comportamiento atómico en dos pasos en includes/auth.php.
-- ============================================================

ALTER TABLE usuarios
  ADD COLUMN intentos_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER activo,
  ADD COLUMN bloqueado_hasta   DATETIME NULL AFTER intentos_fallidos;

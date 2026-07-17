-- ============================================================
-- MIGRACIÓN FASE 3 — único ajuste autorizado al esquema aprobado.
-- El remito real (docs/remito-actual.jpg.jpeg) registra datos que
-- el esquema original no contemplaba. Correr también en el VPS.
-- ============================================================

ALTER TABLE remitos
  ADD COLUMN transporte_origen VARCHAR(120) NULL AFTER cliente_id,   -- quién entrega (puede ser un tercero)
  ADD COLUMN transporte_cuit   VARCHAR(15)  NULL AFTER transporte_origen,
  ADD COLUMN chofer_nombre     VARCHAR(80)  NULL AFTER transporte_cuit,
  ADD COLUMN chofer_dni        VARCHAR(15)  NULL AFTER chofer_nombre,
  ADD COLUMN hoja_ruta         VARCHAR(40)  NULL AFTER chofer_dni,
  ADD COLUMN documentacion     VARCHAR(150) NULL AFTER hoja_ruta,
  ADD COLUMN peajes            VARCHAR(100) NULL AFTER documentacion;

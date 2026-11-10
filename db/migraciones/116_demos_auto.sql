-- =====================================================================
-- 116 — Demos automáticas de 3 días (decisión de Dimitry, 10 nov 2026).
--   El formulario «Solicitar demo» pide los 7 datos completos: nombre, nombre comercial (columna negocio), teléfono, celular
--   (columna whatsapp), dirección, ciudad y correo. Aquí se agregan las dos columnas que faltaban: teléfono y dirección.
--   Los días de demo pasan de 7 a 3 UNA sola vez (marca 'demo_3_dias' en ajustes) y solo si seguían en 7: si Tukán ya los
--   cambió desde el panel, no se pisa.
-- Se puede correr muchas veces.
-- =====================================================================
SET client_min_messages = warning;

ALTER TABLE pedidos_demo ADD COLUMN IF NOT EXISTS telefono  VARCHAR(30);
ALTER TABLE pedidos_demo ADD COLUMN IF NOT EXISTS direccion VARCHAR(250);

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM ajustes WHERE clave = 'demo_3_dias') THEN
        UPDATE ajustes SET valor = '3' WHERE clave = 'dias_prueba' AND valor = '7';
        INSERT INTO ajustes (clave, valor) VALUES ('demo_3_dias', '2026-11-10');
    END IF;
END $$;

-- =====================================================================
-- 119 — Asistente IA a $3 al mes en el Básico (decisión de Dimitry, 12 nov 2026; manda la hoja oficial «Precios y planes Tukán»).
--   El catálogo de módulos (src/Modulos.php) todavía decía $5 en el Asistente IA. La migración 113 ya lo había puesto en $3 en
--   la base, pero si en una instalación quedó en $5 (por ejemplo una base anterior a la 113 o un valor viejo guardado), aquí se corrige.
-- Se pone UNA sola vez (marca 'asistente_ia_3' en ajustes) y solo si seguía en $5: si Dimitry lo cambió a otro valor desde el
-- Panel Tukán Central, no se pisa. No toca el precio acordado de ningún cliente.
-- =====================================================================
SET client_min_messages = warning;

DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM ajustes WHERE clave = 'asistente_ia_3') THEN
        UPDATE modulos SET precio_mensual = 3.00, actualizado = now() WHERE id = 'asistente_ia' AND precio_mensual = 5.00;
        INSERT INTO ajustes (clave, valor) VALUES ('asistente_ia_3', '2026-11-12');
    END IF;
END $$;

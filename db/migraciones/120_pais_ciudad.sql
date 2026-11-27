-- =====================================================================
-- 120 — País y ciudad en los datos del cliente y del negocio (27 nov 2026, regla de Dimitry «exterior = sin IVA»).
--   Con cédula o RUC ecuatoriano válido siempre se factura con IVA; con otro país y sin cédula/RUC válido, la factura
--   y el cobro Plux salen sin IVA. El país por defecto es Ecuador.
--   - clientes (los clientes de los eventos): pais y ciudad. La identificación admite pasaporte (hasta 20).
--   - negocios (quien paga el plan con Plux): pais. La ciudad ya existía.
-- Se puede correr muchas veces. NO cambia ninguna fila existente (las filas viejas quedan en Ecuador por el valor por defecto).
-- =====================================================================
SET client_min_messages = warning;

ALTER TABLE clientes ADD COLUMN IF NOT EXISTS pais   VARCHAR(60) NOT NULL DEFAULT 'Ecuador';
ALTER TABLE clientes ADD COLUMN IF NOT EXISTS ciudad VARCHAR(80);
ALTER TABLE negocios ADD COLUMN IF NOT EXISTS pais   VARCHAR(60) NOT NULL DEFAULT 'Ecuador';

-- La identificación de un cliente o negocio del exterior puede ser un pasaporte (hasta 20 caracteres): solo se ensancha, no cambia datos
DO $$
BEGIN
    IF (SELECT character_maximum_length FROM information_schema.columns WHERE table_name = 'clientes' AND column_name = 'identificacion' AND table_schema = current_schema()) < 20 THEN
        ALTER TABLE clientes ALTER COLUMN identificacion TYPE VARCHAR(20);
    END IF;
    IF (SELECT character_maximum_length FROM information_schema.columns WHERE table_name = 'negocios' AND column_name = 'ruc' AND table_schema = current_schema()) < 20 THEN
        ALTER TABLE negocios ALTER COLUMN ruc TYPE VARCHAR(20);
    END IF;
END $$;

-- =====================================================================
-- 117 — Subir de plan sin suscripción con tarjeta (decisión de Dimitry, 10 nov 2026, 18:26).
--   El cliente que ya pagó su período (por transferencia, por ejemplo) y quiere pasar al Completo paga con tarjeta solo el
--   PRORRATEO de los días que le faltan. Cuando el Panel Tukán Central dice que ese cobro está pagado, «Mi plan» le pone el plan
--   nuevo UNA sola vez: aquí se anota qué cobros ya se aplicaron. El vencimiento no cambia.
-- Se puede correr muchas veces.
-- =====================================================================
SET client_min_messages = warning;

CREATE TABLE IF NOT EXISTS plan_mejoras (
    negocio_id            INT NOT NULL REFERENCES negocios(id) ON DELETE CASCADE,
    cobro_id              BIGINT NOT NULL,               -- número del cobro en el Panel Tukán Central
    plan_codigo           VARCHAR(20) NOT NULL,          -- el plan que se le puso
    monto                 NUMERIC(10,2) NOT NULL DEFAULT 0,   -- lo que pagó sin IVA (0 = la diferencia era menor a $1 y no se cobró)
    aplicado              TIMESTAMP NOT NULL DEFAULT now(),
    PRIMARY KEY (negocio_id, cobro_id)
);

-- =====================================================================
-- 118 — Cambios de plan con prorrateo que están esperando el pago con tarjeta (decisión de Dimitry, 10 nov 2026, 19:12).
--   Cuando el dueño toca «Pagar con tarjeta» en «Pasar al Completo», aquí se anota su negocio. La tarea de cada hora
--   (bin/tareas.php) le pregunta al Panel Tukán Central solo por esos negocios y, si el cobro ya está pagado, pone el plan
--   (antes solo se ponía al abrir Mi plan). Se borra al aplicarse o a los 7 días.
-- Se puede correr muchas veces.
-- =====================================================================
SET client_min_messages = warning;

CREATE TABLE IF NOT EXISTS plan_mejoras_pedidas (
    negocio_id            INT PRIMARY KEY REFERENCES negocios(id) ON DELETE CASCADE,
    pedida                TIMESTAMP NOT NULL DEFAULT now()
);

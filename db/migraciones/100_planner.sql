-- =====================================================================
-- TuPlanner 100: lo propio del organizador de bodas y eventos
--  - Lugares (antes "espacios"): dirección, mapa y contacto de la quinta, hacienda o iglesia
--  - Tipos de evento y categorías nuevas (pedida de mano, baby shower, flores, vestuario, honorarios…)
--  - Lista de tareas por meses: para el organizador o para el cliente (se marca en el portal)
--  - Presupuesto del evento por proveedor: quién paga (cliente directo o el organizador = reventa),
--    lo estimado, lo contratado, los pagos y la comisión del proveedor
--  - Honorarios del organizador: tarifa fija o porcentaje del presupuesto
--  - Ideas (tablero de inspiración) con fotos y paleta de colores
--  - Máximo de eventos por día del equipo (aviso, no bloqueo)
-- Se puede correr muchas veces.
-- =====================================================================
BEGIN;

-- Lugares
ALTER TABLE espacios ADD COLUMN IF NOT EXISTS direccion VARCHAR(250);
ALTER TABLE espacios ADD COLUMN IF NOT EXISTS mapa_url  VARCHAR(400);
ALTER TABLE espacios ADD COLUMN IF NOT EXISTS contacto  VARCHAR(100);
ALTER TABLE espacios ADD COLUMN IF NOT EXISTS telefono  VARCHAR(20);
ALTER TABLE espacios ADD COLUMN IF NOT EXISTS tipo_lugar VARCHAR(12) NOT NULL DEFAULT 'recepcion';
ALTER TABLE espacios DROP CONSTRAINT IF EXISTS espacios_tipo_lugar_check;
ALTER TABLE espacios ADD CONSTRAINT espacios_tipo_lugar_check CHECK (tipo_lugar IN ('recepcion','ceremonia','ambos','otro'));

-- Reglas del organizador
ALTER TABLE negocios ADD COLUMN IF NOT EXISTS max_eventos_dia  INT NOT NULL DEFAULT 2;
ALTER TABLE negocios ADD COLUMN IF NOT EXISTS honorario_modo   VARCHAR(12) NOT NULL DEFAULT 'fija';
ALTER TABLE negocios ADD COLUMN IF NOT EXISTS honorario_pct    NUMERIC(5,2) NOT NULL DEFAULT 10;
ALTER TABLE negocios ADD COLUMN IF NOT EXISTS comision_pct     NUMERIC(5,2) NOT NULL DEFAULT 10;
ALTER TABLE negocios DROP CONSTRAINT IF EXISTS negocios_honorario_modo_check;
ALTER TABLE negocios ADD CONSTRAINT negocios_honorario_modo_check CHECK (honorario_modo IN ('fija','porcentaje'));
ALTER TABLE negocios DROP CONSTRAINT IF EXISTS negocios_max_eventos_dia_check;
ALTER TABLE negocios ADD CONSTRAINT negocios_max_eventos_dia_check CHECK (max_eventos_dia BETWEEN 1 AND 50);
ALTER TABLE negocios ALTER COLUMN color SET DEFAULT '#A0526D';

-- Tipos de evento
ALTER TABLE eventos DROP CONSTRAINT IF EXISTS eventos_tipo_check;
ALTER TABLE eventos ADD CONSTRAINT eventos_tipo_check CHECK (tipo IN ('boda','quince','pedida','baby_shower','despedida','graduacion','bautizo',
    'primera_comunion','cumpleanos','corporativo','aniversario','otro'));
-- Honorarios del evento (se proponen con los del negocio) y paleta de colores
ALTER TABLE eventos ADD COLUMN IF NOT EXISTS honorario_modo VARCHAR(12) NOT NULL DEFAULT 'fija';
ALTER TABLE eventos ADD COLUMN IF NOT EXISTS honorario_pct  NUMERIC(5,2) NOT NULL DEFAULT 10;
ALTER TABLE eventos DROP CONSTRAINT IF EXISTS eventos_honorario_modo_check;
ALTER TABLE eventos ADD CONSTRAINT eventos_honorario_modo_check CHECK (honorario_modo IN ('fija','porcentaje'));
ALTER TABLE eventos ADD COLUMN IF NOT EXISTS paleta VARCHAR(80);          -- hasta 6 colores "#aabbcc,#..."
ALTER TABLE eventos ADD COLUMN IF NOT EXISTS tematica VARCHAR(150);       -- "Rústico con flores blancas"

-- Categorías del catálogo (también son las del presupuesto y los servicios de los proveedores)
ALTER TABLE productos DROP CONSTRAINT IF EXISTS productos_categoria_check;
ALTER TABLE productos ADD CONSTRAINT productos_categoria_check CHECK (categoria IN ('honorarios','espacio','menu','bebidas','pasteleria','decoracion',
    'flores','musica','foto_video','vestuario','belleza','invitaciones','transporte','mobiliario','personal','transmision','otros'));

-- Tareas: para quién es y de qué trata
ALTER TABLE plantilla_tareas ADD COLUMN IF NOT EXISTS asignado VARCHAR(10) NOT NULL DEFAULT 'planner';
ALTER TABLE plantilla_tareas DROP CONSTRAINT IF EXISTS plantilla_tareas_asignado_check;
ALTER TABLE plantilla_tareas ADD CONSTRAINT plantilla_tareas_asignado_check CHECK (asignado IN ('planner','cliente'));
ALTER TABLE evento_tareas ADD COLUMN IF NOT EXISTS asignado VARCHAR(10) NOT NULL DEFAULT 'planner';
ALTER TABLE evento_tareas DROP CONSTRAINT IF EXISTS evento_tareas_asignado_check;
ALTER TABLE evento_tareas ADD CONSTRAINT evento_tareas_asignado_check CHECK (asignado IN ('planner','cliente'));
ALTER TABLE evento_tareas ADD COLUMN IF NOT EXISTS hecha_por VARCHAR(10);  -- planner o cliente (quién la marcó)
ALTER TABLE evento_tareas ADD COLUMN IF NOT EXISTS notas VARCHAR(300);

-- Presupuesto del evento: una partida por cosa que hay que contratar
CREATE TABLE IF NOT EXISTS presupuesto_partidas (
    id                SERIAL PRIMARY KEY,
    negocio_id        INT NOT NULL REFERENCES negocios(id) ON DELETE CASCADE,
    evento_id         INT NOT NULL REFERENCES eventos(id) ON DELETE CASCADE,
    categoria         VARCHAR(20) NOT NULL DEFAULT 'otros',
    concepto          VARCHAR(150) NOT NULL,
    proveedor_id      INT REFERENCES proveedores(id) ON DELETE SET NULL,
    proveedor_nombre  VARCHAR(150),               -- se copia (o lo escribe el organizador si no está en su lista)
    estado            VARCHAR(12) NOT NULL DEFAULT 'idea'
                      CHECK (estado IN ('idea','cotizando','contratado','cancelado')),
    -- cliente = el cliente le paga directo al proveedor; planner = va en el contrato del organizador (reventa)
    paga              VARCHAR(10) NOT NULL DEFAULT 'cliente' CHECK (paga IN ('cliente','planner')),
    estimado          NUMERIC(12,2) NOT NULL DEFAULT 0 CHECK (estimado >= 0),
    contratado        NUMERIC(12,2) CHECK (contratado >= 0),     -- precio final acordado con el proveedor
    vence_pago        DATE,                                      -- cuándo hay que pagarle (lo que falta)
    -- Comisión que el proveedor le paga al organizador por traerle el cliente
    comision_pct      NUMERIC(5,2) NOT NULL DEFAULT 0 CHECK (comision_pct BETWEEN 0 AND 100),
    comision_monto    NUMERIC(12,2) CHECK (comision_monto >= 0), -- NULL = se calcula con el %
    comision_cobrada  NUMERIC(12,2) NOT NULL DEFAULT 0 CHECK (comision_cobrada >= 0),
    comision_cobrada_en DATE,
    visible_cliente   BOOLEAN NOT NULL DEFAULT true,
    notas             VARCHAR(500),
    orden             INT NOT NULL DEFAULT 0,
    creado_en         TIMESTAMP NOT NULL DEFAULT now(),
    actualizado_en    TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS presupuesto_partidas_evento ON presupuesto_partidas (evento_id, orden);
CREATE INDEX IF NOT EXISTS presupuesto_partidas_comision ON presupuesto_partidas (negocio_id) WHERE comision_pct > 0 OR comision_monto > 0;

-- Pagos que hace el CLIENTE directo a un proveedor (el organizador o el cliente los anotan para llevar la cuenta)
CREATE TABLE IF NOT EXISTS presupuesto_pagos (
    id                SERIAL PRIMARY KEY,
    negocio_id        INT NOT NULL REFERENCES negocios(id) ON DELETE CASCADE,
    partida_id        INT NOT NULL REFERENCES presupuesto_partidas(id) ON DELETE CASCADE,
    fecha             DATE NOT NULL DEFAULT CURRENT_DATE,
    monto             NUMERIC(12,2) NOT NULL CHECK (monto > 0),
    metodo            VARCHAR(15) NOT NULL DEFAULT 'transferencia',
    nota              VARCHAR(200),
    origen            VARCHAR(10) NOT NULL DEFAULT 'planner' CHECK (origen IN ('planner','cliente')),
    registrado_por    INT REFERENCES usuarios(id) ON DELETE SET NULL,
    creado_en         TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS presupuesto_pagos_partida ON presupuesto_pagos (partida_id);

-- Ideas: tablero de inspiración (fotos del organizador y del cliente)
CREATE TABLE IF NOT EXISTS evento_ideas (
    id                SERIAL PRIMARY KEY,
    negocio_id        INT NOT NULL REFERENCES negocios(id) ON DELETE CASCADE,
    evento_id         INT NOT NULL REFERENCES eventos(id) ON DELETE CASCADE,
    archivo           VARCHAR(60) NOT NULL,       -- uploads/ideas
    categoria         VARCHAR(20) NOT NULL DEFAULT 'decoracion',
    nota              VARCHAR(300),
    origen            VARCHAR(10) NOT NULL DEFAULT 'planner' CHECK (origen IN ('planner','cliente')),
    aprobada          BOOLEAN,                    -- NULL = sin decidir; el cliente o el organizador la marcan
    creado_en         TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS evento_ideas_evento ON evento_ideas (evento_id, creado_en);

COMMIT;

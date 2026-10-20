#!/usr/bin/env python3
"""Capturas reales de TuPlanner con el negocio de ejemplo (docs/manual/generar/datos.php) para el manual.
Uso: python3 docs/manual/generar/capturas.py --base http://127.0.0.1:8240 --salida docs/manual/capturas --ids ids.json [--solo nombre1,nombre2]
Entra como dueño, coordinador, cliente (portal) y visitante, y guarda cada pantalla en celular (390 px) o computadora (1280 px).
Reglas de la casa: en «Mi plan» y en «No está en tu plan» se esconden los precios del programa (siempre es cotización)."""
import argparse, json, os, subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright

ap = argparse.ArgumentParser()
ap.add_argument('--base', required=True)
ap.add_argument('--salida', required=True)
ap.add_argument('--ids', required=True)
ap.add_argument('--clave', default='PlannerDemo2026')
ap.add_argument('--solo', default='')
a = ap.parse_args()
SAL = Path(a.salida); SAL.mkdir(parents=True, exist_ok=True)
SOLO = set(x for x in a.solo.split(',') if x)
B = a.base.rstrip('/')
I = json.loads(Path(a.ids).read_text())


def q(sql):
    return subprocess.run(['psql', '-h', '127.0.0.1', '-U', os.environ.get('TUPLANNER_DB_USER', 'tuplanner'), '-d', os.environ['TUPLANNER_DB_NAME'], '-tAc', sql],
                          capture_output=True, text=True, env={**os.environ, 'PGPASSWORD': os.environ.get('TUPLANNER_DB_PASS', 'prueba')}).stdout.strip()


D = dict(I)
D['tok_boda'] = q(f"SELECT token_cliente FROM eventos WHERE id={I['boda']}")
D['cot_baby'] = q(f"SELECT token FROM cotizaciones WHERE evento_id={I['baby']} ORDER BY id DESC LIMIT 1")
D['firmar_quince'] = q(f"SELECT token FROM contratos WHERE evento_id={I['quince']} AND estado='enviado' LIMIT 1")
D['partida_foto'] = q(f"SELECT id FROM presupuesto_partidas WHERE evento_id={I['boda']} AND categoria='foto_video' LIMIT 1")

SIN_PRECIOS = ".mp-precio,.mp-desglose,.mp-mod-precio,.mod-apagado-precio{visibility:hidden!important}"

# (archivo, quién entra, ruta, ancho, acciones, página completa)
#   acciones: 'ir:<selector>' baja hasta ese elemento · 'abrir:<selector>' abre ese <details> · 'sin_precios' · 'elem:<selector>' foto solo de ese elemento
PANTALLAS = [
    ('01_login', 'nadie', '/?r=login', 390, [], False),
    ('02_inicio_compu', 'dueno', '/?r=inicio', 1280, [], False),
    ('03_inicio_celular', 'dueno', '/?r=inicio', 390, [], False),
    ('04_primeros_pasos', 'esencial', '/?r=inicio', 1280, ['ir:.g-pasos'], False),
    ('05_usuarios', 'dueno', '/?r=usuarios', 1280, [], False),
    ('06_config_reglas', 'dueno', '/?r=configuracion&tab=reglas', 1280, ['ir:#reserva_pct'], False),
    ('07_lugares', 'dueno', '/?r=espacios', 1280, [], False),
    ('08_catalogo', 'dueno', '/?r=catalogo', 1280, [], False),
    ('09_config_tareas', 'dueno', '/?r=configuracion&tab=tareas', 1280, [], False),
    ('10_calendario', 'dueno', '/?r=calendario', 1280, [], False),
    ('11_eventos_tablero', 'dueno', '/?r=eventos&vista=tablero', 1280, [], False),
    ('12_evento_nuevo', 'dueno', '/?r=evento_nuevo', 1280, [], False),
    ('13_evento_resumen', 'dueno', '/?r=evento&id={boda}', 1280, [], False),
    ('14_plan_dinero', 'dueno', '/?r=evento_plan&id={boda}', 1280, [], False),
    ('15_plan_presupuesto', 'dueno', '/?r=evento_plan&id={boda}', 1280, ['ir:#presupuesto'], False),
    ('16_plan_partida', 'dueno', '/?r=evento_plan&id={boda}', 1280, ['abrir:#partida-{partida_foto} details.pn-detalle', 'ir:#partida-{partida_foto}'], False),
    ('17_plan_tareas', 'dueno', '/?r=evento_plan&id={boda}', 1280, ['ir:#tareas'], False),
    ('18_plan_celular', 'dueno', '/?r=evento_plan&id={boda}', 390, ['ir:#presupuesto'], False),
    ('19_comisiones', 'dueno', '/?r=comisiones', 1280, [], False),
    ('20_ideas', 'dueno', '/?r=evento_ideas&id={boda}', 1280, [], False),
    ('21_cotizacion', 'dueno', '/?r=cotizacion&id={baby}', 1280, [], False),
    ('22_cotizacion_cliente', 'publico', '/?r=cot&t={cot_baby}', 390, [], False),
    ('23_contrato', 'dueno', '/?r=contrato&id={boda}', 1280, [], False),
    ('24_firmar_celular', 'publico', '/?r=firmar&t={firmar_quince}', 390, [], False),
    ('25_pagos_evento', 'dueno', '/?r=evento_pagos&id={quince}', 1280, [], False),
    ('26_portal', 'publico', '/?r=portal&t={tok_boda}', 390, [], False),
    ('27_portal_plan', 'publico', '/?r=portal_plan&t={tok_boda}', 390, [], False),
    ('28_cobros', 'dueno', '/?r=cobros', 1280, [], False),
    ('29_mensajes', 'dueno', '/?r=mensajes', 1280, [], False),
    ('30_seguimiento', 'dueno', '/?r=seguimiento', 1280, [], False),
    ('31_reportes', 'dueno', '/?r=reportes', 1280, [], False),
    ('32_dia_evento', 'dueno', '/?r=evento_dia&id={cerca}', 1280, [], False),
    ('33_mi_plan', 'dueno', '/?r=planes', 1280, ['sin_precios', 'ir:#mp-t-mods'], False),
    ('34_modulo_apagado', 'esencial', '/?r=evento_plan&id={esencial_evento}', 1280, ['sin_precios'], False),
    ('35_sitio_celular', 'publico', '/?r=sitio&s={slug}', 390, [], False),
    ('36_solicitar_demo', 'nadie', '/?r=registro', 1280, [], False),
]

CUENTAS = {'dueno': 'demo@tuplanner.tukanec.com', 'esencial': 'esencial@tuplanner.tukanec.com', 'coordinador': 'diego@tuplanner.tukanec.com'}

with sync_playwright() as p:
    nav = p.chromium.launch(args=['--lang=es-EC'])
    sesiones = {}

    def contexto(quien, ancho):
        k = (quien, ancho)
        if k in sesiones:
            return sesiones[k]
        movil = ancho < 600
        ctx = nav.new_context(viewport={'width': ancho, 'height': 844 if movil else 820}, device_scale_factor=2 if movil else 1.5,
                              locale='es-EC', timezone_id='America/Guayaquil', is_mobile=movil, has_touch=movil)
        pg = ctx.new_page()
        if quien in CUENTAS:
            pg.goto(B + '/?r=login'); pg.fill('input[name=email]', CUENTAS[quien]); pg.fill('input[name=clave]', a.clave)
            pg.click('form button[type=submit]'); pg.wait_for_load_state('networkidle')
        sesiones[k] = pg
        return pg

    for nombre, quien, ruta, ancho, acciones, completa in PANTALLAS:
        if SOLO and nombre not in SOLO:
            continue
        pg = contexto(quien, ancho)
        pg.goto(B + ruta.format(**D)); pg.wait_for_load_state('networkidle')
        elem = None
        for acc in acciones:
            acc = acc.format(**D)
            if acc == 'sin_precios':
                pg.add_style_tag(content=SIN_PRECIOS)
            elif acc.startswith('ir:'):
                pg.evaluate("(s) => { const e = document.querySelector(s); if (e) window.scrollTo(0, e.getBoundingClientRect().top + window.scrollY - 12); }", acc[3:])
            elif acc.startswith('abrir:'):
                pg.evaluate("(s) => { const e = document.querySelector(s); if (e) e.open = true; }", acc[6:])
            elif acc.startswith('elem:'):
                elem = acc[5:]
        pg.wait_for_timeout(400)
        destino = str(SAL / f'{nombre}.png')
        if elem:
            pg.locator(elem).first.screenshot(path=destino)
        else:
            pg.screenshot(path=destino, full_page=completa)
        print('captura', nombre)
    nav.close()

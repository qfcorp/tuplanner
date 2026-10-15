<?php
/** Cronograma del evento: /?r=pdf&tipo=cronograma&id=<evento>. Recibe $u, $negocio, $id. */
use TuPlanner\{DiaEvento, Pdf, PdfDia};

if (!requiere_quinta($u, 'Cronograma', 'El cronograma minuto a minuto con modo en vivo es del plan Agencia.')) return;
$dia = new DiaEvento(db());
$ev = $dia->evento((int) $u['negocio_id'], $id);
Pdf::enviar(PdfDia::cronograma($negocio, $ev, $dia->cronograma((int) $u['negocio_id'], $id), url_completa('vivo', ['t' => $ev['token_vivo']])), 'cronograma-' . $ev['codigo'] . '.pdf');

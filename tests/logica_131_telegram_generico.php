<?php
/**
 * Avisos por Telegram con el bot GENÉRICO «Tukán Software» (decisión de Dimitry, 12 nov 2026): TELEGRAM_PREFIJO=tn.
 * El enlace de conectar lleva «tn_código», el programa acepta el /start con y sin prefijo (compatibilidad con su bot propio),
 * un /start con el prefijo de OTRO programa no conecta a nadie, y sin TELEGRAM_PREFIJO todo sigue como antes.
 * Telegram es un «servidor» de archivos que contesta {"ok":true}: no sale nada a internet.
 */
use TuPlanner\Telegram;

$db = base_limpia();
[$nid, $uDueno] = negocio_prueba($db);
$tgDir = sys_get_temp_dir() . '/tg_generico_' . getmypid();
@mkdir("$tgDir/botT", 0777, true);
file_put_contents("$tgDir/botT/sendMessage", '{"ok":true}');
putenv('TELEGRAM_BOT_TOKEN=T'); putenv('TELEGRAM_BOT_USERNAME=TukanSoftwareBot'); putenv("TELEGRAM_API_BASE=file://$tgDir");
putenv('TELEGRAM_PREFIJO');
$chatDe = fn(int $uid) => $db->query("SELECT telegram_chat_id FROM usuarios WHERE id = $uid")->fetchColumn();
$tg = new Telegram($db);
$uid = (int) $uDueno['id'];

titulo('Telegram con el bot genérico (TELEGRAM_PREFIJO=tn)');
check('Bot propio (sin TELEGRAM_PREFIJO): el parámetro del /start no lleva prefijo', [false, 'abc'], [Telegram::botGenerico(), Telegram::inicio('abc')]);
$enl = $tg->enlaceConectar($uid);
check('Bot propio: el enlace es t.me/<bot>?start=<código> de 24 hexadecimales', 1, preg_match('~^https://t\.me/TukanSoftwareBot\?start=[a-f0-9]{24}$~', (string) $enl));
putenv('TELEGRAM_PREFIJO=tn');
check('Bot genérico: el parámetro del /start lleva el prefijo «tn»', [true, 'tn_abc'], [Telegram::botGenerico(), Telegram::inicio('abc')]);
putenv('TELEGRAM_PREFIJO=zz');
check('Un prefijo de otro programa no enciende el modo genérico', false, Telegram::botGenerico());
putenv('TELEGRAM_PREFIJO=tn');
$enl = $tg->enlaceConectar($uid);
check('Genérico: «Conectar mi Telegram» da t.me/<bot>?start=tn_<código>', 1, preg_match('~^https://t\.me/TukanSoftwareBot\?start=tn_[a-f0-9]{24}$~', (string) $enl));
$codigo = substr((string) $enl, strpos((string) $enl, '?start=tn_') + strlen('?start=tn_'));
$tg->procesar(['message' => ['chat' => ['id' => 7771], 'text' => "/start tn_$codigo"]]);
check('Genérico: /start tn_código conecta al usuario', '7771', (string) $chatDe($uid));
$tg->procesar(['message' => ['chat' => ['id' => 7773], 'text' => "/start tn_$codigo"]]);
check('...y el mismo enlace no sirve dos veces', '7771', (string) $chatDe($uid));
$db->exec("UPDATE usuarios SET telegram_chat_id = NULL WHERE id = $uid");
$enl2 = $tg->enlaceConectar($uid);
$tg->procesar(['message' => ['chat' => ['id' => 7772], 'text' => '/start ' . substr((string) $enl2, strpos((string) $enl2, '=') + 1 + strlen('tn_'))]]);
check('Genérico: el /start viejo, sin prefijo, también conecta (compatibilidad)', '7772', (string) $chatDe($uid));
$db->exec("UPDATE usuarios SET telegram_chat_id = NULL WHERE id = $uid");
$enl3 = $tg->enlaceConectar($uid);
$tg->procesar(['message' => ['chat' => ['id' => 7774], 'text' => '/start zz_' . substr((string) $enl3, strpos((string) $enl3, '=') + 1 + strlen('tn_'))]]);
check('Un /start con el prefijo de OTRO programa no conecta a nadie', null, $chatDe($uid) ?: null);
$tg->procesar(['message' => ['chat' => ['id' => 7775], 'text' => "/start tn_nocodigo"]]);
check('Un /start con código inválido no conecta a nadie y no falla', null, $chatDe($uid) ?: null);
$tg->procesar(['message' => ['chat' => ['id' => 7776], 'text' => "/start tn_" . substr((string) $enl3, strpos((string) $enl3, '=') + 1 + strlen('tn_'))]]);
check('Con el prefijo bueno y el código vigente, conecta', '7776', (string) $chatDe($uid));
putenv('TELEGRAM_PREFIJO');
check('Vuelve al bot propio al quitar TELEGRAM_PREFIJO', [false, 'abc'], [Telegram::botGenerico(), Telegram::inicio('abc')]);
putenv('TELEGRAM_BOT_TOKEN'); putenv('TELEGRAM_BOT_USERNAME'); putenv('TELEGRAM_API_BASE');

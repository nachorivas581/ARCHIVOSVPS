<?php
// --- AJUSTES VITALES PARA QUE NO SE TRABE (CERO BUFFER) ---
set_time_limit(0);
@apache_setenv('no-gzip', 1);
@ini_set('zlib.output_compression', 0);

// Liberación absoluta de CORS para redes externas, celulares y Bitmovin
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: *");

// 1. Detectar qué canal están pidiendo (si no viene nada, por defecto usa espn)
$canal_solicitado = isset($_GET['canal']) ? trim($_GET['canal']) : 'espn';

// Si es una petición de sub-playlist o fragmento .ts, arrastramos el ID del canal original
if (isset($_GET['c_id'])) {
    $canal_solicitado = trim($_GET['c_id']);
}

$archivo_link = "/var/www/html/{$canal_solicitado}.txt";

// Si el archivo del canal no existe porque el script de Node no lo corrió todavía
if (!file_exists($archivo_link)) {
    header("HTTP/1.0 404 Not Found");
    die("Error: El canal '{$canal_solicitado}' no esta configurado o no tiene token activo.");
}

$url_stream_real = trim(file_get_contents($archivo_link));
if (empty($url_stream_real)) {
    header("HTTP/1.0 503 Service Unavailable");
    exit;
}

// Extraemos la URL base y los parámetros del streaming original
$url_base = substr($url_stream_real, 0, strrpos($url_stream_real, '/') + 1);
$query_string = substr($url_stream_real, strpos($url_stream_real, '?'));

// Capturar el archivo interno solicitado por el reproductor (viene cifrado en Base64 seguro)
$file_request = '';
if (isset($_GET['file'])) {
    $decoded = base64_decode($_GET['file'], true);
    if ($decoded !== false) {
        $file_request = $decoded;
    }
}

// Detectar esquema (HTTP/HTTPS) y dominio público de tu servidor
$protocolo = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === 1) || isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? "https://" : "http://";
$mi_dominio = $protocolo . $_SERVER['HTTP_HOST'];

// -------------------------------------------------------------------
// CASO 1: RETRANSMISIÓN DE SUB-PLAYLISTS O FRAGMENTOS .TS (TUNELIZADO)
// -------------------------------------------------------------------
if (!empty($file_request)) {
    if (strpos($file_request, 'http') === 0) {
        $url_final = $file_request;
    } else {
        $file_request_clean = ltrim($file_request, '/');
        $url_final = $url_base . $file_request_clean;
    }

    // Si no trae token propio, le adjuntamos el del link_vivo
    if (strpos($url_final, '?') === false) {
        $url_final .= $query_string;
    }

    // SI ES UNA SUB-PLAYLIST (.m3u8 interna de calidad), procesamos sus líneas
    if (strpos($file_request, '.m3u') !== false) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url_final);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'User-Agent: Mozilla/5.0',
            'Referer: https://streamtpday1.xyz/',
            'Origin: https://streamtpday1.xyz'
        ]);
        $contenido_sub = curl_exec($ch);
        curl_close($ch);

        header("Content-Type: application/vnd.apple.mpegurl");

        $url_base_sub = substr($url_final, 0, strrpos($url_final, '/') + 1);
        $parsed_url = parse_url($url_final);
        $url_host_chunk = $parsed_url['scheme'] . '://' . $parsed_url['host'];

        $lineas = explode("\n", $contenido_sub);
        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if (empty($linea)) continue;

            if (strpos($linea, '#') !== 0 && strpos($linea, 'http') !== 0) {
                $ruta_real_ts = (strpos($linea, '/') === 0) ? $url_host_chunk . $linea : $url_base_sub . $linea;
                $base64_linea = urlencode(base64_encode($ruta_real_ts));

                // Forzamos a pasar por el proxy arrastrando el c_id de este canal específico
                echo $mi_dominio . "/falso_m3u8.php?file=" . $base64_linea . "&c_id=" . $canal_solicitado . "\n";
            } else {
                echo $linea . "\n";
            }
        }
        exit;
    }

    // SI ES UN FRAGMENTO DE VIDEO CRUDO (.ts) - SOLUCIÓN DE FLUIDEZ
    header("Content-Type: video/MP2T");
    header("Cache-Control: no-cache"); // Evita que el navegador del celular intente guardarlo
    while (ob_get_level()) ob_end_clean(); 

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url_final);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 0); // Infinito
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: Mozilla/5.0',
        'Referer: https://streamtpday1.xyz/',
        'Origin: https://streamtpday1.xyz'
    ]);
    
    // ACA ESTA LA MAGIA: Envía el video en trozos exactos a medida que llega
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) {
        echo $data;
        flush();
        return strlen($data);
    });

    curl_exec($ch);
    curl_close($ch);
    exit;
}

// -------------------------------------------------------------------
// CASO 2: SOLICITUD DE LA PLAYLIST MAESTRA INICIAL
// -------------------------------------------------------------------
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url_stream_real);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'User-Agent: Mozilla/5.0',
    'Referer: https://streamtpday1.xyz/',
    'Origin: https://streamtpday1.xyz'
]);
$contenido = curl_exec($ch);
curl_close($ch);

header("Content-Type: application/vnd.apple.mpegurl");
header("Cache-Control: no-cache, must-revalidate");

$lineas = explode("\n", $contenido);
foreach ($lineas as $linea) {
    $linea = trim($linea);
    if (empty($linea)) continue;

    if (strpos($linea, '#') !== 0 && strpos($linea, 'http') !== 0) {
        $base64_linea = urlencode(base64_encode($linea));
        // Me aseguré de que apunte a falsom3u8.php (que es el nombre de tu archivo)
        echo $mi_dominio . "/falso_m3u8.php?file=" . $base64_linea . "&c_id=" . $canal_solicitado . "\n";
    } else {
        echo $linea . "\n";
    }
}
exit;
?>

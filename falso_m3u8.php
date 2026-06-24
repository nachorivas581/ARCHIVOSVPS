<?php
// Configuración para evitar tiempos de espera y compresión innecesaria
set_time_limit(0);
@apache_setenv('no-gzip', 1);
@ini_set('zlib.output_compression', 0);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: *");

// 1. Detectar canal y obtener URL base
$canal_solicitado = isset($_GET['c_id']) ? trim($_GET['c_id']) : (isset($_GET['canal']) ? trim($_GET['canal']) : 'espn');
$archivo_link = "/var/www/html/{$canal_solicitado}.txt";

if (!file_exists($archivo_link)) {
    header("HTTP/1.0 404 Not Found");
    die("Error: Canal no configurado.");
}

$url_stream_real = trim(file_get_contents($archivo_link));
$url_base = substr($url_stream_real, 0, strrpos($url_stream_real, '/') + 1);
$query_string = (strpos($url_stream_real, '?') !== false) ? substr($url_stream_real, strpos($url_stream_real, '?')) : '';

// 2. Procesar solicitud de archivo (segmento o sub-playlist)
$file_request = isset($_GET['file']) ? base64_decode($_GET['file']) : '';

$protocolo = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === 1)) ? "https://" : "http://";
$mi_dominio = $protocolo . $_SERVER['HTTP_HOST'];

// --- CASO: ARCHIVO INTERNO (.ts o .m3u8) ---
if (!empty($file_request)) {
    $url_final = (strpos($file_request, 'http') === 0) ? $file_request : $url_base . ltrim($file_request, '/');
    if (strpos($url_final, '?') === false) $url_final .= $query_string;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url_final);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Referer: https://streamtpday1.xyz/',
        'Origin: https://streamtpday1.xyz'
    ]);

    // Lógica de Streaming fluido (Chunking)
    if (strpos($file_request, '.m3u') !== false) {
        header("Content-Type: application/vnd.apple.mpegurl");
        $contenido_sub = curl_exec($ch);
        $lineas = explode("\n", $contenido_sub);
        foreach ($lineas as $linea) {
            $linea = trim($linea);
            if (empty($linea) || strpos($linea, '#') === 0) { echo $linea . "\n"; continue; }
            $ruta_abs = (strpos($linea, 'http') === 0) ? $linea : $url_base . $linea;
            echo $mi_dominio . "/falso_m3u8.php?c_id={$canal_solicitado}&file=" . base64_encode($ruta_abs) . "\n";
        }
    } else {
        header("Content-Type: video/MP2T");
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) { echo $data; flush(); return strlen($data); });
        curl_exec($ch);
    }
    curl_close($ch);
    exit;
}

// --- CASO: SOLICITUD MAESTRA ---
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url_stream_real);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0', 'Referer: https://streamtpday1.xyz/', 'Origin: https://streamtpday1.xyz']);
$contenido = curl_exec($ch);
curl_close($ch);

header("Content-Type: application/vnd.apple.mpegurl");
foreach (explode("\n", trim($contenido)) as $linea) {
    if (empty($linea) || strpos($linea, '#') === 0 || strpos($linea, 'http') === 0) { echo $linea . "\n"; }
    else { echo $mi_dominio . "/falso_m3u8.php?c_id={$canal_solicitado}&file=" . base64_encode($linea) . "\n"; }
}
?>

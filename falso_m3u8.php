<?php
// Script "Bypass" - Solo reescribe rutas, NO hace de proxy de video
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: *");

$canal_solicitado = isset($_GET['c_id']) ? trim($_GET['c_id']) : (isset($_GET['canal']) ? trim($_GET['canal']) : 'espn');
$archivo_link = "/var/www/html/{$canal_solicitado}.txt";

if (!file_exists($archivo_link)) {
    header("HTTP/1.0 404 Not Found");
    die("Error: Canal no configurado.");
}

$url_stream_real = trim(file_get_contents($archivo_link));
$url_base = substr($url_stream_real, 0, strrpos($url_stream_real, '/') + 1);

$file_request = isset($_GET['file']) ? base64_decode($_GET['file']) : '';

// --- CASO 1: Procesar sub-playlists (.m3u8 internos) ---
if (!empty($file_request) && strpos($file_request, '.m3u') !== false) {
    $url_final = (strpos($file_request, 'http') === 0) ? $file_request : $url_base . ltrim($file_request, '/');
    
    $ch = curl_init($url_final);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0', 'Referer: https://streamtpday1.xyz/']);
    $contenido_sub = curl_exec($ch);
    curl_close($ch);

    header("Content-Type: application/vnd.apple.mpegurl");
    $url_base_sub = substr($url_final, 0, strrpos($url_final, '/') + 1);

    $lineas = explode("\n", $contenido_sub);
    foreach ($lineas as $linea) {
        $linea = trim($linea);
        if (empty($linea) || strpos($linea, '#') === 0) {
            echo $linea . "\n";
        } elseif (strpos($linea, 'http') === 0) {
            // Ya es una URL completa, que Bitmovin la lea directo
            echo $linea . "\n";
        } else {
            // Es un archivo .ts relativo. Lo convertimos a URL ABSOLUTA hacia la CDN
            $ruta_real_ts = (strpos($linea, '/') === 0) ? "https://streamtpday1.xyz" . $linea : $url_base_sub . $linea;
            // ¡MAGIA!: No lo pasamos por nuestro script, le damos la ruta real a Bitmovin
            echo $ruta_real_ts . "\n";
        }
    }
    exit;
}

// --- CASO 2: Solicitud inicial de la lista maestra ---
$ch = curl_init($url_stream_real);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0', 'Referer: https://streamtpday1.xyz/']);
$contenido = curl_exec($ch);
curl_close($ch);

header("Content-Type: application/vnd.apple.mpegurl");

$protocolo = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https://" : "http://";
$mi_dominio = $protocolo . $_SERVER['HTTP_HOST'];

$lineas = explode("\n", $contenido);
foreach ($lineas as $linea) {
    $linea = trim($linea);
    if (empty($linea) || strpos($linea, '#') === 0) {
        echo $linea . "\n";
    } elseif (strpos($linea, 'http') === 0) {
        // En la lista maestra, las resoluciones sí deben pasar por nuestro proxy
        // para que podamos reescribir las rutas de los .ts adentro
        echo $mi_dominio . "/falso_m3u8.php?c_id={$canal_solicitado}&file=" . base64_encode($linea) . "\n";
    } else {
        $ruta_real_m3u8 = (strpos($linea, '/') === 0) ? "https://streamtpday1.xyz" . $linea : $url_base . $linea;
        echo $mi_dominio . "/falso_m3u8.php?c_id={$canal_solicitado}&file=" . base64_encode($ruta_real_m3u8) . "\n";
    }
}
?>

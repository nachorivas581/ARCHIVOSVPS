<?php
set_time_limit(0);
error_reporting(0);

$canal = isset($_GET['c_id']) ? trim($_GET['c_id']) : 'dsports';
$archivo_txt = "/var/www/html/{$canal}.txt";
$url_stream_real = file_exists($archivo_txt) ? trim(file_get_contents($archivo_txt)) : '';

if (!$url_stream_real) { die("Canal no encontrado"); }

$url_base = substr($url_stream_real, 0, strrpos($url_stream_real, '/') + 1);

// Si piden un segmento de video (.ts)
if (isset($_GET['file'])) {
    $url_ts = base64_decode($_GET['file']);
    
    // Header limpio y único
    header("Content-Type: video/MP2T");
    
    $ch = curl_init($url_ts);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0', 'Referer: https://streamtpday1.xyz/']);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) { echo $data; flush(); return strlen($data); });
    curl_exec($ch);
    curl_close($ch);
    exit;
}

// Si piden la playlist (.m3u8)
header("Content-Type: application/vnd.apple.mpegurl");

$ch = curl_init($url_stream_real);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: Mozilla/5.0', 'Referer: https://streamtpday1.xyz/']);
$data = curl_exec($ch);
curl_close($ch);

$lineas = explode("\n", $data);
foreach ($lineas as $linea) {
    $linea = trim($linea);
    if (empty($linea) || strpos($linea, '#') === 0 || strpos($linea, 'http') === 0) { 
        echo $linea . "\n"; 
    } else { 
        $url_completa = (strpos($linea, '/') === 0) ? "https://streamtpday1.xyz" . $linea : $url_base . $linea;
        echo "falso_m3u8.php?c_id=" . urlencode($canal) . "&file=" . base64_encode($url_completa) . "\n";
    }
}
?>

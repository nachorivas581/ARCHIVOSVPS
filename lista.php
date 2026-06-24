<?php
header("Content-Type: application/vnd.apple.mpegurl");
header("Access-Control-Allow-Origin: *");

echo "#EXTM3U\n\n";

// Tus canales configurados
$canales = [
    'dsports' => 'DSports',
    'espn' => 'ESPN Premium',
    'tnt' => 'TNT Sports'
];

// Cabeceras estrictas que exige la CDN de StreamTP
$user_agent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36";
$referer = "https://streamtpday1.xyz/";

foreach ($canales as $id_canal => $nombre_canal) {
    $archivo_txt = "/var/www/html/{$id_canal}.txt";
    
    if (file_exists($archivo_txt)) {
        $url_real = trim(file_get_contents($archivo_txt));
        
        if (!empty($url_real)) {
            // Formato estándar M3U
            echo "#EXTINF:-1 tvg-id=\"{$id_canal}\" tvg-name=\"{$nombre_canal}\", {$nombre_canal}\n";
            
            // MAGIA PARA TELEVIZO: Se le pega el User-Agent y el Referer al final del link
            echo $url_real . "|User-Agent=" . urlencode($user_agent) . "&Referer=" . urlencode($referer) . "\n\n";
        }
    }
}
?>

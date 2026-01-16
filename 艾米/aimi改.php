<?php
error_reporting(0);

$id = $_GET['id'] ?? null;
$ts = $_GET['ts'] ?? null;
$key = $_GET['key'] ?? null;

if (!empty($id) && empty($ts) && empty($key)) {
    // 代理m3u8播放列表，改写ts和key链接
    $random = md5(time());
    $url = "https://hls-gateway.vpstv.net/streams/{$id}.m3u8?rand={$random}";

    $data = curl_get($url, 10);
    if ($data === false) {
        http_response_code(504);
        exit("Failed to fetch playlist.");
    }

    $lines = explode("\n", $data);
    $out = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            $out[] = $line;
            continue;
        }

        if (strpos($line, "URI=") !== false) {
            preg_match('/URI="([^"]+)"/', $line, $matches);
            if (isset($matches[1])) {
                $orig_key = $matches[1];
                $line = str_replace($orig_key, "am.php?key=" . bin2hex($orig_key), $line);
            }
            $out[] = $line;
            continue;
        }

        if (substr($line, 0, 1) === '#') {
            $out[] = $line;
        } else {
            $out[] = "am.php?ts=" . bin2hex($line);
        }
    }

    header('Content-Type: application/vnd.apple.mpegurl; charset=UTF-8');
    echo implode("\n", $out);
    exit;
}

if (empty($id) && !empty($ts) && empty($key)) {
    // 代理ts切片请求，不做缓存，直接远程获取并输出
    $ts_url = hex2bin($ts);
    if ($ts_url === false) {
        http_response_code(400);
        exit("Invalid ts parameter.");
    }

    header("Content-Type: video/mp2t");
    header('Content-Disposition: inline; filename="segment.ts"');
    $data = curl_get($ts_url, 30, false);
    if ($data === false) {
        http_response_code(504);
        exit("Failed to fetch ts segment.");
    }
    echo $data;
    exit;
}

if (empty($id) && empty($ts) && !empty($key)) {
    // 代理key请求，不做缓存，直接远程获取并输出
    $key_url = hex2bin($key);
    if ($key_url === false) {
        http_response_code(400);
        exit("Invalid key parameter.");
    }

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: inline; filename="key.key"');
    $data = curl_get($key_url, 10, true);
    if ($data === false) {
        http_response_code(504);
        exit("Failed to fetch key file.");
    }
    echo $data;
    exit;
}

http_response_code(404);
exit("Invalid request.");

function curl_get($url, $timeout = 10, $returnRaw = true) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Connection: keep-alive",
        "Accept-Encoding: gzip, deflate",
        "User-Agent: Mozilla/5.0 (PHP HLS Proxy)"
    ]);
    $data = curl_exec($ch);
    if (curl_errno($ch)) {
        curl_close($ch);
        return false;
    }
    curl_close($ch);
    return $data;
}

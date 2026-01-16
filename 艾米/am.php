<?php
error_reporting(0);
header('Content-Type: text/json;charset=UTF-8',true,200);

$id = $_GET["id"];
$ts = $_GET["ts"];
$key = $_GET["key"];

if(!empty($id) and empty($ts) and empty($key)){
$random = md5(time());
$data = curl("https://hls-gateway.vpstv.net/streams/{$id}.m3u8?rand={$random}",1,10);
$data = array_filter(explode("\n",$data));
$count = count($data);
for($i=0;$i<$count;$i++){

if(strpos($data[$i],"URI=")){
$key_add = explode('"',explode('URI="',$data[$i])[1])[0];
$data[$i] = str_replace($key_add,"am.php?key=".bin2hex($key_add),$data[$i]);
}

if(substr($data[$i],0,1) !== "#"){
$data[$i] = "am.php?ts=".bin2hex($data[$i]);
}

}
print_r(implode("\n",$data));
}

if(empty($id) and !empty($ts) and empty($key)){
header("Content-Type: video/mp2t");
header('Content-Disposition: attachment; filename=milktv.ts');
print_r(curl(hex2bin($ts),0,30));
}

if(empty($id) and empty($ts) and !empty($key)){
header('Content-Disposition: attachment; filename=milktv.key');
print_r(curl(hex2bin($key),1,10));
}

function curl($url,$type,$timeout){
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, $type);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
if(!empty($timeout)){
curl_setopt($ch, CURLOPT_TIMEOUT,$timeout);
}
$data = curl_exec($ch);
$info = curl_getinfo($ch);
curl_close($ch);
return $data;
}
?>
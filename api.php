<?php
/**
 * @license MIT
 */
$beginn = microtime(true);
header('content-type: application/json; charset=utf-8');
include 'api.class.php';

$API = new MediaDBAPI();

if($API->APIinit($_GET['key'])){
	if(method_exists('MediaDBAPI', 'API_'.$_GET['action'])){
		$funktionsname= 'API_'.$_GET['action'];
		$respons = $API->$funktionsname($_GET, $_POST);
	}
	else{
		$respons = $API->error(1003);
	}
}
else {
	$respons = $API->error(1002);
}

$Laufzeit = microtime(true) - $beginn; 
echo json_encode( $API->APIrespons(sprintf('%.5f', $Laufzeit), $respons),(isset($_GET['Pretty'])?JSON_PRETTY_PRINT:0));
?>
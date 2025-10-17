<?php
error_reporting(0);
//ini_set('display_errors', 1);


	
/*if(empty($_SESSION["Nombre"]) && (!isset($_GET['accion']) || empty($_GET['accion']))){
	header("Location: ../index.php");
	die();
}*/
$inicioRequest = new DateTime();
include("./constants.php");
require_once("./WebApiController.php");

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: OPTIONS,GET,POST,PUT,DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");


$table=$_GET['table'];


if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('HTTP/1.1 405 Method Not Allowed');    
    exit();
}

// all of our endpoints start with /tiposdocumento OR /paises OR /estadosciviles OR /provincias OR /usuarios OR /prisma OR /Reportes OR /tarjetas OR /pagos
// everything else results in a 404 Not Found
$availableTables = [
    "transaccionesdiarias", "descargararchivocuotas"
];

if (!in_array($table, $availableTables)) {
    header("HTTP/1.1 404 Not Found");
	exit();
}

//if ($table != "token" && $table != "onboarding" && $table != "onboardingstatus" && $table != 'balances' && $table != "cvu" && $table != 'accountcvu' && $table != 'transactions' && $table != 'loadcashless' && $table != "tiposdocumento" && $table != "paises" && $table != "estadosciviles" && $table != "provincias" && $table != "usuarios" && $table != "prisma" && $table != "pmc" && $table != "Reportes" && $table != "tarjetas" && $table != "pagos") {
//	header("HTTP/1.1 404 Not Found");
//	exit();
//}



// Función para retornar un mensaje de error en formato JSON
function returnError($message) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $message]);
    exit();
}

// Obtener los headers de la solicitud
$headers = getallheaders();
$body = json_decode(file_get_contents('php://input'), true);


	switch ($table) {
		
		case "transaccionesdiarias":
			$comercio = $_GET['comercio'];
			$controller = new WebApiController('GetDailyTransactions', $comercio);		
		break;	
		case "descargararchivocuotas":
			$fecha = $_GET['fecha'];			
			$controller = new WebApiController('DownloadInstallmentsFile', $fecha);		
		break;
	}
	
    $response = $controller->processRequest();		
	
    
	 
   
	
    // header('Content-Type: application/json');
	echo $response['body'];
	 //echo json_encode($response['body']);
	 
 
  
?>
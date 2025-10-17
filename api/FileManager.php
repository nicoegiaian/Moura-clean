<?php

include("./constants.php");

require_once("./DatabaseConnector.php");
require_once("./AuthorizationController.php");
require_once("./WebApiGateway.php");


	function descargarArchivo($fechaurl, $m, $d){
		// Se obtiene el Token.
		$authorization = new AuthorizationController(ACCESS_TOKEN_URL_BIND, CLIENT_ID_BIND, CLIENT_SECRET_BIND, SCOPE_BIND);
		$accessToken = json_decode($authorization->getAccessToken());  //Debe retornar un string con el json que tiene dentro el access_token. 
		                                                               //Entonces se lo convierte a objeto
		
		
		// A065BOTON110325.311
		$filter = 'A065BOTON' . $fechaurl . '.' . $m . $d;
		
		$webApiGateway = new webApiGateway("","","","");
		$encrypted = $webApiGateway->getFileEncrypted($accessToken,$filter);
				
		$webApiGateway->downloadFile($accessToken, $filter, urlencode($encrypted));		
		
	}


?>
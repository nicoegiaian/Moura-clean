<?php

require_once("./constants.php");
require_once("./AuthorizationController.php");
require_once("./WebApiGateway.php");

class WebApiController {

    private $endPoint;
	private $idUser;
	private $typeUser;
	private $amount;
	private $ticket;
	private $token;
	private $access_token_url;
	private $client_id;
	private $client_secret;
	private $scope;

    private $webApiGateway;
	private $usuarioGateway;
	private $tarjetaGateway;
	
	private $dbConnection;
	
    public function __construct($endPoint, $idUser)
    {
        $this->endPoint = $endPoint;
		$this->idUser = $idUser;
			
        $this->webApiGateway = new webApiGateway("","","","");  //Puede usarse tanto para comunicacion con BIND o PRISMA
		
    }

    public function processRequest()
    {
        
		// Se obtiene el Token.
		$authorization = new AuthorizationController(ACCESS_TOKEN_URL_BIND, CLIENT_ID_BIND, CLIENT_SECRET_BIND, SCOPE_BIND);
		$accessToken = json_decode($authorization->getAccessToken());  //Debe retornar un string con el json que tiene dentro el access_token. 
		                                                               //Entonces se lo convierte a objeto
				
		if( is_string($accessToken))
		{
			$this->token = json_decode($accessToken);  //Se convierte a un objeto
		}
		else{
			$this->token = json_decode(json_encode($accessToken));
		}
	
		switch ($this->endPoint) {
            
			case 'GetDailyTransactions':				
				$response = $this->getDailyTransactions($accessToken, $this->idUser);  //Para esta operacion $this->idUser recibió en el constructor el comercio
                break;
			case 'DownloadInstallmentsFile':				
				$response = $this->downloadInstallmentsFile($accessToken, $this->idUser);  //Para esta operacion $this->idUser recibió en el constructor la fecha
                break;
			
        }
       
	    return $response;
    }

	
	
	private function getDailyTransactions($accessToken, $commerceId)
    {		
        $result = $this->webApiGateway->getDailyTransactions($accessToken, $commerceId);  
        if ($result["httpCode"]==200)
		{
			
			$response['status_code_header'] = 'HTTP/1.1 200 OK';			
			$response['body'] = $result["response"];
		}
		else
		{
			$response['status_code_header'] = 'HTTP/1.1 404 Not Found';	
			$response['body'] = $result["response"];			
		}

        return $response;
    }
	
	private function downloadInstallmentsFile($accessToken, $date)
    {					
        $result = $this->webApiGateway->downloadInstallmentsFile($accessToken, $date);  
        if ($result["httpCode"]==200)
		{
			
			$response['status_code_header'] = 'HTTP/1.1 200 OK';			
			$response['body'] = $result["response"];
		}
		else
		{
			$response['status_code_header'] = 'HTTP/1.1 404 Not Found';	
			$response['body'] = $result["response"];			
		}

        return $response;
    }
	
	
	
    private function unprocessableEntityResponse()
    {
        $response['status_code_header'] = 'HTTP/1.1 422 Unprocessable Entity';
        $response['body'] = json_encode([
            'error' => 'Invalid input'
        ]);
        return $response;
    }

    private function notFoundResponse()
    {
        $response['status_code_header'] = 'HTTP/1.1 404 Not Found';
        $response['body'] = null;
        return $response;
    }
}
?>
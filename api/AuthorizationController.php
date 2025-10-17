<?php
session_start();
require_once("./WebApiGateway.php");

class AuthorizationController {

    private $accessTokenUrl;
    private $clientId;
    private $clientSecret;
	private $scope;

    public function __construct($accessTokenUrl, $clientId, $clientSecret, $scope = NULL)
    {
        $this->accessTokenUrl = $accessTokenUrl;
		$this->clientId = $clientId;
		$this->clientSecret = $clientSecret;
		$this->scope = $scope;
    }

    public function getAccessToken()
    {			
		/*if (isset($_SESSION["AccessToken"])){ // Ya se solicitó el token previamente.
			$accessToken = json_decode($_SESSION["AccessToken"]);
			$expirationTimeToken = clone($_SESSION["RequestTimeToken"]);
			$expirationTimeToken->add(new DateInterval('PT'. $accessToken->expires_in .'S'));
			$accessToken = $_SESSION["AccessToken"];
		}*/
		
		$expirationTimeToken = new DateTime();  //Para mantener compatibilidad
		$expirationTimeToken->modify('-1 hour');
		
		if (!isset($_SESSION["AccessToken"]) || $expirationTimeToken < new DateTime()){ // No se solicitó el token o expiró, se pide uno nuevo.*/
			$url = $this->accessTokenUrl;
			$requestMethod = "POST";
			$postFields = "grant_type=client_credentials&client_id=" . $this->clientId . "&client_secret=" . $this->clientSecret;
			if(isset($this->scope)){
				$postFields = $postFields . "&scope=" . $this->scope;
			}
			$httpHeader = array("content-type: application/x-www-form-urlencoded");
			//var_dump(['postFields' => $postFields]);
			$webApiGateway = new WebApiGateway($url, $requestMethod , $postFields, $httpHeader);
			$webApiGateway->sendRequest(null);
			$accessToken = $webApiGateway->GetResponse();
			//var_dump(['accessToken' => $accessToken]);
			$_SESSION["AccessToken"] = $accessToken; // Se guarda en la sesión
			$_SESSION["RequestTimeToken"] = new DateTime(); // Se guarda el momento de solicitud
		}
					
		return $accessToken['response'];
    }
}

/*$accessToken = new AuthorizationController(ACCESS_TOKEN_URL, CLIENT_ID, CLIENT_SECRET);
$token = json_decode($accessToken->getAccessToken());

//echo $token->token_type . " " . $token->access_token . "<br>";

$curl = curl_init();

curl_setopt_array($curl, array(
	CURLOPT_URL => API_URL . "/prepaidcard/v1/transactions/?card_id=11dd4d8b-ae09-48f3-9f05-617ab7316310&start_date=2023-07-27T00:00:00Z&end_date=2024-11-30T00:00:00Z&size=50&page=0&sort=id",
	CURLOPT_USERAGENT => $_SERVER['HTTP_USER_AGENT'],
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_SSL_VERIFYHOST =>false,
	CURLOPT_SSL_VERIFYPEER => false,
	CURLOPT_ENCODING => "",
	CURLOPT_MAXREDIRS => 10,
	CURLOPT_TIMEOUT => 30,
	CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
	CURLOPT_CUSTOMREQUEST => "GET",
	CURLOPT_HTTPHEADER => array(
		"Authorization: " . $token->token_type . " " . $token->access_token,
		"X-Prisma-Issuer: XPERIENCE"
	),
));
				
$content = curl_exec($curl);

// Check HTTP status code
if (!curl_errno($curl)) {
	switch ($http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE)) {
		case 200:  # OK
			echo "HTTP Code: 200<br>";
			var_dump(json_decode($content));
			break;
		default:
			echo 'Unexpected HTTP Code: ', $http_code, "\n";
	}
}

$err = curl_error($curl);        
curl_close($curl);*/
?>
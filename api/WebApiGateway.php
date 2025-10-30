<?php

require_once("./DatabaseConnector.php");

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class WebApiGateway {

    private $url;
	private $requestMethod;
	private $postFields;
	private $httpHeader;
	private $inicioRequest;
	private $finRequest;
	private $httpCode;
	private $response;
	private $dbConnection;	

    public function __construct($url, $requestMethod, $postFields, $httpHeader)
    {
        $this->url = $url;
		$this->requestMethod = $requestMethod;
		$this->postFields = $postFields;
		$this->httpHeader = $httpHeader;
		$this->dbConnection = (new DatabaseConnector(DB_SERVER, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD))->getConnection();
		
    }	
	

    public function sendRequest($token)  //$token se pasa como objeto
    {		
		$curl = curl_init();
		
		$this->inicioRequest = new DateTime();
		$this->inicioRequest = $this->inicioRequest->format('Y-m-d H:i:s.u');
		
			
		curl_setopt_array($curl, array(
			CURLOPT_URL => $this->url,
			CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/127.0.0.0 Safari/537.36", //$_SERVER['HTTP_USER_AGENT'],
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYHOST =>false,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_ENCODING => "",
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => $this->requestMethod,
			CURLOPT_POSTFIELDS => $this->postFields,
			CURLOPT_HTTPHEADER => $this->httpHeader
			),
		);
				
		
		$this->finRequest = new DateTime();
		$this->finRequest = $this->finRequest->format('Y-m-d H:i:s.u');
										
		$this->response = curl_exec($curl);
		$this->httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		
		//print curl_error($curl);	
		
		curl_close($curl);
		
		if ($this->httpCode == 200 || $this->httpCode == 201 )	{			
			$this->response = array(
									"httpCode" => $this->httpCode,
									"response" => $this->response
									);
		}		
		elseif ($this->httpCode == 204) {		
			$this->response = array(
										"httpCode" => $this->httpCode,
										"response" => array( "response" => "" )
									);						
		}		
		else{			
			$mensajeError = json_decode($this->response,true);			// true para un array asociativo			 
			$this->response = array(
									"httpCode" => $this->httpCode,
									"response" => $mensajeError
									);
		}
		
		http_response_code($this->httpCode);			
		
		return $this->response;

    }
	

    public function getCode()
    {
        return $this->httpCode;    
    }
	
	public function getResponse()
    {
        return $this->response;    
    }
	
	public function logRequest($table, $idUser = null)
    {
		$statement = "
            INSERT INTO " . $table . 
               " (Url, IdUsuario, RequestMethod, postFields, InicioRequest, FinRequest, HttpCode, Response)
            VALUES
                (:url, :idUsuario, :requestMethod, :postFields, :inicioRequest, :finRequest, :httpCode, :response);
        ";

        try {			
			$statement = $this->dbConnection->prepare($statement);
            $statement->execute(array(
				'url' => $this->url,
				'idUsuario' => isset($idUser) ? $idUser : 0 , //0 si es un logueo donde aun no se haya definido el IdUsuario 
				'requestMethod' => $this->requestMethod,
				'postFields' => strpos($this->postFields, "client_credentials") > 0 ? "" : $this->postFields,
				'inicioRequest' => $this->inicioRequest,
				'finRequest' => $this->finRequest,
				'httpCode' => $this->httpCode,				
				'response' => is_array($this->response) ? json_encode($this->response) : (strpos((string)$this->response, "Bearer") > 0 ? "" : substr((string)$this->response, 0, 1000)),
			));
            return $statement->rowCount();
        } catch (\PDOException $e) {
            exit($e->getMessage());
        }
	}	
	
	
	public function updateWithdrawalStatusERROR($cardHolderId)
    {
        $statement = "
            UPDATE retiros
            SET                 
				Estado = 'ERROR'						
            WHERE CardHolderId = :cardHolderId AND Estado = 'PENDIENTE' AND Ejecutar = 1 AND FechaRetiro >= NOW() - INTERVAL 24 HOUR;
        ";

        try {
            $statement = $this->dbConnection->prepare($statement);
            $statement->execute(array(
                'cardHolderId' => $cardHolderId
            ));
            return $statement->rowCount();
        } catch (\PDOException $e) {
            exit($e->getMessage());
        }    
    }
	
	
	
	public function getFileEncrypted($accessToken, $filter)  
	{
		//var_dump($accessToken);
		$this->httpHeader = array(
				"Content-Type: application/json",
				'Accept: */*',
				"Accept-Encoding: gzip, deflate, br",
				"Connection: keep-alive",
				"Authorization: Bearer " . $accessToken->access_token
		);
		  		
		//Consultar archivos Cobro
		$this->url = API_URL_BIND . "/bindentidad-filemanager-v2/v2/api/v1.201/browser?PSP=164&Filter=" . $filter;			
		$this->requestMethod = "GET";
			
		$this->response = $this->sendRequest(null);		//Debe retornar un string con el json que tiene dentro el encrypted. 
		                                                //Entonces se lo convierte a objeto
		//var_dump($this->response);
		$encrypted = json_decode($this->response['response']);
		return $encrypted[0]->encrypted;
		
		
		//return  array( "httpCode" => 404, "response" => array("error" => 404 , "detalle" => "No se encuentra el archivo"));
	}
	
	private function convertirFecha($fecha) {
		// Asegurarse que la cadena tenga 6 caracteres
		if (strlen($fecha) !== 6) {
			return false;
		}
	
		$dia = substr($fecha, 0, 2);
		$mes = substr($fecha, 2, 2);
		$anio = substr($fecha, 4, 2);
	
		// Convertimos el año a 4 dígitos (2000-2099)
		$anioCompleto = (intval($anio) < 50) ? '20' . $anio : '19' . $anio;
	
		// Retornamos en formato Y-m-d
		return "$anioCompleto-$mes-$dia";
	}

	public function getDailyTransactions($accessToken, $commerceId)
	{		
		$this->httpHeader = array(
				"Content-Type: application/json",
				'Accept: */*',
				"Accept-Encoding: gzip, deflate, br",
				"Connection: keep-alive",
				"Authorization: Bearer " . $accessToken->access_token
		);
			
		$fechaActual = date('Y-m-d');
		
		$this->url = API_URL_BIND . "/bindentidad-transaccionquery-v2/v2/api/v1.201/transacciones-pag?Start=0&Length=100&fechaNegocioDesde=" . 
		             $fechaActual . "&fechaNegocioHasta=" . $fechaActual . "&codigoComercio=" . $commerceId;
					 //"2025-05-30&fechaNegocioHasta=2025-05-30&codigoComercio=" . $commerceId;
			
		$this->requestMethod = "GET";		
						
		$result = $this->sendRequest($accessToken);			
			
		return  $result;		
		
	}
	
	public function downloadInstallmentsFile($accessToken, $date)
	{		
		$this->httpHeader = array(
				"Content-Type: application/json",
				'Accept: */*',
				"Accept-Encoding: gzip, deflate, br",
				"Connection: keep-alive",
				"Authorization: Bearer " . $accessToken->access_token
		);
			
		
			
		$downloadDate = $this->convertirFecha($date);
			
			
		$this->url = API_URL_BIND . "/bindentidad-transaccionquery-v2/v2/api/v1.201/transacciones-pag?Start=0&Length=100&fechaNegocioDesde=" . 
		             $downloadDate . "&fechaNegocioHasta=" . $downloadDate;
					 //"2025-05-30&fechaNegocioHasta=2025-05-30";
					 
			
		$this->requestMethod = "GET";		
						
		$result = $this->sendRequest($accessToken);
		
		//$data = $result['response'];
		$data = json_decode($result['response'], true); // con true = array asociativo
		
		$spreadsheet = new Spreadsheet();	
		$sheet = $spreadsheet->getActiveSheet();
		
	
		// Cabeceras
		$sheet->setCellValue('A1', 'Transaccion');
		$sheet->setCellValue('B1', 'Cuotas');
		
		$row = 2; // Comenzamos en la fila 2 (la 1 son los encabezados)
		
		
		foreach ($data['transacciones'] as $transaccion) {
			if (!is_null($transaccion['cuotas'])) {
				$sheet->setCellValue('A' . $row, $transaccion['id']);
				$sheet->setCellValue('B' . $row, $transaccion['cuotas']);
				$row++;
			}
		}
		
		// Guardar archivo Excel
		$writer = new Xlsx($spreadsheet);
		$filename = 'archivocuotas.xlsx';
		
		$writer->save(__DIR__ . '/' . $filename);
		
		return  $result;		
		
	}
	
	public function downloadFile($accessToken, $filename, $encrypted)  
	{		
		$this->httpHeader = array(
				"Content-Type: text/plain",
				"Accept: text/plain",
				"Authorization: Bearer " . $accessToken->access_token
		);	
		
		  		
		//Consultar archivos Cobro
		$this->url = API_URL_BIND . "/bindentidad-filemanager-v2/v2/api/v1.201/Download?encrypted=" . $encrypted;			
		$this->requestMethod = "GET";
			
		$this->response = $this->sendRequest(null);	
		
		$file = fopen( DIR_RAIZ . $filename, "w");
		
		fwrite($file, $this->response['response']);
		fclose($file);
		
		return $this->response;		
		
	}	
	
	
	
	private function findCVUByCuentaId($cuentaId)
    {		
        $statement = "
            SELECT 
                CVU,
				AliasCVU,
				Documento,
				NombreORazonSocial,
				Apellido
            FROM
                usuarios
            WHERE CuentaBIND = ? ;
        ";
		
        try {
            $statement = $this->dbConnection->prepare($statement);
            $statement->execute(array($cuentaId));
            $result = $statement->fetch(\PDO::FETCH_ASSOC);
			
            return $result;
        } catch (\PDOException $e) {
			return false;            
        }    
    }
	
	
}
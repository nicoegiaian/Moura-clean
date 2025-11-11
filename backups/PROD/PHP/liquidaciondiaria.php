<?php

include("./constants.php");

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: OPTIONS,GET,POST,PUT,DELETE");
header("Access-Control-Max-Age: 3600");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

require_once("./DatabaseConnector.php");
require_once("./AuthorizationController.php");
require_once("./WebApiGateway.php");

require 'vendor/autoload.php'; 

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;



	function obtenerTransaccionesPorLiquidar($dbConnection, $idpdv, $fecha)
    {			
		if (strlen($fecha) == 6) {
			// Formato AAMMDD (ejemplo: 240322)
			$fechapago = DateTime::createFromFormat('dmy', $fecha)->format('Y-m-d');
		} elseif (strlen($fecha) == 8) {
			// Formato AAAAMMDD (ejemplo: 20230322)
			$fechapago = DateTime::createFromFormat('dmY', $fecha)->format('Y-m-d');
		} else {
			// Si la longitud no es válida, retorna un error o valor nulo
			return false;
		}
		
        $statement = "
            SELECT nrotransaccion, idpdv, sucursal, importe, metodopago, estadocheque, nrocomprobante, estadocobranza, canal,
				   dendepositante, refdepositante, cuitdepositante, primervencimiento, importeprimervenc, segundovencimiento,
				   importesegundovenc, motivorechazo, nrocheque, fecha, importecheque, importeaplicado, fechapago, fechapagobind, nrocobranza,
					tipotransaccion, procesada, completada, marca, idliquidacion
			FROM transacciones t			
			WHERE t.completada = 0 and t.idpdv = ? and ( DATE(t.fechapago) = ?  or DATE(t.fechapagobind) = ? )
			ORDER BY t.importe
        ";
		
        try {
            $statement = $dbConnection->prepare($statement);
            $statement->execute(array($idpdv, $fechapago, $fechapago));
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);			
			
			return $result;
            
        } catch (\PDOException $e) {
            exit($e->getMessage());
        }    
    }
	
	function obtenerMontosLiquidacionesPendientes($dbConnection, $fecha)
    {			
		if (strlen($fecha) == 6) {
			// Formato AAMMDD (ejemplo: 240322)
			$fechapago = DateTime::createFromFormat('dmy', $fecha)->format('Y-m-d');
		} elseif (strlen($fecha) == 8) {
			// Formato AAAAMMDD (ejemplo: 20230322)
			$fechapago = DateTime::createFromFormat('dmY', $fecha)->format('Y-m-d');
		} else {
			// Si la longitud no es válida, retorna un error o valor nulo
			return false;
		}
		
        $statement = "
            SELECT idpdv, sum(importe) as montoMoura , sum(importeprimervenc-importe) as montoPDV
			FROM transacciones t
			WHERE t.completada = 0 and ( DATE(t.fechapago) = ?  or DATE(t.fechapagobind) = ? )
			GROUP BY t.idpdv;	
        ";
		
        try {
            $statement = $dbConnection->prepare($statement);
            $statement->execute(array($fechapago, $fechapago));
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);			
			
			return $result;
            
        } catch (\PDOException $e) {
            exit($e->getMessage());
        }    
    }
	
	function obtenerMontosLiquidacionesPendientesPorMetodoPago($dbConnection, $idpdv, $fecha)
    {			
		if (strlen($fecha) == 6) {
			// Formato DDMMAA (ejemplo: 240322)
			$fechapago = DateTime::createFromFormat('dmy', $fecha)->format('Y-m-d');
		} elseif (strlen($fecha) == 8) {
			// Formato DDMMAAAA (ejemplo: 22032025)
			$fechapago = DateTime::createFromFormat('dmY', $fecha)->format('Y-m-d');
		} else {
			// Si la longitud no es válida, retorna un error o valor nulo
			return false;
		}
		
        $statement = "
            SELECT metodopago, count(*) as cantidadtrans, sum(importe) as montoMoura , sum(importeprimervenc-importe) as montoPDV
			FROM transacciones t
			WHERE t.completada = 0 and t.idpdv = ? and ( DATE(t.fechapago) = ?  or DATE(t.fechapagobind) = ? )
			GROUP BY t.metodopago;	
        ";
		
        try {
            $statement = $dbConnection->prepare($statement);
            $statement->execute(array($idpdv, $fechapago, $fechapago));
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);			
			
			return $result;
            
        } catch (\PDOException $e) {
            exit($e->getMessage());
        }    
    }
	
	function obtenerMontosDevolucionesPendientesPorMetodoPago($dbConnection, $idpdv)
    {			
        $statement = "
            SELECT t.metodopago, sum(d.importeadevolver) montoDevolucion
			FROM devoluciones d
			INNER JOIN transacciones t 
			ON d.nrotransaccionemparejada = t.nrotransaccion
			WHERE d.idpdv = ? and d.completada = 0 and d.nrotransaccionemparejada is not null
			GROUP BY t.metodopago			
        ";
		
        try {
            $statement = $dbConnection->prepare($statement);
            $statement->execute(array($idpdv));
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);			
			
			return $result;
            
        } catch (\PDOException $e) {
            exit($e->getMessage());
        }    
    }
	
	function obtenerDevolucionesPendientes($dbConnection, $idpdv)
    {		
        $statement = "
            SELECT nrodevolucion, nrotransaccion, idpdv, fechadevolucion, importeadevolver,
                   importedevuelto, completada, idliquidacion 
			FROM devoluciones
			WHERE idpdv  = ? and completada = 0	
			ORDER BY importeadevolver
        ";
		
        try {
            $statement = $dbConnection->prepare($statement);
            $statement->execute(array($idpdv));
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);			
			
			return $result;
            
        } catch (\PDOException $e) {
            exit($e->getMessage());
        }    
    }
	
	function obtenerMontoDevolucionesPendientes($dbConnection, $idpdv)
    {	
				
        $statement = "
            SELECT sum(importeadevolver - importedevuelto) as monto
			FROM devoluciones
			WHERE idpdv  = ? and completada = 0				
        ";
		
        try {
            $statement = $dbConnection->prepare($statement);
            $statement->execute(array($idpdv));
            $result = $statement->fetch(\PDO::FETCH_ASSOC);			
			
			return $result;
            
        } catch (\PDOException $e) {
            exit($e->getMessage());
        }    
    }
	
	
	
	function insertarLiquidacion( $dbConnection, $fecha, $monto, $cantidadtrans, $estado, $idpdv, $fechapago, $metodo, $marca) {
		$query = "
			INSERT INTO liquidaciones 
			(fecha, monto, cantidadtrans, estado, idpdv, fechapago, metodo, marca)
			VALUES 
			(:fecha, :monto, :cantidadtrans, :estado, :idpdv, :fechapago, :metodo, :marca)
		";
	
		try {
			$statement = $dbConnection->prepare($query);
	
			// Asignar valores a los parámetros
			$statement->bindParam(':fecha', $fecha);
			$statement->bindParam(':monto', $monto);
			$statement->bindParam(':cantidadtrans', $cantidadtrans, PDO::PARAM_INT);
			$statement->bindParam(':estado', $estado);
			$statement->bindParam(':idpdv', $idpdv, PDO::PARAM_INT);
			$statement->bindParam(':fechapago', $fechapago);
			$statement->bindParam(':metodo', $metodo);
			$statement->bindParam(':marca', $marca, PDO::PARAM_INT);
	
			$statement->execute();
	
			// Retornar el último ID insertado
			return $dbConnection->lastInsertId();
	
		} catch (PDOException $e) {
			exit("Error al insertar la liquidación: " . $e->getMessage());
		}
	}
	
	function matchearDevolucion($dbConnection, $nroTransaccion, $nroDevolucion){
		
        $statement = "
            UPDATE devoluciones d		
			SET	d.nrotransaccionemparejada = ?
			WHERE d.nrodevolucion = ?
        ";
		
        try {
            $statement = $dbConnection->prepare($statement);
            $statement->execute(array($nroTransaccion, $nroDevolucion));           
			
			return $statement->rowCount();  // Retorna el número de filas afectadas
            
        } catch (\PDOException $e) {
            exit($e->getMessage());
        }    
	}
	
	function actualizarTransaccionesLiquidadas($dbConnection, $idpdv, $idLiq, $metodopago, $fecha){
		
		if (strlen($fecha) == 6) {
			// Formato AAMMDD (ejemplo: 240322)
			$fechapago = DateTime::createFromFormat('dmy', $fecha)->format('Y-m-d');
		} elseif (strlen($fecha) == 8) {
			// Formato AAAAMMDD (ejemplo: 20230322)
			$fechapago = DateTime::createFromFormat('dmY', $fecha)->format('Y-m-d');
		} else {
			// Si la longitud no es válida, retorna un error o valor nulo
			return false;
		}
		
        $statement = "
            UPDATE transacciones t		
			SET	t.completada = 1, t.idliquidacion = ?
			WHERE t.completada = 0 and t.idpdv = ? and t.metodopago = ? and ( DATE(t.fechapago) = ?  or DATE(t.fechapagobind) = ? )
        ";
		
        try {
            $statement = $dbConnection->prepare($statement);
            $statement->execute(array($idLiq, $idpdv, $metodopago, $fechapago, $fechapago));        
			
			return $statement->rowCount();  // Retorna el número de filas afectadas
            
        } catch (\PDOException $e) {
            exit($e->getMessage());
        }    
	}
	
	function actualizarDevolucionesLiquidadas($dbConnection, $idpdv){
		
        $statement = "
            UPDATE devoluciones d		
			SET	d.importedevuelto = d.importeadevolver, d.completada = 1, 
			    d.idliquidacion = (SELECT t.idliquidacion FROM transacciones t WHERE t.nrotransaccion = d.nrotransaccionemparejada LIMIT 1)
			WHERE d.idpdv = ? and d.completada = 0 and d.idliquidacion IS NULL
        ";
		
        try {
            $statement = $dbConnection->prepare($statement);
            $statement->execute(array($idpdv));           
			
			return  $statement->rowCount();  // Retorna el número de filas afectadas
            
        } catch (\PDOException $e) {
            exit($e->getMessage());
        }    
	}
	
	function actualizarMontoAhorroPDV($dbConnection, $idpdv, $idLiq){		
        
		$statement = "
            UPDATE puntosdeventa p
			JOIN (
				SELECT SUM(ld.credmoura) AS suma_credmoura
				FROM transacciones t
				JOIN liquidacionesdetalle ld ON t.nrotransaccion = ld.nrotransaccion
				WHERE t.idliquidacion = ?
			) AS sub ON p.id = ?
			SET p.monto = IFNULL(p.monto, 0) + IFNULL(sub.suma_credmoura, 0);	
        ";
		
        try {
            $statement = $dbConnection->prepare($statement);
            $statement->execute(array($idLiq, $idpdv));        
			
			return $statement->rowCount();  // Retorna el número de filas afectadas
            
        } catch (\PDOException $e) {
            exit($e->getMessage());
        }    
	}
	
	function confirmarLiquidacion($dbConnection, $idpdv, $fecha)
    {			
		if (strlen($fecha) == 6) {
			// Formato AAMMDD (ejemplo: 240322)
			$fechaliq = DateTime::createFromFormat('dmy', $fecha)->format('Y-m-d');
			$fechapago = DateTime::createFromFormat('dmy', $fecha);
		} elseif (strlen($fecha) == 8) {
			// Formato AAAAMMDD (ejemplo: 20230322)
			$fechaliq = DateTime::createFromFormat('dmY', $fecha)->format('Y-m-d');
			$fechapago = DateTime::createFromFormat('dmY', $fecha);
		} else {
			// Si la longitud no es válida, retorna un error o valor nulo
			return false;
		}
		
		$liquidaciones = obtenerMontosLiquidacionesPendientesPorMetodoPago($dbConnection, $idpdv, $fecha);		
		$devoluciones = obtenerMontosDevolucionesPendientesPorMetodoPago($dbConnection, $idpdv);	
		
		$fechapago->modify('+4 hours');
		$fechapago = $fechapago->format('Y-m-d H:i:s');

		foreach( $liquidaciones as $liq ){  //1 registro de liquidacion por cada metodo de pago
			$montoDevolucion = 0;
			foreach ($devoluciones as $devolucion) {
				if ($devolucion['metodopago'] === $liq['metodopago']) {
					$montoDevolucion = $devolucion['montoDevolucion'];
					break; // Detenemos el bucle al encontrar el primero
				}
			}

			//Se inserta el registro en la tabla liquidaciones
			$idLiq = insertarLiquidacion( $dbConnection, $fechaliq, $liq['montoMoura'] + $liq['montoPDV'] - $montoDevolucion, $liq['cantidadtrans'], 
			                     'LIQUIDADA', $idpdv, $fechapago, $liq['metodopago'], '');
				
			//Se actualizan los registros correspondientes en la tabla transacciones
			actualizarTransaccionesLiquidadas($dbConnection, $idpdv, $idLiq, $liq['metodopago'], $fecha);
			actualizarDevolucionesLiquidadas($dbConnection, $idpdv);
			actualizarMontoAhorroPDV($dbConnection, $idpdv, $idLiq);
			
		}	
	}
	
	function obtenerLineasLiquidacion($dbConnection, $fecha)
    {		
        $statement = "
            SELECT linea0, linea1, linea2 
			FROM liquidacionesarchivo
			WHERE fecha  = ?
        ";
		
        try {
            $statement = $dbConnection->prepare($statement);
            $statement->execute(array($fecha));
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);			
			
			return $result;
            
        } catch (\PDOException $e) {
            exit($e->getMessage());
        }    
    }
	

// --------------------------------------------------
	// Código principal del procesamiento por lotes
	// --------------------------------------------------
	try {
		
		$fechaurl=$_GET['fecha'];
		
		// Separar la fecha en día, mes y año
		$d = substr($fechaurl, 0, 2);           // Día (siempre dos caracteres)
		$m = ltrim(substr($fechaurl, 2, 2), '0'); // Mes (con 0 a la izquierda removido si existe)
		$a = substr($fechaurl, 4, 2);           // Año (siempre dos caracteres)
		
		$dbConnection = (new DatabaseConnector(DB_SERVER, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD))->getConnection();		
		
		//Se obtienen todos los montos a liquidar a cada PDV y a Moura cuyo monto es obtenido sumando por cada PDV
		$liquidacionesPendientes = obtenerMontosLiquidacionesPendientes($dbConnection,$fechaurl);		
		
		$montoMoura = 0;
		
		//Para cada liquidacion se hace la transferencia correspondiente y se sumariza para finalizar la transferencia a Moura
		//(Moura: importe   |   PDV: importeprimervenc - importe) 
		foreach( $liquidacionesPendientes as $liquidacion ){		
			
			$montoAdevolver = 0;
			$devolucionesPendientes = obtenerDevolucionesPendientes($dbConnection, $liquidacion['idpdv']);
			
			if(!empty($devolucionesPendientes)){		//Si hay devoluciones pendientes se intenta emparejar cada una a una transaccion
					$transacciones = obtenerTransaccionesPorLiquidar($dbConnection, $liquidacion['idpdv'], $fechaurl);
					$i = 0; // Índice para transacciones (ventas)
					$j = 0; // Índice para devoluciones

					while ($i < count($transacciones) && $j < count($devolucionesPendientes)) {
						if ($transacciones[$i]["importeprimervenc"] - $transacciones[$i]["importe"] >= $devolucionesPendientes[$j]["importeadevolver"]) {
							// Si el importe para el pdv de la transaccion es mayor que la devolución a realizar entonces matchean
							$montoAdevolver += $devolucionesPendientes[$j]["importeadevolver"];
							//echo 'matchearDevolucion nrotransaccion:' . $transacciones[$i]['nrotransaccion'] . 'nrodevolucion:' . $devolucionesPendientes[$j]['nrodevolucion'] . '\n';
							matchearDevolucion($dbConnection, $transacciones[$i]['nrotransaccion'], $devolucionesPendientes[$j]['nrodevolucion']);
							$j++; // Se pasa a la siguiente devolución
						}
						$i++; // En cualquier caso, se avanza a la siguiente transacción
					}					
			}
			// Se obtiene CBU a partir de $liquidacion['idpdv'];
			
			//Se transfiere $liquidacion['montoPDV'] - $montoAdevolver
			$respuestaTransf = 1;
			$a = floatval($liquidacion['montoPDV'] - $montoAdevolver);
			echo "Se transfieren al PDV $" . $a . "\n";
			
			if( $respuestaTransf == 1 ){
				//Si la transferencia es exitosa se confirma la liquidación en la BD tanto para transacciones como para devoluciones
				confirmarLiquidacion($dbConnection,$liquidacion['idpdv'],$fechaurl);
				
				$montoMoura+= $liquidacion['montoMoura'] ;
			}		
		}
		
		// Guardar Liquidacion en un archivo .txt
		$archivoMouraLiquidacion = fopen(DIR_RAIZ . 'LiquidacionesBancos' . DIVISION_BSAS . $fechaurl . '.txt', 'w');
		
		
		$fechaLiquidacion = DateTime::createFromFormat('dmy', $fechaurl)->format('Y-m-d');
		$lineasLiquidacion = obtenerLineasLiquidacion($dbConnection,$fechaLiquidacion);
		
		foreach($lineasLiquidacion as $ll){
			fwrite($archivoMouraLiquidacion, $ll['linea0'] . PHP_EOL);
			fwrite($archivoMouraLiquidacion, $ll['linea1'] . PHP_EOL);
			fwrite($archivoMouraLiquidacion, $ll['linea2'] . PHP_EOL);
			fwrite($archivoMouraLiquidacion, PHP_EOL);
		}
		
		fclose($archivoMouraLiquidacion);
		
		/////////////////////////////////////////////////////////////////////////////////////////////////
		//Convertir el archivo de liquidación a Excel
		
		$spreadsheet = new Spreadsheet();
		$sheet = $spreadsheet->getActiveSheet();
		
		// Títulos del encabezado
		$encabezado = [
			"Data Emissão", "Tp.doc.", "Empresa", "Data Lançamento", "Período", "Moeda/taxa câm.",
			"Grp. ledger", "Referência", "Txt.cab.doc.", "ChvLnçt", "Conta", "Cód.RzE", "Montante",
			"Forma de Pagamento", "Bloqueio de Pagamento", "Condição de Pagamento", "Data Base",
			"Atribuição", "Texto", "Centro de Custo", "Ordem", "Elemento PEP", "Diagrama de Rede",
			"Item do Diagrama", "Centro de lucro", "Divisão", "Local de Negócios", "Cod Imposto"
		];
		
		// Insertar encabezado en la primera fila
		$col = 'A';
		foreach ($encabezado as $titulo) {
			$sheet->setCellValue($col . '1', $titulo);
			$col++;
		}
		
		$archivoMouraLiquidacion = fopen(DIR_RAIZ . 'LiquidacionesBancos' . DIVISION_BSAS . $fechaurl . '.txt', 'r');
		$fila = 2;
		
		while (($linea = fgets($archivoMouraLiquidacion)) !== false) {
			$campos = str_split($linea, 32);
			$columna = 'A';
			foreach ($campos as $valor) {
				$sheet->setCellValue($columna . $fila, trim($valor));
				$columna++;
			}
			$fila++;
		}
		
		fclose($archivoMouraLiquidacion);
		
		$writer = new Xlsx($spreadsheet);
		$writer->save(DIR_RAIZ . 'LiquidacionesBancos' . DIVISION_BSAS . $fechaurl . '.xlsx');
		
		//Se transfiere $liquidacion['montoMoura'] 
		$respuestaTransf = 1;
		echo "Se transfieren a Moura $" . $montoMoura;
		
	} catch (Exception $e) {
		echo "Error: " . $e->getMessage();
	}
	// ------------------------------

	// Registrar finalización del proceso
	echo "Fin del proceso de ejecución.";
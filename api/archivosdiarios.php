<?php
error_reporting(E_ALL);
include("constants.php");

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

	function obtenerCantidadCuotasDesdeExcel(){
		
		$archivo = 'archivocuotas.xlsx';
		$reader = IOFactory::createReader('Xlsx');
		$spreadsheet = $reader->load($archivo);
		$hoja = $spreadsheet->getActiveSheet();
		
		$datos = [];
		
		$fila = 2;
		while (true) {
			$codigo = $hoja->getCell('A' . $fila)->getValue();
			$cuotas = $hoja->getCell('B' . $fila)->getValue();
		
			// Si no hay más códigos, salimos
			if ($codigo === null || $codigo === '') {
				break;
			}
		
			$datos[$codigo] = $cuotas;
			$fila++;
		}
		
		return $datos;
	}
	
	
	function parsearRegistroBIND($registro) {
		// Inicializar el array con los campos definidos
		$datos = array();
	
		// Parseo de los campos según la definición
		$datos['tipo_de_registro'] = substr($registro, 0, 8);           // 01-08
		$datos['codigo_entidad'] = substr($registro, 8, 4);              // 09-12
		$datos['r'] = substr($registro, 12, 1);                           // 13-13
		$datos['codigo_terminal'] = substr($registro, 13, 5);             // 14-18
		$datos['parsubcod'] = substr($registro, 18, 10);                  // 19-28
		$datos['codigo_sucursal'] = substr($registro, 28, 4);            // 29-32
		$datos['relleno1'] = substr($registro, 32, 4);                    // 33-36
		$datos['transaccion'] = substr($registro, 36, 12);                // 37-48
		$datos['codigo_operacion'] = substr($registro, 48, 2);           // 49-50
		$datos['rubro_tx'] = substr($registro, 50, 2);                    // 51-52
		$datos['numero_de_comercio'] = substr($registro, 52, 6);          // 53-58
		$datos['codigo_de_servicio_identificacion'] = substr($registro, 58, 19); // 59-77
		$datos['importe'] = substr($registro, 77, 11);                   // 78-88
		$datos['relleno2'] = substr($registro, 88, 11);                  // 89-99
		$datos['relleno3'] = substr($registro, 99, 11);                  // 100-110
		$datos['moneda'] = substr($registro, 110, 1);                    // 111-111
		$datos['relleno4'] = substr($registro, 111, 4);                   // 112-115
		$datos['tipo_de_usuario'] = substr($registro, 115, 20);           // 116-135
		$datos['relleno5'] = substr($registro, 135, 3);                   // 136-138
		$datos['relleno6'] = substr($registro, 138, 6);                   // 139-144
		$datos['horario_de_la_tx'] = substr($registro, 144, 6);           // 145-150
		$datos['procesador_de_pago'] = substr($registro, 150, 3);         // 151-153
		$datos['relleno7'] = substr($registro, 153, 3);                   // 154-156
		$datos['procesador_de_debito_interno'] = substr($registro, 156, 4); // 157-160
		$datos['fecha_liquidacion'] = substr($registro, 160, 8);          // 161-168
		$datos['relleno8'] = substr($registro, 168, 8);                   // 169-176
		$datos['relleno9'] = substr($registro, 176, 3);                   // 177-179
		$datos['codigo_de_barra'] = substr($registro, 179, 60);           // 180-239
		$datos['fecha_pago'] = substr($registro, 239, 6);                 // 240-245
		$datos['tipo_de_transaccion'] = substr($registro, 245, 1);        // 246-246
		$datos['relleno10'] = substr($registro, 246, 7);                  // 247-253
		$datos['id_cliente'] = substr($registro, 253, 9);                 // 254-262
		$datos['forma_pago'] = substr($registro, 262, 2);                 // 263-264
		
		$datos['relleno11'] = substr($registro, 264, 4);                  // 265-268
		
		$datos['cantidad_de_cuotas'] = substr($registro, 268, 3);         // 269-271
		
		$datos['dni_cliente'] = substr($registro, 271, 15);               // 272-286
		$datos['relleno12'] = substr($registro, 286, 8);                  // 287-294
		$datos['id_tx_procesador'] = substr($registro, 294, 30);          // 295-324
		$datos['barra_cupon_de_pago'] = substr($registro, 324, 150);      // 325-474
		$datos['id_gateway'] = substr($registro, 474, 30);                // 475-504
		$datos['relleno13'] = substr($registro, 504, 30);                 // 505-534
		$datos['relleno14'] = substr($registro, 534, 60);                 // 535-594
		$datos['id_campana'] = substr($registro, 594, 8);                 // 595-602
		$datos['relleno15'] = substr($registro, 602, 3);                 // 603-605
		$datos['bin_de_la_tarjeta'] = substr($registro, 605, 6);          // 606-611
		$datos['id_rubro_comercio'] = substr($registro, 611, 6);          // 612-617
		$datos['id_prov_cliente'] = substr($registro, 617, 3);            // 618-620
	
		//CAMBIO AGOSTO 2025 SI LA OPERACION FUE UN PAGO CON QR LA FECHA DE LIQUIDACION DEBE INDICARSE 2 DIAS 
		//HABILES DESPUES QUE LO QUE INDICA BIND		
		if($datos['forma_pago'] == METODO_PAGO_BIND_QR) {			
			$fl_1 = obtenerProximoDiaHabilAAMMDD($datos['fecha_liquidacion']);
			$fl_2 = obtenerProximoDiaHabilAAMMDD($fl_1);
			$datos['fecha_liquidacion'] = $fl_2;
		}
		// Retornar el array con los datos parseados
		return $datos;
	}
	
	function parsearDevolucionBIND($registro) {
		
		//1	Código Sucursal	N4	01-04	Fijo “2222”
		//2	Código Servidor	N4	05-08	1
		//3	Código Caja	N4	9-12	Fijo “2222”
		//4	Fecha Negocio	C8	13-20	Fecha de la transacción que se inserta cuando se devuelve AAAAMMDD
		//5	Número Transacción anulación	N8	21-28	Id Transacción de Botón que se inserta cuando se realiza la devolución.
		//6	Código Operación	N2	29-30	0
		//7	N° de comercio	N6	31-36	N° de comercio
		//8	Importe	N11	37-47	Importe de Transacción. (11 caracteres numéricos. 9 enteros y 2 decimales)
		//9	Número transacción anulada	N8	48-55	Id Transacción que ha sido anulada (es decir la que se informó como exitosa)
		//10	Hora transacción Anulación	C8	56-63	Hora de la Transacción que se inserta cuando se realiza la devolución HH:MM:SS
		//11	Hora transacción Anulada	C8	64-71	Hora de la Transacción que ha sido anulada (es decir la que se informo como exitosa) HH:MM:SS
		
		// Inicializar el array con los campos definidos
		$datos = array();
	
		// Parseo de los campos según la definición
		$datos['codigo_sucursal'] = substr($registro, 0, 4);            	// 01-04		
		$datos['codigo_servidor'] = substr($registro, 4, 4);           		// 05-08
		$datos['codigo_caja'] = substr($registro, 8, 4);                	// 09-12
		$datos['fecha_negocio'] = substr($registro, 12, 8);             	// 13-20
		$datos['nro_transaccion'] = substr($registro, 20, 8);           	// 21-28
		$datos['codigo_operacion'] = substr($registro, 28, 2);          	// 29-30
		$datos['numero_de_comercio'] = substr($registro, 30, 6);        	// 31-36
		$datos['importe'] = substr($registro, 36, 11);                  	// 37-47
		$datos['transaccion_anulada'] = substr($registro, 47, 8);       	// 48-55
		$datos['hora_transaccion_anulacion'] = substr($registro, 55, 8);    // 56-63
		$datos['hora_transaccion_anulada'] = substr($registro, 63, 8);      // 64-71		
	
		// Retornar el array con los datos parseados
		return $datos;
	}
	
	function obtenerSucursalMoura($dbConnection,$comercio)
    {		
        $statement = "
            SELECT p.sucursalmoura
			FROM puntosdeventa p			
			WHERE p.comercio = ?			
        ";
		
        try {
            $statement = $dbConnection->prepare($statement);
            $statement->execute(array($comercio));
            $result = $statement->fetch(\PDO::FETCH_ASSOC);			
			
			return isset($result['sucursalmoura']) ? $result['sucursalmoura'] : '032';
            
        } catch (\PDOException $e) {
            exit($e->getMessage());
        }    
    }
	
	function obtenerRazonSocial($dbConnection, $comercio)
    {					
        $statement = "
            SELECT p.razonsocial
			FROM puntosdeventa p			
			WHERE p.comercio = ?			
        ";
		
        try {
            $statement = $dbConnection->prepare($statement);
            $statement->execute(array($comercio));
            $result = $statement->fetch(\PDO::FETCH_ASSOC);			
			
			return isset($result['razonsocial']) ? $result['razonsocial'] : '';
            
        } catch (\PDOException $e) {
            exit($e->getMessage());
        }    
    }
	
	function obtenerNroReferencia($dbConnection, $comercio)
    {			
        $statement = "
            SELECT p.nroreferencia
			FROM puntosdeventa p			
			WHERE p.comercio = ?			
        ";
		
        try {
            $statement = $dbConnection->prepare($statement);
            $statement->execute(array($comercio));
            $result = $statement->fetch(\PDO::FETCH_ASSOC);			
			
			return isset($result['nroreferencia']) ? $result['nroreferencia'] : '';
            
        } catch (\PDOException $e) {
            exit($e->getMessage());
        }    
    }
	
	function obtenerCuit($dbConnection, $comercio)
    {			
        $statement = "
            SELECT p.cuit
			FROM puntosdeventa p			
			WHERE p.comercio = ?			
        ";
		
        try {
            $statement = $dbConnection->prepare($statement);
            $statement->execute(array($comercio));
            $result = $statement->fetch(\PDO::FETCH_ASSOC);			
			
			return isset($result['cuit']) ? $result['cuit'] : '';
            
        } catch (\PDOException $e) {
            exit($e->getMessage());
        }    
    }
	
	function obtenerIdPdv($dbConnection, $comercio)
    {			
        $statement = "
            SELECT p.id
			FROM puntosdeventa p			
			WHERE p.comercio = ?			
        ";
		
        try {
            $statement = $dbConnection->prepare($statement);
            $statement->execute(array($comercio));
            $result = $statement->fetch(\PDO::FETCH_ASSOC);			
			
			return isset($result['id']) ? $result['id'] : '';
            
        } catch (\PDOException $e) {
            exit($e->getMessage());
        }    
    }
	
	function comercioExistente($dbConnection,$comercio)
    {		
        $statement = "
            SELECT p.id
			FROM puntosdeventa p			
			WHERE p.comercio = ?			
        ";
		
        try {
            $statement = $dbConnection->prepare($statement);
            $statement->execute(array($comercio));
            $result = $statement->fetch(\PDO::FETCH_ASSOC);			
			
			return isset($result['id']) ? 1 : 0;
            
        } catch (\PDOException $e) {
            exit($e->getMessage());
        }    
    }
	
	function obtenerCuentaContable($division, $concepto)
    {			
        switch ($concepto) {
			case 'PD':
					switch ($division) {
							case '032':
								return CUENTA_CONTABLE_032_PD;
							break;							
							case '033':
								return CUENTA_CONTABLE_033_PD;
							break;
							case '034':
								return CUENTA_CONTABLE_034_PD;
							break;							
							case '035':
								return CUENTA_CONTABLE_035_PD;
							break;
							case '036':
								return CUENTA_CONTABLE_036_PD;
							break;							
							case '037':
								return CUENTA_CONTABLE_037_PD;
							break;
					}
			break;
			
			case 'MO':
					switch ($division) {
							case '032':
								return CUENTA_CONTABLE_032_MO;
							break;							
							case '033':
								return CUENTA_CONTABLE_033_MO;
							break;
							case '034':
								return CUENTA_CONTABLE_034_MO;
							break;							
							case '035':
								return CUENTA_CONTABLE_035_MO;
							break;
							case '036':
								return CUENTA_CONTABLE_036_MO;
							break;							
							case '037':
								return CUENTA_CONTABLE_037_MO;
							break;
					}
			break;
			
			case 'GB':
					return CUENTA_CONTABLE_GB_GB;
			break;
			
			default:
					return '';
			break;
		}
    }
	
	function obtenerCentroDeLucro($division)
    {	
		switch ($division) {
			case '032':
				return CENTRO_DE_LUCRO_032;
			break;
			case '033':
				return CENTRO_DE_LUCRO_033;
			break;
			case '034':
				return CENTRO_DE_LUCRO_034;
			break;			
			case '035':
				return CENTRO_DE_LUCRO_035;
			break;
			case '036':
				return CENTRO_DE_LUCRO_036;
			break;			
			case '037':
				return CENTRO_DE_LUCRO_037;
			break;
			default:
				return '';
			break;
		}	
    }
	
	function obtenerNroSAP($dbConnection, $comercio)
    {			
        $statement = "
            SELECT p.nroSAP
			FROM puntosdeventa p			
			WHERE p.comercio = ?			
        ";
		
        try {
            $statement = $dbConnection->prepare($statement);
            $statement->execute(array($comercio));
            $result = $statement->fetch(\PDO::FETCH_ASSOC);		
			
			return isset($result['nroSAP']) ? $result['nroSAP'] : '';
            
        } catch (\PDOException $e) {
            exit($e->getMessage());
        }    
    }
	
	function pdvLiquidaBIND($dbConnection, $comercio)
    {			
        $statement = "
            SELECT p.liquidabind
			FROM puntosdeventa p			
			WHERE p.comercio = ?		
        ";
		
        try {
            $statement = $dbConnection->prepare($statement);
            $statement->execute(array($comercio));
            $result = $statement->fetch(\PDO::FETCH_ASSOC);		
			
			return isset($result['liquidabind']) ? $result['liquidabind'] : '';
            
        } catch (\PDOException $e) {
            exit($e->getMessage());
        }    
    }
	
	function convertirImporteFormatoBINDANumerico($importeTexto) {
		
		// Separa la parte entera y la parte decimal
		$parteEntera = substr($importeTexto, 0, 9);
		$parteDecimal = substr($importeTexto, 9, 2);

		// Convierte a número flotante
		$importeNumerico = (float)($parteEntera . '.' . $parteDecimal);

		return $importeNumerico;
	}
	
	function convertirImporteNumericoAFormatoMoura($importe) {
		// Asegura que el importe tenga dos decimales
		$importeConDosDecimales = number_format($importe, 2, ',', '.');
			
		// Agrega el símbolo '$ ' al principio
		$importeFormato = '$ ' . $importeConDosDecimales;
			
		// Si el importe tiene menos de 15 caracteres, se completa con espacios a la izquierda
		$importeFormato = str_pad($importeFormato, 15, ' ', STR_PAD_RIGHT);
				
		return $importeFormato;
	}
	
	
	//Convierte importe en formato BIND al formato requerido en el archivo que maneja Moura
	function convertirImporteBINDAMoura($importe_entrada) 
	{
		// Forzar configuración regional para que use el punto como separador de miles
		setlocale(LC_NUMERIC, 'en_US');
		
		// Aseguramos que la entrada tenga exactamente 11 caracteres
		$parte_entera = substr($importe_entrada, 0, 9); // Los primeros 9 dígitos son la parte entera
		$parte_decimal = substr($importe_entrada, 9, 2); // Los últimos 2 dígitos son la parte decimal
	
		// Se convierte a número
		$importe_real = intval($parte_entera) * 100 + intval($parte_decimal);
		$importe_real = $importe_real / 100; 
	
		// Formatear el valor como moneda con $ y puntos para miles
		$importe_formateado = "$ " . number_format($importe_real, 2, ',', '.'); 
		
		// Aseguramos 15 caracteres de salida, completando con espacios si es necesario
		return str_pad($importe_formateado, 15, ' ', STR_PAD_LEFT);
	}

	function convertirMetodoPago($mp)
	{
		
		switch ($mp) {
			case METODO_PAGO_BIND_DEBITO:
					return METODO_PAGO_MOURA_DEBITO;
			break;
			case METODO_PAGO_BIND_CREDITO:
			case METODO_PAGO_BIND_CREDITO_CUOTAS:			
					return METODO_PAGO_MOURA_CREDITO;
			break;
			case METODO_PAGO_BIND_DEBIN:
				return METODO_PAGO_MOURA_PREPAGA;
			break;
			case METODO_PAGO_BIND_QR:
					return METODO_PAGO_MOURA_QR;
			break;
			default:
					return '  ';
			break;
			}
	}

	//Retorna el valor que debe ir en el campo EstadoCheque en el archivo Moura
	//El valor tiene el formato PP-MM donde PP: Porcentaje punto de venta / MM: Porcentaje Moura
	function obtenerEstadoCheque($dbConnection, $comercio, $fecha_liquidacion )
    {		
		if (strlen($fecha_liquidacion) == 6) {
			// Formato AAMMDD (ejemplo: 240322)
			$fecha = DateTime::createFromFormat('ymd', $fecha_liquidacion)->format('Y-m-d H:i:s');
		} elseif (strlen($fecha_liquidacion) == 8) {
			// Formato AAAAMMDD (ejemplo: 20230322)
			$fecha = DateTime::createFromFormat('Ymd', $fecha_liquidacion)->format('Y-m-d H:i:s');
		} else {
			// Si la longitud no es válida, retorna un error o valor nulo
			return false;
		}
		
        $statement = "
            SELECT s.porcentajepdv
			FROM puntosdeventa p
			JOIN splits s 
				ON p.id = s.idpdv
			WHERE p.comercio = ?
			AND s.fecha = (
				SELECT MAX(s2.fecha)
				FROM splits s2
				WHERE s2.idpdv = p.id
					AND s2.fecha < ?
					AND s2.estatus_aprobacion = 'Aprobado'
					AND s2.borrado_en IS NULL
				)
			AND s.estatus_aprobacion = 'Aprobado'
			AND s.borrado_en IS NULL;
        ";
		
        try {
            $statement = $dbConnection->prepare($statement);
            $statement->execute(array($comercio,$fecha));
            $result = $statement->fetch(\PDO::FETCH_ASSOC);
			
			if (isset($result['porcentajepdv'])){
				// Si hay un resultado, devolver el texto con los porcentajes separados por guion, sino devolver null
				$porcentajemoura = 100 - $result['porcentajepdv'];
				
				return  strval($result['porcentajepdv']) . "-" . strval($porcentajemoura);
			}
			return null;
            
        } catch (\PDOException $e) {
            exit($e->getMessage());
        }    
    }
	
	//Retorna el porcentaje de una venta que corresponde a Moura
	function obtenerPorcentajeMoura($dbConnection, $comercio, $fecha_liquidacion )
    {		
		if (strlen($fecha_liquidacion) == 6) {
			// Formato AAMMDD (ejemplo: 240322)
			$fecha = DateTime::createFromFormat('ymd', $fecha_liquidacion)->format('Y-m-d H:i:s');
		} elseif (strlen($fecha_liquidacion) == 8) {
			// Formato AAAAMMDD (ejemplo: 20230322)
			$fecha = DateTime::createFromFormat('Ymd', $fecha_liquidacion)->format('Y-m-d H:i:s');
		} else {
			// Si la longitud no es válida, retorna un error o valor nulo
			return false;
		}
		
        $statement = "
            SELECT s.porcentajepdv
			FROM puntosdeventa p
			JOIN splits s 
				ON p.id = s.idpdv
			WHERE p.comercio = ?
			AND s.fecha = (
				SELECT MAX(s2.fecha)
				FROM splits s2
				WHERE s2.idpdv = p.id
					AND s2.fecha < ?
					AND s2.estatus_aprobacion = 'Aprobado'
					AND s2.borrado_en IS NULL
				)
			AND s.estatus_aprobacion = 'Aprobado'
			AND s.borrado_en IS NULL;
        ";
		
        try {
            $statement = $dbConnection->prepare($statement);
            $statement->execute(array($comercio,$fecha));
            $result = $statement->fetch(\PDO::FETCH_ASSOC);
			
			if (isset($result['porcentajepdv'])){
				// Si hay un resultado, devolver el texto con el porcentaje, sino devolver null
				$porcentajemoura = 100 - $result['porcentajepdv'];
				
				return $porcentajemoura;
			}
			return 100;
            
        } catch (\PDOException $e) {
            exit($e->getMessage());
        }    
    }
	
	//Retorna el porcentaje de una venta que corresponde al PDV
	function obtenerPorcentajePDV($dbConnection, $comercio, $fecha_liquidacion )
    {		
		if (strlen($fecha_liquidacion) == 6) {
			// Formato AAMMDD (ejemplo: 240322)
			$fecha = DateTime::createFromFormat('ymd', $fecha_liquidacion)->format('Y-m-d H:i:s');
		} elseif (strlen($fecha_liquidacion) == 8) {
			// Formato AAAAMMDD (ejemplo: 20230322)
			$fecha = DateTime::createFromFormat('Ymd', $fecha_liquidacion)->format('Y-m-d H:i:s');
		} else {
			// Si la longitud no es válida, retorna un error o valor nulo
			return false;
		}
		
        $statement = "
            SELECT s.porcentajepdv
			FROM puntosdeventa p
			JOIN splits s 
				ON p.id = s.idpdv
			WHERE p.comercio = ?
			AND s.fecha = (
				SELECT MAX(s2.fecha)
				FROM splits s2
				WHERE s2.idpdv = p.id
					AND s2.fecha < ?
					AND s2.estatus_aprobacion = 'Aprobado'
					AND s2.borrado_en IS NULL
				)
			AND s.estatus_aprobacion = 'Aprobado'
			AND s.borrado_en IS NULL;
        ";
		
        try {
            $statement = $dbConnection->prepare($statement);
            $statement->execute(array($comercio,$fecha));
            $result = $statement->fetch(\PDO::FETCH_ASSOC);
			
			if (isset($result['porcentajepdv'])){
				// Si hay un resultado, devolver el texto con el porcentaje, sino devolver null				
				
				return $result['porcentajepdv'];
			}
			return 0;
            
        } catch (\PDOException $e) {
            exit($e->getMessage());
        }    
    }
	
	function convertirFechaBINDAMoura($fecha) {
		// Verifica si la longitud de la fecha es 6 (AAMMDD) o 8 (AAAAMMDD)
		if (strlen($fecha) == 6) {
			// Formato AAMMDD (ejemplo: 240322)
			$fechaFormato = DateTime::createFromFormat('ymd', $fecha);
		} elseif (strlen($fecha) == 8) {
			// Formato AAAAMMDD (ejemplo: 20230322)
			$fechaFormato = DateTime::createFromFormat('Ymd', $fecha);
		} else {
			// Si la longitud no es válida, retorna un error o valor nulo
			return false;
		}
    
		// Retorna la fecha en formato DD/MM/AAAA
		return $fechaFormato->format('d/m/Y');
	}
	
	function obtenerProximoDiaHabilAAMMDD($fechaEntrada) {
		// Detectar el formato de entrada
		$formatoEntrada = strlen($fechaEntrada) === 6 ? 'ymd' : 'Ymd';
		$formatoSalida = strlen($fechaEntrada) === 6 ? 'ymd' : 'Ymd';
		
		// Convertir la fecha de entrada a un objeto DateTime
		$fecha = DateTime::createFromFormat($formatoEntrada, $fechaEntrada);
		
		if (!$fecha) {
			throw new Exception("Fecha inválida proporcionada.");
		}	
		
		// Iterar hasta encontrar un día hábil		
		do {
			$fecha->modify('+1 day');
			$diaSemana = $fecha->format('N'); // 1 = Lunes, 7 = Domingo
		} while ($diaSemana >= 6 || in_array($fecha->format($formatoEntrada), FERIADOS) ); // Repetir si es Sábado (6) o Domingo (7) o feriado
		
		// Devolver la fecha en el mismo formato que se ingresó
		return $fecha->format($formatoSalida);
	}
	
	function obtenerProximoDiaHabilDDMMAA($fechaEntrada) {
		// Detectar el formato de entrada
		$formatoEntrada = strlen($fechaEntrada) === 6 ? 'dmy' : 'dmY';
		$formatoSalida = strlen($fechaEntrada) === 6 ? 'dmy' : 'dmY';
		
		// Convertir la fecha de entrada a un objeto DateTime
		$fecha = DateTime::createFromFormat($formatoEntrada, $fechaEntrada);
		
		if (!$fecha) {
			throw new Exception("Fecha inválida proporcionada.");
		}	
		
		// Iterar hasta encontrar un día hábil
		do {
			$fecha->modify('+1 day');
			$diaSemana = $fecha->format('N'); // 1 = Lunes, 7 = Domingo
		} while ($diaSemana >= 6 || in_array($fecha->format($formatoEntrada), FERIADOS) ); // Repetir si es Sábado (6) o Domingo (7) o feriado
		
		// Devolver la fecha en el mismo formato que se ingresó
		return $fecha->format($formatoSalida);
	}
	
	function esSabadoDomingoFeriadoDDMMAA($fechaEntrada) {
		// Detectar el formato de entrada
		$formatoEntrada = strlen($fechaEntrada) === 6 ? 'dmy' : 'dmY';
		
		// Convertir la fecha de entrada a un objeto DateTime
		$fecha = DateTime::createFromFormat($formatoEntrada, $fechaEntrada);
		
		if (!$fecha) {
			throw new Exception("Fecha inválida proporcionada.");
		}
		//echo 'El dia ' . $fechaEntrada . 'tiene valor ' . $fecha->format('N') . ' \n';
		
		// Si la fecha corresponde a un Sabado o a un Domingo se la pasa para un Lunes
		if($fecha->format('N') == 6 || $fecha->format('N') == 7 || in_array($fecha->format($formatoEntrada), FERIADOS) ){  //Sabado o Domingo o Feriado
			return 1;
		}
		
		return 0;
	}
	
	function esSabadoDomingoFeriadoAAMMDD($fechaEntrada) {
		// Detectar el formato de entrada
		$formatoEntrada = strlen($fechaEntrada) === 6 ? 'ymd' : 'Ymd';
		
		// Convertir la fecha de entrada a un objeto DateTime
		$fecha = DateTime::createFromFormat($formatoEntrada, $fechaEntrada);
		
		if (!$fecha) {
			throw new Exception("Fecha inválida proporcionada.");
		}
		//echo 'El dia ' . $fechaEntrada . 'tiene valor ' . $fecha->format('N') . ' \n';
		echo $fecha->format('Y-m-d (N) l');
		// Si la fecha corresponde a un Sabado o a un Domingo se la pasa para un Lunes
		if($fecha->format('N') == 6 || $fecha->format('N') == 7 || in_array($fecha->format($formatoEntrada), FERIADOS) ){  //Sabado o Domingo o Feriado
			return 1;
		}
		
		return 0;
	}

	function obtenerPorcentajesDeducciones($dbConnection, $fecha_liquidacion)
	{
		if (strlen($fecha_liquidacion) == 6) {
			// Formato AAMMDD (ejemplo: 240322)
			$fecha = DateTime::createFromFormat('ymd', $fecha_liquidacion)->format('Y-m-d H:i:s');
		} elseif (strlen($fecha_liquidacion) == 8) {
			// Formato AAAAMMDD (ejemplo: 20230322)
			$fecha = DateTime::createFromFormat('Ymd', $fecha_liquidacion)->format('Y-m-d H:i:s');
		} else {
			// Si la longitud no es válida, retorna un error o valor nulo
			return false;
		}
		
		
		$statement = "
			SELECT p.idporcentaje, p.porcentaje
			FROM porcentajes p
			WHERE p.idporcentaje IN (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
			AND p.fecha = (
				SELECT MAX(p2.fecha)
				FROM porcentajes p2
				WHERE p2.idporcentaje = p.idporcentaje
					AND p2.fecha < ?
			);
		";
	
		try {
			$statement = $dbConnection->prepare($statement);
			$statement->execute([
				ID_COMISION_PAGOS_DIGITALES, 
				ID_COMISION_PRONTO_PAGO, 
				ID_DESCUENTO_3_CUOTAS, 
				ID_ARANCEL_TARJETA_DEBITO,
				ID_ARANCEL_TARJETA_CREDITO_VISA,
				ID_ARANCEL_TARJETA_CREDITO_AMEX,
				ID_ARANCEL_TARJETA_CREDITO_MASTERCARD,
				ID_DESCUENTO_6_CUOTAS,
				ID_COMISION_PD_DEBITO,
				ID_COMISION_PD_CREDITO,
				ID_COMISION_PD_QR,
				ID_SUBSIDIO_MOURA,
				ID_COSTO_ACREDITACION_DEBITO,
				ID_COSTO_ACREDITACION_CREDITO,
				ID_COSTO_ACREDITACION_QR,
				ID_AHORRO_SPLIT_50_50,
				ID_AHORRO_SPLIT_70_30,
				ID_AHORRO_SPLIT_65_35,
				ID_OTROS_IMPUESTOS,
				ID_CFT_CLIENTE_3_CUOTAS,
				ID_CFT_CLIENTE_6_CUOTAS,
				ID_COMISION_PD_CREDITO_3_CUOTAS,
				ID_COMISION_PD_CREDITO_6_CUOTAS,
				ID_COSTO_ACREDITACION_CREDITO_3_CUOTAS,
				ID_COSTO_ACREDITACION_CREDITO_6_CUOTAS,
				ID_COSTO_ACREDITACION_CREDITO_3_CUOTAS_RESTA,
				ID_COSTO_ACREDITACION_CREDITO_6_CUOTAS_RESTA,
				ID_AHORRO_SPLIT_100_0,
				ID_COSTO_FINANCIERO_MIPYME_3_CUOTAS,
				ID_COSTO_FINANCIERO_MIPYME_6_CUOTAS,
				ID_ARANCEL_QR,
				$fecha
			]);
			
			$result = $statement->fetchAll(\PDO::FETCH_ASSOC);
	
			if (!empty($result)) {
				// Crear un array asociativo con los resultados encontrados
				$porcentajes = [];
				foreach ($result as $row) {
					$porcentajes[$row['idporcentaje']] = floatval($row['porcentaje']);
				}
				
				// Devolver el array con los porcentajes encontrados
				return $porcentajes;
			}
			
			// Si no se encontraron resultados, devolver valores por defecto (100)
			return [
				ID_COMISION_PAGOS_DIGITALES => 100,
				ID_COMISION_PRONTO_PAGOS => 100,
				ID_DESCUENTO_3_CUOTAS => 100,
				ID_ARANCEL_TARJETA_DEBITO => 100,
				ID_ARANCEL_TARJETA_CREDITO_VISA => 100,
				ID_ARANCEL_TARJETA_CREDITO_AMEX => 100,
				ID_ARANCEL_TARJETA_CREDITO_MASTERCARD => 100,
				ID_DESCUENTO_6_CUOTAS => 100,
				ID_COMISION_PD_DEBITO => 100,
				ID_COMISION_PD_CREDITO => 100,
				ID_COMISION_PD_QR => 100,
				ID_SUBSIDIO_MOURA => 100,
				ID_COSTO_ACREDITACION_DEBITO => 100,
				ID_COSTO_ACREDITACION_CREDITO => 100,
				ID_COSTO_ACREDITACION_QR => 100,
				ID_AHORRO_SPLIT_50_50 => 100,
				ID_AHORRO_SPLIT_70_30 => 100,
				ID_AHORRO_SPLIT_65_35 => 100,
				ID_OTROS_IMPUESTOS => 100,
				ID_CFT_CLIENTE_3_CUOTAS => 100,
				ID_CFT_CLIENTE_6_CUOTAS => 100,
				ID_COMISION_PD_CREDITO_3_CUOTAS => 100,
				ID_COMISION_PD_CREDITO_6_CUOTAS => 100,
				ID_COSTO_ACREDITACION_CREDITO_3_CUOTAS => 100,
				ID_COSTO_ACREDITACION_CREDITO_6_CUOTAS => 100,
				ID_COSTO_ACREDITACION_CREDITO_3_CUOTAS_RESTA => 100,
				ID_COSTO_ACREDITACION_CREDITO_6_CUOTAS_RESTA => 100,
				ID_AHORRO_SPLIT_100_0 => 100,
				ID_COSTO_FINANCIERO_MIPYME_3_CUOTAS => 100,
				ID_COSTO_FINANCIERO_MIPYME_6_CUOTAS => 100,
				ID_ARANCEL_QR => 100,
			];
			
		} catch (\PDOException $e) {
			exit($e->getMessage());
		}
	}
	
	function obtenerPorcentajePorTipoOperacion($dbConnection, $fecha_liquidacion, $tipo_operacion_porcentaje_pdv)
	{
		if (strlen($fecha_liquidacion) == 6) {
			// Formato AAMMDD (ejemplo: 240322)
			$fecha = DateTime::createFromFormat('ymd', $fecha_liquidacion)->format('Y-m-d H:i:s');
		} elseif (strlen($fecha_liquidacion) == 8) {
			// Formato AAAAMMDD (ejemplo: 20230322)
			$fecha = DateTime::createFromFormat('Ymd', $fecha_liquidacion)->format('Y-m-d H:i:s');
		} else {
			// Si la longitud no es válida, retorna un error o valor nulo
			return false;
		}
		
		
		$statement = "
			SELECT p.porcentaje
			FROM porcentajes p
			WHERE p.tipooperacion = ?
			AND p.fecha = (
				SELECT MAX(p2.fecha)
				FROM porcentajes p2
				WHERE p2.idporcentaje = p.idporcentaje
					AND p2.fecha < ?
			);
		";
	
		try {
			$statement = $dbConnection->prepare($statement);
			$statement->execute([
				$tipo_operacion_porcentaje_pdv,				
				$fecha
			]);
			
			$result = $statement->fetch(\PDO::FETCH_ASSOC);
	
			if (!empty($result)) {
			// Devolver el id
				return $result['porcentaje'];		
			}

			return 0;			
			
		} catch (\PDOException $e) {
			exit($e->getMessage());
		}
	}

	function recalcularImporteBruto($dbConnection, $importeBruto, $fecha_liquidacion, $cuotas){
		
		$porcentajes = obtenerPorcentajesDeducciones($dbConnection, $fecha_liquidacion);	
		
		if($cuotas == 3) {
			return $importeBruto * ( 1 + $porcentajes[ID_CFT_CLIENTE_3_CUOTAS] / 100 ); 
		}
		elseif($cuotas == 6) {
			return $importeBruto * ( 1 + $porcentajes[ID_CFT_CLIENTE_6_CUOTAS] / 100 ); 
		}
	}
	
	function calcularImporteNeto($dbConnection, $importeBruto, $importeBrutoOriginal, $fecha_liquidacion, $forma_de_pago, $cuotas ){
		
		$porcentajes = obtenerPorcentajesDeducciones($dbConnection, $fecha_liquidacion);		
		
		
		if($forma_de_pago == METODO_PAGO_BIND_CREDITO || $forma_de_pago == METODO_PAGO_BIND_DEBIN){
			$comision = $importeBruto * ($porcentajes[ID_COMISION_PD_CREDITO] + $porcentajes[ID_COMISION_PRONTO_PAGO] ) / 100  ;
		}
		elseif ($forma_de_pago == METODO_PAGO_BIND_CREDITO_CUOTAS){
			if($cuotas == 3){
				$comision = $importeBruto * ($porcentajes[ID_COMISION_PD_CREDITO_3_CUOTAS] + $porcentajes[ID_COMISION_PRONTO_PAGO] ) / 100  ;
			}
			elseif($cuotas == 6){
				$comision = $importeBruto * ($porcentajes[ID_COMISION_PD_CREDITO_6_CUOTAS] + $porcentajes[ID_COMISION_PRONTO_PAGO] ) / 100  ;
			}
		}
		elseif($forma_de_pago == METODO_PAGO_BIND_DEBITO){
			$comision = $importeBruto * ($porcentajes[ID_COMISION_PD_DEBITO] + $porcentajes[ID_COMISION_PRONTO_PAGO] ) / 100  ;
		}
		else{
			$comision = $importeBruto * ($porcentajes[ID_COMISION_PD_QR] + $porcentajes[ID_COMISION_PRONTO_PAGO] ) / 100  ;
		}
		
		if($forma_de_pago == METODO_PAGO_BIND_CREDITO || $forma_de_pago == METODO_PAGO_BIND_DEBIN){
			$costoAcreditacion = $importeBruto * $porcentajes[ID_COSTO_ACREDITACION_CREDITO] / 100  ;
		}
		elseif($forma_de_pago == METODO_PAGO_BIND_CREDITO_CUOTAS){
			if($cuotas == 3){
				$costoAcreditacion = ($importeBruto * $porcentajes[ID_COSTO_ACREDITACION_CREDITO_3_CUOTAS] - $importeBrutoOriginal * $porcentajes[ID_COSTO_ACREDITACION_CREDITO_3_CUOTAS_RESTA]) / 100  ;
			}
			elseif($cuotas == 6){
				$costoAcreditacion = ($importeBruto * $porcentajes[ID_COSTO_ACREDITACION_CREDITO_6_CUOTAS] - $importeBrutoOriginal * $porcentajes[ID_COSTO_ACREDITACION_CREDITO_6_CUOTAS_RESTA]) / 100  ;
			}
		}
		elseif($forma_de_pago == METODO_PAGO_BIND_DEBITO){
			$costoAcreditacion = $importeBruto * $porcentajes[ID_COSTO_ACREDITACION_DEBITO] / 100  ;
		}
		else{
			$costoAcreditacion = $importeBruto * $porcentajes[ID_COSTO_ACREDITACION_QR] / 100  ;
		}
	
		if($forma_de_pago == METODO_PAGO_BIND_CREDITO_CUOTAS){
			       //Para descuento cuotas ese % de descuento ya incluye IVA		
			if($cuotas == 3){
				$descuentoCuotas = $importeBrutoOriginal * ( $porcentajes[ID_CFT_CLIENTE_3_CUOTAS]) / 100  ;
			}
			elseif($cuotas == 6){
				$descuentoCuotas = $importeBrutoOriginal * ( $porcentajes[ID_CFT_CLIENTE_6_CUOTAS]) / 100  ;
			}				   
		}
		else {
			$descuentoCuotas = 0;
		}
		
		if($forma_de_pago == METODO_PAGO_BIND_CREDITO_CUOTAS || $forma_de_pago == METODO_PAGO_BIND_CREDITO || $forma_de_pago == METODO_PAGO_BIND_DEBIN){
			$arancelTarjeta = $importeBruto * $porcentajes[ID_ARANCEL_TARJETA_CREDITO_VISA] / 100  ;
		}
		elseif($forma_de_pago == METODO_PAGO_BIND_DEBITO) {
			$arancelTarjeta = $importeBruto * $porcentajes[ID_ARANCEL_TARJETA_DEBITO] / 100  ;
		}
		elseif($forma_de_pago == METODO_PAGO_BIND_QR) {
			$arancelTarjeta = $importeBruto * $porcentajes[ID_ARANCEL_QR] / 100  ;
		}
		else{
			$arancelTarjeta = 0;
		}
		
		if($forma_de_pago == METODO_PAGO_BIND_CREDITO_CUOTAS){
			       
			if($cuotas == 3){
				$beneficioCredMoura = ($importeBrutoOriginal *  $porcentajes[ID_CFT_CLIENTE_3_CUOTAS] - $importeBruto * $porcentajes[ID_COMISION_PD_CREDITO] - $importeBrutoOriginal *  $porcentajes[ID_COSTO_FINANCIERO_MIPYME_3_CUOTAS]) / 100;
			}
			elseif($cuotas == 6){
				$beneficioCredMoura = ($importeBrutoOriginal *  $porcentajes[ID_CFT_CLIENTE_6_CUOTAS] - $importeBruto * $porcentajes[ID_COMISION_PD_CREDITO] - $importeBrutoOriginal *  $porcentajes[ID_COSTO_FINANCIERO_MIPYME_6_CUOTAS]) / 100;
			}				   
		}
		else {
			$beneficioCredMoura = 0;
		}

		$montoiva = ($comision + $arancelTarjeta + $costoAcreditacion) * IVA ; // 25/06/2025 $descuentoCuotas lleva IVA = 0;
		//Para descuento cuotas ese % de descuento ya incluye IVA por lo cual no lo resto del bruto como iva aparte
		
		$sirtac = $importeBruto * PORCENTAJE_SIRTAC / 100;
		
		$otrosImpuestos = $importeBruto * $porcentajes[ID_OTROS_IMPUESTOS] / 100  ;
		
		return $importeBruto - $comision - $descuentoCuotas - $arancelTarjeta - $montoiva - $costoAcreditacion - $sirtac - $otrosImpuestos + $beneficioCredMoura;		
	}
	
	function calcularSubsidioMoura($dbConnection, $importeNetoMoura, $fecha_liquidacion){
		
		$porcentajes = obtenerPorcentajesDeducciones($dbConnection, $fecha_liquidacion);
		
		return $importeNetoMoura * ($porcentajes[ID_SUBSIDIO_MOURA] * (1 + IVA) ) / 100;
		
	}
	
	function formatearDatosBINDaMoura($dbConnection, $datosBIND) 
	{		
		// Inicializar el array con los campos definidos
		$datosMoura = array();
		
		$datosMoura['sucursal'] = obtenerSucursalMoura($dbConnection, $datosBIND['numero_de_comercio']);
		
		//Corresponde a lo acreditado solo por lo que le corresponde a Moura por SPLIT
		$importeBruto = convertirImporteFormatoBINDANumerico($datosBIND['importe']);	
		
		$importeBrutoOriginal = $importeBruto;  //Por si luego hay que recalcular el bruto se guarda el original para calcular descuentoCuotas
		
		//CAMBIO AGOSTO 2025 EL SISTEMA VA A QUEDAR PARAMETRIZABLE POR TABLA 
		//PUEDE LIQUIDARSE POR PRONTO PAGO SI EL PDV ESTA CONFIGURADO ASI O SI NO POR LA FECHA INDICADA POR EL BANCO
		//Por Pronto Pago la fecha de liquidación no es la que indica el banco sino la proxima fecha habil al día de pago
		//Si es Sabado o Domingo Pagos Digitales considera que es como si fueran Lunes por lo que normalmente el proximo dia habil es Martes
		
		if(pdvLiquidaBIND($dbConnection, $datosBIND['numero_de_comercio']) == 0){    //Liquidacion por Pronto Pago
			if (esSabadoDomingoFeriadoAAMMDD($datosBIND['fecha_pago']) == 0 ){  //AAMMDD
				echo 'Es dia de semana';
				$fechaLiquidacion = obtenerProximoDiaHabilAAMMDD($datosBIND['fecha_pago']);		
			}
			else {
			
				echo 'Es Sabado Domingo Feriado';
				$fechaLiquidacion = obtenerProximoDiaHabilAAMMDD($datosBIND['fecha_pago']);	
				$fechaLiquidacion = obtenerProximoDiaHabilAAMMDD($fechaLiquidacion);	
			}
		}
		else {  //Liquidacion indicada por el BIND
			$fechaLiquidacion = $datosBIND['fecha_liquidacion'];
		}
		
		//Si es pago en cuotas se suma al bruto el Cft. Cliente.
		if($datosBIND['forma_pago'] == METODO_PAGO_BIND_CREDITO_CUOTAS){
			$importeBruto = recalcularImporteBruto($dbConnection, $importeBruto, $fechaLiquidacion, ltrim($datosBIND['cantidad_de_cuotas'],'0'));			
		}
		
		
		$importeNeto = calcularImporteNeto($dbConnection, $importeBruto, $importeBrutoOriginal, $fechaLiquidacion, $datosBIND['forma_pago'], ltrim($datosBIND['cantidad_de_cuotas'],'0') );			
		
		$importeNetoMoura = $importeNeto * (obtenerPorcentajeMoura($dbConnection, $datosBIND['numero_de_comercio'],$fechaLiquidacion) / 100 );
		$datosMoura['importe'] = convertirImporteNumericoAFormatoMoura($importeNetoMoura);
		
		$datosMoura['metodoPago'] = convertirMetodoPago($datosBIND['forma_pago']);
		$datosMoura['estadoCheque'] = obtenerEstadoCheque($dbConnection, $datosBIND['numero_de_comercio'],$fechaLiquidacion);
		$datosMoura['nroComprobante'] = str_pad($datosBIND['transaccion'], 18, "0", STR_PAD_LEFT);
		$datosMoura['estadoCobranza'] = ESTADO_COBRANZA_CREDMOURA;
		
		if($datosBIND['forma_pago'] == METODO_PAGO_BIND_CREDITO_CUOTAS){
			$datosMoura['canal'] = str_pad(trim($datosBIND['cantidad_de_cuotas']) == '' ? '3' : trim($datosBIND['cantidad_de_cuotas']) , 5, "0", STR_PAD_LEFT);
		}
		else{
			$datosMoura['canal'] = str_pad(trim($datosBIND['cantidad_de_cuotas']) == '' ? '1' : trim($datosBIND['cantidad_de_cuotas']) , 5, "0", STR_PAD_LEFT);
		}
		
		$datosMoura['denominacionDepositante'] = str_pad(obtenerRazonSocial($dbConnection, $datosBIND['numero_de_comercio']), 31, " ", STR_PAD_RIGHT);
		$datosMoura['refDepositante'] = str_pad(obtenerNroReferencia($dbConnection, $datosBIND['numero_de_comercio']), 16, " ", STR_PAD_RIGHT);
		$datosMoura['cuitDepositante'] = str_pad(obtenerCuit($dbConnection, $datosBIND['numero_de_comercio']), 11, " ", STR_PAD_RIGHT);
		$datosMoura['primerVencimiento'] = '          ';
		$datosMoura['importePrimerVencimiento'] =  convertirImporteNumericoAFormatoMoura($importeNeto); // Importe total de la venta
		$datosMoura['segundoVencimiento'] = '          ';
		$datosMoura['importeSegundoVencimiento'] = '                   ';
		$datosMoura['motivoRechazo'] = '                 ';
		$datosMoura['nroCheque'] = str_pad($datosBIND['transaccion'], 14, "0", STR_PAD_LEFT);
		$datosMoura['fechaCheque'] = convertirFechaBINDAMoura($datosBIND['fecha_pago']);
		$datosMoura['importeCheque'] = $datosMoura['importe'];
		$datosMoura['importeChequeBruto'] = convertirImporteNumericoAFormatoMoura($importeBruto);  //El bruto se almacena en la BD del sistema
		$datosMoura['importeAplicado'] = '                   ';
		$datosMoura['fechaCobranza'] = convertirFechaBINDAMoura($fechaLiquidacion);
		$datosMoura['nroCobranza'] = str_pad($datosBIND['transaccion'], 16, "0", STR_PAD_LEFT);
		
		//Estos campos se utilizan solo para el archivo de Liquidacion
		$subsidioMoura = calcularSubsidioMoura($dbConnection, $importeBruto, $fechaLiquidacion);
		
		$subsidioMoura = round($subsidioMoura, 2);
		$importeNetoMoura = round($importeNetoMoura, 2);
		
		$datosMoura['importeAcreditadoMoura'] = convertirImporteNumericoAFormatoMoura($importeNetoMoura - $subsidioMoura); 
		$datosMoura['subsidioMoura'] = convertirImporteNumericoAFormatoMoura($subsidioMoura);
		$datosMoura['nroSAP'] = obtenerNroSAP($dbConnection, $datosBIND['numero_de_comercio']);
		
		return $datosMoura;
	}
	
	// Genera el registro del archivo de transacciones diarias que se envía a Moura
	function generarRegistroTxtMoura($datos) 
	{
		// Variables para cada campo
		//$sucursal = '001';
		//$importe = '12345.67';
		//$metodoPago = 'MP';
		//$estadoCheque = '50-50';
		//$nroComprobante = '000000000000000001';
		//$estadoCobranza = 'Cobranza en proceso';
		//$canal = '00001';
		//$denominacionDepositante = 'Depositante Prueba';
		//$refDepositante = '1234567890123456';
		//$cuitDepositante = '20304050607';
		//$primerVencimiento = '2025-03-10';
		//$importePrimerVencimiento = '1000.00';
		//$segundoVencimiento = '2025-03-20';
		//$importeSegundoVencimiento = '500.00';
		//$motivoRechazo = 'Sin Rechazo';
		//$nroCheque = '00000000000123';
		//$fechaCheque = '2025-03-10';
		//$importeCheque = '1500.00';
		//$importeAplicado = '1200.00';
		//$fechaCobranza = '2025-03-11';
		//$nroCobranza = '0000000000000001';
	
		// Formatear cada campo a su longitud correspondiente
		$linea = '   ';
		$linea .= str_pad($datos['sucursal'], 3, '0', STR_PAD_LEFT);
		$linea = str_pad($linea, strlen($linea) + 13, ' ', STR_PAD_RIGHT);
		$linea .= str_pad(str_replace('.', '', $datos['importe']), 15, ' ', STR_PAD_RIGHT);
		$linea .= str_pad($datos['metodoPago'], 2, ' ', STR_PAD_RIGHT);
		$linea = str_pad($linea, strlen($linea) + 23, ' ', STR_PAD_RIGHT);
		$linea .= str_pad($datos['estadoCheque'], 17, ' ', STR_PAD_RIGHT);
		$linea = str_pad($linea, strlen($linea) + 8, ' ', STR_PAD_RIGHT);
		$linea .= str_pad($datos['nroComprobante'], 18, '0', STR_PAD_LEFT);
		$linea .= str_pad($datos['estadoCobranza'], 21, ' ', STR_PAD_RIGHT);
		$linea = str_pad($linea, strlen($linea) + 4, ' ', STR_PAD_RIGHT);
		$linea .= str_pad($datos['canal'], 5, '0', STR_PAD_LEFT);
		$linea = str_pad($linea, strlen($linea) + 7, ' ', STR_PAD_RIGHT);
		$linea .= str_pad($datos['denominacionDepositante'], 31, ' ', STR_PAD_RIGHT);
		$linea .= str_pad($datos['refDepositante'], 16, '0', STR_PAD_LEFT);
		$linea = str_pad($linea, strlen($linea) + 2, ' ', STR_PAD_RIGHT);
		$linea .= str_pad($datos['cuitDepositante'], 11, '0', STR_PAD_LEFT);
		$linea = str_pad($linea, strlen($linea) + 8, ' ', STR_PAD_RIGHT);
		$linea .= str_pad($datos['primerVencimiento'], 10, ' ', STR_PAD_RIGHT);
		$linea = str_pad($linea, strlen($linea) + 2, ' ', STR_PAD_RIGHT);
		$linea .= str_pad($datos['importePrimerVencimiento'], 19, ' ', STR_PAD_RIGHT);
		$linea .= str_pad($datos['segundoVencimiento'], 10, ' ', STR_PAD_RIGHT);
		$linea = str_pad($linea, strlen($linea) + 2, ' ', STR_PAD_RIGHT);
		$linea .= str_pad($datos['importeSegundoVencimiento'], 19, ' ', STR_PAD_RIGHT);
		$linea .= str_pad($datos['motivoRechazo'], 17, ' ', STR_PAD_RIGHT);
		$linea = str_pad($linea, strlen($linea) + 7, ' ', STR_PAD_RIGHT);
		$linea .= str_pad($datos['nroCheque'], 14, '0', STR_PAD_LEFT);
		$linea = str_pad($linea, strlen($linea) + 5, ' ', STR_PAD_RIGHT);
		$linea .= str_pad($datos['fechaCheque'], 10, ' ', STR_PAD_RIGHT);
		$linea = str_pad($linea, strlen($linea) + 2, ' ', STR_PAD_RIGHT);
		$linea .= str_pad(str_replace('.', '', $datos['importeCheque']), 19, ' ', STR_PAD_RIGHT);
		$linea .= str_pad(str_replace('.', '', $datos['importeAplicado']), 19, ' ', STR_PAD_RIGHT);
		$linea .= str_pad($datos['fechaCobranza'], 10, ' ', STR_PAD_RIGHT);
		$linea = str_pad($linea, strlen($linea) + 1, ' ', STR_PAD_RIGHT);
		$linea .= str_pad($datos['nroCobranza'], 16, '0', STR_PAD_LEFT);
	
		return $linea;
	}
	
	// Genera el registro del archivo de liquidaciones diarias que se envía a Moura
	function generarRegistrosTxtLiquidacionMoura($datos) {
		// Por cada registro generado en generarRegistroTxtMoura se generan 3 registros en este
		// Fecha de acreditación
		// SAP interno
		// Interno
		// Fecha de acreditación
		// Periodo 
		// Moneda	
		// Grp Ledger 		(Vacío)	
		// Nro de operación	
		// Nombre de la tienda	
		// Débito/Crédito	
		// Cuenta contable	
		// Cod ZRE 			(Vacío)	
		// Valor a acreditar	
		// Forma de Pago 	(Vacío)	
		// Bloqueo de Pago 	(Vacío)	
		// Condicion de pago (Vacío)	
		// Fecha de acreditación	
		// Atribucion 		(Vacío)	
		// Nro de operación	
		// Centro de Cuosto	(Vacio)
		// Ordem 			(Vacio)
		// Elemento PEP		(Vacio)
		// Diagrama de Rede	(Vacio)
		// Item do Diagrama	(Vacio)
		// Centro de lucro	
		// División	
		// Local Negocios 	(Vacío)	
		// Tabla impuestos SAP
		
		// Formatear cada campo a longitud 32 caracteres
		$linea1 = '';
		$linea1 .= str_pad(str_replace('/', '.', $datos['fechaCobranza']), 32, ' ', STR_PAD_RIGHT);   // Fecha de acreditación
		$linea1 .= str_pad('AB', 32, ' ', STR_PAD_RIGHT);										   // SAP interno
		$linea1 .= str_pad('B003', 32, ' ', STR_PAD_RIGHT);										   // Interno
		$linea1 .= str_pad(str_replace('/', '.', $datos['fechaCobranza']), 32, ' ', STR_PAD_RIGHT);   // Fecha de acreditación
		
		$partesFecha = explode('/', $datos['fechaCobranza']);
		// Segundo elemento es el mes
		$linea1 .= str_pad($partesFecha[1], 32, ' ', STR_PAD_LEFT);											   // Periodo = Mes	
		
		$linea1 .= str_pad('ARS', 32, ' ', STR_PAD_RIGHT);										   // Moneda
		$linea1 .= str_pad('', 32, ' ', STR_PAD_RIGHT);											   // Grp Ledger	(Vacio)	
		$linea1 .= str_pad($datos['nroComprobante'], 32, ' ', STR_PAD_LEFT);					   // Nro Operacion
		$linea1 .= str_pad($datos['denominacionDepositante'], 32, ' ', STR_PAD_RIGHT);			   // Nombre de la tienda	
		
		$linea2 = $linea1;
		$linea3 = $linea1;
		
		$linea1 .= str_pad('50', 32, ' ', STR_PAD_LEFT);											// Débito
		$linea2 .= str_pad('40', 32, ' ', STR_PAD_LEFT);											// Crédito
		$linea3 .= str_pad('40', 32, ' ', STR_PAD_LEFT);											// Crédito

		// Cuenta contable	
		$linea1 .= str_pad(obtenerCuentaContable($datos['sucursal'], 'PD'), 32, ' ', STR_PAD_LEFT);				
		$linea2 .= str_pad(obtenerCuentaContable($datos['sucursal'], 'MO'), 32, ' ', STR_PAD_LEFT);						
		$linea3 .= str_pad(obtenerCuentaContable($datos['sucursal'], 'GB'), 32, ' ', STR_PAD_LEFT);			
		
		// Cod ZRE 			(Vacío)	
		$linea1 .= str_pad('', 32, ' ', STR_PAD_RIGHT);											
		$linea2 .= str_pad('', 32, ' ', STR_PAD_RIGHT);											
		$linea3 .= str_pad('', 32, ' ', STR_PAD_RIGHT);											
		
		// Valor a acreditar	
		$linea1 .= str_pad(str_replace(['$', ' ', '.'], '', $datos['importe']), 32, ' ', STR_PAD_LEFT);							
		$linea2 .= str_pad(str_replace(['$', ' ', '.'], '', $datos['importeAcreditadoMoura']), 32, ' ', STR_PAD_LEFT);							
		$linea3 .= str_pad(str_replace(['$', ' ', '.'], '', $datos['subsidioMoura']), 32, ' ', STR_PAD_LEFT);	

		// Forma de Pago 	(Vacío)	
		// Bloqueo de Pago 	(Vacío)	
		// Condicion de pago (Vacío)
		$linea1 .= str_pad('', 96, ' ', STR_PAD_RIGHT);											
		$linea2 .= str_pad('', 96, ' ', STR_PAD_RIGHT);											
		$linea3 .= str_pad('', 96, ' ', STR_PAD_RIGHT);		
						
		// Fecha de acreditación
		$linea1 .= str_pad(str_replace('/', '.', $datos['fechaCobranza']), 32, ' ', STR_PAD_RIGHT);   
		$linea2 .= str_pad(str_replace('/', '.', $datos['fechaCobranza']), 32, ' ', STR_PAD_RIGHT);   
		$linea3 .= str_pad(str_replace('/', '.', $datos['fechaCobranza']), 32, ' ', STR_PAD_RIGHT);   
		
		// Atribucion 		(Vacío)	
		$linea1 .= str_pad('', 32, ' ', STR_PAD_RIGHT);											
		$linea2 .= str_pad('', 32, ' ', STR_PAD_RIGHT);											
		$linea3 .= str_pad('', 32, ' ', STR_PAD_RIGHT);	
		
		// Nro Operacion  ==> Lleva nroSAP
		$linea1 .= str_pad($datos['nroSAP'], 32, ' ', STR_PAD_LEFT);	
	    $linea2 .= str_pad($datos['nroSAP'], 32, ' ', STR_PAD_LEFT);	
	    $linea3 .= str_pad($datos['nroSAP'], 32, ' ', STR_PAD_LEFT);		

		// Centro de Cuosto	(Vacio)
		// Ordem 			(Vacio)
		// Elemento PEP		(Vacio)
		// Diagrama de Rede	(Vacio)
		// Item do Diagrama	(Vacio)		
		$linea1 .= str_pad('', 160, ' ', STR_PAD_RIGHT);											
		$linea2 .= str_pad('', 160, ' ', STR_PAD_RIGHT);											
		$linea3 .= str_pad('', 160, ' ', STR_PAD_RIGHT);		
		
		// Centro de lucro
		$linea1 .= str_pad(obtenerCentroDeLucro($datos['sucursal']), 32, ' ', STR_PAD_LEFT);				
		$linea2 .= str_pad(obtenerCentroDeLucro($datos['sucursal']), 32, ' ', STR_PAD_LEFT);						
		$linea3 .= str_pad(obtenerCentroDeLucro($datos['sucursal']), 32, ' ', STR_PAD_LEFT);		
		
		// División	
		$linea1 .= str_pad(str_pad($datos['sucursal'], 4, '0', STR_PAD_LEFT), 32, ' ', STR_PAD_RIGHT);	
	    $linea2 .= str_pad(str_pad($datos['sucursal'], 4, '0', STR_PAD_LEFT), 32, ' ', STR_PAD_RIGHT);	
	    $linea3 .= str_pad(str_pad($datos['sucursal'], 4, '0', STR_PAD_LEFT), 32, ' ', STR_PAD_RIGHT);	
		
		// Local Negocios 	(Vacío)	
		$linea1 .= str_pad('', 32, ' ', STR_PAD_RIGHT);											
		$linea2 .= str_pad('', 32, ' ', STR_PAD_RIGHT);											
		$linea3 .= str_pad('', 32, ' ', STR_PAD_RIGHT);	
		
		// Tabla impuestos SAP
		$linea1 .= str_pad('', 32, ' ', STR_PAD_RIGHT);											
		$linea2 .= str_pad('', 32, ' ', STR_PAD_RIGHT);											
		$linea3 .= str_pad('C0', 32, ' ', STR_PAD_RIGHT);	
		
		return [$linea1, $linea2, $linea3];
		
	}
	
	

	function insertarTransaccion($dbConnection, $datosBIND, $datosMoura) {
		// Consulta SQL de inserción
		$query = "INSERT INTO transacciones (
			nrotransaccion,
			idpdv,
			sucursal,
			importe,
			metodopago,
			estadocheque,
			nrocomprobante,
			estadocobranza,
			canal,
			dendepositante,
			refdepositante,
			cuitdepositante,
			primervencimiento,
			importeprimervenc,
			segundovencimiento,
			importesegundovenc,
			motivorechazo,
			nrocheque,
			fecha,
			importecheque,
			importeaplicado,
			fechapago,
			fechapagobind,
			nrocobranza,
			tipotransaccion,
			procesada,
			completada,
			marca,
			idliquidacion
		) VALUES (
			:nrotransaccion,
			:idpdv,
			:sucursal,
			:importe,
			:metodopago,
			:estadocheque,
			:nrocomprobante,
			:estadocobranza,
			:canal,
			:dendepositante,
			:refdepositante,
			:cuitdepositante,
			:primervencimiento,
			:importeprimervenc,
			:segundovencimiento,
			:importesegundovenc,
			:motivorechazo,
			:nrocheque,
			:fecha,
			:importecheque,
			:importeaplicado,
			:fechapago,
			:fechapagobind,
			:nrocobranza,
			:tipotransaccion,
			:procesada,
			:completada,
			:marca,
			:idliquidacion
		)";
	
		// Preparamos la consulta
		$stmt = $dbConnection->prepare($query);
	
		// Asignamos los valores a los parámetros
		$stmt->bindValue(':nrotransaccion', intval($datosBIND['transaccion']));
		$stmt->bindValue(':idpdv', obtenerIdPdv($dbConnection, $datosBIND['numero_de_comercio']));
		$stmt->bindValue(':sucursal', $datosMoura['sucursal']);
		$stmt->bindValue(':importe', floatval(str_replace(['$', '.', ','], ['', '', '.'], $datosMoura['importe'])));
		$stmt->bindValue(':metodopago', $datosMoura['metodoPago']);
		$stmt->bindValue(':estadocheque', $datosMoura['estadoCheque']);
		$stmt->bindValue(':nrocomprobante', $datosMoura['nroComprobante']);
		$stmt->bindValue(':estadocobranza', $datosMoura['estadoCobranza']);
		$stmt->bindValue(':canal', intval($datosMoura['canal']));
		$stmt->bindValue(':dendepositante', $datosMoura['denominacionDepositante']);
		$stmt->bindValue(':refdepositante', $datosMoura['refDepositante']);
		$stmt->bindValue(':cuitdepositante', $datosMoura['cuitDepositante']);
		$stmt->bindValue(':primervencimiento', null);
		$stmt->bindValue(':importeprimervenc',floatval(str_replace(['$', '.', ','], ['', '', '.'], $datosMoura['importePrimerVencimiento'])));
		$stmt->bindValue(':segundovencimiento', null);
		$stmt->bindValue(':importesegundovenc', null);
		$stmt->bindValue(':motivorechazo', null);
		$stmt->bindValue(':nrocheque', $datosMoura['nroCheque']);
		$stmt->bindValue(':fecha', DateTime::createFromFormat('d/m/Y His', $datosMoura['fechaCheque'] . ' ' . $datosBIND['horario_de_la_tx'])->format('Y-m-d H:i:s'));
		$stmt->bindValue(':importecheque', floatval(str_replace(['$', '.', ','], ['', '', '.'], $datosMoura['importeChequeBruto'])));
		$stmt->bindValue(':importeaplicado', null);
		
		if( pdvLiquidaBIND($dbConnection,$datosBIND['numero_de_comercio']) == 0 ) {   //Liquida Pagos Digitales al día siguiente de la transaccion
			$stmt->bindValue(':fechapago', DateTime::createFromFormat('d/m/Y His', $datosMoura['fechaCobranza'] . ' ' . $datosBIND['horario_de_la_tx'])->format('Y-m-d H:i:s'));
			$stmt->bindValue(':fechapagobind', null);
		}
		else {  //Liquida BIND con la fecha indicada en el archivo
			$stmt->bindValue(':fechapago', null);
			$stmt->bindValue(':fechapagobind', DateTime::createFromFormat('Ymd His', $datosBIND['fecha_liquidacion'] . ' ' . $datosBIND['horario_de_la_tx'])->format('Y-m-d H:i:s'));
		}
		
		$stmt->bindValue(':nrocobranza', $datosMoura['nroComprobante']);
		$stmt->bindValue(':tipotransaccion', $datosMoura['estadoCobranza']);
		$stmt->bindValue(':procesada', true, PDO::PARAM_BOOL);
		$stmt->bindValue(':completada', false, PDO::PARAM_BOOL);
		$stmt->bindValue(':marca', '', PDO::PARAM_STR);
		$stmt->bindValue(':idliquidacion', 0, PDO::PARAM_INT);
	
		// Ejecutamos la consulta
		if ($stmt->execute()) {
			echo "Registro insertado correctamente.";
		} else {
			echo "Error al insertar registro: " . $stmt->errorInfo()[2];
		}
	}
	
	function insertarLiquidacionesArchivo($dbConnection, $lineas, $fecha) {
		
		// Consulta SQL de inserción
		$query = "INSERT INTO liquidacionesarchivo (
			linea0,
			linea1,
			linea2,
			fecha
		) VALUES (
			:linea0,
			:linea1,
			:linea2,
			:fecha
		)";
		
		// Prepara la consulta
		$stmt = $dbConnection->prepare($query);
		
		// Se asignan los valores a los parámetros
		$stmt->bindValue(':linea0', $lineas[0]);
		$stmt->bindValue(':linea1', $lineas[1]);
		$stmt->bindValue(':linea2', $lineas[2]);
		$stmt->bindValue(':fecha', $fecha);
		
		// Ejecuta la consulta
		if ($stmt->execute()) {
			echo "Registro Liquidaciones Archivo insertado correctamente.";
		} else {
			echo "Error al insertar registro: " . $stmt->errorInfo()[2];
		}
	}
	
	function insertarDetalleLiquidacion($dbConnection, $datosBIND) {
		
		
		// Consulta SQL de inserción
		$query = "INSERT INTO liquidacionesdetalle (
			nrotransaccion,
			comisionpd,
			ivacomisionpd,
			subsidiomoura,
			ivasubsidiomoura,
			comisionprontopago,
			ivacomisionprontopago,
			descuentocuotas,
			ivadescuentocuotas,
			costoacreditacion,
			ivacostoacreditacion,
			aranceltarjeta,
			ivaaranceltarjeta,
			credmoura,
			sirtac,
			otrosimpuestos,
			beneficiocredmoura
		) VALUES (
			:nrotransaccion,
			:comisionpd,
			:ivacomisionpd,
			:subsidiomoura,
			:ivasubsidiomoura,
			:comisionprontopago,
			:ivacomisionprontopago,
			:descuentocuotas,
			:ivadescuentocuotas,
			:costoacreditacion,
			:ivacostoacreditacion,
			:aranceltarjeta,
			:ivaaranceltarjeta,
			:credmoura,
			:sirtac,
			:otrosimpuestos,
			:beneficiocredmoura
		)";
	
		// Preparamos la consulta
		$stmt = $dbConnection->prepare($query);
		
		$cuotas = ltrim($datosBIND['cantidad_de_cuotas'],'0');
		//Corresponde a lo acreditado solo por lo que le corresponde a Moura por SPLIT
		$importeBruto = convertirImporteFormatoBINDANumerico($datosBIND['importe']);
		
		$importeBrutoOriginal = $importeBruto;
		
		//Por Pronto Pago la fecha de liquidación no es la que indica el banco sino la proxima fecha habil al día de pago
		//Si es Sabado o Domingo Pagos Digitales considera que es como si fueran Lunes por lo que normalmente el proximo dia habil es Martes
		if (esSabadoDomingoFeriadoAAMMDD($datosBIND['fecha_pago']) == 0 ){
			$fechaLiquidacion = obtenerProximoDiaHabilAAMMDD($datosBIND['fecha_pago']);		
		}
		else {
			$fechaLiquidacion = obtenerProximoDiaHabilAAMMDD($datosBIND['fecha_pago']);	
			$fechaLiquidacion = obtenerProximoDiaHabilAAMMDD($fechaLiquidacion);	
		}
		
		//Si es pago en cuotas se suma al bruto el Cft. Cliente.
		if($datosBIND['forma_pago'] == METODO_PAGO_BIND_CREDITO_CUOTAS){
			$importeBruto = recalcularImporteBruto($dbConnection, $importeBruto, $fechaLiquidacion, $cuotas);	
		}	
		
		$porcentajes = obtenerPorcentajesDeducciones($dbConnection, $fechaLiquidacion);
		
		// En la tabla de detalle de liquidacion el par de campos concepto/iva suman el total
		// NETO  =  TOTAL / (1 + IVA)
		$comisionPD = 0;
		if($datosBIND['forma_pago'] == METODO_PAGO_BIND_CREDITO || $datosBIND['forma_pago'] == METODO_PAGO_BIND_DEBIN){
			$comisionPD = $importeBruto * $porcentajes[ID_COMISION_PD_CREDITO] / 100  ;
		}
		elseif($datosBIND['forma_pago'] == METODO_PAGO_BIND_CREDITO_CUOTAS){
			
			if($cuotas == 3){
				$comisionPD = $importeBruto * $porcentajes[ID_COMISION_PD_CREDITO_3_CUOTAS] / 100  ;
			}
			elseif($cuotas == 6){
				$comisionPD = $importeBruto * $porcentajes[ID_COMISION_PD_CREDITO_6_CUOTAS] / 100  ;
			}
		}
		elseif($datosBIND['forma_pago'] == METODO_PAGO_BIND_DEBITO){
			$comisionPD = $importeBruto * $porcentajes[ID_COMISION_PD_DEBITO] / 100  ;
		}
		else{
			$comisionPD = $importeBruto * $porcentajes[ID_COMISION_PD_QR] / 100  ;
		}
		
		$subsidioMoura = $importeBruto * ($porcentajes[ID_SUBSIDIO_MOURA]) / 100  ;
		
		$comisionProntoPago = $importeBruto * $porcentajes[ID_COMISION_PRONTO_PAGO] / 100  ;
		
		if($datosBIND['forma_pago'] == METODO_PAGO_BIND_CREDITO_CUOTAS){
			
			if($cuotas == 3){
				$descuentoCuotas = $importeBrutoOriginal * ($porcentajes[ID_CFT_CLIENTE_3_CUOTAS]) / 100  ;
			                                 //Para descuento cuotas ese % de descuento ya incluye IVA y ese iva es de 10.5%
											 //El descuentoCuotas siempre es con el montoBrutoOriginal antes de haberle sumado el Cft.Cliente.
			}
			elseif($cuotas == 6){
				$descuentoCuotas = $importeBrutoOriginal * ($porcentajes[ID_CFT_CLIENTE_6_CUOTAS]) / 100  ;
			                                 //Para descuento cuotas ese % de descuento ya incluye IVA y ese iva es de 10.5%
											 //El descuentoCuotas siempre es con el montoBrutoOriginal antes de haberle sumado el Cft.Cliente.
			}
			
		}
		else{
			$descuentoCuotas = 0;
		}
		
		if($datosBIND['forma_pago'] == METODO_PAGO_BIND_CREDITO || $datosBIND['forma_pago'] == METODO_PAGO_BIND_DEBIN){
			$costoAcreditacion = $importeBruto * $porcentajes[ID_COSTO_ACREDITACION_CREDITO] / 100  ;
		}
		elseif($datosBIND['forma_pago'] == METODO_PAGO_BIND_CREDITO_CUOTAS ){
						
			if($cuotas == 3){
				$costoAcreditacion = ($importeBruto * $porcentajes[ID_COSTO_ACREDITACION_CREDITO_3_CUOTAS] - $importeBrutoOriginal * $porcentajes[ID_COSTO_ACREDITACION_CREDITO_3_CUOTAS_RESTA]) / 100  ;
			}
			elseif($cuotas == 6){
				$costoAcreditacion = ($importeBruto * $porcentajes[ID_COSTO_ACREDITACION_CREDITO_6_CUOTAS] - $importeBrutoOriginal * $porcentajes[ID_COSTO_ACREDITACION_CREDITO_6_CUOTAS_RESTA]) / 100  ;
			}
		}
		elseif($datosBIND['forma_pago'] == METODO_PAGO_BIND_DEBITO){
			$costoAcreditacion = $importeBruto * $porcentajes[ID_COSTO_ACREDITACION_DEBITO] / 100  ;
		}
		else{
			$costoAcreditacion = $importeBruto * $porcentajes[ID_COSTO_ACREDITACION_QR] / 100  ;
		}
		
		if($datosBIND['forma_pago'] == METODO_PAGO_BIND_CREDITO_CUOTAS || $datosBIND['forma_pago'] == METODO_PAGO_BIND_CREDITO || $datosBIND['forma_pago'] == METODO_PAGO_BIND_DEBIN){
			$arancelTarjeta = $importeBruto * ($porcentajes[ID_ARANCEL_TARJETA_CREDITO_VISA]) / 100 ;
		}
		elseif($datosBIND['forma_pago'] == METODO_PAGO_BIND_DEBITO) {
			$arancelTarjeta = $importeBruto * ($porcentajes[ID_ARANCEL_TARJETA_DEBITO]) / 100  ;
		}
		elseif($datosBIND['forma_pago'] == METODO_PAGO_BIND_QR) {
			$arancelTarjeta = $importeBruto * $porcentajes[ID_ARANCEL_QR] / 100  ;
		}
		else{
			$aranceltarjeta = 0;
		}
		
		if($datosBIND['forma_pago'] == METODO_PAGO_BIND_CREDITO_CUOTAS){
			       
			if($cuotas == 3){
				$beneficioCredMoura = ($importeBrutoOriginal *  $porcentajes[ID_CFT_CLIENTE_3_CUOTAS] - $importeBruto * $porcentajes[ID_COMISION_PD_CREDITO] - $importeBrutoOriginal *  $porcentajes[ID_COSTO_FINANCIERO_MIPYME_3_CUOTAS]) / 100;
			}
			elseif($cuotas == 6){
				$beneficioCredMoura = ($importeBrutoOriginal *  $porcentajes[ID_CFT_CLIENTE_6_CUOTAS] - $importeBruto * $porcentajes[ID_COMISION_PD_CREDITO] - $importeBrutoOriginal *  $porcentajes[ID_COSTO_FINANCIERO_MIPYME_6_CUOTAS]) / 100;
			}				   
		}
		else {
			$beneficioCredMoura = 0;
		}
		
		// Se obtiene el porcentaje de split que corresponde al PDV
		$porcentajePDV = obtenerPorcentajePDV($dbConnection, $datosBIND['numero_de_comercio'], $fechaLiquidacion );		
		
		//A partir del porcentaje de ahorro del PDV se obtiene cualo es el porcentaje de ahorro que le corresponde 
		$porcentajeAhorroSplit = obtenerPorcentajePorTipoOperacion($dbConnection, $fechaLiquidacion, $porcentajePDV);
		
		//Se utiliza el porcentaje de ahorro con el ID correspondiente
		$credMoura = $importeBruto * $porcentajeAhorroSplit / 100;
		
		
		// Asignamos los valores a los parámetros
		$stmt->bindValue(':nrotransaccion', intval($datosBIND['transaccion']));
		$stmt->bindValue(':comisionpd', $comisionPD);
		$stmt->bindValue(':ivacomisionpd', $comisionPD * IVA);
		$stmt->bindValue(':subsidiomoura', $subsidioMoura);
		$stmt->bindValue(':ivasubsidiomoura', $subsidioMoura * IVA);
		$stmt->bindValue(':comisionprontopago', $comisionProntoPago);
		$stmt->bindValue(':ivacomisionprontopago', $comisionProntoPago * IVA);
		$stmt->bindValue(':descuentocuotas', $descuentoCuotas);
		$stmt->bindValue(':ivadescuentocuotas', $descuentoCuotas * 0 );	// 25/06/2025 $descuentoCuotas lleva IVA = 0;
		$stmt->bindValue(':costoacreditacion', $costoAcreditacion);
		$stmt->bindValue(':ivacostoacreditacion', $costoAcreditacion * IVA);
		$stmt->bindValue(':aranceltarjeta', $arancelTarjeta);
		$stmt->bindValue(':ivaaranceltarjeta', $arancelTarjeta * IVA);
		$stmt->bindValue(':credmoura', $credMoura);
		$stmt->bindValue(':sirtac', $importeBruto * PORCENTAJE_SIRTAC / 100); 
		$stmt->bindValue(':otrosimpuestos', $importeBruto * $porcentajes[ID_OTROS_IMPUESTOS] / 100);		
		$stmt->bindValue(':beneficiocredmoura', $beneficioCredMoura);
	
		// Ejecutamos la consulta
		if ($stmt->execute()) {
			echo "Registro Detalle de Liquidacion insertado correctamente.\n";
		} else {
			echo "Error al insertar registro: " . $stmt->errorInfo()[2];
		}
	}
	
	function insertarDevolucion($dbConnection, $datosBIND) {
		
		// Consulta SQL de inserción
		$query = "INSERT INTO devoluciones (
					nrodevolucion,
                    nrotransaccion, 
                    idpdv, 
                    fechadevolucion, 
                    importeadevolver, 
                    importedevuelto, 
                    completada
                ) VALUES (
					:nrodevolucion,
                    :nrotransaccion, 
                    :idpdv, 
                    :fechadevolucion, 
                    :importeadevolver, 
                    :importedevuelto, 
                    :completada
                )";
	
		// Preparamos la consulta
		$stmt = $dbConnection->prepare($query);
		
			
		// Se asignan los valores a los parámetros
		$stmt->bindValue(':nrodevolucion', intval($datosBIND['nro_transaccion']));
		$stmt->bindValue(':nrotransaccion', intval($datosBIND['transaccion_anulada']));
		$stmt->bindValue(':idpdv', obtenerIdPdv($dbConnection, $datosBIND['numero_de_comercio']));
		$stmt->bindValue(':fechadevolucion', DateTime::createFromFormat('Ymd H:i:s', $datosBIND['fecha_negocio'] . ' ' . $datosBIND['hora_transaccion_anulacion'])->format('Y-m-d H:i:s'));
		$stmt->bindValue(':importeadevolver', convertirImporteFormatoBINDANumerico($datosBIND['importe']));
		$stmt->bindValue(':importedevuelto', 0);
		$stmt->bindValue(':completada', 0);
		
		
		// Ejecutamos la consulta
		if ($stmt->execute()) {
			echo "Registro insertado correctamente.";
		} else {
			echo "Error al insertar registro: " . $stmt->errorInfo()[2];
		}
	}
	
	function descargarArchivo($filter){
		// Se obtiene el Token.
		$authorization = new AuthorizationController(ACCESS_TOKEN_URL_BIND, CLIENT_ID_BIND, CLIENT_SECRET_BIND, SCOPE_BIND);
		$accessToken = json_decode($authorization->getAccessToken());  //Debe retornar un string con el json que tiene dentro el access_token. 
		                                                               //Entonces se lo convierte a objeto
				
		$webApiGateway = new webApiGateway("","","","");
		$encrypted = $webApiGateway->getFileEncrypted($accessToken,$filter);
				
		$webApiGateway->downloadFile($accessToken, $filter, urlencode($encrypted));		
		
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
		
		$fechaExtensionBINDDDMMAA = obtenerProximoDiaHabilDDMMAA($fechaurl);
		
		// Separar la fecha de Liquidacion en día, mes y año para usar en la extension del nombre de archivo 
		$dExtensionBIND = substr($fechaExtensionBINDDDMMAA, 0, 2);           // Día (siempre dos caracteres)
		$mExtensionBIND = ltrim(substr($fechaExtensionBINDDDMMAA, 2, 2), '0'); // Mes (con 0 a la izquierda removido si existe)
		$aExtensionBIND = substr($fechaExtensionBINDDDMMAA, 4, 2);           // Año (siempre dos caracteres)			
				
		/////////////////////////////////////////////////////////////////////////////////////////////////
		// VENTAS
		// Ruta al archivo de texto del BIND
		if (esSabadoDomingoFeriadoDDMMAA($fechaurl) == 0 ){
			$nombreArchivo = 'A065BOTON' . $fechaurl . '.' . $m . $d;
			$fechaLiquidacionDDMMAA = obtenerProximoDiaHabilDDMMAA($fechaurl);
		}
		else {  //Si se procesa transacciones de Sabado Domingo o Feriado BIND coloca la extensión de la fecha del día habil en que se procesa
				//Pagos Digitales considera que se liquidará como si fuera un Lunes (normalmente Martes)				
			$nombreArchivo = 'A065BOTON' . $fechaurl . '.' . $mExtensionBIND . $dExtensionBIND;
			$fechaLiquidacionDDMMAA = obtenerProximoDiaHabilDDMMAA($fechaExtensionBINDDDMMAA);
		}
		
		// Separar la fecha de Liquidacion en día, mes y año para usar en la extension del nombre de archivo 
		$dLiquidacion = substr($fechaLiquidacionDDMMAA, 0, 2);           // Día (siempre dos caracteres)
		$mLiquidacion = ltrim(substr($fechaLiquidacionDDMMAA, 2, 2), '0'); // Mes (con 0 a la izquierda removido si existe)
		$aLiquidacion = substr($fechaLiquidacionDDMMAA, 4, 2);           // Año (siempre dos caracteres)
		
		//descargarArchivo($nombreArchivo);
		
		
		// Abrir el archivo en modo de solo lectura
		$archivoBIND = fopen(DIR_RAIZ . $nombreArchivo, 'r');
	
		// Verificar si se pudo abrir correctamente
		if ($archivoBIND === false) {
			die("No se pudo abrir el archivo: $nombreArchivo");
		}
	
		// Leer la primera línea con el header y descartarla
		fgets($archivoBIND);
	
		// Guardar en un archivo .txt por cada Division
		$archivoMoura = fopen(DIR_RAIZ . 'RecibosCredmoura' . DIVISION_BSAS . $fechaurl . '.txt', 'w');
		fwrite($archivoMoura, CABECERA_ARCHIVO_MOURA . PHP_EOL);	
		
		
	
		$dbConnection = (new DatabaseConnector(DB_SERVER, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD))->getConnection();		
		
		// Leer el archivo línea por línea
		while (($registro = fgets($archivoBIND)) !== false ) {
			
			if (strpos($registro, 'TRAILER') !== 0) {				
				// $registro contiene cada línea leída
				$datos = parsearRegistroBIND($registro);  //$datos es el array con datos parseados según la definición BIND
				
				////////////////////////////////////////////////////////////////////////////
				//25/06/2025 Temporal hasta que BIND informe en el archivo la cantidad de cuotas de la transacciones
				$cuotas = obtenerCantidadCuotasDesdeExcel();
				$nroTransaccion = ltrim($datos['transaccion'],'0'); //Se quitan los ceros a la izquierda que vienen en el archivo BIND
				if (isset($cuotas[$nroTransaccion])) {
					$datos['cantidad_de_cuotas'] = $cuotas[$nroTransaccion];
				} else {
					$datos['cantidad_de_cuotas'] = 1; // Si no se colocó en el Excel se considera 1 cuota
				}
				////////////////////////////////////////////////////////////////////////////
				if( comercioExistente($dbConnection,$datos['numero_de_comercio']) == 1 ){
					$datosMoura = formatearDatosBINDaMoura($dbConnection,$datos);
					$linea= generarRegistroTxtMoura($datosMoura); 	
					fwrite($archivoMoura, $linea . PHP_EOL);
					insertarTransaccion($dbConnection, $datos, $datosMoura);
					insertarDetalleLiquidacion($dbConnection, $datos);	
	
					$lineasLiquidacion= generarRegistrosTxtLiquidacionMoura($datosMoura); 
					
					if( pdvLiquidaBIND($dbConnection,$datosBIND['numero_de_comercio']) == 0 ) {   //Liquida Pagos Digitales al día siguiente de la transaccion
						$fl = DateTime::createFromFormat('d/m/Y His', $datosMoura['fechaCobranza'] . ' 000000')->format('Y-m-d H:i:s');
					}
					else  //Liquida con fecha BIND
					{
						$fl = DateTime::createFromFormat('Ymd His', $datos['fecha_liquidacion'] . ' 000000')->format('Y-m-d H:i:s');
					}
					
					insertarLiquidacionesArchivo($dbConnection, $lineasLiquidacion,$fl);
				}
			}
		}		
		
		echo "Registros generados correctamente en el archivo 'Moura.txt'.\n";
		
		// Cerrar el archivo
		fclose($archivoBIND);
		fclose($archivoMoura);
		
		
		/////////////////////////////////////////////////////////////////////////////////////////////////
		//DEVOLUCIONES
		// Ruta al archivo de texto de DEVOLUCIONES del BIND
		if (esSabadoDomingoFeriadoDDMMAA($fechaurl) == 0 ){
			$nombreArchivo = 'A065DEVBOTON' . $a . (strlen($m) == 2 ? $m : '0' . $m) . $d . '.' . $m . $d;
		}
		else {
			$nombreArchivo = 'A065DEVBOTON' . $a . (strlen($m) == 2 ? $m : '0' . $m) . $d . '.' . $mExtensionBIND . $dExtensionBIND;
		}
		
		//descargarArchivo($nombreArchivo);
		
		// Abrir el archivo en modo de solo lectura
		$archivoBIND = fopen(DIR_RAIZ . $nombreArchivo, 'r');
	
		// Verificar si se pudo abrir correctamente
		if ($archivoBIND === false) {
			die("No se pudo abrir el archivo: $nombreArchivo");
		}
	
		// Leer la primera línea con el header y descartarla
		fgets($archivoBIND);
		
		// Leer el archivo línea por línea
		while (($registro = fgets($archivoBIND)) !== false ) {			
			if (strpos($registro, 'TRAILER') !== 0) {	
				// $registro contiene cada línea leída
				$datos = parsearDevolucionBIND($registro);  //$datos es el array con datos parseados según la definición BIND para DEVOLUCIONES
				if( comercioExistente($dbConnection,$datos['numero_de_comercio']) == 1 ){
					insertarDevolucion($dbConnection, $datos);
				}
			}
		}
		
		echo "Registros de devoluciones insertados correctamente en BD\n";
		
		// Cerrar el archivo
		fclose($archivoBIND);
		
	} catch (Exception $e) {
		echo "Error: " . $e->getMessage();
	}
	// ------------------------------

	// Registrar finalización del proceso
	echo "Fin del proceso de ejecución.";

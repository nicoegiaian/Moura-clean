<?php

// Carga el autoloader de Composer para incluir las librerías necesarias.
require 'vendor/autoload.php';

// "Alias" para las clases que vamos a usar.
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * =================================================================
 * FASE 1: LÓGICA DE FECHAS Y FERIADOS
 * =================================================================
 */

/**
 * Obtiene los feriados de Argentina para un año específico desde la API de Nager.Date.
 * @param string $year
 * @return array
 */
function obtenerFeriados(string $year): array
{
    // Nueva URL de la API alternativa (Nager.Date)
    $url = "https://date.nager.at/api/v3/PublicHolidays/{$year}/AR";
    
    // Usamos un contexto para manejar posibles errores de la API y definir un timeout
    $context = stream_context_create([
        'http' => [
            'timeout' => 5, // 5 segundos de espera máxima
            'ignore_errors' => true, // Para poder leer el cuerpo aunque haya un error 4xx/5xx
            'header' => "User-Agent: PHP-Script\r\n" // Algunas APIs requieren un User-Agent
        ]
    ]);

    $response = @file_get_contents($url, false, $context);
    
    // Verificamos si la respuesta HTTP indica un error
    if ($response === false || strpos($http_response_header[0], '200 OK') === false) {
        echo "Advertencia: No se pudo conectar a la API de feriados (Nager.Date). Se procesará sin considerar feriados.\n";
        return [];
    }

    $feriadosData = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo "Advertencia: La respuesta de la API de feriados no es un JSON válido. Se procesará sin feriados.\n";
        return [];
    }

    $feriados = [];
    foreach ($feriadosData as $feriado) {
        // La fecha viene en el campo 'date' en formato 'Y-m-d'
        if (isset($feriado['date'])) {
            $feriados[] = $feriado['date'];
        }
    }
    return $feriados;
}

/**
 * Resta una cantidad de días hábiles a una fecha, considerando feriados.
 * @param DateTime $fechaInicial
 * @param int $diasARestar
 * @param array $feriados
 * @return DateTime
 */
function restarDiasHabiles(DateTime $fechaInicial, int $diasARestar, array $feriados): DateTime
{
    $fecha = clone $fechaInicial;
    $diasRestados = 0;
    while ($diasRestados < $diasARestar) {
        $fecha->modify('-1 day');
        $diaDeLaSemana = $fecha->format('N'); // 1 (Lunes) a 7 (Domingo)
        $esFeriado = in_array($fecha->format('Y-m-d'), $feriados);

        // Si no es Sábado (6), ni Domingo (7), ni feriado, contamos como día hábil restado.
        if ($diaDeLaSemana < 6 && !$esFeriado) {
            $diasRestados++;
        }
    }
    return $fecha;
}

/**
 * =================================================================
 * FASE 3: MÓDULO DE TRANSFORMACIONES
 * =================================================================
 * Recibe una fila de datos del Excel y aplica las reglas de negocio.
 * @param array $datosOrigen
 * @return array
 */
function transformarFila(array $datosOrigen): array
{
    $filaTransformada = [];

    // --- IMPLEMENTACIÓN DE REGLAS DE TRANSFORMACIÓN (1-286) ---

    $filaTransformada['TIPO_REGISTRO'] = "DATOS   "; // 1-8
    $filaTransformada['CODIGO_ENTIDAD'] = "A065"; // 9-12
    $filaTransformada['R'] = "R"; // 13-13
    $filaTransformada['CODIGO_TERMINAL'] = "02222"; // 14-18
    $filaTransformada['PARSUBCOD'] = str_pad('', 10, '0'); // 19-28
    $filaTransformada['CODIGO_SUCURSAL'] = "2222"; // 29-32
    $filaTransformada['RELLENO_33_36'] = str_pad('', 4, '0');; // 33-36

    $transaccion = $datosOrigen['Número de operación'] ?? '';
    $filaTransformada['TRANSACCION'] = str_pad($transaccion, 12, '0', STR_PAD_LEFT); // 37-48

    $filaTransformada['CODIGO_OPERACION'] = "A3"; // 49-50
    $filaTransformada['RUBRO_TX'] = "CC"; // 51-52

    $nroComercio = $datosOrigen['Info adicional comercio'] ?? '';
    $filaTransformada['N_COMERCIO'] = !empty(trim($nroComercio)) ? str_pad($nroComercio, 6, ' ', STR_PAD_RIGHT) : "000000"; // 53-58

    $filaTransformada['CODIGO_SERVICIO'] = str_pad('', 19, ' '); // 59-77

    $montoBruto = (float) str_replace(',', '.', $datosOrigen['Monto bruto'] ?? '0');
    $filaTransformada['__IMPORTE_RAW__'] = $montoBruto; // <-- NUEVO: Campo interno para sumar en el TRAILER
    $montoSinDecimales = $montoBruto * 100;
    $filaTransformada['IMPORTE'] = str_pad($montoSinDecimales, 11, '0', STR_PAD_LEFT); // 78-88

    $filaTransformada['RELLENO_89_99'] = "00000000000"; // 89-99
    $filaTransformada['RELLENO_100_110'] = "00000000000"; // 100-110

    $monedaOrigen = $datosOrigen['Moneda'] ?? '';
    $monedaDestino = '2'; // Valor por defecto
    if ($monedaOrigen === 'ARS') {
        $monedaDestino = '0';
    } elseif ($monedaOrigen === 'USD') {
        $monedaDestino = '1';
    }
    elseif ($monedaOrigen === 'MX') {
        $monedaDestino = '2';
    }
    $filaTransformada['MONEDA'] = $monedaDestino; // 111-111

    $filaTransformada['RELLENO_112_115'] = str_pad('', 4, '0'); // 112-115
    $filaTransformada['TIPO DE USUARIO'] = str_pad('', 20, '0'); // 116-135
    $filaTransformada['RELLENO_136_138'] = str_pad('', 3, '0'); // 136-138
    $filaTransformada['RELLENO_139_144'] = str_pad('', 6, '0'); // 139-144
    
    $fechaTrx = $datosOrigen['Fecha trx'] ?? '';
    $fechaObjeto = null;
    if (!empty($fechaTrx)) {
        try {
            // Se asume que la fecha viene en formato ATOM/ISO8601, si no, hay que ajustar el createFromFormat
            $fechaObjeto = new DateTime($fechaTrx);
                } catch (Exception $e) {
            $fechaObjeto = null; // No se pudo parsear la fecha
        }
    }
    $filaTransformada['HORARIO_DE_LA_TX'] = $fechaObjeto ? $fechaObjeto->format('His') : '000000'; // 145-150 
    
    $filaTransformada['PROCESADOR DE PAGO'] = "010"; // 151-153
    $filaTransformada['RELLENO_154_156'] = str_pad('', 3, '0'); // 154-156
    $filaTransformada['PROCESADOR DE DEBITO INTERNO'] = str_pad('', 4, '0'); // 157-160

    $fechaPagoMerchantStr = $datosOrigen['Fecha de pago al merchant'] ?? '';
    $fechaPagoMerchantObj = null;
    if (!empty($fechaPagoMerchantStr)) {
        try {
            $fechaPagoMerchantObj = new DateTime($fechaPagoMerchantStr);
        } catch (Exception $e) {
            $fechaPagoMerchantObj = null;
        }
    }
    $filaTransformada['FECHA_LIQUIDACION'] = $fechaPagoMerchantObj ? $fechaPagoMerchantObj->format('Ymd') : '00000000'; // 161-168

    $filaTransformada['RELLENO_169_176'] = str_pad('', 8, '0'); // 169-176
    $filaTransformada['RELLENO_177_179'] = str_pad('', 3, '0'); // 177-179
    $filaTransformada['CODIGO DE BARRA'] = str_pad('', 60, '0'); // 180-239

    
    $filaTransformada['FECHA PAGO'] = $fechaObjeto ? $fechaObjeto->format('ymd') : '000000'; // 240-245
    $filaTransformada['TIPO DE TRANSACCION'] = str_pad('', 1, '0'); // 246-246
    $filaTransformada['RELLENO_247_253'] = str_pad('', 7, '0'); // 247-253
    $filaTransformada['ID_CLIENTE'] = str_pad('', 9, '0'); // 254-262

    $medioDePago = $datosOrigen['Medio de pago'] ?? '';
    $cuotas = (int)($datosOrigen['Cuotas'] ?? 0);
    $formaPago = '';

    if ($medioDePago === 'CREDIT') {
        $formaPago = ($cuotas === 1) ? '80' : '30';
    } elseif ($medioDePago === 'DEBIT') {
        $formaPago = '90';
    } elseif ($medioDePago === 'QR') {
        $formaPago = '20';
    } elseif ($medioDePago === 'PREPAID') {
        $formaPago = '70';
    }
    $filaTransformada['FORMA_PAGO'] = $formaPago; // 263-264
    
    $filaTransformada['RELLENO_265_268'] = str_pad('', 4, '0'); // 265-268

    $cuotas = $datosOrigen['Cuotas'] ?? '0';
    $filaTransformada['CANTIDAD_CUOTAS'] = str_pad($cuotas, 3, '0', STR_PAD_LEFT); // 269-271

    $filaTransformada['DNI_CLIENTE'] = str_pad('', 15, '0'); // 272-286

    // --- NUEVAS REGLAS CORREGIDAS (287-620) ---
    
    $filaTransformada['RELLENO_287_294'] = str_pad('', 8, '0'); // 287-294
    $filaTransformada['ID_TX_PROCESADOR'] = str_pad('', 30, '0'); // 295-324
    $filaTransformada['BARRA CUPON DE PAGO'] = str_pad('', 150, '0'); // 325-474
    $filaTransformada['ID GATEWAY'] = str_pad('', 30, '0'); // 475-504
    $filaTransformada['RELLENO 505-534'] = str_pad('', 30, ' '); // 505-534
    $filaTransformada['RELLENO_535-594'] = str_pad('', 60, ' '); // 535-594
    $filaTransformada['ID CAMPAÑA'] = str_pad('', 8, '0'); // 595-602
    $filaTransformada['RELLENO 603-605'] = str_pad('', 3, ' '); // 603-605
    $filaTransformada['BIN DE LA TARJETA'] = str_pad('', 6, '0');// 606-611
    $filaTransformada['ID RUBRO COMERCIO'] = str_pad('', 6, '0');// 612-617
    $filaTransformada['ID PROV CLIENTE'] = str_pad('', 3, '0');// 618-620
    
    return $filaTransformada;
}

/**
 * Concatena los campos transformados para generar la línea de ancho fijo.
 * @param array $filaProcesada
 * @return string
 */
function ensamblarLinea(array $filaProcesada): string
{
    // El orden de las claves en este array DEBE ser el orden del archivo de salida.
    $ordenDeCampos = [
        'TIPO_REGISTRO', 'CODIGO_ENTIDAD', 'R', 'CODIGO_TERMINAL', 'PARSUBCOD', 
        'CODIGO_SUCURSAL', 'RELLENO_33_36', 'TRANSACCION', 'CODIGO_OPERACION', 'RUBRO_TX', 
        'N_COMERCIO', 'CODIGO_SERVICIO', 'IMPORTE', 'RELLENO_89_99', 'RELLENO_100_110', 
        'MONEDA', 'RELLENO_112_115', 'TIPO DE USUARIO','RELLENO_136_138', 'RELLENO_139_144', 'HORARIO_DE_LA_TX', 
        'PROCESADOR DE PAGO', 'RELLENO_154_156', 'PROCESADOR DE DEBITO INTERNO', 'FECHA_LIQUIDACION', 
        'RELLENO_169_176', 'RELLENO_177_179', 'CODIGO DE BARRA', 'FECHA PAGO', 'TIPO DE TRANSACCION',
        'RELLENO_247_253', 'ID_CLIENTE', 'FORMA_PAGO','RELLENO_265_268','CANTIDAD_CUOTAS','DNI_CLIENTE',
        'RELLENO_287_294', 'ID_TX_PROCESADOR', 'BARRA CUPON DE PAGO',
        'ID GATEWAY', 'RELLENO 505-534', 'RELLENO_535-594', 'ID CAMPAÑA', 'RELLENO 603-605',
        'BIN DE LA TARJETA', 'ID RUBRO COMERCIO', 'ID PROV CLIENTE'
    ];
    
    $lineaFinal = '';
    foreach ($ordenDeCampos as $campo) {
        $lineaFinal .= $filaProcesada[$campo] ?? '';
    }

    return $lineaFinal;
}

/**
 * =================================================================
 * FASE 4: BUCLE PRINCIPAL DE EJECUCIÓN
 * =================================================================
 */

echo "--- Iniciando el script de procesamiento por lotes ---\n";

// --- 1. VALIDACIÓN DEL PARÁMETRO DE ENTRADA ---
if (!isset($argv[1]) || !preg_match('/^\d{8}$/', $argv[1])) {
    die("Error: Debe proporcionar una fecha de proceso como parámetro en formato AAAAMMDD.\nEjemplo: php procesador.php 20251013\n");
}
$fechaProcesoStr = $argv[1];
$fechaProceso = DateTime::createFromFormat('Ymd', $fechaProcesoStr);
echo "Fecha de Proceso recibida: " . $fechaProceso->format('d-m-Y') . "\n";

// --- 2. CÁLCULO DE FECHAS DE NEGOCIO ---
$feriados = obtenerFeriados($fechaProceso->format('Y'));
$fechaTransaccion = restarDiasHabiles($fechaProceso, 2, $feriados);
echo "Fecha de Transacción calculada (-2 días hábiles): " . $fechaTransaccion->format('d-m-Y') . "\n";

$fechasDeNegocio = [];
// Si la fecha de transacción es Lunes (1), agregamos días anteriores.
if ($fechaTransaccion->format('N') == 1) {
    echo "La Fecha de Transacción es Lunes. Se generarán lotes para los días previos.\n";
    $fechaEvaluar = clone $fechaTransaccion;

    // Agregamos Lunes, Domingo y Sábado
    $fechasDeNegocio[] = clone $fechaTransaccion;
    $fechasDeNegocio[] = (clone $fechaTransaccion)->modify('-1 day');
    $fechasDeNegocio[] = (clone $fechaTransaccion)->modify('-2 days');

    // Verificamos si el Viernes previo fue feriado
    $viernesPrevio = (clone $fechaTransaccion)->modify('-3 days');
    if (in_array($viernesPrevio->format('Y-m-d'), $feriados)) {
        echo "El Viernes previo (" . $viernesPrevio->format('d-m-Y') . ") fue feriado, se incluirá en el proceso.\n";
        $fechasDeNegocio[] = $viernesPrevio;
    }
} else {
    // Si no es Lunes, solo procesamos la fecha de transacción
    $fechasDeNegocio[] = $fechaTransaccion;
}
// Invertimos el array para procesar las fechas en orden cronológico
$fechasDeNegocio = array_reverse($fechasDeNegocio);

// --- 3. PROCESAMIENTO DEL ARCHIVO EXCEL POR LOTES ---
$archivoEntrada = 'input/reporte_transacciones_16-10-2025_15-08-42.xlsx';
if (!file_exists($archivoEntrada)) {
    die("Error: El archivo de entrada no se encontró en: $archivoEntrada\n");
}

try {
    $spreadsheet = IOFactory::load($archivoEntrada);
    $hojaDeCalculo = $spreadsheet->getActiveSheet();
    $datosExcel = iterator_to_array($hojaDeCalculo->getRowIterator(2)); // Leer todas las filas de datos a memoria

    $encabezados = [];
    $primeraFila = $hojaDeCalculo->getRowIterator(1, 1)->current();
    foreach ($primeraFila->getCellIterator() as $celda) {
        $encabezados[] = $celda->getValue();
    }
    
    $numeroLote = 0;

    foreach ($fechasDeNegocio as $fechaNegocio) {
        $numeroLote++;
        $seEncontraronRegistros = false;

        // --- 3.1. ARMADO DEL HEADER ---
        $header = "HEADER" .
                  "A065" .
                  $fechaNegocio->format('Ymd') .
                  $fechaProceso->format('Ymd') .
                  str_pad($numeroLote, 5, '0', STR_PAD_LEFT);
        
        // Almacenamos las líneas del lote para imprimirlas juntas
        $lineasDelLote = [];
        $totalRegistrosLote = 0; // <-- NUEVO: Contador para el TRAILER
        $totalImporteLote = 0.0; // <-- NUEVO: Acumulador para el TRAILER
        
        foreach ($datosExcel as $fila) {
            $datosFilaAsociativos = [];
            $indiceCelda = 0;
            foreach ($fila->getCellIterator() as $celda) {
                $nombreColumna = $encabezados[$indiceCelda] ?? 'columna_' . $indiceCelda;
                $valorCelda = $celda->getValue();

                // PhpSpreadsheet a veces devuelve fechas como números de serie de Excel
                if (Date::isDateTime($celda)) {
                    $valorCelda = Date::excelToDateTimeObject($valorCelda)->format('Y-m-d H:i:s');
                }

                $datosFilaAsociativos[$nombreColumna] = $valorCelda;
                $indiceCelda++;
            }

            // --- 3.2. LÓGICA DE FILTRADO ---
            $estado = $datosFilaAsociativos['Estado'] ?? '';
            $fechaTrxStr = $datosFilaAsociativos['Fecha trx'] ?? '';
            
            if (!empty($fechaTrxStr)) {
                $fechaTrxObj = new DateTime($fechaTrxStr);
                
                // Comparamos si la fecha de la transacción coincide con la fecha del lote actual Y el estado es APPROVED
                if ($fechaTrxObj->format('Y-m-d') === $fechaNegocio->format('Y-m-d') && $estado === 'APPROVED') {
                    $seEncontraronRegistros = true;
                    
                    // 1. Transformar los datos crudos
                    $filaProcesada = transformarFila($datosFilaAsociativos);

                    // 2. Ensamblar la línea de texto final
                    $lineaFinal = ensamblarLinea($filaProcesada);
                    $lineasDelLote[] = $lineaFinal;
                    
                    // 3. Acumular para el TRAILER // <-- NUEVO
                    $totalRegistrosLote++;
                    $totalImporteLote += $filaProcesada['__IMPORTE_RAW__'];
                }
            }
        }
        
        // --- 3.3. IMPRESIÓN DEL LOTE ---
        // Solo imprimimos el lote si se encontraron registros para esa fecha
        if ($seEncontraronRegistros) {
            echo "\n--- Lote #" . str_pad($numeroLote, 5, '0', STR_PAD_LEFT) . " para Fecha de Negocio: " . $fechaNegocio->format('d-m-Y') . " ---\n";
            // Imprimir Header
            echo $header . "\n";
            
            // Imprimir Líneas de Datos
            foreach($lineasDelLote as $linea) {
                echo $linea . "\n";
            }

            // --- 3.4. ARMADO DEL TRAILER --- // <-- BLOQUE TOTALMENTE NUEVO
            
            // 1. "TRAILER" (Pos 1-7)
            $trailer = "TRAILER";
            
            // 2. Cantidad de registros (Pos 8-15)
            $trailer .= str_pad($totalRegistrosLote, 8, '0', STR_PAD_LEFT);
            
            // 3. Importe (Pos 16-28)
            // Formatear el importe a 2 decimales, sin separador de miles
            $importeFormateado = number_format($totalImporteLote, 2, '.', '');
            // Separar parte entera y decimal
            list($parteEntera, $parteDecimal) = explode('.', $importeFormateado);
            
            // Rellenar parte entera (11 chars, pos 16-26)
            $trailer .= str_pad($parteEntera, 11, '0', STR_PAD_LEFT);
            // Rellenar parte decimal (2 chars, pos 27-28)
            $trailer .= str_pad($parteDecimal, 2, '0', STR_PAD_LEFT);
            
            // 4. Cantidad de trx (Pos 29-36) - (Repite el campo 2)
            $trailer .= str_pad($totalRegistrosLote, 8, '0', STR_PAD_LEFT);
            
            // Imprimir el TRAILER
            echo $trailer . "\n";
            
        } else {
             echo "\n--- No se encontraron registros para la Fecha de Negocio: " . $fechaNegocio->format('d-m-Y') . " ---\n";
        }
    }

} catch (Exception $e) {
    die("Error al leer el archivo Excel: " . $e->getMessage() . "\n");
}

echo "\n--- Fin del script ---\n";
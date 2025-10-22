<?php

// Carga el autoloader de Composer para incluir las librerías necesarias.
require __DIR__ . '/../vendor/autoload.php';
require_once 'constants.php';

// "Alias" para las clases que vamos a usar.
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet; 
use PhpOffice\PhpSpreadsheet\Writer\Xlsx; 

/*
 * =================================================================
 * FASE 1: LÓGICA DE FECHAS Y FERIADOS
 * =================================================================
 */
 /* Por si a futuro se decide conectarse con algun servicio free de feriados
function obtenerFeriados(string $year): array

/*
 * Obtiene los feriados de Argentina para un año específico desde la API de Nager.Date.
 * @param string $year
 * @return array
 */

/*
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
    fin funcion de conexion a servicio de feriados */ 

/*
 * Resta una cantidad de días hábiles a una fecha, considerando feriados.
 * @param DateTime $fechaInicial
 * @param int $diasARestar
 * @param array $feriados
 * @return DateTime
 
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
*/

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
        $formaPago = ($cuotas === 1) ? '80' : '10';
    } elseif ($medioDePago === 'DEBIT') {
        $formaPago = '90';
    } elseif ($medioDePago === 'QR') {
        $formaPago = '20';
    } elseif ($medioDePago === 'PREPAID') {
        $formaPago = '60';
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

// --- 2. CÁLCULO DE EXTENSIÓN DE ARCHIVO  ---
echo "--- 2. CÁLCULO DE EXTENSIÓN DE ARCHIVO ---\n";
$mapaMeses = [
    1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6',
    7 => '7', 8 => '8', 9 => '9', 10 => 'A', 11 => 'B', 12 => 'C'
];

// Formatear la fecha de proceso en todos los formatos que usa FERIADOS
$f_ymd = $fechaProceso->format('ymd');   // 251018
$f_Ymd = $fechaProceso->format('Ymd');   // 20251018
$f_dmy = $fechaProceso->format('dmy');   // 181025
$f_dmY = $fechaProceso->format('dmY');   // 18102025

// Chequeamos si la FechaProceso es feriado usando la constante
$esFeriado = in_array($f_ymd, FERIADOS, true) || 
             in_array($f_Ymd, FERIADOS, true) || 
             in_array($f_dmy, FERIADOS, true) || 
             in_array($f_dmY, FERIADOS, true);
             
$diaDeLaSemana = (int)$fechaProceso->format('N'); // 1 (Lunes) a 7 (Domingo)
$esFinDeSemana = ($diaDeLaSemana >= 6); // Sábado (6) o Domingo (7)

$fechaParaExtension = clone $fechaProceso;

// Si es Sábado, Domingo o Feriado, calculamos el próximo día hábil
if ($esFinDeSemana || $esFeriado) {
    if ($esFinDeSemana) {
        echo "FechaProceso es fin de semana. Calculando siguiente día hábil...\n";
    } else {
        echo "FechaProceso es feriado. Calculando siguiente día hábil...\n";
    }
    
    // Iterar hasta encontrar un día hábil
    while (true) {
        $fechaParaExtension->modify('+1 day');
        $diaLoop = (int)$fechaParaExtension->format('N');
        
        if ($diaLoop >= 6) { // Sigue siendo fin de semana, continuar
            continue;
        }

        // No es fin de semana, chequear si es feriado
        $f_ymd_loop = $fechaParaExtension->format('ymd');
        $f_Ymd_loop = $fechaParaExtension->format('Ymd');
        $f_dmy_loop = $fechaParaExtension->format('dmy');
        $f_dmY_loop = $fechaParaExtension->format('dmY');
        
        $esFeriadoLoop = in_array($f_ymd_loop, FERIADOS, true) || 
                         in_array($f_Ymd_loop, FERIADOS, true) || 
                         in_array($f_dmy_loop, FERIADOS, true) || 
                         in_array($f_dmY_loop, FERIADOS, true);

        if (!$esFeriadoLoop) {
            // ¡Encontrado! No es fin de semana y no es feriado.
            break;
        }
    }
} else {
     echo "FechaProceso es día hábil. La extensión se basará en esta fecha.\n";
}

echo "Fecha para Extensión calculada: " . $fechaParaExtension->format('d-m-Y') . "\n";

$mesNum = (int)$fechaParaExtension->format('n'); // 'n' da el mes sin ceros (1-12)
$diaStr = $fechaParaExtension->format('d'); // 'd' da el día con cero (01-31)
$extensionCalculada = $mapaMeses[$mesNum] . $diaStr;
echo "Extensión de archivo calculada: " . $extensionCalculada . "\n";

// --- 3. BÚSQUEDA DINÁMICA DEL ARCHIVO DE ENTRADA ---
echo "--- 3. BÚSQUEDA DINÁMICA DEL ARCHIVO DE ENTRADA ---\n";

// 3.1. Definir el directorio de entrada (basado en tu ejemplo)
$directorioArchivos = __DIR__ . '/archivos';

// 3.2. Obtener componentes de la FechaProceso (que ya tenemos en $fechaProceso)
$dd = $fechaProceso->format('d'); // Día (ej: 21)
$mm = $fechaProceso->format('m'); // Mes (ej: 11)
$yyyy = $fechaProceso->format('Y'); // Año (ej: 2025)

// 3.3. Construir el patrón de búsqueda (Requisito 2 y 3)
// Busca: archivos/reporte_transacciones_DD-MM-YYYY_*.xlsx
$patronBusqueda = $directorioArchivos . "/reporte_transacciones_{$dd}-{$mm}-{$yyyy}_*.xlsx";

echo "Buscando archivos que coincidan con el patrón: $patronBusqueda\n";

// 3.4. Ejecutar la búsqueda
$archivosEncontrados = glob($patronBusqueda);

if ($archivosEncontrados === false) {
    // Error en la función glob()
    die("Error: Ocurrió un error al leer el directorio de entrada '$directorioEntrada'.\n");
}

$cantidadEncontrada = count($archivosEncontrados);
$archivoEntrada = ''; // Inicializamos la variable

// 3.5. Validar los resultados (Requisito 3)
if ($cantidadEncontrada === 0) {
    // No se encontró ningún archivo
    die("Error: No se encontró ningún archivo de reporte para la fecha {$dd}-{$mm}-{$yyyy}. Patrón buscado: $patronBusqueda\n");

} elseif ($cantidadEncontrada > 1) {
    // Se encontraron múltiples archivos
    echo "Error: Se encontró más de un archivo para la fecha {$dd}-{$mm}-{$yyyy}. Archivos encontrados:\n";
    foreach ($archivosEncontrados as $archivo) {
        echo " - $archivo\n";
    }
    die("Por favor, deje solo un archivo de reporte para la fecha a procesar y vuelva a intentarlo.\n");

} else {
    // ¡Éxito! Se encontró exactamente un archivo.
    $archivoEntrada = $archivosEncontrados[0];
    echo "Archivo de entrada seleccionado para procesar: $archivoEntrada\n";
}

// 3.6. PROCESAMIENTO DEL ARCHIVO EXCEL
try {
    $spreadsheet = IOFactory::load($archivoEntrada);
    $hojaDeCalculo = $spreadsheet->getActiveSheet();
    $datosExcel = iterator_to_array($hojaDeCalculo->getRowIterator(2)); // Leer todas las filas de datos a memoria

    $encabezados = [];
    $primeraFila = $hojaDeCalculo->getRowIterator(1, 1)->current();
    foreach ($primeraFila->getCellIterator() as $celda) {
        $encabezados[] = $celda->getValue();
    }
    
    // 3.1 (Generar archivocuotas.xlsx) ---
    echo "Generando archivo 'archivocuotas.xlsx'...\n";
    
    // 3.1.1. Crear un nuevo objeto Spreadsheet
    $spreadsheetCuotas = new Spreadsheet();
    $sheetCuotas = $spreadsheetCuotas->getActiveSheet();
    $sheetCuotas->setTitle('Cuotas');

    // 3.1.2. Escribir las cabeceras
    $sheetCuotas->setCellValue('A1', 'Transaccion');
    $sheetCuotas->setCellValue('B1', 'Cuotas');

    $filaCuotas = 2; // Fila de inicio de datos
    $registrosCuotas = 0;

    // 3.1.3. Recorrer los datos del Excel que ya tenemos en memoria
    foreach ($datosExcel as $fila) {
        // Convertimos la fila en un array asociativo (mismo método que usa el script)
        $datosFilaAsociativos = [];
        $indiceCelda = 0;
        foreach ($fila->getCellIterator() as $celda) {
            $nombreColumna = $encabezados[$indiceCelda] ?? 'columna_' . $indiceCelda;
            $valorCelda = $celda->getValue();
            $datosFilaAsociativos[$nombreColumna] = $valorCelda;
            $indiceCelda++;
        }

        // 3.1.4. FILTRAR: Solo nos interesan las transacciones aprobadas
        $estado = $datosFilaAsociativos['Estado'] ?? '';
        if ($estado === 'APPROVED') {
            
            // 3.1.5. OBTENER DATOS: Mapeamos las columnas que nos pediste
            $nroOperacion = $datosFilaAsociativos['Número de operación'] ?? '';
            $cuotas = $datosFilaAsociativos['Cuotas'] ?? '1'; // Default a 1 si está vacío

            // 3.1.6. ESCRIBIR EN EL NUEVO EXCEL
            $sheetCuotas->setCellValue('A' . $filaCuotas, $nroOperacion);
            $sheetCuotas->setCellValue('B' . $filaCuotas, (int)$cuotas); // Aseguramos que sea un número
            
            $filaCuotas++;
            $registrosCuotas++;
        }
    }

    // 3.1.7. GUARDAR EL ARCHIVO
    $writer = new Xlsx($spreadsheetCuotas);
    // Lo guardamos en el directorio raíz, donde 'archivosdiarios.php' espera encontrarlo.
    $rutaArchivoCuotas = 'archivocuotas.xlsx'; 
    $writer->save($rutaArchivoCuotas);

    echo "Archivo 'archivocuotas.xlsx' generado con $registrosCuotas registros.\n";
    // --- FIN Generacion de Archivo cuotas ---
    
    // --- 3.2 VERIFICAR/CREAR DIRECTORIO DE SALIDA --- // 
    $directorioSalida = $directorioArchivos;
        if (!is_dir($directorioSalida)) {
        echo "Creando directorio de salida en: $directorioSalida\n";
        if (!mkdir($directorioSalida, 0777, true)) {
            die("Error: No se pudo crear el directorio de salida: $directorioSalida\n");
        }
    }

    $seEncontraronRegistros = false;

    // --- 3.3 ARMADO DEL HEADER ---
    // Usamos $fechaProceso para ambos campos de fecha y '1' para el lote.
    $header = "HEADER" .
              "A065" .
              $fechaProceso->format('Ymd') .
              $fechaProceso->format('Ymd') .
              str_pad('1', 5, '0', STR_PAD_LEFT);
    
    // Almacenamos las líneas del lote para imprimirlas juntas
    $lineasDelLote = [];
    $totalRegistrosLote = 0; // Contador para el TRAILER
    $totalImporteLote = 0.0; // Acumulador para el TRAILER
    
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

        // --- 3.4 LÓGICA DE FILTRADO ---
        $estado = $datosFilaAsociativos['Estado'] ?? '';
        $fechaTrxStr = $datosFilaAsociativos['Fecha trx'] ?? '';
        
        if (!empty($fechaTrxStr)) {
            $fechaTrxObj = new DateTime($fechaTrxStr);
            
            // Comparamos si la 'Fecha trx' coincide con la 'FechaProceso' Y el estado es APPROVED
            if ($fechaTrxObj->format('Y-m-d') === $fechaProceso->format('Y-m-d') && $estado === 'APPROVED') {
                $seEncontraronRegistros = true;
                
                // 3.4.1. Transformar los datos crudos
                $filaProcesada = transformarFila($datosFilaAsociativos);

                // 3.4.2. Ensamblar la línea de texto final
                $lineaFinal = ensamblarLinea($filaProcesada);
                $lineasDelLote[] = $lineaFinal;
                
                // 3.4.3. Acumular para el TRAILER
                $totalRegistrosLote++;
                $totalImporteLote += $filaProcesada['__IMPORTE_RAW__'];
            }
        }
    }
    
    // --- 3.5. GENERACIÓN DE ARCHIVO DE LOTE --- // 
    // Solo generamos archivo si se encontraron registros para esa fecha
    if ($seEncontraronRegistros) {
        
        // --- 3.5.1. Definir nombre de archivo --- //
        // Nombre: "A065BOTON" + "FechaProceso" en formato "ddmmaa"
        $nombreArchivoBase = 'A065BOTON' . $fechaProceso->format('dmy'); // 'dmy' es ddmmaa
        // Ruta completa: output/A065BOTONXXXXXX.EXT
        $rutaArchivoCompleta = $directorioSalida . '/' . $nombreArchivoBase . '.' . $extensionCalculada;

        echo "\n--- Lote #1 para Fecha Proceso " . $fechaProceso->format('d-m-Y') . " ---\n";
        echo "    -> Generando archivo: " . $rutaArchivoCompleta . "\n";
        
        // --- 3.5.2. Abrir y escribir archivo --- //
        $archivoSalida = fopen($rutaArchivoCompleta, 'w');
        if (!$archivoSalida) {
            echo "    -> ERROR: No se pudo abrir el archivo de salida. Omitiendo este lote.\n";
            // No hay 'continue' porque no estamos en un loop
        } else {

            // Escribir Header
            fwrite($archivoSalida, $header . "\n");
            
            // Escribir Líneas de Datos
            foreach($lineasDelLote as $linea) {
                fwrite($archivoSalida, $linea . "\n");
            }

            // --- 3.5.3. ARMADO Y ESCRITURA DEL TRAILER --- //
            
            // 1. "TRAILER" (Pos 1-7)
            $trailer = "TRAILER";
            
            // 2. Cantidad de registros (Pos 8-15)
            $trailer .= str_pad($totalRegistrosLote, 8, '0', STR_PAD_LEFT);
            
            // 3. Importe (Pos 16-28)
            $importeFormateado = number_format($totalImporteLote, 2, '.', '');
            list($parteEntera, $parteDecimal) = explode('.', $importeFormateado);
            $trailer .= str_pad($parteEntera, 11, '0', STR_PAD_LEFT);
            $trailer .= str_pad($parteDecimal, 2, '0', STR_PAD_LEFT);
            
            // 4. Cantidad de trx (Pos 29-36) - (Repite el campo 2)
            $trailer .= str_pad($totalRegistrosLote, 8, '0', STR_PAD_LEFT);
            
            // Escribir el TRAILER
            fwrite($archivoSalida, $trailer . "\n");
            
            // Cerrar el archivo
            fclose($archivoSalida); 

            // --- INICIO: BLOQUE PARA GENERAR A065DEVBOTON VACIO
            echo "    -> Generando archivo A065DEVBOTON...\n";
            
            // Usamos la misma lógica de nombre base (ddmmyy) y extensión
            $nombreArchivoBase_DEV = 'A065DEVBOTON' . $fechaProceso->format('dmy'); // dmy = ddmmaa
            $rutaArchivoCompleta_DEV = $directorioSalida . '/' . $nombreArchivoBase_DEV . '.' . $extensionCalculada;

            // Contenido del archivo
            // $fechaProcesoStr ya contiene 'aaaammdd' del $argv[1]
            $header_DEV = "HEADER" . $fechaProcesoStr; 
            $trailer_DEV = "TRAILER00000";

            $archivoSalida_DEV = fopen($rutaArchivoCompleta_DEV, 'w');
            if (!$archivoSalida_DEV) {
                echo "    -> ERROR: No se pudo abrir el archivo de salida $rutaArchivoCompleta_DEV. Omitiendo este archivo.\n";
            } else {
                fwrite($archivoSalida_DEV, $header_DEV . "\n");
                fwrite($archivoSalida_DEV, $trailer_DEV . "\n");
                fclose($archivoSalida_DEV);
                echo "    -> Archivo $rutaArchivoCompleta_DEV generado con éxito.\n";
            }
            // --- FIN: NUEVO BLOQUE PARA A065DEVBOTON ---

        }

    } else {
         echo "\n--- No se encontraron registros para la Fecha de Proceso: " . $fechaProceso->format('d-m-Y') . " ---\n";
    }

} catch (Exception $e) {
    die("Error al leer el archivo Excel: " . $e->getMessage() . "\n");
}

echo "\n--- Fin del script ---\n";
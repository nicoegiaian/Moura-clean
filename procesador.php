<?php

// Carga el autoloader de Composer para incluir las librerías necesarias.
require 'vendor/autoload.php';

// "Alias" para las clases que vamos a usar.
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * =================================================================
 * FASE 3: MÓDULO DE TRANSFORMACIONES (Versión con Reglas de Negocio Corregidas)
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
            $fechaObjeto = DateTime::createFromFormat(DateTime::ATOM, $fechaTrx);
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
            // 1. Creamos un objeto DateTime a partir del string de la fecha
            $fechaPagoMerchantObj = new DateTime($fechaPagoMerchantStr);
        } catch (Exception $e) {
            $fechaPagoMerchantObj = null; // En caso de error, se mantiene nulo
        }
    }
    // 2. Usamos el objeto para formatear. Si el objeto es nulo, usamos el valor por defecto.
    // Nota: El formato 'Ymd' produce 8 caracteres, por lo que el relleno debe ser de 8 ceros.
    $filaTransformada['FECHA_LIQUIDACION'] = $fechaPagoMerchantObj ? $fechaPagoMerchantObj->format('Ymd') : '00000000'; // 161-168

    $filaTransformada['RELLENO_169_176'] = str_pad('', 8, '0'); // 169-176
    $filaTransformada['RELLENO_177_179'] = str_pad('', 3, '0'); // 177-179
    $filaTransformada['CODIGO DE BARRA'] = str_pad('', 60, '0'); // 180-239

    
    $filaTransformada['FECHA PAGO'] = $fechaObjeto ? $fechaObjeto->format('ymd') : '000000'; // 240-245
    $filaTransformada['TIPO DE TRANSACCION'] = str_pad('', 1, '0'); // 246-246
    $filaTransformada['RELLENO_247_253'] = str_pad('', 7, '0'); // 247-253
    $filaTransformada['ID_CLIENTE'] = str_pad('', 9, '0'); // 247-253

    $medioDePago = $datosOrigen['Medio de pago'] ?? '';
    $cuotas = (int)($datosOrigen['Cuotas'] ?? 0); // Convertimos a número para comparar
    $formaPago = ''; // Valor por defecto si no se cumple ninguna condición

    if ($medioDePago === 'CREDIT') {
        if ($cuotas === 1) {
            $formaPago = '80';
        } elseif ($cuotas > 1) {
            $formaPago = '30';
        }
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

// --- Bucle Principal de Ejecución ---

echo "--- Iniciando el script de procesamiento v6 (Final) ---\n";

$archivoEntrada = 'input/reporte_transacciones_16-10-2025_15-08-42.xlsx';
if (!file_exists($archivoEntrada)) {
    die("Error: El archivo de entrada no se encontró en: $archivoEntrada\n");
}

try {
    $spreadsheet = IOFactory::load($archivoEntrada);
    $hojaDeCalculo = $spreadsheet->getActiveSheet();

    $encabezados = [];
    $primeraFila = $hojaDeCalculo->getRowIterator(1, 1)->current();
    foreach ($primeraFila->getCellIterator() as $celda) {
        $encabezados[] = $celda->getValue();
    }
    
    $numeroFila = 0;
    
    foreach ($hojaDeCalculo->getRowIterator(2) as $fila) {
        $numeroFila++;
        $datosFilaAsociativos = [];
        $indiceCelda = 0;

        foreach ($fila->getCellIterator() as $celda) {
            $nombreColumna = $encabezados[$indiceCelda] ?? 'columna_'.$indiceCelda;
            $valorCelda = $celda->getValue();

            if (Date::isDateTime($celda)) {
                $valorCelda = Date::excelToDateTimeObject($valorCelda)->format('Y-m-d H:i:s');
            }

            $datosFilaAsociativos[$nombreColumna] = $valorCelda;
            $indiceCelda++;
        }

        // 1. Transformar los datos crudos
        $filaProcesada = transformarFila($datosFilaAsociativos);

        // 2. Ensamblar la línea de texto final
        $lineaFinal = ensamblarLinea($filaProcesada);
        
        /*echo "Línea #$numeroFila procesada (Longitud: " . strlen($lineaFinal) . "):\n";*/
        echo $lineaFinal . "\n\n";
        
        //descomentar para trabajar con un conjunto de 5 filas o dejar comentado para procesar todo el archivo
        
        if ($numeroFila >= 11) {
            break;
        }
       
    }

} catch (Exception $e) {
    die("Error al leer el archivo Excel: " . $e->getMessage() . "\n");
}

echo "\n--- Fin del script ---\n";


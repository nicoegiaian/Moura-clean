<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * =================================================================
 * FUNCIÓN DE LOG GLOBAL
 * =================================================================
 * Esta función se registrará para ser llamada al final de la 
 * ejecución del script (incluso si hay un error).
 * Tomará todo el contenido del búfer de salida (todos los 'echo')
 * y lo escribirá en un archivo de log.
 */
function guardar_log_al_finalizar() {
    // Hacemos visible la variable global de tiempo de inicio
    global $GLOBAL_START_TIME;

    // 1. Define el nombre de tu archivo de log
    $archivoLog = __DIR__ . '/procesador_API_Menta.log';

    // 2. Obtiene todo el contenido del búfer
    $contenidoLog = ob_get_contents();

    // 3. Limpia el búfer (y detiene el buffering)
    ob_end_clean();

    // --- INICIO: CÁLCULO DE DURACIÓN ---
    $endTime = microtime(true); // Tiempo exacto de finalización
    
    // (?? $endTime) es un seguro por si $GLOBAL_START_TIME no estuviera definida
    $durationSeconds = $endTime - ($GLOBAL_START_TIME ?? $endTime); 
    $durationMinutes = round($durationSeconds / 60, 2); // Convertimos a minutos
    
    // 4. Creamos el pie de página del log
    $footerLog = "\n\n============================================\n";
    $footerLog .= "== PROCESO FINALIZADO: " . (new DateTime())->format('Y-m-d H:i:s') . " ==\n";
    $footerLog .= "== DURACIÓN TOTAL: $durationMinutes minutos ==\n";
    $footerLog .= "============================================\n";
    // --- FIN: CÁLCULO DE DURACIÓN ---

    // 5. Escribe el contenido + el pie de página en el archivo
    file_put_contents($archivoLog, $contenidoLog . $footerLog);
}

// Cargar las dependencias de Composer (Guzzle, DotEnv)
require __DIR__ . '/vendor/autoload.php';
require_once 'constants.php';

// "Alias" para las clases de PhpSpreadsheet que usaremos
use PhpOffice\PhpSpreadsheet\Spreadsheet; 
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// --- Activacion de Log
ob_start(); // Inicia el búfer: PHP deja de imprimir en consola
register_shutdown_function('guardar_log_al_finalizar'); // Llama a nuestra función al final

// Capturamos el tiempo exacto de inicio en una variable global
$GLOBAL_START_TIME = microtime(true);
// Escribimos el banner de inicio en el log
echo "============================================\n";
echo "== PROCESO INICIADO: " . (new DateTime())->format('Y-m-d H:i:s') . " ==\n";
echo "============================================\n\n";


// Cargar las variables de entorno (desde el archivo .env)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__); 
$dotenv->load();

$fechaProcesoStr = null;
global $fechaProceso; // Hacemos $fechaProceso global para FASE 5

// 1. Leer el parámetro (CLI o WEB)
if (isset($_GET['fecha'])) {
    $fechaProcesoStr = $_GET['fecha'];
} elseif (isset($argv[1])) {
    $fechaProcesoStr = $argv[1];
}

if ($fechaProcesoStr === null) {
    echo "ERROR: No se proporcionó la fecha. Use ?fecha=... o como argumento en la CLI.\n";
    die();
}

echo "INFO: Procesando transacciones para la fecha: $fechaProcesoStr\n";

// 2. Determinar el formato (aaaammdd o ddmmaa)
$formatoFecha = null;
if (preg_match('/^\d{8}$/', $fechaProcesoStr)) {
    $formatoFecha = 'Ymd'; // Formato: aaaammdd
} elseif (preg_match('/^\d{6}$/', $fechaProcesoStr)) {
    $formatoFecha = 'dmy'; // Formato: ddmmaa
} else {
    echo "ERROR: El formato de fecha debe ser 'aaaammdd' o 'ddmmaa'. Se recibió: $fechaProcesoStr\n";
    die();
}

// 3. Crear el objeto DateTime (una sola vez)
try {
    $utc = new DateTimeZone('UTC');
    // Usamos el formato detectado para crear el objeto
    $fechaProceso = DateTime::createFromFormat($formatoFecha, $fechaProcesoStr, $utc);

    if ($fechaProceso === false) { 
        throw new Exception("Fecha inválida o no coincide con el formato $formatoFecha."); 
    }
    
    // 4. Crear los strings 'start' y 'end' para la API
    // Clonamos el objeto para no modificar el original ($fechaProceso se usa en FASE 5)
    $dt_start = clone $fechaProceso;
    $fechaStart = $dt_start->setTime(0, 0, 0)->format('Y-m-d\TH:i:s\Z');

    $dt_end = clone $fechaProceso;
    $fechaEnd = $dt_end->setTime(23, 59, 59)->format('Y-m-d\TH:i:s\Z');
             
    echo "INFO: Rango de búsqueda: $fechaStart -> $fechaEnd\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    die();
}

// --- Definiciones y Constantes ---
$MENTA_USER = $_ENV['MENTA_USER'];
$MENTA_PASSWORD = $_ENV['MENTA_PASSWORD'];
// Asumo esta URL base, ¡ajústala según la documentación!
$MENTA_API_URL = $_ENV['MENTA_API_URL']; 


// Dónde guardaremos nuestro token temporalmente
define('TOKEN_CACHE_FILE', 'token.cache.json');

// Declaramos las clases que usaremos (buenas prácticas)
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

// --- INICIO DEL PROCESO ---

echo "============================================\n";
echo "== INICIANDO PROCESO DE TRANSACCIONES MENTA ==\n";
echo "============================================\n\n";

// Variable global para guardar el token
$accessToken = null;
$transaccionesObtenidas = [];
$transaccionesMapeadas = [];


/**
 * =========================================================================
 * FASE 1: AUTENTICACIÓN (Obtener Access Token)
 * =========================================================================
 * Objetivo: Obtener un token OAuth2 válido, reusando uno cacheado si existe
 * y no ha expirado.
 */
function fase1_autenticacion() {
    global $accessToken, $MENTA_USER, $MENTA_PASSWORD, $MENTA_API_URL;

    echo "--- FASE 1: Autenticación ---\n";

    // 1.1: Revisar si tenemos un token guardado (en caché)
    if (file_exists(TOKEN_CACHE_FILE)) {
        $cacheData = json_decode(file_get_contents(TOKEN_CACHE_FILE), true);
        
        // 1.2: Revisar si el token NO ha expirado (le damos 60s de margen)
        if (isset($cacheData['expires_at']) && $cacheData['expires_at'] > (time() + 60)) {
            echo "INFO: Usando token válido desde caché.\n";
            $accessToken = $cacheData['access_token'];
            return; 
        }
        
        echo "INFO: El token en caché ha expirado o es inválido.\n";
    }

    // 1.3: Si no hay token o expiró, pedimos uno nuevo
    echo "ACCION: Solicitando nuevo token de acceso a Menta...\n";
    
    $client = new Client([
        'base_uri' => $MENTA_API_URL,
        'timeout'  => 10.0, // Timeout de 10 segundos
    ]);

    try {
   
        $response = $client->post('v1/login', [ 
            'json' => [ 
                'user'     => $MENTA_USER,     
                'password' => $MENTA_PASSWORD  
            ]
        ]);

        if ($response->getStatusCode() === 200) {
            $data = json_decode($response->getBody(), true);
            

            $accessToken = $data['token']['access_token']; // El token
            $expiresIn = $data['token']['expires_in'];   // Segundos de vida (ej: 43200)
            
            // Calculamos la marca de tiempo UNIX de expiración
            $expiresAt = time() + $expiresIn;

            // 1.4: Guardar el nuevo token en caché
            file_put_contents(TOKEN_CACHE_FILE, json_encode([
                'access_token' => $accessToken,
                'expires_at'   => $expiresAt
            ]));
            
            echo "EXITO: Nuevo token obtenido y guardado en caché.\n";
            echo "       (Expira en $expiresIn segundos)\n";

        } else {
            echo "ERROR: La API devolvió un estado no exitoso: " . $response->getStatusCode() . "\n";
        }

    } catch (GuzzleException $e) {
        // Error de conexión o error de la API (4xx, 5xx)
        echo "ERROR CRITICO: No se pudo obtener el token.\n";
        echo "Mensaje: " . $e->getMessage() . "\n";
        // Si el error es 400/401, probablemente tus credenciales están mal
        if ($e->hasResponse()) {
            echo "Respuesta de la API: " . $e->getResponse()->getBody()->getContents() . "\n";
        }
        die(); // Morimos. Sin token no podemos continuar.
    }
}


/**
 * =========================================================================
 * FASE 2: PETICIÓN DE TRANSACCIONES (CON PAGINACIÓN)
 * =========================================================================
 * Objetivo: Usar el token para consultar el endpoint 'Transacciones v2.0'.
 * Implementa un bucle 'do-while' para traer TODAS las páginas de resultados.
 */
function fase2_peticion_transacciones() {
    global $accessToken, $transaccionesObtenidas, $MENTA_API_URL, $fechaStart, $fechaEnd;
    
    if (!$accessToken) {
        echo "ERROR: No hay token (Fase 1 falló). No se puede continuar.\n";
        return;
    }
    
    echo "\n--- FASE 2: Petición de Transacciones ---\n";

    // Creamos un nuevo cliente Guzzle para esta fase
    $client = new Client([
        'base_uri' => $MENTA_API_URL,
        'timeout'  => 30.0, // Damos más timeout por si la consulta es pesada
    ]);

    $paginaActual = 0;   // Empezamos en la página 0
    $paginasTotales = 1; // Valor inicial para que el bucle comience

    do {
        echo "INFO: Solicitando página $paginaActual...\n";

        try {
            $response = $client->get('v2/transaction-reports', [ // Endpoint SIN / al inicio
                'headers' => [ 
                    'Authorization' => 'Bearer ' . $accessToken 
                ],
                'query' => [ 
                    'page'  => $paginaActual,
                    'size'  => 10000, // El tamaño de página que pediste
                    'start' => $fechaStart,
                    'end'   => $fechaEnd
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getBody(), true);
                
                $transaccionesPagina = $data['content']; // Transacciones de ESTA página
                $nuevasObtenidas = count($transaccionesPagina);
                
                // Agregamos las transacciones de esta página al array global
                $transaccionesObtenidas = array_merge($transaccionesObtenidas, $transaccionesPagina);
                
                // Actualizamos nuestros contadores de paginación
                $paginasTotales = $data['total_pages'];
                $paginaActual = $data['pageable']['page_number'];
                
                echo "EXITO: Se obtuvieron $nuevasObtenidas transacciones de la página $paginaActual.\n";
                echo "       (Página " . ($paginaActual + 1) . " de $paginasTotales. Total acumulado: " . count($transaccionesObtenidas) . ")\n";

                // Preparamos la siguiente iteración
                $paginaActual++;

            } else {
                echo "ERROR: La API devolvió un estado no exitoso: " . $response->getStatusCode() . "\n";
                break; // Salimos del bucle si hay un error
            }

        } catch (GuzzleException $e) {
            echo "ERROR CRITICO: Falla en la petición de transacciones.\n";
            echo "Mensaje: " . $e->getMessage() . "\n";
            if ($e->hasResponse()) {
                // Si el error es 401, el token expiró. 
                // Una lógica más avanzada re-intentaría la Fase 1 aquí.
                echo "Respuesta de la API: " . $e->getResponse()->getBody()->getContents() . "\n";
            }
            break; // Salimos del bucle
        }

    } while ($paginaActual < $paginasTotales); // Continuamos mientras haya páginas por pedir

    echo "\n--- FIN FASE 2: Se obtuvieron un total de " . count($transaccionesObtenidas) . " transacciones. ---\n";
}

/**
 * =========================================================================
 * FASE 3: MAPEO CON CLASE
 * =========================================================================
 * Objetivo: Convertir el array de datos crudos (JSON) en objetos PHP
 * tipados (nuestra clase 'Transaccion').
 * Si en el futuro decides que sí necesitas el customer_id en tu archivo de texto, el proceso sería:
*       Añadir public ?string $customer_id; a la class Transaccion.
*       Añadir $tx->customer_id = $data['customer_id'] ?? null; al fromArray.
*       Modificar transformarFila y ensamblarLinea para usar ese nuevo campo.
 */

// Definimos la clase que representará nuestros datos
class Transaccion {
    // --- Campos Principales (Nivel 1) ---
    public string $transaction_id;
    public string $operation_id;
    public string $status;
    public string $datetime;
    public float $gross_amount;
    public string $currency;
    public int $installments;
    public string $payment_method;
    public string $operation_type;
    public ?string $merchant_id;
    public ?string $terminal_id;

    // --- Campos de 'operation_detail' (Aplanados) ---
    public ?string $holder_name;
    public ?string $holder_document;
    public ?string $card_brand;
    public ?string $card_mask;
    public ?string $authorization_code;

    // --- Campos de 'tax_info' (Aplanados) ---
    public ?float $net_amount;
    public ?string $payment_date;
    
    // --- CAMPOS NUEVOS (requeridos por transformarFila) ---
    public int $operation_number; // Para el campo 'TRANSACCION'
    public int $ref_operation_number; //solo con datos para ANNULMENTS o REFUNDS donde coincidra con el operation_number de un PAYMENT / APPROVED
    public ?string $merchant_additional_info; // Para el campo 'N_COMERCIO'
    public float $tax_commission;
    public float $tax_commission_vat;
    public float $tax_financial_cost;
    public float $tax_financial_cost_rate = 0.0;
    public float $tax_financial_cost_vat;
    public float $tax_financial_cost_vat_rate = 0.0;
    
    /**
     * Método "Factory" para crear un objeto desde el array REAL de la API
     * @param array $data Un elemento del array 'content' de la API
     */
    public static function fromArray(array $data): self {
        $tx = new self();
        
        // --- Mapeo Nivel 1 (Campos Principales) ---
        $tx->transaction_id = $data['transaction_id'] ?? 'N/A';
        $tx->operation_id = $data['operation_id'] ?? 'N/A';
        $tx->status = $data['status'] ?? 'UNKNOWN';
        $tx->datetime = $data['datetime'] ?? '';
        $tx->gross_amount = (float) ($data['gross_amount'] ?? 0.0);
        $tx->currency = $data['currency'] ?? 'ARS';
        $tx->installments = (int) ($data['installments'] ?? 0);
        $tx->payment_method = $data['payment_method'] ?? 'UNKNOWN';
        $tx->operation_type = $data['operation_type'] ?? 'UNKNOWN';
        $tx->merchant_id = $data['merchant_id'] ?? null;
        $tx->terminal_id = $data['terminal_id'] ?? null;

        // --- Mapeo Nivel 2 (Datos Anidados) ---
        $op_detail = $data['operation_detail'] ?? [];
        $tax_info = $data['tax_info'] ?? [];
        $card_info = $op_detail['card'] ?? [];

        $tx->holder_name = $op_detail['holder_name'] ?? null;
        $tx->holder_document = $op_detail['holder_document'] ?? null;
        $tx->authorization_code = $op_detail['authorization_code'] ?? null;
        $tx->card_brand = $card_info['card_brand'] ?? null;
        $tx->card_mask = $card_info['card_mask'] ?? null;
        $tx->net_amount = (float) ($tax_info['net_amount'] ?? 0.0);
        $tx->payment_date = $tax_info['payment_date'] ?? null;
        
        // --- MAPEANDO LOS CAMPOS NUEVOS ---
        $tx->operation_number = (int) ($data['operation_number'] ?? 0);
        $tx->ref_operation_number = (int) ($data['ref_operation_number'] ?? 0);
        $tx->merchant_additional_info = $data['merchant_additional_info'] ?? null;
        $tx->tax_commission = 0.0;
        $tx->tax_commission_vat = 0.0;
        $tx->tax_financial_cost = 0.0;
        $tx->tax_financial_cost_vat = 0.0;
        
        $tax_breakdown = $tax_info['tax_breakdown'] ?? [];

        if (is_array($tax_breakdown)) {
            foreach ($tax_breakdown as $tax) {
                $tax_code = $tax['tax_code'] ?? '';
                $amount = (float) ($tax['amount'] ?? 0.0);
                
                switch ($tax_code) {
                    case 'ACQUIRER_TO_CUSTOMER_COMMISSION':
                        $tx->tax_commission = $amount;
                        break;
                    case 'ACQUIRER_TO_CUSTOMER_COMMISSION_VAT_TAX':
                        $tx->tax_commission_vat = $amount;
                        break;
                    case 'FINANCIAL_COST':
                        $tx->tax_financial_cost = $amount;
                        $tx->tax_financial_cost_rate = (float) ($tax['rate'] ?? 0.0);
                        break;
                    case 'FINANCIAL_COST_VAT_TAX':
                        $tx->tax_financial_cost_vat = $amount;
                        $rate = (float) ($tax['rate'] ?? 0.0);
                        if ($rate == 21.0) {
                            // RECALCULAMOS: Usamos 10.5% de la base del FINANCIAL_COST
                            // (Asumimos que $tx->tax_financial_cost ya se asignó en el loop)
                            $tx->tax_financial_cost_vat_rate = 10.5;
                        } else {
                            // Si es 10.5 o 0, usamos el monto que vino de la API
                            $tx->tax_financial_cost_vat_rate = (float) ($tax['rate'] ?? 0.0);;
                        }
                        
                        break;
                }
            }
        }
        return $tx;
    }
}

function fase3_mapeo_clases() {
    global $transaccionesObtenidas, $transaccionesMapeadas;
    
    echo "\n--- FASE 3: Mapeo a Clases ---\n";
    
    foreach ($transaccionesObtenidas as $tx_raw) {
        $transaccionesMapeadas[] = Transaccion::fromArray($tx_raw);
    }
    
    echo "EXITO: Se mapearon " . count($transaccionesMapeadas) . " objetos Transaccion.\n";

    /* =================================================================
    // --- INICIO: BLOQUE DE DEBUG A CSV ---
    // =================================================================
    
    // Verificamos si hay algo que escribir
    if (empty($transaccionesMapeadas)) {
        echo "DEBUG: No hay transacciones mapeadas para generar el CSV.\n";
        return; // Salimos de la función
    }

    echo "DEBUG: Generando archivo 'debug_transacciones.csv'...\n";
    
    // 1. Abrimos el archivo
    $archivoDebug = fopen('debug_transacciones.csv', 'w');
    if (!$archivoDebug) {
        echo "DEBUG: ERROR! No se pudo crear 'debug_transacciones.csv'.\n";
        return;
    }

    // 2. Escribimos los Headers (los nombres de las propiedades de la clase)
    // Tomamos la primera transacción como modelo para sacar los nombres
    $primeraTx = $transaccionesMapeadas[0];
    $headers = array_keys(get_object_vars($primeraTx));
    fputcsv($archivoDebug, $headers);

    // 3. Escribimos todas las filas de datos
    foreach ($transaccionesMapeadas as $tx) {
        // Convertimos el objeto en un array y lo escribimos en el CSV
        fputcsv($archivoDebug, get_object_vars($tx));
    }

    // 4. Cerramos el archivo
    fclose($archivoDebug);
    
    echo "DEBUG: Archivo 'debug_transacciones.csv' generado con éxito.\n";
    
    // =================================================================
    // --- FIN: BLOQUE DE DEBUG A CSV ---
    // =================================================================
    */
}




/**
 * =========================================================================
 * FASE 4: MÓDULO DE TRANSFORMACIONES (Adaptado de procesador.php)
 * =========================================================================
 * Recibe un objeto Transaccion (de la API) y aplica las reglas de negocio.
 * @param Transaccion $tx
 * @return array
 */
function transformarFila(Transaccion $tx): array
{
    $filaTransformada = [];

    // --- IMPLEMENTACIÓN DE REGLAS DE TRANSFORMACIÓN (1-286) ---
    // (Lógica portada de procesador.php y adaptada al objeto $tx)

    $filaTransformada['TIPO_REGISTRO'] = "DATOS   "; // 1-8
    $filaTransformada['CODIGO_ENTIDAD'] = "A065"; // 9-12
    $filaTransformada['R'] = "R"; // 13-13
    $filaTransformada['CODIGO_TERMINAL'] = "02222"; // 14-18
    $filaTransformada['PARSUBCOD'] = str_pad('', 10, '0'); // 19-28
    $filaTransformada['CODIGO_SUCURSAL'] = "2222"; // 29-32
    $filaTransformada['RELLENO_33_36'] = str_pad('', 4, '0');; // 33-36

    // ADAPTADO: Leemos de $tx->operation_number
    $transaccion = $tx->operation_number;
    $filaTransformada['TRANSACCION'] = str_pad($transaccion, 12, '0', STR_PAD_LEFT); // 37-48

    $filaTransformada['CODIGO_OPERACION'] = "A3"; // 49-50
    $filaTransformada['RUBRO_TX'] = "00"; // 51-52

    // ADAPTADO: Leemos de $tx->merchant_additional_info
    $nroComercio = $tx->merchant_additional_info ?? '';
    $filaTransformada['N_COMERCIO'] = !empty(trim($nroComercio)) ? str_pad($nroComercio, 6, ' ', STR_PAD_RIGHT) : "000000"; // 53-58

    $filaTransformada['CODIGO_SERVICIO'] = str_pad('', 19, ' '); // 59-77

    // ADAPTADO: Leemos de $tx->gross_amount
    $montoBruto = $tx->gross_amount;
    $filaTransformada['__IMPORTE_RAW__'] = $montoBruto; // Campo interno para sumar en el TRAILER
    $montoSinDecimales = $montoBruto * 100;
    $filaTransformada['IMPORTE'] = str_pad($montoSinDecimales, 11, '0', STR_PAD_LEFT); // 78-88

    $filaTransformada['RELLENO_89_99'] = "00000000000"; // 89-99
    $filaTransformada['RELLENO_100_110'] = "00000000000"; // 100-110

    // ADAPTADO: Leemos de $tx->currency
    $monedaOrigen = $tx->currency;
    $monedaDestino = '2'; // Valor por defecto
    if ($monedaOrigen === 'ARS') {
        $monedaDestino = '0';
    } elseif ($monedaOrigen === 'USD') {
        $monedaDestino = '1';
    }
    // ... (puedes añadir más mapeos si es necesario, como 'MX')
    $filaTransformada['MONEDA'] = $monedaDestino; // 111-111

    $filaTransformada['RELLENO_112_115'] = str_pad('', 4, '0'); // 112-115
    $filaTransformada['TIPO DE USUARIO'] = str_pad('', 20, '0'); // 116-135
    $filaTransformada['RELLENO_136_138'] = str_pad('', 3, '0'); // 136-138
    $filaTransformada['RELLENO_139_144'] = str_pad('', 6, '0'); // 139-144
    
    // ADAPTADO: Leemos de $tx->datetime
    $fechaTrx = $tx->datetime;
    $fechaObjeto = null;
    if (!empty($fechaTrx)) {
        try {
            $fechaObjeto = new DateTime($fechaTrx);
        } catch (Exception $e) {
            $fechaObjeto = null;
        }
    }
    $filaTransformada['HORARIO_DE_LA_TX'] = $fechaObjeto ? $fechaObjeto->format('His') : '000000'; // 145-150 
    
    $filaTransformada['PROCESADOR DE PAGO'] = "010"; // 151-153
    $filaTransformada['RELLENO_154_156'] = str_pad('', 3, '0'); // 154-156
    $filaTransformada['PROCESADOR DE DEBITO INTERNO'] = str_pad('', 4, '0'); // 157-160

    // ADAPTADO: Leemos de $tx->payment_date
    $fechaPagoMerchantStr = $tx->payment_date;
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

    // ADAPTADO: Leemos de $tx->payment_method y $tx->installments
    $medioDePago = $tx->payment_method;
    $cuotas = $tx->installments;
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

    // ADAPTADO: Leemos de $tx->installments
    $filaTransformada['CANTIDAD_CUOTAS'] = str_pad($cuotas, 3, '0', STR_PAD_LEFT); // 269-271

    $filaTransformada['DNI_CLIENTE'] = str_pad('', 15, '0'); // 272-286

    // --- NUEVAS REGLAS CORREGIDAS (287-620) ---
    
    $filaTransformada['RELLENO_287_294'] = str_pad('', 8, '0'); // 287-294
    $filaTransformada['ID_TX_PROCESADOR'] = str_pad('', 30, '0'); // 295-324
    $filaTransformada['BARRA CUPON DE PAGO'] = str_pad('', 150, '0'); // 325-474
    $filaTransformada['ID GATEWAY'] = str_pad('', 30, '0'); // 475-504
   
    $montoComm = $tx->tax_commission * 100;
    $filaTransformada['TAX_COMMISSION'] = str_pad($montoComm, 11, '0', STR_PAD_LEFT); // 505-515
   
    $montoCommVat = $tx->tax_commission_vat * 100;
    $filaTransformada['TAX_COMMISSION_VAT'] = str_pad($montoCommVat, 11, '0', STR_PAD_LEFT); // 516-526
   
    $montoFinCost = $tx->tax_financial_cost * 100;
    $filaTransformada['TAX_FINANCIAL_COST'] = str_pad($montoFinCost, 11, '0', STR_PAD_LEFT); // 527-537
   
    $montoFinCostVat = $tx->tax_financial_cost_vat * 100;
    $filaTransformada['TAX_FINANCIAL_COST_VAT'] = str_pad($montoFinCostVat, 11, '0', STR_PAD_LEFT); // 538-548
   
    // Guardamos el RATE (no el monto) (ej: 15.12% -> 1512)
    $rateFinCost = $tx->tax_financial_cost_rate * 100;
    $filaTransformada['TAX_FINANCIAL_COST_RATE'] = str_pad($rateFinCost, 11, '0', STR_PAD_LEFT); // 549-559
   
    // Guardamos el RATE del IVA (no el monto) (ej: 21.0% -> 2100)
    $rateFinCostVat = $tx->tax_financial_cost_vat_rate * 100;
    $filaTransformada['TAX_FINANCIAL_COST_VAT_RATE'] = str_pad($rateFinCostVat, 11, '0', STR_PAD_LEFT); // 560-570
    
    $filaTransformada['RELLENO_571_594'] = str_pad('', 24, ' '); // 571-594
    $filaTransformada['ID CAMPAÑA'] = str_pad('', 8, '0'); // 595-602
    $filaTransformada['RELLENO 603-605'] = str_pad('', 3, ' '); // 603-605
    $filaTransformada['BIN DE LA TARJETA'] = str_pad('', 6, '0');// 606-611
    $filaTransformada['ID RUBRO COMERCIO'] = str_pad('', 6, '0');// 612-617
    $filaTransformada['ID PROV CLIENTE'] = str_pad('', 3, '0');// 618-620
    
    return $filaTransformada;
}

/**
 * Concatena los campos transformados para generar la línea de ancho fijo.
 * (Esta función es idéntica a la de procesador.php)
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
        'ID GATEWAY', 'TAX_COMMISSION', 'TAX_COMMISSION_VAT', 'TAX_FINANCIAL_COST', 'TAX_FINANCIAL_COST_VAT',
        'TAX_FINANCIAL_COST_RATE','TAX_FINANCIAL_COST_VAT_RATE',
        'RELLENO_571_594','ID CAMPAÑA', 'RELLENO 603-605',
        'BIN DE LA TARJETA', 'ID RUBRO COMERCIO', 'ID PROV CLIENTE'
    ];
    
    $lineaFinal = '';
    foreach ($ordenDeCampos as $campo) {
        $lineaFinal .= $filaProcesada[$campo] ?? '';
    }

    return $lineaFinal;
}

/**
 * =========================================================================
 * FASE 5: GENERACIÓN DE ARCHIVOS DE SALIDA (Portado de procesador.php)
 * =========================================================================
 * Toma los objetos Transaccion mapeados y genera los archivos finales.
 */
function fase5_generar_archivos() {
    global $fechaProceso, $transaccionesMapeadas, $fechaProcesoStr; // Usamos las variables globales

    echo "\n--- FASE 5: Generación de Archivos de Salida ---\n";

    // --- 1. CÁLCULO DE EXTENSIÓN DE ARCHIVO (Lógica de procesador.php) ---
    echo "Cálculo de extensión de archivo...\n";
    $mapaMeses = [
        1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5', 6 => '6',
        7 => '7', 8 => '8', 9 => '9', 10 => 'A', 11 => 'B', 12 => 'C'
    ];

    // Formatear la fecha de proceso en todos los formatos que usa FERIADOS
    $f_ymd = $fechaProceso->format('ymd');
    $f_Ymd = $fechaProceso->format('Ymd');
    $f_dmy = $fechaProceso->format('dmy');
    $f_dmY = $fechaProceso->format('dmY');

    // Chequeamos si la FechaProceso es feriado usando la constante FERIADOS
    $esFeriado = in_array($f_ymd, FERIADOS, true) || 
                 in_array($f_Ymd, FERIADOS, true) || 
                 in_array($f_dmy, FERIADOS, true) || 
                 in_array($f_dmY, FERIADOS, true);
                 
    $diaDeLaSemana = (int)$fechaProceso->format('N');
    $esFinDeSemana = ($diaDeLaSemana >= 6);

    $fechaParaExtension = clone $fechaProceso;

    if ($esFinDeSemana || $esFeriado) {
        //if ($esFinDeSemana) echo "FechaProceso es fin de semana. Calculando siguiente día hábil...\n";
        //else echo "FechaProceso es feriado. Calculando siguiente día hábil...\n";
        
        while (true) {
            $fechaParaExtension->modify('+1 day');
            $diaLoop = (int)$fechaParaExtension->format('N');
            if ($diaLoop >= 6) continue;

            $f_ymd_loop = $fechaParaExtension->format('ymd');
            $f_Ymd_loop = $fechaParaExtension->format('Ymd');
            $f_dmy_loop = $fechaParaExtension->format('dmy');
            $f_dmY_loop = $fechaParaExtension->format('dmY');
            
            $esFeriadoLoop = in_array($f_ymd_loop, FERIADOS, true) || 
                             in_array($f_Ymd_loop, FERIADOS, true) || 
                             in_array($f_dmy_loop, FERIADOS, true) || 
                             in_array($f_dmY_loop, FERIADOS, true);

            if (!$esFeriadoLoop) break;
        }
    } else {
         echo "FechaProceso es día hábil. La extensión se basará en esta fecha.\n";
    }

    echo "Fecha para Extensión calculada: " . $fechaParaExtension->format('d-m-Y') . "\n";
    $mesNum = (int)$fechaParaExtension->format('n');
    $diaStr = $fechaParaExtension->format('d');
    $extensionCalculada = $mapaMeses[$mesNum] . $diaStr;
    echo "Extensión de archivo calculada: " . $extensionCalculada . "\n";

    // --- 2. GENERACIÓN DE 'archivocuotas.xlsx' ---
    echo "Generando archivo 'archivocuotas.xlsx'...\n";
    
    $spreadsheetCuotas = new Spreadsheet();
    $sheetCuotas = $spreadsheetCuotas->getActiveSheet();
    $sheetCuotas->setTitle('Cuotas');
    $sheetCuotas->setCellValue('A1', 'Transaccion');
    $sheetCuotas->setCellValue('B1', 'Cuotas');

    $filaCuotas = 2;
    $registrosCuotas = 0;

    foreach ($transaccionesMapeadas as $tx) {
        // FILTRAR: Solo 'APPROVED' (igual que en procesador.php)
        if ($tx->status === 'APPROVED') {
            // Usamos operation_number (int)
            $sheetCuotas->setCellValue('A' . $filaCuotas, $tx->operation_number);
            // Usamos installments (int)
            $sheetCuotas->setCellValue('B' . $filaCuotas, $tx->installments);
            
            $filaCuotas++;
            $registrosCuotas++;
        }
    }

    $writer = new Xlsx($spreadsheetCuotas);
    $rutaArchivoCuotas = 'archivocuotas.xlsx'; 
    $writer->save($rutaArchivoCuotas);
    echo "Archivo 'archivocuotas.xlsx' generado con $registrosCuotas registros.\n";

    // --- 3. GENERACIÓN DE ARCHIVO DE LOTE (A065BOTON...) ---

    // 3.1. VERIFICAR/CREAR DIRECTORIO DE SALIDA
    $directorioSalida = 'archivos';
    if (!is_dir($directorioSalida)) {
        echo "Creando directorio de salida en: $directorioSalida\n";
        if (!mkdir($directorioSalida, 0777, true)) {
            die("Error: No se pudo crear el directorio de salida: $directorioSalida\n");
        }
    }
    
    // 3.2. ARMADO DEL HEADER
    $header = "HEADER" .
              "A065" .
              $fechaProceso->format('Ymd') .
              $fechaProceso->format('Ymd') .
              str_pad('1', 5, '0', STR_PAD_LEFT);
    
    $lineasDelLote = [];
    $totalRegistrosLote = 0;
    $totalImporteLote = 0.0;
    $totalRegistrosDescartados = 0;
    
    // 3.3. BUCLE DE TRANSFORMACIÓN (Itera sobre los objetos $transaccionesMapeadas)
    foreach ($transaccionesMapeadas as $tx) {
        
        // 3.3.1. LÓGICA DE FILTRADO (de procesador.php)
        // La API ya nos dio solo las del día, pero re-validamos el estado.
        if ($tx->status === 'APPROVED') {
            
            // 3.3.2. Transformar el objeto $tx
            $filaProcesada = transformarFila($tx);

            // 3.3.3. Ensamblar la línea de texto final
            $lineaFinal = ensamblarLinea($filaProcesada);
            $lineasDelLote[] = $lineaFinal;
            
            // 3.3.4. Acumular para el TRAILER
            $totalRegistrosLote++;
            $totalImporteLote += $filaProcesada['__IMPORTE_RAW__'];
        }
        else {
            $totalRegistrosDescartados++; // Contamos el registro descartado
        }
    }

    // 3.4. ESCRITURA DE ARCHIVO DE LOTE
    if ($totalRegistrosLote > 0) {
        
        $nombreArchivoBase = 'A065BOTON' . $fechaProceso->format('dmy');
        $rutaArchivoCompleta = $directorioSalida . '/' . $nombreArchivoBase . '.' . $extensionCalculada;

        echo "\n--- Lote #1 para Fecha Proceso " . $fechaProceso->format('d-m-Y') . " ---\n";
        echo "    -> Generando archivo: " . $rutaArchivoCompleta . "\n";
        
        $archivoSalida = fopen($rutaArchivoCompleta, 'w');
        if (!$archivoSalida) {
            die("    -> ERROR: No se pudo abrir el archivo de salida: $rutaArchivoCompleta\n");
        }

        // Escribir Header
        fwrite($archivoSalida, $header . "\n");
        
        // Escribir Líneas de Datos
        foreach($lineasDelLote as $linea) {
            fwrite($archivoSalida, $linea . "\n");
        }

        // 3.5. ARMADO Y ESCRITURA DEL TRAILER
        $trailer = "TRAILER";
        $trailer .= str_pad($totalRegistrosLote, 8, '0', STR_PAD_LEFT);
        
        $importeFormateado = number_format($totalImporteLote, 2, '.', '');
        list($parteEntera, $parteDecimal) = explode('.', $importeFormateado);
        $trailer .= str_pad($parteEntera, 11, '0', STR_PAD_LEFT);
        $trailer .= str_pad($parteDecimal, 2, '0', STR_PAD_LEFT);
        
        $trailer .= str_pad($totalRegistrosLote, 8, '0', STR_PAD_LEFT);
        
        fwrite($archivoSalida, $trailer . "\n");
        fclose($archivoSalida); 
        
        echo "    -> Archivo generado con $totalRegistrosLote registros de transacciones en status APPROVED.\n";
        
        if ($totalRegistrosDescartados > 0) {
            echo "    -> Se descartaron $totalRegistrosDescartados registros por transacciones con estados FAILED, REVERSED o REJECTED.\n";
        }
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
        }  // --- FIN: NUEVO BLOQUE PARA A065DEVBOTON ---
        else {
        echo "\n--- No se encontraron registros 'APPROVED' para la Fecha de Proceso: " . $fechaProceso->format('d-m-Y') . " ---\n";
        if ($totalRegistrosDescartados > 0) {
            echo "    -> Se descartaron $totalRegistrosDescartados registros por transacciones con estados FAILED, REVERSED o REJECTED.\n";
        }
        }
}


// --- Ejecución del Proceso ---

try {
    fase1_autenticacion();
    fase2_peticion_transacciones();
    fase3_mapeo_clases();
    
    // Solo continuamos si las fases de API y mapeo fueron exitosas
    if (!empty($transaccionesMapeadas)) {
        fase5_generar_archivos();
    } elseif (empty($transaccionesObtenidas)) {
        echo "\n--- No se obtuvieron transacciones de la API. No se generarán archivos. ---\n";
    } else {
        echo "\n--- El mapeo de transacciones falló. No se generarán archivos. ---\n";
    }


} catch (Exception $e) {
    echo "\n\n--- ERROR CRÍTICO ---\n";
    echo "Mensaje: " . $e->getMessage() . "\n";
    echo "Línea: " . $e->getLine() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "-----------------------\n\n";
}

?>
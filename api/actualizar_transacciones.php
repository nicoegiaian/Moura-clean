<?php

/**
 * =========================================================================
 * SCRIPT: Actualizar Datos de Transacciones desde API Menta
 * =========================================================================
 * 
 * Este script busca las transacciones que les faltan datos y consulta la
 * API de Menta para obtenerlos:
 *   - serial_number
 *   - metodopagoOriginal (CREDIT->CR, DEBIT->DE, QR->QR, PREPAID->PR)
 *   - marca (card_brand: VISA, MASTERCARD, AMEX, CABAL, etc.)
 * 
 * USO:
 *   php actualizar_transacciones.php              -> Procesa todo
 *   php actualizar_transacciones.php 190126       -> Solo fecha 19/01/2026
 * 
 * @version: 1.0
 * @fecha: 20 de Enero de 2026
 * =========================================================================
 */

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/vendor/autoload.php';
require_once 'constants.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\GuzzleException;

// Cargar variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// --- Configuración Menta ---
$MENTA_USER = (getenv('MENTA_USER') == "") ? "credimoura2025@gmail.com" : getenv('MENTA_USER');
$MENTA_PASSWORD = (getenv('MENTA_PASSWORD') == "") ? "wCKNzbMzRz8F79x" : getenv('MENTA_PASSWORD');
$MENTA_API_URL = (getenv('MENTA_API_URL') == "") ? 'https://api.menta.global/api/' : getenv('MENTA_API_URL');

define('TOKEN_CACHE_FILE', __DIR__ . '/token.cache.json');

// =========================================================================
// PARÁMETROS
// =========================================================================

$fechaSQL = null;

if (isset($argv[1]) && !empty($argv[1])) {
    $fechaParam = $argv[1];
    
    // Validar formato ddmmyy
    if (preg_match('/^(\d{2})(\d{2})(\d{2})$/', $fechaParam, $matches)) {
        $dia = $matches[1];
        $mes = $matches[2];
        $anio = '20' . $matches[3];
        
        if (checkdate((int)$mes, (int)$dia, (int)$anio)) {
            $fechaSQL = "$anio-$mes-$dia";
            echo "=== FILTRO DE FECHA: $fechaSQL ===\n\n";
        } else {
            die("Error: Fecha inválida. Use formato ddmmyy\n");
        }
    } else {
        die("Error: Formato de fecha incorrecto. Use formato ddmmyy (ej: 190126)\n");
    }
}

// =========================================================================
// CONEXIÓN A BASE DE DATOS
// =========================================================================

try {
    $pdo = new PDO(
        "mysql:host=" . DB_SERVER . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASSWORD,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "✓ Conectado a la base de datos\n\n";
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage() . "\n");
}

// =========================================================================
// FUNCIONES
// =========================================================================

function obtenerToken($client, $user, $password) {
    global $MENTA_API_URL;
    
    // Revisar caché
    if (file_exists(TOKEN_CACHE_FILE)) {
        $cacheData = json_decode(file_get_contents(TOKEN_CACHE_FILE), true);
        if (isset($cacheData['expires_at']) && $cacheData['expires_at'] > (time() + 60)) {
            echo "INFO: Usando token válido desde caché.\n";
            return $cacheData['access_token'];
        }
    }
    
    echo "INFO: Solicitando nuevo token...\n";
    
    $response = $client->post('v1/login', [
        'json' => [
            'user'     => $user,
            'password' => $password
        ]
    ]);
    
    if ($response->getStatusCode() === 200) {
        $data = json_decode($response->getBody(), true);
        $accessToken = $data['token']['access_token'];
        $expiresIn = $data['token']['expires_in'];
        
        file_put_contents(TOKEN_CACHE_FILE, json_encode([
            'access_token' => $accessToken,
            'expires_at'   => time() + $expiresIn
        ]));
        
        echo "✓ Token obtenido correctamente\n";
        return $accessToken;
    }
    
    throw new Exception("Error obteniendo token: " . $response->getStatusCode());
}

function buscarTransaccionesPorFecha($client, $token, $fechaStart, $fechaEnd) {
    $transacciones = [];
    $paginaActual = 0;
    $paginasTotales = 1;
    
    do {
        try {
            $response = $client->get('v2/transaction-reports', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token
                ],
                'query' => [
                    'page'  => $paginaActual,
                    'size'  => 10000,
                    'start' => $fechaStart,
                    'end'   => $fechaEnd
                ]
            ]);
            
            if ($response->getStatusCode() === 200) {
                $data = json_decode($response->getBody(), true);
                $transacciones = array_merge($transacciones, $data['content']);
                $paginasTotales = $data['total_pages'];
                $paginaActual++;
            }
        } catch (Exception $e) {
            echo "Error en página $paginaActual: " . $e->getMessage() . "\n";
            break;
        }
    } while ($paginaActual < $paginasTotales);
    
    return $transacciones;
}

/**
 * Convierte el payment_method de la API al formato de la BD
 */
function convertirMetodoPago($paymentMethod) {
    switch ($paymentMethod) {
        case 'CREDIT':
            return 'CR';
        case 'DEBIT':
            return 'DE';
        case 'QR':
            return 'QR';
        case 'PREPAID':
            return 'PR';
        default:
            return null;
    }
}

// =========================================================================
// PROCESO PRINCIPAL
// =========================================================================

echo "=== ACTUALIZANDO TRANSACCIONES DESDE API MENTA ===\n\n";

// Obtener transacciones que les falta algún dato
$query = "SELECT nrotransaccion, fecha, serial_number, metodopagoOriginal, marca 
          FROM transacciones 
          WHERE (serial_number IS NULL OR serial_number = '' 
                 OR metodopagoOriginal IS NULL OR metodopagoOriginal = ''
                 OR marca IS NULL OR marca = '')";
if ($fechaSQL !== null) {
    $query .= " AND DATE(fecha) = '$fechaSQL'";
}
$query .= " ORDER BY fecha DESC";

$stmt = $pdo->query($query);
$transaccionesSinDatos = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($transaccionesSinDatos);

echo "Transacciones a actualizar: $total\n\n";

if ($total === 0) {
    echo "✓ Todas las transacciones ya tienen todos los datos\n";
    exit(0);
}

// Inicializar cliente Guzzle
$client = new Client([
    'base_uri' => $MENTA_API_URL,
    'timeout'  => 30.0,
]);

// Obtener token
try {
    $token = obtenerToken($client, $MENTA_USER, $MENTA_PASSWORD);
} catch (Exception $e) {
    die("Error de autenticación: " . $e->getMessage() . "\n");
}

echo "\n";

// Preparar update (todos los campos)
$updateStmt = $pdo->prepare("UPDATE transacciones 
    SET serial_number = :serial, 
        metodopagoOriginal = :metodopago,
        marca = :marca 
    WHERE nrotransaccion = :nrotx");

// Agrupar por fecha para hacer menos llamadas a la API
$porFecha = [];
foreach ($transaccionesSinDatos as $tx) {
    $fecha = substr($tx['fecha'], 0, 10); // YYYY-MM-DD
    if (!isset($porFecha[$fecha])) {
        $porFecha[$fecha] = [];
    }
    $porFecha[$fecha][$tx['nrotransaccion']] = [
        'serial_number' => $tx['serial_number'],
        'metodopagoOriginal' => $tx['metodopagoOriginal'],
        'marca' => $tx['marca']
    ];
}

$actualizados = 0;
$noEncontrados = 0;
$conteoMarcas = ['VISA' => 0, 'MASTERCARD' => 0, 'AMEX' => 0, 'CABAL' => 0, 'OTRO' => 0];

foreach ($porFecha as $fecha => $nroTransacciones) {
    echo "--- Procesando fecha: $fecha (" . count($nroTransacciones) . " transacciones) ---\n";
    
    // Buscar todas las transacciones de esa fecha en la API
    $fechaStart = $fecha . 'T00:00:00Z';
    $fechaEnd = $fecha . 'T23:59:59Z';
    
    $txAPI = buscarTransaccionesPorFecha($client, $token, $fechaStart, $fechaEnd);
    echo "    Transacciones encontradas en API: " . count($txAPI) . "\n";
    
    // Crear mapa operation_number => datos de API
    $mapaAPI = [];
    foreach ($txAPI as $tx) {
        $opNum = $tx['operation_number'] ?? null;
        if ($opNum) {
            $cardInfo = $tx['operation_detail']['card'] ?? [];
            $mapaAPI[$opNum] = [
                'serial_number' => $tx['serial_number'] ?? null,
                'payment_method' => $tx['payment_method'] ?? null,
                'card_brand' => $cardInfo['card_brand'] ?? null
            ];
        }
    }
    
    // Actualizar las transacciones
    foreach ($nroTransacciones as $nroTx => $datosActuales) {
        if (isset($mapaAPI[$nroTx])) {
            $datosAPI = $mapaAPI[$nroTx];
            
            // Usar el valor actual si ya existe, sino el de la API
            $serial = !empty($datosActuales['serial_number']) 
                ? $datosActuales['serial_number'] 
                : $datosAPI['serial_number'];
            
            $metodoPago = !empty($datosActuales['metodopagoOriginal']) 
                ? $datosActuales['metodopagoOriginal'] 
                : convertirMetodoPago($datosAPI['payment_method']);
            
            $marca = !empty($datosActuales['marca']) 
                ? $datosActuales['marca'] 
                : $datosAPI['card_brand'];
            
            $updateStmt->execute([
                ':serial' => $serial,
                ':metodopago' => $metodoPago,
                ':marca' => $marca,
                ':nrotx' => $nroTx
            ]);
            $actualizados++;
            
            // Contar por marca
            if (in_array($marca, ['VISA', 'MASTERCARD', 'AMEX', 'CABAL'])) {
                $conteoMarcas[$marca]++;
            } elseif (!empty($marca)) {
                $conteoMarcas['OTRO']++;
            }
            
            echo "    ✓ TX $nroTx -> Serial: $serial, Pago: $metodoPago, Marca: $marca\n";
        } else {
            $noEncontrados++;
            echo "    ✗ TX $nroTx no encontrada en API\n";
        }
    }
    
    echo "\n";
}

// =========================================================================
// RESUMEN
// =========================================================================

echo "=== PROCESO COMPLETADO ===\n";
echo "Actualizados: $actualizados\n";
echo "No encontrados: $noEncontrados\n";
echo "Total procesados: $total\n\n";

echo "=== CONTEO POR MARCA ===\n";
echo "VISA: " . $conteoMarcas['VISA'] . "\n";
echo "MASTERCARD: " . $conteoMarcas['MASTERCARD'] . "\n";
echo "AMEX: " . $conteoMarcas['AMEX'] . "\n";
echo "CABAL: " . $conteoMarcas['CABAL'] . "\n";
echo "OTRO: " . $conteoMarcas['OTRO'] . "\n";

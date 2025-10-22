<?php

// Cargar las dependencias de Composer (Guzzle, DotEnv)
require __DIR__ . '/../vendor/autoload.php';

// Cargar las variables de entorno (desde el archivo .env)
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__); 
$dotenv->load();

$fechaProceso = $argv[1]; // 'aaaammdd'
if (!preg_match('/^\d{8}$/', $fechaProceso)) {
    echo "ERROR: El formato de fecha debe ser 'aaaammdd'.\n";
    die();
}

echo "INFO: Procesando transacciones para la fecha: $fechaProceso\n";

// Convertimos la fecha a los formatos 'start' y 'end' que pide la API
try {
    // Creamos un objeto DateTime desde el string 'aaaammdd'
    $dt = DateTime::createFromFormat('Ymd', $fechaProceso);
    if ($dt === false) { throw new Exception("Formato de fecha inválido."); }

    // Creamos el string 'start': 2025-10-22T00:00:00Z (en UTC 'Z')
    $fechaStart = $dt->setTime(0, 0, 0)
                     ->setTimezone(new DateTimeZone('UTC'))
                     ->format('Y-m-d\TH:i:s\Z');

    // Creamos el string 'end': 2025-10-22T23:59:59Z (en UTC 'Z')
    $fechaEnd = $dt->setTime(23, 59, 59)
                   ->setTimezone(new DateTimeZone('UTC'))
                   ->format('Y-m-d\TH:i:s\Z');
                     
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
            return; // Salimos de la función, ya tenemos token
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
            
            // --- LÍNEA DE DEBUG ---
            echo "DEBUG: Respuesta completa de la API:\n";
            var_dump($data);
            echo "\n";
            // --- FIN DEBUG ---

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
 */

// Definimos la clase que representará nuestros datos
class Transaccion {
    public int $id;
    public float $monto;
    public string $clienteNombre;
    public string $estadoOriginal;
    
    // Método "Factory" para crear un objeto desde el array de la API
    public static function fromArray(array $data): self {
        $tx = new self();
        $tx->id = $data['id'];
        $tx->monto = (float) $data['monto'];
        $tx->clienteNombre = $data['cliente']; // Mapeo de 'cliente' a 'clienteNombre'
        $tx->estadoOriginal = $data['estado'];
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
}


/**
 * =========================================================================
 * FASE 4: TRANSFORMACIÓN
 * =========================================================================
 * Objetivo: Aplicar lógica de negocio a nuestros objetos.
 * (Ej. calcular comisiones, limpiar datos, etc.)
 */
function fase4_transformacion() {
    global $transaccionesMapeadas;
    
    echo "\n--- FASE 4: Transformación de Datos ---\n";
    
    // Aquí podrías aplicar lógica de negocio
    // foreach ($transaccionesMapeadas as $tx) {
    //    if ($tx->estadoOriginal === 'APROBADA') {
    //        // ...
    //    }
    // }
    
    echo "INFO: Transformaciones aplicadas (simulado).\n";
}


/**
 * =========================================================================
 * FASE 5: INSERCIÓN EN LA DB
 * =========================================================================
 * Objetivo: Guardar los objetos transformados en nuestra base de datos
 * usando PDO y transacciones.
 */
function fase5_insercion_db() {
    global $transaccionesMapeadas;
    
    echo "\n--- FASE 5: Inserción en Base de Datos ---\n";

    // Aquí conectarías a tu DB (ej. con PDO)
    // $dsn = 'mysql:host=localhost;dbname=mi_db;charset=utf8mb4';
    // $usuario_db = 'root';
    // $pass_db = 'tu_pass';
    // $pdo = new PDO($dsn, $usuario_db, $pass_db);
    
    // $pdo->beginTransaction();
    // $stmt = $pdo->prepare("INSERT INTO transacciones (api_id, ...) VALUES (?, ...)");
    // foreach ($transaccionesMapeadas as $tx) {
    //    $stmt->execute([ $tx->id, ... ]);
    // }
    // $pdo->commit();
    
    echo "EXITO: " . count($transaccionesMapeadas) . " registros insertados en DB (simulado).\n";
}


// --- Ejecución del Proceso ---

try {
    fase1_autenticacion();
    fase2_peticion_transacciones();
    fase3_mapeo_clases();
    fase4_transformacion();
    fase5_insercion_db();

    echo "\n============================================\n";
    echo "== PROCESO COMPLETADO EXITOSAMENTE ==\n";
    echo "============================================\n";

} catch (Exception $e) {
    echo "\n!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!\n";
    echo "== ERROR INESPERADO DURANTE LA EJECUCION ==\n";
    echo "Mensaje: " . $e->getMessage() . "\n";
    echo "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!\n";
}

?>
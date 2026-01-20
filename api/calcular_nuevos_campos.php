<?php

/**
 * =========================================================================
 * SCRIPT: Cálculo de Campos - Ahorro CredMoura
 * =========================================================================
 * 
 * CAMPOS CALCULADOS:
 *   1. beneficiocredmoura = PrecioVenta * 0.7%
 *   2. ahorrosplit = PrecioVenta * (variable según split + 0.4%)
 *   3. ahorrocredmoura = beneficio + ahorro
 *   4. costofinanciero = PrecioVenta * (8.1% para 3 cuotas, 15.13% para 6 cuotas)
 *   5. aranceltarjeta = API + 0.2%
 *   6. totalneto = PrecioVenta - CostoFin - Arancel - IVA - OtrosImp + Beneficio
 * 
 * USO: 
 *   php calcular_nuevos_campos.php              -> Todas las transacciones
 *   php calcular_nuevos_campos.php 140126       -> Solo fecha 14/01/2026
 * 
 * @version: 4.1
 * @fecha: 20 de Enero de 2026
 * =========================================================================
 */

// =========================================================================
// CONFIGURACIÓN
// =========================================================================

require_once __DIR__ . '/constants.php';

// Constantes de cálculo
const BENEFICIO_CREDMOURA = 0.007;    // 0.7%

// Ahorro Split: variable según split + 0.4% fijo
const AHORRO_SPLIT_VARIABLE = [
    0  => 0.012,    // 0-100: 1.2%
    30 => 0.0084,   // 30-70: 0.84%
    40 => 0.0072,   // 40-60: 0.72%
    50 => 0.006     // 50-50: 0.60%
];
const AHORRO_SPLIT_FIJO = 0.004;  // 0.4%

// Costo Financiero - Tasa MiPyme
const COSTO_FINANCIERO = [
    3 => 0.081,     // 3 cuotas: 8.10%
    6 => 0.1513     // 6 cuotas: 15.13%
];

const IVA_GENERAL = 0.21;           // 21%
const IVA_COSTO_FINANCIERO = 0.105; // 10.5%

// Arancel Tarjeta: API + 0.2% adicional
const ARANCEL_CREDITO = 0.02;       // 2% crédito
const ARANCEL_DEBITO = 0.01;        // 1% débito
const ARANCEL_AMEX = 0.03;          // 3% AMEX
const ARANCEL_ADICIONAL = 0.002;    // 0.2% adicional

const OTROS_IMPUESTOS_RATE = 0.006; // 0.6% 

// =========================================================================
// DETECTAR MODO (CLI o WEB)
// =========================================================================

$isCLI = (php_sapi_name() === 'cli');
$nl = $isCLI ? "\n" : "<br>\n";

if (!$isCLI) {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><title>Calcular Campos - Ahorro CredMoura</title>";
    echo "<style>body{font-family:monospace;background:#1a1a2e;color:#eee;padding:20px;} ";
    echo ".ok{color:#0f0;} .err{color:#f00;} .info{color:#0af;} pre{white-space:pre-wrap;}</style>";
    echo "</head><body><pre>";
}

// =========================================================================
// PARÁMETRO DE FECHA (OPCIONAL)
// =========================================================================

$fechaFiltro = null;
$fechaSQL = null;

// Obtener parámetro de fecha (CLI o GET)
$fechaParam = null;
if ($isCLI && isset($argv[1]) && !empty($argv[1])) {
    $fechaParam = $argv[1];
} elseif (!$isCLI && isset($_GET['fecha']) && !empty($_GET['fecha'])) {
    $fechaParam = $_GET['fecha'];
}

if ($fechaParam !== null) {
    $fechaParam = $fechaParam;

    // Validar formato ddmmyy (6 dígitos)
    if (preg_match('/^(\d{2})(\d{2})(\d{2})$/', $fechaParam, $matches)) {
        $dia = $matches[1];
        $mes = $matches[2];
        $anio = '20' . $matches[3]; // Asumimos siglo 21

        // Validar fecha válida
        if (checkdate((int)$mes, (int)$dia, (int)$anio)) {
            $fechaFiltro = "$dia/$mes/$anio";
            $fechaSQL = "$anio-$mes-$dia";
            echo "=== FILTRO DE FECHA ===$nl";
            echo "Procesando solo transacciones del: $fechaFiltro$nl$nl";
        } else {
            $msg = "✗ Error: Fecha inválida '$fechaParam'. Use formato ddmmyy (ej: 140126 para 14/01/2026)";
            die($isCLI ? "$msg\n" : "<span class='err'>$msg</span></pre></body></html>");
        }
    } else {
        $msg = "✗ Error: Formato de fecha incorrecto '$fechaParam'. Use formato ddmmyy (ej: 140126 para 14/01/2026)";
        die($isCLI ? "$msg\n" : "<span class='err'>$msg</span></pre></body></html>");
    }
} else {
    echo "=== MODO: TODAS LAS TRANSACCIONES ===$nl$nl";
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
    echo ($isCLI ? "✓" : "<span class='ok'>✓</span>") . " Conectado a la base de datos$nl$nl";
} catch (PDOException $e) {
    $msg = "✗ Error de conexión: " . $e->getMessage();
    die($isCLI ? "$msg\n" : "<span class='err'>$msg</span></pre></body></html>");
}

// =========================================================================
// CREAR TABLA SI NO EXISTE
// =========================================================================

echo "=== VERIFICANDO ESTRUCTURA DE TABLA ===$nl";

$createTable = "CREATE TABLE IF NOT EXISTS calculos_ahorro_credmoura (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nrotransaccion BIGINT NOT NULL,
    importecheque DECIMAL(15,2) NOT NULL COMMENT 'Precio de venta',
    metodopagoOriginal VARCHAR(10) NULL COMMENT 'CR, DE, QR, PR',
    metodopago VARCHAR(10) NULL COMMENT 'CR, DE, QR',
    cuotas INT NULL COMMENT 'Cantidad de cuotas',
    porcentajepdv INT NULL COMMENT 'Porcentaje split del PDV',
    beneficiocredmoura DECIMAL(15,2) NULL COMMENT '0.7% del precio de venta',
    ahorrosplit DECIMAL(15,2) NULL COMMENT 'Según split + 0.04% IVA',
    ahorrocredmoura DECIMAL(15,2) NULL COMMENT 'Beneficio + Ahorro Split',
    costofinanciero DECIMAL(15,2) NULL COMMENT '8.10% (3 cuotas) o 15.13% (6 cuotas)',
    aranceltarjeta DECIMAL(15,2) NULL COMMENT '2% crédito, 1% débito/QR/prepaga',
    iva_total DECIMAL(15,2) NULL COMMENT 'IVA total de liquidacionesdetalle',
    otros_impuestos DECIMAL(15,2) NULL COMMENT 'Otros impuestos de liquidacionesdetalle',
    totalneto DECIMAL(15,2) NULL COMMENT 'precio - costo - arancel - iva - otros + beneficio',
    fecha_calculo DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY idx_nrotransaccion (nrotransaccion),
    INDEX idx_fecha (fecha_calculo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Cálculos de ahorro CredMoura por transacción';
";

try {
    $pdo->exec($createTable);
    echo ($isCLI ? "✓" : "<span class='ok'>✓</span>") . " Tabla 'calculos_ahorro_credmoura' verificada/creada$nl";
} catch (PDOException $e) {
    $msg = "✗ Error creando tabla: " . $e->getMessage();
    die($isCLI ? "$msg\n" : "<span class='err'>$msg</span></pre></body></html>");
}

// =========================================================================
// OBTENER TRANSACCIONES A PROCESAR
// =========================================================================

echo "$nl=== OBTENIENDO DATOS ===$nl";

$query = "SELECT 
        t.nrotransaccion,
        t.importecheque,
        t.importeprimervenc,
        t.metodopago,
        t.metodopagoOriginal,
        t.canal,
        t.fecha,
        t.idpdv,
        t.marca,
        COALESCE(s.porcentajepdv, 30) as porcentajepdv,
        IFNULL(ld.aranceltarjeta, 0) as aranceltarjeta_api,
        IFNULL(ld.ivacomisionpd + ld.ivacomisionprontopago + ld.ivadescuentocuotas + ld.ivacostoacreditacion + ld.ivaaranceltarjeta + ld.IVAcostomipyme, 0) as iva_total,
        IFNULL(ld.sirtac + ld.otrosimpuestos, 0) as otros_impuestos,
        IFNULL(ld.comisionpd + ld.comisionprontopago + ld.costoacreditacion + ld.costomipyme, 0) as comisiones_total
    FROM transacciones t
    LEFT JOIN (
        SELECT s1.idpdv, s1.porcentajepdv
        FROM splits s1
        INNER JOIN (
            SELECT idpdv, MAX(fecha) as max_fecha
            FROM splits
            WHERE borrado_en IS NULL
            GROUP BY idpdv
        ) s2 ON s1.idpdv = s2.idpdv AND s1.fecha = s2.max_fecha
        WHERE s1.borrado_en IS NULL
    ) s ON t.idpdv = s.idpdv
    LEFT JOIN liquidacionesdetalle ld ON ld.nrotransaccion = t.nrotransaccion
    WHERE ld.nrotransaccion IS NOT NULL
";

// Agregar filtro de fecha si se especificó
if ($fechaSQL !== null) {
    $query .= " AND DATE(t.fecha) = '$fechaSQL'";
}

$stmt = $pdo->query($query);
$transacciones = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total = count($transacciones);

echo "Total de transacciones a procesar: $total$nl$nl";

if ($total === 0) {
    $msg = "No hay transacciones para procesar.";
    if (!$isCLI) {
        echo "<span class='info'>$msg</span></pre></body></html>";
        exit;
    }
    die("$msg\n");
}

// =========================================================================
// FUNCIONES DE CÁLCULO
// =========================================================================

function extraerCuotas($canal)
{
    $cuotas = (int)$canal;
    return ($cuotas === 3 || $cuotas === 6) ? $cuotas : 0;
}

function calcularBeneficioCredMoura($precioVenta)
{
    return round($precioVenta * BENEFICIO_CREDMOURA, 2);
}

function calcularAhorroSplit($precioVenta, $porcentajePDV)
{
    $porcentajeVariable = AHORRO_SPLIT_VARIABLE[(int)$porcentajePDV] ?? 0;
    $porcentajeTotal = $porcentajeVariable + AHORRO_SPLIT_FIJO;
    return round($precioVenta * $porcentajeTotal, 2);
}

function calcularCostoFinanciero($precioVenta, $cuotas)
{
    $porcentaje = COSTO_FINANCIERO[$cuotas] ?? 0;
    return round($precioVenta * $porcentaje, 2);
}

/**
 * Calcular IVA
 * 
 * Fórmulas:
 *   - Cuotas >= 3: IVA = (PrecioVenta * arancelRate * 21%) + (PrecioVenta * costoFinRate * 10.5%)
 *   - Cuotas < 3:  IVA = PrecioVenta * arancelRate * 21%
 */
function calcularIVAExcel($precioVenta, $cuotas, $metodoPago, $arancelAPI = 0)
{
    // Rate del arancel desde la API
    if ($precioVenta > 0 && $arancelAPI > 0) {
        $arancelBaseRate = $arancelAPI / $precioVenta;
    } elseif ($metodoPago === 'DE' || $metodoPago === 'QR') {
        $arancelBaseRate = 0.008;  // 0.8% débito
    } else {
        $arancelBaseRate = 0.018;  // 1.8% crédito
    }
    
    if ($cuotas >= 3) {
        $costoFinRate = COSTO_FINANCIERO[$cuotas] ?? 0;
        $ivaArancel = $precioVenta * $arancelBaseRate * IVA_GENERAL;
        $ivaCostoFin = $precioVenta * $costoFinRate * IVA_COSTO_FINANCIERO;
        return round($ivaArancel + $ivaCostoFin, 2);
    } else {
        return round($precioVenta * $arancelBaseRate * IVA_GENERAL, 2);
    }
}

function calcularOtrosImpuestosExcel($precioVenta)
{
    return round($precioVenta * OTROS_IMPUESTOS_RATE, 2);
}

// =========================================================================
// PROCESAR Y GUARDAR EN NUEVA TABLA
// =========================================================================

echo "=== PROCESANDO ===$nl";

$insertQuery = $pdo->prepare("INSERT INTO calculos_ahorro_credmoura (
        nrotransaccion, importecheque, metodopagoOriginal, metodopago, cuotas, porcentajepdv,
        beneficiocredmoura, ahorrosplit, ahorrocredmoura, costofinanciero, aranceltarjeta,
        iva_total, otros_impuestos, totalneto
    ) VALUES (
        :nrotransaccion, :importecheque, :metodopagoOriginal, :metodopago, :cuotas, :porcentajepdv,
        :beneficio, :ahorro, :total, :costo, :arancel,
        :iva_total, :otros_impuestos, :totalneto
    )
    ON DUPLICATE KEY UPDATE
        importecheque = VALUES(importecheque),
        metodopagoOriginal = VALUES(metodopagoOriginal),
        metodopago = VALUES(metodopago),
        cuotas = VALUES(cuotas),
        porcentajepdv = VALUES(porcentajepdv),
        beneficiocredmoura = VALUES(beneficiocredmoura),
        ahorrosplit = VALUES(ahorrosplit),
        ahorrocredmoura = VALUES(ahorrocredmoura),
        costofinanciero = VALUES(costofinanciero),
        aranceltarjeta = VALUES(aranceltarjeta),
        iva_total = VALUES(iva_total),
        otros_impuestos = VALUES(otros_impuestos),
        totalneto = VALUES(totalneto),
        fecha_calculo = CURRENT_TIMESTAMP
");

$exitosos = 0;
$errores = 0;

foreach ($transacciones as $i => $tx) {
    try {
        $precioVenta = (float)$tx['importecheque'];
        $porcentajePDV = (int)$tx['porcentajepdv'];
        $cuotas = (int)$tx['canal'];
        $cuotasParaCosto = extraerCuotas($tx['canal']);
        $metodoPago = $tx['metodopagoOriginal'] == "" ? $tx['metodopago'] : $tx['metodopagoOriginal']; // Usar método original si está disponible

        // Cálculos
        $beneficio = calcularBeneficioCredMoura($precioVenta);
        $ahorro = calcularAhorroSplit($precioVenta, $porcentajePDV);
        $totalAhorro = round($beneficio + $ahorro, 2);
        $costo = calcularCostoFinanciero($precioVenta, $cuotasParaCosto);
        
        $arancelAPI = (float)$tx['aranceltarjeta_api'];
        $arancelAdicional = $precioVenta * ARANCEL_ADICIONAL;
        $arancel = $arancelAPI + $arancelAdicional;

        $ivaTotal = calcularIVAExcel($precioVenta, $cuotas, $metodoPago, $arancelAPI);
        $otrosImpuestos = calcularOtrosImpuestosExcel($precioVenta);

        // TotalNeto = PrecioVenta - CostoFin - Arancel - IVA - OtrosImp + Beneficio
        $totalNeto = round($precioVenta - $costo - $arancel - $ivaTotal - $otrosImpuestos + $beneficio, 2);

        // Insertar o actualizar
        $insertQuery->execute([
            ':nrotransaccion' => $tx['nrotransaccion'],
            ':importecheque' => $precioVenta,
            ':metodopagoOriginal' => $tx['metodopagoOriginal'] ?? $tx['metodopago'],
            ':metodopago' => $metodoPago,
            ':cuotas' => $cuotas,
            ':porcentajepdv' => $porcentajePDV,
            ':beneficio' => $beneficio,
            ':ahorro' => $ahorro,
            ':total' => $totalAhorro,
            ':costo' => $costo,
            ':arancel' => $arancel,
            ':iva_total' => $ivaTotal,
            ':otros_impuestos' => $otrosImpuestos,
            ':totalneto' => $totalNeto
        ]);

        $exitosos++;

        if ($exitosos % 100 === 0) {
            $pct = round(($exitosos / $total) * 100, 1);
            echo "Procesados: $exitosos / $total ($pct%)$nl";
        }
    } catch (Exception $e) {
        echo ($isCLI ? "✗" : "<span class='err'>✗</span>") . " Error TX {$tx['nrotransaccion']}: " . $e->getMessage() . $nl;
        $errores++;
    }
}

// =========================================================================
// RESUMEN
// =========================================================================

echo "$nl=== PROCESO COMPLETADO ===$nl";
echo "Exitosos: $exitosos$nl";
echo "Errores: $errores$nl";

// Mostrar ejemplos
echo "$nl=== EJEMPLOS RECIENTES ===$nl";
echo str_repeat("-", 155) . $nl;
printf(
    "%-12s | %-10s | %-6s | %-6s | %-10s | %-10s | %-10s | %-10s | %-12s$nl",
    "Transacción",
    "Importe",
    "Pago",
    "Cuotas",
    "Costo Fin.",
    "Arancel",
    "IVA",
    "Otros Imp.",
    "TOTAL NETO"
);
echo str_repeat("-", 155) . $nl;

$queryEjemplos = "SELECT * FROM calculos_ahorro_credmoura
    ORDER BY fecha_calculo DESC
    LIMIT 10
";
$ejemplos = $pdo->query($queryEjemplos)->fetchAll(PDO::FETCH_ASSOC);

foreach ($ejemplos as $ej) {
    printf(
        "%-12d | $%-9.2f | %-6s | %-6d | $%-9.2f | $%-9.2f | $%-9.2f | $%-9.2f | $%-11.2f$nl",
        $ej['nrotransaccion'],
        $ej['importecheque'],
        $ej['metodopago'],
        $ej['cuotas'],
        $ej['costofinanciero'],
        $ej['aranceltarjeta'],
        $ej['iva_total'],
        $ej['otros_impuestos'],
        $ej['totalneto']
    );
}

echo str_repeat("-", 155) . $nl;

// Resumen por método de pago
echo "$nl=== RESUMEN POR MÉTODO DE PAGO ===$nl";
$queryResumen = "SELECT 
        metodopagoOriginal,
        metodopago,
        COUNT(*) as cantidad,
        SUM(importecheque) as total_ventas,
        SUM(costofinanciero) as total_costo,
        SUM(aranceltarjeta) as total_arancel,
        SUM(iva_total) as total_iva,
        SUM(otros_impuestos) as total_otros,
        SUM(beneficiocredmoura) as total_beneficio,
        SUM(totalneto) as total_neto
    FROM calculos_ahorro_credmoura
    GROUP BY metodopagoOriginal, metodopago
";
$resumen = $pdo->query($queryResumen)->fetchAll(PDO::FETCH_ASSOC);

echo str_repeat("-", 140) . $nl;
printf(
    "%-6s | %-8s | %-15s | %-12s | %-12s | %-12s | %-12s | %-12s | %-15s$nl",
    "Pago Original",
    "Pago",
    "Cant.",
    "Total Ventas",
    "Costo Fin.",
    "Arancel",
    "IVA",
    "Otros Imp.",
    "Beneficio",
    "TOTAL NETO"
);
echo str_repeat("-", 140) . $nl;

foreach ($resumen as $r) {
    printf(
        "%-6s | %-8s | %-15s | %-12s | %-12s | %-12s | %-12s | %-12s | %-15s$nl",
        $r['metodopagoOriginal'],
        $r['metodopago'],
        $r['cantidad'],
        $r['total_ventas'],
        $r['total_costo'],
        $r['total_arancel'],
        $r['total_iva'],
        $r['total_otros'],
        $r['total_beneficio'],
        $r['total_neto']
    );
}
echo str_repeat("-", 140) . $nl;

echo "$nl" . ($isCLI ? "✓" : "<span class='ok'>✓</span>") . " Listo! Datos guardados en tabla 'calculos_ahorro_credmoura'$nl";

if (!$isCLI) {
    echo "</pre></body></html>";
}

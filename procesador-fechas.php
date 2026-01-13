<?php
/**
 * Procesador de Múltiples Fechas
 * Este script ejecuta procesador_API_Menta.php para múltiples fechas
 */

require_once 'constants.php';

/**
 * Verifica si una fecha es día hábil
 */
function esDiaHabil(DateTime $fecha): bool {
    $diaSemana = (int)$fecha->format('N');
    if ($diaSemana >= 6) return false;
    
    $f_dmy = $fecha->format('dmy');
    return !in_array($f_dmy, FERIADOS, true);
}

function esDomingo(DateTime $fecha): bool {
    return (int)$fecha->format('N') != 7;
}

// Generar fechas desde 22 de octubre hasta hoy (solo días hábiles)
$fechaInicio = new DateTime('2025-12-31');
$fechaFin = new DateTime('2025-12-31'); // Hoy
$fechas = [];

$fechaActual = clone $fechaInicio;
while ($fechaActual <= $fechaFin) {
    if (esDomingo($fechaActual)) {
        $fechas[] = $fechaActual->format('dmy'); // formato ddmmaa
    }
    $fechaActual->modify('+1 day');
}

echo "============================================\n";
echo "PROCESADOR DE MÚLTIPLES FECHAS\n";
echo "============================================\n";
echo "Total de fechas a procesar: " . count($fechas) . "\n\n";

$exitosos = 0;
$fallidos = 0;
$resultados = [];

foreach ($fechas as $index => $fecha) {
    $numero = $index + 1;
    echo "[$numero/" . count($fechas) . "] Procesando fecha: $fecha\n";
    echo str_repeat('-', 50) . "\n";
    
    // Ejecutar el procesador para esta fecha
    // $comando = "php " . __DIR__ . "/procesador_API_Menta.php $fecha";
    // $output = [];
    // $returnCode = 0;
    
    // exec($comando, $output, $returnCode);
    
    // Mostrar resultado del procesador
    // if ($returnCode === 0) {
    //     echo "✓ procesador_API_Menta.php completado\n";
        
        // Ejecutar archive_generator.php
        $comandoArchive = "php " . __DIR__ . "/archive_generator.php $fecha";
        $outputArchive = [];
        $returnCodeArchive = 0;
        
        exec($comandoArchive, $outputArchive, $returnCodeArchive);
        
        if ($returnCodeArchive === 0) {
            echo "✓ archive_generator.php completado\n";
            echo "✓ ÉXITO: Fecha $fecha procesada correctamente\n";
            echo "output from archive_generator.php:\n" . implode("\n", $outputArchive) . "\n";
            $exitosos++;
            $resultados[$fecha] = 'ÉXITO';
        } else {
            echo "✗ ERROR en archive_generator.php (Código: $returnCodeArchive)\n";
            $fallidos++;
            $resultados[$fecha] = 'ERROR (archive_generator)';
        }
    // } else {
    //     echo "✗ ERROR: Falló el procesamiento de fecha $fecha (Código: $returnCode)\n";
    //     $fallidos++;
    //     $resultados[$fecha] = 'ERROR';
    // }
    
    echo "\n";
}

// Resumen final
echo "============================================\n";
echo "RESUMEN DEL PROCESAMIENTO\n";
echo "============================================\n";
echo "Total procesadas: " . count($fechas) . "\n";
echo "Exitosas: $exitosos\n";
echo "Fallidas: $fallidos\n\n";

echo "Detalle:\n";
foreach ($resultados as $fecha => $resultado) {
    $simbolo = ($resultado === 'ÉXITO') ? '✓' : '✗';
    echo "  $simbolo $fecha: $resultado\n";
}

echo "\n============================================\n";
echo "Proceso completado\n";
echo "============================================\n";

// Retornar código de salida
exit($fallidos > 0 ? 1 : 0);
?>
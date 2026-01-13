/**
 * =========================================================================
 * FUNCIÓN: insertarDetalleLiquidacion (VERSIÓN REFACTORIZADA 2.0)
 * =========================================================================
 * 
 * @descripcion: Inserta un registro en la tabla liquidacionesdetalle con 
 *               el nuevo esquema de beneficios "Ahorro Split" y beneficio 
 *               base actualizado de 0.5% a 0.7%
 * 
 * @fecha: 13 de Enero de 2026
 * @version: 2.0
 * @cambios: 
 *   - Beneficio Base: 0.5% → 0.7%
 *   - Nuevo cálculo: Ahorro Split (según porcentaje PDV)
 *   - Nuevas columnas: ahorrosplit, costofinanciero
 * 
 * @param PDO $dbConnection Conexión a la base de datos
 * @param array $datosBIND Array con datos de la transacción en formato BIND
 * 
 * @throws Exception Si falla la inserción en la base de datos
 * @return void
 * 
 * =========================================================================
 */

function insertarDetalleLiquidacion($dbConnection, $datosBIND) {
    
    
    // Consulta SQL de inserción (actualizada con nuevas columnas)
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
        beneficiocredmoura,
        costomipyme,
        IVAcostomipyme,
        ahorrosplit,
        costofinanciero
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
        :beneficiocredmoura,
        :costomipyme,
        :IVAcostomipyme,
        :ahorrosplit,
        :costofinanciero
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
    /* MENTA ya trae el importe Bruto correcto en gross_amount, no es necesario recalcular
    if($datosBIND['forma_pago'] == METODO_PAGO_BIND_CREDITO_CUOTAS){
        $importeBruto = recalcularImporteBruto($dbConnection, $importeBruto, $fechaLiquidacion, $cuotas);	
    }	
    */

    $porcentajes = obtenerPorcentajesDeducciones($dbConnection, $fechaLiquidacion);

    // --- INICIO: Lógica costomipyme (Req 3) ---
    // Leemos los RATES que vienen del archivo BIND
    $rate_costomipyme_from_bind = convertirImporteFormatoBINDANumerico($datosBIND['tax_financial_cost_rate']);
    $rate_iva_costomipyme_from_bind = convertirImporteFormatoBINDANumerico($datosBIND['tax_financial_cost_vat_rate']);
    // 1. Calculamos costomipyme (aplicando el rate del BIND al bruto)
    $costomipyme = $importeBrutoOriginal * $rate_costomipyme_from_bind / 100;

    // la siguiente funcion trunca los dos primeros decimales, ya que si no se hace asi y se suma luego con el IVA arrancel tarjeta que viene de Menta que redondea 
    // hacia arriba, al hacer lo mismo con el IVA incrementa en al menos 1 centavo toda la operacion.
    $IVAcostomipyme = floor( ($costomipyme * $rate_iva_costomipyme_from_bind / 100) * 100 ) / 100;

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
    
    $arancelTarjeta = convertirImporteFormatoBINDANumerico($datosBIND['tax_aranceltarjeta']);
    
    // =================================================================
    // CÁLCULO BENEFICIO BASE (ACTUALIZADO)
    // Req: Beneficio CredMoura Base = 0.7% (0.2% dif arancel + 0.5% subsidio)
    // Antes: 0.5% | Ahora: 0.7%
    // =================================================================
    $beneficioBase = $importeBruto * 0.007; // 0.007 es 0.7%

    if($datosBIND['forma_pago'] == METODO_PAGO_BIND_CREDITO_CUOTAS && ($cuotas == 3 || $cuotas == 6)) {
        
        $cftCliente = 0;
        if($cuotas == 3) {
            $cftCliente = $importeBrutoOriginal * $porcentajes[ID_CFT_CLIENTE_3_CUOTAS] / 100;
        } elseif($cuotas == 6) {
            $cftCliente = $importeBrutoOriginal * $porcentajes[ID_CFT_CLIENTE_6_CUOTAS] / 100;
        }
        
        // Fórmula: (CFT Cliente) - (Financial Cost) + (Beneficio Base)
        $beneficioCredMoura = ($cftCliente - $costomipyme) + $beneficioBase;			
    } else {
        // Si no es 3 o 6 cuotas, es solo el Beneficio Base
        $beneficioCredMoura = $beneficioBase;
    }
    
    // =================================================================
    // CÁLCULO AHORRO SPLIT (NUEVO REQUERIMIENTO)
    // Req: Calcular ahorro adicional según porcentaje PDV
    // =================================================================
    
    // Se obtiene el porcentaje de split que corresponde al PDV
    $porcentajePDV = obtenerPorcentajePDV($dbConnection, $datosBIND['numero_de_comercio'], $fechaLiquidacion);
    
    // Inicializar variable de Ahorro Split
    $ahorroSplit = 0.0;
    
    // Aplicar reglas de negocio según porcentaje PDV
    if ($porcentajePDV == 30) {
        // Caso 30-70 (30% PDV): 0.84% base + 0.04% IVA dif = 0.88% total
        $ahorroSplit = $importeBrutoOriginal * 0.0088;
        echo "INFO: Aplicando Ahorro Split 30-70 (0.88%) - TX: {$datosBIND['transaccion']}\n";
        
    } elseif ($porcentajePDV == 0) {
        // Caso 0-100 (0% PDV): 1.2% total
        $ahorroSplit = $importeBrutoOriginal * 0.012;
        echo "INFO: Aplicando Ahorro Split 0-100 (1.2%) - TX: {$datosBIND['transaccion']}\n";
        
    } elseif ($porcentajePDV == 40) {
        // Caso 40-60 (40% PDV): 0.72% total
        $ahorroSplit = $importeBrutoOriginal * 0.0072;
        echo "INFO: Aplicando Ahorro Split 40-60 (0.72%) - TX: {$datosBIND['transaccion']}\n";
        
    } elseif ($porcentajePDV == 50) {
        // Caso 50-50 (50% PDV): 0.6% total
        $ahorroSplit = $importeBrutoOriginal * 0.006;
        echo "INFO: Aplicando Ahorro Split 50-50 (0.6%) - TX: {$datosBIND['transaccion']}\n";
        
    } else {
        // Otros casos: $ahorroSplit permanece en 0
        echo "INFO: Sin Ahorro Split definido para PDV {$porcentajePDV}% - TX: {$datosBIND['transaccion']}\n";
    }
    
    // =================================================================
    // FIN CÁLCULO AHORRO SPLIT
    // =================================================================
    
    //A partir del porcentaje de ahorro del PDV se obtiene cual es el porcentaje de ahorro que le corresponde 
    $porcentajeAhorroSplit = obtenerPorcentajePorTipoOperacion($dbConnection, $fechaLiquidacion, $porcentajePDV);
    
    //Se utiliza el porcentaje de ahorro con el ID correspondiente
    $credMoura = $importeBruto * $porcentajeAhorroSplit / 100;
    
    $ivaArancelTarjeta = convertirImporteFormatoBINDANumerico($datosBIND['tax_aranceltarjeta_vat']);
    $ivaDescuentoCuotas = 0; //el IVA para el costo de financiacion de Moura es 0

    // =================================================================
    // BIND DE VALORES AL INSERT (ACTUALIZADO CON NUEVAS COLUMNAS)
    // =================================================================
    
    // Asignamos los valores a los parámetros
    $stmt->bindValue(':nrotransaccion', intval($datosBIND['transaccion']));
    $stmt->bindValue(':comisionpd', $comisionPD);
    $stmt->bindValue(':ivacomisionpd', $comisionPD * IVA);
    $stmt->bindValue(':subsidiomoura', $subsidioMoura);
    $stmt->bindValue(':ivasubsidiomoura', $subsidioMoura * IVA);
    $stmt->bindValue(':comisionprontopago', $comisionProntoPago);
    $stmt->bindValue(':ivacomisionprontopago', $comisionProntoPago * IVA);
    $stmt->bindValue(':descuentocuotas', $descuentoCuotas);
    $stmt->bindValue(':ivadescuentocuotas', $ivaDescuentoCuotas);
    $stmt->bindValue(':costoacreditacion', $costoAcreditacion);
    $stmt->bindValue(':ivacostoacreditacion', $costoAcreditacion * IVA);
    $stmt->bindValue(':aranceltarjeta', $arancelTarjeta);
    $stmt->bindValue(':ivaaranceltarjeta', $ivaArancelTarjeta);
    $stmt->bindValue(':credmoura', $credMoura);
    $stmt->bindValue(':sirtac', $importeBruto * PORCENTAJE_SIRTAC / 100); 
    $stmt->bindValue(':otrosimpuestos', $importeBruto * $porcentajes[ID_OTROS_IMPUESTOS] / 100);		
    $stmt->bindValue(':beneficiocredmoura', $beneficioCredMoura);
    $stmt->bindValue(':costomipyme', $costomipyme);
    $stmt->bindValue(':IVAcostomipyme', $IVAcostomipyme);
    
    // NUEVOS CAMPOS: Ahorro Split y Costo Financiero
    $stmt->bindValue(':ahorrosplit', $ahorroSplit);
    $stmt->bindValue(':costofinanciero', $costomipyme); // El costo financiero es el mismo que costomipyme (Tasa MiPyme)

    // Ejecutamos la consulta
    if ($stmt->execute()) {
        echo "Registro Detalle de Liquidacion insertado correctamente.\n";
    } else {
        echo  "Error al insertar registro: " . $stmt->errorInfo()[2];
        throw new Exception( "Error al insertar registro: " . $stmt->errorInfo()[2]);
    }
}

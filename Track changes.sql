Track changes

1. COSTO DE SERVICIO
INSERT INTO `porcentajes` (`idporcentaje`, `nombre`, `fecha`, `porcentaje`, `porcentajeiva`, `incluyeiva`, `tipooperacion`, `marca`) VALUES ('10', 'Comisión PD Crédito', '2025-10-27 00:00:00', '0', '21.00', '0', 'CREDMOURA', NULL)
INSERT INTO `porcentajes` (`idporcentaje`, `nombre`, `fecha`, `porcentaje`, `porcentajeiva`, `incluyeiva`, `tipooperacion`, `marca`) VALUES ('9', 'Comisión PD Débito', '2025-10-27 00:00:00', '0', '21.00', '0', 'CREDMOURA', NULL)
INSERT INTO `porcentajes` (`idporcentaje`, `nombre`, `fecha`, `porcentaje`, `porcentajeiva`, `incluyeiva`, `tipooperacion`, `marca`) VALUES ('11', 'Comisión PD QR', '2025-10-27 00:00:00', '0', '21.00', '0', 'CREDMOURA', NULL)

2. COSTO DE FINANCIACION
    comisionprontopago --> es 0
    descuentocuotas --> usa ID_CFT_CLIENTE_3_CUOTAS y ID_CFT_CLIENTE_6_CUOTAS
        Si no encontramos el campo, insertar estos registros en "porcentajes"
        INSERT INTO `porcentajes` (`idporcentaje`, `nombre`, `fecha`, `porcentaje`, `porcentajeiva`, `incluyeiva`, `tipooperacion`, `marca`) VALUES ('20', 'CftCliente 3 cuotas', '2025-10-27 00:00:00', '15.12', '21.00', '0', 'CREDMOURA', NULL)
        INSERT INTO `porcentajes` (`idporcentaje`, `nombre`, `fecha`, `porcentaje`, `porcentajeiva`, `incluyeiva`, `tipooperacion`, `marca`) VALUES ('21', 'CftCliente 6 cuotas', '2025-10-27 00:00:00', '24.73', '21.00', '0', 'CREDMOURA', NULL)

        /*Si encontramos el campo, la propuesta es:
        */

3. ARANCEL TARJETA
    Modificar "procesador_API_Menta" para bajar el campo tax_info -> tax_breakdown -> amount al campo "tax_aranceltarjeta" del BIND en la posicion X a Y para tax_code==ACQUIRER_TO_CUSTOMER_COMMISSION de tipo number
    Modificar "archivosdiarios" para levantar la posicion X a Y y dejarla en liquidacionesdetalle en el nuevo campo "tax_aranceltarjeta" de tipo number
    Reemplazar:
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
    Por:
       $arancelTarjeta = $datosBIND['tax_aranceltarjeta']


4. IVA
    Modificar "procesador_API_Menta" para bajar el campo tax_info -> tax_breakdown -> amount al campo "tax_ivaaranceltarjeta" del BIND en la posicion X a Y para tax_code==ACQUIRER_TO_CUSTOMER_COMMISSION_VAT_TAX 
        = Iva sobre arancel adquirente - columna Z del reporte excel
    Modificar "procesador_API_Menta" para bajar el campo tax_info -> tax_breakdown -> amount al campo "tax_ivacostofinanciero" del BIND en la posicion X a Y para tax_code==FINANCIAL_COST_VAT_TAX
        = Iva sobre descuento financiero por cuotas - columna X del reporte excel

    [ (costo de servicio + arancel tarjeta) * 21% ] + 21% del valor de cuota que te traes de menta ya calculado

    $comisionPD * 0,21 + $ivaaranceltarjeta + $ivadescuentocuotas (ojo que este ultimo es 21 , no 10,5)

    donde:
        $ivaaranceltarjeta == "tax_aranceltarjeta"
        $ivadescuentocuotas == "tax_costofinanciero"


5. BENEFICIO CREDMOURA

    Costo financiamiento  - costo financiero mipyme + 0.5 del monto bruto.

    Donde: 
        Costo Financiamiento 
            Variable ID_CFT_CLIENTE_3_CUOTAS y ID_CFT_CLIENTE_6_CUOTAS
        costo financiero mipyme
            Bajar el campo tax_info -> tax_breakdown -> amount -> "FINANCIAL_COST" de MENTA
            Usar los valores de relleno de la posicion X a Y al generar el archivo BIND ("procesador_API_Menta.php")
            Modificar "archivosdiarios" para pasar de la posicion X a Y del archivo del BIND al campo "nuevo" de la tabla "liquidacionesdetalle"

        *Costo financiero mipyme es el FINANCIA_COST de Menta.

    Eliminar $importeBruto * $porcentajes[ID_COMISION_PD_CREDITO]

    Modificar 
        else {
			$beneficioCredMoura = 0;
    Por:
        else {
			$beneficioCredMoura = 0.5 * $importeBrutoOriginal


    *CALL CON DIEGO
        Moura le dice a los puntos de venta, el costo de bateria es 100k pero si lo van a pagar en cuotas es 115000 (15%) y en 6 cuotas es 124000 (24%.)
        este porcentaje no viene de menta. 

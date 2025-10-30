#Scripts para PROD

INSERT INTO `porcentajes` (`idporcentaje`, `nombre`, `fecha`, `porcentaje`, `porcentajeiva`, `incluyeiva`, `tipooperacion`, `marca`) VALUES ('10', 'Comisión PD Crédito', '2025-10-21 00:00:00', '0', '21.00', '0', 'CREDMOURA', NULL)
INSERT INTO `porcentajes` (`idporcentaje`, `nombre`, `fecha`, `porcentaje`, `porcentajeiva`, `incluyeiva`, `tipooperacion`, `marca`) VALUES ('9', 'Comisión PD Débito', '2025-10-21 00:00:00', '0', '21.00', '0', 'CREDMOURA', NULL)
INSERT INTO `porcentajes` (`idporcentaje`, `nombre`, `fecha`, `porcentaje`, `porcentajeiva`, `incluyeiva`, `tipooperacion`, `marca`) VALUES ('11', 'Comisión PD QR', '2025-10-21 00:00:00', '0', '21.00', '0', 'CREDMOURA', NULL)

INSERT INTO `porcentajes` (`idporcentaje`, `nombre`, `fecha`, `porcentaje`, `porcentajeiva`, `incluyeiva`, `tipooperacion`, `marca`) VALUES ('20', 'CftCliente 3 cuotas', '2025-10-21 00:00:00', '15.12', '21.00', '0', 'CREDMOURA', NULL)
INSERT INTO `porcentajes` (`idporcentaje`, `nombre`, `fecha`, `porcentaje`, `porcentajeiva`, `incluyeiva`, `tipooperacion`, `marca`) VALUES ('21', 'CftCliente 6 cuotas', '2025-10-21 00:00:00', '24.73', '21.00', '0', 'CREDMOURA', NULL)

INSERT INTO `porcentajes` (`idporcentaje`, `nombre`, `fecha`, `porcentaje`, `porcentajeiva`, `incluyeiva`, `tipooperacion`, `marca`) VALUES ('19', 'Otros impuestos', '2025-10-21 00:00:00', '0.6', '0.00', '0', 'CREDMOURA', NULL)

DELETE FROM `liquidacionesdetalle` WHERE `liquidacionesdetalle`.`nrotransaccion` IN (852350050,517999539,504974250,948403273,690758213,63582208,530936366,519655436,580644304,779417958,365129535,271084842,647552387);
DELETE FROM `transacciones` WHERE `transacciones`.`nrotransaccion` IN (852350050,517999539,504974250,948403273,690758213,63582208,530936366,519655436,580644304,779417958,365129535,271084842,647552387) ;




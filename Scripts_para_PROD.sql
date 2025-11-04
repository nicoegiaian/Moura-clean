#Scripts para PROD

INSERT INTO `porcentajes` (`idporcentaje`, `nombre`, `fecha`, `porcentaje`, `porcentajeiva`, `incluyeiva`, `tipooperacion`, `marca`) VALUES ('10', 'Comisión PD Crédito', '2025-10-21 00:00:00', '0', '21.00', '0', 'CREDMOURA', NULL)
INSERT INTO `porcentajes` (`idporcentaje`, `nombre`, `fecha`, `porcentaje`, `porcentajeiva`, `incluyeiva`, `tipooperacion`, `marca`) VALUES ('9', 'Comisión PD Débito', '2025-10-21 00:00:00', '0', '21.00', '0', 'CREDMOURA', NULL)
INSERT INTO `porcentajes` (`idporcentaje`, `nombre`, `fecha`, `porcentaje`, `porcentajeiva`, `incluyeiva`, `tipooperacion`, `marca`) VALUES ('11', 'Comisión PD QR', '2025-10-21 00:00:00', '0', '21.00', '0', 'CREDMOURA', NULL)

INSERT INTO `porcentajes` (`idporcentaje`, `nombre`, `fecha`, `porcentaje`, `porcentajeiva`, `incluyeiva`, `tipooperacion`, `marca`) VALUES ('20', 'CftCliente 3 cuotas', '2025-10-21 00:00:00', '15.12', '21.00', '0', 'CREDMOURA', NULL)
INSERT INTO `porcentajes` (`idporcentaje`, `nombre`, `fecha`, `porcentaje`, `porcentajeiva`, `incluyeiva`, `tipooperacion`, `marca`) VALUES ('21', 'CftCliente 6 cuotas', '2025-10-21 00:00:00', '24.73', '21.00', '0', 'CREDMOURA', NULL)

INSERT INTO `porcentajes` (`idporcentaje`, `nombre`, `fecha`, `porcentaje`, `porcentajeiva`, `incluyeiva`, `tipooperacion`, `marca`) VALUES ('19', 'Otros impuestos', '2025-10-21 00:00:00', '0.6', '0.00', '0', 'CREDMOURA', NULL)


INSERT INTO `porcentajes` (`idporcentaje`, `nombre`, `fecha`, `porcentaje`, `porcentajeiva`, `incluyeiva`, `tipooperacion`, `marca`) VALUES ('12', 'Subsidio Moura', '2025-10-21 00:00:00', '3.5', '21.00', '0', 'CREDMOURA', NULL)

ALTER TABLE `liquidacionesdetalle`
ADD COLUMN `costomipyme` DECIMAL(15,2) NULL DEFAULT NULL AFTER `beneficiocredmoura`,
ADD COLUMN `IVAcostomipyme` DECIMAL(15,2) NULL DEFAULT NULL AFTER `costomipyme`;




DELETE FROM `liquidacionesdetalle` WHERE `liquidacionesdetalle`.`nrotransaccion` IN (852350050,517999539,504974250,948403273,690758213,63582208,530936366,580644304,779417958,365129535,271084842,647552387);
DELETE FROM `transacciones` WHERE `transacciones`.`nrotransaccion` IN (852350050,517999539,504974250,948403273,690758213,63582208,530936366,580644304,779417958,365129535,271084842,647552387) ;



ARCHIVOS:

Migrar LiquidacionGateway.php 
Migrar procesador_API_Menta.php
Migrar archivosdiarios.php

Backupear VENDOR de PROD
PASAR carpeta VENDOR DE TEST A PROD
No correr dos veces ARCHIVOSDIARIOS porque duplica un txt

    Correr ARCHIVOSDIARIOS 

Pasar el .env a la raiz de procesador_API_Menta

Revisar y backupear los archivos a mano que subio NICO

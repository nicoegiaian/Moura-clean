<?php
class LiquidacionGateway {

    private $db = null;

    public function __construct($db)
    {
        $this->db = $db;
    }

    public function findCalendario($idUnidadNegocio, $idPDV, $desde, $hasta)
    {
		$where = "";
		if ($idUnidadNegocio!="0") $where = " AND puntosdeventa.idunidadnegocio = $idUnidadNegocio";
		if ($idPDV!="0") $where .= " AND liquidaciones.idpdv = $idPDV";
		
		$statement = "
            SELECT 
				liquidaciones.fecha as Fecha, 
				COUNT(*) as Ventas, 
				SUM(importecheque) as Total, 
				SUM(credmoura) as 'Ahorro CredMoura',
                GROUP_CONCAT(DISTINCT liquidaciones.id SEPARATOR ',') as id 
			FROM 
				liquidaciones 
			INNER JOIN transacciones ON transacciones.idliquidacion = liquidaciones.id
            INNER JOIN liquidacionesdetalle ON liquidacionesdetalle.nrotransaccion = transacciones.nrotransaccion
			INNER JOIN puntosdeventa ON puntosdeventa.id = liquidaciones.idpdv	
			WHERE '$desde'<=liquidaciones.fecha AND liquidaciones.fecha<=DATE_ADD('$hasta', INTERVAL 1 DAY)
			$where
			GROUP BY liquidaciones.fecha
			ORDER BY liquidaciones.fecha DESC;
        ";
		
        try {
            $statement = $this->db->query($statement);
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            return $result;
        } catch (\PDOException $e) {
            exit($e->getMessage());
        }
    }
	
	public function findDetalle($idLiquidacion)
    {
		$statement = "
            SELECT 
				transacciones.nrotransaccion as 'N° Op.', 
				transacciones.fecha as 'Fecha Venta',
				transacciones.fecha as Hora,	
				importecheque as Venta,
				comisionpd as 'Costo de Servicio',
				(comisionprontopago + descuentocuotas) as 'Servicios Financieros',
				costoacreditacion as 'Costo Acreditación',
				aranceltarjeta as 'Arancel Tarjeta',
				(ivacomisionpd + ivacomisionprontopago + ivadescuentocuotas + ivacostoacreditacion + ivaaranceltarjeta) as 'IVA',
				(cirtag + otrosimpuestos) as 'Otros impuestos',	
				metodopago as 'Cond. Venta', 
				Canal as Cuotas,
				importeprimervenc as 'Sub Total',
				estadocheque as 'Split Moura',
				ROUND((((importeprimervenc)*(splits.porcentajepdv))/100), 2) as 'Cuenta Comercio',
				ROUND((((importeprimervenc)*(100 - splits.porcentajepdv))/100), 2) as 'CC Moura',
				credmoura as 'Ahorro CREDMOURA'				
			FROM 
				transacciones
            INNER JOIN liquidacionesdetalle ON transacciones.nrotransaccion = liquidacionesdetalle.nrotransaccion    
			INNER JOIN splits ON transacciones.idpdv = splits.idpdv 
			WHERE splits.fecha = (SELECT MAX(s2.fecha) FROM splits s2 WHERE s2.idpdv = transacciones.idpdv AND s2.fecha < transacciones.fecha AND s2.estatus_aprobacion = 'Aprobado' AND s2.borrado_en IS NULL)
			AND transacciones.idliquidacion IN($idLiquidacion)
			ORDER BY transacciones.fecha DESC;
        ";
		
        try {
            $statement = $this->db->query($statement);
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            return $result;
        } catch (\PDOException $e) {
            exit($e->getMessage());
        }
    }
	
	public function findDetalle2($idLiquidacion)
    {
		$statement = "
            SELECT 
				transacciones.nrotransaccion as 'N° Op.', 
				transacciones.fecha as 'Fecha Venta',
				liquidaciones.fecha as 'Fecha Liquidación',
				transacciones.fecha as Hora,	
				importecheque as Venta,
				comisionpd as 'Costo de Servicio',
				(comisionprontopago + descuentocuotas) as 'Servicios Financieros',
				costoacreditacion as 'Costo Acreditación',
				aranceltarjeta as 'Arancel Tarjeta',
				(ivacomisionpd + ivacomisionprontopago + ivadescuentocuotas + ivacostoacreditacion + ivaaranceltarjeta) as 'IVA',
				(sirtac + otrosimpuestos) as 'Otros impuestos',	
				metodopago as 'Cond. Venta', 
				Canal as Cuotas,
				importeprimervenc as 'Sub Total',
				estadocheque as 'Split Moura',
				ROUND((((importeprimervenc)*(splits.porcentajepdv))/100), 2) as 'Cuenta Comercio',
				ROUND((((importeprimervenc)*(100 - splits.porcentajepdv))/100), 2) as 'CC Moura',
				credmoura as 'Ahorro CREDMOURA',
				ROUND((((importeprimervenc)*(splits.porcentajepdv))/100), 2) + credmoura as Total
			FROM 
				transacciones
			INNER JOIN liquidaciones ON liquidaciones.id = transacciones.idliquidacion	
            INNER JOIN liquidacionesdetalle ON transacciones.nrotransaccion = liquidacionesdetalle.nrotransaccion    
			INNER JOIN splits ON transacciones.idpdv = splits.idpdv 
			WHERE splits.fecha = (SELECT MAX(s2.fecha) FROM splits s2 WHERE s2.idpdv = transacciones.idpdv AND s2.fecha < transacciones.fecha AND s2.estatus_aprobacion = 'Aprobado' AND s2.borrado_en IS NULL)
			AND transacciones.idliquidacion IN($idLiquidacion)
			ORDER BY transacciones.fecha DESC;
        ";
		
        try {
            $statement = $this->db->query($statement);
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            return $result;
        } catch (\PDOException $e) {
            exit($e->getMessage());
        }
    }
	
	public function findUnidadesNegocios()
    {
		$statement = "
            SELECT 
				id as Id, 
				nombre as Descripcion
			FROM 
				unidadesdenegocio
			ORDER BY nombre;
        ";
		
        try {
            $statement = $this->db->query($statement);
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            return $result;
        } catch (\PDOException $e) {
            exit($e->getMessage());
        }
    }
	
	public function findPDVs()
    {
		$statement = "
            SELECT 
				id as Id, 
				razonsocial as Descripcion
			FROM 
				puntosdeventa
			ORDER BY razonsocial;
        ";
		
        try {
            $statement = $this->db->query($statement);
            $result = $statement->fetchAll(\PDO::FETCH_ASSOC);
            return $result;
        } catch (\PDOException $e) {
            exit($e->getMessage());
        }
    }
}
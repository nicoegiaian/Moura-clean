-- =========================================================================
-- SCRIPT DE ACTUALIZACIÓN: Agregar columnas para Ahorro Split y Costo Financiero
-- Base de datos: CredMoura
-- Tabla: liquidacionesdetalle
-- Fecha: 2026-01-13
-- =========================================================================

-- Agregar columna ahorrosplit
ALTER TABLE liquidacionesdetalle
ADD COLUMN ahorrosplit DECIMAL(15,2) DEFAULT 0.00 NOT NULL
COMMENT 'Ahorro Split calculado según porcentaje PDV (30-70, 0-100, 40-60, 50-50)';

-- Agregar columna costofinanciero
ALTER TABLE liquidacionesdetalle
ADD COLUMN costofinanciero DECIMAL(15,2) DEFAULT 0.00 NOT NULL
COMMENT 'Costo financiero (Tasa MiPyme) - antes llamado costomipyme';

-- Verificar que las columnas se agregaron correctamente
SELECT 
    COLUMN_NAME,
    COLUMN_TYPE,
    COLUMN_DEFAULT,
    IS_NULLABLE,
    COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'liquidacionesdetalle'
    AND COLUMN_NAME IN ('ahorrosplit', 'costofinanciero')
ORDER BY ORDINAL_POSITION;

-- =========================================================================
-- FIN DEL SCRIPT
-- =========================================================================

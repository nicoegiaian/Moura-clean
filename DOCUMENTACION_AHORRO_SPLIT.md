# 📋 DOCUMENTACIÓN TÉCNICA - ACTUALIZACIÓN AHORRO SPLIT Y COSTOFINANCIERO
**Fecha:** 13 de Enero de 2026  
**Desarrollador:** Desarrollador Senior PHP  
**Sistema:** CredMoura - Módulo de Liquidaciones  
**Archivos Modificados:** `liquidacionesdetalle` (DB), `archivosdiarios.php`

---

## 📌 RESUMEN EJECUTIVO

Esta actualización implementa el nuevo esquema de beneficios "Ahorro Split" según los requerimientos del documento "Nuevo ahorro cred moura.pptx", y corrige el cálculo del beneficio base de 0.5% a 0.7%.

---

## 🎯 OBJETIVOS ALCANZADOS

### 1️⃣ **Base de Datos**
- ✅ Nueva columna `ahorrosplit` (DECIMAL 15,2)
- ✅ Nueva columna `costofinanciero` (DECIMAL 15,2)

### 2️⃣ **Lógica de Negocio**
- ✅ Beneficio Base actualizado: **0.5% → 0.7%**
- ✅ Cálculo de Ahorro Split implementado según porcentaje PDV
- ✅ Integración con función `obtenerPorcentajePDV()`
- ✅ Query INSERT actualizado con nuevas columnas

---

## 🗂️ ENTREGABLES

### **ARCHIVO 1: Script SQL**
📄 `UPDATE_liquidacionesdetalle_ahorro_split.sql`

**Ubicación:** `/c:/Users/alejo/Desktop/Moura-clean/`

**Instrucciones de Ejecución:**
```bash
# Opción 1: MySQL CLI
mysql -u [usuario] -p [basededatos] < UPDATE_liquidacionesdetalle_ahorro_split.sql

# Opción 2: phpMyAdmin / Workbench
# Abrir el archivo y ejecutar directamente en el query editor
```

**Verificación:**
```sql
DESCRIBE liquidacionesdetalle;
-- Deberías ver las columnas ahorrosplit y costofinanciero
```

---

### **ARCHIVO 2: Función PHP Refactorizada**
📄 `archivosdiarios.php` → Función `insertarDetalleLiquidacion()`

**Ubicación:** `/c:/Users/alejo/Desktop/Moura-clean/api/archivosdiarios.php`

---

## 🔧 CAMBIOS DETALLADOS EN PHP

### **A. Actualización de Beneficio Base**

**ANTES:**
```php
$beneficioBase = $importeBruto * 0.005; // 0.5%
```

**AHORA:**
```php
$beneficioBase = $importeBruto * 0.007; // 0.7%
// Req: 0.2% dif arancel + 0.5% subsidio = 0.7%
```

---

### **B. Implementación de Ahorro Split**

**Nueva lógica condicional según porcentaje PDV:**

```php
// Se obtiene el porcentaje de split del PDV
$porcentajePDV = obtenerPorcentajePDV($dbConnection, $datosBIND['numero_de_comercio'], $fechaLiquidacion);

// Inicializar variable
$ahorroSplit = 0.0;

// Aplicar reglas de negocio
if ($porcentajePDV == 30) {
    // Caso 30-70: 0.88% total
    $ahorroSplit = $importeBrutoOriginal * 0.0088;
    
} elseif ($porcentajePDV == 0) {
    // Caso 0-100: 1.2% total
    $ahorroSplit = $importeBrutoOriginal * 0.012;
    
} elseif ($porcentajePDV == 40) {
    // Caso 40-60: 0.72% total
    $ahorroSplit = $importeBrutoOriginal * 0.0072;
    
} elseif ($porcentajePDV == 50) {
    // Caso 50-50: 0.6% total
    $ahorroSplit = $importeBrutoOriginal * 0.006;
    
} else {
    // Sin beneficio adicional
    $ahorroSplit = 0;
}
```

**Tabla de Porcentajes:**

| Caso Split | % PDV | % Moura | Ahorro Base | IVA Dif | **Total** |
|------------|-------|---------|-------------|---------|-----------|
| 30-70      | 30%   | 70%     | 0.84%       | 0.04%   | **0.88%** |
| 0-100      | 0%    | 100%    | -           | -       | **1.2%**  |
| 40-60      | 40%   | 60%     | -           | -       | **0.72%** |
| 50-50      | 50%   | 50%     | -           | -       | **0.6%**  |

---

### **C. Actualización del INSERT Query**

**Nuevas columnas agregadas:**
```php
$query = "INSERT INTO liquidacionesdetalle (
    ...
    beneficiocredmoura,
    costomipyme,
    IVAcostomipyme,
    ahorrosplit,        // ← NUEVA
    costofinanciero     // ← NUEVA
) VALUES (
    ...
    :beneficiocredmoura,
    :costomipyme,
    :IVAcostomipyme,
    :ahorrosplit,       // ← NUEVA
    :costofinanciero    // ← NUEVA
)";
```

---

### **D. Binding de Valores**

```php
// Campos existentes (sin cambios)
$stmt->bindValue(':beneficiocredmoura', $beneficioCredMoura);
$stmt->bindValue(':costomipyme', $costomipyme);
$stmt->bindValue(':IVAcostomipyme', $IVAcostomipyme);

// NUEVOS CAMPOS
$stmt->bindValue(':ahorrosplit', $ahorroSplit);
$stmt->bindValue(':costofinanciero', $costomipyme); 
// Nota: costofinanciero = costomipyme (Tasa MiPyme)
```

---

## 🔍 MAPEO DE VARIABLES

| Variable PHP          | Columna DB           | Descripción                                    |
|-----------------------|----------------------|------------------------------------------------|
| `$beneficioCredMoura` | `beneficiocredmoura` | Beneficio base (ahora 0.7%)                   |
| `$costomipyme`        | `costomipyme`        | Costo financiero calculado (rate * bruto)     |
| `$costomipyme`        | `costofinanciero`    | **Mismo valor** - Tasa MiPyme                 |
| `$IVAcostomipyme`     | `IVAcostomipyme`     | IVA sobre costo MiPyme                        |
| `$ahorroSplit`        | `ahorrosplit`        | **Nuevo** - Beneficio adicional según PDV     |

---

## ✅ PRUEBAS RECOMENDADAS

### **Test 1: Verificar Beneficio Base**
```sql
SELECT 
    nrotransaccion,
    beneficiocredmoura,
    (SELECT SUM(importe) FROM transacciones WHERE nrotransaccion = ld.nrotransaccion) as importe_bruto,
    ROUND((beneficiocredmoura / importe_bruto) * 100, 2) as porcentaje_calculado
FROM liquidacionesdetalle ld
WHERE fecha >= '2026-01-13'
LIMIT 10;

-- El porcentaje_calculado debería ser cercano a 0.7% (puede variar por cuotas)
```

### **Test 2: Verificar Ahorro Split por Caso**
```sql
SELECT 
    p.comercio,
    s.porcentajepdv,
    ld.nrotransaccion,
    ld.ahorrosplit,
    t.importe_bruto_original,
    ROUND((ld.ahorrosplit / t.importe_bruto_original) * 100, 2) as porcentaje_ahorro
FROM liquidacionesdetalle ld
JOIN transacciones t ON ld.nrotransaccion = t.nrotransaccion
JOIN puntosdeventa p ON t.idpdv = p.id
JOIN splits s ON p.id = s.idpdv
WHERE ld.fecha >= '2026-01-13'
    AND s.fecha = (SELECT MAX(fecha) FROM splits WHERE idpdv = p.id)
ORDER BY s.porcentajepdv;

-- Verificar:
-- porcentajepdv = 30 → porcentaje_ahorro ≈ 0.88%
-- porcentajepdv = 0  → porcentaje_ahorro ≈ 1.2%
-- porcentajepdv = 40 → porcentaje_ahorro ≈ 0.72%
-- porcentajepdv = 50 → porcentaje_ahorro ≈ 0.6%
```

### **Test 3: Verificar Costo Financiero**
```sql
SELECT 
    nrotransaccion,
    costomipyme,
    costofinanciero,
    CASE 
        WHEN costomipyme = costofinanciero THEN '✓ OK'
        ELSE '✗ ERROR'
    END as validacion
FROM liquidacionesdetalle
WHERE fecha >= '2026-01-13'
LIMIT 20;

-- Ambas columnas deben tener el mismo valor
```

---

## 🚨 PUNTOS DE ATENCIÓN

### ⚠️ **IMPORTANTE:**
1. **Ejecutar el SQL ANTES de correr archivosdiarios.php**
   - Si ejecutas el PHP sin crear las columnas, obtendrás error SQL.

2. **Logging habilitado**
   - Los mensajes de log indican qué caso de Ahorro Split se aplicó
   - Buscar en `archivosdiarios.log` líneas como:
     ```
     INFO: Aplicando Ahorro Split 30-70 (0.88%) - TX: 123456
     ```

3. **Compatibilidad hacia atrás**
   - Las transacciones antiguas tendrán `ahorrosplit = 0.00` por defecto
   - El beneficio base cambió de 0.5% a 0.7% para TODAS las nuevas transacciones

4. **Porcentajes PDV no contemplados**
   - Si un PDV tiene un % diferente a 30, 0, 40, 50 → `ahorrosplit = 0`
   - Aparecerá log: `"Sin Ahorro Split definido para PDV X%"`

---

## 📊 IMPACTO FINANCIERO ESTIMADO

Asumiendo 1000 transacciones diarias con promedio de $10,000 por transacción:

| Concepto               | Antes (0.5%) | Ahora (0.7%) | Diferencia     |
|------------------------|--------------|--------------|----------------|
| Beneficio Base Diario  | $50,000      | $70,000      | **+$20,000**   |
| Beneficio Base Mensual | $1,500,000   | $2,100,000   | **+$600,000**  |

**Plus:** Ahorro Split adicional (varía según distribución de casos)

---

## 📞 CONTACTO Y SOPORTE

**Desarrollador:** Desarrollador Senior PHP  
**Fecha de Implementación:** 13/01/2026  
**Versión:** 2.0 - Ahorro Split + Beneficio Base 0.7%

---

## 📝 HISTORIAL DE CAMBIOS

| Fecha      | Versión | Cambio                                       |
|------------|---------|----------------------------------------------|
| 13/01/2026 | 2.0     | Implementación Ahorro Split + Beneficio 0.7% |
| (anterior) | 1.0     | Sistema original con Beneficio 0.5%          |

---

**FIN DEL DOCUMENTO**

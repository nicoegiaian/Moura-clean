# 🚀 GUÍA RÁPIDA DE IMPLEMENTACIÓN - AHORRO SPLIT

## ⏱️ Tiempo estimado: 5 minutos

---

## 📋 CHECKLIST DE IMPLEMENTACIÓN

### ✅ **PASO 1: Ejecutar el Script SQL** (2 minutos)

1. Abre tu cliente MySQL (Workbench, phpMyAdmin, o CLI)
2. Conecta a la base de datos de CredMoura
3. Ejecuta el archivo:
   ```
   UPDATE_liquidacionesdetalle_ahorro_split.sql
   ```

**Verificación:**
```sql
DESCRIBE liquidacionesdetalle;
```

Deberías ver las nuevas columnas:
- `ahorrosplit` (DECIMAL 15,2)
- `costofinanciero` (DECIMAL 15,2)

---

### ✅ **PASO 2: Verificar el código PHP** (1 minuto)

El archivo `archivosdiarios.php` ya ha sido modificado con los siguientes cambios:

**✓ Línea ~1663:** Beneficio Base actualizado
```php
$beneficioBase = $importeBruto * 0.007; // 0.7%
```

**✓ Líneas ~1683-1710:** Nuevo cálculo Ahorro Split
```php
if ($porcentajePDV == 30) {
    $ahorroSplit = $importeBrutoOriginal * 0.0088; // 0.88%
} elseif ($porcentajePDV == 0) {
    $ahorroSplit = $importeBrutoOriginal * 0.012; // 1.2%
}
// ... etc
```

**✓ Líneas ~1753-1754:** Nuevos bindValue
```php
$stmt->bindValue(':ahorrosplit', $ahorroSplit);
$stmt->bindValue(':costofinanciero', $costomipyme);
```

---

### ✅ **PASO 3: Prueba en Ambiente de Desarrollo** (2 minutos)

Ejecuta el procesamiento de un día de transacciones:

```bash
php archivosdiarios.php
```

**Busca en el log estos mensajes:**
```
INFO: Aplicando Ahorro Split 30-70 (0.88%) - TX: 123456
INFO: Aplicando Ahorro Split 0-100 (1.2%) - TX: 123457
```

---

### ✅ **PASO 4: Validación de Datos** (Opcional)

Ejecuta esta query para verificar que los datos se están guardando correctamente:

```sql
SELECT 
    nrotransaccion,
    beneficiocredmoura,
    ahorrosplit,
    costofinanciero,
    DATE(fecha) as fecha
FROM liquidacionesdetalle
WHERE DATE(fecha) = CURDATE()
ORDER BY nrotransaccion DESC
LIMIT 10;
```

**Verificar que:**
- `ahorrosplit` tenga valores > 0 para algunos registros
- `costofinanciero` = `costomipyme` (mismos valores)
- `beneficiocredmoura` sea aprox. 0.7% del importe bruto (puede variar por cuotas)

---

## 🎯 VALORES ESPERADOS

| Porcentaje PDV | Ahorro Split | Ejemplo ($10,000) |
|----------------|--------------|-------------------|
| 30% (30-70)    | 0.88%        | $88.00           |
| 0% (0-100)     | 1.2%         | $120.00          |
| 40% (40-60)    | 0.72%        | $72.00           |
| 50% (50-50)    | 0.6%         | $60.00           |
| Otros          | 0%           | $0.00            |

---

## ⚠️ TROUBLESHOOTING

### Error: "Unknown column 'ahorrosplit'"
**Solución:** No ejecutaste el script SQL. Vuelve al Paso 1.

### Error: "Undefined variable: $ahorroSplit"
**Solución:** La función no tiene el nuevo código. Revisa que copiaste bien la función refactorizada.

### Ahorro Split siempre es 0
**Solución:** Verifica que `obtenerPorcentajePDV()` esté retornando correctamente el porcentaje del PDV.

---

## 📞 ARCHIVOS DE REFERENCIA

- `UPDATE_liquidacionesdetalle_ahorro_split.sql` - Script SQL
- `DOCUMENTACION_AHORRO_SPLIT.md` - Documentación completa
- `FUNCION_insertarDetalleLiquidacion_REFACTORIZADA.php` - Función completa

---

## ✨ ¡LISTO!

Con estos pasos, tu sistema ya está procesando el nuevo esquema de Ahorro Split y el beneficio base actualizado.

**Fecha de implementación:** 13 de Enero de 2026
**Versión:** 2.0

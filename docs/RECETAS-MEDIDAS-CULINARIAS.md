# Medidas culinarias y cantidades libres

Desde la actualización del 09/10/2026, una receta puede conservar ingredientes «a gusto» o «según necesite». Se representan como cantidad cero y opcionales, con el texto original en notas. No generan faltantes ni descuento de stock; no bloquean cocinar, incluso cuando se consultan ingredientes opcionales. Los ingredientes opcionales con cantidad positiva conservan su comportamiento anterior.

El ABM permite activar «A gusto / cantidad necesaria». La API rechaza cantidades negativas o cero sin opcional; al editar valida el estado combinado bajo bloqueo. Coste y nutrición permanecen como cálculos parciales cuando existen cantidades libres, sin inventar aportes ni equivalencias.

Las compras por unidad entera redondean hacia arriba el faltante cuando no hay un envase resuelto. El consumo y la cantidad requerida conservan sus decimales; medio limón no descuenta un limón entero. Gramos y mililitros no se redondean de esta forma.

El catálogo incorpora 45 medidas y presentaciones culinarias. Se conservan sin asignar pesos o tamaños universales a pizcas, cucharas, vasos o recipientes. Sólo se agregan cinco equivalencias exactas SI entre cc, dl, ml y l. `CulinaryMeasuresSeeder` inserta únicamente códigos/conversiones ausentes; ejecutar el seeder global completo mantiene su alcance habitual y no es el mecanismo de carga mínima productiva.

Validación: 14 pruebas backend / 137 verificaciones con SQLite en memoria; 7 pruebas DOM web; 9 pruebas móviles y TypeScript; revisión estática independiente. La importación de datos tiene manifiestos, transacción, auditoría y verificación posterior separados. No se modifica el esquema ni se ejecutan migraciones.

La actualización visual móvil requiere una APK que incluya estos cambios. El backend conserva compatibilidad con las versiones anteriores.

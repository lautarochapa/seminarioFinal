# Búsqueda de ingredientes en productos — 09/10/2026

Productos y Revisión de productos importados sólo cargaban los primeros 100 ingredientes. El catálogo creció y ya no se podían elegir `fideo` y `puré de tomate`. Además, editar un producto cuyo ingrediente estaba fuera de esa página dejaba el selector vacío.

Se incorpora un buscador remoto compartido al filtro y editor de Productos, y a los formularios de creación/asignación de ingredientes de candidatos. Usa el endpoint autorizado existente con 20 resultados por búsqueda; permite afinar el nombre cuando hay más coincidencias. Conserva el ingrediente seleccionado aunque no aparezca entre los resultados y lo restaura al editar. Nuevos formularios y cambios de candidato limpian la elección previa.

Las respuestas de búsquedas anteriores no reemplazan la selección actual. Enter ejecuta la búsqueda sin enviar el formulario. Los campos de búsqueda no se incluyen en los payloads. Sin cambios de API, permisos, esquema, cuentas ni datos del catálogo.

## Validación local

- TDD de Productos: 3 fallos reproducidos antes de integrar el arreglo; 3 pruebas correctas después.
- Helper: 10 pruebas, incluyendo búsqueda, selección 213/491, carreras, errores, Enter, reinicio y desmontaje.
- Candidatos: 5 pruebas, incluida integración con helper y modales reales para crear/aprobar con 213 y asignar 491.
- Regresión conjunta: **72/72 pruebas correctas**, sin omisiones.
- Sintaxis de los tres scripts y `git diff --check`: correctos.
- Navegador Chrome con HTML/CSS/JS reales y API simulada local: selección existente conservada, búsqueda y selección de puré de tomate, Enter sin guardar, y revisión de candidato con ingrediente sugerido. No se ejecutó scraping ni se guardaron productos reales durante esta comprobación.

Comando reproducible desde la raíz del repositorio (jsdom disponible en mobile/node_modules):

```powershell
$env:NODE_PATH = (Resolve-Path mobile/node_modules).Path
node --max-old-space-size=256 --test --test-concurrency=1 tests/JavaScript/admin-ingredient-picker.test.cjs tests/JavaScript/admin-product-ingredient-search.test.cjs tests/JavaScript/admin-candidate-ingredient-search.test.cjs tests/JavaScript/admin-crud-modals.test.cjs tests/JavaScript/admin-workflows.test.cjs
```

Se usó ejecución secuencial después de que una corrida concurrente abortara por falta de memoria del proceso. La corrida final completa terminó con código 0. Las pruebas simuladas no sustituyen la comprobación de búsquedas contra el catálogo publicado.

# Memoria de Render: INFRA-01

## Incidente confirmado

- Servicio `srv-d9a197beo5us7399su5g`, Docker Free, limite 512 MB.
- Render Events: instancia `5bhpp`, 21/09/2026 20:40 ART (23:40 UTC),
  `Ran out of memory (used over 512MB) while running your code`.
- Version en ese momento: `2e9042f0`; no fue el despliegue posterior de correo.
- Logs 20:39:42 a 20:40:10 ART: solicitudes concurrentes a `.env.*`,
  `.git/FETCH_HEAD`, `.aws/config`, `.config/...` y `.docker/secrets.json`.
  Las solicitudes observadas respondieron 404. No se observa entrega de esos
  archivos en la muestra; esto no equivale a una auditoria de seguridad completa.
- Apache/PHP vuelve a iniciar a las 20:40:20; healthz 200 a las 20:40:27.
  El evento agregado de recuperacion de Render aparece a las 21:02, junto al
  siguiente deploy. No inferir 22 minutos de caida continua de esos eventos.
- Runtime observado: Apache 2.4.54, PHP 7.4.33, MPM prefork.

Hipotesis sustentada: una rafaga compatible con un escaner automatico produjo
concurrencia costosa de PHP al resolver rutas inexistentes. La configuracion
anterior no reducia los limites de prefork de la imagen base para 512 MB.
La correlacion temporal respalda la mitigacion; no demuestra una fuga ni
identifica el consumo de cada proceso. No hay evidencia para atribuirlo a Resend.

El panel Metrics exige un plan pago para memoria/CPU. No hay serie historica
de memoria disponible en esta investigacion, ni shell en la instancia Free.
No se contrato ni amplio el plan.

## Mitigacion

- Rechazar rutas con componentes ocultos en Apache con 404, antes de Laravel.
  Se conserva `.well-known` para challenges; ocultos anidados siguen bloqueados.
- Dos workers prefork como maximo, uno o dos ociosos, reciclados cada 500
  conexiones; KeepAliveTimeout de un segundo. El exceso de concurrencia se
  encola: protege memoria a cambio de posible latencia durante una rafaga.
- Limite PHP explicito de 128M por peticion. No es un limite de RSS ni garantiza
  que el contenedor entero nunca exceda 512 MB; bibliotecas nativas y procesos
  auxiliares tambien consumen memoria. No subirlo sin medir el presupuesto total.
- Una linea `[runtime-memory]` por minuto con uso, working set aproximado
  (uso menos inactive_file) y limite del cgroup. Sin usuarios, URLs ni secretos.
  Compatible con cgroup v1/v2; si no hay cgroup accesible, no imprime muestra.
- No cambiar DNS/proxy de Cloudflare, Neon, claves, envio de correo ni API movil.

## Verificacion reproducible

1. `node tests/Smoke/render-runtime.cjs`: configuracion, sintaxis Bash y fixtures
   de cgroup v1/v2 (incluye limite ilimitado y datos no disponibles).
2. El Dockerfile ejecuta `docker/tests/apache-smoke.sh` durante el build:
   16 peticiones HTTP contra Apache/PHP real y un front controller temporal.
   Comprueba 404 sin PHP, rutas normales, Authorization, archivos estaticos,
   `.well-known` y memory_limit. No usa base de datos ni secretos. El fixture se
   elimina antes de copiar la aplicacion. Una falla impide publicar esa imagen.
3. `node tests/Smoke/render-runtime-online.cjs https://cocinacomidacontrol.com.ar`:
   siete peticiones secuenciales de solo lectura, sin prueba de saturacion.
   Repetir con el host onrender.com usado por APK anteriores.
4. Login/perfil/resumen/presupuesto en cuenta QA, sin modificar stock ni gastos.
5. Buscar `[runtime-config]`, `[runtime-memory]` en Render Logs y revisar Events.
   Documentar intervalo y maximo observado; una muestra corta no demuestra
   estabilidad indefinida ni capacidad para grandes cantidades de usuarios.

Resultado local 21/09: prueba de runtime aprobada; regresion enfocada de login,
registro, inicio, presupuesto y rutas web: 65 tests / 223 assertions, 62 MB
con limite PHP 128M. Es memoria de la suite CLI local, no medicion de Render.
Docker/WSL no estan instalados en esta PC: la prueba Apache se ejecuto en Render.

## Despliegue y aceptacion

- Autorizado por Lautaro: probar y publicar la mitigacion sin cambiar el plan.
- Commit `5cdbd108b757baab3e39bf873991a47130814a64`, deploy
  `dep-daouaan40ujc73bqfu80`. Build iniciado 23:17:14 ART y Live 23:18:24.
- Log de build 23:17:38: `Apache runtime smoke: 16 HTTP checks passed; PHP limit
  and Authorization verified.` Configuracion de inicio confirmada en logs:
  prefork, max_workers=2, php_memory_limit=128M.
- Dominio propio y onrender.com: 14 comprobaciones publicas aprobadas. Healthz,
  landing, login y JS devuelven 200; las tres rutas ocultas de prueba dan 404
  sin cabeceras PHP ni cookies de sesion. Respuestas bloqueadas: 208-306 ms.
- Ocho solicitudes autenticadas aprobadas (23:22:23-23:22:47): login, perfil,
  inicio, grupos, stock, resumen de stock, planes y presupuesto. Solo lecturas
  despues de autenticar; sin cambios en stock/gastos. Presupuesto QA conservado.
  Latencias individuales 2,0-5,5 s: aceptacion funcional, no objetivo de rendimiento.
- Apache informa `AH00161` al alcanzar dos workers durante un acceso normal.
  Esto indica uso de la cola, no OOM: las peticiones verificadas terminaron en
  200. No subir la concurrencia a ciegas para eliminar ese aviso.

Observacion inicial: 23:18:17 a 23:28:17 ART del 21/09 (10 minutos), misma
instancia `h6j8g`, sin nuevos eventos de OOM/reinicio en la revision. Healthz
seguia en 200/ok a las 23:28:01. Maximo entre las muestras: 41.996.288 bytes
(40,05 MiB, 7,82% de 512 MiB); working set maximo 41.070.592 bytes (39,17 MiB).
No es el pico absoluto: el muestreo de un minuto puede omitir picos breves.
No se dispone de un valor numerico anterior comparable, salvo el evento >512 MB.

| Hora ART | Uso cgroup (bytes) | Working set (bytes) |
| --- | ---: | ---: |
| 23:18:17, antes de Apache | 2572288 | 2441216 |
| 23:19:17 | 39821312 | 39538688 |
| 23:20:17 | 39968768 | 39616512 |
| 23:21:17 | 40153088 | 39739392 |
| 23:22:17 | 40259584 | 39784448 |
| 23:23:17 | 41295872 | 40681472 |
| 23:24:17 | 41398272 | 40722432 |
| 23:25:17 | 41242624 | 40505344 |
| 23:26:17 | 41639936 | 40845312 |
| 23:27:17 | 41848832 | 40992768 |
| 23:28:17 | 41996288 | 41070592 |

Estado: mitigacion y verificacion inicial completadas; seguimiento durante el
ensayo pendiente. No implica estabilidad indefinida ni proteccion completa
frente a denegacion de servicio. No se ejecuto una prueba de saturacion online.

## Operacion y limites

Antes del ensayo, revisar Events y buscar `runtime-memory` en Logs. Si aparece
otro OOM, conservar la hora UTC, instancia y solicitudes anteriores; comparar
con el limite del cgroup y no aumentar `memory_limit` como primer recurso.
Las muestras son puntuales cada 60 segundos: pueden omitir picos mas breves.
El primer valor se imprime antes de iniciar Apache y no es el consumo estable.
No se creo un monitor externo ni una tarea programada de seguimiento.

Si hubiera una regresion atribuible a este cambio, el deploy anterior es
`dep-daoso4m0tbcc73fhgpg0` (`3fc737ba`). Un rollback tambien retira la proteccion
contra escaneos; no requiere cambios ni rollback en Neon. No restaurar claves
ni variables antiguas de correo para revertir esta mitigacion.

Fuentes: [eventos del servicio](https://dashboard.render.com/web/srv-d9a197beo5us7399su5g/events),
[logs](https://dashboard.render.com/web/srv-d9a197beo5us7399su5g/logs),
[Apache prefork](https://httpd.apache.org/docs/2.4/mod/prefork.html),
[limites de workers y reciclado](https://httpd.apache.org/docs/2.4/mod/mpm_common.html),
[mod_rewrite](https://httpd.apache.org/docs/2.4/mod/mod_rewrite.html).

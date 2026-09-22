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
Docker/WSL no estan instalados en esta PC: prueba Apache reservada al build Linux.

## Seguimiento

Pendiente: publicar, confirmar build y health checks, registrar las muestras
del nuevo runtime y ejecutar el recorrido online. No marcar cerrado hasta esa
verificacion. La observacion posterior al ensayo sigue siendo necesaria.

Fuentes: [eventos del servicio](https://dashboard.render.com/web/srv-d9a197beo5us7399su5g/events),
[logs](https://dashboard.render.com/web/srv-d9a197beo5us7399su5g/logs),
[Apache prefork](https://httpd.apache.org/docs/2.4/mod/prefork.html),
[limites de workers y reciclado](https://httpd.apache.org/docs/2.4/mod/mpm_common.html),
[mod_rewrite](https://httpd.apache.org/docs/2.4/mod/mod_rewrite.html).

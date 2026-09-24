# Roles y accesos

La primera fase tiene cuatro perfiles:

| Codigo | Perfil | Web | Android |
| --- | --- | --- | --- |
| user | Usuario comun | Hogar, stock, recetas, plan, compras y presupuesto | Si |
| super_admin | Superadministrador | Todos los modulos vigentes | No |
| catalog_admin | Administrador de catalogo y supermercados | Catalogo, productos, ingredientes, supermercados, sucursales, precios y scraping de productos | No |
| recipe_admin | Administrador de recetas / chef | Recetas oficiales, categorias, etiquetas e importacion de recetas | No |

El administrador combinado no puede administrar usuarios, roles, permisos ni
configuracion del sistema. El administrador de recetas no puede modificar el
catalogo comercial ni gestionar supermercados.

Docente queda retirado. Profesional/dietologo se reserva para una segunda fase:
no tiene rutas, permisos ni opciones disponibles en la primera fase. Los datos
historicos no se borran por retirar estas funciones.

Sistema/Jobs no es una cuenta de usuario ni un requisito para las tareas internas.
Los comandos y servicios siguen funcionando con su ejecucion tecnica normal.
No se crea una cuenta interactiva para estos procesos.

Los privilegios se verifican en el servidor. Android identifica sus solicitudes
y acepta exclusivamente el rol user, tambien al restaurar una sesion. Una cuenta
con user mas cualquier rol administrativo no tiene acceso a la APK. El encabezado
de cliente distingue el canal, no sustituye a la autorizacion de cada endpoint.

La baja de cuentas usa el borrado logico existente, desactiva la cuenta y revoca
tokens y sesiones. Conserva las relaciones historicas, el catalogo y la auditoria.
Las contrasenas de evaluacion se entregan en un archivo privado fuera de Git.

## Cuentas de evaluacion

| Perfil | Email de ingreso | Canal |
| --- | --- | --- |
| Superadministrador | superadmin@evaluacion.invalid | Web |
| Catalogo y supermercados | catalogo@evaluacion.invalid | Web |
| Recetas / chef | recetas@evaluacion.invalid | Web |
| Usuario comun | usuario@evaluacion.invalid | Web y Android |

Estas direcciones son identificadores ficticios de prueba, no buzones de correo.
El usuario comun comienza con la puesta en marcha y puede crear su hogar.
Las cuentas anteriores se dan de baja en forma recuperable; sus datos no se
reasignan ni se eliminan. Solo el superadministrador puede restaurarlas.

La restriccion de ingreso de Android se incorpora en la version 1.0.9 (10).
Las versiones anteriores deben actualizarse para aplicar este control de canal.

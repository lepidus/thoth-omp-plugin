[English](README.md) | **Español** | [Português Brasileiro](README-pt_BR.md)

# Entorno Thoth desechable para las pruebas del plugin

Este entorno inicia una API Thoth real, autenticación Zitadel y dos bases de datos PostgreSQL.
La inicialización crea una editorial, un sello y una cuenta de servicio restringida a esa editorial,
con `PUBLISHER_USER` y `WORK_LIFECYCLE`. No utiliza credenciales ni APIs públicas de Thoth.
También prepara una instancia desechable de OMP para ejecutar los escenarios Cypress descritos abajo.

## Uso local

Requisitos: Docker Engine con Compose v2 o posterior y Python 3.9 o posterior.
La imagen de Thoth es Linux/amd64; otros hosts necesitan emulación o una imagen compatible.
Ejecutar desde la raíz de este worktree:

```sh
# Mostrar el plan sin modificar el entorno.
python3 tests/environment/environment.py up

# Construir la imagen auxiliar, iniciar y configurar los servicios y ejecutar la prueba de la API.
python3 tests/environment/environment.py up --apply

# Consultar los contenedores y repetir la creación/consulta/actualización/eliminación mediante la API.
python3 tests/environment/environment.py status
python3 tests/environment/environment.py smoke --apply

# Eliminar únicamente los contenedores, volúmenes, red y credenciales de este worktree.
python3 tests/environment/environment.py down --apply
```

La API está disponible en `http://127.0.0.1:18000/graphql`. Utilizar `up --apply --port 18001`
para otro entorno simultáneo. Cada directorio tiene su propio proyecto Compose.
Las credenciales y los identificadores se guardan en `tests/environment/.state/client.json`,
con permisos 0600 para el archivo y 0700 para el directorio. Este archivo está excluido de Git
y del contexto de compilación. El token del cliente caduca a los dos días; recrear el entorno después de ese plazo.

La inicialización no es idempotente. No reiniciar contenedores individualmente ni intentar
reutilizar sus bases de datos: ejecutar `down --apply` y después `up --apply`. Si falla la
preparación, los recursos quedan disponibles para el diagnóstico; el mismo comando `down`
los elimina. La imagen construida permanece en caché para acelerar la siguiente creación.
Cada ejecución del comando Cypress restaura el conjunto de datos de OMP; cada caso crea
sus propios libros con identificadores únicos, sin depender de los datos dejados por otros casos.

## Componentes y aislamiento

- Thoth 1.8.0: imagen oficial fijada mediante su digest amd64. El commit de origen de la imagen
  es `9ae1e56714096507d2d210b5b315361d626626c9`; su árbol Git es idéntico al del commit
  `4fa7eaa9ccb60d39c41ccd8feb257edf28c173ff` del clon inspeccionado en esta tarea.
- Zitadel 3.2.2 y PostgreSQL 17: imágenes fijadas mediante digest.
- Python: scripts de inicialización y verificación que utilizan únicamente la biblioteca estándar.
- Nginx: gateway local fijado mediante digest, con el puerto publicado solo en loopback.

La red de las bases de datos, Zitadel y Thoth es interna. El gateway la conecta a una
segunda red para publicar el puerto en el host; solo reenvía solicitudes a la API local.
Esta conexión no proporciona salida externa a la API. El gateway no es necesario en CI.
No configurar alojamiento de archivos: las credenciales AWS son ficticias, no hay
buckets/CDN y las pruebas de esta etapa cubren únicamente metadatos. La distribución automática
está desactivada. No utilizar este entorno para datos o credenciales reales.

## Inicialización y prueba de la API

El servicio Zitadel espera a PostgreSQL y crea el directorio del PAT antes de inicializarse.
El servicio Thoth espera a que Zitadel esté listo, ejecuta la configuración upstream, crea la
cuenta restringida, inicia las migraciones/API y crea las fixtures. Solo escribe `ready` después de la prueba.
El PAT administrativo se elimina del volumen tras este proceso. OMP recibe únicamente
el token restringido. La inicialización captura la clave privada sin imprimirla.

La prueba comprueba que la cuenta no es SUPERUSER, solo puede ver la editorial esperada,
tiene permiso de lifecycle, rechaza escrituras anónimas y crea/consulta/actualiza/elimina una
monografía. La monografía de prueba se elimina; la editorial y el sello permanecen disponibles.
Esto todavía no verifica la publicación/activación, todos los metadatos del plugin, las cargas
de archivos, la interfaz de OMP ni el comportamiento del navegador.

## Escenarios Cypress

El siguiente comando prepara una instancia desechable de OMP en otro contenedor, con una base
MySQL `thoth_cypress` en el servicio `omp-db`, sin puertos publicados. El volcado debe ser
el conjunto de datos MySQL de OMP `stable-3_5_0`, contexto `publicknowledge` (Public Knowledge
Press), acompañado de los directorios `files/` y `public/` de la misma instantánea.
No se modifica ningún checkout ni base de datos OMP del host.

```sh
python3 tests/environment/environment.py up --apply
python3 tests/environment/environment.py cypress \
  --dataset /home/lepidus/Work/pkp/datasets/omp/stable-3_5_0/mysql
# Después de revisar el plan, añadir --apply al comando anterior.
python3 tests/environment/environment.py down --apply
```

La suite cubre cuatro recorridos por la interfaz, con libros únicos para cada caso:

- **Registro mediante el botón:** registra una monografía publicada y comprueba su vínculo,
  título, tipo, sello y estado Active en Thoth.
- **Registro al publicar:** publica un borrador con consentimiento de registro y un sello,
  comprobando la publicación en OMP y la obra Active en Thoth.
- **Registro masivo:** selecciona dos de tres libros de un grupo propio y confirma sus
  registros; el tercero no recibe ninguna solicitud de registro y permanece sin vínculo.
- **Actualización de metadatos:** sincroniza mediante el botón un borrador ya vinculado cuya
  obra remota tiene un título antiguo; comprueba el título actualizado en el mismo identificador.

El ejecutor ejecuta la suite dos veces sin restaurar los datos entre ellas. No se simulan
las respuestas de Thoth. Para preparar el caso de actualización, la fixture crea una obra
Forthcoming con un título antiguo en la API desechable y persiste su vínculo en OMP.

La configuración y los comandos Cypress son los de OMP. El router PHP exclusivo de
pruebas inyecta el cliente HTTP real conectado a `http://api:8000`; las reglas de URL
del plugin en producción permanecen intactas. El token queda fuera de la raíz web y no
se envía a Cypress. La configuración del plugin y la creación de fixtures son condiciones
previas, no comportamientos cubiertos por estos escenarios.

El usuario restringido no puede eliminar las obras Active. Los datos creados permanecen
únicamente en las bases de datos desechables hasta `down --apply`, que elimina todo el
proyecto. El contenedor Cypress queda disponible tras la ejecución para inspeccionar
`/tmp/thoth-omp-server.log` y las capturas en `/var/www/omp/cypress/screenshots`.
No reutilizar la base de datos desechable como instalación de desarrollo.

La organización sigue la del plugin `customQuestions`:

- `cypress/tests/functional/*.cy.js`: un recorrido por archivo y sus aserciones;
- `cypress/support/thoth.js`: funciones auxiliares de preparación y consulta;
- `cypress/support/ThothTestData.php`: creación de fixtures y consulta del vínculo/API;
- `tests/environment/`: infraestructura Docker, configuración e inicialización.

La configuración Cypress sigue perteneciendo a OMP. La pipeline habitual incluye
`templates/groups/omp/cypress_tests.yml` y adapta `plugin_integration_tests_omp`
en `.gitlab/thoth-cypress.yml`. Reutiliza las reglas, caché, validación de dependencias
y artefactos de la plantilla; solo modifica los servicios y la preparación/ejecución
necesarios para Thoth. La suite completa `omp_integration_tests` está desactivada.

El workflow compartido ejecuta la pipeline de la rama mientras no haya un MR abierto
y pasa a ejecutar únicamente la pipeline del MR cuando existe. Un nuevo commit cancela
los jobs anteriores de Cypress y de construcción del entorno desechable, marcados como
`interruptible`. Los demás jobs mantienen su política de cancelación.

La construcción BuildKit en `.pre` publica la imagen auxiliar por SHA; Cypress espera
ese job y la inicialización de los servicios Thoth/Zitadel/PostgreSQL. Una instancia MySQL
separada recibe `/tmp/dump.sql` y los archivos correspondientes, ya incluidos en la imagen
OMP fijada. El entrypoint compartido acepta `--image-dataset` en este caso. El token
restringido se copia a `/thoth-state`, fuera de la raíz web, y se elimina en `after_script`.
Las dos ejecuciones generan XML JUnit separados; las capturas y los logs siguen el contrato
de artefactos compartido. CI utiliza una red exclusiva por job, sin Docker-in-Docker.

El escáner de secretos mantiene sus reglas predeterminadas. `.gitleaksignore` identifica
únicamente los tres hallazgos históricos de la URL ficticia de PostgreSQL por commit/archivo/regla/línea;
la definición actual en YAML tiene un comentario `gitleaks:allow`. No excluye archivos
completos ni reglas de detección. Esta instancia no habilita conjuntos de reglas personalizados.

Validación del 17/09/2026: OMP 3.5 de la imagen fijada, PHP 8.4.23, Node 20.19.2,
Cypress 14.5.4 y Electron 130 sin interfaz gráfica. La ejecución consulta la instancia real
de Thoth en los cuatro escenarios y repite la suite sin restaurar los datos entre ejecuciones.
El conjunto de datos utilizado fue `omp/stable-3_5_0/mysql` del checkout local de conjuntos de datos PKP.

## Prueba opcional en GitLab

La raíz `.gitlab-ci.yml` carga `.gitlab/thoth-environment.yml` solo cuando
`THOTH_ENVIRONMENT_PROBE=1`. Sin esta variable, ejecuta las plantillas normales, incluido Cypress integrado.
No es necesario modificar las plantillas compartidas.

- Construcción compartida con Cypress: runner con etiqueta `buildkit-rootless`, construcción sin Docker-in-Docker
  y publicación de la imagen auxiliar en `$CI_REGISTRY_IMAGE/test-environment:$CI_COMMIT_SHA`.
- Prueba: imagen OMP 3.5 ya utilizada por CI, runner con etiqueta `atualizacoes2`, dos
  instancias PostgreSQL, Zitadel y Thoth como servicios. `FF_NETWORK_PER_BUILD=true` permite
  la comunicación entre los servicios del job.
- Las credenciales pasan por el directorio compartido `/builds/thoth-environment-$CI_JOB_ID`,
  fuera del checkout, y se eliminan en `after_script`. No se publican como artefactos.
- La clave de cifrado de Zitadel y las contraseñas de las bases de datos en CI son valores
  públicos exclusivos de este entorno efímero; los tokens de acceso se generan durante la inicialización.
- La imagen de prueba publicada permanece en el registro para reutilización/auditoría. La política
  de retención del registro debe configurarse por separado; el job no elimina imágenes.

La red por job no bloquea la salida externa como la red interna de Compose. Los scripts
solo utilizan los alias locales, pero no afirmamos que exista aislamiento de salida en CI.
La salida de cgroup informa únicamente de los límites visibles para el contenedor del job;
no mide los límites de los servicios ni la RAM libre del host. Esta prueba no mide la capacidad
para ejecutar Cypress de forma concurrente con todos los servicios.

El cliente Python de la prueba utiliza GraphQL directamente. El cliente PHP y la configuración
exclusiva de pruebas para permitir la URL privada en el plugin forman parte de la integración
con OMP. El validador de URL de producción permanece intacto.

## Verificaciones rápidas

```sh
python3 -m unittest discover -s tests/environment -p 'test_*.py' -v
```

Estas pruebas cubren la planificación sin modificaciones, el rechazo de puertos inválidos
y los errores HTTP/GraphQL. La prueba real depende de `up --apply` y `smoke --apply`.

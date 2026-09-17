[English](README.md) | **Español** | [Português Brasileiro](README-pt_BR.md)

# Pruebas Cypress

Las pruebas Cypress utilizan instancias desechables de OMP y Thoth,
sin modificar su instalación local ni escribir en las APIs públicas de Thoth.

## Requisitos

- Docker con Compose v2 y Python 3.9 o posterior.
- Un host Linux/amd64 o emulación compatible.
- El conjunto de datos MySQL de OMP `stable-3_5_0`, contexto `publicknowledge`, con
  `database.sql`, `files/` y `public/` de la misma instantánea.

## Ejecutar

Desde la raíz del plugin, sustituya `/path/to/omp-dataset` por el directorio del conjunto de datos:

```sh
python3 tests/environment/environment.py up --apply
python3 tests/environment/environment.py cypress \
  --dataset /path/to/omp-dataset --apply
```

Para repetir las pruebas, ejecute de nuevo el comando `cypress`. Este restaura la base
de datos OMP desechable y ejecuta la suite dos veces. Sin `--apply`, los comandos solo muestran el plan.

## Uso interactivo

Después de `up`, prepare OMP y abra Cypress:

```sh
python3 tests/environment/environment.py prepare --dataset /path/to/omp-dataset --apply
python3 tests/environment/environment.py open --apply
```

Requiere una sesión local X11/XWayland y `xauth`. El contenedor accede a su sesión X11;
utilice únicamente imágenes y pruebas de confianza.

Seleccione una spec en la ventana de Cypress. Los cambios en las pruebas están disponibles
sin reconstruir la imagen. Cerrar Cypress mantiene OMP activo. Para ejecutar una spec sin
interfaz gráfica, sustituya `example.cy.js` por un archivo de `cypress/tests/functional`:

```sh
python3 tests/environment/environment.py run --spec example.cy.js --apply
```

Omita `--spec` para ejecutar todas las pruebas una vez. `open` y `run` reutilizan la base
preparada; `prepare` la restaura. Cierre Cypress antes de ejecutar otro comando del entorno.

## Finalizar

```sh
python3 tests/environment/environment.py down --apply
```

Esto elimina los servicios y datos de prueba. Para recrear un entorno existente,
ejecute `down` antes de `up`.

Las mismas pruebas también se ejecutan en GitLab CI.

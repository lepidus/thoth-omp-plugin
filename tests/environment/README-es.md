[English](README.md) | **Español** | [Português Brasileiro](README-pt_BR.md)

# Pruebas Cypress

Las pruebas cubren el registro mediante el botón, el registro al publicar, el registro
masivo y la actualización de metadatos. Utilizan instancias desechables de OMP y Thoth,
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

## Finalizar

```sh
python3 tests/environment/environment.py down --apply
```

Esto elimina los servicios y datos de prueba. Para recrear un entorno existente,
ejecute `down` antes de `up`.

Las mismas pruebas también se ejecutan en GitLab CI.

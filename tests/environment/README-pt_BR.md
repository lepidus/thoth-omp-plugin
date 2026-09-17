[English](README.md) | [Español](README-es.md) | **Português Brasileiro**

# Testes Cypress

Os testes Cypress usam OMP e Thoth descartáveis, sem alterar sua instalação
local nem gravar nas APIs públicas da Thoth.

## Requisitos

- Docker com Compose v2 e Python 3.9 ou posterior.
- Host Linux/amd64 ou emulação compatível.
- Dataset MySQL do OMP `stable-3_5_0`, contexto `publicknowledge`, com
  `database.sql`, `files/` e `public/` do mesmo snapshot.

## Executar

Na raiz do plugin, substitua `/path/to/omp-dataset` pelo diretório do dataset:

```sh
python3 tests/environment/environment.py up --apply
python3 tests/environment/environment.py cypress \
  --dataset /path/to/omp-dataset --apply
```

Para repetir os testes, execute novamente o comando `cypress`. Ele restaura o banco
OMP descartável e executa a suíte duas vezes. Sem `--apply`, os comandos apenas mostram o plano.

## Encerrar

```sh
python3 tests/environment/environment.py down --apply
```

Isso remove os serviços e dados de teste. Para recriar um ambiente existente,
execute `down` antes de `up`.

Os mesmos testes também são executados pela CI do GitLab.

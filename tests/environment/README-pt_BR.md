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

## Uso interativo

Após `up`, prepare o OMP e abra o Cypress:

```sh
python3 tests/environment/environment.py prepare --dataset /path/to/omp-dataset --apply
python3 tests/environment/environment.py open --apply
```

Requer uma sessão local X11/XWayland e `xauth`. O container acessa sua sessão X11;
use apenas imagens e testes confiáveis.

Selecione uma spec na janela do Cypress. Alterações nos testes ficam disponíveis sem
reconstruir a imagem. Fechar o Cypress mantém o OMP ativo. Para executar uma spec sem
interface gráfica, substitua `example.cy.js` por um arquivo de `cypress/tests/functional`:

```sh
python3 tests/environment/environment.py run --spec example.cy.js --apply
```

Omita `--spec` para executar todos os testes uma vez. `open` e `run` reutilizam o banco
preparado; `prepare` o restaura. Feche o Cypress antes de executar outro comando do ambiente.

## Encerrar

```sh
python3 tests/environment/environment.py down --apply
```

Isso remove os serviços e dados de teste. Para recriar um ambiente existente,
execute `down` antes de `up`.

Os mesmos testes também são executados pela CI do GitLab.

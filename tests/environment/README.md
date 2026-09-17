# Thoth descartável para testes do plugin

Este ambiente sobe uma API Thoth real, autenticação Zitadel e duas bases PostgreSQL.
O bootstrap cria uma editora, um selo e uma conta de serviço restrita a essa editora,
com `PUBLISHER_USER` e `WORK_LIFECYCLE`. Não usa credenciais nem APIs públicas da Thoth.
Também prepara um OMP descartável para executar os cenários Cypress descritos abaixo.

## Uso local

Requisitos: Docker Engine com Compose v2 ou posterior e Python 3.9 ou posterior.
A imagem da Thoth é Linux/amd64; outros hosts precisam de emulação ou imagem compatível.
Executar a partir da raiz desta worktree:

```sh
# Mostra o plano, sem modificar o ambiente.
python3 tests/environment/environment.py up

# Constrói a imagem auxiliar, sobe e configura os serviços e executa a prova da API.
python3 tests/environment/environment.py up --apply

# Consulta os containers e repete criação/consulta/atualização/exclusão pela API.
python3 tests/environment/environment.py status
python3 tests/environment/environment.py smoke --apply

# Remove somente os containers, volumes, rede e credenciais desta worktree.
python3 tests/environment/environment.py down --apply
```

A API fica em `http://127.0.0.1:18000/graphql`. Usar `up --apply --port 18001`
para outro ambiente simultâneo. Cada diretório tem seu próprio projeto Compose.
As credenciais e IDs ficam em `tests/environment/.state/client.json`, com permissão
0600 e diretório 0700. Esse arquivo está excluído do Git e do contexto de build.
O token do cliente expira em dois dias; recriar o ambiente após esse prazo.

O bootstrap não é idempotente. Não reiniciar containers individualmente nem tentar
reaproveitar seus bancos: usar `down --apply` e depois `up --apply`. Após falha na
preparação, os recursos ficam disponíveis para diagnóstico; o mesmo comando `down`
faz o descarte. A imagem construída fica em cache, para acelerar a próxima criação.
Cada execução do comando Cypress restaura o dataset OMP; cada caso cria livros próprios
com identificadores únicos, sem depender dos dados deixados pelos outros casos.

## Componentes e isolamento

- Thoth 1.8.0: imagem oficial fixada pelo digest amd64. O commit de origem da imagem
  é `9ae1e56714096507d2d210b5b315361d626626c9`; sua árvore Git é idêntica à do clone
  `4fa7eaa9ccb60d39c41ccd8feb257edf28c173ff` inspecionado nesta tarefa.
- Zitadel 3.2.2 e PostgreSQL 17: imagens fixadas por digest.
- Python: inicializadores e verificações usando somente a biblioteca padrão.
- Nginx: gateway local fixado por digest, publicando somente no loopback.

A rede dos bancos, Zitadel e Thoth é interna. O gateway conecta essa rede a uma
segunda rede para publicar a porta no host; ele encaminha somente para a API local.
A API não recebe saída externa por essa ligação. O gateway não é necessário na CI.
Não configurar hospedagem de arquivos: as credenciais AWS são fictícias, não há
buckets/CDN e os testes desta etapa cobrem apenas metadados. Distribuição automática
fica desativada. Não usar este ambiente para dados ou credenciais reais.

## Inicialização e prova

O serviço Zitadel espera PostgreSQL e cria o diretório do PAT antes da inicialização.
O serviço Thoth espera a readiness do Zitadel, executa o setup upstream, cria a conta
restrita, inicia migrations/API e cria as fixtures. Só escreve `ready` após o smoke.
O PAT administrativo é removido do volume após esse processo. O OMP recebe somente
o token restrito. O bootstrap captura a chave privada sem imprimi-la.

O smoke confere que a conta não é SUPERUSER, vê somente a editora esperada, possui
permissão de lifecycle, rejeita escrita anônima e cria/consulta/atualiza/exclui uma
monografia. A monografia de prova é excluída; a editora e o selo permanecem disponíveis.
Isso ainda não prova publicação/ativação, todos os metadados do plugin, upload,
interface OMP ou comportamento do navegador.

## Cenários Cypress

O comando abaixo prepara um OMP descartável em outro container, com banco MySQL
`thoth_cypress` no serviço `omp-db`, sem portas publicadas. O dump deve ser o
dataset MySQL de OMP `stable-3_5_0`, contexto `publicknowledge` (Public Knowledge
Press), acompanhado dos diretórios `files/` e `public/` do mesmo snapshot.
Nenhum checkout ou banco OMP do host é alterado.

```sh
python3 tests/environment/environment.py up --apply
python3 tests/environment/environment.py cypress \
  --dataset /home/lepidus/Work/pkp/datasets/omp/stable-3_5_0/mysql
# Após conferir o plano, adicionar --apply ao comando acima.
python3 tests/environment/environment.py down --apply
```

A suíte cobre quatro jornadas pela interface, com livros únicos por caso:

- **Registro pelo botão:** registra uma monografia publicada e confere o vínculo,
  título, tipo, selo e estado Active na Thoth.
- **Registro ao publicar:** publica um rascunho com consentimento de registro e selo,
  conferindo a publicação no OMP e a obra Active na Thoth.
- **Registro em massa:** seleciona dois de três livros de um grupo próprio e confirma
  os registros; o terceiro permanece sem requisição de registro e sem vínculo.
- **Atualização de metadados:** sincroniza pelo botão um rascunho já vinculado cuja
  obra remota tem título antigo; confere o título atualizado no mesmo identificador.

O runner executa a suíte duas vezes sem restaurar dados entre elas. Não há mocks
nas respostas da Thoth. Para preparar o caso de atualização, a fixture cria uma obra
Forthcoming com título antigo na API descartável e persiste seu vínculo no OMP.

A configuração e os comandos Cypress são os do OMP. O router PHP exclusivo de
testes injeta o cliente HTTP real conectado a `http://api:8000`; as regras de URL
do plugin em produção permanecem intactas. O token fica fora da raiz web e não é
enviado ao Cypress. Configuração do plugin e criação da fixture são precondições,
não comportamentos cobertos por esses cenários.

As obras Active não podem ser excluídas pelo usuário restrito. Os dados criados
permanecem somente nos bancos descartáveis até `down --apply`, que remove todo o
projeto. O container Cypress fica disponível após o término para inspecionar
`/tmp/thoth-omp-server.log` e screenshots em `/var/www/omp/cypress/screenshots`.
Não reutilizar o banco descartável como instalação de desenvolvimento.

A organização acompanha o plugin `customQuestions`:

- `cypress/tests/functional/*.cy.js`: uma jornada por arquivo e suas assertions;
- `cypress/support/thoth.js`: helpers de preparação e consulta;
- `cypress/support/ThothTestData.php`: criação da fixture e leitura do vínculo/API;
- `tests/environment/`: infraestrutura Docker, configuração e inicialização.

A configuração Cypress continua pertencendo ao OMP. A pipeline regular inclui
`templates/groups/omp/cypress_tests.yml` e adapta `plugin_integration_tests_omp`
em `.gitlab/thoth-cypress.yml`. Reutiliza regras, cache, validação de dependências
e artefatos do template; altera somente serviços e preparação/execução necessários
à Thoth. A suíte completa `omp_integration_tests` fica desativada.

O build BuildKit em `.pre` publica a imagem auxiliar por SHA; o Cypress aguarda
esse job e o bootstrap dos serviços Thoth/Zitadel/PostgreSQL. Um MySQL separado
recebe `/tmp/dump.sql` e os arquivos correspondentes, já incluídos na imagem OMP
fixada. O entrypoint compartilhado aceita `--image-dataset` nesse caso. O token
restrito é copiado para `/thoth-state` fora da raiz web e removido em `after_script`.
Os dois runs geram XMLs JUnit separados; screenshots e logs seguem o contrato
de artefatos compartilhado. A CI usa rede exclusiva por job, sem Docker-in-Docker.

O scanner de segredos mantém as regras padrão. `.gitleaksignore` identifica apenas
os três achados históricos da URL fictícia do PostgreSQL por commit/arquivo/regra/linha;
a definição atual em YAML possui comentário `gitleaks:allow`. Não exclui arquivos
inteiros nem regras de detecção. Esta instância não habilita rulesets personalizados.

Validação em 17/09/2026: OMP 3.5 da imagem fixada, PHP 8.4.23, Node 20.19.2,
Cypress 14.5.4 e Electron 130 headless. A execução consulta a Thoth real nos quatro
cenários e repete a suíte sem restaurar os dados entre as execuções.
O dataset usado foi `omp/stable-3_5_0/mysql` do checkout local de datasets PKP.

## Prova opt-in no GitLab

A raiz `.gitlab-ci.yml` carrega `.gitlab/thoth-environment.yml` somente quando
`THOTH_ENVIRONMENT_PROBE=1`. Sem essa variável, executa os templates normais, incluindo o Cypress integrado.
Não é necessário mudar os templates compartilhados.

- Build compartilhado com Cypress: runner com tag `buildkit-rootless`, construção sem Docker-in-Docker e push
  da imagem auxiliar para `$CI_REGISTRY_IMAGE/test-environment:$CI_COMMIT_SHA`.
- Prova: imagem OMP 3.5 já adotada pela CI, runner com tag `atualizacoes2`, dois
  PostgreSQLs, Zitadel e Thoth como serviços. `FF_NETWORK_PER_BUILD=true` permite
  comunicação entre serviços do job.
- As credenciais passam pelo diretório compartilhado `/builds/thoth-environment-$CI_JOB_ID`,
  fora do checkout, e são removidas no `after_script`. Não são publicadas como artefatos.
- A chave de criptografia do Zitadel e as senhas de banco na CI são valores públicos
  exclusivos deste ambiente efêmero; os tokens de acesso são gerados no bootstrap.
- A imagem de teste publicada fica no registry para reutilização/auditoria. A política
  de retenção do registry deve ser configurada separadamente; o job não apaga imagens.

A rede por job não bloqueia saída externa como a rede interna do Compose. Os
scripts só usam os aliases locais, mas não afirmamos isolamento de egress na CI.
A saída de cgroup informa somente os limites visíveis ao container do job; não mede
os limites dos serviços nem a RAM livre do host. Esta prova não mede capacidade
para Cypress concorrente com todos os serviços.

O cliente Python da prova usa GraphQL diretamente. O cliente PHP e o override
exclusivo de testes para permitir a URL privada no plugin ficam para a integração
com o OMP. O validador de URL de produção permanece intacto.

## Verificações rápidas

```sh
python3 -m unittest discover -s tests/environment -p 'test_*.py' -v
```

Esses testes cobrem planejamento sem mutações, rejeição de porta inválida e erros
HTTP/GraphQL. A prova real depende de `up --apply` e `smoke --apply`.

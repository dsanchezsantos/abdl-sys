### A Arquitetura do Passo 1: O Cliente `NowigoService`

Em vez de espalhar chamadas de API (`Http::get(...)`) por todo o código, nós vamos criar uma **Service Class** dedicada (ex: `NowigoService`). Ela será a única parte do seu sistema que sabe falar com a Nowigo. O resto do sistema apenas "pede" os dados a este serviço.

Aqui estão as 3 frentes de implementação refinadas para esta classe:

#### 1. A Formatação da Requisição (Sem Auth, com Inteligência)
Já que não há um *Bearer Token* ou cabeçalhos complexos de segurança, o segredo aqui é a **construção dinâmica da URL/Query**.
*   **Injeção de Dependência:** O seu `NowigoService` deve ser instanciado recebendo o Model `Feira`. Assim, o serviço já nasce sabendo quem é o `evento_id_api` e o `user_id_api`.
*   **Construção Limpa:** A classe usará a Facade `Http` do Laravel. Como os parâmetros são abertos, o serviço vai montar os parâmetros de query (`?eventoId=123&userId=456&page=X`) automaticamente nos bastidores.
*   **O "Padrão de Fábrica":** O seu *Worker* na fila nunca vai saber qual é a URL da Nowigo. Ele fará apenas algo limpo como: `$dados = $nowigoService->buscarPagina(5);`. Se a Nowigo mudar a URL no futuro, você só altera o código em um único ficheiro.

#### 2. O "Bom Cidadão da Rede" (Lidando com a falta de Rate Limit)
O fato de a API não bloquear acessos rápidos é ótimo para a velocidade, **mas é perigoso para a sua VPS e para a estabilidade da rede**. Se o seu Worker tentar disparar 100 requisições num único segundo, o servidor da Nowigo pode não bloquear intencionalmente, mas a infraestrutura de rede (deles ou a sua) pode dropar pacotes (*Timeout*).
*   **A Abordagem Refinada:** Nós implementaremos no `NowigoService` uma funcionalidade nativa do Laravel chamada **`retry()`** (Tentativas Automáticas com Backoff).
*   Se a requisição der um pequeno tropeço na rede, o serviço não falha o Job na hora. Ele espera 1 segundo e tenta de novo. Se falhar, espera 3 segundos e tenta a terceira vez. Se falhar na terceira, aí sim ele declara "A API caiu". Isso salva o sistema de 90% das instabilidades momentâneas da internet.

#### 3. O Sistema de Alertas por E-mail (O Gatilho Anti-Spam)
A sua ideia de enviar um e-mail é essencial para a auditoria, mas precisamos de muito cuidado com o **Efeito Avalanche**. 
Se a Nowigo ficar offline por 1 hora e você tiver 500 Mini-Jobs na fila tentando baixar páginas, o seu sistema dispararia 500 e-mails de erro seguidos. O seu provedor de e-mail (Mailpit/AWS SES) bloquearia a sua conta por *Spam*.

*   **A Abordagem Refinada (Circuit Breaker / Cache Lock):** 
    Quando o `NowigoService` esgotar as tentativas (citadas no ponto 2) e confirmar que a API está morta (Erro 500 ou Timeout), ele fará duas coisas:
    1.  **Lança uma Exceção Crítica:** O serviço grita "Erro de API!", o que faz o Worker do Laravel pausar o lote atual e marcar a tarefa como "Falha".
    2.  **Notificação Inteligente (Throttling):** Antes de disparar o e-mail para o administrador, o código vai olhar no Cache do servidor: *"Eu já enviei um e-mail de 'API Fora do Ar' nos últimos 30 minutos?"*.
    *   Se NÃO: Ele dispara o e-mail (ex: *"Alerta: A extração da Feira XYZ falhou pois a API Nowigo não está a responder"*) e cria o bloqueio no cache por 30 minutos.
    *   Se SIM: Ele apenas falha a tarefa silenciosamente, sabendo que a equipe técnica já foi avisada.

---

### A Arquitetura do Passo 2: Infraestrutura de Filas via Contêineres

Para que a nossa estratégia de "Fila Única e Segura" funcione perfeitamente, a configuração do ambiente deve seguir uma abordagem de **Isolamento e Proteção em Dupla Camada (Double Layer Protection)**.

#### 1. O Isolamento de Serviços (Containers Separados)
Nunca devemos rodar o *Worker* da fila no mesmo serviço/contêiner que responde às requisições Web (Nginx/PHP-FPM). 
*   **A Regra:** A sua configuração de infraestrutura deve prever a subida de um contêiner exclusivo e dedicado apenas para processar a fila (ex: um serviço chamado `queue-worker`).
*   **O Ganho:** Se por algum motivo catastrófico a fila travar ou o contêiner reiniciar, o painel React (Frontend) e a API continuam respondendo instantaneamente para o utilizador, pois rodam em ambientes isolados, consumindo fatias separadas da CPU.

#### 2. Proteção em Dupla Camada (Soft Limit e Hard Limit)
Aqui nós aplicamos os seus limites de forma inteligente para que o sistema se regenere sozinho sem causar alertas falsos.

*   **A Camada 1 (Soft Limit - A Saída Educada):** No comando de inicialização do contêiner (o `entrypoint` ou o comando do *Supervisor*), nós passamos os parâmetros para o Laravel: `php artisan queue:work --memory=128 --max-jobs=500`. Isso instrui o PHP a observar a si mesmo. Ao atingir esses marcos, ele finaliza o lote com calma, diz "tchau" para o banco de dados e morre de forma limpa.
*   **A Camada 2 (Hard Limit - O Machado do Docker):** Na configuração do seu orquestrador, você travará fisicamente o limite de memória desse serviço (ex: `deploy.resources.limits.memory: 256M`). Este limite deve ser sempre um pouco maior que o *Soft Limit* do PHP. 
*   **O Ganho:** Se houver um vazamento de memória absurdamente rápido (Memory Leak) que ignore o aviso do Laravel, o orquestrador entra em ação e executa um *OOM Kill* (Out of Memory Kill), derrubando apenas o contêiner do Worker e protegendo a integridade de toda a sua VPS.

#### 3. A Trava de Fila Indiana (Single Worker Mecânico)
Para garantirmos que os nossos recursos não se esgotem com requisições simultâneas:
*   A configuração do orquestrador deve ter uma declaração estrita de escala: as réplicas desse contêiner devem estar sempre travadas em `1`. 
*   Se o contêiner do Worker for reiniciado (seja pelo limite de Jobs ou por uma falha de rede), o Docker garante que exatamente *um* novo contêiner nasça imediatamente para substituí-lo. A "fila indiana" nunca é quebrada.

---

### A Arquitetura do Passo 3: Gatilho, Mutex e Blindagem

Este passo estabelece a fronteira entre a ação humana (o clique no painel React) e o início do processamento assíncrono (o *Worker* no servidor).

#### 1. A Trava de Segurança Segura (O Mutex no Banco de Dados)
A mudança de status para `is_sincronizando = true` não pode ser uma simples atualização, pois existe o risco de *Race Condition* (exemplo: o utilizador clica duas vezes super rápido no botão "Sincronizar" antes de a tela atualizar, e o sistema tenta disparar dois processamentos simultâneos).

*   **A Abordagem (Pessimistic Locking):** O Controller responsável pela rota de sincronização utilizará uma funcionalidade do banco chamada "Bloqueio Pessimista" (`lockForUpdate()`).
*   **A Lógica:** Quando a requisição chega, o banco de dados "tranca" a linha daquela Feira. O sistema verifica: *"Esta feira já está com `is_sincronizando = true`?"*.
    *   Se SIM: O sistema aborta e avisa: "Já existe uma sincronização em andamento".
    *   Se NÃO: O sistema muda para `true`, guarda no banco, e destranca a linha. Isso garante que, não importa quantos cliques duplos ocorram, apenas a primeira requisição passará.
*   **A Retomada:** Como você bem pontuou, se o processo for uma retomada após uma falha anterior, o sistema fará a mesma validação e reativará a trava de sincronização para proteger a nova tentativa.

#### 2. A Blindagem do Sistema (Modo "Somente Leitura" Temporário)
Para impedir que o utilizador concorra por processamento e memória na VPS enquanto o ETL trabalha, nós vamos aplicar um padrão de **Degradação Elegante de Serviço**.

*   **A Camada Visual (React/Inertia):** 
    *   Assim que o status da feira for detectado como `is_sincronizando = true`, o Dashboard entra em um modo "Somente Leitura".
    *   Um *Banner* global ou *Toast* fixo aparece: *"Sincronização em andamento. Para garantir a estabilidade do servidor, algumas funções estão pausadas."*
    *   Botões pesados (como "Gerar Relatório Final PDF" ou a edição em massa do catálogo de livros) ficam desabilitados (cinzentos).
*   **A Camada de Segurança (Laravel Middleware):** 
    *   A interface gráfica é fácil de burlar. Por isso, criaremos um **Middleware** no Laravel (ex: `CheckFeiraNotSyncing`). 
    *   Nós envolveremos as rotas pesadas (como a de gerar PDFs) com este Middleware. Se o frontend tentar forçar uma requisição pesada enquanto a feira estiver travada, o Middleware intercepta a requisição e devolve um *Erro 423 (Locked)*, poupando a CPU da VPS instantaneamente.

#### 3. O Despacho Seguro (Injetando o Maestro na Fila)
Colocar a tarefa na fila do Redis é uma operação de infraestrutura e pode falhar. Se falhar, não podemos deixar a feira travada para sempre.

*   **A Abordagem (Try/Catch e Rollback):** O despacho será encapsulado numa estrutura de tratamento de erros.
    1.  O sistema muda o `is_sincronizando` para `true` no banco.
    2.  Dentro de um bloco `try`, o Controller usa o *Facade* `Queue` ou o método `dispatch()` para enviar o `MaestroJob` (passando apenas o ID da feira para economizar memória na fila, nunca o objeto inteiro).
    3.  Se a conexão com a fila (Redis) falhar, o bloco `catch` é ativado. Ele imediatamente reverte o `is_sincronizando` para `false` no banco e exibe uma mensagem de erro na interface: *"Falha ao conectar com o motor de processamento. Tente novamente."* Isso evita que a feira fique num estado "zumbi".

---

### Passo 3: O Gatilho, a Blindagem e o Polling Inteligente

Esta etapa desenha a fronteira exata entre a interação do utilizador e o início do trabalho pesado no servidor. O objetivo é garantir que o processo inicie com segurança absoluta, que a VPS seja protegida durante a execução e que o utilizador seja mantido informado sem sobrecarregar a infraestrutura.

#### 1. A Trava de Segurança no Banco (Mutex e Bloqueio Pessimista)
A mudança de status para iniciar a sincronização não pode ser uma edição comum, pois o sistema precisa estar imune a cliques duplos acidentais ou acessos simultâneos mal-intencionados.
*   **Bloqueio Pessimista (`lockForUpdate`):** O Controller do Laravel deve abrir uma transação no banco de dados e aplicar um bloqueio físico na linha referente àquela Feira.
*   **A Validação de Estado:** Com a linha trancada, o sistema avalia: a flag `is_sincronizando` já é `true`? 
    *   Se sim, a requisição é imediatamente abortada com um aviso de que já existe um processo em andamento.
    *   Se não, a flag é alterada para `true`, a transação é confirmada e a linha é destrancada. Esta é a garantia matemática de que apenas uma sincronização rodará por vez.

#### 2. O Despacho Seguro (Proteção contra Falhas de Infraestrutura)
Colocar a tarefa na fila (Redis) é uma operação de rede. Se o serviço de filas estiver inoperante naquele milissegundo, não podemos deixar a feira "zumbi" (travada para sempre com `is_sincronizando = true`).
*   **Isolamento (Try/Catch):** O disparo do Job (o "Maestro") deve ocorrer dentro de um bloco de tratamento de exceções.
*   **Economia de Memória:** O sistema jamais deve passar o objeto inteiro da `Feira` para a fila, apenas o seu identificador (`ID`). O Worker fará a busca do objeto completo na sua própria memória isolada.
*   **O Rollback de Resgate:** Se a conexão com o Redis falhar, o bloco `catch` atua como salva-vidas, revertendo imediatamente a flag `is_sincronizando` para `false` no banco e enviando um erro amigável ao utilizador: *"Não foi possível conectar ao motor de processamento no momento."*

#### 3. A Blindagem do Backend (Degradação Elegante de Serviço)
Enquanto a VPS trabalha extraindo os dados da API da Nowigo, o servidor precisa dos seus recursos livres.
*   **O Middleware Guarda-Costas:** Devemos criar um Middleware no Laravel (ex: `CheckFeiraNotSyncing`) para envelopar todas as rotas de alto custo computacional (como a geração de PDFs com o Gotenberg ou edições em massa no catálogo).
*   **A Ação:** Se qualquer requisição tentar acessar essas funcionalidades enquanto a feira estiver travada (`is_sincronizando = true`), o Middleware bloqueia o acesso e retorna um código de erro *423 Locked*, protegendo a CPU do servidor.

#### 4. O Polling Inteligente e a Reação do Frontend (Inertia.js)
Esta é a camada visual e interativa. O sistema precisa dar a sensação de "tempo real" sem o custo de infraestrutura dos WebSockets.
*   **A Reação Inicial Visual:** Assim que o Dashboard detecta que a feira possui `is_sincronizando: true`, botões de ações críticas são desabilitados (cinzentos) e um aviso visual (Banner ou Toast) informa que as operações pesadas estão temporariamente pausadas.
*   **O Gatilho do Polling (O Temporizador):** O React inicia um `setInterval` nativo (um temporizador) para rodar a cada 5 ou 10 segundos.
*   **O Pedido Microscópico (Partial Reload):** A cada ciclo do temporizador, o React dispara um *Partial Reload* nativo do Inertia (`router.reload({ only: ['feira'] })`). Este pedido vai ao backend e solicita única e exclusivamente o objeto atualizado da feira. Como é uma busca simples pela chave primária, o custo para o servidor é de 1 a 2 milissegundos.
*   **O Encerramento e o Êxito:** Quando a fila terminar todo o processamento e o último Job alterar a flag `is_sincronizando` para `false` no banco de dados, o próximo "ping" do Inertia capturará essa mudança. O React então:
    1.  Destrói o temporizador, cessando as requisições.
    2.  Dispara o *Toast* verde de sucesso.
    3.  Desbloqueia os botões e recarrega os novos dados gráficos na tela.

---

### Passo 4: O "Maestro" (Descobridor, Analista e Criador de Lotes)

O Job "Maestro" (`SincronizarFeiraMaestroJob`) é a primeira engrenagem a rodar no background após o utilizador clicar no botão. Ele não processa a feira inteira; o seu único propósito é investigar o cenário, calibrar o motor e enfileirar o trabalho pesado.

#### 1. A Sondagem Inteligente (Smart Probe & Telemetria)
Em vez de simplesmente perguntar "quantas páginas existem?", o Maestro atua como um batedor. Ele utiliza o serviço HTTP (construído no Passo 1) para fazer a requisição exata da **Página 1**, informando um limite conservador de itens (ex: `perPage = 100`).

*   **A Captura de Metadados:** Ao receber o JSON, o Maestro extrai os nós `totalItems` e `totalPages`. Ele descobre exatamente o tamanho do universo de vendas daquela feira.
*   **O Benchmarking Interno:** Antes e depois da requisição, o Maestro regista os *timestamps* para calcular o tempo exato em milissegundos que a Nowigo demorou a entregar aqueles 100 itens.
*   **O Ajuste Dinâmico (Dynamic Pacing):** Com base nesse tempo, o Maestro toma uma decisão de alocação de recursos:
    *   Se a resposta foi ultrarrápida (ex: menos de 300ms), a rede está excelente. O Maestro decide aumentar o `perPage` para 500 nas próximas requisições, diminuindo a quantidade total de viagens à rede.
    *   Se a resposta demorou (ex: mais de 2 segundos), a API ou a rota estão congestionadas. O Maestro mantém o `perPage` em 100 (ou até reduz para 50) para evitar que os próximos *Jobs* estourem por *Timeout*.
*   **Recálculo de Páginas:** Após decidir o novo tamanho ideal de lote (`perPage` dinâmico), o Maestro faz uma matemática simples (`totalItems` subtraído da página 1, dividido pelo novo `perPage`) para saber quantas páginas *reais* ainda faltam buscar.

#### 2. O Aproveitamento da Carga Inicial (Database Seeding)
A resposta da Página 1, que o Maestro usou para o benchmarking, já contém dados reais (as primeiras dezenas de vendas). 
Para não desperdiçar o processamento e a viagem à rede, o Maestro não deita este JSON fora. Antes de criar os lotes para as outras páginas, ele despacha essa carga inicial da Página 1 diretamente para as tabelas do banco de dados.

#### 3. A Orquestração Sênior com `Bus::batch()` (O Loteamento)
Com o universo de dados mapeado e a Página 1 salva, o Maestro precisa criar os dezenas (ou centenas) de "Mini-Jobs" para o resto das páginas. É aqui que entra o poder nativo do Laravel.

O Maestro cria um *Array* vazio e entra num laço de repetição (loop) começando da Página 2 até a última página calculada. Em cada volta do laço, ele empurra para o Array um `ProcessarPaginaVendaJob`, informando apenas os parâmetros leves: ID da Feira, Número da Página Alvo e o `perPage` dinâmico que ele decidiu usar.

Após preencher o Array com todas as páginas, o Maestro utiliza a funcionalidade de Lotes do Laravel (`Bus::batch`) para despachar tudo para a fila. A genialidade do Batching está nos **Callbacks de Ciclo de Vida**, que permitem programar o que acontece no futuro:

*   **O Despacho em Lote:** O Maestro informa ao Laravel: *"Aqui estão 50 Jobs. Execute-os em fila indiana."*
*   **A Corrente de Sucesso (`->then()`):** O Maestro programa a ação seguinte. Ele diz ao sistema: *"Quando, e somente quando, todos os 50 Jobs terminarem com 100% de sucesso, execute o Job de Calcular as Estatísticas."* Este é o elo perfeito para não sobrecarregar o banco, pois o cálculo só roda no fim da extração inteira.
*   **A Proteção Contra Falhas (`->catch()`):** Se a API cair definitivamente na página 14, e os *retries* (tentativas) acabarem, o Batch interrompe tudo. O callback de erro atua acionando o nosso serviço de notificação (para avisar do erro) e altera o `is_sincronizando` no banco para `false`, impedindo que a feira fique travada permanentemente.
*   **A Limpeza (`->finally()`):** Independentemente de sucesso ou falha, o Maestro pode engatilhar lógicas de limpeza, como gravar nos logs o tempo total que toda a operação demorou.

---

### A Arquitetura do Passo 5: O "Micro-Trator" e a Transação

#### Fase 1: O Pre-processamento em Memória (Sem tocar no banco)
Antes de abrir qualquer transação no banco de dados, o *Worker* deve ser rápido e trabalhar apenas na memória RAM (que nós já sabemos que está controlada e limpa a cada Job).
O Job pega o JSON daquela página e o desmonta, separando os dados em 4 "baldes" (arrays) distintos:
1.  **Balde de Cartões (Descoberta):** Procura todas as tags únicas dentro dos pagamentos.
2.  **Balde de VendaHeader:** Formata os cabeçalhos das vendas.
3.  **Balde de Pagamentos:** Acumula todos os pagamentos de todas as vendas da página.
4.  **Balde de Itens:** Acumula todos os itens de todas as vendas da página.

*Por que isso é sênior?* Porque reduziremos milhares de operações de banco de dados para **exatamente 5 queries** por página.

#### Fase 2: O Cofre (A Transação de Banco de Dados)
Com os 4 baldes cheios, o Job abre a transação (`DB::transaction`). A regra da transação é atômica: **"Tudo ou Nada"**. Se a energia da VPS cair no milissegundo 3, o PostgreSQL descarta tudo e a página inteira volta para a estaca zero, garantindo 100% de integridade financeira.

Dentro do cofre (`DB::transaction`), a ordem exata de execução deve respeitar a hierarquia dos dados (Foreign Keys) e a regra de Idempotência:

**1º Passo: Descoberta e Inserção de Cartões (A Base)**
*   A primeira query é um `Upsert` (ou `Insert Ignore`) do Balde de Cartões.
*   *Motivo:* Como a tabela de pagamentos terá uma relação com o cartão (`tag_code`), os cartões devem existir no banco antes de tentarmos inserir os pagamentos, evitando erros de restrição de chave estrangeira (Foreign Key Constraint).

**2º Passo: O Upsert dos Cabeçalhos (`VendaHeader`)**
*   A segunda query pega o Balde de Cabeçalhos e faz um `Upsert` em lote. Se o `sell_number` não existe, ele cria. Se já existe (numa re-sincronização), ele apenas atualiza o valor total e altera a flag `processado` para `true`.

**3º Passo: A Limpeza (Idempotência dos Detalhes)**
*   A terceira e quarta queries garantem que não haverá duplicação financeira. O Job extrai apenas a lista de `sell_number` (os IDs das vendas) que estão nesta página.
*   O sistema executa um `DELETE FROM pagamentos WHERE sell_number IN (...)` e um `DELETE FROM itens_venda WHERE sell_number IN (...)`.
*   *Motivo:* Isso limpa a lousa. Apaga qualquer rastro antigo (se houver) apenas daquelas vendas específicas, abrindo espaço para a "fotografia" mais recente da API.

**4º Passo: A Inserção em Massa (`Insert` dos Detalhes)**
*   A quinta e sexta queries finalizam o processo. O sistema pega o Balde de Pagamentos e o Balde de Itens e executa um `Insert` em lote (`DB::table('...')->insert($balde)`).
*   Como as lousas foram limpas no passo anterior, este *Insert* entra liso, rápido e sem risco de violação de chaves únicas.

#### Fase 3: O Fechamento e Tratamento de Exceções
Ao final da linha 6, o Laravel fecha automaticamente a transação (`Commit`). Os dados passam a existir oficialmente no banco.

**E se algo der errado (O `catch` e a "Dead Letter Queue"):**
*   Se o JSON vier corrompido ou houver uma falha de tipagem no banco, o bloco `catch` entra em ação.
*   A transação sofre `Rollback` (nenhum dado pela metade é salvo).
*   **O Toque Sênior:** Em vez de simplesmente falhar e parar o lote inteiro do "Maestro", este Mini-Job específico relata o erro no seu sistema de Logs (e dispara o alerta de e-mail), mas marca a si mesmo como `Failed` e permite que o lote (*Batch*) continue processando as outras páginas intactas. Esta página problemática pode ser reprocessada manualmente depois (o conceito de *Dead Letter Queue* - Fila de Mensagens Mortas).

---

### A Arquitetura do Passo 6: O Fechamento da Corrente e a Libertação

#### 1. A Notificação Orgânica (Sem Polling de Banco)
O sistema não precisa (e não deve) ficar consultando o banco de dados para perguntar: *"Já acabaram todos os Mini-Jobs?"*. Isso consome recursos à toa.
*   **A Abordagem:** Lembra-se de que no Passo 4 o "Maestro" despachou tudo usando `Bus::batch()`? O próprio Redis e o motor do Laravel gerem internamente a contagem de tarefas.
*   **O Gatilho:** Quando o contador interno chegar a zero (ou seja, o último `ProcessarPaginaVendaJob` retornar *success*), o Laravel dispara automaticamente o evento de fechamento, chamando a função anónima dentro do método `->then()` que definimos no despacho inicial.

#### 2. O Motor de Estatísticas (Delegação, não Execução)
Um erro comum de arquitetura é colocar lógicas pesadas diretamente dentro da função de encerramento do lote (`then(function() { ... })`). Isso é perigoso porque closures complexas em filas podem sofrer problemas de serialização ou esgotar limites de tempo (Timeout) genéricos.
*   **A Abordagem Sênior (Single Responsibility):** O callback `then()` não vai calcular absolutamente nada. O único trabalho dele será despachar um Job final e isolado, chamado `CalcularEstatisticasFeiraJob`.
*   **O Cálculo no Banco:** Este Job final pega o ID da feira e delega a matemática para o banco de dados. Em vez de trazer 50.000 registros para a RAM do PHP, ele roda as *Queries* com o "Filtro de Ouro" que criamos no Model (`SUM`, `COUNT` com os *Scopes* de exclusão lógica). O PostgreSQL resolve isso em milissegundos.
*   **A Atualização:** Após obter os números, o Job invoca aquele método `FeiraEstatistica::atualizarSnapshot()` que arquitetamos, atualizando a "fotografia" do painel.

#### 3. A Libertação do Bloqueio (O Acordo de Cavalheiros)
O desbloqueio da feira (`is_sincronizando = false`) é a operação mais crítica deste passo. Se ela falhar, a feira torna-se num "Zumbi" (bloqueada para sempre no frontend).

*   **O Erro Comum:** Colocar o destravamento apenas no final do Job de estatísticas. E se a API da Nowigo der erro na metade do lote e o sistema abortar? O Job de estatísticas nunca vai rodar e a feira ficará travada eternamente.
*   **A Abordagem Sênior (A Garantia do `finally`):** A arquitetura de lotes possui três ganchos principais: `then()` (Sucesso total), `catch()` (Uma ou mais falhas críticas) e `finally()` (Executa no final, independentemente de ter sido sucesso ou fracasso absoluto).
*   **A Execução:** Nós não mudamos o status da feira dentro dos Jobs. Nós programamos o callback `finally()` do `Bus::batch` para ser o "Porteiro". Quando a poeira assentar — quer a sincronização tenha sido um sucesso retumbante, quer tenha pegado fogo na metade —, o `finally()` roda, localiza a Feira no banco e força `is_sincronizando = false`.

### O Fluxo Perfeito do Início ao Fim (Resumo do Pipeline)

Veja como o desenho arquitetural que criámos nestes 6 passos flui com segurança cirúrgica:

1. Utilizador clica -> Tranca a Feira (`is_sincronizando = true`) -> React inicia o Polling.
2. Maestro analisa a Página 1, mede o tempo, descobre que existem 50 páginas.
3. Maestro cria o Lote (`Batch`) e programa o futuro (`then` e `finally`).
4. Micro-Tratores começam a rodar, 1 por vez. Esvaziam a RAM. Processam atómicamente com transações no banco.
5. Quando a página 50 acaba, o Lote percebe o fim.
6. O `then()` dispara o cálculo matemático. A tabela `feira_estatisticas` é atualizada num instante.
7. O `finally()` roda logo a seguir e destranca a feira (`is_sincronizando = false`).
8. Dois segundos depois, o Frontend (React) faz o ping automático, percebe o destravamento, cancela o loading e exibe os gráficos impecáveis e atualizados!

---

**Passo 7: A Estratégia de Homologação**:

### Fase 1: O "Smoke Test" (Foco na Lógica de Transformação)
A sua ideia de limitar a 5 ou 10 páginas é perfeita para validar o ETL (Extração, Transformação e Carga) sem poluir o banco de dados desnecessariamente.
*   **O Refinamento Sênior (Comando Isolado):** Em vez de ligar o React e clicar no botão, criaremos um Comando de Terminal no Laravel (ex: `php artisan feira:sync {id_feira} --pages=5`). 
*   **O Ganho:** Isso isola totalmente o frontend. Você roda no terminal e o comando "finge" ser o Maestro, enfileirando apenas 5 páginas. 
*   **A Auditoria:** Durante esta fase no seu ambiente local, ligamos o monitoramento de *Queries* (como o Laravel Telescope ou um `DB::listen`). O objetivo não é ver a velocidade, mas sim provar matematicamente que o "Micro-Trator" está executando as transações na ordem exata e que não existe duplicação de dados (`N+1`).

### Fase 2: O Teste de Estresse (Foco na Infraestrutura e Memória)
Aqui aplicamos a sua ideia de aumentar a carga consideravelmente. Vamos fingir que a feira já tem milhares de vendas.
*   **O Refinamento Sênior (Monitoramento de Contêiner):** Como a aplicação rodará isolada num ambiente de contêineres na sua VPS (como o Docker no seu Ubuntu Server), o objetivo desta fase não é apenas ver se o sistema chega ao fim, mas **como** ele chega ao fim.
*   **O Teste:** Lançamos uma carga de 100 ou 200 páginas na fila. Enquanto o Worker trabalha, você monitora o consumo de RAM do contêiner (usando `docker stats`).
*   **O Ganho:** Você terá a prova visual de que a nossa arquitetura de fragmentação funcionou. A memória subirá ao baixar uma página, mas deverá cair logo a seguir graças ao limite de `--memory=128`. Isso garante que o sistema suporta longas jornadas sem "vazar" memória.

### Fase 3: O Cenário Real e a "Engenharia do Caos" (Foco na Resiliência)
Você propôs rodar o cenário real (o volume total do script original) para dar o carimbo de "pronto". Isso é essencial, mas num sistema sênior, testar o "Caminho Feliz" não basta.
*   **O Refinamento Sênior (Simulação de Tragédia):** Precisamos testar se as nossas travas de segurança (os callbacks `catch` e `finally`) realmente funcionam quando o pior acontece.
*   **O Teste do Caos:** 
    1. Inicie a sincronização completa do cenário real usando o painel React (para testar o *Polling* de 5 em 5 segundos da tela).
    2. Quando o processamento estiver na metade (ex: página 50 de 100), **desligue intencionalmente a internet do seu ambiente local** ou pare o serviço do Redis.
*   **O Ganho:** O teste só será considerado um sucesso absoluto se, perante esta falha catastrófica:
    *   Nenhum dado ficar corrompido no banco (graças ao `Rollback` das transações).
    *   A interface do React receber o aviso de falha e destrancar a tela.
    *   A feira no banco de dados for revertida para `is_sincronizando = false`.

---

### Resumo para a sua IA (O Briefing da IDE):

Instrua a sua IA a preparar o terreno de testes com:
1.  **Artisan Command de Teste:** Um comando como `FeiraSyncTestCommand` que aceita uma *flag* `--limit=X` para sobrescrever o total de páginas apenas durante o desenvolvimento.
2.  **Logs Verbosos:** Garantir que o `ProcessarPaginaVendaJob` grave logs no nível `debug` (ex: `Log::debug('Página 5 concluída. Memória usada: X MB')`) para facilitar a auditoria na Fase 2.
3.  **Preparação de Ambiente:** Configurar o banco de dados local para permitir um `migrate:fresh` rápido entre os testes das Fases 1, 2 e 3.
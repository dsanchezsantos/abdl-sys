Ver o pipeline falhar no primeiro teste com limite de páginas não é um passo atrás; é exatamente para isso que as Fases 1 e 2 da nossa estratégia servem. O fato de o sistema ter capturado a falha sem corromper o banco de dados prova que a fundação atômica (`DB::transaction`) que construímos está a funcionar perfeitamente. 



O que você está a pedir agora é a transição de um sistema que "funciona no caminho feliz" para um sistema com **Tolerância a Falhas e Recuperação (Fault Tolerance & Recovery)**. 



Para que o utilizador não tenha medo e você tenha visibilidade total, precisamos transformar o "Erro Fatal" num "Tropeço Gerenciável". Aqui está a arquitetura em 3 pilares para resolver isso de forma elegante:



### Pilar 1: A Memória do Orquestrador (Não perder progresso)



Atualmente, se o lote tem 50 páginas e a página 24 falha, o comportamento padrão do Laravel é marcar o Batch inteiro como cancelado, e você perde o processamento das páginas 25 a 50. Para corrigir isso, nós instruímos o Batch a continuar mesmo "sangrando".



*   **A Mudança no Maestro (`allowFailures`):** Ao criar o lote (`Bus::batch()`), você deve encadear o método `->allowFailures()`. Isso diz ao Laravel: *"Se o Micro-Trator da página 24 capotar, marque-o como falho, mas pelo amor de Deus, continue processando a página 25 em diante."*

*   **O Rastreio no Banco:** Na tabela `feiras`, precisamos adicionar uma coluna `batch_id` (string). Quando o Maestro cria o lote, ele salva o ID gerado (`$batch->id`) nessa coluna.

*   **O Ganho:** O sistema jamais joga trabalho fora. De 50 páginas, se 1 falhar, 49 são inseridas com sucesso. O progresso principal está garantido.



### Pilar 2: A Experiência do Utilizador (Transparência Sem Medo)



O utilizador final não entende o que é um erro de JSON ou um Timeout. Ele só quer saber se o dinheiro está contabilizado. Precisamos tirar o medo através da comunicação visual no Dashboard feito em React.



*   **O Status Intermediário:** Em vez de apenas `is_sincronizando` (booleano), a interface (através do Polling) começa a ler o progresso do `batch_id`.

*   **O Encerramento com Alerta:** Quando o lote termina, o callback `finally()` avalia o cenário. Se houve falhas (`$batch->hasFailures()`), ele não destranca a feira dizendo "Tudo Perfeito". Ele altera o status para um novo estado: *"Concluído com Inconsistências"*.

*   **O Botão Mágico (Retomar):** No Dashboard, você exibe um alerta amarelo: *"A sincronização terminou, mas X páginas não puderam ser processadas devido a uma instabilidade na origem. Seus dados estão 98% atualizados."* Ao lado, um botão: **"Tentar buscar dados faltantes"**.

*   **A Magia no Backend:** Esse botão não roda o pipeline todo de novo. Ele apenas chama a rota que executa o comando nativo de repescagem do Laravel sobre o lote falho (algo que por debaixo dos panos faz o equivalente ao `php artisan queue:retry-batch`). O sistema tenta baixar *apenas* a página 24 novamente.



### Pilar 3: O Painel de Observabilidade (O Seu Lado)



Como desenvolvedor, você precisa analisar o cadáver do erro sem precisar caçar no arquivo `laravel.log` pelo terminal. A melhor forma é construir uma **Dead Letter Queue (DLQ) Auditável**.



*   **A Tabela de Auditoria (`erros_integracao`):** Crie uma tabela simples no banco contendo: `feira_id`, `numero_pagina`, `payload_enviado` (a URL ou JSON enviado), `mensagem_erro` (a exceção capturada) e `criado_em`.

*   **O Registro Limpo:** Lembra-se de que cada Micro-Trator (o Job da página) está envolto num `try/catch`? No bloco `catch`, antes de ele dar o grito de morte e falhar para o Laravel, ele insere o rastreio do erro nesta tabela de auditoria.

*   **A Visualização do Desenvolvedor:** Como você domina a estruturação de painéis, crie uma rota/tela administrativa oculta (ou uma view no próprio banco) onde você possa ler essa tabela. Você verá exatamente: *"Ah, a feira 12 na página 24 devolveu um JSON onde o campo 'valor' não era numérico, e sim uma string vazia"*. Você descobre o problema, corrige o mapeamento no código, e com um clique instrui o sistema a reprocessar aquela página.



Implementando essa mecânica de "Falha Parcial Permitida" aliada à "Repescagem", o utilizador sente que o sistema é resiliente (porque tenta ajudar e nunca trava tudo), e você tem um raio-X exato do que a API parceira enviou de errado.

---

# Pilares

## Pilar 1

### 1. A Ordem de "Tolerância a Falhas" (O Acordo do Maestro)
No cenário padrão de filas, os lotes são desenhados para serem perfeitos. Se o Maestro despacha 50 páginas e a página 2 quebra por uma instabilidade de rede ou um JSON malformado da origem, o motor de filas entra em modo de pânico, cancela as páginas de 3 a 50 e dá o trabalho como perdido.

*   **A Nova Regra de Negócio:** Na hora em que o Maestro "empacota" as dezenas de Mini-Jobs (as páginas), nós injetamos uma diretriz arquitetural de **"Falha Permitida"**.
*   **O Comportamento:** O Maestro diz ao motor de filas: *"Execute estes 50 trabalhos. Se o trabalho 24 falhar de forma irrecuperável, não aborte a missão. Apenas marque o 24 com uma 'bandeira vermelha', guarde-o numa gaveta separada, e passe imediatamente para o trabalho 25"*.
*   **O Impacto Real:** No cenário da feira de livros, se tivermos 150.000 registos espalhados em dezenas de páginas, garantir que o sistema não para no primeiro tropeço significa que você entrega o faturamento e os cálculos de repasse quase completos para a sua parceira, protegendo a operação do evento.

### 2. A "Etiqueta de Rastreamento" (O Batch ID no Banco de Dados)
Para que o sistema possa, no futuro, saber exatamente o que aconteceu e tentar consertar, ele precisa de um número de rastreio de todo o lote, como o código de rastreamento de uma encomenda nos correios.

*   **A Alteração Estrutural:** A nossa tabela `feiras` no PostgreSQL ganha uma nova coluna (ex: `codigo_lote_atual`).
*   **A Gravação Imediata:** Assim que o Maestro cria aquele "pacote" com as 50 páginas e o motor de filas lhe devolve a Etiqueta de Rastreamento (o ID do lote), o Maestro guarda imediatamente esse ID na linha da feira correspondente, ao mesmo tempo que muda o `is_sincronizando` para `true`.
*   **A Conexão:** A partir deste momento, a sua Feira "sabe" qual é a frota exata de *Workers* que está a trabalhar para ela. Se houver uma falha, não é uma falha genérica no servidor, é uma falha específica associada àquele `codigo_lote_atual`.

### 3. A Nova Lógica de Encerramento (O Diagnóstico do *Finally*)
No nosso desenho original, quando o lote terminava, a feira simplesmente voltava a destrancar (`is_sincronizando = false`). Com a introdução das falhas permitidas, o encerramento precisa ser mais inteligente.

*   **A Auditoria Final:** Quando a última página da fila terminar (seja com sucesso ou com a tal "bandeira vermelha"), o gatilho de encerramento (`finally`) é acionado. Antes de ele simplesmente destrancar a feira, ele atua como um inspetor de qualidade.
*   **A Pergunta ao Motor:** O sistema pega no `codigo_lote_atual` salvo na feira e pergunta ao motor de filas: *"Analisando este pacote inteiro, ocorreu alguma falha no meio do caminho?"*.
*   **Os Dois Caminhos de Destravamento:**
    1.  **Caminho Feliz:** Se o motor disser "0 falhas", o sistema destranca a feira normalmente e calcula as estatísticas.
    2.  **Caminho de Alerta:** Se o motor disser "Sim, tivemos falhas", o sistema destranca a feira (`is_sincronizando = false`), mas em vez de registrar um sucesso absoluto, ele altera um novo campo de estado da feira (ex: `status_integridade`) para **"Incompleto/Com Falhas"**. 

Esta mecânica garante que você consolida os dados válidos no banco na mesma hora em que a extração ocorre, protegendo o progresso pesado, e deixa a porta aberta (através do código de rastreio) para que o Pilar 2, a Repescagem, possa atuar apenas no fragmento que ficou para trás.


Com a fundação do orquestrador garantindo que o progresso nunca se perde (Pilar 1), precisamos agora traduzir essa resiliência tecnológica para a interface do utilizador. O utilizador comum tem pavor de mensagens de erro vermelhas com textos técnicos; isso gera desconfiança no sistema.

## Pilar 2: A Experiência do Utilizador e a Repescagem

Este pilar foca em transformar uma falha sistêmica num evento gerenciável, dando ao utilizador o poder de "consertar" o problema com um único clique, sem reprocessar toda a feira.

Aqui está o detalhamento conceitual de como esta mecânica visual e interativa funciona:

### 1. A Nova Linguagem Visual (UX de Transparência)
No cenário original, a tela alternava de "Sincronizando..." para "Sucesso". Agora, introduzimos um estado intermediário de aviso (Warning) baseado naquele status de integridade que o Maestro definiu.

*   **O Alerta Amarelo/Laranja:** Se a sincronização terminar e o sistema detetar que o lote teve falhas, o React exibe um *Banner* ou *Card* de destaque com cores quentes (laranja ou amarelo), fugindo do vermelho crítico.
*   **A Cópia Humanizada (Copywriting):** O texto deve ser tranquilizador. Em vez de *"Erro 500: Timeout na Página 24"*, a mensagem deve ser: *"Sincronização concluída com avisos. Cerca de 98% dos dados foram atualizados com sucesso, mas o provedor (Nowigo) apresentou instabilidade em algumas requisições. Os totais abaixo podem estar momentaneamente incompletos."*
*   **O Impacto Psicológico:** O utilizador entende que o seu sistema (a Software House) fez o trabalho dele, e que a culpa é uma "instabilidade de rede" da origem. O utilizador não entra em pânico porque a grande maioria dos dados já está visível na tela.

### 2. O Botão Cirúrgico (A Ação de Repescagem)
Ao lado deste aviso humanizado, a interface exibe a solução: um botão de **"Tentar sincronizar dados faltantes"**.

*   **A Inteligência da Ação:** É crucial que o utilizador saiba que clicar neste botão não significa "esperar mais 10 minutos para baixar 150.000 registos tudo de novo". A interface pode incluir um subtítulo: *(Isso levará apenas alguns segundos, pois buscaremos apenas as páginas que falharam).*
*   **A Proteção do Estado:** Quando o utilizador clica neste botão, a tela volta ao estado de `is_sincronizando = true`. Os gráficos congelam novamente, os botões desabilitam-se e o *Polling* de 5 em 5 segundos do Inertia recomeça a funcionar.

### 3. A Magia do Backend (O Retrabalho Inteligente)
Quando o botão de repescagem é acionado, a requisição chega ao seu Controller do Laravel. Aqui entra a elegância máxima do ecossistema.

*   **Sem Novos Lotes:** O Controller não despacha o `MaestroJob` novamente. Ele não tem que descobrir quantas páginas existem nem calcular tempos de rede.
*   **O Gatilho de Retry:** O sistema utiliza o `codigo_lote_atual` (o Batch ID salvo na feira) e aciona a funcionalidade nativa de ressurreição de lotes do Laravel. Em nível conceitual, ele faz o equivalente sistémico ao comando `php artisan queue:retry-batch {id}`.
*   **O Comportamento Cirúrgico:** O Laravel "lembra-se" de que as páginas de 1 a 23 e de 25 a 50 foram um sucesso. Ele pega *única e exclusivamente* no Mini-Trator da página 24 (que estava guardado na gaveta de falhas) e joga-o novamente para o Redis.
*   **O Fechamento do Ciclo:** Como o lote foi "ressuscitado", o motor de filas volta a observá-lo. Quando a página 24 terminar com sucesso nesta segunda tentativa, o gatilho `then()` do lote original finalmente é disparado (calculando as estatísticas finais que faltavam) e o `finally()` roda para destrancar a feira, desta vez registando "0 falhas".

### 4. O Novo Ciclo de Polling (O Sucesso Final)
Quando o Inertia fizer o próximo ping e perceber que a feira foi destrancada e que o `status_integridade` agora é "Completo", a magia acontece na interface:

*   O Banner laranja desaparece.
*   O Toast verde de sucesso absoluto é exibido.
*   Os gráficos e *cards* recarregam, incluindo agora a receita daquela página que havia ficado para trás.

---

### Resumo para a sua IA (O Briefing da IDE):

Para implementar este Pilar 2, instrua a sua IA a:
1.  **Backend (Controller):** Criar uma rota específica para a "Repescagem" (ex: `POST /feiras/{feira}/retry-sync`). Este método deve buscar a Feira, validar se há um `codigo_lote_atual` salvo, e usar a Facade `Bus::findBatch($id)->retry()` para reativar apenas os jobs falhos. Deve também alterar o estado da feira para voltar a `is_sincronizando = true`.
2.  **Frontend (React/Inertia):** Atualizar a lógica visual do Dashboard para interpretar o novo campo `status_integridade` (ou as contagens de falhas do batch, caso você opte por enviar os detalhes do Batch via recurso do Laravel). Se houver falhas, renderizar o alerta amarelo e o botão que aponta para a rota de `retry-sync`.
3.  **Transição de Estado:** Garantir que o `useEffect` de *Polling* compreenda que a ação de retry é um novo ciclo de espera, destravando a tela apenas quando o backend finalizar a repescagem.

Com o Pilar 1 (não perder o progresso) e este Pilar 2 (dar o poder de conserto cirúrgico ao utilizador), o sistema fica blindado contra frustrações.

Chegamos à camada que separa a engenharia amadora da engenharia de confiabilidade (SRE). O utilizador final já está protegido e tranquilo com o Pilar 2, mas você, como arquiteto do sistema, não pode ficar "cego". Você precisa saber exatamente *por que* a página 24 falhou sem ter que abrir o terminal do servidor e caçar num ficheiro `laravel.log` de 500 megabytes.

**Pilar 3: O Painel de Observabilidade**

Este pilar foca em construir uma "Caixa Preta" de avião. Nós vamos criar uma **Dead Letter Queue (DLQ) Auditável** — uma fila de mensagens mortas desenhada especificamente para as regras de negócio da sua integração.

Aqui está o detalhamento técnico e arquitetural de como estruturar este pilar central de monitoramento:

### 1. A Tabela de Auditoria (O Diário de Bordo)
O Laravel já possui uma tabela `failed_jobs`, mas ela é genérica demais (guarda o *stack trace* inteiro do PHP). Nós precisamos de contexto de negócio.

*   **A Estrutura (`erros_integracao`):** Criaremos uma tabela no PostgreSQL focada no negócio.
    *   `id_feira` (Foreign Key).
    *   `numero_pagina` (Integer): Para você saber exatamente qual fragmento quebrou.
    *   `payload_recebido` (`jsonb`): **Este é o campo de ouro.** Ele guardará a cópia exata do JSON que a API da Nowigo enviou e que o seu sistema não conseguiu engolir.
    *   `mensagem_erro` (Text): O resumo do erro (ex: *"Undefined array key 'valor_liquido'"*).
    *   `status` (Enum/String): "Pendente de Análise" ou "Resolvido".

### 2. A Captura Cirúrgica (O *Catch* no Micro-Trator)
Para alimentar essa tabela, nós alteramos a forma como o `ProcessarPaginaVendaJob` (o nosso Micro-Trator do Passo 5) morre. 

*   **O Tratamento de Exceção:** Todo o processamento do Job continua dentro de um bloco `try/catch`. 
*   **O Registo antes da Morte:** Quando o `catch (Throwable $e)` captura um erro (seja uma falha de tipagem no banco ou um JSON malformado da Nowigo), o Job faz uma última ação de resgate: ele executa um `Insert` na tabela `erros_integracao` preenchendo todos os dados acima.
*   **A Sinalização para o Lote:** Logo após salvar o erro no banco, o código do Job invoca `$this->fail($e)`. Isso garante que o motor de *Batching* do Laravel marque aquela tarefa como falha (ativando o Pilar 1 e 2), mas com o seu erro perfeitamente documentado.

### 3. A Interface de Diagnóstico (O Raio-X do Desenvolvedor)
O utilizador final terá o painel em React (Inertia), mas você precisa de uma retaguarda ágil para investigar esses erros. Como a sua arquitetura explora o **Filament PHP**, esta é a ferramenta perfeita para construir a sua sala de controle.

*   **O *Resource* de Auditoria:** Você pode gerar um `ErroIntegracaoResource` no Filament em poucos minutos.
*   **A Visualização do Payload:** Na tela de visualização deste *Resource*, você configura o campo `payload_recebido` para ser exibido formatado como um bloco de código JSON. 
*   **O Diagnóstico Rápido:** Quando o cliente ligar a dizer *"O painel mostrou um aviso amarelo"*, você abre o Filament, clica no erro de hoje, e a tela mostra-lhe imediatamente a resposta da Nowigo. Você perceberá visualmente: *"Ah, na venda X, o parceiro enviou o valor da transação como uma String vazia em vez de 0.00, e o banco recusou"*.

### 4. O Ciclo de Cura (Healing Cycle)
Esta arquitetura fecha perfeitamente o ciclo de manutenção evolutiva do software sem qualquer perda de dados:

1.  **O Incidente:** A extração falha na página 24. O utilizador vê o botão laranja de "Repescar". O erro vai para o banco.
2.  **O Diagnóstico:** Você abre o Filament, analisa o JSON guardado e encontra a anomalia que a Nowigo enviou.
3.  **A Vacina (Correção):** Você ajusta a lógica de transformação no código fonte (ex: adiciona um `floatval()` ou um `isset()` na classe de extração) e sobe a correção para a VPS.
4.  **A Cura:** O utilizador (ou você mesmo) clica no botão "Tentar sincronizar dados faltantes" (Pilar 2). O sistema repassa a página 24, agora com o código imune ao erro. O lote termina e o status da feira fica 100% verde.
5.  **A Limpeza:** Opcionalmente, um evento de sucesso da repescagem pode alterar automaticamente o status daquele erro na tabela de auditoria para "Resolvido".

Com esta trindade (O Lote Resiliente, O Botão de Retrabalho Cirúrgico e a Auditoria Isolada no Filament), o seu sistema deixa de ser apenas uma ponte de dados e passa a ser uma plataforma de processamento *Enterprise*. 

Com a estratégia de ETL totalmente madura e à prova de falhas, para qual frente do sistema deseja focar o nosso próximo debate arquitetural?
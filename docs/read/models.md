### Estrutura do Model `Feira`

*   **`id`** (`BIGSERIAL` / `bigIncrements`): A nossa chave primária interna para relacionamento rápido no PostgreSQL[cite: 3, 5].
*   **`nome`** (`string`): O nome de exibição no painel (ex: "Feira de Saquarema 2025")[cite: 5].
*   **`data_inicio`** (`datetime`): A data e hora oficiais em que o evento começa[cite: 5].
*   **`data_fim`** (`datetime`): A data e hora oficiais em que o evento termina[cite: 5].
*   **`evento_id_api`** (`string`): O ID do evento original na API da Nowigo, essencial para o disparo das requisições de extração[cite: 5].
*   **`user_id_api`** (`string`): O ID do usuário na API, necessário como parâmetro para listar as vendas daquele evento específico[cite: 5].
*   **`ultima_sincronizacao_em`** (`datetime`, *nullable*): Guarda a exata hora em que o *Worker* finalizou o último processamento, alimentando a interface com um feedback visual seguro.
*   **`status`** (`string` / Enum): O ciclo de vida da feira. 

---

### Os Valores do Enum de `Status`
Para manter a integridade dos dados e evitar erros de digitação no banco, utilizaremos um **Enum** nativo do PHP (que se integra perfeitamente ao Eloquent e ao React). Os valores serão exatamente estes:

1.  **`PLANEJADA`**: A feira foi cadastrada no sistema com as datas e os IDs da API, mas a data de início ainda não chegou ou a extração de dados ainda não é o foco.
2.  **`EM_ANDAMENTO`**: A feira está a acontecer (ou no período de fechamento ativo). É aqui que o sistema deve permitir sincronizações constantes com a API para baixar novas transações.
3.  **`ENCERRADA`**: O evento acabou e as contas foram prestadas. Este status pode servir como uma "trava de segurança" lógica, impedindo que o sistema faça novas sincronizações na API e congele os dados no banco para auditorias futuras.

Excelente! O Model `Cartao` (ou Tags/Pulseiras) passou por uma das evoluções mais drásticas e inteligentes na nossa arquitetura. 

Para refrescar a memória, eis **tudo o que já decidimos sobre os Cartões** nas nossas conversas e documentações anteriores:

1.  **O Fim do Excel (Descoberta Orgânica):** Nós abandonamos a leitura do arquivo `cartoes.xls`[cite: 1, 2]. O sistema agora "descobre" os cartões de forma autônoma[cite: 3, 4]. O Worker do Laravel varre o JSON de pagamentos e, sempre que encontra um `tagCode` válido, cadastra-o automaticamente atrelando-o ao seu `grupo` (ex: "Escola Municipal Jardim Ipitangas")[cite: 1, 2, 5].
2.  **O Filtro de Falsos Positivos:** No script em Python, nós implementamos uma trava rigorosa: tags com o valor "NÃO DISPONÍVEL", "NAO DISPONIVEL" ou "N/A" (usadas para pagamentos em dinheiro ou PIX) são sumariamente ignoradas e **não** entram nesta tabela, evitando conflitos no banco[cite: 5].
3.  **Idempotência e Multi-Tenancy:** A tabela possui uma restrição única (Unique Constraint) combinando o `id_feira` e o `tag_code`[cite: 5]. Isso significa que se o script rodar 100 vezes, o cartão "761116B5" só será cadastrado uma vez para aquela feira, e terá seu grupo atualizado se houver mudança[cite: 5].
4.  **A Regra da Exclusão Lógica (Filtro de Ouro):** Na documentação das regras de negócio, definimos que os cartões usados por "equipas de teste" não são apagados do banco, mas devem ser ignorados na hora de gerar a matemática do relatório final para as editoras[cite: 4].

---

### A Proposta para o Model `Cartao` (Eloquent)

Baseado em todo este histórico, a tabela precisa suportar a extração automática e, ao mesmo tempo, facilitar a vida do nosso algoritmo de cálculo (Filtro de Ouro). 

Aqui está a estrutura ideal proposta para o Model:

*   **`id`** (`bigIncrements`): A nossa chave primária interna[cite: 5].
*   **`id_feira`** (`unsignedBigInteger`): A chave estrangeira que liga este cartão a uma feira específica (Multi-Tenancy)[cite: 3, 5].
*   **`tag_code`** (`string`): O código físico/lógico da pulseira ou cartão (ex: "761116B5") extraído da API[cite: 5].
*   **`grupo`** (`string`, *nullable*): O nome da escola, prefeitura ou entidade à qual o aluno pertence, extraído exatamente como vem da Nowigo[cite: 5].
*   **`classificacao`** (`string` / Enum): 
    *   **O motivo:** Para viabilizar o "Filtro de Ouro" de forma performática[cite: 4]. Em vez de o sistema ter que adivinhar pelo nome do grupo se o cartão é de teste, teremos este campo com valores como: `ALUNO` (padrão), `TESTE`, `CORTESIA`, `STAFF`.
    *   **Como funciona:** Todos os cartões nascem como `ALUNO` (ou `NAO_CLASSIFICADO`). No painel em React, o utilizador pode selecionar vários cartões de uma vez e marcá-los como `TESTE`. Na hora de gerar o PDF, o Laravel fará um simples `where('classificacao', '!=', 'TESTE')`.
*   **`identificacao_aluno`** (`string`, *nullable*):
    *   **O motivo:** Atualmente, extraímos apenas a Tag e o Grupo. Se no futuro o utilizador precisar auditar "Quem" gastou aquele cartão (cruzando com listas externas das escolas), ter uma coluna vazia para ele digitar manualmente o nome do aluno ou a matrícula escolar agrega um valor gigantesco ao sistema de auditoria.

### Relacionamentos do Model (Laravel)

No código PHP, o Eloquent ficaria assim:
*   `belongsTo(Feira::class)`: Um cartão pertence a uma feira.
*   `hasMany(Pagamento::class, 'tag_code', 'tag_code')`: **Este é o pulo do gato.** Um cartão tem muitos pagamentos. Nós ligaremos o cartão aos pagamentos através do `tag_code` e `id_feira`. Assim, na interface de "Auditoria de Transações", você clica no cartão "761116B5" e o Laravel lista instantaneamente tudo o que ele comprou.

---

O Model `Livro` é, sem dúvida, um dos mais interessantes do nosso sistema, pois é nele que ocorre a transição entre a extração automatizada e a inteligência humana.

Para recapitular o que já construímos e definimos sobre o catálogo de livros:
1. **O Fim do Excel e Descoberta Orgânica:** O catálogo não é mais carregado via planilha local[cite: 1, 2]. O sistema agora varre as vendas extraídas da API da Nowigo e, ao encontrar um novo produto, cadastra-o automaticamente no banco de dados[cite: 1, 2, 3, 4].
2. **Limitações da API vs. Enriquecimento Manual:** Constatamos que a API retorna apenas dados transacionais rasos (ID, Nome, Quantidade e Valor) e não provê metadados essenciais como ISBN ou Editora[cite: 3]. Por isso, o sistema cria essas colunas com valores padrão (ex: "NÃO INFORMADO")[cite: 5] para que o utilizador as preencha manualmente depois, via interface React[cite: 3, 4].
3. **A Proteção dos Dados Manuais (COALESCE):** No nosso script guia, implementamos uma trava de segurança brilhante no `UPSERT`: se o sistema reprocessar uma venda e tentar inserir um livro que já existe, ele não sobrescreve os dados caso o utilizador já os tenha corrigido ou enriquecido[cite: 5].

---

### A Proposta para o Model `Livro` (Eloquent)

Com base neste histórico, aqui está a estrutura ideal para a tabela e o Model no Laravel:

* **`id`** (`bigIncrements`): A nossa chave primária interna[cite: 3, 5].
* **`id_feira`** (`unsignedBigInteger`): Chave estrangeira para garantir o isolamento Multi-Tenancy[cite: 3, 5].
* **`produto_id_api`** (`bigInteger`): O ID original do livro na Nowigo (Duplo ID)[cite: 3, 5]. A combinação de `id_feira` + `produto_id_api` formará a nossa restrição única (`Unique Constraint`)[cite: 5].
* **`produto`** (`string`): O nome do livro (higienizado e em UPPERCASE)[cite: 1, 2, 5].
* **`valor`** (`decimal(12,2)`): O valor unitário de tabela do livro, convertido para tipo numérico seguro[cite: 3, 5].

#### ✏️ Os Campos de Enriquecimento Analítico (Manuais)
Estes são os campos que nascerão preenchidos com `'NAO INFORMADO'`[cite: 5] e que darão poder aos Dashboards e PDFs:

* **`editora`** (`string`): Ex: "Intrínseca", "Rocco"[cite: 5].
* **`representante`** (`string`): A entidade ou parceiro responsável por faturar aquele livro[cite: 5]. Esta é a principal coluna para o agrupamento na prestação de contas[cite: 2, 4].
* **`categoria`** (`string`, *nullable*): Para análises de gênero literário[cite: 5].
* **`isbn`** (`string`): O código de barras universal, caso o utilizador decida usar um leitor de código de barras no sistema futuramente[cite: 5].

### Regras de Negócio no Laravel (A Inteligência do Model)

No Laravel, nós vamos transformar aquelas regras do banco em código elegante dentro do Model `Livro`:

1. **Relacionamentos:**
   * `belongsTo(Feira::class)`: Um livro pertence ao catálogo de uma feira específica.
   * `hasMany(ItemVenda::class, 'produto_id_api', 'produto_id_api')`: Aqui nós ligaremos o livro ao histórico de vendas. Isso permitirá que a interface mostre rapidamente "Quantas vezes este livro foi vendido?". *(Nota: a ligação real no Laravel usará uma query scope para garantir que o `id_feira` também bata).*

2. **A Proteção de Atualização (O Segredo do Upsert):**
   No Eloquent, quando formos fazer a inserção em massa dos livros recém-descobertos, usaremos o método `upsert()`. A configuração será estrita: nós **nunca** mandaremos o Laravel atualizar as colunas `editora`, `representante` ou `isbn` durante o processamento em background. Assim, garantimos nativamente a mesma proteção do `COALESCE` que validamos no Python[cite: 5]: a API cria o registro básico uma vez, e depois apenas o humano pode enriquecê-lo na interface.

3. **Global Scope de Feira:**
   Assim como os outros Models, o `Livro` deverá ter um *Global Scope* atrelado à sessão do utilizador. Se ele estiver a ver a "Feira 2025", o catálogo de livros exibido para edição será estritamente o daquela feira.

---

Chegamos ao núcleo financeiro do nosso sistema! O Model `VendaHeader` é a "espinha dorsal" transacional. Tudo gravita em torno dele: é a partir do cabeçalho da venda que nós puxamos os pagamentos, os itens comprados e, consequentemente, calculamos os rateios para a prestação de contas.

Para refrescar a memória, eis o que já consolidamos sobre esta estrutura, especialmente com base no script guia em Python que validámos[cite: 5]:

1.  **Multi-Tenancy e Idempotência:** A chave única (Unique Constraint) que impede dados duplicados no banco é a combinação do `id_feira` com o `sell_number` (número do recibo gerado pela Nowigo)[cite: 5].
2.  **O Payload Bruto (`raw_payload`):** Definimos que o banco guardaria o JSON original da venda nesta coluna (`JSONB` no Postgres) para garantir auditoria máxima, caso a API envie campos não mapeados no futuro[cite: 5].
3.  **Tipagem Estrita de Moeda:** O valor total da venda não será texto, mas sim higienizado e salvo como um tipo numérico puro (`NUMERIC(12,2)`)[cite: 3, 4, 5].

---

### A Proposta para o Model `VendaHeader` (Eloquent)

Com base nestes pilares, aqui está o desenho exato de como a tabela e o Model ficarão estruturados no Laravel:

*   **`id`** (`bigIncrements`): Nossa chave primária de sistema[cite: 5].
*   **`id_feira`** (`unsignedBigInteger`): Chave estrangeira que amarra a venda ao seu respectivo evento[cite: 5].
*   **`sell_number`** (`string`): O número identificador da venda na API da Nowigo[cite: 5].
*   **`sale_type`** (`integer`, *nullable*): O tipo de venda retornado pela API (necessário para buscar os detalhes depois)[cite: 5].
*   **`total_value`** (`decimal(12,2)`): O valor bruto faturado naquela transação[cite: 5].
*   **`date_hour`** (`datetime`): A data e a hora exatas em que a transação ocorreu no caixa[cite: 5].
*   **`box`** (`string`, *nullable*): O nome ou identificador do ponto de venda / caixa (PDV) onde a compra foi feita[cite: 5].
*   **`processado`** (`boolean`, default `false`): **Uma coluna vital para a resiliência do sistema.** Indica se os detalhes desta venda (itens e pagamentos) já foram extraídos da API com sucesso[cite: 5].
*   **`raw_payload`** (`jsonb`, *nullable*): O espelho exato da resposta da API[cite: 5].

### A Inteligência do Model no Laravel (Regras e *Casts*)

No código do Laravel, este Model será extremamente elegante graças aos recursos nativos do framework:

#### 1. Conversão Automática (`$casts`)
Em vez de o programador ter que converter dados à mão toda vez que for exibir no React, o Model fará isso sozinho:
```php
protected $casts = [
    'date_hour' => 'datetime',
    'total_value' => 'decimal:2',
    'processado' => 'boolean',
    'raw_payload' => 'array', // O Laravel converte o JSON do Postgres direto para um array/objeto PHP
];
```

#### 2. A Mágica dos Relacionamentos
Este Model será o maestro dos nossos relatórios. Ele terá as seguintes ligações:
*   `belongsTo(Feira::class)`: Sabe exatamente a que evento pertence.
*   `hasMany(Pagamento::class, 'sell_number', 'sell_number')`: Ao carregar uma venda, o Laravel pode trazer os pagamentos automaticamente.
*   `hasMany(ItemVenda::class, 'sell_number', 'sell_number')`: O mesmo para os livros comprados.

*Atenção arquitetural:* Para garantir que o relacionamento seja 100% seguro no Multi-Tenancy, no Laravel nós aplicaremos uma condição na relação para que o `id_feira` do cabeçalho seja igual ao `id_feira` dos detalhes, evitando que, num caso milagroso de dois eventos gerarem o mesmo `sell_number`, os dados se cruzem.

#### 3. O Fluxo de Trabalho do *Worker* (Graças à coluna `processado`)
A coluna `processado` garante que a nossa fila de background (Redis/Worker) nunca trave definitivamente. Se o Laravel extrair 10.000 cabeçalhos de venda e, na hora de buscar os detalhes, a luz cair ou a API da Nowigo der erro de servidor (`Timeout`)[cite: 5], na próxima execução o Worker fará um simples `VendaHeader::where('processado', false)->get()`[cite: 5] e continuará exatamente de onde parou.

---

Chegamos ao nível de detalhe da compra! O Model `ItemVenda` é a ponte exata entre o que aconteceu no caixa (a `VendaHeader`) e o catálogo do nosso sistema (o `Livro`). É aqui que a verdadeira "Matemática de Alocação" vai acontecer na hora de gerar os relatórios finais.

Para relembrar o que nós já construímos e definimos no nosso ETL e script guia sobre os itens da venda:

1. **A Fonte dos Dados:** Ele é extraído da lista de `products` que vem no detalhe da venda na API da Nowigo[cite: 5].
2. **Idempotência (Segurança):** Antes de inserir os itens de uma venda, o nosso *Worker* deleta os itens antigos vinculados àquele recibo (`DELETE FROM itens_venda WHERE sell_number = ?`) para garantir que uma re-extração não duplique a venda do livro[cite: 3, 5].
3. **Valores Financeiros e Auditoria:** Ele também guarda o `raw_payload` em formato JSON e os valores monetários (`unit_value` e `total_value`) devem ser rigorosamente tipados como numéricos[cite: 3, 5].

---

### A Proposta para o Model `ItemVenda` (Eloquent)

A estrutura desta tabela no banco será muito limpa e objetiva:

* **`id`** (`bigIncrements`): Nossa chave primária interna[cite: 5].
* **`id_feira`** (`unsignedBigInteger`): A trava do Multi-Tenancy para garantir o isolamento do evento[cite: 5].
* **`sell_number`** (`string`): O recibo da venda que liga este item ao `VendaHeader`[cite: 5].
* **`produto_id_api`** (`bigInteger`): O ID do livro na API da Nowigo, que fará a ligação com a nossa tabela `livros`[cite: 5].
* **`name`** (`string`): O nome do livro **exatamente como foi vendido no dia**. *(Nota arquitetural: É importante guardar o nome aqui, mesmo ele já existindo na tabela Livros. Isso garante uma "fotografia" da venda. Se o nome do livro for alterado no catálogo 6 meses depois, o registo histórico da venda permanece inalterado)*[cite: 5].
* **`amount`** (`integer`): A quantidade comprada daquele livro nesta transação[cite: 5].
* **`unit_value`** (`decimal(12,2)`): O valor unitário do livro na hora da compra[cite: 5].
* **`total_value`** (`decimal(12,2)`): O valor total (`amount` * `unit_value`)[cite: 5].
* **`raw_payload`** (`jsonb`, *nullable*): O espelho do nó do produto no JSON da API[cite: 5].

### A Inteligência do Model no Laravel

No código PHP, este Model será a base dos nossos cálculos de comissão e repasse.

#### 1. Conversão Automática (`$casts`)
O Laravel fará o trabalho pesado de garantir que você sempre lide com números reais e arrays na programação:
```php
protected $casts = [
    'amount' => 'integer',
    'unit_value' => 'decimal:2',
    'total_value' => 'decimal:2',
    'raw_payload' => 'array', 
];
```

#### 2. Os Relacionamentos
Ele funcionará como o elo de ligação:
* `belongsTo(VendaHeader::class, 'sell_number', 'sell_number')`: O item sabe a que cabeçalho (e data/hora) pertence.
* `belongsTo(Livro::class, 'produto_id_api', 'produto_id_api')`: O item sabe qual é o livro no nosso catálogo. **É através desta ligação que nós puxaremos o `representante`, a `editora` e a `categoria` na hora de desenhar os Dashboards no React.**

#### 3. O Protagonismo no "Filtro de Ouro" (Método Customizado)
Como documentado na nossa Fase 4/Etapa 5, nós precisamos da **Alocação Proporcional**[cite: 4]. O Model `ItemVenda` é o alvo desse cálculo. 

Imagine que uma venda de R$ 100 teve R$ 10 pagos em dinheiro e R$ 90 no cartão (proporção de 90%). Dentro deste Model, no futuro, nós podemos criar um `Accessor` ou método auxiliar (ex: `$item->valor_liquido()`) que pegará a proporção de pagamentos válidos da `VendaHeader` e aplicará sobre o `total_value` deste item. Assim, saberemos exatamente o valor líquido que deve ser repassado ao representante de cada livro individualmente[cite: 4].

---

Chegámos à última peça do nosso quebra-cabeças do banco de dados! O Model `Pagamento` é a engrenagem que dita o ritmo do seu "Filtro de Ouro"[cite: 4]. É olhando para os registos desta tabela que o sistema vai decidir o que entra e o que sai da prestação de contas das editoras.

Para relembrar o que nós já definimos no nosso ETL e regras de negócio:
1. **Auditoria 100%:** Como já frisámos, o banco de dados guarda **tudo**[cite: 4]. Pagamentos em dinheiro, PIX, "Pagamento sem grupo", descontos ou tags de teste: tudo será extraído da API da Nowigo e salvo no Postgres para termos um espelho exato do caixa original[cite: 4].
2. **Descoberta de Cartões:** É varrendo os dados desta tabela durante a extração que o sistema descobre e cadastra novos `tagCodes` (pulseiras/cartões) na tabela de `Cartoes`[cite: 5].
3. **Idempotência:** Assim como nos Itens, o Worker apaga os pagamentos antigos de um `sell_number` (`DELETE FROM pagamentos...`) antes de inserir os novos, prevenindo duplicações[cite: 5].

---

### A Proposta para o Model `Pagamento` (Eloquent)

A estrutura no banco de dados, espelhando o que fizemos no script guia[cite: 5], ficará assim:

* **`id`** (`bigIncrements`): Nossa chave primária interna[cite: 5].
* **`id_feira`** (`unsignedBigInteger`): A trava do Multi-Tenancy[cite: 5].
* **`sell_number`** (`string`): O número do recibo que liga este pagamento à `VendaHeader`[cite: 5].
* **`pagamento_id_api`** (`bigInteger`): O ID interno daquele pagamento na Nowigo[cite: 5].
* **`tag_code`** (`string`, *nullable*): O código da pulseira/cartão usado na compra. Pode vir nulo, vazio ou como "NÃO DISPONÍVEL" no caso de pagamentos externos[cite: 5].
* **`cpf`** (`string`, *nullable*): CPF do comprador, se informado[cite: 5].
* **`payment_way`** (`string`): O meio de pagamento (ex: "Cashless nowigo", "Dinheiro", "PIX", "Desconto")[cite: 5].
* **`value`** (`decimal(12,2)`): O valor exato transacionado neste pagamento[cite: 5].
* **`payment_group`** (`string`, *nullable*): O agrupamento que a API retorna (ex: "Escola Municipal Jardim Ipitangas" ou "Pagamento sem grupo")[cite: 5].
* **`raw_payload`** (`jsonb`, *nullable*): O nó JSON original da API para auditoria futura[cite: 5].

### A Inteligência do Model no Laravel (Regras e Relacionamentos)

No Laravel, este Model será vital para aplicar a exclusão lógica na hora de gerar os PDFs.

#### 1. Conversão Automática (`$casts`)
Garantindo a segurança financeira e de dados no PHP:
```php
protected $casts = [
    'value' => 'decimal:2',
    'raw_payload' => 'array',
];
```

#### 2. Os Relacionamentos (A Rede de Dados)
Este Model conecta a transação ao utilizador físico (Tag):
* `belongsTo(VendaHeader::class, 'sell_number', 'sell_number')`: O pagamento sabe a que venda pertence.
* `belongsTo(Cartao::class, 'tag_code', 'tag_code')`: **Esta é a ligação estratégica.** Como o Eloquent permite buscar relações, você poderá fazer verificações dinâmicas cruzadas (exemplo: verificar se o `Pagamento` pertence a um `Cartao` que está marcado como `TESTE`).

#### 3. Os *Scopes* do Filtro de Ouro (A Mágica da Prestação de Contas)
Para implementar a Fase 4 do nosso Pipeline (onde ignoramos pagamentos inválidos na matemática sem apagá-los do banco)[cite: 4], nós criaremos **Query Scopes** no Laravel. 

Isso permitirá que você escreva códigos legíveis como `$venda->pagamentos()->validosParaRateio()->get()`. Dentro do Model, o Escopo fará o seguinte:

```php
public function scopeValidosParaRateio($query)
{
    return $query->where('payment_way', '!=', 'Desconto')
                 ->where('payment_group', '!=', 'Pagamento sem grupo')
                 // Aqui usamos a relação do Cartão para ignorar tags de teste
                 ->whereHas('cartao', function ($q) {
                     $q->where('classificacao', '!=', 'TESTE');
                 });
}
```

---

### Estrutura do Model `Relatorio` (Tabela `relatorios`)

*   **`id`** (`bigIncrements`): Nossa chave primária.
*   **`id_feira`** (`unsignedBigInteger`): A trava do Multi-Tenancy para isolar os documentos de cada evento[cite: 3, 4].
*   **`usuario_id`** (`unsignedBigInteger`): Para **Auditoria**. Regista exatamente qual operador logado no sistema clicou no botão "Gerar Relatório".
*   **`tipo`** (`string`): O tipo do relatório gerado. Ex: `GERAL`, `POR_EDITORA`, `INCONSISTENCIAS_CATALOGO`.
*   **`parametros_filtro`** (`jsonb`): Para **Auditoria e Reprodutibilidade**. Guarda os filtros exatos que o utilizador escolheu na interface no momento do clique (ex: `{"representante": "FLORESCER", "ignorar_testes": true}`). Isso impede que alguém diga "o sistema calculou errado", pois você tem a prova dos filtros aplicados.
*   **`status`** (`string` / Enum): O coração da comunicação com o Frontend (React). Controla o ciclo de vida da geração do PDF.
*   **`caminho_arquivo`** (`string`, *nullable*): O caminho onde o PDF final (ex: de 65MB) foi salvo no `Storage` do Laravel (S3 ou local) após o *merge* do Gotenberg.
*   **`tamanho_bytes`** (`bigInteger`, *nullable*): O tamanho do ficheiro gerado, útil para métricas e alertas de infraestrutura.
*   **`mensagem_erro`** (`text`, *nullable*): Se o *Worker* falhar (ex: erro de Out of Memory ou Timeout no Gotenberg), ele salva o erro aqui antes de morrer.
*   **`tempo_execucao_segundos`** (`integer`, *nullable*): Métrica de performance para sabermos quanto tempo cada relatório custou ao servidor.

### Os Valores do Enum de `Status` (A Jornada do PDF)

Este Enum vai guiar a UI do React (Inertia) para mostrar ao utilizador o que está a acontecer em tempo real:

1.  **`FILA`**: O utilizador clicou em "Gerar". O registo é criado no banco e o *Job* vai para o Redis. A tela mostra: *"Aguardando início do processamento"*.
2.  **`PROCESSANDO`**: O *Worker* do Laravel pegou o trabalho e começou a consultar o banco em *chunks* e a enviar as páginas HTML para o Gotenberg[cite: 4]. A tela mostra: *"A processar dados e a gerar PDF..."* (com um *spinner*).
3.  **`CONCLUIDO`**: O Gotenberg fez o *merge* do PDF, o Laravel guardou no disco e preencheu a coluna `caminho_arquivo`. A tela muda e mostra o botão verde: **"Descarregar PDF"**.
4.  **`FALHA`**: Algo deu muito errado no background. A tela mostra um X vermelho e a `mensagem_erro`.

---

### A Inteligência do Model no Laravel (Regras e *Casts*)

No código PHP, o Eloquent tratará isto de forma muito elegante:

#### 1. Conversão Automática (`$casts`)
Garante que o Laravel trate os parâmetros em JSON nativamente como um *array* PHP:
```php
protected $casts = [
    'parametros_filtro' => 'array',
    'status' => RelatorioStatusEnum::class, // Usa o Enum nativo do PHP
];
```

#### 2. Relacionamentos
*   `belongsTo(Feira::class)`: O relatório pertence a um evento específico.
*   `belongsTo(User::class, 'usuario_id')`: O relatório foi solicitado por um utilizador específico.

#### 3. Método Auxiliar de Download (A Segurança)
No Model, podemos criar um método que gere a URL de forma segura (Temporary Signed URL), garantindo que ninguém consiga aceder ao PDF de 65MB a não ser que tenha o link assinado que expira em alguns minutos:

```php
public function urlDownloadSegura()
{
    if ($this->status !== RelatorioStatusEnum::CONCLUIDO || !$this->caminho_arquivo) {
        return null;
    }

    // Gera um link que se destrói em 30 minutos
    return URL::temporarySignedRoute(
        'relatorios.download', now()->addMinutes(30), ['relatorio' => $this->id]
    );
}
```

### O Ganho Arquitetural
Com esta tabela, o seu frontend (Inertia.js/React) fará um simples pedido via *polling* (ex: a cada 5 segundos) ou via WebSockets para buscar o status deste `Relatorio` específico. Assim que o *Worker* alterar o status no banco para `CONCLUIDO`, a UI atualiza magicamente para o utilizador, entregando a URL assinada de download sem travar a navegação.

---

### A Proposta para o Model `FeiraEstatistica` (A Tabela de Snapshot)

Diferente das outras tabelas onde inserimos milhares de linhas, a regra de ouro aqui é a **Relação 1 para 1 (1:1)**. O banco de dados terá estritamente **uma única linha de estatística para cada feira**. Toda vez que o encadeamento do *Worker* terminar, ele fará um `update` (ou `upsert`) apenas nesta linha.

A estrutura no banco de dados ficará assim:

*   **`id`** (`bigIncrements`): Nossa chave primária interna.
*   **`id_feira`** (`unsignedBigInteger`, *Unique*): A chave estrangeira com restrição única. É o que garante que a "Feira A" só tenha um painel de consolidação.
*   **`faturamento_bruto`** (`decimal(12,2)`): A soma exata de tudo o que passou pelas SmartPOS.
*   **`faturamento_liquido_valido`** (`decimal(12,2)`): O valor real após o nosso *Worker* subtrair os pagamentos de teste, cortesias e valores sem grupo.
*   **`ticket_medio`** (`decimal(12,2)`): O `faturamento_liquido_valido` dividido pelo número de recibos (`VendaHeader`) válidos.
*   **`total_livros_vendidos`** (`integer`): A soma bruta da coluna `amount` da tabela de `ItemVenda`.
*   **`qtd_inconsistencias_catalogo`** (`integer`): A contagem de livros vendidos que ainda estão com representante ou editora como "NÃO INFORMADO". Isso alimenta diretamente aquele termômetro de alerta visual que discutimos.
*   **`dados_graficos`** (`jsonb`, *nullable*): **Esta é uma adição de ouro para a interface.** Em vez de o React ter que montar os eixos X e Y dos gráficos, o *Worker* do Laravel já salva no banco um JSON mastigado com os "Top 5 Representantes" e a distribuição das "Formas de Pagamento". O front-end apenas consome e desenha.
*   **`atualizado_em`** (`datetime`): A data e hora exatas do término do último Job de cálculo.

### A Inteligência do Model no Laravel (Regras e *Casts*)

No código PHP, a elegância deste Model garantirá que o seu React receba os dados perfeitos sem nenhum esforço do Controller.

#### 1. Conversão Automática (`$casts`)
Como de costume, garantimos a tipagem forte das moedas e dizemos ao Laravel que o campo `dados_graficos` é um *array* PHP nativo. Assim, quando o Inertia mandar os dados para o React, isso vira um objeto JavaScript perfeito.

```php
protected $casts = [
    'faturamento_bruto' => 'decimal:2',
    'faturamento_liquido_valido' => 'decimal:2',
    'ticket_medio' => 'decimal:2',
    'total_livros_vendidos' => 'integer',
    'qtd_inconsistencias_catalogo' => 'integer',
    'dados_graficos' => 'array',
    'atualizado_em' => 'datetime',
];
```

#### 2. Os Relacionamentos (1:1)
No Model `FeiraEstatistica`, o relacionamento aponta de volta para o evento raiz:
*   `belongsTo(Feira::class, 'id_feira')`: A estatística pertence a uma feira.

E, reciprocamente, no nosso já criado Model `Feira`, nós adicionaremos:
*   `hasOne(FeiraEstatistica::class, 'id_feira')`: Uma feira **tem apenas uma** estatística.

#### 3. O Método de Delegação (O "Trigger" do Laravel)
Embora a lógica pesada de soma vá ficar isolada no `CalcularEstatisticasJob`, o Model pode ter um método auxiliar limpo para que o *Job* apenas entregue os dados finais, deixando o Model responsável por atualizar o Snapshot. O Eloquent fará isso de forma brilhante usando o método `updateOrCreate`:

```php
public static function atualizarSnapshot(Feira $feira, array $novosCalculos)
{
    // Se a feira ainda não tem estatística, ele cria. 
    // Se já tem, ele apenas atualiza os valores e a hora do snapshot.
    return self::updateOrCreate(
        ['id_feira' => $feira->id], 
        array_merge($novosCalculos, ['atualizado_em' => now()])
    );
}
```
FASE 1:

**1. A Origem dos Dados (Adeus, Excel)**
* **O que está no script:** Você está utilizando a biblioteca `pandas` para ler arquivos físicos (`livros.xlsx` e `cartoes.xls`) que estariam na mesma pasta do script.
* **O que o novo pipeline exige:** Como você definiu, não teremos mais planilhas extraídas manualmente. A função `read_excel` será completamente removida. O FastAPI fará requisições HTTP (usando bibliotecas como `httpx` ou `requests`) diretamente para os *endpoints* da API do Nowigo, recebendo as informações em formato JSON e transformando isso em tabelas.

**2. A Construção da Tabela `livros` (Inferência em vez de Carga)**
* **O que está no script:** Você cria a tabela de livros simplesmente copiando as colunas `Categoria`, `Produto` e `Valor` da planilha pré-existente.
* **O que o novo pipeline exige:** Como a API não entrega um "catálogo" separado, o seu novo algoritmo terá de "descobrir" os livros. Ele precisará varrer o JSON de transações (os itens vendidos), extrair os nomes únicos (`name` ou `product_id`) e inseri-los na tabela `livros`. Além disso, a tabela precisará ser criada já com as colunas `Categoria` e `Representante` vazias (ou com "NÃO INFORMADO"), para que o seu Frontend em React permita que o usuário preencha isso manualmente depois.

**3. A Construção da Tabela `cartoes` (Extração Indireta)**
* **O que está no script:** Você lê o `cartoes.xls`, renomeia colunas e faz limpezas de caracteres nulos (`\x00`) característicos de exportações antigas de Excel.
* **O que o novo pipeline exige:** O Excel morre aqui. Para popular a tabela de cartões, o novo script vai olhar para os dados de pagamentos retornados pela API do Nowigo. Ele vai varrer cada pagamento, extrair o `tagCode` (código do cartão) e o respectivo `grupo`/aluno associado a essa tag no momento da compra. Se for uma tag nova, ele cadastra na tabela `cartoes`. As limpezas de `\x00` perdem o sentido, pois APIs REST trafegam JSON limpo em UTF-8.

**4. O Gatilho de Execução (Script vs. API REST)**
* **O que está no script:** A execução ocorre via terminal local (`if __name__ == '__main__':`).
* **O que o novo pipeline exige:** Esta lógica vai virar uma "Rota" no FastAPI (ex: um endpoint `POST /api/sincronizar-evento`). O React vai enviar para essa rota as **datas de início e fim do evento** (o Filtro Temporal personalizável que você mencionou). O script usará essas datas para consultar a API do Nowigo apenas naquele período específico.

**O que se aproveita 100% deste script?**
A sua lógica de higienização de moeda (remover "R$", espaços, trocar vírgula por ponto e converter para float) é de extrema importância e será totalmente reaproveitada quando você for extrair os valores do JSON da API para salvar no SQLite.

FASE 2:

**1. Parametrização Engessada (Hardcoded)**
* **O que está no script:** As datas (`dateTimeBegin` e `dateTimeEnd`), assim como o `eventId` e o `userId`, estão escritos diretamente no código.
* **O que o novo pipeline exige:** Como combinamos, o Filtro Temporal será personalizável. O seu backend FastAPI deverá receber essas datas (e possivelmente os IDs) como parâmetros vindos do formulário do React (ex: no "body" de uma requisição POST). O script não pode ter datas fixas no código.

**2. Bloqueio Assíncrono (O Problema do `requests` e `time.sleep`)**
* **O que está no script:** Você usa a biblioteca `requests` e `time.sleep(0.1)`. Ambas são funções "síncronas" (bloqueantes).
* **O que o novo pipeline exige:** O FastAPI é um framework assíncrono projetado para alta concorrência. Se você usar `requests` e `time.sleep` dentro de uma rota do FastAPI, você vai "congelar" o seu servidor inteiro enquanto ele baixa as 100+ páginas (nenhum outro usuário conseguirá acessar o sistema). Você precisará migrar para bibliotecas assíncronas, como o `httpx` (para as requisições) e usar `await asyncio.sleep()`.

**3. O Abismo de Dados (Onde estão os Itens, Pagamentos, Cartões e Livros?)**
* **O que está no script:** O código varre a API, mas extrai **apenas** o cabeçalho da venda (Salvando em `vendas_header`). 
* **O que o novo pipeline exige:** Lembra que na Fase 1 atualizada decidimos que não usaríamos mais arquivos de Excel? É **exatamente nesta requisição** da API (ou num endpoint de detalhe logo após este) que você receberá os dados aninhados: quais livros foram vendidos e como foram pagos. O seu novo script precisará "abrir" cada transação, extrair essas informações e, no mesmo fluxo, **inferir e alimentar as tabelas** `itens_venda`, `pagamentos`, `livros` (catálogo) e `cartoes` (base de cartões). O seu script atual joga fora o ouro que vem junto com a venda.

**4. Feedback de Progresso (Terminal vs Navegador)**
* **O que está no script:** Você usa `sys.stdout.write` para desenhar uma barra de progresso no terminal do seu computador.
* **O que o novo pipeline exige:** No mundo web, o servidor (FastAPI) não tem um "terminal" que o usuário possa ver. Se você quiser que o React mostre uma barra de progresso bonitinha rodando de 0% a 100%, você precisará usar tecnologias como **WebSockets** ou **SSE (Server-Sent Events)** para que o FastAPI vá avisando o React a cada página processada. Ou, mais simples, rodar isso como uma "Background Task" e criar um endpoint onde o React pergunta de tempos em tempos: "Já acabou?".

**5. O Perigo do `DROP TABLE IF EXISTS`**
* **O que está no script:** Você apaga a tabela inteira e recria toda vez que o script roda.
* **O que o novo pipeline exige:** Se o sistema agora guarda todos os dados para fins de **auditoria permanente**, dar um `DROP TABLE` é extremamente perigoso num sistema web (alguém pode clicar no botão errado e apagar o histórico). A melhor abordagem para o ETL no FastAPI será usar comandos de "Upsert" (Update ou Insert) ou dar um `DELETE` apenas nas transações do período que o usuário está sincronizando, mantendo o histórico de outras datas intacto e preservando os mapeamentos de Representantes que os usuários fizeram na tabela de livros.

-> LEMBRANDO QUE:
A deleção pode ser desconsiderada, visto que nós não vamos mais precisar deletar pois o script não vai mais ser testado, ele já está validado, então agora é apenas juntar as peças. As deleções era porque toda vez que ajustava algo no script precisava limpar a tabela antes para poder popular novamente o banco de dados.

FASE 3:

1. Tipagem de Dados Financeiros (Crítico)

O problema: Você está criando as colunas de valores financeiros como TEXT (value em pagamentos; unitValue e totalValue em itens_venda).
Por que isso é ruim: No seu sistema final, você vai querer somar faturamento, calcular ticket médio e agrupar vendas. Bancos de dados SQL não lidam bem com somas (SUM()) de campos de texto, especialmente se a API retornar os dados com vírgulas ou símbolos (ex: "10,50" ou "R$ 10.50").
A Solução:

Altere a criação da tabela para usar REAL ou NUMERIC.

Antes de inserir no banco com o cursor.executemany, faça um tratamento (parse) no Python para garantir que o valor seja um float válido (ex: substituindo vírgula por ponto, removendo "R$").

2. Falta de Idempotência (Risco de Dados Duplicados)

O problema: O script pega as vendas processado = 0, extrai os dados, insere nas tabelas de detalhes e só depois atualiza para processado = 1. Se o script falhar (ex: queda de luz, erro de disco) depois de inserir os pagamentos mas antes de marcar como processado = 1 e dar o commit(), na próxima execução ele pegará a mesma venda e inserirá os pagamentos e itens em duplicidade.
A Solução:

Antes de fazer o INSERT de uma sellNumber, execute um DELETE FROM pagamentos WHERE sellNumber = ? e DELETE FROM itens_venda WHERE sellNumber = ?. Isso garante que, se você reprocessar uma venda, os dados antigos sejam limpos antes dos novos entrarem, tornando o processo seguro (idempotente).

3. Ausência de Índices para Performance (Otimização)

O problema: O seu painel de análise fará muitos cruzamentos (JOINs) entre vendas_header, pagamentos e itens_venda usando a coluna sellNumber. Sem índices, o SQLite fará buscas lentas (Full Table Scan) quando o banco crescer.
A Solução:

Adicione a criação de índices logo após criar as tabelas:
CREATE INDEX IF NOT EXISTS idx_pagamentos_sell ON pagamentos(sellNumber);
CREATE INDEX IF NOT EXISTS idx_itens_sell ON itens_venda(sellNumber);

4. Oportunidade de Enriquecimento de Dados (A avaliar)

O problema: A tabela itens_venda guarda apenas ID, Nome, Quantidade e Valor. Para uma Feira do Livro, as análises mais ricas costumam ser por Editora, Categoria/Gênero, Autor ou ISBN.
A Solução: * Verifique o JSON que a API da Nowigo retorna. Se dentro da lista de products vierem campos como category, publisher ou barcode/isbn, adicione essas colunas na sua tabela. Isso fará uma diferença gigantesca na qualidade do seu Dashboard final.
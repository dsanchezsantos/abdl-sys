Documentação Arquitetural e Plano de Implementação

Motor de Extração e Transformação de Dados (ETL) - Feiras do Livro

1. Visão Geral do Sistema

O objetivo desta fase do projeto é refatorar e unificar os scripts de extração de dados locais (Fases 1, 2 e 3) em um serviço de backend robusto. Este serviço abandonará o uso de planilhas locais e bancos de dados efêmeros, passando a atuar como uma API que alimentará um sistema web centralizado, capaz de gerenciar o histórico financeiro e operacional de múltiplas feiras do livro ao longo do tempo.

2. Arquitetura de Banco de Dados e Multi-Tenancy

A fundação do armazenamento de dados passará por uma grande atualização para suportar o novo escopo do projeto:

Migração para PostgreSQL: Substituição do SQLite local por um banco de dados relacional robusto (PostgreSQL), adequado para concorrência web e análises complexas de BI (Business Intelligence).

Controle de Identificadores (Duplo ID para Auditoria): Para garantir a integridade e rastreabilidade da arquitetura, cada tabela do nosso banco terá seu próprio id interno (auto-incrementável/Serial) atuando como chave primária. No entanto, é obrigatório armazenar em uma coluna dedicada o ID original do registro gerado na API da Nowigo (ex: id_api, produto_id_api), permitindo auditorias precisas e conciliações futuras com o sistema de origem.

Estrutura Multi-Feiras (Multi-Tenancy): Criação de uma tabela mestre chamada feiras, que armazenará o id do evento (oriundo da API), datas de início e fim, e metadados do evento.

Relacionamento Universal: Absolutamente todas as tabelas transacionais (vendas_header, pagamentos, itens_venda, livros, cartoes) receberão uma coluna id_feira. Isso garantirá que os dados de diferentes eventos coexistam no mesmo banco sem conflitos, permitindo prestação de contas histórica.

Fim das Deleções em Massa (DROP TABLE): Como o banco agora é histórico e permanente, as tabelas não serão mais apagadas e recriadas a cada execução. A manutenção dos dados se dará por políticas de atualização (Upsert).

Idempotência (Execuções Seguras): O pipeline será desenhado para não gerar dados duplicados em caso de falha ou reprocessamento. Antes de inserir os detalhes de uma venda (sellNumber), o sistema limpará os registros anteriores exclusivamente daquela venda, garantindo a consistência.

Otimização de Performance: Criação de índices estratégicos nas tabelas (especialmente nas colunas sellNumber e id_feira) para garantir que os relatórios e dashboards do frontend carreguem instantaneamente.

3. Nova Origem e Descoberta de Dados (Fim da Era Excel)

O sistema não dependerá mais de arquivos .xlsx ou .xls inseridos manualmente. Toda a inteligência de catalogação será dinâmica e inferida a partir da API oficial (Nowigo).

Descoberta do Catálogo de Livros: Durante a extração das vendas, o sistema varrerá os itens vendidos. Ao identificar um produto novo, ele o cadastrará automaticamente na tabela livros. Colunas como Categoria e Representante nascerão vazias ou como "NÃO INFORMADO", permitindo que os usuários do sistema web as classifiquem posteriormente via interface (React).

Descoberta de Cartões/Tags: Da mesma forma, ao processar os pagamentos, o sistema extrairá os códigos de pulseiras/cartões (tagCode) e os alunos/grupos atrelados a eles no momento da compra, alimentando a tabela cartoes organicamente.

4. Modernização do Fluxo de Execução (De Script para Web)

A forma como a extração é iniciada e processada mudará para se adequar ao ecossistema web:

Gatilho Dinâmico via API: A execução deixará de ser via terminal e passará a ser uma rota REST (ex: ativada pelo botão "Sincronizar" no frontend).

Filtros Temporais Personalizáveis: Parâmetros como ID do evento e Datas de Início/Fim deixarão de ser fixos no código (hardcoded). Eles serão enviados pelo usuário através da interface web, permitindo extrações cirúrgicas de períodos específicos.

Processamento Assíncrono: O motor utilizará bibliotecas assíncronas para não bloquear o servidor durante a comunicação com a API de origem, garantindo que o sistema web continue responsivo para outros usuários enquanto a sincronização ocorre nos bastidores.

Feedback de Progresso em Tempo Real: As barras de progresso de terminal darão lugar a tecnologias de comunicação web (como WebSockets ou Polling), permitindo que o frontend exiba uma barra de carregamento visual elegante para o usuário (0% a 100%).

5. Transformação e Qualidade dos Dados (Regras de ETL)

Para garantir que os dados alimentem os relatórios financeiros com precisão absoluta, as seguintes regras serão aplicadas durante o fluxo unificado:

Unificação do Processo: O que antes eram "Fases 2 e 3" (separadas entre cabeçalho e detalhes) ocorrerão em um fluxo único. Ao consultar uma venda, o sistema já extrairá e salvará seu cabeçalho, seus itens, seus pagamentos e atualizará os catálogos (livros/cartões) na mesma transação.

Tipagem Financeira Estrita: Todos os valores monetários (value, unitValue, totalValue) serão convertidos rigorosamente para tipos numéricos puros (REAL ou NUMERIC no Postgres).

Higienização de Moedas: A lógica de limpeza já validada (remoção de "R$", troca de vírgulas por pontos e conversão para float) será aplicada como filtro padrão antes de qualquer inserção no banco de dados.

Retenção Integral e Filtros Lógicos: Transações como "cartões de teste" ou "pagamentos sem grupo" não serão descartadas ou excluídas em nenhum momento. Elas serão extraídas e salvas no banco de dados normalmente, garantindo a rastreabilidade e auditoria completa de 100% dos dados gerados na plataforma de origem. A "limpeza" será puramente lógica: essas transações serão apenas desconsideradas pelos algoritmos de geração de relatórios e dashboards, assegurando métricas financeiras reais sem corromper o histórico bruto.

Limitações da API e Enriquecimento Manual: Conforme validado no JSON de origem, a API provê apenas dados transacionais rasos dos produtos (ID, Nome, Quantidade e Valor), não possuindo metadados como ISBN ou Editora. Portanto, o motor registrará o livro no catálogo apenas com as informações básicas descobertas. A responsabilidade de enriquecer esses dados (vinculando ISBN, Categoria, etc.) ficará integralmente a cargo do usuário final através do painel de administração no frontend.

Próximo Passo: Com este documento validado, o desenvolvimento técnico consistirá em criar a base de dados PostgreSQL e desenvolver a lógica unificada e assíncrona do backend utilizando as diretrizes acima.
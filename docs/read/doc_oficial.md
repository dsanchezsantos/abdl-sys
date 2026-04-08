Documentação Arquitetural e Plano de Implementação

Sistema de Gestão e Auditoria - Feiras do Livro

Esta documentação norteia o desenvolvimento do sistema web centralizado, abrangendo desde o motor de extração de dados (Backend/ETL) até as regras de negócio e apresentação (Frontend).

PARTE 1: ARQUITETURA E INFRAESTRUTURA DE DADOS

1. Migração para PostgreSQL: Substituição do modelo de banco de dados local por PostgreSQL, garantindo alta concorrência e suporte a análises complexas (Business Intelligence).

2. Estrutura Multi-Feiras (Multi-Tenancy): * Criação de uma tabela mestre feiras (id do evento, datas, metadados).

Relacionamento Universal: Todas as tabelas transacionais (vendas_header, pagamentos, itens_venda, livros, cartoes) terão obrigatoriamente a coluna id_feira. Isso permite que o histórico de múltiplos eventos coexista no mesmo banco.

3. Controle de Identificadores (Duplo ID): Cada tabela terá seu id interno (auto-incrementável/Serial) atuando como chave primária. No entanto, é obrigatório armazenar em uma coluna dedicada o ID original gerado na API da Nowigo (ex: produto_id_api, pagamento_id_api) para auditorias e conciliações.

4. Regras de Persistência e Idempotência:

Proibição de DROP TABLE: Sendo um banco de dados histórico permanente, tabelas nunca são apagadas.

Idempotência Obrigatória: Para execuções seguras (sem dados duplicados em caso de falha), o motor executará um DELETE cirúrgico antes da inserção. Ex: DELETE FROM pagamentos WHERE sellNumber = ?. Os dados antigos daquela venda específica são limpos e os novos são inseridos no mesmo momento.

Otimização: Criação de índices estratégicos (idx_pagamentos_sell, idx_itens_sell e índices por id_feira) para performance.

PARTE 2: O MOTOR DE EXTRAÇÃO (BACKEND / ETL)

(Substitui a lógica baseada em scripts locais e arquivos Excel por um serviço automatizado)

Etapa 1: Parametrização e Gatilho Dinâmico

A execução não é mais via terminal. Ocorre via rota REST no FastAPI (ex: /api/sincronizar-evento).

O Frontend envia os parâmetros da feira, definindo o Filtro Temporal (Datas de Início e Fim).

O consumo da API ocorre de forma assíncrona (ex: biblioteca httpx), não bloqueando o servidor e emitindo feedback de progresso ao frontend em tempo real (WebSockets ou Polling).

Etapa 2: Construção Automática (Adeus Excel)

A dependência de ficheiros locais (.xlsx, .csv) foi eliminada. Os catálogos nascem a partir dos dados da API.

Descoberta de Livros: Ao varrer itens vendidos, novos product_id alimentam a tabela livros. Devido a limitações da API (ausência de ISBN/Editora no JSON), essas colunas analíticas nascerão vazias ou como "NÃO INFORMADO".

Descoberta de Cartões: Ao varrer pagamentos, novos tagCode e seus grupos alimentam a tabela cartoes.

Etapa 3: Carga Bruta e Normalização (Persistência Total)

Auditoria 100%: Absolutamente todos os dados recebidos da API são guardados. Pagamentos com desconto, PIX, dinheiro e "cartões de teste" são salvaguardados para espelhar a realidade de origem. Nada é descartado na inserção.

Normalização: Tratamento de codificações HTML (&AMP; vira &), padronização para UPPERCASE e remoção de espaços nulos.

Tipagem Estrita: Limpeza de moedas (remoção de "R$", vírgula por ponto) e conversão obrigatória para tipos numéricos (REAL ou NUMERIC). Valores financeiros jamais serão salvos como texto (TEXT).

PARTE 3: REGRAS DE NEGÓCIO E APRESENTAÇÃO (FRONTEND)

(A manipulação e refinamento dos dados brutos consolidados)

Etapa 4: Interação do Utilizador e Enriquecimento Manual

Uma vez que a base de livros foi autodescoberta e populada com dados básicos, o utilizador acede à área de configuração do catálogo no React.

O utilizador mapeia manualmente o Representante, Editora ou Categoria de cada livro, preenchendo as lacunas deixadas pela API.

Etapa 5: O Filtro de Ouro e Motor de Cálculo
(Esta etapa ocorre sob demanda no momento de gerar as visões e relatórios, sem alterar/apagar os dados base do banco).

Exclusão Lógica: O algoritmo ignora os pagamentos com "DESCONTO", pagamentos externos (PIX/Dinheiro/"PAGAMENTO SEM GRUPO") e tags identificadas como contas de teste.

Matemática da Alocação Proporcional:

O sistema calcula a proporção (peso percentual) do pagamento válido via cartão em relação ao valor total bruto da venda.

Aplica esse percentual ao valor individual de cada livro daquela venda para encontrar o valor líquido faturado, abatendo valores pagos "por fora".

Etapa 6: Dashboard e Prestação de Contas

Apresentação Web: Geração de tabelas pesquisáveis e gráficos dinâmicos das Transações, Vendas e Editoras no navegador.

Geração de PDF: Consolidação das visões filtradas num documento PDF oficial de prestação de contas para as editoras, acompanhado do relatório de possíveis inconsistências do catálogo.
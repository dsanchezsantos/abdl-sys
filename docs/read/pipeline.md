Fase 1: Extração (Extract), Parametrização e Descoberta

Configuração e Contexto do Evento (Multi-Tenancy): O sistema web (Frontend) solicita ao utilizador os parâmetros da feira, vinculando a extração ao registro mestre na tabela feiras (garantindo o relacionamento via id_feira) e definindo o Filtro Temporal personalizável (Data/Hora de Início e Fim).

Consumo Dinâmico e Assíncrono da API: O motor de backend (FastAPI) utiliza estes parâmetros para consultar os endpoints da API do Nowigo de forma não-bloqueante (assíncrona), processando as paginações em background enquanto informa o progresso ao frontend.

Construção Automática de Bases (Nova Regra): Em vez de dependermos de ficheiros locais (.csv ou Excel) pré-existentes, o sistema irá inferir e construir as tabelas de livros e cartoes diretamente a partir das transações da API.

Se a API reporta um novo product_id e um name nos itens vendidos, ele alimenta o catálogo de livros. O ID original da API é preservado (Duplo ID) para auditoria, e colunas analíticas (como Categoria e ISBN) nascem vazias para enriquecimento manual futuro.

Se a API reporta um tagCode associado a um grupo/aluno num pagamento, ele alimenta organicamente a base de cartoes.

Fase 2: Carga Bruta, Normalização e Persistência Segura (Load & Cleanse)

Persistência Total (Auditoria 100%): Todos os dados recebidos da API (vendas_header, itens_venda, pagamentos, cartoes, livros) são guardados de forma permanente no banco de dados PostgreSQL. Nada é descartado na entrada. Pagamentos com desconto, PIX, dinheiro, pagamentos sem grupo e cartões de teste são todos salvaguardados para efeitos de auditoria e espelho exato da realidade da origem. As exclusões ou filtros ocorrerão apenas em tempo de leitura (nos algoritmos dos Dashboards).

Normalização (Data Cleansing) e Tipagem: * Tratamento de codificações HTML nos nomes dos livros (ex: transformar &AMP; em &).

Padronização de textos para UPPERCASE e remoção de espaços em branco desnecessários.

Tipagem Estrita: Higienização de campos financeiros (remoção de "R$", substituição de vírgula por ponto) e conversão obrigatória para tipos numéricos puros (como REAL ou NUMERIC) para viabilizar cálculos matemáticos no banco de dados.

Idempotência e Relacionamento Universal: Durante a carga, todo e qualquer registro recebe a marcação do id_feira. Para garantir execuções seguras que não gerem dados duplicados (Idempotência), o sistema limpa os registros atrelados a um recibo (sellNumber) antes de reinseri-los. O uso de exclusões em massa (DROP TABLE) fica terminantemente proibido.

### Fase 3: Interação do Utilizador e Enriquecimento (Transform - Parte 1)
1. **Mapeamento Manual (Nova Regra):** Uma vez que a base de `livros` está populada, o utilizador entra no sistema (Frontend) e acede a uma área de configuração de catálogo. Aqui, ele **mapeia manualmente** qual Representante (ex: FLORESCER, PROLEZO) pertence a qual Categoria/Editora. Esta informação é guardada no banco e atualiza o catálogo para a geração dos relatórios.

### Fase 4: Aplicação de Regras de Negócio e Cálculos (Transform - Parte 2)
*(Esta fase ocorre "on-the-fly" ou cria vistas materializadas apenas na hora de gerar os relatórios de prestação de contas, sem apagar os dados base).*
1. **O Filtro de Ouro (Exclusão Lógica):** Para efeitos de relatório às editoras, o algoritmo filtra o universo de pagamentos:
    * Ignora "DESCONTO".
    * Ignora pagamentos externos ("PAGAMENTO SEM GRUPO" / PIX).
    * Ignora tags associadas a equipas de teste.
    * Condiciona a validade a `tagCodes` mapeadas como contas de alunos.
2. **Alocação Proporcional (O Coração da Matemática):**
    * Calcula o peso do pagamento válido em cartão face ao valor bruto dos livros daquela venda específica.
    * Aplica esse percentual (proporção) ao valor individual de cada livro para abater valores pagos por fora.

### Fase 5: Visualização e Exportação (Presentation)
1. **Dashboard Interativo (Nova Regra):** O sistema oferece as visões (Transações, Vendas, Editoras) diretamente no navegador (React), com tabelas pesquisáveis e gráficas dinâmicas.
2. **Geração de PDF:** Mantém-se a capacidade de consolidar todas as visões filtradas (aplicando a Matemática da Alocação) num documento PDF oficial para prestação de contas, juntamente com o Anexo de Inconsistências de Catálogo.
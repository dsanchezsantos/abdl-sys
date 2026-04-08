Documentação Arquitetural e Plano de Implementação

Stack: Laravel + Inertia.js (React) + PostgreSQL

1. Visão Geral da Arquitetura (O Monolito Moderno)

O sistema adota uma arquitetura de Monolito Moderno utilizando a stack TALL/VILT adaptada (Vue/React, Inertia, Laravel, Tailwind).
Não há separação física entre o backend e o frontend. O Laravel gerencia o roteamento, a segurança e a lógica de banco de dados, e utiliza o Inertia.js para renderizar os componentes React no cliente de forma transparente. Os dados trafegam diretamente dos Controladores PHP para as props dos componentes React.

2. Ecossistema de Serviços (Containers Docker)

A aplicação será orquestrada via Docker (docker-compose.yml) com os seguintes serviços dedicados:

app (Laravel PHP-FPM / Octane):

Papel: O coração do sistema. Recebe as requisições HTTP, processa as regras de negócio, gerencia o roteamento via Inertia e interage com o banco de dados.

Vantagem: Utiliza a fachada Http:: nativa para o ETL e o Eloquent ORM para persistência.

node (Vite dev-server):

Papel: Utilizado em ambiente de desenvolvimento para compilar os assets do React e Tailwind com Hot Module Replacement (HMR). (Em produção, os arquivos são compilados estaticamente e servidos pelo Laravel/Nginx, dispensando este container).

db (PostgreSQL):

Papel: Banco de dados relacional que armazena todo o histórico bruto de transações, catálogo de livros e parâmetros de múltiplas feiras (Multi-Tenancy).

redis (Message Broker & Cache):

Papel: Armazenamento em memória super-rápido. Receberá as solicitações de "geração de relatório" e as enfileirará, garantindo que o servidor principal web nunca fique travado aguardando o processamento.

worker (Laravel Queue Worker):

Papel: Um container rodando a mesma imagem do serviço app, mas executando o comando php artisan queue:work. Ele trabalha em background (sem acesso do usuário), lendo a fila do Redis, processando ETLs pesados e conversando com o Gotenberg.

gotenberg (Motor PDF Chromium-based):

Papel: Recebe documentos HTML do worker e os renderiza fielmente para PDF usando um navegador real em headless mode.

3. Regras de Negócio e Fluxo de ETL (Extract, Transform, Load)

Gatilho e Descoberta: Através da interface React, o usuário dispara a sincronização informando as datas da feira. Um Job é enviado ao Redis. O worker consome a API da Nowigo de forma assíncrona.

Persistência Total e Idempotência: Absolutamente todos os dados recebidos da API são gravados. O Eloquent usará o método upsert() (ou delete() cirúrgico antes do insert()) baseado no sell_number para garantir que repetições da extração não gerem dados duplicados.

Enriquecimento Manual: Como a API de origem provê dados rasos, as tabelas autodescobertas (como livros) possuirão campos em branco (Categoria, Representante) que serão classificados manualmente pelo usuário na interface construída em Inertia.js.

4. Solução de Gargalos Críticos: O Desafio do PDF Gigante

Relatórios de auditoria financeira com dezenas de milhares de páginas não podem ser gerados de forma tradicional. A tentativa de carregar todos os dados na RAM ou enviar um HTML gigante para o Gotenberg resultará em Out of Memory (OOM) e derrubará o sistema.

A abordagem abaixo define como o Laravel solucionará este gargalo:

A. Paginação no Banco de Dados (chunk)

O Worker nunca carregará todas as transações de uma vez com ->get(). Ele utilizará o método nativo do Eloquent Model::chunk(500, function ($vendas) {...}). O banco trará os registros em blocos de 500, mantendo o consumo de memória do PHP baixo, na casa dos ~20MB a 30MB, independente do tamanho da feira.

B. Renderização Fragmentada (Split Generation)

Para cada chunk de 500 registros lidos do banco:

O Laravel renderiza uma view Blade HTML (apenas com esses 500 itens).

O Worker envia esse pequeno HTML para o serviço do Gotenberg via requisição HTTP.

O Gotenberg devolve um pequeno PDF parcial (ex: relatorio_parte_1.pdf).

O Laravel salva este arquivo temporariamente no Storage::disk('local').

C. O Merge Final (Evitando o OOM do Gotenberg)

O Gotenberg também falharia se tentasse ler o arquivo inteiro, por isso usamos a API nativa dele de fusão:

Após terminar todos os chunks, o Laravel pega todos os arquivos parciais gerados (parte_1.pdf até parte_100.pdf).

Dispara uma requisição multipart para a rota /forms/pdf/merge do Gotenberg enviando os arquivos.

O Gotenberg junta os binários de forma otimizada e devolve o grande PDF consolidado (ex: 65MB).

D. Entrega Assíncrona Segura

Como provedores de e-mail limitam os anexos a 25MB:

O Laravel salva o "Megarrelatório" fundido no Storage::disk('local') (ou na AWS S3) na pasta de arquivos privados do evento.

O banco de dados atualiza o status do processamento para "Concluído" e gera uma URL Assinada Temporary (usando URL::temporarySignedRoute).

O Laravel dispara o envio de um e-mail (Mail::to()->send(...)) com o link seguro para download e também emite uma notificação em tempo real (via Laravel Reverb / WebSockets) para a tela do React, permitindo que o usuário clique no botão "Baixar PDF" diretamente no sistema.

Os PDFs parciais (parte_X.pdf) são apagados para liberar espaço em disco.
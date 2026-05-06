### Passo 1: A Camada de Integração (O Cliente HTTP)
Antes de falar de filas e banco de dados, o Laravel precisa saber "falar" com a Nowigo.
*   **O Assunto:** Vamos precisar definir uma classe de serviço (Service Class) dedicada exclusivamente a se comunicar com a API. 
*   **O que debater:** Como vamos lidar com o *Rate Limiting* (se a API bloquear por muitos acessos rápidos)? Como vamos formatar o cabeçalho das requisições (Tokens, User-ID)? E como essa classe vai devolver os erros caso a API da Nowigo fique fora do ar?

### Passo 2: A Infraestrutura de Filas (O Ambiente)
Não podemos testar o processamento assíncrono se o ambiente não estiver configurado para isso.
*   **O Assunto:** Configurar o Laravel para parar de processar as coisas na hora (síncrono) e começar a enviar para o Redis.
*   **O que debater:** Onde e como vamos definir aqueles limites de segurança da VPS que discutimos (`--memory=128`, `--max-jobs=500` e *Single Worker*) no ambiente local e de produção.

### Passo 3: O Gatilho e a Trava de Segurança (Mutex)
O utilizador precisa de um botão para iniciar, mas o sistema precisa se proteger.
*   **O Assunto:** A ação no Controller que recebe o clique de "Sincronizar" e a mecânica de travamento.
*   **O que debater:** Como exatamente vamos mudar o status da Feira para `is_sincronizando = true`, como o Frontend vai reagir a isso, e como despachar a primeira tarefa para a fila.

### Passo 4: O "Maestro" (Descobridor e Criador de Lotes)
Esta é a ponta da nossa arquitetura de fragmentação.
*   **O Assunto:** O primeiro Job que roda na fila. Ele não salva nada no banco, ele apenas "olha" para o tamanho do problema.
*   **O que debater:** Como ele vai consultar a primeira página da API para descobrir o `total_pages`, e como ele vai usar a funcionalidade de *Bus::batch()* (Lotes do Laravel) para enfileirar as dezenas de Mini-Jobs sequenciais.

### Passo 5: O "Micro-Trator" (Processamento de 1 Página)
Aqui é onde o trabalho sujo acontece, o coração da idempotência.
*   **O Assunto:** O Mini-Job que recebe a ordem: "Vá lá, baixe apenas a Página 5 e salve no banco".
*   **O que debater:** A ordem exata das operações no banco de dados dentro deste Job. (1º Upsert do Header, 2º Delete/Insert de Pagamentos, 3º Descoberta de Cartões, etc.) e como envolver isso numa *Database Transaction* para garantir que não fiquem dados pela metade se der erro.

### Passo 6: O Fechamento da Corrente (Estatísticas e Destravamento)
O que acontece quando todos os Mini-Jobs terminam com sucesso (ou falham)?
*   **O Assunto:** O Job final que roda automaticamente após o lote.
*   **O que debater:** Como ele vai ser notificado de que o lote acabou, a chamada da atualização da tabela `feira_estatisticas` e a liberação da trava (`is_sincronizando = false`).

### Passo 7: A Estratégia do Primeiro Teste (Dry Run)
Quando tudo isso estiver escrito, não podemos simplesmente apontar para uma feira com 50.000 registros e rezar.
*   **O Assunto:** Como testar com segurança.
*   **O que debater:** Estratégias como limitar o Maestro a baixar apenas 2 páginas no ambiente de desenvolvimento, ou criar um comando no terminal (Artisan Command) para podermos ver o log em tempo real antes de ligar a interface do React.
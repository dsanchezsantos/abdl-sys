**Sim, apenas um Worker dá conta do pipeline inteiro com extrema segurança, desde que nós mudemos a forma como ele "engole" os dados.** 

Para garantir que um único Worker faça o trabalho pesado sem nunca estourar a memória, nós utilizamos uma abordagem de **Fragmentação (Chunking) e Job Batching**, aliada a um "suicídio programado" do processo. 

Aqui está o desenho de como tornar esse único Worker indestrutível:

### 1. Quebrando a Pedra em Pedregulhos (Laravel Job Batching)
O erro clássico que causa estouro de memória é criar um único *Job* chamado `SincronizarFeiraJob` que faz tudo: baixa a página 1 da API, depois a 2, depois a 3, guarda tudo num array gigante no PHP e tenta salvar no banco. O PHP segura esse array na RAM até o script terminar.

A solução é usar o **Laravel Batches** (`Bus::batch`):
1. O sistema dispara um "Job Maestro" muito leve. A única função dele é ir à API da Nowigo e perguntar: *"Quantas páginas de vendas existem nessa feira?"*. A API responde: *"Existem 50 páginas"*.
2. O Maestro, em vez de baixar os dados, despacha **50 Mini-Jobs** (ex: `ProcessarPaginaVendaJob`) para a fila, um para cada página, e morre.
3. O seu único Worker começa a pegar esses Mini-Jobs, um de cada vez. Ele baixa a Página 1, salva no banco e o script do Mini-Job acaba.
4. **O Segredo:** Como o script do Mini-Job acabou, o PHP aciona o *Garbage Collector* e **libera 100% da RAM utilizada**. Depois, ele pega a Página 2 com a memória limpa, e assim por diante. 

### 2. O "Suicídio Programado" do Worker (Proteção do Daemon)
No Laravel, o processo `queue:work` é um *Daemon* (ele fica rodando em loop infinito no Linux). Mesmo liberando a memória a cada Mini-Job, o PHP tem um problema histórico de *Memory Leak* (vazamento de memória) em processos longos, onde alguns megabytes vão ficando presos na RAM.

Para evitar que a VPS sofra com isso ao longo dos dias, nós passamos dois parâmetros mágicos no seu `docker-compose.yml` ou Supervisor:

*   **`--memory=128`**: Diz ao Worker que ele só pode usar 128MB de RAM no máximo. Se ele chegar perto disso, o Laravel mata o processo de forma segura e o reinicia com a memória zerada.
*   **`--max-jobs=500`**: Diz ao Worker para processar 500 tarefas (ou Mini-Jobs) e, logo em seguida, se desligar e reiniciar automaticamente (um "reboot" microscópico que limpa toda a sujeira da memória).

### 3. Upsert e Selects Limitados (A Dieta do Banco)
Do lado do banco de dados, o Worker também precisa ser educado:
*   Ele nunca fará um `$feira->pagamentos()->get()` se a feira tiver 50.000 pagamentos. Isso explodiria a RAM. Ele usará sempre o `$feira->pagamentos()->chunk(500, function(...) { ... })`.
*   Na hora de salvar, o *array* construído a partir do JSON da API terá um limite estrito (ex: 100 registros por vez) antes de disparar o `upsert()` no Postgres.

### Resumo do Pipeline Seguro

Em vez de uma "cobra tentando engolir um elefante inteiro" (onde a memória estoura), a sua arquitetura será uma "formiga cortadeira":
1. Tranca a feira.
2. O Maestro descobre o tamanho do trabalho e cria o lote (Batch).
3. O único Worker transporta um pedacinho por vez, esvaziando a memória a cada viagem.
4. Quando o lote inteiro (`Batch`) finalizar com sucesso, o Laravel dispara automaticamente o encadeamento (*then()*) que chamará o `CalcularEstatisticasJob`.
5. Destranca a feira.

Esta abordagem te dá a tranquilidade absoluta de que a sua feira pode ter 5.000 ou 5.000.000 de transações, o consumo de RAM do seu servidor permanecerá estático e seguro do início ao fim do processo.
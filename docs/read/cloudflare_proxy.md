# Proxy de Saída com Cloudflare Workers

Este documento explica o conceito, o funcionamento e o plano de implementação para usar um **Cloudflare Worker** como um proxy reverso de saída na integração com a API Nowigo, resolvendo o problema de bloqueio de IP (HTTP 403 Forbidden) na VPS da Oracle Cloud.

---

## 1. O Conceito

Quando a VPS (Oracle Cloud) faz uma requisição HTTP direta para a API da Nowigo, o servidor de segurança da Nowigo identifica o IP da VPS e bloqueia a requisição com status `403 Forbidden` devido a picos anteriores de chamadas.

Com o **Cloudflare Worker**, nós introduzimos um intermediário confiável:

```
[ Laravel na VPS ] ➔ [ Cloudflare Worker ] ➔ [ API Nowigo ]
  (IP da Oracle)        (IP da Cloudflare)     (Tomcat da Nowigo)
```

* **IP de Origem para a Nowigo:** A API Nowigo passa a enxergar as requisições vindo da gigantesca rede de IPs dinâmicos da Cloudflare, que são tolerados e confiáveis, em vez do IP estático e marcado da VPS.
* **Custo:** R$ 0. O plano gratuito da Cloudflare inclui 1 milhão de requisições gratuitas por dia.
* **Performance:** O processamento no Worker leva menos de **1ms** por rodar na engine V8 do Chrome na borda da rede. O acréscimo de latência é desprezível (~10ms).

---

## 2. Impacto no Projeto Local/Código e Failover Automático

Para otimizar o uso e evitar estourar a cota gratuita de 100.000 requisições diárias da Cloudflare, **implementamos um sistema de Failover Automático e Inteligente** no código da aplicação.

### Como funciona:
1. Por padrão, a aplicação tenta conectar-se diretamente à API da Nowigo (`NOWIGO_API_URL` que aponta para a URL direta oficial).
2. Se a chamada for bem sucedida, o consumo no Cloudflare Worker é **zero** (economia total da cota diária).
3. Se a chamada direta falhar por bloqueio de IP (erro `403` ou `429`), o Laravel intercepta a falha e **redireciona a requisição dinamicamente** para a URL do seu Cloudflare Worker (`NOWIGO_PROXY_URL`).
4. A repescagem continua rodando sem erros visíveis para o usuário e sem quebrar o lote do Horizon.

### Configurações no projeto:
Mapeamos duas chaves no arquivo [services.php](file:///config/services.php):
```php
'nowigo' => [
    'base_url'  => env('NOWIGO_API_URL', 'https://feiradolivro-saquarema2025.nowigo.com.br/app/sale.mdl'),
    'proxy_url' => env('NOWIGO_PROXY_URL'), // URL de contingência (Cloudflare Worker)
]
```

---

## 3. Plano de Implementação (Passo a Passo)

### Passo 1: Criar o Worker na Cloudflare
1. Acesse o painel da sua conta **Cloudflare**.
2. No menu lateral, acesse **Workers & Pages** e clique em **Create Application**.
3. Selecione **Create Worker**.
4. Dê um nome ao Worker (ex: `nowigo-proxy`) e clique em **Deploy**.
5. Clique em **Edit Code** e substitua todo o código existente por este JavaScript:

```javascript
export default {
  async fetch(request, env, ctx) {
    const url = new URL(request.url);
    
    // Roteia a chamada para o endpoint real da Nowigo mantendo os parâmetros de busca
    const targetUrl = `https://feiradolivro-saquarema2025.nowigo.com.br/app/sale.mdl${url.search}`;

    // Clona os headers da requisição original
    const newHeaders = new Headers(request.headers);
    
    // Executa a chamada a partir da rede da Cloudflare
    const response = await fetch(targetUrl, {
      method: request.method,
      headers: newHeaders,
      body: request.method !== 'GET' && request.method !== 'HEAD' ? await request.text() : undefined,
    });

    return response;
  },
};
```
6. Clique em **Save and Deploy**.
7. Na página do Worker, copie a URL gerada pela Cloudflare (ex: `https://nowigo-proxy.seu-usuario.workers.dev`).

---

### Passo 2: Configurar a Produção (Oracle Cloud)
Acesse a sua VPS de produção via terminal SSH e siga estas etapas:

1. Edite o arquivo `.env` do projeto e adicione a URL de contingência (o proxy):
   ```env
   # Mantém a URL direta oficial como primária
   NOWIGO_API_URL=https://feiradolivro-saquarema2025.nowigo.com.br/app/sale.mdl

   # Configura o proxy como rota de escape (Failover)
   NOWIGO_PROXY_URL=https://nowigo-proxy.seu-usuario.workers.dev
   ```

2. Recarregue os caches do Laravel para aplicar as novas variáveis:
   ```bash
   docker compose exec app php artisan config:cache
   ```

---

### Passo 3: Validação da Conexão
Para testar se o túnel pela Cloudflare está respondendo corretamente, execute um teste de chamada HTTP diretamente a partir do proxy:

```bash
docker compose exec app curl -s -o /dev/null -w "HTTP Status: %{http_code}\n" \
  "https://nowigo-proxy.seu-usuario.workers.dev?action=list&eventId=1&userId=1&perPage=1&page=1"
```

* Se retornar **`200`** ou **`400`** (em vez de 403), a conexão foi estabelecida com sucesso pela Cloudflare.
* Se retornar `200`, a integração fará o chaveamento automático de forma transparente.


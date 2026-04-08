# PMAC Starter Kit - Padrão de Desenvolvimento de Arraial do Cabo

Este repositório contém o boilerplate oficial robusto e moderno desenvolvido para padronizar a engenharia de software dos novos sistemas da Prefeitura Municipal de Arraial do Cabo. Seu objetivo é promover agilidade no início de novos projetos e garantir facilidade de manutenção a longo prazo.

## Principais Tecnologias e Arquitetura

O projeto é estruturado como um monólito modular com as seguintes bases tecnológicas:

- **Backend:** Laravel 12 (PHP 8.5) com arquitetura orientada a serviços (Service Pattern), garantindo Controllers enxutos e regras de negócio encapsuladas e testáveis.
- **Frontend:** React 19 via Inertia.js, simplificando a comunicação client-server e eliminando a complexidade de APIs REST desnecessárias. Inclui Shadcn/UI com Tailwind CSS pré-configurado.
- **Banco de Dados:** PostgreSQL 18 utilizando UUIDs como chaves primárias para maior segurança e escalabilidade.
- **Infraestrutura:** Totalmente containerizada com Docker, incluindo orquestração inteligente de serviços (Redis, Horizon, Mailpit) e isolamento total entre projetos.

## Funcionalidades Integradas

O kit já vem equipado com ferramentas essenciais para produção:

- **Auth e Permissões:** Integração nativa com ArraiAuth (SSO) via Laravel Socialite e validação de permissões (RBAC).
- **Geração de Documentos:** Serviço Gotenberg integrado para renderização de PDFs modernos via HTML/CSS, substituindo bibliotecas legadas.
- **Segurança e Backups:** Configuração nativa do `spatie/laravel-backup` com driver para Google Drive para rotinas de backup automático.
- **Qualidade de Código:** Ferramentas obrigatórias de linting e análise estática (Laravel Pint e Larastan) para manter o padrão de qualidade.

Este starter kit serve como a fundação sólida sobre a qual todos os novos módulos e sistemas da prefeitura devem ser construídos.

## 🚀 Como Iniciar o Desenvolvimento

Siga os passos abaixo para configurar o ambiente usando Docker.

### 1. Criar o arquivo de ambiente
Duplicar a estrutura do arquivo .env de exemplo em um arquivo de nome '.env' para poder criar as configurações
```bash
cp .env.example .env
```

### 2. Subir a Infraestrutura
Inicie os containers em modo "detached" e force o build inicial:
```bash
docker compose up -d --build
```
> **Nota:** A aplicação ficará acessível em **http://localhost:8889**.

#### ⚠️ Solução de Problemas (Porta 5432 em uso)

Se você receber um erro de **"Bind for 0.0.0.0:5432 failed: port is already allocated"**, significa que você já tem outro banco de dados (ou outro projeto deste kit) rodando na sua máquina.

Para resolver:

1. Abra o arquivo `docker-compose.yml`.
2. Localize o serviço `db`.
3. Altere o mapeamento de porta de `"5432:5432"` para uma porta livre (ex: `"5433:5432"` ou `"5434:5432"`).
4. Salve o arquivo e execute o comando `docker compose up -d` novamente.

### Por que isso resolve?

No Docker, o mapeamento funciona como `HOST:CONTAINER`.

* O lado **direito** (`:5432`) é a porta dentro do container, que o Laravel usa para se comunicar internamente. **Essa você nunca muda.**
* O lado **esquerdo** (`5432:`) é a porta na sua máquina física. Ao mudar para `5433:`, você libera o conflito e consegue conectar seu SGBD (DBeaver/Postico) através da porta `5433`.

---

### 3. Instalar Dependências
Instale os pacotes do PHP e Node.js dentro do container:
```bash
# Backend
docker compose exec app composer install

# Frontend
docker compose exec app npm install

# MCP Server
docker compose exec app bash -c "cd .mcp && npm install"
```

### 4. Configurar o Laravel
Criar a chave de configuração do Laravel
```bash
# Laravel
docker compose exec app php artisan key:generate
```

### 5. Configurar Banco de Dados
Rode as migrações para criar as tabelas no PostgreSQL:
```bash
docker compose exec app php artisan migrate
```
> As credenciais padrão do banco de dados são:
> - **Host:** `localhost` (externo) ou `db` (interno)
> - **Porta:** `5432`
> - **Banco:** `laravel`
> - **Usuário:** `laravel`
> - **Senha:** `secret`

### 6. Compilar Assets (HMR)
Para trabalhar no frontend com atualização automática (Hot Module Replacement), mantenha este comando rodando em um terminal separado:
```bash
docker compose exec app npm run dev
```

### 🛠️ Acesso aos Serviços Auxiliares
- **Aplicação Principal:** [http://localhost:8889](http://localhost:8889)
- **Mailpit (Emails):** [http://localhost:8025](http://localhost:8025)
- **Horizon (Filas):** [http://localhost:8889/horizon](http://localhost:8889/horizon)
- **Gotenberg (PDF):** Interno via `http://gotenberg:3000`

---

## 🤖 Auxílio ao Desenvolvedor (MCP Server)

Este `pmac-starter-kit` vem com um **Model Context Protocol (MCP) Server** embutido na pasta `.mcp/`. 
O objetivo dele é plugar as **regras de negócio**, **arquitetura do PMAC** e **comandos do Docker** diretamente na sua Inteligência Artificial favorita (Cursor, Windsurf, Claude Desktop, etc).

Com ele ativo, se você pedir para a IA *"Criar um módulo de Secretaria"*, ela automaticamente lerá as nossas regras de arquitetura (`docs/ai_architecture_guide.md`), lerá sobre o objetivo do seu sistema (`docs/project_requirements.md`), criará os Services via Docker e rodará o Linter de formatação para você, tudo sozinha!

### Como ativar na sua IDE (Ex: Cursor / Windsurf)
1. Vá nas configurações do seu editor (geralmente em `Cursor Settings > Features > MCP`).
2. Clique em **Add New MCP Server**.
3. Escolha o tipo **Command** ou **stdio**.
4. Dê o nome de `pmac-mcp`.
5. No comando para rodar, coloque:
```bash
bash /caminho/absoluto/para/pmac-starter-kit/.mcp/start.sh
```
6. Salve. O editor agora terá contexto total sobre as regras de Arraial do Cabo!

> **Pré-requisito:** Antes de configurar qualquer editor, garanta que o `start.sh` tenha permissão de execução. O Linux exige isso para rodar scripts `.sh` diretamente. Execute **uma única vez** após clonar o projeto:
> ```bash
> chmod +x .mcp/start.sh
> ```
> Apesar do Git rastrear permissões de arquivo, isso pode ser perdido dependendo de configurações do seu sistema. Em caso de erro `Permission denied` ao iniciar o MCP, basta rodar este comando novamente.

### Como ativar no Antigravity
O Antigravity gerencia todos os servidores MCP através do arquivo `~/.gemini/antigravity/mcp_config.json`. Para adicionar o servidor deste projeto:

1. Abra o arquivo `~/.gemini/antigravity/mcp_config.json`.
2. Adicione a seguinte entrada (ajustando o caminho para onde você clonou o projeto):
```json
{
  "mcpServers": {
    "pmac-starter-kit": {
      "command": "bash",
      "args": [
        "/caminho/absoluto/para/pmac-starter-kit/.mcp/start.sh"
      ]
    }
  }
}
```
3. Salve o arquivo e reinicie o Antigravity.

> **Dica:** Você pode acessar este mesmo arquivo pelo próprio Antigravity em: menu `"..."` (reticências) no topo do painel lateral → **Manage MCP Servers** → **View raw config**.

> **Importante:** O servidor MCP roda dentro do container Docker — logo, o container `app` precisa estar ativo (`docker compose up -d`) para o MCP funcionar. Não é necessário ter Node.js instalado na máquina.

> **Importante:** Logo após clonar este projeto, não esqueça de abrir e preencher o arquivo `docs/project_requirements.md` com o escopo do seu sistema para que a IA possa te ajudar de verdade.

---

## ⚠️ Troubleshooting (Solução de Problemas)

### Erro file_put_contents(...): Failed to open stream: Permission denied
Este erro ocorre porque o usuário do servidor web não possui as devidas permissões de escrita nas pastas `storage` e `bootstrap/cache`, essenciais para o funcionamento do Laravel.

Para resolver o problema, execute os seguintes comandos na raiz do projeto:

```bash
docker compose exec app chown -R www-data:www-data storage bootstrap/cache
docker compose exec app chmod -R 775 storage bootstrap/cache
```

**Por que isso resolve?**
Os comandos acima transferem a propriedade dos diretórios de armazenamento para o usuário do servidor web (`www-data`) e aplicam permissões que garantem o direito de leitura, escrita e execução, permitindo que a aplicação salve seus logs e arquivos de cache corretamente.

### Os arquivos de storage e bootstrap apareceram como modificados no Git. O que eu faço?
Após executar os comandos de permissão acima para resolver o problema anterior, o Git irá listar alguns arquivos sob o rastreio dele (como os arquivos `.gitignore` do `storage/` e do `bootstrap/cache/`) como **modificados**, mesmo que você não tenha editado o conteúdo deles.

**Por que isso acontece?**
O Git rastreia também as **permissões de arquivo** (como quando um arquivo comum passar a ser executável). Como o comando que executamos foi recursivo (com a flag `-R`), a permissão dos arquivos lá dentro também foi alterada (geralmente de `644` para `775`), e o Git identifica isso como uma modificação.

**Como resolver?**
Você pode restaurar o estado original desses arquivos rastreados pelo Git, desfazendo essa alteração de permissão indesejada:

```bash
sudo git restore storage/ bootstrap/cache/
```
*(Nota: O `sudo` pode ser necessário dependo do dono da pasta atualmente no seu ambiente Linux hospedeiro).*

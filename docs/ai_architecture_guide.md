# Guia de Arquitetura e Serviços para Agentes de IA

Este documento descreve os padrões arquiteturais, decisões de design e serviços disponíveis no `pmac-starter-kit`. Utilize este guia como referência ao implementar novas funcionalidades ou realizar manutenções.

## 1. Visão Geral da Arquitetura

O projeto é um **Monólito Modular** construído sobre:
- **Backend:** Laravel 12 (PHP 8.5)
- **Frontend:** React 19 visualizado através do Inertia.js (SSR/CSR híbrido sem complexidade de API REST completa)
- **Banco de Dados:** PostgreSQL 18
- **Infraestrutura:** Docker (Dev/Prod)

## 2. Padrões de Código (Backend)

### Service Pattern (Camada de Serviço)
- **Regra:** Toda a lógica de negócio deve residir em classes de Serviço (`app/Services`).
- **Controllers:** Devem ser "magros" (skinny). Responsabilidade única de receber a Request, chamar o Service apropriado e retornar a Response (Inertia render ou Redirect).
- **Validação:** Utilize `FormRequests` para validação de entrada de dados.

### Eloquent Models
- **Propósito:** Definição de esquema, relacionamentos e Scopes locais.
- **Identificadores:** UUIDs são utilizados como chaves primárias padrão.
- **Restrição:** Evite colocar lógica de negócio complexa nos Models.

### Repositories (Opcional)
- **Uso:** Reservado estritamente para consultas SQL complexas, relatórios pesados ou otimizações de banco de dados.
- **Regra:** Não abstraia o Eloquent para operações CRUD simples (create, update, delete, find). Use o Model diretamente ou via Service para esses casos.

## 3. Infraestrutura e Containerização

O ambiente é 100% Dockerizado com `docker-compose.yml` base e overrides.

### Serviços Principais
- **App:** Servidor PHP/Laravel.
- **DB:** PostgreSQL 18.
- **Redis:** Cache e gerenciador de filas. Cada projeto tem seu container isolado.
- **Horizon:** Painel de gerenciamento de filas do Laravel. Também isolado por projeto para evitar "vizinhos barulhentos".

### Serviços Auxiliares
- **Gotenberg:** API para conversão de HTML/CSS (Tailwind suportado) em PDF. Substitui bibliotecas PHP legadas como mpdf/dompdf.
- **Mailpit:** Servidor SMTP fake para capturar e visualizar e-mails em ambiente de desenvolvimento. (Desativado em produção).

## 4. Integrações e Segurança

### Autenticação e Autorização (RBAC)
- **ArraiAuth:** SSO oficial. Integração via Laravel Socialite.
- **Permissões:** Sistema de RBAC (Role-Based Access Control) nativo para gestão de acesso. Middlewares garantem a segurança das rotas.

### Backups
- **Ferramenta:** `spatie/laravel-backup`.
- **Destino:** Google Drive (Driver configurado).
- **Rotina:** Backup diário automático de banco de dados e arquivos.

## 5. Frontend (React + Inertia)

- **Estilização:** Tailwind CSS + Shadcn/UI (Componentes pré-construídos).
- **Roteamento:** Definido pelas rotas do Laravel (`routes/web.php`), servidas para o React via Inertia.
- **Estado:** Gerenciamento de estado local do componente ou via Props do Inertia.

## 6. Qualidade de Código (QA)

Ferramentas obrigatórias no pipeline:
- **Laravel Pint:** Linter para padronização de estilo de código PHP.
- **Larastan:** Análise estática de código para prevenção de bugs (Nível alto).

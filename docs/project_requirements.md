# Escopo e Requisitos do Projeto

> **Nota para o Desenvolvedor:**
> Este documento é o coração do escopo do seu projeto. Preencha-o logo após clonar o PMAC Starter Kit. O Servidor MCP nativo (`.mcp/`) fará com que sua IA (Cursor, Windsurf, Claude) leia este arquivo **automaticamente** para entender as regras de negócio e funcionalidades antes de ajudar você com código.

## 🎯 Objetivo do Sistema
*(Ex: "Módulo de agendamentos online para a Secretaria de Saúde do município. O sistema visa eliminar filas nas unidades básicas oferecendo marcação pelo celular do munícipe.")*


## 📋 Funcionalidades Principais (Em alto nível)
1. Autenticação unificada via ArraiAuth.
2. *(Ex: Calendário para munícipes marcarem consultas dependendo da unidade)*.
3. *(Ex: Painel para os atendentes das unidades validarem as consultas e remarcarem se necessário)*.
4. *(Ex: Relatório mensal para a gestão sobre as unidades mais sobrecarregadas)*.


## 🔒 Regras de Negócio Cruciais (O que a IA NÃO PODE errar)
- **Regra 1:** *(Ex: Munícipes não podem marcar mais de 2 consultas num período de 30 dias).*
- **Regra 2:** *(Ex: O Perfil Atendente de Unidade só pode ver dados da própria Unidade. Nunca de outra).*
- **Segurança:** Apenas usuários com a role `Admin do Sistema` podem excluir registros. Os demais podem apenas inativar (Soft Deletes).


## 📦 Entidades / Tabelas de Banco de Dados Previstas
*(Lista para servir de norte inicial para as criações de Models)*
- `User` (Obrigatoriamente integrado ao ArraiAuth)
- *(Ex: `UnidadeSaude`)*
- *(Ex: `Consulta` (Relaciona User e UnidadeSaude))*


## 🤔 Dúvidas Pendentes / A Definir:
- [ ] *(Ex: Como vamos notificar que a consulta foi cancelada?)*

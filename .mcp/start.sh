#!/bin/bash
# PMAC MCP Server - Iniciado via Docker (sem necessidade de Node.js no host)
# O container 'app' precisa estar rodando: docker compose up -d

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"

docker compose -f "$PROJECT_ROOT/docker-compose.yml" exec -T app node .mcp/index.js

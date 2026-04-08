import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { z } from "zod";
import fs from "fs/promises";
import path from "path";
import { exec } from "child_process";
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const projectRoot = path.resolve(__dirname, '..');

const server = new McpServer({
  name: "pmac-starter-kit-mcp",
  version: "1.0.0"
});

// Helper para executar comandos no docker
const execDocker = (command) => {
  return new Promise((resolve, reject) => {
    exec(`docker compose exec -T app ${command}`, { cwd: projectRoot }, (error, stdout, stderr) => {
      if (error) {
         resolve(`Error: ${error.message}\nStderr: ${stderr}\nStdout: ${stdout}`);
      } else {
         resolve(stdout || stderr || "Comando executado com sucesso.");
      }
    });
  });
};

// Resources
server.resource(
  "architecture_guide",
  "file:///docs/ai_architecture_guide.md",
  { description: "Guia de Arquitetura e Padrões do PMAC Starter Kit" },
  async (uri) => {
    try {
      const content = await fs.readFile(path.join(projectRoot, 'docs', 'ai_architecture_guide.md'), 'utf-8');
      return {
        contents: [{
          uri: uri.href,
          text: content
        }]
      };
    } catch (e) {
      return { contents: [{ uri: uri.href, text: "Erro ao ler o guia de arquitetura: " + e.message }] };
    }
  }
);

server.resource(
  "business_requirements",
  "file:///docs/project_requirements.md",
  { description: "Requisitos de Negócio, Escopo e Funcionalidades do Projeto atual" },
  async (uri) => {
    try {
      const content = await fs.readFile(path.join(projectRoot, 'docs', 'project_requirements.md'), 'utf-8');
      return {
        contents: [{
          uri: uri.href,
          text: content
        }]
      };
    } catch (e) {
      return { contents: [{ uri: uri.href, text: "O arquivo de requisitos ainda não foi criado ou preenchido pelo desenvolvedor. (docs/project_requirements.md)" }] };
    }
  }
);


// Tools
server.tool(
  "make_service",
  "Cria uma classe Service seguindo o padrão PMAC",
  {
    name: z.string().describe("Nome do Service (ex: Auth/LoginService ou UserService)"),
  },
  async ({ name }) => {
    const result = await execDocker(`php artisan make:class Services/${name}`);
    return {
      content: [{ type: "text", text: result }]
    };
  }
);

server.tool(
  "run_pint",
  "Executa o Laravel Pint para formatar e padronizar o código PHP",
  {},
  async () => {
    const result = await execDocker(`./vendor/bin/pint`);
    return {
      content: [{ type: "text", text: result }]
    };
  }
);

server.tool(
  "run_larastan",
  "Executa o Larastan para análise estática de código (QA)",
  {},
  async () => {
    const result = await execDocker(`./vendor/bin/phpstan analyse`);
    return {
      content: [{ type: "text", text: result }]
    };
  }
);

async function run() {
  const transport = new StdioServerTransport();
  await server.connect(transport);
  console.error("PMAC MCP Server running on stdio");
}

run().catch(console.error);

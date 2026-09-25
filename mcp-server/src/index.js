#!/usr/bin/env node
// Local/stdio mode — for a single person running Claude Desktop with their own token already in
// hand. This is the fallback for tools that can't do a remote OAuth connection at all; whenever
// the AI tool supports it, prefer src/http-server.js instead, which lets the buyer connect through
// a normal login + consent screen with no token to copy. See docs/ai-ordering/README.md.
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";
import { createMcpServer } from "./create-server.js";

const token = process.env.CIN_API_TOKEN;

if (!token) {
  console.error(
    "[chinainnigeria-mcp-server] CIN_API_TOKEN is not set. See docs/ai-ordering/README.md for how to obtain one."
  );
  process.exit(1);
}

const server = createMcpServer(token);
const transport = new StdioServerTransport();
await server.connect(transport);

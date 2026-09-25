#!/usr/bin/env node
// Remote mode — a single shared server that Claude (or any MCP client supporting remote,
// OAuth-authenticated connectors) talks to over HTTPS. Each buyer goes through ChinaInNigeria's
// own login + consent screen (see resources/views/oauth/authorize.blade.php) and the resulting
// access token travels with every request as a normal Authorization header — this file never
// stores or even sees a long-lived credential, and issues nothing itself. It only verifies the
// token on each request (by asking the Laravel API who it belongs to) and, for that one request,
// builds an McpServer that calls the API back as that same buyer. Deploy this behind HTTPS at a
// public URL and register that URL with the AI tool; see docs/ai-ordering/README.md.
import express from "express";
import { StreamableHTTPServerTransport } from "@modelcontextprotocol/sdk/server/streamableHttp.js";
import { requireBearerAuth } from "@modelcontextprotocol/sdk/server/auth/middleware/bearerAuth.js";
import { InvalidTokenError } from "@modelcontextprotocol/sdk/server/auth/errors.js";
import { createMcpServer } from "./create-server.js";
import { apiRequest } from "./api.js";

const PORT = process.env.PORT || 3333;
// The Laravel app's own origin (no /api/v1 suffix) — where the actual OAuth server lives. Buyers
// never see this server's URL directly for auth; it's only advertised so a spec-aware MCP client
// can discover it.
const WEB_BASE_URL = (
  process.env.CIN_WEB_BASE_URL || (process.env.CIN_API_BASE_URL || "").replace(/\/api\/v1\/?$/, "")
).replace(/\/$/, "");
// Where THIS server itself is publicly reachable once deployed — required so the metadata below
// can point back at itself with an absolute URL, rather than guessing it from request headers
// (which isn't reliable behind an arbitrary proxy/load balancer).
const PUBLIC_URL = (process.env.CIN_MCP_PUBLIC_URL || "").replace(/\/$/, "");

if (!WEB_BASE_URL || !PUBLIC_URL) {
  console.error(
    "[chinainnigeria-mcp-server] Set CIN_WEB_BASE_URL (or CIN_API_BASE_URL) and CIN_MCP_PUBLIC_URL before starting the HTTP server."
  );
  process.exit(1);
}

const RESOURCE_METADATA_URL = `${PUBLIC_URL}/.well-known/oauth-protected-resource`;

const app = express();
app.use(express.json());

// RFC 9728 — tells a spec-aware client (Claude's remote connector) where the real authorization
// server is, so it can start the OAuth dance itself instead of a human handing it a token.
app.get("/.well-known/oauth-protected-resource", (_req, res) => {
  res.json({
    resource: `${PUBLIC_URL}/mcp`,
    authorization_servers: [WEB_BASE_URL],
    scopes_supported: ["orders:place", "orders:read"],
    bearer_methods_supported: ["header"],
  });
});

const verifier = {
  async verifyAccessToken(token) {
    try {
      const me = await apiRequest(token, "GET", "/me");

      return {
        token,
        clientId: "chinainnigeria-buyer",
        // The real scope check happens server-side on every Laravel route (see routes/api.php's
        // CheckToken middleware) — this is informational for the MCP client, not enforcement.
        scopes: ["orders:place", "orders:read"],
        extra: { userId: me.id, email: me.email },
      };
    } catch {
      throw new InvalidTokenError("The access token is invalid, expired, or has been revoked.");
    }
  },
};

app.use(
  "/mcp",
  requireBearerAuth({
    verifier,
    resourceMetadataUrl: RESOURCE_METADATA_URL,
  })
);

app.post("/mcp", async (req, res) => {
  // Stateless by design: a fresh server + transport per request, scoped to exactly the buyer whose
  // token requireBearerAuth just validated. Nothing about one buyer's session can leak into
  // another's, and there's no in-memory session table to worry about across restarts/scaling.
  const server = createMcpServer(req.auth.token);
  const transport = new StreamableHTTPServerTransport({ sessionIdGenerator: undefined });

  res.on("close", () => {
    transport.close();
    server.close();
  });

  await server.connect(transport);
  await transport.handleRequest(req, res, req.body);
});

app.get("/mcp", (req, res) => {
  res.status(405).json({ error: "Method not allowed. This server runs in stateless mode (POST only)." });
});

app.listen(PORT, () => {
  console.log(`[chinainnigeria-mcp-server] Listening on :${PORT}, delegating OAuth to ${WEB_BASE_URL}`);
});

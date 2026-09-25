# AI ordering (Claude, ChatGPT, Gemini, Grok, ...)

Lets a buyer connect an AI tool to their ChinaInNigeria account through a normal login + consent
screen — never a token they have to copy/paste or a curl command they have to run — and have it
search products, build a cart, and start checkout on their behalf.

## Why it's built this way

There is no prompt that makes a generic chat model "just place an order" — none of these tools can
make authenticated calls against your database on their own. What actually makes this reliable is
three pieces:

1. **A small, scoped JSON API** (`routes/api.php`, under `/api/v1`) that wraps the exact same
   services the storefront itself uses — `CartResolver`, `TieredPricingService`,
   `CheckoutService`. No business logic was duplicated; the API controllers in
   `app/Http/Controllers/Api/V1/` are thin JSON wrappers around those same services, so pricing,
   stock, and MOQ rules can't drift between the website and the AI path.
2. **A real OAuth2 server** (Laravel Passport). When a buyer connects Claude or a ChatGPT Custom
   GPT, they're redirected to `GET /oauth/authorize` — ChinaInNigeria's own login page if they
   aren't already signed in, then a consent screen (`resources/views/oauth/authorize.blade.php`)
   listing exactly what the AI tool is asking for. Approving it never hands the AI tool a password
   — it gets a scoped, revocable access token, the same way "Sign in with Google" works. Buyers
   manage/revoke connected apps from **My Account → Connected AI Assistants**.
3. **An integration per AI tool** that speaks that OAuth flow:
   - **ChatGPT Custom GPT Action** — native OAuth support, works with zero extra infrastructure.
   - **Claude** — a remote MCP server (`mcp-server/`, `npm run start:remote`) that Claude connects
     to as a "custom connector"; it delegates the actual login/consent to the same OAuth server.
   - **Gemini / others** — the OpenAPI schema (`openapi.yaml`) works anywhere OAuth2 Actions/tools
     are supported.

**Checkout never charges anyone.** `POST /api/v1/checkout` creates the order and returns a Korapay
`checkout_url` — a hosted payment page. The buyer (a human) has to open that link and pay
themselves. This is deliberate: an AI agent, or a leaked token, can create draft orders but can
never move money on its own.

## One-time setup

```bash
composer require laravel/passport
php artisan migrate                 # Passport's oauth_* tables
php artisan passport:keys
npm run build                       # picks up the Tailwind classes in the new consent screen
```

Nothing else changes in `.env` — this reuses the app's existing DB, session, and Korapay config.

## Registering a client for each AI tool

Each AI tool is one OAuth "client". Create one per tool with:

```bash
php artisan passport:client
```

- **Name**: whatever the buyer should see on the consent screen, e.g. "ChatGPT" or "Claude".
- **Redirect URI**: the exact callback URL that tool gives you (see below) — comma-separate if it
  asks for more than one.
- Answer "yes" to confidential/has a secret **unless** the tool's setup screen has no secret field
  (e.g. a PKCE-only public client) — the artisan command lets you pick.

This gives you a **Client ID** and (for confidential clients) a **Client Secret** to paste into that
tool's connector setup.

## Connecting ChatGPT (Custom GPT Action)

1. Create a Custom GPT → Actions → import `openapi.yaml`.
2. Set Authentication to **OAuth**, and fill in:
   - Client ID / Client Secret — from `passport:client` above.
   - Authorization URL: `https://your-domain.test/oauth/authorize`
   - Token URL: `https://your-domain.test/oauth/token`
   - Scope: `orders:place orders:read`
3. Save — ChatGPT then shows you its own **Callback URL**. Go back and create the Passport client
   using that exact URL as the redirect URI (or update an existing client's redirect URI).
4. The first time a buyer uses the GPT, ChatGPT sends them to ChinaInNigeria's login + consent
   screen automatically. Nothing to copy or paste.

## Connecting Claude (remote MCP connector)

Deploy the MCP server publicly (any Node host works — it has no database of its own):

```bash
cd mcp-server
npm install
CIN_API_BASE_URL=https://your-domain.test/api/v1 \
CIN_WEB_BASE_URL=https://your-domain.test \
CIN_MCP_PUBLIC_URL=https://mcp.your-domain.test \
npm run start:remote
```

Then in Claude, add a **custom connector** pointing at `https://mcp.your-domain.test/mcp`. Claude
discovers the OAuth server itself (via `/.well-known/oauth-protected-resource`, which points at
your Laravel app's `/.well-known/oauth-authorization-server`) and walks the buyer through the same
login + consent screen. If Claude's UI asks you to register a client id/secret up front rather than
discovering everything automatically, create one with `passport:client` as above, using whatever
redirect URI Claude's connector setup shows you.

Claude then has `search_products`, `get_product`, `view_cart`, `add_to_cart`, `remove_cart_item`,
`checkout`, `get_order`, and `list_orders` tools, scoped to that one buyer's account.

### Local/dev fallback (stdio, no OAuth dance)

For quick local testing without deploying anything, `mcp-server/src/index.js` still runs over
stdio with a token you already have (e.g. one you minted for yourself while testing the OAuth flow
manually):

```json
{
  "mcpServers": {
    "chinainnigeria": {
      "command": "node",
      "args": ["/absolute/path/to/mcp-server/src/index.js"],
      "env": {
        "CIN_API_BASE_URL": "https://your-domain.test/api/v1",
        "CIN_API_TOKEN": "the-access-token"
      }
    }
  }
}
```

This is a developer convenience, not what buyers should use — the whole point of the OAuth setup
above is that a buyer never sees or handles a raw token.

## Connecting Gemini / Grok / others

Any tool that supports OAuth2 Actions/extensions can use `openapi.yaml` the same way ChatGPT does.
A tool with no OAuth or tool-calling support at all cannot actually place an order — at most it can
help the buyer draft what to do next, which they'd still have to act on themselves.

## Safety properties worth knowing

- A connected app can only place/read orders — it has no access to settings, payouts, other users,
  or the admin/vendor surfaces (see `Passport::tokensCan()` in `AppServiceProvider`).
- A buyer approves scopes explicitly on the consent screen and can revoke a connected app at any
  time from **My Account → Connected AI Assistants** — no password reset required, no waiting on
  support.
- Every product an agent can see or order is filtered through `Product::scopePubliclyListed()` —
  the same visibility rule the storefront itself uses.
- Stock, minimum-order-quantity, and tiered pricing are re-validated server-side at both
  add-to-cart and checkout time — an agent cannot order below MOQ or below current stock by
  crafting the request differently than the website would.
- No payment ever completes through this API — only through the buyer opening the returned
  `checkout_url` themselves.

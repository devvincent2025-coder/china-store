import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { z } from "zod";
import { apiRequest } from "./api.js";

const json = (data) => ({ content: [{ type: "text", text: JSON.stringify(data, null, 2) }] });

/**
 * Builds one McpServer bound to a single buyer's OAuth access token. The HTTP transport
 * (src/http-server.js) creates a fresh instance of this per request, right after
 * requireBearerAuth validates that request's token — so a tool call here can never accidentally
 * act as a different buyer than the one who is actually connected. The stdio transport
 * (src/index.js) creates exactly one instance for the whole process, using a token from its
 * environment instead.
 */
export function createMcpServer(token) {
  const server = new McpServer({ name: "chinainnigeria", version: "1.0.0" });
  const call = (method, path, body) => apiRequest(token, method, path, body);

  server.tool(
    "search_products",
    "Search ChinaInNigeria's product catalog. Use this before adding anything to cart, to find the exact product/vendor the buyer wants and confirm price and stock.",
    {
      q: z.string().optional().describe("Free-text search term, e.g. 'wireless earbuds'"),
      category: z.number().int().optional().describe("Category id, if already known"),
      max_price: z.number().optional().describe("Maximum price in naira"),
      in_stock: z.boolean().optional().describe("Only return items currently in stock"),
    },
    async (args) => {
      const params = new URLSearchParams();
      for (const [key, value] of Object.entries(args)) {
        if (value !== undefined) params.set(key, String(value));
      }
      return json(await call("GET", `/products?${params.toString()}`));
    }
  );

  server.tool(
    "get_product",
    "Get full detail for one product by slug (from search_products results), including variants (e.g. size/color), each with its own price, stock and minimum order quantity.",
    { slug: z.string() },
    async ({ slug }) => json(await call("GET", `/products/${encodeURIComponent(slug)}`))
  );

  server.tool("view_cart", "View everything currently in the buyer's cart, with line totals and a subtotal.", {}, async () =>
    json(await call("GET", "/cart"))
  );

  server.tool(
    "add_to_cart",
    "Add a product (optionally a specific variant) to the buyer's cart. Confirm the product, variant, quantity and price with the buyer before calling this — it changes real state on their account.",
    {
      product_id: z.number().int(),
      product_variant_id: z.number().int().optional(),
      quantity: z.number().int().min(1),
    },
    async (args) => json(await call("POST", "/cart/items", args))
  );

  server.tool(
    "remove_cart_item",
    "Remove one line from the buyer's cart by its cart item id (from view_cart).",
    { cart_item_id: z.number().int() },
    async ({ cart_item_id }) => json(await call("DELETE", `/cart/items/${cart_item_id}`))
  );

  server.tool(
    "checkout",
    "Turn the buyer's current cart into an order and get a Korapay payment link. This does NOT charge the buyer — it only creates the order and returns a checkout_url. You must tell the buyer to open that link themselves to complete payment; never treat the order as paid just because this call succeeded.",
    {
      shipping_name: z.string(),
      shipping_phone: z.string(),
      shipping_line1: z.string(),
      shipping_city: z.string(),
      shipping_state: z.string(),
    },
    async (args) =>
      json(
        await call("POST", "/checkout", {
          shipping_address: {
            name: args.shipping_name,
            phone: args.shipping_phone,
            line1: args.shipping_line1,
            city: args.shipping_city,
            state: args.shipping_state,
          },
        })
      )
  );

  server.tool(
    "get_order",
    "Look up an order's status and contents by its order number (e.g. CIN-20260925-AB12CD).",
    { order_number: z.string() },
    async ({ order_number }) => json(await call("GET", `/orders/${encodeURIComponent(order_number)}`))
  );

  server.tool("list_orders", "List the buyer's recent orders.", {}, async () => json(await call("GET", "/orders")));

  return server;
}

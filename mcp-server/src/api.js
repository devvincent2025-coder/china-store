const BASE_URL = (process.env.CIN_API_BASE_URL || "https://chinainnigeria.com/api/v1").replace(/\/$/, "");

/**
 * Thin fetch wrapper around the Laravel API in routes/api.php. `token` is the caller's own OAuth
 * access token — obtained via the real /oauth/authorize consent screen, never something this file
 * mints or stores — so every action an AI tool takes through this server is scoped to exactly what
 * that buyer approved (see Passport::tokensCan() in app/Providers/AppServiceProvider.php).
 */
export async function apiRequest(token, method, path, body) {
  const res = await fetch(`${BASE_URL}${path}`, {
    method,
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      Authorization: `Bearer ${token}`,
    },
    body: body ? JSON.stringify(body) : undefined,
  });

  const text = await res.text();
  let json;
  try {
    json = text ? JSON.parse(text) : {};
  } catch {
    throw new Error(`Non-JSON response (${res.status}) from ${path}: ${text.slice(0, 300)}`);
  }

  if (!res.ok) {
    const message = json.message || json.errors ? JSON.stringify(json.errors ?? json.message) : `HTTP ${res.status}`;
    throw new Error(`ChinaInNigeria API error on ${method} ${path}: ${message}`);
  }

  return json;
}

/**
 * Siroko API — k6 performance test
 *
 * Targets from PLAN §10:
 *   GET  /api/products        P95 < 50 ms
 *   GET  /api/products/{id}   P95 < 50 ms
 *   GET  /api/carts/{id}      P95 < 30 ms
 *   POST /api/orders          P95 < 200 ms
 *   GET  /api/search          P95 < 150 ms
 *
 * ── Run via Docker (recommended, uses internal network) ──────────────────────
 *
 *   docker compose --profile k6 run --rm k6
 *
 * ── Run with k6 installed locally ───────────────────────────────────────────
 *
 *   BASE_URL=http://localhost:8080 \
 *   ADMIN_KEY=<your-key> \
 *   k6 run k6/load-test.js
 *
 * ── Skip search if OPENAI_API_KEY is not configured ─────────────────────────
 *
 *   SKIP_SEARCH=1 k6 run k6/load-test.js
 */

import http from 'k6/http';
import { check, group, sleep } from 'k6';
import { textSummary } from 'https://jslib.k6.io/k6-summary/0.0.2/index.js';

// ── Config ────────────────────────────────────────────────────────────────────

const BASE_URL   = (__ENV.BASE_URL   || 'http://localhost:8080').replace(/\/$/, '');
const ADMIN_KEY  = __ENV.ADMIN_KEY   || 'change-me';
const SKIP_SEARCH = __ENV.SKIP_SEARCH === '1';

const JSON_HEADERS = { 'Content-Type': 'application/json' };
const AUTH_HEADERS = { ...JSON_HEADERS, 'Authorization': `Bearer ${ADMIN_KEY}` };

// ── Load profile: warm-up → steady → ramp-down ───────────────────────────────

function rampingScenario(vus, exec, tag) {
  return {
    executor: 'ramping-vus',
    startVUs: 0,
    stages: [
      { duration: '15s', target: vus },
      { duration: '60s', target: vus },
      { duration: '15s', target: 0 },
    ],
    exec,
    tags: { scenario: tag },
  };
}

const scenarios = {
  catalog:  rampingScenario(10, 'catalogScenario',  'catalog'),
  cart:     rampingScenario(10, 'cartScenario',     'cart'),
  checkout: rampingScenario(5,  'checkoutScenario', 'checkout'),
};

if (!SKIP_SEARCH) {
  scenarios.search = rampingScenario(5, 'searchScenario', 'search');
}

export const options = {
  scenarios,

  thresholds: {
    // Per-endpoint P95 latency (PLAN §10)
    'http_req_duration{endpoint:list_products}': ['p(95)<50'],
    'http_req_duration{endpoint:get_product}':   ['p(95)<50'],
    'http_req_duration{endpoint:get_cart}':       ['p(95)<30'],
    'http_req_duration{endpoint:checkout}':       ['p(95)<200'],
    'http_req_duration{endpoint:search}':         ['p(95)<150'],

    // Global error rate < 1%
    'http_req_failed': ['rate<0.01'],

    // Check pass rate > 99%
    'checks': ['rate>0.99'],
  },
};

// ── Setup: create shared test data ────────────────────────────────────────────

export function setup() {
  // Product with large stock to survive many checkout iterations
  const productRes = http.post(
    `${BASE_URL}/api/products`,
    JSON.stringify({
      name:        'Producto K6 Load Test',
      description: 'Producto creado automáticamente por el test de carga k6.',
      price:       { amount: 4999, currency: 'EUR' },
      category:    'CYCLING',
      brand:       'Siroko',
      stock:       999999,
    }),
    { headers: AUTH_HEADERS },
  );

  if (productRes.status !== 201) {
    throw new Error(`Setup — no se pudo crear el producto (${productRes.status}): ${productRes.body}`);
  }

  const { productId } = productRes.json();

  // Persistent cart used by the cart read scenario
  const cartRes = http.post(`${BASE_URL}/api/carts`, '{}', { headers: JSON_HEADERS });
  const { cartId } = cartRes.json();

  http.post(
    `${BASE_URL}/api/carts/${cartId}/items`,
    JSON.stringify({ productId, quantity: 3 }),
    { headers: JSON_HEADERS },
  );

  return { productId, cartId };
}

// ── Catalog scenario: list + filter + detail ──────────────────────────────────

export function catalogScenario(data) {
  group('catalog', () => {
    const list = http.get(
      `${BASE_URL}/api/products`,
      { tags: { endpoint: 'list_products' } },
    );
    check(list, {
      'list products — 200':             (r) => r.status === 200,
      'list products — pagination ok':   (r) => r.json('pagination.total') >= 0,
      'list products — data is array':   (r) => Array.isArray(r.json('data')),
    });

    const filtered = http.get(
      `${BASE_URL}/api/products?category=CYCLING&pageSize=10`,
      { tags: { endpoint: 'list_products' } },
    );
    check(filtered, {
      'list by category — 200':          (r) => r.status === 200,
      'list by category — all CYCLING':  (r) => {
        const items = r.json('data') || [];
        return items.every((p) => p.category === 'CYCLING');
      },
    });

    const detail = http.get(
      `${BASE_URL}/api/products/${data.productId}`,
      { tags: { endpoint: 'get_product' } },
    );
    check(detail, {
      'get product — 200':       (r) => r.status === 200,
      'get product — has price': (r) => r.json('price.amount') > 0,
    });

    sleep(0.1);
  });
}

// ── Cart scenario: read cart ──────────────────────────────────────────────────

export function cartScenario(data) {
  group('cart', () => {
    const res = http.get(
      `${BASE_URL}/api/carts/${data.cartId}`,
      { tags: { endpoint: 'get_cart' } },
    );
    check(res, {
      'get cart — 200':       (r) => r.status === 200,
      'get cart — has items': (r) => Array.isArray(r.json('items')),
      'get cart — has total': (r) => r.json('total.amount') >= 0,
    });

    sleep(0.1);
  });
}

// ── Checkout scenario: create cart → add item → checkout ─────────────────────

export function checkoutScenario(data) {
  group('checkout', () => {
    const cartRes = http.post(`${BASE_URL}/api/carts`, '{}', { headers: JSON_HEADERS });
    if (!check(cartRes, { 'create cart — 201': (r) => r.status === 201 })) {
      return;
    }
    const { cartId } = cartRes.json();

    const addRes = http.post(
      `${BASE_URL}/api/carts/${cartId}/items`,
      JSON.stringify({ productId: data.productId, quantity: 1 }),
      { headers: JSON_HEADERS },
    );
    if (!check(addRes, { 'add item — 204': (r) => r.status === 204 })) {
      return;
    }

    const orderRes = http.post(
      `${BASE_URL}/api/orders`,
      JSON.stringify({
        cartId,
        shippingAddress: {
          street:     'Calle Mayor 1',
          city:       'Madrid',
          postalCode: '28001',
          country:    'ES',
        },
      }),
      {
        headers: JSON_HEADERS,
        tags:    { endpoint: 'checkout' },
      },
    );
    check(orderRes, {
      'checkout — 201':         (r) => r.status === 201,
      'checkout — has orderId': (r) => typeof r.json('orderId') === 'string',
    });

    sleep(0.5);
  });
}

// ── Search scenario ───────────────────────────────────────────────────────────

const QUERIES = [
  'maillot de ciclismo',
  'culote para mujer',
  'gafas deportivas',
  'ropa para correr en invierno',
  'zapatillas mtb',
  'ropa térmica ciclismo',
  'culote negro hombre',
  'algo cómodo para pedalear muchas horas',
  'equipación para gran fondo',
  'guantes de ciclismo para frío',
];

export function searchScenario() {
  if (SKIP_SEARCH) return;

  group('search', () => {
    const q   = QUERIES[Math.floor(Math.random() * QUERIES.length)];
    const res = http.get(
      `${BASE_URL}/api/search?q=${encodeURIComponent(q)}`,
      { tags: { endpoint: 'search' } },
    );
    check(res, {
      'search — 200':          (r) => r.status === 200,
      'search — results array':(r) => Array.isArray(r.json('results')),
    });

    sleep(0.2);
  });
}

// ── Teardown: remove test product ─────────────────────────────────────────────

export function teardown(data) {
  http.del(
    `${BASE_URL}/api/products/${data.productId}`,
    null,
    { headers: AUTH_HEADERS },
  );
}

// ── Custom summary ────────────────────────────────────────────────────────────

export function handleSummary(data) {
  const targets = {
    'http_req_duration{endpoint:list_products}': { label: 'GET /api/products',      target: 50  },
    'http_req_duration{endpoint:get_product}':   { label: 'GET /api/products/{id}', target: 50  },
    'http_req_duration{endpoint:get_cart}':       { label: 'GET /api/carts/{id}',   target: 30  },
    'http_req_duration{endpoint:checkout}':       { label: 'POST /api/orders',       target: 200 },
    'http_req_duration{endpoint:search}':         { label: 'GET /api/search',        target: 150 },
  };

  let report = '\n══════════════════════════════════════════════════\n';
  report    += '  Siroko API — Resultados de rendimiento (P95)\n';
  report    += '══════════════════════════════════════════════════\n';

  for (const [metric, { label, target }] of Object.entries(targets)) {
    const p95 = data.metrics[metric]?.values?.['p(95)'];
    if (p95 === undefined) {
      report += `  ⚠  ${label.padEnd(28)} (sin datos)\n`;
      continue;
    }
    const pass  = p95 < target;
    const icon  = pass ? '✓' : '✗';
    const arrow = pass ? '<' : '>';
    report += `  ${icon}  ${label.padEnd(28)} P95 = ${p95.toFixed(1).padStart(7)} ms  (objetivo ${arrow} ${target} ms)\n`;
  }

  const failRate  = data.metrics['http_req_failed']?.values?.rate ?? 0;
  const checkRate = data.metrics['checks']?.values?.rate ?? 0;
  report += '──────────────────────────────────────────────────\n';
  report += `  Error rate:  ${(failRate  * 100).toFixed(2)}%  (objetivo < 1%)\n`;
  report += `  Check rate:  ${(checkRate * 100).toFixed(2)}%  (objetivo > 99%)\n`;
  report += '══════════════════════════════════════════════════\n';

  return {
    stdout: report,
    'k6/summary.json': JSON.stringify(data, null, 2),
  };
}

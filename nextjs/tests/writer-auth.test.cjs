// Run: npm run test:writer-auth (no database connection or production secrets required).
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const ts = require('typescript');
const crypto = require('node:crypto');

const root = path.resolve(__dirname, '..');
function loadTs(relative, modules) {
  const source = fs.readFileSync(path.join(root, relative), 'utf8');
  const compiled = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 } }).outputText;
  const module = { exports: {} };
  const context = { module, exports: module.exports, require(name) {
    if (Object.prototype.hasOwnProperty.call(modules, name)) return modules[name];
    throw Error('Unexpected import: ' + name);
  }, process, Buffer, console, URL };
  vm.runInNewContext(compiled, context, { filename: relative });
  return module.exports;
}
const auth = loadTs('lib/auth.ts', {
  'server-only': {},
  'node:crypto': crypto,
  pg: { Pool: class { constructor() { throw Error('Database must be mocked'); } } },
});

test('password hashing and login verification accept correct password only', () => {
  const hashed = auth.hashPassword('Correct-Horse-2026!');
  assert.equal(auth.verifyPassword('Correct-Horse-2026!', hashed), true);
  assert.equal(auth.verifyPassword('incorrect', hashed), false);
  assert.equal(auth.verifyPassword('anything', 'invalid'), false);
});

test('session is signed, expires, and rejects tampering', () => {
  const previous = process.env.AUTH_SECRET;
  process.env.AUTH_SECRET = 'writer-test-secret-not-for-production-32-chars';
  try {
    const signed = auth.signSession('123');
    assert.equal(auth.verifySession(signed), '123');
    assert.equal(auth.verifySession(signed.replace(/^123/, '124')), null);
    const [id, , signature] = signed.split('.');
    assert.equal(auth.verifySession(id + '.1.' + signature), null);
  } finally {
    if (previous === undefined) delete process.env.AUTH_SECRET;
    else process.env.AUTH_SECRET = previous;
  }
});

function loginRoute(account) {
  const authMock = {
    database: () => ({ query: async (_sql, params) => {
      assert.equal(params[0], 'writer@example.invalid');
      return { rows: account ? [account] : [] };
    } }),
    verifyPassword: auth.verifyPassword,
    signSession: auth.signSession,
  };
  class Response {
    constructor(data, options) {
      this.data = data;
      this.status = options?.status || 200;
      this.cookies = { set: (name, value, options) => { this.cookie = { name, value, options }; } };
    }
    static json(data, options) { return new Response(data, options); }
  }
  return loadTs('app/api/auth/login/route.ts', {
    'next/server': { NextResponse: Response },
    '../../../../lib/auth': authMock,
  });
}
function request(password, origin = 'https://preview.example') {
  return {
    headers: { get: key => key === 'origin' ? origin : null },
    nextUrl: new URL('https://preview.example/api/auth/login'),
    json: async () => ({ email: 'WRITER@example.invalid', password }),
  };
}
test('writer account logs in and receives a signed HttpOnly session', async () => {
  const oldUrl = process.env.DATABASE_URL, oldSecret = process.env.AUTH_SECRET;
  process.env.DATABASE_URL = 'mock://isolated-test-only';
  process.env.AUTH_SECRET = 'writer-test-secret-not-for-production-32-chars';
  try {
    const account = { id: '88', username: 'writer-test', password_hash: auth.hashPassword('safe-password') };
    const { POST } = loginRoute(account);
    const good = await POST(request('safe-password'));
    assert.equal(good.status, 200);
    assert.equal(good.data.ok, true);
    assert.equal(good.cookie.name, 'saradeeeb_web_session');
    assert.equal(good.cookie.options.httpOnly, true);
    assert.equal(auth.verifySession(good.cookie.value), '88');
    const bad = await POST(request('wrong'));
    assert.equal(bad.status, 401);
    assert.equal(bad.cookie, undefined);
    const crossSite = await POST(request('safe-password', 'https://evil.example'));
    assert.equal(crossSite.status, 403);
  } finally {
    if (oldUrl === undefined) delete process.env.DATABASE_URL; else process.env.DATABASE_URL = oldUrl;
    if (oldSecret === undefined) delete process.env.AUTH_SECRET; else process.env.AUTH_SECRET = oldSecret;
  }
});

test('writer dashboard enforces session and role before loading drafts', () => {
  const source = fs.readFileSync(path.join(root, 'app/writer/page.tsx'), 'utf8');
  assert.match(source, /verifySession\(/);
  assert.match(source, /if\(!id\)redirect\("\/account"\)/);
  assert.match(source, /\["writer","trusted_writer","editor","admin"\]\.includes\(writer\.role\)/);
  assert.match(source, /WHERE writer_account_id=\$1/);
});

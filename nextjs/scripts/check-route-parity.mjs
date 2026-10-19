#!/usr/bin/env node
/**
 * Migration gate: inventory PHP routes and detect missing Next.js routes.
 * Run from repository root: node nextjs/scripts/check-route-parity.mjs
 * This intentionally fails until all PHP routes are ported.
 */
import fs from "node:fs";
import path from "node:path";
const root=path.resolve(import.meta.dirname,"../..");
const php=fs.readFileSync(path.join(root,"index.php"),"utf8");
const matches=[...php.matchAll(/\$router->(get|post|put|patch|delete)\(\s*['"]([^'"]+)['"]/g)];
const routes=matches.map(([,method,url])=>({method:method.toUpperCase(),url}));
const app=path.join(root,"nextjs","app");
function candidates(url){const route=url.replace(/\{[^/}]+\}/g,"[id]").replace(/^\//,"");return [path.join(app,route,"page.tsx"),path.join(app,route,"route.ts"),path.join(app,route,"page.ts"),path.join(app,route,"route.js")];}
const missing=routes.filter(r=>!candidates(r.url).some(fs.existsSync));
const summary={totalPhpRoutes:routes.length,missingRouteImplementations:missing.length,coveredByPathOnly:routes.length-missing.length,warning:"Path existence is NOT functional parity. Dynamic parameter names and HTTP methods require manual validation."};
console.log(JSON.stringify(summary,null,2));
if(missing.length){console.log("\nRoutes requiring migration:\n"+missing.map(r=>r.method+" "+r.url).join("\n"));process.exitCode=1;}

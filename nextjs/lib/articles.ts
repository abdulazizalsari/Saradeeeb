import "server-only";
import {Pool} from "pg";
export type Article={title:string;slug:string;excerpt:string|null;content:string;featured_image:string|null;published_at:Date|null;author_name:string};
const locales=new Set(["ar","en","es","fr","ru"]);
let pool:Pool|null=null;
function connection(){if(!process.env.DATABASE_URL)return null;return pool??(pool=new Pool({connectionString:process.env.DATABASE_URL,ssl:{rejectUnauthorized:false},max:4,connectionTimeoutMillis:5000}));}
export async function findPublishedArticle(locale:string,slug:string):Promise<Article|null>{
if(!locales.has(locale)||slug.length>1024)return null;
let normalized=slug;
try{normalized=decodeURIComponent(slug);}catch{}
const candidates=[...new Set([slug,normalized,encodeURIComponent(normalized),encodeURIComponent(normalized).toLowerCase()])];
const conn=connection();if(!conn)return null;
const result=await conn.query<Article>(`SELECT t.title,t.slug,t.excerpt,t.content,a.featured_image,a.published_at,u.username AS author_name
FROM article_translations t JOIN articles a ON a.id=t.article_id JOIN users u ON u.id=a.author_id
WHERE t.locale=$1 AND t.slug=ANY($2::text[]) AND t.status='published' AND a.status='published' LIMIT 1`,[locale,candidates]);
return result.rows[0]??null;
}
export async function listPublishedArticles(locale:string,limit=24):Promise<Article[]>{
if(!locales.has(locale))return [];
const conn=connection();if(!conn)return [];
const result=await conn.query<Article>(`SELECT t.title,t.slug,t.excerpt,t.content,a.featured_image,a.published_at,u.username AS author_name
FROM article_translations t JOIN articles a ON a.id=t.article_id JOIN users u ON u.id=a.author_id
WHERE t.locale=$1 AND t.status='published' AND a.status='published'
ORDER BY a.published_at DESC LIMIT $2`,[locale,Math.min(50,Math.max(1,limit))]);
return result.rows;
}

export async function listArticleSlugs(locale:string):Promise<{slug:string;published_at:Date|null}[]>{
 if(!locales.has(locale))return [];
 const conn=connection();if(!conn)return [];
 const r=await conn.query<{slug:string;published_at:Date|null}>(`SELECT t.slug,a.published_at FROM article_translations t JOIN articles a ON a.id=t.article_id WHERE t.locale=$1 AND t.status='published' AND a.status='published' ORDER BY a.published_at DESC`,[locale]);
 return r.rows;
}
export async function searchPublishedArticles(locale:string,term:string,limit=60):Promise<Article[]>{
 if(!locales.has(locale)||!term.trim())return [];
 const conn=connection();if(!conn)return [];
 const needle="%"+term.trim().slice(0,120).replace(/[\\%_]/g,m=>"\\"+m)+"%";
 const result=await conn.query<Article>(`SELECT t.title,t.slug,t.excerpt,t.content,a.featured_image,a.published_at,u.username AS author_name
 FROM article_translations t JOIN articles a ON a.id=t.article_id JOIN users u ON u.id=a.author_id
 WHERE t.locale=$1 AND t.status='published' AND a.status='published'
 AND (t.title ILIKE $2 ESCAPE '\\' OR t.excerpt ILIKE $2 ESCAPE '\\' OR t.content ILIKE $2 ESCAPE '\\')
 ORDER BY a.published_at DESC LIMIT $3`,[locale,needle,Math.min(200,Math.max(1,limit))]);
 return result.rows;
}

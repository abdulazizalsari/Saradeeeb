import "server-only";
import {Pool} from "pg";
export type Article={title:string;slug:string;excerpt:string|null;content:string;featured_image:string|null;published_at:Date|null;author_name:string;category?:string;categorySlug?:string};
export type Category={id:number;name:string;slug:string;count:number};
const locales=new Set(["ar","en","es","fr","ru"]);
let pool:Pool|null=null;
function connection(){if(!process.env.DATABASE_URL)return null;return pool??(pool=new Pool({connectionString:process.env.DATABASE_URL,ssl:{rejectUnauthorized:false},max:4,connectionTimeoutMillis:5000}));}
export async function listCategories():Promise<Category[]>{const conn=connection();if(!conn)return [];try{const result=await conn.query<Category>(`SELECT c.id,ct.name,ct.slug,0::int AS count FROM categories c JOIN category_translations ct ON ct.category_id=c.id WHERE c.active=1 AND ct.locale='ar' AND ct.status='published' ORDER BY c.sort_order ASC,c.id ASC`);return result.rows}catch(e){console.error('Category list failed',e);return []}}
export async function findPublishedArticle(locale:string,slug:string):Promise<Article|null>{
if(!locales.has(locale)||slug.length>220)return null;
const conn=connection();if(!conn)return null;
try{const result=await conn.query<Article>(`SELECT t.title,t.slug,t.excerpt,t.content,a.featured_image,a.published_at,u.username AS author_name FROM article_translations t JOIN articles a ON a.id=t.article_id JOIN users u ON u.id=a.author_id WHERE t.locale=$1 AND t.slug=$2 AND t.status='published' AND a.status='published' LIMIT 1`,[locale,slug]);return result.rows[0]??null}catch(e){console.error("Article lookup failed",e);return null}
}
export async function listPublishedArticles(locale:string,limit=24,options?:{page?:number;search?:string;category?:number}):Promise<Article[]>{
if(!locales.has(locale))return [];
const conn=connection();if(!conn)return [];
const page=Math.max(1,Math.min(100,Number(options?.page)||1));
const count=Math.min(100,Math.max(1,limit));
const search=(options?.search||"").trim().slice(0,100);
try{const result=await conn.query<Article>(`SELECT t.title,t.slug,t.excerpt,t.content,a.featured_image,a.published_at,u.username AS author_name FROM article_translations t JOIN articles a ON a.id=t.article_id JOIN users u ON u.id=a.author_id WHERE t.locale=$1 AND t.status='published' AND a.status='published' AND ($4::text = '' OR t.title ILIKE '%' || $4 || '%' OR t.excerpt ILIKE '%' || $4 || '%') ORDER BY a.published_at DESC NULLS LAST LIMIT $2 OFFSET $3`,[locale,count,(page-1)*count,search]);return result.rows}catch(e){console.error("Article list failed",e);return []}
}

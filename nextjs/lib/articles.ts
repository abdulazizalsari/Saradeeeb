import "server-only";
import {Pool} from "pg";
export type Article={title:string;slug:string;excerpt:string|null;content:string;featured_image:string|null;published_at:Date|null;author_name:string;category?:string;categorySlug?:string};
export type Category={id:number;name:string;slug:string;count:number};
const locales=new Set(["ar","en","es","fr","ru"]);
let pool:Pool|null=null;
function connection(){if(!process.env.DATABASE_URL)return null;return pool??(pool=new Pool({connectionString:process.env.DATABASE_URL,ssl:{rejectUnauthorized:false},max:4,connectionTimeoutMillis:5000}));}
export async function listCategories():Promise<Category[]>{const conn=connection();if(!conn)return [];try{const result=await conn.query<Category>(`SELECT c.id,ct.name,ct.slug,(SELECT COUNT(*)::int FROM article_categories ac JOIN articles a ON a.id=ac.article_id JOIN article_translations at ON at.article_id=a.id AND at.locale='ar' AND at.status='published' AND NULLIF(BTRIM(at.content),'') IS NOT NULL WHERE ac.category_id=c.id AND a.status='published') AS count FROM categories c JOIN category_translations ct ON ct.category_id=c.id WHERE c.active=1 AND ct.locale='ar' AND ct.status='published' ORDER BY c.sort_order ASC,c.id ASC`);return result.rows}catch(e){console.error('Category list failed',e);return []}}
export async function findPublishedArticle(locale:string,slug:string):Promise<Article|null>{
if(!locales.has(locale)||slug.length>220)return null;
const conn=connection();if(!conn)return null;
try{const result=await conn.query<Article>(`SELECT t.title,t.slug,t.excerpt,t.content,a.featured_image,a.published_at,u.username AS author_name,(SELECT ct.name FROM article_categories ac JOIN category_translations ct ON ct.category_id=ac.category_id AND ct.locale=t.locale AND ct.status='published' WHERE ac.article_id=a.id ORDER BY ac.category_id LIMIT 1) AS category,(SELECT ct.slug FROM article_categories ac JOIN category_translations ct ON ct.category_id=ac.category_id AND ct.locale=t.locale AND ct.status='published' WHERE ac.article_id=a.id ORDER BY ac.category_id LIMIT 1) AS "categorySlug" FROM article_translations t JOIN articles a ON a.id=t.article_id JOIN users u ON u.id=a.author_id WHERE t.locale=$1 AND t.slug=$2 AND NULLIF(BTRIM(t.content),'') IS NOT NULL AND t.status='published' AND a.status='published' LIMIT 1`,[locale,slug]);return result.rows[0]??null}catch(e){console.error("Article lookup failed",e);return null}
}
export async function listPublishedArticles(locale:string,limit=24,options?:{page?:number;search?:string;category?:number}):Promise<Article[]>{
if(!locales.has(locale))return [];
const conn=connection();if(!conn)return [];
const page=Math.max(1,Math.min(100,Number(options?.page)||1));
const count=Math.min(100,Math.max(1,limit));
const search=(options?.search||"").trim().slice(0,100);const category=options?.category||null;
try{const result=await conn.query<Article>(`SELECT t.title,t.slug,t.excerpt,t.content,a.featured_image,a.published_at,u.username AS author_name FROM article_translations t JOIN articles a ON a.id=t.article_id JOIN users u ON u.id=a.author_id WHERE t.locale=$1 AND t.status='published' AND NULLIF(BTRIM(t.content),'') IS NOT NULL AND a.status='published' AND ($4::text = '' OR t.title ILIKE '%' || $4 || '%' OR t.excerpt ILIKE '%' || $4 || '%') AND ($5::bigint IS NULL OR EXISTS (SELECT 1 FROM article_categories ac WHERE ac.article_id=a.id AND ac.category_id=$5)) ORDER BY a.published_at DESC NULLS LAST LIMIT $2 OFFSET $3`,[locale,count,(page-1)*count,search,category]);return result.rows}catch(e){console.error("Article list failed",e);return []}
}

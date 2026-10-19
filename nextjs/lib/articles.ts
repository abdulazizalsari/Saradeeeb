import "server-only";
import {Pool} from "pg";
export type Article={title:string;slug:string;excerpt:string|null;content:string;featured_image:string|null;published_at:Date|null;author_name:string};
const locales=new Set(["ar","en","es","fr","ru"]);
let pool:Pool|null=null;
function connection(){if(!process.env.DATABASE_URL)return null;return pool??(pool=new Pool({connectionString:process.env.DATABASE_URL,ssl:{rejectUnauthorized:false},max:4,connectionTimeoutMillis:5000}));}
export async function findPublishedArticle(locale:string,slug:string):Promise<Article|null>{
if(!locales.has(locale)||slug.length>220)return null;
const conn=connection();if(!conn)return null;
const result=await conn.query<Article>(`SELECT t.title,t.slug,t.excerpt,t.content,a.featured_image,a.published_at,u.username AS author_name
FROM article_translations t JOIN articles a ON a.id=t.article_id JOIN users u ON u.id=a.author_id
WHERE t.locale=$1 AND t.slug=$2 AND t.status='published' AND a.status='published' LIMIT 1`,[locale,slug]);
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

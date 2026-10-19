import "server-only";
import mysql from "mysql2/promise";
export type Article={title:string;slug:string;excerpt:string|null;content:string;featured_image:string|null;published_at:Date|null;author_name:string};
const locales=new Set(["ar","en","es","fr","ru"]);
function db(){const url=process.env.DATABASE_URL;if(!url) return null;return mysql.createPool({uri:url,connectionLimit:4,waitForConnections:true,enableKeepAlive:true});}
let pool:ReturnType<typeof mysql.createPool>|null=null;
function connection(){if(!process.env.DATABASE_URL)return null;return pool??(pool=db());}
export async function findPublishedArticle(locale:string,slug:string):Promise<Article|null>{
if(!locales.has(locale)||slug.length>220)return null;
const conn=connection();if(!conn)return null;
const [rows]=await conn.execute<mysql.RowDataPacket[]>(`SELECT t.title,t.slug,t.excerpt,t.content,a.featured_image,a.published_at,u.username AS author_name
FROM article_translations t JOIN articles a ON a.id=t.article_id JOIN users u ON u.id=a.author_id
WHERE t.locale=? AND t.slug=? AND t.status='published' AND a.status='published' LIMIT 1`,[locale,slug]);
return (rows[0] as Article|undefined)??null;
}
export async function listPublishedArticles(locale:string,limit=24):Promise<Article[]>{
if(!locales.has(locale))return [];
const conn=connection();if(!conn)return [];
const [rows]=await conn.execute<mysql.RowDataPacket[]>(`SELECT t.title,t.slug,t.excerpt,t.content,a.featured_image,a.published_at,u.username AS author_name
FROM article_translations t JOIN articles a ON a.id=t.article_id JOIN users u ON u.id=a.author_id
WHERE t.locale=? AND t.status='published' AND a.status='published'
ORDER BY a.published_at DESC LIMIT ?`,[locale,Math.min(50,Math.max(1,limit))]);
return rows as Article[];
}

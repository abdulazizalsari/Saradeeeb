import "server-only";
import {Pool} from "pg";
const locales=new Set(["ar","en","es","fr","ru"]);
let pool:Pool|undefined;
function db(){if(!process.env.DATABASE_URL)return null;return pool??(pool=new Pool({connectionString:process.env.DATABASE_URL,ssl:{rejectUnauthorized:false},max:3,connectionTimeoutMillis:5000}));}
export type PodcastEpisode={id:string;title:string;slug:string;excerpt:string|null;cover_image:string|null;audio_url:string|null;duration:string|null;published_at:Date|null};
export async function listPodcastEpisodes(locale:string):Promise<PodcastEpisode[]>{
 if(!locales.has(locale))return [];
 const conn=db();if(!conn)return [];
 const result=await conn.query<PodcastEpisode>(`SELECT e.id,COALESCE(t.title,e.title) title,COALESCE(t.slug,e.slug) slug,COALESCE(t.excerpt,e.excerpt) excerpt,e.cover_image,e.audio_url,e.duration,e.published_at
 FROM podcast_episodes e LEFT JOIN podcast_episode_translations t ON t.episode_id=e.id AND t.locale=$1 AND t.status='published'
 WHERE e.status='published' AND ($1='ar' OR t.id IS NOT NULL)
 ORDER BY e.published_at DESC NULLS LAST,e.id DESC LIMIT 100`,[locale]);
 return result.rows;
}
export type Khatera={id:string;body:string;slug:string|null;image_path:string|null;published_at:Date|null};
export async function listKhawatir(locale:string):Promise<Khatera[]>{
 if(!locales.has(locale))return [];
 const conn=db();if(!conn)return [];
 const result=await conn.query<Khatera>(`SELECT k.id,COALESCE(t.body,k.body) body,COALESCE(t.slug,k.slug) slug,k.image_path,k.published_at
 FROM khawatir k LEFT JOIN khawatir_translations t ON t.khawatir_id=k.id AND t.locale=$1 AND t.status='published'
 WHERE k.status='published' AND ($1='ar' OR t.id IS NOT NULL)
 ORDER BY k.published_at DESC NULLS LAST,k.id DESC LIMIT 100`,[locale]);
 return result.rows;
}
export type CommunityTopic={id:string;title:string;slug:string;excerpt:string|null;created_at:Date;replies_count:number};
export async function listCommunityTopics(locale:string):Promise<CommunityTopic[]>{
 if(!locales.has(locale))return [];
 const conn=db();if(!conn)return [];
 const result=await conn.query<CommunityTopic>(`SELECT id,title,slug,excerpt,created_at,replies_count FROM community_topics
 WHERE locale=$1 AND status='published' ORDER BY pinned DESC,last_activity_at DESC NULLS LAST,created_at DESC LIMIT 100`,[locale]);
 return result.rows;
}

import CommunityComposer from "../CommunityComposer";
import Link from "next/link";
import {notFound} from "next/navigation";
import type {Metadata} from "next";
import {findCommunityTopic,listCommunityReplies} from "../../../../lib/public-sections";
type Props={params:Promise<{locale:string;slug:string}>};
export const dynamic="force-dynamic";
export async function generateMetadata({params}:Props):Promise<Metadata>{const {locale,slug}=await params;const topic=await findCommunityTopic(locale,slug);return {title:topic?.title??"موضوع غير موجود | سراديب",description:topic?.excerpt??undefined,robots:topic?undefined:{index:false}};}
export default async function TopicPage({params}:Props){
 const {locale,slug}=await params;const topic=await findCommunityTopic(locale,slug);if(!topic)notFound();
 const replies=await listCommunityReplies(topic.id);const arabic=locale==="ar";
 return <main id="content" className="container article-migration" lang={locale} dir={arabic?"rtl":"ltr"}>
 <Link href={`/${locale}/community`}>{arabic?"← العودة إلى المنتدى":"← Back to community"}</Link>
 <article><h1>{topic.title}</h1><p>{topic.excerpt}</p><div className="article-body" style={{whiteSpace:"pre-wrap"}}>{topic.content}</div></article>
 <section aria-labelledby="replies-heading"><h2 id="replies-heading">{arabic?"الردود":"Replies"} ({replies.length})</h2>{replies.map(reply=><article className="home-section" key={reply.id}><p style={{whiteSpace:"pre-wrap"}}>{reply.content}</p><small>{new Date(reply.created_at).toLocaleDateString(locale)}</small></article>)}{replies.length===0&&<p>{arabic?"لا توجد ردود منشورة بعد.":"No published replies yet."}</p>}</section>
 <CommunityComposer locale={locale} topicId={topic.id}/>
 </main>;
}

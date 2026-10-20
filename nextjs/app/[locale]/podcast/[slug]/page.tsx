import Link from "next/link";
import {notFound} from "next/navigation";
import type {Metadata} from "next";
import {findPodcastEpisode} from "../../../../lib/public-sections";
type Props={params:Promise<{locale:string;slug:string}>};
export const dynamic="force-dynamic";
export async function generateMetadata({params}:Props):Promise<Metadata>{const {locale,slug}=await params;const episode=await findPodcastEpisode(locale,slug);return {title:episode?.title??"حلقة غير موجودة | سراديب",description:episode?.excerpt??undefined,alternates:episode?{canonical:`/${locale}/podcast/${encodeURIComponent(episode.slug)}`}:undefined,robots:episode?undefined:{index:false}};}
export default async function PodcastEpisodePage({params}:Props){const {locale,slug}=await params;const e=await findPodcastEpisode(locale,slug);if(!e)notFound();const arabic=locale==="ar";return <main id="content" className="container article-migration" lang={locale} dir={arabic?"rtl":"ltr"}><Link href={`/${locale}/podcast`}>{arabic?"← العودة إلى البودكاست":"← Back to podcast"}</Link><article>{e.cover_image&&<img src={e.cover_image} alt="" loading="lazy" style={{maxWidth:"100%",height:"auto"}}/>}<h1>{e.title}</h1>{e.excerpt&&<p>{e.excerpt}</p>}{e.duration&&<p>{e.duration}</p>}{e.audio_url&&/^https?:\/\//i.test(e.audio_url)&&<audio controls preload="none" src={e.audio_url} style={{width:"100%"}}/>}</article></main>;}

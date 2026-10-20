import {permanentRedirect} from "next/navigation";

type Props={params:Promise<{slug:string}>};
/** Preserve the original unprefixed article URLs while keeping one canonical path. */
export default async function LegacyArticleRedirect({params}:Props){
 const {slug}=await params;
 permanentRedirect("/ar/article/"+encodeURIComponent(slug));
}

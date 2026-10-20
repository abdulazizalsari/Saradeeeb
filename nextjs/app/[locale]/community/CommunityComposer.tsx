"use client";
import {useState} from "react";
import {useRouter} from "next/navigation";
export default function CommunityComposer({locale,topicId}:{locale:string;topicId?:string}){
 const router=useRouter();const [title,setTitle]=useState("");const [content,setContent]=useState("");const [busy,setBusy]=useState(false);const [error,setError]=useState("");
 const arabic=locale==="ar";
 async function submit(e:React.FormEvent<HTMLFormElement>){
  e.preventDefault();if(busy)return;setBusy(true);setError("");
  try{
   const url=topicId?`/api/community/topics/${topicId}/replies`:"/api/community/topics";
   const response=await fetch(url,{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(topicId?{content}:{title,content,locale})});
   const result=await response.json();
   if(!response.ok){setError(response.status===401?(arabic?"سجّل الدخول أولًا للمشاركة.":"Sign in to participate."):(result.error??"Unable to publish"));return;}
   setContent("");setTitle("");if(result.url)router.push(result.url);router.refresh();
  }catch{setError(arabic?"تعذّر الاتصال. حاول مجددًا.":"Connection failed. Try again.");}finally{setBusy(false);}
 }
 return <form onSubmit={submit} style={{display:"grid",gap:12,maxWidth:740,marginBlock:28}}>
 <h2>{topicId?(arabic?"أضف ردًا":"Post a reply"):(arabic?"ابدأ موضوعًا جديدًا":"Start a topic")}</h2>
 {!topicId&&<label>{arabic?"العنوان":"Title"}<input required minLength={5} maxLength={190} value={title} onChange={e=>setTitle(e.target.value)} style={{display:"block",width:"100%",padding:12}}/></label>}
 <label>{arabic?"المحتوى":"Content"}<textarea required minLength={topicId?2:10} maxLength={topicId?10000:20000} rows={5} value={content} onChange={e=>setContent(e.target.value)} style={{display:"block",width:"100%",padding:12}}/></label>
 {error&&<p role="alert">{error}</p>}
 <button disabled={busy} type="submit" style={{padding:12,justifySelf:"start"}}>{busy?(arabic?"جارٍ النشر...":"Publishing..."):(arabic?"نشر":"Publish")}</button>
 </form>;
}

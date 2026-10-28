"use client";
import {useState} from "react";
type Draft={id:string;title:string;body:string;status:string;updated_at:string};
export default function WriterEditor({initial}:{initial:Draft[]}){
 const [drafts,setDrafts]=useState(initial),[selected,setSelected]=useState<string>(""),[title,setTitle]=useState(""),[body,setBody]=useState(""),[message,setMessage]=useState(""),[busy,setBusy]=useState(false);
 const editable=drafts.find(d=>d.id===selected);
 function select(d:Draft){setSelected(d.id);setTitle(d.title);setBody(d.body);setMessage("")}
 async function save(submit:boolean){
  setBusy(true);setMessage("");
  try{
   const path=selected?"/api/writer/drafts/"+selected:"/api/writer/drafts";
   if(submit&&!selected){setMessage("احفظ المسودة أولًا ثم أرسلها للمراجعة");return}
   const r=await fetch(path,{method:selected?"PATCH":"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({title,body,submit})});
   const data=await r.json();if(!r.ok){setMessage(data.error??"تعذر الحفظ");return}
   const draft=data.draft as Draft;setDrafts(old=>[draft,...old.filter(d=>d.id!==draft.id)]);setSelected(draft.id);setMessage(submit?"أُرسلت للمراجعة":"حُفظت المسودة");
  }catch{setMessage("تعذر الاتصال بالخادم")}finally{setBusy(false)}
 }
 return <section><h2>أعمالي</h2>
  <ul>{drafts.map(d=><li key={d.id}><button type="button" onClick={()=>select(d)}>{d.title} — {d.status}</button></li>)}</ul>
  <button type="button" onClick={()=>{setSelected("");setTitle("");setBody("");setMessage("")}}>مقال جديد</button>
  <h2>{selected?"تحرير المقال":"مقال جديد"}</h2>
  {editable&&!["draft","changes_requested"].includes(editable.status)?<p>هذا المقال قيد المراجعة أو مغلق للتعديل.</p>:<>
   <label>العنوان <input value={title} maxLength={240} onChange={e=>setTitle(e.target.value)}/></label>
   <label>النص <textarea value={body} maxLength={100000} rows={12} onChange={e=>setBody(e.target.value)}/></label>
   <div><button disabled={busy||title.trim().length<3} onClick={()=>save(false)}>حفظ المسودة</button> <button disabled={busy||!selected||title.trim().length<3||!body.trim()} onClick={()=>save(true)}>إرسال للمراجعة</button></div>
  </>}
  <p role="status">{message}</p>
 </section>;
}

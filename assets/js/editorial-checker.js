(()=>{
  'use strict';
  const form=document.querySelector('[data-editor-form]');
  const panel=document.querySelector('[data-editorial-checker]');
  if(!form||!panel)return;

  const openButtons=[...document.querySelectorAll('[data-editorial-check-open]')];
  const closeButton=panel.querySelector('[data-editorial-check-close]');
  const recheckButton=panel.querySelector('[data-editorial-recheck]');
  const toNoteButton=panel.querySelector('[data-editorial-to-note]');
  const loading=panel.querySelector('[data-editorial-check-loading]');
  const results=panel.querySelector('[data-editorial-check-results]');
  const metrics=panel.querySelector('[data-editorial-check-metrics]');
  const readiness=panel.querySelector('[data-editorial-readiness]');
  const scope=panel.querySelector('[data-editorial-check-scope]');
  const severityLabels={technical:'خطأ تقني',review:'يحتاج مراجعة',suggestion:'اقتراح تحسين',passed:'اجتاز الفحص'};
  let lastReport=null;
  let lastFingerprint='';

  const activePanel=()=>form.querySelector('.studio-lang-panel.active')||form.querySelector('.studio-lang-panel');
  const field=(name,locale)=>form.elements.namedItem(`${name}[${locale}]`);
  const selectedLocale=()=>activePanel()?.dataset.locale||'ar';
  const currentContent=locale=>form.querySelector(`[data-rich-editor="${locale}"]`)?.innerHTML||field('content',locale)?.value||'';
  const fingerprint=()=>{
    const locale=selectedLocale(),file=form.querySelector('input[name="featured_image"]')?.files?.[0];
    return JSON.stringify([locale,...['title','slug','excerpt','seo_title','seo_description','image_alt'].map(name=>field(name,locale)?.value||''),currentContent(locale),form.elements.namedItem('category_id')?.value||'',form.querySelector('[data-media-picker-value]')?.value||form.dataset.currentFeatured||'',file?.name||'',file?.size||0]);
  };

  function collect(){
    const locale=selectedLocale(), data=new FormData(), fileInput=form.querySelector('input[name="featured_image"]'), file=fileInput?.files?.[0];
    data.append('_token',form.elements.namedItem('_token')?.value||'');
    data.append('article_id',form.elements.namedItem('article_id')?.value||'0');
    data.append('locale',locale);
    for(const name of ['title','slug','excerpt','seo_title','seo_description','image_alt']) data.append(name,field(name,locale)?.value||'');
    data.append('content',currentContent(locale));
    data.append('category_id',form.elements.namedItem('category_id')?.value||'');
    data.append('featured_image',form.querySelector('[data-media-picker-value]')?.value||form.dataset.currentFeatured||'');
    if(file){data.append('featured_upload_name',file.name);data.append('featured_upload_size',String(file.size));data.append('featured_upload_type',file.type)}
    return data;
  }

  function el(tag,className,text){const node=document.createElement(tag);if(className)node.className=className;if(text!==undefined)node.textContent=text;return node}

  function jump(target){
    const locale=selectedLocale();
    let node=null;
    if(target==='content')node=form.querySelector(`[data-rich-editor="${locale}"]`);
    else if(target==='featured_image')node=form.querySelector('input[name="featured_image"]');
    else if(target==='category_id')node=form.elements.namedItem('category_id');
    else node=field(target,locale);
    if(!node)return;
    const details=node.closest('details');if(details)details.open=true;
    node.scrollIntoView({behavior:'smooth',block:'center'});
    node.classList.add('editorial-target-highlight');
    if(typeof node.focus==='function')node.focus({preventScroll:true});
    setTimeout(()=>node.classList.remove('editorial-target-highlight'),2200);
  }

  function render(report){
    lastReport=report;lastFingerprint=fingerprint();results.replaceChildren();metrics.replaceChildren();
    const metricItems=[['عدد الكلمات',report.metrics.words],['زمن القراءة',`${report.metrics.minutes} د`],['الفقرات',report.metrics.paragraphs]];
    for(const [label,value] of metricItems){const item=el('span');item.append(el('b','',String(value)),el('small','',label));metrics.append(item)}
    metrics.hidden=false;scope.textContent=report.scope||'';
    for(const [key,label] of Object.entries(report.categories||{})){
      const group=el('section','editorial-check-group');group.dataset.category=key;
      group.append(el('h4','',label));
      for(const finding of report.findings.filter(item=>item.category===key)){
        const item=el('article',`editorial-finding severity-${finding.severity}`);
        const head=el('div','editorial-finding-head');
        if(finding.severity!=='passed'){
          const check=el('input');check.type='checkbox';check.className='editorial-finding-select';check.setAttribute('aria-label',`اختيار: ${finding.title}`);check.addEventListener('change',syncSelection);head.append(check);
        }
        head.append(el('strong','',finding.title),el('span','editorial-severity',severityLabels[finding.severity]||finding.severity));
        item.append(head,el('p','',finding.explanation));
        const meta=el('div','editorial-finding-meta');meta.append(el('span','',`الموضع: ${finding.location}`),el('span','',`الإجراء المقترح: ${finding.suggestion}`));item.append(meta);
        if(finding.target){const jumpButton=el('button','editorial-jump','الانتقال إلى الموضع');jumpButton.type='button';jumpButton.addEventListener('click',()=>jump(finding.target));item.append(jumpButton)}
        item._finding=finding;group.append(item);
      }
      results.append(group);
    }
    readiness.className='editorial-readiness '+(report.readiness.includes('تقنية')?'is-technical':report.readiness.includes('تحسينات')?'is-review':'is-ready');
    readiness.textContent=report.readiness;readiness.hidden=false;syncSelection();
  }

  function syncSelection(){if(toNoteButton)toNoteButton.disabled=!panel.querySelector('.editorial-finding-select:checked')}

  async function run(){
    panel.hidden=false;loading.hidden=false;results.replaceChildren();metrics.hidden=true;readiness.hidden=true;if(toNoteButton)toNoteButton.disabled=true;
    panel.scrollIntoView({behavior:'smooth',block:'start'});
    try{
      const response=await fetch('/admin/articles/editorial-check',{method:'POST',body:collect(),headers:{Accept:'application/json'}});
      const report=await response.json();if(!response.ok||!report.ok)throw new Error(report.message||'تعذر إجراء الفحص.');render(report);
    }catch(error){results.replaceChildren(el('div','notice error',error.message||'تعذر إجراء الفحص التحريري.'))}
    finally{loading.hidden=true}
  }

  openButtons.forEach(button=>button.addEventListener('click',run));
  recheckButton?.addEventListener('click',run);
  closeButton?.addEventListener('click',()=>{panel.hidden=true});
  const markStale=()=>{if(!lastReport||fingerprint()===lastFingerprint)return;lastReport=null;if(toNoteButton)toNoteButton.disabled=true;readiness.hidden=false;readiness.className='editorial-readiness is-review';readiness.textContent='تغيّرت المسودة — أعد الفحص للحصول على نتائج محدثة.'};
  form.addEventListener('input',markStale);
  form.addEventListener('change',markStale);
  toNoteButton?.addEventListener('click',()=>{
    if(!lastReport)return;
    const selected=[...panel.querySelectorAll('.editorial-finding-select:checked')].map(box=>box.closest('.editorial-finding')?._finding).filter(Boolean);
    if(!selected.length)return;
    const note=form.elements.namedItem('review_note');if(!note)return;
    const lines=['ملاحظات المراجعة التحريرية:','',...selected.map(item=>`- ${item.location}: ${item.suggestion}`)];
    note.value=(note.value.trim()?note.value.trim()+'\n\n':'')+lines.join('\n');
    note.scrollIntoView({behavior:'smooth',block:'center'});note.focus();note.classList.add('editorial-target-highlight');setTimeout(()=>note.classList.remove('editorial-target-highlight'),2200);
  });
})();

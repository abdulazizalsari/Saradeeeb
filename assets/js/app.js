(()=>{
const d=document;
const pageLocale=d.documentElement.lang||'en';
const copyLabels={ar:'تم النسخ',en:'Copied',es:'Copiado',fr:'Copié',ru:'Скопировано'};
const header=d.querySelector('[data-site-header]');
if(header){
  let lastY=Math.max(0,scrollY),ticking=false;
  const syncHeader=()=>{
    const y=Math.max(0,scrollY),down=y>lastY+3,up=y<lastY-3;
    header.classList.toggle('scrolled',y>14);
    if(header.dataset.hideOnScroll==='1'){
      if(down&&y>120)header.classList.add('scroll-hidden');
      if(up||y<60)header.classList.remove('scroll-hidden');
    }else header.classList.remove('scroll-hidden');
    lastY=y;ticking=false;
  };
  syncHeader();
  addEventListener('scroll',()=>{if(!ticking){ticking=true;requestAnimationFrame(syncHeader)}},{passive:true});
}

/* Soft light is the default public appearance. A saved choice always wins. */
const themeBtn=d.querySelector('[data-theme-toggle]');
const applyTheme=dark=>{
  d.body.classList.toggle('dark',!!dark);
  d.documentElement.style.colorScheme=dark?'dark':'light';
  if(themeBtn){themeBtn.setAttribute('aria-pressed',dark?'true':'false');themeBtn.title=dark?({ar:'الوضع الفاتح',en:'Light mode',es:'Modo claro',fr:'Mode clair',ru:'Светлая тема'}[pageLocale]||'Light mode'):({ar:'الوضع المظلم',en:'Dark mode',es:'Modo oscuro',fr:'Mode sombre',ru:'Тёмная тема'}[pageLocale]||'Dark mode')}
};
const saved=localStorage.getItem('saradeeeb-theme');
applyTheme(saved==='dark');
themeBtn?.addEventListener('click',()=>{const dark=!d.body.classList.contains('dark');applyTheme(dark);localStorage.setItem('saradeeeb-theme',dark?'dark':'light')});

/* Public mobile drawer — reliable on every page and language. */
const publicMenuBtn=d.querySelector('[data-menu]'),publicMobileMenu=d.querySelector('[data-mobile-menu]'),publicMenuBackdrop=d.querySelector('[data-mobile-menu-backdrop]'),publicMenuClose=d.querySelector('[data-mobile-menu-close]');
let publicMenuCloseTimer,publicMenuOpener=null;
if(publicMobileMenu) publicMobileMenu.hidden=true;
const setPublicMenu=open=>{
  if(!publicMobileMenu)return;
  clearTimeout(publicMenuCloseTimer);
  if(open){ publicMenuOpener=d.activeElement; publicMobileMenu.hidden=false; }
  publicMobileMenu.classList.toggle('open',!!open);
  publicMobileMenu.setAttribute('aria-hidden',open?'false':'true');
  publicMenuBtn?.setAttribute('aria-expanded',open?'true':'false');
  if(publicMenuBackdrop){publicMenuBackdrop.hidden=!open;publicMenuBackdrop.classList.toggle('open',!!open)}
  d.body.classList.toggle('public-menu-open',!!open);
  if(!open){ publicMenuCloseTimer=setTimeout(()=>{if(!publicMobileMenu.classList.contains('open')){publicMobileMenu.hidden=true;publicMenuOpener?.focus?.();publicMenuOpener=null}},260); }
  if(open) requestAnimationFrame(()=>publicMenuClose?.focus());
};
publicMenuBtn?.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();setPublicMenu(!publicMobileMenu?.classList.contains('open'))});
publicMenuClose?.addEventListener('click',()=>setPublicMenu(false));
publicMenuBackdrop?.addEventListener('click',()=>setPublicMenu(false));
publicMobileMenu?.querySelectorAll('a').forEach(a=>a.addEventListener('click',()=>setPublicMenu(false)));
d.querySelectorAll('[data-mobile-accordion-toggle]').forEach(btn=>btn.addEventListener('click',()=>{const wrap=btn.closest('[data-mobile-accordion]');const open=!wrap?.classList.contains('open');wrap?.classList.toggle('open',open);btn.setAttribute('aria-expanded',open?'true':'false')}));
publicMobileMenu?.addEventListener('keydown',e=>{if(e.key!=='Tab')return;const focusables=[...publicMobileMenu.querySelectorAll('a,button,input,select,textarea,[tabindex]:not([tabindex="-1"])')].filter(x=>!x.disabled&&x.offsetParent!==null);if(!focusables.length)return;const first=focusables[0],last=focusables[focusables.length-1];if(e.shiftKey&&d.activeElement===first){e.preventDefault();last.focus()}else if(!e.shiftKey&&d.activeElement===last){e.preventDefault();first.focus()}});
addEventListener('resize',()=>{if(innerWidth>1040)setPublicMenu(false)},{passive:true});
d.querySelector('[data-back-to-top]')?.addEventListener('click',()=>scrollTo({top:0,behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'auto':'smooth'}));

/* Global desktop categories mega menu. */
const megaWrappers=[...d.querySelectorAll('[data-header-mega-wrap]')];
const closeHeaderMegas=except=>megaWrappers.forEach(w=>{if(w!==except){w.classList.remove('open');w.querySelector('[data-header-mega-toggle]')?.setAttribute('aria-expanded','false')}});
megaWrappers.forEach(w=>{const btn=w.querySelector('[data-header-mega-toggle]'),panel=w.querySelector('[data-header-mega-panel]');btn?.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();const open=!w.classList.contains('open');closeHeaderMegas(w);w.classList.toggle('open',open);btn.setAttribute('aria-expanded',open?'true':'false')});panel?.addEventListener('click',e=>e.stopPropagation());w.addEventListener('mouseenter',()=>{if(matchMedia('(hover:hover)').matches&&innerWidth>1040){closeHeaderMegas(w);w.classList.add('open');btn?.setAttribute('aria-expanded','true')}});w.addEventListener('mouseleave',()=>{if(matchMedia('(hover:hover)').matches&&innerWidth>1040){w.classList.remove('open');btn?.setAttribute('aria-expanded','false')}})});
d.addEventListener('click',()=>closeHeaderMegas());

/* Floating language selector */
const langWrap=d.querySelector('[data-language-switcher]'),langBtn=d.querySelector('[data-language-toggle]'),langMenu=d.querySelector('[data-language-menu]');
const closeLang=()=>{langWrap?.classList.remove('open');langBtn?.setAttribute('aria-expanded','false')};
langBtn?.addEventListener('click',e=>{e.stopPropagation();const open=!langWrap?.classList.contains('open');closeLang();if(open){langWrap?.classList.add('open');langBtn?.setAttribute('aria-expanded','true')}});
langMenu?.addEventListener('click',e=>e.stopPropagation());d.addEventListener('click',closeLang);
/* Header language selector */
const headerLang=d.querySelector('[data-header-language]'),headerLangBtn=d.querySelector('[data-header-language-toggle]');
const closeHeaderLang=()=>{headerLang?.classList.remove('open');headerLangBtn?.setAttribute('aria-expanded','false')};
headerLangBtn?.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();const open=!headerLang?.classList.contains('open');closeHeaderLang();if(open){headerLang?.classList.add('open');headerLangBtn?.setAttribute('aria-expanded','true')}});
headerLang?.querySelector('[data-header-language-menu]')?.addEventListener('click',e=>e.stopPropagation());d.addEventListener('click',closeHeaderLang);
const footerLang=d.querySelector('[data-footer-language]'),footerLangBtn=d.querySelector('[data-footer-language-toggle]'),footerLangMenu=d.querySelector('[data-footer-language-menu]');
const closeFooterLang=()=>{footerLang?.classList.remove('open');footerLangBtn?.setAttribute('aria-expanded','false')};
footerLangBtn?.addEventListener('click',e=>{e.preventDefault();e.stopPropagation();const open=!footerLang?.classList.contains('open');closeFooterLang();if(open){footerLang?.classList.add('open');footerLangBtn?.setAttribute('aria-expanded','true')}});
footerLangMenu?.addEventListener('click',e=>e.stopPropagation());footerLangMenu?.querySelectorAll('a').forEach(a=>a.addEventListener('click',closeFooterLang));d.addEventListener('click',closeFooterLang);

/* Dashboard sidebar */
const sidebar=d.querySelector('.sidebar'),dashMenu=d.querySelector('[data-dashboard-menu]'),backdrop=d.querySelector('[data-dashboard-backdrop]');
const setSidebar=open=>{sidebar?.classList.toggle('open',open);backdrop?.classList.toggle('open',open);d.body.classList.toggle('dashboard-menu-open',open)};
dashMenu?.addEventListener('click',()=>setSidebar(!sidebar?.classList.contains('open')));backdrop?.addEventListener('click',()=>setSidebar(false));
d.querySelectorAll('.dashboard-nav-link').forEach(a=>a.addEventListener('click',()=>{if(innerWidth<=1000)setSidebar(false)}));
addEventListener('keydown',e=>{if(e.key==='Escape'){setSidebar(false);closeLang();closeHeaderLang();closeFooterLang();setPublicMenu(false);closeHeaderMegas()}});

/* Language tabs */
const tabs=d.querySelectorAll('.lang-tab');tabs.forEach(b=>b.addEventListener('click',()=>{tabs.forEach(x=>x.classList.remove('active'));d.querySelectorAll('.lang-panel').forEach(x=>x.classList.remove('active'));b.classList.add('active');d.querySelector(`[data-panel="${b.dataset.tab}"]`)?.classList.add('active')}));

/* Rich editor */
d.querySelectorAll('.rich-toolbar button').forEach(b=>b.addEventListener('click',()=>{const panel=b.closest('.lang-panel');const ed=panel?.querySelector('.rich-editor');if(!ed)return;ed.focus();if(b.dataset.cmd)document.execCommand(b.dataset.cmd,false,null);if(b.dataset.block)document.execCommand('formatBlock',false,b.dataset.block)}));

/* Page visual/code modes */
const syncModePanel=panel=>{if(!panel)return;const checked=panel.querySelector('[data-page-mode]:checked');const mode=checked?.value||'visual';panel.querySelectorAll('[data-mode-panel]').forEach(x=>x.hidden=x.dataset.modePanel!==mode)};
d.querySelectorAll('.lang-panel').forEach(panel=>{syncModePanel(panel);panel.querySelectorAll('[data-page-mode]').forEach(r=>r.addEventListener('change',()=>syncModePanel(panel)))});

const form=d.querySelector('[data-editor-form]');
form?.addEventListener('submit',()=>d.querySelectorAll('[data-rich-editor]').forEach(ed=>{const panel=ed.closest('.lang-panel');const mode=panel?.querySelector('[data-page-mode]:checked')?.value||'visual';const target=d.querySelector(`[data-rich-target="${ed.dataset.richEditor}"]`);if(target&&mode==='visual')target.value=ed.innerHTML}));

/* Article TOC and progress */
const content=d.querySelector('[data-article-content]'),toc=d.querySelector('[data-toc]'),mobileToc=d.querySelector('[data-mobile-toc-list]');
if(content){
  const headings=[...content.querySelectorAll('h2,h3')];
  content.querySelectorAll('img').forEach((img,i)=>{img.decoding='async';if(i>0)img.loading='lazy'});
  const makeLink=(h)=>{const a=d.createElement('a');a.href='#'+h.id;a.textContent=(h.textContent||'').trim();a.dataset.tocTarget=h.id;if(h.tagName==='H3')a.classList.add('toc-subitem');a.addEventListener('click',()=>{setTimeout(()=>history.replaceState(null,'','#'+h.id),0)});return a};
  headings.forEach((h,i)=>{if(!h.id)h.id='section-'+(i+1);if(toc)toc.appendChild(makeLink(h));if(mobileToc)mobileToc.appendChild(makeLink(h))});
  if(!headings.length){d.querySelector('.article-toc-card-v45')?.setAttribute('hidden','');d.querySelector('[data-mobile-toc]')?.setAttribute('hidden','')}
  if(headings.length&&'IntersectionObserver' in window){const links=[...(toc?.querySelectorAll('a')||[]),...(mobileToc?.querySelectorAll('a')||[])];const observer=new IntersectionObserver(entries=>{entries.forEach(entry=>{if(entry.isIntersecting){links.forEach(a=>a.classList.toggle('is-active',a.dataset.tocTarget===entry.target.id))}})},{rootMargin:'-18% 0px -65% 0px',threshold:0});headings.forEach(h=>observer.observe(h))}
}
const progress=d.querySelector('[data-reading-progress]');if(progress&&content){const u=()=>{const r=content.getBoundingClientRect();const total=Math.max(content.offsetHeight-innerHeight,1);const done=Math.min(Math.max(-r.top,0),total);progress.style.width=(done/total*100)+'%'};u();addEventListener('scroll',u,{passive:true})}
const copyText=async(value,status)=>{try{await navigator.clipboard.writeText(value);if(status){status.textContent=copyLabels[pageLocale]||copyLabels.en;setTimeout(()=>{status.textContent=''},1800)}}catch(_){const area=d.createElement('textarea');area.value=value;area.style.position='fixed';area.style.opacity='0';d.body.appendChild(area);area.select();document.execCommand('copy');area.remove();if(status){status.textContent=copyLabels[pageLocale]||copyLabels.en;setTimeout(()=>{status.textContent=''},1800)}}};
d.querySelector('[data-hero-copy]')?.addEventListener('click',e=>copyText(e.currentTarget.dataset.copyValue||location.href,d.querySelector('[data-hero-share-status]')));
d.querySelector('[data-hero-native-share]')?.addEventListener('click',()=>{const data={title:d.title,text:d.querySelector('.article-lead-v45')?.textContent||d.title,url:location.href};if(navigator.share)navigator.share(data).catch(()=>{});else copyText(location.href,d.querySelector('[data-hero-share-status]'))});
const articleContent=d.querySelector('[data-article-content]'),saveArticle=d.querySelector('[data-article-save]'),fontButton=d.querySelector('[data-font-size]'),fontMenu=d.querySelector('[data-font-menu]');
if(articleContent){const key='saradeeeb-saved-'+location.pathname;const setSaved=state=>{saveArticle?.classList.toggle('is-active',state);saveArticle?.setAttribute('aria-pressed',state?'true':'false');if(saveArticle)saveArticle.querySelector('span').textContent=pageLocale==='ar'?(state?'تم الحفظ':'حفظ المقال'):(state?'Saved':'Save article')};setSaved(localStorage.getItem(key)==='1');saveArticle?.addEventListener('click',()=>{const next=localStorage.getItem(key)!=='1';localStorage.setItem(key,next?'1':'0');setSaved(next)});const baseSize=parseFloat(getComputedStyle(articleContent).fontSize)||18;let size=Number(localStorage.getItem('saradeeeb-font-size')||baseSize);const applySize=()=>{size=Math.max(15,Math.min(25,size));articleContent.style.fontSize=size+'px';localStorage.setItem('saradeeeb-font-size',String(size))};applySize();fontButton?.addEventListener('click',e=>{e.stopPropagation();const open=fontMenu?.hidden!==false;if(fontMenu)fontMenu.hidden=!open;fontButton.setAttribute('aria-expanded',open?'true':'false')});fontMenu?.querySelectorAll('[data-font-step]').forEach(btn=>btn.addEventListener('click',()=>{const step=btn.dataset.fontStep;if(step==='up')size+=1;if(step==='down')size-=1;if(step==='reset')size=baseSize;applySize()}));d.addEventListener('click',e=>{if(fontMenu&&!fontMenu.hidden&&!e.target.closest('[data-font-menu],[data-font-size]')){fontMenu.hidden=true;fontButton?.setAttribute('aria-expanded','false')}})}
const backTop=d.querySelector('[data-back-top]');if(backTop){const toggle=()=>backTop.classList.toggle('is-visible',scrollY>500);addEventListener('scroll',toggle,{passive:true});toggle();backTop.addEventListener('click',()=>scrollTo({top:0,behavior:'smooth'}))}
})();

/* Homepage: mega menu + random story: appears every 15 seconds, then hides */
(()=>{
const d=document;
const megaWrap=d.querySelector('[data-mega-wrap]'),megaBtn=d.querySelector('[data-mega-button]'),megaMenu=d.querySelector('[data-mega-menu]');
const closeMega=()=>{megaWrap?.classList.remove('open');megaBtn?.setAttribute('aria-expanded','false')};
megaBtn?.addEventListener('click',e=>{e.stopPropagation();const open=!megaWrap?.classList.contains('open');closeMega();if(open){megaWrap?.classList.add('open');megaBtn?.setAttribute('aria-expanded','true')}});megaMenu?.addEventListener('click',e=>e.stopPropagation());d.addEventListener('click',closeMega);
const mobileSections=d.querySelector('[data-mobile-sections]'),mobileList=d.querySelector('[data-mobile-category-list]');mobileSections?.addEventListener('click',()=>mobileList?.classList.toggle('open'));

const card=d.querySelector('[data-random-story]'),dataNode=d.getElementById('random-story-data');if(!card||!dataNode)return;
let stories=[];try{stories=JSON.parse(dataNode.textContent||'[]')}catch(_){stories=[]}if(!Array.isArray(stories)||stories.length===0){card.remove();return}
const link=card.querySelector('[data-random-story-link]'),image=card.querySelector('[data-random-story-image]'),title=card.querySelector('[data-random-story-title]'),category=card.querySelector('[data-random-story-category]'),minutes=card.querySelector('[data-random-story-minutes]');
const locale=card.dataset.locale||'ar',defaultLocale=card.dataset.defaultLocale||'ar';let last=-1,timer=null,hideTimer=null,paused=false;
const pick=()=>{if(stories.length===1)return 0;let i=last;while(i===last)i=Math.floor(Math.random()*stories.length);return i};
const hide=()=>{if(paused){hideTimer=setTimeout(hide,1000);return}card.classList.remove('is-visible','is-changing')};
const render=()=>{const i=pick(),s=stories[i];last=i;card.classList.add('is-changing');setTimeout(()=>{const prefix=locale===defaultLocale?'':`/${locale}`;if(link)link.href=`${prefix}/article/${encodeURIComponent(s.slug)}`;if(image)image.style.backgroundImage=s.image?`linear-gradient(0deg,rgba(7,9,12,.08),rgba(7,9,12,.08)),url("${String(s.image).replace(/"/g,'\\"')}")`:'';if(title)title.textContent=s.title||'';if(category)category.textContent=s.category||'';if(minutes)minutes.textContent=s.minutes||'';card.classList.remove('is-changing');card.classList.add('is-visible');clearTimeout(hideTimer);hideTimer=setTimeout(hide,6000)},220)};
const start=()=>{clearInterval(timer);timer=setInterval(()=>{if(!paused)render()},15000)};card.classList.remove('is-visible');start();card.addEventListener('mouseenter',()=>paused=true);card.addEventListener('mouseleave',()=>{paused=false});card.addEventListener('focusin',()=>paused=true);card.addEventListener('focusout',()=>{paused=false});card.querySelector('[data-random-story-close]')?.addEventListener('click',()=>{clearInterval(timer);clearTimeout(hideTimer);card.remove()});
})();

/* Article Studio v4.2: inherited translated category + hashtag writing assistant */
(()=>{
const d=document;
const categorySelect=d.querySelector('[data-article-category]');
const mapNode=d.getElementById('article-category-map');
let categoryMap={};
try{categoryMap=JSON.parse(mapNode?.textContent||'{}')}catch(_){categoryMap={}}
const currentCategoryName=locale=>{
  const id=categorySelect?.value||'';
  return (categoryMap[id]&&categoryMap[id][locale])||'';
};
const syncCategoryLabels=()=>{
  if(!categorySelect) return;
  const id=categorySelect?.value||'';
  categorySelect.closest('form')?.querySelectorAll('[data-category-name]').forEach(node=>{
    const loc=node.dataset.categoryName||'ar';
    const categoryUi={ar:{missing:'اسم التصنيف غير مترجم لهذه اللغة',none:'بدون تصنيف'},en:{missing:'Category is not translated for this language',none:'Uncategorized'},es:{missing:'La categoría no está traducida',none:'Sin categoría'},fr:{missing:'Catégorie non traduite',none:'Sans catégorie'},ru:{missing:'Категория не переведена',none:'Без категории'}}[loc]||{missing:'Category is not translated for this language',none:'Uncategorized'};
    node.textContent=id?(currentCategoryName(loc)||categoryUi.missing):categoryUi.none;
  });
};
categorySelect?.addEventListener('change',syncCategoryLabels);syncCategoryLabels();

const stop=new Set(['هذا','هذه','ذلك','التي','الذي','على','إلى','الى','من','في','عن','مع','بعد','قبل','the','and','for','with','from','this','that','una','para','con','del','los','las','что','как','для','это']);
const cleanWord=w=>String(w||'').replace(/^#+/,'').replace(/[^\p{L}\p{N}_-]+/gu,'').trim();
const normalize=value=>{
  const out=[];
  String(value||'').replace(/[،;\n\r]+/g,' ').split(/[\s,]+/).forEach(raw=>{
    const w=cleanWord(raw); if(!w)return; const tag='#'+w;
    if(!out.includes(tag))out.push(tag);
  });
  return out.slice(0,20);
};
const inputFor=loc=>d.querySelector(`[data-hashtags-input="${loc}"]`);
const previewFor=loc=>d.querySelector(`[data-hashtag-preview="${loc}"]`);
const render=loc=>{
  const input=inputFor(loc),preview=previewFor(loc);if(!input||!preview)return;
  const tags=normalize(input.value);input.value=tags.join(' ');
  preview.innerHTML='';tags.forEach(tag=>{const s=d.createElement('span');s.textContent=tag;preview.appendChild(s)});
};
const addTags=(loc,tags)=>{
  const input=inputFor(loc);if(!input)return;
  input.value=normalize(input.value+' '+tags.join(' ')).join(' ');render(loc);
};
d.querySelectorAll('[data-hashtags-input]').forEach(input=>{const loc=input.dataset.hashtagsInput;render(loc);input.addEventListener('input',()=>render(loc));});
d.querySelectorAll('[data-hashtag-from-title]').forEach(btn=>btn.addEventListener('click',()=>{
  const loc=btn.dataset.hashtagFromTitle;const title=d.querySelector(`[data-article-title="${loc}"]`)?.value||'';
  const words=title.split(/[\s،,:;.!?؟()\[\]"']+/u).map(cleanWord).filter(w=>w.length>=3&&!stop.has(w.toLowerCase()));
  addTags(loc,[...new Set(words)].slice(0,6));
}));
d.querySelectorAll('[data-hashtag-category]').forEach(btn=>btn.addEventListener('click',()=>{
  const loc=btn.dataset.hashtagCategory;const name=currentCategoryName(loc);if(name)addTags(loc,[name.replace(/\s+/g,'_')]);
}));
d.querySelectorAll('[data-hashtag-clean]').forEach(btn=>btn.addEventListener('click',()=>render(btn.dataset.hashtagClean)));
})();


/* v4.3 local autosave: keeps an emergency copy every 30 seconds without publishing. */
(()=>{
 const form=document.querySelector('[data-article-autosave]'); if(!form)return;
 const key='saradeeeb-autosave-'+form.dataset.articleAutosave;
 const snapshot=()=>{
   document.querySelectorAll('[data-rich-editor]').forEach(ed=>{const t=document.querySelector(`[data-rich-target="${ed.dataset.richEditor}"]`);if(t)t.value=ed.innerHTML});
   const data={ts:Date.now(),fields:{}};
   form.querySelectorAll('input:not([type=file]):not([type=hidden]),textarea,select').forEach(el=>{if(el.name)data.fields[el.name]=el.value});
   document.querySelectorAll('[data-rich-editor]').forEach(ed=>data.fields['rich:'+ed.dataset.richEditor]=ed.innerHTML);
   localStorage.setItem(key,JSON.stringify(data));
 };
 setInterval(snapshot,30000);
 form.addEventListener('input',()=>{clearTimeout(form._as);form._as=setTimeout(snapshot,1200)});
 const raw=localStorage.getItem(key); if(!raw)return;
 try{const saved=JSON.parse(raw); if(!saved?.fields)return;
   const age=Date.now()-(saved.ts||0); if(age>7*86400000)return;
   const bar=document.createElement('div');bar.className='autosave-restore-bar';bar.innerHTML='<strong>توجد نسخة حفظ تلقائي محلية.</strong><button type="button">استعادتها</button><button type="button" data-discard>تجاهل</button>';
   form.prepend(bar);
   bar.querySelector('button:not([data-discard])')?.addEventListener('click',()=>{Object.entries(saved.fields).forEach(([name,val])=>{if(name.startsWith('rich:')){const loc=name.slice(5),ed=document.querySelector(`[data-rich-editor="${loc}"]`);if(ed)ed.innerHTML=val;return}const el=form.elements.namedItem(name);if(el&&'value'in el)el.value=val});bar.remove()});
   bar.querySelector('[data-discard]')?.addEventListener('click',()=>{localStorage.removeItem(key);bar.remove()});
 }catch(_){ }
})();

/* Public notifications popup */
(()=>{const pop=document.querySelector('[data-site-popup]');if(!pop)return;const id=pop.dataset.popupId||'0',k='saradeeeb-popup-'+id;if(sessionStorage.getItem(k)==='1'){pop.remove();return}pop.querySelector('[data-site-popup-close]')?.addEventListener('click',()=>{sessionStorage.setItem(k,'1');pop.remove()})})();

/* Official writer awards: one shared card, gently rotated without an overlay. */
(()=>{const host=document.querySelector('[data-floating-awards]');if(!host)return;const cards=[...host.querySelectorAll('.floating-award-item')];if(!cards.length)return;let cursor=Number(sessionStorage.getItem('saradeeeb-award-cursor')||0)%cards.length,timer;
 const eligible=card=>{const id=card.dataset.awardId,mode=card.dataset.mode;if(sessionStorage.getItem('saradeeeb-award-dismiss-'+id)==='1')return false;if(mode==='visitor'&&localStorage.getItem('saradeeeb-award-seen-'+id)==='1')return false;if(mode==='session'&&sessionStorage.getItem('saradeeeb-award-seen-'+id)==='1')return false;return true};
 const hide=card=>{card.classList.remove('is-visible');setTimeout(()=>card.hidden=true,350)};
 const show=()=>{cards.forEach(c=>{c.hidden=true;c.classList.remove('is-visible')});let card;for(let i=0;i<cards.length;i++){const candidate=cards[(cursor+i)%cards.length];if(eligible(candidate)){card=candidate;cursor=(cursor+i+1)%cards.length;break}}if(!card)return;sessionStorage.setItem('saradeeeb-award-cursor',String(cursor));card.hidden=false;requestAnimationFrame(()=>card.classList.add('is-visible'));const id=card.dataset.awardId,mode=card.dataset.mode,duration=Math.max(4,Number(card.dataset.duration)||7)*1000,interval=Math.max(30,Number(card.dataset.interval)||60)*1000;if(mode==='visitor')localStorage.setItem('saradeeeb-award-seen-'+id,'1');if(mode==='session')sessionStorage.setItem('saradeeeb-award-seen-'+id,'1');clearTimeout(timer);timer=setTimeout(()=>{hide(card);if(mode==='interval'||mode==='custom')timer=setTimeout(show,Math.max(1000,interval-duration))},duration)};
 host.addEventListener('click',e=>{const close=e.target.closest('[data-award-close]');if(!close)return;e.preventDefault();e.stopPropagation();const card=close.closest('.floating-award-item');sessionStorage.setItem('saradeeeb-award-dismiss-'+card.dataset.awardId,'1');clearTimeout(timer);hide(card)});setTimeout(show,Number(host.dataset.initialDelay)||15000)})();

/* Ad-block detector. Admin chooses notice-only or strict mode. */
(()=>{const note=document.querySelector('[data-adblock-note]');if(!note)return;const bait=document.createElement('div');bait.className='adsbox ad-banner advertisement';bait.style.cssText='position:absolute;left:-9999px;width:10px;height:10px;';document.body.appendChild(bait);setTimeout(()=>{const blocked=bait.offsetHeight===0||getComputedStyle(bait).display==='none';bait.remove();if(blocked){note.hidden=false;if(note.dataset.adblockMode==='strict')document.body.classList.add('adblock-strict-active')}},450);note.querySelector('[data-adblock-dismiss]')?.addEventListener('click',()=>note.remove())})();

/* Media library: copy paths quickly. */
document.querySelectorAll('.media-library-card input[readonly]').forEach(input=>input.addEventListener('dblclick',async()=>{try{await navigator.clipboard.writeText(input.value);input.classList.add('copied');setTimeout(()=>input.classList.remove('copied'),900)}catch(_){input.select()}}));


/* v4.5 — article sharing */
(()=>{const d=document;d.querySelectorAll('[data-share-bar]').forEach(bar=>{
  const u=bar.dataset.shareUrl||location.href,t=bar.dataset.shareTitle||d.title,status=bar.querySelector('[data-share-status]');
  bar.querySelector('[data-native-share]')?.addEventListener('click',async()=>{try{if(navigator.share)await navigator.share({title:t,url:u});else await navigator.clipboard.writeText(u)}catch(e){}});
  bar.querySelector('[data-copy-share]')?.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(u);if(status){status.textContent='تم نسخ الرابط';setTimeout(()=>status.textContent='',1800)}}catch(e){}});
});})();

/* v4.5 — content protection. Browser protection is deterrence; screenshots cannot be guaranteed blocked. */
(()=>{const d=document,p=window.SARA_PROTECTION||{};if(!p||!Object.keys(p).length)return;
  if(p.select)d.body.classList.add('protect-no-select');
  if(p.print)d.body.classList.add('protect-print');
  if(p.watermark){d.body.classList.add('protect-watermark');d.documentElement.style.setProperty('--protection-watermark',JSON.stringify(p.watermarkText||'Saradeeeb'))}
  if(p.context)d.addEventListener('contextmenu',e=>{if(!e.target.closest('input,textarea,[contenteditable=true]'))e.preventDefault()});
  if(p.copy)d.addEventListener('copy',e=>{if(!e.target.closest('input,textarea,[contenteditable=true]'))e.preventDefault()});
  if(p.drag)d.addEventListener('dragstart',e=>{if(e.target.closest('img'))e.preventDefault()});
  d.querySelectorAll('img').forEach(img=>{img.setAttribute('draggable','false');img.addEventListener('dragstart',e=>e.preventDefault())});
  d.addEventListener('keydown',e=>{const k=e.key.toLowerCase();const editable=e.target.closest?.('input,textarea,[contenteditable=true]');if(p.copy&&(e.ctrlKey||e.metaKey)&&['c','x','a'].includes(k)&&!editable)e.preventDefault();if(p.save&&(e.ctrlKey||e.metaKey)&&k==='s')e.preventDefault();if(p.print&&(e.ctrlKey||e.metaKey)&&k==='p')e.preventDefault();});
})();

// Saradeeeb Community v4.6
(()=>{
  const d=document;
  const editor=d.querySelector('[data-community-editor] textarea');
  if(editor){
    const wrap=(before,after=before)=>{const a=editor.selectionStart??0,b=editor.selectionEnd??0,v=editor.value;const selected=v.slice(a,b)||'النص';editor.value=v.slice(0,a)+before+selected+after+v.slice(b);editor.focus();editor.selectionStart=a+before.length;editor.selectionEnd=a+before.length+selected.length;};
    d.querySelectorAll('[data-editor-tag]').forEach(btn=>btn.addEventListener('click',()=>{const tag=btn.getAttribute('data-editor-tag');wrap(`<${tag}>`,`</${tag}>`)}));
    d.querySelector('[data-editor-list]')?.addEventListener('click',()=>wrap('<ul>\n<li>','</li>\n</ul>'));
    d.querySelector('[data-editor-link]')?.addEventListener('click',()=>{const url=prompt('رابط الصفحة');if(!url)return;wrap(`<a href="${url.replace(/"/g,'')}">`,'</a>')});
    d.querySelector('[data-editor-image]')?.addEventListener('click',()=>{const url=prompt('رابط الصورة (https:// أو /uploads/...)');if(!url)return;const safe=url.replace(/["<>]/g,'');const a=editor.selectionStart??editor.value.length;editor.value=editor.value.slice(0,a)+`<img src="${safe}" alt="">`+editor.value.slice(a);editor.focus();});
  }
  const replyBox=d.querySelector('#reply-box');
  d.querySelector('[data-reply-focus]')?.addEventListener('click',()=>replyBox?.querySelector('textarea')?.focus());
  d.querySelectorAll('[data-reply-to]').forEach(btn=>btn.addEventListener('click',()=>{
    if(!replyBox)return;
    const id=btn.getAttribute('data-reply-to')||'',name=btn.getAttribute('data-reply-name')||'';
    const parent=replyBox.querySelector('[data-reply-parent]'); if(parent)parent.value=id;
    const box=replyBox.querySelector('[data-replying-to]'); if(box){box.hidden=false;const n=box.querySelector('[data-replying-name]');if(n)n.textContent=name;}
    replyBox.scrollIntoView({behavior:'smooth',block:'center'}); setTimeout(()=>replyBox.querySelector('textarea')?.focus(),300);
  }));
  d.querySelector('[data-reply-cancel]')?.addEventListener('click',()=>{if(!replyBox)return;const p=replyBox.querySelector('[data-reply-parent]');if(p)p.value='';const box=replyBox.querySelector('[data-replying-to]');if(box)box.hidden=true;});
  d.querySelector('[data-report-toggle]')?.addEventListener('click',()=>{const f=d.querySelector('[data-report-form]');if(f)f.hidden=!f.hidden;});
  d.querySelectorAll('[data-community-share]').forEach(btn=>btn.addEventListener('click',async()=>{
    const url=btn.getAttribute('data-share-url')||location.href,title=btn.getAttribute('data-share-title')||d.title;
    try{if(navigator.share){await navigator.share({title,url});return;}await navigator.clipboard.writeText(url);const old=btn.textContent;btn.textContent='تم نسخ الرابط';setTimeout(()=>btn.textContent=old,1800);}catch(e){}
  }));
})();
(()=>{document.querySelectorAll('[data-reply-report-toggle]').forEach(btn=>btn.addEventListener('click',()=>{const id=btn.getAttribute('data-reply-report-toggle');const f=document.querySelector(`[data-reply-report="${id}"]`);if(f)f.hidden=!f.hidden;}));})();


// v4.8 media library + article picker
(()=>{
  const drop=document.querySelector('[data-media-drop-zone]');
  if(drop){ const input=drop.querySelector('input[type=file]'), count=drop.querySelector('[data-media-file-count]'); const update=()=>{const n=input?.files?.length||0;if(count)count.textContent=n?`${n} ملف/ملفات جاهزة للرفع`:'لم يتم اختيار ملفات';}; input?.addEventListener('change',update); ['dragenter','dragover'].forEach(ev=>drop.addEventListener(ev,e=>{e.preventDefault();drop.classList.add('is-dragging')})); ['dragleave','drop'].forEach(ev=>drop.addEventListener(ev,e=>{e.preventDefault();drop.classList.remove('is-dragging')})); drop.addEventListener('drop',e=>{if(input&&e.dataTransfer?.files?.length){try{input.files=e.dataTransfer.files}catch(_){ } update();}}); }
  const library=document.querySelector('[data-media-library]');
  if(library){
    document.querySelectorAll('[data-media-view]').forEach(btn=>btn.addEventListener('click',()=>{document.querySelectorAll('[data-media-view]').forEach(b=>b.classList.remove('active'));btn.classList.add('active');library.classList.toggle('is-list',btn.dataset.mediaView==='list');localStorage.setItem('saradeeeb_media_view',btn.dataset.mediaView||'grid')}));
    const saved=localStorage.getItem('saradeeeb_media_view'); if(saved==='list') document.querySelector('[data-media-view="list"]')?.click();
    const selectedCount=document.querySelector('[data-media-selected-count]'); const sync=()=>{if(selectedCount)selectedCount.textContent=String(document.querySelectorAll('[data-media-select]:checked').length)}; document.querySelectorAll('[data-media-select]').forEach(x=>x.addEventListener('change',sync));
    document.querySelectorAll('[data-copy-media]').forEach(btn=>btn.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(btn.dataset.copyMedia||'');const old=btn.textContent;btn.textContent='تم النسخ';setTimeout(()=>btn.textContent=old,1200)}catch(_){}}));
    const modal=document.querySelector('[data-media-preview-modal]'); document.querySelectorAll('[data-media-preview]').forEach(btn=>btn.addEventListener('click',()=>{if(!modal)return;modal.querySelector('img').src=btn.dataset.src||'';modal.querySelector('strong').textContent=btn.dataset.title||'';modal.hidden=false;})); modal?.querySelector('[data-media-preview-close]')?.addEventListener('click',()=>modal.hidden=true); modal?.addEventListener('click',e=>{if(e.target===modal)modal.hidden=true});
  }
  const picker=document.querySelector('[data-media-picker-modal]'); const pickerValue=document.querySelector('[data-media-picker-value]'), pickerSelection=document.querySelector('[data-media-picker-selection]');
  document.querySelector('[data-open-media-picker]')?.addEventListener('click',()=>{if(picker)picker.hidden=false}); document.querySelector('[data-close-media-picker]')?.addEventListener('click',()=>{if(picker)picker.hidden=true});
  document.querySelectorAll('[data-pick-media]').forEach(btn=>btn.addEventListener('click',()=>{if(pickerValue)pickerValue.value=btn.dataset.pickMedia||'';if(pickerSelection)pickerSelection.innerHTML=`<img src="${btn.dataset.pickMedia||''}" alt=""><span>${btn.dataset.mediaTitle||'تم اختيار الصورة'}</span>`;if(picker)picker.hidden=true;}));
  picker?.addEventListener('click',e=>{if(e.target===picker)picker.hidden=true});
})();
// v4.8 translation center search
(()=>{const input=document.querySelector('[data-translation-search]');if(!input)return;const fields=[...document.querySelectorAll('[data-translation-field]')];const groups=[...document.querySelectorAll('.translation-group')];const apply=()=>{const q=(input.value||'').trim().toLowerCase();fields.forEach(f=>{f.hidden=!!q&&!f.textContent.toLowerCase().includes(q)});groups.forEach(g=>{const visible=[...g.querySelectorAll('[data-translation-field]')].some(f=>!f.hidden);g.hidden=!visible;if(q&&visible)g.open=true})};input.addEventListener('input',apply)})();
/* Translation workspace controls: keep a long interface catalogue manageable
   without changing a translation value or the server-side form contract. */
(()=>{const editor=document.querySelector('[data-translation-editor]');if(!editor)return;const groups=[...editor.querySelectorAll('.translation-group')];if(groups.length>1)groups.slice(1).forEach(group=>{group.open=false});const toolbar=document.querySelector('.translation-toolbar');if(!toolbar)return;const controls=document.createElement('div');controls.className='translation-workspace-actions';controls.setAttribute('aria-label','أدوات محرر الترجمة');const make=(label,action)=>{const button=document.createElement('button');button.type='button';button.className='btn btn-ghost';button.textContent=label;button.addEventListener('click',action);controls.append(button)};make('فتح الكل',()=>groups.forEach(group=>{group.hidden=false;group.open=true}));make('طي الكل',()=>groups.forEach(group=>{group.open=false}));make('غير المترجم فقط',()=>{groups.forEach(group=>{let visible=0;group.querySelectorAll('[data-translation-field]').forEach(field=>{const control=field.querySelector('input,textarea');const empty=!control||!control.value.trim();field.hidden=!empty;if(empty)visible++});group.hidden=visible===0;group.open=visible>0})});make('إظهار الكل',()=>{groups.forEach(group=>{group.hidden=false;group.open=false;group.querySelectorAll('[data-translation-field]').forEach(field=>{field.hidden=false})});if(groups[0])groups[0].open=true});toolbar.append(controls)})();
(()=>{const names={code:'رمز اللغة',name:'اسم اللغة',flag:'العلم أو الرمز',dir:'اتجاه الكتابة',sort_order:'ترتيب الظهور',enabled:'إظهار اللغة في الموقع',is_default:'اللغة الأساسية'};document.querySelectorAll('.language-inline-form').forEach(form=>{Object.entries(names).forEach(([name,label])=>{const control=form.querySelector(`[name="${name}"]`);if(control){control.setAttribute('aria-label',label);control.setAttribute('title',label)}})})})();

// Saradeeeb Podcast player
(()=>{const fmt=s=>{s=Math.max(0,Math.floor(s||0));const m=Math.floor(s/60),r=s%60;return String(m).padStart(2,'0')+':'+String(r).padStart(2,'0')};document.querySelectorAll('[data-podcast-player]').forEach(player=>{const audio=player.querySelector('audio'),toggle=player.querySelector('[data-podcast-toggle]'),seek=player.querySelector('[data-podcast-seek]'),vol=player.querySelector('[data-podcast-volume]'),cur=player.querySelector('[data-podcast-current]'),dur=player.querySelector('[data-podcast-duration]');if(!audio||!toggle||!seek)return;const playIcon='<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7L8 5Z"/></svg>',pauseIcon='<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 5h4v14H7V5Zm6 0h4v14h-4V5Z"/></svg>';toggle.addEventListener('click',()=>{document.querySelectorAll('[data-podcast-player] audio').forEach(a=>{if(a!==audio)a.pause()});audio.paused?audio.play().catch(()=>{}):audio.pause()});audio.addEventListener('play',()=>{player.classList.add('is-playing');toggle.innerHTML=pauseIcon});audio.addEventListener('pause',()=>{player.classList.remove('is-playing');toggle.innerHTML=playIcon});audio.addEventListener('loadedmetadata',()=>{if(Number.isFinite(audio.duration)&&dur)dur.textContent=fmt(audio.duration)});audio.addEventListener('timeupdate',()=>{if(Number.isFinite(audio.duration)&&audio.duration>0){seek.value=String((audio.currentTime/audio.duration)*100);seek.style.setProperty('--podcast-progress',seek.value+'%')}if(cur)cur.textContent=fmt(audio.currentTime)});seek.addEventListener('input',()=>{if(Number.isFinite(audio.duration)&&audio.duration>0)audio.currentTime=(Number(seek.value)/100)*audio.duration;seek.style.setProperty('--podcast-progress',seek.value+'%')});vol?.addEventListener('input',()=>{audio.volume=Number(vol.value)});});})();

// Keep Arabic text legible across short, medium and long Khawatir cards.
(()=>{const size=text=>{const length=[...text.textContent.trim()].length;text.classList.remove('kh-text-short','kh-text-medium','kh-text-long');text.classList.add(length<=120?'kh-text-short':length<=260?'kh-text-medium':'kh-text-long')};document.querySelectorAll('.kh-card blockquote').forEach(text=>{size(text);new MutationObserver(()=>size(text)).observe(text,{childList:true,characterData:true,subtree:true})})})();

/* Password inputs: an accessible reveal control shared by sign-in and registration flows. */
(()=>{const copy={ar:['إظهار','إخفاء'],en:['Show','Hide'],es:['Mostrar','Ocultar'],fr:['Afficher','Masquer'],ru:['Показать','Скрыть']};const words=copy[document.documentElement.lang]||copy.en;document.querySelectorAll('input[type="password"]').forEach(input=>{let field=input.closest('.password-field'),button=field?.querySelector('[data-password-toggle]');if(!field){field=document.createElement('div');field.className='password-field';input.before(field);field.append(input)}if(!button){button=document.createElement('button');button.type='button';button.className='password-toggle';button.dataset.passwordToggle='';button.dataset.show=words[0];button.dataset.hide=words[1];field.append(button)}const sync=()=>{const hidden=input.type==='password',label=hidden?(button.dataset.show||words[0]):(button.dataset.hide||words[1]);button.textContent=label;button.setAttribute('aria-label',label);button.setAttribute('aria-pressed',hidden?'false':'true')};button.addEventListener('click',()=>{input.type=input.type==='password'?'text':'password';sync();input.focus()});sync()})})();

/* Podcast source detection, on-demand preview and generated cover preview. */
(()=>{const detect=value=>{try{const url=new URL(value),host=url.hostname.toLowerCase(),path=url.pathname.toLowerCase();if(!['http:','https:'].includes(url.protocol))return ['invalid','بروتوكول غير آمن'];if(/\.(exe|msi|bat|cmd|com|php|phar|sh)$/.test(path))return ['invalid','نوع ملف غير مسموح'];if(host==='youtu.be'||host.includes('youtube.com'))return ['youtube','YouTube'];if(/\.(mp3|m4a|ogg|wav)$/.test(path))return ['direct','ملف صوت مباشر'];if(/rss|feed|podcast|\.(xml|rss)$/.test(value.toLowerCase()))return ['rss','Podcast / RSS'];return ['cdn','استضافة خارجية / CDN']}catch(_){return ['invalid','رابط غير صالح']}};const youtubeId=value=>{try{const u=new URL(value);if(u.hostname==='youtu.be')return u.pathname.split('/')[1]||'';if(u.pathname.startsWith('/shorts/')||u.pathname.startsWith('/embed/'))return u.pathname.split('/')[2]||'';return u.searchParams.get('v')||''}catch(_){return ''}};document.querySelectorAll('[data-podcast-source-box]').forEach(box=>{const input=box.querySelector('[data-podcast-source-url]'),status=box.querySelector('[data-podcast-source-status]'),preview=box.querySelector('[data-podcast-source-preview]'),test=box.querySelector('[data-podcast-test-source]');const report=()=>{const [type,label]=detect(input.value.trim());status.className=type==='invalid'?'is-error':'is-ok';status.textContent=type==='invalid'?'✕ '+label:'✓ تم التعرف على المصدر: '+label;const auto=box.querySelector('[name="source_type"]:checked')?.value==='auto';if(auto&&type!=='invalid')box.querySelector('[name="source_type"][value="'+type+'"]')?.setAttribute('data-detected','true');return type};input?.addEventListener('input',report);test?.addEventListener('click',()=>{const type=report(),url=input.value.trim();preview.hidden=type==='invalid';preview.innerHTML='';if(type==='youtube'){const id=youtubeId(url);if(!/^[\w-]{6,20}$/.test(id)){status.textContent='✕ رابط YouTube غير صالح';preview.hidden=true;return}preview.innerHTML='<div class="podcast-test-video"><iframe loading="lazy" src="https://www.youtube-nocookie.com/embed/'+id+'" title="معاينة YouTube" allowfullscreen></iframe></div>'}else if(type==='direct'||type==='cdn'){const audio=document.createElement('audio');audio.controls=true;audio.preload='metadata';audio.src=url;preview.append(audio)}else if(type==='rss'){preview.innerHTML='<p>الرابط صالح بنيويًا كمصدر RSS. ستُحفظ بيانات الحلقة التي أدخلتها دون استبدال.</p>'}});if(input?.value)report()});document.querySelectorAll('[data-podcast-cover-builder]').forEach(builder=>{const form=builder.closest('form'),cover=builder.querySelector('[data-podcast-cover]'),title=form?.querySelector('[name="title"]'),number=form?.querySelector('[name="episode_number"]');if(!cover)return;const fit=()=>{const heading=cover.querySelector('[data-cover-title]');heading.textContent=title?.value.trim()||'عنوان الحلقة';let size=58;heading.style.fontSize=size+'px';while(size>30&&heading.scrollHeight>heading.clientHeight){size-=2;heading.style.fontSize=size+'px'}cover.className='sar-podcast-generated-cover cover-'+(builder.querySelector('[name="cover_template"]:checked')?.value||'editorial');const small=cover.querySelector('.podcast-cover-main small');if(small)small.textContent=number?.value?'الحلقة '+number.value:'حلقة جديدة'};form?.addEventListener('input',()=>requestAnimationFrame(fit));form?.addEventListener('change',()=>requestAnimationFrame(fit));fit()})})();

/* Home category articles: four-card rotation with local filtering. */
(()=>{document.querySelectorAll('[data-category-rotation]').forEach(section=>{const area=section.querySelector('[data-category-articles]'),cards=[...section.querySelectorAll('[data-category-card]')],filters=[...section.querySelectorAll('[data-category-filter]')],reset=section.querySelector('[data-category-reset]'),all=section.querySelector('[data-category-all]');if(!area||!cards.length)return;let category='',page=0,timer,drawTimer,paused=false;const reduced=matchMedia('(prefers-reduced-motion: reduce)').matches,interval=Math.max(8000,Number(section.dataset.interval)||10000),prefix=section.dataset.categoryAllPrefix||'View all ',allLabel=section.dataset.categoryAllLabel||'View all';const matches=()=>category?cards.filter(card=>card.dataset.categoryCard===category):cards;const draw=(animate=true)=>{const list=matches(),pages=Math.max(1,Math.ceil(list.length/4));page%=pages;clearTimeout(drawTimer);if(animate&&!reduced)area.classList.add('is-changing');drawTimer=setTimeout(()=>{cards.forEach(card=>{card.hidden=true;card.classList.remove('is-visible')});list.slice(page*4,page*4+4).forEach(card=>{card.hidden=false;card.classList.add('is-visible')});area.classList.remove('is-changing')},animate&&!reduced?360:0)};const start=()=>{clearInterval(timer);if(reduced)return;timer=setInterval(()=>{if(paused)return;const list=matches();if(list.length<=4)return;page=(page+1)%Math.ceil(list.length/4);draw()},interval)};filters.forEach(button=>button.addEventListener('click',()=>{category=button.dataset.categoryFilter||'';page=0;filters.forEach(x=>{x.classList.toggle('active',x===button);x.setAttribute('aria-pressed',x===button?'true':'false')});reset?.setAttribute('aria-pressed','false');if(all){all.href=button.dataset.categoryUrl;all.textContent=prefix+button.dataset.homeCategoryName+' ←'}draw();start()}));reset?.addEventListener('click',()=>{category='';page=0;filters.forEach(x=>{x.classList.remove('active');x.setAttribute('aria-pressed','false')});reset.setAttribute('aria-pressed','true');if(all){all.href=reset.dataset.allUrl||all.getAttribute('href');all.textContent=allLabel+' ←'}draw();start()});area.addEventListener('mouseenter',()=>paused=true);area.addEventListener('mouseleave',()=>paused=false);area.addEventListener('focusin',()=>paused=true);area.addEventListener('focusout',()=>paused=false);draw(false);start()})})();

/* Keep the all-articles label aligned with the home section wording. */
(()=>{document.querySelectorAll('[data-category-rotation]').forEach(section=>{const reset=section.querySelector('[data-category-reset]'),all=section.querySelector('[data-category-all]');if(!reset||!all)return;reset.addEventListener('click',()=>setTimeout(()=>{all.textContent=(section.dataset.categoryAllLabel||'View all')+' ←'},0))})})();

/* Measured auto-fit for literary cards. */
(()=>{let frame=0;const clean=value=>value.replace(/\r\n?/g,'\n').replace(/[\t ]+/g,' ').replace(/\n{3,}/g,'\n\n').trim();const fit=(card,quality)=>{const text=card.querySelector('[data-kh-autofit],blockquote'),content=card.querySelector('.kh-card-content'),head=content?.querySelector('header'),foot=content?.querySelector('footer');if(!text||!content)return;const available=Math.max(100,content.clientHeight-(head?.offsetHeight||0)-(foot?.offsetHeight||0)-34);text.style.height=available+'px';const scale=card.clientWidth/1080,length=[...text.textContent.trim()].length;let size=length<=70?96:length<=140?78:length<=220?64:length<=300?52:44;if(card.classList.contains('kh-template-long'))size=Math.min(size,48);const min=card.classList.contains('kh-template-long')?30:34,line=card.classList.contains('kh-template-poetry')?1.95:1.68;do{text.style.fontSize=(size*scale)+'px';text.style.lineHeight=String(line);size-=2}while(size>=min&&text.scrollHeight>text.clientHeight+1);const overflow=text.scrollHeight>text.clientHeight+1,used=size+2;card.classList.toggle('kh-fit-overflow',overflow);if(quality){quality.className='kh-fit-quality '+(overflow?'is-error':used<50?'is-warning':'is-good');quality.textContent=overflow?'✕ النص أطول من المساحة المناسبة. اختصره أو استخدم قالب النص الطويل.':used<50?'⚠ النص طويل وسيظهر بحجم أصغر':'✓ ممتاز لهذا التصميم'}};const schedule=(card,q)=>{cancelAnimationFrame(frame);frame=requestAnimationFrame(()=>fit(card,q))};document.querySelectorAll('[data-kh-editor]').forEach(editor=>{const body=editor.querySelector('[data-kh-body]'),card=editor.querySelector('[data-kh-card]'),category=editor.querySelector('[data-kh-category]'),quality=editor.querySelector('[data-kh-warning]'),preview=editor.querySelector('[data-kh-card-text]'),long=editor.querySelector('[data-kh-long]');if(!card||!body)return;const sync=()=>{const value=clean(body.value);preview.textContent=value||'اكتب خاطرتك هنا لتظهر المعاينة الحية.';const key=long?.checked?'template_long':(category?.selectedOptions[0]?.dataset.template||'template_khawatira');card.className=card.className.replace(/\bkh-template-[\w-]+|\balign-(center|right)|\bemphasis-(normal|strong)/g,'').trim();card.classList.add('kh-template-'+key.replace('template_',''),'align-'+(editor.querySelector('[name="text_alignment"]:checked')?.value||'center'),'emphasis-'+(editor.querySelector('[name="text_emphasis"]:checked')?.value||'normal'));const label=editor.querySelector('[data-kh-card-category]');if(label)label.textContent=category?.selectedOptions[0]?.textContent||'خاطرة';schedule(card,quality)};editor.addEventListener('input',sync);editor.addEventListener('change',sync);window.addEventListener('resize',()=>schedule(card,quality),{passive:true});sync()});document.querySelectorAll('.kh-card').forEach(card=>{if(!card.closest('[data-kh-editor]'))schedule(card,null)})})();

// Dedicated WebP renderer using the same live card at 1080x1350.
document.addEventListener('click',async event=>{const button=event.target.closest('[data-kh-download="webp"]');if(!button)return;event.preventDefault();event.stopImmediatePropagation();const card=document.querySelector('[data-kh-card]'),status=document.querySelector('[data-kh-export-status]');if(!card)return;button.disabled=true;try{await document.fonts?.ready;const clone=card.cloneNode(true),copy=(a,b)=>{let css='';for(const p of getComputedStyle(a))css+=p+':'+getComputedStyle(a).getPropertyValue(p)+';';b.setAttribute('style',css);[...a.children].forEach((c,i)=>b.children[i]&&copy(c,b.children[i]))};copy(card,clone);const wrap=document.createElement('div');wrap.setAttribute('xmlns','http://www.w3.org/1999/xhtml');wrap.style.width=card.offsetWidth+'px';wrap.style.height=card.offsetHeight+'px';wrap.append(clone);const svg='<svg xmlns="http://www.w3.org/2000/svg" width="1080" height="1350" viewBox="0 0 '+card.offsetWidth+' '+card.offsetHeight+'"><foreignObject width="100%" height="100%">'+new XMLSerializer().serializeToString(wrap)+'</foreignObject></svg>',url=URL.createObjectURL(new Blob([svg],{type:'image/svg+xml;charset=utf-8'})),image=new Image();await new Promise((ok,no)=>{image.onload=ok;image.onerror=no;image.src=url});const canvas=document.createElement('canvas');canvas.width=1080;canvas.height=1350;canvas.getContext('2d').drawImage(image,0,0,1080,1350);URL.revokeObjectURL(url);const blob=await new Promise(ok=>canvas.toBlob(ok,'image/webp',.94));if(!blob)throw Error();const link=document.createElement('a');link.href=URL.createObjectURL(blob);link.download='saradeeeb-khawatir.webp';link.click();if(status)status.textContent='تم تنزيل بطاقة WebP.'}catch(_){if(status)status.textContent='تعذر تصدير WebP في هذا المتصفح.'}finally{button.disabled=false}},true);

// Apply the saved template and size preference after the legacy preview sync.
(()=>{document.querySelectorAll('[data-kh-editor]').forEach(editor=>{const card=editor.querySelector('[data-kh-card]');if(!card)return;const apply=()=>{const variant=editor.querySelector('[name="template_variant"]:checked')?.value||'default',mode=editor.querySelector('[name="text_size_mode"]:checked')?.value||'auto';card.dataset.khVariant=variant;card.dataset.khSizeMode=mode;card.classList.remove(...[...card.classList].filter(name=>/^kh-variant-|^kh-size-(auto|small|medium|large)$/.test(name)));card.classList.add('kh-variant-'+variant,'kh-size-'+mode);requestAnimationFrame(()=>{const text=card.querySelector('[data-kh-autofit],blockquote'),content=card.querySelector('.kh-card-content');if(!text||!content)return;const scale=card.clientWidth/1080,length=[...text.textContent.trim()].length;let size=length<=70?64:length<=140?54:length<=220?46:length<=300?38:34;if(mode==='small')size-=4;if(mode==='large')size+=6;size=Math.max(32,Math.min(64,size));const min=32,available=Math.max(100,content.clientHeight-(content.querySelector('header')?.offsetHeight||0)-(content.querySelector('footer')?.offsetHeight||0)-34);text.style.height=available+'px';do{text.style.fontSize=(size*scale)+'px';size-=2}while(size>=min&&text.scrollHeight>text.clientHeight+1)});};editor.addEventListener('change',event=>{if(event.target.matches('[name="template_variant"],[name="text_size_mode"]'))setTimeout(apply,0)});apply()})})();

/* Final measured fit: the selected size is only a preference; the DOM remains the safety limit. */
(()=>{const label={auto:'تلقائي',small:'صغير',medium:'متوسط',large:'كبير'};const fit=(editor)=>{const card=editor.querySelector('[data-kh-card]'),text=card?.querySelector('[data-kh-autofit],blockquote'),content=card?.querySelector('.kh-card-content');if(!card||!text||!content)return;const mode=editor.querySelector('[name="text_size_mode"]:checked')?.value||'auto',length=[...text.textContent.trim()].length,scale=Math.max(.2,card.clientWidth/1080),isPoetry=card.classList.contains('kh-template-poetry');let size=mode==='small'?40:mode==='medium'?50:mode==='large'?60:(length<=70?62:length<=140?54:length<=220?46:length<=300?40:34);if(isPoetry)size=Math.min(size,52);const min=32,header=content.querySelector('header'),footer=content.querySelector('footer'),available=Math.max(100,content.clientHeight-(header?.offsetHeight||0)-(footer?.offsetHeight||0)-30);text.style.height=available+'px';text.style.maxHeight='none';text.style.overflow='visible';text.style.fontSize=(Math.min(64,Math.max(min,size))*scale)+'px';text.style.lineHeight=isPoetry?'1.82':'1.62';let used=Math.min(64,Math.max(min,size));while(used>min&&text.scrollHeight>text.clientHeight+1){used-=1;text.style.fontSize=(used*scale)+'px'}const overflow=text.scrollHeight>text.clientHeight+1;card.classList.toggle('kh-fit-overflow',overflow);const status=editor.querySelector('[data-kh-warning]');if(status){status.className='kh-fit-quality '+(overflow?'is-error':used<Math.min(64,size)?'is-warning':'is-good');status.textContent=overflow?'✕ النص لا يتسع داخل منطقة الأمان. اختصره قليلًا.':'✓ الحجم الفعلي: '+Math.round(used)+'px · '+label[mode]+' مع ملاءمة تلقائية'}const note=editor.querySelector('.kh-size-control small');if(note)note.textContent=overflow?'النظام يحافظ على النص كاملًا؛ قلّل عدد الأسطر أو اختر الحجم التلقائي.':'الحجم الفعلي '+Math.round(used)+'px؛ سيُخفّض تلقائيًا عند الحاجة.'};document.querySelectorAll('[data-kh-editor]').forEach(editor=>{const refresh=()=>requestAnimationFrame(()=>fit(editor));editor.addEventListener('input',refresh);editor.addEventListener('change',event=>{if(event.target.matches('[name="template_variant"],[name="text_size_mode"],[name="text_alignment"],[name="text_emphasis"],[data-kh-category],[data-kh-long]'))refresh()});window.addEventListener('resize',refresh,{passive:true});refresh()})})();

// Podcast artwork title fitting: preserve the full title inside the safe area.
(()=>{document.querySelectorAll('[data-podcast-cover-builder]').forEach(builder=>{const form=builder.closest('form'),cover=builder.querySelector('[data-podcast-cover]'),title=form?.querySelector('[name="title"]');if(!cover)return;const fit=()=>{const heading=cover.querySelector('[data-cover-title]');if(!heading)return;heading.textContent=title?.value.trim()||'عنوان الحلقة';heading.style.display='block';heading.style.webkitLineClamp='unset';heading.style.overflow='hidden';heading.style.maxHeight='3.35em';let size=Math.min(82,Math.max(34,parseFloat(getComputedStyle(heading).fontSize)||58));heading.style.fontSize=size+'px';while(size>24&&heading.scrollHeight>heading.clientHeight+1){size-=2;heading.style.fontSize=size+'px'}if(heading.scrollHeight>heading.clientHeight+1){heading.style.fontSize='24px';heading.style.maxHeight='none';heading.style.overflow='visible'}else{heading.style.display='-webkit-box';heading.style.webkitLineClamp='3'}};form?.addEventListener('input',()=>requestAnimationFrame(fit));form?.addEventListener('change',()=>requestAnimationFrame(fit));fit()})})();

// Khawatir editor, sharing and high-resolution 4:5 export.
(()=>{const editor=document.querySelector('[data-kh-editor]');if(editor){const body=editor.querySelector('[data-kh-body]'),count=editor.querySelector('[data-kh-count]'),warning=editor.querySelector('[data-kh-warning]'),meter=editor.querySelector('.kh-char-meter'),cardText=editor.querySelector('[data-kh-card-text]'),category=editor.querySelector('[data-kh-category]'),cardCategory=editor.querySelector('[data-kh-card-category]'),source=editor.querySelector('[data-kh-source]'),imageInput=editor.querySelector('[data-kh-image]'),card=editor.querySelector('[data-kh-card]'),avatar=editor.querySelector('[data-kh-card-avatar]'),max=Number(editor.dataset.max||420),warn=Number(editor.dataset.warn||300);const sync=()=>{const value=body?.value.trim()||'',length=[...value].length;if(count)count.textContent=String(length);if(cardText){cardText.textContent=value||'اكتب خاطرتك هنا لتظهر المعاينة الحية.';cardText.dataset.length=String(length)}meter?.classList.toggle('warning',length>=warn&&length<max);meter?.classList.toggle('limit',length>=max);if(warning)warning.textContent=length>=max?'وصل النص إلى الحد الأقصى. اختصره قبل الحفظ.':length>=warn?'النص طويل نسبياً؛ راجع توازنه داخل البطاقة.':'النصوص المكثفة تظهر بأفضل توازن داخل البطاقة.'};body?.addEventListener('input',sync);category?.addEventListener('change',()=>{if(cardCategory)cardCategory.textContent=category.selectedOptions[0]?.textContent||'خاطرة'});editor.querySelectorAll('[name="ownership"]').forEach(input=>input.addEventListener('change',()=>{if(source)source.hidden=input.value!=='quoted'||!input.checked}));imageInput?.addEventListener('change',()=>{const file=imageInput.files?.[0];if(!file||!card)return;const reader=new FileReader();reader.onload=()=>{const value=String(reader.result||'');card.style.setProperty('--kh-bg-image',`url("${value}")`);card.classList.add('has-image');if(avatar)avatar.src=value};reader.readAsDataURL(file)});sync()}
const copy=async(value,button)=>{try{await navigator.clipboard.writeText(value);const old=button.textContent;button.textContent='تم النسخ';setTimeout(()=>button.textContent=old,1200)}catch(_){}};document.querySelectorAll('[data-copy-text]').forEach(button=>button.addEventListener('click',()=>copy(button.dataset.copyText||'',button)));document.querySelectorAll('[data-kh-share]').forEach(button=>button.addEventListener('click',async()=>{const data={title:button.dataset.title||document.title,text:button.dataset.text||'',url:button.dataset.url||location.href};if(navigator.share){try{await navigator.share(data);return}catch(error){if(error?.name==='AbortError')return}}copy(data.url,button)}));
const inlineStyles=(source,target)=>{const styles=getComputedStyle(source);let css='';for(const property of styles)css+=property+':'+styles.getPropertyValue(property)+';';target.setAttribute('style',css);[...source.children].forEach((child,index)=>target.children[index]&&inlineStyles(child,target.children[index]))};const inlineImages=async root=>{for(const img of root.querySelectorAll('img')){try{const response=await fetch(img.src,{mode:'cors'});const blob=await response.blob();img.src=await new Promise(resolve=>{const reader=new FileReader();reader.onload=()=>resolve(reader.result);reader.readAsDataURL(blob)})}catch(_){img.remove()}}};document.querySelectorAll('[data-kh-download]').forEach(button=>button.addEventListener('click',async()=>{const card=document.querySelector('[data-kh-card]'),status=document.querySelector('[data-kh-export-status]');if(!card)return;if(status)status.textContent='جارٍ تجهيز البطاقة بدقة 1080×1350...';button.disabled=true;try{await document.fonts?.ready;const clone=card.cloneNode(true);inlineStyles(card,clone);clone.style.width=card.offsetWidth+'px';clone.style.height=card.offsetHeight+'px';clone.style.transform='none';await inlineImages(clone);const wrap=document.createElement('div');wrap.setAttribute('xmlns','http://www.w3.org/1999/xhtml');wrap.style.width=card.offsetWidth+'px';wrap.style.height=card.offsetHeight+'px';wrap.appendChild(clone);const xml=new XMLSerializer().serializeToString(wrap);const svg='<svg xmlns="http://www.w3.org/2000/svg" width="1080" height="1350" viewBox="0 0 '+card.offsetWidth+' '+card.offsetHeight+'"><foreignObject width="100%" height="100%">'+xml+'</foreignObject></svg>';const objectUrl=URL.createObjectURL(new Blob([svg],{type:'image/svg+xml;charset=utf-8'}));const image=new Image();await new Promise((resolve,reject)=>{image.onload=resolve;image.onerror=reject;image.src=objectUrl});const canvas=document.createElement('canvas');canvas.width=1080;canvas.height=1350;const context=canvas.getContext('2d');context.fillStyle='#071a20';context.fillRect(0,0,1080,1350);context.drawImage(image,0,0,1080,1350);URL.revokeObjectURL(objectUrl);const format=button.dataset.khDownload==='jpeg'?'jpeg':'png',mime=format==='jpeg'?'image/jpeg':'image/png';const output=await new Promise(resolve=>canvas.toBlob(resolve,mime,.95));if(!output)throw new Error('export');const link=document.createElement('a');link.href=URL.createObjectURL(output);link.download='saradeeeb-khawatir.'+(format==='jpeg'?'jpg':'png');link.click();setTimeout(()=>URL.revokeObjectURL(link.href),1500);if(status)status.textContent='تم تنزيل البطاقة بالمقاس الأصلي.'}catch(_){if(status)status.textContent='تعذر التصدير في هذا المتصفح. جرّب PNG أو الطباعة إلى PDF.'}finally{button.disabled=false}}))})();
// Podcast cover export keeps the administrative artwork and downloadable output aligned.
(()=>{document.querySelectorAll('[data-podcast-cover-export]').forEach(toolbar=>{const builder=document.querySelector('[data-podcast-cover-builder]'),cover=builder?.querySelector('[data-podcast-cover]'),status=toolbar.querySelector('[data-podcast-export-status]');if(!cover)return;const inline=(source,target)=>{const styles=getComputedStyle(source);let css='';for(const property of styles)css+=property+':'+styles.getPropertyValue(property)+';';target.setAttribute('style',css);[...source.children].forEach((child,index)=>target.children[index]&&inline(child,target.children[index]))};toolbar.querySelectorAll('[data-podcast-export]').forEach(button=>button.addEventListener('click',async()=>{button.disabled=true;if(status)status.textContent='جارٍ تجهيز الغلاف...';try{await document.fonts?.ready;const clone=cover.cloneNode(true),rect=cover.getBoundingClientRect();inline(cover,clone);clone.style.width=rect.width+'px';clone.style.height=rect.height+'px';const wrap=document.createElement('div');wrap.setAttribute('xmlns','http://www.w3.org/1999/xhtml');wrap.style.width=rect.width+'px';wrap.style.height=rect.height+'px';wrap.appendChild(clone);const xml=new XMLSerializer().serializeToString(wrap),svg='<svg xmlns="http://www.w3.org/2000/svg" width="1600" height="1600" viewBox="0 0 '+rect.width+' '+rect.height+'"><foreignObject width="100%" height="100%">'+xml+'</foreignObject></svg>',objectUrl=URL.createObjectURL(new Blob([svg],{type:'image/svg+xml;charset=utf-8'})),image=new Image();await new Promise((resolve,reject)=>{image.onload=resolve;image.onerror=reject;image.src=objectUrl});const canvas=document.createElement('canvas');canvas.width=1600;canvas.height=1600;canvas.getContext('2d').drawImage(image,0,0,1600,1600);URL.revokeObjectURL(objectUrl);const jpeg=button.dataset.podcastExport==='jpeg',blob=await new Promise(resolve=>canvas.toBlob(resolve,jpeg?'image/jpeg':'image/png',.95));if(!blob)throw new Error('export');const link=document.createElement('a');link.href=URL.createObjectURL(blob);link.download='saradeeeb-podcast-cover.'+(jpeg?'jpg':'png');link.click();setTimeout(()=>URL.revokeObjectURL(link.href),1500);if(status)status.textContent='تم تجهيز الغلاف بمقاس 1600×1600.'}catch(_){if(status)status.textContent='تعذر التصدير في هذا المتصفح.'}finally{button.disabled=false}}))})})();

/* Article reader controls: the article stylesheet intentionally uses !important,
   so the selected reader size must be applied at the same priority. */
(()=>{const content=document.querySelector('[data-article-content]'),controls=document.querySelectorAll('[data-font-step]');if(!content||!controls.length)return;const base=parseFloat(getComputedStyle(content).fontSize)||18;let size=Number(localStorage.getItem('saradeeeb-font-size')||base);if(!Number.isFinite(size))size=base;const apply=()=>{size=Math.max(15,Math.min(25,size));content.style.setProperty('font-size',size+'px','important');localStorage.setItem('saradeeeb-font-size',String(size));controls.forEach(button=>button.setAttribute('aria-pressed',button.dataset.fontStep==='reset'?'true':'false'))};apply();controls.forEach(button=>button.addEventListener('click',()=>{if(button.dataset.fontStep==='up')size+=1;else if(button.dataset.fontStep==='down')size-=1;else size=base;apply()}))})();

// Read duration from a local/direct audio source when the browser can inspect it.
(()=>{const form=document.querySelector('.podcast-editor');if(!form)return;const field=form.querySelector('[name="duration"]'),file=form.querySelector('[name="audio_file"]'),url=form.querySelector('[name="audio_url"]');if(!field)return;const format=seconds=>{seconds=Math.round(Number(seconds)||0);if(!seconds)return '';const h=Math.floor(seconds/3600),m=Math.floor((seconds%3600)/60),s=seconds%60;return h?String(h).padStart(2,'0')+':'+String(m).padStart(2,'0')+':'+String(s).padStart(2,'0'):String(m).padStart(2,'0')+':'+String(s).padStart(2,'0')};const inspect=source=>{if(!source)return;const audio=document.createElement('audio');audio.preload='metadata';audio.onloadedmetadata=()=>{const value=format(audio.duration);if(value)field.value=value;URL.revokeObjectURL(audio.src)};audio.src=source};file?.addEventListener('change',()=>{const selected=file.files?.[0];if(selected)inspect(URL.createObjectURL(selected))});url?.addEventListener('change',()=>{const value=url.value.trim();if(/\.(mp3|m4a|ogg|wav)(?:$|\?)/i.test(value))inspect(value)})})();

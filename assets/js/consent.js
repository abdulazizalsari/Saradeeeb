'use strict';
/* v4.5.2 — privacy consent manager for integrations and tracking */
(()=>{
  const root=document.querySelector('[data-tracking-consent]');
  if(!root)return;
  const version=root.dataset.consentVersion||'1';
  const choices={
    analytics:root.querySelector('[data-consent-choice="analytics"]'),
    marketing:root.querySelector('[data-consent-choice="marketing"]'),
    ads:root.querySelector('[data-consent-choice="ads"]')
  };
  const readCookie=()=>{
    const row=document.cookie.split('; ').find(v=>v.startsWith('saradeeeb_consent='));
    if(!row)return null;
    try{return JSON.parse(decodeURIComponent(row.slice(row.indexOf('=')+1)))}catch(_){return null}
  };
  const current=readCookie();
  const valid=current&&String(current.v||'')===String(version);
  const open=()=>{root.classList.add('is-open');root.setAttribute('aria-hidden','false');document.body.classList.add('consent-open')};
  const close=()=>{root.classList.remove('is-open');root.setAttribute('aria-hidden','true');document.body.classList.remove('consent-open')};
  const setChecks=state=>Object.entries(choices).forEach(([key,input])=>{if(input)input.checked=!!state?.[key]});
  if(valid)setChecks(current);else open();
  const save=state=>{
    const payload={v:version,essential:true,analytics:!!state.analytics,marketing:!!state.marketing,ads:!!state.ads,ts:Date.now()};
    const secure=location.protocol==='https:'?'; Secure':'';
    document.cookie='saradeeeb_consent='+encodeURIComponent(JSON.stringify(payload))+'; Max-Age=31536000; Path=/; SameSite=Lax'+secure;
    close();
    location.reload();
  };
  document.querySelectorAll('[data-consent-manage]').forEach(btn=>btn.addEventListener('click',()=>{setChecks(readCookie()||{});open()}));
  root.querySelector('[data-consent-accept]')?.addEventListener('click',()=>save({analytics:true,marketing:true,ads:true}));
  root.querySelector('[data-consent-reject]')?.addEventListener('click',()=>save({analytics:false,marketing:false,ads:false}));
  root.querySelector('[data-consent-save]')?.addEventListener('click',()=>save({analytics:choices.analytics?.checked,marketing:choices.marketing?.checked,ads:choices.ads?.checked}));
})();

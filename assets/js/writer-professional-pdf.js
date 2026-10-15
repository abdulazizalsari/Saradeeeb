(() => {
  const button = document.querySelector('[data-export-pdf]');
  const source = document.querySelector('.professional-pdf');
  if (!button || !source) return;
  button.addEventListener('click', async event => {
    event.preventDefault();
    event.stopImmediatePropagation();
    const status = document.querySelector('[data-card-status]');
    button.disabled = true;
    const frame = document.createElement('iframe');
    frame.title = 'الملف المهني للطباعة';
    frame.style.cssText = 'position:fixed;width:1px;height:1px;bottom:0;left:0;border:0;opacity:0;pointer-events:none';
    document.body.append(frame);
    try {
      const doc = frame.contentDocument;
      doc.open();
      doc.write('<!doctype html><html lang="ar" dir="rtl"><head><meta charset="UTF-8"></head><body></body></html>');
      doc.close();
      doc.title = source.querySelector('h1')?.textContent || 'الملف المهني';
      const loadStyle = href => new Promise((resolve,reject) => {
        const link=doc.createElement('link');
        link.rel='stylesheet';link.href=href;link.onload=resolve;link.onerror=reject;doc.head.append(link);
      });
      const styles=[loadStyle(document.querySelector('[data-professional-pdf-style]').href)];
      const font=document.querySelector('link[href*="fonts.googleapis.com/css2"]');
      if(font) styles.push(loadStyle(font.href).catch(()=>{}));
      const clone=source.cloneNode(true);
      clone.hidden=false;
      clone.querySelectorAll('img').forEach(img=>{img.src=new URL(img.getAttribute('src'),location.href).href;});
      doc.body.append(clone);
      await Promise.all(styles);
      await doc.fonts.ready;
      await Promise.all([...doc.images].map(img=>img.decode().catch(()=>{})));
      frame.contentWindow.addEventListener('afterprint',()=>frame.remove(),{once:true});
      frame.contentWindow.focus();
      frame.contentWindow.print();
      if(status)status.textContent='اختر «حفظ كملف PDF»، وألغِ رؤوس الصفحات وتذييلاتها من إعدادات الطباعة.';
    } catch(error) {
      frame.remove();
      if(status)status.textContent='تعذر تجهيز الملف المهني. أعد المحاولة.';
    } finally { button.disabled=false; }
  },true);
})();

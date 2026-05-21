(function(){
  function el(tag, cls, html){ const e = document.createElement(tag); if(cls) e.className = cls; if(html!==undefined) e.innerHTML = html; return e; }

  const container = document.getElementById('appToast') || (function(){ const c = document.createElement('div'); c.id='appToast'; document.body.appendChild(c); return c; })();

  function buildToast(type, message, opts){
    const toast = el('div','toast toast-'+type);
    const icon = el('div','toast-icon');
    if(type==='success') icon.innerHTML = '<i class="fa-solid fa-check"></i>';
    else if(type==='error') icon.innerHTML = '<i class="fa-solid fa-xmark"></i>';
    else icon.innerHTML = '<i class="fa-solid fa-info"></i>';

    const text = el('div','toast-text', message);

    toast.appendChild(icon);
    toast.appendChild(text);

    return toast;
  }

  function show(toast, timeout){
    container.prepend(toast);
    // trigger animation
    requestAnimationFrame(() => toast.classList.add('show'));
    if(timeout && timeout>0){
      setTimeout(()=> hide(toast), timeout);
    }
  }

  function hide(toast){
    toast.classList.remove('show');
    setTimeout(()=>{ try{ toast.remove(); }catch(e){} }, 220);
  }

  window.notify = function(type, message, opts){
    opts = opts || {};
    const t = type === 'error' ? 'error' : (type === 'info' ? 'info' : 'success');
    const toast = buildToast(t, String(message || ''), opts);
    const timeout = typeof opts.timeout === 'number' ? opts.timeout : 4000;
    show(toast, timeout);
    return toast;
  };
})();

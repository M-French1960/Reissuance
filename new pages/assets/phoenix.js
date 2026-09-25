/* =========================================================
   PHOENIX — script commun
   - PX.shell() construit la barre latérale et la barre supérieure
     à partir des attributs data-* du <body>.
   - Utilitaires de formatage, dialogues, toasts, graphiques SVG.
   Données d'exemple uniquement : à remplacer par l'API Laravel.
   ========================================================= */
const PX = (() => {
  const $ = id => document.getElementById(id);
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const digits = s => String(s ?? '').replace(/\D/g,'');
  const norm = s => String(s ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLowerCase().trim();
  const toDate = v => new Date(typeof v === 'string' && v.length === 10 ? v + 'T00:00:00' : v);
  const fmtDate = v => v ? toDate(v).toLocaleDateString('fr-FR',{day:'numeric',month:'short',year:'numeric'}) : '—';
  const fmtDateLong = v => v ? toDate(v).toLocaleDateString('fr-FR',{day:'numeric',month:'long',year:'numeric'}) : '—';
  const fmtTime = v => v ? toDate(v).toLocaleTimeString('fr-FR',{hour:'2-digit',minute:'2-digit'}) : '';
  const fmtDateTime = v => v ? `${fmtDate(v)} ${fmtTime(v)}` : '—';
  const fmtXAF = n => new Intl.NumberFormat('fr-FR').format(n) + ' FCFA';
  const fmtNum = n => new Intl.NumberFormat('fr-FR').format(n);
  const daysSince = v => Math.floor((Date.now() - toDate(v)) / 86400000);
  const fmtPhone = v => { let d = digits(v); if (d.startsWith('237') && d.length > 9) d = d.slice(3); d = d.slice(0,9);
    return [d.slice(0,3), d.slice(3,5), d.slice(5,7), d.slice(7,9)].filter(Boolean).join(' '); };
  const phoneError = v => { const d = digits(v).replace(/^237(?=\d{9}$)/,'');
    if (!d) return 'Indiquez un numéro de téléphone.'; return /^6\d{8}$/.test(d) ? '' : '9 chiffres commençant par 6, par exemple 6XX XX XX XX.'; };
  const initials = name => String(name || '?').trim().split(/\s+/).slice(0,2).map(w => w[0]).join('').toUpperCase();

  /* ---------- Référentiels partagés ---------- */
  const CENTERS = {
    'yaounde-1':'Yaoundé I','yaounde-2':'Yaoundé II','yaounde-3':'Yaoundé III','yaounde-4':'Yaoundé IV',
    'yaounde-5':'Yaoundé V','yaounde-6':'Yaoundé VI','yaounde-7':'Yaoundé VII'
  };
  const STATUS = {
    pending:{label:'En attente',cls:'s-pending'}, complement:{label:'Complément demandé',cls:'s-complement'},
    verified:{label:'Vérifiée',cls:'s-verified'}, escalated:{label:'Escaladée',cls:'s-escalated'},
    signed:{label:'Signée',cls:'s-signed'}, rejected:{label:'Rejetée',cls:'s-rejected'},
    completed:{label:'Terminée',cls:'s-completed'}
  };
  const pill = key => { const s = STATUS[key] || {label:key,cls:''}; return `<span class="pill ${s.cls}">${esc(s.label)}</span>`; };
  const FEE_XAF = 20000; // montant du prototype d'origine — à confirmer, et à fixer côté serveur

  /* ---------- Icônes ---------- */
  const I = p => `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${p}</svg>`;
  const ICONS = {
    dash:I('<rect x="3" y="3" width="7" height="9" rx="2"/><rect x="14" y="3" width="7" height="5" rx="2"/><rect x="14" y="12" width="7" height="9" rx="2"/><rect x="3" y="16" width="7" height="5" rx="2"/>'),
    doc:I('<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5M10 13h6M10 17h6"/>'),
    check:I('<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5M10 14l2 2 4-4"/>'),
    card:I('<rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/>'),
    bell:I('<path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>'),
    user:I('<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>'),
    gear:I('<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"/>'),
    chart:I('<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>'),
    pen:I('<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>'),
    signed:I('<path d="M7 3h7l5 5v13H7z"/><path d="M14 3v5h5M9 17c1.5-2 2.5-2 3 0s1.5 2 3 0"/>'),
    users:I('<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c1-3.5 3.5-5 6.5-5s5.5 1.5 6.5 5"/><circle cx="17" cy="9" r="2.5"/><path d="M17 14.5c2.3 0 4 1.3 4.5 4"/>'),
    building:I('<path d="M3 21h18M5 21V9l7-5 7 5v12M9 21v-6h6v6M9 11h.01M15 11h.01"/>'),
    list:I('<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>'),
    money:I('<rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="2.5"/><path d="M6 12h.01M18 12h.01"/>'),
    out:I('<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>'),
    menu:I('<path d="M4 7h16M4 12h16M4 17h16"/>'),
    ok:I('<path d="M5 12l5 5 9-10"/>'),
    x:I('<path d="M6 6l12 12M18 6L6 18"/>'),
    info:I('<circle cx="12" cy="12" r="9"/><path d="M12 8v4M12 16h.01"/>'),
    clock:I('<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'),
    download:I('<path d="M12 3v12M7 10l5 5 5-5M4 21h16"/>'),
    upload:I('<path d="M12 21V9M7 14l5-5 5 5M4 3h16"/>'),
    camera:I('<path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/>'),
    shield:I('<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>'),
    search:I('<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>'),
    help:I('<circle cx="12" cy="12" r="9"/><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6V14M12 17h.01"/>'),
    qr:I('<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM20 14v.01M14 20h.01M17 20h4v-3"/>'),
    alert:I('<path d="M12 3l10 18H2z"/><path d="M12 10v4M12 18h.01"/>')
  };
  const LOGO = `<svg viewBox="0 0 40 40" aria-hidden="true"><defs><linearGradient id="pxg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#FFFFFF"/><stop offset="1" stop-color="#C9A8FF"/></linearGradient></defs><path d="M20 4c3 6 9 8 13 7-2 5-6 8-9 9 3 1 6 4 7 9-5-1-9-4-11-8-2 4-6 7-11 8 1-5 4-8 7-9-3-1-7-4-9-9 4 1 10-1 13-7z" fill="url(#pxg)"/></svg>`;

  /* ---------- Navigation par rôle ---------- */
  const NAV = {
    applicant:{ sub:'Espace demandeur', home:'applicant-dashboard.html', signout:'login.html?reason=signedout',
      items:[['dash','Tableau de bord','applicant-dashboard.html','dashboard'],['doc','Mes demandes','requests.html','requests'],
             ['card','Paiements','payments.html','payments'],['bell','Notifications','notifications.html','notifications'],['user','Mon profil','profile.html','profile']],
      box:`<span>Une question sur votre demande ?</span><a href="help.html">Contacter le support</a>` },
    officer:{ sub:'Espace officier', home:'officer-dashboard.html', signout:'officer-login.html?reason=signedout',
      items:[['dash','Tableau de bord','officer-dashboard.html','dashboard'],['check','Demandes à vérifier','officer-dashboard.html?status=pending','verify'],
             ['chart','Rapports','officer-reports.html','reports'],['gear','Paramètres','profile.html?role=officer','profile']],
      box:`<span>Centre d'affectation</span><strong>Yaoundé III</strong>` },
    mayor:{ sub:'Espace maire', home:'mayor-dashboard.html', signout:'officer-login.html?reason=signedout',
      items:[['pen','Parapheur','mayor-dashboard.html','parapheur'],['signed','Actes signés','mayor-signed.html','signed'],
             ['gear','Paramètres','profile.html?role=mayor','profile']],
      box:`<span>Mairie</span><strong>Yaoundé III</strong>` },
    admin:{ sub:'Administration', home:'admin-dashboard.html', signout:'officer-login.html?reason=signedout',
      items:[['dash','Tableau de bord','admin-dashboard.html','dashboard'],['users','Agents','admin-agents.html','agents'],
             ['building','Centres','admin-centers.html','centers'],['money','Paiements','admin-payments.html','payments'],
             ['list','Journal d\'audit','admin-audit.html','audit'],['gear','Configuration','admin-settings.html','settings']],
      box:`<span>Périmètre</span><strong>National</strong>` }
  };
  const USERS = { officer:'Alain Mbarga', mayor:'Paul Essomba', admin:'Claire Abena' };
  function currentUser(role){
    if (role === 'applicant'){
      let n = ''; try { n = localStorage.getItem('userName') || ''; } catch(e) {}
      n = n.replace(/\s+/g,' ').trim().split(' ').filter(Boolean).map(w => w[0].toUpperCase() + w.slice(1).toLowerCase()).join(' ');
      return n || 'Marie France Ngoa';
    }
    return USERS[role] || 'Agent';
  }
  function roleFromPage(){
    const b = document.body.dataset.role;
    if (b === 'any'){ const r = new URLSearchParams(location.search).get('role'); return NAV[r] ? r : 'applicant'; }
    return b;
  }

  /* ---------- Coque des espaces connectés ---------- */
  function shell(opts = {}){
    const body = document.body;
    const role = roleFromPage();
    const nav = NAV[role];
    const active = body.dataset.active;
    const layout = document.querySelector('.layout');
    const main = document.querySelector('.main');
    if (!nav || !layout || !main) return role;

    const name = currentUser(role);
    const counts = opts.counts || {};
    const items = nav.items.map(([ic,label,href,key]) => `
      <li><a href="${href}" ${key === active ? 'aria-current="page"' : ''}>${ICONS[ic]}${esc(label)}${counts[key] ? `<span class="count">${counts[key]}</span>` : ''}</a></li>`).join('');

    layout.insertAdjacentHTML('afterbegin', `
      <aside class="sidebar" id="sidebar" aria-label="Menu principal">
        <a class="brand" href="${nav.home}">${LOGO}<span><span class="brand-name">PHOENIX</span><span class="brand-sub">${esc(nav.sub)}</span></span></a>
        <nav><ul class="side-nav">${items}</ul></nav>
        <div class="side-box">${nav.box}</div>
        <button class="signout" type="button" id="px-signout">${ICONS.out}Se déconnecter</button>
      </aside>
      <div class="scrim" id="px-scrim"></div>`);

    const parent = body.dataset.crumbParent ? body.dataset.crumbParent.split('|') : null;
    const notif = role === 'applicant'
      ? `<a class="icon-btn" href="notifications.html" aria-label="Notifications${counts.notifications ? `, ${counts.notifications} non lues` : ''}">${ICONS.bell.replace('<svg','<svg width="20" height="20"')}${counts.notifications ? `<span class="badge">${counts.notifications}</span>` : ''}</a>` : '';
    main.insertAdjacentHTML('afterbegin', `
      <header class="topbar">
        <button class="menu-toggle" id="px-menu" type="button" aria-controls="sidebar" aria-expanded="false" aria-label="Ouvrir le menu">${ICONS.menu.replace('<svg','<svg width="22" height="22"')}</button>
        <nav class="crumb" aria-label="Fil d'Ariane">${parent ? `<a href="${parent[1]}">${esc(parent[0])}</a><span aria-hidden="true">/</span>` : ''}<strong id="px-crumb">${esc(body.dataset.crumb || '')}</strong></nav>
        <div class="top-actions">${notif}
          <a class="avatar" href="profile.html${role === 'applicant' ? '' : '?role=' + role}" aria-label="Mon profil"><span class="init" aria-hidden="true">${esc(initials(name))}</span><span class="name">${esc(name)}</span></a>
        </div>
      </header>`);
    if (!document.querySelector('.skip')) body.insertAdjacentHTML('afterbegin','<a class="skip" href="#contenu">Aller au contenu</a>');

    const sb = $('sidebar'), scrim = $('px-scrim'), tg = $('px-menu');
    const setMenu = open => { sb.classList.toggle('open', open); scrim.classList.toggle('open', open);
      tg.setAttribute('aria-expanded', open); tg.setAttribute('aria-label', open ? 'Fermer le menu' : 'Ouvrir le menu'); };
    tg.addEventListener('click', () => setMenu(!sb.classList.contains('open')));
    scrim.addEventListener('click', () => setMenu(false));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') setMenu(false); });
    $('px-signout').addEventListener('click', () => {
      if (role === 'applicant'){ try { ['userName','userEmail','userGender','userPassword'].forEach(k => localStorage.removeItem(k)); } catch(e) {} }
      location.href = nav.signout;
    });
    return role;
  }

  /* ---------- Toasts ---------- */
  function toast(msg, type = ''){
    let zone = document.querySelector('.toast-zone');
    if (!zone){ zone = document.createElement('div'); zone.className = 'toast-zone'; zone.setAttribute('role','status'); zone.setAttribute('aria-live','polite'); document.body.appendChild(zone); }
    const t = document.createElement('div'); t.className = 'toast ' + type; t.textContent = msg; zone.appendChild(t);
    setTimeout(() => t.remove(), 3500);
  }

  /* ---------- Confirmation ---------- */
  function confirmDialog({ title, text, ok = 'Confirmer', danger = false, input = null }){
    return new Promise(resolve => {
      const d = document.createElement('dialog');
      d.innerHTML = `<form method="dialog" class="dlg">
        <h2>${esc(title)}</h2><p>${esc(text)}</p>
        ${input ? `<div class="field" style="margin-top:1rem"><label for="px-dlg-input">${esc(input.label)}</label>
          ${input.textarea ? `<textarea id="px-dlg-input"></textarea>` : `<input type="${input.type || 'text'}" id="px-dlg-input" ${input.attrs || ''}>`}
          <p class="err" id="px-dlg-err"></p></div>` : ''}
        <div class="actions"><button class="btn btn-text" value="cancel" formnovalidate>Annuler</button>
        <button class="btn push ${danger ? 'btn-danger' : 'btn-solid'}" value="ok" id="px-dlg-ok">${esc(ok)}</button></div></form>`;
      document.body.appendChild(d);
      const field = d.querySelector('#px-dlg-input');
      d.querySelector('#px-dlg-ok').addEventListener('click', e => {
        if (input && input.validate){ const m = input.validate(field.value); d.querySelector('#px-dlg-err').textContent = m;
          field.setAttribute('aria-invalid', m ? 'true' : 'false'); if (m){ e.preventDefault(); field.focus(); } }
      });
      d.addEventListener('close', () => { resolve(d.returnValue === 'ok' ? (input ? field.value : true) : false); d.remove(); });
      d.showModal(); (field || d.querySelector('#px-dlg-ok')).focus();
    });
  }

  /* ---------- Graphiques SVG ---------- */
  const niceMax = v => { if (v <= 0) return 1; const p = Math.pow(10, Math.floor(Math.log10(v))); const n = v / p; return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 5 ? 5 : 10) * p; };
  function axes(max, W, H, pad){
    let g = '';
    for (let i = 0; i <= 4; i++){ const y = pad.t + (H - pad.t - pad.b) * (1 - i/4);
      g += `<line x1="${pad.l}" x2="${W - pad.r}" y1="${y}" y2="${y}" stroke="rgba(91,15,176,.1)"/><text x="${pad.l - 6}" y="${y + 4}" text-anchor="end">${fmtNum(Math.round(max * i/4))}</text>`; }
    return g;
  }
  function barChart(el, { labels, series, stacked = false, height = 240, title = 'Graphique' }){
    const W = 640, H = height, pad = { l:40, r:8, t:10, b:28 };
    const tops = labels.map((_,i) => stacked ? series.reduce((s,se) => s + se.values[i], 0) : Math.max(...series.map(se => se.values[i])));
    const max = niceMax(Math.max(...tops, 1));
    const band = (W - pad.l - pad.r) / labels.length, ih = H - pad.t - pad.b;
    const bw = stacked ? band * .55 : (band * .7) / series.length;
    let bars = '';
    labels.forEach((lab,i) => {
      let acc = 0;
      series.forEach((se,j) => {
        const h = se.values[i] / max * ih;
        const x = pad.l + band * i + (stacked ? band * .225 : band * .15 + bw * j);
        const y = stacked ? pad.t + ih - acc - h : pad.t + ih - h;
        bars += `<rect x="${x.toFixed(1)}" y="${y.toFixed(1)}" width="${bw.toFixed(1)}" height="${Math.max(h,0).toFixed(1)}" rx="3" fill="${se.color}"><title>${esc(lab)} · ${esc(se.name)} : ${fmtNum(se.values[i])}</title></rect>`;
        if (stacked) acc += h;
      });
      bars += `<text x="${(pad.l + band * i + band/2).toFixed(1)}" y="${H - 8}" text-anchor="middle">${esc(lab)}</text>`;
    });
    el.innerHTML = `<svg class="chart" viewBox="0 0 ${W} ${H}" role="img" aria-label="${esc(title)}">${axes(max,W,H,pad)}${bars}</svg>` +
      (series.length > 1 ? `<div class="legend">${series.map(s => `<span><i style="background:${s.color}"></i>${esc(s.name)}</span>`).join('')}</div>` : '');
  }
  function lineChart(el, { labels, values, height = 240, title = 'Graphique', every = 5 }){
    const W = 640, H = height, pad = { l:40, r:10, t:10, b:28 };
    const max = niceMax(Math.max(...values, 1)), ih = H - pad.t - pad.b, iw = W - pad.l - pad.r;
    const pts = values.map((v,i) => [pad.l + iw * i / (values.length - 1), pad.t + ih - v / max * ih]);
    const line = pts.map((p,i) => (i ? 'L' : 'M') + p[0].toFixed(1) + ' ' + p[1].toFixed(1)).join(' ');
    const area = `${line} L${pts[pts.length-1][0].toFixed(1)} ${pad.t + ih} L${pad.l} ${pad.t + ih} Z`;
    const xl = labels.map((l,i) => i % every === 0 ? `<text x="${pts[i][0].toFixed(1)}" y="${H - 8}" text-anchor="middle">${esc(l)}</text>` : '').join('');
    el.innerHTML = `<svg class="chart" viewBox="0 0 ${W} ${H}" role="img" aria-label="${esc(title)}">
      <defs><linearGradient id="pxarea" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#7B2FF2" stop-opacity=".35"/><stop offset="1" stop-color="#7B2FF2" stop-opacity="0"/></linearGradient></defs>
      ${axes(max,W,H,pad)}<path d="${area}" fill="url(#pxarea)"/><path d="${line}" fill="none" stroke="#5B0FB0" stroke-width="2.5" stroke-linejoin="round"/>${xl}</svg>`;
  }

  /* ---------- Export CSV ---------- */
  function downloadCSV(filename, rows){
    const csv = rows.map(r => r.map(c => `"${String(c ?? '').replace(/"/g,'""')}"`).join(';')).join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob(['\ufeff' + csv], { type:'text/csv;charset=utf-8' }));
    a.download = filename; document.body.appendChild(a); a.click(); a.remove();
  }

  /* ---------- Mot de passe : afficher / masquer ---------- */
  function bindPasswordToggles(){
    document.querySelectorAll('[data-pw-toggle]').forEach(b => b.addEventListener('click', () => {
      const pw = $(b.dataset.pwToggle), show = pw.type === 'password';
      pw.type = show ? 'text' : 'password'; b.textContent = show ? 'Masquer' : 'Afficher'; b.setAttribute('aria-pressed', show); pw.focus();
    }));
  }

  return { $, esc, digits, norm, fmtDate, fmtDateLong, fmtTime, fmtDateTime, fmtXAF, fmtNum, fmtPhone, phoneError, daysSince, initials,
    CENTERS, STATUS, pill, FEE_XAF, ICONS, LOGO, shell, toast, confirmDialog, barChart, lineChart, downloadCSV, bindPasswordToggles };
})();

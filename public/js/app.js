const API_BASE='/api';
const STATE={csrfToken:'',user:null,analyses:[],favorites:[],active:null};

const pageIs=(n)=>{const p=location.pathname;return p.endsWith('/'+n)||p.endsWith(n)};
const esc=(v)=>String(v??'').replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;').replaceAll('"','&quot;').replaceAll("'",'&#39;');
const j=(t)=>{try{return JSON.parse(t)}catch{return null}};
const d=(v)=>{if(!v)return'-';const x=new Date(v);return Number.isNaN(x.getTime())?String(v):x.toLocaleDateString()};

function flash(msg,type='info',id='flash-message'){const el=document.getElementById(id);if(!el){if(msg)console.log(msg);return;}if(!msg){el.className='hidden';el.textContent='';return;}let c='flash';if(type==='success')c+=' flash-success';if(type==='error')c+=' flash-error';el.className=c;el.textContent=msg;}

async function api(path,{method='GET',data=null,formData=null,csrf=false}={}){
  const headers={};let body=null;
  if(formData) body=formData; else if(data!==null){headers['Content-Type']='application/json';body=JSON.stringify(data);}
  if(csrf&&STATE.csrfToken) headers['X-CSRF-Token']=STATE.csrfToken;
  const r=await fetch(`${API_BASE}/${path}`,{method,headers,body,credentials:'same-origin'});
  const t=await r.text();const p=j(t);
  if(p&&typeof p==='object') return {ok:r.ok,status:r.status,...p};
  return {ok:r.ok,status:r.status,error:t||`Request failed (${r.status})`};
}

async function csrf(force=false){
  if(STATE.csrfToken&&!force) return STATE.csrfToken;
  const r=await api('csrf.php');
  if(r.success&&r.data?.token) STATE.csrfToken=r.data.token;
  return STATE.csrfToken;
}

async function withCsrf(path,opts={}){
  await csrf();
  const a=await api(path,{...opts,csrf:true});
  if(a.status===403&&String(a.error||'').toLowerCase().includes('csrf')){await csrf(true);return api(path,{...opts,csrf:true});}
  return a;
}

const norm=(item,f=null)=>{
  let parsed={};
  if(typeof item?.analysis_result==='string') parsed=j(item.analysis_result)||{};
  else if(item?.analysis_result&&typeof item.analysis_result==='object') parsed=item.analysis_result;
  else if(item?.analysis&&typeof item.analysis==='object') parsed=item.analysis;
  const fav=f!==null?f:Number(item?.is_favorite??0)===1;
  return {...item,id:Number(item?.id??0),parsed,confidence_score:Number(item?.confidence_score??parsed.confidence_score??0),market_sentiment_score:Number(item?.market_sentiment_score??parsed.sentiment_score??50),is_favorite:Boolean(fav)};
};

function setPanel(name){
  document.querySelectorAll('[data-panel]').forEach((el)=>el.classList.toggle('panel-hidden',el.getAttribute('data-panel')!==name));
  document.querySelectorAll('[data-panel-target]').forEach((b)=>b.classList.toggle('is-active',b.getAttribute('data-panel-target')===name));
}

function header(){
  if(!STATE.user) return;
  const n=document.getElementById('user-name'); if(n) n.textContent=STATE.user.full_name||STATE.user.email;
  const e=document.getElementById('user-email'); if(e) e.textContent=STATE.user.email;
  const p=document.getElementById('user-plan-badge'); if(p) p.textContent=STATE.user.subscription_plan||'free';
  const a=document.getElementById('admin-link'); if(a) a.classList.toggle('hidden',STATE.user.role!=='admin');
}

function stats({total_analyses,favorite_analyses,avg_confidence}={}){
  const t=document.getElementById('stat-total-analyses'); if(t) t.textContent=String(Number(total_analyses??STATE.analyses.length??0));
  const f=document.getElementById('stat-favorites'); if(f) f.textContent=String(Number(favorite_analyses??STATE.favorites.length??0));
  const c=document.getElementById('stat-confidence'); if(c) c.textContent=`${(Number(avg_confidence??0)*100).toFixed(0)}%`;
}
function sideHistory(){
  const c=document.getElementById('side-history-list'); if(!c) return; c.innerHTML='';
  if(!STATE.analyses.length){c.innerHTML='<div class="text-sm text-slate-500">No analyses yet.</div>';return;}
  STATE.analyses.slice(0,8).forEach((x)=>{const b=document.createElement('button');b.type='button';b.className='w-full text-left data-card p-3 hover:border-cyan-400/60';b.innerHTML=`<div class="text-xs text-slate-400">${esc(d(x.created_at))}</div><div class="text-sm text-slate-200 mt-1 line-clamp-1">${esc(x.parsed?.summary||'Chart analysis')}</div>`;b.onclick=()=>{setPanel('analyze');setCurrent(x,true)};c.appendChild(b);});
}

function card(x){
  const a=document.createElement('article');a.className='data-card';const conf=Number(x.confidence_score||0);
  a.innerHTML=`<div class="flex items-start justify-between gap-2"><div><p class="text-xs uppercase tracking-[0.15em] text-slate-400">${esc(d(x.created_at))}</p><h4 class="font-semibold mt-1">Analysis #${esc(x.id)}</h4></div><span class="badge">${(conf*100).toFixed(0)}% conf</span></div><p class="text-sm text-slate-300 mt-2 line-clamp-1">${esc(x.parsed?.summary||'No summary available')}</p><div class="text-xs text-slate-400 mt-2">Sentiment: ${esc(x.parsed?.sentiment_score??x.market_sentiment_score??50)}</div><div class="flex flex-wrap gap-2 mt-3"><button type="button" class="btn-outline" data-v>View</button><button type="button" class="btn-outline" data-f>${x.is_favorite?'Unfavorite':'Favorite'}</button></div>`;
  a.querySelector('[data-v]')?.addEventListener('click',()=>{setPanel('analyze');setCurrent(x,true)});
  a.querySelector('[data-f]')?.addEventListener('click',()=>toggleFavorite(x.id));
  return a;
}

function listRender(){
  const h=document.getElementById('history-list'); if(h){h.innerHTML=''; if(!STATE.analyses.length) h.innerHTML='<div class="text-sm text-slate-500">No analyses yet.</div>'; else STATE.analyses.forEach((x)=>h.appendChild(card(x)));}
  const f=document.getElementById('favorites-list'); if(f){f.innerHTML=''; if(!STATE.favorites.length) f.innerHTML='<div class="text-sm text-slate-500">No favorites yet.</div>'; else STATE.favorites.forEach((x)=>f.appendChild(card(x)));}
  sideHistory();
}

function annotations(a){const o=document.getElementById('annotations-overlay');if(!o)return;o.innerHTML='';(Array.isArray(a?.visual_annotations)?a.visual_annotations:[]).forEach((m)=>{const x=Number(m?.x),y=Number(m?.y);if(!Number.isFinite(x)||!Number.isFinite(y))return;const d=document.createElement('div');d.className='analysis-marker';d.style.left=`${Math.max(0,Math.min(100,x/10))}%`;d.style.top=`${Math.max(0,Math.min(100,y/10))}%`;d.title=String(m?.label||'Marker');o.appendChild(d);});}

function analysisHtml(a){
  const sent=Number(a?.sentiment_score??50),conf=Number(a?.confidence_score??0),trend=a?.trend_lines||'No trend details available',sum=a?.summary||'No summary generated',idea=a?.trade_idea||sum,risk=a?.risk_assessment||'Moderate';
  const p=Array.isArray(a?.candlestick_patterns)?a.candlestick_patterns:[a?.candlestick_patterns].filter(Boolean);
  const sup=Array.isArray(a?.levels?.support)?a.levels.support.join(', '):'N/A',res=Array.isArray(a?.levels?.resistance)?a.levels.resistance.join(', '):'N/A';
  return `<div class="space-y-4"><div><div class="flex justify-between text-sm text-slate-300 mb-1"><span>Market sentiment</span><span>${sent}</span></div><div class="h-2 rounded-full bg-slate-700 overflow-hidden"><div class="h-full bg-cyan-400" style="width:${Math.max(0,Math.min(100,sent))}%"></div></div></div><div class="grid grid-cols-2 gap-3"><div class="stats-chip"><div class="text-xs text-slate-400 uppercase tracking-wider">Confidence</div><div class="text-xl font-bold text-cyan-200 mt-1">${(conf*100).toFixed(1)}%</div></div><div class="stats-chip"><div class="text-xs text-slate-400 uppercase tracking-wider">Risk</div><div class="text-xl font-bold text-cyan-200 mt-1">${esc(risk)}</div></div></div><div><h4 class="text-sm uppercase tracking-[0.15em] text-slate-400 mb-1">Trend</h4><p class="text-sm text-slate-200">${esc(trend)}</p></div><div><h4 class="text-sm uppercase tracking-[0.15em] text-slate-400 mb-1">Patterns</h4><div class="flex flex-wrap gap-1.5">${p.length?p.map((z)=>`<span class="badge">${esc(z)}</span>`).join(''):'<span class="text-sm text-slate-500">No pattern details.</span>'}</div></div><div class="grid grid-cols-2 gap-3"><div><h4 class="text-xs uppercase tracking-[0.15em] text-slate-400 mb-1">Support</h4><p class="text-sm text-slate-200">${esc(sup)}</p></div><div><h4 class="text-xs uppercase tracking-[0.15em] text-slate-400 mb-1">Resistance</h4><p class="text-sm text-slate-200">${esc(res)}</p></div></div><div class="data-card"><h4 class="text-sm uppercase tracking-[0.15em] text-slate-400 mb-1">Trade idea</h4><p class="text-sm text-slate-100">${esc(idea)}</p></div></div>`;
}

function pdf(a,item){
  const b=document.getElementById('download-pdf');if(!b)return;
  b.onclick=()=>{if(!window.jspdf?.jsPDF){flash('PDF library is not available.','error');return;}const{jsPDF}=window.jspdf;const d1=new jsPDF();d1.setFontSize(20);d1.text('ChartReader.io Analysis Report',20,20);d1.setFontSize(11);d1.text(`Date: ${new Date().toLocaleString()}`,20,30);d1.text(`Analysis ID: ${item?.id??'-'}`,20,37);d1.text(`Sentiment: ${a?.sentiment_score??'-'}`,20,44);d1.text(`Confidence: ${((Number(a?.confidence_score??0))*100).toFixed(1)}%`,20,51);d1.setFontSize(13);d1.text('Trend',20,63);d1.setFontSize(10);d1.text(d1.splitTextToSize(String(a?.trend_lines||'N/A'),170),20,70);d1.setFontSize(13);d1.text('Summary',20,100);d1.setFontSize(10);d1.text(d1.splitTextToSize(String(a?.summary||'N/A'),170),20,107);d1.save(`chart-analysis-${item?.id??'report'}.pdf`);};
}

function favBtn(){const b=document.getElementById('favorite-current-btn');if(!b)return;if(!STATE.active?.id){b.classList.add('hidden');b.removeAttribute('data-id');return;}b.classList.remove('hidden');b.dataset.id=String(STATE.active.id);b.textContent=STATE.active.is_favorite?'Unfavorite':'Add Favorite';}

function setCurrent(item,scroll=false){if(!item)return;STATE.active=item;const r=document.getElementById('results-area');if(r)r.classList.remove('hidden');const p=document.getElementById('chart-preview');if(p&&item.image_path)p.src=`/uploads/${encodeURIComponent(item.image_path)}`;const c=document.getElementById('analysis-content');if(c)c.innerHTML=analysisHtml(item.parsed||{});annotations(item.parsed||{});pdf(item.parsed||{},item);favBtn();if(scroll&&r)r.scrollIntoView({behavior:'smooth',block:'start'});}

function drop(dropZone,fileInput,fn){if(!dropZone||!fileInput)return;dropZone.addEventListener('click',()=>fileInput.click());dropZone.addEventListener('dragover',(e)=>{e.preventDefault();dropZone.classList.add('is-dragover')});dropZone.addEventListener('dragleave',()=>dropZone.classList.remove('is-dragover'));dropZone.addEventListener('drop',(e)=>{e.preventDefault();dropZone.classList.remove('is-dragover');const f=e.dataTransfer?.files?.[0];if(f)fn(f)});fileInput.addEventListener('change',(e)=>{const f=e.target.files?.[0];if(f)fn(f)});}

function compress(file){return new Promise((resolve)=>{const r=new FileReader();r.readAsDataURL(file);r.onload=(e)=>{const i=new Image();i.src=String(e.target?.result||'');i.onload=()=>{const c=document.createElement('canvas');let w=i.width,h=i.height,m=1400;if(w>h&&w>m){h*=m/w;w=m}else if(h>m){w*=m/h;h=m}c.width=Math.round(w);c.height=Math.round(h);const x=c.getContext('2d');if(!x){resolve(file);return;}x.drawImage(i,0,0,c.width,c.height);c.toBlob((b)=>{if(!b){resolve(file);return;}resolve(new File([b],file.name,{type:'image/jpeg'}));},'image/jpeg',0.84);};i.onerror=()=>resolve(file);};r.onerror=()=>resolve(file);});}

async function upload(file){
  if(!file) return; const ok=['image/jpeg','image/png','image/webp']; if(!ok.includes(file.type)){flash('Please upload JPG, PNG, or WEBP image.','error');return;}
  const r=document.getElementById('results-area'); if(r) r.classList.remove('hidden'); const p=document.getElementById('chart-preview'); if(p) p.src=URL.createObjectURL(file); const c=document.getElementById('analysis-content'); if(c)c.innerHTML='<div class="text-sm text-slate-400">Running AI analysis...</div>';
  const processed=await compress(file);const fd=new FormData();fd.append('chart',processed);const res=await withCsrf('analyze.php',{method:'POST',formData:fd});
  if(!res.success){flash(res.error||'Analysis failed.','error');return;}
  const a=res.data?.analysis||{};const item=norm({id:res.data?.id,image_path:res.data?.image,analysis_result:JSON.stringify(a),confidence_score:a?.confidence_score??0,market_sentiment_score:a?.sentiment_score??50,created_at:new Date().toISOString(),is_favorite:0});
  STATE.analyses.unshift(item);listRender();setCurrent(item,true);stats({total_analyses:STATE.analyses.length,favorite_analyses:STATE.favorites.length,avg_confidence:STATE.analyses.reduce((s,x)=>s+Number(x.confidence_score||0),0)/STATE.analyses.length});flash('Analysis completed successfully.','success');
  await loadAnalyses();
}
async function loadProfile(){
  const r=await api('profile.php'); if(!r.success) return;
  STATE.user=r.data?.user||STATE.user; header(); stats(r.data?.stats||{});
  const n=document.getElementById('profile-form-name'); if(n) n.value=STATE.user?.full_name||'';
  const e=document.getElementById('profile-email'); if(e) e.value=STATE.user?.email||'';
  const c=document.getElementById('profile-created'); if(c) c.value=d(STATE.user?.created_at);
  const s=document.getElementById('subscription-status'); if(s) s.value=STATE.user?.subscription_status||'inactive';
  const p=document.getElementById('subscription-plan'); if(p) p.value=STATE.user?.subscription_plan||'free';
}

async function loadAnalyses(){
  const r=await api('analyze.php?limit=200'); if(!r.success){STATE.analyses=[];listRender();return;}
  STATE.analyses=(Array.isArray(r.data)?r.data:[]).map((x)=>norm(x));
  listRender();
}

async function loadFavorites(){
  const r=await api('favorites.php'); if(!r.success){STATE.favorites=[];listRender();return;}
  STATE.favorites=(Array.isArray(r.data?.items)?r.data.items:[]).map((x)=>norm(x,true));
  const ids=new Set(STATE.favorites.map((x)=>x.id));
  STATE.analyses=STATE.analyses.map((x)=>({...x,is_favorite:ids.has(x.id)}));
  listRender();favBtn();stats({favorite_analyses:STATE.favorites.length});
}

async function toggleFavorite(id){
  const r=await withCsrf('favorites.php',{method:'POST',data:{analysisId:id,action:'toggle'}});
  if(!r.success){flash(r.error||'Unable to update favorite.','error');return;}
  const fav=Boolean(r.data?.is_favorite);
  STATE.analyses=STATE.analyses.map((x)=>x.id===id?({...x,is_favorite:fav}):x);
  if(STATE.active?.id===id) STATE.active={...STATE.active,is_favorite:fav};
  const it=STATE.analyses.find((x)=>x.id===id);
  if(fav&&it&&!STATE.favorites.some((x)=>x.id===id)) STATE.favorites.unshift({...it,is_favorite:true});
  if(!fav) STATE.favorites=STATE.favorites.filter((x)=>x.id!==id);
  listRender();favBtn();stats({favorite_analyses:STATE.favorites.length});flash(fav?'Added to favorites.':'Removed from favorites.','success');
}

function bindDashboard(){
  document.querySelectorAll('[data-panel-target]').forEach((b)=>b.addEventListener('click',()=>{const n=b.getAttribute('data-panel-target');if(n)setPanel(n);}));
  drop(document.getElementById('drop-zone-dashboard'),document.getElementById('file-input-dashboard'),upload);

  document.getElementById('logout-btn')?.addEventListener('click',async()=>{const r=await withCsrf('auth.php?action=logout',{method:'POST'});if(!r.success){flash(r.error||'Logout failed.','error');return;}location.href='login.html';});
  document.getElementById('favorite-current-btn')?.addEventListener('click',async(e)=>{const id=Number(e.currentTarget.dataset.id||0);if(id)await toggleFavorite(id);});

  document.getElementById('profile-form')?.addEventListener('submit',async(e)=>{e.preventDefault();const fullName=document.getElementById('profile-form-name')?.value?.trim()||'';const r=await withCsrf('profile.php',{method:'POST',data:{action:'update-profile',fullName}});if(!r.success){flash(r.error||'Could not update profile.','error');return;}STATE.user=r.data?.user||STATE.user;header();flash('Profile updated.','success');});

  document.getElementById('password-form')?.addEventListener('submit',async(e)=>{e.preventDefault();const oldPassword=document.getElementById('old-password')?.value||'';const newPassword=document.getElementById('new-password')?.value||'';const confirmPassword=document.getElementById('confirm-password')?.value||'';const r=await withCsrf('profile.php',{method:'POST',data:{action:'change-password',oldPassword,newPassword,confirmPassword}});if(!r.success){flash(r.error||'Could not change password.','error');return;}e.currentTarget.reset();flash('Password updated successfully.','success');});

  document.getElementById('start-upgrade-btn')?.addEventListener('click',async()=>{const r=await withCsrf('stripe.php?action=create-checkout',{method:'POST',data:{}});if(!r.success||!r.data?.url){flash(r.error||'Failed to start checkout session.','error');return;}location.href=r.data.url;});
  document.getElementById('manage-billing-btn')?.addEventListener('click',async()=>{const r=await withCsrf('stripe.php?action=portal',{method:'POST',data:{}});if(!r.success||!r.data?.url){flash(r.error||'Failed to open billing portal.','error');return;}location.href=r.data.url;});
}

function dashFlags(){const q=new URLSearchParams(location.search);if(q.get('success')==='true')flash('Payment completed. Subscription status will sync shortly.','success');if(q.get('cancel')==='true')flash('Checkout canceled.','error');if(q.get('quick_upload')==='1'){setPanel('analyze');flash('Upload your chart to begin analysis.','success');}}

async function initDashboard(){
  const me=await api('auth.php?action=me'); if(!me.success){location.href='login.html';return;}
  STATE.user=me.data?.user||null; header(); bindDashboard(); dashFlags();
  await loadProfile();
  await loadAnalyses();
  await loadFavorites();
}

async function initAuth(){
  const login=document.getElementById('login-form'); const register=document.getElementById('register-form');
  if(!login&&!register) return;
  const me=await api('auth.php?action=me'); if(me.success){location.href='dashboard.html';return;}
  login?.addEventListener('submit',async(e)=>{e.preventDefault();const email=document.getElementById('login-email')?.value?.trim()||'';const password=document.getElementById('login-password')?.value||'';const r=await withCsrf('auth.php?action=login',{method:'POST',data:{email,password}});if(!r.success){flash(r.error||'Login failed.','error','auth-message');return;}location.href='dashboard.html';});
  register?.addEventListener('submit',async(e)=>{e.preventDefault();const fullName=document.getElementById('register-name')?.value?.trim()||'';const email=document.getElementById('register-email')?.value?.trim()||'';const password=document.getElementById('register-password')?.value||'';const r=await withCsrf('auth.php?action=register',{method:'POST',data:{fullName,email,password}});if(!r.success){flash(r.error||'Registration failed.','error','auth-message');return;}location.href='dashboard.html';});
}

async function initLanding(){
  const dz=document.getElementById('drop-zone-home');const fi=document.getElementById('file-input-home');if(!dz||!fi)return;
  drop(dz,fi,async(file)=>{if(!file)return;sessionStorage.setItem('chartreader_quick_upload_name',file.name);const me=await api('auth.php?action=me');location.href=me.success?'dashboard.html?quick_upload=1':'register.html?quick_upload=1';});
}
function adminRecent(items){
  const t=document.getElementById('admin-recent-analyses'); if(!t)return; t.innerHTML='';
  if(!items.length){t.innerHTML='<tr><td colspan="3" class="text-slate-500">No analysis records.</td></tr>';return;}
  items.forEach((r)=>{const tr=document.createElement('tr');tr.innerHTML=`<td>${esc(r.email||'-')}</td><td>${esc(d(r.created_at))}</td><td>${esc((Number(r.confidence_score||0)*100).toFixed(0))}%</td>`;t.appendChild(tr);});
}

function adminUsers(items){
  const t=document.getElementById('admin-users'); if(!t)return; t.innerHTML='';
  if(!items.length){t.innerHTML='<tr><td colspan="3" class="text-slate-500">No users found.</td></tr>';return;}
  items.forEach((r)=>{const tr=document.createElement('tr');tr.innerHTML=`<td>${esc(r.email)}</td><td>${esc(r.role)}</td><td>${esc(r.subscription_plan||'free')}</td>`;t.appendChild(tr);});
}

function adminPayments(items){
  const t=document.getElementById('admin-payments'); if(!t)return; t.innerHTML='';
  if(!items.length){t.innerHTML='<tr><td colspan="5" class="text-slate-500">No payments found.</td></tr>';return;}
  items.forEach((r)=>{const amt=Number(r.amount||0)/100;const tr=document.createElement('tr');tr.innerHTML=`<td>${esc(r.email)}</td><td>$${amt.toFixed(2)}</td><td>${esc((r.currency||'usd').toUpperCase())}</td><td>${esc(r.status||'-')}</td><td>${esc(d(r.created_at))}</td>`;t.appendChild(tr);});
}

async function initAdmin(){
  const me=await api('auth.php?action=me'); if(!me.success){location.href='login.html';return;}
  const s=await api('admin.php?action=stats');
  if(!s.success){flash(s.error||'Failed to load admin stats.','error');if(s.status===401||s.status===403)location.href='dashboard.html';return;}
  const x=s.data||{};
  const u=document.getElementById('total-users'); if(u)u.textContent=String(x.total_users??0);
  const r=document.getElementById('monthly-revenue'); if(r)r.textContent=`$${Number(x.monthly_revenue??0).toFixed(0)}`;
  const a=document.getElementById('total-analyses'); if(a)a.textContent=String(x.total_analyses??0);
  const n=document.getElementById('active-subscriptions'); if(n)n.textContent=String(x.active_subscriptions??0);
  adminRecent(Array.isArray(x.recent_analyses)?x.recent_analyses:[]);
  const [us,pay]=await Promise.all([api('admin.php?action=users'),api('admin.php?action=payments')]);
  adminUsers(Array.isArray(us.data?.items)?us.data.items:[]);
  adminPayments(Array.isArray(pay.data?.items)?pay.data.items:[]);
}

function sw(){if('serviceWorker' in navigator)navigator.serviceWorker.register('/sw.js').catch(()=>{});}

document.addEventListener('DOMContentLoaded',async()=>{
  await csrf();
  await initLanding();
  await initAuth();
  if(pageIs('dashboard.html')) await initDashboard();
  if(pageIs('admin.html')) await initAdmin();
  sw();
});

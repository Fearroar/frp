'use strict';
const $=s=>document.querySelector(s), root=$('#root');
console.info('[MINGCHI-WMS] v1.3 safe-delete patch loaded');
const S={csrf:'',user:null,conf:null,route:'dashboard',tab:'brand',rows:[],page:1,total:0,search:'',tag:'',warehouse:'',owner:'',grade:'',dateFrom:new Date().toISOString().slice(0,7)+'-01',dateTo:new Date().toISOString().slice(0,10),report:null,lookups:{},modal:null,quoteFees:[],quoteResult:null,editData:null,csvData:null,csvMap:{},busy:false,showArchivedProducts:false,showArchivedByModule:{lots:false,customers:false,leads:false}};
const PERMS={dashboard:'dashboard.view',products:'products.view',lots:'lots.view',movements:'movements.view',customers:'customers.view',leads:'leads.view',orders:'orders.view',expenses:'finance.view',quotes:'quotes.use',parties:'lots.view',warehouses:'lots.view',users:'users.manage',settings:'settings.manage',audit:'settings.manage'};
const EDIT={products:'products.edit',lots:'lots.edit',customers:'customers.edit',leads:'leads.edit',orders:'orders.edit',expenses:'finance.edit',parties:'lots.edit',warehouses:'lots.edit',users:'users.manage',settings:'settings.manage',audit:'settings.manage'};
const ICONS={dashboard:'▣',products:'⬡',lots:'▤',movements:'⇄',customers:'♧',leads:'◎',orders:'▧',expenses:'¥',quotes:'＄',parties:'▥',warehouses:'⌂',users:'♙',settings:'⚙',audit:'◷'};
const TIPS={dashboard:'按人民币查看库存、商机转化与公司经营盈亏',products:'FRP产品按规格、颜色和材质分别建档',lots:'区分产品批次、质量等级、货权与实际仓库',movements:'出入库流水独立留痕，记录对应客户和订单',customers:'管理客户资料、业务负责人和客户标签',leads:'记录来源、预计商机价值、状态和潜在利润',orders:'管理订单销售额、产品成本、直接费用与回款',expenses:'公司经营费用单独登记，不与订单直接成本重复',quotes:'独立即时报价工具，人民币成本与美元报价灵活换算',parties:'公司工厂、合作伙伴和货权所有者',warehouses:'管理不同工厂及合作单位的存放位置',users:'创建管理员、仓库及业务员工账号，逐项授权',settings:'管理企业品牌、字段、栏目、费用、选项和汇率',audit:'查看登录、配置、库存及财务操作审计记录'};
const esc=v=>String(v??'').replace(/[&<>"']/g,x=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[x]));
const fmt=(v,n=2)=>Number(v||0).toLocaleString('zh-CN',{minimumFractionDigits:n,maximumFractionDigits:n});
const cny=v=>Number(v)<0?'−¥'+fmt(Math.abs(Number(v))):'¥'+fmt(v);
const permitted=p=>Boolean(S.user&&(S.user.role==='superadmin'||(S.user.permissions||[]).includes(p)));
const canNav=m=>permitted(PERMS[m]);
const opt=(value,label,selected)=>`<option value="${esc(value)}" ${String(value)===String(selected??'')?'selected':''}>${esc(label)}</option>`;
const btn=(text,action,id='',className='')=>`<button type="button" class="btn ${className}" data-act="${esc(action)}" data-id="${esc(id)}">${esc(text)}</button>`;
const tag=x=>`<span class="tag">${esc(x)}</span>`;
const alertBox=(text,kind='')=>`<div class="notice ${kind}">${esc(text)}</div>`;
const stat=(label,value,foot='',icon='▣')=>`<div class="stat"><div class="stat-icon">${icon}</div><div class="stat-label">${esc(label)}</div><div class="stat-value ${String(value).startsWith('−')?'negative':''}">${esc(value)}</div><div class="stat-foot">${esc(foot)}</div></div>`;
function toast(message,isError=false){const old=$('.toast');if(old)old.remove();const node=document.createElement('div');node.className='toast'+(isError?' error':'');node.textContent=message;document.body.append(node);setTimeout(()=>node.remove(),4500)}
async function api(action,body=null,query={}){
 const url='/api.php?action='+encodeURIComponent(action)+Object.entries(query).map(([k,v])=>'&'+encodeURIComponent(k)+'='+encodeURIComponent(v)).join('');
 const options={credentials:'same-origin',headers:{Accept:'application/json'}};
 if(body!==null){options.method='POST';options.headers['Content-Type']='application/json';options.headers['X-CSRF-Token']=S.csrf;options.body=JSON.stringify(body)}
 const response=await fetch(url,options);
 let data;try{data=await response.json()}catch{throw Error('服务器返回的不是JSON，请检查PHP环境和错误日志')}
 if(!response.ok||!data.ok){if(response.status===401){S.user=null;renderLogin()}throw Error(data.error||'服务器请求失败')}
 return data;
}
async function sessionCheck(){try{const data=await api('session');S.csrf=data.csrf;if(data.authenticated)await boot();else renderLogin()}catch(e){root.innerHTML=`<div class="login-bg"><div class="login-card"><h2>无法连接后台</h2>${alertBox(e.message,'error')}<a href="/install.php">检查安装状态</a></div></div>`}}
async function boot(){const r=await api('bootstrap');S.conf=r;S.user=r.user;S.csrf=r.csrf;document.documentElement.style.setProperty('--primary',r.brand.accent||'#245fc9');const initial=canNav('dashboard')?'dashboard':(S.conf.nav.find(x=>canNav(x.module))?.module||null);if(initial)await navigate(initial);else root.innerHTML='<div class="login-bg"><div class="login-card"><h2>当前账号没有可访问的模块</h2><p>请联系管理员分配权限。</p></div></div>';if(S.user.must_change_password)showPassword(true)}
function renderLogin(){root.innerHTML=`<div class="login-bg"><form id="loginForm" class="login-card"><div class="brand-symbol" style="margin-bottom:18px">M</div><h1>明驰 FRP 库存管理系统</h1><p class="small" style="margin-top:7px">欢迎登录企业内部工作台</p><div class="field"><label>登录邮箱 / 手机号</label><input class="input" name="login" required autocomplete="username" autofocus placeholder="请输入管理员分配的账号"></div><div class="field"><label>登录密码</label><input class="input" type="password" name="password" required autocomplete="current-password" placeholder="请输入密码"></div><button class="btn primary" type="submit">安全登录 →</button><p class="small" style="margin:18px 0 0">首次登录员工账号，系统将要求修改初始密码。</p></form></div>`}
function moduleLabel(module){return S.conf?.nav.find(x=>x.module===module)?.label||module}
function activeFields(mod,forForm=false){return (S.conf?.fields||[]).filter(f=>f.module===mod&&+f.active===1&&(forForm?+f.visible===1:+f.list_visible===1)).sort((a,b)=>Number(a.position)-Number(b.position));}
function fieldDef(mod,key){return (S.conf?.fields||[]).find(f=>f.module===mod&&f.field_key===key)}
function refs(group){return S.conf.refs[group]||[]}
function fkName(mod,id){let a=S.lookups[mod]||[];const x=a.find(v=>String(v.id)===String(id));if(!x)return id?`#${id}`:'—';return x.name||x.title||x.lot_code||x.order_code||x.email||`#${id}`}
function navHtml(){return (S.conf.nav||[]).filter(x=>+x.visible===1&&canNav(x.module)).map(x=>`<button class="nav-btn ${S.route===x.module?'active':''}" data-nav="${esc(x.module)}"><span class="nav-symbol">${ICONS[x.module]||'▣'}</span>${esc(x.label)}</button>`).join('')}
function layout(inner){const brand=S.conf.brand||{};root.innerHTML=`<div class="shell"><aside id="sidebar" class="sidebar"><div class="brand"><span class="brand-symbol">${brand.logo_id?`<img alt="公司Logo" src="media.php?id=${Number(brand.logo_id)}">`:'M'}</span><div class="brand-text">${esc(brand.name||'明驰FRP库存系统')}<small>${esc(brand.subtitle||'INVENTORY OS')}</small></div></div><div class="nav-section">MANAGEMENT / 管理工作台</div><nav class="nav">${navHtml()}</nav><div class="side-bottom">● 系统已连接 · 人民币核算<br>员工操作由服务端验证权限</div></aside><main class="main"><div class="topbar"><div class="crumb"><button class="btn mini mobile-menu" data-act="mobileMenu">☰</button><button data-nav="dashboard">工作台</button><span>›</span><strong>${esc(moduleLabel(S.route))}</strong></div><div class="top-right"><span class="small">${esc(S.user.name)} · ${esc(S.user.role)}</span><span class="user-bubble">${esc(S.user.name.slice(0,1))}</span>${btn('修改密码','password','','mini')}${btn('退出','logout','','mini')}</div></div><div class="content">${inner}<p class="footer-tip">业务数据来源：公司数据库 · 所有金额人民币核算 · 经营盈亏为内部管理估算，非正式财务报表</p></div></main></div>`}
function pageHead(title,actions=''){return `<div class="pagehead"><div><h1>${esc(title)}</h1><p>${esc(TIPS[S.route]||'')}</p></div><div class="actions">${actions}</div></div>`}
function card(title,body,tools=''){return `<section class="card"><div class="card-head"><h3>${esc(title)}</h3><div class="actions">${tools}</div></div><div class="card-body">${body}</div></section>`}
async function loadLookups(){for(const mod of ['products','lots','customers','orders','leads','parties','warehouses','users']){if(!canNav(mod))continue;try{const r=await api('list',null,{entity:mod,limit:100,page:1});S.lookups[mod]=r.rows}catch{S.lookups[mod]=[]}}}
async function navigate(mod){if(!canNav(mod)){toast('当前账号没有该模块权限',true);return}S.route=mod;S.page=1;S.showArchivedProducts=false;S.search='';S.tag='';S.warehouse='';S.owner='';S.grade='';if(mod==='dashboard'){await loadLookups();await refreshReport()}else if(mod==='settings'){await renderSettings()}else if(mod==='quotes'){await loadLookups();S.lookups.quotes=(await api('list',null,{entity:'quotes',limit:20})).rows;S.quoteFees=[{label:'包装费',amount_cny:0},{label:'国内运费',amount_cny:0},{label:'报关港杂',amount_cny:0}];renderQuotes()}else if(mod==='users'){await loadTable()}else{await loadLookups();await loadTable()}}
async function loadTable(){try{const q={entity:S.route,page:S.page,limit:50,q:S.search};if(S.route==='products')q.archived=S.showArchivedProducts?'1':'0';if(['lots','customers','leads'].includes(S.route))q.archived=S.showArchivedByModule[S.route]?'1':'0';if(S.route==='customers'&&S.tag)q.tag=S.tag;if(S.route==='lots'&&S.warehouse)q.warehouse_id=S.warehouse;if(S.route==='lots'&&S.owner)q.owner_party_id=S.owner;if(S.route==='lots'&&S.grade)q.grade=S.grade;
 const r=await api('list',null,q);S.rows=r.rows;S.total=r.total;renderTable()}catch(e){toast(e.message,true)}}
async function refreshReport(){try{const r=await api('report',null,{start:S.dateFrom,end:S.dateTo});S.report=r;renderDashboard()}catch(e){toast(e.message,true)}}
function renderDashboard(){const k=S.report?.report||{},admin=permitted('finance.view');let cards=[];
 if(admin){const options=S.conf.dashboard_cards||[];const defaults=[['company_profit_cny','本期公司经营盈亏'],['order_profit_cny','本期订单贡献利润'],['revenue_cny','本期订单销售额'],['operating_expense_cny','本期经营费用'],['lead_value_cny','商机预计总额'],['converted_cny','商机已转化金额'],['open_cny','待转化商机金额'],['available_lots','有可用量的批次']];
 cards=options.length?options:defaults.map(v=>({key:v[0],label:v[1],visible:true}));}else cards=[{key:'products',label:'产品SKU数量',visible:true},{key:'available_lots',label:'可用库存批次',visible:true},{key:'leads_count',label:'本期询盘数量',visible:true},{key:'orders_count',label:'本期订单数量',visible:true}];
 const stats=cards.filter(x=>x.visible).map(x=>stat(x.label,['products','available_lots','low_stock_lots','leads_count','orders_count'].includes(x.key)?fmt(k[x.key]||0,0):cny(k[x.key]||0),x.key==='company_profit_cny'?'订单贡献利润－公司经营费用':'根据选择的日期范围','▣')).join('');
 let lower='';if(admin){const expenses=S.report.expense_breakdown||[],max=Math.max(1,...expenses.map(x=>Number(x.amount)));const bars=expenses.length?expenses.map(x=>`<div class="chart-row"><span>${esc(x.category)}</span><div class="bartrack"><div class="barfill" style="width:${Math.round(Number(x.amount)/max*100)}%"></div></div><b style="text-align:right">${cny(x.amount)}</b></div>`).join(''):'<div class="empty">本期尚未登记公司经营费用</div>';
 lower=`<div class="cols2">${card('经营盈亏说明',`<div class="quote-line"><span>订单销售收入</span><strong>${cny(k.revenue_cny)}</strong></div><div class="quote-line"><span>订单直接成本</span><strong>${cny(k.direct_cost_cny)}</strong></div><div class="quote-line"><span>订单贡献利润</span><strong>${cny(k.order_profit_cny)}</strong></div><div class="quote-line"><span>公司经营费用</span><strong>${cny(k.operating_expense_cny)}</strong></div><div class="quote-line"><span><b>期间经营盈亏估算</b></span><strong class="${Number(k.company_profit_cny)<0?'negative':'positive'}">${cny(k.company_profit_cny)}</strong></div><p class="small" style="margin:14px 0 0">${esc(S.report.note||'')}</p>`)}${card('经营费用分类',`<div class="chart-bars">${bars}</div>`)}</div>`;}
 layout(pageHead(moduleLabel('dashboard'),`<input id="dateFrom" class="input" type="date" value="${esc(S.dateFrom)}" style="width:155px"><input id="dateTo" class="input" type="date" value="${esc(S.dateTo)}" style="width:155px">${btn('更新看板','applyDates','','primary')}`)+`<div class="stats">${stats}</div>${lower}<div class="cols2">${card('库存工作入口',`<p class="small">快速进入库存批次查看货权、等级、可用数量和批次照片。</p>${btn('查询库存','go','lots','primary')} ${btn('登记出入库','openMove')}`)}${card('商机与订单',`<p class="small">询盘预计价值不会被当成实际销售额。关联订单后才能展示已转化金额。</p>${btn('查看商机','go','leads')} ${btn('订单盈亏','go','orders')}`)}</div>`)}
function fieldColumnValue(mod,key,r){const v=r[key]??(r.custom_json||{})[key],f=fieldDef(mod,key);if(key==='photo_id')return v?`<img class="thumb" data-image="${Number(v)}" src="media.php?id=${Number(v)}" alt="照片">`:'—';
 if(key==='tags_json')return (Array.isArray(v)?v:[]).map(tag).join(' ')||'—';
 if(['product_id','customer_id','owner_party_id','warehouse_id','owner_user_id','party_id','lead_id','lot_id'].includes(key)){const rel={product_id:'products',customer_id:'customers',owner_party_id:'parties',warehouse_id:'warehouses',owner_user_id:'users',party_id:'parties',lead_id:'leads',lot_id:'lots'}[key];return esc(fkName(rel,v))}
 if(['reference_price_cny','unit_cost_cny','estimated_cost_cny','amount_cny','expected_profit_cny','received_cny','profit_cny','revenue_cny'].includes(key))return cny(v);
 if(key==='amount'&&mod==='orders')return esc((r.currency||'')+' '+fmt(v));
 if(key==='expected_amount')return esc((r.currency||'')+' '+fmt(v));
 if(['status','grade','category','kind'].includes(key))return tag(v||'—');
 if(key==='quantity'||key==='reserved')return fmt(v,3);
 if(key==='custom_json')return '';return esc(v==null?'—':v);
}
const SEARCHABLE=new Set(['products','lots','customers','leads','orders','movements','quotes','parties','warehouses','users','audit']);
function rowActions(mod,r,edit){
 const id=r.id,active=Number(r.active)!==0;
 if(mod==='movements'){
   if(!permitted('movements.create'))return '—';
   if(r.reversed_movement_id)return tag('冲正记录');
   if(Number(r.is_reversed))return tag('已冲正');
   if(r.order_id)return '<span class="small">关联订单·人工核对</span>';
   if(!['in','out','adjust_in','adjust_out'].includes(r.movement_type))return '<span class="small">调拨记录·不可单独冲正</span>';
   return btn('冲正','movementReverse',id,'mini danger');
 }
 if(mod==='audit')return '';
 if(mod==='quotes')return btn('重新查看','quoteDetail',id,'mini');
 if(mod==='users')return r.role==='superadmin'?'':btn('编辑账号','userEdit',id,'mini');
 if(mod==='expenses')return btn('删除','costDelete',id,'mini danger');
 if(mod==='orders')return (edit?btn('编辑','edit',id,'mini'):'')+btn('财务详情','orderDetail',id,'mini');
 if(mod==='products')return !edit?'':active?btn('编辑','edit',id,'mini')+btn('删除','productDelete',id,'mini danger'):btn('恢复','productRestore',id,'mini primary');
 if(['lots','customers','leads'].includes(mod)){
   let html='';
   if(active){
     if(edit)html+=btn('编辑','edit',id,'mini')+btn('删除','entityArchive',`${mod}:${id}`,'mini danger');
     if(mod==='lots')html+=btn('出入库','moveFor',id,'mini primary')+(edit?btn('仓库调拨','transferFor',id,'mini'):'');
   }else if(edit)html=btn('恢复','entityRestore',`${mod}:${id}`,'mini primary');
   return html;
 }
 return edit?btn('编辑','edit',id,'mini'):'';
}
function renderTable(){const mod=S.route;const edit=permitted(EDIT[mod]);let columns=activeFields(mod,false);
 if(mod==='lots')columns=columns.filter(x=>x.field_key!=='unit_cost_cny'||permitted('finance.view'));
 if(mod==='products')columns=columns.filter(x=>x.field_key!=='reference_price_cny'||permitted('finance.view'));
 if(mod==='leads')columns=columns.filter(x=>x.field_key!=='estimated_cost_cny'||permitted('finance.view'));
 if(mod==='quotes')columns=columns.filter(x=>x.field_key!=='expected_profit_cny'||permitted('finance.view'));
 const extra=mod==='orders'&&permitted('finance.view')?['revenue_cny','profit_cny'].map(k=>({field_key:k,label:k==='revenue_cny'?'折合人民币收入':'订单贡献利润'})):[];
 const keys=[...columns,...extra];
 const h=keys.map(f=>`<th>${esc(f.label)}</th>`).join('');
 const t=(S.rows||[]).map(r=>`<tr>${keys.map(f=>`<td>${fieldColumnValue(mod,f.field_key,r)}</td>`).join('')}<td><div class="actions">${rowActions(mod,r,edit)}</div></td></tr>`).join('');
 let add='';if(mod==='expenses')add=permitted('finance.edit')?btn('+ 登记经营费用','newExpense','','primary'):'';
 else if(['products','lots','customers','leads','orders','parties','warehouses'].includes(mod)&&edit)add=btn('+ 新增'+moduleLabel(mod),'new','','primary');
 else if(mod==='users')add=btn('+ 创建员工账号','userNew','','primary');
 let filters='';if(mod==='customers')filters=`<select id="filterTag" class="select filter-select">${opt('','全部客户标签',S.tag)}${refs('customer_tags').map(x=>opt(x,x,S.tag)).join('')}</select>`;
 if(mod==='lots')filters=`<select id="filterWarehouse" class="select filter-select">${opt('','全部仓库',S.warehouse)}${(S.lookups.warehouses||[]).map(x=>opt(x.id,x.name,S.warehouse)).join('')}</select><select id="filterOwner" class="select filter-select">${opt('','全部货权归属',S.owner)}${(S.lookups.parties||[]).map(x=>opt(x.id,x.name,S.owner)).join('')}</select><select id="filterGrade" class="select filter-select">${opt('','全部质量等级',S.grade)}${refs('grades').map(x=>opt(x,x,S.grade)).join('')}</select>`;
 let controls=(SEARCHABLE.has(mod)?`<input id="tableSearch" class="input search" placeholder="搜索关键词 / 编号" value="${esc(S.search)}">${btn('搜索','tableSearchSubmit')}`:'')+filters;
 if(mod==='products')controls+=`<label class="checkline" style="display:inline-flex;align-items:center;gap:7px;white-space:nowrap"><input type="checkbox" id="showArchivedProducts" ${S.showArchivedProducts?'checked':''}> 查看已删除产品</label>`;
 if(['lots','customers','leads'].includes(mod))controls+=`<label class="checkline" style="display:inline-flex;align-items:center;gap:7px;white-space:nowrap"><input type="checkbox" id="showArchivedModule" ${S.showArchivedByModule[mod]?'checked':''}> 查看已删除记录</label>`;
 if(mod==='customers'&&permitted('customers.edit'))controls+=`${btn('CSV 导入','importCustomers')}${btn('下载CSV模板','csvTemplate')}`;
 if(permitted('export.data'))controls+=btn('全量导出CSV','exportCsv');
 const html=pageHead(moduleLabel(mod),add)+`<section class="card"><div class="card-body"><div class="toolbar">${controls}<span class="spacer"></span><span class="small">共 ${S.total} 条</span></div><div class="table-wrap"><table class="data-table"><thead><tr>${h}<th>操作</th></tr></thead><tbody>${t||`<tr><td colspan="${keys.length+1}" class="empty">暂无记录，使用右上角按钮新增</td></tr>`}</tbody></table></div><div class="table-foot"><span>第 ${S.page} 页 · 每页 50 条</span><div class="actions">${btn('上一页','pagePrev','','mini')}${btn('下一页','pageNext','','mini')}</div></div></div></section>`;
 layout(html);
}
function showModal(title,body,onSave=null,wide=false,locked=false){
 S.modal={title,onSave,locked};
 const old=$('#overlay');if(old)old.remove();
 const node=document.createElement('div');node.id='overlay';node.className='modal-overlay';
 node.innerHTML=`<section class="modal ${wide?'wide':''}" role="dialog" aria-modal="true" aria-label="${esc(title)}"><div class="modal-header"><h3>${esc(title)}</h3>${locked?'':`<button type="button" class="modal-close" data-act="closeModal" aria-label="关闭">×</button>`}</div><div class="modal-body">${body}</div><div class="modal-footer">${locked?'':btn('取消','closeModal')}${onSave?btn('确认保存','modalSave','','primary'):''}</div></section>`;
 document.body.append(node);
 node.addEventListener('click',e=>{if(e.target===node&&!S.modal?.locked)closeModal()});
}
function closeModal(){const el=$('#overlay');if(el)el.remove();S.modal=null;S.editData=null}
async function lookupsIfNeeded(){if(!S.lookups.products)await loadLookups()}
function relationOpts(key,selected){const mod={product_id:'products',customer_id:'customers',owner_party_id:'parties',warehouse_id:'warehouses',owner_user_id:'users',party_id:'parties',lead_id:'leads',lot_id:'lots'}[key];
 if(!mod)return '';const arr=S.lookups[mod]||[];return opt('','请选择',selected)+arr.map(r=>opt(r.id,r.name||r.title||r.lot_code||r.order_code,selected)).join('')}
function builtinReadOnly(mod,key,editing){return ['sku','customer_code','lead_code','quantity','reserved','created_at','quote_code','quote_total','expected_profit_cny'].includes(key)||(mod==='orders'&&key==='order_code'&&!editing)}
function inputFor(mod,f,r={}){
 const key=f.field_key,type=f.field_type,val=r[key]??'',custom=!+f.builtin;
 const value=custom?(r.custom_json||{})[key]??'':val;
 if(builtinReadOnly(mod,key,Boolean(r.id))){if(key==='order_code'&&!r.id)return '';if(key==='quantity'||key==='reserved')return `<div class="field"><label>${esc(f.label)} <small>出入库记录控制</small></label><input class="input" value="${esc(value)}" disabled></div>`;return r.id?`<div class="field"><label>${esc(f.label)}</label><input class="input" disabled value="${esc(value)}"></div>`:'';}
 const label=`<label class="${+f.required_field?'required':''}">${esc(f.label)}</label>`;
 const required=+f.required_field?'required':'';const attr=`data-field="${esc(key)}" data-custom="${custom?'1':'0'}"`;
 let control='';
 if(key==='photo_id'||(custom&&type==='image'))control=`<input type="file" class="input" accept="image/png,image/jpeg,image/webp" data-photo="${esc(key)}"><input type="hidden" ${attr} value="${esc(value)}">${value?`<img class="thumb" style="margin-top:8px" data-image="${Number(value)}" src="media.php?id=${Number(value)}">`:''}`;
 else if(type==='relation')control=`<select ${attr} class="select" ${required}>${relationOpts(key,value)}</select>`;
 else if(type==='tags')control=`<div class="tag-selector">${refs('customer_tags').map(t=>`<label class="checkline" style="display:inline-flex;margin:6px 12px 5px 0"><input type="checkbox" data-tagval="${esc(t)}" ${(Array.isArray(value)?value:[]).includes(t)?'checked':''}>${esc(t)}</label>`).join('')}</div>`;
 else if(type==='textarea')control=`<textarea ${attr} class="textarea" placeholder="${esc(f.placeholder)}" ${required}>${esc(value)}</textarea>`;
 else if(type==='select'){
 let choices=[];if(custom)choices=JSON.parse(f.options_json||'[]');
 else {const g={category:mod==='expenses'?(S.expenseKind||'operating_expenses'):'product_categories',unit:'units',grade:'grades',status:mod==='orders'?'order_status':'lead_status',source:'lead_sources',kind:'party_kinds',currency:'currencies',role:'user_roles'}[key];choices=g==='currencies'?['CNY','USD','EUR','AUD','CAD','GBP','SAR','PHP','BRL']:g==='user_roles'?['admin','warehouse','sales','employee']:refs(g||'');}
 control=`<select ${attr} class="select" ${required}>${opt('','请选择',value)}${choices.map(x=>opt(x,x,value)).join('')}</select>`;
 }else if(type==='checkbox')control=`<label class="checkline"><input type="checkbox" ${attr} ${value?'checked':''}> 是</label>`;
 else {const htmlType=type==='number'?'number':type==='date'?'date':type==='email'?'email':'text';control=`<input ${attr} class="input" type="${htmlType}" ${htmlType==='number'?'step="any"':''} placeholder="${esc(f.placeholder)}" value="${esc(value)}" ${required}>`;}
 return `<div class="field ${type==='textarea'?'full':''}">${label}${control}</div>`;
}
async function openRecord(mod,id=null){await lookupsIfNeeded();let data={};if(id){const r=await api('detail',null,{entity:mod,id});data=r.record;}S.editData=data;
 const fields=activeFields(mod,true).filter(f=>permitted('finance.view')||!['unit_cost_cny','reference_price_cny','estimated_cost_cny'].includes(f.field_key)).filter(f=>!(mod==='lots'&&['quantity','reserved'].includes(f.field_key)&&!id));
 const h=fields.map(f=>inputFor(mod,f,data)).join('');let hint='';
 if(mod==='lots')hint=alertBox('新建库存批次初始数量为0。保存后，通过「入库」建立第一笔库存流水。已有库存的批次不能直接更换产品、货权或仓库。');
 if(mod==='orders')hint=alertBox('订单原币销售额与参考汇率会保存为快照；订单产品成本、直接费用、收款和库存预留在订单详情中单独登记。');
 showModal((id?'编辑':'新增')+moduleLabel(mod),hint+`<form id="recordForm" class="form-grid">${h}</form>`,()=>saveRecord(mod,id),true);
}
function readCustomFields(form){const out={};for(const el of form.querySelectorAll('[data-custom="1"]'))out[el.dataset.field]=el.type==='checkbox'?el.checked:el.value;return out}
async function getFormPayload(selector='#recordForm'){const form=$(selector);if(form&&!form.reportValidity())throw Error('请填写所有必填内容');const record={custom_json:{}};
 for(const el of form.querySelectorAll('[data-field]')){
   const key=el.dataset.field;let val=el.type==='checkbox'?el.checked:el.value;
   if(el.type==='number')val=val===''?0:Number(val);
   if(el.dataset.custom==='1')record.custom_json[key]=val;else record[key]=val;
 }
 const tags=[...form.querySelectorAll('[data-tagval]:checked')].map(el=>el.dataset.tagval);if(form.querySelector('[data-tagval]'))record.tags_json=tags;
 for(const file of form.querySelectorAll('[data-photo]')){if(!file.files.length)continue;const data=new FormData();data.append('file',file.files[0]);const response=await fetch('/api.php?action=upload',{method:'POST',headers:{'X-CSRF-Token':S.csrf},body:data,credentials:'same-origin'});const up=await response.json();if(!response.ok||!up.ok)throw Error(up.error||'图片上传失败');if(file.dataset.photo.startsWith('cf_'))record.custom_json[file.dataset.photo]=up.id;else record[file.dataset.photo]=up.id;}
 return record;
}
async function saveRecord(mod,id){try{const record=await getFormPayload();if(id)record.id=id;
 if(mod==='leads'||mod==='orders'){
 const cur=record.currency||'USD';if(cur==='CNY')record.fx_to_cny=1;
 else if(!(Number(record.fx_to_cny)>0))record.fx_to_cny=S.conf.finance.default_usd_rate||7.2;
 }
 const r=await api('save',{entity:mod,record});toast('资料已保存');closeModal();await loadLookups();await loadTable();if(mod==='orders')await showOrder(r.id);
 }catch(e){toast(e.message,true)}}
async function openMove(lotId=''){
 if(!permitted('movements.create'))return toast('没有出入库权限',true);await lookupsIfNeeded();
 const body=`<form id="moveForm" class="form-grid"><div class="field"><label class="required">库存批次</label><select class="select" name="lot_id" required>${opt('','选择批次','')}${(S.lookups.lots||[]).map(x=>opt(x.id,`${x.product_name} / ${x.lot_code} / ${x.warehouse_name} / 可用${fmt(x.available||0,3)}`,lotId)).join('')}</select></div><div class="field"><label>操作类型</label><select name="type" class="select">${opt('in','入库','in')}${opt('out','出库','')}${opt('adjust_in','盘盈增加','')}${opt('adjust_out','盘亏减少','')}</select></div><div class="field"><label class="required">数量</label><input class="input" name="qty" type="number" min="0.001" step="0.001" required></div><div class="field"><label>关联订单（可选）</label><select class="select" name="order_id">${opt('','不关联订单','')}${(S.lookups.orders||[]).map(o=>opt(o.id,o.order_code,'' )).join('')}</select></div><div class="field"><label>客户（无订单时可选）</label><select class="select" name="customer_id">${relationOpts('customer_id','')}</select></div><div class="field full"><label>操作说明 / 发货物流信息</label><textarea class="textarea" name="remark" placeholder="如：客户发货 / 入库原因 / 车牌"></textarea></div>${activeFields('movements',true).filter(f=>!+f.builtin).map(f=>inputFor('movements',f)).join('')}</form>${alertBox('出库时自动校验可用库存。每次提交记录操作员工、操作时间和库存变更前后数量。','warn')}`;
 showModal('库存出入库',body,async()=>{try{const form=$('#moveForm');if(!form.reportValidity())throw Error('请填写数量和批次');const data=Object.fromEntries(new FormData(form));data.custom_json=readCustomFields(form);const r=await api('move',data);toast('操作成功，最新库存：'+fmt(r.after,3));closeModal();await loadLookups();await loadTable();}catch(e){toast(e.message,true)}},true);
}
async function transferModal(id){
 if(!permitted('lots.edit')||!permitted('movements.create'))return toast('没有库存调拨权限',true);
 await loadLookups();const lot=(S.lookups.lots||[]).find(x=>String(x.id)===String(id));
 if(!lot)throw Error('找不到库存批次');
 const body=`<div class="notice">来源：${esc(lot.product_name)} / ${esc(lot.lot_code)} / ${esc(lot.warehouse_name)}，可用 ${fmt(lot.available||0,3)}。调拨会同步生成一条出库和一条入库流水，保留原始货权及调拨轨迹。</div><form id="transferForm" class="form-grid"><div class="field"><label>目标仓库</label><select class="select" name="warehouse_id" required>${opt('','请选择目标仓库','')}${(S.lookups.warehouses||[]).map(x=>opt(x.id,x.name,'' )).join('')}</select></div><div class="field"><label>调拨后货权所有者</label><select class="select" name="owner_party_id" required>${opt('','请选择归属','')}${(S.lookups.parties||[]).map(x=>opt(x.id,x.name,lot.owner_party_id)).join('')}</select></div>${numericField('调拨数量','qty',0,'0.001')}<div class="field"><label>目标库位</label><input class="input" name="location" placeholder="例如 B03"></div><div class="field full"><label>备注</label><input class="input" name="remark" placeholder="例如：工厂间调拨、合作商转存"></div></form>`;
 showModal('库存批次调拨',body,async()=>{try{const data=formObj('#transferForm');data.lot_id=id;await api('transfer',data);closeModal();toast('调拨成功，已生成双向出入库流水');await loadLookups();await loadTable()}catch(e){toast(e.message,true)}},true);
}
async function showOrder(id){const res=await api('detail',null,{entity:'orders',id});const o=res.record;S.editData=o;
 let content=`<div class="details-grid" style="margin-bottom:16px"><div class="kv"><small>订单外币金额</small><strong>${esc(o.currency)} ${fmt(o.amount)}</strong></div><div class="kv"><small>订单参考汇率</small><strong>${fmt(o.fx_to_cny,4)}</strong></div><div class="kv"><small>折合人民币收入</small><strong>${cny(Number(o.amount)*Number(o.fx_to_cny))}</strong></div></div>`;
 if(permitted('finance.view')){
 const material=(o.lines||[]).reduce((s,x)=>s+Number(x.qty)*Number(x.unit_cost_cny),0);
 const direct=(o.direct_costs||[]).reduce((s,x)=>s+Number(x.amount_cny),0);
 const received=(o.receipts||[]).reduce((s,x)=>s+Number(x.amount_cny),0);const profit=Number(o.amount)*Number(o.fx_to_cny)-material-direct;
 content+=`<div class="details-grid" style="margin-bottom:15px"><div class="kv"><small>产品直接成本（人民币）</small><strong>${cny(material)}</strong></div><div class="kv"><small>其他直接费用（人民币）</small><strong>${cny(direct)}</strong></div><div class="kv"><small>订单贡献利润（人民币）</small><strong class="${profit<0?'negative':'positive'}">${cny(profit)}</strong></div></div><div class="notice">该利润未扣除公司期间工资、广告、房租等经营费用。实际收款人民币金额另行记录，不会回写订单原始参考汇率。</div>`;
 }
 content+=`<div class="card"><div class="card-head"><h3>订单产品与成本</h3>${permitted('finance.edit')?btn('+ 添加产品成本','orderLineNew',id,'mini primary'):''}</div><div class="card-body">${(o.lines||[]).map(x=>`<div class="list-row"><div class="grow"><b>${esc(x.product_name)}</b><small>${fmt(x.qty,3)} ${esc(x.unit)}${permitted('finance.view')?' × '+cny(x.unit_cost_cny):''}</small></div>${permitted('finance.edit')?btn('移除','orderLineDelete',x.id,'mini danger'):''}</div>`).join('')||'<p class="small">尚未录入订单产品成本。</p>'}</div></div>`;
 if(permitted('finance.view')){
 content+=`<div class="card"><div class="card-head"><h3>订单直接费用（人民币）</h3>${permitted('finance.edit')?btn('+ 新增费用','orderCostNew',id,'mini primary'):''}</div><div class="card-body">${(o.direct_costs||[]).map(x=>`<div class="list-row"><div class="grow"><b>${esc(x.category)} · ${cny(x.amount_cny)}</b><small>${esc(x.cost_date)}　${esc(x.note)}</small></div>${permitted('finance.edit')?btn('删除','costDelete',x.id,'mini danger'):''}</div>`).join('')||'<p class="small">尚无额外直接费用。</p>'}</div></div>`;
 content+=`<div class="card"><div class="card-head"><h3>回款记录</h3>${permitted('finance.edit')?btn('+ 登记回款','receiptNew',id,'mini primary'):''}</div><div class="card-body">${(o.receipts||[]).map(x=>`<div class="list-row"><div class="grow"><b>${esc(x.currency)} ${fmt(x.amount)} → ${cny(x.amount_cny)}</b><small>${esc(x.receipt_date)}　实际汇率 ${fmt(x.fx_to_cny,4)}　${esc(x.note)}</small></div>${permitted('finance.edit')?btn('删除','receiptDelete',x.id,'mini danger'):''}</div>`).join('')||'<p class="small">尚无回款记录。</p>'}</div></div>`;
 }
 content+=`<div class="card"><div class="card-head"><h3>库存预留</h3>${permitted('orders.edit')?btn('+ 设置批次预留','reserveNew',id,'mini primary'):''}</div><div class="card-body">${(o.reservations||[]).map(x=>`<div class="list-row"><div class="grow"><b>${esc(x.product_name)} / ${esc(x.lot_code)}</b><small>已为本订单预留 ${fmt(x.qty,3)}</small></div>${permitted('orders.edit')?btn('调整','reserveEdit',`${id}:${x.lot_id}`,'mini'):''}</div>`).join('')||'<p class="small">暂无预留批次。</p>'}</div></div>`;
 showModal(`订单 ${o.order_code} · 财务与库存`,content,null,true);
}
async function orderDetailRefresh(){const id=S.editData?.id;if(id)await showOrder(id)}
function numericField(label,name,defaultVal=0,step='0.01'){return `<div class="field"><label>${esc(label)}</label><input class="input" name="${name}" type="number" min="0" step="${step}" value="${esc(defaultVal)}"></div>`}
function dateField(label,name,defaultVal){return `<div class="field"><label>${esc(label)}</label><input class="input" name="${name}" type="date" required value="${esc(defaultVal||new Date().toISOString().slice(0,10))}"></div>`}
function formObj(selector){const f=$(selector);if(!f.reportValidity())throw Error('请填写完整表单');return Object.fromEntries(new FormData(f))}
async function expenseModal(oid=''){
 S.expenseKind=oid?'direct_costs':'operating_expenses';
 const data={cost_date:new Date().toISOString().slice(0,10)};
 const customFields=activeFields('expenses',true);
 const body=`<form id="costForm" class="form-grid">${customFields.map(f=>inputFor('expenses',f,data)).join('')}</form>`;
 showModal(oid?'订单直接费用':'公司经营费用',body,async()=>{try{
     const v=await getFormPayload('#costForm');v.order_id=oid||null;
     await api('cost_add',v);toast('费用登记成功');closeModal();
     if(oid)await showOrder(oid);else await loadTable();
 }catch(e){toast(e.message,true)}},true);
}

async function orderLineModal(oid){await loadLookups();const b=`<form id="lineForm" class="form-grid"><div class="field"><label>产品</label><select class="select" name="product_id" id="lineProduct" required>${opt('','请选择产品','')}${(S.lookups.products||[]).map(x=>opt(x.id,x.name+' / '+x.spec,'' )).join('')}</select></div><div class="field"><label>批次（可选）</label><select class="select" name="lot_id" id="lineLot">${opt('','不关联具体批次','')}${(S.lookups.lots||[]).map(x=>opt(x.id,x.product_name+' / '+x.lot_code,'' )).join('')}</select></div>${numericField('产品数量','qty',1,'0.001')}${numericField('每单位产品实际成本（人民币）','unit_cost_cny',0,'0.0001')}</form>${alertBox('这里登记订单成本，不自动扣减库存；真实出货应在库存批次点击「出入库」。')}`;
 showModal('添加订单产品成本',b,async()=>{try{const data=formObj('#lineForm');data.order_id=oid;await api('order_line_add',data);toast('订单成本明细已添加');closeModal();await showOrder(oid)}catch(e){toast(e.message,true)}},true);
}
function receiptModal(oid){const body=`<form id="receiptForm" class="form-grid">${dateField('实际收款日期','receipt_date')}<div class="field"><label>收款币种</label><select class="select" name="currency" id="receiptCurrency">${['USD','CNY','EUR','AUD','CAD','GBP'].map(x=>opt(x,x,'USD')).join('')}</select></div>${numericField('实际收到原币金额','amount',0)}${numericField('实际结汇汇率（1外币=人民币）','fx_to_cny',S.conf.finance.default_usd_rate||7.2,'0.000001')}<div class="field full"><label>备注</label><input class="input" name="note" placeholder="例如：30%预付款、尾款、银行结汇单"></div></form>`;
 showModal('登记实际收款',body,async()=>{try{const data=formObj('#receiptForm');data.order_id=oid;await api('receipt_add',data);closeModal();toast('回款记录已保存');await showOrder(oid)}catch(e){toast(e.message,true)}},true);
}
async function reserveModal(oid,lotId=''){
 await loadLookups();const existing=lotId?(S.editData?.reservations||[]).find(x=>String(x.lot_id)===String(lotId)):null;
 const body=`<form id="reserveForm" class="form-grid"><div class="field"><label>库存批次</label><select class="select" name="lot_id" required>${opt('','请选择批次','')}${(S.lookups.lots||[]).map(x=>opt(x.id,`${x.product_name} / ${x.lot_code}（可用${fmt(x.available||0,3)}）`,lotId)).join('')}</select></div>${numericField('本订单预留数量（填0为释放）','qty',existing?.qty||0,'0.001')}</form>`;
 showModal('设置订单库存预留',body,async()=>{try{const data=formObj('#reserveForm');data.order_id=oid;await api('reserve',data);closeModal();toast('预留库存已更新');await loadLookups();await showOrder(oid)}catch(e){toast(e.message,true)}},true);
}
let Q_DRAFT=null;
function renderQuotes(){const finance=permitted('finance.view');const p=(S.lookups.products||[]),lots=(S.lookups.lots||[]);
 const fees=S.quoteFees.map((f,i)=>`<div class="form-grid row-in-form"><div class="field"><label>费用名称</label><input class="input" data-fee-label="${i}" value="${esc(f.label)}"></div><div class="field"><label>人民币金额 <span class="small">${btn('移除','quoteFeeRemove',i,'mini')}</span></label><input class="input" data-fee-amount="${i}" type="number" step="0.01" min="0" value="${esc(f.amount_cny)}"></div></div>`).join('');
 const form=`<div class="form-grid"><div class="field"><label>报价关联客户（可选）</label><select class="select" id="qCustomer">${opt('','未关联客户','')}${(S.lookups.customers||[]).map(x=>opt(x.id,x.name,'' )).join('')}</select></div><div class="field"><label>选择产品（可选）</label><select class="select" id="qProduct">${opt('','手动报价','')}${p.map(x=>opt(x.id,x.name+' / '+x.spec,'' )).join('')}</select></div><div class="field"><label>选择库存批次（带入成本）</label><select class="select" id="qLot">${opt('','不关联批次','')}${lots.map(x=>opt(x.id,x.product_name+' / '+x.lot_code,'' )).join('')}</select></div>${numericField('产品单位成本（人民币）','dummy',0)}${numericField('数量','dummy',100,'0.001')}<div class="field"><label>报价币种</label><select class="select" id="qCurrency">${['USD','CNY','EUR','AUD','CAD','GBP'].map(x=>opt(x,x,'USD')).join('')}</select></div><div class="field"><label>贸易术语</label><select class="select" id="qTerm">${['EXW','FOB','CFR','CIF','DAP','DDP'].map(x=>opt(x,x,'FOB')).join('')}</select></div>${numericField('手动汇率（1外币 = 人民币）','dummy',S.conf.finance.default_usd_rate||7.2,'0.000001')}${numericField('目标毛利率（%）','dummy',S.conf.finance.default_margin||22,'0.01')}${numericField('佣金占销售额（%）','dummy',S.conf.finance.default_commission||0,'0.01')}<div class="field"><label>计价单位</label><select id="qUnit" class="select">${refs('units').map(x=>opt(x,x,'张')).join('')}</select></div></div>`;
 // Quotes never mutate product or stock; inputs are fully local until save.
 const quoteCustom=activeFields('quotes',true).filter(f=>!+f.builtin).map(f=>inputFor('quotes',f)).join('');
 const body=pageHead(moduleLabel('quotes'),`${btn('新增报价费用','quoteFeeAdd')} ${btn('保存报价方案','quoteSave','','primary')}`)+`<div class="cols2"><div>${card('实时报价参数',`<div id="quoteForm">${form.replace('name="dummy"','id="qCost"').replace('name="dummy"','id="qQty"').replace('name="dummy"','id="qFx"').replace('name="dummy"','id="qMargin"').replace('name="dummy"','id="qCommission"')}</div><h3 style="margin:18px 0 10px">按订单计入的费用（人民币）</h3><div id="quoteFeeContainer">${fees}</div>${quoteCustom?`<h3 style="margin:16px 0 10px">自定义报价字段</h3><div class="form-grid" id="quoteCustomFields">${quoteCustom}</div>`:''}<p class="small">选择术语仅做报价标记；你可以按实际贸易责任手动添加并调整适用费用，避免误将不承担的运费计入。</p>`)}</div><div>${card('计算结果（实时人民币核算）',`<div id="quoteOutput">修改金额后自动计算。</div>${alertBox('报价计算器是员工即时工具；只有点击保存，才会生成报价记录。默认汇率可在设置中心修改，每份保存的报价有独立汇率快照。')}`)}${card('最近保存的报价',`<div id="quoteHistory">${(S.lookups.quotes||[]).map(q=>`<div class="list-row"><div class="grow"><b>${esc(q.quote_code)}</b><small>${esc(q.term)} · ${esc(q.currency)} ${fmt(q.quote_total)} / 预估利润 ${cny(q.expected_profit_cny)}</small></div></div>`).join('')||'<div class="small">如需查看历史报价，可在下方刷新列表。</div>'}</div>`)}</div></div>`;
 layout(body);if(Q_DRAFT){for(const [id,val] of Object.entries(Q_DRAFT)){const el=$('#'+id);if(el)el.value=val}Q_DRAFT=null}updateQuote();}
function captureQuoteDraft(){const data={};for(const id of ['qCustomer','qProduct','qLot','qCurrency','qFx','qTerm','qUnit','qQty','qCost','qMargin','qCommission']){const el=$('#'+id);if(el)data[id]=el.value}S.quoteFees=S.quoteFees.map((f,i)=>({label:$(`[data-fee-label="${i}"]`)?.value||f.label,amount_cny:Number($(`[data-fee-amount="${i}"]`)?.value||0)}));Q_DRAFT=data;}
function quotePayload(){const number=id=>Number($('#'+id)?.value||0);
 return {customer_id:$('#qCustomer')?.value||null,product_id:$('#qProduct')?.value||null,lot_id:$('#qLot')?.value||null,currency:$('#qCurrency')?.value||'USD',fx_to_cny:number('qFx'),term:$('#qTerm')?.value||'FOB',unit:$('#qUnit')?.value||'件',qty:number('qQty'),unit_cost_cny:number('qCost'),margin_pct:number('qMargin'),commission_pct:number('qCommission'),custom_json:$('#quoteCustomFields')?readCustomFields($('#quoteCustomFields')):{},fees:S.quoteFees.map((f,i)=>({label:$(`[data-fee-label="${i}"]`)?.value||f.label,amount_cny:Number($(`[data-fee-amount="${i}"]`)?.value||0)}))};
}
function calculateClientQuote(q){const qty=Number(q.qty),fx=q.currency==='CNY'?1:Number(q.fx_to_cny),margin=Number(q.margin_pct),commission=Number(q.commission_pct);
 const direct=qty*Number(q.unit_cost_cny)+q.fees.reduce((s,x)=>s+Number(x.amount_cny||0),0);
 if(!(qty>0&&fx>0&&margin>=0&&commission>=0&&margin+commission<95&&direct>=0))return null;
 const totalCny=direct/(1-(margin+commission)/100),total=totalCny/fx,profit=totalCny-direct-totalCny*commission/100;
 return {direct,totalCny,total,unitPrice:total/qty,profit,commissionCny:totalCny*commission/100};
}
function updateQuote(){const box=$('#quoteOutput');if(!box)return;const q=quotePayload(),r=calculateClientQuote(q);
 if(!r){box.innerHTML=alertBox('请填写正数数量与汇率；毛利率和佣金合计必须低于95%。','warn');return}
 box.innerHTML=`<div class="quote-result"><small>建议单价 · ${esc(q.currency)} / ${esc(q.unit)}</small><div class="amount">${esc(q.currency)} ${fmt(r.unitPrice)}</div><small>折合人民币 ¥${fmt(r.unitPrice*(q.currency==='CNY'?1:Number(q.fx_to_cny)))} / ${esc(q.unit)}</small></div><div class="quote-line"><span>建议订单总报价</span><b>${esc(q.currency)} ${fmt(r.total)}</b></div><div class="quote-line"><span>折合人民币收入</span><b>${cny(r.totalCny)}</b></div><div class="quote-line"><span>产品成本 + 费用</span><b>${cny(r.direct)}</b></div><div class="quote-line"><span>预计佣金</span><b>${cny(r.commissionCny)}</b></div><div class="quote-line"><span><b>预计订单贡献利润</b></span><b class="positive">${cny(r.profit)}</b></div><p class="small" style="margin-top:14px">计算：报价收入 = （产品成本 + 填写费用）÷（1－目标毛利率－佣金率）。不自动计算退税、关税和未填写的物流项目。</p>`;
}
function quoteLotSync(){const val=$('#qLot')?.value;const lot=(S.lookups.lots||[]).find(x=>String(x.id)===val);if(lot){if($('#qCost'))$('#qCost').value=lot.unit_cost_cny||0;if($('#qProduct'))$('#qProduct').value=lot.product_id;const p=(S.lookups.products||[]).find(x=>String(x.id)===String(lot.product_id));if(p&&$('#qUnit'))$('#qUnit').value=p.unit;}
 updateQuote();}
async function saveQuote(){try{const data=quotePayload();await api('quote_save',data);toast('报价记录已保存');captureQuoteDraft();await loadLookups();S.lookups.quotes=(await api('list',null,{entity:'quotes',limit:20})).rows;renderQuotes()}catch(e){toast(e.message,true)}}
function quoteHistory(){navigate('quotes')}
const SETTINGS_TABS=[['brand','品牌与外观'],['menu','模块与导航'],['fields','字段与列表'],['refs','选项与标签'],['finance','币种与利润'],['dashboard','看板卡片'],['security','权限与数据安全']];
const FIELD_MODULES=['products','lots','customers','leads','orders','expenses','parties','warehouses','users','movements','quotes','audit'];
const REF_GROUPS=[['product_categories','产品分类'],['units','库存单位'],['grades','产品等级'],['lead_status','商机状态'],['lead_sources','商机来源'],['order_status','订单状态'],['operating_expenses','公司经营费用'],['direct_costs','订单直接费用'],['customer_tags','客户标签'],['party_kinds','主体类别']];
async function renderSettings(){if(!canNav('settings'))return;let body='';const brand=S.conf.brand||{},finance=S.conf.finance||{};
 if(S.tab==='brand')body=card('企业品牌与系统外观',`<form id="brandForm" class="form-grid"><div class="field"><label>系统名称</label><input class="input" name="name" value="${esc(brand.name)}" required></div><div class="field"><label>系统副标题</label><input class="input" name="subtitle" value="${esc(brand.subtitle)}"></div><div class="field"><label>主题色</label><input type="color" class="input" name="accent" value="${esc(brand.accent||'#245fc9')}"></div><div class="field"><label>公司 Logo</label><input type="file" id="brandLogoFile" class="input" accept="image/png,image/jpeg,image/webp"></div><input type="hidden" name="logo_id" value="${esc(brand.logo_id||'')}"></form><div class="actions" style="margin-top:18px">${btn('保存品牌配置','brandSave','','primary')}</div>`);
 if(S.tab==='menu')body=card('导航模块名称、顺序与显示',alertBox('每个模块都可以修改名称、调整位置和隐藏；核心设置中心不可隐藏。服务器权限独立生效。')+(S.conf.nav||[]).map((x,i,arr)=>`<div class="list-row"><span class="mini-icon">${ICONS[x.module]||'▣'}</span><div class="grow"><input class="input" style="max-width:230px" data-menu-name="${esc(x.module)}" value="${esc(x.label)}"><small>${esc(x.module)}</small></div><label class="checkline"><input type="checkbox" data-menu-show="${esc(x.module)}" ${+x.visible?'checked':''} ${x.module==='settings'?'disabled':''}> 显示</label>${btn('↑','menuUp',i,'mini')}${btn('↓','menuDown',i,'mini')}</div>`).join('')+`<div style="margin-top:17px">${btn('保存菜单布局','saveMenu','','primary')}</div>`)+card('设置中心栏目顺序', (S.conf.settings_sections||SETTINGS_TABS.map(([id,label],i)=>({id,label,position:(i+1)*10}))).map((x,i)=>`<div class="list-row"><div class="grow"><input class="input" style="max-width:260px" data-section-name="${esc(x.id)}" value="${esc(x.label)}"></div>${btn('↑','sectionUp',i,'mini')}${btn('↓','sectionDown',i,'mini')}</div>`).join('')+`<div style="margin-top:17px">${btn('保存设置栏目排序','saveSections','','primary')}</div>`);
 if(S.tab==='fields'){
 const active=S.fieldModule||'products';const all=(S.conf.fields||[]).filter(f=>f.module===active).sort((a,b)=>a.position-b.position);
 body=card('统一字段配置器',`<div class="toolbar"><select id="fieldModule" class="select" style="max-width:260px">${FIELD_MODULES.map(x=>opt(x,moduleLabel(x),active)).join('')}</select>${active==='audit'?'':btn('+ 创建自定义字段','fieldNew','','primary')}<span class="small">字段顺序和显示位置不会改变固定字段 ID</span></div>${alertBox('字段的 field_key 为永久内部标识。修改颜色和材质的显示顺序、标题、隐藏状态，不会改变已有数据值。核心数量、货权、订单币种等受保护。')}${all.map((f,i)=>`<div class="list-row"><div class="grow"><b>${esc(f.label)}</b> <span class="code-pill">${esc(f.field_key)}</span>${+f.protected?'<span class="tag warn">受保护</span>':''}<small>${esc(f.field_type)} · ${+f.active?'启用':'停用'} · ${+f.visible?'显示':'隐藏'} · ${+f.list_visible?'列表显示':'列表隐藏'}</small></div>${btn('↑','fieldUp',i,'mini')}${btn('↓','fieldDown',i,'mini')}${btn('配置','fieldEdit',f.field_key,'mini')}${+f.protected?'':btn(+f.active?'停用':'恢复','fieldToggle',f.field_key,'mini danger')}</div>`).join('')}<div style="margin-top:17px">${btn('保存字段排序','saveFieldSort','','primary')}</div>`);
 }
 if(S.tab==='refs'){
 if(!S.refRows){try{S.refRows=(await api('references')).rows}catch(e){toast(e.message,true);S.refRows=[]}}
 const group=S.refGroup||'product_categories',selected=S.refRows.filter(x=>x.group_code===group).sort((a,b)=>a.position-b.position);
 body=card('业务选项和标签字典',`<div class="toolbar"><select id="refGroup" class="select" style="max-width:260px">${REF_GROUPS.map(([g,n])=>opt(g,n,group)).join('')}</select>${btn('+ 新增选项','refNew','','primary')}</div>${selected.map(x=>`<div class="list-row"><div class="grow"><b>${esc(x.value)}</b><small>排序 ${esc(x.position)} · ${+x.active?'启用':'停用'}</small></div>${btn('编辑','refEdit',x.id,'mini')}</div>`).join('')||'<div class="empty">暂无选项</div>'}`);
 }
 if(S.tab==='finance')body=card('人民币核算与手动汇率',`<form id="financeForm" class="form-grid"><div class="field"><label>公司统一核算币种</label><input class="input" value="CNY · 人民币（系统固定）" disabled></div>${numericField('默认参考汇率 · 1 USD 兑换人民币','default_usd_rate',finance.default_usd_rate||7.2,'0.000001')}${numericField('默认目标毛利率（%）','default_margin',finance.default_margin||22)}${numericField('默认报价佣金率（%）','default_commission',finance.default_commission||0)}</form><p class="small" style="margin:18px 0">订单与商机保存各自的汇率快照；更改本页默认值不会改变历史订单利润。实际收款结汇汇率在订单回款中分别填写。</p>${btn('保存财务与汇率设置','financeSave','','primary')}`);
 if(S.tab==='dashboard')body=card('老板经营大屏卡片顺序与显隐',(S.conf.dashboard_cards||[]).map((x,i)=>`<div class="list-row"><div class="grow"><input class="input" data-card-label="${esc(x.key)}" value="${esc(x.label)}" style="max-width:290px"><small>${esc(x.key)}</small></div><label class="checkline"><input type="checkbox" data-card-show="${esc(x.key)}" ${x.visible?'checked':''}> 显示</label>${btn('↑','cardUp',i,'mini')}${btn('↓','cardDown',i,'mini')}</div>`).join('')+`<div style="margin-top:18px">${btn('+ 添加指标卡片','cardNew')} ${btn('保存卡片顺序和名称','saveCards','','primary')}</div>`);
 if(S.tab==='security')body=card('账号权限及部署安全',`<p>由超级管理员创建员工账号并逐项授权。普通管理员必须同时拥有 <b>员工管理</b> 或 <b>设置管理</b> 权限才能配置对应模块。</p><p>正式环境必须启用 HTTPS、数据库备份、员工专用密码；员工首次登录强制修改密码。</p>${btn('进入员工权限','go','users','primary')}<hr style="border:0;border-top:1px solid var(--border);margin:20px 0"><h3>配置覆盖说明</h3><p class="small">可调整：品牌、主菜单排序与显隐、内置字段标题/排序/可选隐藏、自定义字段、选项字典、看板卡片、默认汇率和报价规则。不可随意更改：库存扣减事务、固定字段 ID、金额汇率快照、审计记录和服务端权限校验。它们属于数据一致性保护机制。</p>`);
 layout(pageHead(moduleLabel('settings'))+`<div class="tabs">${(S.conf.settings_sections?.length?S.conf.settings_sections:SETTINGS_TABS.map(([id,label])=>({id,label}))).map(x=>`<button type="button" class="tab ${S.tab===x.id?'active':''}" data-setting="${x.id}">${esc(x.label)}</button>`).join('')}</div>${body}`);
}
async function reloadConf(){const r=await api('bootstrap');S.conf=r;S.user=r.user;document.documentElement.style.setProperty('--primary',r.brand.accent||'#245fc9')}
function reorder(arr,index,d){const j=index+d;if(j<0||j>=arr.length)return;[arr[index],arr[j]]=[arr[j],arr[index]];arr.forEach((x,k)=>x.position=(k+1)*10)}
async function saveMenu(){try{const items=S.conf.nav.map(x=>({module:x.module,label:$(`[data-menu-name="${x.module}"]`)?.value||x.label,visible:x.module==='settings'?true:!!$(`[data-menu-show="${x.module}"]`)?.checked,position:x.position}));await api('sort',{type:'menu',items});await reloadConf();toast('导航配置已更新');await renderSettings()}catch(e){toast(e.message,true)}}
async function saveSections(){try{const items=(S.conf.settings_sections||[]).map(x=>({...x,label:$(`[data-section-name="${x.id}"]`)?.value||x.label}));await api('sort',{type:'sections',items});await reloadConf();toast('设置中心栏目配置已保存');renderSettings()}catch(e){toast(e.message,true)}}
async function saveCards(){try{const items=S.conf.dashboard_cards.map(x=>({...x,label:$(`[data-card-label="${x.key}"]`)?.value||x.label,visible:!!$(`[data-card-show="${x.key}"]`)?.checked}));await api('sort',{type:'dashboard',items});await reloadConf();toast('看板设置已保存');renderSettings()}catch(e){toast(e.message,true)}}
async function saveFieldSort(){try{const m=S.fieldModule||'products',fields=S.conf.fields.filter(f=>f.module===m);await api('sort',{type:'fields',items:fields.map(f=>({module:m,field_key:f.field_key,position:f.position}))});await reloadConf();toast('字段排序已保存');renderSettings()}catch(e){toast(e.message,true)}}
function cardNewModal(){
 const options=[['receipts_cny','本期实际收款'],['potential_profit_cny','商机潜在利润'],['lost_cny','已丢失商机金额'],['low_stock_lots','低库存产品数'],['orders_count','本期订单数量'],['leads_count','本期新增商机数量'],['products','产品SKU总数'],['available_lots','可用库存批次']];
 const current=new Set((S.conf.dashboard_cards||[]).map(x=>x.key));
 const available=options.filter(([k])=>!current.has(k));if(!available.length){toast('可添加的指标卡片已经全部使用');return}
 showModal('添加经营看板指标',`<form id="cardForm" class="form-grid"><div class="field"><label>选择指标</label><select id="cardMetric" class="select">${available.map(([k,n])=>opt(k,n)).join('')}</select></div><div class="field"><label>卡片标题</label><input id="cardTitle" class="input" placeholder="留空使用默认指标名称"></div></form>`,async()=>{try{const key=$('#cardMetric').value,label=$('#cardTitle').value.trim()||available.find(x=>x[0]===key)[1];S.conf.dashboard_cards.push({key,label,visible:true});await api('sort',{type:'dashboard',items:S.conf.dashboard_cards});await reloadConf();closeModal();renderSettings();toast('已添加经营指标卡片')}catch(e){toast(e.message,true)}});
}
function fieldModal(key=''){
 const m=S.fieldModule||'products';const f=S.conf.fields.find(x=>x.module===m&&x.field_key===key);
 const type=f?.field_type||'text',choices=f?.options_json?JSON.parse(f.options_json):[];
 const control=`<form id="fieldForm" class="form-grid"><div class="field"><label>字段名称</label><input class="input" name="label" required value="${esc(f?.label||'')}"></div><div class="field"><label>字段类型</label><select class="select" name="field_type" ${f?'disabled':''}>${['text','number','date','select','textarea','checkbox','email','image'].map(x=>opt(x,({text:'单行文本',number:'数字',date:'日期',select:'下拉选项',textarea:'多行文本',checkbox:'是/否',email:'邮箱',image:'图片'})[x],type)).join('')}</select></div><div class="field full"><label>输入框提示文字 Placeholder</label><input class="input" name="placeholder" value="${esc(f?.placeholder||'')}"></div><div class="field full"><label>下拉选项（每行一个，自定义字段使用）</label><textarea class="textarea" name="options">${esc(choices.join('\n'))}</textarea></div><div class="field"><label class="checkline"><input type="checkbox" name="visible" ${!f||+f.visible?'checked':''} ${+f?.protected?'disabled':''}> 表单中显示</label><label class="checkline" style="margin-top:12px"><input type="checkbox" name="required_field" ${+f?.required_field?'checked':''} ${+f?.protected?'disabled':''}> 必填</label></div><div class="field"><label class="checkline"><input type="checkbox" name="list_visible" ${!f||+f.list_visible?'checked':''}> 在列表中显示</label><small class="help">受保护的核心字段不可隐藏或取消必填</small></div></form>`;
 showModal(f?'编辑字段 · '+f.label:'新增字段 · '+moduleLabel(m),control,async()=>{try{const v=formObj('#fieldForm');
 const payload={module:m,field_key:key||'',label:v.label,field_type:f?.field_type||v.field_type,placeholder:v.placeholder,options:v.options.split(/\r?\n/).map(x=>x.trim()).filter(Boolean),visible:f?.protected?true:!!$('#fieldForm [name="visible"]').checked,required_field:f?.protected?true:!!$('#fieldForm [name="required_field"]').checked,list_visible:!!$('#fieldForm [name="list_visible"]').checked,position:f?.position||((S.conf.fields.filter(x=>x.module===m).length+1)*10)};
 await api('field_save',payload);toast('字段配置已更新');closeModal();await reloadConf();await renderSettings();}catch(e){toast(e.message,true)}},true);
}
function refModal(id=''){
 const group=S.refGroup||'product_categories',r=(S.refRows||[]).find(x=>String(x.id)===String(id));
 showModal(id?'编辑选项':'新增选项',`<form id="refForm" class="form-grid"><div class="field"><label>选项名称</label><input class="input" name="value" required value="${esc(r?.value||'')}"></div><div class="field"><label>排序数值</label><input class="input" name="position" type="number" value="${esc(r?.position||100)}"></div><div class="field"><label class="checkline"><input type="checkbox" name="active" ${!r||+r.active?'checked':''}> 启用</label></div></form>`,async()=>{try{const v=formObj('#refForm');await api('ref_save',{id:id||null,group_code:group,value:v.value,position:v.position,active:!!$('#refForm [name="active"]').checked});toast('选项已保存');closeModal();S.refRows=(await api('references')).rows;await reloadConf();await renderSettings()}catch(e){toast(e.message,true)}});
}
async function uploadBrand(){const f=$('#brandLogoFile')?.files?.[0];if(!f)return $('input[name="logo_id"]')?.value||null;const fd=new FormData();fd.append('file',f);const r=await fetch('/api.php?action=upload',{method:'POST',headers:{'X-CSRF-Token':S.csrf},body:fd,credentials:'same-origin'});const v=await r.json();if(!r.ok||!v.ok)throw Error(v.error||'上传Logo失败');return v.id;}
async function saveBrand(){try{const v=formObj('#brandForm');v.logo_id=await uploadBrand();await api('brand_save',v);await reloadConf();toast('品牌配置已保存');await renderSettings()}catch(e){toast(e.message,true)}}
async function saveFinance(){try{const v=formObj('#financeForm');await api('finance_save',v);await reloadConf();toast('币种与利润参数已保存');await renderSettings()}catch(e){toast(e.message,true)}}
function userModal(id=''){
 const u=(S.rows||[]).find(x=>String(x.id)===String(id));
 const base=Array.isArray(u?.permissions_json)?u.permissions_json:[];
 const fields=activeFields('users',true).map(f=>inputFor('users',f,u||{})).join('');
 const body=`<form id="userForm" class="form-grid">${fields}<div class="field"><label>${id?'重置密码（留空不修改）':'初始密码（至少10位）'}</label><input type="password" name="password" class="input" autocomplete="new-password"></div><div class="field"><label class="checkline"><input type="checkbox" name="active" ${!u||+u.active?'checked':''}> 账号启用</label></div><div class="field full"><label>功能权限（由服务端实际校验）</label><div class="two-column">${S.conf.permissions.map(p=>`<label class="checkline" style="padding:4px 0"><input type="checkbox" data-permission="${esc(p)}" ${base.includes(p)?'checked':''}>${esc(p)}</label>`).join('')}</div></div></form>`;
 showModal(id?'编辑员工权限':'创建员工子账号',body,async()=>{try{
   const v=await getFormPayload('#userForm');v.id=id||null;v.password=$('#userForm [name="password"]').value;
   v.active=$('#userForm [name="active"]').checked;
   v.permissions=[...document.querySelectorAll('#userForm [data-permission]:checked')].map(el=>el.dataset.permission);
   await api('user_save',v);toast('员工账户及权限已保存');closeModal();await loadTable();
 }catch(e){toast(e.message,true)}},true);
}

function showPassword(must=false){showModal(must?'首次登录 · 设置个人密码':'修改登录密码',`<form id="pwdForm" class="form-grid"><div class="field full"><label>当前密码</label><input class="input" name="old_password" type="password" required></div><div class="field"><label>新密码（至少12位）</label><input class="input" name="new_password" type="password" minlength="12" required></div><div class="field"><label>再次输入新密码</label><input class="input" name="confirm" type="password" minlength="12" required></div></form>`,async()=>{try{const data=formObj('#pwdForm');if(data.new_password!==data.confirm)throw Error('两次输入的密码不一致');await api('password',data);toast('密码已修改');closeModal();await reloadConf();await navigate(S.route)}catch(e){toast(e.message,true)}},false,must)}
function csvQuote(s){const v=String(s??'');return /[",\n\r]/.test(v)?'"'+v.replaceAll('"','""')+'"':v}
function downloadCsv(name,heads,rows){const content='\ufeff'+[heads,...rows].map(a=>a.map(csvQuote).join(',')).join('\r\n');const blob=new Blob([content],{type:'text/csv;charset=utf-8'});const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download=name+'.csv';document.body.append(a);a.click();setTimeout(()=>{URL.revokeObjectURL(a.href);a.remove()},1000)}
async function exportTable(){try{const query='entity='+encodeURIComponent(S.route)+'&q='+encodeURIComponent(S.search||'');const res=await fetch('/api.php?action=export&'+query,{credentials:'same-origin'});if(!res.ok)throw Error('导出失败，请检查账号权限');const blob=await res.blob();const a=document.createElement('a');a.href=URL.createObjectURL(blob);a.download='mingchi-'+S.route+'-'+new Date().toISOString().slice(0,10)+'.csv';a.click();setTimeout(()=>URL.revokeObjectURL(a.href),1000);toast('已导出列表数据')}catch(e){toast(e.message,true)}}
function exportCurrentPageUnused(){const headers=activeFields(S.route,false).filter(f=>!(S.route==='lots'&&f.field_key==='unit_cost_cny'&&!permitted('finance.view'))),data=S.rows||[];downloadCsv(`${S.route}-${new Date().toISOString().slice(0,10)}`,headers.map(f=>f.label),data.map(r=>headers.map(f=>f.field_key==='tags_json'?(r.tags_json||[]).join('|'):r[f.field_key]??(r.custom_json||{})[f.field_key]??'')));toast('已导出当前页数据（最多50条）')}
function customerTemplate(){downloadCsv('明驰客户导入模板',['客户名称','国家','联系人','邮箱','手机号','商机来源','客户标签'],[['示例客户','Australia','Jason','example@example.com','+61 400 000 000','独立站自然搜索','重点客户|经销商']])}
function parseCSV(text){let first=text.split(/\r?\n/).find(x=>x.trim())||'';
 const delimiters=[',',';','\t'];const count=(s,ch)=>(s.match(new RegExp(ch==='\t'?'\t':ch===';'?';':',','g'))||[]).length;
 const delimiter=delimiters.sort((a,b)=>count(first,b)-count(first,a))[0];
 const rows=[];let row=[],val='',quote=false;
 for(let i=0;i<text.length;i++){const c=text[i];if(quote){if(c==='"'&&text[i+1]==='"'){val+='"';i++;}else if(c==='"')quote=false;else val+=c;}else{if(c==='"')quote=true;else if(c===delimiter){row.push(val);val='';}else if(c==='\n'){row.push(val.replace(/\r$/,''));rows.push(row);row=[];val='';}else val+=c;}}
 if(val!==''||row.length){row.push(val.replace(/\r$/,''));rows.push(row)}return rows.filter(r=>r.some(x=>x.trim())).map(r=>r.map(x=>x.trim()));}
const CSV_FIELDS=[['name','客户名称'],['country','国家/地区'],['contact','联系人'],['email','邮箱'],['phone','电话'],['source','客户来源'],['tags_json','客户标签']];
const MATCH={name:['客户名称','name','customer','company','客户名称/name'],country:['国家','country','地区'],contact:['联系人','contact'],email:['邮箱','email','e-mail'],phone:['手机','手机号','phone','mobile'],source:['来源','source','商机来源'],tags_json:['标签','tags','客户标签']};
async function csvChosen(file){const buffer=await file.arrayBuffer();let text=new TextDecoder('utf-8').decode(buffer);if(text.includes('�'))text=new TextDecoder('gb18030').decode(buffer);text=text.replace(/^\uFEFF/,'');const rows=parseCSV(text);if(rows.length<2)throw Error('CSV没有可导入的客户记录');if(rows.length>1001)throw Error('每次最多1000条，请分批上传');const [headers,...data]=rows;S.csvData={headers,rows:data};S.csvMap={};
 for(const [key] of CSV_FIELDS){const index=headers.findIndex(h=>MATCH[key].some(a=>h.toLowerCase().replace(/\s/g,'').includes(a.toLowerCase())));if(index>=0)S.csvMap[key]=index;}
 showCsvMapping();}
function showCsvMapping(){const c=S.csvData;if(!c)return;
 const row=CSV_FIELDS.map(([key,label])=>`<div class="field"><label>${esc(label)}${key==='name'?' *':''}</label><select data-csv-map="${key}" class="select">${opt('','不导入该字段',S.csvMap[key]??'')}${c.headers.map((x,i)=>opt(i,x,S.csvMap[key]??'' )).join('')}</select></div>`).join('');
 showModal('客户CSV字段映射',`${alertBox('已读取 '+c.rows.length+' 条数据。程序按列名自动匹配，你可以在导入前手动修改匹配关系，避免中文表头编码问题。')}<div class="form-grid">${row}</div><div class="card" style="margin-top:16px"><div class="card-head"><h3>前3条客户数据预览</h3></div><div class="card-body"><div class="table-wrap"><table class="data-table"><thead><tr>${c.headers.map(x=>`<th>${esc(x)}</th>`).join('')}</tr></thead><tbody>${c.rows.slice(0,3).map(r=>`<tr>${r.map(x=>`<td>${esc(x)}</td>`).join('')}</tr>`).join('')}</tbody></table></div></div></div>`,async()=>{try{const map={};document.querySelectorAll('[data-csv-map]').forEach(el=>{if(el.value!=='')map[el.dataset.csvMap]=Number(el.value)});const res=await api('import_customers',{rows:c.rows,mapping:map});toast(`成功导入 ${res.added} 条，跳过 ${res.skipped} 条`);S.csvData=null;closeModal();await loadLookups();await loadTable()}catch(e){toast(e.message,true)}},true);
}
function openCsvImport(){showModal('导入客户 CSV',`<div class="notice">支持 CSV UTF-8 和常见 Excel GBK 编码。下载模板后填写，第一行为表头，系统会在下一步显示实际识别的字段。</div><div class="field"><label>选择客户 CSV 文件</label><input type="file" id="csvFile" class="input" accept=".csv,text/csv"></div><p class="small">建议先备份原始客户表。重复的客户名称与邮箱组合会被跳过，正式系统不会自动覆盖旧客户。</p>`,async()=>{try{const f=$('#csvFile')?.files?.[0];if(!f)throw Error('请先选择CSV文件');if(f.size>2e6)throw Error('CSV文件超过2MB');await csvChosen(f)}catch(e){toast(e.message,true)}},true)}
function previewPhoto(id){showModal('图片预览',`<img class="image-view" src="media.php?id=${Number(id)}" alt="照片预览">`,null,true)}
async function refreshAfterEdit(mod,id){if(mod==='orders')await showOrder(id);else await loadTable()}
// Single event delegation layer: each visible button has a real action or is visibly disabled.
root.addEventListener('submit',async ev=>{if(ev.target.id==='loginForm'){ev.preventDefault();try{const form=Object.fromEntries(new FormData(ev.target));const res=await api('login',form);S.csrf=res.csrf;await boot();toast('登录成功')}catch(e){toast(e.message,true)}}});
document.addEventListener('keydown',e=>{if(e.key==='Escape'&&S.modal&&!S.modal.locked)closeModal()});
document.addEventListener('input',ev=>{const el=ev.target;if(el.closest('#quoteForm')||el.closest('#quoteFeeContainer'))updateQuote()});
document.addEventListener('change',async ev=>{
 const el=ev.target;
 if(el.id==='filterTag'){S.tag=el.value;S.page=1;await loadTable()}
 if(el.id==='showArchivedProducts'){S.showArchivedProducts=el.checked;S.page=1;await loadTable()}
 if(el.id==='showArchivedModule'){S.showArchivedByModule[S.route]=el.checked;S.page=1;await loadTable()}
 if(el.id==='filterWarehouse'){S.warehouse=el.value;S.page=1;await loadTable()}
 if(el.id==='filterOwner'){S.owner=el.value;S.page=1;await loadTable()}
 if(el.id==='filterGrade'){S.grade=el.value;S.page=1;await loadTable()}
 if(el.id==='fieldModule'){S.fieldModule=el.value;renderSettings()}
 if(el.id==='refGroup'){S.refGroup=el.value;renderSettings()}
 if(el.id==='qLot')quoteLotSync();
 if(el.id==='lineLot'){const lot=(S.lookups.lots||[]).find(x=>String(x.id)===el.value);if(lot){$('#lineProduct').value=lot.product_id;$('#lineForm [name="unit_cost_cny"]').value=lot.unit_cost_cny||0;}}
 if(el.id==='qProduct'&&!$('#qLot')?.value){const p=(S.lookups.products||[]).find(x=>String(x.id)===el.value);if(p&&$('#qUnit'))$('#qUnit').value=p.unit;updateQuote()}
 if(el.id==='qCurrency'){if(el.value==='CNY')$('#qFx').value=1;else if(Number($('#qFx')?.value)===1)$('#qFx').value=S.conf.finance.default_usd_rate||7.2;updateQuote()}
});
document.addEventListener('click',async ev=>{
 const img=ev.target.closest('[data-image]');if(img){previewPhoto(img.dataset.image);return}
 const setting=ev.target.closest('[data-setting]');if(setting){S.tab=setting.dataset.setting;await renderSettings();return}
 const nav=ev.target.closest('[data-nav]');if(nav){await navigate(nav.dataset.nav);return}
 const el=ev.target.closest('[data-act]');if(!el)return;
 const action=el.dataset.act,id=el.dataset.id;try{
  switch(action){
   case 'go':await navigate(id);break;
   case 'mobileMenu':$('#sidebar')?.classList.toggle('open');break;
   case 'logout':await api('logout',{});S.user=null;S.conf=null;S.csrf=(await api('session')).csrf;renderLogin();break;
   case 'password':showPassword();break;
   case 'closeModal':if(!S.modal?.locked)closeModal();break;
   case 'modalSave':if(S.modal?.onSave)await S.modal.onSave();break;
   case 'new':await openRecord(S.route);break;
   case 'edit':await openRecord(S.route,id);break;
   case 'productDelete':{
     if(!permitted('products.edit'))throw Error('没有产品删除权限');
     const product=S.rows.find(r=>String(r.id)===String(id));
     if(!confirm(`确定删除产品「${product?.name||('#'+id)}」吗？\n\n删除后将从产品档案隐藏，可在「查看已删除产品」中恢复；库存流水、订单和历史报价不会丢失。\n\n存在库存或订单预留的产品将禁止删除。`))break;
     await api('product_archive',{id:Number(id)});
     toast('产品已删除（归档），需要时可在已删除产品中恢复');
     await loadLookups();await loadTable();break;
   }
   case 'productRestore':{
     if(!permitted('products.edit'))throw Error('没有恢复产品权限');
     await api('product_restore',{id:Number(id)});
     toast('产品已恢复，可重新用于库存管理');
     await loadLookups();await loadTable();break;
   }
   case 'entityArchive':{
     const [entity,rawId]=id.split(':'),record=S.rows.find(x=>String(x.id)===rawId);
     const perms={lots:'lots.edit',customers:'customers.edit',leads:'leads.edit'};
     if(!perms[entity]||!permitted(perms[entity]))throw Error('没有删除权限');
     const name=record?.name||record?.title||record?.lot_code||('#'+rawId);
     const warning=entity==='lots'?'\n批次必须已清空库存和预留，历史流水会保留。':'\n已关联订单、商机和历史资料不会被删除。';
     if(!confirm(`确定删除（归档）「${name}」吗？\n可以在「查看已删除记录」中恢复。${warning}`))break;
     await api('entity_archive',{entity,id:Number(rawId)});
     toast('已归档，可在已删除记录中恢复');await loadLookups();await loadTable();break;
   }
   case 'entityRestore':{
     const [entity,rawId]=id.split(':');
     await api('entity_restore',{entity,id:Number(rawId)});
     toast('已恢复');await loadLookups();await loadTable();break;
   }
   case 'movementReverse':{
     const record=S.rows.find(x=>String(x.id)===String(id));
     if(!permitted('movements.create'))throw Error('没有库存冲正权限');
     if(!confirm(`冲正流水 #${id} 吗？\n\n将创建一条反向库存调整记录，原始流水永久保留。\n若库存不足或已关联订单，系统会拒绝。`))break;
     await api('movement_reverse',{id:Number(id)});
     toast('冲正成功，已生成反向流水');await loadLookups();await loadTable();break;
   }
   case 'tableSearchSubmit':S.search=$('#tableSearch')?.value||'';S.page=1;await loadTable();break;
   case 'pagePrev':if(S.page>1){S.page--;await loadTable()}break;
   case 'pageNext':if(S.page*50<S.total){S.page++;await loadTable()}break;
   case 'applyDates':S.dateFrom=$('#dateFrom').value;S.dateTo=$('#dateTo').value;await refreshReport();break;
   case 'openMove':await openMove();break;
   case 'moveFor':await openMove(id);break;
   case 'transferFor':await transferModal(id);break;
   case 'newExpense':await expenseModal();break;
   case 'orderDetail':await showOrder(id);break;
   case 'orderLineNew':await orderLineModal(id);break;
   case 'orderLineDelete':if(confirm('确认删除订单产品成本明细？')){await api('order_line_delete',{id});await orderDetailRefresh()}break;
   case 'orderCostNew':await expenseModal(id);break;
   case 'costDelete':if(confirm('确认删除这笔费用？')){await api('cost_delete',{id});if(S.route==='orders')await orderDetailRefresh();else await loadTable()}break;
   case 'receiptNew':receiptModal(id);break;
   case 'receiptDelete':if(confirm('确认删除这笔回款记录？')){await api('receipt_delete',{id});await orderDetailRefresh()}break;
   case 'reserveNew':await reserveModal(id);break;
   case 'reserveEdit':{const [oid,lot]=id.split(':');await reserveModal(oid,lot);break}
   case 'quoteFeeAdd':captureQuoteDraft();S.quoteFees.push({label:'自定义费用',amount_cny:0});renderQuotes();break;
   case 'quoteFeeRemove':captureQuoteDraft();S.quoteFees.splice(Number(id),1);renderQuotes();break;
   case 'quoteSave':await saveQuote();break;
   case 'quoteDetail':{const r=await api('detail',null,{entity:'quotes',id});const q=r.record;showModal('历史报价 '+q.quote_code,`<div class="details-grid"><div class="kv"><small>报价币种总额</small><strong>${esc(q.currency)} ${fmt(q.quote_total)}</strong></div><div class="kv"><small>保存汇率</small><strong>${fmt(q.fx_to_cny,4)}</strong></div><div class="kv"><small>预计人民币利润</small><strong>${cny(q.expected_profit_cny)}</strong></div></div><p class="small" style="margin-top:18px">报价 ${esc(q.term)}，保存日期 ${esc(q.created_at)}。每份报价的汇率与成本快照独立保存。</p>`);break}
   case 'userNew':userModal();break;
   case 'userEdit':userModal(id);break;
   case 'importCustomers':openCsvImport();break;
   case 'csvTemplate':customerTemplate();break;
   case 'exportCsv':exportTable();break;
   case 'brandSave':await saveBrand();break;
   case 'financeSave':await saveFinance();break;
   case 'menuUp':reorder(S.conf.nav,Number(id),-1);renderSettings();break;
   case 'menuDown':reorder(S.conf.nav,Number(id),1);renderSettings();break;
   case 'saveMenu':await saveMenu();break;
   case 'sectionUp':reorder(S.conf.settings_sections,Number(id),-1);renderSettings();break;
   case 'sectionDown':reorder(S.conf.settings_sections,Number(id),1);renderSettings();break;
   case 'saveSections':await saveSections();break;
   case 'cardUp':reorder(S.conf.dashboard_cards,Number(id),-1);renderSettings();break;
   case 'cardDown':reorder(S.conf.dashboard_cards,Number(id),1);renderSettings();break;
   case 'saveCards':await saveCards();break;
   case 'cardNew':cardNewModal();break;
   case 'fieldNew':fieldModal();break;
   case 'fieldEdit':fieldModal(id);break;
   case 'fieldUp':{const f=S.conf.fields.filter(x=>x.module===(S.fieldModule||'products')).sort((a,b)=>a.position-b.position);reorder(f,Number(id),-1);renderSettings();break}
   case 'fieldDown':{const f=S.conf.fields.filter(x=>x.module===(S.fieldModule||'products')).sort((a,b)=>a.position-b.position);reorder(f,Number(id),1);renderSettings();break}
   case 'fieldToggle':{const f=S.conf.fields.find(x=>x.module===(S.fieldModule||'products')&&x.field_key===id);if(!f)break;
     if(+f.active)await api('field_disable',{module:f.module,field_key:id});else await api('field_save',{module:f.module,field_key:id,label:f.label,field_type:f.field_type,visible:true,list_visible:true,required_field:!!+f.required_field,position:f.position,options:JSON.parse(f.options_json||'[]'),placeholder:f.placeholder});
     await reloadConf();renderSettings();break}
   case 'saveFieldSort':await saveFieldSort();break;
   case 'refNew':refModal();break;
   case 'refEdit':refModal(id);break;
  }
 }catch(e){toast(e.message,true)}
});
sessionCheck();

/* 담당: 프론트. 화면 상태와 출력 담당. 실제 조회·집계는 PHP API가 담당합니다. */
(()=>{'use strict';
const root=document.getElementById('diamondata-app');if(!root)return;
const $=id=>root.querySelector('#dd-'+id),boot=window.DIAMOND_BOOT||{};
const titles={teams:'구단 종합 비교',players:'선수 검색·성적',contracts:'FA 계약 목록',aggregate:'그룹별 투자 집계',rollup:'구단·시즌별 집계',ranking:'FA 계약 효율 순위',trend:'선수 시즌 성적 추이',watchlist:'관심 선수',detail:'선수 상세 분석',add:'FA 계약 등록'};
const subtitles={teams:'같은 시즌, 구단의 FA 지출과 경기 성적을 한눈에 비교하세요.',players:'등록 선수의 시즌 기록을 살펴보고, 이름을 눌러 계약과 성적을 연결해보세요.',contracts:'선택 시즌에 유효한 FA 계약의 기간·총액·연평균액을 비교하세요.',aggregate:'영입 구단·계약기간을 조합해 FA 투자 규모를 살펴보세요.',rollup:'여러 시즌의 FA 지출을 구단별 소계와 전체 합계로 확인하세요.',ranking:'같은 리그·시즌의 AAV ÷ 시즌 WAR를 비교합니다. 낮을수록 비용 효율이 높습니다.',trend:'선수 이름을 눌러 계약기간과 시즌 성적의 변화를 함께 살펴보세요.',watchlist:'관심 선수 중 2~4명을 선택해 같은 시즌 기록을 비교하세요.',detail:'계약 조건과 확보된 시즌 기록을 함께 확인하세요.',add:'선수와 영입 구단을 선택하고 FA 계약 정보를 입력하세요.'};
const state={view:'teams',league:'KBO',year:2024,team:'',q:'',role:'',sort:'fa_payroll',group:'team',from:2020,span:3,player_id:'',metric:'',contract:'',page:1};
let meta={teams:[],years:[]},data={},watch=new Set(),csrf='',selected=new Set(),requestId=0,jobs=[],csvRows=[],csvCols=[],lastFocus=null,ready=false;
const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const num=(v,d=0)=>v==null||v===''?'—':Number(v).toLocaleString('ko-KR',{minimumFractionDigits:d,maximumFractionDigits:d});
const money=(v,c=state.league==='KBO'?'KRW':'USD')=>v==null?'—':c==='KRW'?num(Number(v)/10000,1)+'억 원':'$'+num(Number(v)/1e6,1)+'M';
const btn=(text,attrs='',cls='secondary')=>`<button type="button" class="${cls}" ${attrs}>${text}</button>`;
const tag=(text,cls='')=>`<span class="tag ${cls}">${esc(text)}</span>`;
const empty=(title,text,action='')=>`<div class="empty"><h2>${esc(title)}</h2><p>${esc(text)}</p>${action}</div>`;
const entity=(name,id,sub='',type='player')=>`<button class="entity" data-${type}="${esc(id)}"><span class="crest">${esc(type==='team'?id.replace('K_','').slice(0,3):name.slice(0,2))}</span><span><strong>${esc(name)}</strong><small>${esc(sub)}</small></span></button>`;
const star=id=>btn(watch.has(id)?'★':'☆',`data-watch="${esc(id)}" aria-label="관심 선수 ${watch.has(id)?'해제':'추가'}" aria-pressed="${watch.has(id)}"`,'star '+(watch.has(id)?'saved':''));
function status(text='',kind=''){ $('status').innerHTML=text?`<div class="notice ${kind}">${esc(text)}</div>`:''; }
function cards(items){return '<div class="stats">'+items.map(([a,b,c])=>`<article class="stat"><span class="stat-label">${esc(a)}</span><strong class="stat-value">${esc(b)}</strong><span class="stat-note">${esc(c)}</span></article>`).join('')+'</div>';}
function table(rows,cols,title,note=''){
 csvRows=rows;csvCols=cols.filter(c=>c.key);const pages=Math.max(1,Math.ceil(rows.length/15));state.page=Math.min(state.page,pages);const visible=rows.slice((state.page-1)*15,state.page*15);
 return `<section class="panel"><div class="panel-head"><div><h2>${esc(title)}</h2><span class="small">${rows.length}개 결과</span></div>${rows.length?btn('↓ CSV 내보내기','data-export'):''}</div>`+
 (rows.length?`<div class="table-wrap" role="region" aria-label="${esc(title)}"><table><caption class="sr-only">${esc(title)}</caption><thead><tr>${cols.map(c=>`<th scope="col" class="${c.num?'num':''}">${esc(c.label)}</th>`).join('')}</tr></thead><tbody>${visible.map(r=>`<tr>${cols.map(c=>`<td class="${c.num?'num':''}">${c.render?c.render(r):esc(r[c.key]??'—')}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`:empty('조건에 맞는 데이터가 없습니다','리그·시즌·구단 또는 검색어를 변경해보세요.'))+
 `<div class="panel-foot"><span>${esc(note)}</span>${pages>1?`<div class="pager">${btn('← 이전',`data-page="${state.page-1}" ${state.page===1?'disabled':''}`)}<span>${state.page} / ${pages}</span>${btn('다음 →',`data-page="${state.page+1}" ${state.page===pages?'disabled':''}`)}</div>`:''}</div></section>`;
}
function controls(){
 $('league').value=state.league;const years=meta.years.filter(y=>y.league_id===state.league).map(y=>Number(y.year_id));if(years.length&&!years.includes(state.year))state.year=years[0];
 $('year').innerHTML=years.map(y=>`<option value="${y}">${y}</option>`).join('');$('year').value=state.year;
 $('team').innerHTML='<option value="">전체 구단</option>'+meta.teams.filter(t=>t.league_id===state.league).map(t=>`<option value="${esc(t.team_id)}">${esc(t.team_name)}</option>`).join('');$('team').value=state.team;
 $('role-label').hidden=!['players','watchlist','trend'].includes(state.view);$('sort-label').hidden=state.view!=='teams';$('group-label').hidden=state.view!=='aggregate';$('from-label').hidden=state.view!=='rollup';$('search-label').hidden=!['teams','players','contracts','watchlist','trend'].includes(state.view);$('filters').hidden=['detail','add'].includes(state.view);
 $('search').placeholder=state.view==='teams'?'구단 이름 검색':'선수 이름 검색';$('search').value=state.q;$('role').value=state.role;$('sort').value=state.sort;$('group').value=state.group;$('from').value=state.from;
}
function chrome(){
 root.dataset.view=state.view; const banner=$('stadium');if(banner)banner.hidden=state.view!=='teams';
 $('title').textContent=titles[state.view];$('crumb').textContent=titles[state.view];$('subtitle').textContent=subtitles[state.view];$('eyebrow').textContent=['teams','players','contracts'].includes(state.view)?'SEASON OVERVIEW':'INVESTMENT ANALYSIS';
 root.querySelectorAll('nav [data-view]').forEach(b=>{const active=b.dataset.view===state.view||(state.view==='detail'&&b.dataset.view==='trend');b.classList.toggle('active',active);if(active)b.setAttribute('aria-current','page');else b.removeAttribute('aria-current');});
 $('watch-count').textContent=watch.size;$('mode').textContent=boot.preview?'공유 SQL · 미리보기':'MariaDB · 연결 화면';$('footnote').textContent=(boot.preview?'2026.11.16 공유 SQL 스냅샷 · ':'')+(state.league==='KBO'?'금액: 억 원 (저장 단위: 만원)':'금액: $M = 백만 달러');
}
async function load(){
 const request=++requestId;chrome();controls();status('');$('body').setAttribute('aria-busy','true');
 try{if(state.view==='add'){data={};render();return;}const result=await window.DIAMOND_API(state.view==='trend'?'players':state.view,state);if(request!==requestId)return;data=result;if(result.watch)watch=new Set(result.watch);if(result.csrf)csrf=result.csrf;render();}
 catch(e){if(request===requestId)$('body').innerHTML=empty('데이터를 불러오지 못했습니다',e.message,btn('다시 시도','data-retry','primary'));}
 finally{if(request===requestId)$('body').removeAttribute('aria-busy');}
}
function go(view,id=''){state.view=view;state.page=1;state.q='';state.role='';selected.clear();if(id){state.player_id=id;state.metric='';state.contract='';}load();}
function teamsView(){
 const rows=data.rows||[],s=data.summary||{};let html=cards([['비교 구단',num(s.team_count)+'개',`${state.league} · ${state.year} 시즌`],['FA 지출 합계',money(s.payroll),'선택 구단의 유효 계약 AAV 합계'],['유효 FA 계약',num(s.contract_count??0)+'건','선택 시즌에 계약기간이 겹치는 계약']]);
 html+=table(rows,[{label:'구단',key:'team_name',render:r=>entity(r.team_name,r.team_id,r.sub_league||state.league,'team')},{label:'FA 지출',key:'fa_payroll',num:true,render:r=>'<strong>'+money(r.fa_payroll)+'</strong>'},{label:'계약 수',key:'contract_count',num:true},{label:'승–패',render:r=>num(r.wins)+' <span class="small">– '+num(r.losses)+'</span>'},{label:'승률',key:'win_pct',num:true,render:r=>num(r.win_pct,3)},{label:'순위',key:'league_rank',num:true,render:r=>tag(r.league_rank==null?'—':r.league_rank+'위',r.league_rank<=3?'green':'')},{label:'포스트시즌',key:'postseason',render:r=>tag(r.postseason||'—',r.postseason&&r.postseason!=='NONE'?'green':'')}],'구단별 FA 지출 및 성적',state.league==='MLB'?'MLB 순위는 AL/NL 내부 순위입니다. 구단을 누르면 계약 내역으로 이동합니다.':'구단을 누르면 해당 시즌의 FA 계약을 확인합니다.');
 const top=data.top||[],max=Math.max(1,...top.map(r=>Number(r.fa_payroll)));
 html+=`<div class="lower-grid"><section class="panel"><div class="panel-head"><h2>FA 지출 상위 구단</h2>${tag('AAV 기준','green')}</div><div class="chart-body">${top.map(r=>`<div class="bar-row"><span class="bar-label" title="${esc(r.team_name)}">${esc(r.team_name)}</span><div class="bar-track"><div class="bar-fill" style="width:${Number(r.fa_payroll)/max*100}%"></div></div><span class="bar-value">${money(r.fa_payroll)}</span></div>`).join('')||'<p class="small">표시할 자료가 없습니다.</p>'}</div><div class="panel-foot">계약 총액이 아닌 연평균액의 시즌별 합계입니다.</div></section><section class="panel"><div class="panel-head"><h2>FA 지출과 승률</h2><span class="small">구단별 분포</span></div><div class="chart-body" id="dd-scatter"></div><div class="panel-foot">지출과 성적의 관계이며 인과관계를 뜻하지 않습니다.</div></section></div>`;
 jobs.push(()=>scatter(rows));return html;
}
function playerColumns(){
 const cols=[{label:'관심',render:r=>star(r.player_id)}];if(state.view==='watchlist')cols.push({label:'비교',render:r=>`<input type="checkbox" data-compare="${esc(r.player_id)}" aria-label="${esc(r.player_name)} 비교 선택" ${selected.has(r.player_id)?'checked':''}>`});
 cols.push({label:'선수',key:'player_name',render:r=>entity(r.player_name,r.player_id,r.primary_pos||'포지션 미수집')},{label:'해당 시즌 구단',key:'team_name'},{label:'경기',key:'games',num:true});
 if(state.league==='MLB')cols.push({label:'WAR',key:'war',num:true,render:r=>num(r.war,1)});
 if(state.role!=='pitch')cols.push({label:'타율',key:'avg',num:true,render:r=>num(r.avg,3)},{label:'OPS',key:'ops',num:true,render:r=>num(r.ops,3)},{label:'HR',key:'hr',num:true,render:r=>num(r.hr)});
 if(state.role!=='bat')cols.push({label:'ERA',key:'era',num:true,render:r=>num(r.era,2)},{label:'이닝*',key:'ip',num:true,render:r=>num(r.ip,1)},{label:'탈삼진',key:'p_so',num:true,render:r=>num(r.p_so)});return cols;
}
function playersView(){
 let html=state.league==='KBO'?'<div class="notice">KBO WAR·생년월일·세부 포지션은 미확보 상태입니다. 타자·투수의 수집 기록을 표시합니다.</div>':'';
 if(state.view==='watchlist')html+=`<div class="compare-strip"><span>${selected.size}명 선택 · 최대 4명</span>${btn('선수 나란히 비교 →','data-compare-open','primary')}</div><p class="data-note">${boot.preview?'미리보기의 관심 목록은 이 화면에서만 유지됩니다.':'관심 목록은 현재 브라우저의 PHP 세션에 저장됩니다.'} 선택 리그·시즌에 기록이 있는 선수만 표시됩니다.</p>`;
 if(state.view==='trend')html+='<div class="notice">선수 이름을 누르면 계약기간을 강조한 성적 그래프와 연도별 기록을 볼 수 있습니다.</div>';
 return html+table(data.rows||[],playerColumns(),'선수 시즌 기록','등록된 분석 대상 선수만 표시합니다. 이적 선수는 구단별로 구분합니다. *이닝은 공유 데이터의 환산 소수값입니다.');
}
function contractsView(){return table(data.rows||[],[{label:'선수',key:'player_name',render:r=>entity(r.player_name,r.player_id,'계약 #'+r.contract_id)},{label:'원소속 → 영입 구단',render:r=>`${esc(r.from_team_name||'자료 없음')} → ${esc(r.to_team_name)}`},{label:'기간',render:r=>`${r.start_year}–${r.end_year} ${tag(r.years+'년')}`},{label:'계약 총액',key:'total_value',num:true,render:r=>money(r.total_value,r.currency)},{label:'AAV',key:'aav',num:true,render:r=>money(r.aav,r.currency)},{label:'관리',render:r=>btn('수정',`data-edit-contract="${r.contract_id}"`)}],'시즌 유효 FA 계약','AAV = 계약 총액 ÷ 계약 연수. 실제 시즌 지급액과는 다릅니다.');}
function analysisView(){
 const rows=data.rows||[];
 if(state.view==='ranking'){
  if(data.unavailable)return `<section class="panel">${empty('KBO WAR 데이터가 필요합니다',data.unavailable,btn('KBO 선수 성적 보기','data-view="players"','primary'))}</section>`;
  return '<div class="notice">WAR가 0 이하이거나 누락된 선수는 제외합니다. 이적 선수의 WAR는 시즌 전체 합계이므로 영입 구단만의 성과와는 다를 수 있습니다.</div>'+table(rows,[{label:'순위',key:'efficiency_rank',render:r=>tag(r.efficiency_rank+'위','green')},{label:'선수',key:'player_name',render:r=>entity(r.player_name,r.player_id,r.team_name)},{label:'시즌 AAV',key:'aav',num:true,render:r=>money(r.aav)},{label:'시즌 WAR',key:'war',num:true,render:r=>num(r.war,1)},{label:'WAR당 비용',key:'cost_per_war',num:true,render:r=>money(r.cost_per_war)},{label:'AAV 4분위',key:'value_quartile',num:true}],'FA 계약 효율 순위','AAV 4분위는 1이 금액 상위 구간입니다. 계약 전체 성과 순위가 아닙니다.');
 }
 if(state.view==='rollup')return table(rows,[{label:'구단 / 합계',key:'team_name',render:r=>r.team_name?esc(r.team_name):tag('전체 합계','green')},{label:'시즌',key:'year_id',render:r=>r.year_id??tag('소계','green')},{label:'FA 지출 합계',key:'payroll',num:true,render:r=>money(r.payroll)},{label:'승수 합계',key:'wins',num:true},{label:'구단·시즌 수',key:'season_count',num:true},{label:'상세',render:r=>r.team_name&&r.year_id?btn('해당 시즌 계약 →',`data-drill="${esc(r.team_name)}" data-year="${r.year_id}"`):'—'}],'구단 → 시즌 → 소계','선택 기간의 시즌 AAV 합계입니다. 계약 총액 합계와는 다릅니다.');
 return table(rows,[{label:state.group==='duration'?'계약기간':state.group==='team_duration'?'영입 구단 × 계약기간':'영입 구단',key:'group_label'},{label:'계약 수',key:'contract_count',num:true},{label:'AAV 합계',key:'payroll',num:true,render:r=>money(r.payroll)},{label:'평균 AAV',key:'avg_aav',num:true,render:r=>money(r.avg_aav)},{label:'최소 AAV',key:'min_aav',num:true,render:r=>money(r.min_aav)},{label:'최대 AAV',key:'max_aav',num:true,render:r=>money(r.max_aav)}],'그룹별 투자 규모','선택 시즌 유효 계약 기준. 투자 규모 집계이며 성과 판정은 아닙니다.');
}
function detailView(){
 const p=data.player;if(!p)return empty('선수를 찾지 못했습니다','선수 검색에서 다시 선택해주세요.',btn('선수 검색','data-view="players"'));
 const series=data.series||[],contracts=data.contracts||[],has=key=>series.some(s=>s[key]!=null);
 const metrics=[['war','WAR'],['ops','OPS'],['era','ERA'],['hr','홈런'],['p_so','탈삼진']].filter(([k])=>has(k));if(!metrics.some(([k])=>k===state.metric))state.metric=metrics[0]?.[0]||'war';
 const c=contracts.find(c=>String(c.contract_id)===state.contract)||contracts.find(c=>c.start_year<=state.year&&c.end_year>=state.year)||contracts.at(-1);state.contract=c?String(c.contract_id):'';const ccy=c?.currency||(p.league_id==='KBO'?'KRW':'USD');
 let html=`<div class="actions" style="margin-bottom:14px">${btn('← 선수 목록','data-view="players"')}</div><section class="hero"><div><p class="eyebrow">PLAYER PROFILE</p><h2>${esc(p.player_name)}</h2><p>${esc(p.team_name||'소속 자료 없음')} · ${esc(p.primary_pos||'포지션 미수집')} · ${esc(p.birth_date||'생년월일 미수집')} · ID ${esc(p.player_id)}</p></div><div class="actions">${star(p.player_id)}<span class="small">관심 선수</span></div></section>`;
 html+=cards([['선택 계약 기간',c?`${c.start_year}–${c.end_year}`:'자료 없음',c?c.years+'년 계약':'계약 정보 없음'],['계약 총액',money(c?.total_value,ccy),c?.to_team_name||''],['연평균액 · AAV',money(c?.aav,ccy),'실제 시즌 지급액과 다를 수 있습니다.']]);
 if(p.league_id==='KBO')html+='<div class="notice amber">KBO 선수는 나이·WAR 미확보로 연령별 에이징커브 대신 계약 전후 시즌 기록을 제공합니다.</div>';
 html+=`<section class="panel"><div class="panel-head"><div><h2>시즌 성적과 계약기간</h2>${tag('음영 = 선택 계약기간','green')}</div><label>표시 계약<select id="dd-contract">${contracts.map(c=>`<option value="${c.contract_id}" ${String(c.contract_id)===state.contract?'selected':''}>${c.start_year}–${c.end_year} · ${esc(c.to_team_name)}</option>`).join('')}</select></label></div><div class="chart-body"><div class="tabs">${metrics.map(([k,label])=>btn(label,`data-metric="${k}" aria-pressed="${state.metric===k}"`,state.metric===k?'selected':'')).join('')}</div><div id="dd-line"></div><p class="data-note">${state.metric==='era'?'ERA는 낮을수록 좋은 지표입니다. ':''}자료가 없는 시즌은 연결하지 않습니다. 여러 구단의 OPS·ERA는 임의 평균내지 않습니다.</p></div></section>`;
 jobs.push(()=>line(series,state.metric,c,p.birth_date));
 const cols=[{label:'시즌',key:'year_id'},{label:'해당 시즌 구단',key:'team_name'},{label:'경기',key:'games',num:true}];if(has('war'))cols.push({label:'WAR',key:'war',num:true,render:r=>num(r.war,1)});if(has('ops'))cols.push({label:'타율',key:'avg',num:true,render:r=>num(r.avg,3)},{label:'OPS',key:'ops',num:true,render:r=>num(r.ops,3)},{label:'HR',key:'hr',num:true});if(has('era'))cols.push({label:'ERA',key:'era',num:true,render:r=>num(r.era,2)},{label:'이닝*',key:'ip',num:true,render:r=>num(r.ip,1)},{label:'탈삼진',key:'p_so',num:true});
 html+=table(data.rows||[],cols,'시즌별 원본 기록','이적 기록은 구단별 표시. *이닝은 공유 데이터의 환산 소수값입니다.');
 if(has('war'))html+=`<section class="panel"><div class="panel-head"><h2>WAR 변화와 이동평균</h2><label>관측 구간<select id="dd-span"><option value="2" ${state.span===2?'selected':''}>최근 2개 관측 시즌</option><option value="3" ${state.span===3?'selected':''}>최근 3개 관측 시즌</option></select></label></div><div class="table-wrap"><table><thead><tr><th>시즌</th><th>WAR</th><th>전년 대비</th><th>관측 누적 WAR</th><th>이동평균</th></tr></thead><tbody>${series.map(s=>`<tr><td>${s.year_id}</td><td>${num(s.war,1)}</td><td>${s.war_change==null?'—':(s.war_change>0?'+':'')+num(s.war_change,1)}</td><td>${num(s.cumulative_war,1)}</td><td>${num(s.moving_avg,2)}</td></tr>`).join('')}</tbody></table></div><div class="panel-foot">최근 N개 관측행 기준. 결측 연도를 0으로 채우지 않으며 통산 전체 기록이 아닙니다.</div></section>`;
 html+=`<section class="panel"><div class="panel-head"><h2>FA 계약 이력</h2></div><div class="table-wrap"><table><thead><tr><th>계약기간</th><th>이전 → 영입 구단</th><th>총액</th><th>AAV</th></tr></thead><tbody>${contracts.map(c=>`<tr><td>${c.start_year}–${c.end_year}</td><td>${esc(c.from_team_name||'—')} → ${esc(c.to_team_name)}</td><td>${money(c.total_value,c.currency)}</td><td>${money(c.aav,c.currency)}</td></tr>`).join('')}</tbody></table></div></section>`;return html;
}
function formView(record=null){
 const r=record||{};const options=(selected,leagueOnly)=>meta.teams.filter(t=>!leagueOnly||t.league_id===state.league).map(t=>`<option value="${esc(t.team_id)}" ${selected===t.team_id?'selected':''}>${esc(t.team_name)}</option>`).join('');
 return `<div class="notice amber">계약 입력 화면입니다. 실제 저장·수정·삭제는 백엔드 연결 전이며 DB에 반영되지 않습니다.</div><section class="panel"><div class="panel-head"><h2>${record?'계약 수정':'새 FA 계약'}</h2>${tag(state.league+' · 입력 단위: '+(state.league==='KBO'?'만원':'달러'))}</div><form id="dd-contract-form"><div class="form-grid"><label>선수<select name="player_id" required><option value="">선수 선택</option>${(meta.players||[]).filter(p=>p.league_id===state.league).map(p=>`<option value="${esc(p.player_id)}" ${r.player_id===p.player_id?'selected':''}>${esc(p.player_name)} · ${esc(p.player_id)}</option>`).join('')}</select></label><label>영입 구단<select name="to_team_id" required><option value="">구단 선택</option>${options(r.to_team_id,true)}</select></label><label>원소속 구단 (선택)<select name="from_team_id"><option value="">자료 없음</option>${options(r.from_team_id,false)}</select></label><label>계약일 (선택)<input name="signed_date" type="date" value="${esc(r.signed_date||'')}"></label><label>시작 연도<input name="start_year" type="number" min="1900" max="2200" value="${r.start_year||state.year}" required></label><label>종료 연도<input name="end_year" type="number" min="1900" max="2200" value="${r.end_year||state.year}" required></label><label>계약 총액 (${state.league==='KBO'?'만원':'달러'})<input name="total_value" type="number" step="1" min="1" value="${r.total_value||''}" required></label><label>보장액 (같은 단위, 선택)<input name="guaranteed_value" type="number" step="1" min="0" value="${r.guaranteed_value??''}"></label></div><div class="panel-foot"><span>AAV와 계약 연수는 최종 저장 시 서버 계산</span><div class="actions"><button type="submit" class="secondary">입력 확인</button><button type="button" class="primary" disabled>저장 · 연결 대기</button></div></div><div id="dd-feedback" role="status" style="padding:0 24px 20px"></div></form></section>`;
}
function render(){jobs=[];chrome();controls();$('body').innerHTML=state.view==='teams'?teamsView():['players','trend','watchlist'].includes(state.view)?playersView():state.view==='contracts'?contractsView():state.view==='detail'?detailView():state.view==='add'?formView():analysisView();jobs.forEach(fn=>fn());}
function svgStart(w,h,title){return `<svg class="plot" viewBox="0 0 ${w} ${h}" role="img" aria-label="${esc(title)}"><title>${esc(title)}</title>`;}
function scatter(rows){
 const box=$('scatter');if(!box)return;const valid=rows.filter(r=>r.fa_payroll!=null&&r.win_pct!=null);if(!valid.length){box.innerHTML=empty('표시할 기록이 없습니다','조회 조건을 변경해주세요.');return;}
 const w=Math.max(240,box.clientWidth||500),h=245,l=54,r=22,t=20,b=48,max=Math.max(1,...valid.map(d=>Number(d.fa_payroll)))*1.1,ys=valid.map(d=>Number(d.win_pct)),lo=Math.max(0,Math.min(...ys)-.05),hi=Math.min(1,Math.max(...ys)+.05),x=n=>l+n/max*(w-l-r),y=n=>h-b-(n-lo)/(hi-lo||1)*(h-t-b);
 let svg=svgStart(w,h,'구단별 FA 지출과 승률');for(let i=0;i<4;i++){const v=lo+(hi-lo)*i/3;svg+=`<line x1="${l}" x2="${w-r}" y1="${y(v)}" y2="${y(v)}" stroke="#e3eaf0"/><text x="${l-8}" y="${y(v)+4}" text-anchor="end">${v.toFixed(3)}</text>`;}
 for(let i=0;i<3;i++){const v=max*i/2;svg+=`<text x="${x(v)}" y="${h-27}" text-anchor="${i===0?'start':i===2?'end':'middle'}">${money(v)}</text>`;}
 valid.forEach(d=>{svg+=`<circle cx="${x(Number(d.fa_payroll))}" cy="${y(Number(d.win_pct))}" r="5" fill="#0a947c" opacity=".8"><title>${esc(d.team_name)} · ${money(d.fa_payroll)} · 승률 ${num(d.win_pct,3)}</title></circle>`;});box.innerHTML=svg+`<text x="${l}" y="13">승률</text><text x="${w-r}" y="${h-6}" text-anchor="end">FA 지출 →</text></svg>`;
}
function line(series,key,contract,birth){
 const box=$('line');if(!box)return;const valid=series.filter(r=>r[key]!=null&&Number.isFinite(Number(r[key])));if(!valid.length){box.innerHTML=empty('이 지표의 기록이 없습니다','다른 지표를 선택해주세요.');return;}
 const w=Math.max(240,box.clientWidth||650),h=270,l=55,r=25,t=30,b=55,years=series.map(s=>Number(s.year_id)),minYear=Math.min(...years),maxYear=Math.max(...years),values=valid.map(s=>Number(s[key])),range=Math.max(...values)-Math.min(...values),lo=Math.min(0,Math.min(...values)-range*.1),hi=Math.max(...values)+(range||1)*.18,x=n=>l+(n-minYear)/Math.max(1,maxYear-minYear)*(w-l-r),y=n=>h-b-(n-lo)/(hi-lo||1)*(h-t-b);
 let svg=svgStart(w,h,'시즌별 '+key.toUpperCase()+' 추이. 음영은 선택 계약기간.');
 if(contract&&contract.end_year>=minYear&&contract.start_year<=maxYear){let a=x(Math.max(minYear,contract.start_year)),z=x(Math.min(maxYear,contract.end_year));svg+=`<rect x="${a}" y="${t}" width="${Math.max(2,z-a)}" height="${h-t-b}" fill="#e4f4eb"/>`;}
 for(let i=0;i<4;i++){const v=lo+(hi-lo)*i/3;svg+=`<line x1="${l}" x2="${w-r}" y1="${y(v)}" y2="${y(v)}" stroke="#e0e9ef"/><text x="${l-8}" y="${y(v)+4}" text-anchor="end">${v.toFixed(key==='ops'?2:1)}</text>`;}
 const step=Math.max(1,Math.ceil((maxYear-minYear)/(w<400?3:4)));for(let yr=minYear;yr<=maxYear;yr+=step)svg+=`<text x="${x(yr)}" y="${h-29}" text-anchor="middle">${yr}</text>`;
 let path='',last=null;for(const row of series){if(row[key]==null){last=null;continue;}path+=(last!==null&&Number(row.year_id)===last+1?' L ':' M ')+x(Number(row.year_id))+' '+y(Number(row[key]));last=Number(row.year_id);}
 svg+=`<path d="${path}" fill="none" stroke="#078570" stroke-width="3"/>`;valid.forEach(s=>{svg+=`<circle cx="${x(Number(s.year_id))}" cy="${y(Number(s[key]))}" r="4" fill="#078570" stroke="#fff" stroke-width="2"><title>${s.year_id}${birth?' · 연말 기준 '+(Number(s.year_id)-Number(birth.slice(0,4)))+'세':''} · ${key.toUpperCase()} ${num(s[key],key==='ops'?3:2)}</title></circle>`;});box.innerHTML=svg+`<text x="${l}" y="17">${key.toUpperCase()}</text><text x="${w-r}" y="${h-6}" text-anchor="end">시즌 →</text></svg>`;
}
function dialog(title,html){lastFocus=document.activeElement;$('dialog-title').textContent=title;$('dialog-body').innerHTML=html;$('dialog').showModal();}
async function compare(){
 if(selected.size<2||selected.size>4){status('비교할 선수를 2~4명 선택해주세요.','amber');return;}
 try{
 const ds=await Promise.all([...selected].map(id=>window.DIAMOND_API('detail',{...state,player_id:id})));
 const metrics=[['구단',d=>d.rows.filter(r=>Number(r.year_id)===state.year).map(r=>r.team_name).join(' / ')||'자료 없음'],['WAR',d=>num(d.series.find(s=>Number(s.year_id)===state.year)?.war,1)],['OPS',d=>num(d.series.find(s=>Number(s.year_id)===state.year)?.ops,3)],['ERA',d=>num(d.series.find(s=>Number(s.year_id)===state.year)?.era,2)],['시즌 유효 AAV',d=>{const cs=d.contracts.filter(c=>c.start_year<=state.year&&c.end_year>=state.year);return cs.length?cs.map(c=>money(c.aav,c.currency)).join(' / '):'유효 계약 없음';}]];
 dialog(`${state.year} · 관심 선수 비교`,`<p>같은 시즌의 기록입니다. 자료 없음은 0이 아닙니다. 여러 구단의 OPS·ERA는 합산하지 않습니다.</p><div class="table-wrap"><table><thead><tr><th>항목</th>${ds.map(d=>`<th>${esc(d.player?.player_name||'자료 없음')}</th>`).join('')}</tr></thead><tbody>${metrics.map(([label,fn])=>`<tr><th>${label}</th>${ds.map(d=>`<td>${esc(fn(d))}</td>`).join('')}</tr>`).join('')}</tbody></table></div>`);
 }catch(e){status(e.message,'amber');}
}
root.addEventListener('click',async e=>{
 const b=e.target.closest('button,a.brand');if(!b)return;
 if(b.matches('a.brand')){e.preventDefault();go('teams');}if(b.dataset.view)go(b.dataset.view);if(b.dataset.player)go('detail',b.dataset.player);if(b.dataset.team){state.team=b.dataset.team;go('contracts');}
 if(b.hasAttribute('data-retry')){if(ready)load();else initialize();}if(b.dataset.metric){state.metric=b.dataset.metric;render();}if(b.dataset.page){state.page=Number(b.dataset.page);render();}
 if(b.dataset.watch){try{watch=new Set(await window.DIAMOND_WATCH(b.dataset.watch,csrf));if(state.view==='watchlist'){selected.delete(b.dataset.watch);await load();}else render();}catch(error){status(error.message,'amber');}}
 if(b.hasAttribute('data-compare-open'))compare();if(b.dataset.drill){state.team=meta.teams.find(t=>t.team_name===b.dataset.drill&&t.league_id===state.league)?.team_id||'';state.year=Number(b.dataset.year);go('contracts');}
 if(b.dataset.editContract){const c=(data.rows||[]).find(c=>String(c.contract_id)===b.dataset.editContract);if(c)dialog('계약 정보 수정',formView(c));}
 if(b.hasAttribute('data-export')){const safe=v=>/^[=+@-]/.test(String(v??''))?"'"+v:String(v??'');const lines=[csvCols.map(c=>c.label),...csvRows.map(r=>csvCols.map(c=>safe(r[c.key])))];const csv='\uFEFF'+lines.map(row=>row.map(v=>'"'+String(v).replaceAll('"','""')+'"').join(',')).join('\r\n');const href=URL.createObjectURL(new Blob([csv],{type:'text/csv;charset=utf-8'}));const a=document.createElement('a');a.href=href;a.download=`DIAMONDATA-${state.view}-${state.league}-${state.year}-raw.csv`;a.click();setTimeout(()=>URL.revokeObjectURL(href),1000);}
});
root.addEventListener('change',e=>{const t=e.target;
 if(t.id==='dd-league'){state.league=t.value;state.team='';state.q='';state.page=1;selected.clear();load();}if(t.id==='dd-year'){state.year=Number(t.value);state.page=1;selected.clear();load();}
 if(t.id==='dd-contract'){state.contract=t.value;render();}if(t.id==='dd-span'){state.span=Number(t.value);load();}
 if(t.hasAttribute('data-compare')){t.checked?selected.add(t.dataset.compare):selected.delete(t.dataset.compare);if(selected.size>4){selected.delete(t.dataset.compare);t.checked=false;status('비교는 최대 4명까지 가능합니다.','amber');}else render();}
});
$('filters').addEventListener('submit',e=>{e.preventDefault();state.team=$('team').value;state.q=$('search').value.trim();state.role=$('role').value;state.sort=$('sort').value;state.group=$('group').value;state.from=Number($('from').value);state.page=1;selected.clear();if(state.view==='rollup'&&state.from>state.year){status('시작 연도는 종료 시즌 이하이어야 합니다.','amber');return;}load();});
root.addEventListener('submit',e=>{if(e.target.id!=='dd-contract-form')return;e.preventDefault();const form=e.target,v=new FormData(form),start=Number(v.get('start_year')),end=Number(v.get('end_year')),total=Number(v.get('total_value')),g=v.get('guaranteed_value');let message='입력 형식을 확인했습니다. 저장 기능은 연결되지 않았으며 DB에는 반영되지 않았습니다.';if(end<start)message='종료 연도는 시작 연도 이상이어야 합니다.';else if(g!==''&&Number(g)>total)message='보장액은 계약 총액 이하여야 합니다.';form.querySelector('#dd-feedback').textContent=message;});
$('reset').onclick=()=>{state.team='';state.q='';state.role='';state.sort='fa_payroll';state.group='team';state.page=1;selected.clear();load();};
$('menu').onclick=()=>{const open=root.querySelector('.rail').classList.toggle('open');$('menu').setAttribute('aria-expanded',String(open));};$('close').onclick=()=>$('dialog').close();$('dialog').addEventListener('close',()=>lastFocus?.focus());
$('help').onclick=()=>dialog('데이터와 계산 기준',`<h3>공유 DB 기준</h3><p>2026.11.16 공유 SQL의 MLB 126명·KBO 132명, 2014–2025 시즌 기록을 사용합니다. 리그 전체 선수 명단이 아닌 등록된 분석 대상입니다.</p><h3>금액</h3><p>FA 지출은 유효 계약의 AAV 합계입니다. 총연봉이나 실제 시즌 지급액이 아닙니다. USD는 달러, KRW는 만원으로 저장되며 화면은 $M·억 원으로 표시합니다. CSV는 원본 저장 단위입니다.</p><h3>결측값과 분석</h3><p>KBO WAR·생년월일·세부 포지션은 미확보 상태입니다. 결측값을 0으로 바꾸지 않습니다. WAR당 비용은 해당 시즌 AAV ÷ WAR이며 계약 전체 성과를 뜻하지 않습니다. 성적 하락만으로 에이징커브를 확정하지 않습니다.</p><h3>업데이트</h3><p>${boot.preview?'이 미리보기는 고정 스냅샷이며 DB와 자동 동기화되지 않습니다. 관심 선택은 새로고침 시 초기화됩니다.':'실제 화면은 MariaDB를 조회합니다. 노션의 SQL 파일을 갱신하는 것만으로 서버 DB가 갱신되지는 않습니다.'}</p>`);
let resizeTimer;if(typeof ResizeObserver!=='undefined')new ResizeObserver(()=>{clearTimeout(resizeTimer);resizeTimer=setTimeout(()=>jobs.forEach(fn=>fn()),120);}).observe($('body'));
async function initialize(){try{meta=await window.DIAMOND_API('meta');ready=true;watch=new Set(meta.watch||[]);csrf=meta.csrf||'';chrome();controls();await load();}catch(e){chrome();status(e.message,'amber');$('body').innerHTML=empty('데이터 연결이 필요합니다','미리보기 HTML을 열거나 XAMPP에서 최신 DB 설치 상태를 확인해주세요.',btn('다시 시도','data-retry'));}}
initialize();
})();

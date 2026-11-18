const colors=['#087f70','#4f70ce','#c17a26','#ac4c84'];
const escape=s=>String(s).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('"','&quot;');
export function mountAnalysisChart({history,getCandidates,getYear,getView}){
 const section=document.createElement('section');section.className='analysis-chart card';section.hidden=true;
 document.querySelector('.data-panel').after(section);
 let chosen=[],mode='efficiency',signature='';
 function value(p){return mode==='salary'?p.salary:mode==='war'?p.war:p.war>0?p.salary/p.war:null}
 function draw(){const view=getView();section.hidden=!['trend','ranking'].includes(view);if(section.hidden)return;
 const list=getCandidates(),available=new Set(list.map(p=>p.id));chosen=chosen.filter(id=>available.has(id));
 const sig=list.map(p=>p.id).join(',');if(sig!==signature){if(!chosen.length)chosen=list.slice(0,3).map(p=>p.id);signature=sig}
 const selected=list.filter(p=>chosen.includes(p.id));
 section.innerHTML=`<div class="card-title"><div><div class="eyebrow">INVESTMENT ANALYSIS</div><h2>${view==='trend'?'선수별 시즌 곡선 비교':'연봉과 선수 기여도 분포'}</h2><p>상단 팀·포지션·FA 여부 필터와 연동됩니다.</p></div><label class="chart-metric">표시 지표 <select id="chartmetric"><option value="efficiency">WAR당 시즌 연봉 ↓</option><option value="war">선수 기여도 WAR ↑</option><option value="salary">시즌 연봉 ($M)</option></select></label></div><div class="chart-select"><label>비교 선수 <select id="chartplayer"><option value="">선수 이름 선택 · 최대 4명</option>${list.filter(p=>!chosen.includes(p.id)).map(p=>`<option value="${p.id}">${escape(p.name)} · ${p.position}</option>`).join('')}</select></label><span>${list.length}명 중 ${chosen.length}명 선택</span><button class="text-button" id="chartclear">선택 지우기</button></div><div class="chart-chips">${selected.map((p,i)=>`<button data-remove="${p.id}" style="--series:${colors[i]}"><i></i>${escape(p.name)} <span aria-hidden="true">×</span><span class="sr-only">비교에서 제외</span></button>`).join('')}</div><div class="chart-svg"></div><p class="chart-explanation"></p><details class="chart-values"><summary>그래프 수치 표로 보기</summary><div></div></details>`;
 section.querySelector('#chartmetric').value=mode;section.querySelector('.chart-metric').hidden=view==='ranking';
 section.querySelector('#chartmetric').onchange=e=>{mode=e.target.value;draw()};
 section.querySelector('#chartplayer').onchange=e=>{if(e.target.value&&chosen.length<4)chosen.push(e.target.value);draw()};
 section.querySelector('#chartplayer').disabled=chosen.length>=4;
 section.querySelector('#chartclear').onclick=()=>{chosen=[];draw()};
 section.querySelectorAll('[data-remove]').forEach(b=>b.onclick=()=>{chosen=chosen.filter(id=>id!==b.dataset.remove);draw()});
 if(!list.length){section.querySelector('.chart-svg').innerHTML='<div class="empty">조건에 맞는 선수가 없습니다.</div>';return}
 const width=1000,height=350,left=70,right=35,top=34,bottom=55,w=width-left-right,h=height-top-bottom;
 const year=getYear();const series=selected.map(p=>({p,values:history.filter(x=>x.id===p.id&&x.year<=year).sort((a,b)=>a.year-b.year)}));
 const allYears=[...new Set(series.flatMap(s=>s.values.map(p=>p.year)))].sort();
 const data=view==='ranking'?list.map(p=>p.war):series.flatMap(s=>s.values.map(value)).filter(v=>v!=null);
 const lo=Math.min(0,...data),hi=Math.max(1,...data),range=hi-lo;
 const y=v=>top+h-(v-lo)/(range*1.12)*h;
 const x=v=>view==='ranking'?left+v/Math.max(1,...list.map(p=>p.salary))*w:left+(v-(allYears[0]||year))/Math.max(1,(allYears.at(-1)||year)-(allYears[0]||year))*w;
 let svg=`<svg viewBox="0 0 ${width} ${height}" role="img" aria-label="${view==='trend'?'선수별 시즌 추이':'선수 연봉 대비 WAR 분포'}"><title>선택 선수의 가상 데이터 비교 그래프</title>`;
 for(let i=0;i<=4;i++){const v=lo+range*1.12*i/4,yy=y(v);svg+=`<line x1="${left}" x2="${width-right}" y1="${yy}" y2="${yy}" stroke="#e6edf2"/><text x="${left-13}" y="${yy+4}" text-anchor="end">${v.toFixed(1)}</text>`}
 svg+=`<text x="${left}" y="17">${view==='ranking'?'WAR ↑':mode==='efficiency'?'백만 달러 / WAR · 낮을수록 효율적':mode==='war'?'WAR':'백만 달러 ($M)'}</text>`;
 if(view==='trend'){
 for(const yr of allYears)svg+=`<text x="${x(yr)}" y="${height-25}" text-anchor="middle">${yr}</text>`;
 series.forEach((s,i)=>{let last=null,path='';s.values.forEach(p=>{const v=value(p);if(v==null){last=null;return}const pt=[x(p.year),y(v)];if(last&&p.year===last.year+1){const dx=(pt[0]-last.x)/3;path+=s.values.length===2?` L ${pt[0]} ${pt[1]}`:` C ${last.x+dx} ${last.y},${pt[0]-dx} ${pt[1]},${pt[0]} ${pt[1]}`}else path+=` M ${pt[0]} ${pt[1]}`;last={x:pt[0],y:pt[1],year:p.year}});svg+=`<path d="${path}" fill="none" stroke="${colors[i]}" stroke-width="3"/>`;s.values.forEach(p=>{const v=value(p);if(v==null)return;svg+=`<circle cx="${x(p.year)}" cy="${y(v)}" r="5" fill="${colors[i]}" stroke="white" stroke-width="2" tabindex="0"><title>${escape(s.p.name)} · ${p.year}: ${v.toFixed(2)}</title></circle>`})});
 }else{
 for(let i=0;i<=5;i++){const n=Math.max(...list.map(p=>p.salary))*i/5;svg+=`<text x="${x(n)}" y="${height-25}" text-anchor="middle">$${n.toFixed(0)}M</text>`}
 list.forEach(p=>{const index=selected.findIndex(s=>s.id===p.id);svg+=`<circle cx="${x(p.salary)}" cy="${y(p.war)}" r="${index>=0?7:4}" fill="${index>=0?colors[index]:'#b8c6d3'}" opacity="${index>=0?1:.6}" tabindex="0"><title>${escape(p.name)} · 연봉 $${p.salary.toFixed(1)}M · WAR ${p.war.toFixed(1)} · WAR당 $${(p.salary/p.war).toFixed(2)}M</title></circle>`});svg+=`<text x="${width-right}" y="${height-3}" text-anchor="end">시즌 연봉 → · 왼쪽 위일수록 적은 비용으로 높은 기여도</text>`;
 }
 svg+='</svg>';section.querySelector('.chart-svg').innerHTML=selected.length||view==='ranking'?svg:'<div class="empty">비교할 선수를 선택하세요.</div>';
 section.querySelector('.chart-explanation').textContent=view==='trend'?'점은 실제 예시 시즌 기록이고 곡선은 시각적 연결입니다. 중간값을 추정한 결과가 아닙니다. WAR ≤ 0인 시즌의 비용 효율은 표시하지 않습니다. 두 시즌만 있으면 직선으로 연결됩니다.':'현재 선택 시즌의 분포입니다. 색 점은 선택 선수이며 회색 점은 필터에 해당하는 전체 선수입니다. 이 그래프의 비용은 시즌 연봉이고, 위 FA 계약 순위의 비용은 AAV입니다.';
 const detail=section.querySelector('.chart-values div');
 detail.innerHTML='<div class="table-wrap"><table><thead><tr><th>선수</th><th>시즌</th><th>시즌 연봉</th><th>WAR</th><th>WAR당 연봉</th></tr></thead><tbody>'+series.flatMap(s=>(view==='ranking'?s.values.filter(p=>p.year===year):s.values).map(p=>`<tr><td>${escape(s.p.name)}</td><td>${p.year}</td><td>$${p.salary.toFixed(1)}M</td><td>${p.war.toFixed(1)}</td><td>${p.war>0?'$'+(p.salary/p.war).toFixed(2)+'M':'—'}</td></tr>`)).join('')+'</tbody></table></div>';
 }
 return {render:draw};
}

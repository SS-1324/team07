/* 미리보기 전용. 실제 PHP 화면은 이 파일을 로드하지 않습니다.
   브라우저 안에서 공유 SQL 스냅샷으로 서버 응답을 재현합니다. */
(()=>{
 const tables=Object.fromEntries(Object.entries(window.DIAMOND_SNAPSHOT).map(([name,t])=>[name,t.rows.map(row=>Object.fromEntries(t.columns.map((k,i)=>[k,row[i]])))]));
 const teams=new Map(tables.teams.map(t=>[t.team_id,t])),players=new Map(tables.players.map(p=>[p.player_id,p]));let watch=new Set();
 const name=p=>p.name_kor||[p.name_first,p.name_last].filter(Boolean).join(' ')||p.player_id;
 const sum=(rs,k)=>rs.reduce((s,r)=>s+Number(r[k]||0),0),match=(n,q)=>String(n||'').toLowerCase().includes(String(q||'').toLowerCase());
 const contract=c=>({...c,player_name:name(players.get(c.player_id)),from_team_name:teams.get(c.from_team_id)?.team_name||null,to_team_name:teams.get(c.to_team_id)?.team_name,league_id:teams.get(c.to_team_id)?.league_id,currency:teams.get(c.to_team_id)?.league_id==='KBO'?'KRW':'USD'});
 function detail(id,span){
  const p=players.get(id);if(!p)return {player:null,rows:[],series:[],contracts:[]};
  const rows=tables.player_seasons.filter(s=>s.player_id===id).map(s=>({...s,team_name:teams.get(s.team_id)?.team_name,league_id:teams.get(s.team_id)?.league_id})).sort((a,b)=>a.year_id-b.year_id||a.stint-b.stint);
  const years=[...new Set(rows.map(r=>r.year_id))].sort((a,b)=>a-b);let cumulative=null;
  const series=years.map(y=>{const rs=rows.filter(r=>r.year_id===y);return{year_id:y,stint_count:rs.length,war:rs.every(r=>r.war!=null)?sum(rs,'war'):null,ops:rs.length===1?rs[0].ops:null,era:rs.length===1?rs[0].era:null,hr:rs.some(r=>r.hr!=null)?sum(rs,'hr'):null,p_so:rs.some(r=>r.p_so!=null)?sum(rs,'p_so'):null};});
  series.forEach((s,i)=>{const prev=series[i-1];s.war_change=prev&&s.year_id-prev.year_id===1&&s.war!=null&&prev.war!=null?s.war-prev.war:null;if(s.war!=null)cumulative=(cumulative??0)+s.war;s.cumulative_war=cumulative;const rs=series.slice(Math.max(0,i-span+1),i+1).filter(r=>r.war!=null);s.moving_avg=rs.length?sum(rs,'war')/rs.length:null;});
  const t=teams.get(p.current_team_id);return {player:{...p,player_name:name(p),team_name:t?.team_name,league_id:t?.league_id},rows,series,contracts:tables.fa_contracts.filter(c=>c.player_id===id).map(contract).sort((a,b)=>a.start_year-b.start_year)};
 }
 window.DIAMOND_WATCH=async id=>{if(!players.has(id))throw new Error('존재하지 않는 선수입니다.');watch.has(id)?watch.delete(id):watch.add(id);return [...watch];};
 window.DIAMOND_API=async(view,f={})=>{
  const league=f.league||'KBO',year=Number(f.year||2024),team=f.team||'',q=f.q||'',eligible=t=>t?.league_id===league&&(!team||t.team_id===team);let result={rows:[]};
  if(view==='meta')result={players:tables.players.map(p=>({player_id:p.player_id,player_name:name(p),league_id:teams.get(p.current_team_id)?.league_id})),leagues:tables.leagues,teams:[...tables.teams].sort((a,b)=>a.team_name.localeCompare(b.team_name)),years:[...new Set(tables.team_seasons.map(s=>teams.get(s.team_id).league_id+'|'+s.year_id))].map(s=>{const[l,y]=s.split('|');return{league_id:l,year_id:Number(y)};}).sort((a,b)=>b.year_id-a.year_id)};
  if(view==='teams'){
   const rows=tables.team_seasons.filter(s=>s.year_id===year&&eligible(teams.get(s.team_id))&&match(teams.get(s.team_id).team_name,q)).map(s=>({...teams.get(s.team_id),...s,contract_count:tables.fa_contracts.filter(c=>c.to_team_id===s.team_id&&c.start_year<=year&&c.end_year>=year).length}));
   const top=[...rows].sort((a,b)=>b.fa_payroll-a.fa_payroll||a.team_id.localeCompare(b.team_id)).slice(0,6);rows.sort((a,b)=>f.sort==='team_name'?a.team_name.localeCompare(b.team_name):Number(b[f.sort==='wins'?'wins':'fa_payroll'])-Number(a[f.sort==='wins'?'wins':'fa_payroll'])||a.team_id.localeCompare(b.team_id));result={rows,top,summary:{team_count:rows.length,payroll:sum(rows,'fa_payroll'),contract_count:sum(rows,'contract_count')}};
  }
  if(view==='players'||view==='watchlist'){
   const rows=tables.player_seasons.filter(s=>s.year_id===year&&eligible(teams.get(s.team_id))&&(view!=='watchlist'||watch.has(s.player_id))&&(f.role!=='bat'||s.pa!=null)&&(f.role!=='pitch'||s.ip!=null)).map(s=>({...players.get(s.player_id),...s,player_name:name(players.get(s.player_id)),team_name:teams.get(s.team_id).team_name,league_id:league})).filter(p=>match(p.player_name,q)||match(p.player_id,q));rows.sort((a,b)=>a.player_name.localeCompare(b.player_name,'ko')||a.team_name.localeCompare(b.team_name)||a.stint-b.stint);result={rows};
  }
  if(view==='contracts')result={rows:tables.fa_contracts.filter(c=>eligible(teams.get(c.to_team_id))&&c.start_year<=year&&c.end_year>=year).map(contract).filter(c=>match(c.player_name,q)).sort((a,b)=>b.aav-a.aav||a.contract_id-b.contract_id)};
  if(view==='detail')result=detail(f.player_id,Number(f.span||3));
  if(view==='aggregate'){
   const groups=new Map();tables.fa_contracts.filter(c=>eligible(teams.get(c.to_team_id))&&c.start_year<=year&&c.end_year>=year).forEach(c=>{const duration=c.years<=3?'1–3년':c.years<=5?'4–5년':'6년 이상';const key=f.group==='duration'?duration:f.group==='team_duration'?teams.get(c.to_team_id).team_name+' · '+duration:teams.get(c.to_team_id).team_name;if(!groups.has(key))groups.set(key,[]);groups.get(key).push(c);});result={rows:[...groups].map(([key,rs])=>({group_label:key,contract_count:rs.length,payroll:sum(rs,'aav'),avg_aav:sum(rs,'aav')/rs.length,min_aav:Math.min(...rs.map(r=>r.aav)),max_aav:Math.max(...rs.map(r=>r.aav))})).sort((a,b)=>b.payroll-a.payroll)};
  }
  if(view==='rollup'){
   if(Number(f.from)>year)throw new Error('시작 연도는 종료 시즌 이하이어야 합니다.');const groups=new Map(),all=tables.team_seasons.filter(s=>eligible(teams.get(s.team_id))&&s.year_id>=Number(f.from||2020)&&s.year_id<=year);all.forEach(s=>{const n=teams.get(s.team_id).team_name;if(!groups.has(n))groups.set(n,[]);groups.get(n).push(s);});const rows=[];
   for(const[n,rs]of [...groups].sort((a,b)=>a[0].localeCompare(b[0]))){rs.sort((a,b)=>a.year_id-b.year_id);rs.forEach(r=>rows.push({team_name:n,year_id:r.year_id,payroll:r.fa_payroll,wins:r.wins,season_count:1}));rows.push({team_name:n,year_id:null,payroll:sum(rs,'fa_payroll'),wins:sum(rs,'wins'),season_count:rs.length});}if(all.length)rows.push({team_name:null,year_id:null,payroll:sum(all,'fa_payroll'),wins:sum(all,'wins'),season_count:all.length});result={rows};
  }
  if(view==='ranking'){
   if(league==='KBO')result={rows:[],unavailable:'KBO WAR 미확보로 효율 순위를 제공하지 않습니다. 계약 규모와 시즌 성적을 각각 확인해주세요.'};
   else{const rows=tables.fa_contracts.filter(c=>eligible(teams.get(c.to_team_id))&&c.start_year<=year&&c.end_year>=year).map(c=>{const rs=tables.player_seasons.filter(s=>s.player_id===c.player_id&&s.year_id===year),war=rs.length&&rs.every(s=>s.war!=null)?sum(rs,'war'):null;return {...c,player_name:name(players.get(c.player_id)),team_name:teams.get(c.to_team_id).team_name,war,cost_per_war:war>0?c.aav/war:null};}).filter(c=>c.cost_per_war!=null).sort((a,b)=>a.cost_per_war-b.cost_per_war||a.contract_id-b.contract_id);
    const amounts=[...rows].sort((a,b)=>b.aav-a.aav||a.contract_id-b.contract_id),n=amounts.length,base=Math.floor(n/4),rest=n%4;let pos=0;for(let bucket=1;bucket<=4;bucket++)for(let i=0;i<base+(bucket<=rest?1:0);i++)amounts[pos++].value_quartile=bucket;rows.forEach((r,i)=>r.efficiency_rank=i&&r.cost_per_war===rows[i-1].cost_per_war?rows[i-1].efficiency_rank:i+1);result={rows};}
  }
  return {...result,watch:[...watch],csrf:'preview-only'};
 };
})();

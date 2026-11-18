// Reproducible synthetic data. No figures represent actual clubs or league rules.
export const teamInfo=[['NYB','뉴욕 블루스','NY','동부','#254f86'],['BOF','보스턴 폭스','BO','동부','#bd4357'],['LAW','LA 웨이브','LA','서부','#3675bf'],['SFS','SF 스타즈','SF','서부','#cb703b'],['CHH','시카고 호크스','CH','중부','#6a5a99'],['SEA','시애틀 앵커스','SE','서부','#247e79'],['ATE','애틀랜타 이글스','AT','동부','#3b607b'],['DED','디트로이트 디어스','DE','중부','#8b6c42']];
export const years=[2024,2023];
export const money=n=>n==null?'—':'$'+n.toLocaleString('en-US',{minimumFractionDigits:1,maximumFractionDigits:1})+'M';
export const pct=n=>(n*100).toFixed(1)+'%';
const round=n=>Math.round(n*10)/10;
export const players=[];
for(const year of years) for(let t=0;t<teamInfo.length;t++) for(let p=0;p<8;p++){
 const pos=['SP','OF','SS','1B','SP','C','RP','OF'][p];const fa=p<[3,4,5,2,4,3,5,2][t];const aav=round(6+((t*11+p*7+p*p)%29));const salary=round(Math.max(1,aav+(year===2024?2:-2)+(fa?0:-3)));
 const war=round(0.5+((t*7+p*3+(year-2023)*4)%49)/10);const yearsLong=[5,4,3,6,1,2,1,2][p];const start=fa?(yearsLong===1?year:2021+p%3):p===5||p===7?2023:year;const end=start+yearsLong-1;
 players.push({id:teamInfo[t][0]+'-'+p,year,teamId:teamInfo[t][0],name:['제임스 밀러','다니엘 리','알렉스 김','루크 윌슨','에릭 한','노아 박','라이언 조','벤 테일러'][p]+' '+String.fromCharCode(65+t),position:pos,salary,aav,total:round(aav*yearsLong),years:yearsLong,start,end,fa,type:fa?(p%2?'FA 재계약':'FA 영입'):'일반 계약',war,games:pos==='SP'?29:pos==='RP'?62:142+p,innings:pos==='SP'?round(152+t*4+p):pos==='RP'?round(58+t):null,hr:!['SP','RP'].includes(pos)?14+t*2+p:null,ops:!['SP','RP'].includes(pos)?round(720+t*8+p*5)/1000:null,era:['SP','RP'].includes(pos)?(2.7+(t+p)/10):null,faEligible:!fa&&p===6?'자격 자료 없음':'계약 만료만 확인'});
}
export function getTeams(year){return teamInfo.map((a,i)=>{const roster=players.filter(p=>p.teamId===a[0]&&p.year===year);const pay=round(roster.reduce((s,p)=>s+p.salary,0));const fas=roster.filter(p=>p.fa);const faCost=round(fas.reduce((s,p)=>s+p.aav,0));const faWar=round(fas.reduce((s,p)=>s+p.war,0));const wins=74+(i*7+(year-2023)*3)%28;return{id:a[0],name:a[1],abbr:a[2],division:a[3],color:a[4],pay,faCost,faShare:faCost/pay,faWar,wins,losses:162-wins,winPct:wins/162,capBase:round(pay+4+i),cap:190,upcoming:roster.filter(p=>p.end===year).length,roster}}).sort((a,b)=>b.wins-a.wins).map((t,i)=>({...t,rank:i+1}))}
export function groupContracts(list,key){const groups=new Map();for(const p of list.filter(p=>p.fa)){const k=key==='position'?p.position:p.years<=3?'1–3년':p.years<=5?'4–5년':'6년 이상';if(!groups.has(k))groups.set(k,{name:k,count:0,cost:0,war:0});const g=groups.get(k);g.count++;g.cost+=p.aav;g.war+=p.war}return[...groups.values()].map(g=>({...g,cost:round(g.cost),war:round(g.war),average:g.cost/g.count,efficiency:g.war>0?g.cost/g.war:null}))}
export function rankContracts(list){return list.filter(p=>p.fa&&p.war>0).map(p=>({...p,efficiency:p.aav/p.war})).sort((a,b)=>a.efficiency-b.efficiency)}

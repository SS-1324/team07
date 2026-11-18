import test from 'node:test';
import assert from 'node:assert/strict';
import {getTeams,players,groupContracts,rankContracts} from './model.mjs';
test('team payroll equals roster salary, FA totals use AAV',()=>{for(const t of getTeams(2024)){assert.ok(Math.abs(t.pay-t.roster.reduce((s,p)=>s+p.salary,0))<.01);assert.ok(Math.abs(t.faCost-t.roster.filter(p=>p.fa).reduce((s,p)=>s+p.aav,0))<.01);assert.equal(t.wins+t.losses,162)}});
test('group totals reconcile to filtered contracts',()=>{const list=players.filter(p=>p.year===2024&&p.teamId==='NYB');const groups=groupContracts(list,'position');assert.equal(groups.reduce((s,g)=>s+g.count,0),list.filter(p=>p.fa).length);assert.ok(Math.abs(groups.reduce((s,g)=>s+g.cost,0)-getTeams(2024).find(t=>t.id==='NYB').faCost)<.01)});
test('efficiency rankings exclude zero/negative WAR and non-FA',()=>{const result=rankContracts([{fa:true,war:0,aav:5},{fa:true,war:-1,aav:4},{fa:false,war:5,aav:1},{fa:true,war:2,aav:10},{fa:true,war:4,aav:8}]);assert.deepEqual(result.map(p=>p.efficiency),[2,5])});
test('sample seasons have active contracts and varied FA exposure',()=>{for(const year of [2023,2024]){const teams=getTeams(year);assert.ok(new Set(teams.map(t=>t.faShare.toFixed(2))).size>3);for(const t of teams)for(const p of t.roster)assert.ok(p.start<=year&&p.end>=year,p.id+' '+year)}});

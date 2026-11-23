"""Render PHP pages against config/config.php. Requires a running test DB."""
import os,pathlib,subprocess
ROOT=pathlib.Path(__file__).resolve().parents[1]
CASES={
 'teams-kbo':('teams/list','league=KBO&year=2024'),
 'teams-mlb':('teams/list','league=MLB&year=2024'),
 'empty':('teams/list','q=NOT_A_REAL_TEAM'),
 'players-kbo':('players/search','league=KBO'),
 'players-mlb':('players/search','league=MLB'),
 'escape':('players/search','name=%22%3E%3Cscript%3Ealert(1)%3C/script%3E'),
 'detail':('players/detail','player_id=judgeaa01'),
 'detail-kbo':('players/detail','player_id=k_anchihong'),
 'missing':('players/detail','player_id=not_real'),
 'player-edit':('players/edit','player_id=judgeaa01'),
 'watchlist':('players/watchlist',''),
 'compare':('players/compare','ids[]=judgeaa01&ids[]=seageco01'),
 'compare-invalid':('players/compare','ids[]=judgeaa01'),
 'contracts':('contracts/list','league=MLB&season=2024'),
 'add':('contracts/add',''),
 'edit':('contracts/edit','contract_id=1'),
 'aggregate':('analysis/aggregate','league=MLB&submitted=1&group[]=role&group[]=years_band'),
 'rollup':('analysis/rollup','league=MLB&level=year'),
 'ranking':('analysis/ranking','league=MLB&metric=war'),
 'ranking-kbo':('analysis/ranking','league=KBO&metric=ops'),
 'ranking-era':('analysis/ranking','league=KBO&metric=era'),
 'ranking-empty':('analysis/ranking','league=KBO&metric=war'),
 'window':('analysis/window','league=MLB&player_id=judgeaa01&year_from=2020'),
 'window-kbo':('analysis/window','league=KBO&player_id=k_anchihong&metric=ops'),
}
if __name__=='__main__':
 out=ROOT/'tests/.output';out.mkdir(exist_ok=True)
 for name,(page,query) in CASES.items():
  r=subprocess.run([os.environ.get('PHP_BIN','php'),'-d','session.save_path='+str(out),str(ROOT/'tests/render.php'),'pages/'+page+'.php',query],capture_output=True,text=True)
  if r.returncode or r.stderr:raise RuntimeError(name+': '+r.stderr)
  (out/(name+'.html')).write_text(r.stdout)
 print('PASS: rendered',len(CASES),'cases')

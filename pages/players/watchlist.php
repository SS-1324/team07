<?php
require_once __DIR__.'/../../layout/ui.php';
ui_start('관심 선수','watchlist','관심 선수는 세션에 최대 10명까지 저장됩니다.');$rows=players_by_ids(watchlist_ids());
echo '<form id="compare-form" method="get" action="'.h(url('pages/players/compare.php')).'"><span id="compare-inputs"></span><button class="primary">선택한 2~4명 비교</button></form>';
ui_table('내 관심 선수',$rows,['pick'=>['비교',fn($r)=>'<input type="checkbox" data-pick value="'.h($r['player_id']).'" aria-label="'.h($r['player_name']).' 비교 선택">'],'watch'=>['관심',fn($r)=>ui_watch($r['player_id'],'pages/players/watchlist.php')],'player_name'=>['선수','ui_player'],'league_id'=>'리그','team_name'=>'현재 구단','contracts'=>'계약 수','total_war'=>ui_number('total_war','기록 전체 WAR',1),'last_year'=>'최근 기록']);
ui_end();

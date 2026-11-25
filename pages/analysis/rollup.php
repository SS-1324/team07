<?php
require_once __DIR__.'/../../layout/ui.php';
$f=ui_filters('rollup');$f['level']=input_choice('level',['league','team','year'],$f['level']??'team');$f['team']=input_str('team',$f['team']??'');filters_remember('rollup',$f);$rows=analysis_rollup($f['league'],$f['year_from'],$f['year_to'],$f['level'],$f['team']);ui_start('구단·시즌별 집계','rollup','리그 합계에서 구단, 시즌, 계약까지 내려가며 확인하세요.');echo '<form class="filterbar" method="get">';ui_range($f);ui_select('level','상세 단계',['league'=>'리그 합계','team'=>'구단 소계','year'=>'시즌 상세'],$f['level']);ui_select('team','구단',ui_teams($f['league']),$f['team']);ui_submit('pages/analysis/rollup.php');echo '</form>';ui_note('구단·리그 소계는 배경색으로 구분합니다. 구단별 FA 지출 합계이며 전체 선수 연봉 합계는 아닙니다.');$columns = ['row_level'=>['단계',fn($r)=>h(['league'=>'리그 합계','team'=>'구단 소계','year'=>'시즌'][$r['row_level']])],'team_name'=>['구단 / 드릴다운',function($r)use($f){$next=$f;if($r['row_level']==='league'){$next['level']='team';$next['team']='';unset($next['detail_year']);}elseif($r['row_level']==='team'){$next['level']='year';$next['team']=$r['team_id'];unset($next['detail_year']);}else{$next['level']='year';$next['team']=$r['team_id'];$next['detail_year']=$r['year_id'];}return ui_link('pages/analysis/rollup.php',($r['team_name']??$r['league_id']).' →',$next);}],'year_id'=>'시즌','seasons'=>'시즌 수','fa_payroll'=>ui_money('fa_payroll','FA 지출 합계'),'wins'=>'승','losses'=>'패','win_pct'=>ui_number('win_pct','승률',3),'postseason_cnt'=>'PS 진출 수','payroll_per_win'=>ui_money('payroll_per_win','승당 FA 지출'),'contracts_signed'=>'신규 계약'];
if ($f['level'] === 'year') {
    $columns['league_rank'] = '시즌 순위';
    $columns['prev_year'] = '이전 기록 연도';
    $columns['prev_rank'] = '이전 순위';
    $columns['rank_pct_change'] = ui_number('rank_pct_change', '순위 백분위 변화 (%p)', 1);
    ui_note('순위 백분위 변화는 같은 구단의 이전 기록 시즌과 비교합니다. 양수면 순위 상승, 음수면 하락입니다.');
}
ui_table('롤업 결과', $rows, $columns);
$year=input_int('detail_year');if($year&&$f['team']!=='')ui_table($year.' 유효 FA 계약',analysis_drill_contracts($f['team'],$year),['player_name'=>['선수','ui_player'],'start_year'=>'시작','end_year'=>'종료','contract_season'=>'계약 연차','aav'=>ui_money('aav','AAV'),'games'=>'경기','war'=>ui_number('war','WAR',1),'ops'=>ui_number('ops','OPS',3),'era'=>ui_number('era','ERA',2)]);
ui_end();

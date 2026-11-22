<?php
require_once __DIR__.'/../../layout/ui.php';
$p=player_find(input_str('player_id'));if(!$p)ui_missing();$id=$p['player_id'];$metric=input_choice('metric',['war','ops','era'],$p['league_id']==='KBO'?($p['role']==='P'?'era':'ops'):'war');$span=(int)input_choice('span',['2','3'],'3');ui_start($p['player_name'],'players',($p['team_name']??'소속 미지정').' · '.(role_options()[$p['role']]??'—'));
echo '<div class="profile-actions">'.ui_watch($id).ui_link('pages/players/edit.php','선수 정보 수정',['player_id'=>$id],'secondary').'</div>';
if($p['league_id']==='KBO')ui_note('KBO의 WAR·나이·세부 포지션은 미확보입니다. OPS 또는 ERA로 확인하세요.');
echo '<form class="filterbar" method="get"><input type="hidden" name="player_id" value="'.h($id).'">';ui_select('metric','지표',metric_options(),$metric);ui_select('span','이동평균 기록 수',[2=>'2시즌',3=>'3시즌'],$span);echo '<button class="primary">조회</button></form>';
$series=analysis_window($id,1900,2200,$metric,$span);ui_chart(strtoupper($metric).' 시즌 추이',$series,['metric_value'=>strtoupper($metric),'moving_avg'=>'이동평균','league_avg'=>'리그 FA 선수 평균']);
ui_table('FA 계약',player_contracts($id),ui_contract_columns());ui_table('시즌별 원본 기록 (이적 시 구단별 행)',player_seasons($id),['year_id'=>'연도','team_name'=>'구단','games'=>'경기','war'=>ui_number('war','WAR',1),'pa'=>'타석','hr'=>'홈런','avg'=>ui_number('avg','타율',3),'ops'=>ui_number('ops','OPS',3),'ip'=>ui_number('ip','이닝',1),'p_so'=>'탈삼진','era'=>ui_number('era','ERA',2),'contract_id'=>'FA 계약 번호']);
ui_end();

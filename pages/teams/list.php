<?php
require_once __DIR__.'/../../layout/ui.php';
$league=input_choice('league',['MLB','KBO'],'KBO');$year=input_int('year',2024);$q=input_str('q');$sort=input_choice('sort',['fa_payroll','wins','league_rank','team_name'],'fa_payroll');
$rows=team_seasons_list($league,$year,$q,$sort);ui_start('구단 종합 비교','teams','같은 시즌, 구단의 FA 지출과 경기 성적을 비교하세요.');
echo '<form class="filterbar" method="get">';ui_league($league);ui_select('year','시즌',ui_years(),$year);ui_select('sort','정렬',['fa_payroll'=>'FA 지출 많은 순','wins'=>'승수 많은 순','league_rank'=>'순위 순','team_name'=>'이름 순'],$sort);ui_input('q','구단 이름',$q,'search');ui_submit('pages/teams/list.php');echo '</form>';
ui_note('FA 지출은 계약 연평균액(AAV)의 합계입니다. 구단 전체 연봉이 아닙니다. MLB 순위는 소속 리그 기준입니다.');
ui_table('구단별 시즌 성적',$rows,['team_name'=>['구단',fn($r)=>ui_link('pages/contracts/list.php',$r['team_name'],['league'=>$league,'season'=>$year,'team'=>$r['team_id']])],'sub_league'=>'소속','wins'=>'승','losses'=>'패','win_pct'=>ui_number('win_pct','승률',3),'league_rank'=>'순위','postseason'=>['포스트시즌',fn($r)=>h(postseason_label($r['postseason']))],'fa_payroll'=>ui_money('fa_payroll','FA 지출')]);
ui_end();

<?php
require_once __DIR__.'/../../layout/ui.php';
$f=ui_filters('ranking');$f['metric']=input_choice('metric',['war','ops','era'],$f['metric']??($f['league']==='KBO'?'ops':'war'));$f['scope']=input_choice('scope',['year','all'],$f['scope']??'year');$f['position']=input_choice('position',array_merge([''],array_keys(position_options())),$f['position']??'');if($f['league']==='KBO')$f['position']='';$f['min_total']=max(0,input_int('min_total',$f['min_total']??0));$f['top']=max(1,min(100,input_int('top',$f['top']??10)));
$f['min_seasons'] = max(1, min(10, input_int('min_seasons', $f['min_seasons'] ?? 2)));
filters_remember('ranking',$f);$rows=analysis_ranking($f);ui_start('FA 계약 효율 순위','ranking','계약 성과 순위와 몸값 순위를 나란히 비교하세요.');echo '<form class="filterbar" method="get">';ui_range($f);ui_select('metric','순위 지표',['war'=>'WAR당 비용 (낮을수록 좋음)','ops'=>'OPS (높을수록 좋음)','era'=>'ERA (낮을수록 좋음)'],$f['metric']);ui_select('scope','순위 범위',['year'=>'시작 연도별','all'=>'전체 기간'],$f['scope']);ui_select('role','역할',[''=>'전체']+role_options(),$f['role']);if($f['league']==='MLB')ui_select('position','포지션',[''=>'전체']+position_options(),$f['position']);ui_input('min_total','최소 총액 ('.($f['league']==='KBO'?'만원':'달러').')',$f['min_total'],'number','min="0"');
ui_input('min_seasons', '최소 시즌 수', $f['min_seasons'], 'number', 'min="1" max="10"');
ui_input('top','그룹별 상위 N',$f['top'],'number','min="1" max="100"');ui_submit('pages/analysis/ranking.php');echo '</form>';
ui_note(
    '1년 계약도 보려면 최소 시즌 수를 1로 설정하세요. '
    . 'WAR은 연봉이 지급된 시즌 수, OPS·ERA는 기록이 있는 시즌 수를 기준으로 합니다. '
    . 'WAR당 비용은 추정 지급액을 단축 시즌 보정 WAR로 나눈 값입니다. '
    . '보정 WAR이 0 이하인 계약은 비용을 표시하지 않고 순위 뒤에 배치합니다. '
    . 'OPS는 200타석 이상, ERA는 50이닝 이상인 계약이 대상입니다. '
    . 'KBO는 OPS·ERA로 조회할 수 있습니다. 몸값 1분위는 평균 연봉(AAV)이 가장 높은 그룹입니다.'
);
$metric=$f['metric']==='war'?ui_money('metric_value','WAR당 비용'):ui_number('metric_value',strtoupper($f['metric']),3);ui_table('계약 성과 순위',$rows,['metric_rank'=>'성과 순위','player_name'=>['선수','ui_player'],'to_team_name'=>'영입 구단','start_year'=>'계약 시작','seasons'=>'기록 시즌','metric_value'=>$metric,'aav'=>ui_money('aav','AAV'),'aav_rank'=>'몸값 순위','aav_quartile'=>'몸값 분위','metric_pct'=>'상위 %(0=1위)','group_size'=>'그룹 크기']);
ui_note('Good은 환산 WAR이 양수이고 같은 리그의 시즌당 WAR 상위 25%인 계약, Bad는 환산 WAR이 0 이하인 계약입니다. KBO는 WAR 기록이 없어 비율을 —로 표시합니다.');
ui_table('몸값 4분위별 성과 — 상위 N 제한 전 전체 대상',analysis_ranking_quartiles($f),['aav_quartile'=>'분위','contracts'=>'계약 수','min_aav'=>ui_money('min_aav','최소 AAV'),'max_aav'=>ui_money('max_aav','최대 AAV'),'avg_aav'=>ui_money('avg_aav','평균 AAV'),'avg_metric'=>$f['metric']==='war'?ui_money('avg_metric','평균 WAR당 비용'):ui_number('avg_metric','평균 지표',3),'avg_rank_pct'=>ui_number('avg_rank_pct','평균 상위 %',1),
    'good_pct' => ui_number('good_pct', 'Good 계약 (%)', 1),
    'bad_pct' => ui_number('bad_pct', 'Bad 계약 (%)', 1),
]);
ui_end();

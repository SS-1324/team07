<?php
// 공통 화면 도구: SQL은 backend/queries의 기존 함수를 사용한다.
require_once __DIR__.'/../backend/bootstrap.php';
foreach (['teams','players','contracts','analysis'] as $query) require_once __DIR__.'/../backend/queries/'.$query.'.php';
function ui_num($v,$d=0){return $v===null||$v===''?'—':number_format((float)$v,$d);}
function ui_link($path,$label,$params=[],$class=''){return '<a class="'.h($class).'" href="'.h(url($path).($params?'?'.http_build_query($params):'')).'">'.h($label).'</a>';}
function ui_select($key,$label,$options,$value='',$required=false){echo '<label>'.h($label).'<select name="'.h($key).'" '.($required?'required':'').'>';foreach($options as $k=>$v)echo '<option value="'.h($k).'" '.((string)$k===(string)$value?'selected':'').'>'.h($v).'</option>';echo '</select></label>';}
function ui_input($key,$label,$value='',$type='text',$attrs=''){echo '<label>'.h($label).'<input name="'.h($key).'" type="'.h($type).'" value="'.h($value).'" '.$attrs.'></label>';}
function ui_teams($league=''){ $o=[''=>'전체 / 미지정'];foreach(teams_all($league) as $r)$o[$r['team_id']]=$r['league_id'].' · '.$r['team_name'];return $o; }
function ui_years(){ $o=[];foreach(seasons_all() as $y)$o[$y]=$y;return $o; }
function ui_league($v){ui_select('league','리그',['KBO'=>'KBO','MLB'=>'MLB'],$v);}
function ui_submit($path){echo '<button class="primary" type="submit">조회</button>'.ui_link($path,'초기화',['reset'=>1],'secondary');}
function ui_note($text){echo '<p class="notice">'.h($text).'</p>';}
function ui_player($r){return ui_link('pages/players/detail.php',$r['player_name'],['player_id'=>$r['player_id']]);}
function ui_watch($id,$back='pages/players/search.php'){$on=watchlist_has($id);return '<form class="inline" method="post" action="'.h(url('actions/watchlist_toggle.php')).'">'.csrf_field().'<input type="hidden" name="player_id" value="'.h($id).'"><input type="hidden" name="back" value="'.h($back).'"><button aria-label="'.($on?'관심 선수 해제':'관심 선수 추가').'" aria-pressed="'.($on?'true':'false').'">'.($on?'★':'☆').'</button></form>';}
function ui_table($title,$rows,$columns){echo '<section class="panel result-panel"><div class="panel-head"><h2>'.h($title).'</h2><span>'.count($rows).'건</span><button type="button" data-csv>CSV 저장</button></div>';if(!$rows){echo '<p class="empty">조건에 맞는 데이터가 없습니다.</p></section>';return;}echo '<div class="table-wrap"><table><thead><tr>';foreach($columns as $k=>$c)echo '<th scope="col">'.h(is_array($c)?$c[0]:$c).'</th>';echo '</tr></thead><tbody>';foreach($rows as $r){echo '<tr'.(isset($r['row_level'])&&$r['row_level']!=='year'?' class="subtotal"':'').'>';foreach($columns as $k=>$c)echo '<td>'.(is_array($c)?$c[1]($r):h($r[$k]??'—')).'</td>';echo '</tr>';}echo '</tbody></table></div><div class="pager" data-pager></div></section>';}
function ui_money($key,$label){return [$label,fn($r)=>h(format_money($r[$key]??null,$r['currency']??'USD'))];}
function ui_number($key,$label,$d=0){return [$label,fn($r)=>h(ui_num($r[$key]??null,$d))];}
function ui_contract_columns($manage=false){$cols=['player_name'=>['선수','ui_player'],'from_team_name'=>'원소속','to_team_name'=>'영입 구단','start_year'=>'시작','end_year'=>'종료','years'=>'기간(년)','total_value'=>ui_money('total_value','총액'),'aav'=>ui_money('aav','연평균(AAV)')];if($manage)$cols['manage']=['관리',function($r){return ui_link('pages/contracts/edit.php','수정',['contract_id'=>$r['contract_id']],'secondary').'<form class="inline" method="post" data-confirm="이 계약을 삭제할까요? 구단 FA 지출도 변경됩니다." action="'.h(url('actions/contract_delete.php')).'">'.csrf_field().'<input type="hidden" name="contract_id" value="'.h($r['contract_id']).'"><button class="danger">삭제</button></form>';}];return $cols;}
function ui_start($title,$menu,$subtitle=''){$pageTitle=$title;$activeMenu=$menu;require __DIR__.'/header.php';}
function ui_end(){require __DIR__.'/footer.php';}
function ui_missing(){http_response_code(404);ui_start('찾을 수 없습니다','');ui_note('요청한 선수 또는 계약이 없습니다.');ui_end();exit;}
function ui_filters($page){if(input_str('reset')==='1')unset($_SESSION['filters'][$page]);$saved=filters_recall($page,['league'=>'MLB','year_from'=>2016,'year_to'=>2025,'role'=>'']);$f=$saved;$f['league']=input_choice('league',['KBO','MLB'],$saved['league']);[$f['year_from'],$f['year_to']]=year_range(max(1900,min(2200,input_int('year_from',$saved['year_from']))),max(1900,min(2200,input_int('year_to',$saved['year_to']))));$f['role']=input_choice('role',['','P','B'],$saved['role']);return $f;}
function ui_range($f){ui_league($f['league']);ui_input('year_from','시작 연도',$f['year_from'],'number','min="1900" max="2200"');ui_input('year_to','종료 연도',$f['year_to'],'number','min="1900" max="2200"');}
function ui_chart($title,$rows,$series,$x='year_id'){echo '<section class="panel"><h2>'.h($title).'</h2><div class="live-chart" data-chart="'.h(json_encode(['rows'=>$rows,'series'=>$series,'x'=>$x],JSON_HEX_TAG|JSON_THROW_ON_ERROR)).'"></div></section>';}

<?php
// Read-only checks against a configured MariaDB test database.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../layout/ui.php';
set_exception_handler(function($e){fwrite(STDERR,(string)$e);exit(1);});
function check($ok,$label){if(!$ok)throw new RuntimeException($label);echo 'PASS: '.$label.PHP_EOL;}
check(count(team_seasons_list('KBO',2024))===10,'KBO teams');
check(count(team_seasons_list('MLB',2024))===30,'MLB teams');
check(count(players_search(['league'=>'MLB','name'=>'Judge']))>0,'player search');
check(count(contracts_list(['league'=>'MLB','season'=>2024]))>0,'contracts');
check(count(analysis_aggregate('MLB',2016,2025,['role','years_band']))>0,'GROUP BY');
$rollup=analysis_rollup('MLB',2016,2025,'year');check(in_array('league',array_column($rollup,'row_level')),'ROLLUP');
$f=['league'=>'MLB','year_from'=>2016,'year_to'=>2025,'metric'=>'war','scope'=>'all','top'=>10];check(count(analysis_ranking($f))>0,'RANK');check(count(analysis_ranking_quartiles($f))===4,'NTILE');
$f['league']='KBO';check(count(analysis_ranking($f))===0,'KBO missing WAR');$f['metric']='ops';check(count(analysis_ranking($f))>0,'KBO OPS');$f['metric']='era';check(count(analysis_ranking($f))>0,'KBO ERA');
$all=analysis_window('judgeaa01',2016,2025);$filtered=analysis_window('judgeaa01',2020,2025);$byYear=array_column($all,null,'year_id');check(count($filtered)>0&&$filtered[0]['prev_value']===$byYear[$filtered[0]['year_id']]['prev_value'],'LAG includes pre-filter record');
check(count(players_compare(['judgeaa01','seageco01']))>=2,'pre/in contract comparison');
$original=$_SESSION['watchlist']??[];$_SESSION['watchlist']=[];watchlist_toggle('judgeaa01');check(watchlist_has('judgeaa01'),'session add');watchlist_toggle('judgeaa01');check(!watchlist_has('judgeaa01'),'session remove');$_SESSION['watchlist']=$original;
$_POST['csrf']=csrf_token();check(csrf_valid(),'valid CSRF');$_POST['csrf']='wrong';check(!csrf_valid(),'invalid CSRF');
echo 'All read-only checks passed. HTTP write actions require XAMPP verification.'.PHP_EOL;

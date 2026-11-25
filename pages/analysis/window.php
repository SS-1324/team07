<?php
require_once __DIR__.'/../../layout/ui.php';

$f = ui_filters('window');
$options = [];
foreach (players_options($f['league']) as $player) {
    $options[$player['player_id']] = $player['player_name'];
}
$f['player_id'] = input_str('player_id', $f['player_id'] ?? '');
if (!isset($options[$f['player_id']])) {
    $f['player_id'] = array_key_first($options) ?? '';
}
$p = $f['player_id'] !== '' ? player_find($f['player_id']) : null;
$metrics = metric_options();
if ($f['league'] === 'KBO') {
    unset($metrics['war']);
}
$metricChoices = ['auto' => '자동 (MLB WAR / KBO 타자 OPS·투수 ERA)'] + $metrics;
$trendChoice = input_choice('metric', array_keys($metricChoices), $f['trend_choice'] ?? 'auto');
$f['trend_choice'] = isset($metricChoices[$trendChoice]) ? $trendChoice : 'auto';
$defaultTrend = $f['league'] === 'MLB' ? 'war' : (($p['role'] ?? '') === 'P' ? 'era' : 'ops');
$f['metric'] = $f['trend_choice'] === 'auto' ? $defaultTrend : $f['trend_choice'];
$f['span'] = (int) input_choice('span', ['2', '3'], (string) ($f['span'] ?? 3));

$groupOptions = ['age_band' => '나이대', 'role' => '투수 / 타자', 'years_band' => '계약 기간', 'move' => '이적 여부'];
$f['group'] = input_choice('group', array_keys($groupOptions), $f['group'] ?? 'age_band');
$f['min_seasons'] = max(1, min(10, input_int('min_seasons', $f['min_seasons'] ?? 2)));
$choice = input_choice('compare_metric', array_keys($metricChoices), $f['compare_choice'] ?? 'auto');
$f['compare_choice'] = isset($metricChoices[$choice]) ? $choice : 'auto';
$defaultCompare = $f['league'] === 'MLB' ? 'war' : ($f['role'] === 'P' ? 'era' : 'ops');
$compare = $f;
$compare['metric'] = $f['compare_choice'] === 'auto' ? $defaultCompare : $f['compare_choice'];
filters_remember('window', $f);

$rows = $p ? analysis_window($f['player_id'], $f['year_from'], $f['year_to'], $f['metric'], $f['span']) : [];
ui_start('선수 시즌 성적 추이', 'window', '선수의 시즌 성적과 FA 계약 전후 성과를 살펴보세요.');
echo '<form class="filterbar" method="get">';
ui_range($f);
ui_select('player_id', '추이 분석 선수', $options, $f['player_id']);
ui_select('metric', '추이 지표', $metricChoices, $f['trend_choice']);
ui_select('span', '이동평균 기록 수', [2 => '2시즌', 3 => '3시즌'], $f['span']);
ui_select('role', '계약 비교 대상', ['' => '전체'] + role_options(), $f['role']);
ui_select('compare_metric', '계약 비교 지표', $metricChoices, $f['compare_choice']);
ui_input('min_seasons', '계약 중 최소 시즌 수', $f['min_seasons'], 'number', 'min="1" max="10"');
ui_select('group', '계약 요약 기준', $groupOptions, $f['group']);
ui_submit('pages/analysis/window.php');
echo '</form>';
if($p)echo '<div class="profile-actions">'.ui_watch($p['player_id'],'pages/analysis/window.php').ui_player($p).'</div>';ui_note('이전 값은 바로 앞 기록 시즌이며 연속된 해가 아닐 수 있습니다. 이동평균·누적에는 조회 시작 연도 이전 기록도 포함됩니다. KBO WAR·나이는 미확보입니다.');ui_chart(strtoupper($f['metric']).' 추이',$rows,['metric_value'=>strtoupper($f['metric']),'moving_avg'=>'이동평균','league_avg'=>'리그 FA 선수 평균']);$cols=['year_id'=>'연도','age'=>'나이','team_ids'=>'구단','metric_value'=>ui_number('metric_value','지표',3),'prev_year'=>'이전 기록 연도','prev_value'=>ui_number('prev_value','이전 값',3),'change'=>ui_number('change','변화',3),'moving_avg'=>ui_number('moving_avg','이동평균',3),'league_avg'=>ui_number('league_avg','리그 평균',3),'league_rank'=>'리그 순위','league_count'=>'유효 선수 수','contract_season'=>'FA 계약 연차'];if($f['metric']==='war')$cols['cumulative']=ui_number('cumulative','누적 WAR',1);ui_table('시즌별 분석',$rows,$cols);

$name = ['war' => '시즌당 환산 WAR', 'ops' => 'OPS', 'era' => 'ERA'][$compare['metric']];
$digits = ['war' => 2, 'ops' => 3, 'era' => 2][$compare['metric']];
ui_note('계약 시작 연도를 기준으로 같은 리그의 계약을 비교합니다. 계약 직전 2시즌 중 기록이 있는 시즌과 계약 기간을 비교하며, OPS는 두 기간 각각 200타석 이상, ERA는 각각 50이닝 이상이어야 합니다. 변화는 계약 중 값에서 계약 전 값을 뺀 값입니다. ERA는 감소할수록, WAR·OPS는 증가할수록 개선입니다.');
ui_table('계약 전후 성과 비교 · '.$name, analysis_contract_compare($compare), [
    'change_rank' => '리그 개선 순위',
    'player_name' => ['선수', 'ui_player'],
    'to_team_name' => '영입 구단',
    'start_year' => '계약 시작',
    'end_year' => '계약 종료',
    'pre_seasons' => '계약 전 기록 시즌',
    'seasons' => '계약 중 기록 시즌',
    'paid_seasons' => '연봉 지급 시즌',
    'metric_pre' => ui_number('metric_pre', '계약 전 '.$name, $digits),
    'metric_in' => ui_number('metric_in', '계약 중 '.$name, $digits),
    'metric_change' => ui_number('metric_change', '변화', $digits),
    'change_pct' => ui_number('change_pct', '개선 상위 %', 1),
]);
ui_table('계약 전후 요약 · '.$groupOptions[$f['group']].' · '.$name, analysis_contract_compare_summary($compare), [
    'group_value' => $groupOptions[$f['group']],
    'contracts' => '계약 수',
    'avg_pre' => ui_number('avg_pre', '계약 전 평균', $digits),
    'avg_in' => ui_number('avg_in', '계약 중 평균', $digits),
    'avg_change' => ui_number('avg_change', '평균 변화', $digits),
    'improved_contracts' => '개선 계약 수',
    'improved_pct' => ui_number('improved_pct', '개선 비율 (%)', 1),
]);
ui_end();

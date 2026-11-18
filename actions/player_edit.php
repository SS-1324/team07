<?php
// actions/player_edit.php
// 담당: 백엔드 신수연
// 과제 요건: UPDATE
// 순서: ① POST·CSRF 확인 → ② 입력 검사 → ③ 처리 → ④ 화면으로 이동

require_once __DIR__ . '/../backend/bootstrap.php';
require_once __DIR__ . '/../backend/queries/players.php';

require_post('pages/players/search.php');

$playerId = input_str('player_id');
if ($playerId === '' || !player_find($playerId)) {
    flash_set('수정할 선수를 찾지 못했습니다.', 'error');
    redirect('pages/players/search.php');
}
$back = 'pages/players/edit.php?player_id=' . rawurlencode($playerId);

[$data, $errors] = player_validate([
    'name_first'      => input_str('name_first'),
    'name_last'       => input_str('name_last'),
    'name_kor'        => input_str('name_kor'),
    'birth_date'      => input_str('birth_date'),
    'primary_pos'     => input_str('primary_pos'),
    'bats'            => input_str('bats'),
    'throws'          => input_str('throws'),
    'current_team_id' => input_str('current_team_id'),
]);
if ($errors) {
    flash_set(implode(' ', $errors), 'error');
    redirect($back);
}

try {
    $changed = player_update($playerId, $data);
} catch (PDOException $e) {
    flash_set('선수 정보를 저장하지 못했습니다.' . (DEBUG ? ' (' . $e->getMessage() . ')' : ''), 'error');
    redirect($back);
}

flash_set($changed ? '선수 정보를 수정했습니다.' : '바뀐 내용이 없습니다.');
redirect('pages/players/search.php');

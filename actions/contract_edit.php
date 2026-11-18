<?php
// actions/contract_edit.php
// 담당: 백엔드 신수연
// 과제 요건: UPDATE (+ 트랜잭션)
// 금액·기간·영입팀이 바뀌면 구단 FA 지출도 맞춰야 하므로 트랜잭션으로 묶는다.
//   1) 옛 계약의 AAV 를 옛 영입팀·옛 기간의 fa_payroll 에서 뺀다
//   2) fa_contracts UPDATE
//   3) 새 계약의 AAV 를 새 영입팀·새 기간의 fa_payroll 에 더한다
// 순서: ① POST·CSRF 확인 → ② 입력 검사 → ③ 트랜잭션 처리 → ④ 화면으로 이동

require_once __DIR__ . '/../backend/bootstrap.php';
require_once __DIR__ . '/../backend/queries/contracts.php';

require_post('pages/contracts/list.php');

$contractId = input_int('contract_id');
$old = $contractId ? contract_find($contractId) : null;
if (!$old) {
    flash_set('수정할 계약을 찾지 못했습니다.', 'error');
    redirect('pages/contracts/list.php');
}
$back = 'pages/contracts/edit.php?contract_id=' . $contractId;

[$data, $errors] = contract_validate([
    'player_id'        => input_str('player_id', $old['player_id']),
    'from_team_id'     => input_str('from_team_id'),
    'to_team_id'       => input_str('to_team_id'),
    'start_year'       => input_str('start_year'),
    'end_year'         => input_str('end_year'),
    'total_value'      => input_str('total_value'),
    'guaranteed_value' => input_str('guaranteed_value'),
]);
if ($errors) {
    flash_set(implode(' ', $errors), 'error');
    redirect($back);
}

$pdo = db();
try {
    $pdo->beginTransaction();

    payroll_apply($old['to_team_id'], (int) $old['start_year'], (int) $old['end_year'], -(int) $old['aav']);
    contract_update($contractId, $data);
    payroll_apply($data['to_team_id'], $data['start_year'], $data['end_year'], $data['aav']);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash_set('계약을 수정하지 못했습니다. 아무것도 바뀌지 않았습니다.' . (DEBUG ? ' (' . $e->getMessage() . ')' : ''), 'error');
    redirect($back);
}

flash_set("계약 #{$contractId} 를 수정했습니다.");
redirect('pages/contracts/list.php');

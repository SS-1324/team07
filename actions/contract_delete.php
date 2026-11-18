<?php
// actions/contract_delete.php
// 담당: 백엔드 신수연
// 과제 요건: DELETE (+ 트랜잭션)
//   1) 지울 계약의 AAV 를 영입팀·계약 기간의 fa_payroll 에서 뺀다
//   2) fa_contracts DELETE
// 순서: ① POST·CSRF 확인 → ② 입력 검사 → ③ 트랜잭션 처리 → ④ 화면으로 이동

require_once __DIR__ . '/../backend/bootstrap.php';
require_once __DIR__ . '/../backend/queries/contracts.php';

require_post('pages/contracts/list.php');

$contractId = input_int('contract_id');
$contract = $contractId ? contract_find($contractId) : null;
if (!$contract) {
    flash_set('삭제할 계약을 찾지 못했습니다.', 'error');
    redirect('pages/contracts/list.php');
}

$pdo = db();
try {
    $pdo->beginTransaction();

    payroll_apply($contract['to_team_id'], (int) $contract['start_year'], (int) $contract['end_year'], -(int) $contract['aav']);
    contract_delete($contractId);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash_set('계약을 삭제하지 못했습니다.' . (DEBUG ? ' (' . $e->getMessage() . ')' : ''), 'error');
    redirect('pages/contracts/list.php');
}

flash_set("{$contract['player_name']} 선수의 {$contract['start_year']}년 계약(#{$contractId})을 삭제했습니다.");
redirect('pages/contracts/list.php');

<?php
// actions/contract_add.php
// 담당: 백엔드 신수연
// 과제 요건: INSERT + 트랜잭션
// 계약 등록은 세 테이블을 함께 바꾼다. 하나라도 실패하면 전부 되돌린다(rollBack).
//   1) fa_contracts          INSERT
//   2) players               current_team_id 를 영입팀으로 UPDATE
//   3) team_seasons          계약 기간에 해당하는 영입팀 시즌의 fa_payroll 에 AAV 를 더함
// 순서: ① POST·CSRF 확인 → ② 입력 검사 → ③ 트랜잭션 처리 → ④ 화면으로 이동

require_once __DIR__ . '/../backend/bootstrap.php';
require_once __DIR__ . '/../backend/queries/contracts.php';

// ① POST + CSRF 토큰 확인
require_post('pages/contracts/add.php');

// ② 입력 검사 (years, aav 는 화면에서 받지 않고 서버에서 계산한다)
[$data, $errors] = contract_validate([
    'player_id'        => input_str('player_id'),
    'from_team_id'     => input_str('from_team_id'),
    'to_team_id'       => input_str('to_team_id'),
    'start_year'       => input_str('start_year'),
    'end_year'         => input_str('end_year'),
    'total_value'      => input_str('total_value'),
    'guaranteed_value' => input_str('guaranteed_value'),
]);
if ($errors) {
    // 입력한 값을 세션에 남겨 두면 화면에서 다시 채워 줄 수 있다 (filters_recall('contract_add'))
    filters_remember('contract_add', $_POST);
    flash_set(implode(' ', $errors), 'error');
    redirect('pages/contracts/add.php');
}

// ③ 트랜잭션
$pdo = db();
try {
    $pdo->beginTransaction();

    $contractId = contract_insert($data);
    player_set_team($data['player_id'], $data['to_team_id']);
    $seasons = payroll_apply($data['to_team_id'], $data['start_year'], $data['end_year'], $data['aav']);

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    filters_remember('contract_add', $_POST);
    flash_set('계약을 저장하지 못했습니다. 아무것도 바뀌지 않았습니다.' . (DEBUG ? ' (' . $e->getMessage() . ')' : ''), 'error');
    redirect('pages/contracts/add.php');
}

// ④ 성공: 입력 기억을 지우고 목록으로
filters_remember('contract_add', []);
flash_set("계약 #{$contractId} 를 등록했습니다. 구단 FA 지출 {$seasons}개 시즌을 함께 갱신했습니다.");
redirect('pages/contracts/list.php');

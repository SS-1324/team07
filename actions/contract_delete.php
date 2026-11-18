<?php
// actions/contract_delete.php
// 담당: 백엔드 (이름)
// 과제 요건: DELETE
// 작성 방법은 actions/watchlist_toggle.php 를 따라 한다. ① POST 확인 → ② 입력 검사 → ③ 처리 → ④ 화면으로 이동

require_once __DIR__ . '/../backend/bootstrap.php';

// TODO: contract_id 검사
// TODO: contract_delete() 호출. 지운 계약만큼 fa_payroll 을 뺀다 (트랜잭션 권장)

flash_set('아직 만들지 않은 기능입니다.', 'error');
redirect('pages/contracts/list.php');

<?php
// actions/contract_add.php
// 담당: 백엔드 (이름)
// 과제 요건: INSERT + 트랜잭션
// 작성 방법은 actions/watchlist_toggle.php 를 따라 한다. ① POST 확인 → ② 입력 검사 → ③ 처리 → ④ 화면으로 이동

require_once __DIR__ . '/../backend/bootstrap.php';

// TODO: 입력 검사: 선수·영입팀 필수, 종료 연도 >= 시작 연도, 총액 > 0
// TODO: years = 종료 - 시작 + 1, aav = 총액 / years 를 서버에서 계산
// TODO: db()->beginTransaction()
// TODO:   1) fa_contracts INSERT
// TODO:   2) players.current_team_id UPDATE
// TODO:   3) 계약 기간에 해당하는 team_seasons.fa_payroll UPDATE (영입팀)
// TODO: 모두 성공하면 commit(), 하나라도 실패하면 catch 에서 rollBack() 후 오류 메시지
// TODO: 성공하면 pages/contracts/list.php 로 이동

flash_set('아직 만들지 않은 기능입니다.', 'error');
redirect('pages/contracts/add.php');

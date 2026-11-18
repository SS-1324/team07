<?php
// actions/player_edit.php
// 담당: 백엔드 (이름)
// 과제 요건: UPDATE
// 작성 방법은 actions/watchlist_toggle.php 를 따라 한다. ① POST 확인 → ② 입력 검사 → ③ 처리 → ④ 화면으로 이동

require_once __DIR__ . '/../backend/bootstrap.php';

// TODO: player_id 와 수정할 값(이름, 포지션, 투타, 현재 소속) 검사
// TODO: player_update() 호출

flash_set('아직 만들지 않은 기능입니다.', 'error');
redirect('pages/players/search.php');

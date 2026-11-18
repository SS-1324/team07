<?php
// actions/watchlist_toggle.php
// 담당: 백엔드
// 과제 요건: 세션 — 관심 선수를 $_SESSION 에 넣거나 뺀 뒤 원래 화면으로 돌아간다.
// 다른 처리 파일을 만들 때 따라 하는 "기준 예시"다. 순서: ① POST 확인 → ② 입력 검사 → ③ 처리 → ④ 화면으로 이동

require_once __DIR__ . '/../backend/bootstrap.php';

// ① POST 로 온 요청만 처리한다
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('pages/players/search.php');
}

// ② 입력 검사
$playerId = input_str('player_id');
// 돌아갈 화면은 허용 목록에서만 고른다 (임의 주소로 보내지 않기 위해)
$back = input_choice('back', ['pages/players/search.php', 'pages/players/watchlist.php'], 'pages/players/search.php');

if ($playerId === '') {
    flash_set('선수를 선택하지 않았습니다.', 'error');
    redirect($back);
}

// ③ 처리: 세션의 관심 선수 목록에 넣거나 뺀다
watchlist_toggle($playerId);
flash_set(watchlist_has($playerId) ? '관심 선수에 추가했습니다.' : '관심 선수에서 뺐습니다.');

// ④ 화면으로 이동 (새로고침해도 다시 제출되지 않게 항상 redirect 로 끝낸다)
redirect($back);

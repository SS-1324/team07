<?php
// pages/players/search.php
// 담당: 프론트 (이름) / 쿼리: 백엔드 (이름)
// 과제 요건: SELECT, 세션
// 만드는 방법은 pages/teams/list.php 를 따라 한다. ① 입력 읽기 → ② 백엔드 함수 호출 → ③ HTML 출력

require_once __DIR__ . '/../../backend/bootstrap.php';

$pageTitle  = '선수 검색';
$activeMenu = 'players';
require __DIR__ . '/../../layout/header.php';
?>

<section class="panel todo">
    <p>아직 만들지 않은 화면입니다. (과제 요건: SELECT, 세션)</p>
    <ul>
        <li>필터: 이름, 포지션, 현재 소속 구단</li>
        <li>행마다 관심 선수 버튼(actions/watchlist_toggle.php 로 POST)과 정보 수정 링크</li>
    </ul>
</section>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

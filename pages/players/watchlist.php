<?php
// pages/players/watchlist.php
// 담당: 프론트 (이름) / 쿼리: 백엔드 (이름)
// 과제 요건: 세션
// 만드는 방법은 pages/teams/list.php 를 따라 한다. ① 입력 읽기 → ② 백엔드 함수 호출 → ③ HTML 출력

require_once __DIR__ . '/../../backend/bootstrap.php';

$pageTitle  = '관심 선수';
$activeMenu = 'watchlist';
require __DIR__ . '/../../layout/header.php';
?>

<section class="panel todo">
    <p>아직 만들지 않은 화면입니다. (과제 요건: 세션)</p>
    <ul>
        <li>watchlist_ids() 로 세션에 저장된 선수 ID를 꺼내 목록으로 보여준다</li>
        <li>체크박스로 2~4명을 골라 pages/players/compare.php 로 보낸다</li>
    </ul>
</section>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

<?php
// pages/players/compare.php
// 담당: 프론트 (이름) / 쿼리: 백엔드 (이름)
// 과제 요건: 세션
// 만드는 방법은 pages/teams/list.php 를 따라 한다. ① 입력 읽기 → ② 백엔드 함수 호출 → ③ HTML 출력

require_once __DIR__ . '/../../backend/bootstrap.php';

$pageTitle  = '관심 선수 비교';
$activeMenu = 'watchlist';
require __DIR__ . '/../../layout/header.php';
?>

<section class="panel todo">
    <p>아직 만들지 않은 화면입니다. (과제 요건: 세션)</p>
    <ul>
        <li>세션의 관심 선수 중 선택한 선수의 계약과 시즌 성적을 나란히 보여준다</li>
    </ul>
</section>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

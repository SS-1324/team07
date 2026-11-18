<?php
// pages/players/edit.php
// 담당: 프론트 (이름) / 쿼리: 백엔드 (이름)
// 과제 요건: UPDATE
// 만드는 방법은 pages/teams/list.php 를 따라 한다. ① 입력 읽기 → ② 백엔드 함수 호출 → ③ HTML 출력

require_once __DIR__ . '/../../backend/bootstrap.php';

$pageTitle  = '선수 정보 수정';
$activeMenu = 'players';
require __DIR__ . '/../../layout/header.php';
?>

<section class="panel todo">
    <p>아직 만들지 않은 화면입니다. (과제 요건: UPDATE)</p>
    <ul>
        <li>주소의 player_id 로 기존 값을 불러와 폼에 채운다</li>
        <li>폼은 actions/player_edit.php 로 POST 한다</li>
    </ul>
</section>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

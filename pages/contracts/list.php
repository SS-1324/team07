<?php
// pages/contracts/list.php
// 담당: 프론트 (이름) / 쿼리: 백엔드 (이름)
// 과제 요건: SELECT, DELETE
// 만드는 방법은 pages/teams/list.php 를 따라 한다. ① 입력 읽기 → ② 백엔드 함수 호출 → ③ HTML 출력

require_once __DIR__ . '/../../backend/bootstrap.php';

$pageTitle  = 'FA 계약 목록';
$activeMenu = 'contracts';
require __DIR__ . '/../../layout/header.php';
?>

<section class="panel todo">
    <p>아직 만들지 않은 화면입니다. (과제 요건: SELECT, DELETE)</p>
    <ul>
        <li>필터: 리그, 시즌, 구단, 포지션, 선수 이름 검색</li>
        <li>표: 선수, 원소속팀 → 영입팀, 기간, 총액, AAV</li>
        <li>행마다 수정 링크(pages/contracts/edit.php)와 삭제 버튼(actions/contract_delete.php 로 POST)</li>
    </ul>
</section>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

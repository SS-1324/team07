<?php
// pages/contracts/add.php
// 담당: 프론트 (이름) / 쿼리: 백엔드 (이름)
// 과제 요건: INSERT, 트랜잭션
// 만드는 방법은 pages/teams/list.php 를 따라 한다. ① 입력 읽기 → ② 백엔드 함수 호출 → ③ HTML 출력

require_once __DIR__ . '/../../backend/bootstrap.php';

$pageTitle  = 'FA 계약 등록';
$activeMenu = 'contract_add';
require __DIR__ . '/../../layout/header.php';
?>

<section class="panel todo">
    <p>아직 만들지 않은 화면입니다. (과제 요건: INSERT, 트랜잭션)</p>
    <ul>
        <li>입력: 선수(select), 원소속팀, 영입팀, 시작·종료 연도, 총액, 보장액</li>
        <li>폼은 actions/contract_add.php 로 POST 한다</li>
        <li>AAV와 계약 연수는 입력받지 않고 서버에서 계산한다</li>
    </ul>
</section>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

<?php
// pages/analysis/ranking.php
// 담당: 프론트 (이름) / 쿼리: 백엔드 (이름)
// 과제 요건: 고급 분석 3 — RANK, NTILE
// 만드는 방법은 pages/teams/list.php 를 따라 한다. ① 입력 읽기 → ② 백엔드 함수 호출 → ③ HTML 출력

require_once __DIR__ . '/../../backend/bootstrap.php';

$pageTitle  = 'FA 계약 효율 순위';
$activeMenu = 'ranking';
require __DIR__ . '/../../layout/header.php';
?>

<section class="panel todo">
    <p>아직 만들지 않은 화면입니다. (과제 요건: 고급 분석 3 — RANK, NTILE)</p>
    <ul>
        <li>입력: 리그, 시작 연도, 포지션, 최소 계약 총액, 상위 몇 건까지 볼지</li>
        <li>결과: 같은 리그·연도 안의 WAR당 비용 순위, 금액 4분위</li>
        <li>쿼리 함수는 analysis_ranking()</li>
    </ul>
</section>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

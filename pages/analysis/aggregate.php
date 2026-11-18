<?php
// pages/analysis/aggregate.php
// 담당: 프론트 (이름) / 쿼리: 백엔드 (이름)
// 과제 요건: 고급 분석 1 — 여러 열 기준 GROUP BY 집계
// 만드는 방법은 pages/teams/list.php 를 따라 한다. ① 입력 읽기 → ② 백엔드 함수 호출 → ③ HTML 출력

require_once __DIR__ . '/../../backend/bootstrap.php';

$pageTitle  = '그룹별 집계';
$activeMenu = 'aggregate';
require __DIR__ . '/../../layout/header.php';
?>

<section class="panel todo">
    <p>아직 만들지 않은 화면입니다. (과제 요건: 고급 분석 1 — 여러 열 기준 GROUP BY 집계)</p>
    <ul>
        <li>입력: 리그, 시작 연도 범위, 묶을 기준 체크박스(포지션, 계약기간 구간, 영입팀)</li>
        <li>결과: 계약 수, AVG/MAX/MIN(AAV), SUM(WAR), WAR당 금액</li>
        <li>쿼리 함수는 backend/queries/analysis.php 의 analysis_aggregate()</li>
    </ul>
</section>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

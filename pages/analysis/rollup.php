<?php
// pages/analysis/rollup.php
// 담당: 프론트 (이름) / 쿼리: 백엔드 (이름)
// 과제 요건: 고급 분석 2 — WITH ROLLUP, 드릴다운
// 만드는 방법은 pages/teams/list.php 를 따라 한다. ① 입력 읽기 → ② 백엔드 함수 호출 → ③ HTML 출력

require_once __DIR__ . '/../../backend/bootstrap.php';

$pageTitle  = '롤업 / 드릴다운';
$activeMenu = 'rollup';
require __DIR__ . '/../../layout/header.php';
?>

<section class="panel todo">
    <p>아직 만들지 않은 화면입니다. (과제 요건: 고급 분석 2 — WITH ROLLUP, 드릴다운)</p>
    <ul>
        <li>입력: 리그, 연도 범위, 집계 단계(리그 → 구단 → 연도) 라디오 버튼</li>
        <li>결과: 단계별 FA 지출·승수 소계. 구단 행을 누르면 그 구단의 연도별·계약별 내역으로 내려간다</li>
        <li>쿼리 함수는 analysis_rollup()</li>
    </ul>
</section>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

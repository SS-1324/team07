<?php
// pages/analysis/window.php
// 담당: 프론트 (이름) / 쿼리: 백엔드 (이름)
// 과제 요건: 고급 분석 4 — 윈도우 함수 (LAG, 누적합, 이동평균)
// 만드는 방법은 pages/teams/list.php 를 따라 한다. ① 입력 읽기 → ② 백엔드 함수 호출 → ③ HTML 출력

require_once __DIR__ . '/../../backend/bootstrap.php';

$pageTitle  = '시즌 성적 추이';
$activeMenu = 'window';
require __DIR__ . '/../../layout/header.php';
?>

<section class="panel todo">
    <p>아직 만들지 않은 화면입니다. (과제 요건: 고급 분석 4 — 윈도우 함수 (LAG, 누적합, 이동평균))</p>
    <ul>
        <li>입력: 선수 선택, 연도 범위, 이동평균 구간(2년/3년)</li>
        <li>결과: 시즌별 WAR, 전년 대비 변화, 누적 WAR, 이동평균</li>
        <li>쿼리 함수는 analysis_window()</li>
    </ul>
</section>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

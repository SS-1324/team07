<?php
// layout/header.php
// 담당: 프론트
// 모든 화면의 공통 상단 + 왼쪽 메뉴. 화면 파일에서 $pageTitle, $activeMenu 를 정한 뒤 require 한다.

$pageTitle  = $pageTitle  ?? 'FA Contract Value';
$activeMenu = $activeMenu ?? '';

// 메뉴: [묶음 이름 => [메뉴 키 => [이름, 경로]]]
$menu = [
    '데이터 탐색' => [
        'teams'     => ['구단 종합 비교', 'pages/teams/list.php'],
        'contracts' => ['FA 계약 목록', 'pages/contracts/list.php'],
        'players'   => ['선수 검색', 'pages/players/search.php'],
    ],
    '투자 분석' => [
        'aggregate' => ['그룹별 집계', 'pages/analysis/aggregate.php'],
        'rollup'    => ['롤업 / 드릴다운', 'pages/analysis/rollup.php'],
        'ranking'   => ['FA 계약 효율 순위', 'pages/analysis/ranking.php'],
        'window'    => ['시즌 성적 추이', 'pages/analysis/window.php'],
    ],
    '데이터 관리' => [
        'contract_add' => ['FA 계약 등록', 'pages/contracts/add.php'],
    ],
    '내 작업' => [
        'watchlist' => ['관심 선수 (' . count(watchlist_ids()) . ')', 'pages/players/watchlist.php'],
    ],
];
$flash = flash_take();
?>
<!doctype html><html lang="ko"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?= h($pageTitle) ?> · DIAMONDATA</title>
<?php foreach(['frontend-base','frontend-modern','live'] as $css): ?><link rel="stylesheet" href="<?= h(url('assets/css/'.$css.'.css')) ?>"><?php endforeach; ?>
<script defer src="<?= h(url('assets/js/live.js')) ?>"></script></head><body><div id="diamondata-app">
<a class="skip" href="#dd-main">본문 바로가기</a><aside class="rail"><a class="brand" href="<?= h(url('index.php')) ?>"><b>D</b> DIAMONDATA<i></i></a><p class="rail-sub">BASEBALL DATA WORKSPACE</p><nav id="dd-nav" aria-label="주 메뉴">
<?php foreach($menu as $group=>$items): ?><p><?= h($group) ?></p><?php foreach($items as $key=>[$label,$path]): ?><a class="nav-link <?= $key===$activeMenu?'active':'' ?>" <?= $key===$activeMenu?'aria-current="page"':'' ?> href="<?= h(url($path)) ?>"><?= h($label) ?></a><?php endforeach;endforeach; ?>
</nav><div class="rail-bottom"><i></i> TEAM 07<small>계약과 성적 사이, 데이터로 찾는 가치</small></div></aside><div class="shell"><header class="topbar"><div><button id="dd-menu" aria-controls="dd-nav" aria-expanded="false">☰ 메뉴</button><span class="crumb-parent">워크스페이스</span><span class="slash">/</span><strong><?= h($pageTitle) ?></strong></div><span class="mode">MariaDB · 실제 조회</span></header><main id="dd-main">
<?php if($activeMenu==='teams'): ?><section class="stadium-banner"><img class="stadium-photo" src="<?= h(url('assets/img/stadium.webp')) ?>" alt="AI로 생성한 가상 야구장 전경" width="1536" height="1024"><div class="stadium-copy"><span class="stadium-kicker">BEYOND THE SCOREBOARD</span><h2>기록 너머의 가치,<br>데이터로 읽는 야구.</h2><p>선수의 계약부터 시즌 성적까지.<br>당신의 야구에 새로운 관점을 더하세요.</p><a class="stadium-cta" href="<?= h(url('pages/players/search.php')) ?>">선수 데이터 탐색 ↗</a></div><div class="stadium-caption"><span>DIAMONDATA / FIELD NOTES</span><span>AI-generated stadium</span></div></section><?php endif; ?>
<div class="page-heading"><div><p class="eyebrow">FA CONTRACT VALUE</p><h1><?= h($pageTitle) ?></h1><p><?= h($subtitle??'') ?></p></div></div>
<?php if($flash): ?><p class="notice" role="status"><?= h($flash['message']) ?></p><?php endif; ?>

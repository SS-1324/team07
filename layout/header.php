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
<!doctype html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?> · FA Contract Value</title>
    <link rel="stylesheet" href="<?= h(url('assets/css/style.css')) ?>">
</head>
<body>
<aside class="sidebar">
    <a class="brand" href="<?= h(url('index.php')) ?>">FA Contract Value</a>
    <nav>
        <?php foreach ($menu as $groupName => $items): ?>
            <p class="nav-group"><?= h($groupName) ?></p>
            <?php foreach ($items as $key => [$label, $path]): ?>
                <a class="<?= $key === $activeMenu ? 'active' : '' ?>" href="<?= h(url($path)) ?>"><?= h($label) ?></a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
</aside>
<main class="content">
    <h1><?= h($pageTitle) ?></h1>
    <?php if ($flash): ?>
        <p class="flash flash-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></p>
    <?php endif; ?>

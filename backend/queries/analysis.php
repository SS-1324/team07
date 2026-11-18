<?php
// backend/queries/analysis.php
// 담당: 백엔드 (이름)
// 고급 분석 4종 쿼리 (각각 별도 메뉴에서 사용)
// 작성 방법은 backend/queries/teams.php 를 따라 한다. 사용자 입력은 반드시 ? 와 $params 로 넘긴다.

// TODO: analysis_aggregate($leagueId, $yearFrom, $yearTo, $groupBy) — 여러 열 GROUP BY. $groupBy 는 허용 목록으로 검사
// TODO: analysis_rollup($leagueId, $yearFrom, $yearTo, $level) — GROUP BY ... WITH ROLLUP
// TODO: analysis_ranking($leagueId, $startYear, $position, $minTotal, $limit) — RANK() OVER, NTILE(4)
// TODO: analysis_window($playerId, $yearFrom, $yearTo, $span) — LAG(), SUM() OVER, AVG() OVER (ROWS ...)

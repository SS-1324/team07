<?php
// backend/queries/analysis.php
// 담당: 백엔드 신수연
// 고급 분석 4종 쿼리 (각각 별도 메뉴에서 사용)
//   1. analysis_aggregate()       여러 열 GROUP BY + SUM/AVG/MAX/MIN
//   2. analysis_rollup()          GROUP BY ... WITH ROLLUP (리그 → 구단 → 연도), analysis_drill_contracts() 로 드릴다운
//   3. analysis_ranking()         RANK / NTILE / PERCENT_RANK, analysis_ranking_quartiles() 로 4분위별 요약
//   4. analysis_window()          LAG / 누적 SUM / 이동 AVG / 같은 해 리그 내 RANK
//
// 모든 함수는 사용자 입력을 ? 와 $params 로 넘긴다. ? 로 못 넘기는 것(묶을 열, 정렬 방향, 이동평균 구간)은
// 이 파일 안의 허용 목록에서 고른 SQL 조각이나 정수로 고정한 값만 SQL 에 들어간다.
//
// 금액은 리그마다 단위가 다르다 (MLB 달러, KBO 만원). 그래서 모든 집계는 리그를 먼저 나눈다.
// KBO 는 WAR 이 없다 (NULL). WAR 기준 결과는 MLB 만 나오고, KBO 는 OPS/ERA 로 본다.
//
// MariaDB(XAMPP) 호환: GROUPING() 함수는 MariaDB 에 없어서 쓰지 않는다. WITH ROLLUP 은 같은 SELECT 에
// ORDER BY 를 붙일 수 없어서 바깥 쿼리에서 정렬한다.

require_once __DIR__ . '/common.php';

// =====================================================================================
// 단축 시즌 보정 (2020 MLB 60경기)
// =====================================================================================
// WAR·승수 같은 누적 기록은 경기 수에 비례한다. 단축 시즌의 기록을 정규 시즌(leagues.games_per_season)
// 기준으로 환산해서 비율 지표(WAR당 비용, 시즌당 WAR, 1승당 FA 지출, 시즌 추이)에 쓴다.
// 금액(AAV, fa_payroll)은 바꾸지 않는다 → 금액 합계는 그대로 team_seasons.fa_payroll 과 맞는다.
// 화면에 보이는 원래 기록(war, wins)도 그대로 두고, 환산값은 war_adj 같은 별도 키로 돌려준다.

// 리그 => [연도 => 그 시즌 실제 팀당 경기 수]
const SHORT_SEASONS = ['MLB' => [2020 => 60]];

// season_scale(league_id, year_id, scale) CTE. 목록에 없는 리그·연도는 LEFT JOIN 후 COALESCE(scale, 1) 로 쓴다.
// SQL 에 들어가는 값은 위 상수뿐이다 (사용자 입력 없음).
function sql_cte_season_scale(): string
{
    $rows = [];
    foreach (SHORT_SEASONS as $league => $years) {
        foreach ($years as $year => $games) {
            $rows[] = sprintf(
                "SELECT league_id, %d AS year_id, games_per_season / %d AS scale FROM leagues WHERE league_id = '%s'",
                $year, $games, preg_replace('/[^A-Z]/', '', $league)
            );
        }
    }
    return 'season_scale AS (' . implode(' UNION ALL ', $rows) . ')';
}

// =====================================================================================
// 계약별 성과 (집계·순위에서 사용, ps_year 와 season_scale CTE 뒤에 붙인다)
// =====================================================================================
// common.php 의 contract_perf 는 지급액을 'AAV × 기록이 있는 시즌 수'로 잡는다. 그러면 부상·방출·출장정지로
// 기록이 없는 해의 연봉이 빠져서 나쁜 계약이 효율적으로 보인다. 여기서는 이렇게 계산한다.
//   eff_end      : 계약이 실제로 유효한 마지막 해 = 아래 셋 중 가장 이른 해
//                  - 계약 종료 연도 (end_year)
//                  - 같은 선수의 다음 계약 시작 직전 해 (옵트아웃·연장으로 새 계약이 기존 계약을 대체한 경우)
//                  - DB 의 마지막 시즌 (진행 중 계약은 지금까지만)
//   paid_seasons : start_year ~ eff_end 시즌 수 (기록이 없어도 연봉은 나간 해로 센다)
//   paid         : AAV × paid_seasons
//   seasons      : 그 기간 중 기록이 있는 시즌 수
//   war, war_adj : 기록이 없는 해는 WAR 0. 단, WAR 을 기록하지 않는 리그(KBO)는 NULL 그대로 둔다.
//                  war_adj 는 단축 시즌을 정규 시즌으로 환산한 값
//   ops, pa_ops, era, ip : 기록이 있는 시즌만으로 계산 (비율 지표라 빠진 해를 0 으로 채우지 않는다)
// 한계: 자진 은퇴·출전 거부로 연봉을 받지 않은 해는 DB 에 지급 여부가 없어서 지급한 것으로 센다.
const SQL_CTE_CONTRACT_EFF = "league_war AS (
    SELECT t.league_id, COUNT(ps.war) > 0 AS has_war
      FROM player_seasons ps
      JOIN teams t ON t.team_id = ps.team_id
     GROUP BY t.league_id
),
contract_span AS (
    SELECT c.contract_id, c.player_id, c.start_year, c.aav, tt.league_id,
           LEAST(c.end_year,
                 COALESCE(LEAD(c.start_year) OVER (PARTITION BY c.player_id ORDER BY c.start_year, c.contract_id) - 1,
                          c.end_year),
                 (SELECT MAX(year_id) FROM team_seasons)) AS eff_end
      FROM fa_contracts c
      JOIN teams tt ON tt.team_id = c.to_team_id
),
contract_eff AS (
    SELECT cs.contract_id, cs.eff_end,
           GREATEST(cs.eff_end - cs.start_year + 1, 0)           AS paid_seasons,
           cs.aav * GREATEST(cs.eff_end - cs.start_year + 1, 0)  AS paid,
           COUNT(y.year_id)                                      AS seasons,
           CASE WHEN lw.has_war THEN COALESCE(SUM(y.war), 0) ELSE SUM(y.war) END AS war,
           CASE WHEN lw.has_war THEN COALESCE(SUM(y.war * COALESCE(sc.scale, 1)), 0)
                ELSE SUM(y.war * COALESCE(sc.scale, 1)) END     AS war_adj,
           SUM(y.ops_pa) / NULLIF(SUM(y.pa_ops), 0)              AS ops,
           SUM(y.pa_ops)                                         AS pa_ops,
           SUM(y.era_ip) / NULLIF(SUM(y.ip), 0)                  AS era,
           SUM(y.ip)                                             AS ip
      FROM contract_span cs
      LEFT JOIN league_war lw   ON lw.league_id = cs.league_id
      LEFT JOIN ps_year y       ON y.player_id = cs.player_id AND y.year_id BETWEEN cs.start_year AND cs.eff_end
      LEFT JOIN season_scale sc ON sc.league_id = y.league_id AND sc.year_id = y.year_id
     GROUP BY cs.contract_id, cs.eff_end, cs.start_year, cs.aav, lw.has_war
)";

// =====================================================================================
// 1. 복합 그룹화 집계
// =====================================================================================

// 묶을 수 있는 기준: 키 => [화면 표시, SQL 식]
function aggregate_group_options(): array
{
    return [
        'role'       => ['투수/타자', "CASE r.role WHEN 'P' THEN '투수' ELSE '타자' END"],
        'position'   => ['포지션 (MLB)', "COALESCE(p.primary_pos, '미상')"],
        'years_band' => ['계약 기간', "CASE WHEN c.years <= 2 THEN '1-2년' WHEN c.years <= 4 THEN '3-4년'
                                              WHEN c.years <= 6 THEN '5-6년' ELSE '7년 이상' END"],
        'age_band'   => ['계약 시 나이 (MLB)', "CASE WHEN p.birth_date IS NULL THEN '미상'
                                              WHEN c.start_year - YEAR(p.birth_date) <= 29 THEN '29세 이하'
                                              WHEN c.start_year - YEAR(p.birth_date) <= 32 THEN '30-32세'
                                              ELSE '33세 이상' END"],
        'move'       => ['이적 여부', "CASE WHEN c.from_team_id IS NULL THEN '해외/기타'
                                              WHEN c.from_team_id = c.to_team_id THEN '잔류' ELSE '이적' END"],
        'to_team'    => ['영입 구단', 'tt.team_name'],
        'start_year' => ['계약 시작 연도', 'c.start_year'],
    ];
}

// $leagueId : 'MLB' | 'KBO' | '' (전체 — 리그별로 따로 묶인다)
// $groupBy  : aggregate_group_options() 의 키 배열 (1~3개, 고른 순서대로 묶는다)
// $role     : 'P' | 'B' | ''
// 돌려주는 키: league_id, currency, (묶은 기준 키들), contracts, avg_aav, max_aav, min_aav, sum_total,
//             avg_years, seasons, paid_seasons, war, war_adj, war_per_season, ops, era, cost_per_war
//   seasons      : 기록이 있는 시즌 수 합,  paid_seasons : 연봉이 나간 시즌 수 합 (기록이 없는 해 포함, contract_eff 참고)
//   war          : 기록 그대로의 WAR 합,  war_adj : 단축 시즌을 정규 시즌으로 환산한 WAR 합
//   war_per_season : war_adj ÷ paid_seasons (기록이 없는 해는 WAR 0 으로 들어간다)
//   cost_per_war : 지급액(AAV × paid_seasons) ÷ 환산 WAR 합. 환산 WAR 합이 0 이하이거나 없으면 null
function analysis_aggregate(string $leagueId, int $yearFrom, int $yearTo, array $groupBy, string $role = ''): array
{
    [$yearFrom, $yearTo] = year_range($yearFrom, $yearTo);
    $options = aggregate_group_options();
    $groupBy = array_slice(array_values(array_filter($groupBy, fn ($g) => isset($options[$g]))), 0, 3);
    if (!$groupBy) {
        $groupBy = ['role'];
    }

    // GROUP BY 에는 별칭이 아니라 식 전체를 쓴다 (GROUP BY 는 별칭보다 원래 열 이름을 먼저 찾기 때문)
    $selects = $groups = $orders = [];
    foreach ($groupBy as $key) {
        $selects[] = $options[$key][1] . ' AS `' . $key . '`';   // $key 는 허용 목록의 키라서 안전하다
        $groups[]  = $options[$key][1];
        $orders[]  = '`' . $key . '`';
    }

    $sql = 'WITH ' . SQL_CTE_PS_YEAR . ', ' . SQL_CTE_PLAYER_ROLE . ', '
                   . sql_cte_season_scale() . ', ' . SQL_CTE_CONTRACT_EFF . '
            SELECT tt.league_id, l.currency, ' . implode(', ', $selects) . ',
                   COUNT(*)                                         AS contracts,
                   ROUND(AVG(c.aav))                                AS avg_aav,
                   MAX(c.aav)                                       AS max_aav,
                   MIN(c.aav)                                       AS min_aav,
                   SUM(c.total_value)                               AS sum_total,
                   ROUND(AVG(c.years), 1)                           AS avg_years,
                   SUM(cp.seasons)                                  AS seasons,
                   SUM(cp.paid_seasons)                             AS paid_seasons,
                   ROUND(SUM(cp.war), 1)                            AS war,
                   ROUND(SUM(cp.war_adj), 1)                        AS war_adj,
                   ROUND(SUM(cp.war_adj) / NULLIF(SUM(CASE WHEN cp.war IS NOT NULL THEN cp.paid_seasons END), 0), 2) AS war_per_season,
                   ROUND(SUM(cp.ops * cp.pa_ops) / NULLIF(SUM(CASE WHEN cp.ops IS NOT NULL THEN cp.pa_ops END), 0), 3) AS ops,
                   ROUND(SUM(cp.era * cp.ip) / NULLIF(SUM(CASE WHEN cp.era IS NOT NULL THEN cp.ip END), 0), 2)       AS era,
                   CASE WHEN SUM(cp.war_adj) > 0
                        THEN ROUND(SUM(CASE WHEN cp.war IS NOT NULL THEN cp.paid END) / SUM(cp.war_adj))
                   END                                              AS cost_per_war
              FROM fa_contracts c
              JOIN teams tt         ON tt.team_id = c.to_team_id
              JOIN leagues l        ON l.league_id = tt.league_id
              JOIN players p        ON p.player_id = c.player_id
              JOIN player_role r    ON r.player_id = c.player_id
              JOIN contract_eff cp  ON cp.contract_id = c.contract_id
             WHERE c.start_year BETWEEN ? AND ?';
    $params = [$yearFrom, $yearTo];

    if ($leagueId !== '') {
        $sql .= ' AND tt.league_id = ?';
        $params[] = $leagueId;
    }
    if ($role !== '') {
        $sql .= ' AND r.role = ?';
        $params[] = $role;
    }

    $sql .= ' GROUP BY tt.league_id, l.currency, ' . implode(', ', $groups)
          . ' ORDER BY tt.league_id DESC, ' . implode(', ', $orders);

    return db_all($sql, $params);
}

// =====================================================================================
// 2. OLAP 롤업 / 드릴다운
// =====================================================================================

// 리그 → 구단 → 연도 3단계 롤업.
// $level    : 'league' (리그 합계만) | 'team' (구단 소계까지) | 'year' (연도 상세까지)
// $teamId   : '' 이면 전체, 값이 있으면 그 구단만 (구단 행을 눌러 드릴다운할 때)
// 돌려주는 키: row_level('league'|'team'|'year'), league_id, currency, team_id, team_name, year_id,
//             seasons, fa_payroll, wins, losses, win_pct, postseason_cnt, payroll_per_win, contracts_signed
//   소계 행은 team_id 또는 year_id 가 null 이다. 전체 합계 행은 리그끼리 통화가 달라 만들지 않는다.
//   payroll_per_win 은 단축 시즌 승수를 정규 시즌으로 환산해서 나눈다 (wins 는 기록 그대로).
function analysis_rollup(string $leagueId, int $yearFrom, int $yearTo, string $level = 'team', string $teamId = ''): array
{
    [$yearFrom, $yearTo] = year_range($yearFrom, $yearTo);
    $sql = 'WITH ' . sql_cte_season_scale() . '
            SELECT *
              FROM (
                    SELECT t.league_id, ts.team_id, ts.year_id,
                           MAX(t.team_name)                                        AS team_name,
                           MAX(l.currency)                                         AS currency,
                           COUNT(*)                                                AS seasons,
                           SUM(ts.fa_payroll)                                      AS fa_payroll,
                           SUM(ts.wins)                                            AS wins,
                           SUM(ts.losses)                                          AS losses,
                           ROUND(SUM(ts.wins) / NULLIF(SUM(ts.wins + ts.losses), 0), 3) AS win_pct,
                           SUM(ts.postseason <> \'NONE\')                          AS postseason_cnt,
                           ROUND(SUM(ts.fa_payroll) / NULLIF(SUM(ts.wins * COALESCE(s.scale, 1)), 0)) AS payroll_per_win,
                           SUM(COALESCE(cs.signed, 0))                             AS contracts_signed
                      FROM team_seasons ts
                      JOIN teams t   ON t.team_id = ts.team_id
                      JOIN leagues l ON l.league_id = t.league_id
                      LEFT JOIN season_scale s ON s.league_id = t.league_id AND s.year_id = ts.year_id
                      LEFT JOIN (SELECT to_team_id, start_year, COUNT(*) AS signed
                                   FROM fa_contracts GROUP BY to_team_id, start_year) cs
                             ON cs.to_team_id = ts.team_id AND cs.start_year = ts.year_id
                     WHERE ts.year_id BETWEEN ? AND ?';
    $params = [$yearFrom, $yearTo];

    if ($leagueId !== '') {
        $sql .= ' AND t.league_id = ?';
        $params[] = $leagueId;
    }
    if ($teamId !== '') {
        $sql .= ' AND ts.team_id = ?';
        $params[] = $teamId;
    }

    $sql .= '        GROUP BY t.league_id, ts.team_id, ts.year_id WITH ROLLUP
                   ) r
             WHERE r.league_id IS NOT NULL';

    // 보여줄 단계: 허용 목록에서 고른 조건만 붙인다
    $levelWhere = [
        'league' => ' AND r.team_id IS NULL',
        'team'   => ' AND r.year_id IS NULL',
        'year'   => '',
    ];
    $sql .= $levelWhere[$level] ?? $levelWhere['team'];

    // 리그 → (구단 이름순 → 구단 소계) → 리그 소계 순서
    $sql .= ' ORDER BY r.league_id DESC, r.team_id IS NULL, r.team_name, r.year_id IS NULL, r.year_id';

    $rows = db_all($sql, $params);
    foreach ($rows as &$row) {
        $row['row_level'] = $row['team_id'] === null ? 'league' : ($row['year_id'] === null ? 'team' : 'year');
        if ($row['team_id'] === null) {
            $row['team_name'] = null;   // 리그 소계 행의 MAX(team_name) 은 의미 없는 값이라 비운다
        }
    }
    unset($row);
    return $rows;
}

// 드릴다운 마지막 단계: 한 구단·한 시즌에 유효했던 FA 계약과 그 시즌 선수 성적
// 키: contract_id, player_id, player_name, start_year, end_year, years, aav, total_value, currency,
//     contract_season(계약 몇 년차), games, war, pa, ops, ip, era
function analysis_drill_contracts(string $teamId, int $year): array
{
    return db_all(
        'WITH ' . SQL_CTE_PS_YEAR . '
         SELECT c.contract_id, c.player_id, ' . SQL_PLAYER_NAME . ' AS player_name,
                c.start_year, c.end_year, c.years, c.aav, c.total_value, l.currency,
                ? - c.start_year + 1                              AS contract_season,
                y.games, y.war, y.pa,
                ROUND(y.ops_pa / NULLIF(y.pa_ops, 0), 3)          AS ops,
                y.ip,
                ROUND(y.era_ip / NULLIF(y.ip, 0), 2)              AS era
           FROM fa_contracts c
           JOIN players p   ON p.player_id = c.player_id
           JOIN teams t     ON t.team_id = c.to_team_id
           JOIN leagues l   ON l.league_id = t.league_id
           LEFT JOIN ps_year y ON y.player_id = c.player_id AND y.year_id = ?
          WHERE c.to_team_id = ? AND ? BETWEEN c.start_year AND c.end_year
          ORDER BY c.aav DESC',
        [$year, $year, $teamId, $year]
    );
}

// =====================================================================================
// 3. 순위 분석
// =====================================================================================

// 지표별 정의: [순위 기준 SQL 식, 순위 정렬 (base 의 열 이름 기준, 좋은 계약이 앞), 포함 조건]
//   war : 환산 WAR 1 당 비용 (낮을수록 효율적). 지급액은 기록이 없는 해까지 포함 (contract_eff 참고).
//         WAR 기록이 있는 리그(MLB)에서 연봉이 1시즌 이상 나간 계약 전부.
//         환산 WAR 합이 0 이하이면 WAR 당 비용을 계산할 수 없어서 metric_value 는 null 이고,
//         WAR 이 양수인 계약 전부의 뒤에 둔다. 그 안에서는 지급액이 많을수록, 같으면 WAR 이 낮을수록 아래다.
//         (0 이하 WAR 로 나누면 음수 비용이 1등이 되므로 나누지 않는다)
//   ops : 계약 기간 OPS (높을수록 좋음). 타석 200 이상
//   era : 계약 기간 ERA (낮을수록 좋음). 이닝 50 이상
function ranking_metric_sql(string $metric): array
{
    $metrics = [
        // 화면에 보이는 war_adj(소수 첫째 자리)가 0.0 인데 비용이 나오지 않게 반올림한 값으로 판단한다
        'war' => ['CASE WHEN ROUND(cp.war_adj, 1) > 0 THEN ROUND(cp.paid / cp.war_adj) END',
                  'metric_value IS NULL, metric_value ASC, paid ASC, war_adj DESC',
                  'cp.war_adj IS NOT NULL AND cp.paid_seasons > 0'],
        'ops' => ['ROUND(cp.ops, 3)', 'metric_value DESC', 'cp.pa_ops >= 200'],
        'era' => ['ROUND(cp.era, 2)', 'metric_value ASC',  'cp.ip >= 50'],
    ];
    return $metrics[$metric] ?? $metrics['war'];
}

// 순위 계산 공통 부분. [SQL, params] 를 돌려준다 (analysis_ranking, analysis_ranking_quartiles 가 사용)
function ranking_base_sql(array $f): array
{
    [$valueSql, $orderSql, $validSql] = ranking_metric_sql($f['metric'] ?? 'war');
    // 순위를 나누는 범위: 'year' 는 같은 리그·같은 시작 연도 안에서, 'all' 은 리그 전체에서
    $partition = (($f['scope'] ?? 'year') === 'all') ? 'league_id' : 'league_id, start_year';

    $sql = 'WITH ' . SQL_CTE_PS_YEAR . ', ' . SQL_CTE_PLAYER_ROLE . ', '
                   . sql_cte_season_scale() . ', ' . SQL_CTE_CONTRACT_EFF . ',
            base AS (
                SELECT c.contract_id, c.player_id, ' . SQL_PLAYER_NAME . ' AS player_name,
                       tt.league_id, l.currency, tt.team_name AS to_team_name,
                       c.start_year, c.end_year, c.years, c.total_value, c.aav,
                       r.role, p.primary_pos,
                       cp.seasons, cp.paid_seasons, cp.paid, ROUND(cp.war, 1) AS war, ROUND(cp.war_adj, 1) AS war_adj,
                       ROUND(cp.war_adj / NULLIF(cp.paid_seasons, 0), 2) AS war_per_season,
                       ROUND(cp.ops, 3) AS ops, ROUND(cp.era, 2) AS era,
                       ' . $valueSql . ' AS metric_value
                  FROM fa_contracts c
                  JOIN teams tt         ON tt.team_id = c.to_team_id
                  JOIN leagues l        ON l.league_id = tt.league_id
                  JOIN players p        ON p.player_id = c.player_id
                  JOIN player_role r    ON r.player_id = c.player_id
                  JOIN contract_eff cp  ON cp.contract_id = c.contract_id
                 WHERE ' . $validSql . ' AND c.start_year BETWEEN ? AND ?';
    $params = year_range((int) ($f['year_from'] ?? 2016), (int) ($f['year_to'] ?? 2024));

    if (($f['league'] ?? '') !== '') {
        $sql .= ' AND tt.league_id = ?';
        $params[] = $f['league'];
    }
    if (($f['role'] ?? '') !== '') {
        $sql .= ' AND r.role = ?';
        $params[] = $f['role'];
    }
    if (($f['position'] ?? '') !== '') {
        $sql .= ' AND p.primary_pos = ?';
        $params[] = $f['position'];
    }
    // 최소 총액은 리그 단위(MLB 달러, KBO 만원) 그대로의 숫자라서 리그를 하나 골랐을 때만 건다.
    // 리그가 '전체'이면 같은 숫자가 두 통화에 동시에 걸려 한쪽 리그가 통째로 빠지므로 무시한다.
    if (!empty($f['min_total']) && ($f['league'] ?? '') !== '') {
        $sql .= ' AND c.total_value >= ?';
        $params[] = (int) $f['min_total'];
    }
    // 최소 시즌 수: 표본이 작은 계약은 뺀다. 기본 2, 1~10
    //   war     : 연봉이 나간 시즌 수(paid_seasons) 기준. 기록이 없는 해도 WAR 0 으로 지표에 들어가기 때문
    //   ops/era : 기록이 있는 시즌 수(seasons) 기준. 기록이 있는 시즌만으로 계산하는 지표이기 때문
    $seasonCol = (($f['metric'] ?? 'war') === 'ops' || ($f['metric'] ?? 'war') === 'era') ? 'cp.seasons' : 'cp.paid_seasons';
    $sql .= ' AND ' . $seasonCol . ' >= ?';
    $params[] = max(1, min(10, (int) ($f['min_seasons'] ?? 2)));

    // good / bad 계약 판정 (고른 순위 지표와 상관없이 항상 WAR 기준, WAR 이 없는 KBO 는 null)
    //   wps_pct : 리그 안에서 시즌당 WAR(war_per_season) 이 상위 몇 % 인지 (CUME_DIST, 같은 값은 같은 %)
    //   grade   : 'bad'  = 환산 WAR 합(war_adj, 소수 첫째 자리) 0 이하
    //             'good' = bad 가 아니고 wps_pct 25 이하 (리그 안 시즌당 WAR 상위 25%)
    //             null   = 그 사이, 또는 WAR 이 없는 리그
    //   비교 대상은 이 화면의 조건(연도·역할·포지션·최소 총액·최소 시즌)을 통과한 같은 리그 계약이다.
    $sql .= '),
            ranked0 AS (
                SELECT base.*,
                       RANK()       OVER (PARTITION BY ' . $partition . ' ORDER BY ' . $orderSql . ') AS metric_rank,
                       DENSE_RANK() OVER (PARTITION BY ' . $partition . ' ORDER BY aav DESC)                   AS aav_rank,
                       NTILE(4)     OVER (PARTITION BY league_id ORDER BY aav DESC)                             AS aav_quartile,
                       ROUND(100 * PERCENT_RANK() OVER (PARTITION BY ' . $partition . ' ORDER BY ' . $orderSql . '), 1) AS metric_pct,
                       COUNT(*)     OVER (PARTITION BY ' . $partition . ')                                     AS group_size,
                       CASE WHEN war_per_season IS NOT NULL
                            THEN ROUND(100 * CUME_DIST() OVER (PARTITION BY league_id ORDER BY war_per_season DESC), 1)
                       END                                                                                       AS wps_pct
                  FROM base
            ),
            ranked AS (
                SELECT ranked0.*,
                       CASE WHEN wps_pct IS NULL THEN NULL
                            WHEN war_adj <= 0    THEN \'bad\'
                            WHEN wps_pct <= 25   THEN \'good\'
                       END AS grade
                  FROM ranked0
            )';
    return [$sql, $params];
}

// 계약 효율 순위
//   $f 키: league('MLB'|'KBO'|''), year_from, year_to, metric('war'|'ops'|'era'), scope('year'|'all'),
//          role('P'|'B'|''), position(''|MLB 포지션), min_total(이 금액 이상 계약만, 리그 단위 그대로. league 가 '' 이면 무시),
//          min_seasons(이 시즌 수 이상인 계약만, 기본 2, 1~10. war 는 연봉이 나간 시즌, ops/era 는 기록이 있는 시즌 기준.
//                      1년 계약까지 보려면 1), top(각 그룹 상위 N)
// 돌려주는 키: contract_id, player_id, player_name, league_id, currency, to_team_name, start_year, end_year, years,
//             total_value, aav, role, primary_pos, seasons(기록 시즌 수), paid_seasons(연봉 나간 시즌 수),
//             paid(AAV × paid_seasons), war, war_adj(단축 시즌 환산 WAR), ops, era,
//             metric_value(순위 기준값, war 지표는 paid ÷ war_adj. war_adj 가 0 이하이면 null 이고 순위는 맨 뒤쪽),
//             metric_rank(그룹 안 순위), aav_rank(그룹 안 몸값 순위),
//             aav_quartile(리그 안 몸값 4분위, 1=최고액), metric_pct(상위 몇 %, 0=1등), group_size,
//             war_per_season(war_adj ÷ paid_seasons), wps_pct(리그 안 시즌당 WAR 상위 몇 %),
//             grade('good' | 'bad' | null, ranking_base_sql 의 판정 주석 참고. KBO 는 항상 null)
//   metric_rank 와 aav_rank 의 차이가 크면 "몸값에 비해 잘한/못한" 계약이다.
function analysis_ranking(array $f): array
{
    [$sql, $params] = ranking_base_sql($f);
    $sql .= ' SELECT * FROM ranked WHERE metric_rank <= ?
              ORDER BY league_id DESC, ' . ((($f['scope'] ?? 'year') === 'all') ? '' : 'start_year DESC, ') . 'metric_rank, aav DESC';
    $params[] = max(1, min(100, (int) ($f['top'] ?? 10)));
    return db_all($sql, $params);
}

// 몸값 4분위별 성과 요약 (순위 결과를 분위로 다시 묶은 분석)
// 키: league_id, currency, aav_quartile, contracts, min_aav, max_aav, avg_aav, avg_metric, avg_rank_pct, war_le0,
//     good_contracts, bad_contracts, good_pct, bad_pct
//   avg_metric : metric_value 평균. war 지표는 WAR 이 양수인 계약만의 평균 (0 이하는 null 이라 빠진다)
//   war_le0    : 환산 WAR 0 이하라 metric_value 가 null 인 계약 수 (ops/era 지표는 항상 0)
//   avg_rank_pct 는 WAR 0 이하 계약의 순위까지 포함한 평균이다.
//   good_contracts / bad_contracts : grade 가 good / bad 인 계약 수 (고른 지표와 상관없이 WAR 기준)
//   good_pct / bad_pct : 그 분위 계약 중 good / bad 비율(%). WAR 이 없는 리그(KBO)는 네 키 모두 null
function analysis_ranking_quartiles(array $f): array
{
    [$sql, $params] = ranking_base_sql($f);
    $sql .= ' SELECT league_id, MAX(currency) AS currency, aav_quartile,
                     COUNT(*) AS contracts, MIN(aav) AS min_aav, MAX(aav) AS max_aav, ROUND(AVG(aav)) AS avg_aav,
                     ROUND(AVG(metric_value), 3) AS avg_metric, ROUND(AVG(metric_pct), 1) AS avg_rank_pct,
                     SUM(metric_value IS NULL) AS war_le0,
                     SUM(CASE WHEN wps_pct IS NULL THEN NULL WHEN grade = \'good\' THEN 1 ELSE 0 END) AS good_contracts,
                     SUM(CASE WHEN wps_pct IS NULL THEN NULL WHEN grade = \'bad\'  THEN 1 ELSE 0 END) AS bad_contracts,
                     ROUND(100 * SUM(CASE WHEN wps_pct IS NULL THEN NULL WHEN grade = \'good\' THEN 1 ELSE 0 END)
                               / NULLIF(COUNT(wps_pct), 0), 1)                                           AS good_pct,
                     ROUND(100 * SUM(CASE WHEN wps_pct IS NULL THEN NULL WHEN grade = \'bad\'  THEN 1 ELSE 0 END)
                               / NULLIF(COUNT(wps_pct), 0), 1)                                           AS bad_pct
                FROM ranked
               GROUP BY league_id, aav_quartile
               ORDER BY league_id DESC, aav_quartile';
    return db_all($sql, $params);
}

// =====================================================================================
// 4. 윈도우 함수
// =====================================================================================

// 선수 한 명의 시즌별 성적 추이.
// $metric : 'war' | 'ops' | 'era'  (KBO 선수는 WAR 이 없으므로 ops/era 를 쓴다)
// $span   : 이동평균 구간 2 또는 3 (시즌)
// 같은 해·같은 리그의 FA 계약 선수들과 비교하기 위해 윈도우를 '리그 전체' 기준으로 먼저 계산한 뒤 이 선수만 고른다.
// 돌려주는 키: year_id, age, team_ids, games, pa, ip, war, ops, era,
//             metric_value(고른 지표 값. war 는 단축 시즌을 정규 시즌으로 환산한 값, war 키는 기록 그대로),
//             season_scale(환산 배율, 정규 시즌 1 / 2020 MLB 2.7), prev_year/prev_value(LAG: 바로 앞 기록 시즌. DB 에 빠진 해가 있으면
//             바로 전 해가 아닐 수 있다), change(앞 시즌 대비),
//             cumulative(누적 합, WAR 에서만 의미 있음), moving_avg(이동평균),
//             league_avg(같은 해 리그 FA 선수 평균), league_rank(같은 해 리그 FA 선수 중 순위), league_count,
//             contract_id, contract_season(계약 몇 년차, 계약 기간 밖이면 null)
function analysis_window(string $playerId, int $yearFrom, int $yearTo, string $metric = 'war', int $span = 3): array
{
    [$yearFrom, $yearTo] = year_range($yearFrom, $yearTo);
    $values = [
        'war' => ['ROUND(y.war * COALESCE(s.scale, 1), 2)', 'DESC'],   // 단축 시즌은 정규 시즌으로 환산
        'ops' => ['ROUND(y.ops_pa / NULLIF(y.pa_ops, 0), 3)', 'DESC'],
        'era' => ['ROUND(y.era_ip / NULLIF(y.ip, 0), 2)', 'ASC'],
    ];
    [$valueSql, $rankDir] = $values[$metric] ?? $values['war'];
    $preceding = in_array($span, [2, 3], true) ? $span - 1 : 2;   // 정수로 고정

    $sql = 'WITH ' . SQL_CTE_PS_YEAR . ', ' . sql_cte_season_scale() . ',
            yv AS (
                SELECT y.*, ' . $valueSql . ' AS metric_value,
                       ROUND(y.ops_pa / NULLIF(y.pa_ops, 0), 3) AS ops,
                       ROUND(y.era_ip / NULLIF(y.ip, 0), 2)     AS era,
                       COALESCE(s.scale, 1)                     AS season_scale
                  FROM ps_year y
                  LEFT JOIN season_scale s ON s.league_id = y.league_id AND s.year_id = y.year_id
            ),
            w AS (
                SELECT yv.player_id, yv.year_id, yv.league_id, yv.team_ids, yv.games, yv.pa, yv.ip,
                       yv.war, yv.ops, yv.era, yv.metric_value, yv.season_scale,
                       LAG(yv.year_id)      OVER (PARTITION BY yv.player_id ORDER BY yv.year_id)              AS prev_year,
                       LAG(yv.metric_value) OVER (PARTITION BY yv.player_id ORDER BY yv.year_id)              AS prev_value,
                       yv.metric_value - LAG(yv.metric_value) OVER (PARTITION BY yv.player_id ORDER BY yv.year_id)   AS change_value,
                       SUM(yv.metric_value) OVER (PARTITION BY yv.player_id ORDER BY yv.year_id
                                           ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW)          AS cumulative,
                       AVG(yv.metric_value) OVER (PARTITION BY yv.player_id ORDER BY yv.year_id
                                           ROWS BETWEEN ' . $preceding . ' PRECEDING AND CURRENT ROW) AS moving_avg,
                       AVG(yv.metric_value) OVER (PARTITION BY yv.league_id, yv.year_id)                     AS league_avg,
                       RANK()        OVER (PARTITION BY yv.league_id, yv.year_id ORDER BY yv.metric_value IS NULL, yv.metric_value ' . $rankDir . ') AS league_rank,
                       COUNT(yv.metric_value) OVER (PARTITION BY yv.league_id, yv.year_id)                   AS league_count
                  FROM yv
            )
            SELECT w.year_id, w.year_id - YEAR(p.birth_date) AS age, w.team_ids, w.games, w.pa, w.ip,
                   w.war, w.ops, w.era, w.metric_value, w.season_scale, w.prev_year, w.prev_value,
                   ROUND(w.change_value, 3) AS `change`,
                   ROUND(w.cumulative, 3)   AS cumulative,
                   ROUND(w.moving_avg, 3)   AS moving_avg,
                   ROUND(w.league_avg, 3)   AS league_avg,
                   CASE WHEN w.metric_value IS NULL THEN NULL ELSE w.league_rank END AS league_rank,
                   w.league_count,
                   c.contract_id,
                   CASE WHEN c.contract_id IS NULL THEN NULL ELSE w.year_id - c.start_year + 1 END AS contract_season
              FROM w
              JOIN players p ON p.player_id = w.player_id
              LEFT JOIN fa_contracts c
                     ON c.contract_id = (SELECT c2.contract_id FROM fa_contracts c2
                                          WHERE c2.player_id = w.player_id
                                            AND w.year_id BETWEEN c2.start_year AND c2.end_year
                                          ORDER BY c2.start_year DESC LIMIT 1)
             WHERE w.player_id = ? AND w.year_id BETWEEN ? AND ?
             ORDER BY w.year_id';

    return db_all($sql, [$playerId, $yearFrom, $yearTo]);
}

// 분석 화면의 연도 select 범위 (계약 시작 연도 최소~최대)
// 키: min_year, max_year
function contract_year_range(): array
{
    return db_one('SELECT MIN(start_year) AS min_year, MAX(start_year) AS max_year FROM fa_contracts')
        ?? ['min_year' => 2016, 'max_year' => 2024];
}

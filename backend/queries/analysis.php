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
//             avg_years, seasons, war, war_per_season, ops, era, cost_per_war
//   cost_per_war : 계약 기간 중 지급액(AAV × 기록 시즌 수) ÷ WAR 합. WAR 합이 0 이하이거나 없으면 null
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

    $sql = 'WITH ' . SQL_CTE_PS_YEAR . ', ' . SQL_CTE_CONTRACT_PERF . ', ' . SQL_CTE_PLAYER_ROLE . '
            SELECT tt.league_id, l.currency, ' . implode(', ', $selects) . ',
                   COUNT(*)                                         AS contracts,
                   ROUND(AVG(c.aav))                                AS avg_aav,
                   MAX(c.aav)                                       AS max_aav,
                   MIN(c.aav)                                       AS min_aav,
                   SUM(c.total_value)                               AS sum_total,
                   ROUND(AVG(c.years), 1)                           AS avg_years,
                   SUM(cp.seasons)                                  AS seasons,
                   ROUND(SUM(cp.war), 1)                            AS war,
                   ROUND(SUM(cp.war) / NULLIF(SUM(CASE WHEN cp.war IS NOT NULL THEN cp.seasons END), 0), 2) AS war_per_season,
                   ROUND(SUM(cp.ops * cp.pa_ops) / NULLIF(SUM(CASE WHEN cp.ops IS NOT NULL THEN cp.pa_ops END), 0), 3) AS ops,
                   ROUND(SUM(cp.era * cp.ip) / NULLIF(SUM(CASE WHEN cp.era IS NOT NULL THEN cp.ip END), 0), 2)       AS era,
                   CASE WHEN SUM(cp.war) > 0
                        THEN ROUND(SUM(CASE WHEN cp.war IS NOT NULL THEN cp.paid END) / SUM(cp.war))
                   END                                              AS cost_per_war
              FROM fa_contracts c
              JOIN teams tt         ON tt.team_id = c.to_team_id
              JOIN leagues l        ON l.league_id = tt.league_id
              JOIN players p        ON p.player_id = c.player_id
              JOIN player_role r    ON r.player_id = c.player_id
              JOIN contract_perf cp ON cp.contract_id = c.contract_id
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
function analysis_rollup(string $leagueId, int $yearFrom, int $yearTo, string $level = 'team', string $teamId = ''): array
{
    [$yearFrom, $yearTo] = year_range($yearFrom, $yearTo);
    $sql = 'SELECT *
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
                           ROUND(SUM(ts.fa_payroll) / NULLIF(SUM(ts.wins), 0))     AS payroll_per_win,
                           SUM(COALESCE(cs.signed, 0))                             AS contracts_signed
                      FROM team_seasons ts
                      JOIN teams t   ON t.team_id = ts.team_id
                      JOIN leagues l ON l.league_id = t.league_id
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

// 지표별 정의: [순위 기준 SQL 식, 정렬 방향, 포함 조건]
//   war : WAR 1 당 비용 (낮을수록 효율적). 계약 기간 WAR 합이 0 보다 커야 한다 → MLB 만
//   ops : 계약 기간 OPS (높을수록 좋음). 타석 200 이상
//   era : 계약 기간 ERA (낮을수록 좋음). 이닝 50 이상
function ranking_metric_sql(string $metric): array
{
    $metrics = [
        'war' => ['ROUND(cp.paid / cp.war)', 'ASC',  'cp.war > 0'],
        'ops' => ['ROUND(cp.ops, 3)',        'DESC', 'cp.pa_ops >= 200'],
        'era' => ['ROUND(cp.era, 2)',        'ASC',  'cp.ip >= 50'],
    ];
    return $metrics[$metric] ?? $metrics['war'];
}

// 순위 계산 공통 부분. [SQL, params] 를 돌려준다 (analysis_ranking, analysis_ranking_quartiles 가 사용)
function ranking_base_sql(array $f): array
{
    [$valueSql, $dir, $validSql] = ranking_metric_sql($f['metric'] ?? 'war');
    // 순위를 나누는 범위: 'year' 는 같은 리그·같은 시작 연도 안에서, 'all' 은 리그 전체에서
    $partition = (($f['scope'] ?? 'year') === 'all') ? 'league_id' : 'league_id, start_year';

    $sql = 'WITH ' . SQL_CTE_PS_YEAR . ', ' . SQL_CTE_CONTRACT_PERF . ', ' . SQL_CTE_PLAYER_ROLE . ',
            base AS (
                SELECT c.contract_id, c.player_id, ' . SQL_PLAYER_NAME . ' AS player_name,
                       tt.league_id, l.currency, tt.team_name AS to_team_name,
                       c.start_year, c.end_year, c.years, c.total_value, c.aav,
                       r.role, p.primary_pos,
                       cp.seasons, cp.paid, ROUND(cp.war, 1) AS war, ROUND(cp.ops, 3) AS ops, ROUND(cp.era, 2) AS era,
                       ' . $valueSql . ' AS metric_value
                  FROM fa_contracts c
                  JOIN teams tt         ON tt.team_id = c.to_team_id
                  JOIN leagues l        ON l.league_id = tt.league_id
                  JOIN players p        ON p.player_id = c.player_id
                  JOIN player_role r    ON r.player_id = c.player_id
                  JOIN contract_perf cp ON cp.contract_id = c.contract_id
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
    if (!empty($f['min_total'])) {
        $sql .= ' AND c.total_value >= ?';
        $params[] = (int) $f['min_total'];
    }

    $sql .= '),
            ranked AS (
                SELECT base.*,
                       RANK()       OVER (PARTITION BY ' . $partition . ' ORDER BY metric_value ' . $dir . ') AS metric_rank,
                       DENSE_RANK() OVER (PARTITION BY ' . $partition . ' ORDER BY aav DESC)                   AS aav_rank,
                       NTILE(4)     OVER (PARTITION BY league_id ORDER BY aav DESC)                             AS aav_quartile,
                       ROUND(100 * PERCENT_RANK() OVER (PARTITION BY ' . $partition . ' ORDER BY metric_value ' . $dir . '), 1) AS metric_pct,
                       COUNT(*)     OVER (PARTITION BY ' . $partition . ')                                     AS group_size
                  FROM base
            )';
    return [$sql, $params];
}

// 계약 효율 순위
//   $f 키: league('MLB'|'KBO'|''), year_from, year_to, metric('war'|'ops'|'era'), scope('year'|'all'),
//          role('P'|'B'|''), position(''|MLB 포지션), min_total(이 금액 이상 계약만, 리그 단위 그대로), top(각 그룹 상위 N)
// 돌려주는 키: contract_id, player_id, player_name, league_id, currency, to_team_name, start_year, end_year, years,
//             total_value, aav, role, primary_pos, seasons, paid, war, ops, era,
//             metric_value(순위 기준값), metric_rank(그룹 안 순위), aav_rank(그룹 안 몸값 순위),
//             aav_quartile(리그 안 몸값 4분위, 1=최고액), metric_pct(상위 몇 %, 0=1등), group_size
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
// 키: league_id, currency, aav_quartile, contracts, min_aav, max_aav, avg_aav, avg_metric, avg_rank_pct
function analysis_ranking_quartiles(array $f): array
{
    [$sql, $params] = ranking_base_sql($f);
    $sql .= ' SELECT league_id, MAX(currency) AS currency, aav_quartile,
                     COUNT(*) AS contracts, MIN(aav) AS min_aav, MAX(aav) AS max_aav, ROUND(AVG(aav)) AS avg_aav,
                     ROUND(AVG(metric_value), 3) AS avg_metric, ROUND(AVG(metric_pct), 1) AS avg_rank_pct
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
//             metric_value(고른 지표 값), prev_year/prev_value(LAG: 바로 앞 기록 시즌. DB 에 빠진 해가 있으면
//             바로 전 해가 아닐 수 있다), change(앞 시즌 대비),
//             cumulative(누적 합, WAR 에서만 의미 있음), moving_avg(이동평균),
//             league_avg(같은 해 리그 FA 선수 평균), league_rank(같은 해 리그 FA 선수 중 순위), league_count,
//             contract_id, contract_season(계약 몇 년차, 계약 기간 밖이면 null)
function analysis_window(string $playerId, int $yearFrom, int $yearTo, string $metric = 'war', int $span = 3): array
{
    [$yearFrom, $yearTo] = year_range($yearFrom, $yearTo);
    $values = [
        'war' => ['y.war', 'DESC'],
        'ops' => ['ROUND(y.ops_pa / NULLIF(y.pa_ops, 0), 3)', 'DESC'],
        'era' => ['ROUND(y.era_ip / NULLIF(y.ip, 0), 2)', 'ASC'],
    ];
    [$valueSql, $rankDir] = $values[$metric] ?? $values['war'];
    $preceding = in_array($span, [2, 3], true) ? $span - 1 : 2;   // 정수로 고정

    $sql = 'WITH ' . SQL_CTE_PS_YEAR . ',
            yv AS (
                SELECT y.*, ' . $valueSql . ' AS metric_value,
                       ROUND(y.ops_pa / NULLIF(y.pa_ops, 0), 3) AS ops,
                       ROUND(y.era_ip / NULLIF(y.ip, 0), 2)     AS era
                  FROM ps_year y
            ),
            w AS (
                SELECT yv.player_id, yv.year_id, yv.league_id, yv.team_ids, yv.games, yv.pa, yv.ip,
                       yv.war, yv.ops, yv.era, yv.metric_value,
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
                   w.war, w.ops, w.era, w.metric_value, w.prev_year, w.prev_value,
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

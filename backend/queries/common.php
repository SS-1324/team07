<?php
// backend/queries/common.php
// 담당: 백엔드 신수연
// 여러 쿼리 파일이 같이 쓰는 SQL 조각과 선택 목록.
// 여기 있는 SQL 조각에는 사용자 입력이 들어가지 않는다 (입력은 항상 ? 와 $params 로 넘긴다).

// 선수 표시 이름: 한글 이름이 있으면 한글(KBO 선수, 한국인 MLB 선수), 없으면 영문 "이름 성"
const SQL_PLAYER_NAME = "COALESCE(p.name_kor, CONCAT_WS(' ', p.name_first, p.name_last))";

// 선수-연도별 성적 (시즌 중 이적해 stint 가 여러 개면 합친다)
//   war    : stint 합 (KBO 는 NULL)
//   ops    : 타석 가중 평균,  era : 이닝 가중 평균
//   pa_ops : OPS 가 있는 타석 수 (여러 연도를 다시 가중 평균할 때 사용)
const SQL_CTE_PS_YEAR = "ps_year AS (
    SELECT ps.player_id, ps.year_id,
           MIN(t.league_id)                                      AS league_id,
           GROUP_CONCAT(ps.team_id ORDER BY ps.stint SEPARATOR '/') AS team_ids,
           SUM(ps.games)                                         AS games,
           SUM(ps.war)                                           AS war,
           SUM(ps.pa)                                            AS pa,
           SUM(ps.hr)                                            AS hr,
           SUM(ps.ops * ps.pa)                                   AS ops_pa,
           SUM(CASE WHEN ps.ops IS NOT NULL THEN ps.pa END)      AS pa_ops,
           SUM(ps.ip)                                            AS ip,
           SUM(ps.era * ps.ip)                                   AS era_ip,
           SUM(ps.p_so)                                          AS p_so
      FROM player_seasons ps
      JOIN teams t ON t.team_id = ps.team_id
     GROUP BY ps.player_id, ps.year_id
)";

// 계약별 '계약 기간 중' 성적 (start_year ~ end_year 사이에 DB 에 있는 시즌만)
//   seasons : 기록이 있는 시즌 수,  paid : 그 시즌 동안 받은 금액 추정 (AAV × seasons)
const SQL_CTE_CONTRACT_PERF = "contract_perf AS (
    SELECT c.contract_id,
           COUNT(y.year_id)                          AS seasons,
           c.aav * COUNT(y.year_id)                  AS paid,
           SUM(y.war)                                AS war,
           SUM(y.ops_pa) / NULLIF(SUM(y.pa_ops), 0)  AS ops,
           SUM(y.pa_ops)                             AS pa_ops,
           SUM(y.era_ip) / NULLIF(SUM(y.ip), 0)      AS era,
           SUM(y.ip)                                 AS ip
      FROM fa_contracts c
      LEFT JOIN ps_year y
             ON y.player_id = c.player_id AND y.year_id BETWEEN c.start_year AND c.end_year
     GROUP BY c.contract_id, c.aav
)";

// 선수 역할: 투수(P) / 타자(B).
// MLB 는 primary_pos 로 정한다. KBO 는 primary_pos 가 없어서 기록으로 정한다 (이닝×3 이 타석보다 많으면 투수).
const SQL_CTE_PLAYER_ROLE = "player_role AS (
    SELECT p.player_id,
           CASE
             WHEN p.primary_pos IN ('SP', 'RP') THEN 'P'
             WHEN p.primary_pos IS NOT NULL      THEN 'B'
             WHEN COALESCE(SUM(ps.ip), 0) * 3 > COALESCE(SUM(ps.pa), 0) THEN 'P'
             ELSE 'B'
           END AS role
      FROM players p
      LEFT JOIN player_seasons ps ON ps.player_id = p.player_id
     GROUP BY p.player_id, p.primary_pos
)";

// ---- 화면 select / 체크박스에 쓰는 선택 목록 (값 => 화면 표시)

function role_options(): array
{
    return ['P' => '투수', 'B' => '타자'];
}

// MLB 만 값이 있다 (KBO 선수는 primary_pos 가 비어 있음)
function position_options(): array
{
    return [
        'SP' => '선발투수', 'RP' => '구원투수', 'C' => '포수', '1B' => '1루수', '2B' => '2루수',
        '3B' => '3루수', 'SS' => '유격수', 'LF' => '좌익수', 'CF' => '중견수', 'RF' => '우익수', 'DH' => '지명타자',
    ];
}

function hand_options(): array
{
    return ['R' => '우', 'L' => '좌', 'B' => '양'];
}

// 성과 지표. KBO 는 WAR 이 없어서 WAR 을 고르면 MLB 결과만 나온다.
function metric_options(): array
{
    return ['war' => 'WAR', 'ops' => 'OPS (타자)', 'era' => 'ERA (투수)'];
}

// 포스트시즌 코드 → 화면 표시 (리그마다 코드가 다르다)
function postseason_label(?string $code): string
{
    $labels = [
        'NONE' => '-',
        // MLB
        'WS' => '월드시리즈 우승', 'CS' => '리그 우승', 'DS' => '지구 우승', 'WC' => '와일드카드',
        // KBO
        'KS' => '한국시리즈 우승', 'KSR' => '한국시리즈 준우승', 'PO' => '플레이오프',
        'SPO' => '준플레이오프',
    ];
    return $labels[$code ?? 'NONE'] ?? (string) $code;
}

// 연도 범위: 시작이 끝보다 크게 들어오면 (화면에서 거꾸로 고른 경우) 바꿔서 돌려준다
function year_range(int $from, int $to): array
{
    return $from <= $to ? [$from, $to] : [$to, $from];
}

// IN (?, ?, ...) 자리표시자를 개수만큼 만든다
function sql_placeholders(int $count): string
{
    return implode(', ', array_fill(0, max(1, $count), '?'));
}

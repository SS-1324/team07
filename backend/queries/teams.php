<?php
// backend/queries/teams.php
// 담당: 백엔드
// 구단 관련 SELECT. 화면(pages)은 이 함수들이 돌려주는 배열만 사용한다.

// 필터 select에 넣을 리그 목록
function leagues_all(): array
{
    return db_all('SELECT league_id, league_name, currency FROM leagues ORDER BY league_id DESC');
}

// 필터 select에 넣을 시즌 목록
function seasons_all(): array
{
    return array_column(
        db_all('SELECT DISTINCT year_id FROM team_seasons ORDER BY year_id DESC'),
        'year_id'
    );
}

// 구단 종합 비교: 선택한 리그·시즌의 구단별 성적과 FA 지출
// $sort 는 화면에서 input_choice()로 허용 목록을 통과한 값만 넘긴다.
function team_seasons_list(string $leagueId, int $year, string $keyword = '', string $sort = 'fa_payroll'): array
{
    $sql = 'SELECT t.team_id, t.team_name, t.sub_league, l.currency,
                   ts.wins, ts.losses, ts.win_pct, ts.league_rank, ts.postseason, ts.fa_payroll
              FROM team_seasons ts
              JOIN teams t   ON t.team_id = ts.team_id
              JOIN leagues l ON l.league_id = t.league_id
             WHERE t.league_id = ? AND ts.year_id = ?';
    $params = [$leagueId, $year];

    // 검색어가 있을 때만 조건을 추가한다 (동적 쿼리)
    if ($keyword !== '') {
        $sql .= ' AND t.team_name LIKE ?';
        $params[] = '%' . $keyword . '%';
    }

    $orderBy = [
        'fa_payroll'  => 'ts.fa_payroll DESC',
        'wins'        => 'ts.wins DESC',
        'league_rank' => 'ts.league_rank ASC',
        'team_name'   => 't.team_name ASC',
    ];
    $sql .= ' ORDER BY ' . ($orderBy[$sort] ?? $orderBy['fa_payroll']);

    return db_all($sql, $params);
}

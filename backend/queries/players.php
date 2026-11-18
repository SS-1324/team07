<?php
// backend/queries/players.php
// 담당: 백엔드 신수연
// 선수 SELECT / UPDATE. 화면(pages)은 이 함수들이 돌려주는 배열만 사용한다.
// 사용자 입력은 전부 ? 와 $params 로 넘긴다.

require_once __DIR__ . '/common.php';

// 선수 검색 (동적 쿼리: 넘어온 조건만 WHERE 에 붙인다)
//   $filters: name(이름 일부, 한글/영문), league('MLB'|'KBO'|''), role('P'|'B'|''),
//             position(MLB 포지션|''), team(현재 소속 team_id|'')
// 돌려주는 키: player_id, player_name, name_first, name_last, name_kor, primary_pos, role,
//             bats, throws, birth_date, league_id, current_team_id, team_name, contracts, last_year
function players_search(array $filters, int $limit = 200): array
{
    $sql = 'WITH ' . SQL_CTE_PLAYER_ROLE . '
            SELECT p.player_id, ' . SQL_PLAYER_NAME . ' AS player_name,
                   p.name_first, p.name_last, p.name_kor, p.primary_pos, r.role,
                   p.bats, p.throws, p.birth_date,
                   CASE WHEN p.player_id LIKE \'k\\_%\' THEN \'KBO\' ELSE \'MLB\' END AS league_id,
                   p.current_team_id, t.team_name,
                   (SELECT COUNT(*) FROM fa_contracts c WHERE c.player_id = p.player_id)     AS contracts,
                   (SELECT MAX(ps.year_id) FROM player_seasons ps WHERE ps.player_id = p.player_id) AS last_year
              FROM players p
              JOIN player_role r ON r.player_id = p.player_id
              LEFT JOIN teams t ON t.team_id = p.current_team_id
             WHERE 1 = 1';
    $params = [];

    $name = trim($filters['name'] ?? '');
    if ($name !== '') {
        $sql .= " AND (p.name_kor LIKE ? OR CONCAT_WS(' ', p.name_first, p.name_last) LIKE ?)";
        $params[] = '%' . $name . '%';
        $params[] = '%' . $name . '%';
    }
    if (($filters['league'] ?? '') !== '') {
        // 선수의 리그 = player_id 규칙 (KBO 는 k_ 로 시작)
        $sql .= ($filters['league'] === 'KBO') ? " AND p.player_id LIKE 'k\\_%'" : " AND p.player_id NOT LIKE 'k\\_%'";
    }
    if (($filters['role'] ?? '') !== '') {
        $sql .= ' AND r.role = ?';
        $params[] = $filters['role'];
    }
    if (($filters['position'] ?? '') !== '') {
        $sql .= ' AND p.primary_pos = ?';
        $params[] = $filters['position'];
    }
    if (($filters['team'] ?? '') !== '') {
        $sql .= ' AND p.current_team_id = ?';
        $params[] = $filters['team'];
    }

    $limit = max(1, min(500, $limit));   // 정수로 고정한 뒤에만 SQL 에 넣는다
    $sql .= ' ORDER BY player_name LIMIT ' . $limit;
    return db_all($sql, $params);
}

// 선수 한 명 (없으면 null). 키는 players_search() 와 같고 birth_country, currency 가 더 있다
function player_find(string $playerId): ?array
{
    return db_one(
        'WITH ' . SQL_CTE_PLAYER_ROLE . '
         SELECT p.*, ' . SQL_PLAYER_NAME . ' AS player_name, r.role,
                CASE WHEN p.player_id LIKE \'k\\_%\' THEN \'KBO\' ELSE \'MLB\' END AS league_id,
                CASE WHEN p.player_id LIKE \'k\\_%\' THEN \'KRW\' ELSE \'USD\' END AS currency,
                t.team_name
           FROM players p
           JOIN player_role r ON r.player_id = p.player_id
           LEFT JOIN teams t ON t.team_id = p.current_team_id
          WHERE p.player_id = ?',
        [$playerId]
    );
}

// 관심 선수 목록용: 세션에 저장된 ID 들로 조회 (세션 순서대로)
// 키: player_id, player_name, role, primary_pos, league_id, team_name, contracts, total_war, last_year
function players_by_ids(array $ids): array
{
    $ids = array_values(array_filter($ids, 'is_string'));
    if (!$ids) {
        return [];
    }
    $rows = db_all(
        'WITH ' . SQL_CTE_PLAYER_ROLE . '
         SELECT p.player_id, ' . SQL_PLAYER_NAME . ' AS player_name, r.role, p.primary_pos,
                CASE WHEN p.player_id LIKE \'k\\_%\' THEN \'KBO\' ELSE \'MLB\' END AS league_id,
                t.team_name,
                (SELECT COUNT(*) FROM fa_contracts c WHERE c.player_id = p.player_id) AS contracts,
                (SELECT SUM(ps.war) FROM player_seasons ps WHERE ps.player_id = p.player_id) AS total_war,
                (SELECT MAX(ps.year_id) FROM player_seasons ps WHERE ps.player_id = p.player_id) AS last_year
           FROM players p
           JOIN player_role r ON r.player_id = p.player_id
           LEFT JOIN teams t ON t.team_id = p.current_team_id
          WHERE p.player_id IN (' . sql_placeholders(count($ids)) . ')',
        $ids
    );
    // 세션에 담은 순서를 지킨다
    $byId = array_column($rows, null, 'player_id');
    return array_values(array_filter(array_map(fn ($id) => $byId[$id] ?? null, $ids)));
}

// 선수의 시즌별 성적 (시즌 중 이적하면 stint 마다 한 행)
// 키: year_id, stint, team_id, team_name, games, war, pa, hr, rbi, sb, avg, ops, ip, p_wins, p_so, era,
//     contract_id (그 시즌이 어느 FA 계약 기간 안이면 그 계약 번호, 아니면 null)
function player_seasons(string $playerId): array
{
    return db_all(
        'SELECT ps.year_id, ps.stint, ps.team_id, t.team_name, ps.games, ps.war,
                ps.pa, ps.hr, ps.rbi, ps.sb, ps.`avg`, ps.ops, ps.ip, ps.p_wins, ps.p_so, ps.era,
                (SELECT c.contract_id FROM fa_contracts c
                  WHERE c.player_id = ps.player_id AND ps.year_id BETWEEN c.start_year AND c.end_year
                  ORDER BY c.start_year DESC LIMIT 1) AS contract_id
           FROM player_seasons ps
           JOIN teams t ON t.team_id = ps.team_id
          WHERE ps.player_id = ?
          ORDER BY ps.year_id, ps.stint',
        [$playerId]
    );
}

// 선수의 FA 계약 목록. 키는 contracts_list() 와 같다 (backend/queries/contracts.php)
function player_contracts(string $playerId): array
{
    require_once __DIR__ . '/contracts.php';
    return contracts_list(['player' => $playerId], 'start_year');
}

// 관심 선수 비교 화면용: 여러 선수의 계약 요약 + 계약 전/중 성적
// 계약 전 = 계약 시작 직전 2시즌 (DB 에 MLB 는 계약 2년 전부터만 기록이 있다)
// 키: player_id, player_name, league_id, currency, contract_id, start_year, end_year, years, aav,
//     total_value, pre_war, pre_ops, pre_era, in_seasons, in_war, in_ops, in_era
function players_compare(array $ids): array
{
    $ids = array_values(array_filter($ids, 'is_string'));
    if (!$ids) {
        return [];
    }
    return db_all(
        'WITH ' . SQL_CTE_PS_YEAR . ', ' . SQL_CTE_CONTRACT_PERF . '
         SELECT c.player_id, ' . SQL_PLAYER_NAME . ' AS player_name,
                t.league_id, l.currency,
                c.contract_id, c.start_year, c.end_year, c.years, c.aav, c.total_value,
                (SELECT SUM(y.war) FROM ps_year y
                  WHERE y.player_id = c.player_id AND y.year_id BETWEEN c.start_year - 2 AND c.start_year - 1) AS pre_war,
                (SELECT ROUND(SUM(y.ops_pa) / NULLIF(SUM(y.pa_ops), 0), 3) FROM ps_year y
                  WHERE y.player_id = c.player_id AND y.year_id BETWEEN c.start_year - 2 AND c.start_year - 1) AS pre_ops,
                (SELECT ROUND(SUM(y.era_ip) / NULLIF(SUM(y.ip), 0), 2) FROM ps_year y
                  WHERE y.player_id = c.player_id AND y.year_id BETWEEN c.start_year - 2 AND c.start_year - 1) AS pre_era,
                cp.seasons AS in_seasons,
                cp.war AS in_war, ROUND(cp.ops, 3) AS in_ops, ROUND(cp.era, 2) AS in_era
           FROM fa_contracts c
           JOIN players p        ON p.player_id = c.player_id
           JOIN teams t          ON t.team_id = c.to_team_id
           JOIN leagues l        ON l.league_id = t.league_id
           JOIN contract_perf cp ON cp.contract_id = c.contract_id
          WHERE c.player_id IN (' . sql_placeholders(count($ids)) . ')
          ORDER BY c.player_id, c.start_year',
        $ids
    );
}

// 분석 화면의 선수 select 용 (기록이 있는 선수만)
// 키: player_id, player_name, league_id
function players_options(string $leagueId = ''): array
{
    $sql = "SELECT p.player_id, " . SQL_PLAYER_NAME . " AS player_name,
                   CASE WHEN p.player_id LIKE 'k\\_%' THEN 'KBO' ELSE 'MLB' END AS league_id
              FROM players p
             WHERE EXISTS (SELECT 1 FROM player_seasons ps WHERE ps.player_id = p.player_id)";
    if ($leagueId === 'KBO') {
        $sql .= " AND p.player_id LIKE 'k\\_%'";
    } elseif ($leagueId === 'MLB') {
        $sql .= " AND p.player_id NOT LIKE 'k\\_%'";
    }
    return db_all($sql . ' ORDER BY player_name');
}

// 선수 정보 수정 입력 검사. 통과하면 [정리된 값, []], 아니면 [null, 오류 메시지 목록]
// $in 키: name_first, name_last, name_kor, birth_date(YYYY-MM-DD|''), primary_pos, bats, throws, current_team_id
function player_validate(array $in): array
{
    $errors = [];
    $data = [
        'name_first'      => trim((string) ($in['name_first'] ?? '')),
        'name_last'       => trim((string) ($in['name_last'] ?? '')),
        'name_kor'        => trim((string) ($in['name_kor'] ?? '')),
        'birth_date'      => trim((string) ($in['birth_date'] ?? '')),
        'primary_pos'     => (string) ($in['primary_pos'] ?? ''),
        'bats'            => (string) ($in['bats'] ?? ''),
        'throws'          => (string) ($in['throws'] ?? ''),
        'current_team_id' => (string) ($in['current_team_id'] ?? ''),
    ];

    if ($data['name_first'] === '' && $data['name_last'] === '' && $data['name_kor'] === '') {
        $errors[] = '이름을 하나 이상 입력하세요.';
    }
    if (mb_strlen($data['name_first']) > 40 || mb_strlen($data['name_last']) > 40) {
        $errors[] = '영문 이름은 40자 이하로 입력하세요.';
    }
    if (mb_strlen($data['name_kor']) > 20) {
        $errors[] = '한글 이름은 20자 이하로 입력하세요.';
    }
    if ($data['birth_date'] !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $data['birth_date']);
        if (!$d || $d->format('Y-m-d') !== $data['birth_date']) {
            $errors[] = '생년월일은 YYYY-MM-DD 형식으로 입력하세요.';
        }
    }
    if ($data['primary_pos'] !== '' && !array_key_exists($data['primary_pos'], position_options())) {
        $errors[] = '포지션이 올바르지 않습니다.';
    }
    foreach (['bats' => '타석', 'throws' => '투구 손'] as $key => $label) {
        if ($data[$key] !== '' && !array_key_exists($data[$key], hand_options())) {
            $errors[] = $label . ' 값이 올바르지 않습니다.';
        }
    }
    if ($data['current_team_id'] !== ''
        && !db_one('SELECT 1 AS ok FROM teams WHERE team_id = ?', [$data['current_team_id']])) {
        $errors[] = '현재 소속 구단이 올바르지 않습니다.';
    }

    if ($errors) {
        return [null, $errors];
    }
    // 빈 문자열은 NULL 로 저장
    foreach ($data as $key => $value) {
        $data[$key] = $value === '' ? null : $value;
    }
    return [$data, []];
}

// 선수 정보 UPDATE. $data 는 player_validate() 를 통과한 값. 바뀐 행 수를 돌려준다
function player_update(string $playerId, array $data): int
{
    return db_exec(
        'UPDATE players
            SET name_first = ?, name_last = ?, name_kor = ?, birth_date = ?,
                primary_pos = ?, bats = ?, throws = ?, current_team_id = ?
          WHERE player_id = ?',
        [
            $data['name_first'], $data['name_last'], $data['name_kor'], $data['birth_date'],
            $data['primary_pos'], $data['bats'], $data['throws'], $data['current_team_id'],
            $playerId,
        ]
    );
}

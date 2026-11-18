<?php
// backend/queries/contracts.php
// 담당: 백엔드 신수연
// FA 계약 SELECT / INSERT / UPDATE / DELETE 와 구단 FA 지출(team_seasons.fa_payroll) 갱신.
// 사용자 입력은 전부 ? 와 $params 로 넘긴다. INSERT/UPDATE/DELETE 는 actions/ 에서 트랜잭션으로 묶어 호출한다.

require_once __DIR__ . '/common.php';

// 계약 목록 (동적 쿼리: 넘어온 조건만 WHERE 에 붙인다)
//   $filters: league('MLB'|'KBO'|''), season(int: 그 시즌에 유효한 계약), team(영입팀 team_id),
//             role('P'|'B'), position(MLB 포지션), q(선수 이름 일부), player(player_id 정확히)
//   $sort   : 'start_year' | 'total_value' | 'aav' | 'years' | 'player_name'
// 돌려주는 키: contract_id, player_id, player_name, primary_pos, role, league_id, currency,
//             from_team_id, from_team_name, to_team_id, to_team_name, start_year, end_year, years,
//             total_value, guaranteed_value, aav, signed_date
function contracts_list(array $filters = [], string $sort = 'start_year'): array
{
    $sql = 'WITH ' . SQL_CTE_PLAYER_ROLE . '
            SELECT c.contract_id, c.player_id, ' . SQL_PLAYER_NAME . ' AS player_name,
                   p.primary_pos, r.role, tt.league_id, l.currency,
                   c.from_team_id, tf.team_name AS from_team_name,
                   c.to_team_id, tt.team_name AS to_team_name,
                   c.start_year, c.end_year, c.years, c.total_value, c.guaranteed_value, c.aav, c.signed_date
              FROM fa_contracts c
              JOIN players p      ON p.player_id = c.player_id
              JOIN player_role r  ON r.player_id = c.player_id
              JOIN teams tt       ON tt.team_id = c.to_team_id
              JOIN leagues l      ON l.league_id = tt.league_id
              LEFT JOIN teams tf  ON tf.team_id = c.from_team_id
             WHERE 1 = 1';
    $params = [];

    if (($filters['league'] ?? '') !== '') {
        $sql .= ' AND tt.league_id = ?';
        $params[] = $filters['league'];
    }
    if (isset($filters['season']) && $filters['season'] !== null && $filters['season'] !== '') {
        $sql .= ' AND ? BETWEEN c.start_year AND c.end_year';
        $params[] = (int) $filters['season'];
    }
    if (($filters['team'] ?? '') !== '') {
        $sql .= ' AND c.to_team_id = ?';
        $params[] = $filters['team'];
    }
    if (($filters['role'] ?? '') !== '') {
        $sql .= ' AND r.role = ?';
        $params[] = $filters['role'];
    }
    if (($filters['position'] ?? '') !== '') {
        $sql .= ' AND p.primary_pos = ?';
        $params[] = $filters['position'];
    }
    if (trim($filters['q'] ?? '') !== '') {
        $sql .= " AND (p.name_kor LIKE ? OR CONCAT_WS(' ', p.name_first, p.name_last) LIKE ?)";
        $params[] = '%' . trim($filters['q']) . '%';
        $params[] = '%' . trim($filters['q']) . '%';
    }
    if (($filters['player'] ?? '') !== '') {
        $sql .= ' AND c.player_id = ?';
        $params[] = $filters['player'];
    }

    // 정렬 열은 ? 로 넘길 수 없으므로 허용 목록에서만 고른다. 리그를 먼저 나눠 통화가 섞이지 않게 한다
    $orderBy = [
        'start_year'  => 'c.start_year DESC, c.aav DESC',
        'total_value' => 'c.total_value DESC',
        'aav'         => 'c.aav DESC',
        'years'       => 'c.years DESC, c.aav DESC',
        'player_name' => 'player_name ASC, c.start_year',
    ];
    $sql .= ' ORDER BY tt.league_id DESC, ' . ($orderBy[$sort] ?? $orderBy['start_year']);

    return db_all($sql, $params);
}

// 계약 한 건 (없으면 null). 키는 contracts_list() 와 같다
function contract_find(int $contractId): ?array
{
    $rows = db_all(
        'SELECT c.*, ' . SQL_PLAYER_NAME . ' AS player_name, tt.league_id, l.currency,
                tt.team_name AS to_team_name, tf.team_name AS from_team_name
           FROM fa_contracts c
           JOIN players p     ON p.player_id = c.player_id
           JOIN teams tt      ON tt.team_id = c.to_team_id
           JOIN leagues l     ON l.league_id = tt.league_id
           LEFT JOIN teams tf ON tf.team_id = c.from_team_id
          WHERE c.contract_id = ?',
        [$contractId]
    );
    return $rows[0] ?? null;
}

// 계약 입력 검사. 통과하면 [정리된 값, []], 아니면 [null, 오류 메시지 목록]
// $in 키: player_id, from_team_id(''=없음), to_team_id, start_year, end_year, total_value, guaranteed_value(''=총액과 같음)
// 금액 단위: 영입팀 리그가 MLB 면 달러, KBO 면 만원
// 정리된 값에는 서버에서 계산한 years, aav 가 들어간다 (화면에서 받지 않는다)
function contract_validate(array $in): array
{
    $errors = [];
    $data = [
        'player_id'        => trim((string) ($in['player_id'] ?? '')),
        'from_team_id'     => trim((string) ($in['from_team_id'] ?? '')),
        'to_team_id'       => trim((string) ($in['to_team_id'] ?? '')),
        'start_year'       => $in['start_year'] ?? null,
        'end_year'         => $in['end_year'] ?? null,
        'total_value'      => $in['total_value'] ?? null,
        'guaranteed_value' => $in['guaranteed_value'] ?? null,
    ];

    if ($data['player_id'] === '' || !db_one('SELECT 1 AS ok FROM players WHERE player_id = ?', [$data['player_id']])) {
        $errors[] = '선수를 선택하세요.';
    }
    if ($data['to_team_id'] === '' || !db_one('SELECT 1 AS ok FROM teams WHERE team_id = ?', [$data['to_team_id']])) {
        $errors[] = '영입 구단을 선택하세요.';
    }
    if ($data['from_team_id'] !== '' && !db_one('SELECT 1 AS ok FROM teams WHERE team_id = ?', [$data['from_team_id']])) {
        $errors[] = '원소속 구단이 올바르지 않습니다.';
    }

    $start = filter_var($data['start_year'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => 2040]]);
    $end   = filter_var($data['end_year'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => 2050]]);
    if ($start === false || $end === false) {
        $errors[] = '계약 연도를 올바르게 입력하세요.';
    } elseif ($end < $start) {
        $errors[] = '종료 연도는 시작 연도보다 같거나 늦어야 합니다.';
    } elseif ($end - $start + 1 > 15) {
        $errors[] = '계약 기간은 15년 이하로 입력하세요.';
    }

    $total = filter_var($data['total_value'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($total === false) {
        $errors[] = '계약 총액은 1 이상의 정수로 입력하세요.';
    }
    $guaranteed = ($data['guaranteed_value'] === null || $data['guaranteed_value'] === '')
        ? $total
        : filter_var($data['guaranteed_value'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    if ($guaranteed === false) {
        $errors[] = '보장액은 0 이상의 정수로 입력하세요.';
    } elseif ($total !== false && $guaranteed > $total) {
        $errors[] = '보장액은 총액보다 클 수 없습니다.';
    }

    if ($errors) {
        return [null, $errors];
    }

    $years = $end - $start + 1;
    return [[
        'player_id'        => $data['player_id'],
        'from_team_id'     => $data['from_team_id'] === '' ? null : $data['from_team_id'],
        'to_team_id'       => $data['to_team_id'],
        'start_year'       => $start,
        'end_year'         => $end,
        'years'            => $years,
        'total_value'      => $total,
        'guaranteed_value' => $guaranteed,
        'aav'              => (int) round($total / $years),
    ], []];
}

// INSERT. 새 contract_id 를 돌려준다. (actions/contract_add.php 의 트랜잭션 안에서 호출)
function contract_insert(array $data): int
{
    db_exec(
        'INSERT INTO fa_contracts
            (player_id, from_team_id, to_team_id, start_year, end_year, years, total_value, guaranteed_value, aav)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $data['player_id'], $data['from_team_id'], $data['to_team_id'], $data['start_year'],
            $data['end_year'], $data['years'], $data['total_value'], $data['guaranteed_value'], $data['aav'],
        ]
    );
    return (int) db()->lastInsertId();
}

// UPDATE. 바뀐 행 수를 돌려준다
function contract_update(int $contractId, array $data): int
{
    return db_exec(
        'UPDATE fa_contracts
            SET player_id = ?, from_team_id = ?, to_team_id = ?, start_year = ?, end_year = ?,
                years = ?, total_value = ?, guaranteed_value = ?, aav = ?
          WHERE contract_id = ?',
        [
            $data['player_id'], $data['from_team_id'], $data['to_team_id'], $data['start_year'],
            $data['end_year'], $data['years'], $data['total_value'], $data['guaranteed_value'], $data['aav'],
            $contractId,
        ]
    );
}

// DELETE. 지운 행 수를 돌려준다
function contract_delete(int $contractId): int
{
    return db_exec('DELETE FROM fa_contracts WHERE contract_id = ?', [$contractId]);
}

// 구단 FA 지출 갱신: 계약 기간에 해당하는 영입팀 시즌들의 fa_payroll 에 $delta 를 더한다.
// 계약 추가는 +aav, 삭제는 -aav, 수정은 (옛 계약 -aav) 후 (새 계약 +aav).
// DB 에 없는 시즌(예: 2026년 이후)은 건너뛴다. 갱신된 시즌 수를 돌려준다.
function payroll_apply(string $teamId, int $startYear, int $endYear, int $delta): int
{
    return db_exec(
        'UPDATE team_seasons
            SET fa_payroll = GREATEST(fa_payroll + ?, 0)
          WHERE team_id = ? AND year_id BETWEEN ? AND ?',
        [$delta, $teamId, $startYear, $endYear]
    );
}

// 선수의 현재 소속을 영입팀으로 바꾼다 (계약 등록 트랜잭션의 일부)
function player_set_team(string $playerId, string $teamId): int
{
    return db_exec('UPDATE players SET current_team_id = ? WHERE player_id = ?', [$teamId, $playerId]);
}

// 계약 등록·수정 화면의 선수 select 용 (전체 선수)
// 키: player_id, player_name, league_id, current_team_id
function contract_player_options(): array
{
    return db_all(
        "SELECT p.player_id, " . SQL_PLAYER_NAME . " AS player_name,
                CASE WHEN p.player_id LIKE 'k\\_%' THEN 'KBO' ELSE 'MLB' END AS league_id,
                p.current_team_id
           FROM players p
          ORDER BY league_id DESC, player_name"
    );
}

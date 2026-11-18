<?php
// pages/teams/list.php
// 담당: 프론트 (쿼리: 백엔드 backend/queries/teams.php)
// 구단 종합 비교 — 다른 화면을 만들 때 따라 하는 "기준 예시" 화면이다.
// 순서: ① 입력 읽기 → ② 백엔드 함수 호출 → ③ HTML 출력

require_once __DIR__ . '/../../backend/bootstrap.php';
require_once __DIR__ . '/../../backend/queries/teams.php';

// ① 입력 읽기
$leagueId = input_str('league', 'MLB');
$year     = input_int('year', 2024);
$keyword  = input_str('q');
$sort     = input_choice('sort', ['fa_payroll', 'wins', 'league_rank', 'team_name'], 'fa_payroll');

// ② 백엔드 함수 호출 (DB 오류가 나도 화면 틀은 보여준다)
$error = null;
$leagues = $seasons = $rows = [];
try {
    $leagues = leagues_all();
    $seasons = seasons_all();
    $rows    = team_seasons_list($leagueId, $year, $keyword, $sort);
} catch (PDOException $e) {
    $error = DEBUG ? $e->getMessage() : '데이터를 불러오지 못했습니다.';
}

// ③ HTML 출력
$pageTitle  = '구단 종합 비교';
$activeMenu = 'teams';
require __DIR__ . '/../../layout/header.php';
?>

<form class="panel filter" method="get">
    <label>리그
        <select name="league">
            <?php foreach ($leagues as $league): ?>
                <option value="<?= h($league['league_id']) ?>" <?= $league['league_id'] === $leagueId ? 'selected' : '' ?>>
                    <?= h($league['league_id']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>시즌
        <select name="year">
            <?php foreach ($seasons as $season): ?>
                <option value="<?= h($season) ?>" <?= (int) $season === $year ? 'selected' : '' ?>><?= h($season) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>정렬
        <select name="sort">
            <?php foreach (['fa_payroll' => 'FA 지출 많은 순', 'wins' => '승수 많은 순', 'league_rank' => '순위 순', 'team_name' => '구단 이름 순'] as $value => $label): ?>
                <option value="<?= h($value) ?>" <?= $value === $sort ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>구단 검색
        <input type="search" name="q" value="<?= h($keyword) ?>" placeholder="구단 이름">
    </label>
    <button class="primary" type="submit">조회</button>
</form>

<section class="panel">
    <?php if ($error): ?>
        <p class="empty"><?= h($error) ?></p>
    <?php elseif (!$rows): ?>
        <p class="empty">조건에 맞는 구단이 없습니다.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>구단</th><th>소속</th><th class="num">승</th><th class="num">패</th>
                    <th class="num">승률</th><th class="num">순위</th><th>포스트시즌</th><th class="num">FA 지출 (AAV 합계)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= h($row['team_name']) ?></td>
                        <td><?= h($row['sub_league']) ?></td>
                        <td class="num"><?= h($row['wins']) ?></td>
                        <td class="num"><?= h($row['losses']) ?></td>
                        <td class="num"><?= h($row['win_pct']) ?></td>
                        <td class="num"><?= h($row['league_rank']) ?></td>
                        <td><?= h($row['postseason']) ?></td>
                        <td class="num"><?= h(format_money($row['fa_payroll'], $row['currency'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../../layout/footer.php'; ?>

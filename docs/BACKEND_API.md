# 백엔드 함수 안내 (프론트용)

담당: 백엔드 신수연

화면(`pages/`)에서 부르는 함수 목록입니다. 함수는 **배열만 돌려주고 HTML 은 만들지 않습니다.**
화면은 `pages/teams/list.php` 처럼 ① 입력 읽기 → ② 함수 호출 → ③ HTML 출력 순서로 만듭니다.

## 먼저 알아둘 것

| 내용 | 화면에서 할 일 |
|---|---|
| 금액 단위가 리그마다 다름 (MLB 달러, KBO 만원) | 금액은 항상 `format_money($값, $row['currency'])` 로 출력. 돌려주는 행에 `currency` 가 들어 있음 |
| KBO 는 WAR 이 없음 (NULL) | WAR 칸은 `$row['war'] ?? '—'`. KBO 를 보여줄 땐 지표 select 에서 OPS/ERA 를 고르게 안내 |
| KBO 선수는 포지션·생년월일·투타 정보가 없음 | 포지션 필터는 "MLB 만" 이라고 표시. 투수/타자 구분은 `role` (`P`/`B`) 사용 — KBO 도 값이 있음 |
| 포스트시즌 코드가 리그마다 다름 | `postseason_label($row['postseason'])` 로 출력 |
| POST 폼 (등록·수정·삭제·관심 선수) | 폼 안에 `<?= csrf_field() ?>` 한 줄 필수. 없으면 "요청이 만료되었습니다" 로 돌아옴 |
| select·체크박스 목록 | `role_options()`, `position_options()`, `hand_options()`, `metric_options()`, `aggregate_group_options()` (값 => 표시 이름) |
| 분석 조건 기억 (세션) | `$f = filters_recall('ranking', [기본값])` 로 꺼내고, 조회 후 `filters_remember('ranking', $f)` 로 저장 |

`require_once` 할 파일: `backend/queries/teams.php`, `players.php`, `contracts.php`, `analysis.php` (필요한 것만. `common.php` 는 자동으로 같이 들어옴)

## 구단 — `backend/queries/teams.php`

| 함수 | 돌려주는 키 |
|---|---|
| `leagues_all()` | league_id, league_name, currency |
| `seasons_all()` | 연도 숫자 배열 |
| `teams_all($leagueId = '')` | team_id, team_name, league_id, sub_league |
| `team_find($teamId)` | team_id, team_name, league_id, sub_league, city, currency |
| `team_seasons_list($leagueId, $year, $keyword, $sort)` | (기존 예시 화면에서 사용 중) |

## 선수 — `backend/queries/players.php`

| 함수 | 쓰는 화면 | 돌려주는 키 |
|---|---|---|
| `players_search($filters, $limit = 200)` | 선수 검색 | player_id, player_name, name_first, name_last, name_kor, primary_pos, role, bats, throws, birth_date, league_id, current_team_id, team_name, contracts, last_year |
| `player_find($playerId)` | 선수 수정 | players 테이블 전부 + player_name, role, league_id, currency, team_name (없으면 null) |
| `players_by_ids(watchlist_ids())` | 관심 선수 | player_id, player_name, role, primary_pos, league_id, team_name, contracts, total_war, last_year |
| `players_compare($ids)` | 관심 선수 비교 | 계약 한 건당 한 행: player_id, player_name, league_id, currency, contract_id, start_year, end_year, years, aav, total_value, pre_war, pre_ops, pre_era (계약 직전 2시즌), in_seasons, in_war, in_ops, in_era (계약 기간) |
| `player_seasons($playerId)` | 선수 상세 | year_id, stint, team_id, team_name, games, war, pa, hr, rbi, sb, avg, ops, ip, p_wins, p_so, era, contract_id |
| `player_contracts($playerId)` | 선수 상세 | `contracts_list()` 와 같음 |
| `players_options($leagueId = '')` | 시즌 추이(윈도우) 선수 select | player_id, player_name, league_id |

`players_search` 의 `$filters`: `name`, `league` (`MLB`/`KBO`/''), `role` (`P`/`B`/''), `position`, `team` (현재 소속). 빈 값은 조건에서 빠짐.

선수 수정 폼 → `actions/player_edit.php` (POST): `player_id`, `name_first`, `name_last`, `name_kor`, `birth_date` (YYYY-MM-DD), `primary_pos`, `bats`, `throws`, `current_team_id`

## FA 계약 — `backend/queries/contracts.php`

| 함수 | 쓰는 화면 | 돌려주는 키 |
|---|---|---|
| `contracts_list($filters, $sort)` | 계약 목록 | contract_id, player_id, player_name, primary_pos, role, league_id, currency, from_team_id, from_team_name, to_team_id, to_team_name, start_year, end_year, years, total_value, guaranteed_value, aav, signed_date |
| `contract_find($contractId)` | 계약 수정 | fa_contracts 전부 + player_name, league_id, currency, to_team_name, from_team_name (없으면 null) |
| `contract_player_options()` | 계약 등록·수정 선수 select | player_id, player_name, league_id, current_team_id |

`$filters`: `league`, `season` (그 시즌에 유효한 계약), `team` (영입팀), `role`, `position`, `q` (이름 검색), `player`.
`$sort`: `start_year` (기본) / `total_value` / `aav` / `years` / `player_name`. 화면에서 `input_choice()` 로 받아서 넘기기.

| 폼 | 보낼 곳 (POST) | 필드 |
|---|---|---|
| 계약 등록 | `actions/contract_add.php` | `player_id`, `from_team_id` (비워도 됨), `to_team_id`, `start_year`, `end_year`, `total_value`, `guaranteed_value` (비우면 총액) |
| 계약 수정 | `actions/contract_edit.php` | 위와 같음 + `contract_id` |
| 계약 삭제 | `actions/contract_delete.php` | `contract_id` |

- 계약 연수·AAV 는 **입력받지 않음** (서버 계산).
- 금액 입력 단위: 영입팀이 MLB 면 달러, KBO 면 만원. 입력칸 옆에 단위를 표시해 주세요.
- 등록·수정·삭제는 모두 **트랜잭션**: 계약 + 구단 FA 지출(`team_seasons.fa_payroll`) (+ 등록은 선수 현재 소속)을 한 번에 바꿈.
- 등록 실패 시 입력값이 세션에 남음: `filters_recall('contract_add')` 로 폼을 다시 채울 수 있음.

## 고급 분석 — `backend/queries/analysis.php`

| 메뉴 | 함수 | 입력 (화면 컨트롤 예시) |
|---|---|---|
| 1. 그룹별 집계 | `analysis_aggregate($league, $yearFrom, $yearTo, $groupBy, $role)` | 리그 select, 연도 범위 select 2개, 묶을 기준 **체크박스** (`input_choices('group', array_keys(aggregate_group_options()))`, 1~3개), 투수/타자 라디오 |
| 2. 롤업/드릴다운 | `analysis_rollup($league, $yearFrom, $yearTo, $level, $teamId)` | 리그, 연도 범위, 단계 **라디오** (`league`/`team`/`year`). 구단 행 클릭 → `$teamId` 넣고 `year` 단계로 |
|  | `analysis_drill_contracts($teamId, $year)` | 연도 행 클릭 → 그 구단·시즌의 계약과 선수 성적 |
| 3. 효율 순위 | `analysis_ranking($f)` / `analysis_ranking_quartiles($f)` | 리그, 연도 범위, 지표 select, 순위 범위 라디오 (`year` 연도별 / `all` 전체), 투수/타자, 포지션, 최소 총액 입력, 상위 N 숫자 입력 |
| 4. 시즌 추이 | `analysis_window($playerId, $yearFrom, $yearTo, $metric, $span)` | 선수 select, 연도 범위, 지표 select, 이동평균 2/3년 라디오 |

연도 select 범위는 `contract_year_range()` (min_year, max_year) 또는 `seasons_all()`.

### 돌려주는 키

**analysis_aggregate**: league_id, currency, *고른 기준 키들* (예: `role`, `years_band`), contracts, avg_aav, max_aav, min_aav, sum_total, avg_years, seasons, war, war_per_season, ops, era, cost_per_war
- `cost_per_war` = 계약 기간 지급액 ÷ WAR 합 (WAR 없거나 0 이하면 null → KBO 는 항상 null)

**analysis_rollup**: row_level (`league`/`team`/`year`), league_id, currency, team_id, team_name, year_id, seasons, fa_payroll, wins, losses, win_pct, postseason_cnt, payroll_per_win, contracts_signed
- `row_level` 로 소계 행을 구분해 굵게 표시. 리그 소계는 team_id·team_name 이 null, 구단 소계는 year_id 가 null.

**analysis_drill_contracts**: contract_id, player_id, player_name, start_year, end_year, years, aav, total_value, currency, contract_season (몇 년차), games, war, pa, ops, ip, era

**analysis_ranking** (`$f` 키: `league`, `year_from`, `year_to`, `metric` (`war`/`ops`/`era`), `scope`, `role`, `position`, `min_total`, `top`):
contract_id, player_id, player_name, league_id, currency, to_team_name, start_year, end_year, years, total_value, aav, role, primary_pos, seasons, paid, war, ops, era, metric_value, metric_rank, aav_rank, aav_quartile, metric_pct, group_size
- `metric_value`: war 이면 WAR 1당 비용(낮을수록 좋음), ops 는 OPS, era 는 ERA
- `aav_quartile`: 리그 안 몸값 4분위 (1 = 가장 비싼 25%)
- `metric_rank` 와 `aav_rank` 차이가 크면 "몸값 대비 잘한/못한 계약"

**analysis_ranking_quartiles**: league_id, currency, aav_quartile, contracts, min_aav, max_aav, avg_aav, avg_metric, avg_rank_pct

**analysis_window**: year_id, age, team_ids, games, pa, ip, war, ops, era, metric_value, prev_year, prev_value, change, cumulative, moving_avg, league_avg, league_rank, league_count, contract_id, contract_season
- `cumulative` 는 WAR 일 때만 의미 있음
- `league_avg`·`league_rank` 는 같은 해 같은 리그 **FA 계약 선수들** 기준 (DB 에 그 선수들만 있음)
- `contract_season` 이 있으면 계약 기간 중인 시즌 → 색으로 구분 추천

## 데이터 주의 (분석 결과 해석)

- MLB 시즌 기록은 계약 시작 **2년 전부터만** DB 에 있음. "계약 전" = 최대 2시즌.
- 보장액(`guaranteed_value`)은 원자료에 없어서 전부 총액과 같음 → 화면에 굳이 보여줄 필요 없음.
- 옵트아웃으로 계약 기간이 겹치는 선수 4명(코레아, 세스페데스, 워커, 카스테야노스)은 겹친 해 FA 지출이 두 번 더해져 있음.
- 오타니 AAV 7,000만 달러는 지급 유예가 반영 안 된 명목 금액.
- 애슬레틱스는 `OAK`(~2024)와 `ATH`(2025) 두 구단으로 나뉘어 있음.

# 데이터 생성 입력 자료

프로젝트 루트에서 `python3 generate_dbinsert.py`를 실행하면 `dbinsert.sql`을 생성합니다. 완전한 MLB + KBO 데이터 생성에는 아래 입력 파일이 모두 필요합니다. 일부 입력으로 시험하려면 `python3 generate_dbinsert.py --allow-incomplete`를 사용하세요. 이 옵션은 최종 제출용이 아닌 부분 SQL을 생성합니다.

## WAR

`data/war/`에 다음 탭 구분 파일을 둡니다.

- `war_daily_bat.txt`
- `war_daily_pitch.txt`

두 파일 모두 `player_ID`, `year_ID`, `stint_ID`, `WAR` 열이 있어야 합니다. `player_ID`는 Lahman `People.csv`의 `bbrefID`와 일치해야 합니다. 타격 WAR와 투구 WAR를 선수·연도·stint별로 합산합니다.

## KBO

아래 CSV는 `data/manual/`에 둡니다. KBO ID는 ERD 규칙에 맞춰 `K_LG` 형식의 구단 ID와 `k_` 접두 선수 ID를 사용하고, 시즌 범위는 ERD의 2014–2025년과 맞춥니다.

- `kbo_teams.csv`: `team_id,team_name,city`
- `kbo_team_seasons.csv`: `team_id,year_id,wins,losses,win_pct,league_rank,postseason,runs_scored,runs_allowed`
- `kbo_players.csv`: `player_id,name_first,name_last,name_kor,birth_date,birth_country,bats,throws,primary_pos,current_team_id`
- `kbo_player_seasons.csv`: `player_id,year_id,stint,team_id,games,war,pa,hr,rbi,sb,avg,ops,ip,p_wins,p_so,era`

`kbo_team_seasons.csv`의 `fa_payroll`은 스크립트가 계약 데이터로 계산하므로 입력하지 않습니다. `kbo_player_seasons.csv`는 선수 유형상 해당 없는 기록을 빈칸으로 둘 수 있습니다. WAR는 별도 출처에서 수집해 `war` 열에 넣고 출처를 기록하세요.

`kbo_player_seasons.csv`는 KBO 공식 기록의 2014–2025 정규시즌 순위표에서 수집합니다. [타자 기본기록](https://www.koreabaseball.com/Record/Player/HitterBasic/Basic1.aspx)과 [타자 세부기록](https://www.koreabaseball.com/Record/Player/HitterBasic/Basic2.aspx)은 시즌·선수명·팀으로 결합하고, OPS는 세부기록 표의 공식 값을 사용합니다. 도루는 [주루 기록](https://www.koreabaseball.com/Record/Player/Runner/Basic.aspx), 투수 기록은 [투수 기본기록](https://www.koreabaseball.com/Record/Player/PitcherBasic/Basic1.aspx)에서 가져옵니다. 각 표의 페이지를 모두 확인하며, 공식 표에 표시되지 않은 선수·시즌은 행으로 만들지 않습니다. 타격 기록은 있지만 주루 표에 같은 시즌·선수·팀 행이 없는 경우 `sb`는 0으로 둡니다. 따라서 이 파일은 KBO의 전체 출전 선수 명부가 아니라 해당 기록 순위표에 올라온 선수만 포함합니다. 표에 해당 기록이 없는 선수 유형별 열과 WAR는 빈칸으로 두며, 투구 이닝의 `1/3`, `2/3`은 각각 `.3`, `.7`로 저장합니다.

`k_gimsangsu2`는 2014–2025 기록에서 현재 이름 `김태혁`으로 표시되는 투수입니다. 공식 KBO 선수 ID `76430`으로 확인해 이전 이름 `김상수`의 프로젝트 ID에 연결합니다. 2014년 공식 기록표에 해당 기록이 없어 행을 만들지 않으며, 2015–2025년 투수 기록을 포함합니다.

## 계약

`data/manual/fa_contracts.csv`에는 MLB와 KBO 계약을 함께 둡니다. 필수 열은 `player_id,from_team_id,to_team_id,start_year,end_year,total_value`이며, `guaranteed_value,signed_date`는 선택 열입니다. 선수 ID와 구단 ID는 마스터 CSV의 ID와 정확히 일치해야 합니다. MLB 금액은 USD, KBO 금액은 만원 단위로 입력합니다.

MLB Spotrac 자료는 연도별 Free Agents 페이지를 참고합니다. 2016–2024년은 계약 금액 내림차순 상위 15건을 기준으로 정리합니다. 2024년 목록도 다른 연도와 같은 기준으로 맞춰 총 15건으로 구성했습니다. 일본·한국 리그에서 MLB로 온 선수처럼 원소속팀이 Lahman MLB 구단이 아닌 경우 `from_team_id`는 비워 둡니다.

- [2016](https://www.spotrac.com/mlb/free-agents/_/year/2016), [2017](https://www.spotrac.com/mlb/free-agents/_/year/2017), [2018](https://www.spotrac.com/mlb/free-agents/_/year/2018), [2019](https://www.spotrac.com/mlb/free-agents/_/year/2019)
- [2020](https://www.spotrac.com/mlb/free-agents/_/year/2020), [2021](https://www.spotrac.com/mlb/free-agents/_/year/2021), [2022](https://www.spotrac.com/mlb/free-agents/_/year/2022), [2023](https://www.spotrac.com/mlb/free-agents/_/year/2023), [2024](https://www.spotrac.com/mlb/free-agents/_/year/2024)

`data/manual/kbo_birth_dates.csv`에는 KBO 공식 선수 조회를 바탕으로 정리한 선수 ID·이름·생년월일과 동명이인 확인 메모가 있습니다. 이 자료를 `player_id`와 한국어 이름으로 대조해 `data/manual/kbo_players.csv` 132명에게 반영합니다. 생년월일 변경 후에는 `dbinsert.sql`을 다시 생성하고, DB 적재·검증을 마친 뒤 `dbdump.sql`을 다시 내보냅니다.

## 기존 Lahman 파일

`data/lahman/`에는 `People.csv`, `Teams.csv`, `Batting.csv`, `Pitching.csv`, `Appearances.csv`가 있어야 합니다. 이 파일들은 현재 작업 폴더에 포함되어 있습니다.

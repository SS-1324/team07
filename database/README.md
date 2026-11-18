# database

담당: DB

| 파일 | 내용 |
|---|---|
| `dbcreate.sql` | 테이블, PK, FK, 인덱스 생성 |
| `dbinsert.sql` | 초기 데이터 INSERT |
| `dbdrop.sql` | 전체 테이블 삭제 |
| `dbdump.sql` | DB 덤프 (제출용) |
| `tools/` | `dbinsert.sql` 을 만드는 스크립트(generate_dbinsert.py)와 원본 데이터 |

실행 순서: `dbdrop.sql` → `dbcreate.sql` → `dbinsert.sql`

## 지금 들어 있는 데이터

leagues 2, teams 31, players 126, team_seasons 360, player_seasons 761, fa_contracts 135 — 합계 1,415건.

## 확인할 것

- [ ] KBO 데이터가 없다. `leagues` 에 KBO 한 행만 있고 구단·선수·계약이 없어서 리그별 비교가 MLB 하나만 나온다. `docs/ERD.md` 의 건수(약 2,400건, 구단 40개)와도 다르다.
- [ ] `dbdump.sql` 을 다시 뜬다. 지금 파일은 `fa_contracts` 정의에서 `ENGINE/CHARSET` 과 `CHECK (end_year >= start_year)` 가 빠져 있다.
- [ ] `generate_dbinsert.py` 를 `tools/` 에 넣는다.
- [ ] `fa_contracts.signed_date` 가 거의 전부 NULL 이다. 쓰지 않을 거면 화면에서도 뺀다.

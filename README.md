# team07 — FA Contract Value

FA 계약 효율성 분석 웹사이트. Apache + PHP + MariaDB (XAMPP).

## 설치

1. 이 폴더를 통째로 `xampp/htdocs/team07` 에 둔다.
2. XAMPP에서 Apache와 MySQL을 켠다.
3. DB와 계정을 만든다 (처음 한 번, root로 접속해서).

   ```sql
   CREATE DATABASE team07 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'team07'@'localhost' IDENTIFIED BY 'team07';
   GRANT ALL PRIVILEGES ON team07.* TO 'team07'@'localhost';
   ```

4. `database` 폴더에서 스크립트를 순서대로 실행한다.

   ```sql
   source dbdrop.sql
   source dbcreate.sql
   source dbinsert.sql
   ```

5. 브라우저에서 `http://localhost/team07/` 을 연다.

DB 계정이나 폴더 이름이 다르면 `config/config.php` 만 고친다.

## 폴더 구조와 담당

```
team07/
├── index.php            첫 화면 (구단 종합 비교로 이동)
├── config/              [백엔드] DB 계정, 기본 주소
├── pages/               [프론트] 브라우저로 여는 화면
│   ├── teams/             구단 종합 비교
│   ├── contracts/         FA 계약 목록, 등록, 수정
│   ├── players/           선수 검색, 수정, 관심 선수, 비교
│   └── analysis/          고급 분석 4종 (집계, 롤업, 순위, 윈도우)
├── layout/              [프론트] 공통 상단·메뉴(header), 하단(footer)
├── assets/              [프론트] css, js, img
├── backend/             [백엔드] 화면에서 불러 쓰는 PHP
│   ├── bootstrap.php      모든 파일이 맨 위에서 require 하는 파일
│   ├── db.php             DB 연결, PreparedStatement 실행 함수
│   ├── session.php        세션 (관심 선수, 알림 메시지)
│   ├── helpers.php        출력·입력·주소·금액 함수
│   └── queries/           SQL을 담은 함수 (teams, contracts, players, analysis)
├── actions/             [백엔드] 폼 제출을 받아 처리하고 화면으로 돌려보내는 파일
├── database/            [DB] dbcreate / dbinsert / dbdrop / dbdump, 데이터 생성 도구
└── docs/                ERD, 화면 예시(prototype), 보고서·논문·발표 자료
```

| 담당 | 고치는 폴더 |
|---|---|
| 프론트 | `pages/`, `layout/`, `assets/` |
| 백엔드 | `backend/`, `actions/`, `config/` |
| DB | `database/`, `docs/ERD.md` |

## 일하는 방식

화면 하나는 세 조각으로 나뉜다.

1. **프론트**가 `pages/` 에 화면을 만든다. 입력을 읽고, 백엔드 함수를 부르고, 받은 배열을 HTML로 출력한다. 화면 파일에는 SQL을 쓰지 않는다.
2. **백엔드**가 `backend/queries/` 에 함수를 만든다. 입력값을 받아 SQL을 실행하고 배열을 돌려준다. HTML은 쓰지 않는다.
3. 저장·수정·삭제는 화면의 폼이 `actions/` 파일로 POST 하고, 처리 후 화면으로 redirect 한다.

프론트와 백엔드는 **함수 이름, 받는 값, 돌려주는 배열의 키**만 먼저 맞추면 따로 작업할 수 있다.

따라 할 예시가 두 개 들어 있다.

- 화면 + 쿼리: `pages/teams/list.php` 와 `backend/queries/teams.php`
- 처리 파일: `actions/watchlist_toggle.php`

나머지 파일은 빈 틀이고, 파일 맨 위 주석에 과제 요건과 할 일이 적혀 있다.

## 과제 요건이 들어갈 자리

| 요건 | 파일 |
|---|---|
| SELECT | `pages/teams/list.php`, `pages/contracts/list.php`, `pages/players/search.php` |
| INSERT | `pages/contracts/add.php` → `actions/contract_add.php` |
| UPDATE | `pages/contracts/edit.php`, `pages/players/edit.php` → `actions/*_edit.php` |
| DELETE | `pages/contracts/list.php` → `actions/contract_delete.php` |
| 분석 1 복합 그룹화 집계 | `pages/analysis/aggregate.php` |
| 분석 2 롤업 / 드릴다운 | `pages/analysis/rollup.php` |
| 분석 3 순위 | `pages/analysis/ranking.php` |
| 분석 4 윈도우 함수 | `pages/analysis/window.php` |
| PreparedStatement | `backend/db.php` 의 `db_all`, `db_one`, `db_exec` |
| 세션 | `backend/session.php`, `actions/watchlist_toggle.php`, `pages/players/watchlist.php` |
| 트랜잭션 | `actions/contract_add.php`, `actions/contract_edit.php`, `actions/contract_delete.php` |
| 설정 변수 | `config/config.php` |

## 규칙

- 사용자 입력을 SQL 문자열에 직접 이어 붙이지 않는다. `?` 와 `$params` 로 넘긴다. 정렬 열처럼 `?` 로 못 넘기는 값은 `input_choice()` 로 허용 목록을 통과시킨다.
- 화면에 출력하는 값은 전부 `h()` 로 감싼다.
- POST 폼에는 `<?= csrf_field() ?>` 를 넣는다. 처리 파일은 첫 줄에서 `require_post()` 로 검사한다.
- 백엔드 함수 이름·인자·돌려주는 키는 `docs/BACKEND_API.md` 에 정리되어 있다.
- 주소는 `url('pages/...')` 로 만든다. `/team07/...` 을 직접 쓰지 않는다.
- 계산·정렬·집계는 JavaScript가 아니라 SQL에서 한다.
- 각 PHP 파일 맨 위 `담당:` 주석에 본인 이름을 적는다 (과제 평가 항목).
- 테이블 구조를 바꾸면 `dbcreate.sql`, `dbinsert.sql`, `dbdump.sql`, `docs/ERD.md` 를 함께 고친다.

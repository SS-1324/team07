# DIAMONDATA 프론트엔드와 PHP 백엔드 연결

Modern v2 디자인을 실제 PHP 화면에 적용했습니다. `docs/frontend-preview`는 디자인 검토용 정적 미리보기이며, 이번 구현은 `pages/`, `layout/`, `assets/`에서 기존 MariaDB 조회 함수와 POST 처리 파일을 호출하는 실제 앱입니다.

## 실행하기 — 팀원용

1. 이 변경이 적용된 `frontend/modern-v2` 브랜치를 내려받습니다.
2. 프로젝트 루트(`index.php`, `backend`, `pages`가 있는 폴더)를 `xampp/htdocs/team07`에 둡니다. 기존 프로젝트가 있다면 먼저 백업하고 변경 파일을 덮어씁니다.
3. XAMPP에서 Apache와 MySQL을 켭니다. PHP의 `pdo_mysql`, `mbstring` 확장이 필요합니다.
4. 기존 팀 DB가 있으면 그대로 사용합니다. **기존 데이터가 있는 DB에 `dbdrop.sql`을 실행하지 마세요.** 새 테스트 DB는 루트 README의 설치 절차로 생성하고 `database/dbcreate.sql`, `database/dbinsert.sql`을 적용합니다.
5. `config/config.php`의 DB 연결 설정을 본인 환경에 맞춥니다. 기본 프로젝트 경로는 `/team07`입니다.
6. 브라우저에서 **http://localhost/team07/** 를 엽니다. PHP 파일을 더블클릭하거나 GitHub Pages로 열면 실행되지 않습니다.

`docs`는 기존 `.htaccess`로 웹 접근이 차단된 문서 폴더입니다. 실제 앱은 `docs` 아래 파일에 의존하지 않습니다. 디자인 CSS와 경기장 이미지를 공개 assets 경로로 복사했습니다.

## 무엇이 연결되었나

| 화면 | 실제 연결 |
| --- | --- |
| 구단 종합 비교 | 리그·시즌·구단 검색·정렬, 구단 클릭 시 계약 목록 |
| 선수 검색·상세 | 역할·포지션·현재 구단 필터, 선수별 계약·시즌 기록·성적 차트 |
| 관심 선수 | 서버 세션에 최대 10명 저장, 2~4명 계약 전·중 성적 비교 |
| 계약 관리 | 기존 등록·수정·삭제 actions 호출, CSRF 토큰, 삭제 확인, 기간·보장액 입력 검사 |
| 그룹별 집계 | 최대 세 기준을 선택하는 기존 GROUP BY 쿼리 |
| 구단·시즌별 집계 | 리그→구단→시즌→유효 계약 드릴다운, 소계 구분 |
| FA 계약 효율 순위 | WAR당 비용 / OPS / ERA, 연도별·전체 순위, 몸값 4분위 요약 |
| 시즌 성적 추이 | 이전 기록 대비 변화, 이동평균, 리그 FA 선수 평균, WAR 누적 |
| 공통 | 모바일 메뉴, 표 20행 단위 페이지 이동, 전체 조회 결과 CSV 다운로드, 빈 결과 안내 |

읽기는 기존 `backend/queries/*.php` 함수를 사용합니다. 쓰기는 기존 `actions/*.php`로 POST합니다. 프론트엔드는 SQL이나 트랜잭션을 재구현하지 않습니다. `backend/`, `actions/`, `config/`, `database/`는 변경하지 않았습니다.

## 데이터 해석

- KBO 금액은 **만원**, MLB는 **달러**로 저장됩니다. 화면은 기존 `format_money()`로 억원 또는 $M으로 표시합니다. CSV는 화면의 표시 단위이며 계산용 원본 숫자 덤프가 아닙니다.
- KBO WAR·생년월일·세부 포지션의 누락은 0이 아니라 `—`로 표시합니다. KBO 순위는 OPS·ERA를 이용하세요.
- 구단 FA 지출은 계약 AAV 합계입니다. 구단 전체 선수 연봉과 다릅니다.
- WAR당 비용은 기록이 있는 계약 시즌의 추정 지급액 ÷ 양수 WAR입니다. OPS 순위는 200타석, ERA는 50이닝 이상입니다.
- 집계·순위의 연도 조건은 계약 시작 연도입니다. 롤업·추이는 기록 시즌 연도입니다.
- 이동평균과 이전 기록 비교는 조회 시작 연도 이전의 기록도 사용합니다. 빠진 연도가 있으면 직전 기록은 바로 전 해가 아닐 수 있습니다. 차트는 누락된 해를 연결하지 않습니다.
- 계약 등록 오류는 기존 백엔드가 입력값을 복원합니다. 수정 오류는 기존 저장값으로 돌아갑니다. 이 동작은 기존 actions의 정책입니다.

## 변경 파일과 역할

- `layout/header.php`, `footer.php`: 브랜드, 메뉴, 경기장 이미지, 공통 화면.
- `layout/ui.php`: 필터·표·차트 출력, 이스케이프, 기존 쿼리 모음 로딩.
- `layout/contract-form.php`: 등록·수정 공통 폼.
- `pages/`: 각 화면의 입력 → 백엔드 함수 호출 → 결과 출력.
- `assets/css/frontend-base.css`, `frontend-modern.css`, `live.css`: 기존 Modern v2 테마 및 실제 PHP 화면 보완.
- `assets/js/live.js`: 페이지 이동, CSV, 비교 선택, 입력 검사, SVG 차트. 분석값은 SQL 결과 그대로 사용.
- `assets/img/stadium.webp`: 이전 Modern v2의 AI 생성 가상 야구장 이미지.
- `tests/`: 실행 환경에서 재검증할 수 있는 읽기·화면 검사.

## 검증 결과와 한계

기준 브랜치: `frontend/modern-v2`, 커밋 `fb301e9b451d80accf20a4013c1bda0be1817d73`. 확인 시 main 대비 백엔드 차이는 없었습니다.

이번 작업 환경에서 다음을 확인했습니다.

- PHP 8.4.27 문법 검사, JavaScript 문법 검사.
- 별도 MariaDB 11.8.6에 저장소의 `dbcreate.sql`, `dbinsert.sql` 적용.
- 팀 백엔드의 실제 SQL과 바인딩 값으로 조회 후 PHP 화면 **24가지** 렌더링 통과.
- KBO 10구단·MLB 30구단, GROUP BY·ROLLUP·RANK·NTILE·LAG, KBO OPS·ERA 및 WAR 미확보, 계약 전·중 성적 조회 통과.
- jsdom에서 24개 렌더링 화면의 메뉴·표 페이지 이동·CSRF 폼·2~4명 비교·입력 검증·삭제 취소·문자열 이스케이프·차트·빈 결과 확인.
- 관심 선수 세션 추가·해제와 CSRF 유효/무효 검사 통과.

**XAMPP에서 HTTP 요청으로 전체 앱을 실행한 최종 통합 검증은 아직 아닙니다.** 이 환경에서는 서버 소켓 실행이 제한되어 MariaDB bootstrap 모드로 SQL을 실행하고, 별도 QA 복사본에서만 조회 어댑터로 PHP에 결과를 전달했습니다. 실제 SQL 엔진은 MariaDB이며 SQLite나 가짜 성적 데이터로 분석을 대체하지 않았습니다. 이 어댑터는 배포 파일에 포함되지 않고 원래 PDO 연결 코드가 유지됩니다.

브라우저의 실제 픽셀 배치, Apache/PDO 연결, POST 후 리다이렉트·쿠키 및 DB 쓰기 완료까지는 팀 PC에서 아래 체크리스트로 확인해야 합니다. 이 작업에서 팀 공용 DB에 쓰기는 수행하지 않았습니다.

## 팀 PC 재검증

루트에서 다음 명령을 실행합니다. 테스트용 DB를 사용하세요.

```sh
php tests/smoke.php
python tests/render_pages.py
```

PHP가 PATH에 없으면 `PHP_BIN` 환경변수에 PHP 실행 파일 경로를 지정합니다. 화면 테스트용 Node 의존성은 기존 미리보기 package-lock을 사용합니다.

```sh
cd docs/frontend-preview
npm ci
cd ../..
node tests/ui.cjs
```

Node 버전은 `docs/frontend-preview/package.json`의 engines를 따릅니다. `tests/.output`은 생성된 테스트 화면이며 업로드하지 않습니다. tests는 CLI 실행 전용이고 웹 접근도 차단했습니다.

최종 브라우저 확인 순서:

1. KBO/MLB 구단 목록과 선수 검색을 조회한다.
2. 선수 두 명을 관심 목록에 넣고 비교한다.
3. 별도 테스트 DB에서 계약을 등록하고 목록·구단 지출을 확인한다.
4. 그 계약을 수정하고 삭제하여 변경 결과를 확인한다.
5. 선수 정보를 수정하고 저장 결과를 확인한다.
6. 분석 메뉴 4개, 빈 결과, KBO OPS/ERA, 작은 화면 메뉴를 확인한다.

## 팀원에게 공유할 설명

“Modern v2 디자인을 실제 PHP 페이지에 연결했습니다. 기존 백엔드 함수와 actions를 그대로 사용하며 선수·계약·관심 목록·분석 화면을 구현했습니다. MariaDB SQL 조회와 24개 화면 동작 검사는 통과했고, XAMPP에서 최종 브라우저 실행 및 등록·수정·삭제를 함께 확인하면 됩니다.”

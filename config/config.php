<?php
// config/config.php
// 담당: 백엔드
// 설치 서버가 바뀌면 이 파일만 고친다. 다른 파일에 DB 계정이나 경로를 직접 쓰지 않는다.

define('DB_HOST', 'localhost');
define('DB_NAME', 'team07');
define('DB_USER', 'team07');
define('DB_PASS', 'team07');

// htdocs 아래 폴더 이름. htdocs/team07 이면 '/team07'
define('BASE_URL', '/team07');

// 개발 중에는 true, 제출 전에는 false (오류 내용을 화면에 그대로 보여줄지)
define('DEBUG', true);

<?php
// backend/bootstrap.php
// 담당: 백엔드
// 모든 화면(pages)과 처리 파일(actions)이 맨 위에서 이 파일 하나만 require 한다.

require_once __DIR__ . '/../config/config.php';

ini_set('display_errors', DEBUG ? '1' : '0');
error_reporting(E_ALL);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';

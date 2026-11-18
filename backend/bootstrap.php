<?php
// backend/bootstrap.php
// 담당: 백엔드
// 모든 화면(pages)과 처리 파일(actions)이 맨 위에서 이 파일 하나만 require 한다.

require_once __DIR__ . '/../config/config.php';

ini_set('display_errors', DEBUG ? '1' : '0');
error_reporting(E_ALL);

// 잡지 못한 오류(예: DB 서버가 꺼져 있음)가 나면, 제출 모드(DEBUG=false)에서는 내부 정보 대신 안내 문구만 보여준다
// (담당: 백엔드 신수연)
set_exception_handler(function (Throwable $e): void {
    error_log((string) $e);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo DEBUG
        ? '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>'
        : '<p>일시적인 오류가 발생했습니다. DB 연결 설정(config/config.php)을 확인하세요.</p>';
});

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/session.php';

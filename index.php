<?php
// index.php
// 담당: 프론트
// 첫 화면. 지금은 구단 종합 비교로 바로 보낸다.

require_once __DIR__ . '/backend/bootstrap.php';
redirect('pages/teams/list.php');

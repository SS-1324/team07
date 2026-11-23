<?php
require_once __DIR__.'/../../layout/ui.php';
$editing=false;$contract=filters_recall('contract_add');ui_start('FA 계약 등록','contract_add','계약 기간과 연평균 금액은 서버가 계산합니다.');require __DIR__.'/../../layout/contract-form.php';
ui_end();

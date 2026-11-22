<?php
require_once __DIR__.'/../../layout/ui.php';
$contract=contract_find(input_int('contract_id',0));if(!$contract)ui_missing();$editing=true;ui_start('FA 계약 수정','contracts',$contract['player_name']);require __DIR__.'/../../layout/contract-form.php';
ui_end();

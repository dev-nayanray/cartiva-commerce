<?php
// Every settings tab renders its own fields, with no PHP errors.
$markers=['general'=>'buy_now','fields'=>'main_bd_phone','protect'=>'protect_hours','courier'=>'sf_key','cart'=>'side_cart_open','recovery'=>'recovery_after','tracking'=>'fb_pixel','sms'=>'otp_landing'];
$pass=0;$fail=0;
foreach($markers as $tab=>$field){
  $out=shell_exec('php -d display_errors=1 -d error_reporting=-1 '.escapeshellarg(__DIR__.'/dump-settings.php').' '.escapeshellarg($tab).' 2>&1');
  $okField=str_contains($out,'cartiva_commerce_settings['.$field.']'); $okClean=!preg_match('/(Warning|Fatal error|Notice|Deprecated):/',$out); $okSave=str_contains($out,'Save changes');
  if($okField&&$okClean&&$okSave) $pass++; else {$fail++; echo "FAIL: $tab tab (field:".(int)$okField." clean:".(int)$okClean." save:".(int)$okSave.")\n";}
}
echo "\n$pass passed, $fail failed\n";

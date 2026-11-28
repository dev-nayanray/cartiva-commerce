<?php
define('ABSPATH','/'); define('CARTIVA_COMMERCE_TESTING',1); define('CARTIVA_COMMERCE_DIR',dirname(__DIR__).'/'); define('DAY_IN_SECONDS',86400); define('HOUR_IN_SECONDS',3600); define('MINUTE_IN_SECONDS',60); define('COOKIEPATH','/'); define('COOKIE_DOMAIN',''); define('CARTIVA_COMMERCE_LANDING','cartiva_landing');
$F=[]; function add_filter($h,$c,$p=10,$a=1){global $F;$F[$h][]=$c;} function add_action(){} function apply_filters($h,$v,...$a){global $F; foreach($F[$h]??[] as $c) $v=call_user_func($c,$v,...$a); return $v;}
function __($s){return $s;} function esc_html__($s){return $s;} function esc_html($s){return htmlspecialchars($s);} function esc_attr($s){return htmlspecialchars($s);}
function wp_parse_args($a,$d){return array_merge($d,(array)$a);} function absint($n){return abs((int)$n);} function sanitize_key($s){return strtolower(preg_replace('/[^a-z0-9_\-]/i','',$s));} function sanitize_text_field($s){return trim(strip_tags($s));} function wp_unslash($v){return $v;}
function wp_hash($d){return hash_hmac('md5',$d,'secret-salt');} function is_ssl(){return true;} function is_admin(){return false;} function wp_doing_ajax(){return false;}
function wp_timezone(){return new DateTimeZone('Asia/Dhaka');} function current_user_can(){return false;}
$META=[]; function get_post_meta($id,$k,$s=true){global $META;return $META[$id][$k]??'';}
$SINGULAR=0; function is_singular($t){global $SINGULAR;return $SINGULAR>0;} function get_queried_object_id(){global $SINGULAR;return $SINGULAR;} function cartiva_commerce_is_bot(){return false;}
$CUR=0; function cartiva_commerce_current_landing_id(){global $CUR;return $CUR;}
function cartiva_commerce_landing_config($id){ $c=wp_parse_args((array)get_post_meta($id,'_cartiva_landing'),['offers'=>[],'bumps'=>[]]); return apply_filters('cartiva_commerce_landing_config',$c,$id); }
require CARTIVA_COMMERCE_DIR.'includes/class-countdown.php';
Cartiva_Commerce_Countdown::init(); add_filter('cartiva_commerce_landing_config',['Cartiva_Commerce_Countdown','apply_ending'],10,2);
$C='Cartiva_Commerce_Countdown';
$pass=0;$fail=0; function ok($c,$m){global $pass,$fail; if($c)$pass++; else {$fail++; echo "FAIL: $m\n";}}
function reset_cd(){ $r=new ReflectionProperty('Cartiva_Commerce_Countdown','deadlines'); $r->setAccessible(true); $r->setValue(null,[]); }
function page($id,$cd,$offers=[]){ global $META; $META[$id]['_cartiva_landing']=['countdown'=>$C=Cartiva_Commerce_Countdown::sanitize($cd),'offers'=>$offers,'bumps'=>[['product_id'=>5,'discount'=>20]]]; reset_cd(); }

// Sanitize
$s=$C::sanitize(['enabled'=>1,'mode'=>'fixed','ended'=>'restart','date'=>'2026-12-31T23:59','days'=>99,'style'=>'x']);
ok($s['ended']==='hide' && $s['days']===60 && $s['style']==='boxes' && $s['date']==='2026-12-31T23:59','fixed date cannot restart; limits applied');
ok($C::sanitize(['date'=>'tomorrow'])['date']==='' && $C::sanitize(['days'=>0,'hours'=>0])['days']===3,'bad date dropped; zero length falls back to 3 days');

// Evergreen per visitor: 3 / 5 / 7 days
foreach([3,5,7] as $d){ page(10,['enabled'=>1,'days'=>$d,'ended'=>'price']); $_COOKIE=[]; $SINGULAR=10; $C::start_visitor(); reset_cd();
 $left=$C::deadline(10)-time(); ok(abs($left-$d*86400)<=2,"$d days from the first visit (left ".round($left/86400,2)." days)"); }
page(10,['enabled'=>1,'days'=>3,'hours'=>12,'ended'=>'price']); $_COOKIE=[]; $C::start_visitor(); reset_cd(); ok(abs($C::deadline(10)-time()-(3*86400+12*3600))<=2,'custom 3 days 12 hours');
// a returning visitor keeps their deadline
$start=time()-86400; $_COOKIE['cartiva_cd_10']=$C::sign(10,$start); reset_cd(); $C::start_visitor(); reset_cd(); ok($C::deadline(10)===$start+3*86400+12*3600,'returning visitor keeps the same deadline');
// tampered cookie
$_COOKIE['cartiva_cd_10']=(time()-99*86400).'.'.substr($C::sign(10,$start),-16); reset_cd(); ok($C::visitor_start(10)===0,'a hand-edited cookie is not trusted');
$_COOKIE['cartiva_cd_10']=(time()+999).'.'.'x'; reset_cd(); ok($C::visitor_start(10)===0,'future or unsigned start rejected');
// another visitor (no cookie) -> own clock
$_COOKIE=[]; reset_cd(); ok($C::deadline(10)===0,'no timer until the visitor opens the page');

// Endings
page(11,['enabled'=>1,'days'=>3,'ended'=>'price'],[['product_id'=>9,'discount'=>15]]); $_COOKIE['cartiva_cd_11']=$C::sign(11,time()-4*86400); reset_cd();
ok($C::ending(11)==='price','after 3 days: discounts end');
$cfg=cartiva_commerce_landing_config(11); ok($cfg['offers'][0]['discount']===0 && $cfg['bumps'][0]['discount']===0,'expired: option and bump discounts are 0 for this visitor');
$_COOKIE['cartiva_cd_11']=$C::sign(11,time()-86400); reset_cd(); $cfg=cartiva_commerce_landing_config(11); ok($cfg['offers'][0]['discount']===15,'still running: discount kept');
ok(str_contains($C::html(11,'form'),'data-unit="d"') && str_contains($C::html(11,'form'),'>02<'),'timer shows 2 days left after 1 of 3 days');

page(12,['enabled'=>1,'days'=>5,'ended'=>'close','message'=>'Offer over']); $_COOKIE['cartiva_cd_12']=$C::sign(12,time()-6*86400); reset_cd();
ok($C::ending(12)==='close' && str_contains($C::html(12,'form'),'Offer over'),'close: ended message shown');
class Err{public $e=[]; function add($c,$m){$this->e[]=$m;}} $e=new Err; $CUR=12; $C::validate_order([],$e); ok($e->e===['Offer over'],'close: an order from a form left open is refused');
$e=new Err; $CUR=11; $_COOKIE['cartiva_cd_11']=$C::sign(11,time()-86400); reset_cd(); $C::validate_order([],$e); ok($e->e===[],'running offer: orders go through');

page(13,['enabled'=>1,'days'=>7,'ended'=>'restart']); $SINGULAR=13; $_COOKIE['cartiva_cd_13']=$C::sign(13,time()-8*86400); reset_cd(); $C::start_visitor(); reset_cd();
ok(abs($C::deadline(13)-time()-7*86400)<=2 && $C::ending(13)==='','restart: an expired visitor gets a new 7 days on the next visit');

page(14,['enabled'=>1,'days'=>3,'ended'=>'hide']); $_COOKIE['cartiva_cd_14']=$C::sign(14,time()-4*86400); reset_cd(); ok($C::html(14,'form')==='' && $C::ending(14)==='hide','hide: timer gone, offer stays');

// Fixed date in the store's time zone (Asia/Dhaka, UTC+6)
page(15,['enabled'=>1,'mode'=>'fixed','date'=>'2030-01-01T00:00','ended'=>'close']); ok($C::deadline(15)===gmmktime(18,0,0,12,31,2029),'fixed date read in the store time zone (00:00 Dhaka = 18:00 UTC)');
page(16,['enabled'=>1,'mode'=>'fixed','date'=>'2020-01-01T00:00','ended'=>'close']); ok($C::ending(16)==='close','past fixed date: offer closed for everyone');

// Disabled
page(17,['enabled'=>0,'days'=>3]); $_COOKIE['cartiva_cd_17']=$C::sign(17,time()-9*86400); reset_cd(); ok($C::ending(17)==='' && $C::html(17)==='','countdown off: nothing changes');

echo "\n$pass passed, $fail failed\n";

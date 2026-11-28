<?php
define('ABSPATH','/'); define('CARTIVA_COMMERCE_TESTING',1); define('CARTIVA_COMMERCE_DIR',dirname(__DIR__).'/');
function add_filter(){} function add_action(){} function apply_filters($h,$v){return $v;} function __($s){return $s;} function wp_hash($d){return md5($d);} function wp_timezone(){return new DateTimeZone('UTC');} function _n($a,$b,$n){return $n==1?$a:$b;}
function wp_parse_args($a,$d){return array_merge($d,(array)$a);} function absint($n){return abs((int)$n);} function sanitize_key($s){return strtolower(preg_replace('/[^a-z0-9_\-]/i','',$s));}
function sanitize_text_field($s){return trim(strip_tags($s));} function sanitize_textarea_field($s){return trim(strip_tags($s));} function wp_strip_all_tags($s){return strip_tags($s);} function get_option($k,$d=false){return $d;}
$META=[]; function get_post_meta($id,$k,$s=true){global $META;return $META[$id][$k]??'';}
$STATUS=[]; function get_post_status($id){global $STATUS;return $STATUS[$id]??'publish';} function get_post_type($id){return 'page';}
class P{ public $id,$type,$parent,$children,$buy,$stock,$attr; function __construct($id,$type,$o=[]){$this->id=$id;$this->type=$type;$this->parent=$o['parent']??0;$this->children=$o['children']??[];$this->buy=$o['buy']??true;$this->stock=$o['stock']??true;$this->attr=$o['attr']??'';}
 function is_type($t){return $this->type===$t;} function get_id(){return $this->id;} function get_parent_id(){return $this->parent;} function get_children(){return $this->children;} function is_purchasable(){return $this->buy;} function is_in_stock(){return $this->stock;} function get_name(){return "Shirt - {$this->attr}";} }
$PR=[10=>new P(10,'simple'),20=>new P(20,'variable',['children'=>[21,22,23,24]]),21=>new P(21,'variation',['parent'=>20,'attr'=>'Size: M']),22=>new P(22,'variation',['parent'=>20,'attr'=>'Size: L']),23=>new P(23,'variation',['parent'=>20,'attr'=>'Size: XL','stock'=>false]),24=>new P(24,'variation',['parent'=>20,'attr'=>'Size: S','buy'=>false]),30=>new P(30,'variable',['children'=>[31]]),31=>new P(31,'variation',['parent'=>30,'attr'=>'x','stock'=>false])];
function wc_get_product($id){global $PR;return $PR[$id]??false;} function wc_get_formatted_variation($v){return $v->attr;}
class Cartiva_Commerce_Fields{ static function presets(){return ['bd_cod'=>1];} static function sanitize_config($c){return [];} }
function WC(){ return new class{ function payment_gateways(){return null;} }; }
require CARTIVA_COMMERCE_DIR.'includes/functions.php'; require CARTIVA_COMMERCE_DIR.'includes/class-countdown.php'; require CARTIVA_COMMERCE_DIR.'includes/class-cod.php';
require CARTIVA_COMMERCE_DIR.'includes/class-landing.php';
$pass=0;$fail=0; function ok($c,$m){global $pass,$fail; if($c)$pass++; else {$fail++; echo "FAIL: $m\n";}}
$L='Cartiva_Commerce_Landing';

// simple product still works
$s=$L::sanitize(['offer_mode'=>'single','offers'=>[['item'=>10,'default'=>1]]]);
ok(count($s['offers'])===1 && $s['offers'][0]['product_id']===10 && $s['product_id']===10 && $s['offer_mode']==='single','simple product assigned');
ok(empty($L::$notices),'no notice for a simple product');

// THE BUG: variable product used to be dropped silently
$L::$notices=[]; $s=$L::sanitize(['offer_mode'=>'single','offers'=>[['item'=>20,'qty'=>2,'discount'=>10,'default'=>1]]]);
ok(count($s['offers'])===2,'variable product kept: 2 buyable variations (out-of-stock and unbuyable skipped): '.count($s['offers']));
ok($s['offers'][0]['variation_id']===21 && $s['offers'][1]['variation_id']===22 && $s['offers'][0]['product_id']===20,'variations stored with their parent');
ok($s['offers'][0]['label']==='Size: M' && $s['offers'][1]['label']==='Size: L','each choice labeled by its variation');
ok($s['offers'][0]['qty']===2 && $s['offers'][1]['discount']===10.0,'quantity and discount copied to every variation');
ok($s['offers'][0]['default']===true && $s['offers'][1]['default']===false,'first variation preselected');
ok($s['offer_mode']==='one','switched to "pick one" so shoppers choose a size');
ok($s['product_id']===20 && $s['variation_id']===21,'1.0 keys mirror the first choice');
ok(count($L::$notices)===1 && $L::$notices[0][0]==='info','admin told what happened');

// label prefix kept
$L::$notices=[]; $s=$L::sanitize(['offers'=>[['item'=>20,'label'=>'2-pack']]]); ok($s['offers'][0]['label']==='2-pack — Size: M','own label kept in front');

// nothing buyable
$L::$notices=[]; $s=$L::sanitize(['offers'=>[['item'=>30]]]); ok($s['offers']===[] && $L::$notices[0][0]==='error','no buyable variation: clear error, not silence');

// a specific variation chosen directly
$L::$notices=[]; $s=$L::sanitize(['offers'=>[['item'=>22]]]); ok($s['offers'][0]['variation_id']===22 && $s['offer_mode']==='single','single variation stays one product');

// empty row (the new always-visible field) is ignored, not an error
$L::$notices=[]; $s=$L::sanitize(['offers'=>[['item'=>'','default'=>1]]]); ok($s['offers']===[] && empty($L::$notices),'empty product field saves cleanly');

// bumps and upsell offer: variable -> first buyable variation
$L::$notices=[]; $s=$L::sanitize(['bumps'=>[['item'=>20]],'offer'=>['item'=>20]]);
ok($s['bumps'][0]['variation_id']===21 && $s['offer']['variation_id']===21,'bump and upsell use one exact variation');
ok(count($L::$notices)===2,'admin told which variation was used');

echo "\n$pass passed, $fail failed\n";

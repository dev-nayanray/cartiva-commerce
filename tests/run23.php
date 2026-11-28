<?php
define('ABSPATH','/');
function add_action(){} function __($s){return $s;} function esc_html($s){return htmlspecialchars((string)$s);} function esc_attr($s){return htmlspecialchars((string)$s);} function wp_kses_post($s){return $s;} function wc_price($p){return "৳$p";} function wp_strip_all_tags($s){return strip_tags($s);}
function checked($a,$b=true,$e=true){$r=$a==$b?' checked="checked"':''; if($e) echo $r; return $r;} function is_admin(){return false;} function wp_doing_ajax(){return true;} function wc_get_price_decimals(){return 2;}
function sanitize_text_field($s){return trim(strip_tags($s));} function sanitize_textarea_field($s){return trim(strip_tags($s));} function absint($n){return abs((int)$n);} function wp_unslash($v){return $v;}
$SET=['main_bumps'=>[]]; function cartiva_commerce_setting($k){global $SET;return $SET[$k]??null;} $LAND=0; function cartiva_commerce_current_landing_id(){global $LAND;return $LAND;}
function cartiva_commerce_discounted($p,$d){ $d=max(0,min(90,(float)$d)); return round((float)$p*(100-$d)/100,2);}
class P{public $id,$price,$stock=true; function __construct($id,$price){$this->id=$id;$this->price=$price;} function get_price(){return $this->price;} function get_name(){return "Product {$this->id}";} function is_purchasable(){return true;} function is_in_stock(){return $this->stock;} function get_image(){return '<img>';} function set_price($p){$this->price=$p;} function is_type($t){return $t==='simple';} function get_id(){return $this->id;} function get_parent_id(){return 0;} function get_children(){return [];}}
$PRODUCTS=[1=>new P(1,1000),2=>new P(2,200),3=>new P(3,150),4=>new P(4,500)]; function wc_get_product($id){global $PRODUCTS;return isset($PRODUCTS[$id])?clone $PRODUCTS[$id]:false;}
class Cart{ public $items=[]; function get_cart(){return $this->items;} function add_to_cart($p,$q,$v,$a,$data){ $k='k'.count($this->items).rand(); $this->items[$k]=['product_id'=>$p,'variation_id'=>$v,'quantity'=>$q,'line_subtotal'=>0,'data'=>wc_get_product($p)]+$data; return $k;} function remove_cart_item($k){unset($this->items[$k]);} }
$CART=new Cart; function WC(){ global $CART; return new class($CART){ public $cart; function __construct($c){$this->cart=$c;} }; }
class Cartiva_Commerce_Landing{ static function resolve_single($id){return wc_get_product($id)?['product_id'=>(int)$id,'variation_id'=>0]:null;} static function resolve_item($id){return self::resolve_single($id);} }
class Cartiva_Commerce_Offers{ static function clear_added_notices(){} }
$JSON=null; class Done extends Exception{} function wp_send_json_success($d){global $JSON;$JSON=['ok'=>true,'d'=>$d]; throw new Done;} function wp_send_json_error($d=null,$c=0){global $JSON;$JSON=['ok'=>false]; throw new Done;} function check_ajax_referer(){return true;}
require dirname(__DIR__).'/includes/class-main-bumps.php';
$pass=0;$fail=0; function ok($c,$m){global $pass,$fail; if($c)$pass++; else {$fail++; echo "FAIL: $m\n";}}
$B='Cartiva_Commerce_Main_Bumps';
$rows=$B::sanitize([['active'=>1,'item'=>2,'discount'=>'20','title'=>' Add a cap <b>!</b>','text'=>'Goes with it','min_total'=>'','when'=>''],['active'=>1,'item'=>3,'discount'=>'150','when'=>1,'min_total'=>'800'],['item'=>0],['active'=>1,'item'=>4],['active'=>1,'item'=>4]]);
ok(count($rows)===3 && $rows[0]['title']==='Add a cap !' && $rows[1]['discount']===90.0 && $rows[1]['when_id']===1 && $rows[2]['product_id']===4,'rows cleaned: empty product dropped, discount capped, max three');
$SET['main_bumps']=$rows;
$CART->items=['a'=>['product_id'=>4,'variation_id'=>0,'quantity'=>1,'line_subtotal'=>500]];
$v=$B::visible(); ok(array_keys($v)===[0],'bump 2 needs product 1; bump 3 is already in the cart');
$CART->items=['a'=>['product_id'=>1,'variation_id'=>0,'quantity'=>1,'line_subtotal'=>1000]];
ok(array_keys($B::visible())===[0,1,2],'product 1 over 800: all three show');
$CART->items['a']['line_subtotal']=700; ok(array_keys($B::visible())===[0,2],'below the minimum: bump 2 hidden');
$CART->items['a']['line_subtotal']=1000;
ob_start(); $B::render(); $h=ob_get_clean(); ok(substr_count($h,'cartiva-main-bump__check')===3 && str_contains($h,'Add a cap !') && str_contains($h,'<del aria-hidden="true">৳200</del>') && str_contains($h,'৳160'),'boxes with discounted price');
$LAND=5; ob_start(); $B::render(); ok(ob_get_clean()==='','not on landing page forms'); $LAND=0;
$_POST=['index'=>0,'on'=>1]; try{$B::ajax_toggle();}catch(Done $e){} ok($JSON['ok'] && count($B::lines())===1,'bump added');
try{$B::ajax_toggle();}catch(Done $e){} ok(count($B::lines())===1,'adding twice keeps one');
$B::prices($CART); $line=$CART->items[$B::lines()[0]]; ok($line['data']->get_price()==160.0,'bump discount applied');
$B::prices($CART); ok($CART->items[$B::lines()[0]]['data']->get_price()==160.0,'not discounted twice');
ob_start(); $B::render(); $h=ob_get_clean(); ok(str_contains($h,'is-added') && str_contains($h,'checked="checked"'),'added box shown ticked');
$_POST=['index'=>1,'on'=>1]; try{$B::ajax_toggle();}catch(Done $e){} $CART->items['a']['line_subtotal']=500; $B::clean_cart(); ok(array_keys($B::lines())===[0],'cart dropped under the minimum: bump 2 removed');
$_POST=['index'=>0,'on'=>0]; try{$B::ajax_toggle();}catch(Done $e){} ok($B::lines()===[],'bump removed by unticking');
$_POST=['index'=>9,'on'=>1]; try{$B::ajax_toggle();}catch(Done $e){} ok(!$JSON['ok'],'unknown bump refused');
$_POST=['index'=>0,'on'=>1]; try{$B::ajax_toggle();}catch(Done $e){} unset($CART->items['a']); $B::clean_cart(); ok($B::lines()===[],'cart with only the bump left: bump removed');
$SET['main_bumps'][0]['active']=false; $CART->items=['a'=>['product_id'=>1,'variation_id'=>0,'quantity'=>1,'line_subtotal'=>1000]]; ok(!isset($B::visible()[0]),'switched off: hidden');
class LI{public $m=[]; function add_meta_data($k,$v){$this->m[$k]=$v;}} $li=new LI; $B::tag_line_item($li,'k',['cartiva_main_bump'=>2]); ok($li->m['_cartiva_bump']==='main-2','order line marked as bump');
echo "\n$pass passed, $fail failed\n";

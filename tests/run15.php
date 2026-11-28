<?php
define('ABSPATH','/'); define('CARTIVA_COMMERCE_TESTING',1); define('CARTIVA_COMMERCE_DIR',dirname(__DIR__).'/'); define('DAY_IN_SECONDS',86400);
function add_filter(){} function add_action(){} function apply_filters($h,$v){return $v;} function __($s){return $s;} function esc_html__($s){return $s;} function esc_html($s){return htmlspecialchars($s);}
function wp_parse_args($a,$d){return array_merge($d,(array)$a);} function wc_get_price_decimals(){return 2;} function is_admin(){return false;} function wp_doing_ajax(){return false;} function wc_coupons_enabled(){return true;} function sanitize_key($s){return strtolower(preg_replace('/[^a-z0-9_\-]/i','',$s));} function sanitize_text_field($s){return trim($s);} function wp_unslash($v){return $v;}
function wc_format_coupon_code($c){return strtolower(trim($c));} $TRANS=[]; function get_transient($k){global $TRANS;return $TRANS[$k]??false;} function set_transient($k,$v){global $TRANS;$TRANS[$k]=$v;}
$SETTINGS=['deals'=>[]]; function cartiva_commerce_setting($k){global $SETTINGS;return $SETTINGS[$k]??null;}
class P{ public $id,$price,$stock=true; function __construct($id,$price){$this->id=$id;$this->price=$price;} function get_price(){return $this->price;} function set_price($p){$this->price=$p;} function is_purchasable(){return true;} function has_enough_stock($q){return $this->stock;} function is_type($t){return false;} }
$PRODUCTS=[10=>new P(10,500),20=>new P(20,300)]; function wc_get_product($id){global $PRODUCTS; return isset($PRODUCTS[$id])? clone $PRODUCTS[$id] : false;}
class Cart{ public $lines=[],$coupons=[],$n=0; function get_cart(){return $this->lines;} function get_cart_item($k){return $this->lines[$k]??[];}
 function add_to_cart($pid,$q,$vid,$attr,$data){ $k='k'.(++$this->n); $this->lines[$k]=array_merge(['product_id'=>$pid,'variation_id'=>$vid,'quantity'=>$q,'data'=>wc_get_product($vid?:$pid)],$data); Cartiva_Commerce_Deals::sync(); return $k; }
 function remove_cart_item($k){ unset($this->lines[$k]); Cartiva_Commerce_Deals::sync(); } function set_quantity($k,$q,$r){ $this->lines[$k]['quantity']=$q; Cartiva_Commerce_Deals::sync(); }
 function is_empty(){return !$this->lines;} function has_discount($c){return in_array($c,$this->coupons,true);} function apply_coupon($c){$this->coupons[]=$c;} }
class Sess{ public $d=[]; function get($k){return $this->d[$k]??null;} function set($k,$v){$this->d[$k]=$v;} }
$CART=new Cart; $SESS=new Sess; function WC(){ global $CART,$SESS; return new class($CART,$SESS){ public $cart,$session; function __construct($c,$s){$this->cart=$c;$this->session=$s;} }; }
class WC_Coupon{ const WC_COUPON_SUCCESS=200; public $code; static $known=['spend2000'=>5]; function __construct($c){$this->code=$c;} function get_id(){return self::$known[$this->code]??0;} function get_code(){return $this->code;} }
$VALID=[]; class WC_Discounts{ function __construct($c){} function is_coupon_valid($c){ global $VALID; return in_array($c->code,$VALID,true) ? true : new stdClass; } }
function get_posts($a){ return []; }
require CARTIVA_COMMERCE_DIR.'includes/class-deals.php';
$src=file_get_contents(CARTIVA_COMMERCE_DIR.'includes/class-cart.php'); // progress() is static and pure
require CARTIVA_COMMERCE_DIR.'includes/class-cart.php';
$pass=0;$fail=0; function ok($c,$m){global $pass,$fail; if($c)$pass++; else {$fail++; echo "FAIL: $m\n";}}

// 1. Gift quantity
$r=['buy_qty'=>2,'gift_qty'=>1,'repeat'=>true];
ok(Cartiva_Commerce_Deals::gift_quantity($r,1)===0,'buy 2 get 1: 1 bought = no gift');
ok(Cartiva_Commerce_Deals::gift_quantity($r,2)===1,'2 bought = 1 gift');
ok(Cartiva_Commerce_Deals::gift_quantity($r,5)===2,'5 bought = 2 gifts (repeat)');
$r['repeat']=false; ok(Cartiva_Commerce_Deals::gift_quantity($r,9)===1,'no repeat: always 1');
ok(Cartiva_Commerce_Deals::gift_quantity(['buy_qty'=>1,'gift_qty'=>1,'repeat'=>true],500)===99,'gifts capped at 99');
ok(Cartiva_Commerce_Deals::gift_price(300,100)===0.0 && Cartiva_Commerce_Deals::gift_price(300,50)===150.0 && Cartiva_Commerce_Deals::gift_price(300,150)===0.0,'gift prices: free, half, never negative');

// 2. Cart sync: Buy 2 of product 10, get product 20 free (repeat)
$SETTINGS['deals']=[['active'=>true,'product_id'=>10,'variation_id'=>0,'buy_qty'=>2,'gift_product_id'=>20,'gift_variation_id'=>0,'gift_qty'=>1,'discount'=>100,'repeat'=>true]];
$k=$CART->add_to_cart(10,1,0,[],[]);
$gifts=function(){ global $CART; return array_values(array_filter($CART->lines,fn($l)=>!empty($l['cartiva_gift']))); };
ok(count($gifts())===0,'1 in cart: no gift yet');
$CART->set_quantity($k,2,true); ok(count($gifts())===1 && $gifts()[0]['product_id']===20 && $gifts()[0]['quantity']===1,'2 in cart: gift added');
$CART->set_quantity($k,4,true); ok($gifts()[0]['quantity']===2,'4 in cart: 2 gifts');
$CART->set_quantity($k,1,true); ok(count($gifts())===0,'back to 1: gift removed');
$CART->set_quantity($k,2,true); Cartiva_Commerce_Deals::gift_prices($CART); ok($gifts()[0]['data']->get_price()===0.0,'gift is free in totals');
ok($CART->lines[$k]['data']->get_price()===500,'bought product keeps its price');

// 3. BOGO on the same product: gift lines do not count as bought
$CART=new Cart; $SETTINGS['deals']=[['active'=>true,'product_id'=>10,'variation_id'=>0,'buy_qty'=>1,'gift_product_id'=>10,'gift_variation_id'=>0,'gift_qty'=>1,'discount'=>100,'repeat'=>true]];
$k=$CART->add_to_cart(10,3,0,[],[]); ok($gifts()[0]['quantity']===3 && count($CART->lines)===2,'buy 1 get 1 same product: 3 bought = 3 free, no runaway loop');
$SETTINGS['deals'][0]['active']=false; Cartiva_Commerce_Deals::sync(); ok(count($gifts())===0,'switching the deal off removes the gift');
$SETTINGS['deals'][0]['active']=true; $PRODUCTS[10]->stock=false; Cartiva_Commerce_Deals::sync(); ok(count($gifts())===0,'gift out of stock: not added'); $PRODUCTS[10]->stock=true;

// 4. Free shipping progress
$p=Cartiva_Commerce_Cart::progress(1500,2000); ok($p['left']===500.0 && $p['percent']===75 && !$p['done'],'1500 of 2000: 500 left, 75%');
$p=Cartiva_Commerce_Cart::progress(2300,2000); ok($p['left']===0.0 && $p['percent']===100 && $p['done'],'over the threshold: done');
ok(Cartiva_Commerce_Cart::progress(100,0)===null,'no threshold: no bar');

// 5. Auto coupons
$CART=new Cart; $CART->add_to_cart(10,1,0,[],[]); $TRANS['cartiva_auto_coupons']=['spend2000','ghost'];
$VALID=[]; Cartiva_Commerce_Deals::sync(); ok($CART->coupons===[],'not applied while the cart does not qualify');
$VALID=['spend2000','ghost']; Cartiva_Commerce_Deals::sync(); ok($CART->coupons===['spend2000'],'applied once it qualifies; deleted coupon skipped');
Cartiva_Commerce_Deals::sync(); ok($CART->coupons===['spend2000'],'not applied twice');
$CART->coupons=[]; $_REQUEST=['wc-ajax'=>'remove_coupon']; Cartiva_Commerce_Deals::coupon_removed('SPEND2000'); Cartiva_Commerce_Deals::sync();
ok($CART->coupons===[],'shopper removed it: not added again');
$SESS->d=[]; $_REQUEST=[]; $_SERVER['REQUEST_URI']='/cart/'; Cartiva_Commerce_Deals::coupon_removed('spend2000'); Cartiva_Commerce_Deals::sync();
ok($CART->coupons===['spend2000'],'removed by WooCommerce (cart stopped qualifying): comes back when it qualifies');

echo "\n$pass passed, $fail failed\n";

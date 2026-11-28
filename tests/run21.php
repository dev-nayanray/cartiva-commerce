<?php
define('ABSPATH','/tmp/fakewp/'); define('MINUTE_IN_SECONDS',60); define('ARRAY_A','ARRAY_A');
@mkdir('/tmp/fakewp/wp-admin/includes',0777,true); file_put_contents('/tmp/fakewp/wp-admin/includes/upgrade.php','<?php function dbDelta($sql){ global $wpdb; $sql=preg_replace(["/bigint\\(20\\) unsigned/","/int\\(10\\) unsigned/","/tinyint\\(1\\) unsigned/","/smallint\\(5\\) unsigned/","/decimal\\(19,4\\)/","/varchar\\(20\\)/","/,\\s*KEY [a-z_]+ \\([a-z_,]+\\)/","/PRIMARY KEY  \\(order_id\\)/"],["INTEGER","INTEGER","INTEGER","INTEGER","REAL","TEXT","","PRIMARY KEY (order_id)"],$sql); $wpdb->pdo->exec($sql); }');
function add_action(){} function get_option($k,$d=false){global $OPT;return $OPT[$k]??$d;} function update_option($k,$v){global $OPT;$OPT[$k]=$v;} function delete_option($k){global $OPT;unset($OPT[$k]);}
$EV=[]; function wp_next_scheduled($h){global $EV;return $EV[$h]??false;} function wp_schedule_single_event($t,$h){global $EV;$EV[$h]=$t;}
$CACHE=[]; function wp_cache_get($k,$g){global $CACHE;return $CACHE["$g:$k"]??false;} function wp_cache_set($k,$v,$g,$t=0){global $CACHE;$CACHE["$g:$k"]=$v;}
function wc_get_order_statuses(){return ['wc-pending'=>1,'wc-processing'=>1,'wc-completed'=>1];}
class FakeDB{ public $prefix='wp_'; public $pdo; function __construct(){$this->pdo=new PDO('sqlite::memory:');}
 function get_charset_collate(){return '';}
 function prepare($q,$args){ $args=is_array($args)?$args:array_slice(func_get_args(),1); $i=0; return preg_replace_callback('/%[dsf]/',function($m)use(&$i,$args){$v=$args[$i++]; return $m[0]==='%s'?$this->pdo->quote($v):($m[0]==='%d'?(int)$v:(float)$v);},$q); }
 function replace($t,$row,$f){ $c=implode(',',array_keys($row)); $p=implode(',',array_fill(0,count($row),'?')); $st=$this->pdo->prepare("REPLACE INTO $t ($c) VALUES ($p)"); return $st->execute(array_values($row)); }
 function delete($t,$w,$f){ $st=$this->pdo->prepare("DELETE FROM $t WHERE order_id=?"); $st->execute([$w['order_id']]); return $st->rowCount(); }
 function get_row($q,$o){ return $this->pdo->query($q)->fetch(PDO::FETCH_ASSOC); } function get_results($q,$o){ return $this->pdo->query($q)->fetchAll(PDO::FETCH_ASSOC); } }
$wpdb=new FakeDB;
class D{ public $t; function __construct($t){$this->t=$t;} function getTimestamp(){return $this->t;} function date_i18n($f){return gmdate($f,$this->t+6*3600);} }
class Item{public $m,$t; function __construct($t,$m=[]){$this->t=$t;$this->m=$m;} function get_meta($k){return $this->m[$k]??'';} function get_total(){return $this->t;}}
class WC_Order{ public $id,$meta,$status,$total,$t,$items,$type='shop_order'; function __construct($id,$landing,$status,$total,$t,$items=[],$offers=null){$this->id=$id;$this->meta=['_cartiva_landing_id'=>$landing,'_cartiva_offers'=>$offers];$this->status=$status;$this->total=$total;$this->t=$t;$this->items=$items;}
 function get_meta($k){return $this->meta[$k]??'';} function get_id(){return $this->id;} function get_status(){return $this->status;} function get_total(){return $this->total;} function get_date_created(){return new D($this->t);} function get_items(){return $this->items;} function get_type(){return $this->type;} }
$ALL=[]; function wc_get_orders($a){global $ALL; $ids=array_keys(array_filter($ALL,fn($o)=>$o->get_meta('_cartiva_landing_id'))); sort($ids); return array_slice($ids,$a['offset'],$a['limit']);} function wc_get_order($id){global $ALL;return $ALL[$id]??false;}
require dirname(__DIR__).'/includes/class-ledger.php';
$pass=0;$fail=0; function ok($c,$m){global $pass,$fail; if($c)$pass++; else {$fail++; echo "FAIL: $m\n";}}
$L='Cartiva_Commerce_Ledger';
$L::maybe_install(); ok(get_option('cartiva_commerce_db_version')==='1' && isset($EV[$L::BACKFILL]) && !$L::ready(),'table made, older orders queued');
$now=1764000000;
$ALL[1]=new WC_Order(1,10,'processing',1000,$now-3600,[new Item(200,['_cartiva_bump'=>'1']),new Item(800)],[50=>'yes']);
$ALL[2]=new WC_Order(2,10,'completed',500,$now-86400*3,[new Item(500)],[50=>'no']);
$ALL[3]=new WC_Order(3,11,'processing',700,$now-7200,[new Item(300,['_cartiva_upsell'=>'1']),new Item(400)],[60=>'yes']);
$ALL[4]=new WC_Order(4,10,'cancelled',900,$now-100,[]);
$ALL[5]=new WC_Order(5,0,'processing',999,$now,[]); // main site
foreach($ALL as $o) $L::record($o);
$n=$wpdb->pdo->query('SELECT COUNT(*) FROM wp_cartiva_order_stats')->fetchColumn(); ok((int)$n===4,'main-site orders are not added');
$s=$L::summary([10],0,$now+10);
ok($s['orders']===2 && $s['revenue']===1500.0 && $s['bump_orders']===1 && $s['bump_revenue']===200.0 && $s['upsell_yes']===1 && $s['upsell_no']===1,'page totals; cancelled order not counted');
ok(count($s['days'])===2 && array_sum($s['days'])===1500.0,'revenue by day');
$s=$L::summary([10,11],$now-86400,$now+10); ok($s['orders']===2 && $s['revenue']===1700.0 && $s['upsell_revenue']===300.0,'several pages, last day only');
ok($L::summary([],0,0)['orders']===0,'no pages: zeros');
// status change updates row
$ALL[4]->status='processing'; $L::record($ALL[4]); ok($L::summary([10],0,$now+10)['orders']===3,'order moved to processing now counts (cache cleared)');
$L::forget(4); ok($L::summary([10],0,$now+10)['orders']===2,'deleted order removed');
// backfill from scratch
$wpdb->pdo->exec('DELETE FROM wp_cartiva_order_stats'); for($i=100;$i<350;$i++){ $ALL[$i]=new WC_Order($i,12,'completed',10,$now-50,[]); }
$L::backfill(); ok(!$L::ready() && get_option('cartiva_commerce_ledger_offset')===200,'first batch of 200');
$L::backfill(); ok($L::ready() && $L::summary([12],0,$now+10)['orders']===250,'second batch finishes; all older orders counted');
ok($L::summary([10,11],0,$now+10)['orders']===4,'older pages counted too (order 4 is processing now)');
echo "\n$pass passed, $fail failed\n";

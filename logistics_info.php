<?php
/**
 * 查询物流信息
 */
define('IN_ECS', true);
require(dirname(__FILE__) . '/includes/init.php');
date_default_timezone_set('Asia/Shanghai');

$aikuaidi_key = '1766788d664b4322abc0e7b4955832d9';
// 快递公司code映射
$shipping_code = array (
    'ems'         => 'ems',
    'ems2'        => 'ems',
    'sto_express' => 'zjs',
    'sto_nopay'   => 'zjs',
    'zto'         => 'zhongtong',
    'sto'         => 'shentong',
    'yto'         => 'yuantong',
    'yto_no_pay'  => 'yuantong',
    'sf'          => 'shunfeng',
    'emssn'       => 'ems',
    'sf2'         => 'shunfeng',
    'yunda'       => 'yunda',
    'postb'       => 'bgpyghx',
);

//自动批量确认收货
if($_REQUEST['act'] == 'auto_checked_logistics'){
    $end_time = $_SERVER['REQUEST_TIME']-(24*60*60*2);
    $sql_select = 'SELECT order_id,tracking_sn,shipping_code FROM '.
        $GLOBALS['ecs']->table('order_info')." WHERE order_status=5 AND shipping_status=1 AND add_time<$end_time"
        ." AND order_type<>1 AND admin_id<>185 AND final_amount>0 AND shipping_id NOT IN(24,25,16) AND tracking_sn<>'$";

    $order_info = $GLOBALS['db']->getAll($sql_select);

    if ($order_info) {
        $check = array();
        foreach ($order_info as $v) {
            $logistics_code = $shipping_code[$order_info['shipping_code']] ? $shipping_code[$order_info['shipping_code']] : $order_info['shipping_code'];
            $logistics_url = "http://www.aikuaidi.cn/rest/?key=$aikuaidi_key&order={$order_info['tracking_sn']}&id=$logistics_code&ord=asc&show=json";
            $logistics_info = file_get_contents($logistics_url);
            $logistics_info = $json->decode($logistics_info,true);
            if ($logistics_info['status'] == 4) {
                $check[] = $v['order_id'];
            }
        }
        if ($check) {
            $check = implode($check);
            $sql = 'UPDATE '.$GLOBALS['ecs']->table('order_info').' SET shipping_status=2 AND';
        }
    }
}

if ($id = intval($_REQUEST['id'])) {
    $sql_select = 'SELECT consignee,mobile,tel,tracking_sn,shipping_code,shipping_name FROM '.
        $GLOBALS['ecs']->table('order_info')." WHERE order_id=$id";
    $order_info = $GLOBALS['db']->getRow($sql_select);

    $logistics_code = $shipping_code[$order_info['shipping_code']] ? $shipping_code[$order_info['shipping_code']] : $order_info['shipping_code'];

    $logistics_url = "http://www.aikuaidi.cn/rest/?key=$aikuaidi_key&order={$order_info['tracking_sn']}&id=$logistics_code&ord=asc&show=html";

    $logistics_info = file_get_contents($logistics_url);
    //var_dump($logistics_info);

    //$logistics_info = iconv('gb2312', 'UTF-8', $logistics_info);

    echo <<<EOF
        <span>收货人：<strong>{$order_info['consignee']}</strong></span>
        <span>配送：<strong>{$order_info['shipping_name']}</strong></span>
        <span>运单号：<strong>{$order_info['tracking_sn']}</strong></span><br><br>
EOF;
    //<span>联系电话：<strong>{$order_info['mobile']} // {$order_info['tel']}</strong></span>
    echo $logistics_info;

    echo "<br><br><a href='$logistics_url' target='_self'>点我点我</a>";
    //exit;
}

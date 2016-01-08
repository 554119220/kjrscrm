<?php
define('IN_ECS', true);
error_reporting(0);
require(dirname(__FILE__) . '/includes/init.php');
require_once(ROOT_PATH . 'includes/lib_order.php');

date_default_timezone_set('Asia/Shanghai');

$date_time = $_SERVER['REQUEST_TIME']-3*24*3600;
$sql_select = 'SELECT final_amount,add_time,user_id,order_id FROM '.$GLOBALS['ecs']->table('order_info')." WHERE shipping_time>$date_time AND shipping_status=1 AND order_type NOT IN(1,100)";
$order_amount = $GLOBALS['db']->getAll($sql_select);

if ($order_amount) {
    foreach ($order_amount as $v) {
        $sql = 'SELECT customer_type FROM'.$GLOBALS['ecs']->table('users')." WHERE user_id={$v['user_id']}";
        $customer_type = $GLOBALS['db']->getOne($sql);
        if (!in_array($customer_type,array(21,6)) && 0 < $v['final_amount']) {
            if ($v['final_amount'] < 800) {
                $sql_update = 'UPDATE '.$GLOBALS['ecs']->table('users')." SET admin_id=520 WHERE user_id={$v['user_id']} ".
                    ' AND role_id NOT IN ('.OFFLINE_SALE.',8,23) LIMIT 1';
            } else {
                $sql_update = 'UPDATE '.$GLOBALS['ecs']->table('users')." SET admin_id=605 WHERE user_id={$v['user_id']}".
                    ' AND role_id NOT IN ('.OFFLINE_SALE.',8,23) LIMIT 1';
            }
        }
        if($sql_update){
            if ($GLOBALS['db']->query($sql_update)) {
               $sql_update = 'UPDATE '.$GLOBALS['ecs']->table('users').' u,'.$GLOBALS['ecs']->table('admin_user').
                   " a SET u.order_time={$v['add_time']},u.admin_name=a.user_name,u.role_id=a.role_id,".
                   'u.group_id=a.group_id,u.assign_time=UNIX_TIMESTAMP() WHERE u.role_id NOT IN ('.OFFLINE_SALE.
                   ",8) AND u.user_id={$v['user_id']} AND a.user_id=u.admin_id";
               $GLOBALS['db']->query($sql_update);
            }
        }
    }
}

<?php
/**
 * ECSHOP 订单管理
 * ============================================================================
 * 版权所有 2005-2010 上海商派网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.ecshop.com；
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和
 * 使用；不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * $Author: yehuaixiao $
 * $Id: order.php 17157 2010-05-13 06:02:31Z yehuaixiao $
 */
define('IN_ECS', true);

require(dirname(__FILE__).'/includes/init.php');
require_once(ROOT_PATH.'includes/lib_order.php');
require_once(ROOT_PATH.'includes/lib_goods.php');
require_once(ROOT_PATH.'includes/lib_main.php');
require_once(ROOT_PATH.'admin/includes/lib_wav.php');


$file = basename($_SERVER['PHP_SELF'], '.php');
$smarty->assign('filename', $file);

date_default_timezone_set('Asia/Shanghai');
ini_set('memory_limit', '512M');
$nowtime = time();

$smarty->assign('full', 1);
$smarty->assign('act', $_REQUEST['act']);
$res['left'] = sub_menu_list($file);
if ($res['left'] === false) { unset($res['left']); }

if (!empty($_REQUEST['start_time']) && !empty($_REQUEST['end_time'])) {
    $smarty->assign('start_time', stamp2date($_REQUEST['start_time'], 'Y-m-d'));
    $smarty->assign('end_time', stamp2date($_REQUEST['end_time'], 'Y-m-d'));
}

/*-- 左侧菜单 --*/
if ($_REQUEST['ext'] == 'top') {
    $file = strstr(basename($_SERVER['PHP_SELF']), '.', true);
    $nav = list_nav();
    $smarty->assign('nav_2nd', $nav[1][$file]);
    $smarty->assign('nav_3rd', $nav[2]);
    $smarty->assign('file_name', $file);
    $res['left'] = $smarty->fetch('left.htm');
}

error_reporting(E_ALL ^ E_NOTICE);
/*-- 报表子菜单 --*/
if ($_REQUEST['act'] == 'menu')
{
    $file = strstr(basename($_SERVER['PHP_SELF']), '.', true);
    $nav = list_nav();
    $smarty->assign('nav_2nd', $nav[1][$file]);
    $smarty->assign('nav_3rd', $nav[2]);
    $smarty->assign('file_name', $file);

    die($smarty->fetch('left.htm'));
}

//统计部门订单数量及销量
elseif($_REQUEST['act'] == 'role_order_sales' ){
    $res['switch_tag'] = true;
    $res['id'] = isset($_REQUEST['platform']) ? $_REQUEST['platform'] : 0;

    $status = ' AND order_status IN (1,5) AND shipping_status<>3';
    $refund_where = '';
    $trans_role_list = '';

    $department_list = get_department(' AND onsale=1',true);
    //$role_list = get_role_list(1,true,' AND parent_id IN(0,-1)');      // 销售平台
    if (!empty($res['id'])) {
        //$platform_list = $role_list;
        //$sql = 'SELECT depart_id FROM '.$GLOBALS['ecs']->table('role')." WHERE depart_id={$res['id']}";
        //$depart_id = $GLOBALS['db']->getOne($sql);
        $sql = 'SELECT role_id FROM '.$GLOBALS['ecs']->table('role')." WHERE depart_id={$res['id']}";
        $stats_list = $GLOBALS['db']->getCol($sql);
        if ($stats_list) {
            $stats_list = implode(',',$stats_list);
        }
    }else{
        $stats_list = report_authority($status,$refund_where,$trans_role_list);
        $platform_list = platform_list($trans_role_list);      // 销售平台
        $_REQUEST['group_by'] = true;
    }

    if (!empty($stats_list)) {
        $status .= " AND platform IN ($stats_list)";
        $refund_where = " AND platform IN ($stats_list)";
    }

    $role_sales = get_role_list(1,true," AND role_id IN($stats_list)");      // 销售平台
    if ($_REQUEST['group_by']) {
        $result = platform_order_stats($refund_where,$status,$role_list);
    }else{
        $result = platform_order_stats($refund_where,$status,$role_sales);
    }
    extract($result);

    $temp = array();
    if ($_REQUEST['group_by']) {
        foreach ($department_list as $val) {
            $temp[$val['role_id']] = $val['role_name'];
        }
    }else{
        foreach ($role_sales as $val) {
            $temp[$val['role_describe']] = $val['role_name'];
        }
    }

    $smarty->assign('platform_list', $department_list);
    $smarty->assign('act',   $_REQUEST['act']);
    $smarty->assign('stats', $stats);
    $smarty->assign('temp', $temp);
    $smarty->assign('return', $return);

    $res['main'] = $smarty->fetch('order_sales.htm');

    die($json->encode($res));
}

/* 统计小组订单数量及销量 */
elseif ($_REQUEST['act'] == 'order_sales') {
    $res['switch_tag'] = true;
    $res['id'] = isset($_REQUEST['platform']) ? $_REQUEST['platform'] : 0;

    $status = ' AND order_status IN (1,5) AND shipping_status<>3 ';
    $refund_where = '';
    $trans_role_list = '';

    $stats_list = report_authority($status,$refund_where,$trans_role_list);
    if (!empty($stats_list)) {
        $status .= " AND platform IN ($stats_list)";
        $refund_where = " AND platform IN ($stats_list)";
    }

    // 销售平台
    if (isset($_REQUEST['platform']) && $_REQUEST['platform'] >0) {
        $status .= ' AND platform='.intval($_REQUEST['platform']);
        $refund_where .= ' AND platform='.intval($_REQUEST['platform']);
    }

    $result = platform_order_stats($refund_where,$status);
    extract($result);

    $platform_list = platform_list($trans_role_list);      // 销售平台
    $smarty->assign('platform_list', $platform_list);

    $smarty->assign('act',   $_REQUEST['act']);
    $smarty->assign('stats', $stats);

    $temp = array();
    foreach ($platform_list as $val) {
        //$temp[$val['role_describe']] = preg_replace('/\d/', '', $val['role_name']);
        $temp[$val['role_describe']] = $val['role_name'];
    }

    $smarty->assign('temp', $temp);
    $smarty->assign('return', $return);
    $res['main'] = $smarty->fetch('order_sales.htm');

    die($json->encode($res));
}

/* 产品销售排行 */
elseif ($_REQUEST['act'] == 'goods_num') {
    $depart_list = get_department();
    $brand_list = get_brand_id_name(true);
    // 单品销量
    $sales_rank = sales_rank();

    // 套餐销量
    $_REQUEST['package'] = 1;
    $package_sales_rank  = sales_rank();
    $package_sales = break_up_package($package_sales_rank['sales_order_data']);
    $sales_rank['sales_order_data'] = merge_to_single($sales_rank['sales_order_data'], $package_sales);

    $platform_list = platform_list();
    if (admin_priv('rank_list_part', '', false)) {
        array_unshift($platform_list, array('role_name'=>'全部','role_id'=>0));
    }

    $smarty->assign('rank', $sales_rank['sales_order_data']);
    $smarty->assign('platform_list', $platform_list);
    if (admin_priv('all','',false)) {
        $smarty->assign('depart_list',$depart_list);
    }
    if ($_REQUEST['depart_id']) {
        $smarty->assign('depart_id',$_REQUEST['depart_id']);
    }

    $smarty->assign('curr_title', '产品销量排行');
    //$smarty->assign('num', sprintf('（共%d条记录）', $sales_rank['record_count']));

    // 分页设置
    if (!empty($sales_rank['page_set'])) {
        $smarty->assign('filter',       $sales_rank['filter']);
        $smarty->assign('record_count', $sales_rank['record_count']);
        $smarty->assign('page_count',   $sales_rank['page_count']);
        $smarty->assign('page_size',    $sales_rank['page_size']);
        $smarty->assign('page_start',   $sales_rank['start']);
        $smarty->assign('page_end',     $sales_rank['end']);
        $smarty->assign('full_page',    1);
        $smarty->assign('page_link',    $sales_rank['condition']);
        $smarty->assign('page_set',     $sales_rank['page_set']);
        $smarty->assign('page',         $sales_rank['page']);
        $smarty->assign('act',          trim($_REQUEST['act']));
    }

    $smarty->assign('platform',     isset($_REQUEST['platform']) ? $_REQUEST['platform']:0);
    $smarty->assign('start_time',   $sales_rank['start_time']);
    $smarty->assign('end_time',     $sales_rank['end_time']);

    if (isset($_REQUEST['platform'])) {
        $res['id'] = intval($_REQUEST['platform']);
        $res['switch_tag'] = 'true';
    }

    if (isset($_REQUEST['package'])) {
        $smarty->assign('package_struct', 1);
    }

    $config = report_statistics_limit(1); // 报表统计范围
    if ($config['statistics_date_limit'] > 0 && $config['offset_month'] > 0) {
        $final_month = date('Y')*12 + date('m') -$config['offset_month'];
        $min_date = 'minDate:\''.floor($final_month/12).'-'.($final_month%12).'-01 00:00:00\'';
        $max_date = 'maxDate:\''.date('Y-m-t 23:59:59').'\'';

        $smarty->assign('min_date', $min_date);
        $smarty->assign('max_date', $max_date);
    }

    $smarty->assign('sel_item_list',array(1=>'部门','小组','员工'));
    $smarty->assign('brand_id',$_REQUEST['brand_id']);
    $smarty->assign('brand_list',$brand_list);
    $res['act'] = $_REQUEST['act'];
    $smarty->assign('goods_sale_rank',$smarty->fetch('goods_sale_rank.htm'));
    $res['main'] = $smarty->fetch('sales_rank.htm');
    die($json->encode($res));
}
//产品销量明细排行
elseif($_REQUEST['act'] == 'goods_sale_rank'){
    $sel_item   = is_numeric($_REQUEST['sel_item']) ? intval($_REQUEST['sel_item']) : 2;
    $goods_sn   = mysql_real_escape_string($_REQUEST['goods_sn']);
    $start_time = strtotime(date('Y-m-d 00:00:00',strtotime($_REQUEST['start_time'])));
    $end_time   = strtotime(date('Y-m-d 23:59:59',strtotime($_REQUEST['end_time'])));
    $platform   = intval($_REQUEST['platform']);
    $depart_id  = intval($_REQUEST['depart_id']);

    //不包含待发货
    $where = " WHERE oi.order_status IN(5,1) AND oi.shipping_status IN(1,2,4) AND oi.add_time BETWEEN $start_time AND $end_time AND oi.order_type IN (2,3,4,6,100,9) ";
    if ($platform) {
        $where .= " AND oi.platform=$platform ";
    }

    if ($depart_id) {
        $platform = get_role_by_depart($depart_id,true);
        $where .= " AND oi.platform IN($platform) ";
    }

    $platform_sales = for_goods_sale_rank($where);
    $where .= ' AND oi.order_id=og.order_id ';
    //总的销售额
    $sql_count = 'SELECT SUM(og.goods_number*og.goods_price) FROM '.$GLOBALS['ecs']->table('order_goods').' og,'
        .$GLOBALS['ecs']->table('order_info')." oi $where AND og.goods_sn='$goods_sn' AND og.is_gift<>1 ";
    $total = $GLOBALS['db']->getOne($sql_count);
    $key_name = $sel_item==1?'depart_id':'role_id';
    if ($total) {
        // @sel_item 1 部门 2 小组 3 员工
        if ($sel_item == 3) {
            $table = 'admin_user';
            $field = ',t.user_name name,t.role_id';
            $where .= ' AND t.user_id=oi.admin_id ';
            $group_by = 'oi.admin_id, ';
        }else{
            $table = 'role';
            $field = ',t.role_name name,t.role_id';
            if ($sel_item == 1) {
                $field .= ",t.depart_id";
            }
            $where .= ' AND t.role_id=oi.platform ';
            $group_by = 'oi.platform,';
        }

        //正常销量不包含赠品
        $sql = 'SELECT og.goods_name,SUM(og.goods_number) goods_num,count(oi.order_id) order_num,'.
            'SUM(og.goods_number*og.goods_price) turnover %s FROM '.$GLOBALS['ecs']->table('order_goods').' og, '.
            $GLOBALS['ecs']->table('order_info').' oi, '.$GLOBALS['ecs']->table($table)
            ." t $where %s GROUP BY %s og.goods_sn ORDER BY goods_num DESC";
        $rank_status = $GLOBALS['db']->getAll(sprintf($sql, $field," AND og.goods_sn='$goods_sn' AND og.is_gift<>1 ",$group_by));
        $gift_rank_status = $GLOBALS['db']->getAll(sprintf($sql, $field," AND og.goods_sn='$goods_sn' AND og.is_gift=1 ",$group_by));
        $package_sales_rank  = $GLOBALS['db']->getAll(sprintf($sql, $field.',og.goods_sn '," AND og.goods_sn REGEXP '\d*_\d*' AND og.is_gift<>1 ",$group_by));

        if ($package_sales_rank) {
            foreach ($package_sales_rank as $val){
                $package_sn[] = $val['goods_sn'];
            }
            $sql_select = 'SELECT package_sn,goods_sn,goods_name,num FROM '
                .$GLOBALS['ecs']->table('packing_goods')
                ." WHERE goods_sn='$goods_sn' "
                .' AND package_sn IN ("'.implode('","', $package_sn).'")';
            $result = $GLOBALS['db']->getAll($sql_select);
            $package_goods_list = array();
            foreach ($package_sales_rank as &$p) {
                foreach ($result as $r) {
                    if (!strcmp($r['package_sn'],$p['goods_sn'])) {
                        $p['total_num'] = $r['num']*$p['goods_num'];
                        $package_goods_list[] = $p;
                    }
                }
            }
        }

        if ($rank_status) {
            $res['goods_name'] = $rank_status[0]['goods_name'];
            if ($gift_rank_status) {
                foreach ($gift_rank_status as $k=>&$g) {
                    $g['turnover'] = 0;
                    foreach ($rank_status as &$v) {
                        if (!strcmp($g['name'],$v['name'])) {
                            $v['goods_num'] = $g['goods_num']+$v['goods_num'];
                            unset($gift_rank_status[$k]);
                        }
                    }   
                }
                if (count($gift_rank_status)) {
                    $rank_status = array_merge($rank_status,$gift_rank_status);
                }
            }
            $total = 0;
            if ($package_goods_list) {
                unset($v,$p);
                foreach ($rank_status as &$v) {
                    foreach ($package_goods_list as $k=>$p) {
                        if (!strcmp($v['name'],$p['name'])) {
                            $v['turnover'] = bcadd($p['turnover'],$v['turnover'],2);
                            $v['goods_num'] = $p['total_num']+$v['goods_num'];
                            unset($package_goods_list[$k]);
                        }
                    }
                }
                if (count($package_goods_list)>0){
                   $rank_status = array_merge($rank_status,$package_goods_list);
                }
            }
            unset($v);
            foreach ($rank_status as $v) {
                $total += $v['turnover'];
            }
            foreach ($rank_status as &$r) {
                $r['sale_rate'] = bcdiv($r['turnover'],$total,3)*100;
                $r['ave'] = bcdiv($r['turnover'],$r['goods_num'],2);
            }

            //部门
            if ($sel_item == 1) {
                unset($v,$r);
                $depart_list = get_department();
                foreach ($depart_list as $ky=>&$v) {
                    $v['name'] = $v['depart_name'];
                    foreach ($rank_status as $k=>$r) {
                        if ($v['depart_id'] == $r['depart_id']) {
                            $v['goods_num'] += $r['goods_num'];
                            $v['order_num'] += $r['order_num'];
                            $v['turnover'] += $r['turnover'];
                        }
                    }
                    if ($v['goods_num'] > 0 ) {
                        $v['turnover'] = sprintf("%.2f",$v['turnover']);
                        $v['ave'] = bcdiv($v['turnover'],$v['goods_num'],2);
                        $v['sale_rate'] = bcdiv($v['turnover'],$total,3)*100;
                        $sort[] = $v['turnover'];
                    }else{
                        unset($depart_list[$ky]);
                    }
                }
                if ($depart_list) {
                    array_multisort($sort,SORT_DESC,$depart_list);
                    $rank_status = $depart_list;
                }
            }
        }
    }
    if ($rank_status && $platform_sales) {
        unset($v);
        foreach ($rank_status as &$v) {
            foreach ($platform_sales as $s) {
                if ($v[$key_name] == $s[$key_name] && $s['final_amount']>0) {
                    $v['platform_sale_rate'] = bcdiv($v['turnover'],$s['final_amount'],4)*100;
                }
            }
        }
    }

    $smarty->assign('sel_item',$sel_item);
    $smarty->assign('sel_item_list',array(1=>'部门','小组','员工'));
    $smarty->assign('rank_status',$rank_status);
    $res['goods_sn'] = $goods_sn;
    $res['main'] = $smarty->fetch('goods_sale_rank.htm');
    die($json->encode($res));
}

/* 销售统计 */
elseif ($_REQUEST['act'] == 'nature_stats') {
    if (admin_priv('nature_stats_all', '', false)) {
        $_REQUEST['target'] = isset($_REQUEST['target']) ? $_REQUEST['target'] : 'platform_stats';
    } elseif (admin_priv('platform_stats', '', false) || admin_priv('nature_trans-part', '', false)) {
        $_REQUEST['target'] = 'platform_stats';
        $smarty->assign('platform_stats', 1);
    } elseif (admin_priv('self_stats', '', false)) {
        $_REQUEST['target'] = 'self_stats';
        $smarty->assign('self_stats', 1);
    }

    $nature_stats = nature_stats();
    $stats_all = stats_all();
    $stats_all_return = stats_all_return();

    // 获取当月的日期
    $date_limit = date('d',    time()) +1;
    $year_month = date('Y-m-', time());
    for ($i = $date_limit; $i > 0; $i--) {
        $date_list[] = $i < 10 ? $year_month.'0'.$i : $year_month.$i;
    }

    $smarty->assign('date_list', $date_list);
    $smarty->assign('curr_title', '各平台销量');

    $config = report_statistics_limit(1); // 报表统计范围
    if ($config['statistics_date_limit'] > 0 && $config['offset_month'] > 0) {
        $final_month = date('Y')*12 + date('m') -$config['offset_month'];
        $min_date = 'minDate:\''.floor($final_month/12).'-'.($final_month%12).'-01 00:00:00\'';
        $max_date = 'maxDate:\''.date('Y-m-t 23:59:59').'\'';

        $smarty->assign('min_date', $min_date);
        $smarty->assign('max_date', $max_date);
    }

    $smarty->assign('nature_stats', $nature_stats['stats_list']);
    $smarty->assign('start_time',   $nature_stats['start_time']);
    $smarty->assign('end_time',     $nature_stats['end_time']);
    $smarty->assign('stats',        $stats_all);
    $smarty->assign('stats_return', $stats_all_return);
    $smarty->assign('target',       @$_REQUEST['target']);

    $smarty->assign('tag', isset($_REQUEST['tag']) ? $_REQUEST['tag'] : 0);

    $res['act']  = 'person_style';
    $res['main'] = $smarty->fetch('nature_stats.htm');
    die($json->encode($res));
}

/* 重复购买 */
elseif ($_REQUEST['act'] == 'rebuy_stats') {

    $rebuy_stats = rebuy_stats();

    if ($rebuy_stats === false) {
        $res['req_msg'] = true;
        $res['timeout'] = 2000;
        $res['message'] = '未查询到重复购买的记录！';

        die($json->encode($res));
    }

    $admin = array ();
    if (admin_priv('rebuy_stats_all', '', false)) {
        $platform = get_role(' role_id IN ('.OFFLINE_SALE.') AND depart_id>0 ');
        $platform_list = array ();
        foreach ($platform as $val) {
            $platform_list[$val['role_id']] = $val['role_name'];
        }

        $smarty->assign('platform',    $platform_list);

        $admin_list = get_admin_tmp_list();
    } else {
        $admin_list = get_admin_tmp_list($_SESSION['role_id']);
    }

    foreach ($admin_list as $val) {
        $admin[$val['user_id']] = $val['user_name'];
    }

    $smarty->assign('admin_list',  $admin);

    $smarty->assign('rebuy_stats', $rebuy_stats['stats']); // 统计结果

    $smarty->assign('dst_script',  'report_forms');
    $smarty->assign('act',         $_REQUEST['act']);

    $smarty->assign('condition',    $rebuy_stats['condition']);
    $smarty->assign('page',         $rebuy_stats['page']);
    $smarty->assign('page_count',   $rebuy_stats['page_count']);
    $smarty->assign('record_count', $rebuy_stats['record_count']);
    $smarty->assign('page_list',    $rebuy_stats['page_set']);
    $smarty->assign('page_start',   $rebuy_stats['start']);
    $smarty->assign('page_end',     $rebuy_stats['end']);
    $smarty->assign('page_size',    $rebuy_stats['page_size']);

    if (isset($_REQUEST['method']) && $_REQUEST['method'] == 'Ajax') {
        $res['main'] = $smarty->fetch('rebuy_stats_data.htm');
        $res['page'] = $smarty->fetch('page_fragment.htm');

        die($json->encode($res));
    }

    $config = report_statistics_limit(1); // 报表统计范围
    if ($config['statistics_date_limit'] > 0 && $config['offset_month'] > 0) {
        $final_month = date('Y')*12 + date('m') -$config['offset_month'];
        $min_date = 'minDate:\''.floor($final_month/12).'-'.($final_month%12).'-01 00:00:00\'';
        $max_date = 'maxDate:\''.date('Y-m-t 23:59:59').'\'';

        $smarty->assign('min_date', $min_date);
        $smarty->assign('max_date', $max_date);
    }

    $res['main'] = $smarty->fetch('rebuy_stats.htm');

    die($json->encode($res));
}

/* 回购 */
elseif ($_REQUEST['act'] == 'buy_back_stats') {
    if (!admin_priv('buy_back_stats', '', false)) {
        $res = array (
            'req_msg'=>true,
            'timeout'=>2000,
            'message'=>'对不起，你没有足够的权限访问该页面！',
        );
        die($json->encode($res));
    }

    $buy_back = buy_back_stats('team');
    $role = get_role_list(1);

    $platform = array ();
    foreach ($role as $val) {
        $platform[$val['role_id']] = $val['role_name'];
    }

    $platform['total'] = '总计';

    $admin_list = get_admin_tmp_list();
    foreach ($admin_list as $val) {
        $admin[$val['user_id']] = $val['user_name'];
    }

    $smarty->assign('buy_back',    $buy_back);
    $smarty->assign('platform',    $platform);
    $smarty->assign('admin_list',  $admin);

    if (!isset($_REQUEST['start_time'], $_REQUEST['end_time'])) {
        $smarty->assign('show_all', 1);
    }

    $config = report_statistics_limit(1); // 报表统计范围
    if ($config['statistics_date_limit'] > 0 && $config['offset_month'] > 0) {
        $final_month = date('Y')*12 + date('m') -$config['offset_month'];
        $min_date = 'minDate:\''.floor($final_month/12).'-'.($final_month%12).'-01 00:00:00\'';
        $max_date = 'maxDate:\''.date('Y-m-t 23:59:59').'\'';

        $smarty->assign('min_date', $min_date);
        $smarty->assign('max_date', $max_date);
    }

    if (admin_priv('back_stats_query', '', false)) {
        $smarty->assign('back_stats_query', true);
    }

    $res['main'] = $smarty->fetch('rate_stats.htm');

    die($json->encode($res));
}

/* 平台&品牌销量 */
elseif ($_REQUEST['act'] == 'platform_brand') {
    if (!admin_priv('platform_brand', '', false))
    {
        $res = array (
            'req_msg'=>true,
            'timeout'=>2000,
            'message'=>'对不起，你没有足够的权限访问该页面！',
        );
        die($json->encode($res));
    }

    $res['switch_tag'] = true;
    $res['id']         = isset($_REQUEST['platform']) ? $_REQUEST['platform'] : 0;

    $stats_brand = stats_brand();
    $sql_select = 'SELECT brand_name,brand_id FROM '.$GLOBALS['ecs']->table('brand');
    $brand = $GLOBALS['db']->getAll($sql_select);
    $brand_list = array ();
    foreach ($brand as $val) {
        $brand_list[$val['brand_id']] = $val['brand_name'];
    }

    //$smarty->assign('day_list',   $day_list);
    $smarty->assign('brand_list', $brand_list);
    $smarty->assign('stats',      $stats_brand);
    $smarty->assign('platform_list', platform_list());

    $res['main'] = $smarty->fetch('platform_brand.htm');

    die($json->encode($res));
}

/* 销售平台下的客服列表 */
elseif ($_REQUEST['act'] == 'get_platform_admin')
{
    $platform = intval($_REQUEST['platform']);
    $admin_list = admin_list_assign($platform);

    die($json->encode($admin_list));
}

/* 顾客统计 */
elseif ($_REQUEST['act'] == 'user_stats') {
    if (admin_priv('all', '', false)) {
        $customer_type_list = get_customer_type();
        $type_list = array();
        foreach ($customer_type_list as $val) {
            $type_list[$val['type_id']] = $val['type_name'];
        }
        $type_list['total'] = '总计';
        $smarty->assign('customer_type_list', $type_list);
        $role_user_stats = user_stats2();
        $color = array('#CCFFCC','#FAF9DE','#E3EDCD','#DCE2F1','#C7EDCC','#EBEBE4');
        $smarty->assign('section', $role_user_stats['section']);
        $smarty->assign('total',   $role_user_stats['total']);
        $smarty->assign('person',  empty($_REQUEST['person']) ? 0 : $_REQUEST['person']);
        $customers_kv = customers_kv();
        $role_color = array();
        foreach ($role_user_stats['section'] as $key=>$val) {
            $role_color[$key] = $color[$customers_kv[$key]%6];
        }
        $smarty->assign('color',        $role_color);
        $smarty->assign('column_title', '部门');
        $smarty->assign('curr_title',   '顾客统计');
        if (isset($_REQUEST['person']) && $_REQUEST['person'] == 1) {
            $smarty->assign('column_title', '客服');
        }
        $result['main'] = $smarty->fetch('user_role_stats.htm');
        die($json->encode($result));
    }

    if (admin_priv('user_stats_all', '', false)) {
        $smarty->assign('selected_role', isset($_REQUEST['role_id'])?intval($_REQUEST['role_id']):0);
    } elseif (admin_priv('user_stats_part', '', false)) {
        // 部门顾客数量
        $_REQUEST['role_id'] = $_SESSION['role_id'];
        $_REQUEST['shape']   = 'table';
        $_REQUEST['get']     = isset($_REQUEST['get']) ? $_REQUEST['get'] : 'show_all';
    } elseif (admin_priv('user_stats_mine', '', false)) {
        // 个人顾客数量
    }

    $res['response_action'] = 'user_stats';
    $res['shape'] = isset($_REQUEST['shape']) ? $_REQUEST['shape'] : 'table';

    if (isset($_REQUEST['role_id']) && $_REQUEST['role_id']) {
        $admin_list = admin_sales(array($_REQUEST['role_id']));
    } else {
        $admin_list = admin_sales();
    }

    $smarty->assign('admin_list', $admin_list);
    $overview_data = user_stats();

    if (empty($_REQUEST['shape'])) {
        $_REQUEST['shape'] = 'table';
    }

    switch ($_REQUEST['shape']) {
    case 'table' :
        $stats_table = user_stats_table();
        $smarty->assign('stats_table', $stats_table);
        $res['main'] = $smarty->fetch('user_stats_table.htm');
        break;
    case 'pieChart' :
        $stats_tag  = array();
        $role_stats = array();
        foreach ($overview_data['section'] as $v) {
            $stats_tag[]  = $v['role_name'];
            $role_stats[] = array('value'=>$v['total'],'name'=>$v['role_name']);
        }

        $stats_table = user_stats_table();
        $admin_stats = array();
        foreach ($stats_table as $a) {
            $stats_tag[]   = $a['admin_name'];
            $admin_stats[] = array('value'=>$a['user_number'],'name'=>$a['admin_name']);
        }

        $res['data']    = $stats_tag;
        $res['role']    = $role_stats;
        $res['admin']   = $admin_stats;
        $res['title']   = '顾客归属分布图';
        $res['subtext'] = "顾客总数量：{$overview_data['total']}";
        break;
    case 'lineChart'   : break;
    case 'mapChart'    :
        $user_stats     = user_stats_region();
        $res['data']    = $user_stats;
        $res['title']   = '顾客全国分布图';
        $res['subtext'] = "顾客总数量：{$overview_data['total']}";
        $res['max_num'] = max_prov($user_stats) + 1000;
        break;
    case 'sourceChart' : break;
    }

    $smarty->assign('role_id',  isset($_REQUEST['role_id'])?$_REQUEST['role_id']:$_SESSION['role_id']?$_SESSION['role_id']:0);
    $smarty->assign('overview', $overview_data);

    if (isset($_REQUEST['role_id']) && $_REQUEST['shape'] == 'table' && $_REQUEST['get'] != 'show_all') {
        $res['condition'] = $smarty->fetch('user_stats_condition.htm');
    } else {
        $smarty->assign('total', $overview_data['total']);
        $others = 0;
        $member = 0;
        $middle_and_old_age = 0;
        foreach ($overview_data['section'] as $val) {
            switch ($val['role_id']) {
            case 29 : $middle_and_old_age += $val['total']; break;
            case 1  : $middle_and_old_age += $val['total']; break;
            case 9  : $member += $val['total']; break;
            case 27 : $member += $val['total']; break;
            case 28 : $member += $val['total']; break;
            case 13 : $smarty->assign('cloud', $val['total']); break;
            default : $others += $val['total'];
            }
        }

        $smarty->assign('middle_and_old_age', $middle_and_old_age);
        $smarty->assign('member', $member);
        $smarty->assign('others', $others);

        $res['main'] = $smarty->fetch('user_stats.htm');
    }

    die($json->encode($res));
}

/* 订单数据 */
elseif ($_REQUEST['act'] == 'order_data_amount')
{
     /*
     $res['response_action'] = $_REQUEST['act'];
      */
    $res['title'] = '支付方式使用率';
    $res['subtext'] = date('Y-m-01', time()).'—'.date('Y-m-d');
    if (!empty($_REQUEST['query_time']))
    {
        $res['subtext'] = date('Y.m.01', strtotime($_REQUEST['query_time'])).'-'.date('Y.m.t', strtotime($_REQUEST['query_time']));
    }

    if (!(isset($_REQUEST['query_time']) || isset($_REQUEST['platform']) || isset($_REQUEST['admin_id'])))
    {
        $smarty->assign('show', 1);
    }

    $order_data_amount = order_data_amount();

    $payment = payment_list();
    $payment_list = array();
    foreach ($payment as $val)
    {
        $payment_list[$val['pay_id']] = preg_replace("/<(.*?)>/","",$val['pay_name']);
    }

    $platform = get_role_list(1);
    $platform_list = array ();
    foreach ($platform as $val)
    {
        $platform_list[$val['role_id']] = $val['role_name'];
    }

    $admin = array ();
    $admin_list = get_admin_tmp_list();
    foreach ($admin_list as $val)
    {
        $admin[$val['user_id']] = $val['user_name'];
    }

    $total = array('amount'=>0,'shipping'=>0);
    foreach ($order_data_amount as $val)
    {
        $total['amount']   = bcadd($val['final_amount'], $total['amount'], 2);
        $total['shipping'] = bcadd($val['shipping_fee'], $total['shipping'], 2);

        $res['name'][] = $payment_list[$val['pay_id']];
        $res['data'][] = array ('value'=>$val['final_amount'],'name'=>$payment_list[$val['pay_id']]);
    }

    $smarty->assign('platform', $platform_list);
    $smarty->assign('admin_list', $admin);

    $smarty->assign('order_data_amount', $order_data_amount);
    $smarty->assign('total', $total);
    $smarty->assign('payment', $payment_list);

    $config = report_statistics_limit(1); // 报表统计范围
    if ($config['statistics_date_limit'] > 0 && $config['offset_month'] > 0) {
        $final_month = date('Y')*12 + date('m') -$config['offset_month'];
        $min_date = 'minDate:\''.floor($final_month/12).'-'.($final_month%12).'-01 00:00:00\'';

        $smarty->assign('min_date', $min_date);
    }

    $res['main'] = $smarty->fetch('order_data_amount.htm');

    die($json->encode($res));
}

/* 统计热销商品的销售情况 */
elseif ($_REQUEST['act'] == 'stats_hot_goods')
{
    $sql_select = 'SELECT goods_id,goods_sn FROM '.$GLOBALS['ecs']->table('goods');
}

/* 会员部在各平台销量占比 */
elseif ($_REQUEST['act'] == 'stats_member') {
    if (!isset($_REQUEST['start_time'], $_REQUEST['end_time'])) {
        $smarty->assign('show', 1);
    }

    $stats_res = stats_member();

    $total = array('final_amount'=>0,'order_num'=>0);
    //foreach ($stats_res as $val) {
    //    $total['final_amount'] = bcadd($total['final_amount'],$val['final_amount'],2);
    //    $total['order_num'] += $val['order_num'];
    //}

    $config = report_statistics_limit(1); // 报表统计范围
    if ($config['statistics_date_limit'] > 0 && $config['offset_month'] > 0) {
        $final_month = date('Y')*12 + date('m') -$config['offset_month'];
        $min_date = 'minDate:\''.floor($final_month/12).'-'.($final_month%12).'-01 00:00:00\'';
        $max_date = 'maxDate:\''.date('Y-m-t 23:59:59').'\'';
        $smarty->assign('min_date', $min_date);
        $smarty->assign('max_date', $max_date);
    }

    //$smarty->assign('total', $total);
    $smarty->assign('stats_res', $stats_res);
    $res['main'] = $smarty->fetch('stats_member.htm');

    die($json->encode($res));
}

/* 顾客统计 */
elseif ($_REQUEST['act'] == 'user_stream_analysis') {
    $user_num = user_region_stats();
    $smarty->assign('user_num', $user_num);
    $res['main'] = $smarty->fetch('user_stream_analysis.htm');
    die($json->encode($res));
}

/* 按功效统计会员部顾客 */
elseif ($_REQUEST['act'] == 'user_stats_effect') {
    crm_admin_priv('user_stats_effect', '当前帐号暂无权限访问该页面');
    $effects = user_stats_effect();

    // 获取每个客服的所有顾客
    $where = '';
    if (admin_priv('user_stats_part', '', false)) {
        $where = " AND u.role_id>31 ";
    } elseif (admin_priv('user_stats_effect', '', false)) {
        $where = " AND u.admin_id={$_SESSION['admin_id']}";
    }

    if ($_REQUEST['role_id']) {
        $where .= ' AND role_id='.intval($_REQUEST['role_id']);
    }

    // 统计每个客服的所有顾客数量
    $sql_select = 'SELECT COUNT(*) user_number,admin_name FROM '.$GLOBALS['ecs']->table('users').
        " u WHERE u.admin_id>0 $where AND customer_type IN (2,3,4,5,11) GROUP BY admin_id ORDER BY u.role_id";
    $result = $GLOBALS['db']->getAll($sql_select);
    $total = array();
    foreach ($result as $val){
        $total[$val['admin_name']] = $val['user_number'];
    }
    $total['共计'] = array_sum($total);

    // 获取顾客分类
    $sql = 'SELECT eff_id,eff_name FROM '.$ecs->table('effects').' ORDER BY sort ASC';
    $smarty->assign('effect', $db->getAll($sql));

    $smarty->assign('role_id', $_REQUEST['role_id']);
    $smarty->assign('role_list', get_role_customer(' AND role_id IN(33,34,35,36,37)'));
    $smarty->assign('effects', $effects);
    $smarty->assign('total', $total);

    $res['main'] = $smarty->fetch('user_stats_effect.htm');

    die($json->encode($res));
}

/* 每月新增顾客 */
elseif ($_REQUEST['act'] == 'user_stats_monthly') {

    // 统计每个客服的所有顾客数量
    $sql_select = 'SELECT COUNT(*) user_num,admin_id,role_id FROM '.$GLOBALS['ecs']->table('users').
        ' u WHERE u.admin_id>0 AND customer_type IN (2,3,4,5,11) AND role_id IN ('
        .OFFLINE_SALE.') GROUP BY admin_id ORDER BY u.role_id';
    $result = $GLOBALS['db']->getAll($sql_select);
    $total = array();
    $role_total = array();
    foreach ($result as $val){
        $total[$val['admin_id']] = $val['user_num'];
        @$role_total[$val['role_id']] += $val['user_num'];
    }

    $sql_select = 'SELECT role_name,role_id FROM '.$GLOBALS['ecs']->table('role');
    $result = $GLOBALS['db']->getAll($sql_select);
    $role_list = array();
    foreach ($result as $val) {
        $role_list[$val['role_id']] = $val['role_name'];
    }

    $user_monthly = user_stats_monthly();

    $smarty->assign('user_stats', $user_monthly['stats']);
    $smarty->assign('admin_list', $user_monthly['admin_list']);
    $smarty->assign('date_list',  $user_monthly['date_list']);
    $smarty->assign('total',      $total);
    $smarty->assign('role_total', $role_total);
    $smarty->assign('role_list',  $role_list);

    if (!isset($_REQUEST['is_ajax'])) {
        $smarty->assign('show', 1);
    }
    $res['main'] = $smarty->fetch('user_stats_monthly.htm');

    die($json->encode($res));
}

/* 顾客来源统计 */
elseif ($_REQUEST['act'] == 'user_source_stats') {

    // 来源
    $result = get_from_where();
    $aSource_list = array();
    foreach ($result as $val){
        $aSource_list[$val['from_id']] = $val['from'];
    }
    $aSource_list[0] = '未知来源';
    $smarty->assign('aSource_list', $aSource_list);

    // 团队
    $sql_select = 'SELECT role_id, role_name FROM '.$GLOBALS['ecs']->table('role');
    $aResult = $GLOBALS['db']->getAll($sql_select);
    foreach ($aResult as $val){
        $aRole_list[$val['role_id']] = $val['role_name'];
    }
    $smarty->assign('aRole_list', $aRole_list);

    // 客服
    $sql_select = 'SELECT user_id, user_name FROM '.$GLOBALS['ecs']->table('admin_user');//.' WHERE status>0 AND stats>0';
    $aResult = $GLOBALS['db']->getAll($sql_select);
    $aAdmin_list = array();
    foreach ($aResult as $val){
        $aAdmin_list[$val['user_id']] = $val['user_name'];
    }
    $smarty->assign('aAdmin_list', $aAdmin_list);

    // 来源统计
    $aStats = user_source_stats();
    $smarty->assign('aStats', $aStats['aStats']);
    $smarty->assign('aSource_used_list', $aStats['aSource_used_list']);
    $smarty->assign('aRole_stats', $aStats['aRole_stats']);

    //print_r($aStats['aStats']);
    //print_r($aStats['aRole_stats']);

    if (!isset($_REQUEST['is_ajax'])) {
        $smarty->assign('show', 1);
    }
    $res['main'] = $smarty->fetch('user_source_stats.htm');

    die($json->encode($res));
}

/* 顾客性别统计 */
else if ($_REQUEST['act'] == 'user_sex_stats') {

    $result = user_sex_stats();
    //print_r($result);

    // 团队
    $sql_select = 'SELECT role_id, role_name FROM '.$GLOBALS['ecs']->table('role');
    $aResult = $GLOBALS['db']->getAll($sql_select);
    foreach ($aResult as $val){
        $aRole_list[$val['role_id']] = $val['role_name'];
    }
    $smarty->assign('aRole_list', $aRole_list);

    // 客服
    $sql_select = 'SELECT user_id, user_name FROM '.$GLOBALS['ecs']->table('admin_user');//.' WHERE status>0 AND stats>0';
    $aResult = $GLOBALS['db']->getAll($sql_select);
    $aAdmin_list = array();
    foreach ($aResult as $val){
        $aAdmin_list[$val['user_id']] = $val['user_name'];
    }
    $smarty->assign('aAdmin_list', $aAdmin_list);

    $smarty->assign('aStats', $result['aUser_stats']);
    $smarty->assign('aRole_stats', $result['aRole_stats']);
    $smarty->assign('aSex', array('0'=>'未知', '1'=>'男', '2'=>'女'));

    //print_r($result['aUser_stats']);
    if (!isset($_REQUEST['is_ajax'])) {
        $smarty->assign('show', 1);
    }
    $res['main'] = $smarty->fetch('user_sex_stats.htm');

    die($json->encode($res));
}

/* 购买力统计 */
elseif ($_REQUEST['act'] == 'user_buy_stats') {

    // 团队
    $sql_select = 'SELECT role_id, role_name FROM '.$GLOBALS['ecs']->table('role');
    $aResult = $GLOBALS['db']->getAll($sql_select);
    foreach ($aResult as $val){
        $aRole_list[$val['role_id']] = $val['role_name'];
    }
    $smarty->assign('aRole_list', $aRole_list);

    // 客服
    $sql_select = 'SELECT user_id, user_name FROM '.$GLOBALS['ecs']->table('admin_user');//.' WHERE status>0 AND stats>0';
    $aResult = $GLOBALS['db']->getAll($sql_select);
    $aAdmin_list = array();
    foreach ($aResult as $val){
        $aAdmin_list[$val['user_id']] = $val['user_name'];
    }
    $smarty->assign('aAdmin_list', $aAdmin_list);

    $aResult = user_buy_stats();
    //print_r($aResult['aRole_stats']);
    $smarty->assign('aRole_stats', $aResult['aRole_stats']);
    $smarty->assign('aTotal', $aResult['aTotal']);
    $smarty->assign('offset', array(500,1000,1500,5000,'max_up'));

    if (!isset($_REQUEST['is_ajax'])) {
        $smarty->assign('show', 1);
    }

    $config = report_statistics_limit(1); // 报表统计范围
    if ($config['statistics_date_limit'] > 0 && $config['offset_month'] > 0) {
        $final_month = date('Y')*12 + date('m') -$config['offset_month'];
        $min_date = 'minDate:\''.floor($final_month/12).'-'.($final_month%12).'-01 00:00:00\'';
        $max_date = 'maxDate:\''.date('Y-m-t 23:59:59').'\'';

        $smarty->assign('min_date', $min_date);
        $smarty->assign('max_date', $max_date);
    }

    $res['main'] = $smarty->fetch('user_buy_stats.htm');

    die($json->encode($res));
}

/* 售前客服的销量 */
elseif ($_REQUEST['act'] == 'pre_sales') {
    // 售前客服列表
    $admin_list = get_admin(13);

    // 按每日进行统计
    if (!admin_priv('part_stats', '', false) || isset($_REQUEST['admin_id'])) {
        $start_time  = strtotime($_REQUEST['start_time']) ?: strtotime(date('Y-m-01 00:00:00'));
        $end_time    = strtotime($_REQUEST['end_time'])   ?: strtotime(date('Y-m-t 23:59:59'));
        $month_list = array();
        do {
            $month_list[] = date('Y-m-d', $start_time);
            $start_time += 24*3600;
        } while ($start_time < $end_time);
        $month_list[] = date('Y-m-d', $end_time);
        $month_list = array_flip($month_list);
        $smarty->assign('list',  $month_list); // 月列表
        $ex_group = ' GROUP BY DATE_FORMAT(FROM_UNIXTIME(i.add_time), "%Y-%m-%d")';
    } else {
        $ex_group = ' GROUP BY i.admin_id';
        $list = array();
        foreach ($admin_list as $val){
            $list[$val['user_name']] = $val['user_id'];
        }
        unset($val);
        $smarty->assign('list', $list);
    }

    $x = '<>';
    // 各售前客服总销量
    // $ex_where = "i.order_type IN (3,4,5,7) AND a.role_id=13 AND i.shipping_status{$x}3"; 包含静默订单
    $ex_where = "i.order_type IN (4,5) AND a.role_id=13 AND i.shipping_status{$x}3"; //不包含静默订单
    $total_order_stats = stats_order_amount($ex_where, $ex_group); // 计算个人销量
    $total['total_order_stats'] = stats_order_amount($ex_where); // 计算平台总销量

    // 统计每位客服的货到付款订单
    // $ex_where = " i.order_type IN (3,4,5,7) AND a.role_id=13 AND i.pay_id=3 AND i.shipping_status{$x}3"; 包含静默订单
    $ex_where = " i.order_type IN (4,5) AND a.role_id=13 AND i.pay_id=3 AND i.shipping_status{$x}3"; // 不包含静默订单
    $cod_order_stats = stats_order_amount($ex_where, $ex_group); // 计算个人销量
    $total['cod_order_stats'] = stats_order_amount($ex_where); // 计算平台总销量

    // 统计每位客服个人订单销量
    $ex_where = " i.order_type IN (4,5) AND a.role_id=13 AND i.pay_id<>3 AND i.shipping_status{$x}3";
    $order_stats = stats_order_amount($ex_where, $ex_group); // 计算个人销量
    $total['order_stats'] = stats_order_amount($ex_where); // 计算平台总销量

    // 统计每位客服的静默订单
    $ex_where = "i.order_type IN (3,7) AND a.role_id=13 AND i.pay_id<>3 AND i.shipping_status{$x}3";
    $quiesce_order_stats = stats_order_amount($ex_where, $ex_group);
    $total['quiesce_order_stats'] = stats_order_amount($ex_where);

    // 每位客服货到付款退货
    $left_join = 'LEFT JOIN '.$GLOBALS['ecs']->table('returns_order').' r ON r.order_id=i.order_id ';
    $ex_where = ' i.order_type IN (3,4,5,7) AND a.role_id=13 AND i.pay_id=3 AND i.shipping_status=4';
    $returns['cod_order_stats'] = stats_order_amount($ex_where, '', $left_join);

    // 每位客服个人订单退货
    $left_join = 'LEFT JOIN '.$GLOBALS['ecs']->table('returns_order').' r ON r.order_id=i.order_id ';
    $ex_where = ' i.order_type IN (4,5) AND a.role_id=13 AND i.pay_id<>3 AND i.shipping_status=4';
    $returns['order_stats'] = stats_order_amount($ex_where, '', $left_join);

    // 每位客服静默订单退货
    $left_join = 'LEFT JOIN '.$GLOBALS['ecs']->table('returns_order').' r ON r.order_id=i.order_id ';
    $ex_where = ' i.order_type IN (3,7) AND a.role_id=13 AND i.pay_id<>3 AND i.shipping_status=4';
    $returns['quiesce_order_stats'] = stats_order_amount($ex_where, '', $left_join);

    // 统计每位客服的退货订单
    $left_join = 'LEFT JOIN '.$GLOBALS['ecs']->table('returns_order').' r ON r.order_id=i.order_id ';
    // $ex_where = 'i.order_type IN (3,4,5,7) AND a.role_id=13 AND i.shipping_status=4'; 包含静默订单的退货销量
    $ex_where = 'i.order_type IN (4,5) AND a.role_id=13 AND i.shipping_status=4';
    if (isset($_REQUEST['admin_id'])) {
        $ex_group = ' GROUP BY DATE_FORMAT(FROM_UNIXTIME(r.return_time), "%Y-%m-%d"),i.admin_id';
    }
    $return_order = stats_order_amount($ex_where, $ex_group, $left_join);
    $total['rtn_order_stats'] = stats_order_amount($ex_where, '', $left_join);

    // 退货
    $returns['rtn_order_stats'] = stats_order_amount($ex_where, '', $left_join);

    // 售前客服列表
    $smarty->assign('admin_list',  $admin_list);
    $smarty->assign('order_stats', $order_stats);
    //$smarty->assign('jd_order_stats',    $jd_order_stats); // 京东销量
    //$smarty->assign('sp_order_stats',    $sp_order_stats); // 手拍销量
    //$smarty->assign('pmt_order_stats',   $pmt_order_stats); // 活动销量
    $smarty->assign('quiesce_order_stats', $quiesce_order_stats);
    $smarty->assign('cod_order_stats',   $cod_order_stats);
    $smarty->assign('total_order_stats', $total_order_stats);
    $smarty->assign('rtn_order_stats',   $return_order);

    $smarty->assign('total', $total);     // 销量总计
    $smarty->assign('returns', $returns); // 各类型订单退货情况

    if (admin_priv('pre_sales_query', '', false)) {
        $smarty->assign('pre_sales_query', true);
    }

    if (admin_priv('part_stats', '', false)) {
        $smarty->assign('admin_show', 1);
    }

    $config = report_statistics_limit(1); // 报表统计范围
    if ($config['statistics_date_limit'] > 0 && $config['offset_month'] > 0) {
        $final_month = date('Y')*12 + date('m') -$config['offset_month'];
        $min_date = 'minDate:\''.floor($final_month/12).'-'.($final_month%12).'-01 00:00:00\'';

        $smarty->assign('min_date', $min_date);
    }

    $res['main'] = $smarty->fetch('pre_sales_data.htm');

    if (!isset($_REQUEST['admin_id']) && !isset($_REQUEST['start_time'])) {
        $smarty->assign('data', $res['main']);
        $res['main'] = $smarty->fetch('pre_sales.htm');
    }

    die($json->encode($res));
}

/* 销售明细 */
elseif ($_REQUEST['act'] == 'sale_detail') {
    if (!admin_priv('sale_detail', '', false)) {
        $res = array (
            'timeout' => 2000,
            'req_msg' => true,
            'message' => '当前账号无权访问该页面！！',
        );
        die($json->encode($res));
    }
    $filter = isset($_REQUEST['data']) ? $json->decode($_REQUEST['data'], true) : '';
    // 销售平台
    $platform   = isset($filter['platform']) && intval($filter['platform']) ? intval($filter['platform']) : false;
    $start_time = isset($filter['startTime']) ? $filter['startTime'] : date("Y-m-01", time());
    $end_time   = isset($filter['endTime']) ? $filter['endTime'] : date("Y-m-t",  time());
    $com_where = " WHERE add_time BETWEEN ".strtotime($start_time.' 00:00:00').' AND '.strtotime($end_time.' 23:59:59');
    if ($platform) {
        $com_where .= " AND platform=$platform";
    }
    // 是否包含退货
    $shipping_symbol = '<>';
    if (isset($filter['rtn']) && $filter['rtn'] == 1) {
        $shipping_symbol = '<';
    }
    // 获取所有的支付方式
    $sql_select = 'SELECT pay_id,pay_name FROM '.
        $GLOBALS['ecs']->table('payment')." WHERE enabled=1 AND is_cod=0 ORDER BY pay_id ASC";
    $result = $GLOBALS['db']->getAll($sql_select);
    $pay_id = array();
    $pay_list = array();
    foreach ($result as $val){
        $pay_id[] = $val['pay_id'];
        $pay_list[$val['pay_id']] = str_replace('（', '（', $val['pay_name']);
    }
    $pay_id = implode(',', $pay_id);
    // 总销量
    $final_total  = array('pay' => 0, 'shipping' => 0);
    $final_number = array('pay' => 0, 'shipping' => 0);

    // 获取所有的到付方式
    $sql_select = 'SELECT shipping_id,shipping_name FROM '.
        $GLOBALS['ecs']->table('shipping')." WHERE enabled=1 AND pay_after_shipping=2 ORDER BY shipping_id ASC";
    $result = $GLOBALS['db']->getAll($sql_select);
    $shipping_id = array();
    $shipping_list = array();
    foreach ($result as $val){
        $shipping_id[] = $val['shipping_id'];
        $shipping_list[$val['shipping_id']] = $val['shipping_name'];
    }
    $shipping_id = implode(',', $shipping_id);

    $sql_select = 'SELECT SUM(final_amount) final_amount,COUNT(*) order_number,pay_id,shipping_id FROM '
        .$GLOBALS['ecs']->table('order_info');
    // 根据支付方式统计订单销量
    $ex_where = $com_where." AND pay_id IN ($pay_id) AND order_status IN (1,5) AND shipping_status{$shipping_symbol}3 GROUP BY pay_id";
    $result = $GLOBALS['db']->getAll($sql_select.$ex_where);
    $pay_final  = array();
    $pay_number = array();
    foreach ($result as $val){
        $pay_final[$val['pay_id']]  = $val['final_amount'];
        $pay_number[$val['pay_id']] = $val['order_number'];
        $final_total['pay'] = bcadd($final_total['pay'], $val['final_amount'], 2);
        $final_number['pay'] += $val['order_number'];
    }

    // 根据到付方式统计订单销量
    $ex_where = $com_where." AND shipping_id IN ($shipping_id) AND order_status IN (1,5) AND shipping_status{$shipping_symbol}3 GROUP BY shipping_id";
    $result = $GLOBALS['db']->getAll($sql_select.$ex_where);
    $shipping_final = array();
    foreach ($result as $val){
        $shipping_final[$val['shipping_id']] = $val['final_amount'];
        $shipping_number[$val['shipping_id']] = $val['order_number'];
        $final_total['shipping'] = bcadd($final_total['shipping'], $val['final_amount'], 2);
        $final_number['shipping'] += $val['order_number'];
    }

    // 计算到付与在线支付总销量
    $final_total['total'] = bcadd($final_total['pay'], $final_total['shipping'], 2);
    $final_number['total'] = $final_number['pay'] + $final_number['shipping'];

    $smarty->assign('platform',       platform_list());
    $smarty->assign('pay_list',       $pay_list);
    $smarty->assign('pay_final',      $pay_final);
    $smarty->assign('pay_number',     $pay_number);
    $smarty->assign('shipping_list',  $shipping_list);
    $smarty->assign('shipping_final', $shipping_final);
    $smarty->assign('shipping_number', $shipping_number);
    $smarty->assign('final_total',    $final_total);
    $smarty->assign('final_number',    $final_number);
    $smarty->assign('curr_title',     '销售明细');
    $res['main'] = $smarty->fetch('saless_detail_data.htm');
    if (!isset($_REQUEST['data'])) {
        $smarty->assign('data', $res['main']);
        $res['main'] = $smarty->fetch('saless_detail.htm');
    }
    die($json->encode($res));
}

/* 个人回购率 */
elseif ($_REQUEST['act'] == 'personal_repo') {
    if (admin_priv('personal_repo_all', '', false)) {
        $platform = get_role_list(' WHERE role_id IN ('.OFFLINE_SALE.') ');
        $platform_list = array ();
        foreach ($platform as $val) {
            $platform_list[$val['role_id']] = $val['role_name'];
        }
        $smarty->assign('platform',    $platform_list);
        $sql_select = 'SELECT user_name,user_id FROM '.$GLOBALS['ecs']->table('admin_user').
            ' WHERE role_id IN ('.OFFLINE_SALE.') AND status=1 AND stats=1 ';
        $admin_list = $GLOBALS['db']->getAll($sql_select);
    } elseif (admin_priv('personal_repo_part', '', false)) {
        if (admin_priv('personal_repo_trans-part', '', false)) {
            $trans_role_list = trans_part_list();
            $admin_list = admin_list_by_role($trans_role_list);
            if ($admin_list === false) {
                $admin_list = get_admin_tmp_list($_SESSION['role_id']);
            }
        } else {
            $admin_list = get_admin_tmp_list($_SESSION['role_id']);
        }
    } else {
        $_REQUEST['admin_id'] = $_SESSION['admin_id'];
        $admin[$_SESSION['admin_id']] = $_SESSION['admin_name'];
    }

    if (!empty($admin_list) && count($admin_list) > 1) {
        $admin = array ();
        foreach ($admin_list as $val) {
            $admin[$val['user_id']] = $val['user_name'];
        }

        $admin['total'] = '总计';
    }

    $smarty->assign('admin_list',  $admin);

    // 计算个人总回购率
    //$buy_back = buy_back_stats('admin_id');

    // 计算某段时间内的个人回购率
    $buy_back = repo_rate();

    $smarty->assign('buy_back', $buy_back);
    $reques_key = array_keys($_REQUEST);
    if (isset($_REQUEST['ajax'])) {
    } else {
        $smarty->assign('show_all', 1);
    }

    $config = report_statistics_limit(1); // 报表统计范围
    if ($config['statistics_date_limit'] > 0 && $config['offset_month'] > 0) {
        $final_month = date('Y')*12 + date('m') -$config['offset_month'];
        $min_date = 'minDate:\''.floor($final_month/12).'-'.($final_month%12).'-01 00:00:00\'';
        $max_date = 'maxDate:\''.date('Y-m-t 23:59:59').'\'';

        $smarty->assign('min_date', $min_date);
        $smarty->assign('max_date', $max_date);
    }

    if (admin_priv('personal_repo_query', '', false)) {
        $smarty->assign('personal_repo_query', true);
    }

    $res['main'] = $smarty->fetch('repo_rate.htm');
    die($json->encode($res));
}

/* 报表设置 */
elseif ($_REQUEST['act'] == 'statistics_date_limit') {
    if (!admin_priv('statistics_date_limit', '', false)) {
        $res = array (
            'req_msg' => true,
            'timeout' => 2000,
            'message' => '对不起，您无权访问该页面！',
        );

        die($json->encode($res));
    }

    $sql_select = 'SELECT profile_id, profile_enable, extend_config FROM '.
        $GLOBALS['ecs']->table('profiles').' WHERE profile_name="statistics"';
    $profiles = $GLOBALS['db']->getRow($sql_select);

    $sql_select = 'SELECT * FROM '.$GLOBALS['ecs']->table('profile_config')." WHERE profile_id={$profiles['profile_id']}";
    $configs = $GLOBALS['db']->getAll($sql_select);

    $smarty->assign('profiles', $profiles);
    $smarty->assign('configs',  $configs);

    $smarty->assign('curr_title', '报表设置');
    $res['main'] = $smarty->fetch('statistics_date_limit.htm');
    die($json->encode($res));
}

/* 保存报表设置 */
elseif ($_REQUEST['act'] == 'save_statistics_date_limit') {
    $res = array (
        'req_msg' => true,
        'timeout' => 2000,
    );

    if (!admin_priv('statistics_date_limit', '', false)) {
        $res['message'] = '很抱歉，您无权修改报表参数';
        die($json->encode($res));
    }

    $config = addslashes_deep($_REQUEST);
    unset($config['act']);

    foreach ($config as $key=>$val) {
        $sql_update = 'UPDATE '.$GLOBALS['ecs']->table('profile_config').
            " SET config_value=$val WHERE config_name='$key'";
        $GLOBALS['db']->query($sql_update);
    }

    $res['message'] = '报表配置已更新！';

    die($json->encode($res));
}

/* 统计个人月销量 */
elseif ($_REQUEST['act'] == 'stats_saler_month') {
    $month_start = strtotime($_REQUEST['start'].' 00:00:00');
    $month_end   = strtotime($_REQUEST['end'].' 23:59:59');

    // 权限控制
    if (!admin_priv('everyone_sales', '',false) && !admin_priv('personal_trans-part_stats', '', false)) {
        $role_id = $_SESSION['role_id'];
    } elseif (!admin_priv('all', '', false) && admin_priv('personal_trans-part_stats', '', false)) {
        if (admin_priv('salary_deal','',false)) {
        $role_id = OFFLINE_SALE;
        }else{
            $role_id = implode(',', trans_part_list());
        }
    } else {
        $role_id = OFFLINE_SALE;
    }

    // 获取目标销量
    $sales_target = get_saler_target($month_start, $month_end);

    $sql_select = 'SELECT user_id,role_id,group_id FROM '.$GLOBALS['ecs']->table('admin_user').
        " WHERE role_id IN ($role_id) AND status>0 AND stats>0 ";
    $admin_users = $GLOBALS['db']->getAll($sql_select);
    $admin_list = array ();
    $admin_info = array ();
    foreach ($admin_users as $val) {
        $admin_info[$val['user_id']] = $val;
        $admin_list[]                = $val['user_id'];
    }
    unset($val);

    $admin_list = implode(',', $admin_list);

    $sql_select = 'SELECT SUM(i.final_amount) final_amount,COUNT(*) order_num,i.admin_id,i.admin_name,r.role_describe FROM '.
        $GLOBALS['ecs']->table('order_info').' i,'.$GLOBALS['ecs']->table('role').' r WHERE i.order_status IN (1,5) AND '.
        'i.shipping_status<>3 AND i.order_type IN (4,5,6,9) AND i.platform=r.role_id AND i.add_time BETWEEN '.
        " $month_start AND $month_end AND i.admin_id IN ($admin_list) GROUP BY admin_id ORDER BY final_amount DESC";
    $res = $GLOBALS['db']->getAll($sql_select);

    //如果选择了退货时间选项
    if ($_REQUEST['r_start'] && $_REQUEST['r_end']) {
        $month_start = strtotime($_REQUEST['r_start'].' 00:00:00');
        $month_end   = strtotime($_REQUEST['r_end'].' 23:59:59');
    }

    //$sql_select = 'SELECT COUNT(*) order_num,SUM(i.final_amount) final_amount,i.admin_name,i.admin_id FROM '.
    //    $GLOBALS['ecs']->table('order_info').' i LEFT JOIN '.$GLOBALS['ecs']->table('returns_order').
    //    " r ON r.order_id=i.order_id WHERE r.return_time BETWEEN $month_start AND $month_end AND i.admin_id IN ($admin_list) ".
    //    " AND i.order_status IN (5,1) AND i.order_type IN (4,5,6) $r_where GROUP BY i.admin_id";
    //$return = $GLOBALS['db']->getAll($sql_select);
    $return = stats_returns_sales($month_start,$month_end,$admin_list);
    $return_report = return_sales_report($month_start,$admin_list); //上月单本月退货
    if ($return && $return_report) {
        foreach ($return as &$r) {
            foreach ($return_report as $rp) {
                if ($r['admin_id'] == $rp['admin_id']) {
                    $r['p_return_amount'] = $rp['final_amount'];
                    $r['p_return_num'] = $rp['num'];
                }
            }
        }
    }

    $total = array ('final_amount'=>0,'order_num'=>0);
    foreach ($res as &$val) {
        $total['target'] = bcadd($total['target'], $sales_target[$val['admin_id']]['sales_target'], 2);
        $val['target']   = $sales_target[$val['admin_id']]['sales_target'];
        $val['group_id'] = $admin_info[$val['admin_id']]['group_id'];
        $val['role_id']  = $admin_info[$val['admin_id']]['role_id'];

        // 合并退货订单数据到  $res 数组
        foreach ($return as $v) {
            if ($v['admin_id'] == $val['admin_id']) {
                $val['order_num']--;
                $val['final_amount'] = bcsub($val['final_amount'],bcsub($v['final_amount'],$v['p_return_amount']),2);
                $val['return_amount'] = $v['final_amount'];
                $val['return_count']  = $v['num'];
                $val['p_return_amount'] = $v['p_return_amount'];
                $val['p_return_num']  = $v['p_return_num'];
            }
        }
        $total['final_amount'] = bcadd($total['final_amount'], $val['final_amount'], 2);
        $total['order_num'] += $val['order_num'];
        if ($val['order_num']) {
            $val['average'] = bcdiv($val['final_amount'], $val['order_num'], 2);
        }
    }

    $ret = array ('return_amount'=>0, 'return_count'=>0);
    foreach ($return as $va) {
        $ret['return_amount'] = bcadd($ret['return_amount'], $va['final_amount'], 2);
        $ret['return_count'] += $va['num'];
        $ret['p_return_amount'] = bcadd($ret['p_return_amount'], $va['p_return_amount'], 2);
        $ret['p_return_num'] += $va['p_return_num'];
    }

    $total['average'] = bcdiv($total['final_amount'], $total['order_num'], 2);

    $total += $ret;

    $smarty->assign('start_time',  $_REQUEST['start']);
    $smarty->assign('end_time',    $_REQUEST['end']);
    $smarty->assign('r_start_time',  $_REQUEST['r_start']);
    $smarty->assign('r_end_time',    $_REQUEST['r_end']);
    $smarty->assign('sales_list',  $res);
    $smarty->assign('total',       $total);

    $saler_sales = $smarty->fetch('sales_list.htm');

    die($json->encode($saler_sales));
}

/* 个人销量统计 */
elseif ($_REQUEST['act'] == 'personal_sales_stats') {
    $today = strtotime(date('Y-m-d 23:59:59', time())); // 今天
    // 权限控制
    $trans_role_list = '';
    if (!admin_priv('all', '', false) && admin_priv('personal_trans-part_stats', '', false)) {
        if (admin_priv('salary_deal','',false)) {
            $range = ' r.role_id IN ('.OFFLINE_SALE.') AND a.stats>0 ';
        }else{
            $trans_role_list = implode(',', trans_part_list());
            $range = "r.role_id IN ($trans_role_list) AND a.stats>0";
        }
    } elseif (!admin_priv('all', '',false) && !admin_priv('finance', '', false)) {
        if (admin_priv('personal_part_stats', '', false)) {
            $admin_list = get_admin_tmp_list($_SESSION['role_id']);
            $trans_role_list  = get_depart_role($_SESSION['role_id']);
            $range = " r.role_id IN($trans_role_list) AND a.stats>0";
            //$group_list = get_group_list($_SESSION['role_id']);
            $smarty->assign('group_list', $group_list);
        } elseif (admin_priv('personal_group_stats', '', false)) {
            $range = " a.group_id={$_SESSION['group_id']} AND a.stats=1 ";
            $group_list = get_admin_list_by_group($_SESSION['group_id']);
        } else {
            $range = " a.user_id={$_SESSION['admin_id']} ";
        }
    } else {
        //if ($_SESSION['role_id']) {
        //    //$role_id = implode(',', trans_part_list());
        //    //$r_str = return_role_by_all();
        //    $range = ' r.role_id IN ('.$role_id.') AND a.stats>0 ';
        //}else{
        $range = ' r.role_id IN ('.OFFLINE_SALE.') AND a.stats>0 ';
        //}
        $smarty->assign('part_all', true);
    }

    // 部门列表
    $trans_role_list = empty($trans_role_list) ? ' AND role_type IN (1,2) ' : " AND role_type IN (1,2) AND role_id IN ($trans_role_list) ";
    $smarty->assign('role_list', get_role_list(0,true,$trans_role_list.' AND depart_id>0'));

    $role_list = array_merge(explode(',', OFFLINE_SALE), explode(',', FINANCE));
    if (empty($_SESSION['role_id']) || in_array($_SESSION['role_id'], $role_list)) {
        $sql_select = 'SELECT a.user_id,a.role_id,a.group_id,r.role_describe,r.depart_id FROM '.
            $GLOBALS['ecs']->table('admin_user').' a, '.$GLOBALS['ecs']->table('role').
            " r WHERE $range AND a.status>0 AND a.user_id<>74 AND a.role_id=r.role_id";
        $admin_users = $GLOBALS['db']->getAll($sql_select);
        $admin_list = array();
        $admin_info = array();
        foreach ($admin_users as $val) {
            $admin_info[$val['user_id']] = $val;
            $admin_list[]                = $val['user_id'];
        }
        unset($val);
        $admin_list = implode(',', $admin_list);
        $condition    = " AND admin_id IN ($admin_list) AND order_type IN (4,5,6,9) GROUP BY admin_id ";
        $yesterday    = $today -24*3600;                            // 昨天
        $before_ytday = $yesterday -24*3600;                        // 前天
        $month_start  = strtotime(date('Y-m-01 00:00:00', time())); // 本月初
        $month_end    = strtotime(date('Y-m-t 23:59:59', time()));  // 本月末

        // 当天销量
        $today_stats = stats_personal_sales($yesterday, $today, $condition);
        // 昨天销量
        $yesterday_stats = stats_personal_sales($before_ytday, $yesterday, $condition);
        // 当月销量
        $condition .= ' ORDER BY final_amount DESC';
        $month_stats = stats_personal_sales($month_start, $month_end, $condition);
        // 当月退货订单及销量
        $month_return  = stats_returns_sales($month_start, $month_end, $admin_list);
        $return_report = return_sales_report($month_start,$admin_list);
        $sales_return  = array();
        if ($return_report) {
            foreach ($return_report as $r) {
                //    //上个月下单本月退货
                   $sales_return[$r['admin_id']]['p_return_count']  = $r['num'];
                   $sales_return[$r['admin_id']]['p_return_amount'] = $r['final_amount'];
            }
        }
        foreach ($month_return as $val) {
            $sales_return[$val['admin_id']]['return_count']  = $val['num'];
            $sales_return[$val['admin_id']]['return_amount'] = $val['final_amount'];
            $sales_return[$val['admin_id']]['admin_name']    = $val['admin_name'];
        }
        // 获取当月目标销量
        $target_list = get_saler_target();
        $past_days  = date('d');
        $month_days = date('t');
        $sales_list = array ();
        unset($val);

        foreach ($month_stats as $val) {
            @$val['final_amount'] = bcsub($val['final_amount'],bcsub($sales_return[$val['admin_id']]['return_amount'],$sales_return[$val['admin_id']]['p_return_amount'],2),2);
            @$sales_list[$val['admin_id']]['month_amount'] = $val['final_amount'];
            $val['num'] -=$sales_return[$val['admin_id']]['p_return_count'];
            @$sales_list[$val['admin_id']]['month_count']  = $val['num'];
            @$sales_list[$val['admin_id']]['admin_name']   = $val['admin_name'];
            @$sales_list[$val['admin_id']]['return_count']  = $sales_return[$val['admin_id']]['return_count'];
            @$sales_list[$val['admin_id']]['return_amount'] = $sales_return[$val['admin_id']]['return_amount'];
            @$sales_list[$val['admin_id']]['p_return_count']  = $sales_return[$val['admin_id']]['p_return_count'];
            @$sales_list[$val['admin_id']]['p_return_amount'] = $sales_return[$val['admin_id']]['p_return_amount'];
            @$sales_list[$val['admin_id']]['role_id']   = $admin_info[$val['admin_id']]['role_id'];
            @$sales_list[$val['admin_id']]['depart_id']  = $admin_info[$val['admin_id']]['depart_id'];
            //@$sales_list[$val['admin_id']]['group_id']  = $admin_info[$val['admin_id']]['group_id'];
            @$sales_list[$val['admin_id']]['admin_id']  = $val['admin_id'];
            @$sales_list[$val['admin_id']]['role_code'] = $admin_info[$val['admin_id']]['role_describe'];
            $net_sales = $sales_list[$val['admin_id']]['month_amount'];
            if ($net_sales > 0) {
                $sales_list[$val['admin_id']]['forecast'] = bcmul(bcdiv($net_sales, $past_days, 2), $month_days, 2);
            }
            if (isset($target_list[$val['admin_id']]) && $target_list[$val['admin_id']]['sales_target'] > 0) {
                // 个人目标销量
                @$sales_list[$val['admin_id']]['target'] = $target_list[$val['admin_id']]['sales_target'];
                // 完成进度
                //@$sales_list[$val['admin_id']]['progress'] = sprintf('%.2f%%', bcdiv($val['final_amount'],$target_list[$val['admin_id']]['sales_target'],2)*100);
                // 今日任务
                $remain_sales = bcsub($target_list[$val['admin_id']]['sales_target'],$val['final_amount'],2);
                if ($remain_sales <= 0) {
                    $remain_sales = bcsub($sales_list[$val['admin_id']]['forecast'],$target_list[$val['admin_id']]['sales_target'],2);
                }
                $remain_days = date('t') - date('d') + 1;
                // 剩余销量
                @$sales_list[$val['admin_id']]['remain_sales'] = $remain_sales;
                @$sales_list[$val['admin_id']]['today_target'] = bcdiv($remain_sales,$remain_days, 2);
            }
            if ($val['num'] > 0) {
                $sales_list[$val['admin_id']]['PCT'] = bcdiv($val['final_amount'], $val['num'], 2);
            } else {
                $sales_list[$val['admin_id']]['PCT'] = 0;
            }

        }
        foreach ($today_stats as $val) {
            $sales_list[$val['admin_id']]['today_amount'] = $val['final_amount'];
            $sales_list[$val['admin_id']]['today_count']  = $val['num'];
            if (isset($sales_list[$val['admin_id']]['today_target']) && $val['final_amount'] >= $sales_list[$val['admin_id']]['today_target']) {
                $sales_list[$val['admin_id']]['today_target'] = '已完成';
            }
        }
        foreach ($yesterday_stats as $val) {
            $sales_list[$val['admin_id']]['yesterday_amount'] = $val['final_amount'];
            $sales_list[$val['admin_id']]['yesterday_count']  = $val['num'];
            $sales_list[$val['admin_id']]['admin_name']       = $val['admin_name'];
        }
        $tmp_sales = array ();
        //if (admin_priv('personal_stats_total', '', false)) {
        if (admin_priv('all', '', false)) {
            foreach ($sales_list as $val) {
                // 月度总销量
                @$tmp_sales['month_amount'] = bcadd($tmp_sales['month_amount'], $val['month_amount'], 2);
                @$tmp_sales['month_count'] += $val['month_count'];
                // 今日任务
                @$tmp_sales['today_target'] = bcadd($tmp_sales['today_target'], $val['today_target'], 2);
                // 当月目标
                @$tmp_sales['target'] = bcadd($tmp_sales['target'], $val['target'], 2);
                // 昨天总销量
                @$tmp_sales['yesterday_amount'] = bcadd($tmp_sales['yesterday_amount'], $val['yesterday_amount'], 2);
                @$tmp_sales['yesterday_count'] += $val['yesterday_count'];
                // 当天总销量
                @$tmp_sales['today_amount'] = bcadd($tmp_sales['today_amount'], $val['today_amount'], 2);
                @$tmp_sales['today_count'] += $val['today_count'];
                @$tmp_sales['return_amount'] = bcadd($tmp_sales['return_amount'], $val['return_amount'], 2);
                @$tmp_sales['return_count'] += $val['return_count'];
                @$tmp_sales['admin_name'] = '总计';
            }
            if (isset($tmp_sales['month_count']) && $tmp_sales['month_count'] > 0) {
                $tmp_sales['PCT'] = number_format(round($tmp_sales['month_amount']/$tmp_sales['month_count'], 2), 2);
            } else {
                $tmp_sales['PCT'] = 0;
            }
            //$tmp_sales['forecast'] = bcsub($tmp_sales['month_amount'], $tmp_sales['return_amount'], 2);
            $tmp_sales['forecast'] = bcdiv($tmp_sales['month_amount'], $past_days, 2);
            $tmp_sales['forecast'] = bcmul($tmp_sales['forecast'], $month_days, 2);
            // 总进度
            //$tmp_sales['progress'] = sprintf('%.2f%%', bcdiv($tmp_sales['month_amount'], $tmp_sales['target'], 2)*100);
            // 剩余总销量
            $tmp_sales['remain_sales'] = bcsub($tmp_sales['target'], $tmp_sales['month_amount'], 2);
            if ($tmp_sales['remain_sales'] <= 0) {
                $tmp_sales['remain_sales'] = bcsub($tmp_sales['forecast'],$tmp_sales['target'],2);
            }
        }

        //排序
        foreach ($sales_list as $key=>$val) {
            $sort_key[$key] = $val['month_amount'];
        }
        if ($sort_key) {
            array_multisort($sort_key, SORT_DESC,$sales_list);
        }
        unset($key,$val);
        //if (admin_priv('all','',false)) {
        $sales_list[] = $tmp_sales;
        //}

        $smarty->assign('sales_list', $sales_list);

        // 获取客服通话数据
        $wav_time = getSingleWavInfo(false);
        $time_info   = array();
        $number_info = array();
        foreach ($wav_time as $val) {
            $time_info[$val['user_name']]   = $val['time_info'];
            $number_info[$val['user_name']] = $val['number_info'];
            if (!isset($time_info['总计'])) {
                $time_info['总计']   = $val['time_info'];
                $number_info['总计'] = $val['number_info'];
            } else {
                $time_info['总计']   += $val['time_info'];
                $number_info['总计'] += $val['number_info'];
            }
        }
        $smarty->assign('time_info',   $time_info);   // 通话时长
        $smarty->assign('number_info', $number_info); // 通话数量

        // 统计当月团队销量
        if($_SESSION['role_id'] && $_SESSION['role_id'] != 8) {
            $condition = " AND platform={$_SESSION['role_id']} ";
        } else {
            $condition = " ";
        }
        $platform = stats_personal_sales($month_start, $month_end, $condition);
        $smarty->assign('platform', $platform[0]);
        $report_config = report_statistics_limit(1); // 报表统计范围
        if ($report_config['statistics_date_limit'] > 0 && $report_config['offset_month'] > 0) {
            $final_month = date('Y')*12 + date('m') -$report_config['offset_month'];
            $min_date = 'minDate:\''.floor($final_month/12).'-'.($final_month%12).'-01 00:00:00\'';
            $smarty->assign('min_date', $min_date);
        }

        $smarty->assign('curr_title', '个人销售统计');
        if (admin_priv('personal_stats_query', '', false)) {
            $smarty->assign('personal_stats_query', true);
        }
        $smarty->assign('depart_list',array(array('depart_id'=>7,'depart_name'=>'客服二部'),array('depart_id'=>8,'depart_name'=>'客服一部')));
        $res['main'] = $smarty->fetch('personal_sales_stats.htm');
        die($json->encode($res));
    }
}

/* 设置目标销量 */
elseif ($_REQUEST['act'] == 'set_sales_target') {
    if (!admin_priv('all', '', false) && admin_priv('role_trans-part', '', false)) {
        $role_id = $_SESSION['role_id'];
        $trans_role_list = trans_part_list();
        $trans_role = empty($trans_role_list) ? ' WHERE role_type IN (1,2) ' : ' WHERE role_type IN (1,2) AND role_id IN ('.implode(',', $trans_role_list).') ';

        $admin_list = admin_list_by_role($trans_role_list);
        $smarty->assign('role_list', get_role_list($trans_role));
    } elseif (!admin_priv('everyone_sales', '',false)) {
        $role_id = $_SESSION['role_id'];
        $admin_list = offline_admin_list($_SESSION['role_id']);
        $group_list = get_group_list($_SESSION['role_id']);

        $smarty->assign('group_list', $group_list);
    } else {
        // 部门列表
        $smarty->assign('role_list', get_role_list(' WHERE role_type IN (1,2) '));
        $admin_list = offline_admin_list('session');
        $role_id = OFFLINE_SALE;
    }

    $month = strtotime(date('Y-m'));
    $sql_select = 'SELECT admin_id,sales_target FROM '.$GLOBALS['ecs']->table('sales_target')." WHERE month_target>=$month";
    $target = $GLOBALS['db']->getAll($sql_select);

    $sales_target = array();
    foreach ($target as $val){
        $sales_target[$val['admin_id']] = $val['sales_target'];
    }


    $smarty->assign('sales_target', $sales_target);
    $smarty->assign('admin_list', $admin_list);

    $smarty->assign('curr_title', '设置目标销量');
    $res['main'] = $smarty->fetch('set_sales_target.htm');

    die($json->encode($res));
}

/* 统计档案顾客 */
elseif ($_REQUEST['act'] == 'users_data_stats') {
    $res = array('req_msg'=>true,'timeout'=>2000);
    if (!admin_priv('users_data_stats', '', false)) {
        $res['message'] = '该账号暂时无访问权限！';
        die($json->encode($res));
    }

    // 资料中的有效顾客数量
    $users_data_stats = users_data_stats();

    @$smarty->assign('current',      $users_data_stats['current']);
    @$smarty->assign('last',          $users_data_stats['last']);
    @$smarty->assign('current_month', $users_data_stats['current_month']);

    $res['req_msg'] = false;
    $res['main'] = $smarty->fetch('users_data_stats.htm');

    die($json->encode($res));
}

/* 平台销量细分 */
elseif ('sales_detail' == $_REQUEST['act']) {
    $sql_select = 'SELECT GROUP_CONCAT(role_id) FROM '.$GLOBALS['ecs']->table('role').
        ' WHERE role_describe="'.mysql_real_escape_string($_REQUEST['platform']).'" GROUP BY role_describe';
    $platform = $GLOBALS['db']->getOne($sql_select);
    $period   = trim($_REQUEST['period']);
    if (!$platform) {
        exit;
    }

    $sql_select = 'SELECT GROUP_CONCAT(role_name) FROM '.$GLOBALS['ecs']->table('role')." WHERE role_id IN ($platform)";
    $platform_name = $GLOBALS['db']->getOne($sql_select);

    $res = array (
        'title'   => $platform_name,
        'req_msg' => true
    );

    $order_source = get_order_source($platform, $period);

    if (false !== $order_source) {
        $smarty->assign('order_source', $order_source);
        $res['message'] = $smarty->fetch('order_source.htm');
    } else {
        exit;
    }

    die($json->encode($res));
}

/* 商品报表 */
elseif ($_REQUEST['act'] == 'product_sales') {
    if (admin_priv('product_all_sales', '', false)) {
    } elseif (admin_priv('product_part_sales', '', false)) {
    } elseif (admin_priv('product_group_sales', '', false)) {
    } else {
    }

    require_once('includes/cls_organizeSales.php');
    $sales = new organizeSales();

    if (!empty($_REQUEST['months']) && substr_count($_REQUEST['months'], '-')) {
        $max = date('t', strtotime($_REQUEST['months'])) +1;
        $sales->productStats('d');
    } else {
        $max = 13;
        $sales->productStats('t');
    }

    $row_list = array();
    for ($i = 1; $i < $max; $i++) {
        $row_list[$i] = $i < 10 ? "0$i" : $i;
    }

    $product_sales = product_sales_stats();

    // 商品列表
    $sql_select = 'SELECT goods_sn,goods_name FROM '.$GLOBALS['ecs']->table('goods');
    $result = $GLOBALS['db']->getAll($sql_select);

    $goods_list = array();
    foreach ($result as $val){
        $goods_list[$val['goods_sn']] = $val['goods_name'];
    }

    $smarty->assign('year_mark', $product_sales['year_mark']);
    $smarty->assign('goods_list', $goods_list);
    $smarty->assign('row_list', $row_list);
    $smarty->assign('product_sales', $product_sales['sales']);

    if (!empty($_REQUEST['months']) && substr_count($_REQUEST['months'], '-')) {
        $smarty->assign('curr_title', "产品销量（{$_REQUEST['months']}每天）");
        $res['main'] = $smarty->fetch('product_sales_days.htm');
    } else {
        $_REQUEST['months'] = !empty($_REQUEST['months']) ? $_REQUEST['months'] : date('Y');
        $smarty->assign('curr_title', "产品销量（{$_REQUEST['months']}年每月）");
        $res['main'] = $smarty->fetch('product_sales_months.htm');
    }

    die($json->encode($res));
}

/* 客服产品销量 */
elseif ($_REQUEST['act'] == 'service_sales') {
    //require_once('includes/cls_organizeSales.php');
    //$sales = new organizeSales();
    //$sales->productStats('t', ',admin_id');

    // 获取客服列表
    $admin_list = servicer_list();
    $smarty->assign('row_list', $admin_list);

    // 统计商品销售数据
    $sales_list = service_sales_list();
    $smarty->assign('sales_list', $sales_list);

    // 商品列表
    $sql_select = 'SELECT goods_sn,goods_name FROM '.$GLOBALS['ecs']->table('goods');
    $result = $GLOBALS['db']->getAll($sql_select);
    $goods_list = array();
    foreach ($result as $val){
        $goods_list[$val['goods_sn']] = $val['goods_name'];
    }

    $smarty->assign('goods_list', $goods_list);

    $smarty->assign('curr_title', '客服产品销量');

    $res['main'] = $smarty->fetch('service_sales.htm');

    die($json->encode($res));
}

/*员工销售排行*/
elseif($_REQUEST['act'] == 'work_award') {
    $year      = mysql_real_escape_string($_REQUEST['year']);
    $month_day = mysql_real_escape_string($_REQUEST['month_day']);

    if($year == ''){
        $year = date('Y');
        //list($year) = explode('-',date('Y-m',$_SERVER['REQUEST_TIME']));
    }

    if($month_day == ''){
        $month_day = date('m-d',$_SERVER['REQUEST_TIME']);
    }

    $date_time     = $year.'-'.$month_day;
    $admin_id      = intval($_REQUEST['admin_id']);
    $order_where   = ' WHERE order_status IN(1,5) AND shipping_status<>3 ';
    $service_where = ' WHERE s.valid=1 ';
    $condition     = '';
    $result        = get_admin_sql();
    $admin_id_str  = $result['admin_id_str'];
    $order_rate    = $result['admin_id_arr'];

    if(!empty($date_time)){
        $date_start = strtotime(date('Y-m-01',strtotime($date_time)));
        $date_end   = strtotime(date('Y-m-t',strtotime($date_time)));
    }else{
        $date_start = strtotime(date('Y-m-01',$_SERVER['REQUEST_TIME']));
        $date_end   = strtotime(date('Y-m-t',$_SERVER['REQUEST_TIME']));
        $date_time  = date('Y-m',$_SERVER['REQUEST_TIME']);
    }

    $service_where .= " AND s.service_time>=$date_start AND s.service_time<=$date_end AND s.admin_id IN($admin_id_str)";

    $sql_select = 'SELECT s.admin_id,COUNT(*) AS service_num FROM '.$GLOBALS['ecs']->table('service').' s '.
        $service_where.' GROUP BY admin_id';

    $service    = $GLOBALS['db']->getAll($sql_select);
    $admin_list = array();

    if($order_rate != null){
        foreach($order_rate as $val){
            $admin_list[] = $val['user_id'];
        }
    }

    if($admin_list){
        $admin_list = implode(',',$admin_list);
        $sql_select = 'SELECT COUNT(*) AS order_num,admin_id FROM '.$GLOBALS['ecs']->table('order_info').
            $order_where." AND admin_id IN($admin_list) AND add_time>=$date_start AND add_time<$date_end GROUP BY admin_id";
        $order_list = $GLOBALS['db']->getAll($sql_select);

        foreach($order_rate as &$rate){
            foreach($order_list as $key=>$val){
                if($rate['user_id'] == $val['admin_id']){
                    if(empty($val['order_sum'])){
                        $val['order_sum'] = 0;
                    }

                    if($rate['service_num'] == 0){
                        $rate['service_num'] = 1;
                    }

                    $rate['order_rate'] = ceil($val['order_num']/$rate['service_num']*100).'%';

                    $rate['order_num'] = $val['order_num'];
                    unset($val[$key]);
                }
            }
        }
    }


    if(admin_priv('all','',false)){
        $smarty->assign('platform_list',get_role());
    }

    $smarty->assign('admin_name',$_REQUEST['admin']);
    $smarty->assign('date',array('year'=>$year,'month_day'=>$month_day));
    $smarty->assign('source','order_rate');
    $smarty->assign('date_time',$date_time);
    $smarty->assign('order_rate',$order_rate);
    $smarty->assign('caption',$year.'年'.date('m').'月成单率排行');
    $smarty->assign('award_list_div',$smarty->fetch('work_award_div.htm'));


    $res['code'] = 1;
    $res['main'] = $smarty->fetch('work_award.htm');
    die($json->encode($res));
}

/*季度销售排行*/
elseif($_REQUEST['act'] == 'quarter_performance'){

    $year    = mysql_real_escape_string($_REQUEST['year']);
    $quarter = intval($_REQUEST['quarter']);
    $quarter = $quarter == 0 ? 1 : $quarter;

    $year = $year == '' ? date('Y') : $year;

    switch($quarter){
    case 1 :
        $start_time = strtotime("$year-01-01 00:00:00");
        $end_time   = strtotime("$year-03-01 00:00:00");
        break;
    case 2 :
        $start_time = strtotime("$year-03-01 00:00:00");
        $end_time   = strtotime("$year-06-01 00:00:00");
        break;
    case 3 :
        $start_time = strtotime("$year-06-01 00:00:00");
        $end_time   = strtotime("$year-09-01 00:00:00");
        break;
    case 4 :
        $start_time = strtotime("$year-10-01 00:00:00");
        $end_time   = strtotime(($year+1).'-01-01 00:00:00');
        break;
    }

    $sale_performance = get_sale_performance($start_time,$end_time);

    if(admin_priv('all','',false)){
        $smarty->assign('platform_list',get_role());
    }

    $smarty->assign('admin_name',$_REQUEST['admin']);
    $smarty->assign('caption',$year.'年第'.$quarter.'季度销售排行');
    $smarty->assign('source','quarter_performance');
    $smarty->assign('date',array('year'=>$year));
    $smarty->assign('quarter',$quarter);
    $smarty->assign('sale_performance',$sale_performance);
    $smarty->assign('award_list_div',$smarty->fetch('work_award_div.htm'));

    $res['code'] = 1;
    $res['main'] = $smarty->fetch('work_award.htm');
    die($json->encode($res));
}

/*年度销售*/
elseif($_REQUEST['act'] == 'year_performance') {
    $year   = mysql_real_escape_string($_REQUEST['year']);
    $year   = $year == '' ? date('Y') : $year;

    $start_time = strtotime($year.'-01-01 00:00:00');
    $end_time   = strtotime(($year+1).'-01-01 00:00:00');

    $sale_performance = get_sale_performance($start_time,$end_time);

    if(admin_priv('all','',false)){
        $smarty->assign('platform_list',get_role());
    }

    $smarty->assign('admin_name',$_REQUEST['admin']);
    $smarty->assign('caption',$year.'年度销售排行');
    $smarty->assign('source','year_performance');
    $smarty->assign('date',array('year'=>$year));
    $smarty->assign('sale_performance',$sale_performance);
    $smarty->assign('award_list_div',$smarty->fetch('work_award_div.htm'));

    $res['code'] = 1;
    $res['main'] = $smarty->fetch('work_award.htm');
    die($json->encode($res));
}

/* 销量统计 退货按照订单添加时间统计 */
elseif ($_REQUEST['act'] == 'orders_statistics') {
    if (admin_priv('nature_stats_all', '', false)) {
        $_REQUEST['target'] = isset($_REQUEST['target']) ? $_REQUEST['target'] : 'platform_stats';
    } elseif (admin_priv('platform_stats', '', false) || admin_priv('nature_trans-part', '', false)) {
        $_REQUEST['target'] = 'platform_stats';
        $smarty->assign('platform_stats', 1);
    } elseif (admin_priv('self_stats', '', false)) {
        $_REQUEST['target'] = 'self_stats';
        $smarty->assign('self_stats', 1);
    }

    $nature_stats = nature_stats();
    $stats_all = stats_all();
    $stats_all_return = stats_month_return();

    // 获取当月的日期
    $date_limit = date('d',    time()) +1;
    $year_month = date('Y-m-', time());
    for ($i = $date_limit; $i > 0; $i--) {
        $date_list[] = $i < 10 ? $year_month.'0'.$i : $year_month.$i;
    }

    $smarty->assign('date_list', $date_list);

    $smarty->assign('curr_title', '各平台销量');

    $config = report_statistics_limit(1); // 报表统计范围
    if ($config['statistics_date_limit'] > 0 && $config['offset_month'] > 0) {
        $final_month = date('Y')*12 + date('m') -$config['offset_month'];
        $min_date = 'minDate:\''.floor($final_month/12).'-'.($final_month%12).'-01 00:00:00\'';
        $max_date = 'maxDate:\''.date('Y-m-t 23:59:59').'\'';

        $smarty->assign('min_date', $min_date);
        $smarty->assign('max_date', $max_date);
    }

    $smarty->assign('nature_stats', $nature_stats['stats_list']);
    $smarty->assign('start_time',   $nature_stats['start_time']);
    $smarty->assign('end_time',     $nature_stats['end_time']);
    $smarty->assign('stats',        $stats_all);
    $smarty->assign('stats_return', $stats_all_return);
    $smarty->assign('target',       @$_REQUEST['target']);

    $smarty->assign('tag', isset($_REQUEST['tag']) ? $_REQUEST['tag'] : 0);

    $res['act']  = 'person_style';
    $res['main'] = $smarty->fetch('month_nature_stats.htm');
    die($json->encode($res));
}

/* 服务统计 */
elseif ($_REQUEST['act'] == 'service_stats') {
    $smarty->assign('role_id',$_REQUEST['role_id']);

    $report_time = report_time_list();
    extract($report_time);
    $today      = service_stats($today_start_time, $today_end_time); // 当日
    $yesterday  = service_stats($yesterday_start_time, $yesterday_end_time); // 昨日
    $month      = service_stats($month_start_time, $month_end_time); // 当月

    if ($_REQUEST['start_time'] && $_REQUEST['end_time']) {
        $start_time = strtotime(date('Y-m-d 00:00:00',strtotime($_REQUEST['start_time'])));
        $end_time = strtotime(date('Y-m-d 23:59:59',strtotime($_REQUEST['end_time'])));
        if ($end_time<$start_time) {
            $start_time=$start_time^$end_time;
            $end_time=$end_time^$start_time;
            $start_time=$start_time^$end_time;
        }
        $last_month = service_stats($start_time,$end_time);
        $smarty->assign('search',true);
    }else{
        $last_month = service_stats($last_month_start_time, $last_month_end_time);
    }

    $final = final_report($today,$yesterday,$month,$last_month);
    $smarty->assign('final', $final);
    $smarty->assign('role_list', get_role_customer(' AND role_id IN('.MEMBER_SALE.')'));
    $smarty->assign('curr_title', '通话统计');
    $res['main'] = $smarty->fetch('service_stats.htm');
    die($json->encode($res));
}
//电话接通率
elseif('phone_connect_stats' == $_REQUEST['act']){
    $smarty->assign('role_id',$_REQUEST['role_id']);
    $report_time = report_time_list();
    extract($report_time);
    $today      = connect_stats($today_start_time, $today_end_time); // 当日
    $yesterday  = connect_stats($yesterday_start_time, $yesterday_end_time); // 昨日
    $month      = $today; // 当月
    $last_month = $today; // 上个月

    //$yesterday  = connect_stats($yesterday_start_time, $yesterday_end_time); // 昨日
    //$month      = connect_stats($month_start_time, $month_end_time); // 当月
    //$last_month = connect_stats($last_month_start_time, $month_end_time); // 上个月

    $final = call_final_report($today,$yesterday,$month,$last_month);
    $smarty->assign('final', $final);

    $smarty->assign('role_list', get_role_customer(' AND role_id IN('.MEMBER_SALE.')'));
    $smarty->assign('curr_title', '电话接通率');
    $res['main'] = $smarty->fetch('connect_stats.htm');
    die($json->encode($res));
}

//签单成功率
elseif('order_success_stats' == $_REQUEST['act']){
    $report_time = report_time_list();
    extract($report_time);
    $month      = order_success_stats($month_start_time, $month_end_time); // 当月
    $last_month = order_success_stats($last_month_start_time, $last_month_end_time);
    if ($_REQUEST['start_time'] && $_REQUEST['end_time']) {
        $customer = order_success_stats(strtotime($_REQUEST['start_time']),strtotime($_REQUEST['end_time']));
        $smarty->assign('search',true);
    }
    $final = final_order_success($month,$last_month,$customer);
    $smarty->assign('final', $final);
    $smarty->assign('curr_title', '订单签收率');
    $smarty->assign('role_id', $_REQUEST['role_id']);
    $smarty->assign('role_list', get_role_customer(' AND role_id IN('.KEFU.','.KEFU2.')'));
    $res['main'] = $smarty->fetch('order_success_stats.htm');
    die($json->encode($res));
}
//新顾客跟进率
elseif('user_service_stats' == $_REQUEST['act']){
    $start_time = strtotime($_REQUEST['start_time'] ? $_REQUEST['start_time'] : date('Y-m-01'));
    $end_time   = strtotime($_REQUEST['end_time'] ? $_REQUEST['end_time'] : date('Y-m-t'));
    $role_id    = intval($_REQUEST['role_id']);

    $smarty->assign('start_time',date('Y-m-d',$start_time));
    $smarty->assign('end_time',date('Y-m-d',$end_time));
    $smarty->assign('role_id',$role_id);

    $sql = 'SELECT COUNT(*) total,admin_id FROM '.$GLOBALS['ecs']->table('users');
    $where = " WHERE customer_type NOT IN(5,6,21) AND assign_time BETWEEN $start_time AND $end_time ";
    $role_str = KEFU.','.KEFU2;
    if ($role_id) {
        $where .= " AND role_id=$role_id ";
        $role_list = array($role_id);
    }else{
        $role_list = explode(',',$role_str);
    }
    $admin_list = admin_list_by_role($role_list);
    $assigned = $GLOBALS['db']->getAll($sql.$where.' GROUP BY admin_id');    //分配顾客数量
    $where .= "AND service_time BETWEEN $start_time AND $end_time AND service_time>assign_time";
    $serviced = $GLOBALS['db']->getAll($sql.$where.' GROUP BY admin_id');    //跟进过的顾客数量 
    if ($assigned) {
        foreach ($assigned as &$v) {
            foreach ($serviced as $k=>$s) {
                if ($v['admin_id'] == $s['admin_id']) {
                    $v['service_num'] = $s['total']; 
                    $v['service_stats'] = round($v['service_num']/$v['total']*100).'%';
                    unset($serviced[$k]);
                } 
            }
        }
        unset($v,$k);
        foreach ($admin_list as &$v) {
            foreach ($assigned as $t) {
                if ($v['user_id'] == $t['admin_id']) {
                    $v = array_merge($v,$t);
                    $total['total'] += $v['total'];
                    $total['service_num'] += $v['service_num'];
                }
            }
        }
        if ($total['total']) {
            $total['service_stats'] = round($total['service_num']/$total['total']*100).'%';
            $total['user_name'] = '总计';
        }
        $admin_list['total'] = $total;
    }

    $smarty->assign('list',$admin_list);
    $smarty->assign('curr_title', '新顾客跟进率');
    $smarty->assign('role_id', $_REQUEST['role_id']);
    $smarty->assign('role_list', get_role_customer(" AND role_id IN($role_str)"));
    $res['main'] = $smarty->fetch('user_service_stats.htm');
    die($json->encode($res));
}

//顾客分析之有无QQ，微信，旺旺
elseif ('user_analyse' == $_REQUEST['act']){
    $smarty->assign('role_id',$_REQUEST['role_id']);
    $list = analyse_user_contact();
    $smarty->assign('role_list', get_role_customer(' AND role_id IN('.MEMBER_SALE.')'));
    $smarty->assign('list',$list);
    $res['main'] = $smarty->fetch('user_analyse_stats.htm');
    die($json->encode($res));
}
//顾客分析图表
elseif ('user_analyse_report' == $_REQUEST['act']){

    $role_list = get_role_list('',true,' AND role_id IN('.SALE.')');
    $sel_opt   = array('不限','有','无');
    $item_list = array('所有','QQ','微信');

    $smarty->assign('admin_list',     get_admin_tmp_list());
    $smarty->assign('customer_type',  get_customer_type('',true));
    $smarty->assign('sel_opt',        $sel_opt);
    $smarty->assign('item_list',      $item_list);
    $smarty->assign('role_list',      $role_list);
    $res['main'] = $smarty->fetch('user_analyse.htm');
    die($json->encode($res));
}

elseif('act_user_analyse' == $_REQUEST['act']){
    $analyse_result = analyse_user('contact');
    die($json->encode($analyse_result));
}
//成交方式
elseif($_REQUEST['act'] == 'deal_method_report'){
    $smarty->assign('curr_title','成交方式统计');
    $smarty->assign('final',deal_order_report());
    $smarty->assign('role_list',get_role_list('','role_id,role_name'," AND depart_id IN(7,8)"));
    $smarty->assign('deal_method',get_deal_method());
    if (isset($_REQUEST['sch'])) {
        $res['response_action'] = 'search_service';
        $res['main'] = $smarty->fetch('deal_method_report_div.htm'); 
    }else{
        $smarty->assign('role_list',get_role_list('','role_id,role_name'," AND depart_id IN(7,8)"));
        $smarty->assign('deal_method_div',$smarty->fetch('deal_method_report_div.htm'));
        $res['main'] = $smarty->fetch('deal_method_report.htm'); 
    }
    die($json->encode($res));
}

//QQ,微信添加统计
elseif($_REQUEST['act'] == 'add_contact_report'){
    $report_time = report_time_list();
    $role_id     = isset($_REQUEST['role_id']) && $_REQUEST['role_id'] ? intval($_REQUEST['role_id']) : KEFU.','.KEFU2 ;
    $admin_list  = admin_for_report(" AND role_id IN($role_id)");
    $yesterday   = add_contact_report($report_time['yesterday_start_time'],$report_time['yesterday_end_time']);
    $today       = add_contact_report($report_time['today_start_time'],$report_time['today_end_time']);
    $month       = add_contact_report($report_time['month_start_time'],$report_time['month_end_time']);
    $last_month  = add_contact_report($report_time['last_month_start_time'],$report_time['last_month_end_time']);
    $year        = add_contact_report(strtotime(date('2012-01-01 00:00')),$_SERVER['REQUEST_TIME']);

    $total = array(
        'admin_name' => '总计',
    );
    $keys = array('yesterday','today','month','last_month','year');
    if (isset($_REQUEST['start_time']) && !empty($_REQUEST['start_time']) && !empty($_REQUEST['end_time'])) {
        $start_time = strtotime($_REQUEST['start_time'].' 00:00:00');
        $end_time   = strtotime($_REQUEST['end_time'].' 23:59:59');
        $customer   = add_contact_report($start_time,$end_time);
        $smarty->assign('search',true);
        $keys[] = 'customer';
    }
    foreach ($admin_list as &$v) {
        foreach ($keys as $k) {
            if (!empty($$k)) {
                $ar = $$k;
                $v[$k] = $ar[$v['admin_id']];
                $total[$k]['qq'] += $ar[$v['admin_id']]['qq'];
                $total[$k]['wechat'] += $ar[$v['admin_id']]['wechat'];
                $total[$k]['access'] += $ar[$v['admin_id']]['access'];
            }
        }
    }
    array_push($admin_list,$total);
    $smarty->assign('admin_list',$admin_list);
    $smarty->assign('role_list', get_role_customer(' AND role_id IN('.KEFU.','.KEFU2.')'));
    $smarty->assign('curr_title','QQ，微信添加统计');
    $res['main'] = $smarty->fetch('add_contact_report.htm');
    die($json->encode($res));
}
//平台核心数据报表
elseif($_REQUEST['act']=='spread_report'){
    if (!empty($_REQUEST['start_time']) && !empty($_REQUEST['end_time'])) {
        $smarty->assign('start_time',$_REQUEST['start_time']);
        $smarty->assign('end_time',$_REQUEST['end_time']);
        $start_time = strtotime($_REQUEST['start_time']);
        $end_time = strtotime($_REQUEST['end_time']);
        $sch = true;
    }else{
        $start_time = strtotime(date('Y-m-d 00:00:00'));
        $end_time = strtotime(date('Y-m-d 23:59:59'));
        $smarty->assign('start_time',date('Y-m-01'));
        $smarty->assign('end_time',date('Y-m-t'));
    }
    $where = " WHERE report_time BETWEEN $start_time AND $end_time ";

    if ($_REQUEST['platform']) {
        $platform = intval($_REQUEST['platform']);
        $smarty->assign('platform',$platform);
        $where .= " AND platform=$platform ";
    }

    $online_store = ONLINE_STORE;
    if (admin_priv('all','',false) || admin_priv('spread_all','',false)) {
        $where .= " AND platform IN($online_store) ";
    }elseif(admin_priv('spread_part','',false)){
        $platform = trans_part_list();
        $platform = implode(',',$platform);
        $where .= " AND platform IN($platform) ";
    }else{
        $where .= " AND platform={$_SESSION['role_id']}";
    }

    if (isset($_REQUEST['sel_type']) && $_REQUEST['sel_type']) {
        $sql = "SELECT r.role_name,sum(pc_spread_uv) pc_spread_uv,sum(pc_order_num) pc_order_num,sum(pc_sale) pc_sale,sum(m_spread_uv) m_spread_uv,sum(m_order_num) m_order_num,sum(m_sale) m_sale,sum(activity_sale) activity_sale,sum(scalping_num) scalping_num,sum(scalping_amount) scalping_amount,sum(ad_amount) ad_amount,'-' report_time"
            .' FROM '.$GLOBALS['ecs']->table('spread').' s LEFT JOIN '.$GLOBALS['ecs']->table('role').' r ON s.platform=r.role_id'
            ." $where GROUP BY s.platform ORDER BY report_time DESC";
        $smarty->assign('sel_type',$_REQUEST['sel_type']);
    }else{
        $sql = 'SELECT r.role_name,pc_spread_uv,pc_order_num,pc_sale,m_spread_uv,m_order_num,m_sale,activity_sale,scalping_num,scalping_amount,ad_amount,report_time'
            .' FROM '.$GLOBALS['ecs']->table('spread').' s LEFT JOIN '.$GLOBALS['ecs']->table('role').' r ON s.platform=r.role_id'
            ." $where ORDER BY report_time DESC";    
    }
    $data = $GLOBALS['db']->getAll($sql);
    if ($data) {
        //if ($total_data) {
        //    $data = array_merge($total_data,$data);
        //}
        $total = array(
            'role_name'=>'总计',
        );
        foreach ($data as &$v) {
            $v['add_time']             = $v['report_time'] != '-'?date('Y-m-d',$v['report_time']):'-';
            $v['total_sale'] = bcadd($v['pc_sale'],$v['m_sale'],2);
            $v['total_spread_uv']             = $v['pc_spread_uv']+$v['m_spread_uv'];
            $v['total_order_num']      = $v['m_order_num']+$v['m_order_num'];
            $v['total_transformation'] = bcdiv($v['total_order_num'],$v['total_spread_uv'],4)*100;
            $v['pc_transformation']    = bcdiv($v['pc_order_num'],$v['pc_spread_uv'],4)*100;
            $v['m_transformation']     = bcdiv($v['m_order_num'],$v['m_spread_uv'],2)*100;
            //if ($v['add_time'] == '-') {
            //    $v['add_time'] = '总合';
            //    continue;
            //}
            $total['pc_spread_uv']    += $v['pc_spread_uv'];
            $total['pc_order_num']    += $v['pc_order_num'];
            $total['pc_sale']         = bcadd($v['pc_sale'],$total['pc_sale'],2);
            $total['m_spread_uv']     += $v['m_spread_uv'];
            $total['m_order_num']     += $v['m_order_num'];
            $total['m_sale']          = bcadd($v['m_sale'],$total['m_sale'],2);
            $total['scalping_num']    += $v['scalping_num'];
            $total['scalping_amount'] = bcadd($v['scalping_amount'],$total['scalping_amount'],2);
            $total['ad_amount']       = bcadd($v['ad_amount'],$total['ad_amount'],2);
            $total['activity_sale']       = bcadd($v['activity_sale'],$total['activity_sale'],2);
        }
        $total['total_sale'] = bcadd($total['pc_sale'],$total['m_sale'],2);
        $total['total_spread_uv'] = $total['pc_spread_uv']+$total['m_spread_uv'];
        $total['total_order_num'] = $total['pc_order_num']+$total['m_order_num'];
        $total['total_transformation'] = bcdiv($total['total_order_num'],$total['total_spread_uv'],2)*100;
        $total['pc_transformation'] = bcdiv($total['pc_order_num'],$total['pc_spread_uv'],2)*100;
        $total['m_transformation'] = bcdiv($total['m_order_num'],$total['m_spread_uv'],2)*100;
        array_push($data,$total);
    }
    $role_list = get_role(" role_id IN($online_store)");
    $smarty->assign('data',$data);
    $smarty->assign('role_list',$role_list);
    $res['main'] = $smarty->fetch('spread_report.htm');
    die($json->encode($res));
}

//顾客评价
elseif($_REQUEST['act'] == 'evaluate'){
    $c = new TopClient;
    $c->appkey = $appkey;
    $c->secretKey = $secret;
    $req = new TmallTraderateFeedsGetRequest;
    $req->setChildTradeId("11111111111");
    $resp = $c->execute($req, $sessionKey);

}
//快递费用报表
elseif($_REQUEST['act'] == 'express_fee_report'){
    $shipping_list = get_shipping_list('');
    $role_list     = get_role(' role_id IN('.ONLINE_STORE.','.OFFLINE_SALE.') AND role_type>0 ');
    $depart_list   = get_department(' AND depart_id IN('.SALE_DEPART.')');
    $shipping_list = get_shipping_list('');
    $list = express_fee_report($shipping_list);

    $smarty->assign('list',$list);
    $smarty->assign('shipping_list',$shipping_list);
    $smarty->assign('depart_list',$depart_list);
    $smarty->assign('role_list',$role_list);
    $smarty->assign('shipping_list',$shipping_list);
    $res['main'] = $smarty->fetch('express_fee_report.htm');
    die($json->encode($res));
}

elseif($_REQUEST['act']=='set_main_sale'){
    $role_list     = get_role(' role_id IN('.ONLINE_STORE.','.OFFLINE_SALE.') AND role_type>0 ');
    $depart_list   = get_department(' AND depart_id IN('.SALE_DEPART.')');

    $smarty->assign('main_sale_list',main_sale_list());
    $smarty->assign('depart_list',$depart_list);
    $smarty->assign('role_list',$role_list);
    $res['main'] = $smarty->fetch('set_main_sale.htm');
    die($json->encode($res));
}

//保存主推产品
elseif($_REQUEST['act']=='save_main_sale_done'){
    $goods_id = mysql_real_escape_string($_GET['data']);
    if ($goods_id) {
        $sql = 'SELECT goods_id FROM '.$GLOBALS['ecs']->table('goods')." WHERE goods_sn IN($goods_id)";
        $goods_id = $GLOBALS['db']->getCol($sql);
        foreach ($goods_id as $v) {
            $sql = 'REPLACE INTO '.$GLOBALS['ecs']->table('main_sale')
                .'(role_id,goods_id,add_admin,add_time,main_key)VALUES('
                ."{$_SESSION['role_id']},$v,{$_SESSION['admin_id']},{$_SERVER['REQUEST_TIME']},{$_SESSION['role_id']}$v)";
            $GLOBALS['db']->query($sql);
        }
        $res = crm_msg('保存成功');
    }else{
        $res = crm_msg('保存失败');
    }
    die($json->encode($res));
}
//主推产品报表
elseif($_REQUEST['act']=='main_sale_report'){
    $role_list     = get_role(' role_id IN('.ONLINE_STORE.') AND role_type>0 ');
    $depart_list   = get_department(' AND depart_id IN('.SALE_DEPART.')');
    $role_id   = intval($_REQUEST['role_id']);
    $depart_id = intval($_REQUEST['depart_id']);
    $where     = ' WHERE 1';
    if ($depart_id) {
        $where .= " AND r.depart_id=$depart_id";
    }
    if ($role_id) {
        $where .= " AND r.role_id=$role_id";
    }
    $sql = 'SELECT m.goods_id FROM '.$GLOBALS['ecs']->table('main_sale').' m LEFT JOIN '.$GLOBALS['ecs']->table('role')
        .' r ON r.role_id=m.role_id'.$where;
    $goods_id = $GLOBALS['db']->getCol($sql);
    if ($goods_id) {
        $_REQUEST['goods_id'] = implode(',',$goods_id);
    }
    //$single_sale = sales_rank();
    $start_time = isset($_REQUEST['start_time'])?strtotime($_REQUEST['start_time']):$_SERVER['REQUEST'];
    $end_time = isset($_REQUEST['end_time'])?strtotime($_REQUEST['end_time']):$_SERVER['REQUEST_TIME'];
    for ($i=date('d',$end_time); $i>=1; $i--) {
        $list[]['date'] = date("Y-m-$i",$end_time);
    }
    $smarty->assign('list',$list);
    $smarty->assign('main_sale_report_div',$smarty->fetch('main_sale_report_div.htm'));

    $smarty->assign('role_list',$role_list);
    $smarty->assign('depart_list',$depart_list);
    $res['main'] = $smarty->fetch('main_sale_report.htm');
    die($json->encode($res));
}
elseif($_REQUEST['act']=='del_main_sale'){
    $role_id  = intval($_REQUEST['role_id']);
    $goods_id = intval($_REQUEST['goods_id']);
    $sql = 'DELETE FROM'.$GLOBALS['ecs']->table('main_sale')." WHERE main_key='$role_id$goods_id'";
    $code = $GLOBALS['db']->query($sql);
    if ($code) {
    $res = crm_msg('删除成功',$code);
    $res['id'] = "$role_id$goods_id";
    }else{
        $res = crm_msg('删除失败');
    }
    die($json->encode($res));
}

/*------------------------------------------------------ */
//--排行统计需要的函数
/*------------------------------------------------------ */
/**
 * 取得销售排行数据信息
 * @param   bool  $is_pagination  是否分页
 * @return  array   销售排行数据
 */
function sales_rank ($is_pagination = true) {
    $condition = '';

    $filter['end_time']   = empty($_REQUEST['end_time'])   ? '' : $_REQUEST['end_time'];
    $filter['start_time'] = empty($_REQUEST['start_time']) ? '' : $_REQUEST['start_time'];
    $filter['brand_id']   = empty($_REQUEST['brand_id']) ? '' : intval($_REQUEST['brand_id']);
    $filter['sort_by']    = empty($_REQUEST['sort_by'])    ? 'goods_num' : trim($_REQUEST['sort_by']);
    $filter['sort_order'] = empty($_REQUEST['sort_order']) ? 'DESC'      : trim($_REQUEST['sort_order']);
    $filter['platform']   = empty($_REQUEST['platform'])   ? '' : intval($_REQUEST['platform']);
    $filter['depart_id']  = empty($_REQUEST['depart_id'])   ? '' : intval($_REQUEST['depart_id']);

    $config = report_statistics_limit(1); // 报表统计范围
    if ($config['statistics_date_limit'] > 0 && $config['offset_month'] > 0 && (empty($filter['start_time']) || empty($filter['end_time']))) {
        $filter['start_time'] = date('Y-m-01');
        $filter['end_time']   = date('Y-m-t');
    }


    // 收集查询条件
    foreach ($filter as $key=>$val) {
        if (!empty($val)) {
            $condition .= "&$key=$val";
        }
    }

    $where = ' WHERE og.order_id=oi.order_id AND oi.order_status IN (1,5) AND oi.shipping_status IN (4,1,2)';

    if ($_REQUEST['goods_id']) {
        $where .= " AND og.goods_id IN({$_REQUEST['goods_id']}) ";
    }

    if ($filter['start_time'] && $filter['end_time']) {
        $where .= ' AND oi.add_time BETWEEN '.strtotime($filter['start_time'].' 00:00:00').
            ' AND '.strtotime($filter['end_time'].' 23:59:59');
    }

    if (admin_priv('all', '', false) || admin_priv('finance', '', false)) {
        if ($filter['platform']) {
            $where .= " AND oi.platform={$filter['platform']} ";
        }
    } elseif (admin_priv('rank_list_part', '', false)) {
        if (empty($_SESSION['role_id']) && empty($filter['platform'])) {
            $where .= ' AND oi.platform IN ('.ONLINE_STORE.')';
        } elseif (!empty($filter['platform'])) {
            $where .= " AND oi.platform={$filter['platform']} ";
        } else {
            $where .= " AND oi.platform={$_SESSION['role_id']} ";
        }
    } else {
        $where .= " AND oi.admin_id={$_SESSION['admin_id']} ";
    }

    //按部门查看产品销量
    if ($filter['depart_id']) {
        $role_condition = "depart_id={$filter['depart_id']}";
        $role_list = get_role($role_condition);
        $list = array();
        foreach ($role_list as &$v) {
            $list[] = $v['role_id'];
        }
        $where .= " AND oi.platform IN(".implode(',',$list).') ';
    }

    if (isset($_REQUEST['package'])) {
        $order_type = ' AND og.goods_sn LIKE "%\_%" ';
    } else {
        $order_type = ' AND CONCAT("", og.goods_sn *1)=og.goods_sn ';
        !empty($filter['brand_id']) && $order_type .= " AND og.brand_id={$filter['brand_id']} ";
        $gift_where = ' AND og.is_gift=1 ';
    }

    $sql = 'SELECT og.goods_id,og.goods_sn,og.goods_name,oi.order_status,SUM(og.goods_number) goods_num,'.
        'SUM(og.goods_number*og.goods_price) turnover FROM '.$GLOBALS['ecs']->table('order_goods').' og, '.
        $GLOBALS['ecs']->table('order_info')." oi $where %s GROUP BY og.goods_sn ".
        ' ORDER BY ' . $filter['sort_by'] . ' ' . $filter['sort_order'];
    // 日常销量
    $sales_order_data = $GLOBALS['db']->getAll(sprintf($sql, $order_type." AND og.is_gift<>1 AND oi.order_type IN (2,3,4,6,100,9)"));

    if ($gift_where) {
        $gift_arr = $GLOBALS['db']->getAll(sprintf($sql, $order_type." AND oi.order_type IN (2,3,4,6,100,10) $gift_where"));
        if ($gift_arr) {
            foreach ($gift_arr as $g) {
                $gifts[$g['goods_sn']] = $g;
            }
        }
    }

    // 活动销量
    $res = $GLOBALS['db']->getAll(sprintf($sql, $order_type." AND oi.order_type IN (5,7) "));
    $promotions = array();
    foreach($res as $val) {
        $promotions[$val['goods_sn']] = $val;
    }
    foreach ($sales_order_data as &$val) {
        // 活动数量
        @$val['promotion_num'] = $promotions[$val['goods_sn']]['goods_num'];
        @$val['promotion_amt'] = $promotions[$val['goods_sn']]['turnover'];

        // 赠品数量
        @$val['gift_num'] = $gifts[$val['goods_sn']]['goods_num'];
        @$val['gift_amt'] = $gifts[$val['goods_sn']]['turnover'];

        // 订单总计
        @$val['turnover']  = bcadd($val['turnover'], $promotions[$val['goods_sn']]['turnover'], 3);
        //@$val['turnover']  = bcadd($val['turnover'], $gifts[$val['goods_sn']]['turnover'], 2);
        //@$val['total_num'] = $val['goods_num'] + $promotions[$val['goods_sn']]['goods_num'] + $gifts[$val['goods_sn']]['goods_num'];
        @$val['total_num'] = $val['goods_num']+$val['gift_num'];
    }

    $i = 1;
    foreach ($sales_order_data as $key=>$item) {
        $sales_order_data[$key]['wvera_price'] = $item['total_num']?bcdiv($item['turnover'],$item['total_num'],2):0;
        $sales_order_data[$key]['short_name']  = sub_str($item['goods_name'], 30, true);
        $sales_order_data[$key]['goods_num']  = $item['goods_num']+$item['gitf_num'];
        $sales_order_data[$key]['turnover']    = $item['turnover'];
        $sales_order_data[$key]['index']       = $i++;
    }
    
    return array (
        'sales_order_data' => $sales_order_data,
        'filter'           => $filter,
        'start_time'       => $filter['start_time'],
        'end_time'         => $filter['end_time'],
    );
}


/**
 * 统计销量
 */
function stats_order ($start_time, $end_time, $status,$platform_list = array())
{
    if ($_REQUEST['group_by']) {
        $group_by = 'r.depart_id';
        $key = 'depart_id';
    }else{
        $group_by = 'r.role_describe';
        $key = 'role_describe';
    }
    $result = array ();
    // 获取各平台的销售数据
    $sql_select = 'SELECT r.depart_id,r.role_describe,i.order_type,COUNT(*) order_number,SUM(i.final_amount) final_amount,'.
        'SUM(i.goods_amount) goods_amount,SUM(i.shipping_fee) shipping_fee FROM '.$GLOBALS['ecs']->table('order_info').' i, '.
        $GLOBALS['ecs']->table('role')." r WHERE i.add_time BETWEEN $start_time AND $end_time $status AND ".
        "r.role_id=i.platform AND i.order_type NOT IN (1,2,8,10) GROUP BY $group_by,i.order_type ORDER BY final_amount DESC";

    $result = $GLOBALS['db']->getAll($sql_select);

    if (empty($platform_list)){
        $platform_list = platform_list();
    }
    $month_days    = date('t', $end_time);
    $month_now_day = date('j', time());

    // 计算同一平台所有订单类型总销量
    $s = $result;
    $r = $result;
    $res = array ();
    foreach ($s as $k) {
        @$res[$k[$key]][$k['order_type']] = $k;
        @$res[$k[$key]]['total_number'] += $k['order_number'];
        @$res[$k[$key]]['total_amount'] = bcadd($k['final_amount'],$res[$k[$key]]['total_amount'],2);
    }

    // 合并活动个人订单、活动静默订单
    foreach ($res as &$v){
        if (isset($v[5],$v[7])) {
            $v['o']['order_type'] = 'o';
            $v['o']['order_number'] = $v[5]['order_number'] + $v[7]['order_number'];
            $v['o']['final_amount'] = bcadd($v[5]['final_amount'], $v[7]['final_amount'], 2);
            $v['o']['goods_amount'] = bcadd($v[5]['goods_amount'], $v[7]['goods_amount'], 2);
            $v['o']['shipping_fee'] = bcadd($v[5]['shipping_fee'], $v[7]['shipping_fee'], 2);
        } elseif (isset($v[5])) {
            $v[5]['order_type'] = 'o';
            $v['o'] = $v[5];
        } elseif (isset($v[7])) {
            $v[7]['order_type'] = 'o';
            $v['o'] = $v[7];
        }
    }

    // 加入没有订单的平台
    if (!$_REQUEST['group_by']) {
        foreach ($platform_list as $p) {
            if(!array_key_exists($p[$key], $res)){
                $res[$p[$key]] = array();
            }
        }
    }

    // 计算所有平台同一订单类型总销量
    foreach ($r as $j) {
        if (in_array($j['order_type'], array(5,7))) {
            @$res['合计']['o']['order_number'] += $j['order_number'];
            @$res['合计']['o']['final_amount'] = bcadd($j['final_amount'],$res['合计']['o']['final_amount'],2);
            @$res['合计']['total_number'] += $j['order_number'];
            @$res['合计']['total_amount'] = bcadd($j['final_amount'],$res['合计']['total_amount'],2);
        } else {
            @$res['合计'][$j['order_type']]['order_number'] += $j['order_number'];
            @$res['合计'][$j['order_type']]['final_amount'] = bcadd($j['final_amount'],$res['合计'][$j['order_type']]['final_amount'],2);
            @$res['合计']['total_number'] += $j['order_number'];
            @$res['合计']['total_amount'] = bcadd($j['final_amount'],$res['合计']['total_amount'],2);
        }
    }

    foreach ($res as &$val) {
        @$val['avg']      = $val['total_amount'] ? round($val['total_amount']/$val['total_number'], 2) : 0;
        @$val['forecast'] = round($val['total_amount']/$month_now_day*$month_days, 2);
    }

    return $res;
}

/**
 * 获取年、月
 * @param $time       string/timestamp
 * @param $date_type  char    月:n  年:Y
 */
function date_time ($time, $date_type = 'n')
{
    if (strpos($time, '-'))
    {
        $time = strtotime($time);
    }

    $month = date($date_type, $time);

    return $month;
}

/**
 * 自然统计
 */
function nature_stats ()
{
    $now_time = time();
    $filter['v'] = isset($_REQUEST['v']) ? addslashes_deep($_REQUEST['v']) : 0;

    $filter['start_time'] = isset($_REQUEST['start_time']) ? strtotime($_REQUEST['start_time']) : 0;
    $filter['end_time']   = isset($_REQUEST['end_time'])   ? strtotime($_REQUEST['end_time'].' 23:59:59'): $now_time;

    if ($filter['start_time'] == 0) {
        $filter['start_time'] = strtotime(date('Y-m-01 00:00:00', $now_time));
    }

    $format = array (
        'day'   => '%Y-%m-%d',
        'month' => '%Y-%m',
        'year'  => '%Y',
    );

    // 设置统计标准
    if (isset($_REQUEST['v'])) {
        $fmt = $format[$filter['v']];
    } else {
        $fmt = $format['day'];
    }

    // 设置统计开始时间
    $fields = '';
    $sql_where = " WHERE add_time BETWEEN {$filter['start_time']} AND {$filter['end_time']} AND order_status IN (5,1) AND shipping_status<>3 AND order_type NOT IN(1,10) AND order_type<100 ";
    $sql_platform = '';
    $k = '';
    if (admin_priv('nature_stats_all', '', false)) {
        switch ($_REQUEST['target']) {
        case 'platform_stats':
            $fields = 'r.role_name role_name,';
            $k = 'platform';
            $sql_platform = ','.$GLOBALS['ecs']->table('role')
                ." r $sql_where AND r.role_id=i.platform GROUP BY DT,r.depart_desc ";
            break;
        case 'self_stats':
            $fields = ' role_name,';
            $k = 'admin_id';
            $sql_platform = " $sql_where GROUP BY DT,admin_id ";
            break;
        }
    } elseif (admin_priv('platform_stats', '', false)) {
        $fields = 'r.role_name role_name,';
        $k = 'platform';
        $platform = get_manage_platform();
        $sql_platform = ','.$GLOBALS['ecs']->table('role')." r $sql_where AND r.role_id=i.platform AND i.platform IN ($platform) GROUP BY DT";
    } elseif (admin_priv('nature_trans-part', '', false)) {
        $fields = 'r.role_name role_name,';
        $k = 'platform';
        $platform = trans_part_list();
        $platform = implode(',', $platform);
        $sql_platform = ','.$GLOBALS['ecs']->table('role')." r $sql_where AND r.role_id=i.platform AND i.platform IN ($platform) GROUP BY DT";
    } elseif (admin_priv('self_stats', '', false)) {
        $k = 'admin_id';
        $sql_platform = " $sql_where AND admin_id={$_SESSION['admin_id']} GROUP BY DT ";
    }

    $sql_select = "SELECT r.depart_desc platform,i.admin_id,i.admin_name, $fields COUNT(*) order_num,".
        ' SUM(final_amount) order_amount,DATE_FORMAT(FROM_UNIXTIME(add_time),'."'$fmt') DT FROM ".
        $GLOBALS['ecs']->table('order_info')." i $sql_platform ORDER BY add_time DESC";
    $res = $GLOBALS['db']->getAll($sql_select);

    $stats_list = array ();
    foreach ($res as $v) {
        @$stats_list[$v['DT']][$v[$k]] = $v;
        if ($k != 'admin_id') {
            @$stats_list[$v['DT']]['total']['order_num'] += $v['order_num'];
            @$stats_list[$v['DT']]['total']['order_amount'] = bcadd($stats_list[$v['DT']]['total']['order_amount'], $v['order_amount'], 2);
        }
    }

    $final = array (
        'stats_list' => $stats_list,
        'start_time' => date('Y-m-d', $filter['start_time']),
        'end_time'   => date('Y-m-d', $filter['end_time']),
    );

    return $final;
}

/**
 * 计算当月各平台总销量
 */
function stats_all ()
{
    if (isset($_REQUEST['start_time'], $_REQUEST['end_time'])){
        $start = isset($_REQUEST['start_time']) ? strtotime($_REQUEST['start_time']) : 0;
        $end   = isset($_REQUEST['end_time'])   ? strtotime($_REQUEST['end_time'].' 23:59:59'): $now_time;
    } else {
        $start = strtotime(date('Y-m-01 00:00:00'));
        $end   = strtotime(date('Y-m-t 23:59:59'));
    }

    $sql_platform = '';
    // 统计权限限制
    if (admin_priv('nature_stats_all', '', false)){
    }
    else {
        $sql_platform = " AND platform={$_SESSION['role_id']} ";
    }

    $k = '';
    if (admin_priv('nature_stats_all', '', false)) {
        switch ($_REQUEST['target']) {
        case 'platform_stats':
            $k = 'platform';
            $sql_platform = ' GROUP BY r.depart_desc ';
            break;
        case 'self_stats':
            $k = 'admin_id';
            $sql_platform = " GROUP BY i.admin_id ";
            break;
        }
    } elseif (admin_priv('platform_stats', '', false)) {
        $k = 'platform';
        $platform = get_manage_platform();
        $sql_platform = " AND i.platform IN ($platform) GROUP BY r.role_describe";
    } elseif (admin_priv('nature_trans-part', '', false)) {
        $k = 'platform';
        $platform = implode(',', trans_part_list());
        $sql_platform = " AND i.platform IN ($platform) GROUP BY r.role_describe";
    } elseif (admin_priv('self_stats', '', false)) {
        $k = 'admin_id';
        $sql_platform = " AND i.admin_id={$_SESSION['admin_id']} GROUP BY i.admin_id";
    }

    $sql_select = 'SELECT COUNT(*) order_num,SUM(i.final_amount) order_amount,r.depart_desc platform,i.admin_id,i.admin_name FROM '.
        $GLOBALS['ecs']->table('order_info').' i,'.$GLOBALS['ecs']->table('role').
        " r WHERE i.order_status IN (1,5) AND i.order_type NOT IN (1,10,100) AND i.shipping_status<>3 AND i.platform=r.role_id AND i.add_time BETWEEN $start AND $end $sql_platform";
    $res = $GLOBALS['db']->getAll($sql_select);

    $stats_all = array ();
    foreach ($res as $val) {
        @$stats_all[$val[$k]] = $val;
        if ($k != 'admin_id')
        {
            @$stats_all['total']['order_num'] += $val['order_num'];
            @$stats_all['total']['order_amount'] = bcadd($stats_all['total']['order_amount'], $val['order_amount'], 2);
        }
    }

    return $stats_all;
}

/**
 * 统计各平台当前月退货数量
 */
function stats_all_return ()
{
    if (isset($_REQUEST['start_time'], $_REQUEST['end_time'])) {
        $start = isset($_REQUEST['start_time']) ? strtotime($_REQUEST['start_time']) : 0;
        $end   = isset($_REQUEST['end_time'])   ? strtotime($_REQUEST['end_time'].' 23:59:59'): $now_time;
    } else {
        $start = strtotime(date('Y-m-01 00:00:00'));
        $end   = strtotime(date('Y-m-t 23:59:59'));
    }

    $sql_platform = '';

    // 统计权限限制
    if (admin_priv('nature_stats_all', '', false)){
    } elseif (admin_priv('nature_trans-part', '', false)) {
        $platform = implode(',', trans_part_list());
        $sql_platform = " AND i.platform IN ($platform)";
    } else {
        $sql_platform = " AND i.platform={$_SESSION['role_id']} ";
    }

    $k = '';
    if (admin_priv('nature_stats_all', '', false)) {
        switch ($_REQUEST['target']) {
        case 'platform_stats':
            $k = 'platform';
            $sql_platform = ' GROUP BY i.platform ';
            break;
        case 'self_stats':
            $k = 'admin_id';
            $sql_platform = " GROUP BY i.admin_id ";
            break;
        }
    } elseif (admin_priv('platform_stats', '', false)) {
        $k = 'platform';
        $platform = get_manage_platform();
        $sql_platform = " AND i.platform IN ($platform) GROUP BY i.platform";
    } elseif (admin_priv('nature_trans-part', '', false)) {
        $k = 'platform';
        $platform = implode(',', trans_part_list());
        $sql_platform = " AND i.platform IN ($platform) GROUP BY i.platform";
    } elseif (admin_priv('self_stats', '', false)) {
        $k = 'admin_id';
        $sql_platform = " AND i.admin_id={$_SESSION['admin_id']} GROUP BY i.admin_id";
    }

    $sql_select = 'SELECT COUNT(*) order_num,SUM(i.final_amount) order_amount,role.depart_desc platform,i.admin_id,i.admin_name FROM '.
        $GLOBALS['ecs']->table('order_info').' i,'.$GLOBALS['ecs']->table('returns_order').' r, '.$GLOBALS['ecs']->table('role').
        ' role WHERE i.order_status IN (1,5) AND i.shipping_status=4 AND r.order_id=i.order_id AND role.role_id=i.platform AND '.
        " r.return_time BETWEEN $start AND $end $sql_platform";
    $res = $GLOBALS['db']->getAll($sql_select);

    $stats_all = array ();
    foreach ($res as $val) {
        @$stats_all[$val[$k]]['platform'] = $val['platform'];
        if (empty($stats_all[$val[$k]]['order_num'])) {
            $stats_all[$val[$k]]['order_num'] = $val['order_num'];
        } else {
            $stats_all[$val[$k]]['order_num'] += $val['order_num'];
        }
        if (empty($stats_all[$val[$k]]['order_amount'])){
            $stats_all[$val[$k]]['order_amount'] = $val['order_amount'];
        } else {
            $stats_all[$val[$k]]['order_amount'] = bcadd($val['order_amount'],$stats_all[$val[$k]]['order_amount'],2) ;
        }
        if ($k != 'admin_id') {
            @$stats_all['total']['order_num'] += $val['order_num'];
            @$stats_all['total']['order_amount'] = bcadd($stats_all['total']['order_amount'], $val['order_amount'], 2);
        }
    }

    return $stats_all;
}

/**
 * 按照订单时间统计各平台当月退货
 */
function stats_month_return ()
{
    if (isset($_REQUEST['start_time'], $_REQUEST['end_time'])) {
        $start = isset($_REQUEST['start_time']) ? strtotime($_REQUEST['start_time']) : 0;
        $end   = isset($_REQUEST['end_time'])   ? strtotime($_REQUEST['end_time'].' 23:59:59'): $now_time;
    } else {
        $start = strtotime(date('Y-m-01 00:00:00'));
        $end   = strtotime(date('Y-m-t 23:59:59'));
    }

    $sql_platform = '';

    // 统计权限限制
    if (admin_priv('nature_stats_all', '', false)){
    } elseif (admin_priv('nature_trans-part', '', false)) {
        $platform = implode(',', trans_part_list());
        $sql_platform = " AND i.platform IN ($platform)";
    } else {
        $sql_platform = " AND i.platform={$_SESSION['role_id']} ";
    }

    $k = '';
    if (admin_priv('nature_stats_all', '', false)) {
        switch ($_REQUEST['target']) {
        case 'platform_stats':
            $k = 'platform';
            $sql_platform = ' GROUP BY i.platform ';
            break;
        case 'self_stats':
            $k = 'admin_id';
            $sql_platform = " GROUP BY i.admin_id ";
            break;
        }
    } elseif (admin_priv('platform_stats', '', false)) {
        $k = 'platform';
        $platform = get_manage_platform();
        $sql_platform = " AND i.platform IN ($platform) GROUP BY i.platform";
    } elseif (admin_priv('nature_trans-part', '', false)) {
        $k = 'platform';
        $platform = implode(',', trans_part_list());
        $sql_platform = " AND i.platform IN ($platform) GROUP BY i.platform";
    } elseif (admin_priv('self_stats', '', false)) {
        $k = 'admin_id';
        $sql_platform = " AND i.admin_id={$_SESSION['admin_id']} GROUP BY i.admin_id";
    }

    $sql_select = 'SELECT COUNT(*) order_num,SUM(i.final_amount) order_amount,role.role_describe platform,i.admin_id,i.admin_name FROM '.
        $GLOBALS['ecs']->table('order_info').' i,'.$GLOBALS['ecs']->table('returns_order').' r, '.$GLOBALS['ecs']->table('role').
        ' role WHERE i.order_status IN (1,5) AND i.shipping_status=4 AND r.order_id=i.order_id AND role.role_id=i.platform AND '.
        " i.add_time BETWEEN $start AND $end $sql_platform";
    $res = $GLOBALS['db']->getAll($sql_select);

    $stats_all = array ();
    foreach ($res as $val) {
        @$stats_all[$val[$k]]['platform'] = $val['platform'];
        if (empty($stats_all[$val[$k]]['order_num'])) {
            $stats_all[$val[$k]]['order_num'] = $val['order_num'];
        } else {
            $stats_all[$val[$k]]['order_num'] += $val['order_num'];
        }
        if (empty($stats_all[$val[$k]]['order_amount'])){
            $stats_all[$val[$k]]['order_amount'] = $val['order_amount'];
        } else {
            $stats_all[$val[$k]]['order_amount'] = bcadd($val['order_amount'],$stats_all[$val[$k]]['order_amount'],2) ;
        }
        if ($k != 'admin_id') {
            @$stats_all['total']['order_num'] += $val['order_num'];
            @$stats_all['total']['order_amount'] = bcadd($stats_all['total']['order_amount'], $val['order_amount'], 2);
        }
    }

    return $stats_all;
}


/**
 * 统计重复购买的
 */
function rebuy_stats ($num_limit = 2) {
    $filter['start_time'] = empty($_REQUEST['start_time']) ? '' : trim($_REQUEST['start_time']);
    $filter['end_time']   = empty($_REQUEST['end_time'])   ? '' : trim($_REQUEST['end_time']);

    $filter['platform'] = empty($_REQUEST['platform']) ? 0 : intval($_REQUEST['platform']);
    $filter['admin_id'] = empty($_REQUEST['admin_id']) ? 0 : intval($_REQUEST['admin_id']);

    if (empty($filter['start_time']) || empty($filter['end_time'])) {
        $filter['start_time'] = date('Y-m-01 00:00:00');
        $filter['end_time']   = date('Y-m-t 00:00:00');
    }

    $filter['page_size'] = empty($_REQUEST['page_size']) ? 20 : intval($_REQUEST['page_size']);
    $filter['page']      = empty($_REQUEST['page_no'])      ? 1  : intval($_REQUEST['page_no']);

    $ex_where = ' p.ordinal_number>1 ';

    if (admin_priv('rebuy_stats_all', '', false)) {
        if ($filter['platform']) {
            $ex_where .= " AND p.team={$filter['platform']}";
        }
    } else {
        $ex_where .= " AND p.team={$_SESSION['role_id']}";
    }

    if ($filter['admin_id']) {
        $ex_where .= " AND p.admin_id={$filter['admin_id']} ";
    }

    $condition = '';
    foreach ($filter as $key=>$val) {
        if (!empty($val)) {
            if ($key == 'type') {
                $condition .= "&$key=".urlencode($val);
                continue;
            }

            $condition .= "&$key=$val";
        }
    }

    $start_time = strtotime($filter['start_time']);
    $end_time   = strtotime($filter['end_time']);

    // 统计总记录数
    $sql_select = 'SELECT COUNT(*) FROM '.$GLOBALS['ecs']->table('repeat_purchase').
        " p WHERE $ex_where AND buy_time BETWEEN $start_time AND $end_time";
    $record_count = $GLOBALS['db']->getOne($sql_select);

    $page = break_pages($record_count, $filter['page_size'], $filter['page']);

    $sql_select = 'SELECT ordinal_number,final_amount,FROM_UNIXTIME(buy_time,"%Y-%m-%d") buy_time,u.user_name,p.user_id,a.user_name admin_name,r.role_name FROM '.
        $GLOBALS['ecs']->table('repeat_purchase').' p LEFT JOIN '.$GLOBALS['ecs']->table('users').' u ON u.user_id=p.user_id LEFT JOIN '.
        $GLOBALS['ecs']->table('role').' r ON p.team=r.role_id LEFT JOIN '.$GLOBALS['ecs']->table('admin_user').
        " a ON a.user_id=p.admin_id WHERE $ex_where AND buy_time BETWEEN $start_time AND $end_time ORDER BY final_amount DESC,ordinal_number DESC LIMIT ".($filter['page'] -1)*$filter['page_size'].', '.$filter['page_size'];
    $res = $GLOBALS['db']->getAll($sql_select);

    $result = array(
        'stats'        => $res,
        'filter'       => $filter,
        'page_count'   => $page['page_count'],
        'record_count' => $record_count,
        'page_size'    => $filter['page_size'],
        'page'         => $filter['page'],
        'page_set'     => $page['page_set'],
        'condition'    => $condition,
        'start'        => $page['start'],
        'end'          => $page['end'],
    );

    return $result;
}

/**
 * 统计回购数据
 */
function buy_back_stats($field)
{
    if (!isset($_REQUEST['start_time'],$_REQUEST['end_time'])){
        $filter['start_time'] = date('Y-m-01 00:00:00', time());
        $filter['end_time']   = date('Y-m-t 23:59:59', time());
    } else {
        $filter['start_time'] = $_REQUEST['start_time'].' 00:00:00';
        $filter['end_time']   = $_REQUEST['end_time'].' 23:59:59';
    }

    $ex_where = ' AND user_id>0';


    if ($field == 'team') {
        if (admin_priv('buy_back_stats_all', '', false)) {
            $ex_where .= ' AND team>0 ';
        } else {
            if (admin_priv('personal_repo_trans-part', '', false)) {
                $trans_role_list = implode(',', trans_part_list());
                $ex_where .= " AND team IN ($trans_role_list) ";
            } else {
                $ex_where .= " AND team={$_SESSION['role_id']} ";
            }
        }
    } else {
        if (admin_priv('personal_repo_all', '', false)) {
            $ex_where .= ' AND admin_id>0 ';
        } elseif (admin_priv('personal_repo_part', '', false)) {
            $ex_where .= " AND team={$_SESSION['role_id']} ";
        } else {
            $ex_where .= " AND admin_id={$_SESSION['admin_id']} ";
        }
    }

    // 统计各平台老顾客购买人数
    //$sql_select = "SELECT COUNT(DISTINCT user_id) times,$field FROM ".$GLOBALS['ecs']->table('order_info').
    //    ' WHERE order_status IN (1,5) AND shipping_status IN (0,1,2) AND order_type IN (3,4,5,6,7) AND team<>23'.
    //    " $ex_where GROUP BY user_id HAVING COUNT(user_id)>1";
    $available_type = VALID_ORDER_TYPE;
    $start_time = '1316719262';
    $end_time   = $_SERVER['REQUEST_TIME'];
    $condition  = " add_time BETWEEN $start_time AND $end_time ";
    $sql_select = "SELECT 1 times,$field,user_id FROM ".$GLOBALS['ecs']->table('order_info')
        ." WHERE $condition AND order_status IN (1,5) AND shipping_status IN (0,1,2) AND order_type IN ($available_type) AND team<>23"
        ." $ex_where ";
    $all_old_users_res = $GLOBALS['db']->getAll($sql_select);

    //过虑只购买过一次的顾客
    //if ($_SESSION['admin_id'] == 142) {
        $all_old_users_res = rebuy_repeat($all_old_users_res,'user_id');
    //}

    $platform_old_users_count = array(); // 各平台老顾客购买总人数
    $all_old_users_count = 0;            // 老顾客购买总人数
    foreach ($all_old_users_res as $val) {
        $platform_old_users_count[$val[$field]] += $val['times'];
        $all_old_users_count += $val['times'];
    }

    // 统计各平台有购买记录的顾客总数量
    $sql_select = "SELECT 1 times,$field FROM ".$GLOBALS['ecs']->table('order_info').
        " WHERE $condition AND order_status IN (1,5) AND shipping_status IN (0,1,2) AND team<>23 AND final_amount>0 AND order_type IN ($available_type) $ex_where";

    $platform_users_res = $GLOBALS['db']->getAll($sql_select);
    $platform_users_res = rebuy_repeat($platform_users_res,'team');
    // 计算有购买记录的顾客总数量
    $all_users_count = 0;
    $platform_users_count = array();
    foreach ($platform_users_res as $val) {
        $platform_users_count[$val[$field]] = $val['times'];
        $all_users_count += $val['times'];
    }

    // 老顾客数量/顾客总数量
    $platform_users_rate = array();
    foreach ($platform_users_count as $key=>$value) {
        @$platform_users_rate[$key] = sprintf('%.2f%%', round($platform_old_users_count[$key]/$value*100, 4));
    }

    if ($all_users_count) {
        $all_users_rate = sprintf('%.2f%%', round($all_old_users_count/$all_users_count *100, 4));
    } else {
        $all_users_rate = '-';
    }

    // 统计各平台订单总数量
    $sql_select = "SELECT COUNT(1) times,$field,SUM(final_amount) total_amount FROM ".$GLOBALS['ecs']->table('order_info').
        " WHERE $condition AND order_status IN (1,5) AND shipping_status IN (0,1,2) AND team<>23 AND order_type IN ($available_type) $ex_where GROUP BY $field";

    //if ($_SESSION['admin_id'] == 142) {
    //    echo $sql_select;exit;
    //}
    $platform_order_res = $GLOBALS['db']->getAll($sql_select);

    // 计算订单总数量
    $all_order_count       = 0;
    $all_order_amount      = 0;
    $platform_order_count  = array();
    $platform_order_amount = array();
    foreach ($platform_order_res as $val) {
        $platform_order_count[$val[$field]]  = $val['times'];
        $platform_order_amount[$val[$field]] = $val['total_amount'];

        $all_order_count += $val['times'];
        $all_order_amount = bcadd($all_order_amount, $val['total_amount'], 2);
    }

    // 统计各平台老顾客购买人次
    $sql_select = "SELECT COUNT(order_id) times,$field,SUM(final_amount) old_total_amount FROM ".$GLOBALS['ecs']->table('order_info').
        ' WHERE order_status IN (1,5) AND shipping_status IN (0,1,2) AND team<>23 AND order_type IN (3,4,5,6,7)'.
        " $ex_where GROUP BY user_id HAVING COUNT(user_id)>1";
    $platform_old_order_res = $GLOBALS['db']->getAll($sql_select);
    $platform_old_order_count = array();
    $platform_old_order_amount = array();

    $all_old_order_count  = 0;
    $all_old_order_amount = 0;
    foreach ($platform_old_order_res as $val){
        @$platform_old_order_count[$val[$field]] += $val['times'];
        @$platform_old_order_amount[$val[$field]] = bcadd($platform_old_order_amount[$val[$field]], $val['old_total_amount'], 2);

        $all_old_order_count += $val['times'];
        $all_old_order_amount = bcadd($all_old_order_amount, $val['old_total_amount'], 2);
    }

    // 老顾客购买次数/订单总量
    foreach ($platform_order_count as $key=>$val){
        @$platform_order_rate[$key] = sprintf('%.2f%%', round($platform_old_order_count[$key]/$val*100, 4));
    }
    unset($val);

    if ($all_order_count) {
        $all_order_rate = sprintf('%.2f%%', round($all_old_order_count/$all_order_count*100, 4));
    } else {
        $all_order_rate = '-';
    }

    // 老顾客购买总金额/所有订单总金额
    foreach ($platform_order_amount as $key=>$val){
        if ($val > 0) {
            @$platform_amount_rate[$key] = sprintf('%.2f%%', round($platform_old_order_amount[$key]/$val*100, 4));
        } else {
            $platform_amount_rate[$key] = '-';
        }
    }

    if ($all_order_amount) {
        $all_amount_rate = sprintf('%.2f%%', round($all_old_order_amount/$all_order_amount*100, 4));
    } else {
        $all_amount_rate = '-';
    }

    // 统计时间条件
    $ex_where .= ' AND add_time BETWEEN '.strtotime($filter['start_time']).' AND '.strtotime($filter['end_time']);
    $sql_select = "SELECT COUNT(order_id) order_num,COUNT(DISTINCT user_id) user_num,SUM(final_amount) final_amount,$field,platform FROM ".
        $GLOBALS['ecs']->table('order_info').' WHERE order_status=5 AND shipping_status IN (1,2) AND team<>23 AND order_type IN (3,4,5,6,7)'.
        " $ex_where AND user_id IN (SELECT DISTINCT user_id FROM ".$GLOBALS['ecs']->table('order_info').' WHERE order_status IN (1,5) AND '.
        ' shipping_status IN (0,1,2) AND team<>23 AND order_type IN (3,4,5,6,7) AND add_time<'.strtotime($filter['start_time']).') GROUP BY user_id ';
    $result = $GLOBALS['db']->getAll($sql_select);

    $current_old = array();
    foreach ($result as $key=>&$val) {
        // 计算每个平台的老顾客数量 订单数量 订单金额
        @$current_old[$val[$field]]['old_users_num'] += $val['user_num'];
        @$current_old[$val[$field]]['old_order_num'] += $val['order_num'];
        @$current_old[$val[$field]]['old_final_amount'] = bcadd($current_old[$val[$field]]['old_final_amount'], $val['final_amount'], 2);

        // 计算总量
        @$current_old['total']['old_users_num'] += $val['user_num'];
        @$current_old['total']['old_order_num'] += $val['order_num'];
        @$current_old['total']['old_final_amount'] = bcadd($current_old['total']['old_final_amount'], $val['final_amount'], 2);
    }
    unset($result);

    // 获取起止时间内所有购买了产品的顾客信息
    $sql_select = "SELECT COUNT(order_id) order_num,COUNT(DISTINCT user_id) user_num,SUM(final_amount) final_amount,$field,platform FROM ".
        $GLOBALS['ecs']->table('order_info').' WHERE order_status IN (1,5) AND shipping_status IN (0,1,2) AND team<>23 AND order_type IN (3,4,5,6,7)'.
        " $ex_where GROUP BY user_id";
    $result = $GLOBALS['db']->getAll($sql_select);
    $current_all = array();
    foreach ($result as $val){
        // 计算每个平台的新增顾客数量 订单数量 订单金额
        @$current_all[$val[$field]]['order_num'] += $val['order_num'];
        @$current_all[$val[$field]]['users_num'] += $val['user_num'];
        @$current_all[$val[$field]]['final_amount'] = bcadd($current_all[$val[$field]]['final_amount'], $val['final_amount'], 2);

        // 计算总量
        @$current_all['total']['order_num'] += $val['order_num'];
        @$current_all['total']['users_num'] += $val['user_num'];
        @$current_all['total']['final_amount'] = bcadd($current_all['total']['final_amount'], $val['final_amount'], 2);
    }

    // 合并数据
    $result = array();
    $arr_keys = array_unique(array_merge(array_keys($current_all), array_keys($current_old)));
    foreach ($arr_keys as $val) {
        if (!empty($current_all[$val]) && !empty($current_old[$val])) {
            $result[$val] = _array_merge($current_all[$val], $current_old[$val]);
        } elseif (!empty($current_all[$val])) {
            $result[$val] = $current_all[$val];
        } elseif (!empty($current_old[$val])) {
            $result[$val] = $current_old[$val];
        }
    }

    // 计算百分比
    foreach ($result as &$val){
        @$val['users_rate']  = sprintf("%.2f%%",round($val['old_users_num']/$val['users_num'] *100, 4));
        @$val['order_rate']  = sprintf("%.2f%%",round($val['old_order_num']/$val['order_num'] *100, 4));
        @$val['amount_rate'] = sprintf("%.2f%%",round($val['old_final_amount']/$val['final_amount'] *100, 4));
    }

    // 与全部数据进行合并
    foreach ($result as $k=>&$v) {
        @$v['platform_users']       = $platform_users_count[$k];
        @$v['platform_order']       = $platform_order_count[$k];
        @$v['platform_amount']      = $platform_order_amount[$k];
        @$v['platform_old_users']   = $platform_old_users_count[$k];
        @$v['platform_old_order']   = $platform_old_order_count[$k];
        @$v['platform_old_amount']  = $platform_old_order_amount[$k];
        @$v['platform_users_rate']  = $platform_users_rate[$k];
        @$v['platform_order_rate']  = $platform_order_rate[$k];
        @$v['platform_amount_rate'] = $platform_amount_rate[$k];

        @$platform_users_count[$k] > 0 && $v['total_rate'] = sprintf("%.2f%%",round(@$v['old_users_num']/$platform_users_count[$k] *100, 4));
    }
    unset($val);

    foreach ($result as $val) {
        $result['total']['platform_users']     += $val['platform_users'];
        $result['total']['platform_old_users'] += $val['platform_old_users'];
        $result['total']['platform_order']     += $val['platform_order'];
        $result['total']['platform_old_order'] += $val['platform_old_order'];

        @$result['total']['old_amount'] = bcadd($result['total']['old_amount'], $val['platform_old_amount'], 2);
        @$result['total']['amount']     = bcadd($result['total']['amount'], $val['platform_amount'], 2);
    }
    unset($val);

    if ($result['total']['platform_users']) {
        $result['total']['total_rate'] = sprintf("%.2f%%",round($result['total']['old_users_num']/$result['total']['platform_users'] *100,4));
    } else {
        $result['total']['total_rate'] = '-';
    }

    $result['total']['platform_users_rate'] = $all_users_rate;
    $result['total']['platform_order_rate'] = $all_order_rate;
    $result['total']['platform_amount_rate'] = $all_amount_rate;

    $total = $result['total'];
    unset($result['total']);
    ksort($result);
    $result['total'] = $total;
    foreach ($result as &$val) {
        if ($val['final_amount'] > 0) {
            @$val['old_rate'] = sprintf("%.2f%%",round($val['old_final_amount']/$val['final_amount'] *100, 4));
        } else {
            $val['old_rate'] = '-';
        }
    }

    return $result;
}

/**
 * 统计各平台各品牌销售情况
 */
function stats_brand ()
{
    if (!isset($_REQUEST['start_time'],$_REQUEST['end_time'])){
        $filter['start_time'] = strtotime(date('Y-m-01 00:00:00', time()));
        $filter['end_time']   = strtotime(date('Y-m-t 23:59:59', time()));
    }else{
        $filter['start_time'] = strtotime($_REQUEST['start_time']);
        $filter['end_time']   = strtotime($_REQUEST['end_time']);
    }

    $filter['platform'] = empty($_REQUEST['platform']) ? 1 : intval($_REQUEST['platform']);

    // 设置查询的时间周期
    $fmt = '%Y-%m-%d';
    $sql_select = 'SELECT g.brand_id,SUM(g.goods_number) goods_num,SUM(g.goods_price) goods_amount, '.
        "FROM_UNIXTIME(i.add_time, '$fmt') days FROM ".$GLOBALS['ecs']->table('order_goods').
        ' g, '.$GLOBALS['ecs']->table('order_info').' i WHERE i.order_id=g.order_id AND '.
        ' i.order_status=5 AND i.shipping_status IN (1,2) AND g.is_package=0 AND add_time BETWEEN '.
        "{$filter['start_time']} AND {$filter['end_time']} AND team={$filter['platform']} GROUP BY days,g.brand_id";
    $brand_list = $GLOBALS['db']->getAll($sql_select);

    $date_list = array ();
    foreach ($brand_list as $val)
    {
        $date_list['day'][date('d', strtotime($val['days']))] = date('d', strtotime($val['days']));
        $date_list['title'] = date(str_replace('%','',substr($fmt,0,strrpos($fmt,'-'))), strtotime($val['days']));
        $date_list['list'][$val['brand_id']][date(str_replace('-%','',substr($fmt,strrpos($fmt,'-'))),strtotime($val['days']))] = $val;
    }

    return $date_list;
}

/**
 * 获取指定平台下的客服列表
 */
function admin_list_assign ($platform = 0)
{
    $sql_select = 'SELECT user_id, user_name FROM '.$GLOBALS['ecs']->table('admin_user').
        ' WHERE status>0 AND stats>0 ';
    if (!empty($platform))
    {
        $sql_select .= " AND role_id=$platform ";
    }

    $admin_list = $GLOBALS['db']->getAll($sql_select);

    return $admin_list;
}

/**
 * 顾客统计
 */
function user_stats()
{
    $result = array ();
    // 获取所有顾客数据
    $sql_select = 'SELECT COUNT(*) total FROM '.$GLOBALS['ecs']->table('users').
        ' WHERE admin_id>0 AND role_id>0 AND customer_type<5';
    $result['total'] = $GLOBALS['db']->getOne($sql_select);

    // 获取各部门的顾客数量
    $sql_select = 'SELECT COUNT(*) total,u.role_id,r.role_name FROM '.$GLOBALS['ecs']->table('users').' u,'.
        $GLOBALS['ecs']->table('role').' r WHERE r.role_id=u.role_id AND u.admin_id>0 AND u.role_id>0 AND customer_type<5 GROUP BY u.role_id ORDER BY u.role_id ASC';
    $result['section'] = $GLOBALS['db']->getAll($sql_select);

    return $result;
}

/**
 * 顾客统计
 */
function user_stats2() {
    // 获取所有顾客数据(过滤黑名单，刷单，DM顾客)
    $sql_select = 'SELECT COUNT(*) total, customer_type FROM '.$GLOBALS['ecs']->table('users').
        ' WHERE customer_type NOT IN(5,6,7,21) GROUP BY customer_type ORDER BY role_id';
    $result = $GLOBALS['db']->getAll($sql_select);
    $user_total = array();
    foreach ($result as $val) {
        $user_total[$val['customer_type']] = $val['total'];
        if (in_array($val['customer_type'],array(1,2,3,4,22))) {
            if (isset($user_total['total'])) {
                $user_total['total'] += $val['total'];
            } else {
                $user_total['total'] = $val['total'];
            }
        }
    }

    // 获取各部门的顾客数量
    $table    = 'role';
    $ex_group = 'u.role_id,u.customer_type';
    $ex_field = 'u.role_id id ,r.role_name k';
    $ex_where = 'r.role_id=u.role_id';
    //客服的顾客数量
    if (isset($_REQUEST['person']) && 1 == $_REQUEST['person']) {
        $table    = 'admin_user';
        $ex_group = 'u.admin_id,u.customer_type';
        $ex_field = 'u.admin_id id,r.user_name k,u.role_id role_color';
        $ex_where = 'r.user_id=u.admin_id';
    }
    $sql_select = 'SELECT COUNT(*) total,u.customer_type,%s FROM '.$GLOBALS['ecs']->table('users').' u,'.
        $GLOBALS['ecs']->table($table).' r WHERE %s GROUP BY %s ORDER BY u.role_id DESC';
    $result = $GLOBALS['db']->getAll(sprintf($sql_select, $ex_field, $ex_where, $ex_group));

    $section_total = array();
    foreach ($result as &$val) {
        $section_total[$val['k']][$val['customer_type']] = $val['total'];
    }
    foreach ($section_total as &$val) {
        $val['total'] = array_sum($val);
    }
    //print_r($section_total);exit;
    return array('total' => $user_total, 'section' => $section_total);
}

/**
 * 客服kv表
 */
function customers_kv() {
    $sql_select = 'SELECT user_name,role_id FROM '.$GLOBALS['ecs']->table('admin_user');
    $result = $GLOBALS['db']->getAll($sql_select);
    $final = array();
    foreach ($result as $val) {
        $final[$val['user_name']] = $val['role_id'];
    }
    return $final;
}

/**
 * 顾客统计 -- 表格形式展示
 */
function user_stats_table()
{
    $filter['role_id']    = isset($_REQUEST['role_id'])    ? intval($_REQUEST['role_id']) : 0;
    $filter['admin_list'] = empty($_REQUEST['admin_list']) ? 0 : $_REQUEST['admin_list'];
    $filter['shape']      = empty($_REQUEST['shape'])      ? 'table' : $_REQUEST['shape'];

    $sql_select = 'SELECT COUNT(*) user_number,u.admin_name,u.admin_id,u.role_id,r.role_name FROM '.
        $GLOBALS['ecs']->table('users').' u, '.$GLOBALS['ecs']->table('role').
        ' r WHERE r.role_id=u.role_id AND customer_type<5';

    if ($filter['role_id'] == 0) {
        $sql_select .= ' AND u.role_id IN ('.SALE.') ';
    } elseif ($filter['role_id']) {
        $sql_select .= " AND u.role_id={$filter['role_id']} ";
    }

    if ($filter['admin_list']) {
        $sql_select .= " AND u.admin_id IN ({$filter['admin_list']}) ";
    }

    $sql_select .= ' AND u.admin_id>0 GROUP BY admin_id ORDER BY u.role_id ASC,user_number DESC';

    return $GLOBALS['db']->getAll($sql_select);
}

/**
 * 客服列表
 */
function admin_sales($role = array (SALE))
{
    $sql_select = 'SELECT user_id, user_name FROM '.$GLOBALS['ecs']->table('admin_user').
        ' WHERE status>0 AND role_id IN ('.implode(',', $role).')';
    return $GLOBALS['db']->getAll($sql_select);
}
/**
 * 排序
 */
function sort_by_sales($sales)
{
    $temp_order = array();
    foreach ($sales as $key=>$val)
    {
        if ($key != '合计')
        {
            @$temp_order[$key] = $val['total_amount'];
        }
    }

    arsort($temp_order);

    $final_order = array();
    foreach ($temp_order as $k=>$v)
    {
        $final_order[$k] = $sales[$k];
    }

    @$final_order['合计'] = $sales['合计'];

    return $final_order;
}

/**
 * 订单数据
 */
function order_data_amount()
{
    if (empty($_REQUEST['query_time']))
    {
        $filter = query_time_limit();
    }
    else
    {
        $filter = query_time_limit($_REQUEST['query_time']);
    }

    $filter['platform'] = empty($_REQUEST['platform']) ? 0 : intval($_REQUEST['platform']);
    $filter['admin_id'] = empty($_REQUEST['admin_id']) ? 0 : intval($_REQUEST['admin_id']);

    $where = '';
    if ($filter['platform'])
    {
        $where .= " AND team={$filter['platform']} ";
    }

    if ($filter['admin_id'])
    {
        $where .= " AND admin_id={$filter['admin_id']} ";
    }

    $sql_select = 'SELECT pay_id,SUM(final_amount) final_amount,SUM(shipping_fee) shipping_fee FROM '.
        $GLOBALS['ecs']->table('order_info')." WHERE add_time BETWEEN {$filter['start']} AND ".
        "{$filter['end']} AND order_status IN (1,5) AND shipping_status IN (0,1,2) $where GROUP BY pay_id";
    $res = $GLOBALS['db']->getAll($sql_select);

    return $res;
}

/**
 * 查询时间条件
 */
function query_time_limit($time = '')
{
    // 1、参数为时间戳
    // 2、参数为日期字符串
    // 3、参数为空
    if (is_numeric($time))
    {
        $time_limit = array(
            'start' => date('Y-m-01 00:00:00', $time),
            'end'   => date('Y-m-t 23:59:59', $time),
        );
    }
    elseif (!empty($time) && is_string($time))
    {
        $time = strtotime($time);
        $time_limit = array(
            'start' => date('Y-m-01 00:00:00', $time),
            'end'   => date('Y-m-t 23:59:59', $time),
        );
    }

    $time_limit = array(
        'start' => empty($time_limit['start'])?strtotime(date('Y-m-01 00:00:00')):strtotime($time_limit['start']),
        'end'   => empty($time_limit['end'])  ?strtotime(date('Y-m-t 00:00:00')) :strtotime($time_limit['end']),
    );

    return $time_limit;
}

/**
 * 区域顾客数量统计
 */
function user_stats_region ()
{
    $filter['admin_id'] = empty($_REQUEST['admin_list']) ? 0 : $_REQUEST['admin_list'];
    $filter['role_id']  = empty($_REQUEST['role_id']) ? 0 : $_REQUEST['role_id'];

    $ex_where = ' WHERE u.user_id=a.user_id AND u.role_id>0 AND u.admin_id>0';
    if ($filter['admin_id'])
    {
        $ex_where .= " AND u.admin_id IN ({$filter['admin_id']}) ";
    }
    elseif ($filter['role_id'])
    {
        $ex_where .= " AND u.role_id IN ({$filter['role_id']}) ";
    }

    $sql_select = 'SELECT COUNT(*) value,LEFT(r.region_name COLLATE utf8_general_ci,2) name FROM '.
        $GLOBALS['ecs']->table('users').' u,'.$GLOBALS['ecs']->table('user_address').' a,'.
        $GLOBALS['ecs']->table('region').' r '.$ex_where.
        ' AND a.province=r.region_id AND r.region_type=1 AND customer_type IN (2,3,4,5,11) GROUP BY a.province';
    $prov = $GLOBALS['db']->getAll($sql_select);
    foreach ($prov as &$val)
    {
        if ($val['name'] == '内蒙')
        {
            $val['name'] = '内蒙古';
        }
        elseif($val['name'] == '黑龙')
        {
            $val['name'] = '黑龙江';
        }
    }

    $sql_select = 'SELECT COUNT(*) value,r.region_name name FROM '.$GLOBALS['ecs']->table('users').
        ' u,'.$GLOBALS['ecs']->table('user_address').' a,'.$GLOBALS['ecs']->table('region').' r '.
        $ex_where.' AND a.city=r.region_id AND customer_type IN (2,3,4,5,11) GROUP BY a.city';
    $city = $GLOBALS['db']->getAll($sql_select);

    // 北京 上海 天津 重庆 四大直辖市下辖区顾客数量
    $sql_select = 'SELECT COUNT(*) value,r.region_name name FROM '.$GLOBALS['ecs']->table('users').
        ' u,'.$GLOBALS['ecs']->table('user_address').' a,'.$GLOBALS['ecs']->table('region').' r '.
        $ex_where.' AND a.district=r.region_id AND r.region_type=3 AND customer_type IN (2,3,4,5,11) AND a.city IN (110100,120100,310100,500100) GROUP BY a.district';
    $district = $GLOBALS['db']->getAll($sql_select);

    return $res = array_merge($prov,$city,$district);
}

/**
 * 获取最大的人数值
 */
function max_prov($arr)
{
    $num = array();
    foreach ($arr as $val)
    {
        $num[] = $val['value'];
    }

    return max($num);
}

/**
 * 统计会员部销量组成
 */
function stats_member()
{
    $filter['start_time'] = empty($_REQUEST['start_time']) ? 0 : $_REQUEST['start_time'];
    $filter['end_time']   = empty($_REQUEST['end_time'])   ? 0 : $_REQUEST['end_time'];

    $where = ' WHERE 1';
    if ($filter['start_time'] && $filter['end_time']) {
        $filter['start_time'] = strtotime(stamp2date($filter['start_time'], 'Y-m-d H:i:s'));
        $filter['end_time']   = strtotime(stamp2date($filter['end_time'], 'Y-m-d H:i:s'));
        $where .= " AND o.add_time BETWEEN {$filter['start_time']} AND {$filter['end_time']} ";
    } else {
        $filter['start_time'] = strtotime(date('Y-m-01 00:00:00'));
        $filter['end_time']   = time();
        $where .= " AND o.add_time BETWEEN {$filter['start_time']} AND {$filter['end_time']} ";
    }

    // 统计来自各个平台的二部销量
    $sql_select = 'SELECT SUM(o.final_amount) final_amount,COUNT(*) order_num,r.role_name,o.team FROM '.
        $GLOBALS['ecs']->table('order_info').' o,'.$GLOBALS['ecs']->table('role')." r $where AND order_status".
        ' IN (1,5) AND r.role_id=o.team AND o.platform IN ('.KEFU2.') AND o.team<>o.platform AND shipping_status<>3 GROUP BY o.team';
    $res = $GLOBALS['db']->getAll($sql_select);

    $sql_select = "SELECT SUM(final_amount) total_amount, COUNT(*) total, %s FROM ".$GLOBALS['ecs']->table('order_info').
        " o $where AND order_status IN (1,5)  %s AND shipping_status<>3 GROUP BY %s";
    // 统计各个线上平台的所有销量
    $each_role = $GLOBALS['db']->getAll(sprintf($sql_select,'team',' AND team IN('.ONLINE_STORE.')','team'));
    //$offline_each_role = $GLOBALS['db']->getAll(sprintf($sql_select,'platform team',' AND platform IN('.KEFU2.')','platform'));
    //$each_role = array_merge($online_each_role,$offline_each_role);

    $each = array ();
    $total = array(
        'role_name'    => '总计',
        'order_sum'    => 0,
        'final_amount' => 0,
        'total'        => 0,
        'total_amount' => 0,
        'order_ratio'  => 0,
        'amount_ratio' => 0
    );
    foreach ($each_role as $val) {
        $each[$val['team']] = $val;
    }
    unset($val);

    foreach ($res as &$val) {
        if ($each[$val['team']]) {
            $val = array_merge($val, $each[$val['team']]);
            if ($each[$val['team']]['total']) {
                $val['order_ratio']  = sprintf('%.2f%%', bcdiv($val['order_num'], $each[$val['team']]['total'], 4)*100);
                $val['amount_ratio'] = sprintf('%.2f%%', bcdiv($val['final_amount'], $each[$val['team']]['total_amount'], 4)*100);
            }
            $total['order_num'] += $val['order_num'];
            $total['final_amount'] += $val['final_amount'];
            $total['total'] += $val['total'];
            $total['total_amount'] += $val['total_amount'];
        }
    }

    $total['order_ratio']  = sprintf('%.2f%%', bcdiv($total['order_num'], $total['total'], 4)*100);
    $total['amount_ratio'] = sprintf('%.2f%%', bcdiv($total['final_amount'], $total['total_amount'], 4)*100);
    array_push($res,$total);
    return $res;
}

/**
 * 顾客来源统计
 */
function user_source_stats()
{
    // 查询条件
    $filter['admin_id'] = empty($_REQUEST['admin_id']) ? 0 : intval($_REQUEST['admin_id']);
    $filter['from_id']  = empty($_REQUEST['from_id'])  ? 0 : intval($_REQUEST['from_id']);
    $filter['role_id']  = empty($_REQUEST['role_id'])  ? 0 : intval($_REQUEST['role_id']);

    $where = ' WHERE admin_id>0 AND customer_type IN (2,3,4,5,11) ';

    // 客服
    if ($filter['admin_id']) {
        $where .= " AND admin_id={$filter['admin_id']} ";
    }

    // 来源
    if ($filter['from_id']) {
        $where .= " AND from_where={$filter['from_id']} ";
    } else {
        $where .= ' AND from_where IN (1,2,14,16,38) ';
    }

    // 团队
    if ($filter['role_id'] && admin_priv('user_source_all', '', false)) {
        $where .= " AND role_id={$filter['role_id']} ";
    } elseif (!admin_priv('user_source_all', '', false)) {
        $where .= " AND role_id={$_SESSION['role_id']} ";
    }

    $sql_select = 'SELECT COUNT(*) user_num,from_where,admin_id,role_id FROM '.
        $GLOBALS['ecs']->table('users').$where.' GROUP BY from_where,admin_id ';
    $aResult = $GLOBALS['db']->getAll($sql_select);
    $aStats = array();
    foreach ($aResult as $aVal){
        $aStats[$aVal['role_id']][$aVal['admin_id']][$aVal['from_where']] = $aVal['user_num'];
        $aSource_used_list[] = $aVal['from_where'];
        @$aRole_stats[$aVal['role_id']][$aVal['from_where']] += $aVal['user_num'];
    }

    return array('aStats'=>$aStats, 'aRole_stats'=>$aRole_stats, 'aSource_used_list'=>array_unique($aSource_used_list));
}

/**
 * 顾客分布
 */
function user_region_stats()
{
    $where = '';
    if (admin_priv('all', '', false)) {
    } elseif (admin_priv('user_stats_part', '', false)) {
        $where = " AND u.role_id={$_SESSION['role_id']} ";
    }

    // 获取每个客服的所有顾客
    $sql_select = 'SELECT COUNT(*) user_number,admin_name FROM '.$GLOBALS['ecs']->table('users').
        " u WHERE u.admin_id>0 $where AND customer_type IN (2,3,4,5,11) GROUP BY admin_id ORDER BY u.role_id";
    $result = $GLOBALS['db']->getAll($sql_select);
    $total = array();
    foreach ($result as $val){
        $total[$val['admin_name']] = $val['user_number'];
    }

    // 获取指定地区的每个客服的顾客数量
    $sql_select = 'SELECT COUNT(*) user_number,admin_id,admin_name,r.region_name city FROM '.$GLOBALS['ecs']->table('users').
        ' u,'.$GLOBALS['ecs']->table('user_address').' a,'.$GLOBALS['ecs']->table('region')." r WHERE a.user_id=u.user_id $where"
        .' AND u.admin_id>0 AND a.city IN (110100,310100,440100,440300) AND customer_type IN (2,3,4,5,11) AND a.city=r.region_id GROUP BY a.city,u.admin_id ORDER BY u.role_id';
    $result = $GLOBALS['db']->getAll($sql_select);

    $user_num = array();
    foreach ($result as $val){
        $user_num[$val['admin_name']]['total']['user_number'] = $total[$val['admin_name']];
        $user_num[$val['admin_name']][$val['city']] = $val;
    }

    // 获取广东省顾客数量
    $sql_select = 'SELECT COUNT(*) user_number, admin_name FROM '.$GLOBALS['ecs']->table('users').' u,'.
        $GLOBALS['ecs']->table('user_address').' a, '.$GLOBALS['ecs']->table('region')." r WHERE u.admin_id>0 $where".
        ' AND a.province=440000 AND r.region_id=a.province AND a.user_id=u.user_id AND customer_type IN (2,3,4,5,11) GROUP BY a.province,u.admin_id ORDER BY u.role_id';
    $gd_total = $GLOBALS['db']->getAll($sql_select);
    foreach ($gd_total as $val){
        $user_num[$val['admin_name']]['广东省']['user_number'] = $val['user_number'];
    }

    $sql_select = 'SELECT a.user_name,r.role_name FROM '.$GLOBALS['ecs']->table('admin_user').' a,'.
        $GLOBALS['ecs']->table('role').' r WHERE r.role_id=a.role_id AND status>0 AND stats>0';
    $admin_list = $GLOBALS['db']->getAll($sql_select);
    $role = array();
    foreach ($admin_list as $val){
        if ($val['user_name'] == '会员部临时账号') {
            $role[$val['role_name']][] = '会员部';
        } else {
            $role[$val['role_name']][] = $val['user_name'];
        }
    }

    $temp = array();
    foreach ($user_num as $key=>$val){
        foreach ($role as $k=>$v){
            if (in_array($key, $v)) {
                $temp[$k][$key] = $val;
            }
        }
    }

    foreach ($temp as $key=>$val){
        foreach ($val as $v){
            foreach ($v as $k=>$a){
                @$temp[$key]['共计'][$k]['user_number'] += $a['user_number'];
            }
        }
    }

    return $temp;
}

/**
 * 按功效统计顾客数量
 */
function user_stats_effect()
{
    $where = '';
    if (admin_priv('user_stats_part', '', false)) {
        $where = " AND u.role_id>31 ";
    } elseif (admin_priv('user_stats_effect', '', false)) {
        $where = " AND u.admin_id={$_SESSION['admin_id']}";
    }

    if ($_REQUEST['role_id']) {
        $where .= ' AND u.role_id='.intval($_REQUEST['role_id']);
    }

    // 按功效统计每个客服的顾客数量
    $sql_select = 'SELECT COUNT(*) user_num,u.admin_id,u.admin_name,u.eff_id FROM '.$GLOBALS['ecs']->table('users')
        ." u WHERE u.admin_id>0 AND u.customer_type IN (2,3,4,5,11) $where GROUP BY u.eff_id,u.admin_id ORDER BY u.role_id DESC";
    $result = $GLOBALS['db']->getAll($sql_select);

    $final = array();
    $total = array();
    foreach ($result as $val){
        @$final[$val['admin_name']][$val['eff_id']] = $val['user_num'];
        @$total['共计'][$val['eff_id']] += $val['user_num'];
    }

    $final += $total;

    return $final;
}

/**
 * 每月新增顾客
 */
function user_stats_monthly ()
{
    $filter['end_time']   = empty($_REQUEST['end_time'])   ? time()               : strtotime(stamp2date($_REQUEST['end_time'], 'Y-m-d'));
    $filter['start_time'] = empty($_REQUEST['start_time']) ? strtotime(date('Y-m-01 00:00:00', $filter['end_time'])) : strtotime(stamp2date($_REQUEST['start_time'], 'Y-m-d'));
    $filter['role_id']    = empty($_REQUEST['role_id'])    ? 0 : intval($_REQUEST['role_id']);

    $where = '';

    // 统计的时间范围  默认为当前时间 向前推12个月
    if ($filter['end_time'] > $filter['start_time']) {
        $where .= " AND add_time BETWEEN {$filter['start_time']} AND {$filter['end_time']} ";
    } else {
        $where .= " AND add_time BETWEEN {$filter['end_time']} AND {$filter['start_time']} ";
    }

    // 获取时间标签
    $sql_select = 'SELECT DISTINCT FROM_UNIXTIME(add_time, "%Y-%m") FROM '.$GLOBALS['ecs']->table('users')." WHERE 1 $where";
    $date_list = $GLOBALS['db']->getCol($sql_select);

    // 统计的团队条件
    if (admin_priv('user_stats_monthly_all', '', false)) {
        if ($filter['role_id']) {
            $where .= " AND role_id={$filter['role_id']} ";
        } else {
            $where .= ' AND role_id IN ('.OFFLINE_SALE.') ';
        }
    } elseif (admin_priv('user_stats_monthly_part', '', false)) {
        $where .= " AND role_id={$_SESSION['role_id']} ";
    } elseif (admin_priv('user_stats_monthly_row', '', false)) {
    }


    $sql_select = 'SELECT COUNT(*) user_num,FROM_UNIXTIME(add_time,"%Y-%m") date_month,admin_id,role_id FROM '.
        $GLOBALS['ecs']->table('users')." WHERE admin_id>0 AND customer_type IN (2, 3, 4, 5, 11) $where ".
        ' GROUP BY DATE_FORMAT(FROM_UNIXTIME(add_time), "%Y-%m"),admin_id ORDER BY role_id';
    $result = $GLOBALS['db']->getAll($sql_select);
    $stats = array();
    $final = array();
    foreach ($result as $val){
        $stats[$val['admin_id']][$val['date_month']] = $val['user_num'];
        @$final[$val['role_id']]['共计'][$val['date_month']] += $val['user_num'];
    }

    // 获取客服列表
    $sql_select = 'SELECT user_id,user_name,role_id FROM '.$GLOBALS['ecs']->table('admin_user').
        ' WHERE stats=1 AND role_id IN ('.OFFLINE_SALE.')';
    if (!admin_priv('all', '', false) && $filter['role_id']) {
        $sql_select .= " AND role_id={$filter['role_id']} ";
    }
    $result = $GLOBALS['db']->getAll($sql_select);

    $stats_tmp = array();
    foreach ($result as $val){
        $stats_tmp[$val['role_id']][$val['user_id']] = isset($stats[$val['user_id']]) ? $stats[$val['user_id']] : 0;
    }

    $admin_list = array();
    foreach ($result as $val){
        $admin_list[$val['user_id']] = $val['user_name'];
    }

    @$admin_list['共计'] = '共计';

    foreach ($stats_tmp as $key=>&$val) {
        foreach ($final as $k=>$v) {
            if ($key==$k) {
                $val += $v;
            }
        }
    }

    return array('stats'=>$stats_tmp, 'admin_list'=>$admin_list, 'date_list'=>$date_list);
}

/**
 * 顾客性别统计
 */
function user_sex_stats()
{
    $aFilter['admin_id'] = empty($_REQUEST['admin_id']) ? 0 : intval($_REQUEST['admin_id']);
    $aFilter['role_id']  = empty($_REQUEST['role_id'])  ? 0 : intval($_REQUEST['role_id']);
    $aFilter['sex']      = empty($_REQUEST['sex'])      ? 0 : intval($_REQUEST['sex']);

    $sWhere = ' AND customer_type IN (2, 3, 4, 5, 11)';
    // 客服
    if ($aFilter['admin_id'] && admin_priv('user_sex_stats', '', false)) {
        $sWhere .= " AND admin_id={$aFilter['admin_id']} ";
    } elseif (!admin_priv('user_sex_stats', '', false)) {
        $sWhere .= " AND admin_id={$_SESSION['admin_id']} ";
    }

    // 团队
    if ($aFilter['role_id'] && admin_priv('user_sex_stats', '', false)) {
        $sWhere .= " AND role_id={$aFilter['role_id']} ";
    } elseif (!admin_priv('user_sex_stats', '', false)) {
        $sWhere .= " AND role_id={$_SESSION['role_id']} ";
    }

    // 性别
    if ($aFilter['sex']) {
        $sWhere .= " AND sex={$aFilter['sex']} ";
    }

    $sql_select = 'SELECT COUNT(*) user_num,sex,admin_id,role_id FROM '.$GLOBALS['ecs']->table('users').
        " WHERE admin_id>0 $sWhere GROUP BY sex,admin_id";
    $aResult = $GLOBALS['db']->getAll($sql_select);
    $aUser_stats = array();
    $aRole_stats = array();
    foreach ($aResult as $val){
        $aUser_stats[$val['role_id']][$val['admin_id']][$val['sex']] = $val['user_num'];
        @$aRole_stats[$val['role_id']][$val['sex']] += $val['user_num'];
    }

    return array('aUser_stats'=>$aUser_stats, 'aRole_stats'=>$aRole_stats);
}

/**
 * 顾客购买力统计
 */
function user_buy_stats()
{
    // 统计条件
    $filter['end_time']   = empty($_REQUEST['end_time'])   ? time() : strtotime($_REQUEST['end_time']);
    $filter['start_time'] = empty($_REQUEST['start_time']) ? strtotime(date('Y-m-01 00:00:00', $filter['end_time'])) : strtotime($_REQUEST['start_time']);
    if ($filter['start_time'] > $filter['end_time']) {
        $time_tmp = $filter['start_time'];
        $filter['start_time'] = $filter['end_time'];
        $filter['end_time'] = $time_tmp;
        unset($time_tmp);
    }

    $where = " AND add_time>{$filter['start_time']} AND add_time<{$filter['end_time']} ";

    // 获取满足时间条件的所有订单数量
    $sql_select = 'SELECT SUM(final_amount) amount,COUNT(*) order_num, user_id, admin_id,platform FROM '.$GLOBALS['ecs']->table('order_info').
        " WHERE order_status IN (1,5) AND shipping_status IN (0,1,2) AND final_amount>0 $where GROUP BY user_id ORDER BY add_time ASC";
    $aResult = $GLOBALS['db']->getAll($sql_select);

    // 统计满足金额条件的订单数量
    foreach ($aResult as $val){
        if ($val['amount'] < 500) {
            $aStats[$val['platform']][$val['admin_id']][500][] = $val;
        } elseif ($val['amount'] < 1000) {
            $aStats[$val['platform']][$val['admin_id']][1000][] = $val;
        } elseif ($val['amount'] < 1500) {
            $aStats[$val['platform']][$val['admin_id']][1500] = $val;
        } elseif ($val['amount'] < 5000) {
            $aStats[$val['platform']][$val['admin_id']][5000][] = $val;
        } else {
            $aStats[$val['platform']][$val['admin_id']]['max_up'][] = $val;
        }
    }

    $aTotal = array();
    $aRole_total = array();
    foreach ($aStats as $key=>$val){
        foreach ($val as $k=>$v){
            foreach ($v as $e=>$a){
                $tmp = count($a);
                $aTotal[$key][$k][$e] = $tmp;
                @$aRole_total[$key][$e] += $tmp;
            }
        }
    }

    return array('aStats'=>$aStats, 'aTotal'=>$aTotal, 'aRole_stats'=>$aRole_total);
}

/**
 * 统计退货
 */
function stats_return_order ($status, $group)
{
    $where = ' WHERE i.order_status=5 AND i.shipping_status=4 AND i.order_id=r.order_id AND role.role_id=i.platform '.$status.$group;
    $sql_select = 'SELECT COUNT(*) order_num, SUM(i.final_amount) final_amount, role.role_describe platform,role.depart_id FROM '.
        $GLOBALS['ecs']->table('order_info').' i,'.$GLOBALS['ecs']->table('returns_order').' r, '.$GLOBALS['ecs']->table('role').' role'.$where;
    $result = $GLOBALS['db']->getAll($sql_select);

    $final = array();
    $temp  = array();
    $key   = $_REQUEST['group_by'] ? 'depart_id' : 'platform';

    foreach ($result as $val){
        @$final[$val[$key]]['order_num']    += $val['order_num'];
        @$final[$val[$key]]['final_amount'] += $val['final_amount'];

        @$temp['合计']['order_num'] += $val['order_num'];
        @$temp['合计']['final_amount'] = bcadd($temp['合计']['final_amount'], $val['final_amount'], 2);
    }

    $final += $temp;

    return $final;
}

/**
 * 获取所辖销售平台
 */
function get_manage_platform() {
    $action_list = implode("','", explode(',', $_SESSION['action_list']));
    $sql_select = 'SELECT role_id FROM '.$GLOBALS['ecs']->table('role')." WHERE action IN ('$action_list')";
    $platform_list = $GLOBALS['db']->getCol($sql_select);

    if (empty($platform_list)) {
        return $_SESSION['role_id'];
    } else {
        return implode(',', $platform_list);
    }
}

/**
 * 统计订单
 */
function stats_order_amount($ex_where, $ex_group = '', $left_join = '') {
    // 订单月份
    $filter['start_time'] = isset($_REQUEST['start_time']) ? strtotime($_REQUEST['start_time']) : '';
    $filter['end_time']   = isset($_REQUEST['end_time'])   ? strtotime($_REQUEST['end_time'])   : '';

    // 客服
    $filter['admin_id'] = empty($_REQUEST['admin_id']) ? '' : intval($_REQUEST['admin_id']);

    // 下单时间
    $where_field = empty($left_join) ? 'i.add_time' : 'r.return_time';
    $start_time  = $filter['start_time'] ?: strtotime(date('Y-m-01 00:00:00'));
    $end_time    = $filter['end_time']   ?: strtotime(date('Y-m-t 23:59:59'));
    $ex_where .= sprintf(' AND %s BETWEEN %s AND %s ',$where_field,$start_time,$end_time+24*3600);

    if (admin_priv('part_stats', '', false)) {
        $filter['admin_id'] && $ex_where .= " AND i.admin_id={$filter['admin_id']}";
    } else {
        $ex_where .= " AND i.admin_id={$_SESSION['admin_id']} ";
    }

    $ex_field = empty($left_join) ? 'i.add_time' : 'r.return_time';
    $sql_select = "SELECT COUNT(*) order_num,SUM(i.final_amount) final_amount,FROM_UNIXTIME($ex_field,'%Y-%m-%d') add_time,".
        "i.admin_id,i.admin_name FROM ".$GLOBALS['ecs']->table('order_info').' i LEFT JOIN '.$GLOBALS['ecs']->table('admin_user').
        " a ON a.user_id=i.admin_id $left_join WHERE i.order_status IN (1,5) AND $ex_where $ex_group";
    $res = $GLOBALS['db']->getAll($sql_select);

    $everyday_sales = array();
    foreach ($res as $val){
        $key = $ex_group == ' GROUP BY i.admin_id' ? $val['admin_name'] : $val['add_time'];
        unset($val['add_time'], $val['admin_name'], $val['admin_id']);
        $everyday_sales[$key]['order_num']    = $val['order_num'];
        $everyday_sales[$key]['final_amount'] = $val['final_amount'];
    }

    $final_sales = array();
    if (empty($ex_group)) {
        foreach ($everyday_sales as $key=>$val){
            $final_sales['order_num']    = $val['order_num'];
            $final_sales['final_amount'] = $val['final_amount'];
            if ($val['order_num'] > 0) {
                $final_sales['PCT'] = number_format(round($val['final_amount']/$val['order_num'], 2), 2);
            } else {
                $final_sales['PCT'] = 0;
            }
        }
    } else {
        foreach ($everyday_sales as $key=>$val){
            $final_sales['order_num'][$key]    = $val['order_num'];
            $final_sales['final_amount'][$key] = $val['final_amount'];
            if ($val['order_num'] > 0) {
                $final_sales['PCT'][$key] = number_format(round($val['final_amount']/$val['order_num'], 2), 2);
            } else {
                $final_sales['PCT'][$key] = 0;
            }
        }
    }

    return $final_sales;
}

/**
 * 合并数据
 */
function _array_merge($arr1, $arr2)
{
    return array_merge($arr1,$arr2);
}


/**
 * 统计销售明细：各支付方式、各货到付款配送方式
 */
function sale_detail()
{
    if (!empty($_REQUEST['order_month'])) {
        $current_time = strtotime($_REQUEST['order_month']);

        $filter['start_time'] = date('Y-m-01 00:00:00', $current_time);
        $filter['end_time']   = date('Y-m-t 23:59:59', $current_time);
    } else {
        $filter['start_time'] = date('Y-m-01 00:00:00');
        $filter['end_time']   = date('Y-m-t 23:59:59');
    }

    $start_time = strtotime($filter['start_time']);
    $end_time   = strtotime($filter['end_time']);

    // 统计在线支付的订单金额
    $sql_select = 'SELECT SUM(final_amount) final_amount, COUNT(*) order_num, p.pay_id, r.role_id FROM '.
        $GLOBALS['ecs']->table('order_info').' i LEFT JOIN '.$GLOBALS['ecs']->table('payment').
        ' p ON i.pay_id=p.pay_id LEFT JOIN '.$GLOBALS['ecs']->table('role').' r ON r.role_id=i.team WHERE '.
        'i.order_status=5 AND i.shipping_status IN (0,1,2) AND i.order_type<>1 AND i.order_type<100 AND p.is_cod=0 '.
        " AND i.add_time BETWEEN $start_time AND $end_time GROUP BY i.pay_id,i.team";
    $online_pay = $GLOBALS['db']->getAll($sql_select);
    $online = array();
    foreach ($online_pay as $val){
        $online[$val['pay_id']][$val['role_id']] = $val;
    }
    unset($val);

    // 统计线下支付、货到付款等订单金额
    $sql_select = 'SELECT SUM(final_amount) final_amount, COUNT(*) order_num,s.shipping_id,r.role_id FROM '.
        $GLOBALS['ecs']->table('order_info').' i LEFT JOIN '.$GLOBALS['ecs']->table('role').
        ' r ON r.role_id=i.team LEFT JOIN '.$GLOBALS['ecs']->table('payment').' p ON p.pay_id=i.pay_id LEFT JOIN '.
        $GLOBALS['ecs']->table('shipping').' s ON i.shipping_id=s.shipping_id WHERE i.order_status=5 AND i.shipping_status'.
        " IN (0,1,2) AND i.order_type<>1 AND i.order_type<100 AND p.is_cod=1 AND i.add_time BETWEEN $start_time AND $end_time GROUP BY i.pay_id,i.team,i.shipping_id";
    $offline_pay = $GLOBALS['db']->getAll($sql_select);
    $offline = array();
    foreach ($offline_pay as $val){
        $offline[$val['shipping_id']][$val['role_id']] = $val;
    }

    $result = array('online' => $online, 'offline' => $offline);
    return $result;
}

/**
 * 计算客单价
 */
function calc_pct($arr) {
    foreach ($arr['order_num'] as $key=>$val){
        if ($val['order_num'] > 0) {
            $arr['PCT'][$key] = round($arr['final_amount'][$key]/$val, 2);
        } else {
            $val['PCT'][$key] = 0;
        }
    }

    return $arr;
}

/**
 * 计算客服的顾客回购率
 */
function repo_rate () {

    $filter['role_id']  = isset($_REQUEST['platform']) && !empty($_REQUEST['platform']) ? intval($_REQUEST['platform']) : 0;
    $filter['admin_id'] = isset($_REQUEST['admin_id']) && !empty($_REQUEST['admin_id']) ? intval($_REQUEST['admin_id']) : 0;

    $ex_where = '';
    // 按部门 获取需要计算回购率的客服
    // 中老年、会员部 客服列表
    if (admin_priv('personal_repo_all', '', false)) {
        // 部门
        $ex_where = $filter['role_id'] ? " AND u.role_id={$filter['role_id']} " : ' AND u.role_id IN ('.OFFLINE_SALE.') ';
        // 客服
        $ex_where .= $filter['admin_id'] ? " AND u.admin_id={$filter['admin_id']} " : '';
    } elseif (admin_priv('personal_repo_part', '', false)) { // 所属部门客服
        if (admin_priv('personal_repo_trans-part', '', false)) {
            $trans_role_list = trans_part_list();
            $admin_list = admin_list_by_role($trans_role_list);
            if ($admin_list === false) {
                $admin_list = get_admin_tmp_list($_SESSION['role_id']);
            }

            $ex_where = ' AND u.role_id IN ('.implode(',', $trans_role_list).')';
        } else {
            $admin_list = get_admin_tmp_list($_SESSION['role_id']);
            $ex_where = " AND u.role_id={$_SESSION['role_id']} ";
        }

        // 个人
        $ex_where .= $filter['admin_id'] ? " AND u.admin_id={$filter['admin_id']} " : '';
    } elseif (admin_priv('personal_repo_row', '', false)) { // 所属小组客服
        // 小组
    } else {
        // 客服
        $_REQUEST['admin_id'] = $_SESSION['admin_id'];
        $admin_list[$_SESSION['admin_id']] = $_SESSION['admin_name'];

        $ex_where = " AND u.admin_id={$_SESSION['admin_id']} ";
    }

    // 时间
    if (empty($_REQUEST['start_time']) || empty($_REQUEST['end_time'])) {
        $filter['start_time'] = date('Y-m-01 00:00:00');
        $filter['end_time']   = date('Y-m-t 23:59:59');
    } else {
        $filter['start_time'] = trim($_REQUEST['start_time']);
        $filter['end_time']   = trim($_REQUEST['end_time']);
    }

    $time_limit = ' AND i.add_time BETWEEN '.strtotime($filter['start_time']).' AND '.strtotime($filter['end_time']);
    // 取该段时间之前的有过购买记录的顾客
    $before_time = ' AND i.user_id IN (SELECT user_id FROM '.$GLOBALS['ecs']->table('order_info').
        ' WHERE order_status IN (1,5) AND shipping_status IN (0,1,2) AND team<>23 AND final_amount>0 AND team>0 AND add_time<'.
        strtotime($filter['start_time']).')';

    $sql_select = 'SELECT SUM(i.final_amount) purchases_amount,COUNT(i.order_id) purchases_number,u.admin_id,u.admin_name,u.user_id FROM '.
        $GLOBALS['ecs']->table('users').' u,'.$GLOBALS['ecs']->table('order_info').
        ' i WHERE i.order_status IN (1,5) AND i.shipping_status IN (0,1,2) AND i.team<>23 '.
        ' AND u.user_id=i.user_id AND i.final_amount>0 AND i.team>0 AND u.admin_id>0 ';
    $owner_order = 'AND i.admin_id=u.admin_id AND i.order_type=4 ';
    $group_by = ' GROUP BY i.user_id';
    $having_count = ' HAVING COUNT(order_id)>1 ';
    // 获取老顾客当期的有效订单
    $repo_list = $GLOBALS['db']->getAll($sql_select.$owner_order.$ex_where.$time_limit.$before_time.$group_by);
    $calc_repo_current = calc_total($repo_list);

    // 获取当期所有的有效订单
    $purchases = $GLOBALS['db']->getAll($sql_select.$owner_order.$ex_where.$time_limit.$group_by);
    $calc_total_current = calc_total($purchases);

    // 获取所有老顾客的订单总量
    $repo_total_list = $GLOBALS['db']->getAll($sql_select.$ex_where.$group_by.$having_count);
    $calc_repo_total = calc_total($repo_total_list);

    // 获取顾客所有有效订单
    $purchases_total = $GLOBALS['db']->getAll($sql_select.$ex_where.$group_by);
    $calc_total_all = calc_total($purchases_total);

    // 统计每位客服的顾客数量
    //$sql_select = 'SELECT COUNT(DISTINCT user_id) users_number, admin_id, admin_name FROM '.
        //$GLOBALS['ecs']->table('users').' WHERE admin_id>0 AND customer_type IN (2,3,4,5,11) AND role_id<>23 GROUP BY admin_id';
    $sql_select = 'SELECT COUNT(DISTINCT user_id) users_number, admin_id, admin_name FROM '.
        $GLOBALS['ecs']->table('users').' u WHERE u.admin_id>0 AND u.customer_type NOT IN(5,6,7,21) AND u.user_id IN (SELECT user_id FROM '.$GLOBALS['ecs']->table('order_info').
        ' WHERE order_status IN (1,5) AND shipping_status IN (0,1,2) AND team<>23 AND final_amount>0 AND order_type IN (3,4,5,6,7))';
    $users_number = $GLOBALS['db']->getAll($sql_select.$ex_where.' GROUP BY admin_id');

    $users_list  = array();
    $users_total = 0;
    foreach ($users_number as $val){
        $users_list[$val['admin_id']] = $val;
        $users_total += $val['users_number'];
    }

    // 计算每位客服的顾客回购率
    $merge_data = array ();
    foreach ($calc_total_all as $key=>$val) {
        // 全部订单
        @$merge_data[$key]['total_all_users_number'] = $val['users_number'];
        @$merge_data[$key]['total_all_order_number'] = $val['order_number'];
        @$merge_data[$key]['total_all_order_amount'] = $val['order_amount'];

        // 当期所有订单
        @$merge_data[$key]['total_current_users_number'] = $calc_total_current[$key]['users_number'];
        @$merge_data[$key]['total_current_order_number'] = $calc_total_current[$key]['order_number'];
        @$merge_data[$key]['total_current_order_amount'] = $calc_total_current[$key]['order_amount'];

        // 老顾客所有订单
        @$merge_data[$key]['repo_total_users_number'] = $calc_repo_total[$key]['users_number'];
        @$merge_data[$key]['repo_total_order_number'] = $calc_repo_total[$key]['order_number'];
        @$merge_data[$key]['repo_total_order_amount'] = $calc_repo_total[$key]['order_amount'];

        // 老顾客当期订单
        @$merge_data[$key]['repo_current_users_number'] = $calc_repo_current[$key]['users_number'];
        @$merge_data[$key]['repo_current_order_number'] = $calc_repo_current[$key]['order_number'];
        @$merge_data[$key]['repo_current_order_amount'] = $calc_repo_current[$key]['order_amount'];

        @$merge_data[$key]['admin_name']   = $users_list[$key]['admin_name'];
        @$merge_data[$key]['users_number'] = $users_list[$key]['users_number'];
    }
    unset($key, $val);

    // 计算百分比
    foreach ($merge_data as $key=>&$val) {
        // 老顾客占顾客总量
        @$val['users_current_old_vs_total']=sprintf('%.2f%%',bcdiv($val['repo_current_users_number'],$val['users_number'],4)*100);

        // 订单金额比 当期
        @$val['order_current_old_vs_total']=sprintf('%.2f%%',bcdiv($val['repo_current_order_amount'],$val['total_all_order_amount'],4)*100);

        // 顾客数量比
        @$val['users_old_vs_total'] = sprintf('%.2f%%', bcdiv($val['repo_total_users_number'],$val['users_number'],4)*100);

        // 订单数量比
        @$val['order_number_old_vs_total'] = sprintf('%.2f%%', bcdiv($val['repo_total_order_number'],$val['total_all_order_number'],4)*100);

        // 订单金额比 所有的订单
        @$val['order_amount_old_vs_total'] = sprintf('%.2f%%', bcdiv($val['repo_total_order_amount'],$val['total_all_order_amount'],4)*100);
    }


    @$merge_data['total']['admin_name']         = '总计';
    @$merge_data['total']['users_number']       = $users_total;
    @$merge_data['total']['users_old_vs_total'] = sprintf('%.2f%%', bcdiv($merge_data['total']['repo_total_users_number'],$users_total,4)*100);
    @$merge_data['total']['users_current_old_vs_total'] = sprintf('%.2f%%', bcdiv($merge_data['total']['repo_current_users_number'],$users_total,4)*100);

    krsort($merge_data);

    return $merge_data;
}

/**
 * 计算订单数据
 */
function calc_total ($data)
{
    $calc_total = array();
    foreach ($data as $val){
        @$calc_total[$val['admin_id']]['users_number'] += 0 + 1;
        @$calc_total[$val['admin_id']]['order_number'] += $val['purchases_number'];
        @$calc_total[$val['admin_id']]['order_amount'] = bcadd($calc_total[$val['admin_id']]['order_amount'], $val['purchases_amount'], 2);

        @$calc_total['total']['users_number'] += 0 + 1;
        @$calc_total['total']['order_number'] += $val['purchases_number'];
        @$calc_total['total']['order_amount'] = bcadd($calc_total['total']['order_amount'], $val['purchases_amount'], 2);
    }
    unset($val);

    return $calc_total;
}

/**
 * 统计客服销量
 * @param timestamp $start 开始时间
 * @param timestamp $end   结束时间
 * @param string    $condition  查询条件
 */
function stats_personal_sales ($start, $end, $condition) {
    $sql_select = 'SELECT SUM(final_amount) final_amount,COUNT(*) num,admin_id,admin_name FROM '.
        $GLOBALS['ecs']->table('order_info').' WHERE order_status IN (1,5) AND shipping_status<>3 '.
        " AND add_time BETWEEN $start AND $end $condition";
    return $GLOBALS['db']->getAll($sql_select);
}

/**
 * 统计当月的退货数量
 */
function stats_returns_sales ($start, $end, $admin_list) {
    $sql_select = 'SELECT COUNT(*) num,SUM(i.final_amount) final_amount,i.admin_name ,i.admin_id FROM '.
        $GLOBALS['ecs']->table('order_info').' i LEFT JOIN '.$GLOBALS['ecs']->table('returns_order').
        " r ON r.order_id=i.order_id WHERE r.return_time BETWEEN $start AND $end AND i.admin_id IN ($admin_list)".
        " AND i.order_type IN (4,5,6) GROUP BY i.admin_id";
    return $GLOBALS['db']->getAll($sql_select);
}

//当月15日到次月15日的退货计算规则
function return_sales_report($start,$admin_list){
    $end = strtotime(date('Y-m-15',$start));
    $sql_select = 'SELECT COUNT(*) num,SUM(i.final_amount) final_amount,i.admin_name ,i.admin_id,i.add_time order_time FROM '.
        $GLOBALS['ecs']->table('order_info').' i LEFT JOIN '.$GLOBALS['ecs']->table('returns_order')
        ." r ON r.order_id=i.order_id WHERE r.return_time BETWEEN $start AND $end AND i.add_time<$start "
        ." AND i.admin_id IN ($admin_list) AND i.order_type IN (4,5,6) GROUP BY i.admin_id";
    return $GLOBALS['db']->getAll($sql_select);
}

/**
 * 获取客服目标销量
 */
function get_saler_target ($start = 0, $end = 0)
{
    $month_start = $start ? $start : strtotime(date('Y-m-01 00:00:00'));
    $month_end   = $end   ? $end   : strtotime(date('Y-m-t 23:59:59'));

    $sql_select = 'SELECT sales_target,admin_id,group_id,role_id FROM '.$GLOBALS['ecs']->table('sales_target').
        " WHERE month_target BETWEEN $month_start AND $month_end";
    $target_list = $GLOBALS['db']->getAll($sql_select);

    $target = array ();
    foreach ($target_list as $val){
        $target[$val['admin_id']] = $val;
    }

    return $target;
}

/**
 * 统计顾客档案
 */
function users_data_stats()
{
    $final = array();
    $sql_select = 'SELECT COUNT(*) user_number,d.admin_id,a.user_name,user_type FROM '.$GLOBALS['ecs']->table('users_data_stats').' d,'.
        $GLOBALS['ecs']->table('admin_user').'a WHERE a.user_id=d.admin_id AND d.handle_time BETWEEN %s AND %s GROUP BY d.admin_id,d.user_type';

    // 当天
    $current_start = strtotime(date('Y-m-d 00:00:00'));
    $current_end   = strtotime(date('Y-m-d 23:59:59'));
    $res['current'] = $GLOBALS['db']->getAll(sprintf($sql_select, $current_start, $current_end));
    if (!empty($res['current'])) {

        $current = array();
        foreach ($res['current'] as $val){
            $final['current'][$val['user_name']][$val['user_type']] = $val['user_number'];
            @$final['current'][$val['user_name']]['total'] += $val['user_number'];
            @$final['current'][$val['user_name']]['efficient'] = sprintf('%.2f%%', bcdiv($final['current'][$val['user_name']][4],$final['current'][$val['user_name']]['total'], 4) *100);

            // 总计
            @$current['合计'][$val['user_type']] += $val['user_number'];
            @$current['合计']['total'] += $val['user_number'];
        }

        @$current['合计']['efficient'] = sprintf('%.2f%%', bcdiv($current['合计'][4],$current['合计']['total'], 4) *100);
        @$final['current'] += $current;
        unset($val,$current);
    }

    // 前一天
    $last_end    = $current_start -1;
    $last_start  = $current_start -24*3600;
    $res['last'] = $GLOBALS['db']->getAll(sprintf($sql_select, $last_start, $last_end));

    if (!empty($res['last'])) {
        $last = array();
        foreach ($res['last'] as $val){
            $final['last'][$val['user_name']][$val['user_type']] = $val['user_number'];
            @$final['last'][$val['user_name']]['total'] += $val['user_number'];
            @$final['last'][$val['user_name']]['efficient'] = sprintf('%.2f%%', bcdiv($final['last'][$val['user_name']][4],$final['last'][$val['user_name']]['total'], 4) *100);

            // 总计
            @$last['合计'][$val['user_type']] += $val['user_number'];
            @$last['合计']['total'] += $val['user_number'];
        }

        @$last['合计']['efficient'] = sprintf('%.2f%%', bcdiv($last['合计'][4],$last['合计']['total'], 4) *100);
        @$final['last'] += $last;
        unset($val,$last);
    }

    // 当月
    $current_month_start  = strtotime(date('Y-m-01 00:00:00'));
    $current_month_end    = strtotime(date('Y-m-t 23:59:59'));
    $res['current_month'] = $GLOBALS['db']->getAll(sprintf($sql_select, $current_month_start, $current_month_end));

    if (!empty($res['current_month'])) {
        $current_month = array();
        foreach ($res['current_month'] as $val){
            $final['current_month'][$val['user_name']][$val['user_type']] = $val['user_number'];
            @$final['current_month'][$val['user_name']]['total'] += $val['user_number'];
            @$final['current_month'][$val['user_name']]['efficient'] = sprintf('%.2f%%', bcdiv($final['current_month'][$val['user_name']][4],$final['current_month'][$val['user_name']]['total'], 4) *100);

            // 总计
            @$current_month['合计'][$val['user_type']] += $val['user_number'];
            @$current_month['合计']['total'] += $val['user_number'];
        }

        @$current_month['合计']['efficient'] = sprintf('%.2f%%', bcdiv($current_month['合计'][4],$current_month['合计']['total'], 4) *100);
        @$final['current_month'] += $current_month;
        unset($val,$current_month);
    }

    return $final;
}

/**
 * 拆分套餐商品
 */
function package_struct($package_sn, $sales = 1)
{
    $sql_select = "SELECT p.packing_name,p.packing_desc,g.goods_sn,g.goods_name,g.num goods_number,g.num*$sales sales_number FROM "
        .$GLOBALS['ecs']->table('packing').' p LEFT JOIN '.$GLOBALS['ecs']->table('packing_goods').
        ' g ON p.packing_id=g.packing_id '." WHERE package_sn='$package_sn'";
    $package_struct = $GLOBALS['db']->getAll($sql_select);
    return $package_struct;
}

/**
 * 平台销量细分
 */
function get_order_source($platform, $period)
{
    switch ($period) {
    case 'current':
        $start = strtotime(date('Y-m-d 00:00:00')) -6*3600;
        $end   = strtotime(date('Y-m-d 23:59:59')) -6*3600;
        break;
    case 'last_day':
        $start = strtotime(date('Y-m-d 00:00:00')) -30*3600;
        $end   = strtotime(date('Y-m-d 23:59:59')) -30*3600;
        break;
    case 'month':
        $start = strtotime(date('Y-m-01 00:00:00')) -6*3600;
        $end   = strtotime(date('Y-m-t 23:59:59')) -6*3600;
        break;
    }

    $period = " AND i.add_time BETWEEN $start AND $end ";

    $sql_select = 'SELECT SUM(i.final_amount) final_amount,s.source_name,COUNT(*) order_number FROM '.
        $GLOBALS['ecs']->table('order_info').' i LEFT JOIN '.$GLOBALS['ecs']->table('order_source').' s ON s.source_id=i.platform_type '.
        "WHERE i.platform IN ($platform) $period AND order_status IN (1,5) AND shipping_status<>3 GROUP BY platform_type ORDER BY final_amount";
    $sub_sales = $GLOBALS['db']->getAll($sql_select);

    return $sub_sales;
}

/**
 * someFunc
 * @return void
 * @author John Doe
 **/
function product_sales_stats()
{
    if (admin_priv('product_all_sales', '', false)) {
    } elseif (admin_priv('product_part_sales', '', false)) {
    } elseif (admin_priv('product_group_sales', '', false)) {
    } else {
    }

    if (!empty($_REQUEST['months']) && substr_count($_REQUEST['months'], '-')) {
        $fields = 'the_day';
        $tables = 'sales_day';
        $pattern = mysql_real_escape_string($_REQUEST['months']);
    } else {
        $fields = 'the_month';
        $tables = 'sales_month';
        $pattern = empty($_REQUEST['months']) ? date('Y', time()) : mysql_real_escape_string($_REQUEST['months']);
    }

    $sql_select = "SELECT s.$fields order_date,s.goods_sn,s.goods_number,ROUND(s.goods_amount) goods_amount FROM ".
        $GLOBALS['ecs']->table($tables).' s RIGHT JOIN '.$GLOBALS['ecs']->table('goods').
        " g ON s.goods_sn=g.goods_sn WHERE s.$fields LIKE '$pattern-%'";
    $result = $GLOBALS['db']->getAll($sql_select);

    // 整理数据
    $final = array();
    $year_mark = null;
    foreach ($result as $val){
        $final[$val['goods_sn']][substr($val['order_date'], -2)] = $val;
        if (null === $year_mark) {
            $year_mark = substr($val['order_date'], 0, 4);
        }
    }

    return array('sales'=>$final, 'year_mark'=>$year_mark);
}

/**
 * 客服产品销量
 */
function service_sales_list ()
{
    if (empty($_REQUEST['months'])) {
        $months = date('Y-m', time());
    } else {
        $months = mysql_real_escape_string($_REQUEST['months']);
    }

    $sql_select = 'SELECT s.the_month,s.goods_sn,s.goods_amount,s.goods_number,g.goods_name,s.admin_id FROM '.
        $GLOBALS['ecs']->table('service_sales').' s,'.$GLOBALS['ecs']->table('goods').' g,'.$GLOBALS['ecs']->table('admin_user').
        " a WHERE s.goods_sn=g.goods_sn AND a.user_id=s.admin_id AND the_month='$months'";
    $result = $GLOBALS['db']->getAll($sql_select);

    $final = array();
    foreach ($result as $val){
        $final[$val['goods_sn']][$val['admin_id']] = $val;
    }

    return $final;
}

/**
 * 获取客服列表
 */
function servicer_list()
{
    if (empty($_REQUEST['months'])) {
        $months = date('Y-m', time());
    } else {
        $months = mysql_real_escape_string($_REQUEST['months']);
    }

    $sql_select = 'SELECT DISTINCT s.admin_id,u.user_name FROM '.$GLOBALS['ecs']->table('service_sales').
        ' s,'.$GLOBALS['ecs']->table('admin_user')." u WHERE u.user_id=s.admin_id AND s.the_month='$months'";
    $result = $GLOBALS['db']->getAll($sql_select);

    $final = array();
    foreach ($result as $val){
        $final[$val['admin_id']] = $val['user_name'];
    }

    return $final;
}

/*员工销售排行*/
function get_sale_performance($start_time,$end_time){

    $result           = get_admin_sql();
    $admin_id_str     = $result['admin_id_str'];
    $sale_performance = $result['admin_id_arr'];

    $where         = " WHERE order_status IN(1,5) AND shipping_status<>3 AND admin_id IN($admin_id_str) AND add_time>=$start_time AND add_time<=$end_time";

    $sql_select = 'SELECT admin_id,SUM(final_amount) as final_amount,COUNT(*) order_num FROM '
        .$GLOBALS['ecs']->table('order_info').$where.' GROUP BY admin_id ';
    $order_list = $GLOBALS['db']->getAll($sql_select);

    if($order_list != null){
        foreach($sale_performance as &$sale){
            foreach($order_list as $key=>$order){
                if($sale['user_id'] == $order['admin_id']){
                    $sale['final_amount'] = $order['final_amount'];
                    $sale['order_num']    = $order['order_num'];
                    unset($order[$key]);
                }
            }
        }
    }

    foreach($sale_performance as &$val){
        $val['final_amount'] = empty($val['final_amount']) ? '0.00' : $val['final_amount'];
        $val['order_num']    = empty($val['order_num']) ? '0' : $val['order_num'];
    }

    return $sale_performance;
}

/*通过权限判断返回查询员工列表SQL语句*/
function get_admin_sql(){
    $admin_where = ' WHERE 1 ';
    if(isset($_REQUEST['admin'])){
        $_REQUEST['admin'] = trim($_REQUEST['admin']);
    }

    if(admin_priv('all','',false)){
        $authority      = 'all';

        if(isset($_REQUEST['platform']) && !empty($_REQUEST['platform'])){
            $admin_where .= " AND role_id={$_REQUEST['platform']} ";
        }else{
            $admin_where .= ' AND role_id IN('.MEMBER_SALE.')';
        }

        if(isset($_REQUEST['group_id']) && !empty($_REQUEST['group_id'])){
            $admin_where .= " AND group_id={$_REQUEST['group_id']} ";
        }

        if(isset($_REQUEST['admin']) && !empty($_REQUEST['admin'])){
            $admin_where .= " AND user_name LIKE '%{$_REQUEST['admin']}%'";
        }
    }elseif(admin_priv('award_platform_view','',false)){
        $authority      = 'role_view';

        if(isset($_REQUEST['group_id']) && !empty($_REQUEST['group_id'])){
            $admin_where .= " AND group_id={$_REQUEST['group_id']} ";
        }

        if(isset($_REQUEST['admin']) && !empty($_REQUEST['admin'])){
            $admin_where .= " AND user_name LIKE '%{$_REQUEST['admin']}%'";
        }

        $admin_where .= " AND platform={$_SESSION['role_id']} ";
    }elseif(admin_priv('award_group_view','',false)){
        $authority      = 'group_view';

        if(isset($_REQUEST['admin']) && !empty($_REQUEST['admin'])){
            $admin_where .= " AND user_name LIKE '%{$_REQUEST['admin']}%'";
        }

        $admin_where .= " AND platform={$_SESSION['role_id']} AND group_id={$_SESSION['group_id']} ";
    }else{
        $authority      = 'general_view';
        $admin_where .= " AND admin_id={$_SESSION['admin_id']} ";
    }

    $admin_sql_select = 'SELECT user_id,user_name FROM '.$GLOBALS['ecs']->table('admin_user').$admin_where;
    $result = $GLOBALS['db']->getAll($admin_sql_select);

    $admin_id_list    = array();

    foreach($result as $val){
        $admin_id_list[] = $val['user_id'];
    }

    if(!count($admin_id_list)){
        $admin_id_list[] = 0;
    }

    $admin_id_str = implode(',',$admin_id_list);

    return array('admin_id_str'=>$admin_id_str,'admin_id_arr'=>$result);
}

/**
 * 拆分套餐
 */
function break_up_package($package) {
    $goods_list = array();
    $package_sn = array();
    foreach ($package as $val){
        $package_sn[] = $val['goods_sn'];
    }
    if (empty($package_sn)) {
        return false;
    }
    $sql_select = 'SELECT package_sn,goods_sn,goods_name,num FROM '
        .$GLOBALS['ecs']->table('packing_goods').' WHERE package_sn IN ("'.implode('","', $package_sn).'")';
    $result = $GLOBALS['db']->getAll($sql_select);
    $package_list = array();
    foreach ($result as $val){
        $package_list[$val['package_sn']][$val['goods_sn']] = $val['num'];
    }
    foreach ($package as $val){
        if (!isset($package_list[$val['goods_sn']])) {
            continue;
        }
        foreach ($package_list[$val['goods_sn']] as $k=>$v){
            if (isset($goods_list[$k])) {
                $goods_list[$k]['goods_num'] += bcmul($v,$val['goods_num']);
            } else {
                $goods_list[$k]['goods_num'] = bcmul($v,$val['goods_num']);
            }
            $goods_list[$k]['turnover'] += $val['turnover'];
        }
    }

    return $goods_list;
}
/**
 *合并套餐数据到单品
 */
function merge_to_single($single, $package) {
    $goods_list = array();
    foreach ($single as $val){
        $goods_list[$val['goods_sn']] = $val;
    }
    foreach ($goods_list as $key=>&$val){
        if (isset($package[$key])) {
            $val['package_num'] = $package[$key]['goods_num'];
            $val['total_num']  += $package[$key]['goods_num'];
            $val['turnover'] += $package[$key]['turnover'];
            $val['wvera_price'] = bcdiv($val['turnover'],$val['total_num'],2);
        } else {
            $val['package_num'] = 0;
        }
    }
    return $goods_list;
}

/**
 * 服务记录统计
 */
function service_stats($start_time, $end_time) {
    if (!empty($_REQUEST['role_id'])) {
        $ex_where = ' AND role_id='.intval($_REQUEST['role_id']);
        $service_where = ' AND platform='.intval($_REQUEST['role_id']);
    }
    $sql_wavtime = 'SELECT w.wav_id,w.admin_id,a.user_name,a.role_id,a.group_id,w.ext,SUM(w.time_info) time_info,SUM(w.number_info) number_info FROM '.
        $GLOBALS['ecs']->table('wavtime').' w, '.$GLOBALS['ecs']->table('admin_user').
        " a WHERE w.admin_id=a.user_id AND a.status>0 AND a.stats>0 AND w.update_time BETWEEN $start_time AND $end_time $ex_where GROUP BY admin_id";
    $wavInfo = $GLOBALS['db']->getAll($sql_wavtime);
    $result = array();
    foreach ($wavInfo as $val) {
        $val['time_info'] = round($val['time_info']/60);
        $val['service_info'] = round($val['time_info']/$val['number_info']);
        $result[$val['admin_id']] = $val;
    }
    return $result;
}
//电话接通率
function connect_stats($start_time, $end_time){
    $parameter = array('act'=>'connect_stats','start_time'=>$start_time,'end_time'=>$end_time);
    $res = data_from_jinlun('missCall.php',$parameter);
    $admin = return_admin();   
    $result = array();
    if ($res) {
        foreach ($admin as &$val) {
            foreach ($res['call_list'] as $r) {
                if ($val['ext'] == $r['clid']) {
                    if ($r['disposition']) {
                        $val['answered'] = $r['total'];
                    }else{
                        $val['noanswered'] = $r['total'];
                    }
                }
            }
            $result[$val['admin_id']] = $val;
        }

        foreach($result as &$v){
            $v['call_num'] = $v['answered']+$v['noanswered'];
            if ($v['call_num']>0) {
                $v['call_rate'] = sprintf('%.2f',$v['answered']/$v['call_num']*100).'%';
            }
        }
    }
    return $result;
}

//报表全部信息
function final_report($today,$yesterday,$month,$pre_month){
    $final = array();
    foreach ($month as $val) {
        $final[$val['admin_id']] = array(
            'admin_id'           => $val['admin_id'],
            'admin_name'         => $val['user_name'],
            'role_id'            => $val['role_id'],
            'group_id'           => $val['group_id'],
            'today_time_info'    => $today[$val['admin_id']]['time_info'],
            'today_number_info'  => $today[$val['admin_id']]['number_info'],
            'today_service_info' => $today[$val['admin_id']]['service_info'],
            'yday_time_info'     => $yesterday[$val['admin_id']]['time_info'],
            'yday_number_info'   => $yesterday[$val['admin_id']]['number_info'],
            'yday_service_info'  => $yesterday[$val['admin_id']]['service_info'],
            'month_time_info'    => $val['time_info'],
            'month_number_info'  => $val['number_info'],
            'month_service_info' => $val['service_info'],
            'pre_month_time_info'     => $pre_month[$val['admin_id']]['time_info'],
            'pre_month_number_info'   => $pre_month[$val['admin_id']]['number_info'],
            'pre_month_service_info'  => $pre_month[$val['admin_id']]['service_info'],
        );
        $today_time_info[]    = $today[$val['admin_id']]['time_info'];
        $today_number_info[]  = $today[$val['admin_id']]['number_info'];
        $today_service_info[] = $today[$val['admin_id']]['service_info'];
        $yday_time_info[]     = $yesterday[$val['admin_id']]['time_info'];
        $yday_number_info[]   = $yesterday[$val['admin_id']]['number_info'];
        $yday_service_info[]  = $yesterday[$val['admin_id']]['service_info'];
        $month_time_info[]    = $val['time_info'];
        $month_number_info[]  = $val['number_info'];
        $month_service_info[] = $val['service_info'];
        $pre_month_time_info[]     = $pre_month[$val['admin_id']]['time_info'];
        $pre_month_number_info[]   = $pre_month[$val['admin_id']]['number_info'];
        $pre_month_service_info[]  = $pre_month[$val['admin_id']]['service_info'];
    }
    $keys = array_keys(current($final));
    foreach ($keys as $val) {
        if (is_array($$val)) {
            $final['total'][$val] = array_sum($$val);
        }
    }
    $final['total']['admin_id'] = $final['total']['role_id'] = $final['total']['group_id'] = 'total';
    $final['total']['admin_name'] = '总计';
    return $final;
}

//常规报表时间
function report_time_list(){
    return array(
        'today_start_time'      => strtotime(date('Y-m-d 00:00:00', $_SERVER['REQUEST_TIME'])),
        'today_end_time'        => strtotime(date('Y-m-d 23:59:59',$_SERVER['REQUEST_TIME'])),
        'yesterday_start_time'  => strtotime(date('Y-m-d 00:00:00', strtotime('-1 day'))),
        'yesterday_end_time'    => strtotime(date('Y-m-d 23:59:59',  strtotime('-1 day'))),
        'month_start_time'      => strtotime(date('Y-m-01 00:00:00', $_SERVER['REQUEST_TIME'])),
        'month_end_time'        => strtotime(date('Y-m-t 23:59:59',  $_SERVER['REQUEST_TIME'])),
        'last_month_start_time' => strtotime(date('Y-m-01 00:00:00', strtotime('-1 month'))),
        'last_month_end_time'   => strtotime(date('Y-m-t 23:59:59',  strtotime('-1 month'))),
    );
}

//电话接通率
function call_final_report($today,$yesterday,$month,$pre_month){
    $final = array();
    foreach ($month as $id=>$val) {
        $final[$val['admin_id']] = array(
            'admin_id'           => $val['admin_id'],
            'admin_name'         => $val['user_name'],
            'role_id'            => $val['role_id'],
            'group_id'           => $val['group_id'],
            'today_call_num'    => $today[$val['admin_id']]['call_num'],
            'today_call_rate'    => $today[$val['admin_id']]['call_rate'],
            'today_answered_num'  => $today[$val['admin_id']]['answered'],
            'today_noanswered_num' => $today[$val['admin_id']]['noanswered'],
            'yday_call_num'     => $yesterday[$val['admin_id']]['call_num'],
            'yday_call_rate'   => $yesterday[$val['admin_id']]['call_rate'],
            'yday_answered_num'  => $yesterday[$val['admin_id']]['answered'],
            'yday_noanswer_num'  => $yesterday[$val['admin_id']]['noanswered'],
            'month_call_num'    => $val['call_num'],
            'month_call_rate'  => $val['call_rate'],
            'month_answered_num' => $val['answered'],
            'month_noanswered_num' => $val['noanswered_num'],
            //'pre_month_time_info'     => $pre_month[$val['admin_id']]['time_info'],
            //'pre_month_number_info'   => $pre_month[$val['admin_id']]['number_info'],
            //'pre_month_service_info'  => $pre_month[$val['admin_id']]['service_info'],
        );
        $today_call_num[]    = $today[$val['admin_id']]['call_num'];
        $today_call_rate[]  = $today[$val['admin_id']]['call_rate'];
        $today_answered_num[] = $today[$val['admin_id']]['answered'];
        $today_noanswered_num[] = $today[$val['admin_id']]['service_info'];
        $yday_time_info[]     = $yesterday[$val['admin_id']]['time_info'];
        $yday_number_info[]   = $yesterday[$val['admin_id']]['number_info'];
        $yday_service_info[]  = $yesterday[$val['admin_id']]['service_info'];
        //$month_time_info[]    = $val['time_info'];
        //$month_number_info[]  = $val['number_info'];
        //$month_service_info[] = $val['service_info'];
        //$pre_month_time_info[]     = $pre_month[$val['admin_id']]['time_info'];
        //$pre_month_number_info[]   = $pre_month[$val['admin_id']]['number_info'];
        //$pre_month_service_info[]  = $pre_month[$val['admin_id']]['service_info'];

    }
    $keys = array_keys(current($final));
    foreach ($keys as $val) {
        if (is_array($$val)) {
            $final['total'][$val] = array_sum($$val);
        }
    }
    $final['total']['today_call_rate'] = sprintf("%.2f",$final['total']['today_answered_num']/$final['total']['today_call_num']*100).'%';
    if ($final['total']['yday_call_num']) {
        $final['total']['yday_call_rate'] = sprintf("%.2f",$final['total']['yday_answered_num']/$final['total']['yday_call_num']*100).'%';
    }
    $final['total']['admin_id'] = $final['total']['role_id'] = $final['total']['group_id'] = 'total';
    $final['total']['admin_name'] = '总计';
    return $final;
}

//订单签收率
function order_success_stats($start_time,$end_time){
    $admin = return_admin();
    $sql = "SELECT COUNT(*) total,SUM(i.final_amount) amount,".
        "i.admin_id FROM ".$GLOBALS['ecs']->table('order_info').' i LEFT JOIN '.$GLOBALS['ecs']->table('admin_user')
        ." a ON a.user_id=i.admin_id WHERE i.order_status IN(5,1) AND i.order_type NOT IN(1,3)"
        ." AND a.role_id>32  AND i.add_time BETWEEN $start_time AND $end_time";
    //$sql = 'SELECT COUNT(o.order_id) total,u.admin_id,SUM(o.final_amount) amount FROM '.$GLOBALS['ecs']->table('order_info')
    //    .' o LEFT JOIN '.$GLOBALS['ecs']->table('users').' u ON u.user_id=o.user_id '
    //    ." WHERE o.add_time BETWEEN $start_time AND $end_time AND u.role_id>32 AND order_type NOT IN(1,3)";
    //成功签收
    $success_order = $GLOBALS['db']->getAll($sql." AND i.shipping_status=2 GROUP BY i.admin_id");
    //已发货的
    $sended_order = $GLOBALS['db']->getAll($sql.' AND i.shipping_status IN(1,2,4)'.' GROUP BY i.admin_id');
    if ($admin && $success_order && $sended_order) {
        foreach ($sended_order as &$o) {
            foreach ($success_order as $k=>$s) {
                if ($o['admin_id'] == $s['admin_id']) {
                    $o['success_total'] = $s['total'];
                    $o['success_amount'] = $s['amount'];
                    if (!empty($o['total'])) {
                        $o['success_rate'] = sprintf("%.2f",$s['total']/$o['total']*100).'%';
                        if ($o['amount']>0) {
                            $o['amount_rate'] = sprintf("%.2f",$s['amount']/$o['amount']*100).'%';
                        }
                        unset($success_order[$k]);
                    }
                }
            }
        }
        foreach ($admin as &$v) {
            foreach ($sended_order as $o) {
                if ($v['admin_id'] == $o['admin_id']) {
                    $v = array_merge($v,$o); 
                }
            }
            $result[$v['admin_id']] = $v;
        }
    }
    return $result;
}

function return_admin(){
    if (!empty($_REQUEST['role_id'])) {
        $ex_where = ' AND role_id='.intval($_REQUEST['role_id']);
    }
    $sql_admin = 'SELECT ext,user_name,user_id admin_id FROM '.$GLOBALS['ecs']->table('admin_user')." WHERE status>0 AND stats>0 AND ext>0 $ex_where";
    return $GLOBALS['db']->getAll($sql_admin);
}

function final_order_success($month,$last_month,$customer){
    foreach ($month as $id=>$val) {
        $final[$val['admin_id']] = array(
            'admin_id'                  => $val['admin_id'],
            'admin_name'                => $val['user_name'],
            'month_total'               => $val['total'],
            'month_amount'              => $val['amount'],
            'month_success_total'       => $val['success_total'],
            'month_success_amount'      => $val['success_amount'],
            'month_success_rate'        => $val['success_rate'],
            'month_amount_rate'         => $val['amount_rate'],
            'last_month_total'          => $last_month[$val['admin_id']]['total'],
            'last_month_amount'         => $last_month[$val['admin_id']]['amount'],
            'last_month_success_total'  => $last_month[$val['admin_id']]['success_total'],
            'last_month_success_amount' => $last_month[$val['admin_id']]['success_amount'],
            'last_month_amount_rate'    => $last_month[$val['admin_id']]['amount_rate'],

        );
        if ($customer) {
            $a = array(
                'customer_total' => $customer[$val['admin_id']]['total'],
                'customer_amount'     => $last_month[$val['admin_id']]['amount'],
                'customer_success_total' => $customer[$val['admin_id']]['success_total'],
                'customer_success_amount' => $customer[$val['admin_id']]['success_amount'],
                'customer_rate' => $customer[$val['admin_id']]['success_rate'],
            );
            if ($a) {
                $final[$val['admin_id']] = array_merge($final[$val['admin_id']],$a);
            }
        }
    }

    unset($val);
    $keys = array_keys(current($final));
    $keys = array_slice($keys,2);
    foreach ($keys as $val) {
        foreach ($final as $f) {
            $final['total'][$val] += $f[$val];
        }
    }
    if ($final['total']['month_total']) {
        $final['total']['month_success_rate'] =sprintf("%.2f",$final['total']['month_success_total']/$final['total']['month_total']*100).'%'; 
        $final['total']['month_amount_rate'] =sprintf("%.2f",$final['total']['month_success_amount']/$final['total']['month_amount']*100).'%'; 
    }
    if ($final['total']['last_month_total']) {
        $final['total']['last_month_success_rate'] =sprintf("%.2f",$final['total']['last_month_success_total']/$final['total']['last_month_total']*100).'%'; 
        $final['total']['last_month_amount_rate'] =sprintf("%.2f",$final['total']['last_month_success_amount']/$final['total']['last_month_amount']*100).'%'; 
    }
    if ($final['total']['customer_total']) {
        $final['total']['customer_rate'] =sprintf('%.2f',$final['total']['customer_success_total']/$final['total']['customer_total']*100).'%'; 
        $final['total']['customer_amount_rate'] = sprintf("%.2f",$final['total']['month_success_amount']/$final['total']['customer_amount']*100).'%'; 
    }

    $final['total']['admin_id'] = $final['total']['role_id'] = $final['total']['group_id'] = 'total';
    $final['total']['admin_name'] = '总计';
    return $final;   
}

//小组或平台销量统计报表
function platform_order_stats($refund_where='',$status='',$platform_list=array()){
    $param = addslashes_deep($_REQUEST);
    global $nowtime;
    // 计算统计时间  月
    $param['start_time'] = date('Y-m-01 00:00:00', $nowtime);
    $param['end_time']   = date('Y-m-d 23:59:59', $nowtime);

    $start_time = strtotime($param['start_time']);
    $end_time   = strtotime($param['end_time']);
    $end_month  = strtotime(date('Y-m-t 23:59:59', $nowtime));


    $stats['month'] = stats_order($start_time,$end_month,$status,$platform_list);  // 当月销量
    $stats['month'] = sort_by_sales($stats['month']);

    // 计算昨日统计时间
    $yesterday_end = strtotime(date('Y-m-d 00:00:00', $nowtime));
    $yesterday_start = $yesterday_end -24*3600;
    $stats['last_day'] = stats_order($yesterday_start,$yesterday_end,$status,$platform_list);  // 昨日销量
    $stats['last_day'] = sort_by_sales($stats['last_day']);

    // 计算今日统计时间
    $today_end = strtotime(date('Y-m-d 23:59:59', $nowtime));
    $today_start = $today_end -24*3600;
    $stats['current'] = stats_order($today_start,$today_end,$status,$platform_list);  // 当日销量
    $stats['current'] = sort_by_sales($stats['current']);
    $stats = array_reverse($stats);

    // 退货订单统计
    //$status = $refund_where.' AND order_status=5 AND shipping_status=4 ';
    //$stats['refund'] = stats_order($start_time,$end_time,$status);  // 退货订单数据

    $key = $_REQUEST['group_by'] ? 'depart_id' : 'platform';
    $group = " GROUP BY $key  ";
    $status = " $refund_where AND r.return_time BETWEEN $today_start AND $today_end";
    $result = stats_return_order($status, $group);
    $return = array('current' => '', 'last_day' => '', 'month' => '');
    foreach ($stats['current'] as $key=>$val){
        @$return['current'][$key] = $result[$key] ? $result[$key] : '-';
    }

    $status = " $refund_where AND r.return_time>$yesterday_start AND r.return_time<$yesterday_end";
    $result = stats_return_order($status, $group);
    foreach ($stats['last_day'] as $key=>$val){
        @$return['last_day'][$key] = $result[$key] ? $result[$key] : '-';
    }

    $status = " $refund_where AND r.return_time>$start_time AND r.return_time<$end_time";
    $result = stats_return_order($status, $group); // 当月退货统计
    foreach ($stats['month'] as $key=>$val){
        @$return['month'][$key] = $result[$key] ? $result[$key] : '-';
    }
    return array('stats'=>$stats,'return'=>$return);
}

//销量统计报表的权限
function report_authority(&$status,&$refund_where,&$trans_role_list){
    if (admin_priv('order_sales_all', '', false)) {
        $stats_list = explode(',',SALE.','.ONLINE_STORE);
        $stats_list = array_unique($stats_list);
        $stats_list = implode(',',$stats_list);
    } elseif (admin_priv('order_sales_trans-part', '', false)) {
        $trans_role_list = trans_part_list();
        $stats_list = @implode(',', $trans_role_list);
    } elseif (admin_priv('order_sales_part', '', false)) {
        $platform_stats = platform_list();
        foreach ($platform_stats as $val) {
            $stats_list[] = $val['role_id'];
        }
        $stats_list = @implode(',', $stats_list);
    } elseif (admin_priv('order_sales_branch','',false)) {
        $sql_select = 'SELECT action FROM '.$GLOBALS['ecs']->table('role')." WHERE role_id={$_SESSION['role_id']}";
        $action = $GLOBALS['db']->getOne($sql_select);
        $sql_select = 'SELECT role_id FROM '.$GLOBALS['ecs']->table('role')." WHERE action='$action'";
        $role_list = $GLOBALS['db']->getCol($sql_select);
        $stats_list = implode(',', $role_list);
    }

    return $stats_list;
}

function analyse_user($opt){
    switch($opt){
    case 'contact':
        $sel_opt = intval($_REQUEST['sel_opt']);
        $item_opt = intval($_REQUEST['item_opt']);
        $customer_type = intval($_REQUEST['customer_type']);
        $where = ' WHERE 1 ';
        if ($item_opt) {
            $operator = $sel_opt == 1 ? '<>' : '=';  
            $field = $item_opt == 1 ? 'qq' : 'wechat';  
            $res['title'] = $sel_opt == 1 ? "有{$field}顾客" : "无{$field}顾客";
            $where .= " AND $field$operator'' ";
            $sql = 'SELECT r.role_name,COUNT(*) count FROM '.$GLOBALS['ecs']->table('users')
                .' u LEFT JOIN '.$GLOBALS['ecs']->table('role').' r ON u.role_id=r.role_id';
            $append = ' AND u.role_id IN('.OFFLINE_SALE.')'
                .' AND u.customer_type NOT IN(5,6,7) GROUP BY u.role_id ORDER BY count DESC';
            $result = $GLOBALS['db']->getAll($sql.$where.$append);
            //$no_qq = $GLOBALS['db']->getAll($sql.$where.$append);
            foreach ($result as $v) {
                $data_name[] = $v['role_name'];
                $data_detail[] = array('value'=>$v['count'],'name'=>$v['role_name']);  
            }

            $res['max'] = $result[0]['count'];
            $res['data1'] = $data_name;
            $res['data2'] = $data_detail;
        }else{
            $res = false;
        }
    }
    return $res;
}

//顾客分析有无联系方式
function analyse_user_contact(){
    $role_id    = isset($_REQUEST['role_id']) ? intval($_REQUEST['role_id']) : 0;
    if (!$role_id) {
        $role_id = OFFLINE_SALE;
    }
    //$admin_list = get_admin_tmp_list($role_id);
    $where      = " WHERE 1 ";
    $append     = " AND customer_type NOT IN(4,5,6)";
    $sql        = 'SELECT admin_id,count(*) count FROM '.$GLOBALS['ecs']->table('users');
    $where .= " AND role_id IN($role_id)";
    $sql .= $where;
    $group_by = ' GROUP BY admin_id ORDER BY admin_id ASC';
    $list = $GLOBALS['db']->getAll('SELECT admin_id,count(*) total,admin_name FROM '.$GLOBALS['ecs']->table('users')
        ." WHERE role_id IN($role_id) $append $group_by");
    $arr['has_qq']= $GLOBALS['db']->getAll($sql." AND qq<>'' $aqqend ".$group_by);
    //$arr['no_qq'] = $GLOBALS['db']->getAll($sql." AND qq='' $append ".$group_by);
    $arr['has_wechat'] = $GLOBALS['db']->getAll($sql." AND wechat<>'' $append ".$group_by);
    //$arr['no_wechat'] = $GLOBALS['db']->getAll($sql." AND wechat='' $append ".$group_by);
    foreach ($arr as &$v) {
        $v = optimize_array($v); 
    }
    unset($v);
    $total = array(
        'user_id'    => 'total',
        'admin_name'  => '总计',
        'total'      => 0,
        'has_qq'     => 0,
        'qq_rate'      => 0,
        'has_wechat' => 0,
        'wechat_rate'  => 0,
    );
    foreach ($list as &$v) {
        $v['has_qq']     = $arr['has_qq'][$v['admin_id']];
        $v['qq_rate']    = sprintf("%.2f",$v['has_qq']/$v['total'])*100;
        $v['has_wechat'] = $arr['has_wechat'][$v['admin_id']];
        $v['wechat_rate']  = sprintf("%.2f",$v['has_wechat']/$v['total'])*100;

        $total['total']     += $v['total'];
        $total['has_qq']     += $v['has_qq'];
        //$total['no_qq']      += $v['no_qq'];
        $total['has_wechat'] += $v['has_wechat'];
        //$total['no_wechat']  += $v['no_wechat'];
    }

    $total['qq_rate'] = sprintf("%.2f",$total['has_qq']/$total['total'])*100;
    $total['wechat_rate'] = sprintf("%.2f",$total['has_wechat']/$total['total'])*100;
    array_push($list,$total);
    return $list;
}

//成交方式报表
function deal_order_report(){
    $filter_type = intval($_REQUEST['fileter_type']);
    $start_time  = strtotime($_REQUEST['start_time']);
    $end_time    = strtotime($_REQUEST['end_time']);

    if (!$start_time && !$end_time) {
        $start_time = strtotime(date('Y-m-01 00:00:00'));
        $end_time = strtotime(date('Y-m-t 23:59:59'));
    }
    $where = " AND o.add_time BETWEEN $start_time AND $end_time"; 

    if(!$filter_type){
        $sql = "SELECT SUM(o.final_amount) final_amount,COUNT(*) order_num,o.admin_id,o.admin_name,o.deal_method,a.role_id,r.role_code FROM "
            .$GLOBALS['ecs']->table('order_info').' o LEFT JOIN '.$GLOBALS['ecs']->table('admin_user')
            .' a ON o.admin_id=a.user_id LEFT JOIN '.$GLOBALS['ecs']->table('role')
            .' r ON a.role_id=r.role_id '
            ." WHERE o.order_type IN(4,5,6) AND o.order_status IN(5,1) AND o.shipping_status<>3 AND a.status=1 $where GROUP BY o.admin_id,o.deal_method ORDER BY final_amount DESC";
        
    }else{
        if ($_REQUEST['role_id']) {
            $role_id = intval($_REQUEST['role_id']);
            $where .= " AND o.platform=$role_id ";
        }
        $sql = "SELECT SUM(o.final_amount) final_amount,COUNT(*) order_num,o.deal_method,r.role_name,r.depart_id FROM "
            .$GLOBALS['ecs']->table('order_info').' o LEFT JOIN '.$GLOBALS['ecs']->table('role')
            .' r ON o.platform=r.role_id WHERE o.order_type IN(4,5,6) AND o.order_status IN(5,1) AND o.shipping_status<>3'
            ." $where GROUP BY o.platform,o.deal_method ORDER BY final_amount DESC";
    }
    $res = $GLOBALS['db']->getAll($sql);
    if ($res) {
        $total = array(
            'name' => '总计',
        );
        $list = array();
        if (!$filter_type) {
            foreach ($res as $v) {
                $list[$v['admin_id']]['role_id'] = $v['role_id'];
                $list[$v['admin_id']]['role_code'] = $v['role_code'];
                $list[$v['admin_id']]['name'] = $v['admin_name'];
                $list[$v['admin_id']]['list'][$v['deal_method']] = $v;
                $total['list'][$v['deal_method']]['final_amount'] += $v['final_amount'];
                $total['list'][$v['deal_method']]['order_num'] += $v['order_num'];
                $total['list'][$v['deal_method']]['deal_method'] = $v['deal_method'];
                $total['final_amount'] += $v['final_amount'];
            }
        }else{
            //部门，小组

        }
        $res = $list;
    }
    array_push($res,$total);
    unset($v);
    $deal_method = get_deal_method();
    array_push($deal_method,array('method_id'=>0,'method_name'=>'以前'));
    foreach ($res as &$v) {
        foreach ($v['list'] as $k=>&$l) {
            $l['amount_percent'] = bcdiv($l['final_amount'],$total['final_amount'],2)*100;
            foreach ($deal_method as $m) {
                if ($v['list'][$m['method_id']]) {
                    continue;
                }else{
                    $v['list'][$m['method_id']] = array(
                        'order_num'=>0,'deal_method'=>$m['method_id'],'final_amount'=>0,'amount_percent'=>0,
                    );
                }
            }
        }
        ksort($v['list']);
    }

    return $res;
}

function optimize_array($arr){
    foreach ($arr as $v) {
        $res[$v['admin_id']] = $v['count'];
    }
    unset($v);
    return $res;
}

//添加QQ，微信统计
function add_contact_report($start,$end){
    $where = ' WHERE 1 ';
    $role_id = isset($_REQUEST['role_id']) && !empty($_REQUEST['role_id']) ? intval($_REQUEST['role_id']) : KEFU.','.KEFU2 ;
    $where .= " AND u.role_id IN($role_id)";
    $sql = 'SELECT COUNT(*) total,c.add_admin,c.contact_name FROM '.$GLOBALS['ecs']->table('user_contact')
        .' c LEFT JOIN '.$GLOBALS['ecs']->table('users').' u ON u.user_id=c.user_id '.
        " $where %s AND c.add_time BETWEEN $start AND $end AND c.contact_name IN('qq','wechat') AND c.add_time>0 GROUP BY c.add_admin,%s ORDER BY total DESC";

    $res = $GLOBALS['db']->getAll(sprintf($sql,'','c.contact_name'));
    $checked = $GLOBALS['db']->getAll(sprintf($sql,' AND c.access=1','c.access'));
    if ($res) {
        $list = array();
        foreach ($res as $v) {
         $list[$v['add_admin']][$v['contact_name']] = $v['total'];  
        }
        unset($v);
        if ($checked) {
            foreach ($checked as $c) {
                foreach ($list as $k=>&$l) {
                    if ($c['add_admin'] == $k) {
                        $l['access'] = $c['total'];
                    }
                }
            }
        }
        $res = $list;
    }
    return $res;
}

function admin_for_report($where){
    $sql = 'SELECT user_id admin_id,user_name admin_name,group_id,role_id FROM '.$GLOBALS['ecs']->table('admin_user')
        ." WHERE status>0 AND stats>0 $where";
    return $GLOBALS['db']->getAll($sql);
}

function rebuy_repeat($res,$key){
    $tmp = array();
    $final = array();
    foreach ($res as $ar) {
        $final[$ar[$key]] = $ar;
        $tmp[$ar[$key]] += 1;
    }
    foreach ($tmp as $k=>$t) {
        if ($t == 1) {
            unset($final[$k]);
        }
    }
    return $final;
}

function express_fee_report($shipping_list=array()){
    $start_time = isset($_REQUEST['start_time']) ? strtotime($_REQUEST['start_time']) : strtotime(date('Y-m-01 00:00:00'));
    $end_time = isset($_REQUEST['end_time']) ? strtotime($_REQUEST['end_time']) : strtotime(date('Y-m-t 00:00:00'));
    foreach ($shipping_list as $s) {
        $sp[] = $s['shipping_id'];
    }
    $sp = implode(',',$sp);
    //外包
    $sql = 'SELECT SUM(express_fee) express_fee,shipping_id,platform FROM '.$GLOBALS['ecs']->table('other_express_fee')." WHERE add_time BETWEEN $start_time AND $end_time".
        ' GROUP BY shipping_id,platform';
    $other_express_fee = $GLOBALS['db']->query($sql);
    global $smarty;
    $smarty->assign('start_time',date('Y-m-d',$start_time));
    $smarty->assign('end_time',date('Y-m-d',$end_time));
    $where = " AND o.order_status=5 AND o.shipping_status<>3 AND o.add_time BETWEEN $start_time AND $end_time "
        ." AND o.shipping_id IN($sp)";
    $p_sql = ' role_id IN('.OFFLINE_SALE.','.ONLINE_STORE.') AND role_type>0';
    if ($depart_id = intval($_REQUEST['depart_id'])) {
        $where .= " AND r.depart_id=$depart_id";
        $p_sql .= " AND depart_id=$depart_id";
        $smarty->assign('depart_id',$depart_id);
    }

    if ($role_id = intval($_REQUEST['role_id'])) {
        $where .= " AND o.platform=$role_id ";
        $p_sql .= " AND role_id=$role_id";
        $smarty->assign('role_id',$role_id);
    }

    $sql = 'SELECT o.platform,o.express_fee,o.shipping_id FROM '.$GLOBALS['ecs']->table('order_info').' o,'.$GLOBALS['ecs']->table('role')  
        ." r WHERE o.platform=r.role_id $where";
    $list = $GLOBALS['db']->getAll($sql);

    $role_list = get_role($p_sql);
    $total = array(
        'role_id' =>0,
        'role_name' =>'总计'
    );
    array_push($role_list,$total);
    if ($shipping_list) {
        foreach ($shipping_list as $s) {
            foreach ($role_list as &$r) {
                $r['express_fee_list'][$s['shipping_id']] = 0;
            }
        }
    }
    $total = array_pop($role_list);
    modifyData($list,$role);
    if ($other_express_fee) {
    }
    if ($list) {
        unset($r);
        foreach ($list as $l) {
            foreach ($role_list as &$r) {
                if ($l['platform'] == $r['role_id']) {
                    $r['express_fee_list'][$l['shipping_id']] += $l['express_fee'];
                }
            }
        }
        unset($r,$l); 
        foreach ($role_list as $r) {
           foreach ($r['express_fee_list'] as $k=>$l) {
               $total['express_fee_list'][$k] += $l;
           } 
        }
        array_push($role_list,$total);
    }
    return $role_list;
}

function modifyData($list,&$role_list){
    
}

//平台销量，用于产品销量的统计
function for_goods_sale_rank($where){
    $sel_item   = is_numeric($_REQUEST['sel_item']) ? intval($_REQUEST['sel_item']) : 2;
    $group_by = $sel_item == 1 ? ' GROUP BY r.depart_id':' GROUP BY oi.platform';
    $sql = 'SELECT SUM(oi.final_amount) final_amount,oi.platform role_id,r.depart_id FROM '.$GLOBALS['ecs']->table('order_info')
        .' oi LEFT JOIN '.$GLOBALS['ecs']->table('role').' r ON oi.platform=r.role_id'
        ." $where $group_by";
    return $GLOBALS['db']->getAll($sql);
}

function main_sale_list(){
    $deaprt_id = isset($_REQUEST['depart_id'])?intval($_REQUEST['depart_id']):0;
    $role_id   = isset($_REQUEST['role_id'])?intval($_REQUEST['role_id']):0;
    $where = ' WHERE 1 ';
    if ($depart_id) {
        $where .= " AND r.depart_id=$depart_id";
    }
    if ($role_id) {
        $where .= " AND m.role_id=$role_id";
    }
    $sql = 'SELECT m.sale_id,m.goods_id,g.goods_name,r.role_name,m.role_id FROM '.$GLOBALS['ecs']->table('main_sale')
        .' m LEFT JOIN '.$GLOBALS['ecs']->table('goods').' g ON g.goods_id=m.goods_id LEFT JOIN '
        .$GLOBALS['ecs']->table('role')." r ON m.role_id=r.role_id $where";
    $res = $GLOBALS['db']->getAll($sql); 
    $list = array();
    $info = array();
    if ($res) {
        foreach ($res as $v) {
            $info[$v['role_id']] = array('role_name'=>$v['role_name']);
            $list[$v['role_id']]['goods_list'][] = array('goods_id'=>$v['goods_id'],'goods_name'=>$v['goods_name']);
        }
    }
    foreach ($list as $k=>$l) {
        $info[$k]['goods_list'] = $l['goods_list'];
    }
    return $info;
}

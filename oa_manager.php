<?php
define('IN_ECS', true);
require(dirname(__FILE__).'/includes/init.php');
date_default_timezone_set('Asia/Shanghai');

/*定时修改积分规则可用性*/ 

/*-- 服务子菜单 --*/
if ($_REQUEST['act'] == 'menu')
{
    $file = strstr(basename($_SERVER['PHP_SELF']), '.', true);
    $nav = list_nav();
    $smarty->assign('nav_2nd', $nav[1][$file]);
    $smarty->assign('nav_3rd', $nav[2]);
    $smarty->assign('file_name', $file);

    die($smarty->fetch('left.htm'));
}

//出题
elseif($_REQUEST['act'] == 'examination'){
    $type_list = array('1'=>'产品知识','健康知识','话术');
    $smarty->assign('type_list',$type_list);
    $res['main'] = $smarty->fetch('examination.htm'); 
    die($json->encode($res));
}
//生成试卷
elseif($_REQUEST['act'] == 'create_examination'){
    $page_num      = intval($_REQUEST['page_num']);  //试卷数
    $sql           = 'SELECT ex_id,question,answer FROM '.$GLOBALS['ecs']->table('examine')
        ." WHERE type_id=%d AND catalog=%d ORDER BY RAND() LIMIT 1,%d";
    $fill_question = array_filter($_REQUEST['fill_question']);
    $short_answer  = array_filter($_REQUEST['short_answer']);
    if (empty($fill_question) && empty($short_answer)){
        header('Location:index.php');
        exit;
    }

    $file_path = $_SERVER['DOCUMENT_ROOT'].'/crm2/files/'.date('Y-m-d-H-i-s',$_SERVER['REQUEST_TIME']);  //存放试卷目录
    //打包试卷
    $file_name = strtotime(date('Y-m-d'));
    $q_file_path = $file_path.'/q_'.$file_name.'.txt';
    $a_file_path = $file_path.'/a_'.$file_name.'.txt';
    if (mkdir($file_path,777)) {
        $q_f = fopen($q_file_path,'a');
        $a_f = fopen($a_file_path,'a');
        for ($i = 0; $i < $page_num; $i++) {
            $n = 1;
            $m = 1;
            fwrite($q_f,"\n试卷编号："."$file_name\n姓名：______\n====================================\n");
            fwrite($a_f,"\n试卷编号："."$file_name  答案\n=============================================\n");
            //填空题
            $fq = array();
            foreach ( $fill_question as $k=>$f) {
                $res = $GLOBALS['db']->getAll(sprintf($sql,$k,1,$f));
                //$fq = array_merge($fq,$res);
                foreach ($res as $q) {
                    fwrite($q_f,($n++).'、  '.$q['question']."\n");
                    fwrite($a_f,($m++).'、  '.$q['answer']."\n");
                    //$fq_answer[] = $q['anaser']; 
                }
            }
            //简答题
            $sa = array();
            fwrite($q_f,"\n");
            fwrite($a_f,"\n");
            if ($short_answer) {
                foreach ($short_answer as $ke=>$s) {
                    $res = $GLOBALS['db']->getAll(sprintf($sql,$ke,2,$s));
                    //$sa = array_merge($sa,$res);
                    foreach ($res as $a) {
                        fwrite($q_f,($n++).'、  '.$a['question']."\n");
                        fwrite($a_f,($m++).'、  '.$a['answer']."\n");
                    }
                }
            }
        }
        $answer = file_get_contents($a_file_path);
        fwrite($q_f,"\n".$answer);
        header("Content-Type:application/force-download");
        header("Content-Disposition:attachment;filename=".basename($q_file_path));
        readfile($q_file_path);
    }else{
        echo '生成失败';
    }
}


//办公电脑管理
elseif ($_REQUEST['act'] == 'pc_manager')
{
    /*
    $room = $_REQUEST['room'];
    $pre_seat = $_REQUEST['pre_seat'];

    for($i=1;$i<=25;$i++)
    {
       if($i<10) 
       {
           $seat = $room.$pre_seat.'0'.$i;
       }
       else
       {
           $seat = $room.$pre_seat.$i;
       }
       $sql_insert = 'INSERT INTO '.$GLOBALS['ecs']->table('office_seat').
           "(seat,room)VALUES('$seat','$room')";
       $GLOBALS['db']->query($sql_insert);
    }
    exit();
     */

    //$res = array('req_msg'=>true,'message'=>'','timeout'=>2000);

    if(admin_priv('all','',false))
    {
        $super = 0;
        $role = 1;
    }
    elseif(admin_priv('pc_manager','',false))
    {
        $super = 1;
        $role = 1;
    }

    if($role)
    {
        $sql_select = 'SELECT room FROM '.$GLOBALS['ecs']->table('office_seat').' GROUP BY room';
        $room_info = $GLOBALS['db']->getAll($sql_select);
        $room = $room_info;
        $room_nu = count($room_info); //房间数量

        for($i =0; $i<$room_nu; $i++)
        {
            $sql_select= 'SELECT s.*,p.*,a.user_name AS admin_name,r.role_name FROM '.$GLOBALS['ecs']->table('office_seat').
                ' AS s LEFT JOIN '.$GLOBALS['ecs']->table('pc_manager').
                ' AS p ON s.pc_id=p.pc_id LEFT JOIN '.$GLOBALS['ecs']->table('admin_user').
                ' AS a ON s.admin_id=a.user_id LEFT JOIN '.$GLOBALS['ecs']->table('role').
                ' AS r ON a.role_id=r.role_id '.
                " WHERE s.room='{$room_info[$i]['room']}' ORDER BY seat ASC";

            $room_info[$i]['seat_info'] = $GLOBALS['db']->getAll($sql_select);
        }

        for($i = 0; $i<$room_nu;$i++)
        {
            if($i == 0)
            {
                $room_info[$i]['status'] = "style=\"display:''\"";
            }
            else
            {
                $room_info[$i]['status'] = "style=\"display:none\"";
            }
        }
    }
    else
    {
        //$res['message'] = '';
    }

    $sql_select = 'SELECT * FROM '.$GLOBALS['ecs']->table('account_type');
    $account_type = $GLOBALS['db']->getAll($sql_select);
    $account_info = array();
    $total = count($account_type);

    $account_type_list = array('qq','ppcrm','qqcrm','wangwang');
    for($i=0; $i<count($account_type_list); $i++)
    {
        $sql_select = 'SELECT account_name FROM '.$GLOBALS['ecs']->table('account').
            ' WHERE type_id = (SELECT type_id FROM '.$GLOBALS['ecs']->table('account_type').
            " WHERE label='{$account_type_list[$i]}')";
        $account_info[$account_type_list[$i]] = $GLOBALS['db']->getCol($sql_select);    
    }

    $smarty->assign('super',$super);
    $smarty->assign('room_info',$room_info);
    $smarty->assign('room',$room);
    $smarty->assign('account_info',$account_info);
    $smarty->assign('admin_info',get_admin_userlist());

    $res['main'] = $smarty->fetch('pc_manager.htm');
    die($json->encode($res));
}

?>

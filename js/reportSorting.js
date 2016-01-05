/* 表格排序 */
function tableSort (id) {
  var sortFields     = document.getElementById('sortByThis');
  console.log(sortFields);

  var tableObj       = sortFields.parentNode;
  var sortByThisCols = sortFields.getElementsByTagName('th');

  for (var i=0; i < sortByThisCols.length; i++) {
    sortByThisCols[i].ondblclick = function () {
      sortByThisCol(this);
    };
  }
}

function sortByThisCol (obj) {
  var tbodyObj    = obj.parentNode.parentNode.parentNode.tBodies[0];
  var siblings    = obj.parentNode.getElementsByTagName('th');
  var objNowStyle = obj.className;
  var arr         = [];
  var idx         = obj.cellIndex;

  if (idx == 0) {
    return false;
  }

  for (var i = 0; i < siblings.length; i++) {
    siblings[i].className = 'noSort';
  }

  for (var i = 0; i < tbodyObj.rows.length; i++) {
    arr.push(tbodyObj.rows[i]);
  }

  var totalChild = arr.pop();
  arr.sort(function (t1, t2) {
    var n1 = Number(parseFloat(t1.cells[idx].innerText)) || 0;
    var n2 = Number(parseFloat(t2.cells[idx].innerText)) || 0;
    return n1 - n2;
  });

  if (objNowStyle == 'downSortByCurrent') {
    obj.className = 'upSortByCurrent'; // 升序排列
    arr.reverse();
  } else {
    obj.className = 'downSortByCurrent'; // 降序排列
  }

  var n = 1;
  for (var i = arr.length - 1; i >= 0; i--){
    if (arr[i].className == '' && document.getElementById('rank')) {
      arr[i].cells[0].innerText = n++;
    }

    tbodyObj.appendChild(arr[i]);
  }

  tbodyObj.appendChild(totalChild);
}

/**
 * 左右移动表格
 */
function scrollStart (scrollId, reportFormId, ev) {
  var scrollBlockObj = document.getElementById(scrollId);
  var reportFormObj  = document.getElementById(reportFormId);
  var menuObj = document.getElementById('left_parent');

  var correctionValue = 0;
  var disX = null;

  if (menuObj.style.display == 'none') {
    correctionValue = 22;
  } else if (menuObj.style.display == 'inline') {
    correctionValue = 185;
  }

  var evObj = ev || event;
  disX = evObj.clientX - scrollBlock.offsetLeft;

  document.onmousemove = function (ev) {
    var evObj = ev || event;
    var distance = evObj.clientX - disX;

    if (distance < 0) {
      distance = 1;
    } else if (distance > scrollBlockObj.parentNode.offsetWidth - scrollBlockObj.offsetWidth) {
      distance = scrollBlockObj.parentNode.offsetWidth - scrollBlockObj.offsetWidth -3;
    }

    scrollBlockObj.style.left = distance + 'px';

    var scale = (distance/(scrollBlockObj.parentNode.offsetWidth - scrollBlockObj.offsetWidth -3)).toFixed(1);
    var moveDis = -scale*(reportFormObj.offsetWidth - reportFormObj.parentNode.offsetWidth);

    reportFormObj.style.left = -scale*(reportFormObj.offsetWidth - reportFormObj.parentNode.offsetWidth) + correctionValue + 'px';
  };

  document.onmouseup = function () {
    document.onmousemove = null;
    document.onmouseup   = null;
    left = null;
  };

  document.onselectstart = function () {
    return false;
  };

  return false;
}

/**
 * 完成销量
 */
function salesCompletedFilter(bool) {
  var role_id  = document.getElementById('role_id').value;
  var group_id = document.getElementById('group_id').value;

  var attribute = null;
  if (/\d+/.test(role_id)) {
    attribute = 'role';
    role_id = parseInt(role_id);
  } else {
    attribute = 'role_code';
  }

  var tBodyObj = document.getElementById('person_style').tBodies[0].rows;
  var tmpNodes = [];

  var offset = 1;
  if (group_id > 0) {
    for (var i = 0; i < tBodyObj.length - 1; i++) {
      if (tBodyObj[i].getAttribute('group') == group_id) {
        tmpNodes.push(tBodyObj[i]);
      }
    }
  } else if (role_id) {
    for (var i = 0; i < tBodyObj.length - 1; i++) {
      if (tBodyObj[i].getAttribute(attribute) == role_id) {
        tmpNodes.push(tBodyObj[i]);
      }
    }
  }

  if (tmpNodes.length > 0) {
    tBodyObj = tmpNodes;
    offset = 0;
  }

  var j = 1;
  var cellIndex = document.getElementById('pct').cellIndex;
  for (var i = 0; i < tBodyObj.length - offset; i++) {
    tBodyObj[i].className = 'hide';

    if (bool) {
      if (parseFloat(tBodyObj[i].cells[cellIndex -1].innerText) > parseFloat(tBodyObj[i].cells[cellIndex +3].innerText)) {
        tBodyObj[i].className = '';
      }
    } else {
      if (parseFloat(tBodyObj[i].cells[cellIndex -1].innerText) < parseFloat(tBodyObj[i].cells[cellIndex +3].innerText)) {
        tBodyObj[i].className = '';
      }
    }

    if (tBodyObj[i].className == '') {
      tBodyObj[i].cells[0].innerText = j++;
    }
  }

  calcTotal();
}

function calcTotal() {
  var rowsList = document.getElementById('person_style').tBodies[0].rows;
  var lastRow = rowsList[rowsList.length -1];
  for (var i = lastRow.cells.length - 1; i >= 2; i--) {
    lastRow.cells[i].innerText = 0.00;
  }

  for (var m = lastRow.cells.length - 1; m >= 2; m--) {
    for (var i = rowsList.length - 2; i >= 0; i--) {
      if (rowsList[i].className == '' && parseFloat(rowsList[i].cells[m].innerText) > 0) {
        lastRow.cells[m].innerText = parseFloat(lastRow.cells[m].innerText) + parseFloat(rowsList[i].cells[m].innerText);
      }
    }

    if (isNaN(lastRow.cells[m].innerText)) {
      lastRow.cells[m].innerText = '';
    }

    if (/\./.test(lastRow.cells[m].innerText) && !isNaN(lastRow.cells[m].innerText)) {
      lastRow.cells[m].innerText = parseFloat(lastRow.cells[m].innerText).toFixed(2);
    }
  }

  var n = 0;
  for (var i = rowsList.length - 2; i >= 0; i--) {
    if (rowsList[i].className == '') {
      n++;
    }
  }

  for (var i = rowsList.length - 2; i >= 0; i--) {
    if (rowsList[i].className == '') {
      rowsList[i].cells[0].innerText = n--;
    }
  }

  var pctObj = document.getElementById('pct');
  if (pctObj !== null && pctObj) {
    var ave = (pctObj.previousSibling.previousSibling.innerText/pctObj.previousSibling.previousSibling.previousSibling.previousSibling.innerText).toFixed(2);
    pctObj.innerText = isNaN(ave) ? 0 : ave;
  }
}

/**
 * 获取套餐详细信息
 */
function getPackageStruct(package_sn,sales) {
  Ajax.call('storage.php?act=get_package_struct&psn='+package_sn+'&sales='+sales, '', getPackageStructResp, 'GET', 'TEXT');
}

function getPackageStructResp(res) {
  showMsg({req_msg:true,message:res});
}

/**
 * 查询统计数据：销售明细
 */
function getSaleDetail() {
  var theForm = document.forms['saleDetail'];
  var data = {};
  for (var i = 0; i < theForm.elements.length; i++) {
    if (!!theForm.elements[i].name && !!theForm.elements[i].value) {
      if (theForm.elements[i].type == 'checkbox' && !theForm.elements[i].checked) {
        continue;
      }
      data[theForm.elements[i].name] = theForm.elements[i].value;
    }
  }
  if (!data.startTime != !data.endTime) {
    showMsg({req_msg:true,timeout:2000,message:'统计时间必须为一个时间段：请选择开始时间和结束时间！'});
    return false;
  }
  Ajax.call(theForm.action, 'data='+JSON.stringify(data), getSaleDetailResp, 'GET', 'JSON');
  return false;
}

function getSaleDetailResp(res) {
  document.getElementById('data').innerHTML = res.main;
}

//服务统计检索
function serviceStats(obj,act){
  var data =[];
  for (var i in obj.elements) {
    data.push(obj.elements[i].name+'='+obj.elements[i].value);
  }
  Ajax.call('report_forms.php?act='+act,data.join('&'),inMain,'POST','JSON');
}

function connectStats(obj){
  Ajax.call('report_forms.php?act=phone_connect_stats','role_id='+obj.value,inMain,'POST','JSON');
}

//订单签收率
function successOrderStats(obj){
  var role_id = obj.elements['role_id'].value;
  var start_time = obj.elements['start_time'].value;
  var end_time = obj.elements['end_time'].value;
  Ajax.call('report_forms.php?act=order_success_stats','role_id='+role_id+'&start_time='+start_time+'&end_time='+end_time,inMain,'POST','JSON');
}

//订单成交方式
function successOrderStats(obj){
  var role_id    = obj.elements['role_id'].value;
  var start_time = obj.elements['start_time'].value;
  var end_time   = obj.elements['end_time'].value;
  Ajax.call('report_forms.php?act=deal_method_report','role_id='+role_id+'&start_time='+start_time+'&end_time='+end_time+'&sch='+true,fullSearchResponse,'GET','JSON');
}

//QQ,微信添加统计
function addContactReport(obj){
  var role_id    = obj.elements['role_id'].value;
  var start_time = obj.elements['start_time'].value;
  var end_time   = obj.elements['end_time'].value;
  $.get(
      'report_forms.php?act=add_contact_report'+'&role_id='+role_id+'&start_time='+start_time+'&end_time='+end_time,
      function(res){
        inMain(res);
      },'JSON');
}
function statsEffects(obj){
  Ajax.call('report_forms.php?act=user_stats_effect','role_id='+obj.value,inMain,'GET','JSON');
}

//新顾客跟进率
function userServiceStats(obj){
  var role_id = obj.elements['role_id'].value;
  var start_time = obj.elements['start_time'].value;
  var end_time = obj.elements['end_time'].value;
  Ajax.call('report_forms.php?act=user_service_stats','role_id='+role_id+'&start_time='+start_time+'&end_time='+end_time,inMain,'POST','JSON');
}

function analyseUser(){
  var itemOpt = $("[name='item_opt']").val();  
  var selOpt = $("[name='sel_opt']").val();  
  var customerType = $("[name='customer_type']").val();  
  Ajax.call('report_forms.php?act=act_user_analyse','item_opt='+itemOpt+'&sel_opt='+selOpt+'&customer_type='+customerType,analyseUserResp,'POST','JSON');
}

function analyseUserResp(res){
  if (res) {
    // 路径配置
    require.config({
      paths: {
               //echarts: './js'
               echarts: 'http://echarts.baidu.com/build/dist'
             }
    });

    // 使用
    require(
        [
        'echarts',
        'echarts/chart/pie', // 使用柱状图就加载bar模块，按需加载
        'echarts/chart/funnel',
        ],
        function (ec) {
          var myChart = ec.init(document.getElementById('analyse')); 
          option = {
            title : {
                      text: '顾客分析',
      subtext: res.title,
      x:'center'
                    },
      tooltip : {
                  trigger: 'item',
      formatter: "{a} <br/>{b} : {c} ({d}%)"
                },
      legend: {
                orient : 'vertical',
    x : 'left',
    data:res.data1
              },
      toolbox: {
                 show : true,
                 feature : {
                   mark : {show: true},
                   dataView : {show: true, readOnly: false},
                   magicType : {
                     show: true, 
                     type: ['pie', 'funnel'],
                     option: {
                       funnel: {
                                 x: '25%',
                                 width: '50%',
                                 funnelAlign: 'left',
                                 max:1 
                               }
                     }
                   },
                   restore : {show: true},
                   saveAsImage : {show: true}
                 }
               },
      calculable : true,
      series : [
      {
        name:res.title,
        type:'pie',
        radius : '55%',
        center: ['50%', '60%'],
        //data:[{value: "1", name: "有qq"}, {value: "0", name: "无qq"}]
        data:res.data2
      }
    ]
          };

          // 为echarts对象加载数据 
          myChart.setOption(option); 
        }
    );
  }
}

//顾客分析有没有联系方式
function userAnalyse(role_id){
  Ajax.call('report_forms.php?act=user_analyse','role_id='+role_id,inMain,'GET','JSON');
}

//产品销量报表
function getGoodsSaleRank(para){
  var goodsSn = '';

  goodsSn = typeof(para) == 'string' ? para : $("#goods_sn").val();
  var selItem = $("[name='sel_item']:checked").val();

  var startTime = $("#start_time").val();
  var endTime = $("#end_time").val();
  var departId = $("#depart_id").val();
  var platform = $("#platform").val();
  Ajax.call('report_forms.php?act=goods_sale_rank','goods_sn='+goodsSn+'&start_time='+startTime+'&end_time='+endTime+'&sel_item='+selItem+'&depart_id='+departId+'&platform='+platform,getGoodsSaleRankResp,'GET','JSON');
}

function getGoodsSaleRankResp(res){
  $("#goods_rank_slae_div").html(res.main);
  $("#goods_sn").val(res.goods_sn);
  $("#goods_name").html(res.goods_name);
}

//平台核心数据报表
function spreadReport(obj){
  var startTime = obj.elements['start_time'].value;
  var endTime = obj.elements['end_time'].value;
  var platform = obj.elements['platform'].value;
  var sel_type = obj.elements['sel_type'].value;
  Ajax.call('report_forms.php?act=spread_report','start_time='+startTime+'&end_time='+endTime+'&platform='+platform+'&sel_type='+sel_type,inMain,'GET','JSON');
}

function sale_trend(res){
}

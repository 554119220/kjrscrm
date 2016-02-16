/**
 * 发送获取订单请求
 */
function giveMeOrder (obj) {
  Ajax.call(obj.value, '', sendToServerResponse, 'POST', 'JSON');
}


/**
 * 订单商品信息过滤
 */
function sendGoodsFilter () {
  try {
    var filterURL = document.getElementById('page_link').href;
    var goods_kind = document.getElementById('goods_kind').value;
    if (filterURL == undefined) {
      filterURL = 'order.php?act=current_order'; 
    }

    Ajax.call(filterURL+'&goods_kind='+goods_kind, '', sendToServerResponse, 'GET', 'JSON');
  } catch (ex) {
    alert(ex);
  }
}

/**
 * 查询订单是否已经存在
 */
function checkOrderSn (obj) {
  // 拍拍
  if (/^C\d{2}-\d{7}-\d{7}$|^\d{14}$|^\d{15,16}$|^\d{13}$|^\d{10,11}$|^\d{8}-\d{8}-\d{10}$|^\d{6}[0-9a-z]{6}$/i.test(obj.value)) {
    Ajax.call('order.php?act=check_order&order_sn='+obj.value, '', checkOrderSnResp, 'GET', 'JSON');
  } else if (obj.value.length > 0){
    showMsg({req_msg:true,message:'请填写正确的订单编号！'});
    obj.focus();
  }
}

function checkOrderSnResp(res) {
  if (res.req_msg) {
    showMsg(res);

    document.getElementById('detail').parentNode.removeChild(document.getElementById('detail'));
  }

  return false;
}

/**
 * 添加订单编号到刷单列表
 */
function addOrderSnForBrush(inputObj, oid) {
  var orderSn       = inputObj.value;

  if (!inputObj.value) {
    inputObj.parentNode.innerHTML = '';
    return false;
  }

  var brushPlatform = inputObj.parentNode.getAttribute('platform');

  var msg = {req_msg:true,timeout:2000};
  if (!inputObj.value) {
    msg.message = '请填写正确的订单编号！';
    showMsg(msg);
    return false;
  }

  switch (brushPlatform) {
    case '6':
      if (!/^\d{15}$/.test(orderSn)) {
        msg.message = '提交的订单编号不是来自天猫！';
      }
      break;
    case 10:
    case 16:
      if (!/^\d{10}$/.test(orderSn)) {
        msg.message = '提交的订单编号不是来自京东或当当！';
      }
      break;
    case 14:
      if (!/^\d{12}$/.test(orderSn)) {
        msg.message = '提交的订单编号不是来自一号店！';
      }
      break;
  }

  if (msg.message) {
    showMsg(msg);
    inputObj.focus();
    return false;
  }

  Ajax.call('order.php?act=save_brush_order_sn', 'order_sn='+orderSn+'&brush_platform='+brushPlatform+'&order_id='+oid, addOrderSnForBrushResp, 'POST', 'JSON');
}

function addOrderSnForBrushResp(res) {
  var inputObj = document.getElementById('html_'+res.id);
  inputObj.parentNode.innerText = inputObj.value;
  showMsg(res);
}

/**
 * 显示顾客联系信息：手机、电话、地址
 */
function showThisInfo(oid, table) {
  Ajax.call('order.php?act=show_single_info&order_id='+oid+'&table='+table, '', showMsg, 'GET', 'JSON');
}

//批量确认订单
function dealFlushOrder(obj){
  var orderlist = obj.elements['order_sn_list'].value;
  var shipping_id = obj.elements['shipping_id'].value;
  
  if (orderlist) {
    Ajax.call('order.php?act=deal_flush_order&behave=deal','&orderlist='+orderlist+'&shipping_id='+shipping_id,dealFlushOrderRes,'POST','JSON');
  }
}

function dealFlushOrderRes(res){
  $('#error_div').html(res); 
}

//批量标记刷单
function markFlushOrder(obj){
  var orderlist = obj.elements['order_sn_list'].value;
  var shipping_id = obj.elements['shipping_id'].value;
  
  if (orderlist) {
    Ajax.call('order.php?act=deal_flush_order&behave=mark','&orderlist='+orderlist+'&shipping_id='+shipping_id,dealFlushOrderRes,'POST','JSON');
  }
}

//批量确认收货
function autoCheckLogistics(){
    Ajax.call('logistics_info.php?act=auto_checked_logistics','',showMsg,'GET','JSON');
}


function justMarkFlushOrder(obj){
  var platform = obj.elements['platform'].value;
  var goodsSn = obj.elements['goods_sn'].value;
  var price = obj.elements['price'].value;
  if (platform && goodsSn && price) {
    Ajax.call('order.php?act=mark_flush_order','&platform='+platform+'&goods_sn='+goodsSn+'&price='+price,showMsg,'POST','JSON');
  }else return false;
}

//取货表单
function switchOrderType(obj){
  if (obj.value && obj.value == 10) {
    var userId = $("#ID").val(); 
    Ajax.call('order.php?act=store_order','user_id='+userId,pickUpOrderForm,'GET','JSON');
  }else{
    $("#gerneral_order").show(); 
    $("#store_order_goods_form").hide();
  }
}

function pickUpOrderForm(res){
  $("#gerneral_order").hide(); 
  $("#store_order_goods_form").html(res.main);
  $("#store_order_goods_form").show();
}

//取货商品表单
function storeGoodsForm(orderId){
  if ($("[name='"+orderId+"']").length) {
    $("[name='"+orderId+"']").remove();
  }else{
    $.get(
        'order.php?act=store_order_goods&order_id='+orderId,
        function(res){
          $("#store_order_goods").append(res.main);
        },'JSON');
  }
}

function calculateAmount(){
  var goodsPrice = 0;
  $("#store_order_goods [type='number']").each(function(){
    goodsPrice = parseInt($(this).val()) * parseInt($(this).attr('goods_price'));
  });
  $("[name='goods_amount']").val(goodsPrice);
  $("[name='order_amount']").val(goodsPrice);
}

//存货订单明细
function getStoreOrderDetail(orderId,obj){
  if (orderId) {
    $.get(
        'order.php?act=get_store_order_detail&order_id='+orderId,
        function(res){
          var index = parseInt(obj.parent().parent().first().index());
          var table = document.getElementById("store_order_list");
          var tr = table.insertRow(index);
          var td = tr.insertCell();
          td.setAttribute('colspan',9);
          td.innerHTML = res;
          //$("#take_log").html(res);
        },'JSON');
  }
}

function setUserLevel(level,id){
  if (level && id) {
    var url = 'users.php?act=set_user_level&id='+id+'&level='+level;
    if ($(".user_detail")) {
      url += '&table=user';
    }else{
      url += '&table=order';
    }
    $.get(
        url,
        function(res){
          return false;
        },'JSON');
  }else return false;
}

//设置评价
function setTraderates(obj,orderId){
  $("[name='traderates']").each(function(){
    $(this).parent("td").html($(this).children('option').filter(':selected').text());
  });
  obj.html(
      '<select name="traderates" onchange="saveTraderates(this,'+orderId+')"> <option value="0">顾客评价</option> <option value="1" {if $order.traderates eq 1}selected{/if}>好评</option> <option value="2" {if $order.traderates eq 2}selected{/if}>中评</option> <option value="3" {if $order.traderates eq 3}selected{/if}>差评</option> </select>'
      ); 
}

function saveTraderates(obj,orderId){
  if (obj.value) {
    $.get(
        'order.php?act=set_traderates&order_id='+orderId+'&traderates='+obj.value,
        function(res){
          showMsg(res);
        },'JSON');
  }
}

function fillOrderShipping(){
  var orderNum = $("[name='order_num']").val();
  var trackingStart = $("[name='tracking_start']").val();
  if (trackingStart && orderNum) {
    $.get(
        'order.php?act=fill_order_sn'+'&tracking_start='+trackingStart+'&order_num='+orderNum+$("#page_url").val(),
        function(res){
          if (res.success) {
            //console.log(res.success);
            for (var i in res.success) {
              $("#"+res.success[i].order_id).html(res.success[i].tracking_sn);
            }
          }
          showMsg(res);
        },'JSON');
  }else return false;
}

function manualOrderSync(obj){
  if (obj.value!='') {
    var startTime = $("#startTime").val();
    var endTime = $("#endTime").val();
    $.get(
        'order.php?act=manual_order_synchro&behave=1&action='+obj.value+'&start_time='+startTime+'&end_time='+endTime,
        function(res){
          obj.parentNode.parentNode.cells[2].innerHTML=res.message;
        },'JSON');
  }else return false;
}

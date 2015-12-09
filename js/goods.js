/**
 * 查询商品生产日期及库存
 */
function checkStockBatch (gid)
{
  Ajax.call('storage.php', 'act=check_stock_batch&goods_id='+gid, checkStockBatchResponse, 'POST', 'JSON');
}

/**
 * 查询商品生产日期及库存 (回调函数)
 */
function checkStockBatchResponse (res)
{
  showMsg(res);
}

/**
 * 添加新品牌
 */
function addBrand ()
{
  var brand      = document['brand'];
  var brand_name = brand['brand_name'].value;
  var brand_desc = brand['brand_desc'].value;

  Ajax.call('storage.php', 'act=add_brand&brand_name='+brand_name+'&brand_desc='+brand_desc, addBrandResponse, 'POST', 'JSON');
}

/**
 * 添加商品品牌(回调函数)
 */
function addBrandResponse (res)
{
  showMsg(res);
  if (res.code)
  {
    var tableObj = document.getElementById('brand_list');
    var trObj = tableObj.insertRow(tableObj.rows.length);

    //tdObj = trObj.insertCell(0);
    //tdObj.innerHTML = '<a href="'+res.cat_id+'" >删除</a>';

    tdObj = trObj.insertCell(0);
    tdObj.innerHTML = '<img src="images/1.gif" />';

    tdObj = trObj.insertCell(0);
    tdObj.innerHTML = res.brand_desc;

    tdObj = trObj.insertCell(0);
    tdObj.innerHTML = res.brand_name;

    tdObj = trObj.insertCell(0);
    tdObj.innerHTML = '↑';
  }
}

/**
 * 查询商品编号是否已经存在
 */
function checkGoodsSn (goods_sn)
{
  Ajax.call('storage.php', 'act=check_goods_sn&goods_sn='+goods_sn, checkGoodsSnResponse, 'POST', 'JSON');
}

/**
 * 查询商品编号是否已经存在 (回调函数)
 */
function checkGoodsSnResponse (res)
{
  if (res.req_msg)
  {
    showMsg(res);
  }
}

/**
 * 添加套餐商品
 */
function addGoodsToPackage ()
{
  // 表单对象
  var theForm = document.forms['theForm'];

  // 商品信息
  var number   = theForm.elements['number'].value;
  var goodsObj = theForm.elements['goods_id'];
  var id       = goodsObj.value;

  for (var i = 0; i < goodsObj.length; i++)
  {
    if (goodsObj.options[i].selected)
    {
      var name = goodsObj.options[i].text;
    }
  }

  var goodsList = document.getElementById('goods_list'); 
  var rowObj = goodsList.insertRow(goodsList.rows.length);
  rowObj.className = 'tr_list';

  // 商品ID
  var goodsId = rowObj.insertCell(0);
  goodsId.innerHTML = '<input type="checkbox" form="package" name="list_id[]" value="'+id+'" id="goods_id_'+id+'"/><strong onclick="removeGoods(this)">删除</strong>';

  // 商品名称
  var goodsName = rowObj.insertCell(1);
  goodsName.innerHTML = name+'<input type="hidden" form="package" name="list_name[]" value="'+name+'"/>';

  // 商品数量
  var goodsNumber = rowObj.insertCell(2);
  goodsNumber.innerHTML = number+'<input type="hidden" form="package" name="list_number[]" value="'+number+'"/>';
}

/**
 * 添加新套餐
 */
function addNewPackage () {
  var theForm = document['package'];
  var data = '';
  for (var i = 0; i < theForm.length; i++) {
    if (theForm[i].value == '') {
      var msg = new Array ();
      msg.req_msg = true;
      msg.timeout = 2000;
      msg.message = '请填写所有套餐信息！';
      showMsg(msg);
      var submitButton = document.getElementById('submit');
      submitButton.disabled = true;
      submitButton.className = 'input_submit_right_disable';
      return false;
    }
    if (theForm[i].name) data += '&'+theForm[i].name+'='+theForm[i].value;
  }
  Ajax.call('storage.php', data, addNewPackageResponse, 'POST', 'JSON');
}

/**
 * 添加新套餐 (回调函数)
 */
function addNewPackageResponse (res) {
  showMsg(res);
  var theForm = document['package'];
  for (var i = 0; i < theForm.length; i++) {
    if (theForm[i].type == 'text') {
      theForm[i].value = '';
    }
  }
}

/**
 * 验证套餐编号是否存在
 */
function checkPackageSn (obj)
{
  if (obj.value && obj.value.length > 3)
  {
    Ajax.call('storage.php', 'act=check_package_sn&packing_desc='+obj.value, checkPackageSnResponse, 'POST', 'JSON');
  }
}

/**
 * 验证套餐编号 (回调函数)
 */
function checkPackageSnResponse (res)
{
  var submitButton = document.getElementById('submit');
  if (res.req_msg){
    showMsg(res);
    submitButton.disabled = true;
    submitButton.className = 'input_submit_right_disable';
  } else{
    submitButton.disabled = false;
    submitButton.className = 'input_submit_right';
  }

  return false;
}

//品牌 商品 联级
function getBrandGoods(brand_id)
{
  if(brand_id != 0){
    Ajax.call('finance.php?act=get_brand_goods','brand_id='+brand_id,getBrandGoodsRes,'GET','JSON');
  }
  else
    return ;
}

function getBrandGoodsRes(res)
{
  var goods_sel = document.getElementById('goods');
  goods_sel.innerHTML = '';
  var opt = document.createElement('option');
  opt.value = 0;
  opt.text = '请选择商品';
  goods_sel.appendChild(opt);

  for(var i in res.goods)
  {
    if (typeof(goods[i]) == 'function') continue;
    var opt = document.createElement('option');
    opt.value = res.goods[i].goods_id;
    opt.text = res.goods[i].goods_name;
    goods_sel.appendChild(opt);
  }
}

/**
 * 动态显示商品
 */
function listGoods(obj) {
  if (/^[\u4e00-\u9fa5]{2,}$/.test(obj.value)) {
    Ajax.call(obj.getAttribute('AJAXhref'), 'goods_name='+obj.value, listGoodsResp, 'GET', 'JSON');
  }
}

function listGoodsResp(res) {
  var options = '';
  for (var i = 0; i < res.length; i++) {
    options += '<option value="'+res[i].goods_name+'"/>'+res[i].goods_sn+'</option>';
  }
  document.getElementById('goodsList').innerHTML = options;
}

function selectGoods() {
  var dataList = document.getElementById('goodsList');
  var selected = document.getElementById('selectedGoods').value;
  for (var i = 0; i < dataList.options.length; i++) {
    if (dataList.options[i].value == selected) {
      document.getElementById('goodsSn').value = dataList.options[i].text;
    }
  }
}

function submitForm(formName) {
  var theForm = document.forms[formName];
  var data = {};

  var start = theForm.elements['start_time'].value;
  var end   = theForm.elements['end_time'].value;
  if (!start != !end) {
    showMsg({req_msg:true,timeout:2000,message:'促销时间必须包含开始时间和结束时间！'});
    return false;
  } else if (start >= end) {
    showMsg({req_msg:true,timeout:2000,message:'开始时间必须小于结束时间！'});
    return false;
  }
  for (var i = 0; i < theForm.elements.length; i++) {
    if (theForm.elements[i].name && !!theForm.elements[i].value) {
      data[theForm.elements[i].name] = theForm.elements[i].value;
    }
  }
  data = JSON.stringify(data);
  Ajax.call(theForm.action, 'info='+data, submitFormResp, 'POST', 'JSON');
  return false;
}

function submitFormResp(res) {
  showMsg(res);
}

//搜索产品知识
function searchArticle(obj){
  var component = obj.elements['component'].value;
  var goodsSn = obj.elements['goods_id'].value;
  Ajax.call('article.php?act=sch_article','component='+component+'&goods_sn='+goodsSn,sendToServerResponse,'POST','JSON');
}

//编辑成分
function editComponnet(obj){
  obj.parentNode.innerHTML =  '<input type="text" value="'+obj.innerText+'" onblur="saveComponent(this,'+obj.value+')" id="cp"/>';
}
function saveComponent(obj,id){
  Ajax.call('article.php?act=edit_component','cat_id='+id+'&cat_name='+obj.value,saveResp,'GET','JSON');
}
function saveResp(res){
  if (res.code) {
    $("#cp").parent().html(res.main);
  }else return false;
}

//编辑产品详情
function editGoodsDetail(){
  var arrChk = $("input[name='component_name[]']");
  var goodsFeatureContent = goodsFeature.getData();
  var component =[];
  var data = {};
  arrChk.each(function(){
    if ($(this).prop('checked')) {
      component.push($(this).val());
    }
  });
  if (component.length == 0 && $.trim(goodsFeatureContent) == '') {
    return false;
  }else{
    data['component'] = component;
    data['feature'] = goodsFeatureContent;
    data['goods_sn'] = $("#goods_sn").val();
    Ajax.call('article.php?act=edit_goods_detail',data,showMsg,'POST','JSON');
  }
}

//修改产品基本信息
function editGoodsInfo(goodsId,behave){
  //var goodsName = ''
  var data = [];
  data.push('behave='+behave);
  data.push('goods_id='+goodsId);
  if (behave == 'done') {
    //goodsName = $.trim($("[name='edit_goods_name").val()); 
    data.push('goods_name='+$.trim($("[name='edit_goods_name").val())); 
    data.push('bar_code='+$.trim($("[name='bar_code").val())); 
    data.push('office_url='+$("[name='office_url']").val()); 
    //if (goodsName) {
    //  goodsName = '&goods_name='+goodsName+'&office_url='+$("[name='office_url']").val();
    //}else return false;
  }
  if (goodsId) {
    Ajax.call('storage.php?act=add_goods&',data.join('&'),editGoodsInfoResp,'GET','JSON');
  }
}

function editGoodsInfoResp(res){
  if (res.act == 'done') {
    if (res.code) {
      close_pop();
      showMsg(res);
    }
  }else{
    $("#div_pop_ups").show('slow');
    $("#fade").show();
    $("#pop_ups").html(res.main);
    $("#pop_ups .box_center").css('width','94%');  
  }
}

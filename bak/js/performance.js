/**
 * 添加一行记录
 */
function insertRowElement(obj) {
	var table = document.getElementById('ad_table'); // 获取表格对象
	var rowIndex = table.rows.length;

	var rowObj = obj.parentNode.parentNode; // 获取当前文本所在的行对象
	var rowInnerHtml = rowObj.innerHTML; // 获取当前行的全部内容
	// 将 添加行元素 的函数 add_ad 替换为 删除行元素 的函数 remove_ad
	rowInnerHtml = rowInnerHtml.replace(/insertRowElement/, 'removeRowElement');
	rowInnerHtml = rowInnerHtml.replace(/\+/, '-'); // 替换 添加 + 为 删除 -
	var newRow = table.insertRow(rowIndex); // 在表格尾部添加一行
	newRow.innerHTML = rowInnerHtml; // 在新添加的行元素中填充内容
	var input = newRow.getElementsByTagName('input');
	for (var i in input) {
		input[i].value = '';
	}
}

/**
 * 删除行
 */
function removeTr(obj,val) {
	var tableObj = obj.parentNode.parentNode.parentNode.parentNode;
	var trIndex = obj.parentNode.parentNode.rowIndex;
	var url = document.getElementById('target_url').value;
	var tableName = tableObj.id;

	if (val) {
		Ajax.call(url, 'val=' + val + '&table_name=' + tableName + '&tr_index=' + trIndex, removeTrResp, 'GET', 'JSON');
	} else {
		return;
	}
}

function removeTrResp(res) {
	showMsg(res);
	if (res.code) {
		var tblObj = document.getElementById(res.table_name);
		tblObj.deleteRow(res.tr_index);
	} else {
		return;
	}
}

/**
*  添加一条推广记录
*/
function addSpread(obj) {
	var table = document.getElementById('ad_table');
	var form = document.forms['spread'];

	var str = '';
	for (var i in form.elements) {
		if (typeof(form.elements[i]) == 'object' && form.elements[i].name != '') {
			str = str + '&' + form.elements[i].name + '=' + form.elements[i].value;
		}
	}

	Ajax.call('performance.php?act=add_spread', str, editSpreadResponse, 'POST', 'JSON');
}

/**
*  编辑一条推广记录
*/
function editSpread(obj) {
	var form = document.forms['spread'];

	var str = '';
	for (var i in form.elements) {
		if (typeof(form.elements[i]) == 'object' && form.elements[i].name != '') {
			str = str + '&' + form.elements[i].name + '=' + form.elements[i].value;
		}
	}

	Ajax.call('performance.php?act=update_spread', str, editSpreadResponse, 'POST', 'JSON');
}

/**
*  编辑一条推广记录的回调函数
*/
function editSpreadResponse(res) {
	if (res.req_msg) {
		showMsg(res);
	}
}

/**
*  添加一条客服记录
*/
function addServiceRecord(obj) {
	var form = document.forms['service_record'];
	var str = '';

	for (var i in form.elements) {
		if (typeof(form.elements[i]) == 'object' && form.elements[i].name != '') {
			str = str + '&' + form.elements[i].name + '=' + form.elements[i].value;
		}
	}

	Ajax.call('performance.php?act=add_service_record', str, addServiceResponse, 'POST', 'JSON');
}

/**
*	 添加客服的回调函数
*/
function addServiceResponse(res) {
	if (res.req_msg) {
		showMsg(res);
	}
}

/**
*  客服记录查找、筛选
*/
function searchTime() {
	var form = document.forms['worksummary_time'];
	var time = form.elements['time'].value;
	var username = form.elements['username'].value;
	var str = 'time=' + time + '&username=' + username;
	Ajax.call('performance.php?act=search_time', str, searchTimeResponse, 'POST', 'JSON');
}

/**
*	回调函数
*/
function searchTimeResponse(res) {
	if (res.req_msg) {
		showMsg(res);
	}

	if (res.code == 1) {
		document.getElementById('main').innerHTML = res.main;
	}
}

/**
*  编辑一条客服记录
*/
function editServiceRecord(obj) {
	var form = document.forms['service_record'];

	var str = '';
	for (var i in form.elements) {
		if (typeof(form.elements[i]) == 'object' && form.elements[i].name != '') {
			str = str + '&' + form.elements[i].name + '=' + form.elements[i].value;
		}
	}

	Ajax.call('performance.php?act=update_service_record', str, editServiceResponse, 'POST', 'JSON');
}

/**
*  编辑客服回调函数
*/
function editServiceResponse(res) {
	if (res.req_msg) {
		showMsg(res);
	}
}

/**
*  删除一条客服记录
*/
function delService(obj) {
	if (!confirm("确认删除吗")) {
		return false;
	}

	var str = 'work_id=' + obj;
	Ajax.call('performance.php?act=service_personal_delete', str, delServiceResponse, 'POST', 'JSON');
}

/**
*  删除客服回调函数
*/
function delServiceResponse(res) {
	if (res.req_msg) {
		showMsg(res);
	}
}

/**
*  删除一条个人推广记录
*/
function delSpread(obj) {
	if (!confirm("确认删除吗")) {
		return false;
	}

	var str = 'spread_id=' + obj;
	Ajax.call('performance.php?act=spread_delete', str, delSpreadResponse, 'POST', 'JSON');
}

/**
*  删除个人推广回调函数
*/
function delSpreadResponse(res) {
	if (res.req_msg) {
		showMsg(res);
	}
}

//查看平台活动
function activity(do_what) {
	var data = [];
	Ajax.call('performance.php?act=activity', 'data=' + data.join('&'), activityResp, 'GET', 'JSON');
}

function activityResp(res) {
	document.getElementById('main').innerHTML = res.main;
}

// 添加平台活动
function addMoreActivity(behave,confirm) {
	if (!confirm) {
		if (document.getElementById('goods_list_div')) {
			return;
		}

		Ajax.call('performance.php?act=add_more_activity', 'view=' + true, getActivityResp, 'GET', 'JSON');
		return;
	}else if (confirm == 1){
		var url = 'performance.php?act=add_more_activity';
	}

	var data = [];

	if (confirm) {
		var msg = [];
		msg['timeout'] = 2000;

		var obj = document.forms['add_activity_forms'];

		for (var i = 0; i < obj.elements.length - 5; i++) {
			if (obj.elements[i].value == '') {
				msg['message'] = '请完整填写活动信息';
				showMsg(msg);
				return;
			} else {
				data.push(obj.elements[i].name + '=' + obj.elements[i].value);
			}
		}
	}

	if (document.getElementById('number') || document.getElementById('money')) {
		if (document.getElementById('number')) {
			var goods_num = document.getElementById('number').value;
			data.push('number=' + goods_num);
		} else if (document.getElementById('money')) {
			var money = document.getElementById('money').value;
			data.push('money=' + money);
		}

		var gifts_num = document.getElementById('gifts_num').value;
		var gifts_sn = document.getElementById('goods_id').value;
		data.push('gifts_sn=' + gifts_sn);
		data.push('gifts_num=' + gifts_num);
	}

	if (data) {
		Ajax.call(url, data.join('&') + '&confirm=' + confirm, addMoreActivityResp, 'GET', 'JSON');
	} else {
		return;
	};
}

function addMoreActivityResp(res) {
	showMsg(res);
	activity('nothing');
	init();
}

// 商品
function getGoodslist(obj) {
	var brandId = obj.elements['brand_id'].value;
	var goods_sn = obj.elements['act_goods_sn'].value;
	var keyword = obj.elements['keyword'].value;
	var activity_id = document.getElementById('activity_id').value;

	Ajax.call('performance.php?act=get_goods_list', 'brand_id=' + brandId + '&goods_sn=' + goods_sn + '&keyword=' + keyword + '&activity_id=' + activity_id, getActivityResp, 'GET', 'JSON');
}

// 搜索推广活动
function schActivity(obj) {
	var data = [];
	for (var i = 0; i < obj.elements.length - 1; i++) {
		data.push(obj.elements[i].name + '=' + obj.elements[i].value);
	}

	Ajax.call('performance.php?act=sch_activity', data.join('&'), getActivityResp, 'GET', 'JSON');
}

//选择商品参加活动
function joinActivity(obj, goods_sn, is_join) {
	if (goods_sn != '') {
		var tr_index = obj.parentNode.parentNode.rowIndex;
		var activity_id = document.getElementById('activity_id').value;

		Ajax.call('performance.php?act=get_goods_list', 'goods_sn=' + goods_sn + '&is_join=' + is_join + '&tr_index=' + tr_index + '&join_goods=' + 'join_goods' + '&activity_id=' + activity_id, joinActivityResp, 'GET', 'JSON');
	}
}

function joinActivityResp(res) {
	if (res.code) {
		var obj = document.getElementById('goods_list_tbl');
		var trObj = obj.rows[res.tr_index];
		var inputObj = trObj.getElementsByTagName('input');

		if (res.is_join) {
			inputObj[0].checked = true;
			trObj.cells[4].innerHTML = '<button class="btn_new" onclick="joinActivity(this,' + "'" + res.goods_sn + "'" + ',0)">退出活动</button>';
		} else {
			inputObj[0].checked = false;
			trObj.cells[4].innerHTML = '<button class="btn_new" onclick="joinActivity(this,' + "'" + res.goods_sn + "'" + ',1)">参加活动</button>';
		}
	}
}

function getActivityResp(res) {
	if (res.forms_div) {
		document.getElementById('forms_div').innerHTML = res.forms_div;
	}

	document.getElementById('resource').innerHTML = res.main;
	init();
}

// 优惠内容
function setPrivilegeView(obj) {
	var tdObj = document.getElementById('privilege_td');
	var inputObj = tdObj.getElementsByTagName('input');
	var privilege = obj.value;

	for (var i = 0; i < inputObj.length; i++) {
		if (inputObj[i].value == privilege) {
			inputObj[i].parentNode.className = ' onclk_privilege';
		} else {
			inputObj[i].parentNode.className = '';
		}
	}

	if (privilege != '') {
		var insertHtml = '';
		if (privilege == 'fillNum') {
			insertHtml = ' 满 <input type="number" id="number" min="0" value="0" style="width:32px;" max="99"/> 件';
		} else if (privilege == 'fillAny') {
			insertHtml = ' 满 <input type="text" id="money" min="0" value="0" style="width:32px" max="99"/> 元';
		}

		document.getElementById('privilege_condition').innerHTML = insertHtml;
		document.getElementById('privilege_condition').style.display = '';
		document.getElementById('gifts').style.display = '';
	} else {
		return;
	}
}

function getGoodsInActivity(brand_id) {
	Ajax.call('storage.php?act=get_goods_by_brand', 'brand_id=' + brand_id, getGoodsInActivityResp, 'GET', 'JSON');
}

function getGoodsInActivityResp(goods) {
	var obj = document.getElementById('act_goods_sn');
	var opt = document.createElement('option');

	obj.length = 0;
	opt.value = 0;
	opt.text = '请选择商品';

	obj.appendChild(opt);

	for (var i in goods) {
		if (typeof(goods[i]) == 'function') continue;

		var opt = document.createElement('option');
		opt.value = goods[i].goods_sn;
		opt.text = goods[i].goods_name;
		obj.appendChild(opt);
	}
}

// 查看参加活动的商品
function getActivityGoods(act_id) {
	if (parseInt(act_id) != 0) {
		Ajax.call('performance.php?act=get_act_goods', 'act_id=' + act_id,onlyShowMsg, 'GET', 'JSON');
	}
}

function onlyShowMsg(res) {
	showMsg(res);
}

//绩效统计
function statisticsPerformance(obj){
  var data = [];
  for(var i = 0; i < obj.elements.length-1; i++){
    data.push(obj.elements[i].name + '=' + obj.elements[i].value);
  }

  Ajax.call('performance.php?act=statistics_performance',data.join('&'),fullSearchResponse,'GET','JSON');
}

//添加任务
function taskSite(obj){

  var data       = getTasksFormData(obj);
  var msg        = new Array();
  msg['timeout'] = 2000;
  msg['message'] = '';
  msg['code']    = true;

  if(obj.elements['purpose_value'] == '0'){
    msg['message'] = '目标值不能为空';
    showMsg(msg);
    return ;
  }

  Ajax.call('performance.php?act=add_tasks',data.join('&'),taskSiteResp,'GET','JSON');
}

function taskSiteResp(res){
  showMsg(res);
  if(res.code){
    document.getElementById('resource').innerHTML = res.main;
    init();
  }else{
    return ;
  }
}

// 修改或删除任务操作
function controlTask(obj,task_id,behave){
  if(task_id != 0){
    var data = [];

    if(behave != 'mod_done'){
      var trIndex  = obj.parentNode.parentNode.rowIndex;
      data.push('tr_index' + '=' + trIndex);
    }else{
      data = getTasksFormData(obj);
    }

    data.push('task_id' + '=' + task_id);
    data.push('behave' + '=' + behave);

		Ajax.call('performance.php?act=control_task',data.join('&'),controlTaskResp,'GET','JSON');
	}else{
		return ;
	}
}

function controlTaskResp(res){
  if(res.behave == 'del'){
    if(res.code){
      var obj = document.getElementById('task_list_table');
      obj.deleteRow(res.tr_index);
    }
    showMsg(res);
    document.getElementById('doTitle').innerHTML = '';
  }else if(res.behave == 'mod_form'){
    document.getElementById('tasks_site_form_div').innerHTML = res.main;
    document.getElementById('doTitle').innerHTML = '(正在修改任务)';
  }else if(res.behave == 'mod_done'){
    showMsg(res);
    if(res.code){
      document.getElementById('resource').innerHTML = res.main;
      init();
    }
  }
}


//查询任务
function schTasks(obj){
  var data = getTasksFormData(obj);
  Ajax.call('performance.php?act=sch_tasks',data.join('&'),fullSearchResponse,'GET','JSON');
  return ;
}

/*选择任务周期分配*/
function selPeriod(obj){
  var objForm = document.forms['task_site_form'];
  inputList   = objForm.getElementsByTagName('input');

  objForm.elements['deadline'].value = '';

  for(var i = 0; i < inputList.length; i++){
    if(inputList[i].type == 'radio' && inputList[i].name == 'period_id'){
      inputList[i].disabled = !(obj.checked);
    }
  }
}

/*整理任务表单数据*/
function getTasksFormData(obj){
  var data          = [];
  var task_name     = obj.elements['task_name'].value;
  var purpose_value = obj.elements['purpose_value'].value;

  data.push('deadline=' + obj.elements['deadline'].value);
  data.push('task_name=' + task_name);
  data.push('purpose_value=' + purpose_value);

  if(obj.elements['sel_period'].checked){
    data.push('period_id=' + obj.elements['period_id'].value);
  }

  if(obj.elements['platform'] && obj.elements['group']){
    data.push('platform=' + obj.elements['platform'].value);
    data.push('group=' + obj.elements['group'].value);
  } 

  return data;
}

/*搜索网络黑名单*/
function schNetworkBlacklist(obj){
  var data = new Array();
  var elementList = obj.elements;

  for(var i = 0; i < elementList.length - 3; i++){
   data.push(elementList[i].name + '=' + elementList[i].value);
  }

  data.push('is_checked' + '=' + obj.elements['is_checked'].value);

  Ajax.call('performance.php?act=sch_network_blacklist',data.join('&'),fullSearchResponse,'GET','JSON');
}

//审核网络黑名单
function checkNetworkBlacklist(obj,account_id,value){
  if(account_id != 0){
    var r = confirm('你确定要将【' + value + '】加入网络黑名单');
    if(r){
      Ajax.call('performance.php?act=check_network_blacklist','account_id='+account_id,checkNetworkBlacklistRes,'GET','JSON');
    }else{
      return ;
    }
  }else{
    return ;
  }
}

function checkNetworkBlacklistRes(res){
  showMsg(res);
  var obj = document.forms['sch_net_blacklist_form'];
  schNetworkBlacklist(obj);
}


/*工作绩效*/
function getAward(source){
  var objForm     = document.forms['award_form'];
  var data        = new Array();
  var elementList = objForm.elements;
  var url         = '';
  var response    = '';

  for(var i = 0; i < elementList.length - 4; i++){
    data.push(elementList[i].name + '=' + elementList[i].value);
  }

  if(source == ''){
    source = document.getElementById('source').value;
  }

  if(source == '' || source == 'order_rate'){
    source = 'work_award';
  }

  Ajax.call('performance.php?act='+source,data.join('&'),searchTimeResponse,'GET','JSON');
}

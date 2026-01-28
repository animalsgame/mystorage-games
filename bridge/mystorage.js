(function(window){

var installRun = 0;
var queueEnable = 0;
var isPushQueue = 1;
var queueTimer = -1;
var queueList = [];

function log(){
var date = new Date();
var timeArr = [date.getHours(), date.getMinutes(), date.getSeconds()].map(function(v){return v<10 ? '0'+v : v});
console.log.apply(console, [timeArr.join(':')].concat([].slice.call(arguments)));
}

function runMigrate(my, bsend, cb){
var keys = [];
var lastV = queueEnable;
var lastV2 = installRun;
var countTry = 3;
var isComplete = 0;

var cbres = function(v){
queueEnable = lastV;
installRun = lastV2;
if(v){
if(isComplete)log('Перенос успешно завершён');	
}else{
log('Ошибка при переносе данных');
setTimeout(function(){
document.location.href = '';
}, 5000);
}
cb(v, isComplete);
};

var cberr = cbres.bind(null, 0);

var cbmigrate = function(arr){
if(!arr)arr = [];
var lastQueueList = queueList;
queueList = [];
my.api('storage.setAll', {data:arr, migrate:1}).then(function(res){
var isok = 0;
if(res && res.length == arr.length+1){
isok = 1;
isComplete = 1;
}
cbres(isok);
}).catch(cberr);
runQueue(my);
queueList = lastQueueList;
};

var send1 = function(method, params, obj){
var args = arguments;
return new Promise(function(resolve, reject){
--obj.count;
bsend(method, params).then(resolve).catch(function(err){
if(obj.count > 0){
setTimeout(function(){send1.apply(null, args)}, obj.interval);
}else{
reject(err);	
}
});
});
};

installRun = 1;
isPushQueue = 0;
my.api('storage.migrate').then(function(res){
if(!res){
cbres(1);
return;
}

queueEnable = 1;
installRun = 1;
log('Перенос начался');
send1('VKWebAppStorageGetKeys', {offset:0, count:1000}, {count:countTry, interval:500}).then(function(data){
if(data.keys && data.keys.length > 0){
send1('VKWebAppStorageGet', {keys:data.keys}, {count:countTry, interval:800}).then(function(data){
if(data.keys && data.keys.length > 0){
//console.log('data vk', data.keys);
var arr = data.keys.map(function(o){return {key:o.key, value:o.value}});
cbmigrate(arr);
}else cbmigrate();
}).catch(cberr);

}else{
cbmigrate();
}
}).catch(cberr);

}).catch(cberr);

isPushQueue = 1;

}

function runQueue(my){
var methods = [];
var queueDup = queueList.slice();

for(var i = 0; i < queueList.length; i++){
var obj = queueList[i];
var params = {method:obj.method};
for(var n in obj.params){
var val = obj.params[n];
if(typeof val == 'function')continue;
params[n] = val;
}
methods.push(params);
}

queueList.length = 0;

var lastV = queueEnable;
queueEnable = 0;
my.api('execute', {code:methods}).then(function(arr){
if(arr){
for(var i = 0; i < arr.length; i++){
if(i < queueDup.length){
var res = arr[i];
var q = queueDup[i];
if(res && res.error){
q.cb.reject(res.error);
}else{
q.cb.resolve(res);
}
//console.log(res, q);
}
}
}
}).catch(function(e){
for(var i = 0; i < queueDup.length; i++){
var q = queueDup[i];
q.cb.reject(e);
}
});
queueEnable = lastV;
}

function pushQueue(my, o){
queueList.push(o);
if(installRun)return;

if(queueTimer == -1){
queueTimer = setTimeout(function(){
queueTimer = -1;
runQueue(my);
}, 1);
}
}

function post(url, params, appjson){
return new Promise(function(resolve, reject){
var body = (appjson) ? appjson : Object.keys(params).map(function(key){return encodeURIComponent(key)+'='+encodeURIComponent(params[key])}).join('&');
var xhr = new XMLHttpRequest();
var contentType = (appjson) ? 'application/json' : 'application/x-www-form-urlencoded';
xhr.onerror = function(e){
var err = {type:'network', data:{message:'network error'}};
reject(err);
};
xhr.onload = function(e){
if(e.target.status == 200){
var resp = e.target.response;
var ct = e.target.getResponseHeader('Content-Type');
if (ct && ct.indexOf('application/json') > -1)resp = JSON.parse(resp);
resolve(resp);
}else{
xhr.onerror(e);
}
};

xhr.open('POST', url);
xhr.setRequestHeader('Content-Type', contentType);
xhr.send(body);
});
}

function api(my, method, params, obj){
if(!obj)obj = {};
var args = arguments;
var appjson = null;
params.method = method;
if(obj.props)Object.assign(params, obj.props);
if(method == 'execute')appjson = JSON.stringify(params);
return new Promise(function(resolve, reject){
post(my.scriptURL, params, appjson).then(function(result){
if(result && result.error){
reject(result.error);
}else{
if(result && typeof result == 'object' && 'response' in result)result = result.response;
resolve(result);
}
}).catch(reject);
});
}

function MyStorage(cfg){
var th = this;
this.cfg = cfg || {};
this.scriptURL = 'storage.php';
this.queryParams = window.location.search;

var migrateCB = function(){
runMigrate(th, th.origBridgeSend, function(v, res){
if(v){
runQueue(th);
}
});
};

if(this.cfg.queue)queueEnable = 1;
if(this.cfg.install){
var lastV = queueEnable;
queueEnable = 0;
installRun = 1;
th.api('install').then(function(status){
installRun = 0;
if(status == 1){
log('Установка завершена, удалите свойство install или установите ему 0');
if(th.cfg.bridge && th.cfg.migrate){
migrateCB();
}else{
runQueue(th);
}
}else{
log('Ошибка при установке', status);	
}
}).catch(function(err){log('Ошибка при установке', err)});
queueEnable = lastV;
}

if(this.cfg.bridge)this.bridge(this.cfg.bridge);

if(this.cfg.bridge && this.cfg.migrate && !installRun){
migrateCB();
if(th.origBridgeSend)delete th.origBridgeSend;
}

}

Object.assign(MyStorage.prototype, {

bridge:function(o){
var th = this;
var subscribers = [];
var methods = {};
var methodsList = [['VKWebAppStorageSet', 'storage.set'], ['VKWebAppStorageGet', 'storage.get'], ['VKWebAppStorageGetKeys', 'storage.getKeys']];

var createBridgeEvent = function(type, data){
return {detail:{type:type, data:data}};
};

var runSubscribe = function(data){
for(var i = 0; i < subscribers.length; i++){
var cb = subscribers[i];
cb(data);
}
};

var addMethod = function(name, apiname){
methods[name] = function(params){

var resultCB = function(data, cb){
if(apiname == 'storage.set')data = {result:!!data};
else data = {keys:data};
var ev = createBridgeEvent(name+'Result', data);
runSubscribe(ev);
cb(data);
};

var errorCB = function(data, cb){
var errObj = {error_type:data.type, error_data:data.data || {}};
var ev = createBridgeEvent(name+'Failed', errObj);
runSubscribe(ev);
cb(ev.detail);
};

return new Promise(function(resolve, reject){
if(apiname == 'storage.get')params.keys = params.keys.join(',') || '';
th.api(apiname, params).then(function(data){resultCB(data, resolve)}).catch(function(e){errorCB(e, reject)});
});
};
};

for(var i = 0; i < methodsList.length; i++)addMethod.apply(null, methodsList[i]);

var _send = o.send;
var _subscribe = o.subscribe;
var _unsubscribe = o.unsubscribe;

o.send = function(method, params){
if(!params)params = {};
if(method in methods)return methods[method](params);
return _send.apply(o, arguments);
};

th.origBridgeSend = _send.bind(o);

o.subscribe = function(cb){
subscribers.push(cb);
return _subscribe.apply(o, arguments);
};

o.unsubscribe = function(cb){
var index = subscribers.indexOf(cb);
if(index > -1)subscribers.splice(index, 1);
return _unsubscribe.apply(o, arguments);
};

},

api:function(method, params){
var th = this;
if(!params)params = {};
if(queueEnable && isPushQueue){
return new Promise(function(resolve, reject){
pushQueue(th, {method:method, params:params, cb:{resolve:resolve, reject:reject}});
});
}
params.q = th.queryParams;
if(method == 'storage.setAll')params.data = (params.data) ? JSON.stringify(params.data) : null;
return api(th, method, params);
}
});

window.MyStorage = MyStorage;
})(window);
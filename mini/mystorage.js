(function(window){

function api(my, method, params){
return new Promise(function(resolve, reject){
params.method = method;
fetch(my.scriptURL, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(params)}).then(function(res){return res.json()}).then(function(result){
if(result && result.error){
reject(result.error);
}else{
if(result && typeof result == 'object' && 'response' in result)result = result.response;
resolve(result);
}
}).catch(function(e){
reject({type:'network', data:{message:'network error'}});
});	
});
}

function MyStorage(cfg){
Object.assign(this, {queryParams:window.location.search, queueTimer:-1, queueList:[], cfg:cfg || {}, scriptURL:'storage.php'});
if(this.cfg.install)this.api('install').then(function(v){console.log(v ? 'Установка завершена, удалите свойство install или установите ему 0' : 'Ошибка при установке')}).catch(function(err){console.log('Ошибка при установке', err)});
}

MyStorage.prototype.api = function(method, params){
var my = this;
return new Promise(function(resolve, reject){
var o = {method:method, params:params, cb:{resolve:resolve, reject:reject}};
my.queueList.push(o);

if(my.queueTimer == -1){
my.queueTimer = setTimeout(function(){
my.queueTimer = -1;

var methods = [];
var queueDup = my.queueList.slice();

for(var i = 0; i < my.queueList.length; i++){
var obj = my.queueList[i];
var params = {method:obj.method};
if(obj.params){
for(var n in obj.params){
var val = obj.params[n];
if(typeof val == 'function')continue;
params[n] = val;
}
}
methods.push(params);
}

my.queueList.length = 0;

api(my, 'execute', {q:my.queryParams, code:methods}).then(function(arr){
if(arr){
for(var i = 0; i < arr.length; i++){
if(i < queueDup.length){
var res = arr[i];
var q = queueDup[i];
if(res && res.error)q.cb.reject(res.error);
else q.cb.resolve(res);
}
}
}
}).catch(function(e){
for(var i = 0; i < queueDup.length; i++){
var q = queueDup[i];
q.cb.reject(e);
}
});

}, 1);
}

});
};

window.MyStorage = MyStorage;
})(window);
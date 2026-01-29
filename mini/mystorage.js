(function(window){

function magic(o, o_o){
if(o === null || o === undefined)return null;
if(typeof o == 'function'){
var v = 0;
while(1){
v = 10 + Math.floor(Math.random() * 1000000);
if(!o_o[v])break;
}
o_o[v] = o;
return v;
}
if(typeof o == 'object'){
if(Array.isArray(o)){
var arr = [];
for(var i = 0; i < o.length; i++)arr.push(magic(o[i], o_o));
return arr;
}
var obj = {};
for(var n in o)obj[n] = magic(o[n], o_o);
return obj;
}
return o;
}

function api(my, method, params, obj){
return new Promise(function(resolve, reject){
params.method = method;
fetch(my.scriptURL, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(params)}).then(function(res){return res.json()}).then(function(result){
if(result && result.error){
reject(result.error);
}else{
if(result && typeof result == 'object'){
var o_o = result['()'];
if(o_o){
for(var i = 0; i < o_o.length; i++){
var Oo = o_o[i];
var oO = obj.OoO[Oo[0]];
if(oO)oO.apply(null, Oo[1]);
}
}
if('response' in result)result = result.response;
}
obj.OoO = null;
resolve(result);
}
}).catch(function(e){
obj.OoO = null;
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

var oo = {OoO:{}};
var methods = [];
var queueDup = my.queueList.slice();

for(var i = 0; i < my.queueList.length; i++){
var obj = my.queueList[i];
var params = obj.params ? magic(obj.params, oo.OoO) : {};
params.method = obj.method;
methods.push(params);
}

my.queueList.length = 0;

api(my, 'execute', {q:my.queryParams, code:methods}, oo).then(function(arr){
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
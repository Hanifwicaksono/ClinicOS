import test from 'node:test';
import assert from 'node:assert/strict';
import {patientDuplicateReview} from '../resources/js/patient-duplicates.js';

globalThis.FormData = class extends Map {
    constructor() { super([['name','Dummy'],['phone','abc']]); }
};
function review() {
    return Object.assign(patientDuplicateReview('/duplicates'), {form:{}, $nextTick:async()=>{}});
}
test('duplicate review preserves phone validation details and blocks submit',async()=>{
    globalThis.fetch=async()=>({ok:false,status:422,json:async()=>({errors:{phone:['Nomor telepon maksimal 30 karakter.']}})});
    const state=review();assert.equal(await state.check(),false);assert.match(state.message,/telepon.*30/);assert.equal(state.busy,false);
});
test('non-JSON server failure has actionable feedback',async()=>{
    globalThis.fetch=async()=>({ok:false,status:500,json:async()=>{throw new SyntaxError('Unexpected token <');}});
    const state=review();assert.equal(await state.check(),false);assert.match(state.message,/coba lagi/);assert.doesNotMatch(state.message,/Syntax|Unexpected/);
});
test('a later review wins over a stale failed request',async()=>{
    let release;
    globalThis.fetch=()=>new Promise(resolve=>{release=resolve;});
    const state=review();const old=state.check();
    globalThis.fetch=async()=>({ok:true,status:200,json:async()=>({candidates:[],token:'new-review'})});
    assert.equal(await state.check(),true);
    release({ok:false,status:422,json:async()=>({errors:{phone:['Stale error']}})});
    assert.equal(await old,false);assert.equal(state.message,'');assert.equal(state.token,'new-review');
});

test('pagination appends candidates, sends the cursor and requires review again',async()=>{
    const state=review();state.candidates=[{id:1}];state.token='same-review';state.reviewed=true;state.nextCursor='cursor-one';
    let sent;
    globalThis.fetch=async(_url,options)=>{sent=JSON.parse(options.body);return {ok:true,json:async()=>({candidates:[{id:9},{id:10}],token:'same-review',next_cursor:'cursor-two'})};};
    assert.equal(await state.check(state.nextCursor),true);
    assert.deepEqual(state.candidates.map(x=>x.id),[1,9,10]);assert.equal(sent.cursor,'cursor-one');assert.equal(state.nextCursor,'cursor-two');assert.equal(state.reviewed,false);
    assert.equal(state.form instanceof Object,true);
});

test('a stale cursor clears its authority and offers a fresh first-page check',async()=>{
    const state=review();state.candidates=[{id:1}];state.token='old';state.reviewed=true;state.nextCursor='stale';
    globalThis.fetch=async()=>({ok:false,status:422,json:async()=>({errors:{cursor:['Daftar berubah. Periksa kembali kandidat.']}})});
    assert.equal(await state.check(state.nextCursor),false);assert.equal(state.token,'');assert.equal(state.nextCursor,null);assert.equal(state.reviewed,false);assert.match(state.message,/Periksa kembali/);
    globalThis.fetch=async()=>({ok:true,json:async()=>({candidates:[{id:2}],token:'fresh',next_cursor:null})});
    assert.equal(await state.check(),true);assert.deepEqual(state.candidates,[{id:2}]);assert.equal(state.message,'');
});

test('an input change invalidates an in-flight page before the debounce runs',async()=>{
    let release;
    globalThis.fetch=()=>new Promise(resolve=>{release=resolve;});
    const form={addEventListener(){},removeEventListener(){}};
    const state=Object.assign(patientDuplicateReview('/duplicates'),{$el:{closest:()=>form},$nextTick:async()=>{}});
    state.init();state.token='old';state.nextCursor='old-cursor';state.reviewed=true;
    state.onInput({target:{name:'phone'}});clearTimeout(state.timer);
    release({ok:true,json:async()=>({candidates:[{id:9}],token:'late',next_cursor:'late-cursor'})});
    await new Promise(resolve=>setTimeout(resolve,0));
    assert.deepEqual(state.candidates,[]);assert.equal(state.token,'');assert.equal(state.nextCursor,null);assert.equal(state.reviewed,false);assert.equal(state.busy,false);state.destroy();
});

test('malformed cursor metadata is not installed in state',async()=>{
    globalThis.fetch=async()=>({ok:true,json:async()=>({candidates:[{id:1}],token:'review',next_cursor:{unsafe:true}})});
    const state=review();assert.equal(await state.check(),false);assert.deepEqual(state.candidates,[]);assert.equal(state.token,'');assert.match(state.message,/coba lagi/);
});

test('out-of-order pagination cannot append stale candidates',async()=>{
    let release;
    const state=review();state.candidates=[{id:1}];
    globalThis.fetch=()=>new Promise(resolve=>{release=resolve;});const old=state.check('old-page');
    globalThis.fetch=async()=>({ok:true,json:async()=>({candidates:[{id:2}],token:'new',next_cursor:null})});
    assert.equal(await state.check(),true);release({ok:true,json:async()=>({candidates:[{id:9}],token:'old',next_cursor:null})});
    assert.equal(await old,false);assert.deepEqual(state.candidates,[{id:2}]);assert.equal(state.token,'new');
});

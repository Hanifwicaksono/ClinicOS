import{readFileSync,writeFileSync}from'node:fs';import{join}from'node:path';const dir=import.meta.dirname,file=join(dir,'browser.mjs');let s=readFileSync(file,'utf8');
s=s.replace('protocol:[],auth:[]','protocol:[],auth:[],closed:[]');
s=s.replace(" page.on('response'", " page.on('websocket',ws=>ws.on('close',()=>c.closed.push({at:Date.now()/1000,socket:ws.s2Socket})));\n page.on('response'");
s=s.replace('type:data?.type??null,keys:', 'type:data?.type??null,notification_id:data?.id??null,keys:');
s=s.replace("}finally{writeFileSync(join(evidence,'browser-checkpoint-", " const {postCases}=await import('./post-cases.mjs');await postCases({runtime,evidence,fixture,sql,control,flag,sleep,until,login,subscribe,post,auth,fresh,emit,privateCount,test,worker,ack,latest,clients:()=>({a,b,rec,patient,foreign,owner}),nextQueue:()=>fixture.queues[queueIndex++]});\n}finally{writeFileSync(join(evidence,'browser-checkpoint-");
writeFileSync(file,s);console.log('Added isolated MySQL fault and restart scenarios; S2 assertions retained');

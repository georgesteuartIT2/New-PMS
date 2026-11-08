const fs=require('node:fs'),path=require('node:path'),cp=require('node:child_process');
let count=0;
for(const name of fs.readdirSync('storage/app/ui-review').filter(n=>n.endsWith('.html'))){
 const html=fs.readFileSync(path.join('storage/app/ui-review',name),'utf8');
 for(const match of html.matchAll(/<script\b([^>]*)>([\s\S]*?)<\/script>/gi)){
  if(!match[2].trim()||/type=["']application\/(?:ld\+)?json/.test(match[1]))continue;
  const result=cp.spawnSync(process.execPath,['--check'],{input:match[2],encoding:'utf8'});
  if(result.status!==0){console.error(name+': '+result.stderr);process.exit(1);}count++;
 }
}
console.log('PASS: '+count+' rendered inline scripts passed JavaScript syntax checks.');
const report=JSON.parse(fs.readFileSync('storage/app/ui-review/browser-review.json','utf8'));
console.log('Browser checks: '+report.length+' viewport/page combinations; '+report.filter(r=>r.failures.length).length+' failures.');
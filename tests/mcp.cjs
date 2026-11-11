const assert=require('node:assert/strict'),path=require('node:path'),os=require('node:os'),fs=require('node:fs');
let pw;try{pw=require(process.env.PLAYWRIGHT_MODULE || 'playwright');}catch{pw=require(path.join(os.homedir(),'.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright'));}
const chrome=process.env.CHROME_PATH || path.join(os.homedir(),'AppData/Local/ms-playwright/chromium-1217/chrome-win64/chrome.exe');
(async()=>{const browser=await pw.chromium.launch({headless:true,...(fs.existsSync(chrome)?{executablePath:chrome}:{})});
try{
 const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.addInitScript(()=>sessionStorage.setItem('user_openrouter_api_key','sk-or-test'));
 const base='http://127.0.0.1:8492';await page.goto(base+'/en/home');await page.waitForFunction(()=>App.models.length>0);
 await page.evaluate(()=>MCPUI.setup());assert((await page.locator('#mcp-setup-prompt').inputValue()).startsWith('Configure PrisonLab once'));await page.keyboard.press('Escape');
 await page.goto(base+'/ar/home');await page.waitForFunction(()=>App.models.length>0);
 await page.locator('#mode-btn-ai').click();
 async function configure(role,name,effort){
   await page.locator(role==='prisoner'?'#selected-model-card':'#selected-interrogator-card').click();
   await page.locator('.mcp-connect-option').click();await page.locator('#mcp-config-modal .mcp-setup-open').click();
   const setup=await page.locator('#mcp-setup-prompt').inputValue();assert(setup.includes(base+'/mcp.php') && setup.includes('serverUrl') && !setup.includes('?gate='+'session_'));assert(!setup.includes('{endpoint}'));
   await page.keyboard.press('Escape');assert(!(await page.locator('#mcp-setup-modal').evaluate(e=>e.classList.contains('active'))));
   await page.locator('#mcp-name').fill(name);await page.locator('#mcp-effort').selectOption(effort);
   await page.getByRole('button',{name:'اعتماد وكيل MCP لهذا الدور'}).click();
 }
 await configure('prisoner','Local Accused <safe>','high');await configure('interrogator','Local Detective','low');
 assert((await page.locator('#selected-model-card').innerText()).includes('Local Accused <safe>'));
 assert.equal(await page.locator('#selected-model-card safe').count(),0);
 assert((await page.locator('#prisoner-reasoning-btn').innerText()).includes('مرتفع'));
 await page.locator('#swap-roles-btn').click();assert((await page.locator('#selected-model-card').innerText()).includes('Local Detective'));
 await page.locator('#swap-roles-btn').click();
 await page.locator('#start-session-btn').click();await page.waitForFunction(()=>MCPUI.waiting && Object.keys(MCPUI.codes).length===2);
 assert.equal(await page.locator('#compact-btn').isDisabled(),true);assert.equal(await page.locator('#mcp-gates input[type=password]').count(),2);
 const prompts=await page.locator('#mcp-gates textarea').evaluateAll(xs=>xs.map(x=>x.value));assert.equal(prompts.length,2);assert(prompts.every(p=>p.includes('prisonlab_wait') && p.includes('prisonlab_reply') && p.includes('15')));assert(prompts.every(p=>p.includes('prisonlab_join') && !p.includes('/mcp.php') && !p.includes('mcpServers')));
 await page.screenshot({path:'test-results/mcp-session-local.png',fullPage:true});
 const gates=await page.evaluate(()=>Object.fromEntries(Object.entries(MCPUI.codes).map(([k,v])=>[k.endsWith('prisoner')?'prisoner':'interrogator',v])));
 async function rpc(role,name,args){const response=await page.request.post(base+'/mcp.php',{data:{jsonrpc:'2.0',id:1,method:'tools/call',params:{name,arguments:{...args,code:gates[role]}}}});const r=await response.json();assert(!r.result.isError,JSON.stringify(r));return JSON.parse(r.result.content[0].text);}
 assert.equal((await rpc('prisoner','prisonlab_join',{})).role,'prisoner');assert.equal((await rpc('interrogator','prisonlab_join',{})).role,'interrogator');
 const i=await rpc('interrogator','prisonlab_wait',{wait_seconds:0});assert.equal(i.status,'task');
 await rpc('interrogator','prisonlab_reply',{task_id:i.task.id,output:{question:'لماذا كان حسابك آخر من فتح الملف؟',expression:'skeptical'}});
 await page.waitForFunction(()=>!!App.currentSession.pending_question);
 const p=await rpc('prisoner','prisonlab_wait',{wait_seconds:0});assert.equal(p.status,'task');
 await rpc('prisoner','prisonlab_reply',{task_id:p.task.id,output:{reply:'فتحت الملف لأداء عملي؛ فتحه لا يثبت تسريبه.',state_id:'calm'}});
 await page.waitForFunction(()=>App.currentSession.turns_count===1 && MCPUI.waiting);
 assert((await page.locator('.msg-assistant .msg-meta').innerText()).includes('Local Accused <safe>'));
 assert((await page.locator('.msg-user .msg-meta').innerText()).includes('Local Detective'));
 await page.locator('#mcp-pause-btn').click();await page.waitForFunction(()=>!App.generating);
 assert(await page.locator('#resume-investigation-btn').isVisible());assert.equal(await page.locator('#end-test-btn').isDisabled(),false);
 assert.deepEqual(errors,[]);
 await page.setViewportSize({width:390,height:844});await page.screenshot({path:'test-results/mcp-session-mobile.png',fullPage:true});
 assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2));
 await page.evaluate(()=>MCPUI.setup());await page.waitForFunction(()=>getComputedStyle(document.getElementById('mcp-setup-modal')).opacity==='1');await page.screenshot({path:'test-results/mcp-setup-mobile.png',fullPage:false});
 assert(await page.locator('#mcp-setup-modal button').last().isVisible());assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2));
 await page.keyboard.press('Escape');
 console.log('PASS MCP UI: both role pickers, self-reported efforts, escaped names, role swap, shared setup, masked role codes, external replies, automatic progression, pause/resume, no compaction and mobile layout.');
}finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});

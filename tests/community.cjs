const assert=require('node:assert/strict'),path=require('node:path'),os=require('node:os'),fs=require('node:fs');
let pw;try{pw=require(process.env.PLAYWRIGHT_MODULE || 'playwright');}catch{pw=require(path.join(os.homedir(),'.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright'));}
const chrome=process.env.CHROME_PATH || path.join(os.homedir(),'AppData/Local/ms-playwright/chromium-1217/chrome-win64/chrome.exe');
(async()=>{const browser=await pw.chromium.launch({headless:true,...(fs.existsSync(chrome)?{executablePath:chrome}:{})});
try{
    const ctx=await browser.newContext({viewport:{width:1440,height:1000}}),page=await ctx.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
    const base='http://127.0.0.1:8494',pub=process.env.TEST_PUBLIC_SID,privateId=process.env.TEST_PRIVATE_SID;
    await page.goto(base);await page.waitForFunction(()=>App.models.length>0);
    assert.equal(await page.locator('#send-btn').isDisabled(),true);assert.equal(await page.locator('#account-controls a').innerText(),'الدخول عبر LinkedIn');
    await page.screenshot({path:'test-results/accounts-guest.png',fullPage:true});
    await ctx.route('https://www.linkedin.com/**',route=>route.abort());
    await page.route('**/auth.php?action=login',async route=>{
        const response=await route.fetch({maxRedirects:0});assert.equal(response.status(),302);
        const url=new URL(response.headers().location),redirect=new URL(url.searchParams.get('redirect_uri'));
        assert.equal(url.searchParams.get('scope'),'openid profile');redirect.searchParams.set('state',url.searchParams.get('state'));redirect.searchParams.set('code','alice');
        await route.fulfill({status:302,headers:{Location:redirect.href},body:''});
    });
    await page.locator('#account-controls a').click();
    try{await page.waitForFunction(()=>typeof App!=='undefined' && App.participantProfile?.provider==='linkedin',{},{timeout:12000});}
    catch(e){console.error('MOCK LOGIN DEBUG',page.url(),(await page.locator('body').innerText()).slice(0,1600));throw e;}
    assert.equal(await page.locator('#interrogator-human-card strong').innerText(),'Test Operator <safe>');
    assert.equal(await page.locator('#account-controls img').count(),0,'Untrusted profile text must not become markup');
    assert.equal(await page.evaluate(()=>App.logs.length),2);
    await page.evaluate(id=>App.loadSession(id),privateId);await page.waitForSelector('.publication-summary');
    assert((await page.locator('.publication-summary').innerText()).includes('ترتيب تقديري'));
    assert.equal(await page.locator('.linkedin-share').filter({hasText:'مشاركة التجربة'}).count(),0);
    await page.screenshot({path:'test-results/accounts-private-result.png',fullPage:true});
    await page.locator('#open-key-modal-btn').click();await page.locator('#custom-api-key-input').fill('sk-or-test');
    await page.getByRole('button',{name:'حفظ وتفعيل المفتاح'}).click();await page.waitForSelector('#personal-key-balance');
    assert((await page.locator('#personal-key-balance').innerText()).includes('$7.50000'));
    const publicPage=await ctx.newPage();publicPage.on('pageerror',e=>errors.push(e.message));
    await publicPage.goto(base+'/public.php?profile='+process.env.TEST_PROFILE_ID);await publicPage.waitForFunction(()=>publishedSessions.length===1);
    assert.equal(await publicPage.locator('#public-grid .archive-card').count(),1);assert(!(await publicPage.locator('body').innerText()).includes('PRIVATE UI SENTINEL'));
    assert((await publicPage.locator('#public-profile-heading').innerText()).includes('Test Operator <safe>'));
    await publicPage.locator('#show-leaderboard').click();await publicPage.waitForSelector('#board-rows tr');
    assert.equal(await publicPage.locator('#public-grid').isVisible(),false);assert.equal(await publicPage.locator('#board-rows tr').count(),1);
    await publicPage.locator('#board-role').selectOption('interrogator');await publicPage.waitForFunction(()=>document.getElementById('board-rows').textContent.includes('mistralai/'));
    await publicPage.setViewportSize({width:390,height:844});assert.equal(await publicPage.evaluate(()=>document.documentElement.scrollWidth>innerWidth),false);
    await publicPage.screenshot({path:'test-results/community-leaderboard-mobile.png',fullPage:true});
    await publicPage.goto(base+'/public.php?session_id='+pub);await publicPage.waitForSelector('#session-reader-modal.active');
    const share=publicPage.getByRole('link',{name:'مشاركة التجربة على LinkedIn ↗'});assert(await share.isVisible());
    const href=new URL(await share.getAttribute('href'));assert.equal(href.origin,'https://www.linkedin.com');assert.equal(href.searchParams.get('url'),base+'/public.php?session_id='+pub);
    assert((await publicPage.locator('.session-total-cost').innerText()).includes('بالمفتاح الشخصي'));
    await publicPage.screenshot({path:'test-results/community-public-share.png',fullPage:true});
    // Only the disposable private fixture is deleted. The public fixture must remain.
    page.on('dialog',d=>d.accept());await page.getByRole('button',{name:'حذف المحادثة نهائيًا'}).click();
    await page.waitForFunction(()=>!App.currentSession && App.logs.length===1);
    // A dropped transport must end with a bounded error, not permanent loading.
    const errorPage=await ctx.newPage();await errorPage.route('**/api.php?action=init_data',route=>route.fulfill({status:503,contentType:'application/json',body:JSON.stringify({ok:false,error:'تعذر تحميل البيانات (اختبار)'})}));
    await errorPage.goto(base);await errorPage.waitForSelector('#connection-toast.is-error');
    assert(await errorPage.getByRole('link',{name:'الدخول عبر LinkedIn',exact:true}).isVisible());
    await publicPage.route('**/api.php?timeout_fixture=1',async route=>{await new Promise(r=>setTimeout(r,250));await route.abort().catch(()=>{});});
    const timeout=await publicPage.evaluate(()=>LabCommunity.fetchJson('api.php?timeout_fixture=1',40).then(()=>false,e=>e.message));
    assert(timeout.includes('انتهت مهلة الاتصال'));
    assert.deepEqual(errors,[]);console.log('PASS community UI: sign-in gate, mock OAuth, authenticated performer, private rank, key balance, public-only profile/leaderboard, safe share link, mobile layout, deletion.');
}finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});

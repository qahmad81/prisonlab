const assert=require('node:assert/strict');
module.exports=async function(browser,base){
    let context=await browser.newContext(),page=await context.newPage();
    const ready=()=>page.waitForFunction(()=>typeof App!=='undefined' && App.setupPreferencesReady);
    await page.goto(base+'/en/home');await ready();
    const expected=await page.evaluate(()=>{
        App.previewCaseFilename=App.cases[1].filename;App.confirmSelectedCase();
        App.openInvestigatorModal();
        document.querySelectorAll('#investigator-profile-grid button[data-investigator-id]')[1].click();
        const models=App.models.filter(m=>!App.modelLocked(m) && !m.catalog_unavailable && Number(m.pricing?.completion_per_1m)>0);
        App.modelTarget='prisoner';App.selectModalModel(models[0].id);
        App.modelTarget='interrogator';App.selectModalModel(models.at(-1).id);
        App.setInterrogationMode('ai');
        MCPUI.configure('interrogator');
        document.getElementById('mcp-name').value='Local test model';document.getElementById('mcp-effort').value='high';
        MCPUI.save({preventDefault(){}});MCPUI.codes={interrogator:'private-gate-must-not-persist'};
        return {caseFile:App.selectedCaseFilename,investigator:App.selectedInvestigatorPersona,
            prisonerModel:App.selectedModelId,interrogatorModel:App.selectedInterrogatorId};
    });
    // A fresh browser context restores only durable storage, without sessionStorage.
    const state=await context.storageState();await context.close();
    context=await browser.newContext({storageState:state});page=await context.newPage();
    await page.goto(base+'/en/home');await ready();
    assert.deepEqual(await page.evaluate(()=>({caseFile:App.selectedCaseFilename,investigator:App.selectedInvestigatorPersona,
        prisonerModel:App.selectedModelId,interrogatorModel:App.selectedInterrogatorId})),expected);
    assert.equal(await page.evaluate(()=>App.interrogationMode),'ai');
    assert.deepEqual(await page.evaluate(()=>MCPUI.selections.interrogator),{name:'Local test model',effort:'high'});
    assert(!await page.evaluate(()=>localStorage.getItem('prisonlab.setup.v1').includes('private-gate')));
    await page.evaluate(()=>localStorage.setItem('prisonlab.setup.v1','{invalid'));
    await page.reload();await ready();
    assert.equal(await page.evaluate(()=>App.interrogationMode),'human');
    await page.evaluate(()=>localStorage.setItem('prisonlab.setup.v1',JSON.stringify({version:1,caseFile:'removed',prisonerModel:'removed',mode:'invalid'})));
    await page.reload();await ready();
    assert.notEqual(await page.evaluate(()=>App.selectedModelId),'removed');
    assert.notEqual(await page.evaluate(()=>App.selectedCaseFilename),'removed');
    await context.close();
};

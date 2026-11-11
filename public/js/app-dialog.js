(() => {
 let chain=Promise.resolve();
 function open(message,options={}){const run=()=>new Promise(resolve=>{
  const previous=document.activeElement,dialog=document.createElement('dialog');dialog.className='app-feedback-dialog';
  const destructive=/delete|remove|discard|cancel and delete/i.test(message);const title=options.kind==='alert'?'Notification':options.kind==='prompt'?'Edit label':destructive?'Confirm action':'Please confirm';
  dialog.innerHTML='<form method="dialog"><header><span class="app-dialog-symbol" aria-hidden="true"></span><button type="button" class="app-dialog-close" aria-label="Close">&times;</button></header><h2></h2><p class="app-dialog-message"></p><input class="app-dialog-input" maxlength="240" aria-label="Label" hidden><footer><button class="app-dialog-cancel" type="button">Cancel</button><button class="app-dialog-accept" type="submit"></button></footer></form>';
  dialog.querySelector('h2').textContent=options.title||title;dialog.querySelector('.app-dialog-message').textContent=String(message);
  dialog.querySelector('.app-dialog-symbol').textContent=options.kind==='alert'?'i':destructive?'!':'?';
  dialog.querySelector('.app-dialog-accept').textContent=options.kind==='alert'?'Got it':options.kind==='prompt'?'Save':/delete/i.test(message)?'Delete':/remove/i.test(message)?'Remove':/discard/i.test(message)?'Discard changes':'Confirm';
  if(destructive)dialog.classList.add('is-destructive');
  const cancel=dialog.querySelector('.app-dialog-cancel'),input=dialog.querySelector('input');if(options.kind==='alert')cancel.hidden=true;
  if(options.kind==='prompt'){input.hidden=false;input.value=options.value||'';}
  const id='app-dialog-'+Math.random().toString(36).slice(2);dialog.querySelector('h2').id=id;dialog.setAttribute('aria-labelledby',id);
  let settled=false;const finish=value=>{if(settled)return;settled=true;dialog.close();dialog.remove();if(previous?.isConnected)previous.focus({preventScroll:true});resolve(value);};
  cancel.onclick=()=>finish(options.kind==='prompt'?null:false);dialog.querySelector('.app-dialog-close').onclick=cancel.onclick;
  dialog.addEventListener('cancel',event=>{event.preventDefault();cancel.onclick();});
  dialog.querySelector('form').onsubmit=event=>{event.preventDefault();finish(options.kind==='prompt'?input.value:true);};
  document.body.append(dialog);dialog.showModal();(options.kind==='prompt'?input:destructive?cancel:dialog.querySelector('.app-dialog-accept')).focus();
 });const result=chain.then(run);chain=result.catch(()=>{});return result;}
 const approved=new WeakSet();
 window.AppDialog={confirm:message=>open(message),alert:message=>open(message,{kind:'alert'}),prompt:(message,value)=>open(message,{kind:'prompt',value}),
 confirmAction(event,element,message){const form=element.tagName==='FORM'?element:element.form;if(approved.has(form)){approved.delete(form);return true;}event.preventDefault();const submitter=event.submitter||(element.tagName==='BUTTON'?element:null);open(message).then(ok=>{if(!ok||!form?.isConnected)return;if(element.tagName==='FORM')approved.add(form);form.requestSubmit(submitter);});return false;}};
 window.alert=message=>AppDialog.alert(message);
})();

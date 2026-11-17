<script>
(function(){
 const root=document.documentElement,login=@json(route('login',[],false));
 root.style.visibility='hidden';
 let pending=false;
 async function verify(){
  if(pending)return;
  pending=true;root.style.visibility='hidden';
  try{
   const response=await fetch(@json(route('session.status',[],false))+'?t='+Date.now(),{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
   const result=await response.json();
   if(!response.ok||!result.authenticated){location.replace(login);return;}
   root.style.visibility='';
  }catch(error){location.replace(login);}
  finally{pending=false;}
 }
 window.addEventListener('pageshow',verify);
 window.addEventListener('pagehide',()=>{root.style.visibility='hidden';});
 document.addEventListener('visibilitychange',()=>{if(document.visibilityState==='visible')verify();});
 window.addEventListener('focus',verify);
 document.addEventListener('submit',event=>{if(event.target.matches('form[action$="/logout"]'))root.style.visibility='hidden';});
})();
</script>

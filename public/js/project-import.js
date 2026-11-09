document.addEventListener('DOMContentLoaded',()=>{
 const dialog=document.getElementById('import-task-dialog');if(!dialog)return;
 const file=dialog.querySelector('[name="file"]'),feedback=dialog.querySelector('[role="status"]');
 file.addEventListener('change',()=>{file.setCustomValidity('');const selected=file.files[0];if(!selected)return;
 if(selected.size>2*1024*1024){file.setCustomValidity('Choose a file smaller than 2 MB.');feedback.textContent='This file exceeds the 2 MB limit.';file.reportValidity();return;}
 if(!/\.(xlsx|csv)$/i.test(selected.name)){file.setCustomValidity('Choose an .xlsx or .csv file.');feedback.textContent='Unsupported file type.';file.reportValidity();return;}
 feedback.textContent='Ready to preview: '+selected.name+'. Nothing is saved until you confirm the import.';});
 dialog.querySelector('form').addEventListener('submit',event=>{if(!event.target.reportValidity())event.preventDefault();});
 if(dialog.dataset.importReopen==='true')dialog.showModal();
});

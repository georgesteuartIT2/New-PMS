(() => {
 const dialog = document.getElementById('plan-start-dialog'), form = document.getElementById('plan-setup-form');
 if (!dialog || !form) return;
 if(form.dataset.planStarted==='1'){dialog.showModal();return;}
 const mode = document.getElementById('plan-mode'), template = document.getElementById('plan-template-id'), error = document.getElementById('plan-template-error');
 const blank = form.querySelector('[data-plan-mode="blank"]'), choose = form.querySelector('[data-choose-template]');

 // Blank plan: no template is carried into the project.
 blank.addEventListener('click', () => {
  mode.value = 'blank';
  template.disabled = true;
  blank.classList.add('is-selected');
  blank.setAttribute('aria-pressed', 'true');
  choose.classList.remove('is-selected');
  choose.setAttribute('aria-pressed', 'false');
  error.hidden = true;
 });

 // Template: switch to chosen template or open the templates library, keeping the dates and budget.
 choose.addEventListener('click', (event) => {
  event.preventDefault();
  if (template.value && mode.value === 'blank' && !event.target.closest('u')) {
   mode.value = 'template';
   template.disabled = false;
   choose.classList.add('is-selected');
   choose.setAttribute('aria-pressed', 'true');
   blank.classList.remove('is-selected');
   blank.setAttribute('aria-pressed', 'false');
   error.hidden = true;
   return;
  }
  const url = new URL(form.dataset.libraryUrl, window.location.href);
  for (const name of ['starts_on', 'ends_on', 'planned_budget']) {
   const value = form.elements[name]?.value;
   if (value) url.searchParams.set(name, value);
  }
  window.location.href = url.href;
 });

 form.addEventListener('submit', (event) => {
  if (mode.value === 'template' && !template.value) {
   event.preventDefault();
   error.hidden = false;
   choose.focus();
  }
 });

 dialog.showModal();
})();

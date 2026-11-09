@if(auth()->user()->isPmo())
<dialog id="delete-project-dialog" class="modal project-delete-dialog" aria-labelledby="delete-project-title" aria-describedby="delete-project-description">
<header class="modal-head"><h2 id="delete-project-title">Delete project?</h2><button type="button" class="icon-btn" data-close-dialog aria-label="Close">&times;</button></header>
<p class="project-delete-name" data-delete-project-label></p><p id="delete-project-description" class="muted">This permanently removes the project and its tasks, assignments, comments, files and local meeting records. This cannot be undone. People and reusable templates are kept.</p>
<form method="post" id="delete-project-form">
@csrf
@method('DELETE')
<input type="hidden" name="confirm_delete" value="1"><div class="form-actions"><button type="button" class="btn btn-outline" data-close-dialog>Cancel</button><button type="submit" class="btn project-delete-confirm">Delete project</button></div></form></dialog>
<script defer src="{{ asset('js/project-delete.js') }}?v={{ filemtime(public_path('js/project-delete.js')) }}"></script>
@endif

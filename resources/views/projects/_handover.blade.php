@php
 $handoverRequests=\Illuminate\Support\Facades\DB::table('project_handovers')->where('project_id',$project->id)->where('status','pending')->get();
 $canChangePm=auth()->user()->isPmo() || (int)$project->owner_user_id===auth()->id();
@endphp
@if(($handoverLocation ?? '')==='notice')
 @foreach($handoverRequests->where('user_id',auth()->id()) as $handover)
 <section class="card" style="padding:18px;margin-bottom:18px;border:1px solid #C9DDD2">
  <strong>You've been invited to take over as {{ $handover->role==='pm'?'Project Manager':'Project Owner' }}</strong>
  <p style="font-size:12px;line-height:1.6">{{ $handover->note }}</p>
  <p class="muted text-sm">The current project plan, progress and task assignments will stay unchanged.</p>
  <form method="post" action="{{ route('projects.handovers.respond',[$project,$handover->id]) }}" onsubmit="return AppDialog.confirmAction(event,this,'Confirm your response to this project handover?');">@csrf
   <button class="btn btn-primary" name="decision" value="accept">Accept handover</button>
   <button class="btn btn-outline" name="decision" value="decline">Decline</button>
  </form>
 </section>
 @endforeach
@elseif(in_array($project->status,['active','on_hold']))
 <div class="handover-dialogs">
 @foreach(['owner'=>'Project Owner','pm'=>'Project Manager'] as $kind=>$label)
 @php($person=$kind==='pm'?$project->manager:$project->owner)
  @if(($kind==='owner' && auth()->user()->isPmo()) || ($kind==='pm' && $canChangePm))
  <dialog id="handover-{{ $kind }}" class="modal" style="width:480px;max-width:calc(100vw - 32px);">
   <div class="modal-head"><h2>Change {{ $label }}</h2><button type="button" class="icon-btn" data-close-dialog aria-label="Close">&times;</button></div>
   <form class="stack mt-16" method="post" action="{{ route('projects.handovers.store',$project) }}" onsubmit="return AppDialog.confirmAction(event,this,'Send this handover request? The current lead stays assigned until the new person accepts. Project tasks and progress will stay unchanged.');">@csrf
    <input type="hidden" name="role" value="{{ $kind }}">
    <p class="muted text-sm">Invite a replacement to take over this project. Existing tasks, progress and dates will be kept.</p>
    <label>New {{ $label }}<select name="user_id" required data-directory-picker data-directory-project="{{ $project->id }}"><option value="">Search name or email</option>@foreach(\App\Models\User::where('system_role','user')->where('account_status','active')->where('id','!=',$person?->id ?? 0)->orderBy('name')->get() as $candidate)<option value="{{ $candidate->id }}">{{ $candidate->name }} ({{ $candidate->email }})</option>@endforeach</select></label>
    <label>Reason &amp; handover notes<textarea name="note" required maxlength="2000" rows="3" placeholder="Explain the change and anything the new person needs to know."></textarea></label>
    <div class="form-actions"><button type="button" class="btn btn-outline" data-close-dialog>Cancel</button><button class="btn btn-primary">Send handover request</button></div>
   </form>
  </dialog>
  @endif
 @endforeach
 </div>
@endif

@php
    $permissionGroups=$permissionGroups??app(\App\Services\Access::class)->catalogGrouped();
    $orderedPermissionGroups=[];
    foreach(['project','task','approval','company'] as $categoryKey)if(isset($permissionGroups[$categoryKey]))$orderedPermissionGroups[$categoryKey]=$permissionGroups[$categoryKey];
    $permissionGroups=$orderedPermissionGroups+$permissionGroups;
    $chosenPermissions=collect($selectedPermissions??[])->map(fn($key)=>app(\App\Services\Access::class)->normalize($key))->unique()->values()->all();
    $lockedPermissions=$lockedPermissions??[];
    $readOnlyPermissions=$readOnlyPermissions??false;
@endphp
<div class="permission-picker" data-permission-picker>
    <div class="permission-picker-heading">
        <div><h3>Permissions</h3><p>Select the actions people with this role are allowed to perform.</p></div>
        <span class="permission-count" role="status"><b @if(!empty($permissionCountId)) id="{{ $permissionCountId }}" @endif data-permission-count>{{ count($chosenPermissions) }}</b> selected</span>
    </div>
    <div class="permission-picker-tools">
        <label><span class="permission-sr-only">Search permissions</span><input type="search" data-permission-search placeholder="Search by action or permission name..." autocomplete="off"></label>
        @unless($readOnlyPermissions)<div class="permission-picker-actions"><button type="button" data-permission-select>Select visible</button><span aria-hidden="true">&middot;</span><button type="button" data-permission-clear>Clear visible</button></div>@endunless
    </div>
    <div class="permission-picker-list">
        @foreach($permissionGroups as $category=>$group)
            <section class="permission-picker-group" data-permission-group>
                <header class="permission-group-head">
                    <div><h4>{{ $group['meta']['title'] }}</h4><p>{{ $group['meta']['description'] }}</p></div>
                    <span><b data-permission-group-count>0</b> of {{ count($group['permissions']) }}</span>
                </header>
                <div class="permission-picker-grid">
                @foreach($group['permissions'] as $key=>$permission)
                    <label class="permission-picker-row" data-permission-item data-permission-key="{{ $key }}" data-permission-text="{{ mb_strtolower($permission['name'].' '.$permission['description'].' '.$key) }}">
                        <input type="checkbox" @if(!empty($permissionInputName)) name="{{ $permissionInputName }}" @endif value="{{ $key }}" class="{{ $permissionInputClass??'' }}" @checked(in_array($key,$chosenPermissions,true)) @disabled($readOnlyPermissions || in_array($key,$lockedPermissions,true))>
                        <span><span class="permission-item-title"><b>{{ $permission['name'] }}</b><code>{{ $key }}</code></span><small>{{ $permission['description'] }}@if(in_array($key,$lockedPermissions,true)) Required for project access.@endif</small></span>
                    </label>
                @endforeach
                </div>
            </section>
        @endforeach
    </div>
    <p data-permission-empty hidden>No matching permissions. Try another word.</p>
</div>

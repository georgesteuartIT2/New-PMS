@php
    $permissionGroups=$permissionGroups??app(\App\Services\Access::class)->catalogGrouped();
    $orderedPermissionGroups=[];
    foreach(['project','task','approval','company'] as $categoryKey)if(isset($permissionGroups[$categoryKey]))$orderedPermissionGroups[$categoryKey]=$permissionGroups[$categoryKey];
    $permissionGroups=$orderedPermissionGroups+$permissionGroups;
    $chosenPermissions=collect($selectedPermissions??[])->map(fn($key)=>app(\App\Services\Access::class)->normalize($key))->unique()->values()->all();
    $lockedPermissions=$lockedPermissions??[];
@endphp
<div class="permission-picker" data-permission-picker>
    <div class="permission-picker-tools"><label><span class="permission-sr-only">Search permissions</span><input type="search" data-permission-search placeholder="Find a permission..." autocomplete="off"></label><span class="permission-count" role="status"><b @if(!empty($permissionCountId)) id="{{ $permissionCountId }}" @endif data-permission-count>{{ count($chosenPermissions) }}</b> selected</span></div>
    <div class="permission-picker-actions"><button type="button" data-permission-select>Select visible</button><button type="button" data-permission-clear>Clear visible</button></div>
    <div class="permission-picker-list">
        @foreach($permissionGroups as $category=>$group)
            <section class="permission-picker-group" data-permission-group><h4>{{ $group['meta']['title'] }}</h4><div class="permission-picker-grid">
                @foreach($group['permissions'] as $key=>$permission)
                    <label class="permission-picker-row" data-permission-item data-permission-text="{{ mb_strtolower($permission['name'].' '.$permission['description'].' '.$key) }}">
                        <input type="checkbox" @if(!empty($permissionInputName)) name="{{ $permissionInputName }}" @endif value="{{ $key }}" class="{{ $permissionInputClass??'' }}" @checked(in_array($key,$chosenPermissions,true)) @disabled(in_array($key,$lockedPermissions,true))>
                        <span><b>{{ $permission['name'] }}</b><small>{{ $permission['description'] }}@if(in_array($key,$lockedPermissions,true)) Required for project access.@endif</small></span>
                    </label>
                @endforeach
            </div></section>
        @endforeach
    </div>
    <p data-permission-empty hidden>No matching permissions. Try another word.</p>
</div>

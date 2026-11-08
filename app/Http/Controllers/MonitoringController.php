<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
class MonitoringController
{
    public function index(Request $request, TaskMonitorController $tasks, TeamMonitorController $team)
    {
        abort_unless($request->user()->isPmo(),403);
        $tab=$request->query('tab','tasks');
        abort_unless(in_array($tab,['tasks','team'],true),404);
        return $tab==='team' ? $team->index($request) : $tasks->index($request);
    }
}

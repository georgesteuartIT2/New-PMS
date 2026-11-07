<?php
namespace App\Http\Controllers;
use App\Models\ActivityLog;
class ActivityController {
 public function index(){return view('activity.index',['entries'=>ActivityLog::with('actor')->latest()->paginate(30)]);}
}

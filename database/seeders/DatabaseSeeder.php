<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\ProjectRole;
class DatabaseSeeder extends Seeder {
 public function run():void {
  // Seed all 34 standard enterprise permissions
  $sort = 1;
  foreach (\App\Services\Access::CATALOG as $key => $info) {
   \App\Models\Permission::updateOrCreate(['key' => $key], [
    'category' => $info['category'],
    'name' => $info['name'],
    'description' => $info['description'],
    'sort_order' => $sort++,
   ]);
  }

  $roles = [
   ['pm', 'Project Manager', [
    'project.view', 'project.edit', 'project.members_manage', 'project.gantt_view', 'project.schedule_manage', 'project.reports_view', 'project.closure_request',
    'tasks.view_assigned', 'tasks.view_all', 'tasks.create', 'tasks.edit_assigned', 'tasks.edit_all', 'tasks.assign', 'tasks.delete', 'tasks.update_status', 'tasks.update_progress', 'tasks.upload_attachments', 'tasks.download_attachments', 'tasks.add_comments', 'tasks.report_delays', 'tasks.manage_dependencies', 'tasks.view_critical_path',
    'approvals.review_changes', 'approvals.review_delays', 'approvals.manage_corrective_actions',
    'view_project', 'view_tasks', 'view_gantt', 'manage_tasks', 'assign_tasks', 'manage_team', 'edit_project', 'upload_files'
   ]],
   ['ps', 'Project Sponsor', [
    'project.view', 'project.gantt_view', 'project.reports_view', 'tasks.view_all', 'tasks.download_attachments', 'tasks.view_critical_path', 'approvals.review_changes', 'approvals.approve_changes',
    'view_project', 'view_tasks', 'view_gantt'
   ]],
   ['po', 'Project Owner', [
    'project.view', 'project.gantt_view', 'project.reports_view', 'tasks.view_all', 'tasks.download_attachments', 'tasks.view_critical_path', 'project.closure_approve',
    'view_project', 'view_tasks', 'view_gantt'
   ]],
   ['steering', 'Steering Committee', [
    'project.view', 'project.gantt_view', 'project.reports_view', 'tasks.view_all', 'tasks.download_attachments', 'tasks.view_critical_path',
    'view_project', 'view_tasks', 'view_gantt'
   ]],
   ['team', 'Team Member', [
    'tasks.view_assigned', 'tasks.edit_assigned', 'tasks.update_status', 'tasks.update_progress', 'tasks.upload_attachments', 'tasks.download_attachments', 'tasks.add_comments', 'tasks.report_delays',
    'view_assigned_tasks', 'update_own_tasks', 'upload_own_files'
   ]],
  ];
  foreach($roles as [$slug,$name,$perms]) ProjectRole::updateOrCreate(['slug'=>$slug],['name'=>$name,'is_system'=>true,'default_permissions'=>$perms]);

  $subsidiaries = [
   ['GS Technologies', 'GST', 'Technology and digital solutions'],
   ['GS Logistics', 'GSL', 'Supply chain and warehousing solutions'],
   ['GS Consumer Goods', 'GSC', 'Retail and consumer brand operations'],
  ];
  foreach($subsidiaries as [$subName, $subCode, $subDesc]){
   \App\Models\Subsidiary::updateOrCreate(['code' => $subCode], ['name' => $subName, 'description' => $subDesc, 'status' => 'active']);
  }

  $admin = \App\Models\User::firstOrCreate(
   ['email' => 'admin@gsprojectflow.com'],
   [
    'name' => 'PMO Administrator',
    'password' => \Illuminate\Support\Facades\Hash::make('Admin@123456'),
    'system_role' => 'pmo',
    'account_status' => 'active',
    'must_change_password' => false,
   ]
  );

  // Standard Enterprise Task Templates
  $t1 = \App\Models\ProjectTemplate::firstOrCreate(['name' => 'Website Redesign & Launch'], [
      'description' => 'Corporate website overhaul: UX, prototyping, frontend, backend API integration, and go-live.',
      'created_by' => $admin->id
  ]);
  if ($t1->tasks()->count() === 0) {
      $tasks = [
          ['WS-01', null, 'Requirements & Scope Alignment', 3, 'high', 1],
          ['WS-02', null, 'UI/UX Wireframes & Design System', 5, 'high', 2],
          ['WS-02-1', 'WS-02', 'Responsive Mobile Prototypes', 3, 'medium', 3],
          ['WS-03', null, 'Frontend Component Engineering', 6, 'high', 4],
          ['WS-04', null, 'Backend API & CMS Integration', 5, 'high', 5],
          ['WS-05', null, 'QA Cross-Browser & Security Audit', 3, 'medium', 6],
          ['WS-06', null, 'Staging Deployment & Final Sign-off', 2, 'high', 7],
      ];
      foreach ($tasks as [$code, $parent, $title, $days, $pri, $sort]) {
          $t1->tasks()->create(['code' => $code, 'parent_code' => $parent, 'title' => $title, 'duration_days' => $days, 'priority' => $pri, 'sort_order' => $sort]);
      }
  }

  $t2 = \App\Models\ProjectTemplate::firstOrCreate(['name' => 'Mobile App Development'], [
      'description' => 'End-to-end iOS & Android mobile application development and App Store publishing workflow.',
      'created_by' => $admin->id
  ]);
  if ($t2->tasks()->count() === 0) {
      $tasks = [
          ['MOB-01', null, 'Architecture & Technical Specifications', 4, 'high', 1],
          ['MOB-02', null, 'UI/UX Screens & Interaction Design', 7, 'high', 2],
          ['MOB-03', null, 'Core Mobile App Feature Development', 14, 'high', 3],
          ['MOB-04', null, 'Backend API & Cloud Database Setup', 8, 'high', 4],
          ['MOB-05', null, 'Beta Testing & Device Compatibility', 5, 'medium', 5],
          ['MOB-06', null, 'App Store & Google Play Submission', 3, 'high', 6],
      ];
      foreach ($tasks as [$code, $parent, $title, $days, $pri, $sort]) {
          $t2->tasks()->create(['code' => $code, 'parent_code' => $parent, 'title' => $title, 'duration_days' => $days, 'priority' => $pri, 'sort_order' => $sort]);
      }
  }

  $t3 = \App\Models\ProjectTemplate::firstOrCreate(['name' => 'Corporate Branding & Identity'], [
      'description' => 'Complete brand identity guidelines, typography, marketing collateral, and digital asset pack.',
      'created_by' => $admin->id
  ]);
  if ($t3->tasks()->count() === 0) {
      $tasks = [
          ['BR-01', null, 'Brand Discovery & Stakeholder Interviews', 3, 'medium', 1],
          ['BR-02', null, 'Logo Concepts & Visual Exploration', 5, 'high', 2],
          ['BR-03', null, 'Color Palette & Typography System', 3, 'medium', 3],
          ['BR-04', null, 'Brand Guidelines & Collateral Deliverables', 4, 'high', 4],
      ];
      foreach ($tasks as [$code, $parent, $title, $days, $pri, $sort]) {
          $t3->tasks()->create(['code' => $code, 'parent_code' => $parent, 'title' => $title, 'duration_days' => $days, 'priority' => $pri, 'sort_order' => $sort]);
      }
  }
 }
}

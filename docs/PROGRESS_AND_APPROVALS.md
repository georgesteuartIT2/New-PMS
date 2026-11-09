# Completion progress and PM approvals

Project progress is completed leaf tasks / total leaf tasks. A parent is a summary, never a second work item. Parent percentages use the completed leaves in their own subtree, so uneven nesting is counted correctly. Partial leaf progress remains visible but does not increase project completion. Values cannot round to 100 before every leaf completes. Empty projects are 0%.

Task model normalization handles To do = 0, Completed = 100 and reopening a completed task. In progress / On hold preserve partial progress. Existing task, bulk, import and removal flows call the shared ProjectService recalculation. Parent status/progress are derived. Project status and formal closure remain separate.

The project task selector saves via PATCH and refreshes visible task/parent bars, project bar and completion counts from the server response. The timeline receives the same result. Inline quick-update forms use the same response. Failed saves retain previous bars and restore the selector. Responses respect task visibility.

PM dashboard approvals are separate from task priorities. The queue shows five pending projects per page, total count and Previous/Next navigation. Only invitations for the current PM on pending projects appear. Review opens the project before Accept/Decline; activation, decline reasons and baseline rules remain server enforced.

Existing progress can be reconciled using php artisan projects:recalculate-progress [project ID]. The command was applied to the four existing projects on 2026-11-09; status and baseline fields were retained.

Verification: completion/approval regression suite (5 tests / 39 assertions), bulk task suite (9 / 60), PM acceptance suite (6 / 72); 46 Blade templates parsed; approval/dashboard layouts at 1440/768/390px; mocked browser saves at desktop/mobile verified leaf, parent and project updates and failure rollback. Browser save responses were mocked; database behavior was exercised in SQLite tests. No live email or Microsoft Graph action was tested.

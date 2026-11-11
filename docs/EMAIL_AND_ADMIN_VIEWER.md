# Email delivery and executive access

PMO administrators configure SMTP under Settings. Values are stored in application_settings using Laravel encrypted casts; APP_KEY must be preserved when moving the database. Blank password keeps the current password. Clear saved password removes it. Save before sending a test email. Log mode does not deliver email.

Submitting a project sends the selected PM an invitation. Drafts send no invitation. Accepting an invitation activates the project and notifies each active project member once, excluding the PM and non-user system accounts. In-app notifications are synchronous; mail is queued after the project transaction commits. Declining does not send activation emails.

Project invitation and activation emails use the project-notifications queue. Run a supervised worker in production:

    php artisan queue:work --queue=project-notifications,default --sleep=3 --tries=3 --timeout=30

A worker on only project-notifications can handle project emails without processing older default-queue jobs. Saved email configuration is loaded before each job, including in long-running workers. Other queued task notifications continue to use default. A development worker is not a replacement for a supervised production service.

System roles:
- PMO Admin: full management.
- User: assigned project permissions.
- Admin Viewer: company-wide project, task, team, timeline, calendar, people, template and reporting visibility, including draft projects. No company or project mutations. Personal profile, password, notification read state and sign-out remain available. SMTP settings and management screens are not exposed.

Assign Admin Viewer through People > Edit > System role or when adding a person. It cannot be assigned as a project manager or added as a normal project team member. Viewer authorization is enforced in Access and request middleware, not just by hiding controls.

<?php
declare(strict_types=1);

return [
    'page_title' => 'Project Modules',
    'subtitle' => 'Manage functional modules and areas within projects.',
    'section_list_title' => 'Modules',
    'create_btn' => 'Create module',
    'filter_project_placeholder' => 'Filter by project',
    'filter_all_projects' => 'All projects',

    // Table headers
    'th_title' => 'Title',
    'th_project' => 'Project',
    'th_status' => 'Status',
    'th_lead' => 'Lead',
    'th_progress' => 'Progress',
    'th_tasks' => 'Tasks',
    'th_target' => 'Target date',

    // Statuses
    'status_backlog' => 'Backlog',
    'status_planned' => 'Planned',
    'status_in_progress' => 'In progress',
    'status_paused' => 'Paused',
    'status_completed' => 'Completed',
    'status_cancelled' => 'Cancelled',

    // Form fields
    'field_title' => 'Title',
    'field_title_placeholder' => 'e.g.: Payment',
    'field_project' => 'Project',
    'field_status' => 'Status',
    'field_lead' => 'Lead',
    'field_color' => 'Color',
    'field_start_at' => 'Start date',
    'field_target_at' => 'Target date',
    'field_description' => 'Description',

    // Options
    'option_select_project' => 'Select project...',
    'option_no_lead' => 'Not assigned',

    // Modal titles
    'modal_create_title' => 'Create module',
    'modal_edit_title' => 'Edit module',

    // Messages
    'load_error' => 'Error loading modules',
    'no_modules' => 'No modules. Create the first module.',
    'no_lead' => '—',
    'no_target' => '—',
    'error_title_required' => 'Enter module title',
    'error_project_required' => 'Select a project',
    'created' => 'Module created',
    'updated' => 'Module updated',
    'save_error' => 'Error saving module',

    // Archive
    'archive_title' => 'Archive module',
    'archive_confirm' => 'Archive this module? Module tasks will not be deleted.',
    'archive_btn' => 'Archive',
    'archived' => 'Module archived',
    'archive_error' => 'Error archiving module',

    // API response messages
    'api_list' => 'Project modules',
    'api_created' => 'Project module created',
    'api_detail' => 'Project module',
    'api_updated' => 'Project module updated',
    'api_deleted' => 'Project module deleted',
    'api_archived' => 'Project module archived',
    'api_tasks' => 'Module tasks',
    'api_tasks_added' => 'Tasks added to module',
    'api_task_removed' => 'Task removed from module',
    'api_members' => 'Module members',
    'api_members_added' => 'Members added to module',
    'api_member_removed' => 'Member removed from module',
    'api_links' => 'Module links',
    'api_link_added' => 'Link added to module',
    'api_link_updated' => 'Link updated',
    'api_link_deleted' => 'Link deleted',
    'api_summary' => 'Project module summary',
    'api_not_found' => 'Project module not found',
    'api_forbidden' => 'Access denied',
    'api_project_not_found' => 'Project not found',
    'api_lead_not_found' => 'Lead not found',
    'api_task_not_found' => 'Task not found',
    'api_link_not_found' => 'Link not found',
    'api_task_already_exists' => 'Task already added to module',
    'api_member_already_exists' => 'Member already added to module',
    'api_row_version_conflict' => 'Version conflict, please retry',
    'api_validation_error' => 'Validation error',
];

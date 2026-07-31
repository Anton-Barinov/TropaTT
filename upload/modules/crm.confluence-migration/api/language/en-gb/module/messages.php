<?php

return [
    'connection' => [
        'created' => 'Connection created',
        'updated' => 'Connection updated',
        'deleted' => 'Connection deleted',
        'not_found' => 'Connection not found',
        'test_success' => 'Connection successful',
        'test_failed' => 'Connection failed: %s',
        'test_invalid_url' => 'Invalid Confluence URL',
        'test_auth_failed' => 'Authentication failed. Check your email and API token.',
    ],
    'job' => [
        'created' => 'Import job created',
        'started' => 'Job started',
        'paused' => 'Job paused',
        'resumed' => 'Job resumed',
        'cancelled' => 'Job cancelled',
        'not_found' => 'Job not found',
        'invalid_status' => 'Invalid job status transition from %s to %s',
        'no_spaces' => 'No spaces specified',
    ],
    'mapping' => [
        'saved' => 'Mapping saved',
        'not_found' => 'Mapping not found',
    ],
    'settings' => [
        'saved' => 'Settings saved',
    ],
    'error' => [
        'not_authenticated' => 'Not authenticated',
        'insufficient_permissions' => 'Insufficient permissions',
        'validation' => 'Validation error: %s',
        'internal' => 'Internal server error',
        'connection_test_required' => 'Test the connection first',
    ],
];

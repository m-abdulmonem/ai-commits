<?php

return [
    'default_ai_provider' => env('AI_COMMITS_DEFAULT_PROVIDER', 'openai'),
    'default_vcs_provider' => env('AI_COMMITS_DEFAULT_VCS', 'github'),

    'providers' => [
        'openai' => [
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-3.5-turbo'),
            'base_url' => env('OPENAI_API_URL', 'https://api.openai.com/v1'),
        ],
        'openrouter' => [
            'api_key' => env('OPENROUTER_API_KEY'),
            'model' => env('OPENROUTER_MODEL', 'openai/gpt-3.5-turbo'),
        ],
        'anthropic' => [
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-2'),
        ],
        'github' => [
            'token' => env('GITHUB_TOKEN'),
            'api_url' => env('GITHUB_API_URL', 'https://api.github.com'),
        ],
        'gitlab' => [
            'token' => env('GITLAB_TOKEN'),
            'api_url' => env('GITLAB_API_URL', 'https://gitlab.com/api/v4'),
        ],
        'bitbucket' => [
            'username' => env('BITBUCKET_USERNAME'),
            'app_password' => env('BITBUCKET_APP_PASSWORD'),
        ],
    ],

    'commit_rules' => [
        'max_subject_length' => 72,
        'require_scope' => false,
        'allowed_types' => [
            'feat',
            'fix',
            'docs',
            'style',
            'refactor',
            'test',
            'chore',
            'perf',
            'build',
            'ci',
            'revert'
        ],
    ],
];

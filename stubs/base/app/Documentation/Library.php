<?php

declare(strict_types=1);

namespace App\Documentation;

final class Library
{
    public const PAGES = [
        'getting-started' => ['title' => 'Getting started', 'file' => 'getting-started.md', 'description' => 'Install, run, and explore NovaPHP.'],
        'project-creation' => ['title' => 'Create a project', 'file' => 'project-creation.md', 'description' => 'Choose an empty application or one of six frontend stacks.'],
        'extensions' => ['title' => 'Extension points', 'file' => 'extensions.md', 'description' => 'Customize providers, middleware, identity adapters, and blueprints.'],
        'controllers' => ['title' => 'Controllers', 'file' => 'controllers.md', 'description' => 'Generate controllers, validate input, and return responses.'],
        'authentication' => ['title' => 'Authentication & ownership', 'file' => 'authentication.md', 'description' => 'Register accounts, protect routes, and isolate user records.'],
        'models' => ['title' => 'Models & Nova Records', 'file' => 'models.md', 'description' => 'Query and persist models with NovaPHP’s own ORM.'],
        'migrations' => ['title' => 'Migrations', 'file' => 'migrations.md', 'description' => 'Define tables with field maps or raw SQL.'],
        'database' => ['title' => 'Database', 'file' => 'database.md', 'description' => 'Configure PDO, bind values, and use transactions.'],
        'routing' => ['title' => 'Routing & middleware', 'file' => 'routing.md', 'description' => 'Connect URLs, controllers, and middleware.'],
        'forms' => ['title' => 'Forms & views', 'file' => 'forms.md', 'description' => 'Sessions, CSRF, validation, and escaped templates.'],
        'container' => ['title' => 'Container & providers', 'file' => 'container.md', 'description' => 'Wire services and bootstrap your application.'],
        'configuration' => ['title' => 'Configuration', 'file' => 'configuration.md', 'description' => 'Use environment files and inspect configuration.'],
        'reference' => ['title' => 'CLI & project reference', 'file' => 'reference.md', 'description' => 'Find commands, file locations, tests, and limits.'],
    ];
}

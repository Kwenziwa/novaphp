<?php

declare(strict_types=1);

use Nova\Console\ProjectCreator;

$projectRoot = sys_get_temp_dir() . '/nova-projects-' . bin2hex(random_bytes(6));
mkdir($projectRoot, 0700);
$creator = new ProjectCreator(dirname(__DIR__));
$removeProjectFixture = static function (string $path) use (&$removeProjectFixture): void {
    foreach (scandir($path) as $name) {
        if ($name === '.' || $name === '..') { continue; }
        $file = $path . '/' . $name;
        if (is_dir($file) && !is_link($file)) { $removeProjectFixture($file); } else { unlink($file); }
    }
    rmdir($path);
};
try {
    foreach (ProjectCreator::STACKS as $stack) {
        $target = $creator->create($projectRoot . '/' . $stack, $stack);
        check(is_file($target . '/nova') && is_file($target . '/src/Core/Application.php'), 'generated ' . $stack . ' project includes the framework');
        check(!file_exists($target . '/.git') && !file_exists($target . '/.env') && !file_exists($target . '/storage/database.sqlite') && !file_exists($target . '/vendor') && !file_exists($target . '/tests/Fixtures'), 'generated ' . $stack . ' excludes repository state data dependencies and fixtures');
        check(!is_file($target . '/app/Models/Note.php') && glob($target . '/database/migrations/*.php') === [], 'generated ' . $stack . ' has no application models or migrations');
        $output = [];
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($target . '/tests/run.php'), $output, $status);
        check($status === 0 && str_contains(implode('', $output), 'passed'), 'generated ' . $stack . ' boots independently');
        if ($stack === 'empty') { check(!file_exists($target . '/package.json'), 'empty projects need no Node tooling'); }
        else {
            $package = json_decode(file_get_contents($target . '/package.json'), true, 512, JSON_THROW_ON_ERROR);
            check(isset($package['devDependencies']['vite']) && is_file($target . '/package-lock.json') && is_file($target . '/vite.config.js'), 'generated ' . $stack . ' has a locked frontend blueprint');
        }
    }
    // Creating from an application must not clone that application's private code/config/data.
    file_put_contents($projectRoot . '/empty/app/Controllers/PrivateController.php', '<?php // private application code');
    file_put_contents($projectRoot . '/empty/.env', 'PRIVATE_SECRET=must-not-copy');
    file_put_contents($projectRoot . '/empty/storage/private.sqlite', 'private data');
    $nested = (new ProjectCreator($projectRoot . '/empty'))->create($projectRoot . '/independent', 'empty');
    check(!file_exists($nested . '/app/Controllers/PrivateController.php') && !file_exists($nested . '/.env') && !file_exists($nested . '/storage/private.sqlite'), 'creation from an existing application uses the clean blueprint rather than private application files');
    file_put_contents($projectRoot . '/empty/config/app.php', '<?php throw new RuntimeException("source application must not boot");');
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($projectRoot . '/empty/nova') . ' new ' . escapeshellarg($projectRoot . '/from-broken-app') . ' --stack=empty', $ignored, $status);
    check($status === 0, 'project creation does not bootstrap the source application');
    $broken = $projectRoot . '/broken-source';
    mkdir($broken . '/stubs/base', 0755, true);
    file_put_contents($broken . '/stubs/base/marker', 'staged');
    fails(fn () => (new ProjectCreator($broken))->create($projectRoot . '/failed-project', 'empty'), 'Cannot copy project template');
    check(!file_exists($projectRoot . '/failed-project') && glob($projectRoot . '/.failed-project.nova-*') === [], 'failed generation removes its own staging files');
    $marker = $projectRoot . '/empty/user-file.txt';
    file_put_contents($marker, 'keep');
    fails(fn () => $creator->create($projectRoot . '/empty', 'empty'), 'Destination already exists');
    check(file_get_contents($marker) === 'keep', 'project creation preserves an existing destination');
    fails(fn () => $creator->create($projectRoot . '/bad', 'unknown'), 'Unknown stack');
    $input = fopen('php://memory', 'r+');
    $output = fopen('php://memory', 'r+');
    fwrite($input, "5\n"); rewind($input);
    check(ProjectCreator::choose($input, $output) === 'solid', 'interactive project selection accepts menu numbers');
    fclose($input); fclose($output);
    $input = fopen('php://memory', 'r+'); $output = fopen('php://memory', 'r+');
    fwrite($input, "\n"); rewind($input);
    check(ProjectCreator::choose($input, $output) === 'empty', 'interactive project selection defaults to empty');
    fclose($input); fclose($output);
    $cli = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/nova');
    exec($cli . ' new ' . escapeshellarg($projectRoot . '/cli') . ' --stack=empty', $ignored, $status);
    check($status === 0 && is_file($projectRoot . '/cli/nova'), 'CLI new generates an empty project');
    exec($cli . ' new ' . escapeshellarg($projectRoot . '/no-choice') . ' < /dev/null 2>&1', $ignored, $status);
    check($status === 1 && !file_exists($projectRoot . '/no-choice'), 'non-interactive creation requires an explicit stack');
} finally { $removeProjectFixture($projectRoot); }

<?php

declare(strict_types=1);

namespace Nova\Console;

use RuntimeException;
use Throwable;

final class ProjectCreator
{
    public const STYLES = ['plain', 'tailwind', 'bootstrap'];

    public const STACKS = ['empty', 'vue', 'react', 'svelte', 'solid', 'preact', 'alpine'];

    public function __construct(private readonly string $source) {}

    /** Generate from an allowlist: never copy Git state, dependencies, local data, or environment secrets. */
    public function create(string $destination, string $stack, string $style = 'plain'): string
    {
        if (!in_array($stack, self::STACKS, true)) { throw new RuntimeException('Unknown stack. Choose: ' . implode(', ', self::STACKS)); }
        if (!in_array($style, self::STYLES, true)) { throw new RuntimeException('Unknown style. Choose: ' . implode(', ', self::STYLES)); }
        if ($stack === 'empty' && $style !== 'plain') { throw new RuntimeException('Empty projects are PHP-only. Choose a frontend stack to use Tailwind or Bootstrap.'); }
        $destination = rtrim($destination, '/');
        $name = basename($destination);
        if (!preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/D', $name)) { throw new RuntimeException('Use a project directory name such as my-app.'); }
        $parent = realpath(dirname($destination));
        if ($parent === false || !is_dir($parent)) { throw new RuntimeException('Project parent directory must already exist.'); }
        $target = $parent . '/' . $name;
        if (file_exists($target) || is_link($target)) { throw new RuntimeException('Destination already exists; choose a new directory.'); }
        $stage = $parent . '/.' . $name . '.nova-' . bin2hex(random_bytes(6));
        if (!mkdir($stage, 0755)) { throw new RuntimeException('Cannot create project staging directory.'); }
        try {
            $this->copy($this->source . '/stubs/base', $stage);
            foreach (['src', 'stubs', 'nova'] as $entry) {
                $this->copy($this->source . '/' . $entry, $stage . '/' . $entry);
            }
            foreach (['storage', 'database/migrations', 'app/Models', 'tests'] as $directory) {
                if (!is_dir($stage . '/' . $directory) && !mkdir($stage . '/' . $directory, 0755, true)) { throw new RuntimeException('Cannot create project directory.'); }
            }
            $this->write($stage . '/storage/.gitkeep', '');
            $this->write($stage . '/database/migrations/.gitkeep', '');
            $this->write($stage . '/tests/run.php', <<<'PHPTEST'
<?php
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$response = $app->handle(new Nova\Http\Request('GET', '/'));
if ($response->status !== 200) { throw new RuntimeException('Application smoke test failed.'); }
echo "Application smoke test passed.\n";
PHPTEST
            );
            $composer = json_decode(file_get_contents($stage . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
            $composer['name'] = 'app/' . strtolower($name);
            $composer['description'] = 'A NovaPHP application';
            $this->write($stage . '/composer.json', json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
            if ($stack !== 'empty') {
                $this->copy($this->source . '/stubs/common', $stage);
                $this->copy($this->source . '/stubs/' . $stack, $stage);
                if ($style !== 'plain') { $this->copy($this->source . '/stubs/styles/' . $style . '/' . $stack, $stage); }
                $this->write($stage . '/nova.json', json_encode(['name' => $name, 'stack' => $stack, 'style' => $style], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n");
            }
            $frontend = $stack === 'empty' ? '' : "\nFrontend ($stack, $style):\n\n```bash\nnpm install\nnpm run dev\n# or npm run build, then serve the build through PHP\n```\n\nVite serves the frontend at http://127.0.0.1:5173/build/ and proxies /api requests to http://127.0.0.1:8000. Use NOVA_BACKEND_URL when your PHP server runs elsewhere. After npm run build, the PHP welcome route serves public/build/index.html. Node 22.12+ or 24+ is recommended.\n";
            $this->write($stage . '/README.md', "# $name\n\nNovaPHP application · stack: $stack · style: $style\n\n```bash\nphp nova serve\nphp tests/run.php\n```\n\nOpen http://127.0.0.1:8000 and /docs. No application database tables are installed. Create your models, migrations, and routes as needed. Composer is optional.\n" . $frontend);
            // Refuse a late collision too; this command never merges into an existing project.
            if (!@mkdir($target, 0755)) { throw new RuntimeException('Cannot publish project; destination may already exist.'); }
            if (!rename($stage, $target)) {
                @rmdir($target); // Only succeeds while the directory just claimed above is still empty.
                throw new RuntimeException('Cannot publish project.');
            }
            return $target;
        } catch (Throwable $error) {
            $this->removeStage($stage);
            throw $error;
        }
    }

    private function write(string $path, string $content): void
    {
        if (file_put_contents($path, $content) !== strlen($content)) { throw new RuntimeException('Cannot write generated project file.'); }
    }

    private function copy(string $source, string $destination): void
    {
        if (is_link($source)) { throw new RuntimeException('Project templates cannot contain symlinks.'); }
        if (is_dir($source)) {
            if (!is_dir($destination) && !mkdir($destination, 0755, true)) { throw new RuntimeException('Cannot create project directory.'); }
            foreach (scandir($source) as $name) {
                if ($name === '.' || $name === '..' || $name === '.nova-migrate.lock') { continue; }
                $this->copy($source . '/' . $name, $destination . '/' . $name);
            }
        } elseif (!is_file($source) || !copy($source, $destination)) { throw new RuntimeException('Cannot copy project template: ' . basename($source)); }
    }

    /** Only called for the freshly allocated staging directory owned by this invocation. */
    private function removeStage(string $path): void
    {
        if (!is_dir($path)) { return; }
        foreach (scandir($path) as $name) {
            if ($name === '.' || $name === '..') { continue; }
            $file = $path . '/' . $name;
            if (is_dir($file) && !is_link($file)) { $this->removeStage($file); } else { unlink($file); }
        }
        rmdir($path);
    }

    public static function chooseStyle($input, $output): string
    {
        fwrite($output, "Choose styling:\n  1) Plain CSS\n  2) Tailwind\n  3) Bootstrap\nStyle [plain]: ");
        $line = fgets($input);
        if ($line === false) { throw new RuntimeException('No styling selection received. Use --style=plain for non-interactive creation.'); }
        $choice = strtolower(trim($line));
        if ($choice === '') { return 'plain'; }
        if (ctype_digit($choice) && isset(self::STYLES[(int) $choice - 1])) { return self::STYLES[(int) $choice - 1]; }
        if (in_array($choice, self::STYLES, true)) { return $choice; }
        throw new RuntimeException('Invalid styling selection.');
    }

    public static function choose($input, $output): string
    {
        fwrite($output, "Choose your project starter:\n");
        foreach (self::STACKS as $index => $stack) { fwrite($output, '  ' . ($index + 1) . ') ' . ucfirst($stack) . "\n"); }
        fwrite($output, 'Stack [empty]: ');
        $line = fgets($input);
        if ($line === false) { throw new RuntimeException('No selection received. Use --stack=empty for non-interactive creation.'); }
        $choice = strtolower(trim($line));
        if ($choice === '') { return 'empty'; }
        if (ctype_digit($choice) && isset(self::STACKS[(int) $choice - 1])) { return self::STACKS[(int) $choice - 1]; }
        if (in_array($choice, self::STACKS, true)) { return $choice; }
        throw new RuntimeException('Invalid project starter selection.');
    }
}

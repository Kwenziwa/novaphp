<?php

declare(strict_types=1);

namespace Nova\Database;

use InvalidArgumentException;
use ParseError;
use RuntimeException;

final class ModelGenerator
{
    public function __construct(private readonly string $directory) {}

    public function make(string $name, ?string $table = null): string
    {
        if (!preg_match('/^[A-Z][A-Za-z0-9_]*$/D', $name)) { throw new InvalidArgumentException('Use a simple model class name such as Post.'); }
        try { token_get_all('<?php class ' . $name . ' {}', TOKEN_PARSE); }
        catch (ParseError) { throw new InvalidArgumentException('Model name is a reserved PHP name.'); }
        if ($table === null) {
            $base = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name));
            $table = preg_match('/[^aeiou]y$/', $base) ? substr($base, 0, -1) . 'ies'
                : $base . (preg_match('/(?:s|x|z|ch|sh)$/', $base) ? 'es' : 's');
        }
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]{0,62}$/D', $table)) { throw new InvalidArgumentException('Model table must be a simple name of at most 63 characters.'); }
        if (!is_dir($this->directory) && !mkdir($this->directory, 0755, true)) { throw new RuntimeException('Cannot create models directory.'); }
        $path = $this->directory . '/' . $name . '.php';
        $file = @fopen($path, 'x');
        if ($file === false) { throw new RuntimeException('Model exists or cannot be created.'); }
        $source = "<?php\n\ndeclare(strict_types=1);\n\nnamespace App\\Models;\n\nfinal class $name extends \\Nova\\Database\\Record\n{\n    protected const TABLE = '$table';\n    protected const WRITABLE = [\n        // 'title', 'body',\n    ];\n}\n";
        try {
            if (fwrite($file, $source) !== strlen($source)) { throw new RuntimeException('Cannot write model.'); }
        } finally { fclose($file); }
        return $path;
    }
}

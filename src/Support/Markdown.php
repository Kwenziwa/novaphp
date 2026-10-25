<?php

declare(strict_types=1);

namespace Nova\Support;

/** Small documentation-only Markdown subset. Raw HTML is always escaped. */
final class Markdown
{
    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function inline(string $text): string
    {
        $tokens = preg_split('/(`[^`\n]+`|\[[^\]\n]+\]\([^\s)]+\)|\*\*[^*\n]+\*\*)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        $html = '';
        foreach ($tokens as $token) {
            if (str_starts_with($token, '`') && str_ends_with($token, '`')) {
                $html .= '<code>' . $this->escape(substr($token, 1, -1)) . '</code>';
            } elseif (str_starts_with($token, '**') && str_ends_with($token, '**')) {
                $html .= '<strong>' . $this->escape(substr($token, 2, -2)) . '</strong>';
            } elseif (preg_match('/^\[([^\]]+)\]\(([^\s)]+)\)$/', $token, $match)
                && preg_match('~^(?:https?://|/docs(?:/|$|#)|\#[A-Za-z0-9_-]+$)~i', $match[2])) {
                $html .= '<a href="' . $this->escape($match[2]) . '">' . $this->escape($match[1]) . '</a>';
            } else { $html .= $this->escape($token); }
        }
        return $html;
    }

    private function cells(string $row): array
    {
        return array_map('trim', explode('|', trim(trim($row), '|')));
    }

    private function tableDivider(string $row): bool
    {
        $cells = $this->cells($row);
        foreach ($cells as $cell) { if (!preg_match('/^:?-{3,}:?$/D', $cell)) { return false; } }
        return $cells !== [];
    }

    public function render(string $markdown): string
    {
        $lines = preg_split('/\r\n|\n|\r/', $markdown);
        $html = '';
        $anchors = [];
        $count = count($lines);
        for ($index = 0; $index < $count; $index++) {
            $line = $lines[$index];
            if (trim($line) === '') { continue; }
            if (preg_match('/^```([A-Za-z0-9_-]*)\s*$/D', $line, $match)) {
                $code = [];
                while (++$index < $count && trim($lines[$index]) !== '```') { $code[] = $lines[$index]; }
                $html .= '<pre><code class="language-' . $this->escape($match[1]) . '">' . $this->escape(implode("\n", $code)) . '</code></pre>';
                continue;
            }
            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $match)) {
                $level = strlen($match[1]);
                $slug = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($match[2])), '-') ?: 'section';
                $occurrence = ($anchors[$slug] ?? 0) + 1;
                $anchors[$slug] = $occurrence;
                $id = $slug . ($occurrence > 1 ? '-' . $occurrence : '');
                $html .= "<h$level id=\"$id\">" . $this->inline($match[2]) . "</h$level>";
                continue;
            }
            if (str_contains($line, '|') && isset($lines[$index + 1]) && $this->tableDivider($lines[$index + 1])) {
                $html .= '<div class="table-scroll"><table><thead><tr>';
                foreach ($this->cells($line) as $cell) { $html .= '<th scope="col">' . $this->inline($cell) . '</th>'; }
                $html .= '</tr></thead><tbody>';
                $index += 2;
                while ($index < $count && str_contains($lines[$index], '|') && trim($lines[$index]) !== '') {
                    $html .= '<tr>';
                    foreach ($this->cells($lines[$index]) as $cell) { $html .= '<td>' . $this->inline($cell) . '</td>'; }
                    $html .= '</tr>';
                    $index++;
                }
                $index--;
                $html .= '</tbody></table></div>';
                continue;
            }
            if (preg_match('/^(?:- |\d+\. )(.+)$/', $line, $match)) {
                $tag = str_starts_with($line, '- ') ? 'ul' : 'ol';
                $pattern = $tag === 'ul' ? '/^- (.+)$/' : '/^\d+\. (.+)$/';
                $html .= '<' . $tag . '>';
                do {
                    preg_match($pattern, $lines[$index], $match);
                    $html .= '<li>' . $this->inline($match[1]) . '</li>';
                    $index++;
                } while ($index < $count && preg_match($pattern, $lines[$index]));
                $index--;
                $html .= '</' . $tag . '>';
                continue;
            }
            $paragraph = [$line];
            while (isset($lines[$index + 1]) && trim($lines[$index + 1]) !== ''
                && !preg_match('/^(?:#|```|- |\d+\. )/', $lines[$index + 1])) {
                $paragraph[] = $lines[++$index];
            }
            $html .= '<p>' . $this->inline(implode(' ', $paragraph)) . '</p>';
        }
        return $html;
    }
}

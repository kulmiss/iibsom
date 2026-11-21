<?php

namespace App\Support\Translations;

use RuntimeException;

/**
 * Changes texts inside a lang/xx/*.php file without touching anything else (PR 112):
 * comments, order and spacing stay exactly as they are. Only plain quoted texts can be changed
 * (a text built with "." or a heredoc is refused, so nothing is broken by accident).
 */
class LangFileWriter
{
    /**
     * @param  array<string, string>  $changes  "dotted.key" (inside this file) => new text
     * @return array{code: string, done: list<string>, refused: list<string>}
     */
    public static function apply(string $code, array $changes): array
    {
        $tokens = token_get_all($code);
        $stack = [];      // keys of the arrays we are inside (null for an array without a key)
        $pendingKey = null;
        $afterArrow = false;
        $done = [];
        $refused = [];
        $out = '';
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];
            $text = is_array($token) ? $token[1] : $token;
            $id = is_array($token) ? $token[0] : null;

            if ($id === T_CONSTANT_ENCAPSED_STRING) {
                $next = self::nextSignificant($tokens, $i);
                if (! $afterArrow && $next === T_DOUBLE_ARROW) {
                    $pendingKey = self::unquote($text);
                } elseif ($afterArrow && $pendingKey !== null) {
                    $path = implode('.', array_merge(array_filter($stack, fn ($k) => $k !== null), [$pendingKey]));
                    if (array_key_exists($path, $changes)) {
                        if (in_array($next, [',', ']', ')'], true)) {
                            $text = var_export($changes[$path], true); // single quotes, safe escaping
                            $done[] = $path;
                        } else {
                            $refused[] = $path; // built from parts: change it by hand
                        }
                    }
                    $pendingKey = null;
                    $afterArrow = false;
                }
            } elseif ($id === T_DOUBLE_ARROW) {
                $afterArrow = true;
            } elseif ($text === '[') { // the lang files use short arrays only
                $stack[] = $afterArrow ? $pendingKey : null;
                $pendingKey = null;
                $afterArrow = false;
            } elseif ($text === ']') {
                array_pop($stack);
            } elseif ($text === ',') {
                $pendingKey = null;
                $afterArrow = false;
            }

            $out .= $text;
        }

        if (! self::parses($out)) {
            throw new RuntimeException('The changed file would not be valid PHP.');
        }

        foreach (array_keys($changes) as $path) {
            if (! in_array($path, $done, true) && ! in_array($path, $refused, true)) {
                $refused[] = $path; // not found in this file
            }
        }

        return ['code' => $out, 'done' => $done, 'refused' => $refused];
    }

    /** @param  array<int, mixed>  $tokens */
    private static function nextSignificant(array $tokens, int $i): string|int|null
    {
        for ($j = $i + 1, $n = count($tokens); $j < $n; $j++) {
            $t = $tokens[$j];
            if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return is_array($t) ? $t[0] : $t;
        }

        return null;
    }

    private static function unquote(string $literal): string
    {
        $quote = $literal[0];
        $inner = substr($literal, 1, -1);

        return $quote === "'"
            ? str_replace(['\\\\', "\\'"], ['\\', "'"], $inner)
            : stripcslashes($inner);
    }

    private static function parses(string $code): bool
    {
        try {
            // TOKEN_PARSE makes PHP check the whole file; a broken file throws.
            return token_get_all($code, TOKEN_PARSE) !== [];
        } catch (\ParseError) {
            return false;
        }
    }
}

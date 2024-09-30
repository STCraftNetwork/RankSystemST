<?php

declare(strict_types=1);

namespace KanadeBlue\RankSystemST;

class PlaceholderManager
{
    private array $placeholders = [];
    private array $cache = [];

    /**
     * Register a new placeholder.
     *
     * @param string $name
     * @param callable $callback
     */
    public function registerPlaceholder(string $name, callable $callback): void
    {
        $this->placeholders[$name] = $callback;
    }

    /**
     * Replace placeholders in a string.
     *
     * @param string $text
     * @param array $data
     * @return string
     */
    public function replacePlaceholders(string $text, array $data = []): string
    {
        $cacheKey = md5($text . serialize($data));
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        foreach ($this->placeholders as $name => $callback) {
            if (isset($data[$name])) {
                $text = str_replace("{" . $name . "}", $data[$name], $text);
            } else {
                $text = preg_replace_callback("/\{" . $name . "(:([^}]+))?}/", function ($matches) use ($callback) {
                    $format = $matches[2] ?? null;
                    $result = call_user_func($callback, $matches[0]);

                    return $this->applyFormat($result, $format);
                }, $text);
            }
        }

        $this->cache[$cacheKey] = $text;
        return $text;
    }

    /**
     * Apply formatting to a placeholder result.
     *
     * @param string $result
     * @param string|null $format
     * @return string
     */
    private function applyFormat(string $result, ?string $format): string
    {
        return match ($format) {
            'uppercase' => strtoupper($result),
            'lowercase' => strtolower($result),
            'capitalize' => ucwords($result),
            default => $result,
        };
    }

    /**
     * Replace conditional placeholders.
     * Supports {if:placeholder}content{endif}.
     *
     * @param string $text
     * @param array $data
     * @return string
     */
    public function replaceConditionalPlaceholders(string $text, array $data = []): string
    {
        $pattern = "/\{if:([a-zA-Z0-9_]+)}(.*?)\{endif}/s";
        return preg_replace_callback($pattern, function ($matches) use ($data) {
            $placeholder = $matches[1];
            $content = $matches[2];

            return !empty($data[$placeholder]) ? $content : '';
        }, $text);
    }

    /**
     * Clear the cache.
     */
    public function clearCache(): void
    {
        $this->cache = [];
    }
}

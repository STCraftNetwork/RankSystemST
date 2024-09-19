<?php

declare(strict_types=1);

namespace KanadeBlue\RankSystemST;

class PlaceholderManager
{
    private array $placeholders = [];

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
        foreach ($this->placeholders as $name => $callback) {
            if (isset($data[$name])) {
                $text = str_replace("{" . $name . "}", $data[$name], $text);
            } else {
                $text = preg_replace_callback("/\{" . $name . "}/", $callback, $text);
            }
        }

        return $text;
    }
}

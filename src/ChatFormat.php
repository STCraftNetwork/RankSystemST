<?php

declare(strict_types=1);

namespace KanadeBlue\RankSystemST;

use pocketmine\player\chat\ChatFormatter;

final class ChatFormat implements ChatFormatter
{
    private Session $session;

    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * Format the chat message according to the session's chat format.
     *
     * @param string $username
     * @param string $message
     * @return string
     */
    public function format(string $username, string $message): string
    {
        return str_replace("{message}", $message, $this->session->getChatFormat());
    }
}

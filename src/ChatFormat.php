<?php

namespace KanadeBlue\RankSystemST;

use pocketmine\player\chat\ChatFormatter;

final class ChatFormat implements ChatFormatter {

    public function __construct(
        private Session $session
    ) {
    }

    public function format(string $username, string $message) : string {
        return str_replace("{message}", $message, $this->session->getChatFormat());
    }
}
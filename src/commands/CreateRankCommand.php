<?php

namespace KanadeBlue\RankSystemST\commands;

use KanadeBlue\RankSystemST\RankSystem;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use jojoe77777\FormAPI\CustomForm;

class CreateRankCommand extends Command {

    private RankSystem $plugin;

    public function __construct(RankSystem $plugin) {
        $this->setPermission("command.rank.create");
        parent::__construct("createrank", "Create a new rank");
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void {
        if (!$sender instanceof Player) {
            $sender->sendMessage("This command must be run in-game.");
            return;
        }

        $form = new CustomForm(function (Player $player, ?array $data) {
            if ($data === null) {
                return;
            }

            $rankName = trim($data[0]);
            $parentRank = trim($data[1]);
            $color = trim($data[2]);

            if (empty($rankName)) {
                $player->sendMessage("Rank name cannot be empty.");
                return;
            }

            $rankManager = $this->plugin->getRankManager();
            if ($rankManager->rankExists($rankName)) {
                $player->sendMessage("Rank '{$rankName}' already exists.");
                return;
            }

            if (!empty($parentRank) && !$rankManager->rankExists($parentRank)) {
                $player->sendMessage("Parent rank '{$parentRank}' does not exist.");
                return;
            }

            $rankManager->createRank($rankName, $parentRank ?: null, $color ?: null);
            $player->sendMessage("Rank '{$rankName}' created successfully with color '{$color}'.");
        });

        $form->setTitle("Create Rank");
        $form->addInput("Rank name");
        $form->addInput("Parent rank (optional)");
        $form->addInput("Color (optional, format: &c for red, &b for blue, etc.)");
        $sender->sendForm($form);
    }
}

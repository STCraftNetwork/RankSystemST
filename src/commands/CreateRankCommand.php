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

        $rankManager = $this->plugin->getRankManager();
        $ranks = $rankManager->getRanks();
        $rankNames = array_keys($ranks);
        array_unshift($rankNames, "None");

        $colors = [
            "&0 - Black", "&1 - Dark Blue", "&2 - Dark Green", "&3 - Dark Aqua",
            "&4 - Dark Red", "&5 - Dark Purple", "&6 - Gold", "&7 - Gray",
            "&8 - Dark Gray", "&9 - Blue", "&a - Green", "&b - Aqua",
            "&c - Red", "&d - Light Purple", "&e - Yellow", "&f - White",
            "None"
        ];

        $form = new CustomForm(function (Player $player, ?array $data) use ($rankNames, $colors) {
            if ($data === null) {
                return;
            }

            $rankName = trim($data[0]);
            $parentRankIndex = $data[1];
            $colorIndex = $data[2];

            if (empty($rankName)) {
                $player->sendMessage("Rank name cannot be empty.");
                return;
            }

            $parentRank = $parentRankIndex === 0 ? null : $rankNames[$parentRankIndex];
            $color = $colorIndex === count($colors) - 1 ? null : substr($colors[$colorIndex], 0, 2);
            $rankManager = $this->plugin->getRankManager();

            if ($rankManager->rankExists($rankName)) {
                $player->sendMessage("Rank '{$rankName}' already exists.");
                return;
            }

            if ($parentRank && !$rankManager->rankExists($parentRank)) {
                $player->sendMessage("Parent rank '{$parentRank}' does not exist.");
                return;
            }

            $rankManager->createRank($rankName, $parentRank ?: null, $color ?: null);
            $player->sendMessage("Rank '{$rankName}' created successfully with color '{$color}'.");
        });

        $form->setTitle("Create Rank");
        $form->addInput("Rank name");
        $form->addDropdown("Parent rank (optional)", $rankNames);
        $form->addDropdown("Color (optional)", $colors);
        $form->addLabel("Preview:");
        $form->addLabel("");

        $form->setHandler(function (Player $player, ?array $data) use ($form, $rankNames, $colors): void {
            if ($data === null) {
                return;
            }

            $previewColor = $data[2] === count($colors) - 1 ? "" : substr($colors[$data[2]], 0, 2);
            $parentRank = $data[1] === 0 ? "None" : $rankNames[$data[1]];

            $previewText = "Rank: " . $data[0] . "\nParent Rank: " . $parentRank . "\nColor: " . $previewColor;
            $form->setLabel(3, $previewText);
        });

        $sender->sendForm($form);
    }
}

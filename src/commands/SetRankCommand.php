<?php

namespace KanadeBlue\RankSystemST\commands;

use KanadeBlue\RankSystemST\RankSystem;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use jojoe77777\FormAPI\CustomForm;

class SetRankCommand extends Command{

    private RankSystem $plugin;

    public function __construct(RankSystem $plugin){
        $this->setPermission("command.rank.set");
        parent::__construct("setrank", "Set a player's rank");
        $this->plugin = $plugin;
    }

    public function execute(CommandSender $sender, string $commandLabel, array $args): void
    {
        if(!$sender instanceof Player){
            $sender->sendMessage("This command must be run in-game.");
            return;
        }

        $form = new CustomForm(function (Player $player, ?array $data) {
            if($data === null){
                return;
            }

            $target = $this->plugin->getServer()->getPlayerByPrefix($data[0]);
            if($target === null){
                $player->sendMessage("Player not found.");
                return;
            }

            $session = $this->plugin->getSession($target);
            if($session === null){
                $player->sendMessage("Player session not found.");
                return;
            }

            $session->addRank($data[1]);
            $player->sendMessage("Set {$target->getName()}'s rank to {$data[1]}.");
        });

        $form->setTitle("Set Rank");
        $form->addInput("Player name");
        $form->addInput("Rank");
        $sender->sendForm($form);
    }
}
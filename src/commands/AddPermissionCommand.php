<?php

namespace KanadeBlue\RankSystemST\commands;

use KanadeBlue\RankSystemST\RankSystem;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use jojoe77777\FormAPI\CustomForm;

class AddPermissionCommand extends Command {

    private RankSystem $plugin;

    public function __construct(RankSystem $plugin) {
        $this->setPermission("command.permission.add");
        parent::__construct("addpermission", "Add a permission to a rank or a user");
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

        $players = $this->fetchAllPlayerNames();
        array_unshift($players, "None");

        $form = new CustomForm(function (Player $player, ?array $data) use ($rankNames, $players) {
            if ($data === null) {
                return;
            }

            $rankIndex = $data[0];
            $permission = trim($data[1]);
            $playerIndex = $data[2];

            $selectedRank = $rankIndex === 0 ? null : $rankNames[$rankIndex];
            $targetPlayerName = $playerIndex === 0 ? null : $players[$playerIndex];
            $rankManager = $this->plugin->getRankManager();

            if ($selectedRank && !$rankManager->rankExists($selectedRank)) {
                $player->sendMessage("Rank '{$selectedRank}' does not exist.");
                return;
            }

            if (empty($permission)) {
                $player->sendMessage("Permission cannot be empty.");
                return;
            }

            if ($selectedRank) {
                $rankManager->addPermission($selectedRank, $permission);
                $player->sendMessage("Permission '{$permission}' added to rank '{$selectedRank}'.");
            }

            if ($targetPlayerName) {
                $session = $this->plugin->getSession($targetPlayerName);
                if ($session === null) {
                    $player->sendMessage("Player '{$targetPlayerName}' not found.");
                    return;
                }
                $session->addPermission($permission);
                $player->sendMessage("Permission '{$permission}' added to player '{$targetPlayerName}'.");
            }
        });

        $form->setTitle("Add Permission");
        $form->addDropdown("Select rank (optional)", $rankNames);
        $form->addInput("Permission to add");
        $form->addDropdown("Select player (optional)", $players);

        $sender->sendForm($form);
    }

    /**
     * Fetch all player names from the database.
     *
     * @return array
     */
    private function fetchAllPlayerNames(): array {
        $db = $this->plugin->getDatabase();
        $sql = "SELECT name FROM players";
        $result = $db->query($sql);

        if ($result === false) {
            error_log("Failed to fetch player names: " . $db->error);
            return [];
        }

        $playerNames = [];
        while ($row = $result->fetch_assoc()) {
            $playerNames[] = $row['name'];
        }

        return $playerNames;
    }
}

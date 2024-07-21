<?php

namespace KanadeBlue\RankSystemST;

use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use mysqli;

class Session {

    private ?Player $player;
    private string $playerName;
    private array $ranks;
    private array $permissions;
    private string $chatColor;
    private array $tags;
    private mysqli $db;
    private RankManager $rankManager;

    /**
     * Constructor for Session.
     *
     * @param Player|null $player The player object if online, null if offline.
     * @param string|null $playerName The player's name if the player object is null.
     * @param mysqli $db The database connection.
     * @param RankManager $rankManager The rank manager.
     */
    public function __construct(?Player $player, ?string $playerName, mysqli $db, RankManager $rankManager) {
        $this->player = $player;
        $this->playerName = $playerName ?? ($player ? $player->getName() : '');
        $this->db = $db;
        $this->rankManager = $rankManager;
        $this->createTables();
        $this->loadData();
    }

    private function createTables() : void {
        $this->db->query("CREATE TABLE IF NOT EXISTS players (
            name VARCHAR(30) PRIMARY KEY,
            ranks TEXT,
            permissions TEXT,
            chatColor VARCHAR(30),
            tags TEXT
        )");
    }

    private function loadData() : void {
        $name = $this->db->escape_string($this->playerName);
        $result = $this->db->query("SELECT * FROM players WHERE name = '$name'");
        if ($result === false || $result->num_rows === 0) {
            $this->ranks = ["Default"];
            $this->permissions = [];
            $this->chatColor = TF::RED;
            $this->tags = [];
        } else {
            $data = $result->fetch_assoc();
            $this->ranks = explode(",", $data["ranks"]);
            $this->permissions = explode(",", $data["permissions"]);
            $this->chatColor = $this->getTextFormatColor("&" . $data["chatColor"]);
            $this->tags = explode(",", $data["tags"]);
        }
    }

    private function getTextFormatColor(string $colorCode): string {
        $colorMap = [
            "&0" => TF::BLACK,
            "&1" => TF::DARK_BLUE,
            "&2" => TF::DARK_GREEN,
            "&3" => TF::DARK_AQUA,
            "&4" => TF::DARK_RED,
            "&5" => TF::DARK_PURPLE,
            "&6" => TF::GOLD,
            "&7" => TF::GRAY,
            "&8" => TF::DARK_GRAY,
            "&9" => TF::BLUE,
            "&a" => TF::GREEN,
            "&b" => TF::AQUA,
            "&c" => TF::RED,
            "&d" => TF::LIGHT_PURPLE,
            "&e" => TF::YELLOW,
            "&f" => TF::WHITE,
        ];

        return $colorMap[$colorCode] ?? TF::RED;
    }

    public function getRanks() : array {
        return $this->ranks;
    }

    public function addRank(string $rank) : void {
        if (!in_array($rank, $this->ranks)) {
            if ($this->rankManager->rankExists($rank)) {
                $this->ranks[] = $rank;
                $this->saveData();
            } else {
                RankSystem::getInstance()->getLogger()->info("The rank {$rank} does not exist.");
            }
        } else {
            RankSystem::getInstance()->getLogger()->info("The player already has the rank {$rank}.\n");
        }
    }

    public function removeRank(string $rank) : void {
        $key = array_search($rank, $this->ranks);
        if ($key !== false) {
            unset($this->ranks[$key]);
            $this->ranks = array_values($this->ranks);
            $this->saveData();
        }
    }

    public function getPermissions() : array {
        return $this->permissions;
    }

    public function addPermission(string $permission) : void {
        $this->permissions[] = $permission;
        $this->saveData();
    }

    public function removePermission(string $permission) : void {
        $key = array_search($permission, $this->permissions);
        if ($key !== false) {
            unset($this->permissions[$key]);
            $this->saveData();
        }
    }

    public function getChatColor() : string {
        return $this->chatColor;
    }

    public function setChatColor(string $chatColor) : void {
        $this->chatColor = $this->getTextFormatColor($chatColor);
        $this->saveData();
    }

    public function getTags() : array {
        return $this->tags;
    }

    public function addTag(string $tag) : void {
        if (!in_array($tag, $this->tags)) {
            $this->tags[] = $tag;
            $this->saveData();
        } else {
            RankSystem::getInstance()->getLogger()->info("The player already has the tag {$tag}.\n");
        }
    }

    public function removeTag(string $tag) : void {
        $key = array_search($tag, $this->tags);
        if ($key !== false) {
            unset($this->tags[$key]);
            $this->tags = array_values($this->tags);
            $this->saveData();
        }
    }

    private function saveData() : void {
        $name = $this->db->escape_string($this->playerName);
        $ranks = $this->db->escape_string(implode(",", $this->ranks));
        $permissions = $this->db->escape_string(implode(",", $this->permissions));
        $chatColor = $this->db->escape_string(strtolower(array_search($this->chatColor, $this->getTextFormatColorMap())));
        $tags = $this->db->escape_string(implode(",", $this->tags));

        $query = "
        INSERT INTO players (name, ranks, permissions, chatColor, tags)
        VALUES ('$name', '$ranks', '$permissions', '$chatColor', '$tags')
        ON DUPLICATE KEY UPDATE 
            ranks = VALUES(ranks), 
            permissions = VALUES(permissions), 
            chatColor = VALUES(chatColor), 
            tags = VALUES(tags)
    ";

        $this->db->query($query);
    }

    private function getTextFormatColorMap(): array {
        return [
            TF::BLACK => "&0",
            TF::DARK_BLUE => "&1",
            TF::DARK_GREEN => "&2",
            TF::DARK_AQUA => "&3",
            TF::DARK_RED => "&4",
            TF::DARK_PURPLE => "&5",
            TF::GOLD => "&6",
            TF::GRAY => "&7",
            TF::DARK_GRAY => "&8",
            TF::BLUE => "&9",
            TF::GREEN => "&a",
            TF::AQUA => "&b",
            TF::RED => "&c",
            TF::LIGHT_PURPLE => "&d",
            TF::YELLOW => "&e",
            TF::WHITE => "&f",
        ];
    }

    public function getChatFormat() : string {
        $tags = implode(" ", $this->getTags());
        $highestRank = $this->getHighestRank();
        $chatColor = $this->getChatColor();
        $displayName = $this->player ? $this->player->getDisplayName() : $this->playerName;
        if ($highestRank == null) return $tags . " " . $displayName . " " . $chatColor . "{message}";
        return $highestRank . " " . $tags . " " . $displayName . " " . $chatColor . "{message}";
    }

    public function getHighestRank() : ?string {
        $rankHierarchy = $this->rankManager->getRankHierarchy();

        $highestRank = "";
        foreach ($this->ranks as $rank) {
            if (array_search($rank, $rankHierarchy) > array_search($highestRank, $rankHierarchy)) {
                $highestRank = $rank;
            }
        }

        if ($rankHierarchy == null || $this->rankManager->getFormattedRank($highestRank) == null) {
            return null;
        }

        return $this->rankManager->getFormattedRank($highestRank);
    }
}


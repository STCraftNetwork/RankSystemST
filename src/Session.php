<?php

namespace KanadeBlue\RankSystemST;

use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use mysqli;

class Session {

    private ?Player $player;
    private string $playerName;
    private array $ranks = [];
    private array $permissions = [];
    private string $chatColor;
    private array $tags = [];
    private array $displayTags = [];
    private mysqli $db;
    private RankManager $rankManager;

    public function __construct(?Player $player, ?string $playerName, mysqli $db, RankManager $rankManager) {
        $this->player = $player;
        $this->playerName = $playerName ?? ($player ? $player->getName() : '');
        $this->db = $db;
        $this->rankManager = $rankManager;
        $this->initialize();
    }

    private function initialize() : void {
        $this->createTables();
        $this->loadData();
    }

    private function createTables() : void {
        $sql = "CREATE TABLE IF NOT EXISTS players (
            name VARCHAR(30) PRIMARY KEY,
            ranks TEXT,
            permissions TEXT,
            chatColor VARCHAR(30),
            tags TEXT,
            displayTags TEXT
        )";
        $this->executeQuery($sql);
    }

    private function loadData() : void {
        $stmt = $this->prepareStatement("SELECT * FROM players WHERE name = ?");
        $stmt->bind_param("s", $this->playerName);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result === false || $result->num_rows === 0) {
            $this->setDefaultValues();
        } else {
            $this->populateData($result->fetch_assoc());
        }

        $stmt->close();
    }

    private function setDefaultValues() : void {
        $this->ranks = ["Default"];
        $this->permissions = [];
        $this->chatColor = TF::RED;
        $this->tags = [];
        $this->displayTags = [];
    }

    private function populateData(array $data) : void {
        $this->ranks = isset($data["ranks"]) ? explode(",", $data["ranks"]) : [];
        $this->permissions = isset($data["permissions"]) ? explode(",", $data["permissions"]) : [];
        $this->chatColor = isset($data["chatColor"]) ? $this->getTextFormatColor($data["chatColor"]) : null;
        $this->tags = isset($data["tags"]) ? explode(",", $data["tags"]) : [];
        $this->displayTags = isset($data["displayTags"]) ? explode(",", $data["displayTags"]) : [];
    }


    private function getTextFormatColor(string $colorCode): string {
        $colorMap = $this->getTextFormatColorMap();
        return $colorMap[$colorCode] ?? TF::RED;
    }

    private function getTextFormatColorMap(): array {
        return [
            "§0" => TF::BLACK,
            "§1" => TF::DARK_BLUE,
            "§2" => TF::DARK_GREEN,
            "§3" => TF::DARK_AQUA,
            "§4" => TF::DARK_RED,
            "§5" => TF::DARK_PURPLE,
            "§6" => TF::GOLD,
            "§7" => TF::GRAY,
            "§8" => TF::DARK_GRAY,
            "§9" => TF::BLUE,
            "§a" => TF::GREEN,
            "§b" => TF::AQUA,
            "§c" => TF::RED,
            "§d" => TF::LIGHT_PURPLE,
            "§e" => TF::YELLOW,
            "§f" => TF::WHITE,
        ];
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
                $this->logInfo("The rank {$rank} does not exist.");
            }
        } else {
            $this->logInfo("The player already has the rank {$rank}.");
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
        if (!in_array($permission, $this->permissions)) {
            $this->permissions[] = $permission;
            $this->saveData();
            $this->setPlayerPermission($permission, true);
        }
    }

    public function removePermission(string $permission) : void {
        $key = array_search($permission, $this->permissions);
        if ($key !== false) {
            unset($this->permissions[$key]);
            $this->permissions = array_values($this->permissions);
            $this->saveData();
            $this->setPlayerPermission($permission, false);
        }
    }

    private function setPlayerPermission(string $permission, bool $value) : void {
        if ($this->player) {
            $attachment = $this->player->addAttachment(Server::getInstance()->getPluginManager()->getPlugin("RankSystemST"));
            $attachment->setPermission($permission, $value);
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
            $this->logInfo("The player already has the tag {$tag}.");
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

    public function getDisplayTags() : array {
        return $this->displayTags;
    }

    public function addDisplayTag(string $displayTag) : void {
        if (!in_array($displayTag, $this->displayTags)) {
            $this->displayTags[] = $displayTag;
            $this->saveData();
        } else {
            $this->logInfo("The player already has the display tag {$displayTag}.");
        }
    }

    public function removeDisplayTag(string $displayTag) : void {
        $key = array_search($displayTag, $this->displayTags);
        if ($key !== false) {
            unset($this->displayTags[$key]);
            $this->displayTags = array_values($this->displayTags);
            $this->saveData();
        }
    }

    private function saveData() : void {
        $stmt = $this->prepareStatement("
            INSERT INTO players (name, ranks, permissions, chatColor, tags, displayTags)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                ranks = VALUES(ranks), 
                permissions = VALUES(permissions), 
                chatColor = VALUES(chatColor), 
                tags = VALUES(tags),
                displayTags = VALUES(displayTags)
        ");

        $ranks = implode(",", $this->ranks);
        $permissions = implode(",", $this->permissions);
        $chatColor = strtolower(array_search($this->chatColor, $this->getTextFormatColorMap()));
        $tags = implode(",", $this->tags);
        $displayTags = implode(",", $this->displayTags);

        $stmt->bind_param("ssssss", $this->playerName, $ranks, $permissions, $chatColor, $tags, $displayTags);
        if (!$stmt->execute()) {
            $this->handleError('Failed to execute statement');
        }

        $stmt->close();
    }

    private function prepareStatement(string $query) {
        $stmt = $this->db->prepare($query);
        if ($stmt === false) {
            $this->handleError('Failed to prepare statement');
        }
        return $stmt;
    }

    private function executeQuery(string $query) : void {
        if (!$this->db->query($query)) {
            $this->handleError('Failed to create players table');
        }
    }

    private function logInfo(string $message) : void {
        RankSystem::getInstance()->getLogger()->info($message);
    }

    public function getChatFormat() : string {
        $selectedTag = !empty($this->tags) ? $this->tags[array_rand($this->tags)] : "";
        $displayTags = implode(" ", $this->displayTags);

        $highestRank = $this->getHighestRank();
        $chatColor = $this->chatColor;
        $displayName = $this->player ? $this->player->getDisplayName() : $this->playerName;
        $ranks = $this->getRanks();

        if (!$highestRank && !empty($ranks)) {
            $highestRank = $ranks[array_rand($ranks)];
        }

        return ($highestRank ? $highestRank . " " : "") . $selectedTag . " " . $displayTags . " " . $displayName . " " . $chatColor . "{message}";
    }

    public function getHighestRank() : ?string {
        $rankHierarchy = $this->rankManager->getRankHierarchy();
        $highestRank = null;

        foreach ($this->ranks as $rank) {
            if (isset($rankHierarchy[$rank])) {
                if ($highestRank === null || $rankHierarchy[$rank] < $rankHierarchy[$highestRank]) {
                    $highestRank = $rank;
                }
            }
        }

        return $highestRank ? $this->rankManager->getFormattedRank($highestRank) : null;
    }

    private function handleError(string $message) : void {
        error_log($message . ': ' . $this->db->error);
    }
}

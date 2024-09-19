<?php

namespace KanadeBlue\RankSystemST;

use OnlyJaiden\Faction\Main;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use mysqli;
use mysqli_stmt;

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
    private PlaceholderManager $placeholderManager;

    public function __construct(?Player $player, ?string $playerName, mysqli $db, RankManager $rankManager) {
        $this->player = $player;
        $this->playerName = $playerName ?? ($player ? $player->getName() : '');
        $this->db = $db;
        $this->rankManager = $rankManager;
        $this->placeholderManager = new PlaceholderManager();
        $this->initialize();
    }

    private function initialize(): void {
        $this->createTables();
        $this->loadData();
    }

    private function createTables(): void {
        $sql = "
            CREATE TABLE IF NOT EXISTS players (
                name VARCHAR(30) PRIMARY KEY,
                ranks TEXT NOT NULL,
                permissions TEXT NOT NULL,
                chatColor VARCHAR(30) NOT NULL,
                tags TEXT NOT NULL,
                displayTags TEXT NOT NULL
            )";
        $this->executeQuery($sql);
    }

    private function loadData(): void {
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

    private function setDefaultValues(): void {
        $this->ranks = ["Default"];
        $this->permissions = [];
        $this->chatColor = TF::RED;
        $this->tags = [];
        $this->displayTags = [];
    }

    private function populateData(array $data): void {
        $this->ranks = $this->explodeData($data["ranks"]);
        $this->permissions = $this->explodeData($data["permissions"]);
        $this->chatColor = $this->getTextFormatColor($data["chatColor"] ?? "&c");
        $this->tags = $this->explodeData($data["tags"]);
        $this->displayTags = $this->explodeData($data["displayTags"]);
    }

    private function explodeData(?string $data): array {
        return $data ? explode(",", $data) : [];
    }

    private function getTextFormatColor(string $colorCode): string {
        return $this->getTextFormatColorMap()[$colorCode] ?? TF::RED;
    }

    private function getTextFormatColorMap(): array {
        return [
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
    }

    public function getRanks(): array {
        return $this->ranks;
    }

    public function addRank(string $rank): void {
        if (!in_array($rank, $this->ranks, true)) {
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

    public function removeRank(string $rank): void {
        if (($key = array_search($rank, $this->ranks, true)) !== false) {
            unset($this->ranks[$key]);
            $this->ranks = array_values($this->ranks);
            $this->saveData();
        }
    }

    public function getPermissions(): array {
        return $this->permissions;
    }

    public function addPermission(string $permission): void {
        if (!in_array($permission, $this->permissions, true)) {
            $this->permissions[] = $permission;
            $this->saveData();
            $this->setPlayerPermission($permission, true);
        }
    }

    public function removePermission(string $permission): void {
        if (($key = array_search($permission, $this->permissions, true)) !== false) {
            unset($this->permissions[$key]);
            $this->permissions = array_values($this->permissions);
            $this->saveData();
            $this->setPlayerPermission($permission, false);
        }
    }

    private function setPlayerPermission(string $permission, bool $value): void {
        if ($this->player) {
            $attachment = $this->player->addAttachment(Server::getInstance()->getPluginManager()->getPlugin("RankSystemST"));
            $attachment->setPermission($permission, $value);
        }
    }

    public function getChatColor(): string {
        return $this->chatColor;
    }

    public function setChatColor(string $chatColor): void {
        $this->chatColor = $this->getTextFormatColor($chatColor);
        $this->saveData();
    }

    public function getTags(): array {
        return $this->tags;
    }

    public function addTag(string $tag): void {
        if (!in_array($tag, $this->tags, true)) {
            $this->tags[] = $tag;
            $this->saveData();
        } else {
            $this->logInfo("The player already has the tag {$tag}.");
        }
    }

    public function removeTag(string $tag): void {
        if (($key = array_search($tag, $this->tags, true)) !== false) {
            unset($this->tags[$key]);
            $this->tags = array_values($this->tags);
            $this->saveData();
        }
    }

    public function getDisplayTags(): array {
        return $this->displayTags;
    }

    public function addDisplayTag(string $displayTag): void {
        if (!in_array($displayTag, $this->displayTags, true)) {
            $this->displayTags[] = $displayTag;
            $this->saveData();
        } else {
            $this->logInfo("The player already has the display tag {$displayTag}.");
        }
    }

    public function removeDisplayTag(string $displayTag): void {
        if (($key = array_search($displayTag, $this->displayTags, true)) !== false) {
            unset($this->displayTags[$key]);
            $this->displayTags = array_values($this->displayTags);
            $this->saveData();
        }
    }

    private function saveData(): void {
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

        $implodeTags = implode(",", $this->tags);
        $implodeRanks = implode(",", $this->ranks);
        $implodePermissions = implode(",", $this->permissions);
        $chatColor = array_search($this->chatColor, $this->getTextFormatColorMap(), true) ?? '&c';
        $implodeDisplayTags = implode(",", $this->displayTags);

        $stmt->bind_param(
            "ssssss",
            $this->playerName,
            $implodeRanks,
            $implodePermissions,
            $chatColor,
            $implodeTags,
            $implodeDisplayTags
        );

        $this->executeStatement($stmt);
    }

    private function prepareStatement(string $query): mysqli_stmt {
        $stmt = $this->db->prepare($query);
        if ($stmt === false) {
            throw new \RuntimeException('Failed to prepare statement: ' . $this->db->error);
        }
        return $stmt;
    }
    private function executeQuery(string $query): void {
        if (!$this->db->query($query)) {
            throw new \RuntimeException('Failed to execute query: ' . $this->db->error);
        }
    }

    private function executeStatement(mysqli_stmt $stmt): void {
        if (!$stmt->execute()) {
            throw new \RuntimeException('Failed to execute statement: ' . $stmt->error);
        }
        $stmt->close();
    }

    private function logInfo(string $message): void {
        RankSystem::getInstance()->getLogger()->info($message);
    }

    public function getChatFormat(): string {
        $selectedTag = !empty($this->tags) ? $this->tags[array_rand($this->tags)] : "";
        $displayTags = implode(" ", $this->displayTags);
        $highestRank = $this->getHighestRank() ?? "";
        $displayName = $this->player ? $this->player->getDisplayName() : $this->playerName;
        $plugin = Server::getInstance()->getPluginManager()->getPlugin("Faction");

        if (!$plugin instanceof Main) {
            $this->logInfo("Missing faction plugin. :(");
            return "";
        }

        $factionmanager = $plugin->getFactionManager();
        $faction = $factionmanager->getPlayerFaction($this->player->getUniqueId());
        $faction_placement = $factionmanager->getFactionPlacement($faction);

        if ($faction == null || $faction_placement == null) {
            $format = "{highestRank} {selectedTag} {displayTags} {displayName} {chatColor} {message}";


            $placeholders = [
                '{highestRank}' => $highestRank,
                '{selectedTag}' => $selectedTag,
                '{displayTags}' => $displayTags,
                '{displayName}' => $displayName,
                '{chatColor}' => $this->chatColor,
                '{message}' => '{message}'
            ];

            foreach ($placeholders as $key => $value) {
                $format = str_replace($key, $value, $format);
            }

            return $this->placeholderManager->replacePlaceholders($format);

        }

        $format = "{highestRank} {factionPlacement} {faction} {selectedTag} {displayTags} {displayName} {chatColor} {message}";


        $placeholders = [
            '{highestRank}' => $highestRank,
            '{selectedTag}' => $selectedTag,
            '{displayTags}' => $displayTags,
            '{displayName}' => $displayName,
            '{chatColor}' => $this->chatColor,
            '{message}' => '{message}'
        ];

        $placeholderManager = new PlaceholderManager();
        $faction = $placeholderManager->replacePlaceholders('{faction}', $faction);
        $factionPlacement = $placeholderManager->replacePlaceholders('{faction_placement}', $faction_placement);

        $placeholders['{faction}'] = $faction;
        $placeholders['{factionPlacement}'] = $factionPlacement;

        foreach ($placeholders as $key => $value) {
            $format = str_replace($key, $value, $format);
        }

        return $this->placeholderManager->replacePlaceholders($format);
    }


    public function getHighestRank(): ?string {
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
}

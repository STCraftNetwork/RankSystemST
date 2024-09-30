<?php

namespace KanadeBlue\RankSystemST;

use mysqli_stmt;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat as TF;
use mysqli;

class Session {

    private ?Player $player;
    private string $playerName;
    private array $ranks = [];
    private array $permissions = [];
    private string $chatColor = TF::RED;
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
        $this->executeQuery("
            CREATE TABLE IF NOT EXISTS players (
                name VARCHAR(30) PRIMARY KEY,
                ranks TEXT NOT NULL,
                permissions TEXT NOT NULL,
                chatColor VARCHAR(30) NOT NULL,
                tags TEXT NOT NULL,
                displayTags TEXT NOT NULL
            )
        ");
    }

    private function loadData(): void {
        $stmt = $this->prepareStatement("SELECT * FROM players WHERE name = ?");
        $stmt->bind_param("s", $this->playerName);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {
            $this->populateData($result->fetch_assoc());
        } else {
            $this->setDefaultValues();
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
        $colorMap = $this->getTextFormatColorMap();
        return $colorMap[$colorCode] ?? TF::RED;
    }

    private function getTextFormatColorMap(): array {
        return [
            "&0" => TF::BLACK, "&1" => TF::DARK_BLUE, "&2" => TF::DARK_GREEN,
            "&3" => TF::DARK_AQUA, "&4" => TF::DARK_RED, "&5" => TF::DARK_PURPLE,
            "&6" => TF::GOLD, "&7" => TF::GRAY, "&8" => TF::DARK_GRAY, "&9" => TF::BLUE,
            "&a" => TF::GREEN, "&b" => TF::AQUA, "&c" => TF::RED, "&d" => TF::LIGHT_PURPLE,
            "&e" => TF::YELLOW, "&f" => TF::WHITE,
        ];
    }

    public function getRanks(): array {
        return $this->ranks;
    }

    public function addRank(string $rank): void {
        if (!in_array($rank, $this->ranks, true) && $this->rankManager->rankExists($rank)) {
            $this->ranks[] = $rank;
            $this->saveData();
        }
    }

    public function removeRank(string $rank): void {
        if (($key = array_search($rank, $this->ranks, true)) !== false) {
            unset($this->ranks[$key]);
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
            ON DUPLICATE KEY UPDATE ranks = VALUES(ranks), permissions = VALUES(permissions),
                chatColor = VALUES(chatColor), tags = VALUES(tags), displayTags = VALUES(displayTags)
        ");

        $implode = implode(",", $this->ranks);
        $implode1 = implode(",", $this->permissions);
        $f = array_search($this->chatColor, $this->getTextFormatColorMap(), true) ?? '&c';
        $implode2 = implode(",", $this->tags);
        $implode3 = implode(",", $this->displayTags);
        $stmt->bind_param(
            "ssssss",
            $this->playerName,
            $implode,
            $implode1,
            $f,
            $implode2,
            $implode3,
        );

        $this->executeStatement($stmt);
    }

    private function prepareStatement(string $query): \mysqli_stmt {
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
        $displayTags = !empty($this->displayTags) ? implode(" ", $this->displayTags) : "";
        $highestRank = $this->getHighestRank();

        $format = RankSystem::getInstance()->getConfig()->get("chat-format");
        $format = str_replace(
            ["{player}", "{rank}", "{faction}", "{faction_placement}", "{displayTags}", "{selectedTag}", "{message}"],
            [$this->playerName, $highestRank, implode(" ", $this->tags), $displayTags, $selectedTag, "{message}"],
            $format
        );
        return $this->placeholderManager->replacePlaceholders($format);
    }

    private function getHighestRank(): string {
        $highestRank = "Default";
        foreach ($this->ranks as $rank) {
            if ($this->rankManager->rankExists($rank)) {
                $highestRank = $rank;
                break;
            }
        }
        return $highestRank;
    }
}

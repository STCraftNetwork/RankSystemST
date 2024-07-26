<?php

namespace KanadeBlue\RankSystemST;

use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use mysqli;

class Session {

    private ?Player $player;
    private string $playerName;
    private array $ranks = [];
    private array $permissions = [];
    private string $chatColor;
    private array $tags = [];
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

    /**
     * Create the players table if it does not exist.
     */
    private function createTables() : void {
        $sql = "CREATE TABLE IF NOT EXISTS players (
            name VARCHAR(30) PRIMARY KEY,
            ranks TEXT,
            permissions TEXT,
            chatColor VARCHAR(30),
            tags TEXT
        )";
        if (!$this->db->query($sql)) {
            $this->handleError('Failed to create players table');
        }
    }

    /**
     * Load player data from the database.
     */
    private function loadData() : void {
        $stmt = $this->db->prepare("SELECT * FROM players WHERE name = ?");
        if ($stmt === false) {
            $this->handleError('Failed to prepare statement');
            return;
        }
        $stmt->bind_param("s", $this->playerName);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false || $result->num_rows === 0) {
            $this->ranks = ["Default"];
            $this->permissions = [];
            $this->chatColor = TF::RED;
            $this->tags = [];
        } else {
            $data = $result->fetch_assoc();
            $this->ranks = explode(",", $data["ranks"]);
            $this->permissions = explode(",", $data["permissions"]);
            $this->chatColor = $this->getTextFormatColor($data["chatColor"]);
            $this->tags = explode(",", $data["tags"]);
        }
        $stmt->close();
    }

    /**
     * Map color codes to text format colors.
     *
     * @param string $colorCode
     * @return string
     */
    private function getTextFormatColor(string $colorCode): string {
        $colorMap = $this->getTextFormatColorMap();
        return $colorMap[$colorCode] ?? TF::RED;
    }

    /**
     * Get a map of text format colors.
     *
     * @return array
     */
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

    /**
     * Get the ranks of the player.
     *
     * @return array
     */
    public function getRanks() : array {
        return $this->ranks;
    }

    /**
     * Add a rank to the player.
     *
     * @param string $rank
     */
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

    /**
     * Remove a rank from the player.
     *
     * @param string $rank
     */
    public function removeRank(string $rank) : void {
        $key = array_search($rank, $this->ranks);
        if ($key !== false) {
            unset($this->ranks[$key]);
            $this->ranks = array_values($this->ranks);
            $this->saveData();
        }
    }

    /**
     * Get the permissions of the player.
     *
     * @return array
     */
    public function getPermissions() : array {
        return $this->permissions;
    }

    /**
     * Add a permission to the player.
     *
     * @param string $permission
     */
    public function addPermission(string $permission) : void {
        if (!in_array($permission, $this->permissions)) {
            $this->permissions[] = $permission;
            $this->saveData();
        }
    }

    /**
     * Remove a permission from the player.
     *
     * @param string $permission
     */
    public function removePermission(string $permission) : void {
        $key = array_search($permission, $this->permissions);
        if ($key !== false) {
            unset($this->permissions[$key]);
            $this->permissions = array_values($this->permissions);
            $this->saveData();
        }
    }

    /**
     * Get the chat color of the player.
     *
     * @return string
     */
    public function getChatColor() : string {
        return $this->chatColor;
    }

    /**
     * Set the chat color of the player.
     *
     * @param string $chatColor
     */
    public function setChatColor(string $chatColor) : void {
        $this->chatColor = $this->getTextFormatColor($chatColor);
        $this->saveData();
    }

    /**
     * Get the tags of the player.
     *
     * @return array
     */
    public function getTags() : array {
        return $this->tags;
    }

    /**
     * Add a tag to the player.
     *
     * @param string $tag
     */
    public function addTag(string $tag) : void {
        if (!in_array($tag, $this->tags)) {
            $this->tags[] = $tag;
            $this->saveData();
        } else {
            $this->logInfo("The player already has the tag {$tag}.");
        }
    }

    /**
     * Remove a tag from the player.
     *
     * @param string $tag
     */
    public function removeTag(string $tag) : void {
        $key = array_search($tag, $this->tags);
        if ($key !== false) {
            unset($this->tags[$key]);
            $this->tags = array_values($this->tags);
            $this->saveData();
        }
    }

    /**
     * Save player data to the database.
     */
    private function saveData() : void {
        $stmt = $this->db->prepare("
            INSERT INTO players (name, ranks, permissions, chatColor, tags)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                ranks = VALUES(ranks), 
                permissions = VALUES(permissions), 
                chatColor = VALUES(chatColor), 
                tags = VALUES(tags)
        ");
        if ($stmt === false) {
            $this->handleError('Failed to prepare statement');
            return;
        }

        $ranks = implode(",", $this->ranks);
        $permissions = implode(",", $this->permissions);
        $chatColor = strtolower(array_search($this->chatColor, $this->getTextFormatColorMap()));
        $tags = implode(",", $this->tags);

        $stmt->bind_param("sssss", $this->playerName, $ranks, $permissions, $chatColor, $tags);
        if (!$stmt->execute()) {
            $this->handleError('Failed to execute statement');
        }
        $stmt->close();
    }

    /**
     * Log information messages.
     *
     * @param string $message
     */
    private function logInfo(string $message) : void {
        RankSystem::getInstance()->getLogger()->info($message);
    }

    /**
     * Get the chat format for the player.
     *
     * @return string
     */
    public function getChatFormat() : string {
        $tags = implode(" ", $this->tags);
        $highestRank = $this->getHighestRank();
        $chatColor = $this->chatColor;
        $displayName = $this->player ? $this->player->getDisplayName() : $this->playerName;
        return ($highestRank ? $highestRank . " " : "") . $tags . " " . $displayName . " " . $chatColor . "{message}";
    }

    /**
     * Get the highest rank of the player.
     *
     * @return string|null
     */
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

    /**
     * Handle database errors by logging them.
     *
     * @param string $message
     */
    private function handleError(string $message) : void {
        error_log($message . ': ' . $this->db->error);
    }
}

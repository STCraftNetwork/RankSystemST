<?php

namespace KanadeBlue\RankSystemST;

use pocketmine\player\Player;
use pocketmine\utils\TextFormat as TF;
use mysqli;

class Session{

    private Player $player;
    private array $ranks;
    private array $permissions;
    private string $chatColor;
    private array $tags;
    private mysqli $db;
    private RankManager $rankManager;

    public function __construct(Player $player, mysqli $db, RankManager $rankManager){
        $this->player = $player;
        $this->db = $db;
        $this->rankManager = $rankManager;
        $this->createTables();
        $this->loadData();
    }

    private function createTables() : void{
        $this->db->query("CREATE TABLE IF NOT EXISTS players (
            name VARCHAR(30) PRIMARY KEY,
            ranks TEXT,
            permissions TEXT,
            chatColor VARCHAR(30),
            tags TEXT
        )");
    }

    private function loadData() : void{
        $name = $this->db->escape_string($this->player->getName());
        $result = $this->db->query("SELECT * FROM players WHERE name = '$name'");
        if($result === false || $result->num_rows === 0){
            $this->ranks = ["Default"];
            $this->permissions = [];
            $this->chatColor = TF::WHITE;
            $this->tags = [];
        } else {
            $data = $result->fetch_assoc();
            $this->ranks = explode(",", $data["ranks"]);
            $this->permissions = explode(",", $data["permissions"]);
            $this->chatColor = constant(TF::class . "::" . strtoupper($data["chatColor"]));
            $this->tags = explode(",", $data["tags"]);
        }
    }

    public function getRanks() : array{
        return $this->ranks;
    }

    public function addRank(string $rank) : void{
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

    public function removeRank(string $rank) : void{
        $key = array_search($rank, $this->ranks);
        if($key !== false){
            unset($this->ranks[$key]);
            $this->ranks = array_values($this->ranks);
            $this->saveData();
        }
    }

    public function getPermissions() : array{
        return $this->permissions;
    }

    public function addPermission(string $permission) : void{
        $this->permissions[] = $permission;
        $this->saveData();
    }

    public function removePermission(string $permission) : void{
        $key = array_search($permission, $this->permissions);
        if($key !== false){
            unset($this->permissions[$key]);
            $this->saveData();
        }
    }

    public function getChatColor() : string{
        return $this->chatColor;
    }

    public function setChatColor(string $chatColor) : void{
        $this->chatColor = constant(TF::class . "::" . strtoupper($chatColor));
        $this->saveData();
    }

    public function getTags() : array{
        return $this->tags;
    }

    public function addTag(string $tag) : void{
        if (!in_array($tag, $this->tags)) {
            $this->tags[] = $tag;
            $this->saveData();
        } else {
            RankSystem::getInstance()->getLogger()->info("The player already has the tag {$tag}.\n");
        }
    }

    public function removeTag(string $tag) : void{
        $key = array_search($tag, $this->tags);
        if($key !== false){
            unset($this->tags[$key]);
            $this->tags = array_values($this->tags);
            $this->saveData();
        }
    }

    private function saveData() : void{
        $name = $this->db->escape_string($this->player->getName());
        $ranks = $this->db->escape_string(implode(",", $this->ranks));
        $permissions = $this->db->escape_string(implode(",", $this->permissions));
        $chatColor = $this->db->escape_string(strtolower(substr($this->chatColor, 2)));
        $tags = $this->db->escape_string(implode(",", $this->tags));
        $this->db->query("UPDATE players SET ranks = '$ranks', permissions = '$permissions', chatColor = '$chatColor', tags = '$tags' WHERE name = '$name'");
    }

    public function getChatFormat() : string
    {
        $tags = implode(" ", $this->getTags());
        $highestRank = $this->getHighestRank();
        $chatColor = $this->getChatColor();
        $displayName = $this->player->getDisplayName();
        if ($highestRank == null) return $tags . " " . $displayName. " ". $chatColor . "{message}";
        if (empty($this->getTags()) && $highestRank == null) return $displayName .  " " . $chatColor . "{message}";
        return $highestRank . " " . $tags . " " . $displayName .  " " . $chatColor . "{message}";
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

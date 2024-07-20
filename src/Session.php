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
    private string $tag;
    private mysqli $db;
    private RankManager $rankManager;

    public function __construct(Player $player, mysqli $db, RankManager $rankManager){
        $this->player = $player;
        $this->db = $db;
        $this->rankManager = $rankManager;
        $this->loadData();
    }

    private function loadData() : void{
        $name = $this->db->escape_string($this->player->getName());
        $result = $this->db->query("SELECT * FROM players WHERE name = '$name'");
        if($result === false || $result->num_rows === 0){
            $this->ranks = ["Default"];
            $this->permissions = [];
            $this->chatColor = TF::WHITE;
            $this->tag = "";
        } else {
            $data = $result->fetch_assoc();
            $this->ranks = explode(",", $data["ranks"]);
            $this->permissions = explode(",", $data["permissions"]);
            $this->chatColor = constant(TF::class . "::" . strtoupper($data["chatColor"]));
            $this->tag = $data["tag"];
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
                echo "The rank {$rank} does not exist.\n";
            }
        } else {
            echo "The player already has the rank {$rank}.\n";
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

    public function getTag() : string{
        return $this->tag;
    }

    public function setTag(string $tag) : void{
        $this->tag = $tag;
        $this->saveData();
    }

    private function saveData() : void{
        $name = $this->db->escape_string($this->player->getName());
        $ranks = $this->db->escape_string(implode(",", $this->ranks));
        $permissions = $this->db->escape_string(implode(",", $this->permissions));
        $chatColor = $this->db->escape_string(strtolower(substr($this->chatColor, 2)));
        $tag = $this->db->escape_string($this->tag);
        $this->db->query("UPDATE players SET ranks = '$ranks', permissions = '$permissions', chatColor = '$chatColor', tag = '$tag' WHERE name = '$name'");
    }
}
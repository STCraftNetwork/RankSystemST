<?php

namespace KanadeBlue\RankSystemST;

use mysqli;

class RankManager{

    private mysqli $db;
    private array $ranks = [];

    public function __construct(mysqli $db){
        $this->db = $db;
        $this->createTables();
        $this->loadRanks();
    }

    private function createTables() : void{
        $this->db->query("CREATE TABLE IF NOT EXISTS ranks (
            name VARCHAR(30) PRIMARY KEY
        )");
    }

    private function loadRanks() : void{
        $result = $this->db->query("SELECT * FROM ranks");
        if($result === false){
            return;
        }
        while($row = $result->fetch_assoc()){
            $this->ranks[$row["name"]] = $row;
        }
    }

    public function getRanks() : array{
        return $this->ranks;
    }

    public function rankExists(string $rank) : bool{
        return isset($this->ranks[$rank]);
    }

    public function createRank(string $rank) : ?int{
        if($this->rankExists($rank)){
            return null;
        }
        $rank = $this->db->escape_string($rank);
        $this->db->query("INSERT INTO ranks (name) VALUES ('$rank')");
        $this->ranks[$rank] = ["name" => $rank];
        return 1;
    }

    public function deleteRank(string $rank) : ?int{
        if(!$this->rankExists($rank)){
            return null;
        }
        $rank = $this->db->escape_string($rank);
        $this->db->query("DELETE FROM ranks WHERE name = '$rank'");
        unset($this->ranks[$rank]);
        return 1;
    }
}
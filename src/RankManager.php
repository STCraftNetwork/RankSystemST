<?php

namespace KanadeBlue\RankSystemST;

use mysqli;

class RankManager {

    private mysqli $db;
    private array $ranks = [];

    public function __construct(mysqli $db) {
        $this->db = $db;
        $this->createTables();
        $this->loadRanks();
    }

    private function createTables() : void {
        $this->db->query("CREATE TABLE IF NOT EXISTS ranks (
            name VARCHAR(30) PRIMARY KEY,
            parent VARCHAR(30) DEFAULT NULL,
            color VARCHAR(7) DEFAULT NULL,
            FOREIGN KEY (parent) REFERENCES ranks(name)
        )");
    }

    private function loadRanks() : void {
        $result = $this->db->query("SELECT * FROM ranks");
        if ($result === false) {
            return;
        }
        while ($row = $result->fetch_assoc()) {
            $this->ranks[$row["name"]] = $row;
        }
    }

    public function getRanks() : array {
        return $this->ranks;
    }

    public function rankExists(string $rank) : bool {
        return isset($this->ranks[$rank]);
    }

    public function createRank(string $rank, ?string $parent = null, ?string $color = null) : ?int {
        if ($this->rankExists($rank)) {
            return null;
        }
        if ($parent !== null && !$this->rankExists($parent)) {
            return null; // Parent rank does not exist
        }
        $rank = $this->db->escape_string($rank);
        $parent = $parent ? $this->db->escape_string($parent) : null;
        $color = $color ? $this->db->escape_string($color) : null;
        $parentValue = $parent ? "'$parent'" : "NULL";
        $this->db->query("INSERT INTO ranks (name, parent, color) VALUES ('$rank', $parentValue, '$color')");
        $this->ranks[$rank] = ["name" => $rank, "parent" => $parent, "color" => $color];
        return 1;
    }

    public function deleteRank(string $rank) : ?int {
        if (!$this->rankExists($rank)) {
            return null;
        }
        $rank = $this->db->escape_string($rank);
        $this->db->query("DELETE FROM ranks WHERE name = '$rank'");
        unset($this->ranks[$rank]);
        return 1;
    }

    private array $rankHierarchy = [
        'owner' => 1,
        'Owner' => 2,
        'member' => 3,
        'guest' => 4,
    ];

    public function getRankHierarchy() : array {
        return $this->rankHierarchy;
    }

    private function addToHierarchy(array &$hierarchy, string $parent, string $name) : void {
        foreach ($hierarchy as $key => &$children) {
            if ($key === $parent) {
                $children[$name] = [];
                return;
            }
            $this->addToHierarchy($children, $parent, $name);
        }
    }

    public function getFormattedRank(string $rank): ?string {
        if (!$this->rankExists($rank)) {
            return null;
        }
        $rankData = $this->ranks[$rank];
        $color = $rankData['color'] ?? '';
        return "{$color}{$rank}&r";
    }
}

<?php

namespace KanadeBlue\RankSystemST;

use mysqli;
use mysqli_stmt;

class RankManager {

    private mysqli $db;
    private array $ranks = [];
    private array $rankHierarchy = [];

    public function __construct(mysqli $db) {
        $this->db = $db;
        $this->createTables();
        $this->loadRanks();
    }

    /**
     * Create the ranks and permissions tables if they do not exist.
     */
    private function createTables() : void {
        // Create ranks table
        $sql = "CREATE TABLE IF NOT EXISTS ranks (
            name VARCHAR(30) PRIMARY KEY,
            parent VARCHAR(30) DEFAULT NULL,
            chat_format VARCHAR(50) DEFAULT NULL,
            FOREIGN KEY (parent) REFERENCES ranks(name)
        )";
        if (!$this->db->query($sql)) {
            $this->handleError('Failed to create ranks table');
        }

        // Create permissions table
        $sql = "CREATE TABLE IF NOT EXISTS permissions (
            rank_name VARCHAR(30),
            permission VARCHAR(255),
            FOREIGN KEY (rank_name) REFERENCES ranks(name),
            PRIMARY KEY (rank_name, permission)
        )";
        if (!$this->db->query($sql)) {
            $this->handleError('Failed to create permissions table');
        }
    }

    /**
     * Load all ranks and their permissions from the database into memory.
     */
    private function loadRanks() : void {
        $sql = "SELECT * FROM ranks";
        $result = $this->db->query($sql);
        if ($result === false) {
            $this->handleError('Failed to load ranks');
            return;
        }
        while ($row = $result->fetch_assoc()) {
            $this->ranks[$row["name"]] = $row;
        }
        $this->rankHierarchy = $this->buildRankHierarchy();
    }

    /**
     * Build the rank hierarchy from the ranks loaded.
     *
     * @return array
     */
    private function buildRankHierarchy() : array {
        $hierarchy = [];
        foreach ($this->ranks as $rankName => $rankData) {
            $parent = $rankData['parent'] ?? null;
            if ($parent) {
                if (!isset($hierarchy[$parent])) {
                    $hierarchy[$parent] = ['children' => []];
                }
                $hierarchy[$parent]['children'][$rankName] = ['children' => []];
            } else {
                $hierarchy[$rankName] = ['children' => []];
            }
        }
        return $hierarchy;
    }

    /**
     * Get all ranks.
     *
     * @return array
     */
    public function getRanks() : array {
        return $this->ranks;
    }

    /**
     * Check if a rank exists.
     *
     * @param string $rank
     * @return bool
     */
    public function rankExists(string $rank) : bool {
        return isset($this->ranks[$rank]);
    }

    /**
     * Create a new rank.
     *
     * @param string $rank
     * @param string|null $parent
     * @param string|null $chatFormat
     * @return int|null
     */
    public function createRank(string $rank, ?string $parent = null, ?string $chatFormat = null) : ?int {
        if ($this->rankExists($rank)) {
            return null;
        }
        if ($parent !== null && !$this->rankExists($parent)) {
            return null;
        }

        $stmt = $this->db->prepare("INSERT INTO ranks (name, parent, chat_format) VALUES (?, ?, ?)");
        if ($stmt === false) {
            $this->handleError('Failed to prepare statement');
            return null;
        }
        $stmt->bind_param("sss", $rank, $parent, $chatFormat);
        if (!$stmt->execute()) {
            $this->handleError('Failed to execute statement');
            return null;
        }
        $stmt->close();

        $this->ranks[$rank] = [
            "name" => $rank,
            "parent" => $parent,
            "chat_format" => $chatFormat
        ];
        $this->rankHierarchy = $this->buildRankHierarchy();
        return 1;
    }

    /**
     * Delete a rank.
     *
     * @param string $rank
     * @return int|null
     */
    public function deleteRank(string $rank) : ?int {
        if (!$this->rankExists($rank)) {
            return null;
        }

        // Delete associated permissions first
        $stmt = $this->db->prepare("DELETE FROM permissions WHERE rank_name = ?");
        if ($stmt === false) {
            $this->handleError('Failed to prepare statement');
            return null;
        }
        $stmt->bind_param("s", $rank);
        if (!$stmt->execute()) {
            $this->handleError('Failed to execute statement');
            return null;
        }
        $stmt->close();

        // Then delete the rank
        $stmt = $this->db->prepare("DELETE FROM ranks WHERE name = ?");
        if ($stmt === false) {
            $this->handleError('Failed to prepare statement');
            return null;
        }
        $stmt->bind_param("s", $rank);
        if (!$stmt->execute()) {
            $this->handleError('Failed to execute statement');
            return null;
        }
        $stmt->close();

        unset($this->ranks[$rank]);
        $this->rankHierarchy = $this->buildRankHierarchy();
        return 1;
    }

    /**
     * Add a permission to a rank.
     *
     * @param string $rank
     * @param string $permission
     * @return int|null
     */
    public function addPermission(string $rank, string $permission) : ?int {
        if (!$this->rankExists($rank)) {
            return null;
        }

        $stmt = $this->db->prepare("INSERT INTO permissions (rank_name, permission) VALUES (?, ?)");
        if ($stmt === false) {
            $this->handleError('Failed to prepare statement');
            return null;
        }
        $stmt->bind_param("ss", $rank, $permission);
        if (!$stmt->execute()) {
            $this->handleError('Failed to execute statement');
            return null;
        }
        $stmt->close();
        return 1;
    }

    /**
     * Remove a permission from a rank.
     *
     * @param string $rank
     * @param string $permission
     * @return int|null
     */
    public function removePermission(string $rank, string $permission) : ?int {
        if (!$this->rankExists($rank)) {
            return null;
        }

        $stmt = $this->db->prepare("DELETE FROM permissions WHERE rank_name = ? AND permission = ?");
        if ($stmt === false) {
            $this->handleError('Failed to prepare statement');
            return null;
        }
        $stmt->bind_param("ss", $rank, $permission);
        if (!$stmt->execute()) {
            $this->handleError('Failed to execute statement');
            return null;
        }
        $stmt->close();
        return 1;
    }

    /**
     * Get all permissions for a rank.
     *
     * @param string $rank
     * @return array
     */
    public function getPermissions(string $rank) : array {
        if (!$this->rankExists($rank)) {
            return [];
        }

        $stmt = $this->db->prepare("SELECT permission FROM permissions WHERE rank_name = ?");
        if ($stmt === false) {
            $this->handleError('Failed to prepare statement');
            return [];
        }
        $stmt->bind_param("s", $rank);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false) {
            $this->handleError('Failed to execute statement');
            return [];
        }

        $permissions = [];
        while ($row = $result->fetch_assoc()) {
            $permissions[] = $row['permission'];
        }
        $stmt->close();
        return $permissions;
    }

    /**
     * Get the rank hierarchy.
     *
     * @return array
     */
    public function getRankHierarchy() : array {
        return $this->rankHierarchy;
    }

    /**
     * Get the formatted rank string.
     *
     * @param string $rank
     * @return string|null
     */
    public function getFormattedRank(string $rank): ?string {
        if (!$this->rankExists($rank)) {
            return null;
        }
        $rankData = $this->ranks[$rank];
        $chatFormat = $rankData['chat_format'] ?? '';
        return "{$chatFormat}{$rank}§r";
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

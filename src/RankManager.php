<?php

declare(strict_types=1);

namespace KanadeBlue\RankSystemST;

use mysqli;
use mysqli_stmt;

class RankManager
{
    private mysqli $db;
    private array $ranks = [];
    private array $rankHierarchy = [];
    private PlaceholderManager $placeholderManager;

    public function __construct(mysqli $db)
    {
        $this->db = $db;
        $this->createTables();
        $this->loadRanks();
        $this->placeholderManager = new PlaceholderManager();

        $this->placeholderManager->registerPlaceholder('rank_name', function($matches) {
            return $matches[1] ?? 'Unknown';
        });
    }

    private function createTables(): void
    {
        $queries = [
            "CREATE TABLE IF NOT EXISTS ranks (
                name VARCHAR(30) PRIMARY KEY,
                parent VARCHAR(30) DEFAULT NULL,
                chat_format VARCHAR(50) DEFAULT NULL,
                description TEXT DEFAULT NULL,
                color VARCHAR(20) DEFAULT NULL,
                FOREIGN KEY (parent) REFERENCES ranks(name)
            )",
            "CREATE TABLE IF NOT EXISTS permissions (
                rank_name VARCHAR(30),
                permission VARCHAR(255),
                FOREIGN KEY (rank_name) REFERENCES ranks(name),
                PRIMARY KEY (rank_name, permission)
            )"
        ];

        foreach ($queries as $query) {
            if (!$this->db->query($query)) {
                $this->handleError('Failed to create tables');
                return;
            }
        }
    }

    private function loadRanks(): void
    {
        $result = $this->db->query("SELECT * FROM ranks");

        if ($result === false) {
            $this->handleError('Failed to load ranks');
            return;
        }

        while ($row = $result->fetch_assoc()) {
            $this->ranks[$row["name"]] = $row;
        }

        $this->rankHierarchy = $this->buildRankHierarchy();
    }

    private function buildRankHierarchy(): array
    {
        $hierarchy = [];

        foreach ($this->ranks as $rankName => $rankData) {
            $parent = $rankData['parent'] ?? null;
            if ($parent) {
                $hierarchy[$parent]['children'][$rankName] = ['children' => []];
            } else {
                $hierarchy[$rankName] = ['children' => []];
            }
        }

        return $hierarchy;
    }

    public function getRanks(): array
    {
        return $this->ranks;
    }

    public function rankExists(string $rank): bool
    {
        return isset($this->ranks[$rank]);
    }

    public function createRank(string $rank, ?string $parent = null, ?string $chatFormat = null, ?string $description = null, ?string $color = null): ?int
    {
        if ($this->rankExists($rank) || ($parent !== null && !$this->rankExists($parent))) {
            return null;
        }

        $stmt = $this->prepareStatement(
            "INSERT INTO ranks (name, parent, chat_format, description, color) VALUES (?, ?, ?, ?, ?)",
            'Failed to create rank'
        );
        if ($stmt === null) {
            return null;
        }

        $stmt->bind_param("sssss", $rank, $parent, $chatFormat, $description, $color);
        return $this->executeStatement($stmt);
    }

    public function deleteRank(string $rank): ?int
    {
        if (!$this->rankExists($rank)) {
            return null;
        }

        if (!$this->deletePermissions($rank)) {
            return null;
        }

        $stmt = $this->prepareStatement("DELETE FROM ranks WHERE name = ?", 'Failed to delete rank');
        if ($stmt === null) {
            return null;
        }

        $stmt->bind_param("s", $rank);
        if ($stmt->execute()) {
            unset($this->ranks[$rank]);
            $this->rankHierarchy = $this->buildRankHierarchy();
            return 1;
        }

        $this->handleError('Failed to execute delete rank statement');
        return null;
    }

    public function addPermission(string $rank, string $permission): ?int
    {
        if (!$this->rankExists($rank)) {
            return null;
        }

        $stmt = $this->prepareStatement("INSERT INTO permissions (rank_name, permission) VALUES (?, ?)", 'Failed to add permission');
        if ($stmt === null) {
            return null;
        }

        $stmt->bind_param("ss", $rank, $permission);
        return $this->executeStatement($stmt);
    }

    public function removePermission(string $rank, string $permission): ?int
    {
        if (!$this->rankExists($rank)) {
            return null;
        }

        $stmt = $this->prepareStatement("DELETE FROM permissions WHERE rank_name = ? AND permission = ?", 'Failed to remove permission');
        if ($stmt === null) {
            return null;
        }

        $stmt->bind_param("ss", $rank, $permission);
        return $this->executeStatement($stmt);
    }

    public function getPermissions(string $rank): array
    {
        if (!$this->rankExists($rank)) {
            return [];
        }

        $stmt = $this->prepareStatement("SELECT permission FROM permissions WHERE rank_name = ?", 'Failed to get permissions');
        if ($stmt === null) {
            return [];
        }

        $stmt->bind_param("s", $rank);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result === false) {
            $this->handleError('Failed to execute get permissions statement');
            return [];
        }

        $permissions = [];
        while ($row = $result->fetch_assoc()) {
            $permissions[] = $row['permission'];
        }

        $stmt->close();
        return $permissions;
    }

    public function getRankHierarchy(): array
    {
        return $this->rankHierarchy;
    }

    public function getFormattedRank(string $rank): ?string
    {
        if (!$this->rankExists($rank)) {
            return null;
        }

        $chatFormat = $this->ranks[$rank]['chat_format'] ?? '';
        $color = $this->ranks[$rank]['color'] ?? '';

        $data = [
            'rank_name' => $rank,
            'description' => $this->ranks[$rank]['description'] ?? '',
            'color' => $color,
        ];

        $formatted = $this->placeholderManager->replacePlaceholders("{$color}{$chatFormat}{$rank}&r", $data);
        return $formatted;
    }

    public function registerPlaceholder(string $name, callable $callback): void
    {
        $this->placeholderManager->registerPlaceholder($name, $callback);
    }

    private function prepareStatement(string $query, string $errorMessage): ?mysqli_stmt
    {
        $stmt = $this->db->prepare($query);

        if ($stmt === false) {
            $this->handleError($errorMessage);
            return null;
        }

        return $stmt;
    }

    private function executeStatement(mysqli_stmt $stmt): ?int
    {
        if (!$stmt->execute()) {
            $this->handleError('Failed to execute statement');
            return null;
        }

        $stmt->close();
        return 1;
    }

    private function deletePermissions(string $rank): bool
    {
        $stmt = $this->prepareStatement("DELETE FROM permissions WHERE rank_name = ?", 'Failed to delete permissions');
        if ($stmt === null) {
            return false;
        }

        $stmt->bind_param("s", $rank);
        if (!$stmt->execute()) {
            $this->handleError('Failed to execute delete permissions statement');
            return false;
        }

        $stmt->close();
        return true;
    }

    private function handleError(string $message): void
    {
        error_log($message . ': ' . $this->db->error);
    }
}

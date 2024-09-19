<?php

declare(strict_types=1);

namespace KanadeBlue\RankSystemST;

use KanadeBlue\RankSystemST\commands\AddPermissionCommand;
use KanadeBlue\RankSystemST\commands\CreateRankCommand;
use KanadeBlue\RankSystemST\commands\SetRankCommand;
use pocketmine\player\OfflinePlayer;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerChatEvent;
use mysqli;

class RankSystem extends PluginBase implements Listener
{
    private static RankSystem $instance;
    private array $sessions = [];
    private mysqli $db;
    private RankManager $rankManager;

    protected function onEnable(): void
    {
        $this->initializeDatabase();
        $this->initializeCommands();
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        self::$instance = $this;
    }

    protected function onDisable(): void
    {
        $this->db->close();
    }

    public static function getInstance(): RankSystem
    {
        return self::$instance;
    }

    private function initializeDatabase(): void
    {
        $this->saveDefaultConfig();
        $config = $this->getConfig()->get("database");
        $this->db = new mysqli($config["host"], $config["username"], $config["password"], $config["name"]);

        if ($this->db->connect_error) {
            $this->getLogger()->error("Failed to connect to database: " . $this->db->connect_error);
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        $this->rankManager = new RankManager($this->db);
    }

    private function initializeCommands(): void
    {
        $commandMap = $this->getServer()->getCommandMap();
        $commandMap->register("setrank", new SetRankCommand($this));
        $commandMap->register("createrank", new CreateRankCommand($this));
        $commandMap->register("addpermission", new AddPermissionCommand($this));
    }

    public function onJoin(PlayerJoinEvent $event): void
    {
        $player = $event->getPlayer();
        $session = new Session($player, $player->getName(), $this->db, $this->rankManager);
        $this->sessions[$player->getName()] = $session;

        $this->applyPermissions($player, $session);
        $this->updatePlayerNameTag($player, $session);
    }

    private function applyPermissions(Player $player, Session $session): void
    {
        $attachment = $player->addAttachment($this);
        foreach ($session->getPermissions() as $permission) {
            $attachment->setPermission($permission, true);
        }

        foreach ($session->getRanks() as $rank) {
            foreach ($this->rankManager->getPermissions($rank) as $permission) {
                $attachment->setPermission($permission, true);
            }
        }
    }

    private function updatePlayerNameTag(Player $player, Session $session): void
    {
        $tags = implode(" ", $session->getTags());
        $nameTag = $tags . $session->getChatColor() . " " . $player->getName();
        $player->setNameTag($nameTag);
    }

    public function onQuit(PlayerQuitEvent $event): void
    {
        $player = $event->getPlayer();
        unset($this->sessions[$player->getName()]);
    }

    public function onChat(PlayerChatEvent $event): void
    {
        $player = $event->getPlayer();
        $session = $this->getSession($player);

        if ($session !== null) {
            $formatter = new ChatFormat($session);
            $event->setFormatter($formatter);
        }
    }

    public function getSession(OfflinePlayer|Player $player): ?Session
    {
        return $this->sessions[$player->getName()] ?? null;
    }

    public function getDatabase(): mysqli
    {
        return $this->db;
    }

    public function getRankManager(): RankManager
    {
        return $this->rankManager;
    }
}

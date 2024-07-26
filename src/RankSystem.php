<?php

declare(strict_types=1);

namespace KanadeBlue\RankSystemST;

use KanadeBlue\RankSystemST\commands\AddPermissionCommand;
use KanadeBlue\RankSystemST\commands\CreateRankCommand;
use KanadeBlue\RankSystemST\commands\SetRankCommand;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerChatEvent;
use mysqli;

class RankSystem extends PluginBase implements Listener{

    private static RankSystem $instance;
    private array $sessions = [];
    private mysqli $db;

    private RankManager $rankManager;

    public function onEnable() : void{
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->saveDefaultConfig();
        RankSystem::$instance = $this;
        $config = $this->getConfig()->get("database");
        $this->db = new mysqli($config["host"], $config["username"], $config["password"], $config["name"]);
        if($this->db->connect_error){
            $this->getLogger()->error("Failed to connect to database: " . $this->db->connect_error);
            $this->getServer()->getPluginManager()->disablePlugin($this);
        }
        $this->rankManager = new RankManager($this->db);
        $this->getServer()->getCommandMap()->register("setrank", new SetRankCommand($this));
        $this->getServer()->getCommandMap()->register("createrank", new CreateRankCommand($this));
        $this->getServer()->getCommandMap()->register("addpermission", new AddPermissionCommand($this));
    }

    public function onDisable() : void{
        $this->db->close();
    }

    public static function getInstance(): RankSystem
    {
        return self::$instance;
    }

    public function onJoin(PlayerJoinEvent $event): void
    {
        $player = $event->getPlayer();
        $session = new Session($player, $player->getName(), $this->db, $this->rankManager);
        $this->sessions[$player->getName()] = $session;
        foreach($session->getPermissions() as $permission){
            $player->addAttachment($this)->setPermission($permission, true);
        }
        foreach ($session->getRanks() as $rank) {
            foreach ($this->getRankManager()->getPermissions($rank) as $permission) {
                $player->addAttachment($this)->setPermission($permission, true);
            }
        }
        $tags = implode(" ", $session->getTags());
        $player->setNameTag($tags . $session->getChatColor() . " " . $player->getName());
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
        if($session !== null){
            $formatter = New ChatFormat($this->getSession($player));
            $event->setFormatter($formatter);
        }
    }



    public function getSession(Player $player) : ?Session{
        return $this->sessions[$player->getName()] ?? null;
    }

    public function getRankManager() : ?RankManager
    {
        return $this->rankManager ?? null;
    }
}

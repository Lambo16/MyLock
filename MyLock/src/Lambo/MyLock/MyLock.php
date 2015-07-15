<?php

namespace Lambo\MyLock;

use Lambo\MyLock\providers\MySQLProvider;

use pocketmine\plugin\PluginBase;
use pocketmine\Server;

use pocketmine\utils\Config;

use pocketmine\math\Vector3;

use pocketmine\block\Chest as Chest;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use pocketmine\Player;
use pocketmine\entity\Entity;

use pocketmine\level\Level;
use pocketmine\level\Position;

use pocketmine\event\Event;
use pocketmine\event\EventPriority;
use pocketmine\event\Listener;

use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockBreakEvent;

class MyLock extends PluginBase implements Listener{

    private $provider;
    private $autolock=false;
    private $allowadding=false;
    private $limit=8;

    private $locktemp=array();

    public $chestconfig=null;

    public function onEnable(){
        $this->saveDefaultConfig();
        $this->saveResource("chests.yml");
        $conf = $this->getConfig();
        $this->autolock = $conf->get("autolock");
        $this->allowadding = $conf->get("allow-adding");
        $this->limit = $conf->get("limit");

        if($conf->get("MySQL-host") != null or $conf->get("MySQL-port") != false or $conf->get("MySQL-user") != ""){
            $this->provider = new MySQLProvider($this, $conf->get("MySQL-host"), $conf->get("MySQL-port"), $conf->get("MySQL-user"), $conf->get("MySQL-password"), $conf->get("MySQL-database"));
        }

        $this->chestconfig = (new Config($this->getDataFolder()."chests.yml"));
        $this->getServer()->getLogger()->info("MyLock enabled");
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onDisable(){
        $this->getServer()->getLogger()->info("MyLock disabled");
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args){
        if($command->getName() == "mylock"){
            switch($args[0]){
                case "lock":
                if($this->provider->lockedChests($sender) < $this->limit){
                    $sender->sendMessage("§7[§cMyLock§7] §bPlease touch the chest that you would like to lock within the following §c15 seconds§b.");
                    $this->locktemp[$sender->getName()] = array("action"=>"lock","time"=>time());
                }else{
                    $sender->sendMessage("§7[§cMyLock§7] §bPlease §cunlock§b one of your chests to lock a new one.");
                }
                break;
                case "unlock":
                $sender->sendMessage("§7[§cMyLock§7] §bPlease touch the chest that you would like to unlock within the following §c15 seconds§b.");
                $this->locktemp[$sender->getName()] = array("action"=>"unlock","time"=>time());
                break;
            }
        }
    }

    /**
     * @param PlayerInteractEvent $event
     *
     * @priority MONITOR
     * @ignoreCancelled false
     */
    public function PlayerInteractEvent(PlayerInteractEvent $event){
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $action = $event->getAction();
        $coords = [$block->getFloorX(), $block->getFloorY(), $block->getFloorZ()];
        $this->getLogger()->info($action);
        if($block instanceof Chest){
            $chest = $this->provider->getChestDetails(new Position($coords[0],$coords[1],$coords[2],$block->getLevel()));
            if($chest !== null){
                if(isset($this->locktemp[$player->getName()])){
                    if($this->locktemp[$player->getName()]['action'] == "lock"){
                        if((time() - $this->locktemp[$player->getName()]['time']) <= 15){
                            $player->sendMessage("§7[§cMyLock§7] §bThis chest is already §clocked§b!");
                            $event->setCancelled();
                            return;
                        }else unset($this->locktemp[$player->getName()]);$event->setCancelled();
                    }else
                    if($this->locktemp[$player->getName()]['action'] == "unlock"){
                        if((time() - $this->locktemp[$player->getName()]['time']) <= 15){
                            if($chest['user'] === trim(strtolower($player->getName()))){
                                $this->provider->deleteChest(new Position($coords[0],$coords[1],$coords[2],$block->getLevel()));
                                $player->sendMessage("§7[§cMyLock§7] §bYou have unlocked your chest.");
                                $event->setCancelled();
                            }else $player->sendMessage("§7[§cMyLock§7] §bYou are not the owner of this chest.");$event->setCancelled();
                        }else unset($this->locktemp[$player->getName()]);$event->setCancelled();
                    }
                }
                $owner = $chest["user"];
                $players = [];
                foreach(explode(",",$chest["players"]) as $plyer){
                    $players[$plyer]=1;
                }
                if(trim(strtolower($player->getName())) !== $owner and !isset($players[trim(strtolower($player->getName()))])){
                    $event->setCancelled();
                    $player->sendMessage("§7[§cMyLock§7] §bThis chest is §clocked§b!");
                }
            }else{
                if(isset($this->locktemp[$player->getName()])){
                    if($this->locktemp[$player->getName()]['action'] == "lock"){
                        if((time() - $this->locktemp[$player->getName()]['time']) <= 15){
                            if($this->provider->lockedChests($player) < $this->limit){
                                $this->provider->registerChest($player, new Position($coords[0],$coords[1],$coords[2],$block->getLevel()));
                                $player->sendMessage("§7[§cMyLock§7] §bYou have §clocked§b your chest.");
                                $event->setCancelled();
                            }else $player->sendMessage("§7[§cMyLock§7] §bPlease §cunlock§b one of your chests to lock a new one.");
                        }else unset($this->locktemp[$player->getName()]);$event->setCancelled();
                    }else
                    if($this->locktemp[$player->getName()]['action'] == "unlock"){
                        if((time() - $this->locktemp[$player->getName()]['time']) <= 15){
                            $player->sendMessage("§7[§cMyLock§7] §bYou cannot unlock a chest that isn't locked.");
                            $event->setCancelled();
                        }else unset($this->locktemp[$player->getName()]);$event->setCancelled();
                    }
                }
            }
        }
    }

    /**
     * @param BlockPlaceEvent $event
     *
     * @priority MONITOR
     * @ignoreCancelled false
     */
    public function BlockPlaceEvent(BlockPlaceEvent $event){
        if($this->autolock){
            $player = $event->getPlayer();
            $block = $event->getBlock();
            $coords = [$block->getFloorX(), $block->getFloorY(), $block->getFloorZ()];
            if($block instanceof Chest){
                if($player->hasPermission("mychest.limit.*") or $this->provider->lockedChests($player) < $this->limit){
                    $this->provider->registerChest($player, new Position($coords[0],$coords[1],$coords[2],$block->getLevel()));
                    $player->sendMessage("§7[§cMyLock§7] §bYour chest has been §clocked§b!\n§7[§cMyLock§7] §bUse /mychest add <player> to grant access to a player for this chest.");
                }else{
                    $player->sendMessage("§7[§cMyLock§7] §bYou cannot lock anymore chests.\n§7[§cMyLock§7] §bUnlock some other chests to be able to lock new ones.");
                }
            }
        }
    }

    /**
     * @param BlockBreakEvent $event
     *
     * @priority MONITOR
     * @ignoreCancelled false
     */
    public function BlockBreakEvent(BlockBreakEvent $event){
        $player = $event->getPlayer();
        $block = $event->getBlock();
        $coords = [$block->getFloorX(), $block->getFloorY(), $block->getFloorZ()];
        if($block instanceof Chest){
            $chest = $this->provider->getChestDetails(new Position($coords[0],$coords[1],$coords[2],$block->getLevel()));
            if($chest !== null){
                $owner = $chest["user"];
                $players = explode(",",$chest["players"]);
                if(in_array(trim(strtolower($player->getName())),$players)){
                    $event->setCancelled();
                    $player->sendMessage("§7[§cMyLock§7] §bYou have access to this chest but you cannot break it!");
                }else
                if(trim(strtolower($player->getName())) !== $owner){
                    $event->setCancelled();
                    $player->sendMessage("§7[§cMyLock§7] §bThis chest is belongs to someone else!");
                }else{
                    $this->provider->deleteChest(new Position($coords[0],$coords[1],$coords[2],$block->getLevel()));
                    $player->sendMessage("§7[§cMyLock§7] §bYou have broke one of your locked chests.\n§7[§cMyLock§7] §bIt has now been unlocked and removed.");
                }
            }
        }
    }
}

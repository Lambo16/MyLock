<?php

namespace Lambo\MyLock\providers;

use pocketmine\Player;
use pocketmine\level\Position;
use pocketmine\level\Level;

class MySQLProvider{
	
	protected $connection;

	private $plugin;

	public function __construct($plugin, $host, $port, $user, $password, $database){
		$this->plugin = $plugin;
		$this->connection = new \mysqli($host, $user, $password, $database, $port);
		if($this->connection->connect_error){
			$this->plugin->getLogger()->info("Cannot connect to MySQL database. Error: ".$this->connection->connect_error);
		}else{
			$this->plugin->getLogger()->info("Successfully connected to MySQL database.");
			$this->connection->query("CREATE TABLE IF NOT EXISTS MyLock (
			user VARCHAR(32),
			x INT,
			y INT,
			z INT,
			level VARCHAR(32),
			players VARCHAR(270)
			);");
		}
	}

	public function lockedChests(Player $player){
		$query = $this->connection->query("SELECT * FROM MyLock WHERE user = '".trim(strtolower($player->getName()))."';");
		return $query->num_rows;
	}

	public function registerChest(Player $player, Position $pos){
		$this->plugin->getLogger()->info("register");
		$this->plugin->getLogger()->info("INSERT INTO MyLock
			VALUES('".trim(strtolower($player->getName()))."', ".$pos->getFloorX().", ".$pos->getFloorY().", ".$pos->getFloorZ().", '".$pos->getLevel()->getName()."');");
		$query = $this->connection->query("INSERT INTO MyLock
			VALUES('".trim(strtolower($player->getName()))."', ".$pos->getFloorX().", ".$pos->getFloorY().", ".$pos->getFloorZ().", '".$pos->getLevel()->getName()."','');");
		return true;
	}

	public function deleteChest(Position $pos){
		$query = $this->connection->query("DELETE FROM MyLock WHERE x = ".$pos->getFloorX()." AND y = ".$pos->getFloorY()." AND z = ".$pos->getFloorZ().";");
		return true;
	}

	public function addPlayerToChest(Position $pos, Player $player){
		//if(count(explode(',',$this->getChestDetails($pos)['players'])) < 8)
		$newplayers = $this->getChestDetails($pos)['players'].",".trim(strtolower($player->getName()));
		$query = $this->connection->query("UPDATE MyLock SET players = '".$newplayers."' WHERE x = ".$pos->getFloorX()." AND y = ".$pos->getFloorY()." AND z = ".$pos->getFloorZ().";");
		return true;
	}

	public function removePlayerFromChest(Position $pos, $player){
		//if(count(explode(',',$this->getChestDetails($pos)['players'])) < 8)
		$newplayers = str_replace(trim(strtolower($player)), "", $this->getChestDetails($pos)['players']);
		$query = $this->connection->query("UPDATE MyLock SET players = '".$newplayers."' WHERE x = ".$pos->getFloorX()." AND y = ".$pos->getFloorY()." AND z = ".$pos->getFloorZ().";");
		return true;
	}

	public function getChestDetails(Position $pos){
		$query = $this->connection->query("SELECT * FROM MyLock WHERE x = ".$pos->getFloorX()." AND y = ".$pos->getFloorY()." AND z = ".$pos->getFloorZ().";");
		if($query->num_rows > 0){
			return $query->fetch_assoc();
		}
		return null;
	}
}

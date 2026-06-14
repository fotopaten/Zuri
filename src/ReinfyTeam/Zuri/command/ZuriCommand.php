<?php

declare(strict_types=1);

namespace ReinfyTeam\Zuri\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use ReinfyTeam\Zuri\ZuriAC;

class ZuriCommand extends Command {
	public function __construct() {
		parent::__construct("zuri", "Zuri main command.", "/zuri <help|version|reload>");
		$this->setPermission("zuri.command");
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool {
		if (!$this->testPermissionSilent($sender)) {
			$sender->sendMessage("No tienes permiso para usar este comando.");
			return true;
		}

		$sub = $args[0] ?? "help";

		switch (strtolower($sub)) {
			case "version":
				$sender->sendMessage("Zuri version: " . ZuriAC::getInstance()->getDescription()->getVersion());
				return true;
			case "reload":
				ZuriAC::getInstance()->reloadConfig();
				$sender->sendMessage("Zuri: configuración recargada.");
				return true;
			default:
				$sender->sendMessage("Uso: /zuri <help|version|reload>");
				return true;
		}
	}
}

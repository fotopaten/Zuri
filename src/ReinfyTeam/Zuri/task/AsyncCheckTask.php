<?php

/*
 *
 *  ____           _            __           _____
 * |  _ \    ___  (_)  _ __    / _|  _   _  |_   _|   ___    __ _   _ __ ___
 * | |_) |  / _ \ | | | '_ \  | |_  | | | |   | |    / _ \  / _` | | '_ ` _ \
 * |  _ <  |  __/ | | | | | | |  _| | |_| |   | |   |  __/ | (_| | | | | | | |
 * |_| \_\  \___| |_| |_| |_| |_|    \__, |   |_|    \___|  \__,_| |_| |_| |_|
 *                                   |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Zuri attempts to enforce "vanilla Minecraft" mechanics, as well as preventing
 * players from abusing weaknesses in Minecraft or its protocol, making your server
 * more safe. Organized in different sections, various checks are performed to test
 * players doing, covering a wide range including flying and speeding, fighting
 * hacks, fast block breaking and nukers, inventory hacks, chat spam and other types
 * of malicious behaviour.
 *
 * @author ReinfyTeam
 * @link https://github.com/ReinfyTeam/
 *
 *
 */

declare(strict_types=1);

namespace ReinfyTeam\Zuri\task;

use pocketmine\scheduler\AsyncTask;
use ReinfyTeam\Zuri\check\ResultsHandler;
use ReinfyTeam\Zuri\player\PlayerManager;
use ReinfyTeam\Zuri\ZuriAC;
use function serialize;
use function unserialize;

/**
 * Async task that executes batches of checks off the main thread.
 */
class AsyncCheckTask extends AsyncTask {
	/** @var string Serialized batch payload. */
	private $batchCheck;

	/**
	 * @param array $batchCheck Array of serialized check payloads.
	 */
	public function __construct(array $batchCheck) {
		$checkBatch = [];

		// Serialize constants once per task instead of per item
		$constantsSerialized = serialize(ZuriAC::getConstants()->export());

		foreach ($batchCheck as $data) {
			$checkData = $data["checkData"];

			$playerObj = PlayerManager::get($checkData["player"]);
			$player = $playerObj !== null ? $playerObj->jsonSerialize() : null;

			// Use the check class name instead of serializing the whole object
			$checkClass = is_object($data["check"]) ? get_class($data["check"]) : (string) $data["check"];

			$extraData = $checkData["data"] ?? null;

			$checkBatch[] = [
				"type" => $checkData["type"],
				"player" => $player,
				"checkClass" => $checkClass,
				"data" => $extraData,
				"constants" => $constantsSerialized
			];
		}

		$this->batchCheck = serialize($checkBatch);
	}

	/**
	 * Executes the checks in the async worker and collects results.
	 */
	public function onRun() : void {
		$results = [];

		$batch = unserialize($this->batchCheck);
		foreach ($batch as $checkData) {
			$checkClass = $checkData["checkClass"];
			$type = $checkData["type"];

			$constants = unserialize($checkData["constants"]);

			$playerData = $checkData["player"] ?? null;
			$data = $checkData["data"] ?? null;

			$result = [];
			$result["result"] = $checkClass::check([
				"type" => $type,
				"data" => $data,
				"playerData" => $playerData,
				"constantData" => $constants
			]);

			$result["checkClass"] = $checkClass;
			$result["player"] = $playerData["name"] ?? null;

			$results[] = $result;
		}

		$this->setResult(serialize($results));
	}

	/**
	 * Called on the main thread when the async task completes.
	 * Processes results and delegates handling to ResultsHandler.
	 */
	public function onCompletion() : void {
		$results = unserialize($this->getResult());
		foreach ($results as $result) {
			ResultsHandler::handle($result);
		}
	}
}
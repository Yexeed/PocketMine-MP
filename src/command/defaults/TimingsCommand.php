<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\errorhandler\ErrorToExceptionHandler;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\Player;
use pocketmine\scheduler\BulkCurlTask;
use pocketmine\scheduler\BulkCurlTaskOperation;
use pocketmine\timings\TimingsHandler;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\InternetException;
use pocketmine\utils\InternetRequestResult;
use pocketmine\YmlServerProperties;
use Symfony\Component\Filesystem\Path;
use function count;
use function fclose;
use function file_exists;
use function fopen;
use function fwrite;
use function http_build_query;
use function implode;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function mkdir;
use function strtolower;
use const CURLOPT_AUTOREFERER;
use const CURLOPT_FOLLOWLOCATION;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_POST;
use const CURLOPT_POSTFIELDS;
use const PHP_EOL;

class TimingsCommand extends VanillaCommand{

	public function __construct(){
		parent::__construct(
			"timings",
			KnownTranslationFactory::pocketmine_command_timings_description(),
			KnownTranslationFactory::pocketmine_command_timings_usage()
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_TIMINGS);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(count($args) !== 1){
			throw new InvalidCommandSyntaxException();
		}

		$mode = strtolower($args[0]);

		if($mode === "on"){
			if(TimingsHandler::isEnabled()){
				$sender->sendMessage(KnownTranslationFactory::pocketmine_command_timings_alreadyEnabled());
				return true;
			}
			TimingsHandler::setEnabled();
			Command::broadcastCommandMessage($sender, KnownTranslationFactory::pocketmine_command_timings_enable());

			return true;
		}elseif($mode === "off"){
			TimingsHandler::setEnabled(false);
			Command::broadcastCommandMessage($sender, KnownTranslationFactory::pocketmine_command_timings_disable());
			return true;
		}

		if(!TimingsHandler::isEnabled()){
			$sender->sendMessage(KnownTranslationFactory::pocketmine_command_timings_timingsDisabled());

			return true;
		}

		$paste = $mode === "paste";

		if($mode === "reset"){
			TimingsHandler::reload();
			Command::broadcastCommandMessage($sender, KnownTranslationFactory::pocketmine_command_timings_reset());
		}elseif($mode === "merged" || $mode === "report" || $paste){
			$timingsPromise = TimingsHandler::requestPrintTimings();
			Command::broadcastCommandMessage($sender, KnownTranslationFactory::pocketmine_command_timings_collect());
			$timingsPromise->onCompletion(
				fn(array $lines) => $paste ? $this->uploadReport($lines, $sender) : $this->createReportFile($lines, $sender),
				fn() => throw new AssumptionFailedError("This promise is not expected to be rejected")
			);
		}else{
			throw new InvalidCommandSyntaxException();
		}

		return true;
	}

	/**
	 * @param string[] $lines
	 * @phpstan-param list<string> $lines
	 */
	private function createReportFile(array $lines, CommandSender $sender) : void{
		$index = 0;
		$timingFolder = Path::join($sender->getServer()->getDataPath(), "timings");

		if(!file_exists($timingFolder)){
			mkdir($timingFolder, 0777);
		}
		$timings = Path::join($timingFolder, "timings.txt");
		while(file_exists($timings)){
			$timings = Path::join($timingFolder, "timings" . (++$index) . ".txt");
		}

		$fileTimings = ErrorToExceptionHandler::trapAndRemoveFalse(fn() => fopen($timings, "a+b"));
		foreach($lines as $line){
			fwrite($fileTimings, $line . PHP_EOL);
		}
		fclose($fileTimings);

		Command::broadcastCommandMessage($sender, KnownTranslationFactory::pocketmine_command_timings_timingsWrite($timings));
	}

	/**
	 * @param string[] $lines
	 * @phpstan-param list<string> $lines
	 */
	private function uploadReport(array $lines, CommandSender $sender) : void{
		$data = [
			"browser" => $agent = $sender->getServer()->getName() . " " . $sender->getServer()->getPocketMineVersion(),
			"data" => implode("\n", $lines)
		];

		$host = $sender->getServer()->getConfigGroup()->getPropertyString(YmlServerProperties::TIMINGS_HOST, "timings.pmmp.io");

		$sender->getServer()->getAsyncPool()->submitTask(new BulkCurlTask(
			[new BulkCurlTaskOperation(
				"https://$host?upload=true",
				10,
				[],
				[
					CURLOPT_HTTPHEADER => [
						"User-Agent: $agent",
						"Content-Type: application/x-www-form-urlencoded"
					],
					CURLOPT_POST => true,
					CURLOPT_POSTFIELDS => http_build_query($data),
					CURLOPT_AUTOREFERER => false,
					CURLOPT_FOLLOWLOCATION => false
				]
			)],
			function(array $results) use ($sender, $host) : void{
				/** @phpstan-var array<InternetRequestResult|InternetException> $results */
				if($sender instanceof Player && !$sender->isOnline()){ // TODO replace with a more generic API method for checking availability of CommandSender
					return;
				}
				$result = $results[0];
				if($result instanceof InternetException){
					$sender->getServer()->getLogger()->logException($result);
					return;
				}
				$response = json_decode($result->getBody(), true);
				if(is_array($response) && isset($response["id"]) && (is_int($response["id"]) || is_string($response["id"]))){
					Command::broadcastCommandMessage($sender, KnownTranslationFactory::pocketmine_command_timings_timingsRead(
						"https://" . $host . "/?id=" . $response["id"]));
				}else{
					$sender->getServer()->getLogger()->debug("Invalid response from timings server (" . $result->getCode() . "): " . $result->getBody());
					Command::broadcastCommandMessage($sender, KnownTranslationFactory::pocketmine_command_timings_pasteError());
				}
			}
		));
	}
}

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

namespace pocketmine\block\inventory\window;

use pocketmine\block\tile\EnderChest;
use pocketmine\network\mcpe\protocol\BlockEventPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\world\Position;
use pocketmine\world\sound\EnderChestCloseSound;
use pocketmine\world\sound\EnderChestOpenSound;
use pocketmine\world\sound\Sound;

final class EnderChestInventoryWindow extends AnimatedBlockInventoryWindow{

	protected function getViewerCount() : int{
		$enderChest = $this->holder->getWorld()->getTile($this->getHolder());
		if(!$enderChest instanceof EnderChest){
			return 0;
		}
		return $enderChest->getViewerCount();
	}

	private function updateViewerCount(int $amount) : void{
		$enderChest = $this->holder->getWorld()->getTile($this->getHolder());
		if($enderChest instanceof EnderChest){
			$enderChest->setViewerCount($enderChest->getViewerCount() + $amount);
		}
	}

	protected function getOpenSound() : Sound{
		return new EnderChestOpenSound();
	}

	protected function getCloseSound() : Sound{
		return new EnderChestCloseSound();
	}

	protected function animateBlock(Position $position, bool $isOpen) : void{
		//event ID is always 1 for a chest
		$position->getWorld()->broadcastPacketToViewers($position, BlockEventPacket::create(BlockPosition::fromVector3($position), 1, $isOpen ? 1 : 0));
	}

	public function onOpen() : void{
		parent::onOpen();
		$this->updateViewerCount(1);
	}

	public function onClose() : void{
		parent::onClose();
		$this->updateViewerCount(-1);
	}
}

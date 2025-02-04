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

namespace pocketmine\block;

use pocketmine\block\inventory\window\BlockInventoryWindow;
use pocketmine\block\tile\EnderChest as TileEnderChest;
use pocketmine\block\utils\AnimatedContainer;
use pocketmine\block\utils\AnimatedContainerTrait;
use pocketmine\block\utils\FacesOppositePlacingPlayerTrait;
use pocketmine\block\utils\SupportType;
use pocketmine\item\Item;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Facing;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\BlockEventPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\world\sound\EnderChestCloseSound;
use pocketmine\world\sound\EnderChestOpenSound;
use pocketmine\world\sound\Sound;

class EnderChest extends Transparent implements AnimatedContainer{
	use AnimatedContainerTrait {
		onContainerOpen as private traitOnContainerOpen;
		onContainerClose as private traitOnContainerClose;
	}
	use FacesOppositePlacingPlayerTrait;

	public function getLightLevel() : int{
		return 7;
	}

	protected function recalculateCollisionBoxes() : array{
		//these are slightly bigger than in PC
		return [AxisAlignedBB::one()->contract(0.025, 0, 0.025)->trim(Facing::UP, 0.05)];
	}

	public function getSupportType(int $facing) : SupportType{
		return SupportType::NONE;
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool{
		if($player instanceof Player){
			$enderChest = $this->position->getWorld()->getTile($this->position);
			if($enderChest instanceof TileEnderChest && $this->getSide(Facing::UP)->isTransparent()){
				$player->setCurrentWindow(new BlockInventoryWindow($player, $player->getEnderInventory(), $this));
			}
		}

		return true;
	}

	public function getDropsForCompatibleTool(Item $item) : array{
		return [
			VanillaBlocks::OBSIDIAN()->asItem()->setCount(8)
		];
	}

	public function isAffectedBySilkTouch() : bool{
		return true;
	}

	protected function getContainerViewerCount() : int{
		$enderChest = $this->position->getWorld()->getTile($this->position);
		if(!$enderChest instanceof TileEnderChest){
			return 0;
		}
		return $enderChest->getViewerCount();
	}

	private function updateContainerViewerCount(int $amount) : void{
		$enderChest = $this->position->getWorld()->getTile($this->position);
		if($enderChest instanceof TileEnderChest){
			$enderChest->setViewerCount($enderChest->getViewerCount() + $amount);
		}
	}

	protected function getContainerOpenSound() : Sound{
		return new EnderChestOpenSound();
	}

	protected function getContainerCloseSound() : Sound{
		return new EnderChestCloseSound();
	}

	protected function doContainerAnimation(Position $position, bool $isOpen) : void{
		//event ID is always 1 for a chest
		//TODO: we probably shouldn't be sending a packet directly here, but it doesn't fit anywhere into existing systems
		$position->getWorld()->broadcastPacketToViewers($position, BlockEventPacket::create(BlockPosition::fromVector3($position), 1, $isOpen ? 1 : 0));
	}

	public function onContainerOpen() : void{
		$this->updateContainerViewerCount(1);
		$this->traitOnContainerOpen();
	}

	public function onContainerClose() : void{
		$this->traitOnContainerClose();
		$this->updateContainerViewerCount(-1);
	}
}

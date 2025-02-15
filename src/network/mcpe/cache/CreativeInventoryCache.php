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

namespace pocketmine\network\mcpe\cache;

use pocketmine\inventory\CreativeCategory;
use pocketmine\inventory\CreativeInventory;
use pocketmine\inventory\data\CreativeGroup;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\CreativeContentPacket;
use pocketmine\network\mcpe\protocol\types\inventory\CreativeGroupEntry;
use pocketmine\network\mcpe\protocol\types\inventory\CreativeItemEntry;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\utils\SingletonTrait;
use function spl_object_id;

final class CreativeInventoryCache{
	use SingletonTrait;

	/**
	 * @var CreativeContentPacket[]
	 * @phpstan-var array<int, CreativeContentPacket>
	 */
	private array $caches = [];

	public function getCache(CreativeInventory $inventory) : CreativeContentPacket{
		$id = spl_object_id($inventory);
		if(!isset($this->caches[$id])){
			$inventory->getDestructorCallbacks()->add(function() use ($id) : void{
				unset($this->caches[$id]);
			});
			$inventory->getContentChangedCallbacks()->add(function() use ($id) : void{
				unset($this->caches[$id]);
			});
			$this->caches[$id] = $this->buildCreativeInventoryCache($inventory);
		}
		return $this->caches[$id];
	}

	/**
	 * Rebuild the cache for the given inventory.
	 */
	private function buildCreativeInventoryCache(CreativeInventory $inventory) : CreativeContentPacket{
		/** @var CreativeGroupEntry[] $groups */
		$groups = [];
		/** @var CreativeItemEntry[] $items */
		$items = [];

		$typeConverter = TypeConverter::getInstance();

		$index = 0;
		$mappedGroups = array_reduce($inventory->getItemGroup(), function (array $carry, CreativeGroup $group) use ($typeConverter, &$index, &$groups) : array{
			if (!isset($carry[$id = spl_object_id($group)])) {
				$carry[$id] = $index++;

				$categoryId = match($group->getCategoryId()){
					CreativeCategory::CONSTRUCTION => CreativeContentPacket::CATEGORY_CONSTRUCTION,
					CreativeCategory::NATURE => CreativeContentPacket::CATEGORY_NATURE,
					CreativeCategory::EQUIPMENT => CreativeContentPacket::CATEGORY_EQUIPMENT,
					CreativeCategory::ITEMS => CreativeContentPacket::CATEGORY_ITEMS
				};

				$groupIcon = $group->getIcon();
				$groups[] = new CreativeGroupEntry(
					$categoryId,
					$group->getName(),
					$groupIcon === null ? ItemStack::null() : $typeConverter->coreItemStackToNet($groupIcon)
				);
			}
			return $carry;
		}, []);

		//creative inventory may have holes if items were unregistered - ensure network IDs used are always consistent
		foreach($inventory->getAll() as $k => $item){
			$items[] = new CreativeItemEntry(
				$k,
				$typeConverter->coreItemStackToNet($item),
				$mappedGroups[spl_object_id($inventory->getGroup($k))]
			);
		}

		return CreativeContentPacket::create($groups, $items);
	}
}

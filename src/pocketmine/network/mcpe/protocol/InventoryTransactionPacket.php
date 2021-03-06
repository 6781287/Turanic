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

namespace pocketmine\network\mcpe\protocol;

#include <rules/DataPacket.h>

use pocketmine\inventory\CraftingGrid;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\transaction\action\CreativeInventoryAction;
use pocketmine\inventory\transaction\action\DropItemAction;
use pocketmine\inventory\transaction\action\InventoryAction;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\network\mcpe\protocol\types\ContainerIds;

class InventoryTransactionPacket extends DataPacket{
    const NETWORK_ID = ProtocolInfo::INVENTORY_TRANSACTION_PACKET;

    const TYPE_NORMAL = 0;
    const TYPE_MISMATCH = 1;
    const TYPE_USE_ITEM = 2;
    const TYPE_USE_ITEM_ON_ENTITY = 3;
    const TYPE_RELEASE_ITEM = 4;

    const USE_ITEM_ACTION_CLICK_BLOCK = 0;
    const USE_ITEM_ACTION_CLICK_AIR = 1;
    const USE_ITEM_ACTION_BREAK_BLOCK = 2;

    const RELEASE_ITEM_ACTION_RELEASE = 0; //bow shoot
    const RELEASE_ITEM_ACTION_CONSUME = 1; //eat food, drink potion

    const USE_ITEM_ON_ENTITY_ACTION_INTERACT = 0;
    const USE_ITEM_ON_ENTITY_ACTION_ATTACK = 1;

    const SOURCE_CONTAINER = 0;

    const SOURCE_WORLD = 2; //drop/pickup item entity
    const SOURCE_CREATIVE = 3;

    const SOURCE_CRAFT = 99999;

    /**
     * These identifiers are used for special slot types for transaction/inventory types that are not yet implemented.
     * Expect these to change in the future.
     */
    const SOURCE_TYPE_CRAFTING_ADD_INGREDIENT = -2;
    const SOURCE_TYPE_CRAFTING_REMOVE_INGREDIENT = -3;
    const SOURCE_TYPE_CRAFTING_RESULT = -4;
    const SOURCE_TYPE_CRAFTING_USE_INGREDIENT = -5;

    const SOURCE_TYPE_ANVIL_INPUT = -10;
    const SOURCE_TYPE_ANVIL_MATERIAL = -11;
    const SOURCE_TYPE_ANVIL_RESULT = -12;
    const SOURCE_TYPE_ANVIL_OUTPUT = -13;

    const SOURCE_TYPE_ENCHANT_INPUT = -15;
    const SOURCE_TYPE_ENCHANT_MATERIAL = -16;
    const SOURCE_TYPE_ENCHANT_OUTPUT = -17;

    const SOURCE_TYPE_TRADING_INPUT_1 = -20;
    const SOURCE_TYPE_TRADING_INPUT_2 = -21;
    const SOURCE_TYPE_TRADING_USE_INPUTS = -22;
    const SOURCE_TYPE_TRADING_OUTPUT = -23;

    const SOURCE_TYPE_BEACON = -24;

    const SOURCE_TYPE_CONTAINER_DROP_CONTENTS = -100;


    const ACTION_MAGIC_SLOT_DROP_ITEM = 0;
    const ACTION_MAGIC_SLOT_PICKUP_ITEM = 1;

    const ACTION_MAGIC_SLOT_CREATIVE_DELETE_ITEM = 0;
    const ACTION_MAGIC_SLOT_CREATIVE_CREATE_ITEM = 1;

    const ACTION_CRAFT_PUT_SLOT = 3;
    const ACTION_CRAFT_GET_SLOT = 5;
    const ACTION_CRAFT_GET_RESULT = 7;
    const ACTION_CRAFT_USE = 9;

    /** @var InventoryAction[] */
    public $actions = [];

    /** @var \stdClass */
    public $trData;

    public $transactionType;

    protected function decodePayload(){
        $type = $this->getUnsignedVarInt();

        $actionCount = $this->getUnsignedVarInt();
        for($i = 0; $i < $actionCount; ++$i){
            $this->actions[] = $this->getActions();
        }

        $this->trData = new \stdClass();
        $this->transactionType = $type;

        switch($type){
            case self::TYPE_NORMAL:
            case self::TYPE_MISMATCH:
                //Regular ComplexInventoryTransaction doesn't read any extra data
                break;
            case self::TYPE_USE_ITEM:
                $this->trData->actionType = $this->getUnsignedVarInt();
                $this->getBlockPosition($this->trData->x, $this->trData->y, $this->trData->z);
                $this->trData->face = $this->getVarInt();
                $this->trData->hotbarSlot = $this->getVarInt();
                $this->trData->itemInHand = $this->getSlot();
                $this->trData->playerPos = $this->getVector3Obj();
                $this->trData->clickPos = $this->getVector3Obj();
                break;
            case self::TYPE_USE_ITEM_ON_ENTITY:
                $this->trData->entityRuntimeId = $this->getEntityRuntimeId();
                $this->trData->actionType = $this->getUnsignedVarInt();
                $this->trData->hotbarSlot = $this->getVarInt();
                $this->trData->itemInHand = $this->getSlot();
                $this->trData->vector1 = $this->getVector3Obj();
                $this->trData->vector2 = $this->getVector3Obj();
                break;
            case self::TYPE_RELEASE_ITEM:
                $this->trData->actionType = $this->getUnsignedVarInt();
                $this->trData->hotbarSlot = $this->getVarInt();
                $this->trData->itemInHand = $this->getSlot();
                $this->trData->headPos = $this->getVector3Obj();
                break;
            default:
                throw new \UnexpectedValueException("Unknown transaction type $type");
        }
    }

    protected function encodePayload(){
        //TODO
    }

    protected function getActions(){
        $sourceType = $this->getUnsignedVarInt();
        $containerId = ContainerIds::NONE;
        $unknown = 0;
        $action = -1;

        switch($sourceType){
            case self::SOURCE_CONTAINER:
                $containerId = $this->getVarInt();
                break;
            case self::SOURCE_WORLD:
                $unknown = $this->getUnsignedVarInt();
                break;
            case self::SOURCE_CREATIVE:
                break;
            case self::SOURCE_CRAFT:
                $action = $this->getVarInt();
                break;
            default:
                throw new \UnexpectedValueException("Unexpected inventory source type $sourceType");
        }

        $inventorySlot = $this->getUnsignedVarInt();
        $sourceItem = $this->getSlot();
        $targetItem = $this->getSlot();

        switch($sourceType){
            case self::SOURCE_CONTAINER:
                if($containerId === ContainerIds::ARMOR){
                    //TODO: HACK!
                    $inventorySlot += 36;
                    $containerId = ContainerIds::INVENTORY;
                }
                if($containerId === ContainerIds::CURSOR){
                    $inventorySlot = PlayerInventory::CURSOR_INDEX;
                    $containerId = ContainerIds::INVENTORY;
                }
                return new SlotChangeAction($sourceItem, $targetItem, $containerId, $inventorySlot);
            case self::SOURCE_WORLD:
                if($inventorySlot !== self::ACTION_MAGIC_SLOT_DROP_ITEM){
                    throw new \UnexpectedValueException("Only expect drop item world action types from client");
                }

                return new DropItemAction($sourceItem, $targetItem);
            case self::SOURCE_CREATIVE:
                if($inventorySlot === self::ACTION_MAGIC_SLOT_CREATIVE_DELETE_ITEM){
                    return new CreativeInventoryAction($sourceItem, $targetItem, CreativeInventoryAction::TYPE_DELETE_ITEM);
                }elseif($inventorySlot === self::ACTION_MAGIC_SLOT_CREATIVE_CREATE_ITEM){
                    return new CreativeInventoryAction($sourceItem, $targetItem, CreativeInventoryAction::TYPE_CREATE_ITEM);
                }else{
                    throw new \UnexpectedValueException("Unknown creative inventory action type $inventorySlot");
                }
            case self::SOURCE_CRAFT:
                switch($action){
                    case self::SOURCE_TYPE_CRAFTING_RESULT:
                        $slot = CraftingGrid::RESULT_INDEX;
                        break;
                    default:
                        $slot = $inventorySlot;
                        break;
                }
                return new SlotChangeAction($sourceItem, $targetItem, CraftingGrid::WINDOW_ID, $slot);
            default:
                throw new \UnexpectedValueException("Unknown source type $sourceType");
        }
    }
}
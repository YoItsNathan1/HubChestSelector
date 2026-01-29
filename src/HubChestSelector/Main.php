<?php

declare(strict_types=1);

namespace HubChestSelector;

use muqsit\invmenu\InvMenu;
use muqsit\invmenu\InvMenuHandler;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\ItemFactory;
use pocketmine\item\Item;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;

final class Main extends PluginBase{

    protected function onEnable() : void{
        $this->saveDefaultConfig();

        if(!InvMenuHandler::isRegistered()){
            InvMenuHandler::register($this);
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
        if($command->getName() === "hub"){
            if(!$sender instanceof Player){
                $sender->sendMessage("Run this in-game.");
                return true;
            }
            $this->openMainMenu($sender);
            return true;
        }
        return false;
    }

    private function openMainMenu(Player $player) : void{
        $menu = InvMenu::create(InvMenu::TYPE_CHEST);
        $menu->setName("§l§bSelector");

        $inv = $menu->getInventory();
        $inv->clearAll();

        $inv->setItem(10, $this->namedItem($this->item("minecraft:player_head"), "§bProfile", ["§7Coming soon"]));
        $inv->setItem(12, $this->namedItem($this->item("minecraft:book"), "§dFriends", ["§7Coming soon"]));
        $inv->setItem(14, $this->namedItem($this->item("minecraft:name_tag"), "§eParties", ["§7Coming soon"]));
        $inv->setItem(16, $this->namedItem($this->item("minecraft:compass"), "§aGames", ["§7Open games menu", "", "§eClick"]));

        $menu->setListener(function(InvMenuTransaction $tx) : InvMenuTransactionResult{
            $player = $tx->getPlayer();
            $slot = $tx->getAction()->getSlot();

            if($slot === 16){
                $this->openGamesMenu($player);
            }
            // Other buttons do nothing (coming soon)

            return $tx->discard();
        });

        $menu->send($player);
    }

    private function openGamesMenu(Player $player) : void{
        $menu = InvMenu::create(InvMenu::TYPE_CHEST);
        $menu->setName("§l§aGames");

        $inv = $menu->getInventory();
        $inv->clearAll();

        $inv->setItem(11, $this->namedItem($this->item("minecraft:grass_block"), "§aSMP", ["§7Survival world", "", "§eClick to join"]));
        $inv->setItem(13, $this->namedItem($this->item("minecraft:red_bed"), "§cBedWars - Solos", ["§7Coming soon"]));
        $inv->setItem(15, $this->namedItem($this->item("minecraft:red_bed"), "§cBedWars - Duos", ["§7Coming soon"]));
        $inv->setItem(22, $this->namedItem($this->item("minecraft:arrow"), "§7Back", ["§eReturn to selector"]));

        $menu->setListener(function(InvMenuTransaction $tx) : InvMenuTransactionResult{
            $player = $tx->getPlayer();
            $slot = $tx->getAction()->getSlot();

            if($slot === 11){
                // Close the menu then run the Waterdog server command as the player.
                $player->removeCurrentWindow();

                // Default Waterdog command is /server <name>
                // Change "smp" if your backend name differs.
                $this->getServer()->dispatchCommand($player, "server smp");
            }elseif($slot === 22){
                $this->openMainMenu($player);
            }

            return $tx->discard();
        });

        $menu->send($player);
    }

    private function item(string $id) : Item{
        // Creates item from string ID in a version-tolerant way
        return ItemFactory::getInstance()->get($id);
    }

    private function namedItem(Item $item, string $name, array $lore = []) : Item{
        $item->setCustomName($name);
        if($lore !== []){
            $item->setLore($lore);
        }
        return $item;
    }
}

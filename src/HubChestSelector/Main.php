<?php

declare(strict_types=1);

namespace HubChestSelector;

use muqsit\invmenu\InvMenu;
use muqsit\invmenu\InvMenuHandler;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\inventory\transaction\InventoryTransaction;
use pocketmine\inventory\transaction\TransactionBuilder;

final class Main extends PluginBase{

    private Config $cfg;

    protected function onEnable() : void{
        $this->saveDefaultConfig();
        $this->cfg = $this->getConfig();

        // InvMenu needs to be registered once before use
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
        $title = (string)($this->cfg->getNested("menus.main.title", "Selector"));

        $menu = InvMenu::create(InvMenu::TYPE_CHEST);
        $menu->setName($title);

        $inv = $menu->getInventory();
        $inv->clearAll();

        // Slots (0-26). Feel free to rearrange.
        $inv->setItem(10, $this->namedItem(VanillaItems::PLAYER_HEAD(), "§bProfile", ["§7Coming soon"]));
        $inv->setItem(12, $this->namedItem(VanillaItems::BOOK(), "§dFriends", ["§7Coming soon"]));
        $inv->setItem(14, $this->namedItem(VanillaItems::NAME_TAG(), "§eParties", ["§7Coming soon"]));
        $inv->setItem(16, $this->namedItem(VanillaItems::COMPASS(), "§aGames", ["§7Open games menu", "", "§eClick"]));

        $menu->setListener(function(InventoryTransaction $tx) use ($player) : \muqsit\invmenu\transaction\InvMenuTransactionResult{
            $action = $tx->getActions()[0] ?? null;
            if($action instanceof SlotChangeAction){
                $slot = $action->getSlot();
                if($slot === 16){
                    // Open Games panel
                    $this->openGamesMenu($player);
                }
                // Profile/Friends/Parties do nothing (coming soon)
            }
            return \muqsit\invmenu\transaction\InvMenuTransactionResult::discard();
        });

        $menu->send($player);
    }

    private function openGamesMenu(Player $player) : void{
        $title = (string)($this->cfg->getNested("menus.games.title", "Games"));

        $menu = InvMenu::create(InvMenu::TYPE_CHEST);
        $menu->setName($title);

        $inv = $menu->getInventory();
        $inv->clearAll();

        // SMP
        $inv->setItem(11, $this->namedItem(
            VanillaItems::GRASS_BLOCK(),
            "§aSMP",
            ["§7Survival world", "", "§eClick to join"]
        ));

        // Coming soon BedWars
        $inv->setItem(13, $this->namedItem(
            VanillaItems::RED_BED(),
            "§cBedWars - Solos",
            ["§7Coming soon"]
        ));
        $inv->setItem(15, $this->namedItem(
            VanillaItems::RED_BED(),
            "§cBedWars - Duos",
            ["§7Coming soon"]
        ));

        // Back button
        $inv->setItem(22, $this->namedItem(
            VanillaItems::ARROW(),
            "§7Back",
            ["§eReturn to selector"]
        ));

        $menu->setListener(function(InventoryTransaction $tx) use ($player) : \muqsit\invmenu\transaction\InvMenuTransactionResult{
            $action = $tx->getActions()[0] ?? null;
            if($action instanceof SlotChangeAction){
                $slot = $action->getSlot();

                if($slot === 11){
                    // Close & transfer to SMP
                    $this->dispatchTransfer($player, (string)$this->cfg->getNested("servers.smp", "smp"));
                    $player->removeCurrentWindow();
                }elseif($slot === 22){
                    $this->openMainMenu($player);
                }
                // BedWars slots do nothing (coming soon)
            }
            return \muqsit\invmenu\transaction\InvMenuTransactionResult::discard();
        });

        $menu->send($player);
    }

    private function dispatchTransfer(Player $player, string $serverName) : void{
        $template = (string)$this->cfg->get("transfer-command", "server {server}");
        $cmd = str_replace("{server}", $serverName, $template);

        // Dispatch as the player, so it works with Waterdog's /server command
        $this->getServer()->dispatchCommand($player, $cmd);
    }

    private function namedItem(\pocketmine\item\Item $item, string $name, array $lore = []) : \pocketmine\item\Item{
        $item->setCustomName($name);
        if(!empty($lore)){
            $item->setLore($lore);
        }
        return $item;
    }
}

<?php

declare(strict_types=1);

namespace HubChestSelector;

use muqsit\invmenu\InvMenu;
use muqsit\invmenu\InvMenuHandler;
use muqsit\invmenu\transaction\InvMenuTransaction;
use muqsit\invmenu\transaction\InvMenuTransactionResult;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\EventHandler;
use pocketmine\event\EventPriority;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\Item;
use pocketmine\item\StringToItemParser;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;

final class Main extends PluginBase implements Listener{

    private Config $cfg;

    protected function onEnable() : void{
        $this->saveDefaultConfig();
        $this->cfg = $this->getConfig();

        if(!InvMenuHandler::isRegistered()){
            InvMenuHandler::register($this);
        }

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
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

    /**
     * Runs even if another plugin cancels the interact event (Navigator/hub items often do).
     */
    #[EventHandler(priority: EventPriority::HIGHEST, handleCancelled: true)]
    public function onPlayerInteract(PlayerInteractEvent $event) : void{
        if(!(bool)$this->cfg->getNested("compass-open.enabled", true)){
            return;
        }

        // Right-click detection WITHOUT relying on isRightClick()
        $action = $event->getAction();

        // Use constants if they exist; otherwise fall back to the common numeric values.
        $rcAir = defined(PlayerInteractEvent::class . "::RIGHT_CLICK_AIR") ? PlayerInteractEvent::RIGHT_CLICK_AIR : 3;
        $rcBlock = defined(PlayerInteractEvent::class . "::RIGHT_CLICK_BLOCK") ? PlayerInteractEvent::RIGHT_CLICK_BLOCK : 1;

        if($action !== $rcAir && $action !== $rcBlock){
            return;
        }

        $player = $event->getPlayer();
        $held = $event->getItem();

        $expectedId = (string)$this->cfg->getNested("compass-open.item", "minecraft:compass");
        $expectedItem = $this->item($expectedId);

        if($held->getTypeId() !== $expectedItem->getTypeId()){
            return;
        }

        $requireName = (bool)$this->cfg->getNested("compass-open.require-custom-name", false);
        if($requireName){
            $need = (string)$this->cfg->getNested("compass-open.custom-name", "§aNavigator");
            if($held->getCustomName() !== $need){
                return;
            }
        }

        // Stop other interactions and open our GUI
        $event->cancel();
        $this->openMainMenu($player);
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
                $msg = (string)$this->cfg->getNested("messages.opening-games", "§aOpening Games…");
                if($msg !== ""){
                    $player->sendMessage($msg);
                }
                $this->openGamesMenu($player);
            }elseif($slot === 10 || $slot === 12 || $slot === 14){
                $msg = (string)$this->cfg->getNested("messages.coming-soon", "§7Coming soon!");
                if($msg !== ""){
                    $player->sendMessage($msg);
                }
            }

            return $tx->discard();
        });

        $menu->send($player);
    }

    private function openGamesMenu(Player $player) : void{
        $menu = InvMenu::create(InvMenu::TYPE_CHEST);
        $menu->setName("§l§aGames");

        $inv = $menu->getInventory();
        $inv->clearAll();

        $inv->setItem(11, $this->namedItem(
            $this->item("minecraft:grass_block"),
            "§aSMP",
            ["§7Survival world", "", "§eClick to join"]
        ));

        $inv->setItem(13, $this->namedItem($this->item("minecraft:red_bed"), "§cBedWars - Solos", ["§7Coming soon"]));
        $inv->setItem(15, $this->namedItem($this->item("minecraft:red_bed"), "§cBedWars - Duos", ["§7Coming soon"]));
        $inv->setItem(22, $this->namedItem($this->item("minecraft:arrow"), "§7Back", ["§eReturn to selector"]));

        $menu->setListener(function(InvMenuTransaction $tx) : InvMenuTransactionResult{
            $player = $tx->getPlayer();
            $slot = $tx->getAction()->getSlot();

            if($slot === 11){
                $player->removeCurrentWindow();
                $server = (string)$this->cfg->getNested("servers.smp", "smp");
                $this->dispatchTransfer($player, $server);
            }elseif($slot === 13 || $slot === 15){
                $msg = (string)$this->cfg->getNested("messages.coming-soon", "§7Coming soon!");
                if($msg !== ""){
                    $player->sendMessage($msg);
                }
            }elseif($slot === 22){
                $this->openMainMenu($player);
            }

            return $tx->discard();
        });

        $menu->send($player);
    }

    private function dispatchTransfer(Player $player, string $serverName) : void{
        $template = (string)$this->cfg->get("transfer-command", "server {server}");
        $cmd = str_replace("{server}", $serverName, $template);
        $this->getServer()->dispatchCommand($player, $cmd);
    }

    private function item(string $id) : Item{
        $parser = StringToItemParser::getInstance();
        return $parser->parse($id) ?? $parser->parse("minecraft:stone");
    }

    private function namedItem(Item $item, string $name, array $lore = []) : Item{
        $item->setCustomName($name);
        if($lore !== []){
            $item->setLore($lore);
        }
        return $item;
    }
}

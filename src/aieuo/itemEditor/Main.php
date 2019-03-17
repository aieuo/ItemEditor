<?php

namespace aieuo\itemEditor;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\item\Durable;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\network\mcpe\protocol\ModalFormResponsePacket;
use pocketmine\network\mcpe\protocol\ModalFormRequestPacket;
use pocketmine\plugin\PluginBase;

class Main extends PluginBase implements Listener {

    private $forms = [];

    public function onEnable() {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool {
        if(!$command->testPermission($sender)) return true;
        $item = $sender->getInventory()->getItemInHand();
        if($item->getId() === 0) {
            $sender->sendMessage("アイテムを持っていません");
            return true;
        }
        $form = [
            "type" => "form",
            "title" => "選択",
            "content" => "§7ボタンを押してください",
            "buttons" => [
                ["text" => "名前"],
                ["text" => "説明"],
                ["text" => "メタ値"],
                ["text" => "個数"],
                ["text" => "エンチャント"],
                ["text" => "耐久値が<減る/減らない>ようにする"],
            ]
        ];
        $this->sendForm($sender, $form, [$this, "onMenu"]);
        return true;
    }

    public function onMenu($player, $data) {
        if($data === null) return;
        $item = $player->getInventory()->getItemInHand();
        switch($data) {
            case 0:
                $form = [
                    "type" => "custom_form",
                    "title" => "名前変更",
                    "content" => [
                        [
                            "type" => "input",
                            "text" => "名前",
                            "default" => $item->getName()
                        ]
                    ]
                ];
                $this->sendForm($player, $form, [$this, "onChangeName"]);
                break;
            case 1:
                $lore = $item->getLore();
                $form = [
                    "type" => "form",
                    "title" => "選択",
                    "content" => "§7ボタンを押してください",
                    "buttons" => array_map(function($str) { return ["text" => $str]; }, $lore)
                ];
                $form["buttons"][] = ["text" => "<追加する>"];
                $this->sendForm($player, $form, [$this, "onSelectLore"], $lore);
                break;
            case 2:
                $max = $item instanceof Durable ? $item->getMaxDurability() : 15;
                $form = [
                    "type" => "custom_form",
                    "title" => "メタ値変更",
                    "content" => [
                        [
                            "type" => "input",
                            "text" => "メタ値",
                            "default" => (string)$item->getDamage(),
                            "placeholder" => "0~".$max
                        ]
                    ]
                ];
                $this->sendForm($player, $form, [$this, "onChangeDamage"]);
                break;
            case 3:
                $max = $item->getMaxStackSize();
                $form = [
                    "type" => "custom_form",
                    "title" => "個数変更",
                    "content" => [
                        [
                            "type" => "input",
                            "text" => "個数",
                            "default" => (string)$item->getCount(),
                            "placeholder" => "1~".$max
                        ]
                    ]
                ];
                $this->sendForm($player, $form, [$this, "onChangeCount"]);
                break;
            case 4:
                $enchantments = $item->getEnchantments();
                $form = [
                    "type" => "form",
                    "title" => "選択",
                    "content" => "§7ボタンを押してください",
                    "buttons" => array_map(function($enchant) {
                        return ["text" => $enchant->getType()->getName().":".$enchant->getLevel()];
                    }, $enchantments)
                ];
                $form["buttons"][] = ["text" => "<追加する>"];
                $this->sendForm($player, $form, [$this, "onSelectEnchant"], $enchantments);
                break;
            case 5:
                $unbreakable = ($item instanceof Durable and $item->isUnbreakable());
                $form = [
                    "type" => "form",
                    "title" => "選択",
                    "content" => "今は: 耐久値が減りま".($unbreakable ? "せん" : "す"),
                    "buttons" => [
                        ["text" => "耐久値が減るようにする"],
                        ["text" => "耐久値が減らないようにする"]
                    ]
                ];
                $this->sendForm($player, $form, [$this, "onChangeUnbreakable"]);
                break;
        }
    }

    public function onChangeName($player, $data) {
        if($data === null) return;
        $item = $player->getInventory()->getItemInHand();
        $item->setCustomName($data[0]);
        $player->getInventory()->setItemInHand($item);
        $player->sendMessage("変更しました");
    }

    public function onSelectLore($player, $data, $lore) {
        if($data === null) return;
        $form = [
            "type" => "custom_form",
            "title" => "説明変更",
            "content" => [
                [
                    "type" => "input",
                    "text" => "説明",
                    "default" => isset($lore[$data]) ? $lore[$data] : ""
                ],
                [
                    "type" => "toggle",
                    "text" => "削除する"
                ]
            ]
        ];
        $this->sendForm($player, $form, [$this, "onChangeLore"], $lore, $data);
    }

    public function onChangeLore($player, $data, $lore, $num) {
        if($data === null) return;
        $item = $player->getInventory()->getItemInHand();
        if($data[1]) {
            unset($lore[$num]);
        } else {
            $lore[$num] = $data[0];
        }
        $item->setLore($lore);
        $player->getInventory()->setItemInHand($item);
        $player->sendMessage($data[1] ? "削除しました" : "変更しました");
    }

    public function onChangeDamage($player, $data) {
        if($data === null) return;
        $item = $player->getInventory()->getItemInHand();
        $max = $item instanceof Durable ? $item->getMaxDurability() : 15;
        $damage = (int)$data[0];
        if($damage < 0 or $max < $damage) {
            $player->sendMessage("0~".$max."の範囲で設定してください");
            return;
        }
        $item->setDamage($damage);
        $player->getInventory()->setItemInHand($item);
        $player->sendMessage("変更しました");
    }

    public function onChangeCount($player, $data) {
        if($data === null) return;
        $item = $player->getInventory()->getItemInHand();
        $max = $item->getMaxStackSize();
        $count = (int)$data[0];
        if($count < 1 or $max < $count) {
            $player->sendMessage("1~".$max."の範囲で設定してください");
            return;
        }
        $item->setCount($count);
        $player->getInventory()->setItemInHand($item);
        $player->sendMessage("変更しました");
    }

    public function onSelectEnchant($player, $data, $enchantments) {
        if($data === null) return;
        if(!isset($enchantments[$data])) {
            $form = [
                "type" => "custom_form",
                "title" => "エンチャント",
                "content" => [
                    [
                        "type" => "input",
                        "text" => "エンチャントの名前かid"
                    ],
                    [
                        "type" => "input",
                        "text" => "レベル"
                    ]
                ]
            ];
            $this->sendForm($player, $form, [$this, "onAddEnchant"]);
        } else {
            $enchant = $enchantments[$data];
            $form = [
                "type" => "custom_form",
                "title" => "エンチャント",
                "content" => [
                    [
                        "type" => "label",
                        "text" => $enchant->getType()->getName()."  id:".$enchant->getId()
                    ],
                    [
                        "type" => "input",
                        "text" => "レベル",
                        "default" => (string)$enchant->getLevel()
                    ],
                    [
                        "type" => "toggle",
                        "text" => "削除する"
                    ]
                ]
            ];
            $this->sendForm($player, $form, [$this, "onAddEnchant"], $enchant->getId());
        }
    }

    public function onAddEnchant($player, $data, $id = null) {
        if($data === null) return;
        if($id === null) $id = $data[0];
        if(is_numeric($id)) {
            $enchant = Enchantment::getEnchantment((int)$id);
        } else {
            $enchant = Enchantment::getEnchantmentByName($id);
        }
        if(!($enchant instanceof Enchantment)) {
            $player->sendMessage("エンチャントが見つかりません");
            return;
        }
        $level = (int)$data[1];
        $item = $player->getInventory()->getItemInHand();
        $item->addEnchantment(new EnchantmentInstance($enchant, $level));
        $player->getInventory()->setItemInHand($item);
        $player->sendMessage("変更しました");
    }

    public function onChangeUnbreakable($player, $data) {
        if($data === null) return;
        $item = $player->getInventory()->getItemInHand();
        if(!($item instanceof Durable)) {
            $player->sendMessage("そのアイテムには適用できません");
            return;
        }
        $item->setUnbreakable((bool)$data);
        $player->getInventory()->setItemInHand($item);
        $player->sendMessage("耐久値がへ".((bool)$data ? "らない" : "る")."ようにしました");
    }





    public function encodeJson($data){
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_BIGINT_AS_STRING | JSON_UNESCAPED_UNICODE);
        return $json;
    }

    public function sendForm($player, $form, $callable = null, ...$datas) {
        while(true) {
            $id = mt_rand(0, 999999999);
            if(!isset($this->forms[$id])) break;
        }
        $this->forms[$id] = [$callable, $datas];
        $pk = new ModalFormRequestPacket();
        $pk->formId = $id;
        $pk->formData = $this->encodeJson($form);
        $player->dataPacket($pk);
    }

    public function Receive(DataPacketReceiveEvent $event){
        $pk = $event->getPacket();
        $player = $event->getPlayer();
        if($pk instanceof ModalFormResponsePacket){
            if(isset($this->forms[$pk->formId])) {
                $json = str_replace([",]",",,"], [",\"\"]",",\"\","], $pk->formData);
                $data = json_decode($json);
                if(is_callable($this->forms[$pk->formId][0])) {
                    call_user_func_array($this->forms[$pk->formId][0], array_merge([$player, $data], $this->forms[$pk->formId][1]));
                }
                unset($this->forms[$pk->formId]);
            }
        }
    }
}
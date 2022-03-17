<?php

declare(strict_types=1);

namespace practice;

use JetBrains\PhpStorm\Pure;
use muqsit\invmenu\InvMenuHandler;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\data\bedrock\EntityLegacyIds;
use pocketmine\data\bedrock\PotionTypeIdMap;
use pocketmine\data\bedrock\PotionTypeIds;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\world\World;
use practice\arenas\ArenaHandler;
use practice\commands\advanced\ArenaCommand;
use practice\commands\advanced\KitCommand;
use practice\commands\advanced\MuteCommand;
use practice\commands\advanced\RankCommand;
use practice\commands\advanced\ReportCommand;
use practice\commands\advanced\StatsCommand;
use practice\commands\basic\AcceptCommand;
use practice\commands\basic\ClearInventoryCommand;
use practice\commands\basic\DuelCommand;
use practice\commands\basic\ExtinguishCommand;
use practice\commands\basic\FeedCommand;
use practice\commands\basic\FlyCommand;
use practice\commands\basic\FreezeCommand;
use practice\commands\basic\HealCommand;
use practice\commands\basic\KickAllCommand;
use practice\commands\basic\PingCommand;
use practice\commands\basic\PlayerInfoCommand;
use practice\commands\basic\SpawnCommand;
use practice\commands\basic\SpectateCommand;
use practice\commands\basic\TeleportLevelCommand;
use practice\duels\DuelHandler;
use practice\duels\IvsIHandler;
use practice\game\entity\FishingHook;
use practice\game\entity\SplashPotion;
use practice\game\items\ItemHandler;
use practice\game\SetTimeDayTask;
use practice\kits\KitHandler;
use practice\manager\MysqlManager;
use practice\parties\PartyManager;
use practice\player\gameplay\ChatHandler;
use practice\player\gameplay\ReportHandler;
use practice\player\info\IPHandler;
use practice\player\permissions\PermissionsHandler;
use practice\player\permissions\PermissionsToCfgTask;
use practice\player\PlayerHandler;
use practice\ranks\RankHandler;
use UnexpectedValueException;

class PracticeCore extends PluginBase
{
    /** @var PracticeCore */
    private static PracticeCore $instance;

    /** @var PlayerHandler */
    private static PlayerHandler $playerHandler;
    /** @var ChatHandler */
    private static ChatHandler $chatHandler;
    /** @var RankHandler */
    private static RankHandler $rankHandler;
    /** @var ItemHandler */
    private static ItemHandler $itemHandler;
    /** @var KitHandler */
    private static KitHandler $kitHandler;
    /** @var ArenaHandler */
    private static ArenaHandler $arenaHandler;
    /** @var DuelHandler */
    private static DuelHandler $duelHandler;
    /** @var IvsIHandler */
    private static IvsIHandler $ivsiHandler;
    /** @var ReportHandler */
    private static ReportHandler $reportHandler;
    /* @var PermissionsHandler */
    private static PermissionsHandler $permissionsHandler;
    /** @var PartyManager */
    private static PartyManager $partyManager;
    /** @var IPHandler */
    private static IPHandler $ipHandler;
    /** @var MysqlManager */
    private static MysqlManager $mysqlManager;

    /** @var bool */
    private bool $serverMuted;

    /**
     * @return PracticeCore
     */
    public static function getInstance(): PracticeCore
    {
        return self::$instance;
    }

    /**
     * @return ChatHandler
     */
    public static function getChatHandler(): ChatHandler
    {
        return self::$chatHandler;
    }

    /**
     * @return PlayerHandler
     */
    public static function getPlayerHandler(): PlayerHandler
    {
        return self::$playerHandler;
    }

    /**
     * @return RankHandler
     */
    public static function getRankHandler(): RankHandler
    {
        return self::$rankHandler;
    }

    /**
     * @return ItemHandler
     */
    public static function getItemHandler(): ItemHandler
    {
        return self::$itemHandler;
    }

    /**
     * @return KitHandler
     */
    public static function getKitHandler(): KitHandler
    {
        return self::$kitHandler;
    }

    /**
     * @return ArenaHandler
     */
    public static function getArenaHandler(): ArenaHandler
    {
        return self::$arenaHandler;
    }

    /**
     * @return DuelHandler
     */
    public static function getDuelHandler(): DuelHandler
    {
        return self::$duelHandler;
    }

    /**
     * @return IvsIHandler
     */
    public static function get1vs1Handler(): IvsIHandler
    {
        return self::$ivsiHandler;
    }

    /**
     * @return ReportHandler
     */
    public static function getReportHandler(): ReportHandler
    {
        return self::$reportHandler;
    }

    /**
     * @return PermissionsHandler
     */
    public static function getPermissionHandler(): PermissionsHandler
    {
        return self::$permissionsHandler;
    }

    /**
     * @return PartyManager
     */
    public static function getPartyManager(): PartyManager
    {
        return self::$partyManager;
    }

    /**
     * @return IPHandler
     */
    public static function getIPHandler(): IPHandler
    {
        return self::$ipHandler;
    }

    /**
     * @return MysqlManager
     */
    public static function getMysqlHandler(): MysqlManager
    {
        return self::$mysqlManager;
    }

    /**
     * @return void
     */
    public function onEnable(): void
    {
        if(!class_exists(InvMenuHandler::class)){
            $this->getServer()->getLogger()->info("InvMenu not found! Disabling the plugin.");
            $this->getServer()->shutdown();
        }

        if(!InvMenuHandler::isRegistered()) InvMenuHandler::register($this);

        $this->loadWorlds();

        $this->registerEntities();

        date_default_timezone_set("America/Los_Angeles");

        $this->initDataFolder();
        $this->saveDefaultConfig();
        $this->initMessageConfig();
        $this->initMysqlConfig();
        $this->initNameConfig();
        $this->initRankConfig();
        $this->initCommands();

        self::$instance = $this;

        self::$playerHandler = new PlayerHandler($this);
        self::$kitHandler = new KitHandler();
        self::$arenaHandler = new ArenaHandler();

        self::$mysqlManager = new MysqlManager($this->getDataFolder());

        if (!PracticeUtil::isMysqlEnabled()) self::$playerHandler->updateLeaderboards();

        self::$itemHandler = new ItemHandler($this);
        self::$rankHandler = new RankHandler();
        self::$chatHandler = new ChatHandler();
        self::$duelHandler = new DuelHandler();
        self::$ivsiHandler = new IvsIHandler();
        self::$reportHandler = new ReportHandler();
        self::$permissionsHandler = new PermissionsHandler($this);
        self::$partyManager = new PartyManager();
        self::$ipHandler = new IPHandler($this);

        $this->serverMuted = false;

        PracticeUtil::reloadPlayers();

        $scheduler = $this->getScheduler();

        $this->getServer()->getPluginManager()->registerEvents(new PracticeListener($this), $this);
        $scheduler->scheduleDelayedTask(new SetTimeDayTask($this), 10);
        $scheduler->scheduleDelayedTask(new PermissionsToCfgTask(), 10);
        $scheduler->scheduleRepeatingTask(new PracticeTask($this), 1);
    }

    /**
     * @return void
     */
    private function loadWorlds(): void
    {
        $worlds = PracticeUtil::getWorldsFromFolder($this);

        $size = count($worlds);

        if ($size > 0) {
            foreach ($worlds as $world) PracticeUtil::loadWorld($world);
        }
    }

    /**
     * @return void
     */
    private function registerEntities(): void
    {
        EntityFactory::getInstance()->register(SplashPotion::class, function (World $world, CompoundTag $nbt): SplashPotion {
            $potionType = PotionTypeIdMap::getInstance()->fromId($nbt->getShort("PotionId", PotionTypeIds::WATER));
            if ($potionType === null) {
                throw new UnexpectedValueException("No such potion type");
            }
            return new SplashPotion(EntityDataHelper::parseLocation($nbt, $world), null, $potionType, $nbt);
        }, ["ThrownPotion", "minecraft:potion", "thrownpotion"], EntityLegacyIds::SPLASH_POTION);

        EntityFactory::getInstance()->register(FishingHook::class, function (World $world, CompoundTag $nbt): FishingHook {
            return new FishingHook(EntityDataHelper::parseLocation($nbt, $world), null, $nbt);
        }, ["FishingHook", "minecraft:fishing_hook"], EntityLegacyIds::FISHING_HOOK);
    }

    /**
     * @return void
     */
    private function initDataFolder(): void
    {
        $dataFolder = $this->getDataFolder();

        if (!is_dir($dataFolder)) mkdir($dataFolder);
    }

    /**
     * @return void
     */
    private function initMessageConfig(): void
    {
        $this->saveResource("messages.yml");
    }

    /**
     * @return void
     */
    private function initMysqlConfig(): void
    {
        $this->saveResource("mysql.yml");
    }

    /**
     * @return void
     */
    private function initNameConfig(): void
    {
        $this->saveResource("names.yml");
    }

    /**
     * @return void
     */
    private function initRankConfig(): void
    {
        $this->saveResource("ranks.yml");
    }

    /**
     * @return void
     */
    private function initCommands(): void
    {
        $this->registerCommand(new KitCommand());
        $this->registerCommand(new ExtinguishCommand());
        $this->registerCommand(new ClearInventoryCommand());
        $this->registerCommand(new FeedCommand());
        $this->registerCommand(new FlyCommand());
        $this->registerCommand(new FreezeCommand());
        $this->registerCommand(new FreezeCommand(false));
        $this->registerCommand(new MuteCommand());
        $this->registerCommand(new RankCommand());
        $this->registerCommand(new SpawnCommand());
        $this->registerCommand(new ArenaCommand());
        $this->registerCommand(new HealCommand());
        $this->registerCommand(new DuelCommand());
        $this->registerCommand(new AcceptCommand());
        $this->registerCommand(new ReportCommand());
        $this->registerCommand(new SpectateCommand());
        $this->registerCommand(new StatsCommand());
        $this->registerCommand(new PingCommand());
        $this->registerCommand(new TeleportLevelCommand());
        $this->registerCommand(new KickAllCommand());
        $this->registerCommand(new PlayerInfoCommand());

        $this->unregisterCommand("me");
        $this->unregisterCommand("tell");
    }

    /**
     * @param Command $cmd
     * @return void
     */
    private function registerCommand(Command $cmd): void
    {
        $this->getServer()->getCommandMap()->register($cmd->getName(), $cmd);
    }

    /**
     * @param string $name
     * @return void
     */
    private function unregisterCommand(string $name): void
    {
        $map = $this->getServer()->getCommandMap();
        $cmd = $map->getCommand($name);
        if ($cmd !== null) $this->getServer()->getCommandMap()->unregister($cmd);
    }

    /**
     * @return void
     */
    public function onLoad(): void
    {
        $this->loadWorlds();
    }

    /**
     * @return bool
     */
    public function isServerMuted(): bool
    {
        return $this->serverMuted;
    }

    /**
     * @param bool $mute
     * @return void
     */
    public function setServerMuted(bool $mute): void
    {
        $this->serverMuted = $mute;
    }

    /**
     * @return Config
     */
    public function getMessageConfig(): Config
    {
        $path = $this->getDataFolder() . "messages.yml";
        return new Config($path, Config::YAML);
    }

    /**
     * @return Config
     */
    public function getRankConfig(): Config
    {
        $path = $this->getDataFolder() . "ranks.yml";
        return new Config($path, Config::YAML);
    }

    /**
     * @return Config
     */
    public function getNameConfig(): Config
    {
        $path = $this->getDataFolder() . "names.yml";
        return new Config($path, Config::YAML);
    }

    /**
     * @param CommandSender $sender
     * @param Command $command
     * @param string $label
     * @param array $args
     * @return bool
     */
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        return parent::onCommand($sender, $command, $label, $args);
    }

    /**
     * @return string
     */
    #[Pure] public function getResourcesFolder(): string
    {
        return $this->getFile() . 'resources/';
    }
}
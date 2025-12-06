<?php
namespace Ankou;

use FML\Controls\Frame;
use FML\Controls\Label;
use FML\Controls\Quad;
use FML\Controls\Quads\Quad_Icons128x128_1;
use FML\ManiaLink;
use ManiaControl\Callbacks\CallbackListener;
use ManiaControl\Callbacks\TimerListener;
use ManiaControl\Commands\CommandListener;
use ManiaControl\Communication\CommunicationListener;
use ManiaControl\ManiaControl;
use ManiaControl\Manialinks\ManialinkManager;
use ManiaControl\Manialinks\ManialinkPageAnswerListener;
use ManiaControl\Manialinks\SidebarMenuEntryListener;
use ManiaControl\Manialinks\SidebarMenuManager;
use ManiaControl\Players\Player; // for pause
use ManiaControl\Players\PlayerManager;
use ManiaControl\Plugins\Plugin;
use ManiaControl\Settings\Setting;
use ManiaControl\Settings\SettingManager;

/**
 *
 *
 * @author  Ankou
 * @version 0.1
 */
class LobbyServersListPlugin implements CallbackListener, CommandListener, TimerListener, CommunicationListener, Plugin, ManialinkPageAnswerListener, SidebarMenuEntryListener
{
    const ID      = 216;
    const VERSION = 0.6;
    const NAME    = 'LobbyServersList';
    const AUTHOR  = 'Ankou';

    const SETTING_X           = "Widget X";
    const SETTING_Y           = "Widget Y";
    const SETTING_WIDTH       = "Widget Width";
    const SETTING_HEIGHT      = "Widget Height";
    const SETTING_USEICONMENU = "Use icon menu";
    const SETTING_CONFIGFILE  = "Config file";

    const SETTING_GLOBALJOINPASS = "Global Join password";
    const SETTING_GLOBALSPECPASS = "Global Spec password";

    const ML_MAIN   = "LobbyServersList.Main";
    const ICON_MENU = "LobbyServersList.MenuIcon";
    const MLID_ICON = "LobbyServersList.IconWidgetId";

    const ACT_OPENLIST = "LobbyServersList.OpenList";
    const ACT_CLOSE    = "LobbyServersList.Close";

    const SERVER_ITEM_HEIGHT = 10;

    protected $message = "";
    protected $x;
    protected $y;
    protected $width;
    protected $height;

    protected $globalJoinPass = "";
    protected $globalSpecPass = "";

    protected $useIconMenu = true;
    protected $configFile  = "";

    private $config = null;

    /** @var ManiaControl $maniaControl */
    private $maniaControl = null;

    /**
     * @see \ManiaControl\Plugins\Plugin::load()
     */
    public function load(ManiaControl $maniaControl)
    {
        $this->maniaControl = $maniaControl;

        $this->maniaControl->getCallbackManager()->registerCallbackListener(PlayerManager::CB_PLAYERCONNECT, $this, 'handlePlayerConnect');
        $this->maniaControl->getCallbackManager()->registerCallbackListener(SettingManager::CB_SETTING_CHANGED, $this, 'updateSetting');

        //===========================================================================================================================
        //settings===================================================================================================================
        //===========================================================================================================================

        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_X, 70);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_Y, 30);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_WIDTH, 78);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_HEIGHT, 99);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_USEICONMENU, true);
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_CONFIGFILE, "", 'Check $lhttps://github.com/kilianbreton/LobbyServersListPlugin');
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_GLOBALJOINPASS, "", '');
        $this->maniaControl->getSettingManager()->initSetting($this, self::SETTING_GLOBALSPECPASS, "", '');

        $this->maniaControl->getManialinkManager()->registerManialinkPageAnswerListener(self::ACT_OPENLIST, $this, 'handleOpenList');
        $this->maniaControl->getManialinkManager()->registerManialinkPageAnswerListener(self::ACT_CLOSE, $this, 'handleClose');

        $this->loadAllSettings();
    }

    private function loadAllSettings()
    {
        $this->x           = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_X);
        $this->y           = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_Y);
        $this->width       = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_WIDTH);
        $this->height      = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_HEIGHT);
        $this->useIconMenu = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_USEICONMENU);
        $this->configFile  = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_CONFIGFILE);
        $this->globalJoinPass = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_GLOBALJOINPASS);
        $this->globalSpecPass = $this->maniaControl->getSettingManager()->getSettingValue($this, self::SETTING_GLOBALSPECPASS);
        $this->loadConfig();
        if ($this->useIconMenu) {
            $this->maniaControl->getManialinkManager()->getSidebarMenuManager()->addMenuEntry(SidebarMenuManager::ORDER_PLAYER_MENU + 5, self::ICON_MENU, $this, 'showIcon');
        } else {
            $this->maniaControl->getManialinkManager()->getSidebarMenuManager()->deleteMenuEntry($this, self::ICON_MENU);
            $this->maniaControl->getManialinkManager()->hideManialink(self::ICON_MENU);
            $this->maniaControl->getManialinkManager()->hideManialink(self::MLID_ICON);
            //test
            $this->buildManiaLink();
        }

    }

    public function updateSetting(Setting $setting)
    {
        switch ($setting->setting) {
            case self::SETTING_X:
                $this->x = $setting->value;
                break;
            case self::SETTING_Y:
                $this->y = $setting->value;
                break;
            case self::SETTING_WIDTH:
                $this->width = $setting->value;
                break;
            case self::SETTING_HEIGHT:
                $this->height = $setting->value;
                break;
            case self::SETTING_GLOBALJOINPASS:
                $this->globalJoinPass = $setting->value;
                break;
            case self::SETTING_GLOBALSPECPASS:
                $this->globalSpecPass = $setting->value;
                break;
            case self::SETTING_USEICONMENU:
                $this->useIconMenu = $setting->value;
                if ($this->useIconMenu) {
                    $this->maniaControl->getManialinkManager()->getSidebarMenuManager()->addMenuEntry(SidebarMenuManager::ORDER_PLAYER_MENU + 5, self::ICON_MENU, $this, 'showIcon');
                } else {
                    $this->maniaControl->getManialinkManager()->getSidebarMenuManager()->deleteMenuEntry($this, self::ICON_MENU);
                    $this->maniaControl->getManialinkManager()->hideManialink(self::ICON_MENU);
                    $this->maniaControl->getManialinkManager()->hideManialink(self::MLID_ICON);
                }
                break;
            case self::SETTING_CONFIGFILE:
                $this->configFile = $setting->value;
                $this->loadConfig();
                break;

        }
    }

    private function loadConfig()
    {
        if ($this->configFile == "") {
            $this->maniaControl->getChat()->sendErrorToAdmins('[LobbyServersList] No config file, please check $lhttps://github.com/kilianbreton/LobbyServersListPlugin');
            return;
        }

        $data = file_get_contents($this->configFile);
        if ($data == null) {
            $this->maniaControl->getChat()->sendErrorToAdmins('[LobbyServersList] Unable to load config file');
            return;
        }

        $data = json_decode($data);
        if ($data == null) {
            $this->maniaControl->getChat()->sendErrorToAdmins('[LobbyServersList] Config file : invalid syntax');
            return;
        }

        if (! is_array($data)) {
            $this->maniaControl->getChat()->sendErrorToAdmins('[LobbyServersList] Config file : must be an array');
            return;
        }

        $this->config = $data;
    }

    public function showIcon($login = false)
    {
        if (! $this->useIconMenu) {
            return;
        }

        $pos               = $this->maniaControl->getManialinkManager()->getSidebarMenuManager()->getEntryPosition(self::ICON_MENU);
        $width             = $this->maniaControl->getSettingManager()->getSettingValue($this->maniaControl->getManialinkManager()->getSidebarMenuManager(), SidebarMenuManager::SETTING_MENU_ITEMSIZE);
        $quadStyle         = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultQuadStyle();
        $quadSubstyle      = $this->maniaControl->getManialinkManager()->getStyleManager()->getDefaultQuadSubstyle();
        $itemMarginFactorX = 1.3;
        $itemMarginFactorY = 1.2;

        $itemSize = $width;

        $maniaLink = new ManiaLink(self::MLID_ICON);

        //Custom Vote Menu Iconsframe
        $frame = new Frame();
        $maniaLink->addChild($frame);
        $frame->setPosition($pos->getX(), $pos->getY());
        $frame->setZ(ManialinkManager::MAIN_MANIALINK_Z_VALUE);

        $backgroundQuad = new Quad();
        $frame->addChild($backgroundQuad);
        $backgroundQuad->setSize($width * $itemMarginFactorX, $itemSize * $itemMarginFactorY);
        $backgroundQuad->setStyles($quadStyle, $quadSubstyle);

        $iconFrame = new Frame();
        $frame->addChild($iconFrame);

        $iconFrame->setSize($itemSize, $itemSize);
        $itemQuad = new Quad_Icons128x128_1();
        $itemQuad->setSubStyle($itemQuad::SUBSTYLE_ServersSuggested);
        $itemQuad->setSize($itemSize, $itemSize);
        $iconFrame->addChild($itemQuad);
        $itemQuad->setAction(self::ACT_OPENLIST);
        // Send manialink
        $this->maniaControl->getManialinkManager()->sendManialink($maniaLink, $login);
    }

    /**
     * Summary of buildManiaLink
     * @param Player $player
     * @return void
     */
    public function buildManiaLink($player = null)
    {
        $manialink = new ManiaLink(self::ML_MAIN);
        $frame     = new Frame();
        $manialink->addChild($frame);
        $frame->setPosition($this->x, $this->y, 1);
        $frame->setAlign("left", "top");

        //Main Window
        $window = new Quad();
        $frame->addChild($window);
        $window->setSize($this->width, $this->height);
        $window->setStyles("Bgs1", "BgButtonOff");
        $window->setPosition(0, 0);
        $window->setAlign("left", "top");
        $window->setZ(2);

        //Header
        $backgroundHeader = new Quad();
        $frame->addChild($backgroundHeader);
        $backgroundHeader->setSize($this->width - 0.3, 5);
        $backgroundHeader->setStyles("Bgs1", "BgWindow1");
        $backgroundHeader->setPosition(0.1, 0);
        $backgroundHeader->setAlign("left", "top");
        $backgroundHeader->setZ(3);

        //title
        $title = new Label();
        $frame->addChild($title);
        $title->setPosition(2, -2);
        $title->setText("Server list");
        $title->setTextSize(1.8);
        $title->setAlign("left", "top");
        $title->setZ(4);

        //Close button
        $closeButton = new Label();
        $frame->addChild($closeButton);
        $closeButton->setPosition($this->width - 3, -2);
        $closeButton->setText("X");
        $closeButton->setSize(3, 3);
        $closeButton->setTextSize(1.9);
        $closeButton->setAlign("left", "top");
        $closeButton->setZ(4);
        $closeButton->setAction(self::ACT_CLOSE);

        if ($this->config == null) {
            $this->maniaControl->getManialinkManager()->sendManialink($manialink, $player);
            return;
        }

        $cpt = 1;
        foreach ($this->config as $server) {
            $serverFrame = new Frame();
            $manialink->addChild($serverFrame);
            $serverFrame->setPosition($this->x + 2, $this->y - (self::SERVER_ITEM_HEIGHT * $cpt) - 2, 8);
            $serverFrame->setAlign("left", "top");

            //server background
            $back = new Quad();
            $serverFrame->addChild($back);
            $back->setSize($this->width - 5.2, self::SERVER_ITEM_HEIGHT);
            $back->setStyles("Bgs1", "BgTitle");
            $back->setPosition(0.5, 0, 9, 9);
            $back->setAlign("left", "center2");

            $name = new Label();
            $serverFrame->addChild($name);
            $name->setSize($this->width - 4.5, self::SERVER_ITEM_HEIGHT);
            $name->setPosition(1.5, 0, 10);
            $name->setAlign("left", "center2");
            $name->setText($server->Name);
            $name->setTextSize(2);
            ++$cpt;


            $joinString = "#qjoin=" . $server->Login;
            if(!empty($server->JoinPassword))
                $joinString .= ":" . $server->JoinPassword;
            else
                if(!empty($this->globalJoinPass) && $player != null && !$player->isSpectator)
                    $joinString .= ":" . $this->globalJoinPass;

            if(!empty($server->TitlePack))
                $joinString .= "@" . $server->TitlePack;

            $joinString = str_replace("{current}", $this->maniaControl->getClient()->getServerPassword(), $joinString);


            //Join button
            $joinButton = new Quad();
            $serverFrame->addChild($joinButton);
            $joinButton->setPosition($this->width - 12, 0, 10);
            $joinButton->setSize(8, 8);
            $joinButton->setAlign("left", "center2");
            $joinButton->setStyles("Icons64x64_1", "ClipPlay");
            $joinButton->setManialink($joinString);


            $specString = "#qspectate=" . $server->Login;
            if(!empty($server->JoinPassword))
                $specString .= ":" . $server->SpectatePassword;
            else
                if(!empty($this->globalSpecPass))
                    $specString .= ":" . $this->globalSpecPass;

            $specString = str_replace("{current}", $this->maniaControl->getClient()->getServerPasswordForSpectator(), $specString);

            if(!empty($server->TitlePack))
                $specString .= "@" . $server->TitlePack;

            //Spectate button
            $joinButton = new Quad();
            $serverFrame->addChild($joinButton);
            $joinButton->setPosition($this->width - 19, 0, 10);
            $joinButton->setSize(8, 8);
            $joinButton->setAlign("left", "center2");
            $joinButton->setStyles("Icons64x64_1", "TV");
            $joinButton->setManialink($specString);
        }
        $this->maniaControl->getManialinkManager()->sendManialink($manialink, $player);

    }

    public function handleOpenList(array $callback, Player $player)
    {
        $this->buildManiaLink($player);
    }
    public function handleClose(array $callback, Player $player)
    {
        $this->maniaControl->getManialinkManager()->hideManialink(self::ML_MAIN, $player);
    }

    /**
     * Handle PlayerConnect callback
     *
     * @param Player $player
     */
    public function handlePlayerConnect(Player $player)
    {
        if (! $player) {
            return;
        }

        if ($this->useIconMenu) {
            $this->showIcon($player);
        } else {
            $this->buildManiaLink($player);
        }

    }

    /**
     * @see \ManiaControl\Plugins\Plugin::unload()
     */
    public function unload()
    {
        $this->maniaControl = null;
    }

    /**
     * @see \ManiaControl\Plugins\Plugin::getId()
     */
    public static function getId()
    {
        return self::ID;
    }

    /**
     * @see \ManiaControl\Plugins\Plugin::getName()
     */
    public static function getName()
    {
        return self::NAME;
    }

    /**
     * @see \ManiaControl\Plugins\Plugin::getVersion()
     */
    public static function getVersion()
    {
        return self::VERSION;
    }

    /**
     * @see \ManiaControl\Plugins\Plugin::getAuthor()
     */
    public static function getAuthor()
    {
        return self::AUTHOR;
    }

    /**
     * @see \ManiaControl\Plugins\Plugin::getDescription()
     */
    public static function getDescription()
    {
        return 'Display a message to the player when he connects to the server';
    }

    /**
     * @see \ManiaControl\Plugins\Plugin::prepare()
     */
    public static function prepare(ManiaControl $maniaControl)
    {
        // TODO: Implement prepare() method.
    }
}

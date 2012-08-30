#!/usr/bin/env php
<?php
/**
 * @license ALL RIGHTS RESERVED - Commercial use only with my approbation
 *
 * @author Jacques Bodin-Hullin <jacques@bodin-hullin.net>
 * @package Installer
 * @copyright Copyright (c) 2011-2012 Jacques Bodin-Hullin (http://jacques.sh)
 */

declare( ticks = 1 );

// TODO system.xml
// TODO adminhtml.xml

ini_set('date.timezone', 'Europe/Paris');

defined('PWD')                  || define('PWD', getenv('PWD'));
defined('LICENSE')              || define('LICENSE', getenv('LICENSE') ? getenv('LICENSE') : 'All rights reserved');
defined('USER_EMAIL')           || define('USER_EMAIL', getenv('USER_EMAIL') ? getenv('USER_EMAIL') : '');
defined('USER_NAME')            || define('USER_NAME', getenv('USER_NAME') ? getenv('USER_NAME') : '');
defined('DESIGN')               || define('DESIGN', getenv('DESIGN') ? getenv('DESIGN') : 'base_default');
defined('COMPANY_NAME')         || define('COMPANY_NAME', getenv('COMPANY_NAME') ? getenv('COMPANY_NAME') : '');
defined('COMPANY_NAME_SHORT')   || define('COMPANY_NAME_SHORT', getenv('COMPANY_NAME_SHORT') ? getenv('COMPANY_NAME_SHORT') : '');
defined('COMPANY_URL')          || define('COMPANY_URL', getenv('COMPANY_URL') ? getenv('COMPANY_URL') : '');
defined('LOCALES')              || define('LOCALES', getenv('LOCALES') ? getenv('LOCALES') : 'fr_FR,en_US');
defined('OS')                   || define('OS', getenv('OS') ? getenv('OS') : (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'windows' : 'unix'));
defined('DEBUG_ENABLED')        || define('DEBUG_ENABLED', getenv('DEBUG_ENABLED') ? getenv('DEBUG_ENABLED') : false);

function red()      { return isUnix() ? "\033[01;31m" : ""; }
function yellow()   { return isUnix() ? "\033[01;33m" : ""; }
function blue()     { return isUnix() ? "\033[01;34m" : ""; }
function green()    { return isUnix() ? "\033[01;32m" : ""; }
function white()    { return isUnix() ? "\033[00m" : ""; }

function isUnix()   { return (OS == 'unix'); }

function debug($var) { if (DEBUG_ENABLED) { echo "\n\n" . green(); var_dump($var); echo white() . "\n\n"; } }

// Read functions
if (!function_exists('readline')) {
    function readline($prompt = '')
    {
        if (!empty($prompt) && is_string($prompt)) {
            echo $prompt;
        }
        $fp = fopen("php://stdin", "r");
        $in = fgets($fp);
        fclose($fp);
        return $in;
    }
}

if (!function_exists('readline_add_history')) {
    function readline_add_history($line)
    {
        // nothing...
    }
}

class Installer
{

    protected $_templates;

    protected $_pool = 'local';
    protected $_namespace;
    protected $_module;

    protected $_params;
    protected $_config;

    protected $_stop = false;

    protected $_lastMethod;
    protected $_lastParams;

    protected $_cli = true;

    public function __construct(array $argv, $useCmdLine = true)
    {
        // Welcome message
        echo green() . "The Installer - by jacquesbh\n";
        if (isUnix()) {
            echo "\033]0;" . "The Installer" . "\007";
        }

        // Execution path
        if (!is_dir($this->getAppDir())) {
            echo red() . "Bad execution path.\n";
            exit;
        }

        // Tidy required
        if (!function_exists('tidy_parse_string')) {
            echo red() . "Tidy is required ! http://tidy.sourceforge.net/\n";
            exit;
        }

        $this->setCli($useCmdLine);

        $this->_init($argv);
        $this->_start();
    }

    public function help()
    {
        echo white();
        echo <<<HELP
  ---------------------- ----------------------- -------------------------------------------
 | COMMAND              | ALIAS                 | PARAMETERS                                |
 |----------------------|-----------------------|-------------------------------------------|
 | help                 | -h ?                  |                                           |
 | module               | mod                   | namespace name pool                       |
 | general              |                       |                                           |
 | info                 | i config conf         |                                           |
 | clean                |                       | [all, cache, log(s)]                      |
 | controller           | c                     | name [actions]                            |
 | helper               | h                     | name [methods]                            |
 | model                | m                     | name [methods]                            |
 | observer             | o                     | name [methods]                            |
 | observer Observer    | oo                    | [methods]                                 |
 | block                | b                     | name [methods] [-p]                       |
 | translate            | t                     | where                                     |
 | translates           | ts                    |                                           |
 | layout               | l                     | where                                     |
 | layouts              | ls                    |                                           |
 | resources            | res                   |                                           |
 | entity               | ent                   | name table                                |
 | grid                 |                       | entity                                    |
 | setup                | sql set               |                                           |
 | upgrade              | up                    | [from] to                                 |
 | event                |                       | name model method where                   |
 | cron                 |                       | identifier 1 2 3 4 5 model method         |
 | default              | def conf              | name value                                |
 | depends              | dep                   | (-)module                                 |
 | exit                 |                       |                                           |
 | delete               | del rm remove         |                                           |
 | last                 |                       | [...]                                     |
 | addtranslate         | __                    |                                           |
 | routers              | r route router        | where frontName                           |
 | tmp                  |                       | action                                    |
 | misc                 | script                | name (without .php)                       |
 | doc                  |                       | [title]                                   |
 |                      |                       |                                           |
  ---------------------- ----------------------- -------------------------------------------

HELP;
    }

    protected function _quit()
    {
        exit;
    }

    protected function _init(array $params)
    {
        $this->_program = array_shift($params);
        if (!empty($params)) {
            $this->_processModule($params, true);
        }
    }

    protected function _start()
    {
        while (!$this->_stop) {
            $this->read();
        }
    }

    public function setCli($flag)
    {
        $this->_cli = (bool) $flag;
        return $this;
    }

    public function isCli()
    {
        return $this->_cli;
    }

    public function read()
    {
        $line = $this->_read();
        if (empty($line)) {
            echo white() . 'Try help?' . "\n";
            return;
        }
        $params = array_map('trim', explode(' ', $line));

        foreach ($params as $key => $param) {
            if ($param === '') {
                unset($params[$key]);
            }
        }

        $command = array_shift($params);
        $params = array_values($params);

        switch ($command) {
            case 'exit':
                echo "\n";
                $this->_quit();
                break;
            case 'echo':
                echo "\n" . white() . implode(' ', $params) . "\n";
                break;
            case 'help':
            case '-h':
            case '?':
                $this->help();
                break;
            case 'module':
            case 'mod':
                $this->_processModule($params, true);
                break;
            case 'clean':
                $this->_processClean($params);
                break;
            case 'info':
            case 'i':
            case 'config':
            case 'conf':
                $this->_processModule();
                $this->_processInfo($params);
                break;
            case 'general':
                $this->_processGeneral($params);
                break;
            case 'helper':
            case 'h':
                $this->_processModule();
                $this->_processHelper($params);
                break;
            case 'block':
            case 'b':
                $this->_processModule();
                $this->_processBlock($params);
                break;
            case 'controller':
            case 'c':
                $this->_processModule();
                $this->_processController($params);
                break;
            case 'model':
            case 'm':
                $this->_processModule();
                $this->_processModel($params);
                break;
            case 'observer':
            case 'o':
                $this->_processModule();
                $this->_processModel($params, 'observer');
                break;
            case 'oo':
                $this->_processModule();
                array_unshift($params, 'observer');
                $this->_processModel($params, 'observer');
                break;
            case 'version':
            case 'v':
                $this->_processModule();
                $this->_processVersion($params);
                break;
            case 'translate':
            case 't':
                $this->_processModule();
                $this->_processTranslate($params);
                break;
            case 'translates':
            case 'ts':
                $this->_processModule();
                $this->_processTranslate(array('admin'));
                $this->_processTranslate(array('front'));
                break;
            case 'addtranslate':
            case '__':
                $this->_processModule();
                $this->_processAddTranslate();
                break;
            case 'layout':
            case 'l':
                $this->_processModule();
                $this->_processLayout($params);
                break;
            case 'layouts':
            case 'ls':
                $this->_processModule();
                $this->_processLayout(array('admin'));
                $this->_processLayout(array('front'));
                break;
            case 'resources':
            case 'res':
                $this->_processModule();
                $this->_processResources(array());
                break;
            case 'entity':
            case 'ent':
                $this->_processModule();
                $this->_processEntity($params);
                break;
            case 'setup':
            case 'set':
            case 'sql':
                $this->_processModule();
                $this->_processSetupSql($params);
                break;
            case 'upgrade':
            case 'up':
                $this->_processModule();
                $this->_processUpgradeSql($params);
                break;
            case 'event':
            case 'e':
                $this->_processModule();
                $this->_processEvent($params);
                break;
            case 'cron':
                $this->_processModule();
                $this->_processCron($params);
                break;
            case 'default':
            case 'def':
            case 'conf':
                $this->_processModule();
                $this->_processDefault($params);
                break;
            case 'depends':
            case 'dep':
                $this->_processModule();
                $this->_processDepends($params);
                break;
            case 'delete':
            case 'remove':
            case 'del':
            case 'rm':
                $this->_processModule();
                $this->_processDelete();
            case 'last':
                $this->_processLast($params);
                break;
            case 'routers':
            case 'r':
            case 'route':
            case 'router':
                $this->_processModule();
                $this->_processRouter($params);
                break;
            case 'grid':
                $this->_processModule();
                $this->_processGrid($params);
                break;
            case 'tmp':
                $this->_processTmp($params);
                break;
            case 'misc':
            case 'script':
                $this->_processMisc($params);
                break;
            case 'doc':
                $this->_processDocumentation($params);
                break;
            default:
                echo white() . 'Try help?' . "\n";
                break;
        }

        usleep(100000);
    }

    protected function _processDocumentation(array $params)
    {
        // Title?
        if (!empty($params)) {
            $title = implode(' ', $params);
        } else {
            $title = $this->getModuleTitle();
        }

        $dir = $this->getModuleDir('doc');

        if (!is_file($filename = $dir . '/README.md')) {
            file_put_contents($filename, $this->getTemplate('doc_readme', array(
                'title' => $title
            )));
        }
    }

    protected function _processMisc(array $params)
    {
        if (empty($params)) {
            do {
                $name = $this->prompt('Which name?');
            } while (empty($name));
        } else {
            $name = array_shift($params);
        }

        $name = str_replace(' ', '_', strtolower($name));

        $dir = $this->getMiscDir();
        if (!is_dir($dir)) {
            mkdir($dir);
        }

        $filename = $dir . '/' . $name . '.php';

        if (!is_file($filename)) {
            file_put_contents($filename, $this->getTemplate('misc'));
        }

    }

    protected function _processTmp(array $params)
    {
        $this->_processModule(array(COMPANY_NAME_SHORT, 'tmp', 'local'), true);
        $this->_processRouter(array('front', 'tmp'));

        if (empty($params)) {
            $params = array('index');
        }
        array_unshift($params, 'index');
        $this->_processController($params);
    }

    protected function _processGrid(array $params)
    {
        // Check entity
        if (empty($params)) {
            do {
                $entity = $this->prompt('Which entity?');
            } while (empty($entity));
        } else {
            $entity = array_shift($params);
        }

        // Check entity exists
        $this->_processResources(array());

        $config = $this->getConfig();
        if (!isset($config->global)) {
            $config->addChild('global');
        }
        $resourceModel = $config->global->models->{strtolower($this->getModuleName())}->resourceModel;
        $entities = $config->global->models->{$resourceModel}->entities;
        if (!$entities->{strtolower($entity)}) {
            $this->_processEntity(array($entity));
        }
        unset($config);

        // Create directories :)
        $names = array_map('ucfirst', explode('_', $entity));
        array_unshift($names, 'Adminhtml');

        list($dir, $created) = $this->getModuleDir('Block', true);

        if ($created) {
            $config = $this->getConfig();
            $global = $config->global;
            if (!isset($global['blocks'])) {
                $global->addChild('blocks')->addChild(strtolower($this->getModuleName()))->addChild('class', $this->getModuleName() . '_Block');
            }
            $this->writeConfig();
        }

        foreach ($names as $rep) {
            $dir .= $rep . '/';
            if (!is_dir($dir)) {
                mkdir($dir);
            }
        }

        // Create container
        $filename = $dir . '../' . ucfirst($entity) . '.php';

        if (!is_file($filename)) {
            file_put_contents($filename, $this->getTemplate('grid_container_block', array(
                '{Entity}' => ucfirst($entity),
                '{Name}' => 'Adminhtml_' . ucfirst($entity),
                '{blockGroup}' => strtolower($this->getModuleName()),
                '{controller}' => 'adminhtml_' . strtolower($entity)
            )));
        }

        // Create grid
        $filename = $dir . '/Grid.php';

        if (!is_file($filename)) {
            file_put_contents($filename, $this->getTemplate('grid_block', array(
                '{Entity}' => ucfirst($entity),
                '{Name}' => 'Adminhtml_' . ucfirst($entity) . '_Grid',
                '{resource_model_collection}' => strtolower($this->getModuleName()) . '/' . strtolower($entity) . '_collection',
                '{Collection_Model}' => $this->getModuleName() . '_Model_Mysql4_' . ucfirst($entity) . '_Collection'
             )));
        }
    }

    protected function _processGeneral(array $params)
    {
        // If no config file
        if (false === $local = $this->_getLocalXml()) {
            return;
        }

        $width = 80;

        // General
        echo red() . "General Configuration\n";
        echo red() . str_repeat('-', $width) . "\n";

        // Database
        if (
            $local->global
            && $local->global->resources
            && $local->global->resources->default_setup
            && $local->global->resources->default_setup->connection
        ) {
            echo green() . "Database\n";
            $c = $local->global->resources->default_setup->connection;
            echo yellow() . 'Host' . white() . ' : ' . trim($c->host) . "\n";
            echo yellow() . 'User' . white() . ' : ' . trim($c->username) . "\n";
            echo yellow() . 'Pass' . white() . ' : ' . trim($c->password) . "\n";
            echo yellow() . 'Name' . white() . ' : ' . trim($c->dbname) . "\n";
        }

        echo "\n";
    }

    protected function _getLocalXml()
    {
        if (!is_file($filename = $this->getAppDir() . 'etc/local.xml')) {
            echo red() . 'local.xml not found' . "\n";
            return false;
        }

        return simplexml_load_file($filename);
    }

    protected function _processInfo(array $params)
    {
        // Colors
        $r = red();
        $y = yellow();
        $b = blue();
        $g = green();
        $w = white();

        $config = $this->getConfig();

        $width = 80;

        // Global
        echo $r . "Global Configuration\n";
        echo $r . str_repeat('-', $width) . "\n";

        // Helpers
        if ($config->global && $config->global->helpers) {
            echo $g . "Helpers\n";
            foreach ($config->global->helpers->children() as $child) {
                $namespace = $child->getName();
                if ($child->class) {
                    echo $w . $namespace;
                    echo $b . ' => ';
                    echo $w . $child->class . "\n";
                }
                if ($child->rewrite) {
                    foreach ($child->rewrite->children() as $rewrite) {
                        echo $w . $namespace . '/' . $rewrite->getName();
                        echo $r . ' => ';
                        echo $w . $rewrite . "\n";
                    }
                }
            }
            echo "\n";
        }

        // Blocks
        if ($config->global && $config->global->blocks) {
            echo $g . "Blocks\n";
            foreach ($config->global->blocks->children() as $child) {
                $namespace = $child->getName();
                if ($child->class) {
                    echo $w . $namespace;
                    echo $b . ' => ';
                    echo $w . $child->class . "\n";
                }
                if ($child->rewrite) {
                    foreach ($child->rewrite->children() as $rewrite) {
                        echo $w . $namespace . '/' . $rewrite->getName();
                        echo $r . ' => ';
                        echo $w . $rewrite . "\n";
                    }
                }
            }
            $space = $w . str_repeat(' ', 20);
            echo "\n";
        }

        // Models
        if ($config->global && $config->global->models) {
            echo $g . "Models\n";
            $resourcesModels = array();
            foreach ($config->global->models->children() as $child) {
                $namespace = $child->getName();
                if ($child->class) {
                    echo $w . $namespace;
                    echo $b . ' => ';
                    echo $w . $child->class;
                }
                if ($child->resourceModel) {
                    $resourceModels[(string) $child->resourceModel] = $namespace;
                    echo $w . ' (' . $child->resourceModel . ')';
                }
                if ($child->class) {
                    echo "\n";
                }
                if ($child->entities) {
                    foreach ($child->entities->children() as $entity) {
                        echo $y . '  table ';
                        echo $w . (array_key_exists($namespace, $resourceModels) ? $resourceModels[$namespace] : $namespace) . '/' . $entity->getName();
                        echo $y . ' => ';
                        echo $w . trim($entity->table) . "\n";
                    }
                }
                if ($child->rewrite) {
                    foreach ($child->rewrite->children() as $rewrite) {
                        echo $w . $namespace . '/' . $rewrite->getName();
                        echo $r . ' => ';
                        echo $w . trim($rewrite) . "\n";
                    }
                }
            }
            echo "\n";
        }

        // Events
        if ($config->global && $config->global->events) {
            $this->_processInfoEvents($config->global->events);
        }

        // Resources
        if ($config->global && $config->global->resources) {
            echo green() . "Resources\n";
            foreach ($config->global->resources->children() as $child) {
                echo $w . $child->getName() . "\n";
                if ($child->setup) {
                    echo $y . '  setup' . $w . ': ' . $child->setup->module;
                    if ($child->setup->class) {
                        echo ' (' . $child->setup->class . ')';
                    }
                    echo "\n";
                }
                if ($child->connection) {
                    echo $y . '  connection' . $w . ': use ' . $child->connection->use . "\n";
                }
                if ($child->use) {
                    echo $y . '  use' . $w . ': ' . $child->use . "\n";
                }
            }
            echo "\n";
        }

        // Template
        if ($config->global && $config->global->template) {
            if ($config->global->template->email) {
                // TODO
            }
        }


        // Frontend
        echo $r . "Frontend Configuration\n";
        echo $r . str_repeat('-', $width) . "\n";


        // Routers
        if ($config->frontend && $config->frontend->routers) {
            $this->_processInfoRouters($config->frontend->routers);
        }

        // Layout
        if ($config->frontend && $config->frontend->layout) {
            $this->_processInfoLayout($config->frontend->layout);
        }

        // Translate
        if ($config->frontend && $config->frontend->translate) {
            $this->_processInfoTranslate($config->frontend->translate);
        }


        // Admin
        echo $r . "Admin & Adminhtml Configurations\n";
        echo $r . str_repeat('-', $width) . "\n";


        // Routers
        if ($config->admin && $config->admin->routers) {
            $this->_processInfoRouters($config->admin->routers);
        }

        // Layout
        if ($config->admin && $config->adminhtml->layout) {
            $this->_processInfoLayout($config->adminhtml->layout);
        }

        // Translate
        if ($config->admin && $config->adminhtml->translate) {
            $this->_processInfoTranslate($config->adminhtml->translate);
        }


        // Config
        echo $r . "Default System Configuration\n";
        echo $r . str_repeat('-', $width) . "\n";

        if ($config->default && $config->default) {
            foreach ($config->default->children() as $namespace) {
                foreach ($namespace->children() as $section) {
                    foreach ($section->children() as $key) {
                        echo $w . $namespace->getName();
                        echo $y . '/';
                        echo $w . $section->getName();
                        echo $y . '/';
                        echo $w . $key->getName();
                        $value = (string) $key;
                        if (strlen($value) > 0) {
                            echo $b . ' => ' . $w . $value;
                        }
                        echo "\n";
                    }
                }
            }
        }

        $this->_processReloadConfig();
    }

    protected function _processInfoTranslate($translate)
    {
        echo green() . "Translate\n";
        if ($translate->modules) {
            foreach ($translate->modules->children() as $child) {
                echo white() . $child->getName();
                echo blue() . ' => ';
                echo white() . $child->files->default . "\n";
            }
        }
        echo "\n";
    }

    protected function _processInfoLayout($layout)
    {
        echo green() . "Layout\n";
        if ($layout->updates) {
            foreach ($layout->updates->children() as $child) {
                echo white() . $child->getName();
                echo blue() . ' => ';
                echo white() . $child->file . "\n";
            }
        }
        echo "\n";
    }

    protected function _processInfoRouters($routers)
    {
        echo green() . "Routers\n";
        foreach ($routers->children() as $child) {
            echo white() . $child->getName() . "\n";
            if ($child->use) {
                echo yellow() . '  type' . white() . ': ' . $child->use . "\n";
            }
            if ($child->args) {
                if ($child->args->module) {
                    echo yellow() . '  module' . white() . ': ' . $child->args->module . "\n";
                }
                if ($child->args->frontName) {
                    echo yellow() . '  frontName' . white() . ': ' . $child->args->frontName . "\n";
                }
                if ($child->args->modules) {
                    foreach ($child->args->modules->children() as $subchild) {
                        echo red() . '  use ';
                        echo white() . $subchild;
                        if ($subchild['before']) {
                            echo red() . ' before ' . white() . $subchild['before'];
                        }
                        echo red() . ' for ' . white() . $subchild->getName() . "\n";
                    }
                }
            }
        }
        echo "\n";
    }

    protected function _processInfoEvents($events)
    {
        echo green() . "Events\n";
        $resourcesModels = array();
        foreach ($events->children() as $child) {
            $eventname = $child->getName();
            echo yellow() . $eventname . "\n";
            if ($child->observers) {
                foreach ($child->observers->children() as $subchild) {
                    echo white() . '  ' . $subchild->getName() . blue() . ' => ' . white() . $subchild->class . '::' . $subchild->method;
                    echo "\n";
                }
            }
        }
        echo "\n";
    }

    protected function _processReloadConfig()
    {
        $this->_config = null;
    }

    protected function _processRouter(array $params)
    {
        if (empty($params)) {
            $where = $this->prompt('Where? (enter for front)');
            if (empty($where)) {
                $where = 'front';
            }
        } else {
            $where = array_shift($params);
        }
        while (!in_array($where, array('front', 'admin'))) {
            $where = $this->prompt('Where? (enter for front)');
            if (empty($where)) {
                $where = 'front';
            }
        }

        if ($where == 'front') {
            $where .= 'end';
        }

        if (empty($params)) {
            do {
                $frontName = $this->prompt('Front name ?');
            } while (empty($frontName));
        } else {
            $frontName = array_shift($params);
        }

        // Config
        $config = $this->getConfig();

        if (!isset($config->{$where})) {
            $config->addChild($where);
        }

        // Routers
        if (!$routers = $config->{$where}->routers) {
            $routers = $config->{$where}->addChild('routers');
        }

        // Module
        $routerName = strtolower($this->getModuleName()) . ($where == 'admin' ? '_adminhtml' : '');
        if (!$moduleRoute = $routers->{$routerName}) {
            $moduleRoute = $routers->addChild($routerName);
        }

        // Use
        if (!$moduleRoute->use) {
            $moduleRoute->addChild('use', ($where == 'frontend') ? 'standard' : 'admin');
        }

        // Args
        if (!$args = $moduleRoute->args) {
            $args = $moduleRoute->addChild('args');
        }

        // module
        if (!$args->module) {
            $args->addChild('module', $this->getModuleName() . ($where == 'admin' ? '_Adminhtml' : ''));
        }

        // frontName
        if (!$args->frontName) {
            $args->addChild('frontName', $frontName);
        }

        $this->writeConfig();
    }

    protected function _processDefault(array $params)
    {
        if (empty($params)) {
            do {
                $name = $this->prompt("Name?");
            } while (empty($name));
        } else {
            $name = array_shift($params);
        }

        if (!count($params)) {
            do {
                $value = $this->prompt("Value?");
            } while ($value === '');
        } else {
            $value = array_shift($params);
        }

        // conf
        /* @var $config SimpleXMLElement */
        $config = $this->getConfig();
        if (!isset($config->default)) {
            $config->addChild('default');
        }
        $config = $config->default;

        $names = explode('/', strtolower($name));

        foreach ($names as $name) {
            if (!$config->$name) {
                $config->addChild($name);
            }
            $config = $config->$name;
        }

        $config[0] = $value;

        $this->writeConfig();
    }

    protected function _processLast(array $params)
    {
        if (null !== $this->_lastMethod) {
            $name = $this->_lastMethod;
            $this->$name(array_merge($this->_lastParams, $params));
        }
    }

    public function setLast($name = null, $params = array())
    {
        $this->_lastMethod = $name;
        if (!is_array($params)) {
            $params = array($params);
        }
        $this->_lastParams = $params;
    }

    protected function _processDelete()
    {
        do {
            $response = strtolower($this->prompt('Are you sure you want to delete the module ' . red() . $this->getModuleName() . white() . '? (yes/no)'));
        } while (!in_array($response, array('yes', 'no')));

        if ($response === 'yes') {
            $this->_rmdir($this->getModuleDir());
            @unlink($this->getAppDir() . 'etc/modules/' . $this->getModuleName() . '.xml');
            $this->_rmdir($this->getDesignDir('frontend') . strtolower($this->getModuleName()));
            $this->_rmdir($this->getDesignDir('adminhtml') . strtolower($this->getModuleName()));
            foreach ($this->getLocales() as $locale) {
                @unlink($this->getAppDir() . 'locale/' . $locale . '/' . $this->getModuleName() . '.csv');
            }
            $this->_namespace = null;
            $this->_module = null;
            $this->_pool = null;
            $this->_config = null;
        }
    }

    protected function _rmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir . $object) == "dir") $this->_rmdir($dir . $object . "/");
                    else @unlink($dir . $object);
                }
            }
            reset($objects);
            @rmdir($dir);
        }
    }

    protected function _processDepends(array $params)
    {
        if (empty($params)) {
            do {
                $params = $this->prompt('Modules?');
            } while (empty($params));
            $params = explode(' ', $params);
        }

        $config = $this->getconfig();
        $etc = simplexml_load_file($etcFilename = $this->getAppDir() . 'etc/modules/' . $this->getModuleName() . '.xml');

        if (!$configDepends = $config->modules->{$this->getModuleName()}->depends) {
            $configDepends = $config->modules->{$this->getModuleName()}->addChild('depends');
        }
        if (!$etcDepends = $etc->modules->{$this->getModuleName()}->depends) {
            $etcDepends = $etc->modules->{$this->getModuleName()}->addChild('depends');
        }


        foreach ($params as $module) {
            if ($module[0] == '-') {
                $module = substr($module, 1);
                if ($configDepends->{$module}) {
                    unset($configDepends->{$module});
                }
                if ($etcDepends->{$module}) {
                    unset($etcDepends->{$module});
                }
            } else {
                if (!$configDepends->{$module}) {
                    $configDepends->addChild($module);
                }
                if (!$etcDepends->{$module}) {
                    $etcDepends->addChild($module);
                }
            }
        }

        $this->writeConfig();

        // Write etc/modules
        $dom = new DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = 4;
        $dom->loadXML($etc->asXML());
        $tidy = tidy_parse_string($dom->saveXml(), array(
            'indent' => true,
            'input-xml' => true,
            'output-xml' => true,
            'add-xml-space' => false,
            'indent-spaces' => 4,
            'wrap' => 300
        ));
        $tidy->cleanRepair();
        file_put_contents($etcFilename, (string) $tidy);
        unset($dom);

        $this->setLast(__FUNCTION__);
    }

    protected function _processCron(array $params)
    {
        // Ask parts of cron ;)
        // Cron name
        if (empty($params)) {
            do {
                $line = $this->prompt('Identifier?');
            } while (empty($line));
        } else {
            $line = array_shift($params);
        }
        $identifier = $line;

        // Minutes
        if (empty($params)) {
            do {
                $line = $this->prompt('Minutes?');
            } while ($line !== '');
        } else {
            $line = array_shift($params);
        }
        $minutes = $line;

        // Hours
        if (empty($params)) {
            do {
                $line = $this->prompt('Hours?');
            } while ($line !== '');
        } else {
            $line = array_shift($params);
        }
        $hours = $line;

        // Days (0-31)
        if (empty($params)) {
            do {
                $line = $this->prompt('Days? (0-31)');
            } while ($line !== '');
        } else {
            $line = array_shift($params);
        }
        $days = $line;

        // Month
        if (empty($params)) {
            do {
                $line = $this->prompt('Month?');
            } while ($line !== '');
        } else {
            $line = array_shift($params);
        }
        $month = $line;

        // Week days (0-6)
        if (empty($params)) {
            do {
                $line = $this->prompt('Days of week?');
            } while ($line !== '');
        } else {
            $line = array_shift($params);
        }
        $daysWeek = $line;

        // Model
        if (empty($params)) {
            do {
                $line = $this->prompt('Model?');
            } while (empty($line));
        } else {
            $line = array_shift($params);
        }
        $model = $line;

        // Method
        if (empty($params)) {
            do {
                $line = $this->prompt('Method?');
            } while (empty($line));
        } else {
            $line = array_shift($params);
        }
        $method = $line;


        // Now the Config
        $config = $this->getConfig();
        if (!isset($config->crontab)) {
            $config->addChild('crontab');
        }
        if (!isset($config->crontab->jobs)) {
            $config->crontab->addChild('jobs');
        }

        // Our cron
        $cron = $config->crontab->jobs->addChild($identifier);
        $cron->addChild('schedule')->addChild('cron_expr');
        $cron->schedule->cron_expr = sprintf('%s %s %s %s %s', $minutes, $hours, $days, $month, $daysWeek);
        $cron->addChild('run')->addChild('model');
        $cron->run->model = sprintf('%s::%s', $model, $method);

        $this->writeConfig();
    }

    protected function _processEvent(array $params)
    {
        if (empty($params)) {
            do {
                $eventName = $this->prompt('Event?');
            } while (empty($eventName));
        } else {
            $eventName = array_shift($params);
        }

        if (empty($params)) {
            do {
                $name = $this->prompt('Name?');
            } while (empty($name));
        } else {
            $name = array_shift($params);
        }

        if (empty($params)) {
            do {
                $class = $this->prompt('Model Class?');
            } while (empty($class));
        } else {
            $class = array_shift($params);
        }

        if (empty($params)) {
            do {
                $method = $this->prompt('Method?');
            } while (empty($method));
        } else {
            $method = array_shift($params);
        }

        if (!empty($params)) {
            $where = array_shift($params);
        }
        while (!isset($where) || !in_array($where, array('front', 'admin', 'global'))) {
            $where = $this->prompt('Where? (enter for front)');
            if (empty($where)) {
                $where = 'front';
            }
        }
        if ($where == 'front') {
            $where = 'frontend';
        } elseif ($where == 'admin') {
            $where = 'adminhtml';
        } elseif ($where == 'global') {
            $where = 'global';
        }

        // Config
        $config = $this->getConfig();
        if (!isset($config->{$where})) {
            $config->addChild($where);
        }
        $where = $config->{$where};

        // Events
        if (!$events = $where->events) {
            $events = $where->addChild('events');
        }

        // Event
        if (!$event = $events->{$eventName}) {
            $event = $events->addChild($eventName);
            $observers = $event->addChild('observers');
        } else {
            $observers = $event->observers;
        }

        // Observers
        if (!$observer = $observers->{$name}) {
            $observer = $observers->addChild($name);
            $observer->addChild('class');
            $observer->addChild('method');
        }

        $observer->class = $class;
        $observer->method = $method;

        $this->writeConfig();

        $this->setLast(__FUNCTION__);
    }

    protected function _processEntity(array $params)
    {
        $this->_processResources(array());

        if (empty($params)) {
            do {
                $name = ucfirst($this->prompt('Entity?'));
            } while (empty($name));
        } else {
            $name = ucfirst(array_shift($params));
        }

        if (empty($params)) {
            do {
                $table = $this->prompt('Table?');
            } while (empty($table));
        } else {
            $table = array_shift($params);
        }

        $noFiles = false;
        if (!empty($params)) {
            $noFiles = (array_shift($params) == '-');
        }

        $config = $this->getConfig();
        if (!isset($config->global)) {
            $config->addChild('global');
        }
        $entities = $config->global->models->{strtolower($this->getModuleName() . '_mysql4')}->entities;

        $entity = implode('_', array_map('ucfirst', explode('_', $name)));

        $names = explode('_', $entity);
        $lastName = array_pop($names);
        $filename = $lastName . '.php';

        if ($entities->{strtolower($name)}) {
            echo red() . "Entity $entity already exist.\n" . white();
            $this->_processReloadConfig();
            return;
        }

        $entities->addChild(strtolower($name))->addChild('table', $table);
        $this->writeConfig();

        $dir = $this->getModuleDir('Model');

        $construct = $this->getTemplate('entity_class_construct', array(
            '{Entity}' => $entity,
            '{entity}' => strtolower($entity),
            '{resourceModel}' => strtolower($this->getModuleName() .  '/' . $entity)
        ));

        foreach ($names as $name) {
            if (!is_dir($dir = $dir . $name . '/')) {
                if (!$noFiles) {
                    mkdir($dir);
                }
            }
        }

        if (!$noFiles) {
            file_put_contents($dir . $filename, $this->getTemplate('model_class', array(
                '{Name}' => $entity,
                $this->getTag('new_method') => $construct . "\n\n" . $this->getTag('new_method')
            )));
        }

        $dir = $this->getModuleDir('Model') . 'Mysql4/';

        foreach ($names as $name) {
            if (!is_dir($dir = $dir . $name . '/')) {
                if (!$noFiles) {
                    mkdir($dir);
                }
            }
        }

        if (!$noFiles) {
            file_put_contents($dir . $filename, $this->getTemplate('mysql4_entity_class', array(
                '{Name}' => $entity,
                '{mainTable}' => strtolower($this->getModuleName() .  '/' . $entity),
                '{idField}' => strtolower($lastName) . '_id'
            )));
        }

        if (!is_dir($dir = $dir . $lastName . '/')) {
            if (!$noFiles) {
                mkdir($dir);
            }
        }
        if (!$noFiles) {
            file_put_contents($dir . 'Collection.php', $this->getTemplate('mysql4_collection_class', array(
                '{Name}' => $entity,
                '{model}' => strtolower($this->getModuleName() .  '/' . $entity)
            )));
        }

        $this->_processReloadConfig();

        $this->setLast(__FUNCTION__);
    }

    protected function _processUpgradeSql(array $params)
    {
        list($dir, $created) = $this->getModuleDir('sql', true);
        $dir = $dir . strtolower($this->getModuleName()) . '_setup/';
        $config = $this->getConfig();
        if (!isset($config->global)) {
            $config->addChild('global');
        }
        $global = $config->global;

        if ($created || !is_dir($dir) || !$global->resources || !$global->resources->{strtolower($this->getModuleName()) . '_setup'}) {
            echo "You need to execute setup command before upgrading.\n";
            $this->_processReloadConfig();
            return;
        }

        $version = $this->_processVersion(array(), true);
        if (empty($params)) {
            $from = $this->prompt('From which version? (enter for ' . $version . ')');
            if (empty($from)) {
                $from = $version;
            }
            do {
                $to = $this->prompt('To which version?');
            } while (empty($to));
        } else {
            if (count($params) === 1) {
                $from = $version;
                $to = array_shift($params);
            } else {
                $from = array_shift($params);
                $to = array_shift($params);
            }
        }
        echo 'Mysql4 Upgrade From ' . red() . $from . white() . ' to ' . red() . $to . white() . ".\n";

        $filename = $dir . 'mysql4-upgrade-' . $from . '-' . $to . '.php';
        if (!is_file($filename)) {
            file_put_contents($filename, $this->getTemplate('setup_class'));
        }

        $this->_processReloadConfig();

        $this->setLast(__FUNCTION__);
    }

    protected function _processSetupSql(array $params)
    {
        list($dir, $created) = $this->getModuleDir('sql', true);

        $config = $this->getconfig();
        if (!isset($config->global)) {
            $config->addChild('global');
        }
        $global = $config->global;
        if (!$global->resources || !$global->resources->{strtolower($this->getModuleName()) . '_setup'}) {
            if (!$resources = $global->resources) {
                $resources = $global->addChild('resources');
            }
            if (!$moduleSetup = $resources->{strtolower($this->getModuleName()) . '_setup'}) {
                $moduleSetup = $resources->addChild(strtolower($this->getModuleName()) . '_setup');
            }

            $setup = $moduleSetup->addChild('setup');
            $setup->addChild('module', $this->getModuleName());
            $setup->addChild('class', 'Mage_Core_Model_Resource_Setup');
            $this->writeConfig();
        }

        $dir = $dir . strtolower($this->getModuleName()) . '_setup/';

        if (!is_dir($dir)) {
            mkdir($dir);
        }

        $filename = $dir . 'mysql4-install-' . $this->getConfigVersion() . '.php';
        if (!is_file($filename)) {
            file_put_contents($filename, $this->getTemplate('setup_class'));
        }

        $this->_processReloadConfig();

        $this->setLast(__FUNCTION__);
    }

    protected function _processLayout(array $params)
    {
        if (!empty($params) && in_array($params[0], array('admin', 'front'))) {
            $where = $params[0];
        } else {
            do {
                $where = $this->prompt('Where? (enter for front)');
                if (empty($where)) {
                    $where = 'front';
                }
            } while (!in_array($where, array('admin', 'front')));
        }

        if ($where == 'admin') {
            $where = 'adminhtml';
        } else {
            $where = 'frontend';
        }

        $config = $this->getConfig();

        if (!isset($config->{$where})) {
            $config->addChild($where);
        }

        if (!isset($config->{$where}->layout)) {
            $file = strtolower($this->getModuleName()) . '.xml';
            $config->{$where}
                ->addChild('layout')
                ->addChild('updates')
                ->addChild(strtolower($this->getModuleName()))
                ->addChild('file', $file);
            $this->writeConfig();
            $dir = $this->getAppDir() . 'design/' . $where . '/';

            if ($this->_pool == 'community') {
                $dirs = array('base', 'default');
            } else {
                $dirs = explode('_', DESIGN);
            }

            foreach ($dirs as $d) {
                if (!is_dir($dir = $dir . $d . '/')) {
                    mkdir($dir);
                }
            }

            $dirs = array('layout', 'etc', 'template');

            foreach ($dirs as $d) {
                if (!is_dir($dd = $dir . $d . '/')) {
                    mkdir($dd);
                }
            }

            if (!file_exists($dir . 'layout/' . $file)) {
                file_put_contents($dir . 'layout/' . $file, $this->getTemplate('layout_xml'));
            }
        }

        $this->_processReloadConfig();

        $this->setLast(__FUNCTION__);
    }

    protected function _processAddTranslate()
    {
        $config = $this->getConfig();
        if (!isset($config->frontend)) {
            $config->addChild('frontend');
        }
        if (!isset($config->frontend->translate) && !isset($config->adminhtml->translate)) {
            $this->_processTranslate(array());
        }

        do {
            $translate = $this->prompt('Translate?');
        } while (empty($translate));

        $translate = str_replace('"', '""', $translate);

        foreach ($this->getLocales() as $locale) {
            $traduction = $this->prompt('Traduction for ' . red() . $locale . white() . '?');
            if (empty($traduction)) {
                $traduction = $translate;
            } else {
                $traduction = str_replace('"', '""', $traduction);
            }
            $dir = $this->getAppDir() . 'locale/' . $locale . '/';
            $filename = $dir . $this->getModuleName() . '.csv';
            if (is_dir($dir) && is_file($filename)) {
                $fp = fopen($filename, 'a');
                $str = '"' . $translate . '","' . $traduction . '"' . "\n";
                fputs($fp, $str, mb_strlen($str));
                fclose($fp);
            }
        }
    }

    protected function _processTranslate(array $params)
    {
        if (!empty($params) && in_array($params[0], array('admin', 'front'))) {
            $where = $params[0];
        } else {
            do {
                $where = $this->prompt('Where? (enter for front)');
                if (empty($where)) {
                    $where = 'front';
                }
            } while (!in_array($where, array('admin', 'front')));
        }

        if ($where == 'admin') {
            $where = 'adminhtml';
        } else {
            $where = 'frontend';
        }

        $config = $this->getConfig();

        if (!isset($config->{$where})) {
            $config->addChild($where);
        }

        if (!isset($config->{$where}->translate)) {
            $this->_processHelper(array('data', '-'));
            $config->{$where}
                ->addChild('translate')
                ->addChild('modules')
                ->addChild($this->getModuleName())
                ->addChild('files')
                ->addChild('default', $this->getModuleName() . '.csv');
            $this->writeConfig();

            foreach ($this->getLocales() as $locale) {
                $dir = $this->getAppDir() . 'locale/' . $locale . '/';
                if (!is_dir($dir)) {
                    mkdir($dir);
                }
                touch($dir . $this->getModuleName() . '.csv');
            }
        }

        $this->_processReloadConfig();

        $this->setLast(__FUNCTION__);
    }

    protected function _processVersion(array $params, $return = false)
    {
        $config = $this->getConfig();
        if (empty($params)) {
            $version = $config->modules->{$this->getModuleName()}->version;
        } else {
            $config->modules->{$this->getModuleName()}->version = $version = $params[0];
            file_put_contents($this->getConfigFilename(), $config->asXml());
        }

        $this->_processReloadConfig();

        if ($return) {
            return $version;
        }

        echo red() . "Current Version is " . white() . $version .   "\n";

        $this->setLast(__FUNCTION__);
    }

    protected function _processResources(array $params)
    {
        list($dir, $created) = $this->_createModelDir();

        $config = $this->getConfig();
        $models = $config->global->models;

        if (!$models->{strtolower($this->getModuleName())}->resourceModel) {
            $models->{strtolower($this->getModuleName())}->addChild('resourceModel', strtolower($this->getModuleName()) . '_mysql4');
            $mysql4 = $models->addChild(strtolower($this->getModuleName()) . '_mysql4');
            $mysql4->addChild('class', $this->getModuleName() . '_Model_Mysql4');
            $mysql4->addChild('entities');
            $this->writeConfig();
            mkdir($dir = $dir . 'Mysql4/');
        }

        $this->_processReloadConfig();

        $this->setLast(__FUNCTION__);
    }

    protected function _createModelDir()
    {
        list($dir, $created) = $this->getModuleDir('Model', true);

        if ($created) {
            $config = $this->getConfig();
            if (!isset($config->global)) {
                $config->addChild('global');
            }
            $global = $config->global;
            if (!isset($global['models'])) {
                $global->addChild('models')->addChild(strtolower($this->getModuleName()))->addChild('class', $this->getModuleName() . '_Model');
            }
            $this->writeConfig();
        }

        return array($dir, $created);
    }

    protected function _processModel(array $params, $type = 'model')
    {
        if (empty($params)) {
            do {
                $name = ucfirst($this->prompt('Class?'));
            } while (empty($name));
        } else {
            $name = ucfirst(array_shift($params));
        }
        $officialName = $name;

        list($dir, $created) = $this->_createModelDir();

        $names = array_map('ucfirst', explode('_', $name));
        $name = array_pop($names);

        foreach ($names as $rep) {
            $dir .= $rep . '/';
            if (!is_dir($dir)) {
                mkdir($dir);
            }
        }

        $filename = $dir . $name . '.php';
        if (!is_file($filename)) {
            file_put_contents($filename, $this->getTemplate('model_class', array('{Name}' => implode('_', $names) . (empty($names) ? '' : '_') . $name)));
        }

        if (empty($params)) {
            $params = explode(' ', $this->prompt('Methods?'));
        }

        $content = file_get_contents($filename);
        $this->replaceVarsAndMethods($content, $params, $type);
        file_put_contents($filename, $content);

        $this->setLast(__FUNCTION__, $officialName);
    }

    protected function _processController(array $params)
    {
        if (empty($params)) {
            do {
                $name = ucfirst($this->prompt('Name? (enter for index)'));
                if (empty($name)) {
                    $name = 'Index';
                }
            } while (empty($name));
        } else {
            $name = array_shift($params);
        }
        $officialName = $name;

        $isAdminhtml = stripos($officialName, 'admin') === 0;

        $names = array_map('ucfirst', explode('_', $name));
        $name = array_pop($names);

        $dir = $this->getModuleDir('controllers');
        foreach ($names as $rep) {
            $dir .= $rep . '/';
            if (!is_dir($dir)) {
                mkdir($dir);
            }
        }

        $filename = $dir . $name . 'Controller.php';
        if (!is_file($filename)) {
            file_put_contents($filename, $this->getTemplate('controller_class', array(
                '{Name}' => implode('_', $names) . (empty($names) ? '' : '_') . $name,
                'Mage_Core_Controller_Front_Action' => $isAdminhtml ? 'Mage_Adminhtml_Controller_Action' : 'Mage_Core_Controller_Front_Action'
            )));
        }

        if (empty($params)) {
            $params = explode(' ', $this->prompt('Action?'));
        }

        $content = file_get_contents($filename);
        $this->replaceVarsAndMethods($content, $params, 'action');
        file_put_contents($filename, $content);

        $this->setLast(__FUNCTION__, $officialName);
    }

    protected function _processBlock(array $params)
    {
        if (empty($params)) {
            do {
                $name = ucfirst($this->prompt('Class?'));
            } while (empty($name));
        } else {
            $name = array_shift($params);
        }
        $officialName = $name;

        $isAdminhtml = stripos($officialName, 'admin') === 0;

        $names = array_map('ucfirst', explode('_', $name));

        // Create file
        list($dir, $created) = $this->getModuleDir('Block', true);

        if ($created) {
            $config = $this->getConfig();
            if (!isset($config->global)) {
                $config->addChild('global');
            }
            $global = $config->global;
            if (!isset($global['blocks'])) {
                $global->addChild('blocks')->addChild(strtolower($this->getModuleName()))->addChild('class', $this->getModuleName() . '_Block');
            }
            $this->writeConfig();
        }

        $name = array_pop($names);

        foreach ($names as $rep) {
            $dir .= $rep . '/';
            if (!is_dir($dir)) {
                mkdir($dir);
            }
        }

        $filename = $dir . $name . '.php';
        if (!is_file($filename)) {
            file_put_contents($filename, $this->getTemplate('block_class', array(
                '{Name}' => implode('_', $names) . (empty($names) ? '' : '_') . $name,
                'Mage_Core_Block_Template' => $isAdminhtml ? 'Mage_Adminhtml_Block_Template' : 'Mage_Core_Block_Template'
            )));
        }

        $phtmlKey = array_search('-p', $params);
        if ($phtmlKey !== false) {
            unset($params[$phtmlKey]);
            $dir = $this->getDesignDir('frontend', 'template');
            $dirs = $names;
            array_unshift($dirs, strtolower($this->getModuleName()));
            foreach ($dirs as $rep) {
                $dir .= strtolower($rep) . '/';
                if (!is_dir($dir)) {
                    mkdir($dir);
                }
            }
            $phtmlFilepath = strtolower(implode('/', $dirs) . '/' . $name . '.phtml');
            $phtmlFilename = $dir . strtolower($name) . '.phtml';
            if (!is_file($phtmlFilename)) {
                file_put_contents($phtmlFilename, $this->getTemplate('phtml', array('{Name}' => implode('_', $names) . (empty($names) ? '' : '_') . $name)));
            }
            $type = lcfirst($this->_namespace) . '_' . strtolower($this->_module) . '/' . implode('_', array_map('lcfirst', explode('_', $officialName)));
            echo "\n" . white() . '<block type="' . red() . $type . white() . '" name="' . lcfirst($name) . '" as="' . red() . lcfirst($name) . white() . '" template="' . red() . $phtmlFilepath . white() . '" />' . "\n\n";
        }

        if (empty($params) && $phtmlKey === false) {
            $params = explode(' ', $this->prompt('Methods?'));
        }

        if ($phtmlKey !== false) {
            array_unshift($params, 'TEMPLATE=' . $phtmlFilepath);
        }

        $content = file_get_contents($filename);
        $this->replaceVarsAndMethods($content, $params);
        file_put_contents($filename, $content);

        $this->setLast(__FUNCTION__, $officialName);
    }

    protected function _processHelper(array $params)
    {
        if (empty($params)) {
            $name = ucfirst($this->prompt('Class? (enter for Data)'));
            if (empty($name)) {
                $name = 'Data';
            }
        } else {
            $name = ucfirst(array_shift($params));
        }
        $officialName = $name;

        // Create file
        list($dir, $created) = $this->getModuleDir('Helper', true);

        if ($created) {
            $config = $this->getConfig();
            if (!isset($config->global)) {
                $config->addChild('global');
            }
            $global = $config->global;
            if (!isset($global['helpers'])) {
                $global->addChild('helpers')->addChild(strtolower($this->getModuleName()))->addChild('class', $this->getModuleName() . '_Helper');
            }
            $this->writeConfig();
        }

        $names = array_map('ucfirst', explode('_', $name));
        $name = array_pop($names);

        foreach ($names as $rep) {
            $dir .= $rep . '/';
            if (!is_dir($dir)) {
                mkdir($dir);
            }
        }

        $filename = $dir . $name . '.php';
        if (!is_file($filename)) {
            file_put_contents($filename, $this->getTemplate('helper_class', array('{Name}' => implode('_', $names) . (empty($names) ? '' : '_') . $name)));
        }

        if (empty($params)) {
            $params = explode(' ', $this->prompt('Methods?'));
        }

        $content = file_get_contents($filename);
        $this->replaceVarsAndMethods($content, $params);
        file_put_contents($filename, $content);

        $this->setLast(__FUNCTION__, $officialName);
    }

    public function replaceVarsAndMethods(&$content, $params, $type = false)
    {
        while (!empty($params)) {
            $name = trim(array_shift($params));
            if (!empty($name) && $name !== "-") {
                $match = array();
                if (preg_match('`^([A-Z0-9_][A-Z0-9_]+)(?:=(.+))?$`', $name, $match)) { // const
                    $name = $match[1];
                    list($type, $value) = $this->getVarTypeAndValue(isset($match[2]) ? $match[2] : '');
                    $shortDesc = 'short_description_here';
                    if ($name == 'TEMPLATE') {
                        $shortDesc = 'Template filename for this block';
                    }
                    $const = $this->getTemplate('const_var', array(
                        '{name}' => $name,
                        '{type}' => $type,
                        '{value}' => $value,
                        'short_description_here' => $shortDesc
                    )) . "\n\n" . $this->getTag('new_const');
                    $content = str_replace($this->getTag('new_const'), $const, $content);
                } elseif (preg_match('`^\$([a-z_][a-z0-9_]+)(?:=(.+))?$`i', $name, $match)) { // var
                    $name = $match[1];
                    list($type, $value) = $this->getVarTypeAndValue(isset($match[2]) ? $match[2] : '');
                    if (strpos($name, '_') === 0) {
                        $var = $this->getTemplate('protected_var', array(
                            '{name}' => $name,
                            '{type}' => $type,
                            '{value}' => $value
                        )) . "\n\n" . $this->getTag('new_var');
                    } else {
                        $var = $this->getTemplate('public_var', array(
                            '{name}' => $name,
                            '{type}' => $type,
                            '{value}' => $value
                        )) . "\n\n" . $this->getTag('new_var');
                    }
                    $content = str_replace($this->getTag('new_var'), $var, $content);
                } elseif (preg_match('`^(_?)(_[a-z][a-z0-9_]*|[a-z][a-z0-9_]*)(\(\))?(?::([0-9a-zA-Z._]*))?(?:/(p))?$`i', $name, $match)) { // Method
                    $vars = false;
                    $return = '';
                    $name = $match[2];
                    if (isset($match[3]) && $match[3] == '()') {
                        $vars = $this->prompt('Params for ' . red() . $name . '()' . white() . '?');
                    }
                    if (isset($match[4])) {
                        $return = $match[4];
                        if (empty($return)) {
                            $return = $this->prompt('Return for ' . red() . $name . '()' . white() . '?');
                        }
                    }
                    $useParent = isset($match[5]) && $match[5] === 'p';
                    if ($name == '_construct' && $match[1] == '_') {
                        $method = $this->getTemplate('constructor_method', array(
                            '{params}' => $vars,
                            '// Code here' => (!$useParent ? '' : "parent::$name($vars);\n        ") . '// Code here'
                        )) . "\n\n" . $this->getTag('new_method');
                    } elseif ($match[1] == '_') {
                        $method = $this->getTemplate('protected_method', array(
                            '{name}' => $name,
                            '{params}' => $vars,
                            '{return}' => $return,
                            '// Code here' => (!$useParent ? '' : "parent::_$name($vars);\n        ") . '// Code here'
                        )) . "\n\n" . $this->getTag('new_method');
                    } else {
                        $method = $this->getTemplate('public_method', array(
                            '{name}' => $name . ($type == 'action' ? 'Action' : ''),
                            '{params}' => ($type == 'observer' && !$vars) ? 'Varien_Event_Observer $observer' : $vars,
                            '{return}' => $return,
                            '// Code here' => (!$useParent ? '' : "parent::$name($vars);\n        ") . '// Code here'
                        )) . "\n\n" . $this->getTag('new_method');
                    }
                    $content = str_replace($this->getTag('new_method'), $method, $content);
                } else {
                    echo "Bad syntax for " . red() . $name . white() . ".\n";
                }
            }
        }
    }

    public function getVarTypeAndValue($value)
    {
        if (strtolower($value) === 'null') {
            $type = 'null';
            $value = 'null';
        } elseif (strtolower($value) === 'array') {
            $type = 'array';
            $value = 'array()';
        } elseif (strtolower($value) === 'true' || strtolower($value) === 'false') {
            $type = 'bool';
            $value = strtolower($value) === 'true' ? 'true' : 'false';
        } elseif (is_numeric($value)) {
            if (intval($value) == $value) {
                $type = 'int';
            } else {
                $type = 'float';
            }
        } elseif (!empty($value)) {
            $value = "'" . str_replace("'", "\'", $value) . "'";
            $type = 'string';
        } else {
            $value = "null";
            $type = '';
        }
        return array($type, $value);
    }

    protected function _processModule(array $params = array(), $force = false)
    {
        if ($force || !$this->_namespace) {
            // Namespace
            if (isset($params[0])) {
                $this->_namespace = ucfirst($params[0]);
            } else {
                $this->_namespace = ucfirst($this->prompt("Namespace? (enter for " . COMPANY_NAME_SHORT . ")"));
                if (empty($this->_namespace)) {
                    $this->_namespace = COMPANY_NAME_SHORT;
                }
            }
            // Module
            if (isset($params[1])) {
                $this->_module = ucfirst($params[1]);
            } else {
                do {
                    $this->_module = ucfirst($this->prompt("Module?"));
                } while (empty($this->_module));
            }
            // Pool
            if (isset($params[2]) && in_array($params[2], array('local', 'community'))) {
                $this->_pool = $params[2];
            } else {
                $this->_pool = strtolower($this->prompt("Pool? (enter for local)"));
                if (empty($this->_pool)) {
                    $this->_pool = 'local';
                }
            }

            $filename = $this->getModuleDir('etc') . 'config.xml';
            if (!is_file($filename)) {
                file_put_contents($filename, $this->getTemplate('config_xml'));
                file_put_contents($filename, $this->getTemplate('config_xml'));
                file_put_contents(
                    $this->getAppDir() . 'etc/modules/' . $this->getModuleName() . '.xml',
                    $this->getTemplate(
                        'module_xml',
                        array('{pool}' => $this->_pool)
                    )
                );
            }

            $this->_config = null;

            echo red() . "Using: " . white() . $this->getModuleName() . ' in ' . $this->_pool . "\n";
        }

        $this->setLast();
    }

    protected function _processClean(array $params)
    {
        $cache  = false;
        $logs   = false;

        if (!count($params)) {
            $params = array('all');
        }

        foreach ($params as $param) {
            switch ($param) {
            case 'log':
            case 'logs':
                $logs = true;
                break;
            case 'cache':
                $cache = true;
                break;
            case 'all':
            default:
                $cache = true;
                $logs = true;
                break;
            }
        }

        $varDir = PWD . '/var/';
        if (is_dir($varDir)) {
            if ($logs) {
                $logDir = $varDir . 'log/';
                if (is_dir($logDir)) {
                    $files = glob("{$logDir}*.log");
                    foreach ($files as $file) {
                        $fp = fopen($file, 'w');
                        ftruncate($fp, 0);
                        fclose($fp);
                    }
                }
                echo green() . "[OK] Logs\n";
            }
            if ($cache) {
                $cacheDir = $varDir . 'cache/';
                if (is_dir($cacheDir)) {
                    $this->_rmdir($cacheDir);
                }
                $fpcDir = $varDir . 'full_page_cache/';
                if (is_dir($fpcDir)) {
                    $this->_rmdir($fpcDir);
                }
                echo green() . "[OK] Cache\n";
            }
        }
    }

    public function getLocales()
    {
        return explode(',', LOCALES);
    }

    public function getConfigFilename()
    {
        return $this->getModuleDir('etc') . 'config.xml';
    }

    public function getConfig()
    {
        if (is_null($this->_config)) {
            $this->_config = simplexml_load_file($this->getConfigFilename());
        }
        return $this->_config;
    }

    public function getConfigVersion()
    {
        $v = $this->getConfig()->modules->{$this->getModuleName()}->version;
        $this->_processReloadConfig();
        return $v;
    }

    public function writeConfig()
    {
        if (!is_null($this->_config)) {
            $dom = new DOMDocument('1.0');
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = 4;
            $dom->loadXML($this->_config->asXML());
            $tidy = tidy_parse_string($dom->saveXml(), array(
                'indent' => true,
                'input-xml' => true,
                'output-xml' => true,
                'add-xml-space' => false,
                'indent-spaces' => 4,
                'wrap' => 300
            ));
            $tidy->cleanRepair();
            file_put_contents($this->getConfigFilename(), (string) $tidy);
            unset($dom);
            $this->_config = null;
        }
    }

    public function getTemplate($name, array $vars = array())
    {
        if (!$this->_templates) {
            $fp = fopen(__FILE__, 'r');
            fseek($fp, __COMPILER_HALT_OFFSET__);
            $this->_templates = stream_get_contents($fp);
            fclose($fp);
        }
        $template = preg_replace('`^(?:.+)?BEGIN ' . $name . "\n(.+)\nEND " . $name . '(?:.+)?$`is', '$1', $this->_templates);

        $searchAndReplace = array(
            '<_?php' => '<?php',
            '<_?xml' => '<?xml',
            '{Module_Name}' => $this->getModuleName(),
            '{LICENSE}' => LICENSE,
            '{USER_NAME}' => utf8_encode(USER_NAME),
            '{USER_EMAIL}' => USER_EMAIL,
            '{Namespace}' => $this->_namespace,
            '{date_year}' => date('Y'),
            '{COMPANY_NAME}' => utf8_encode(COMPANY_NAME),
            '{COMPANY_URL}' => COMPANY_URL
        );

        if ($name !== 'copyright') {
            $searchAndReplace['{COPYRIGHT}'] = $this->getTemplate('copyright');
        }

        $template = str_replace(array_keys($searchAndReplace), array_values($searchAndReplace), $template);

        return str_replace(array_keys($vars), array_values($vars), $template);
    }

    public function getMiscDir()
    {
        return PWD . '/misc/';
    }

    public function getAppDir()
    {
        return PWD . '/app/';
    }

    public function getPoolDir()
    {
        $dir = $this->getAppDir() . 'code/' . $this->_pool . '/';
        if (!is_dir($dir)) {
            mkdir($dir);
        }
        return $dir;
    }

    public function getNamespaceDir()
    {
        $dir = $this->getPoolDir() . $this->_namespace . '/';
        if (!is_dir($dir)) {
            mkdir($dir);
        }
        return $dir;
    }

    public function getModuleDir($name = null, $getCreated = false)
    {
        if (is_null($name)) {
            $dir = $this->getNamespaceDir() . $this->_module . '/';
        } else {
            $dir = $this->getModuleDir() . $name . '/';
        }
        $created = false;
        if (!is_dir($dir)) {
            mkdir($dir);
            $created = true;
        }
        return (is_null($name) || !$getCreated) ? $dir : array($dir, $created);
    }

    public function getDesignDir($where, $child = '')
    {
        $dir = $this->getAppDir() . 'design/' . $where . '/';
        $names = explode('_', DESIGN);

        if ($child) {
            $names[] = strtolower($child);
        }

        foreach ($names as $name) {
            $dir .= $name . '/';
            if (!is_dir($dir)) {
                mkdir($dir);
            }
        }

        return $dir;
    }

    public function getModuleTitle()
    {
        if (!$this->_namespace) {
            return null;
        }
        return sprintf('%s %s', $this->_namespace, $this->_module);
    }

    public function getModuleName()
    {
        if (!$this->_namespace) {
            return null;
        }
        return $this->_namespace . '_' . $this->_module;
    }

    public function getTag($name)
    {
        return '// ' . COMPANY_NAME . ' Tag ' . strtoupper($name);
    }

    public function prompt($text)
    {
        echo white() . $text . "\n" . blue() . '> ' . white();
        return $this->_read(false);
    }

    protected function _read($usePrompt = true)
    {
        $prompt = null;
        if ($usePrompt) {
            $prompt = white() . $this->getModuleName() . red() . '> ' . white();
        }
        $line = trim(readline($prompt));
        if (!empty($line)) {
            readline_add_history($line);
        }
        $continue = false;
        return $line;
    }

}

class Installer_Exception extends Exception
{}

$installer = new Installer($argv);

__HALT_COMPILER();

?>

BEGIN copyright
/**
 * This file is part of {Module_Name} for Magento.
 *
 * @license {LICENSE}
 * @author {USER_NAME} <{USER_EMAIL}>
 * @category {Namespace}
 * @package {Module_Name}
 * @copyright Copyright (c) {date_year} {COMPANY_NAME} ({COMPANY_URL})
 */
END copyright

BEGIN module_xml
<_?xml version="1.0" encoding="utf-8" ?>
<!--
{COPYRIGHT}
-->
<config>
    <modules>
        <{Module_Name}>
            <active>true</active>
            <codePool>{pool}</codePool>
        </{Module_Name}>
    </modules>
</config>
END module_xml

BEGIN config_xml
<_?xml version="1.0" encoding="utf-8" ?>
<!--
{COPYRIGHT}
-->
<config>
    <modules>
        <{Module_Name}>
            <version>0.1.0</version>
        </{Module_Name}>
    </modules>
</config>
END config_xml

BEGIN model_class
<_?php
{COPYRIGHT}

/**
 * {Name} Model
 * @package {Module_Name}
 */
class {Module_Name}_Model_{Name} extends Mage_Core_Model_Abstract
{

// {COMPANY_NAME} Tag NEW_CONST

// {COMPANY_NAME} Tag NEW_VAR

// {COMPANY_NAME} Tag NEW_METHOD

}
END model_class

BEGIN controller_class
<_?php
{COPYRIGHT}

/**
 * {Name} Controller
 * @package {Module_Name}
 */
class {Module_Name}_{Name}Controller extends Mage_Core_Controller_Front_Action
{

// {COMPANY_NAME} Tag NEW_CONST

// {COMPANY_NAME} Tag NEW_VAR

// {COMPANY_NAME} Tag NEW_METHOD

}
END controller_class

BEGIN helper_class
<_?php
{COPYRIGHT}

/**
 * {Name} Helper
 * @package {Module_Name}
 */
class {Module_Name}_Helper_{Name} extends Mage_Core_Helper_Abstract
{

// {COMPANY_NAME} Tag NEW_CONST

// {COMPANY_NAME} Tag NEW_VAR

// {COMPANY_NAME} Tag NEW_METHOD

}
END helper_class

BEGIN block_class
<_?php
{COPYRIGHT}

/**
 * {Name} Block
 * @package {Module_Name}
 */
class {Module_Name}_Block_{Name} extends Mage_Core_Block_Template
{

// {COMPANY_NAME} Tag NEW_CONST

// {COMPANY_NAME} Tag NEW_VAR

// {COMPANY_NAME} Tag NEW_METHOD

}
END block_class

BEGIN public_method
    /**
     * short_description_here
     * @access public
     * @return {return}
     */
    public function {name}({params})
    {
        // Code here
    }
END public_method

BEGIN protected_method
    /**
     * short_description_here
     * @access protected
     * @return {return}
     */
    protected function _{name}({params})
    {
        // Code here
    }
END protected_method

BEGIN constructor_method
    /**
     * Main Constructor
     * @access public
     * @return void
     */
    public function __construct({params})
    {
        // Code here
    }
END constructor_method

BEGIN setup_class
<_?php
{COPYRIGHT}

try {

    /* @var $installer Mage_Core_Model_Resource_Setup */
    $installer = $this;
    $installer->startSetup();



    $installer->endSetup();

} catch (Exception $e) {
    // Silence is golden
}

END setup_class

BEGIN entity_class_construct
    /**
     * Prefix of model events names
     * @var string
     */
    protected $_eventPrefix = '{entity}';

    /**
     * Parameter name in event
     * In observe method you can use $observer->getEvent()->getObject() in this case
     * @var string
     */
    protected $_eventObject = '{entity}';

    /**
     * {Entity} Constructor
     * @access protected
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_init('{resourceModel}');
    }
END entity_class_construct

BEGIN mysql4_entity_class
<_?php
{COPYRIGHT}

/**
 * Resource Model of {Name}
 * @package {Module_Name}
 */
class {Module_Name}_Model_Mysql4_{Name} extends Mage_Core_Model_Mysql4_Abstract
{

// {COMPANY_NAME} Tag NEW_CONST

// {COMPANY_NAME} Tag NEW_VAR

    /**
     * {Name} Resource Constructor
     * @access protected
     * @return void
     */
    protected function _construct()
    {
        $this->_init('{mainTable}', '{idField}');
    }

// {COMPANY_NAME} Tag NEW_METHOD

}
END mysql4_entity_class

BEGIN mysql4_collection_class
<_?php
{COPYRIGHT}

/**
 * Collection of {Name}
 * @package {Module_Name}
 */
class {Module_Name}_Model_Mysql4_{Name}_Collection extends Mage_Core_Model_Mysql4_Collection_Abstract
{

// {COMPANY_NAME} Tag NEW_CONST

// {COMPANY_NAME} Tag NEW_VAR

    /**
     * {Name} Collection Resource Constructor
     * @access protected
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_init('{model}');
    }

// {COMPANY_NAME} Tag NEW_METHOD

}
END mysql4_collection_class

BEGIN layout_xml
<_?xml version="1.0" encoding="utf-8" ?>
<!--
{COPYRIGHT}
-->
<layout version="0.1.0">

</layout>
END layout_xml

BEGIN const_var
    /**
     * short_description_here
     * @const {name} {type}
     */
    const {name} = {value};
END const_var

BEGIN protected_var
    /**
     * short_description_here
     * @access protected
     * @var {type}
     */
    protected ${name} = {value};
END protected_var

BEGIN public_var
    /**
     * short_description_here
     * @access public
     * @var {type}
     */
    public ${name} = {value};
END public_var

BEGIN phtml
<_?php
{COPYRIGHT}
/* @var $this {Module_Name}_Block_{Name} */
?>

END phtml

BEGIN grid_container_block
<_?php
{COPYRIGHT}

/**
 * {Entity} Grid Container
 * @package {Module_Name}
 */
class {Module_Name}_Block_{Name} extends Mage_Adminhtml_Block_Widget_Grid_Container
{

// {COMPANY_NAME} Tag NEW_CONST

// {COMPANY_NAME} Tag NEW_VAR

    /**
     * Constructor Override
     * @access protected
     * @return {Module_Name}_Block_{Name}
     */
    protected function _construct()
    {
        parent::_construct();

        $this->_blockGroup = '{blockGroup}';
        $this->_controller = '{controller}';
        $this->_headerText = $this->__('');

        return $this;
    }

    /**
     * Prepare Layout
     * @access protected
     * @return {Module_Name}_Block_{Name}
     */
    protected function _prepareLayout()
    {
        //$this->_removeButton('add');
        return parent::_prepareLayout();
    }

// {COMPANY_NAME} Tag NEW_METHOD

}
END grid_container_block

BEGIN grid_block
<_?php
{COPYRIGHT}

/**
 * {Entity} Grid
 * @package {Module_Name}
 */
class {Module_Name}_Block_{Name} extends Mage_Adminhtml_Block_Widget_Grid
{

// {COMPANY_NAME} Tag NEW_CONST

// {COMPANY_NAME} Tag NEW_VAR

    /**
     * Get collection object
     * @access public
     * @return {Collection_Model}
     */
    public function getCollection()
    {
        if (!parent::getCollection()) {
            $collection = Mage::getResourceModel('{resource_model_collection}');
            $this->setCollection($collection);
        }

        return parent::getCollection();
    }

    /**
     * Prepare columns
     * @access protected
     * @return {Module_Name}_Block_{Name}
     */
    protected function _prepareColumns()
    {
        return parent::_prepareColumns();
    }

// {COMPANY_NAME} Tag NEW_METHOD

}
END grid_block

BEGIN misc
<_?php
{COPYRIGHT}

// Mage !
require_once __DIR__ . '/../app/Mage.php';

// Init Magento
Mage::app('admin');

// Init store (needed for save products, for example)
Mage::app()->setCurrentStore(Mage_Core_Model_App::ADMIN_STORE_ID);

// code here
END misc

BEGIN doc_readme
# title

Enter the module description here ;).

END doc_readme


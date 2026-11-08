<?php
/**
 * PrestaShop harness for statsdata module tests (PHP 5.4+ compatible).
 */

if (!defined('_PS_VERSION_')) {
    define('_PS_VERSION_', '9.9.9-test');
}
if (!defined('_DB_PREFIX_')) {
    define('_DB_PREFIX_', 'ps_');
}
if (!defined('_COOKIE_KEY_')) {
    define('_COOKIE_KEY_', 'cookie-key-fixture');
}

$GLOBALS['STATS_ROOT'] = dirname(dirname(__DIR__));

class Db
{
    /** @var Db */
    private static $instance;

    /** @var PDO */
    private $pdo;

    /** @var string */
  public $lastSql = '';

    public function __construct()
    {
        $host = getenv('DB_HOST') ? getenv('DB_HOST') : '127.0.0.1';
        $port = getenv('DB_PORT') ? getenv('DB_PORT') : '3306';
        $name = getenv('DB_NAME') ? getenv('DB_NAME') : 'statsdata';
        $user = getenv('DB_USER') ? getenv('DB_USER') : 'statsdata';
        $pass = getenv('DB_PASSWORD') ? getenv('DB_PASSWORD') : 'statsdata';
        $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $name . ';charset=utf8';
        $this->pdo = new PDO($dsn, $user, $pass, array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ));
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public static function resetInstance()
    {
        self::$instance = null;
    }

    public function getPdo()
    {
        return $this->pdo;
    }

    public function getRow($sql)
    {
        $this->lastSql = $sql;
        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return false;
        }

        return $row;
    }

    public function executeSchema($sql)
    {
        $this->pdo->exec($sql);
    }
}

class Configuration
{
    public static function get($name)
    {
        $stmt = Db::getInstance()->getPdo()->prepare(
            'SELECT value FROM ' . _DB_PREFIX_ . 'configuration WHERE name = ?'
        );
        $stmt->execute(array($name));
        $value = $stmt->fetchColumn();
        if ($value === false) {
            return false;
        }

        return $value;
    }

    public static function updateValue($name, $value)
    {
        $pdo = Db::getInstance()->getPdo();
        $stmt = $pdo->prepare(
            'INSERT INTO ' . _DB_PREFIX_ . 'configuration (name, value) VALUES (?, ?) '
            . 'ON DUPLICATE KEY UPDATE value = VALUES(value)'
        );
        $stmt->execute(array($name, (string) $value));
    }
}

class Tools
{
    private static $shopProtocol = 'http://';

    public static function setShopProtocol($protocol)
    {
        self::$shopProtocol = $protocol;
    }

    public static function getShopProtocol()
    {
        return self::$shopProtocol;
    }

    public static function isSubmit($key)
    {
        return isset($_POST[$key]) || isset($_GET[$key]);
    }

    public static function getValue($key, $default = null)
    {
        if (array_key_exists($key, $_POST)) {
            return $_POST[$key];
        }
        if (array_key_exists($key, $_GET)) {
            return $_GET[$key];
        }

        return $default;
    }

    public static function getAdminTokenLite($controller)
    {
        return 'admintoken';
    }
}

class Cookie
{
    public $id_guest;
    public $id_customer;
    public $id_connections;
}

class Guest
{
    public $id;
    public $id_customer;

    public function __construct($id = null)
    {
        $this->id = $id === null ? null : (int) $id;
        $this->id_customer = 0;
        if ($this->id !== null) {
            $stmt = Db::getInstance()->getPdo()->prepare(
                'SELECT id_customer FROM ' . _DB_PREFIX_ . 'guest WHERE id_guest = ?'
            );
            $stmt->execute(array($this->id));
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $this->id_customer = (int) $row['id_customer'];
            }
        }
    }

    public function update()
    {
        $pdo = Db::getInstance()->getPdo();
        $stmt = $pdo->prepare(
            'UPDATE ' . _DB_PREFIX_ . 'guest SET id_customer = ? WHERE id_guest = ?'
        );
        $stmt->execute(array((int) $this->id_customer, (int) $this->id));
    }

    public function add()
    {
        $pdo = Db::getInstance()->getPdo();
        $stmt = $pdo->prepare(
            'INSERT INTO ' . _DB_PREFIX_ . 'guest (id_customer) VALUES (?)'
        );
        $stmt->execute(array((int) $this->id_customer));
        $this->id = (int) $pdo->lastInsertId();
    }

    public static function setNewGuest($cookie)
    {
        $guest = new Guest();
        $guest->id_customer = 0;
        $guest->add();
        $cookie->id_guest = (int) $guest->id;
    }

    public function mergeWithCustomer($idGuest, $idCustomer)
    {
        $this->id = (int) $idGuest;
        $this->id_customer = (int) $idCustomer;
    }
}

class Connection
{
    public static $next = array(
        'id_connections' => 10,
        'id_page' => 20,
        'time_start' => '2016-01-02 03:04:05',
    );

    public static function setPageConnection($cookie)
    {
        $pdo = Db::getInstance()->getPdo();
        $idGuest = isset($cookie->id_guest) ? (int) $cookie->id_guest : 0;
        $stmt = $pdo->prepare(
            'INSERT INTO ' . _DB_PREFIX_ . 'page_connection_log (id_guest) VALUES (?)'
        );
        $stmt->execute(array($idGuest));

        return self::$next;
    }
}

class ConnectionsSource
{
    public static function logHttpReferer()
    {
        $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        $pdo = Db::getInstance()->getPdo();
        $stmt = $pdo->prepare(
            'INSERT INTO ' . _DB_PREFIX_ . 'connections_source (http_referer) VALUES (?)'
        );
        $stmt->execute(array($referer));
    }
}

class Page
{
    public static function setPageViewed($idPage)
    {
        $pdo = Db::getInstance()->getPdo();
        $stmt = $pdo->prepare(
            'UPDATE ' . _DB_PREFIX_ . 'page_viewed SET counter = counter + 1 WHERE id_page = ?'
        );
        $stmt->execute(array((int) $idPage));
    }
}

class Link
{
    public function getPageLink($controller, $ssl)
    {
        if ($ssl) {
            return 'https://shop.test/index.php?controller=statistics';
        }

        return 'http://shop.test/index.php?controller=statistics';
    }

    public function getAdminLink($controller, $withToken)
    {
        return 'index.php?controller=AdminModules';
    }
}

class Language
{
    public $id;

    public function __construct($id)
    {
        $this->id = (int) $id;
    }
}

class ControllerStub
{
  public $javascriptRegistrations = array();

    public function registerJavascript($id, $path, $options = array())
    {
        $this->javascriptRegistrations[] = array(
            'id' => $id,
            'path' => $path,
            'position' => isset($options['position']) ? $options['position'] : null,
            'priority' => isset($options['priority']) ? $options['priority'] : null,
        );
    }

    public function getLanguages()
    {
        return array();
    }
}

class Context
{
    /** @var Context */
    private static $instance;

    /** @var Cookie */
    public $cookie;

    /** @var ControllerStub */
    public $controller;

    /** @var Link */
    public $link;

    /** @var Language */
    public $language;

    public static function getContext()
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->cookie = new Cookie();
            self::$instance->controller = new ControllerStub();
            self::$instance->link = new Link();
            self::$instance->language = new Language(1);
        }

        return self::$instance;
    }

    public static function reset()
    {
        self::$instance = null;
    }
}

class HelperForm
{
    public $show_toolbar;
    public $table;
    public $default_form_language;
    public $allow_employee_form_lang;
    public $identifier;
    public $submit_action;
    public $currentIndex;
    public $token;
    public $tpl_vars = array();

    public function generateForm($forms)
    {
        $html = '';
        $html .= 'submit_action:' . $this->submit_action . ';';
        $html .= 'currentIndex:' . $this->currentIndex . ';';
        $html .= 'token:' . $this->token . ';';
        $html .= 'default_form_language:' . $this->default_form_language . ';';
        $html .= 'allow_employee_form_lang:' . $this->allow_employee_form_lang . ';';
        if (isset($this->tpl_vars['fields_value']) && is_array($this->tpl_vars['fields_value'])) {
            foreach ($this->tpl_vars['fields_value'] as $name => $value) {
                $html .= 'field:' . $name . '=' . $value . ';';
            }
        }
        if (isset($forms[0]['form']['input'])) {
            foreach ($forms[0]['form']['input'] as $input) {
                if (isset($input['name'])) {
                    $html .= 'input:' . $input['name'] . ';';
                }
            }
        }

        return $html;
    }
}

class Module
{
    public $name;
    public $tab;
    public $version;
    public $author;
    public $need_instance;
    public $bootstrap;
    public $displayName;
    public $description;
    public $ps_versions_compliancy;
    public $table = 'module';
    public $identifier = 'id_module';

    /** @var Context */
    public $context;

    /** @var bool */
    public static $parentInstallResult = true;

    /** @var array */
    public static $registerHookFailures = array();

    public function __construct()
    {
        $this->context = Context::getContext();
    }

    public function install()
    {
        return (bool) self::$parentInstallResult;
    }

    public function registerHook($hook)
    {
        if (in_array($hook, self::$registerHookFailures, true)) {
            return false;
        }
        $pdo = Db::getInstance()->getPdo();
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO ' . _DB_PREFIX_ . 'hook_module (hook_name) VALUES (?)'
        );
        $stmt->execute(array($hook));

        return true;
    }

    public function unregisterHook($hook)
    {
        $pdo = Db::getInstance()->getPdo();
        $stmt = $pdo->prepare(
            'DELETE FROM ' . _DB_PREFIX_ . 'hook_module WHERE hook_name = ?'
        );
        $stmt->execute(array($hook));

        return true;
    }

    public function trans($string, $parameters, $domain)
    {
        return $string;
    }

    public function displayConfirmation($message)
    {
        return $message;
    }
}

function statsdata_bootstrap_schema()
{
    $pdo = Db::getInstance()->getPdo();
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . 'guest (
            id_guest INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            id_customer INT UNSIGNED NOT NULL DEFAULT 0,
            KEY id_customer (id_customer)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . 'configuration (
            name VARCHAR(254) NOT NULL PRIMARY KEY,
            value VARCHAR(255) NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . 'hook_module (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            hook_name VARCHAR(64) NOT NULL UNIQUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . 'page_viewed (
            id_page INT UNSIGNED NOT NULL PRIMARY KEY,
            counter INT UNSIGNED NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . 'connections_source (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            http_referer VARCHAR(255) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
    );
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ' . _DB_PREFIX_ . 'page_connection_log (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            id_guest INT UNSIGNED NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8'
    );
}

require_once $GLOBALS['STATS_ROOT'] . '/statsdata.php';
require_once __DIR__ . '/StatsdataTestCase.php';

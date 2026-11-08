<?php

class StatsdataTestCase extends PHPUnit_Framework_TestCase
{
    /** @var statsdata */
    protected $module;

    protected function setUp()
    {
        Db::resetInstance();
        statsdata_bootstrap_schema();
        $pdo = Db::getInstance()->getPdo();
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach (array(
            'guest',
            'configuration',
            'hook_module',
            'page_viewed',
            'connections_source',
            'page_connection_log',
        ) as $table) {
            $pdo->exec('TRUNCATE TABLE ' . _DB_PREFIX_ . $table);
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');

        $_GET = array();
        $_POST = array();
        if (isset($_SERVER['HTTP_REFERER'])) {
            unset($_SERVER['HTTP_REFERER']);
        }

        Context::reset();
        Connection::$next = array(
            'id_connections' => 10,
            'id_page' => 20,
            'time_start' => '2016-01-02 03:04:05',
        );
        Tools::setShopProtocol('http://');
        Module::$parentInstallResult = true;
        Module::$registerHookFailures = array();

        Configuration::updateValue('PS_LANG_DEFAULT', '1');

        $this->module = new statsdata();
    }

    protected function seedPageCounters()
    {
        $pdo = Db::getInstance()->getPdo();
        $stmt = $pdo->prepare(
            'INSERT INTO ' . _DB_PREFIX_ . 'page_viewed (id_page, counter) VALUES (?, 0)'
        );
        $stmt->execute(array(20));
        $stmt->execute(array(21));
    }

    protected function insertGuest($idGuest, $idCustomer)
    {
        $pdo = Db::getInstance()->getPdo();
        $stmt = $pdo->prepare(
            'INSERT INTO ' . _DB_PREFIX_ . 'guest (id_guest, id_customer) VALUES (?, ?)'
        );
        $stmt->execute(array((int) $idGuest, (int) $idCustomer));
    }

    protected function fetchConfig($name)
    {
        return Configuration::get($name);
    }

    protected function fetchGuestRow($idGuest)
    {
        $stmt = Db::getInstance()->getPdo()->prepare(
            'SELECT id_guest, id_customer FROM ' . _DB_PREFIX_ . 'guest WHERE id_guest = ?'
        );
        $stmt->execute(array((int) $idGuest));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertInternalType('array', $row);

        return $row;
    }

    protected function hookParamsWithCookie($cookie = null)
    {
        if ($cookie === null) {
            $cookie = Context::getContext()->cookie;
        }

        return array('cookie' => $cookie);
    }
}

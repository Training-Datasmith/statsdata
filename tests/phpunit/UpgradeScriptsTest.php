<?php

class UpgradeScriptsTest extends StatsdataTestCase
{
    public function testUpgrade211MovesAuthenticationHook()
    {
        $pdo = Db::getInstance()->getPdo();
        $pdo->prepare(
            'INSERT INTO ' . _DB_PREFIX_ . 'hook_module (hook_name) VALUES (?)'
        )->execute(array('authentication'));

        require_once $GLOBALS['STATS_ROOT'] . '/upgrade/upgrade-2.1.1.php';
        $result = upgrade_module_2_1_1($this->module);
        $this->assertTrue($result);

        $hooks = $this->fetchHooks();
        $this->assertContains('actionAuthentication', $hooks);
        $this->assertNotContains('authentication', $hooks);
    }

    public function testUpgrade212MovesCreateAccountHook()
    {
        $pdo = Db::getInstance()->getPdo();
        $pdo->prepare(
            'INSERT INTO ' . _DB_PREFIX_ . 'hook_module (hook_name) VALUES (?)'
        )->execute(array('createAccount'));

        require_once $GLOBALS['STATS_ROOT'] . '/upgrade/upgrade-2.1.2.php';
        $result = upgrade_module_2_1_2($this->module);
        $this->assertTrue($result);

        $hooks = $this->fetchHooks();
        $this->assertContains('actionCustomerAccountAdd', $hooks);
        $this->assertNotContains('createAccount', $hooks);
    }

    private function fetchHooks()
    {
        $stmt = Db::getInstance()->getPdo()->query(
            'SELECT hook_name FROM ' . _DB_PREFIX_ . 'hook_module'
        );
        $hooks = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $hooks[] = $row['hook_name'];
        }

        return $hooks;
    }
}

<?php

class StatsdataInstallTest extends StatsdataTestCase
{
    public function testInstallRegistersTheThreeHooks()
    {
        $result = $this->module->install();
        $this->assertInternalType('bool', $result);
        $this->assertTrue($result);
        $hooks = $this->fetchHookNames();
        sort($hooks);
        $this->assertSame(
            array('actionAuthentication', 'actionCustomerAccountAdd', 'displayBeforeBodyClosingTag'),
            $hooks
        );
    }

    public function testInstallStopsWhenParentInstallFails()
    {
        Module::$parentInstallResult = false;
        $result = $this->module->install();
        $this->assertFalse($result);
        $this->assertSame(array(), $this->fetchHookNames());
    }

    public function testInstallStopsWhenHookRegistrationFails()
    {
        Module::$registerHookFailures = array('actionAuthentication');
        $result = $this->module->install();
        $this->assertFalse($result);
        $hooks = $this->fetchHookNames();
        $this->assertContains('displayBeforeBodyClosingTag', $hooks);
        $this->assertNotContains('actionCustomerAccountAdd', $hooks);
    }

    private function fetchHookNames()
    {
        $stmt = Db::getInstance()->getPdo()->query(
            'SELECT hook_name FROM ' . _DB_PREFIX_ . 'hook_module ORDER BY hook_name'
        );
        $hooks = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $hooks[] = $row['hook_name'];
        }

        return $hooks;
    }
}

<?php

class StatsdataFrontHookTest extends StatsdataTestCase
{
    public function testNewGuestWithoutPluginsCreatesGuestAndReturnsEmptyPluginScript()
    {
        $this->seedPageCounters();
        $cookie = new Cookie();
        $html = $this->module->hookDisplayBeforeBodyClosingTag($this->hookParamsWithCookie($cookie));

        $this->assertSame('', $html);
        $stmt = Db::getInstance()->getPdo()->query(
            'SELECT COUNT(*) AS c FROM ' . _DB_PREFIX_ . 'guest'
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertInternalType('array', $row);
        $this->assertInternalType('string', $row['c']);
        $this->assertSame('1', $row['c']);
        $this->assertInternalType('int', $cookie->id_guest);

        $counter = Db::getInstance()->getPdo()->query(
            'SELECT counter FROM ' . _DB_PREFIX_ . 'page_viewed WHERE id_page = 20'
        )->fetchColumn();
        $this->assertInternalType('string', $counter);
        $this->assertSame('0', $counter);
        $this->assertSame(array(), Context::getContext()->controller->javascriptRegistrations);
    }

    public function testExistingGuestSkipsPluginDetection()
    {
        $this->insertGuest(5, 0);
        Configuration::updateValue('PS_STATSDATA_PLUGINS', '1');
        $cookie = new Cookie();
        $cookie->id_guest = 5;

        $html = $this->module->hookDisplayBeforeBodyClosingTag($this->hookParamsWithCookie($cookie));

        $this->assertSame('', $html);
        $row = $this->fetchGuestRow(5);
        $this->assertInternalType('string', $row['id_customer']);
        $this->assertSame('0', $row['id_customer']);
        $this->assertInternalType('int', $cookie->id_guest);
        $this->assertSame(5, $cookie->id_guest);
        $this->assertSame(array(), Context::getContext()->controller->javascriptRegistrations);
    }

    public function testPluginScriptTokenAndLibraryForNewGuest()
    {
        Configuration::updateValue('PS_STATSDATA_PLUGINS', '1');
        Tools::setShopProtocol('https://');
        $cookie = new Cookie();

        $html = $this->module->hookDisplayBeforeBodyClosingTag($this->hookParamsWithCookie($cookie));

        $this->assertInternalType('int', $cookie->id_guest);
        $token = sha1((string) $cookie->id_guest . _COOKIE_KEY_);
        $this->assertContains('navinfo', $html);
        $this->assertContains('ajax', $html);
        foreach (array(
            'adobe_director',
            'adobe_flash',
            'apple_quicktime',
            'windows_media',
            'sun_java',
            'real_player',
        ) as $plugin) {
            $this->assertContains($plugin, $html);
        }
        $this->assertContains((string) $cookie->id_guest, $html);
        $this->assertContains($token, $html);
        $this->assertContains('https://shop.test/index.php?controller=statistics', $html);
        $this->assertNotContains('sendBeacon', $html);

        $regs = Context::getContext()->controller->javascriptRegistrations;
        $this->assertCount(1, $regs);
        $this->assertSame('modules-plugindetect', $regs[0]['id']);
        $this->assertSame('modules/statsdata/js/plugindetect.js', $regs[0]['path']);
        $this->assertSame('bottom', $regs[0]['position']);
        $this->assertSame(150, $regs[0]['priority']);

        $path = $GLOBALS['STATS_ROOT'] . '/js/plugindetect.js';
        $this->assertFileExists($path);
        $this->assertGreaterThan(0, filesize($path));
    }

    public function testPageTrackingAlwaysRecordsConnectionAndReferer()
    {
        $this->insertGuest(5, 0);
        $_SERVER['HTTP_REFERER'] = 'http://referrer.example/start';
        $cookie = new Cookie();
        $cookie->id_guest = 5;

        $html = $this->module->hookDisplayBeforeBodyClosingTag($this->hookParamsWithCookie($cookie));

        $this->assertSame('', $html);
        $source = Db::getInstance()->getPdo()->query(
            'SELECT http_referer FROM ' . _DB_PREFIX_ . 'connections_source'
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertInternalType('array', $source);
        $this->assertSame('http://referrer.example/start', $source['http_referer']);

        $count = Db::getInstance()->getPdo()->query(
            'SELECT COUNT(*) AS c FROM ' . _DB_PREFIX_ . 'page_connection_log'
        )->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('1', $count['c']);
    }

    public function testGlobalPageViewsIncrementOnlyTheReturnedPage()
    {
        $this->seedPageCounters();
        Configuration::updateValue('PS_STATSDATA_PAGESVIEWS', '1');
        $this->insertGuest(5, 0);
        $cookie = new Cookie();
        $cookie->id_guest = 5;

        $html = $this->module->hookDisplayBeforeBodyClosingTag($this->hookParamsWithCookie($cookie));

        $this->assertSame('', $html);
        $c20 = Db::getInstance()->getPdo()->query(
            'SELECT counter FROM ' . _DB_PREFIX_ . 'page_viewed WHERE id_page = 20'
        )->fetchColumn();
        $c21 = Db::getInstance()->getPdo()->query(
            'SELECT counter FROM ' . _DB_PREFIX_ . 'page_viewed WHERE id_page = 21'
        )->fetchColumn();
        $this->assertInternalType('string', $c20);
        $this->assertInternalType('string', $c21);
        $this->assertSame('1', $c20);
        $this->assertSame('0', $c21);
    }

    public function testCustomerPageViewBeaconToken()
    {
        $this->seedPageCounters();
        Configuration::updateValue('PS_STATSDATA_CUSTOMER_PAGESVIEWS', '1');
        $this->insertGuest(5, 0);
        $cookie = new Cookie();
        $cookie->id_guest = 5;

        $html = $this->module->hookDisplayBeforeBodyClosingTag($this->hookParamsWithCookie($cookie));

        $token = sha1('10202016-01-02 03:04:05' . _COOKIE_KEY_);
        $this->assertContains($token, $html);
        $this->assertContains('pagetime', $html);
        $this->assertContains('FormData', $html);
        $this->assertContains('sendBeacon', $html);
        $this->assertContains('ajax', $html);
        $this->assertContains('id_connections', $html);
        $this->assertContains('id_page', $html);
        $this->assertContains('time_start', $html);
        $this->assertContains('http://shop.test/index.php?controller=statistics', $html);
        $this->assertNotContains('navinfo', $html);

        $counter = Db::getInstance()->getPdo()->query(
            'SELECT counter FROM ' . _DB_PREFIX_ . 'page_viewed WHERE id_page = 20'
        )->fetchColumn();
        $this->assertSame('0', $counter);
    }

    public function testHookConcatenatesPluginScriptBeforePageScript()
    {
        $this->seedPageCounters();
        Configuration::updateValue('PS_STATSDATA_PLUGINS', '1');
        Configuration::updateValue('PS_STATSDATA_PAGESVIEWS', '1');
        Configuration::updateValue('PS_STATSDATA_CUSTOMER_PAGESVIEWS', '1');
        $cookie = new Cookie();

        $html = $this->module->hookDisplayBeforeBodyClosingTag($this->hookParamsWithCookie($cookie));

        $navPos = strpos($html, 'navinfo');
        $pagePos = strpos($html, 'pagetime');
        $this->assertInternalType('int', $navPos);
        $this->assertInternalType('int', $pagePos);
        $this->assertTrue($navPos < $pagePos);

        $counter = Db::getInstance()->getPdo()->query(
            'SELECT counter FROM ' . _DB_PREFIX_ . 'page_viewed WHERE id_page = 20'
        )->fetchColumn();
        $this->assertSame('1', $counter);
        $this->assertContains('sendBeacon', $html);
        $this->assertContains('navinfo', $html);
    }

    public function testStatisticsLinkFollowsShopProtocol()
    {
        Configuration::updateValue('PS_STATSDATA_CUSTOMER_PAGESVIEWS', '1');
        Tools::setShopProtocol('http://');
        $this->insertGuest(5, 0);
        $cookie = new Cookie();
        $cookie->id_guest = 5;

        $html = $this->module->hookDisplayBeforeBodyClosingTag($this->hookParamsWithCookie($cookie));

        $this->assertContains('http://shop.test/index.php?controller=statistics', $html);
        $this->assertFalse(strpos($html, 'https://shop.test/index.php?controller=statistics'));
    }
}

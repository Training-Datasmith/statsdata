<?php

class StatsdataMetadataTest extends StatsdataTestCase
{
    public function testConstructorPublishesModuleIdentity()
    {
        $this->assertSame('statsdata', $this->module->name);
        $this->assertSame('administration', $this->module->tab);
        $this->assertSame('2.1.2', $this->module->version);
        $this->assertSame('PrestaShop', $this->module->author);
        $this->assertInternalType('int', $this->module->need_instance);
        $this->assertSame(0, $this->module->need_instance);
        $this->assertInternalType('bool', $this->module->bootstrap);
        $this->assertTrue($this->module->bootstrap);
    }

    public function testConstructorPublishesTranslatedCopy()
    {
        $this->assertSame('Data mining for statistics', $this->module->displayName);
        $this->assertSame(
            'Collect as much information as possible to enrich your stats and run your business further.',
            $this->module->description
        );
    }

    public function testVersionCompliancyTracksShopVersion()
    {
        $this->assertSame('1.7.1.0', $this->module->ps_versions_compliancy['min']);
        $this->assertSame(_PS_VERSION_, $this->module->ps_versions_compliancy['max']);
    }

    public function testConfigXmlAgreesOnNameVersionAndFlags()
    {
        $xml = simplexml_load_file($GLOBALS['STATS_ROOT'] . '/config.xml');
        $this->assertInternalType('object', $xml);
        $this->assertSame('statsdata', (string) $xml->name);
        $this->assertSame($this->module->version, (string) $xml->version);
        $this->assertSame('0', (string) $xml->need_instance);
        $this->assertSame('1', (string) $xml->is_configurable);
    }
}

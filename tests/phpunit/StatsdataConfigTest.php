<?php

class StatsdataConfigTest extends StatsdataTestCase
{
    public function testGetContentWithoutSubmitLeavesConfigurationUntouched()
    {
        Configuration::updateValue('PS_STATSDATA_CUSTOMER_PAGESVIEWS', '1');
        Configuration::updateValue('PS_STATSDATA_PAGESVIEWS', '0');
        Configuration::updateValue('PS_STATSDATA_PLUGINS', '1');

        $html = $this->module->getContent();

        $this->assertNotContains('Settings updated', $html);
        $this->assertSame('1', $this->fetchConfig('PS_STATSDATA_CUSTOMER_PAGESVIEWS'));
        $this->assertSame('0', $this->fetchConfig('PS_STATSDATA_PAGESVIEWS'));
        $this->assertSame('1', $this->fetchConfig('PS_STATSDATA_PLUGINS'));
        $this->assertContains('PS_STATSDATA_CUSTOMER_PAGESVIEWS', $html);
        $this->assertContains('PS_STATSDATA_PAGESVIEWS', $html);
        $this->assertContains('PS_STATSDATA_PLUGINS', $html);
        $this->assertContains('field:PS_STATSDATA_CUSTOMER_PAGESVIEWS=1;', $html);
        $this->assertContains('field:PS_STATSDATA_PAGESVIEWS=0;', $html);
        $this->assertContains('field:PS_STATSDATA_PLUGINS=1;', $html);
    }

    public function testGetContentSubmitCastsValuesAndConfirms()
    {
        $_POST['submitStatsData'] = '1';
        $_POST['PS_STATSDATA_CUSTOMER_PAGESVIEWS'] = '1abc';
        $_POST['PS_STATSDATA_PAGESVIEWS'] = '';
        $_POST['PS_STATSDATA_PLUGINS'] = '2';

        $html = $this->module->getContent();

        $this->assertSame('1', $this->fetchConfig('PS_STATSDATA_CUSTOMER_PAGESVIEWS'));
        $this->assertSame('0', $this->fetchConfig('PS_STATSDATA_PAGESVIEWS'));
        $this->assertSame('2', $this->fetchConfig('PS_STATSDATA_PLUGINS'));
        $this->assertContains('Settings updated', $html);
        $this->assertContains('field:PS_STATSDATA_CUSTOMER_PAGESVIEWS=1abc;', $html);
        $this->assertContains('field:PS_STATSDATA_PAGESVIEWS=;', $html);
        $this->assertContains('field:PS_STATSDATA_PLUGINS=2;', $html);
    }

    public function testGetConfigFieldsValuesPrefersSubmittedValue()
    {
        Configuration::updateValue('PS_STATSDATA_CUSTOMER_PAGESVIEWS', '0');
        Configuration::updateValue('PS_STATSDATA_PAGESVIEWS', '1');
        Configuration::updateValue('PS_STATSDATA_PLUGINS', '0');
        $_POST['PS_STATSDATA_PAGESVIEWS'] = '1';

        $values = $this->module->getConfigFieldsValues();
        $this->assertInternalType('array', $values);
        $this->assertArrayHasKey('PS_STATSDATA_CUSTOMER_PAGESVIEWS', $values);
        $this->assertSame('0', $values['PS_STATSDATA_CUSTOMER_PAGESVIEWS']);
        $this->assertSame('1', $values['PS_STATSDATA_PAGESVIEWS']);
        $this->assertSame('0', $values['PS_STATSDATA_PLUGINS']);
    }

    public function testRenderFormPublishesSwitchesTokenAndIndex()
    {
        Configuration::updateValue('PS_LANG_DEFAULT', '2');
        $html = $this->module->renderForm();

        $this->assertContains('input:PS_STATSDATA_CUSTOMER_PAGESVIEWS;', $html);
        $this->assertContains('input:PS_STATSDATA_PAGESVIEWS;', $html);
        $this->assertContains('input:PS_STATSDATA_PLUGINS;', $html);
        $this->assertContains('submit_action:submitStatsData;', $html);
        $this->assertContains('token:admintoken;', $html);
        $this->assertContains('default_form_language:2;', $html);
        $this->assertContains(
            'currentIndex:index.php?controller=AdminModules&configure=statsdata&tab_module=administration&module_name=statsdata;',
            $html
        );
    }

    public function testRenderFormEmployeeLanguageFlag()
    {
        $html = $this->module->renderForm();
        $this->assertContains('allow_employee_form_lang:0;', $html);

        Configuration::updateValue('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', '3');
        $html = $this->module->renderForm();
        $this->assertContains('allow_employee_form_lang:3;', $html);
    }
}

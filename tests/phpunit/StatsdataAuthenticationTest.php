<?php

class StatsdataAuthenticationTest extends StatsdataTestCase
{
    public function testMergeKeepsTheCustomersExistingGuestOnTheCookie()
    {
        $this->insertGuest(5, 0);
        $this->insertGuest(9, 100);
        $this->insertGuest(8, 200);

        $cookie = new Cookie();
        $cookie->id_guest = 5;
        $cookie->id_customer = 100;

        $this->module->hookActionAuthentication(array('cookie' => $cookie));

        $this->assertInternalType('int', $cookie->id_guest);
        $this->assertSame(9, $cookie->id_guest);
        $control = $this->fetchGuestRow(8);
        $this->assertInternalType('string', $control['id_customer']);
        $this->assertSame('200', $control['id_customer']);
    }

    public function testCustomerIdIsCastBeforeTheGuestQuery()
    {
        $this->insertGuest(5, 0);
        $this->insertGuest(9, 100);
        $this->insertGuest(8, 200);

        $cookie = new Cookie();
        $cookie->id_guest = 5;
        $cookie->id_customer = '100abc';

        try {
            $this->module->hookActionAuthentication(array('cookie' => $cookie));
        } catch (PDOException $e) {
            // Strict SQL may throw before assertions; lastSql still records the statement.
        }

        $sql = Db::getInstance()->lastSql;
        $this->assertContains('`id_customer` = 100', $sql);
        $this->assertNotContains('100abc', $sql);
        $this->assertSame(9, $cookie->id_guest);
        $guestEight = $this->fetchGuestRow(8);
        $this->assertSame('200', $guestEight['id_customer']);

        $count = Db::getInstance()->getPdo()->query(
            'SELECT COUNT(*) AS c FROM ' . _DB_PREFIX_ . 'guest'
        )->fetchColumn();
        $this->assertSame('3', $count);
    }

    public function testUnownedGuestIsUpdatedInPlace()
    {
        $this->insertGuest(5, 0);
        $cookie = new Cookie();
        $cookie->id_guest = 5;
        $cookie->id_customer = 100;

        $this->module->hookActionAuthentication(array('cookie' => $cookie));

        $row = $this->fetchGuestRow(5);
        $this->assertInternalType('string', $row['id_customer']);
        $this->assertSame('100', $row['id_customer']);
        $this->assertSame(5, $cookie->id_guest);

        $count = Db::getInstance()->getPdo()->query(
            'SELECT COUNT(*) AS c FROM ' . _DB_PREFIX_ . 'guest'
        )->fetchColumn();
        $this->assertSame('1', $count);
    }

    public function testGuestAlreadyLinkedToAnotherCustomerIsDuplicated()
    {
        $this->insertGuest(5, 50);
        $cookie = new Cookie();
        $cookie->id_guest = 5;
        $cookie->id_customer = 100;

        $this->module->hookActionAuthentication(array('cookie' => $cookie));

        $row5 = $this->fetchGuestRow(5);
        $this->assertSame('50', $row5['id_customer']);

        $stmt = Db::getInstance()->getPdo()->query(
            'SELECT id_guest, id_customer FROM ' . _DB_PREFIX_ . 'guest WHERE id_customer = 100'
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertInternalType('array', $row);
        $this->assertNotSame('5', $row['id_guest']);
        $this->assertSame('100', $row['id_customer']);
    }

    public function testAccountCreationHookUpdatesTheGuestTheSameWay()
    {
        $this->insertGuest(5, 0);
        $cookie = new Cookie();
        $cookie->id_guest = 5;
        $cookie->id_customer = 100;

        $this->module->hookActionCustomerAccountAdd(array('cookie' => $cookie));

        $row = $this->fetchGuestRow(5);
        $this->assertSame('100', $row['id_customer']);
    }
}

<?php
/**
 * Юнит-тестирование goDB
 * Тестирование объекта-ссылки
 *
 * @package    goDB
 * @subpackage unittest
 * @version    1.3.0
 * @author     Григорьев Олег aka vasa_c
 * @link       http://pyha.ru/go/godb/godblink/
 * @link       http://pyha.ru/go/godb/unit/
 * @uses       PHPUnit
 */

require_once(dirname(__FILE__).'/base/goDBTestBase.php');
require_once(dirname(__FILE__).'/helpers/debug.php');
require_once(dirname(__FILE__).'/helpers/decorator.php');

class goDBLinkTest extends goDBTestBase {

    public function testCall() {
        $db = $this->db();

        $link = $db->getLinkObject();

        $this->assertEquals($db->client_version, $link->client_version);

        $this->assertFalse($db->inTransaction());
        $link->transactionBegin();
        $this->assertTrue($db->inTransaction());
        $link->transactionCommit();
        $this->assertFalse($db->inTransaction());
    }

    public function testPrefix() {

        $db = $this->db();

        $db->setPrefix('x_');
        $link = $db->getLinkObject();
        $this->assertEquals('x_', $link->getPrefix());
        $link->setPrefix('y_');

        $pattern = 'SELECT * FROM {table1}, {table2} AS `t2`';
        $qDB  = $db->makeQuery($pattern, null);
        $qL   = $link->makeQuery($pattern, null);
        $qL2  = $link->makeQuery($pattern, null, 'x_');

        $this->assertEquals('SELECT * FROM `x_table1`, `x_table2` AS `t2`', $qDB);
        $this->assertEquals('SELECT * FROM `y_table1`, `y_table2` AS `t2`', $qL);
        $this->assertEquals($qDB, $qL2);
    }

    public function testDebug() {

        $db   = $this->db(true);
        $link = $db->getLinkObject();

        $link->setPrefix('l_');

        $d1 = new goDBTestHelperDebug('DB');
        $d2 = new goDBTestHelperDebug('LINK');

        $db->setDebug(array($d1, 'debug'));
        $link->setDebug(array($d2, 'debug'));

        $pattern  = 'SELECT `caption` FROM `godb` AS {t} WHERE ?c=?i';
        $data     = array(array('t', 'id'), 3);
        $expected = 'five';

        $this->assertEquals($expected, $db->query($pattern, $data, 'el'));
        $this->assertEquals($expected, $link->query($pattern, $data, 'el'));

        $p1 = 'DB: SELECT `caption` FROM `godb` AS `t` WHERE `t`.`id`=3';
        $this->assertEquals($p1, $d1->getMessage());

    }

    public function testDecorator() {

        $db = $this->db(true);
        $link = $db->getLinkObject();

        $w1 = new goDBTestHelperDecorator('`id`=3', '`id`=4', '`id`=5');
        $w2 = new goDBTestHelperDecorator('`id`=4', '`id`=5', '`id`=6');

        $db->queryDecorated($w1->getCallback());
        $link->queryDecorated($w2->getCallback());

        $pattern = 'SELECT `id` FROM `godb` WHERE `id`=?i';

        $this->assertEquals(4, $db->query($pattern, array(3), 'el'));
        $this->assertEquals(4, $db->query($pattern, array(4), 'el'));
        $this->assertEquals(false, $db->query($pattern, array(5), 'el'));
        $this->assertEquals(6, $db->query($pattern, array(6), 'el'));

        $this->assertEquals(3, $link->query($pattern, array(3), 'el'));
        $this->assertEquals(5, $link->query($pattern, array(4), 'el'));
        $this->assertEquals(5, $link->query($pattern, array(5), 'el'));
        $this->assertEquals(false, $link->query($pattern, array(6), 'el'));
    }

}

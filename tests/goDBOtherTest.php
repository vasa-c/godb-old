<?php
/**
 * Юнит-тестирование goDB
 * Тестирование функций не вошедших в другие тесты
 *
 * @package    goDB
 * @subpackage unittest
 * @version    1.3.0
 * @author     Григорьев Олег aka vasa_c
 * @link       http://pyha.ru/go/godb/unit/
 * @link       http://pyha.ru/go/godb/etc/
 * @uses       PHPUnit
 */

require_once(dirname(__FILE__).'/base/goDBTestBase.php');
require_once(dirname(__FILE__).'/helpers/debug.php');
require_once(dirname(__FILE__).'/helpers/decorator.php');

class goDBOtherTest extends goDBTestBase {

    /**
     * Префикс таблиц
     *
     * @covers goDB::setPrefix
     * @covers goDB::getPrefix
     */
    public function testPrefix() {
        $db = $this->db();

        $pattern = 'SELECT * FROM `one` LEFT JOIN {two} ON `one`.`a`=?t.?c WHERE ?c=?i';
        $data    = array('two', 'a', array('two', 'b'), 10);

        $query   = 'SELECT * FROM `one` LEFT JOIN `two` ON `one`.`a`=`two`.`a` WHERE `two`.`b`=10';
        $result  = $db->makeQuery($pattern, $data);
        $this->assertEquals($query, $result);
        $this->assertEquals('', $db->getPrefix());

        $db->setPrefix('t_');
        $query   = 'SELECT * FROM `one` LEFT JOIN `t_two` ON `one`.`a`=`t_two`.`a` WHERE `t_two`.`b`=10';
        $result  = $db->makeQuery($pattern, $data);
        $this->assertEquals($query, $result);
        $this->assertEquals('t_', $db->getPrefix());

        $db->setPrefix(null);
    }

    /**
     * Подсчёт количества запросов
     * 
     * @covers goDB::getQQuery
     */
    public function testQQuery() {
        $prev = goDB::getQQuery();
        $this->db()->query('SHOW TABLES');
        $current = goDB::getQQuery();
        $this->assertEquals($current, $prev + 1);
    }

    /**
     * Отладочная информация
     * 
     * @covers goDB::setDebug
     */
    public function testDebug() {
        $db = $this->db();

        $debug = new goDBTestHelperDebug('D');

        $db->query('SHOW TABLES');
        $this->assertNull($debug->getMessage());
        $d = $debug->getCallback();
        $db->setDebug($d);
        $this->assertSame($d, $db->getDebug());
        $db->query('SHOW DATABASES');
        $db->setDebug(false);
        $this->assertEquals('D: SHOW DATABASES', $debug->getMessage());
        $this->debugQuery = null;
        $db->query('SHOW TABLES');
        $this->assertEquals('D: SHOW DATABASES', $debug->getMessage());
    }


    /**
     * Тестирование декоратора
     *
     * @covers goDB::queryDecorated
     */
    public function testDecorator() {
        $db = $this->db(true);

        $decor = new goDBTestHelperDecorator('LIMIT 3', 'LIMIT 5', 'LIMIT 4');

        $r2 = $db->query('SELECT `id` FROM `godb` LIMIT 2', null, 'num');
        $r3 = $db->query('SELECT `id` FROM `godb` LIMIT 3', null, 'num');
        $r4 = $db->query('SELECT `id` FROM `godb` LIMIT 4', null, 'num');
        $this->assertEquals(2, $r2);
        $this->assertEquals(3, $r3);
        $this->assertEquals(4, $r4);

        $db->queryDecorated($decor->getCallback());
        $r2 = $db->query('SELECT `id` FROM `godb` LIMIT 2', null, 'num');
        $r3 = $db->query('SELECT `id` FROM `godb` LIMIT 3', null, 'num');
        $r4 = $db->query('SELECT `id` FROM `godb` LIMIT 4', null, 'num');
        $this->assertEquals(2, $r2);
        $this->assertEquals(5, $r3);
        $this->assertEmpty($r4);

        $db->queryDecorated(null);
        $r2 = $db->query('SELECT `id` FROM `godb` LIMIT 2', null, 'num');
        $r3 = $db->query('SELECT `id` FROM `godb` LIMIT 3', null, 'num');
        $r4 = $db->query('SELECT `id` FROM `godb` LIMIT 4', null, 'num');
        $this->assertEquals(2, $r2);
        $this->assertEquals(3, $r3);
        $this->assertEquals(4, $r4);
    }
    
}


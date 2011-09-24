<?php
/**
 * Юнит-тестирование goDB
 * Тестирование мультизапросов
 *
 * @package    goDB
 * @subpackage unittest
 * @version    1.3.0
 * @author     Григорьев Олег aka vasa_c
 * @link       http://pyha.ru/go/godb/unit/
 * @link       http://pyha.ru/go/godb/multi/
 * @uses       PHPUnit
 */

require_once(dirname(__FILE__).'/base/goDBTestBase.php');

class goDBMultiTest extends goDBTestBase {

    /**
     * Формат вызова - три отдельных массива
     */
    public function testSepArrays() {
        $db = $this->db(true); 
        $patterns = array(
            'SELECT COUNT(*) FROM `godb`',
            'INSERT INTO `godb` SET `number`=?i',
            'SELECT COUNT(*) FROM `godb`',
            'INSERT INTO `godb` SET `number`=?i',
            'SELECT COUNT(*) FROM `godb` WHERE `id`>?i',
        );
        $datas = array(
            null, array(2), null, array(3), array(2),
        );
        $fetches  = array('el', 'id', 'el', 'id', 'el');
        $expected = array(7, 8, 8, 9, 7);
        $result   = $db->multiQuery($patterns, $datas, $fetches);
        $this->assertEquals($expected, $result);
    }

    /**
     * Формат вызова - один шаблон
     */
    public function testOnePattern() {
        $db = $this->db(true);
        $pattern  = 'INSERT INTO `godb` SET `number`=?i, `caption`=?n';
        $datas    = array(array(8, 'eight'), array(11, 'eleven'), array(22, null));
        $fetch    = 'id';
        $expected = array(8, 9, 10);
        $result   = $db->multiQuery($pattern, $datas, $fetch);
        $this->assertEquals($expected, $result);
        $count    = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');
        $this->assertEquals(10, $count);
    }

    /**
     * Фетч последнего результата
     */
    public function testLastFetch() {
        $db = $this->db(true);       
        $pattern  = 'INSERT INTO `godb` SET `number`=?i, `caption`=?n';
        $datas    = array(array(8, 'eight'), array(11, 'eleven'), array(22, null));
        $fetch    = 'last:id';
        $expected = 10;
        $result   = $db->multiQuery($pattern, $datas, $fetch);
        $this->assertEquals($expected, $result);
    }

    /**
     * Всё в одном массиве
     */
    public function testQueries() {
        $db = $this->db(true);
        $queries = array(
            array(
                'SELECT COUNT(*) FROM `godb` WHERE `id`>?i',
                array(2),
                'el',
            ),
            array(
                'INSERT INTO `godb` SET `number`=?i',
                array(3),
                'id',
            ),
            array(
                'SELECT COUNT(*) FROM `godb` WHERE `id`>?i',
                array(2),
                'el',
            ),
        );
        $expected = array(5, 8, 6);
        $result   = $db->multiQuery($queries);
        $this->assertEquals($expected, $result);
    }

    /**
     * Шаблоны и данные в одном массиве, а фетч в другом
     */
    public function testQueriesFetch() {
        $db = $this->db(true);      
        $queries = array(
            array(
                'SELECT COUNT(*) FROM `godb` WHERE `id`>?i',
                array(2),
            ),
            array(
                'INSERT INTO `godb` SET `number`=?i',
                array(3),
            ),
            array(
                'SELECT COUNT(*) FROM `godb` WHERE `id`>?i',
                array(2),
            ),
        );
        $result = $db->multiQuery($queries, null, 'last:el');
        $this->assertEquals(6, $result);
    }

    /**
     * Ошибка в запросе внутри транзакции
     *
     * @expectedException goDBExceptionQuery
     */
    public function testErrorTransaction() {
        $te = $this->testTableEngine;
        $this->testTableEngine = 'InnoDB';
        $db = $this->db(true);
        $this->testTableEngine = $te;
        $queries = array(
            array(
                'INSERT INTO `godb` SET `number`=?i',
                array(1),
            ),
            array(
                'INSERT INTO `godb` SET `number`=?i',
                array(2),
            ),
            array(
                'INSERT INTO `godb` SET `unknown_column`=?i',
                array(3),
            ),
            array(
                'INSERT INTO `godb` SET `number`=?i',
                array(4),
            ),
        );
        try {
            $db->multiQuery($queries, null, 'last:id', true);
        } catch (goDBExceptionQuery $e) {
            $count = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');
            $this->assertEquals(7, $count);
            throw $e;
        }
    }

    /**
     * Ошибка в запросе вне транзакции
     *
     * @expectedException goDBExceptionQuery
     */
    public function testErrorNotTransaction() {
        $te = $this->testTableEngine;
        $this->testTableEngine = 'InnoDB';
        $db = $this->db(true);
        $this->testTableEngine = $te;
        $queries = array(
            array(
                'INSERT INTO `godb` SET `number`=?i',
                array(1),
            ),
            array(
                'INSERT INTO `godb` SET `number`=?i',
                array(2),
            ),
            array(
                'INSERT INTO `godb` SET `unknown_column`=?i',
                array(3),
            ),
            array(
                'INSERT INTO `godb` SET `number`=?i',
                array(4),
            ),
        );
        try {
            $db->multiQuery($queries, null, 'last:id', false);
        } catch (goDBExceptionQuery $e) {
            $count = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');
            $this->assertEquals(9, $count);
            throw $e;
        }
    }

    /**
     * Количество данных не совпадает с количеством шаблонов
     *
     * @expectedException goDBExceptionMulti
     */
    public function testErrorDataCount() {
        $db = $this->db(true);
        $patterns = array(
            'SELECT `number` FROM `godb` WHERE `id`=?i',
            'SELECT `number` FROM `godb` WHERE `id`=?i',
        );
        $datas = array(array(1), array(2), array(3));
        $db->multiQuery($patterns, $datas, 'el');
    }

    /**
     * Количество фетчей не совпадает с количеством шаблонов
     *
     * @expectedException goDBExceptionMulti
     */
    public function testErrorFetchCount() {
        $db = $this->db(true);
        $patterns = array(
            'SELECT `number` FROM `godb` WHERE `id`=?i',
            'SELECT `number` FROM `godb` WHERE `id`=?i',
        );
        $datas = array(array(1), array(2));
        $fetches = array('el', 'el', 'el');
        $db->multiQuery($patterns, $datas, $fetches);
    }

    /**
     * Ошибка плейсхолдера - ни один запрос не выполняется
     *
     * @expectedException goDBExceptionDataPlaceholder
     */
    public function testErrorPlaceholder() {
        $db = $this->db(true);

        $count1 = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');

        $queries = array(
            array(
                'INSERT INTO `godb` SET `number`=?i',
                array(1),
            ),
            array(
                'INSERT INTO `godb` SET `number`=?unknown',
                array(2),
            ),
            array(
                'INSERT INTO `godb` SET `number`=?i',
                array(3),
            ),
        );
        try {
            $db->multiQuery($queries, null, 'last:id', false);
        } catch (goDBExceptionDataPlaceholder $e) {
            $count2 = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');
            $this->assertEquals($count1, $count2);
            throw $e;
        }
    }

    /**
     * Ошибка формата разбора - выполняются все
     *
     * @expectedException goDBExceptionFetch
     */
    public function testErrorFetch() {
        $db = $this->db(true);

        $count1 = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');

        $queries = array(
            array(
                'INSERT INTO `godb` SET `number`=?i',
                array(1),
                'id',
            ),
            array(
                'INSERT INTO `godb` SET `number`=?i',
                array(2),
                'unknown',
            ),
            array(
                'INSERT INTO `godb` SET `number`=?i',
                array(3),
                'id',
            ),
        );

        $this->assertFalse($db->inTransaction());
        try {
            $db->multiQuery($queries);
        } catch (goDBExceptionFetchUnknown $e) {
            $count2 = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');
            $this->assertFalse($db->inTransaction());
            $this->assertEquals($count1 + 3, $count2);
            throw $e;
        }
    }

}
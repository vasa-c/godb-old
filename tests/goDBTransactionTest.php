<?php
/**
 * Юнит-тестирование goDB
 * Тестирование надстройки над транзакциями
 *
 * @package    goDB
 * @subpackage unittest
 * @version    1.3.0
 * @author     Григорьев Олег aka vasa_c
 * @link       http://pyha.ru/go/godb/unit/
 * @link       http://pyha.ru/go/godb/transaction/
 * @uses       PHPUnit
 */

require_once(dirname(__FILE__).'/base/goDBTestBase.php');
require_once(dirname(__FILE__).'/helpers/transaction.php');

class goDBTransactionTest extends goDBTestBase {

    /**
     * Транзакции проверяем на InnoDB
     *
     * @var string
     */
    protected $testTableEngine = 'InnoDB';

    /**
     * Проверка, что mysqli-транзакции по прежнему работают
     *
     * @covers autocommit
     * @covers commit
     * @covers rollback
     */
    public function testMysqli() {
        $db = $this->db(true);

        $patternS = 'SELECT COUNT(*) FROM `godb`';
        $patternI = 'INSERT INTO `godb` SET `number`=1';

        $count1 = $db->query($patternS, null, 'el');

        $db->autocommit(false);

        $db->query($patternI);
        $count2 = $db->query($patternS, null, 'el');
        $this->assertEquals($count1 + 1, $count2);
        $db->commit();

        $db->query($patternI);
        $count3 = $db->query($patternS, null, 'el');
        $this->assertEquals($count2 + 1, $count3);
        $db->rollback();

        $count4 = $db->query($patternS, null, 'el');
        $this->assertEquals($count2, $count4);

        $db->autocommit(true);
        $db->query($patternI);
        $count5 = $db->query($patternS, null, 'el');
        $db->rollback();
        $this->assertEquals($count4 + 1, $count5);
    }

    /**
     * Простая одноуровневая транзакция
     *
     * @covers transactionBegin
     * @covers transactionCommit
     * @covers transactionRollback
     */
    public function testPlain() {

        $db = $this->db(true);

        $patternS = 'SELECT COUNT(*) FROM `godb`';
        $patternI = 'INSERT INTO `godb` SET `number`=1';

        $count = $db->query($patternS, null, 'el');
        $db->query($patternI);
        $count1 = $db->query($patternS, null, 'el');
        $this->assertEquals($count + 1, $count1);
        $this->assertFalse($db->inTransaction());

        $db->transactionBegin();
        $db->query($patternI);
        $db->query($patternI);
        $count2 = $db->query($patternS, null, 'el');
        $this->assertEquals($count1 + 2, $count2);
        $this->assertTrue($db->inTransaction());

        $db->transactionRollback();
        $count3 = $db->query($patternS, null, 'el');
        $this->assertEquals($count1, $count3);
        $this->assertFalse($db->inTransaction());

        $db->transactionBegin();
        $db->query($patternI);
        $db->query($patternI);
        $count4 = $db->query($patternS, null, 'el');
        $this->assertEquals($count2, $count4);
        $this->assertTrue($db->inTransaction());

        $db->transactionCommit();
        $count5 = $db->query($patternS, null, 'el');
        $this->assertEquals($count4, $count5);
        $db->query($patternI);
        $count6 = $db->query($patternS, null, 'el');
        $this->assertEquals($count5 + 1, $count6);

        $this->assertFalse($db->inTransaction());
    }

    /**
     * Выполнение функции в транзакции
     *
     * @covers transactionRun
     * @covers transactionRollback
     * @uses   helperTransactionRun
     */
    public function testTransactionRun() {
        $db = $this->db(true);

        $count1   = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');
        $callback = new goDBTestHelperTransactionRun($db, 25);

        $result = $db->transactionRun($callback->getCallback());
        $this->assertEquals(25, $result);
        $count2 = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');
        $this->assertEquals($count1 + 2, $count2);
        $this->assertEquals($count2, $callback->getCount());

        $count1   = $count2;
        $callback = new goDBTestHelperTransactionRun($db, false);
        $result   = $db->transactionRun($callback->getCallback());
        $this->assertFalse($result);
        $count2 = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');
        $this->assertEquals($count1, $count2);
        $this->assertEquals($count1 + 2, $callback->getCount());

        $count1   = $count2;
        $callback = new goDBTestHelperTransactionRun($db, 'rollback');
        $result   = $db->transactionRun($callback->getCallback());
        $this->assertFalse($result);
        $count2 = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');
        $this->assertEquals($count1, $count2);
        $this->assertEquals($count1 + 2, $callback->getCount());

        $count1   = $count2;
        $callback = new goDBTestHelperTransactionRun($db, 'error');
        $this->setExpectedException('goDBExceptionQuery');
        $db->transactionRun($callback->getCallback());
    }

    /**
     * Вложенные транзакции (с окончательным коммитом)
     *
     * @covers transactionBegin
     * @covers transactionCommit
     */
    public function testTransactionLevelCommit() {
        $db = $this->db(true);

        $this->assertFalse($db->inTransaction());
        $this->assertEquals(0, $db->transactionLevel());

        $countB = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');

        $db->transactionBegin();
        $this->assertTrue($db->inTransaction());
        $this->assertEquals(1, $db->transactionLevel());
        $db->query('INSERT INTO `godb` SET `number`=1');

        $db->transactionBegin();
        $this->assertTrue($db->inTransaction());
        $this->assertEquals(2, $db->transactionLevel());
        $db->query('INSERT INTO `godb` SET `number`=2');

        $db->transactionBegin();
        $this->assertTrue($db->inTransaction());
        $this->assertEquals(3, $db->transactionLevel());
        $db->query('INSERT INTO `godb` SET `number`=3');

        $db->transactionCommit();
        $this->assertTrue($db->inTransaction());
        $this->assertEquals(2, $db->transactionLevel());

        $db->transactionCommit();
        $this->assertTrue($db->inTransaction());
        $this->assertEquals(1, $db->transactionLevel());
        $db->query('INSERT INTO `godb` SET `number`=4');

        $db->transactionCommit();
        $this->assertFalse($db->inTransaction());
        $this->assertEquals(0, $db->transactionLevel());

        $countE = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');

        $this->assertEquals($countB + 4, $countE);
    }

    /**
     * Вложенные транзакции (с окончательным откатом)
     *
     * @covers transactionBegin
     * @covers transactionRollback
     */
    public function testTransactionLevelRollback() {
        $db = $this->db(true);

        $this->assertFalse($db->inTransaction());
        $this->assertEquals(0, $db->transactionLevel());

        $countB = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');

        $db->transactionBegin();
        $this->assertTrue($db->inTransaction());
        $this->assertEquals(1, $db->transactionLevel());
        $db->query('INSERT INTO `godb` SET `number`=1');

        $db->transactionBegin();
        $this->assertTrue($db->inTransaction());
        $this->assertEquals(2, $db->transactionLevel());
        $db->query('INSERT INTO `godb` SET `number`=2');

        $db->transactionBegin();
        $this->assertTrue($db->inTransaction());
        $this->assertEquals(3, $db->transactionLevel());
        $db->query('INSERT INTO `godb` SET `number`=3');

        $db->transactionCommit();
        $this->assertTrue($db->inTransaction());
        $this->assertEquals(2, $db->transactionLevel());

        $db->transactionCommit();
        $this->assertTrue($db->inTransaction());
        $this->assertEquals(1, $db->transactionLevel());
        $db->query('INSERT INTO `godb` SET `number`=4');

        $countM = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');
        $this->assertEquals($countB + 4, $countM);

        $db->transactionRollback();
        $this->assertFalse($db->inTransaction());
        $this->assertEquals(0, $db->transactionLevel());

        $countE = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');

        $this->assertEquals($countB, $countE);
    }

    /**
     * Тихий откат транзакции
     *
     * @covers transactionRollback
     */
    public function testRollbackQuiet() {

        $db = $this->db(true);

        $countB = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');

        $db->transactionBegin(); // begin level 1
        $db->query('INSERT INTO `godb` SET `number`=1');

        $db->transactionBegin(); // begin level 2
        $db->query('INSERT INTO `godb` SET `number`=2');
        $this->assertTrue($db->inTransaction());
        $this->assertEquals(2, $db->transactionLevel());
        $this->assertFalse($db->transactionFailed());
        $countM = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');
        $this->assertEquals($countB + 2, $countM);
        
        $db->transactionRollback(); // rollback on level 2
        
        $this->assertTrue($db->inTransaction());
        $this->assertEquals(1, $db->transactionLevel());
        $this->assertTrue($db->transactionFailed());

        $this->assertFalse($db->query('SELECT COUNT(*) FROM `godb`', null, 'el'));
        $this->assertFalse($db->query('INSERT INTO `godb` SET `number`=2'));

        $db->transactionCommit(); // commit on level 1
        $countE = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');
        $this->assertEquals($countB, $countE);
        $this->assertFalse($db->inTransaction());

    }

    /**
     * Откат транзакции с исключением
     *
     * @covers transactionRollback
     */
    public function testRollbackException() {

        $db = $this->db(true);

        $count1 = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');

        /* Трёхуровневая транзакция с корректным завершением */
        $level3 = new goDBTestHelperTransactionRun($db, true);
        $level2 = new goDBTestHelperTransactionRollback($db, $level3->getCallback());
        $level1 = new goDBTestHelperTransactionRollback($db, $level2->getCallback());

        $result = $level1->run();
        $this->assertTrue($result);
        $this->assertFalse($db->inTransaction());
        $count2 = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');
        $this->assertEquals($count1 + 4, $count2);
        $this->assertEquals($count1 + 1, $level1->getCount());
        $this->assertEquals($count1 + 2, $level2->getCount());
        $this->assertEquals($count1 + 4, $level3->getCount());


        /* Тоже самое с откатом */
        $level3 = new goDBTestHelperTransactionRun($db, 'rollback');
        $level2 = new goDBTestHelperTransactionRollback($db, $level3->getCallback());
        $level1 = new goDBTestHelperTransactionRollback($db, $level2->getCallback());

        $r = $level1->run();
        $this->assertFalse($r);
        $this->assertFalse($db->inTransaction());
        $count3 = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');
        $this->assertEquals($count2, $count3);
        $this->assertEquals($count2 + 1, $level1->getCount());
        $this->assertEquals($count2 + 2, $level2->getCount());
        $this->assertEquals($count2 + 4, $level3->getCount());
        
    }

    /**
     * Проверка сохранения автокоммита перед транзакцией
     */
    public function testAutocommitSave() {

        $db = $this->db();

        $db->autocommit(false);
        $this->assertFalse($db->getAutocommit());
        $db->transactionBegin(false);
        $this->assertFalse($db->getAutocommit());
        $db->transactionCommit();
        $this->assertTrue($db->getAutocommit());

        $db->autocommit(false);
        $this->assertFalse($db->getAutocommit());
        $db->transactionBegin(true);
        $this->assertFalse($db->getAutocommit());
        $db->transactionCommit();
        $this->assertFalse($db->getAutocommit());

        $db->autocommit(true);
        $this->assertTrue($db->getAutocommit());
    }

    /**
     * Тестирование совмещение транзакций goDB и mysqli
     */
    public function testMysqliGodb() {

        $db = $this->db(true);

        $countB = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');
        $db->transactionBegin();
        $db->query('INSERT INTO `godb` SET `number`=1');
        $db->autocommit(false);
        $db->query('INSERT INTO `godb` SET `number`=2');
        $db->commit();
        $db->autocommit(true);
        $db->query('INSERT INTO `godb` SET `number`=3');
        $countM = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');
        $this->assertEquals($countB + 3, $countM);
        $this->assertTrue($db->inTransaction());
        $db->transactionRollback();

        $countE = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');
        $this->assertEquals($countB, $countE);
        
        $countB = $countE;
        $db->transactionBegin();
        $db->query('INSERT INTO `godb` SET `number`=1');
        $db->autocommit(false);
        $db->query('INSERT INTO `godb` SET `number`=2');
        $db->rollback();
        $db->autocommit(true);
        $db->query('INSERT INTO `godb` SET `number`=3');
        $countM = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');
        $this->assertFalse($countM);
        $this->assertTrue($db->inTransaction());
        $db->transactionRollback();

        $countE = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');
        $this->assertEquals($countB, $countE);
    }

    /**
     * Закрытие транзакции
     */
    public function testTransactionClose() {
        $db = $this->db(true);

        $count1 = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');

        $db->transactionBegin();
        $db->query('INSERT INTO `godb` SET `number`=1');
        $db->transactionBegin();
        $db->query('INSERT INTO `godb` SET `number`=1');
        $count2 = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');
        $this->assertEquals($count1 + 2, $count2);
        $this->assertTrue($db->inTransaction());
        $this->assertEquals(2, $db->transactionLevel());

        $db->transactionClose();
        $count3 = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');
        $this->assertEquals($count1, $count3);
        $this->assertFalse($db->inTransaction());
    }

    /**
     * Проверка мультизапросов внутри транзакции
     */
    public function testMulti() {

        $db = $this->db(true);
        $count1 = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');

        $db->transactionBegin();
        $queries = array(
            array('INSERT INTO `godb` SET `number`=1', null),
            array('INSERT INTO `godb` SET `number`=1', null),
            array('SELECT COUNT(*) FROM `godb`', null),
        );
        $count2 = $db->multiQuery($queries, null, 'last:el');
        $this->assertEquals($count1 + 2, $count2);

        $db->transactionBegin();
        $db->transactionRollback();
        $queries = array(
            array('INSERT INTO `godb` SET `number`=1', null),
            array('INSERT INTO `godb` SET `number`=1', null),
            array('SELECT COUNT(*) FROM `godb`', null),
        );
        $count3 = $db->multiQuery($queries, null, 'last:el');
        $this->assertFalse($count3);

        $this->assertTrue($db->inTransaction());
        $db->transactionCommit();
        $this->assertFalse($db->inTransaction());
        $count4 = $db->query('SELECT COUNT(*) FROM `godb`', null, 'el');
        $this->assertEquals($count1, $count4);
    }

    /**
     * Проверка подготовленных выражений внутри транзакций
     */
    public function testPrepare() {
        $db = $this->db(true);

        $prepareSelect = $db->prepare('SELECT COUNT(*) FROM `godb`', true);
        $prepareInsert = $db->prepare('INSERT INTO `godb` SET `number`=1', true);

        $count1 = $prepareSelect->execute(null, 'el');

        $db->transactionBegin();
        $prepareInsert->execute();
        $count2 = $prepareSelect->execute(null, 'el');
        $this->assertEquals($count1 + 1, $count2);

        $db->transactionBegin();
        $db->transactionRollback();
        $count3 = $prepareSelect->execute(null, 'el');
        $this->assertFalse($count3);

        $this->assertTrue($db->inTransaction());
        $db->transactionCommit();
        $this->assertFalse($db->inTransaction());

        $count4 = $prepareSelect->execute(null, 'el');
        $this->assertEquals($count1, $count4);

        $prepareSelect->close();
        $prepareInsert->close();
    }

}



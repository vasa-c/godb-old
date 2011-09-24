<?php
/**
 * Хелперы для тестирования транзакций
 */

/**
 * Хелпер для тестирования transactionRun,
 * предоставляет callback-функцию вставляющую две строки в таблицу
 */
class goDBTestHelperTransactionRun {

    /**
     * Конструктор
     *
     * @param goDB $db
     *        тестируемый объект базы
     * @param string $result
     *        способ завершения callback-функции:
     *        "rollback" - rollback с исключением
     *        "error"    - исключение по ошибке в запросе
     *        любое другое возвращается напрямую
     */
    public function __construct($db, $finish) {
        $this->db     = $db;
        $this->finish = $finish;
    }

    /**
     * Метод для кэлбэка
     */
    public function callback() {
        $this->db->query('INSERT INTO `godb` SET `number`=1');
        $this->db->query('INSERT INTO `godb` SET `number`=2');
        $this->count = $this->db->query('SELECT COUNT(*) FROM `godb`', null, 'el');
        if ($this->finish === 'rollback') {
            return $this->db->transactionRollback(true);
        } elseif ($this->finish === 'error') {
            return $this->db->query('WTF');
        }
        return $this->finish;
    }

    /**
     * Получить количество строк, которое было в момент манипуляций
     */
    public function getCount() {
        return $this->count;
    }

    public function getCallback() {
        return array($this, 'callback');
    }

    private $db, $finish, $count;
}

/**
 * Хелпер для тестирования Rollback с исключением
 *
 * В транзакции вставляет строку, запоминает новое количество и вызывает указанную функцию
 */
class goDBTestHelperTransactionRollback {

    public function __construct($db, $callback) {
        $this->db = $db;
        $this->callback = $callback;
    }

    public function run() {
        try {
            $level = $this->db->transactionBegin();
            $this->db->query('INSERT INTO `godb` SET `number`=?i', array($level));
            $this->count = $this->db->query('SELECT COUNT(*) FROM `godb`', null, 'el');
            if ($this->callback) {
                call_user_func($this->callback);
            }
            $this->db->transactionCommit();
        } catch (goDBExceptionTransactionRollback $e) {
            if ($level > 1) {
                throw $e;
            }
            return false;
        }
        return true;
    }

    public function getCount() {
        return $this->count;
    }

    public function getCallback() {
        return array($this, 'run');
    }

    private $db, $callback, $count;

}
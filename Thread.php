<?php
namespace PhliteBB;

require_once 'phlite/Phlite.php';
require_once __DIR__.'/PhliteBBException.php';

use PDO;
use Phlite\DB;

class Thread {
    protected $id = NULL;
 
    public function __construct(int $id) {
        $sql = 'SELECT COUNT(*) FROM threads WHERE id = :i';
        $q = DB::prepare($sql);
        $q->bindValue(':i', $id, PDO::PARAM_INT);
        $q->execute();
        if($q->fetchColumn() == 0)
            throw new ThreadException(ThreadException::CODE['NOT_FOUND']);
        $this->id = $id;
    }

    /**********
     * Titles *
     **********/
    public static function validTitle(string $t) : bool {
        // TODO: Actual validation
        return true;
    }

    /*********************
     * Thread management *
     *********************/
    public static function add(string $title) : self {
        if(!self::validTitle($title))
            throw new ThreadException(ThreadException::CODE['TITLE_INVALID']);
        $sql = 'INSERT INTO threads(title) VALUES(:t)';
        $query = DB::prepare($sql);
        $query->bindValue(':t', $title, PDO::PARAM_STR);
        $query->execute();
        return new self(DB::get()->lastInsertId());
    }

    /************
     * Database *
     ************/
    public static function setupDB() : void {
        DB::execFile('sql/threads.sql');
        DB::execFile('sql/threads_edits.sql');
        DB::execFile('sql/threads_current_title_view.sql');
    }
}

class ThreadException extends PhliteBBException {
    public const CODE_PREFIX = 100;
    public const CODE = [
        'THREAD_NOT_FOUND'    => self::CODE_PREFIX + 1,
        'THREAD_NAME_INVALID' => self::CODE_PREFIX + 2,
    ];
    protected const MESSAGE = [
        self::CODE['NOT_FOUND']    => 'Thread not found',
        self::CODE['TITLE_INVALID'] => 'Invalid thread title',
    ];

    public function __construct(int $code) {
        parent::__construct("$code ".self::MESSAGE[$code], $code);
    }
}

?>

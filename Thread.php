<?php
namespace PhliteBB;

require_once 'phlite/Phlite.php';
require_once __DIR__.'/PhliteBBException.php';

use PDO;
use Phlite\DB;
use Phlite\User;

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

    public function getID() : int {
        return $this->id;
    }

    /**********
     * Titles *
     **********/
    public function getTitle() : string {
        $sql = 'SELECT title FROM threads_current_title_view WHERE id = :i';
        $q = DB::prepare($sql);
        $q->bindValue(':i', $this->id, PDO::PARAM_INT);
        $q->execute();
        $q->bindColumn('title', $title, PDO::PARAM_STR);
        $q->fetch(PDO::FETCH_BOUND);
        return $title;
    }

    public static function validTitle(string $t) : bool {
        // TODO: Actual validation
        return true;
    }

    /********
     * Time *
     ********/
    // Returns time of the first post in the thread, or null if this is a dangling thread.
    public function getFirstPostTime() : ?int {
        $sql = 'SELECT time FROM posts WHERE threadID = :i ORDER BY time ASC LIMIT 1';
        $q = DB::prepare($sql);
        $q->bindValue(':i', $this->id, PDO::PARAM_INT);
        $q->execute();
        $q->bindColumn('time', $time, PDO::PARAM_INT);
        $q->fetch(PDO::FETCH_BOUND);
        return $time;
    }

    // Returns time of the last post in the thread, or null if this is a dangling thread.
    public function getLastPostTime() : ?int {
        $sql = 'SELECT time FROM posts WHERE threadID = :i ORDER BY time DESC LIMIT 1';
        $q = DB::prepare($sql);
        $q->bindValue(':i', $this->id, PDO::PARAM_INT);
        $q->execute();
        $q->bindColumn('time', $time, PDO::PARAM_INT);
        $q->fetch(PDO::FETCH_BOUND);
        return $time;
    }

    /*********************
     * Thread management *
     *********************/
    // This is a plumbing method which adds a dangling thread, with no associated user or timestamp.
    // The userID and timestamp are determined by the first post in a thread.
    public static function add(string $title) : self {
        if(!self::validTitle($title))
            throw new ThreadException(ThreadException::CODE['TITLE_INVALID']);
        $sql = 'INSERT INTO threads(title) VALUES(:t)';
        $query = DB::prepare($sql);
        $query->bindValue(':t', $title, PDO::PARAM_STR);
        $query->execute();
        return new self(DB::get()->lastInsertId());
    }

    // Porcelain method which adds a new thread /and/ associated first post.
    public static function post(string $title, string $text, User $user) : self {
        $t = self::add($title);
        Post::add($text, $t, $user);
        return $t;
    }

    // Probably not so useful at scale.
    public static function getAll() : array {
        $sql = 'SELECT id FROM threads ORDER BY id DESC';
        $q = DB::prepare($sql);
        $q->execute();
        return array_map(
            function($id) {
                return new Thread($id);
            },
            $q->fetchAll(PDO::FETCH_COLUMN, 0)
        );
    }

    /*********
     * Posts *
     *********/
    public function getPosts() : array {
        $sql = 'SELECT id FROM posts WHERE threadID = :tid';
        $query = DB::prepare($sql);
        $query->bindValue(':tid', $this->id, PDO::PARAM_INT);
        $query->execute();
        return array_map(
            function($pid) {
                return new Post($pid);
            },
            $query->fetchAll(PDO::FETCH_COLUMN, 0)
        );
    }

    /************
     * Database *
     ************/
    public static function setupDB() : void {
        DB::execFile(__DIR__.'/sql/threads.sql');
        DB::execFile(__DIR__.'/sql/threads_edits.sql');
        DB::execFile(__DIR__.'/sql/threads_current_title_view.sql');
        DB::execFile(__DIR__.'/sql/threads_by_latest_post_view.sql');
    }
}

class ThreadException extends PhliteBBException {
    public const CODE_PREFIX = 100;
    public const CODE = [
        'NOT_FOUND'     => self::CODE_PREFIX + 1,
        'TITLE_INVALID' => self::CODE_PREFIX + 2,
    ];
    protected const MESSAGE = [
        self::CODE['NOT_FOUND']     => 'Thread not found',
        self::CODE['TITLE_INVALID'] => 'Invalid thread title',
    ];

    public function __construct(int $code) {
        parent::__construct("$code ".self::MESSAGE[$code], $code);
    }
}

?>

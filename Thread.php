<?php
namespace PhliteBB;

require_once 'phlite/Phlite.php';
require_once __DIR__.'/PhliteBBException.php';

use PDO;
use Phlite\DB;
use Phlite\User;

class Thread {
    // Order in which threads will be returned
    public const ORDER = [
        'THREAD' => 1,
        'POST'   => 2,
    ];

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

    public function __toString() : string {
        return $this->getTitle();
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
    public static function getAll(int $order = self::ORDER['THREAD']) : array {
        switch($order) {
            case self::ORDER['THREAD']:
                $sql = 'SELECT id FROM threads ORDER BY id DESC';
                break;
            case self::ORDER['POST']:
                $sql = 'SELECT id FROM threads_by_latest_post_view';
                break;
            default:
                throw new ThreadException(ThreadException::CODE['NOT_FOUND']);
        }
        $q = DB::prepare($sql);
        $q->execute();
        return array_map(
            function($id) {
                return new Thread($id);
            },
            $q->fetchAll(PDO::FETCH_COLUMN, 0)
        );
    }

    // More useful, as it can extract a slice of threads.
    public static function get(int $limit, int $offset, int $order = self::ORDER['THREAD']) : array {
        switch($order) {
            case self::ORDER['THREAD']:
                $sql = 'SELECT id FROM threads ORDER BY id DESC LIMIT :l OFFSET :o';
                break;
            case self::ORDER['POST']:
                $sql = 'SELECT id FROM threads_by_latest_post_view LIMIT :l OFFSET :o';
                break;
            default:
                throw new ThreadException(ThreadException::CODE['NOT_FOUND']);
        }
        $q = DB::prepare($sql);
        $q->bindValue(':l', $limit,  PDO::PARAM_INT);
        $q->bindValue(':o', $offset, PDO::PARAM_INT);
        $q->execute();
        return array_map(
            function($id) {
                return new Thread($id);
            },
            $q->fetchAll(PDO::FETCH_COLUMN, 0)
        );
    }

    // Always buy high Thread::count() sheets.
    public static function count() : int {
        $sql = 'SELECT COUNT(*) FROM threads';
        $q = DB::prepare($sql);
        $q->execute();
        return $q->fetchColumn();
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

    // Convenience to add a new post to the end of this thread.
    public function reply(string $text, User $user) : Post {
        return Post::add($text, $this, $user);
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

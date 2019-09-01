<?php
namespace PhliteBB;

require_once 'phlite/Phlite.php';
require_once __DIR__.'/Thread.php';
require_once __DIR__.'/PhliteBBException.php';

use PDO;
use Phlite\DB;
use Phlite\User;

class Post {
    protected $id = NULL;
 
    public function __construct(int $id) {
        $sql = 'SELECT COUNT(*) FROM posts WHERE id = :i';
        $q = DB::prepare($sql);
        $q->bindValue(':i', $id, PDO::PARAM_INT);
        $q->execute();
        if($q->fetchColumn() == 0)
            throw new PostException(PostException::CODE['NOT_FOUND']);
        $this->id = $id;
    }

    public function getID() : int {
        return $this->id;
    }

    // Returns time of original post (not of latest edit).
    public function getTime() : int {
        $sql = 'SELECT time FROM posts WHERE id = :i';
        $q = DB::prepare($sql);
        $q->bindValue(':i', $this->id, PDO::PARAM_INT);
        $q->execute();
        $q->bindColumn('time', $time, PDO::PARAM_INT);
        $q->fetch(PDO::FETCH_BOUND);
        return $time;
    }

    /********
     * Text *
     ********/
    public function getText() : string {
        $sql = 'SELECT text FROM posts_current_text_view WHERE id = :i';
        $q = DB::prepare($sql);
        $q->bindValue(':i', $this->id, PDO::PARAM_INT);
        $q->execute();
        $q->bindColumn('text', $text, PDO::PARAM_STR);
        $q->fetch(PDO::FETCH_BOUND);
        return $text;
    }

    public static function validText(string $t) : bool {
        // TODO: Actual validation
        return true;
    }

    /*******************
     * Post management *
     *******************/
    public static function add(string $text, Thread $thread, User $user) : self {
        if(!self::validText($text))
            throw new PostException(PostException::CODE['TEXT_INVALID']);
        $sql = 'INSERT INTO posts(text, time, threadID, userID) VALUES(:text, :time, :tid, :uid)';
        $query = DB::prepare($sql);
        $query->bindValue(':text', $text,            PDO::PARAM_STR);
        $query->bindValue(':time', time(),           PDO::PARAM_INT);
        $query->bindValue(':tid',  $thread->getID(), PDO::PARAM_INT);
        $query->bindValue(':uid',  $user->getID(),   PDO::PARAM_INT);
        $query->execute();
        return new self(DB::get()->lastInsertId());
    }

    /************
     * Database *
     ************/
    public static function setupDB() : void {
        DB::execFile('sql/posts.sql');
        DB::execFile('sql/posts_edits.sql');
        DB::execFile('sql/posts_current_text_view.sql');
    }
}

class PostException extends PhliteBBException {
    public const CODE_PREFIX = 200;
    public const CODE = [
        'NOT_FOUND'    => self::CODE_PREFIX + 1,
        'TEXT_INVALID' => self::CODE_PREFIX + 2,
    ];
    protected const MESSAGE = [
        self::CODE['NOT_FOUND']    => 'Post not found',
        self::CODE['TEXT_INVALID'] => 'Invalid post text',
    ];

    public function __construct(int $code) {
        parent::__construct("$code ".self::MESSAGE[$code], $code);
    }
}

?>

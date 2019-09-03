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

    public function __toString() : string {
        return $this->getText();
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

    // Returns User which originally posted the post.
    public function getUser() : User {
        $sql = 'SELECT userID FROM posts WHERE id = :i';
        $q = DB::prepare($sql);
        $q->bindValue(':i', $this->id, PDO::PARAM_INT);
        $q->execute();
        $q->bindColumn('userID', $id, PDO::PARAM_INT);
        $q->fetch(PDO::FETCH_BOUND);
        return new User($id);
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

    //Return text formatted with paragraph tags, sanitised for output, etc.
    public function getFormattedText() : string {
        return self::format($this->getText());
    }

    public static function validText(string $t) : bool {
        // TODO: Actual validation
        return true;
    }

    // Return text with prettified paragraph tags, line breaks, stripped unallowed HTML tags, etc.
    protected static function format(string $s) : string {
        // TODO: doesn't strip naughty attributes, which is insecure
        $stripped = strip_tags($s, '<a><b><i>');

        // Replace multiple newlines by wrapping the preceding text in <p> tags.
        $paragraphs = array_reduce(
            preg_split('/\n{2,}/', $stripped),
            function($carry, $item) {
                return $carry .= "<p>$item</p>";
            },
            ''
        );

        // Replace remaining single newlines with breaks.
        return str_replace("\n", '<br />', $paragraphs);
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
        Thread::setupDB();
        DB::execFile(__DIR__.'/sql/posts.sql');
        DB::execFile(__DIR__.'/sql/posts_edits.sql');
        DB::execFile(__DIR__.'/sql/posts_current_text_view.sql');
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

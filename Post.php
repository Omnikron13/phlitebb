<?php
namespace PhliteBB;

require_once 'phlite/Phlite.php';
require_once __DIR__.'/Thread.php';
require_once __DIR__.'/PhliteBBException.php';

use PDO;
use Phlite\DB;
use Phlite\User;

class Post {
    // Tags which won't be stripped when formatting text for output.
    public const WHITELIST_TAGS = [
        'a',
        'abbr',
        'b',
        'br',
        'i',
        'img',
        'p',
    ];

    // Attributes which won't be stripped when formatting text for output.
    public const WHITELIST_ATTR = [
        'alt',
        'href',
        'src',
        'title',
    ];

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

    // Replace placeholders in a template file with various data about the post.
    public function render() : string {
        return Template::render(
            // TODO: config for template file
            __DIR__.'/templates/post.html',
            [
                'text'     => $this->getFormattedText(),
                // TODO: config for time format
                'time'     => date('d/m/y H:i', $this->getTime()),
                'username' => $this->getUser(),
                // TODO: further replacements for e.g. userID for linking, last edit, etc.
            ],
        );
    }

    public static function validText(string $t) : bool {
        // TODO: Actual validation
        return true;
    }

    // Return text with prettified paragraph tags, line breaks, stripped unallowed HTML tags, etc.
    protected static function format(string $html) : string {
        // Strip carriage returns to make further processing uniform.
        $html = str_replace("\r", '', $html);

        // Replace multiple newlines by wrapping the preceding text in <p> tags.
        $html = array_reduce(
            preg_split('/\n{2,}/', $html),
            function($carry, $item) {
                return $carry .= "<p>$item</p>";
            },
            ''
        );

        // Replace remaining single newlines with breaks.
        $html = str_replace("\n", '<br />', $html);

        // Strip out nonsense tags so DOM can work smoothly.
        $html = (new \tidy())->repairString(
            $html, 
            [
                'output-xhtml'    => true,
                'show-body-only'  => true,
            ],
        );

        $dom = new \DOMDocument();
        $dom->loadHTML($html);

        foreach(iterator_to_array($dom->getElementsByTagName('body')[0]->childNodes) as $n) {
            self::sanitiseTag($n);
        }

        $out = $dom->saveXML();

        // This is dirtier than my broswer history...
        $regex = '/<\?xml.*?body>(.*)<\/body><\/html>/s';
        preg_match($regex, $out, $match);
        return $match[1];
    }

    // TODO: config for whitelists?
    protected static function sanitiseTag(\DOMNode $node) {
        if(!is_a($node, 'DOMElement'))
            return;
        // Remove naughty tags
        if(!in_array($node->tagName, self::WHITELIST_TAGS)) {
            $node->parentNode->removeChild($node);
            return;
        }
        // Remove naughty attributes
        // (Must not iterate over the attributes directly, or removing during the loop fucks shit up.)
        foreach(iterator_to_array($node->attributes) as $a) {
            if(!in_array($a->name, self::WHITELIST_ATTR)) {
                $node->removeAttributeNode($a);
            }
        }
        // TODO: is this needed..?
        if(!$node->hasChildNodes())
            return;
        foreach($node->childNodes as $n) {
            self::sanitiseTag($n);
        }
    }

    /*******************
     * Post management *
     *******************/
    public static function add(string $text, Thread $thread, User $user) : self {
        if(!self::validText($text))
            throw new PostException(PostException::CODE['TEXT_INVALID']);
        $sql = 'INSERT INTO posts(text, time, threadID, userID) VALUES(:text, :time, :tid, :uid)';
        $q = DB::prepare($sql);
        $q->bindValue(':text', $text,            PDO::PARAM_STR);
        $q->bindValue(':time', time(),           PDO::PARAM_INT);
        $q->bindValue(':tid',  $thread->getID(), PDO::PARAM_INT);
        $q->bindValue(':uid',  $user->getID(),   PDO::PARAM_INT);
        $q->execute();
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

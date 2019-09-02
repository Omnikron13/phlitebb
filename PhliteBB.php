<?php
namespace PhliteBB;

require_once __DIR__.'/Thread.php';
require_once __DIR__.'/Post.php';

function setupDB() : void {
    \Phlite\setupDB();
    Post::setupDB();
}

?>

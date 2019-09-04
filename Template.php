<?php
namespace PhliteBB;

class Template {
    public static function render(string $file, array $replace) {
        $template = file_get_contents($file);
        foreach($replace as $k => $v) {
            $template = str_replace("[$k]", $v, $template);
        }
        return $template;
    }
}

?>

<?php
$content = file_get_contents('db.php');
$tokens = token_get_all($content);
$inClass = false;
$inMethod = false;
$depth = 0;

foreach ($tokens as $token) {
    if (is_array($token)) {
        $name = token_name($token[0]);
        $text = $token[1];
        $line = $token[2];
        
        if ($name === 'T_CLASS') $inClass = true;
        if ($name === 'T_FUNCTION') $inMethod = true;
        
        if ($text === 'require_once') {
            echo "require_once at line $line: " . ($inClass ? ($inMethod ? "Inside Method" : "Inside Class (ERROR)") : "Outside Class") . "\n";
        }
    } else {
        if ($token === '{') $depth++;
        if ($token === '}') {
            $depth--;
            if ($depth === 1) $inMethod = false; // Assuming class is depth 0 and method is depth 1
            if ($depth === 0) $inClass = false;
        }
    }
}

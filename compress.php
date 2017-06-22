<?php 

require 'lml.php';
$compressedCode = '';
lml()->refineCode(file_get_contents('lml.php'), $compressedCode);
file_put_contents('lml.min.php', $compressedCode);

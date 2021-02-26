<?php

include 'vendor/autoload.php';

set_error_handler(function ($error_no, $error_str, $error_file, $error_line) {
    throw new ErrorException($error_str, $error_no, E_USER_ERROR, $error_file, $error_line);
});

$a = 1/0;
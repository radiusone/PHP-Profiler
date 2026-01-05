<?php
if (!function_exists('_')) {
    function _(string $message): string {
        return $message;
    }
}

function __(string $message): string
{
    return htmlspecialchars(_($message));
}

<?php

namespace App\Support;

class TemplateRenderer
{
    /**
     * Replaces {{variable}} placeholders. A missing variable renders as an
     * empty string rather than leaving the raw {{...}} syntax visible to
     * the customer.
     */
    public function render(string $template, array $variables): string
    {
        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
            fn (array $match) => array_key_exists($match[1], $variables) ? (string) $variables[$match[1]] : '',
            $template
        );
    }
}
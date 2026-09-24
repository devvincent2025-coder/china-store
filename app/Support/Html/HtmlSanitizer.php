<?php

namespace App\Support\Html;

/**
 * Strips vendor-authored product description HTML down to the exact tag set the
 * RichTextEditor toolbar can produce (bold/italic/underline/lists) — every attribute is
 * dropped and any other tag (script, style, img, a, on*-handlers, etc.) is unwrapped rather
 * than removed outright, so the vendor's text survives even if a tag gets stripped.
 */
class HtmlSanitizer
{
    private const ALLOWED_TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li'];

    public static function clean(?string $html): ?string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return null;
        }

        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8" ?><div>'.$html.'</div>',
            LIBXML_NOERROR | LIBXML_NOWARNING
        );
        libxml_clear_errors();

        $root = $document->getElementsByTagName('div')->item(0);

        if (! $root) {
            return null;
        }

        self::sanitizeNode($document, $root);

        $result = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $result .= $document->saveHTML($child);
        }

        $result = trim($result);

        return $result === '' ? null : $result;
    }

    private static function sanitizeNode(\DOMDocument $document, \DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMComment) {
                $node->removeChild($child);
                continue;
            }

            if ($child instanceof \DOMText) {
                continue;
            }

            if (! $child instanceof \DOMElement) {
                $node->removeChild($child);
                continue;
            }

            // Script/style content is dangerous even as text, so drop the element entirely
            // instead of just unwrapping it.
            if (in_array(strtolower($child->tagName), ['script', 'style'], true)) {
                $node->removeChild($child);
                continue;
            }

            while ($child->attributes && $child->attributes->length > 0) {
                $child->removeAttribute($child->attributes->item(0)->name);
            }

            self::sanitizeNode($document, $child);

            if (! in_array(strtolower($child->tagName), self::ALLOWED_TAGS, true)) {
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
            }
        }
    }
}

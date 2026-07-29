<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allowlist sanitizer for rich-text authored in the CMS and rendered as HTML
 * elsewhere (the user-facing app renders topup instructions verbatim).
 *
 * Anything not explicitly allowed is removed. Editors are staff, but stored
 * markup reaches every user's browser, so a single pasted <script> or onclick
 * would be stored XSS — sanitising on save keeps the bad markup out of the
 * database rather than relying on every reader to escape it.
 */
class HtmlSanitizer
{
    /** Tags Tiptap's StarterKit + Underline/TextStyle/Color/Highlight produce. */
    private const ALLOWED_TAGS = [
        'p', 'br', 'hr', 'div',
        'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'code', 'mark', 'span',
        'pre', 'blockquote',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li',
        'a',
    ];

    /** Per-tag attribute allowlist; every other attribute is dropped. */
    private const ALLOWED_ATTRS = [
        'a' => ['href', 'target', 'rel'],
        'span' => ['style'],
        'mark' => ['style'],
    ];

    /** Dropped with their contents — the payload lives inside them. */
    private const STRIP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'object', 'embed', 'form',
        'input', 'button', 'select', 'textarea', 'svg', 'math', 'link', 'meta',
    ];

    private const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    /** CSS properties that can't be used to obscure or redress the page. */
    private const ALLOWED_STYLE_PROPS = ['color', 'background-color'];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        // The meta charset keeps UTF-8 intact; the wrapper gives a stable root.
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div id="sanitizer-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('sanitizer-root');

        if ($root === null) {
            return '';
        }

        self::cleanNode($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    private static function cleanNode(DOMNode $node): void
    {
        // Snapshot first: the list is live and we mutate while walking it.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                // Text and CDATA stay; comments can hide markup, so drop them.
                if ($child->nodeType === XML_COMMENT_NODE) {
                    $node->removeChild($child);
                }

                continue;
            }

            $tag = strtolower($child->nodeName);

            if (in_array($tag, self::STRIP_WITH_CONTENT, true)) {
                $node->removeChild($child);

                continue;
            }

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                // Unknown wrapper: keep the text, discard the element.
                self::cleanNode($child);
                self::unwrap($child);

                continue;
            }

            self::cleanAttributes($child, $tag);
            self::cleanNode($child);
        }
    }

    private static function cleanAttributes(DOMElement $el, string $tag): void
    {
        $allowed = self::ALLOWED_ATTRS[$tag] ?? [];

        foreach (iterator_to_array($el->attributes) as $attr) {
            $name = strtolower($attr->nodeName);

            // Catches every on* handler without needing to enumerate them.
            if (! in_array($name, $allowed, true)) {
                $el->removeAttribute($attr->nodeName);

                continue;
            }

            if ($name === 'href' && ! self::isSafeUrl($attr->nodeValue)) {
                $el->removeAttribute($attr->nodeName);

                continue;
            }

            if ($name === 'style') {
                $style = self::cleanStyle($attr->nodeValue);
                $style === '' ? $el->removeAttribute($attr->nodeName) : $el->setAttribute('style', $style);
            }
        }

        // Links opened in a new tab get noopener, so the opener can't be hijacked.
        if ($tag === 'a' && $el->getAttribute('target') === '_blank') {
            $el->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private static function isSafeUrl(?string $url): bool
    {
        $url = trim((string) $url);

        if ($url === '') {
            return false;
        }

        // Relative links and anchors carry no scheme and are safe.
        if (str_starts_with($url, '/') || str_starts_with($url, '#')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, self::ALLOWED_SCHEMES, true);
    }

    private static function cleanStyle(?string $style): string
    {
        $kept = [];

        foreach (explode(';', (string) $style) as $rule) {
            if (! str_contains($rule, ':')) {
                continue;
            }

            [$prop, $value] = explode(':', $rule, 2);
            $prop = strtolower(trim($prop));
            $value = trim($value);

            if (! in_array($prop, self::ALLOWED_STYLE_PROPS, true)) {
                continue;
            }

            // url() and expression() are the only ways CSS reaches out.
            if (preg_match('/url\(|expression\(|javascript:/i', $value)) {
                continue;
            }

            $kept[] = "{$prop}: {$value}";
        }

        return implode('; ', $kept);
    }

    /** Replace an element with its children. */
    private static function unwrap(DOMElement $el): void
    {
        $parent = $el->parentNode;

        if ($parent === null) {
            return;
        }

        while ($el->firstChild !== null) {
            $parent->insertBefore($el->firstChild, $el);
        }

        $parent->removeChild($el);
    }
}

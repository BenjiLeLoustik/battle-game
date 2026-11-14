<?php

namespace App\Shared\Helper;

use DOMDocument;
use DOMElement;

class SvgSanitizer
{
    private const TAGS = ['path', 'circle', 'rect', 'line', 'polyline', 'polygon', 'ellipse'];
    private const ATTRIBUTES = ['d', 'cx', 'cy', 'r', 'rx', 'ry', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'points', 'width', 'height'];

    public static function icon(string $markup): string
    {
        $markup = trim($markup);

        if ($markup === '' || strlen($markup) > 8000) {
            return '';
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML('<svg xmlns="http://www.w3.org/2000/svg">' . $markup . '</svg>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded || $document->documentElement === null) {
            return '';
        }

        $output = '';

        foreach ($document->documentElement->childNodes as $node) {
            if (!$node instanceof DOMElement || !in_array($node->localName, self::TAGS, true)) {
                continue;
            }

            $attributes = '';

            foreach (self::ATTRIBUTES as $name) {
                $value = $node->getAttribute($name);

                if ($value !== '' && preg_match('/^[0-9a-zA-Z.,\s\-]{1,4000}$/', $value)) {
                    $attributes .= sprintf(' %s="%s"', $name, $value);
                }
            }

            if ($attributes !== '') {
                $output .= '<' . $node->localName . $attributes . '/>';
            }
        }

        return $output;
    }
}

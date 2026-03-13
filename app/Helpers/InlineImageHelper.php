<?php

namespace App\Helpers;

use Pringgojs\LaravelItop\Models\InlineImage;
use Pringgojs\LaravelItop\Services\ItopServiceBuilder;

class InlineImageHelper
{
    /**
     * Extract inline image id/secret pairs from an HTML string.
     * Returns array of ['id' => (int), 'secret' => (string)] entries.
     *
     * @param string $html
     * @return array
     */
    public static function extractFromHtml(string $html): array
    {
        $results = [];

        if (trim($html) === '') {
            return $results;
        }

        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();

        // Ensure proper encoding to avoid warnings
        $htmlWrapped = '<!doctype html><html><body>' . $html . '</body></html>';
        $doc->loadHTML(mb_convert_encoding($htmlWrapped, 'HTML-ENTITIES', 'UTF-8'));

        $imgs = $doc->getElementsByTagName('img');

        foreach ($imgs as $img) {
            $idAttr = $img->getAttribute('data-img-id');
            $secretAttr = $img->getAttribute('data-img-secret');

            $id = $idAttr !== '' ? $idAttr : null;
            $secret = $secretAttr !== '' ? $secretAttr : null;

            if (!$id || !$secret) {
                // Fallback: try to parse from src query (e.g., id=2&s=f5d444)
                $src = $img->getAttribute('src');
                if ($src) {
                    $parts = parse_url(html_entity_decode($src));
                    if (!empty($parts['query'])) {
                        parse_str($parts['query'], $qs);
                        if (isset($qs['id']) && isset($qs['s'])) {
                            $id = $id ?? $qs['id'];
                            $secret = $secret ?? $qs['s'];
                        }
                    }
                }
            }

            if ($id && $secret) {
                $entry = ['id' => (int)$id, 'secret' => (string)$secret];

                // avoid duplicates
                $hash = $entry['id'] . '|' . $entry['secret'];
                if (!isset($results[$hash])) {
                    $results[$hash] = $entry;
                }
            }
        }

        // return values (reset numeric keys)
        return array_values($results);
    }

    /**
     * Adjust description HTML to point img src and data attributes to the destination itop instance.
     * It will look up InlineImage by `secret` on the destination connection and replace
     * the `src` (base URL + id & s) and `data-img-id`/`data-img-secret` accordingly.
     *
     * @param string $html
     * @param string $destBaseUrl Base URL of destination itop (host or full path). Example: "http://itop-b.example.com" or "http://itop-b.example.com/pages/ajax.document.php"
     * @param string|null $destConnection Optional DB connection name for destination itop (defaults to env('DB_ITOP_EXTERNAL'))
     * @return string Adjusted HTML
     */
    public static function adjustDescriptionForDestination(string $html, string $destBaseUrl, ?string $destConnection = null): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $destConnection = $destConnection ?? env('DB_ITOP_EXTERNAL');

        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $htmlWrapped = '<!doctype html><html><body>' . $html . '</body></html>';
        $doc->loadHTML(mb_convert_encoding($htmlWrapped, 'HTML-ENTITIES', 'UTF-8'));

        $imgs = $doc->getElementsByTagName('img');

        foreach ($imgs as $img) {
            $secret = $img->getAttribute('data-img-secret') ?: null;

            if (!$secret) {
                // try parse from src query
                $src = $img->getAttribute('src');
                if ($src) {
                    $parts = parse_url(html_entity_decode($src));
                    if (!empty($parts['query'])) {
                        parse_str($parts['query'], $qs);
                        if (isset($qs['s'])) {
                            $secret = $qs['s'];
                        }
                    }
                }
            }

            if (!$secret) {
                continue;
            }

            $destInline = InlineImage::on($destConnection)->where('secret', $secret)->first();
            if (!$destInline) {
                continue;
            }

            // build download path
            if (strpos($destBaseUrl, 'pages/ajax.document.php') !== false) {
                $downloadPath = rtrim($destBaseUrl, '?&');
            } else {
                $downloadPath = rtrim($destBaseUrl, '/') . '/pages/ajax.document.php';
            }

            $newSrc = $downloadPath . '?operation=download_inlineimage&id=' . $destInline->id . '&s=' . $destInline->secret;

            $img->setAttribute('src', $newSrc);
            $img->setAttribute('data-img-id', $destInline->id);
            $img->setAttribute('data-img-secret', $destInline->secret);
        }

        // return innerHTML of body
        $body = $doc->getElementsByTagName('body')->item(0);
        $inner = '';
        foreach ($body->childNodes as $child) {
            $inner .= $doc->saveHTML($child);
        }

        return $inner;
    }

    /**
     * Fetch InlineImage models from the itop connection for given id/secret pairs.
     * Returns array of found InlineImage models.
     *
     * @param array $pairs Array of ['id'=>int,'secret'=>string]
     * @param string|null $connection Optional DB connection name (defaults to env DB_ITOP_EXTERNAL)
     * @return array
     */
    public static function fetchInlineImages(array $pairs, ?string $connection = null): array
    {
        $connection = $connection ?? env('DB_ITOP_EXTERNAL');
        $found = [];

        foreach ($pairs as $pair) {
            if (!isset($pair['id']) || !isset($pair['secret'])) {
                continue;
            }

            $model = InlineImage::on($connection)
                ->where('id', $pair['id'])
                ->where('secret', $pair['secret'])
                ->first();

            if ($model) {
                $found[] = $model;
            }
        }

        return $found;
    }

    /**
     * Build an itop attachment create payload for a given InlineImage model.
     * Returns the payload produced by ItopServiceBuilder::payloadAttachmentCreate().
     *
     * @param InlineImage $inlineImage
     * @param string $itemClass
     * @param int|string $itemId
     * @param int|null $orgId
     * @return array
     */
    public static function toAttachmentPayload(InlineImage $inlineImage, string $itemClass, $itemId, ?int $orgId = null): array
    {
        $orgId = $orgId ?? env('ORG_ID_ITOP_ELITERY', 2);

        $payload = [
            'class' => 'InlineImage',
            'item_class' => $itemClass,
            'item_id' => $itemId,
            'item_org_id' => $orgId,
            'secret' => $inlineImage->secret ?? null,
            'contents' => [
                'filename' => $inlineImage->contents_filename ?? ($inlineImage->filename ?? null),
                'mimetype' => $inlineImage->contents_mimetype ?? ($inlineImage->mimetype ?? null),
                'binary' => base64_encode($inlineImage->contents_data ?? ($inlineImage->data ?? '')),
            ]
        ];

        return ItopServiceBuilder::payloadAttachmentCreate($payload);
    }
}

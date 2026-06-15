<?php

namespace App\Services\Ribenyan;

class RibenyanProductListParser
{
    public function parseListPage($html, $ftype, $brandName, $parentName)
    {
        $products = [];
        $blocks = preg_split('/<div class="d-flex py-2 border-bottom\s*">/u', (string) $html);

        if (count($blocks) <= 1) {
            return $products;
        }

        foreach (array_slice($blocks, 1) as $block) {
            $item = $this->parseBlock($block, $ftype, $brandName, $parentName);
            if ($item) {
                $products[] = $item;
            }
        }

        return $products;
    }

    protected function parseBlock($block, $ftype, $brandName, $parentName)
    {
        if (!preg_match('/<img src="([^"]+)"/u', $block, $imageMatch)) {
            return null;
        }

        $refId = null;
        if (preg_match('/data-bs-title="编号:\s*([^"]+)"/u', $block, $refMatch)) {
            $refId = trim($refMatch[1]);
        } elseif (preg_match('/rgba\(33,\s*37,\s*41,\s*0\.3\);"[^>]*>\s*([A-Z]?\d+)\s*</u', $block, $refMatch)) {
            $refId = trim($refMatch[1]);
        }

        if (!$refId) {
            return null;
        }

        if (!preg_match('/<p class="mb-1">([^<]*)<\/p>/u', $block, $titleMatch)) {
            return null;
        }

        $subtitle = '';
        if (preg_match('/<p class="mb-1 text-muted small">([^<]*)<\/p>/u', $block, $subtitleMatch)) {
            $subtitle = trim(html_entity_decode($subtitleMatch[1], ENT_QUOTES, 'UTF-8'));
        }

        $price = 0;
        if (preg_match('/data-goodsprice="(\d+)"/u', $block, $priceMatch)) {
            $price = (int) $priceMatch[1];
        }

        $goodsId = null;
        if (preg_match('/add_goods_shoppingcart\(this\s*,\s*(\d+)\)/u', $block, $goodsIdMatch)) {
            $goodsId = (int) $goodsIdMatch[1];
        }

        if ($price <= 0 || !$goodsId) {
            return null;
        }

        $title = trim(html_entity_decode($titleMatch[1], ENT_QUOTES, 'UTF-8'));
        $imageUrl = $this->normalizeImageUrl($imageMatch[1]);
        $extraNotes = $this->extractExtraNotes($block);

        return [
            'ref_id' => $refId,
            'goods_id' => $goodsId,
            'title' => $title,
            'subtitle' => $subtitle,
            'extra_notes' => $extraNotes,
            'price' => $price,
            'image_url' => $imageUrl,
            'ftype' => (int) $ftype,
            'category_parent' => $parentName,
            'category_brand' => $brandName,
        ];
    }

    protected function extractExtraNotes($block)
    {
        $notes = [];
        if (preg_match_all('/<p class="mb-1 text-danger small">([^<]*)<\/p>/u', $block, $matches)) {
            foreach ($matches[1] as $note) {
                $note = trim(html_entity_decode($note, ENT_QUOTES, 'UTF-8'));
                if ($note !== '') {
                    $notes[] = $note;
                }
            }
        }

        return implode("\n", $notes);
    }

    protected function normalizeImageUrl($src)
    {
        $src = trim(html_entity_decode($src, ENT_QUOTES, 'UTF-8'));
        if ($src === '') {
            return '';
        }

        if (preg_match('/^https?:\/\//i', $src)) {
            return $src;
        }

        $base = rtrim(config('ribenyan_import.base_url'), '/');
        if (strpos($src, '/') === 0) {
            return $base.$src;
        }

        return $base.'/'.ltrim($src, './');
    }
}

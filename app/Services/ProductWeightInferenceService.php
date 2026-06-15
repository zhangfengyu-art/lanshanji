<?php

namespace App\Services;

class ProductWeightInferenceService
{
    /**
     * 推断 EMS 计费用的单位重量（克）。焦油 mg 不作为重量。
     */
    public function inferUnitWeightGrams($title, $subtitle, $tobaccoType, $unitSticks = null)
    {
        $text = trim((string) $title.' '.(string) $subtitle);
        $type = (string) $tobaccoType;

        if ($type === OrderTobaccoLimitService::TYPE_ROLLING_TOBACCO) {
            return $this->inferRollingTobaccoWeight($text);
        }

        if (OrderTobaccoLimitService::countsTowardStickLimit($type)) {
            return $this->inferStickPackWeight($text, $unitSticks);
        }

        $explicit = $this->parseExplicitGrams($text);
        if ($explicit !== null) {
            return $explicit;
        }

        return 26;
    }

    /**
     * 仅手卷烟丝（Shag）重量推断，供批量脚本专用。
     */
    public function inferShagWeightGrams($title, $subtitle = '')
    {
        return $this->inferRollingTobaccoWeight(trim((string) $title.' '.(string) $subtitle));
    }

    protected function inferRollingTobaccoWeight($text)
    {
        $explicit = $this->parseExplicitGrams($text);
        if ($explicit !== null) {
            return $explicit;
        }

        $config = config('shag_weight_inference', []);
        $tiers = $config['brand_tiers'] ?? [];

        foreach ($tiers as $tier) {
            if ($this->matchesShagTier($text, $tier)) {
                return (int) $tier['grams'];
            }
        }

        if ($this->matchesRollingTinPackaging($text)) {
            return (int) ($config['tin_default_grams'] ?? 100);
        }

        if ($this->matchesKizamiBoxPackaging($text)) {
            return 10;
        }

        return (int) ($config['default_grams'] ?? 25);
    }

    protected function matchesShagTier($text, array $tier)
    {
        if (!$this->matchesBrand($text, $tier['needles'] ?? [])) {
            return false;
        }

        if (!empty($tier['require']) && !preg_match($tier['require'], $text)) {
            return false;
        }

        if (!empty($tier['exclude']) && preg_match($tier['exclude'], $text)) {
            return false;
        }

        return true;
    }

    protected function inferStickPackWeight($text, $unitSticks)
    {
        $explicit = $this->parseExplicitGrams($text);
        if ($explicit !== null && !$this->looksLikeTarMg($text, $explicit)) {
            return $explicit;
        }

        $sticks = (int) $unitSticks;
        if ($sticks < 1) {
            if (preg_match('/(\d+)\s*支/ui', $text, $m)) {
                $sticks = (int) $m[1];
            } else {
                $sticks = 20;
            }
        }

        if (preg_match('/(罐装|缶入|铁盒|罐|听装)/ui', $text) && $sticks >= 40) {
            if (preg_match('/(\d+)\s*支/ui', $text, $m) && (int) $m[1] >= 40) {
                return 120;
            }

            return 100;
        }

        if (preg_match('/(细支|スリム|slim|100\'?s|长支)/ui', $text)) {
            return $this->weightBySticks($sticks, -3);
        }

        if (preg_match('/(小雪茄|シガー|cigarillo)/ui', $text)) {
            return max(15, (int) round($sticks * 1.2 + 8));
        }

        return $this->weightBySticks($sticks);
    }

    protected function weightBySticks($sticks, $adjust = 0)
    {
        $map = [
            10 => 13,
            14 => 18,
            20 => 26,
            50 => 120,
        ];

        if (isset($map[$sticks])) {
            return max(1, $map[$sticks] + $adjust);
        }

        if ($sticks <= 10) {
            return max(1, (int) round($sticks * 1.2 + 5) + $adjust);
        }

        if ($sticks <= 20) {
            return max(1, (int) round($sticks * 1.0 + 6) + $adjust);
        }

        return max(1, (int) round($sticks * 2.0 + 20) + $adjust);
    }

    protected function parseExplicitGrams($text)
    {
        if (preg_match('/(\d+)\s*g装/ui', $text, $m)) {
            return $this->sanitizeGramValue((int) $m[1], $text);
        }

        if (preg_match('/(\d+)\s*g(?:\s|装|\/|$)/ui', $text, $m)) {
            return $this->sanitizeGramValue((int) $m[1], $text);
        }

        return null;
    }

    protected function sanitizeGramValue($grams, $text)
    {
        if ($grams < 1 || $grams > 500) {
            return null;
        }

        if ($this->looksLikeTarMg($text, $grams)) {
            return null;
        }

        return $grams;
    }

    protected function looksLikeTarMg($text, $value)
    {
        if ($value > 50) {
            return false;
        }

        return (bool) preg_match('/\b'.preg_quote((string) $value, '/').'\s*mg/ui', $text);
    }

    protected function matchesRollingTinPackaging($text)
    {
        return (bool) preg_match('/(缶入|缶|罐装|铁罐|tin|can|筒装)/ui', $text);
    }

    protected function matchesKizamiBoxPackaging($text)
    {
        return (bool) preg_match('/(小粋|こいき|宝船|たからぶね|刻み|箱入|箱装)/ui', $text);
    }

    protected function matchesBrand($text, array $needles)
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && stripos($text, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}

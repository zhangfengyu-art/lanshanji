<?php

namespace App\Services;

class CategoryNameCleanupService
{
    /**
     * 去掉分类名中的 EMS直邮 文案（保留前台模板里的绿色徽章即可）。
     */
    public function stripEmsDirectMailLabel($name)
    {
        $name = trim((string) $name);
        if ($name === '') {
            return $name;
        }

        $name = $this->normalizeLatinWidth($name);

        for ($i = 0; $i < 8; $i++) {
            $next = $this->stripOneRound($name);
            if ($next === $name) {
                break;
            }
            $name = $next;
        }

        $name = trim(preg_replace('/\s{2,}/u', ' ', $name));

        return $name;
    }

    public function stillContainsEmsLabel($name)
    {
        $normalized = $this->normalizeLatinWidth(trim((string) $name));

        return (bool) preg_match('/EMS\s*直邮/ui', $normalized);
    }

    protected function stripOneRound($name)
    {
        $patterns = [
            '/\s*[（(【\[]\s*EMS\s*直邮\s*[）)】\]]\s*/iu',
            '/\s*[-—–]\s*EMS\s*直邮\s*/iu',
            '/\s+EMS\s*直邮\s*/iu',
            '/\s*EMS\s*直邮\s*$/iu',
            '/^EMS\s*直邮\s+/iu',
        ];

        foreach ($patterns as $pattern) {
            $name = preg_replace($pattern, ' ', $name);
        }

        return trim(preg_replace('/\s{2,}/u', ' ', $name));
    }

    protected function normalizeLatinWidth($name)
    {
        return preg_replace_callback('/[Ａ-Ｚａ-ｚ０-９]/u', function ($match) {
            return mb_convert_kana($match[0], 'a', 'UTF-8');
        }, $name);
    }
}

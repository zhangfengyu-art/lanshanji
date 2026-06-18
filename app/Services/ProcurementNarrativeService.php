<?php

namespace App\Services;

class ProcurementNarrativeService
{
    /** @var string[]|null */
    protected static $templatePool = null;

    /**
     * @return string[]
     */
    public function templates(): array
    {
        if (static::$templatePool === null) {
            static::$templatePool = $this->buildTemplatePool();
        }

        return static::$templatePool;
    }

    /**
     * @param  int[]  $excludeIndices
     * @return array{text: string, template_index: int}
     */
    public function build(string $itemName, float $amount, ?int $templateIndex = null, array $excludeIndices = []): array
    {
        $templates = $this->templates();
        if ($templateIndex === null) {
            $candidates = array_keys($templates);
            if ($excludeIndices !== []) {
                $filtered = array_values(array_diff($candidates, $excludeIndices));
                if ($filtered !== []) {
                    $candidates = $filtered;
                }
            }
            $templateIndex = (int) $candidates[array_rand($candidates)];
        }

        $formattedAmount = number_format(round($amount, 2), 2);
        $text = sprintf($templates[$templateIndex], $itemName, $formattedAmount);

        return [
            'text' => $text,
            'template_index' => $templateIndex,
        ];
    }

    /**
     * 组合生成 120+ 条不重复话术模板。
     *
     * @return string[]
     */
    protected function buildTemplatePool(): array
    {
        $openings = [
            '想求购', '想入手', '帮忙带', '急求', '有没有人出', '蹲一个', '求代购', '想收',
            '最近种草', '帮带', '想捡漏', '求带', '有没有姐妹出', '想补货', '求帮忙买',
        ];
        $budgetPhrases = [
            '预算%s', '预算大概%s', '预算在%s左右', '手头大约%s', '最多%s', '预算差不多%s',
            '预算就%s', '大概%s', '控制在%s以内', '预算%s上下',
        ];
        $tails = [
            '要正品', '求靠谱', '能面交优先', 'EMS直邮也行', '接受轻微盒损', '尽快',
            '有空帮忙看看', '谢谢', '可接受排队', '不急但想买到', '最好有收据', '包装完好优先',
            '同城可自取', '可接受拆盒', '希望本周能买到', '不急慢慢等', '可预付定金',
        ];

        $pool = [];
        foreach ($openings as $opening) {
            foreach ($budgetPhrases as $budget) {
                foreach ($tails as $tail) {
                    $pool[] = $opening.'%s，'.$budget.'日元，'.$tail.'。';
                }
            }
        }

        $extra = [
            '在日本的朋友帮带%s，预算%s日元，回国前能买到最好。',
            '看到%s很心动，预算%s左右，有代购愿意接吗？',
            '给家里人带%s，预算%s，希望包装别压坏。',
            '之前买过%s想再囤，预算%s上下，求带。',
            '限定款%s有人出吗？预算%s，可加价。',
            '学生党求带%s，预算%s，谢谢各位。',
            '出差顺路帮带%s，预算%s，面交或邮寄都行。',
            '纪念日礼物想送%s，预算%s，求推荐购买渠道。',
            '帮朋友问一下%s，预算%s左右，靠谱优先。',
            '断货好久了，想收%s，预算%s，不急。',
            '第一次找代购带%s，预算%s，请多指教。',
            '想对比几家价格，%s预算%s，有票最好。',
            '回国行李还有空位，想带%s，预算%s。',
            '之前被跑单了，重新求%s，预算%s，走平台。',
            '给娃买的%s，预算%s，要未开封。',
            '收藏向%s，预算%s，盒况要求高。',
            '自用不急，慢慢蹲%s，预算%s。',
            '帮同事带%s，预算%s，月底前要。',
            '旅游伴手礼想选%s，预算%s，求建议。',
            '上次代购体验不错，继续求%s，预算%s。',
        ];

        foreach ($extra as $line) {
            $pool[] = $line;
        }

        return array_values(array_unique($pool));
    }
}

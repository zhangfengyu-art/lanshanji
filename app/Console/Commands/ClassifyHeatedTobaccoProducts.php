<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Services\HeatedTobaccoClassificationService;
use App\Services\OrderTobaccoLimitService;
use Illuminate\Console\Command;

class ClassifyHeatedTobaccoProducts extends Command
{
    protected $signature = 'products:classify-heated-tobacco
                            {--dry-run : 仅预览，不写入数据库}
                            {--only-unset : 仅处理未设置烟草分类的商品（默认）}
                            {--include-cigarette : 将匹配规则但标为「香烟」的旧品也改为「加热烟」}
                            {--fill-sticks : 未填支数时写入 config 默认支数}';

    protected $description = '按分类名/商品标题将加热烟商品标为 heated_tobacco，并可选补全每包支数';

    public function handle(HeatedTobaccoClassificationService $classifier)
    {
        $dryRun = (bool) $this->option('dry-run');
        $includeCigarette = (bool) $this->option('include-cigarette');
        $onlyUnset = $includeCigarette ? false : true;
        $fillSticks = (bool) $this->option('fill-sticks');

        $products = $classifier->scanProducts($onlyUnset, $includeCigarette);

        if ($products->isEmpty()) {
            $this->info('没有需要归类的商品。');
            $this->line('提示：旧品保持「香烟」可不传 --include-cigarette；新品请放在名称含「加热烟」等分类下，或在后台手动选「加热烟」。');

            return 0;
        }

        $this->table(
            ['ID', '当前分类', '标题', '判定理由'],
            $products->map(function (Product $product) use ($classifier) {
                $info = $classifier->classifyProduct($product);

                return [
                    $product->id,
                    $product->tobacco_type ?: '（未设置）',
                    mb_substr($product->title, 0, 40),
                    $info['reason'],
                ];
            })->all()
        );

        if ($dryRun) {
            $this->warn('以上为预览（--dry-run），未写入数据库。去掉 --dry-run 后执行写入。');

            return 0;
        }

        $updated = 0;
        foreach ($products as $product) {
            if ($classifier->applyToProduct($product, $fillSticks)) {
                $updated++;
            }
        }

        $this->info('已更新 '.$updated.' 个商品为「加热烟」。');
        $this->line('未匹配的旧品若仍标「香烟」可继续使用，与加热烟合计仍受 400 支限制。');

        return 0;
    }
}

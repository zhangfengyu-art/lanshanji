<?php

namespace App\Services\Admin;

use App\Exceptions\InvalidRequestException;
use App\Models\Category;
use App\Services\ShippingModeService;

class CategoryBatchService
{
    protected function categoriesByIds(array $ids)
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (count($ids) === 0) {
            throw new InvalidRequestException('请先勾选分类');
        }

        return Category::query()->whereIn('id', $ids)->get();
    }

    public function batchSetShippingMode(array $ids, $mode)
    {
        $options = ShippingModeService::options();
        if (!array_key_exists($mode, $options)) {
            throw new InvalidRequestException('无效的寄送模式');
        }

        $count = Category::query()->whereIn('id', array_map('intval', $ids))->update([
            'default_shipping_mode' => $mode,
        ]);

        return ['updated' => $count, 'message' => '已设置默认寄送模式为「'.$options[$mode].'」（'.$count.' 个分类）'];
    }

    public function batchSetDirectory(array $ids, $isDirectory)
    {
        $count = Category::query()->whereIn('id', array_map('intval', $ids))->update([
            'is_directory' => $isDirectory ? 1 : 0,
        ]);
        $label = $isDirectory ? '目录' : '非目录';

        return ['updated' => $count, 'message' => '已设为'.$label.'（'.$count.' 个分类）'];
    }

    public function batchMoveParent(array $ids, $parentId)
    {
        $parentId = (int) $parentId;
        if ($parentId === 0) {
            $parentId = null;
        } elseif (!Category::query()->where('id', $parentId)->exists()) {
            throw new InvalidRequestException('目标父分类不存在');
        }

        $updated = 0;
        foreach ($this->categoriesByIds($ids) as $category) {
            if ($parentId !== null) {
                if ((int) $parentId === (int) $category->id) {
                    throw new InvalidRequestException('分类「'.$category->name.'」不能设为自己的子分类');
                }
                $parent = Category::query()->find($parentId);
                if ($parent) {
                    $descendants = $category->descendantIds();
                    if (in_array((int) $parentId, $descendants, true)) {
                        throw new InvalidRequestException('不能将分类「'.$category->name.'」移动到其子分类下');
                    }
                }
            }

            $category->update(['parent_id' => $parentId]);
            $updated++;
        }

        $label = $parentId ? '指定父分类' : '根分类';

        return ['updated' => $updated, 'message' => '已移动 '.$updated.' 个分类到'.$label];
    }
}

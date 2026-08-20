<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\ChecklistItem;

class ChecklistProgressService
{
    public function recalculate(ChecklistItem $item): ChecklistItem
    {
        $item->load(['requirements', 'activeOverride']);
        $approvedStatuses = config('production.approved_asset_statuses');
        $requiredTotal = 0;
        $progressParts = [];
        $missing = [];

        foreach ($item->requirements as $requirement) {
            $base = $item->assets()
                ->where('assets.type', $requirement->asset_type)
                ->when($requirement->asset_category_id, fn ($query) => $query->where('assets.category_id', $requirement->asset_category_id));

            $uploaded = (clone $base)->whereNotIn('assets.status', ['Отклонено', 'Требуется переснять'])->count();
            $rejected = (clone $base)->whereIn('assets.status', ['Отклонено', 'Требуется переснять'])->count();
            $approved = (clone $base)->whereIn('assets.status', $approvedStatuses)->count();
            $counted = $requirement->approved_only ? $approved : $uploaded;

            $requirement->update([
                'current_count' => $counted,
                'uploaded_count' => $uploaded,
                'rejected_count' => $rejected,
            ]);

            if ($requirement->is_required) {
                $requiredTotal++;
                $progressParts[] = $requirement->minimum_count === 0
                    ? 100
                    : min(100, (int) floor(($counted / $requirement->minimum_count) * 100));

                if ($counted < $requirement->minimum_count) {
                    $missing[] = sprintf('%s: не хватает %d', $requirement->label, $requirement->minimum_count - $counted);
                }
            }
        }

        $requirementsMet = $requiredTotal === 0 || $missing === [];
        $progress = $requiredTotal === 0 ? ($item->completion_method === 'Вручную' ? 0 : 100) : (int) round(array_sum($progressParts) / $requiredTotal);
        $override = $item->activeOverride;

        if ($requirementsMet) {
            if ($override) {
                $override->update(['is_active' => false]);
            }
            $attributes = [
                'status' => 'Выполнено',
                'progress' => 100,
                'completion_mode' => $override ? 'Вручную' : 'Автоматически',
                'has_warning' => false,
                'warning_text' => null,
            ];
        } elseif ($override) {
            $warning = implode('; ', $missing);
            $override->update(['missing_summary' => $warning]);
            $attributes = [
                'status' => 'Выполнено',
                'progress' => $progress,
                'completion_mode' => 'Вручную с предупреждением',
                'has_warning' => true,
                'warning_text' => $warning,
            ];
        } else {
            $attributes = [
                'status' => 'Требуются материалы',
                'progress' => $progress,
                'completion_mode' => null,
                'has_warning' => false,
                'warning_text' => null,
            ];
        }

        $item->update($attributes);

        return $item->fresh(['requirements', 'activeOverride']);
    }

    public function recalculateForAsset(Asset $asset): void
    {
        $asset->checklistItems()->get()->each(fn (ChecklistItem $item) => $this->recalculate($item));
    }
}

<?php

namespace App\Services;

use App\Exceptions\OpenAiException;
use App\Models\AiRequest;
use App\Models\AiUsageRecord;
use App\Models\User;

class OpenAiBudgetService
{
    public function assertCanRequest(User $user, float $estimatedCost = 0, ?int $ignoreRequestId = null): void
    {
        $dailyLimit = config('openai.user_daily_limit');
        $dailyQuery = AiRequest::where('user_id', $user->id)->whereDate('created_at', today())->when($ignoreRequestId, fn ($q) => $q->whereKeyNot($ignoreRequestId));
        if ($dailyLimit > 0 && $dailyQuery->count() >= $dailyLimit) {
            throw new OpenAiException('Достигнут дневной лимит запросов пользователя. Попробуйте завтра.');
        }
        $monthlyBudget = config('openai.monthly_budget');
        $monthlySpend = (float) AiUsageRecord::whereBetween('usage_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->sum('cost');
        if ($monthlyBudget > 0 && $monthlySpend + $estimatedCost > $monthlyBudget) {
            throw new OpenAiException('Месячный бюджет OpenAI исчерпан. Новые запросы остановлены.');
        }
    }
}

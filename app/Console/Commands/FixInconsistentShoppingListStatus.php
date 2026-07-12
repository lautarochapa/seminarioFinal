<?php

namespace App\Console\Commands;

use App\AuditLog;
use App\ShoppingList;
use Illuminate\Console\Command;

class FixInconsistentShoppingListStatus extends Command
{
    protected $signature = 'shopping-lists:fix-inconsistent-status {--dry-run : Only report the lists that would be fixed, without changing anything}';

    protected $description = 'Reverts shopping lists stuck as "completed" that still have pending items back to "active", so users can resume the purchase.';

    public function handle()
    {
        $dryRun = (bool) $this->option('dry-run');

        $lists = ShoppingList::where('status', ShoppingList::STATUS_COMPLETED)
            ->whereNull('deleted_at')
            ->whereHas('items', function ($query) {
                $query->where('status', 'pending');
            })
            ->get();

        if ($lists->isEmpty()) {
            $this->info('No inconsistent shopping lists found.');

            return 0;
        }

        $this->info(($dryRun ? '[dry-run] ' : '').'Found '.$lists->count().' shopping list(s) marked as completed with pending items:');

        foreach ($lists as $list) {
            $this->line(' - #'.$list->id.' (family_group_id='.$list->family_group_id.', source_type='.$list->source_type.')');

            if ($dryRun) {
                continue;
            }

            $old = ['status' => $list->status];
            $list->status = ShoppingList::STATUS_ACTIVE;
            $list->save();

            AuditLog::create([
                'user_id' => null,
                'action' => 'shopping_list.status_fixed',
                'entity_name' => 'shopping_lists',
                'entity_id' => (string) $list->id,
                'old_values' => $old,
                'new_values' => ['status' => $list->status],
                'ip_address' => '127.0.0.1',
                'user_agent' => 'artisan shopping-lists:fix-inconsistent-status',
            ]);
        }

        if (!$dryRun) {
            $this->info('Fixed '.$lists->count().' shopping list(s).');
        }

        return 0;
    }
}

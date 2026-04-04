<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Policies;

use App\Models\User;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Enums\EntryStatus;
use Illuminate\Auth\Access\HandlesAuthorization;

class JournalEntryPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('journal_entry.view');
    }

    public function view(User $user, JournalEntry $entry): bool
    {
        return $user->can('journal_entry.view');
    }

    public function create(User $user): bool
    {
        return $user->can('journal_entry.create');
    }

    /**
     * التعديل مسموح فقط إذا كان القيد "مسودة"
     */
    public function update(User $user, JournalEntry $entry): bool
    {
        return $user->can('journal_entry.update') && $entry->status === EntryStatus::Draft;
    }

    /**
     * الحذف مسموح فقط إذا كان القيد "مسودة"
     */
    public function delete(User $user, JournalEntry $entry): bool
    {
        return $user->can('journal_entry.delete') && $entry->status === EntryStatus::Draft;
    }

    /**
     * صلاحية خاصة للترحيل (Posting)
     */
    public function post(User $user, JournalEntry $entry): bool
    {
        // يجب أن يملك صلاحية الترحيل + القيد يجب أن يكون مسودة حالياً
        return $user->can('journal_entry.post') && $entry->status === EntryStatus::Draft;
    }
}

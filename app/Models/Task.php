<?php

namespace App\Models;

use App\Enums\TaskApprovalStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $product_id
 * @property string $title
 * @property string|null $description
 * @property TaskStatus $status
 * @property TaskPriority $priority
 * @property int|null $assignee_user_id
 * @property Carbon|null $due_at
 * @property int|null $created_by
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property TaskApprovalStatus $approval_status
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property string|null $approval_comment
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id',
    'product_id',
    'title',
    'description',
    'status',
    'priority',
    'assignee_user_id',
    'due_at',
    'created_by',
    'subject_type',
    'subject_id',
    'approval_status',
    'approved_by',
    'approved_at',
    'approval_comment',
])]
class Task extends Model
{
    protected $table = 'tasks';

    protected function casts(): array
    {
        return [
            'status' => TaskStatus::class,
            'priority' => TaskPriority::class,
            'approval_status' => TaskApprovalStatus::class,
            'due_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}

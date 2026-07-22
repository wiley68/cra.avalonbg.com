<?php

namespace App\Models;

use App\Enums\UserSecurityInstructionSectionKey;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Section within a user security instructions document.
 *
 * @property int $id
 * @property int $instruction_id
 * @property UserSecurityInstructionSectionKey $section_key
 * @property string|null $title_override
 * @property string $body
 * @property int $sort_order
 * @property bool $is_applicable
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read UserSecurityInstruction|null $instruction
 */
#[Fillable([
    'instruction_id',
    'section_key',
    'title_override',
    'body',
    'sort_order',
    'is_applicable',
])]
class UserSecurityInstructionSection extends Model
{
    protected function casts(): array
    {
        return [
            'section_key' => UserSecurityInstructionSectionKey::class,
            'is_applicable' => 'boolean',
        ];
    }

    /** @return BelongsTo<UserSecurityInstruction, $this> */
    public function instruction(): BelongsTo
    {
        return $this->belongsTo(UserSecurityInstruction::class, 'instruction_id');
    }
}
